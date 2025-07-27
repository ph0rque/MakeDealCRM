<?php
/**
 * Service class for managing Deal-Checklist relationships
 * Provides secure API methods for checklist operations
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class ChecklistRelationshipService
{
    private $db;
    private $log;

    public function __construct()
    {
        $this->db = $GLOBALS['db'];
        $this->log = $GLOBALS['log'];
    }

    /**
     * Apply a checklist template to a deal
     * 
     * @param string $dealId The deal ID
     * @param string $templateId The checklist template ID
     * @param array $options Additional options (due_date, assigned_user_id, etc.)
     * @return array Result with success status and template instance ID
     */
    public function applyChecklistTemplate($dealId, $templateId, $options = array())
    {
        try {
            // Validate inputs
            if (!$this->validateDealAccess($dealId)) {
                throw new Exception("Access denied to deal: {$dealId}");
            }

            if (!$this->validateTemplateExists($templateId)) {
                throw new Exception("Checklist template not found: {$templateId}");
            }

            // Check if template is already applied
            if ($this->isTemplateApplied($dealId, $templateId)) {
                throw new Exception("Template already applied to this deal");
            }

            $this->db->startTransaction();

            // Create the template instance
            $instanceId = create_guid();
            $appliedDate = TimeDate::getInstance()->nowDb();
            $dueDate = isset($options['due_date']) ? $options['due_date'] : null;
            $assignedUserId = isset($options['assigned_user_id']) ? $options['assigned_user_id'] : $GLOBALS['current_user']->id;

            $sql = "INSERT INTO deals_checklist_templates 
                    (id, deal_id, template_id, applied_date, due_date, assigned_user_id, created_by, date_entered, date_modified) 
                    VALUES ('{$instanceId}', '{$dealId}', '{$templateId}', '{$appliedDate}', " . 
                    ($dueDate ? "'{$dueDate}'" : "NULL") . ", '{$assignedUserId}', '{$GLOBALS['current_user']->id}', '{$appliedDate}', '{$appliedDate}')";

            $this->db->query($sql);

            // Create individual checklist items for this deal
            $this->createChecklistItems($dealId, $templateId, $instanceId, $options);

            $this->db->commit();

            $this->log->info("ChecklistRelationshipService: Applied template {$templateId} to deal {$dealId}");

            return array(
                'success' => true,
                'instance_id' => $instanceId,
                'message' => 'Checklist template applied successfully'
            );

        } catch (Exception $e) {
            $this->db->rollback();
            $this->log->error("ChecklistRelationshipService::applyChecklistTemplate - Error: " . $e->getMessage());
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Update checklist item completion status
     * 
     * @param string $dealId The deal ID
     * @param string $itemId The checklist item ID
     * @param string $status New completion status
     * @param array $options Additional data (notes, actual_hours, etc.)
     * @return array Result with success status
     */
    public function updateChecklistItemStatus($dealId, $itemId, $status, $options = array())
    {
        try {
            if (!$this->validateDealAccess($dealId)) {
                throw new Exception("Access denied to deal: {$dealId}");
            }

            $validStatuses = array('pending', 'in_progress', 'completed', 'not_applicable', 'blocked');
            if (!in_array($status, $validStatuses)) {
                throw new Exception("Invalid status: {$status}");
            }

            $this->db->startTransaction();

            $updateFields = array();
            $updateFields[] = "completion_status = '{$status}'";
            $updateFields[] = "date_modified = '" . TimeDate::getInstance()->nowDb() . "'";
            $updateFields[] = "modified_user_id = '{$GLOBALS['current_user']->id}'";

            if ($status === 'completed') {
                $updateFields[] = "completion_date = '" . TimeDate::getInstance()->nowDb() . "'";
            }

            if (isset($options['notes'])) {
                $notes = $this->db->quote($options['notes']);
                $updateFields[] = "notes = {$notes}";
            }

            if (isset($options['actual_hours'])) {
                $actualHours = (float)$options['actual_hours'];
                $updateFields[] = "actual_hours = {$actualHours}";
            }

            if (isset($options['document_received'])) {
                $docReceived = $options['document_received'] ? 1 : 0;
                $updateFields[] = "document_received = {$docReceived}";
            }

            $sql = "UPDATE deals_checklist_items SET " . implode(', ', $updateFields) . 
                   " WHERE deal_id = '{$dealId}' AND item_id = '{$itemId}' AND deleted = 0";

            $this->db->query($sql);

            // Update template instance completion percentage
            $this->updateTemplateCompletion($dealId);

            $this->db->commit();

            $this->log->info("ChecklistRelationshipService: Updated item {$itemId} status to {$status} for deal {$dealId}");

            return array(
                'success' => true,
                'message' => 'Checklist item updated successfully'
            );

        } catch (Exception $e) {
            $this->db->rollback();
            $this->log->error("ChecklistRelationshipService::updateChecklistItemStatus - Error: " . $e->getMessage());
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Get checklist progress for a deal
     * 
     * @param string $dealId The deal ID
     * @return array Checklist progress data
     */
    public function getChecklistProgress($dealId)
    {
        try {
            if (!$this->validateDealAccess($dealId)) {
                throw new Exception("Access denied to deal: {$dealId}");
            }

            // Get template instances
            $templatesQuery = "
                SELECT 
                    dct.id as instance_id,
                    dct.template_id,
                    ct.name as template_name,
                    ct.category,
                    dct.applied_date,
                    dct.due_date,
                    dct.completion_percentage,
                    dct.status
                FROM deals_checklist_templates dct
                INNER JOIN checklist_templates ct ON dct.template_id = ct.id
                WHERE dct.deal_id = '{$dealId}' AND dct.deleted = 0
                ORDER BY dct.applied_date
            ";

            $templatesResult = $this->db->query($templatesQuery);
            $templates = array();

            while ($template = $this->db->fetchByAssoc($templatesResult)) {
                // Get items for this template instance
                $itemsQuery = "
                    SELECT 
                        dci.item_id,
                        ci.name as item_name,
                        ci.description,
                        ci.sort_order,
                        ci.is_required,
                        ci.requires_document,
                        dci.completion_status,
                        dci.completion_date,
                        dci.due_date,
                        dci.priority,
                        dci.notes,
                        dci.document_requested,
                        dci.document_received,
                        dci.estimated_hours,
                        dci.actual_hours
                    FROM deals_checklist_items dci
                    INNER JOIN checklist_items ci ON dci.item_id = ci.id
                    WHERE dci.deal_id = '{$dealId}' 
                    AND dci.template_instance_id = '{$template['instance_id']}'
                    AND dci.deleted = 0
                    ORDER BY ci.sort_order
                ";

                $itemsResult = $this->db->query($itemsQuery);
                $items = array();

                while ($item = $this->db->fetchByAssoc($itemsResult)) {
                    $items[] = $item;
                }

                $template['items'] = $items;
                $templates[] = $template;
            }

            // Calculate overall progress
            $overallProgress = $this->calculateOverallProgress($dealId);

            return array(
                'success' => true,
                'deal_id' => $dealId,
                'overall_completion' => $overallProgress,
                'templates' => $templates
            );

        } catch (Exception $e) {
            $this->log->error("ChecklistRelationshipService::getChecklistProgress - Error: " . $e->getMessage());
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Remove a checklist template from a deal
     * 
     * @param string $dealId The deal ID
     * @param string $templateId The template ID
     * @return array Result with success status
     */
    public function removeChecklistTemplate($dealId, $templateId)
    {
        try {
            if (!$this->validateDealAccess($dealId)) {
                throw new Exception("Access denied to deal: {$dealId}");
            }

            $this->db->startTransaction();

            // Soft delete the template instance
            $sql = "UPDATE deals_checklist_templates SET deleted = 1, date_modified = '" . 
                   TimeDate::getInstance()->nowDb() . "', modified_user_id = '{$GLOBALS['current_user']->id}' 
                   WHERE deal_id = '{$dealId}' AND template_id = '{$templateId}'";

            $this->db->query($sql);

            // Soft delete related checklist items
            $sql = "UPDATE deals_checklist_items SET deleted = 1, date_modified = '" . 
                   TimeDate::getInstance()->nowDb() . "', modified_user_id = '{$GLOBALS['current_user']->id}' 
                   WHERE deal_id = '{$dealId}' AND template_instance_id IN (
                       SELECT id FROM deals_checklist_templates 
                       WHERE deal_id = '{$dealId}' AND template_id = '{$templateId}' AND deleted = 1
                   )";

            $this->db->query($sql);

            $this->db->commit();

            $this->log->info("ChecklistRelationshipService: Removed template {$templateId} from deal {$dealId}");

            return array(
                'success' => true,
                'message' => 'Checklist template removed successfully'
            );

        } catch (Exception $e) {
            $this->db->rollback();
            $this->log->error("ChecklistRelationshipService::removeChecklistTemplate - Error: " . $e->getMessage());
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Private helper methods
     */

    private function validateDealAccess($dealId)
    {
        // Load the deal and check ACL
        $deal = BeanFactory::getBean('Deals', $dealId);
        return $deal && $deal->ACLAccess('view');
    }

    private function validateTemplateExists($templateId)
    {
        $sql = "SELECT id FROM checklist_templates WHERE id = '{$templateId}' AND deleted = 0 AND is_active = 1";
        $result = $this->db->query($sql);
        return $this->db->getRowCount($result) > 0;
    }

    private function isTemplateApplied($dealId, $templateId)
    {
        $sql = "SELECT id FROM deals_checklist_templates 
                WHERE deal_id = '{$dealId}' AND template_id = '{$templateId}' AND deleted = 0";
        $result = $this->db->query($sql);
        return $this->db->getRowCount($result) > 0;
    }

    private function createChecklistItems($dealId, $templateId, $instanceId, $options)
    {
        // Get template items
        $sql = "SELECT * FROM checklist_items WHERE template_id = '{$templateId}' AND deleted = 0 ORDER BY sort_order";
        $result = $this->db->query($sql);

        $assignedUserId = isset($options['assigned_user_id']) ? $options['assigned_user_id'] : $GLOBALS['current_user']->id;
        $baseDueDate = isset($options['due_date']) ? strtotime($options['due_date']) : time();

        while ($item = $this->db->fetchByAssoc($result)) {
            $itemInstanceId = create_guid();
            $dueDate = date('Y-m-d', $baseDueDate + ($item['sort_order'] * 86400)); // Stagger due dates

            $insertSql = "INSERT INTO deals_checklist_items 
                         (id, deal_id, item_id, template_instance_id, due_date, assigned_user_id, 
                          estimated_hours, document_requested, created_by, date_entered, date_modified) 
                         VALUES ('{$itemInstanceId}', '{$dealId}', '{$item['id']}', '{$instanceId}', 
                                '{$dueDate}', '{$assignedUserId}', '{$item['estimated_hours']}', 
                                '{$item['requires_document']}', '{$GLOBALS['current_user']->id}', 
                                '" . TimeDate::getInstance()->nowDb() . "', '" . TimeDate::getInstance()->nowDb() . "')";

            $this->db->query($insertSql);
        }
    }

    private function updateTemplateCompletion($dealId)
    {
        // Calculate completion for each template instance
        $sql = "UPDATE deals_checklist_templates dct SET 
                completion_percentage = (
                    SELECT ROUND(
                        (SUM(CASE WHEN dci.completion_status = 'completed' THEN 1 ELSE 0 END) * 100.0) / COUNT(*), 2
                    )
                    FROM deals_checklist_items dci 
                    WHERE dci.template_instance_id = dct.id AND dci.deleted = 0
                )
                WHERE dct.deal_id = '{$dealId}' AND dct.deleted = 0";

        $this->db->query($sql);
    }

    private function calculateOverallProgress($dealId)
    {
        $sql = "SELECT AVG(completion_percentage) as overall_completion 
                FROM deals_checklist_templates 
                WHERE deal_id = '{$dealId}' AND deleted = 0 AND status = 'active'";

        $result = $this->db->query($sql);
        $row = $this->db->fetchByAssoc($result);

        return $row ? round((float)$row['overall_completion'], 2) : 0.0;
    }
}