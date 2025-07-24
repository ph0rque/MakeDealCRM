<?php
/**
 * Logic Hook Class for Deal-Checklist relationship management
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class ChecklistLogicHook
{
    /**
     * Update checklist completion percentage when deal is saved
     * 
     * @param SugarBean $bean The Deal bean
     * @param string $event The event (after_save)
     * @param array $arguments Additional arguments
     */
    public function updateChecklistCompletion($bean, $event, $arguments)
    {
        if (!$bean instanceof SugarBean || $bean->module_dir !== 'Deals') {
            return;
        }

        try {
            // Calculate completion percentage from related checklist items
            $completion = $this->calculateChecklistCompletion($bean->id);
            
            // Update the deal's checklist completion field
            if ($completion !== null) {
                $GLOBALS['db']->query(
                    "UPDATE opportunities SET checklist_completion_c = {$completion} WHERE id = '{$bean->id}'"
                );
            }

            // Update active checklists count
            $activeCount = $this->getActiveChecklistsCount($bean->id);
            $GLOBALS['db']->query(
                "UPDATE opportunities SET active_checklists_count_c = {$activeCount} WHERE id = '{$bean->id}'"
            );

            // Update overdue items count
            $overdueCount = $this->getOverdueChecklistItemsCount($bean->id);
            $GLOBALS['db']->query(
                "UPDATE opportunities SET overdue_checklist_items_c = {$overdueCount} WHERE id = '{$bean->id}'"
            );

        } catch (Exception $e) {
            $GLOBALS['log']->error("ChecklistLogicHook::updateChecklistCompletion - Error: " . $e->getMessage());
        }
    }

    /**
     * Cascade delete checklists when deal is deleted
     * 
     * @param SugarBean $bean The Deal bean
     * @param string $event The event (before_delete)
     * @param array $arguments Additional arguments
     */
    public function cascadeDeleteChecklists($bean, $event, $arguments)
    {
        if (!$bean instanceof SugarBean || $bean->module_dir !== 'Deals') {
            return;
        }

        try {
            $dealId = $bean->id;
            
            // Mark checklist templates relationship as deleted
            $GLOBALS['db']->query(
                "UPDATE deals_checklist_templates SET deleted = 1 WHERE deal_id = '{$dealId}'"
            );

            // Mark checklist items relationship as deleted
            $GLOBALS['db']->query(
                "UPDATE deals_checklist_items SET deleted = 1 WHERE deal_id = '{$dealId}'"
            );

            $GLOBALS['log']->info("ChecklistLogicHook::cascadeDeleteChecklists - Cascaded delete for deal: {$dealId}");

        } catch (Exception $e) {
            $GLOBALS['log']->error("ChecklistLogicHook::cascadeDeleteChecklists - Error: " . $e->getMessage());
        }
    }

    /**
     * Validate checklist relationship before adding
     * 
     * @param SugarBean $bean The Deal bean
     * @param string $event The event (before_relationship_add)
     * @param array $arguments Additional arguments
     */
    public function validateChecklistRelationship($bean, $event, $arguments)
    {
        if (!$bean instanceof SugarBean || $bean->module_dir !== 'Deals') {
            return;
        }

        try {
            // Check user permissions for checklist operations
            if (!$this->hasChecklistPermission($bean)) {
                throw new Exception("User does not have permission to modify checklists for this deal");
            }

            // Validate checklist template/item exists and is active
            if (isset($arguments['related_id']) && isset($arguments['link'])) {
                if (!$this->validateRelatedRecord($arguments['related_id'], $arguments['link'])) {
                    throw new Exception("Related checklist record is not valid or not active");
                }
            }

        } catch (Exception $e) {
            $GLOBALS['log']->error("ChecklistLogicHook::validateChecklistRelationship - Error: " . $e->getMessage());
            // In a real implementation, you might want to prevent the relationship addition
            return false;
        }
    }

    /**
     * Update pipeline stage based on checklist completion
     * 
     * @param SugarBean $bean The Deal bean
     * @param string $event The event (after_relationship_add/delete)
     * @param array $arguments Additional arguments
     */
    public function updatePipelineStage($bean, $event, $arguments)
    {
        if (!$bean instanceof SugarBean || $bean->module_dir !== 'Deals') {
            return;
        }

        try {
            $completion = $this->calculateChecklistCompletion($bean->id);
            
            // Auto-advance pipeline stage based on completion
            if ($completion >= 100.0) {
                $this->advancePipelineStage($bean);
            } elseif ($completion >= 75.0 && $bean->pipeline_stage_c === 'due_diligence') {
                // Move to valuation stage when 75% of DD is complete
                $bean->pipeline_stage_c = 'valuation';
                $bean->stage_entered_date_c = TimeDate::getInstance()->nowDb();
                $bean->save();
            }

        } catch (Exception $e) {
            $GLOBALS['log']->error("ChecklistLogicHook::updatePipelineStage - Error: " . $e->getMessage());
        }
    }

    /**
     * Calculate checklist completion percentage for a deal
     * 
     * @param string $dealId The deal ID
     * @return float|null The completion percentage or null if no checklists
     */
    private function calculateChecklistCompletion($dealId)
    {
        $sql = "
            SELECT 
                COUNT(*) as total_items,
                SUM(CASE WHEN dci.completion_status = 'completed' THEN 1 ELSE 0 END) as completed_items
            FROM deals_checklist_items dci
            INNER JOIN checklist_items ci ON dci.item_id = ci.id
            WHERE dci.deal_id = '{$dealId}' 
            AND dci.deleted = 0 
            AND ci.deleted = 0
        ";
        
        $result = $GLOBALS['db']->query($sql);
        $row = $GLOBALS['db']->fetchByAssoc($result);
        
        if ($row && $row['total_items'] > 0) {
            return ($row['completed_items'] / $row['total_items']) * 100;
        }
        
        return 0.0;
    }

    /**
     * Get count of active checklists for a deal
     * 
     * @param string $dealId The deal ID
     * @return int The count of active checklists
     */
    private function getActiveChecklistsCount($dealId)
    {
        $sql = "
            SELECT COUNT(*) as active_count
            FROM deals_checklist_templates dct
            INNER JOIN checklist_templates ct ON dct.template_id = ct.id
            WHERE dct.deal_id = '{$dealId}' 
            AND dct.deleted = 0 
            AND ct.deleted = 0
            AND dct.status = 'active'
        ";
        
        $result = $GLOBALS['db']->query($sql);
        $row = $GLOBALS['db']->fetchByAssoc($result);
        
        return $row ? (int)$row['active_count'] : 0;
    }

    /**
     * Get count of overdue checklist items for a deal
     * 
     * @param string $dealId The deal ID
     * @return int The count of overdue items
     */
    private function getOverdueChecklistItemsCount($dealId)
    {
        $today = date('Y-m-d');
        $sql = "
            SELECT COUNT(*) as overdue_count
            FROM deals_checklist_items dci
            INNER JOIN checklist_items ci ON dci.item_id = ci.id
            WHERE dci.deal_id = '{$dealId}' 
            AND dci.deleted = 0 
            AND ci.deleted = 0
            AND dci.completion_status != 'completed'
            AND dci.due_date < '{$today}'
        ";
        
        $result = $GLOBALS['db']->query($sql);
        $row = $GLOBALS['db']->fetchByAssoc($result);
        
        return $row ? (int)$row['overdue_count'] : 0;
    }

    /**
     * Check if user has permission to modify checklists for this deal
     * 
     * @param SugarBean $bean The Deal bean
     * @return bool True if user has permission
     */
    private function hasChecklistPermission($bean)
    {
        global $current_user;
        
        // Check if user can edit the deal
        if (!$bean->ACLAccess('edit')) {
            return false;
        }

        // Check if user is assigned to the deal or is admin
        if ($current_user->is_admin || $bean->assigned_user_id === $current_user->id) {
            return true;
        }

        // Additional business logic for team-based permissions could go here
        return false;
    }

    /**
     * Validate that the related record exists and is active
     * 
     * @param string $relatedId The related record ID
     * @param string $linkName The relationship link name
     * @return bool True if valid
     */
    private function validateRelatedRecord($relatedId, $linkName)
    {
        if ($linkName === 'checklist_templates') {
            $sql = "SELECT id FROM checklist_templates WHERE id = '{$relatedId}' AND deleted = 0 AND is_active = 1";
        } elseif ($linkName === 'checklist_items') {
            $sql = "SELECT id FROM checklist_items WHERE id = '{$relatedId}' AND deleted = 0";
        } else {
            return false;
        }
        
        $result = $GLOBALS['db']->query($sql);
        return $GLOBALS['db']->getRowCount($result) > 0;
    }

    /**
     * Advance pipeline stage based on business rules
     * 
     * @param SugarBean $bean The Deal bean
     */
    private function advancePipelineStage($bean)
    {
        $stageProgression = [
            'due_diligence' => 'valuation',
            'valuation' => 'loi_negotiation',
            'loi_negotiation' => 'financing'
        ];

        if (isset($stageProgression[$bean->pipeline_stage_c])) {
            $bean->pipeline_stage_c = $stageProgression[$bean->pipeline_stage_c];
            $bean->stage_entered_date_c = TimeDate::getInstance()->nowDb();
            $bean->save();
            
            $GLOBALS['log']->info("ChecklistLogicHook: Advanced deal {$bean->id} to stage {$bean->pipeline_stage_c}");
        }
    }
}