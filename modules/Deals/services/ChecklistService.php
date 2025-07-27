<?php
/**
 * Centralized Checklist Service for Deal Checklists
 * 
 * This service provides a unified interface for all checklist-related operations
 * including creation, updates, deletions, template management, and reporting.
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 * @author SuiteCRM Architecture Team
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('data/BeanFactory.php');
require_once('include/TimeDate.php');

class ChecklistService
{
    private $db;
    private $currentUser;
    private $timeDate;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        global $db, $current_user;
        $this->db = $db;
        $this->currentUser = $current_user;
        $this->timeDate = TimeDate::getInstance();
    }
    
    /**
     * Create a new checklist from a template
     * 
     * @param string $dealId The deal ID
     * @param string $templateId The template ID
     * @param array $options Additional options
     * @return array Result with success status and checklist data or error message
     */
    public function createChecklistFromTemplate($dealId, $templateId, $options = array())
    {
        try {
            // Validate inputs
            if (empty($dealId) || empty($templateId)) {
                throw new Exception('Deal ID and Template ID are required');
            }
            
            // Load and validate the deal
            $deal = BeanFactory::getBean('Opportunities', $dealId);
            if (!$deal || $deal->deleted) {
                throw new Exception('Deal not found or deleted');
            }
            
            // Check permissions
            if (!$deal->ACLAccess('edit')) {
                throw new Exception('You do not have permission to modify this deal');
            }
            
            // Load and validate the template
            $template = BeanFactory::getBean('ChecklistTemplates', $templateId);
            if (!$template || $template->deleted || !$template->is_active) {
                throw new Exception('Template not found, deleted, or inactive');
            }
            
            // Check template permissions
            if (!$template->is_public && $template->created_by != $this->currentUser->id) {
                throw new Exception('You do not have permission to use this template');
            }
            
            // Create the checklist
            $checklist = BeanFactory::newBean('DealChecklists');
            $checklist->name = $template->name . ' - ' . $deal->name;
            $checklist->deal_id = $dealId;
            $checklist->template_id = $templateId;
            $checklist->assigned_user_id = $options['assigned_user_id'] ?? $this->currentUser->id;
            $checklist->status = 'not_started';
            $checklist->progress = 0;
            $checklist->date_started = $this->timeDate->nowDb();
            $checklist->save();
            
            // Create checklist items from template
            $items = $this->createChecklistItems($checklist->id, $templateId, $options);
            
            // Update the deal-template relationship
            $this->createDealTemplateRelationship($dealId, $templateId);
            
            // Update deal checklist fields
            $this->updateDealChecklistFields($dealId);
            
            // Log the activity
            $this->logChecklistActivity('created', $checklist->id, array(
                'deal_id' => $dealId,
                'template_id' => $templateId,
                'items_created' => count($items)
            ));
            
            return array(
                'success' => true,
                'checklist_id' => $checklist->id,
                'checklist_name' => $checklist->name,
                'items_created' => count($items),
                'message' => 'Checklist created successfully'
            );
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('ChecklistService::createChecklistFromTemplate - Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Update checklist item status
     * 
     * @param string $itemId The checklist item ID
     * @param string $status The new status
     * @param array $data Additional data (notes, completed_by, etc.)
     * @return array Result with success status
     */
    public function updateChecklistItem($itemId, $status, $data = array())
    {
        try {
            // Load the checklist item
            $item = BeanFactory::getBean('ChecklistItems', $itemId);
            if (!$item || $item->deleted) {
                throw new Exception('Checklist item not found');
            }
            
            // Get the associated checklist
            $checklist = BeanFactory::getBean('DealChecklists', $item->checklist_id);
            if (!$checklist || $checklist->deleted) {
                throw new Exception('Associated checklist not found');
            }
            
            // Check permissions
            if (!$this->hasChecklistPermission($checklist)) {
                throw new Exception('You do not have permission to modify this checklist');
            }
            
            // Update the item status
            $oldStatus = $item->status;
            $item->status = $status;
            
            // Handle status-specific updates
            if ($status === 'completed') {
                $item->completed_date = $this->timeDate->nowDb();
                $item->completed_by = $data['completed_by'] ?? $this->currentUser->id;
            } elseif ($status === 'in_progress' && empty($item->date_started)) {
                $item->date_started = $this->timeDate->nowDb();
            }
            
            // Update additional fields if provided
            if (isset($data['notes'])) {
                $item->notes = $data['notes'];
            }
            if (isset($data['assigned_user_id'])) {
                $item->assigned_user_id = $data['assigned_user_id'];
            }
            
            $item->save();
            
            // Update checklist progress
            $this->updateChecklistProgress($checklist->id);
            
            // Update associated task if exists
            if (!empty($item->task_id)) {
                $this->updateAssociatedTask($item->task_id, $status);
            }
            
            // Check for stage advancement rules
            $this->checkStageAdvancement($checklist);
            
            // Log the activity
            $this->logChecklistActivity('item_updated', $item->id, array(
                'checklist_id' => $checklist->id,
                'old_status' => $oldStatus,
                'new_status' => $status
            ));
            
            return array(
                'success' => true,
                'message' => 'Checklist item updated successfully',
                'item_id' => $itemId,
                'new_status' => $status
            );
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('ChecklistService::updateChecklistItem - Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Delete a checklist
     * 
     * @param string $checklistId The checklist ID
     * @param boolean $cascadeDelete Whether to cascade delete items
     * @return array Result with success status
     */
    public function deleteChecklist($checklistId, $cascadeDelete = true)
    {
        try {
            // Load the checklist
            $checklist = BeanFactory::getBean('DealChecklists', $checklistId);
            if (!$checklist || $checklist->deleted) {
                throw new Exception('Checklist not found');
            }
            
            // Check permissions
            if (!$this->hasChecklistPermission($checklist)) {
                throw new Exception('You do not have permission to delete this checklist');
            }
            
            $dealId = $checklist->deal_id;
            
            // Delete associated items if cascade delete is enabled
            if ($cascadeDelete) {
                $this->deleteChecklistItems($checklistId);
            }
            
            // Mark checklist as deleted
            $checklist->mark_deleted($checklistId);
            
            // Update deal checklist fields
            $this->updateDealChecklistFields($dealId);
            
            // Log the activity
            $this->logChecklistActivity('deleted', $checklistId, array(
                'deal_id' => $dealId,
                'cascade_delete' => $cascadeDelete
            ));
            
            return array(
                'success' => true,
                'message' => 'Checklist deleted successfully'
            );
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('ChecklistService::deleteChecklist - Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Get all checklists for a deal
     * 
     * @param string $dealId The deal ID
     * @param array $filters Optional filters
     * @return array Array of checklists
     */
    public function getDealChecklists($dealId, $filters = array())
    {
        try {
            $where = "deal_id = '{$dealId}' AND deleted = 0";
            
            // Apply filters
            if (!empty($filters['status'])) {
                $where .= " AND status = '{$this->db->quote($filters['status'])}'";
            }
            if (!empty($filters['template_id'])) {
                $where .= " AND template_id = '{$this->db->quote($filters['template_id'])}'";
            }
            
            // Build query
            $sql = "SELECT 
                        dc.*,
                        ct.name as template_name,
                        ct.category as template_category,
                        u.user_name as assigned_user_name
                    FROM deal_checklists dc
                    LEFT JOIN checklist_templates ct ON dc.template_id = ct.id
                    LEFT JOIN users u ON dc.assigned_user_id = u.id
                    WHERE {$where}
                    ORDER BY dc.date_entered DESC";
            
            $result = $this->db->query($sql);
            $checklists = array();
            
            while ($row = $this->db->fetchByAssoc($result)) {
                // Get item statistics
                $itemStats = $this->getChecklistItemStats($row['id']);
                $row['total_items'] = $itemStats['total'];
                $row['completed_items'] = $itemStats['completed'];
                $row['overdue_items'] = $itemStats['overdue'];
                
                $checklists[] = $row;
            }
            
            return $checklists;
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('ChecklistService::getDealChecklists - Error: ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Get checklist progress report
     * 
     * @param string $checklistId The checklist ID
     * @return array Progress report data
     */
    public function getChecklistProgress($checklistId)
    {
        try {
            $checklist = BeanFactory::getBean('DealChecklists', $checklistId);
            if (!$checklist || $checklist->deleted) {
                throw new Exception('Checklist not found');
            }
            
            // Get items grouped by status
            $sql = "SELECT 
                        status,
                        COUNT(*) as count,
                        SUM(CASE WHEN is_required = 1 THEN 1 ELSE 0 END) as required_count
                    FROM checklist_items
                    WHERE checklist_id = '{$checklistId}' AND deleted = 0
                    GROUP BY status";
            
            $result = $this->db->query($sql);
            $statusBreakdown = array();
            $totalItems = 0;
            $completedItems = 0;
            $requiredCompleted = 0;
            $totalRequired = 0;
            
            while ($row = $this->db->fetchByAssoc($result)) {
                $statusBreakdown[$row['status']] = array(
                    'count' => (int)$row['count'],
                    'required_count' => (int)$row['required_count']
                );
                
                $totalItems += $row['count'];
                $totalRequired += $row['required_count'];
                
                if ($row['status'] === 'completed') {
                    $completedItems = $row['count'];
                    $requiredCompleted = $row['required_count'];
                }
            }
            
            // Calculate progress percentages
            $overallProgress = $totalItems > 0 ? round(($completedItems / $totalItems) * 100) : 0;
            $requiredProgress = $totalRequired > 0 ? round(($requiredCompleted / $totalRequired) * 100) : 0;
            
            // Get overdue items
            $overdueItems = $this->getOverdueItems($checklistId);
            
            // Get recent activity
            $recentActivity = $this->getRecentChecklistActivity($checklistId, 10);
            
            return array(
                'checklist_id' => $checklistId,
                'checklist_name' => $checklist->name,
                'status' => $checklist->status,
                'overall_progress' => $overallProgress,
                'required_progress' => $requiredProgress,
                'total_items' => $totalItems,
                'completed_items' => $completedItems,
                'total_required' => $totalRequired,
                'required_completed' => $requiredCompleted,
                'status_breakdown' => $statusBreakdown,
                'overdue_items' => count($overdueItems),
                'recent_activity' => $recentActivity,
                'date_started' => $checklist->date_started,
                'date_completed' => $checklist->date_completed,
                'estimated_completion' => $this->estimateCompletionDate($checklist)
            );
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('ChecklistService::getChecklistProgress - Error: ' . $e->getMessage());
            return array('error' => $e->getMessage());
        }
    }
    
    /**
     * Clone a checklist template
     * 
     * @param string $templateId The template ID to clone
     * @param string $newName The name for the cloned template
     * @param array $options Additional options
     * @return array Result with success status and new template ID
     */
    public function cloneTemplate($templateId, $newName, $options = array())
    {
        try {
            $template = BeanFactory::getBean('ChecklistTemplates', $templateId);
            if (!$template || $template->deleted) {
                throw new Exception('Template not found');
            }
            
            // Check permissions
            if (!$template->is_public && $template->created_by != $this->currentUser->id) {
                throw new Exception('You do not have permission to clone this template');
            }
            
            // Clone the template
            $clonedTemplate = $template->cloneTemplate($newName);
            
            // Apply any additional options
            if (isset($options['category'])) {
                $clonedTemplate->category = $options['category'];
            }
            if (isset($options['is_public'])) {
                $clonedTemplate->is_public = $options['is_public'];
            }
            if (isset($options['description'])) {
                $clonedTemplate->description = $options['description'];
            }
            
            $clonedTemplate->save();
            
            // Log the activity
            $this->logChecklistActivity('template_cloned', $clonedTemplate->id, array(
                'source_template_id' => $templateId,
                'new_name' => $newName
            ));
            
            return array(
                'success' => true,
                'template_id' => $clonedTemplate->id,
                'message' => 'Template cloned successfully'
            );
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('ChecklistService::cloneTemplate - Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Get available templates for a user
     * 
     * @param array $filters Optional filters
     * @return array Array of templates
     */
    public function getAvailableTemplates($filters = array())
    {
        try {
            $where = "deleted = 0 AND is_active = 1";
            
            // Add visibility filter
            $where .= " AND (is_public = 1 OR created_by = '{$this->currentUser->id}')";
            
            // Apply additional filters
            if (!empty($filters['category'])) {
                $where .= " AND category = '{$this->db->quote($filters['category'])}'";
            }
            if (!empty($filters['search'])) {
                $search = $this->db->quote($filters['search']);
                $where .= " AND (name LIKE '%{$search}%' OR description LIKE '%{$search}%')";
            }
            
            // Build query
            $sql = "SELECT 
                        t.*,
                        u.user_name as created_by_name,
                        (SELECT COUNT(*) FROM checklist_items WHERE template_id = t.id AND deleted = 0) as item_count,
                        (SELECT COUNT(*) FROM deal_checklists WHERE template_id = t.id AND deleted = 0) as usage_count
                    FROM checklist_templates t
                    LEFT JOIN users u ON t.created_by = u.id
                    WHERE {$where}
                    ORDER BY t.name ASC";
            
            $result = $this->db->query($sql);
            $templates = array();
            
            while ($row = $this->db->fetchByAssoc($result)) {
                // Get template statistics
                $row['statistics'] = $this->getTemplateStatistics($row['id']);
                $templates[] = $row;
            }
            
            return $templates;
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('ChecklistService::getAvailableTemplates - Error: ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Bulk update checklist items
     * 
     * @param array $itemIds Array of item IDs
     * @param array $updates Updates to apply
     * @return array Result with success count
     */
    public function bulkUpdateItems($itemIds, $updates)
    {
        try {
            $successCount = 0;
            $errors = array();
            
            foreach ($itemIds as $itemId) {
                $result = $this->updateChecklistItem($itemId, $updates['status'] ?? null, $updates);
                if ($result['success']) {
                    $successCount++;
                } else {
                    $errors[] = "Item {$itemId}: " . $result['message'];
                }
            }
            
            return array(
                'success' => true,
                'updated_count' => $successCount,
                'total_count' => count($itemIds),
                'errors' => $errors
            );
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('ChecklistService::bulkUpdateItems - Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Export checklist to various formats
     * 
     * @param string $checklistId The checklist ID
     * @param string $format Export format (pdf, excel, csv)
     * @return mixed Export result or file download
     */
    public function exportChecklist($checklistId, $format = 'pdf')
    {
        try {
            $checklist = BeanFactory::getBean('DealChecklists', $checklistId);
            if (!$checklist || $checklist->deleted) {
                throw new Exception('Checklist not found');
            }
            
            // Check permissions
            if (!$this->hasChecklistPermission($checklist, 'view')) {
                throw new Exception('You do not have permission to export this checklist');
            }
            
            switch ($format) {
                case 'pdf':
                    return $checklist->exportToPDF();
                case 'excel':
                case 'csv':
                    return $checklist->exportToExcel();
                default:
                    throw new Exception('Unsupported export format');
            }
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('ChecklistService::exportChecklist - Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Get checklist analytics
     * 
     * @param string $dealId Optional deal ID for deal-specific analytics
     * @param array $dateRange Optional date range filter
     * @return array Analytics data
     */
    public function getChecklistAnalytics($dealId = null, $dateRange = array())
    {
        try {
            $where = "dc.deleted = 0";
            
            if (!empty($dealId)) {
                $where .= " AND dc.deal_id = '{$dealId}'";
            }
            
            if (!empty($dateRange['start'])) {
                $where .= " AND dc.date_entered >= '{$dateRange['start']}'";
            }
            if (!empty($dateRange['end'])) {
                $where .= " AND dc.date_entered <= '{$dateRange['end']}'";
            }
            
            // Get completion metrics
            $sql = "SELECT 
                        COUNT(*) as total_checklists,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_checklists,
                        AVG(progress) as avg_progress,
                        AVG(CASE WHEN status = 'completed' THEN DATEDIFF(date_completed, date_started) END) as avg_completion_days
                    FROM deal_checklists dc
                    WHERE {$where}";
            
            $result = $this->db->query($sql);
            $metrics = $this->db->fetchByAssoc($result);
            
            // Get template usage
            $sql = "SELECT 
                        ct.name as template_name,
                        ct.category,
                        COUNT(*) as usage_count,
                        AVG(dc.progress) as avg_progress
                    FROM deal_checklists dc
                    JOIN checklist_templates ct ON dc.template_id = ct.id
                    WHERE {$where}
                    GROUP BY dc.template_id
                    ORDER BY usage_count DESC
                    LIMIT 10";
            
            $result = $this->db->query($sql);
            $templateUsage = array();
            while ($row = $this->db->fetchByAssoc($result)) {
                $templateUsage[] = $row;
            }
            
            // Get bottleneck analysis
            $bottlenecks = $this->analyzeBottlenecks($dealId);
            
            return array(
                'metrics' => $metrics,
                'template_usage' => $templateUsage,
                'bottlenecks' => $bottlenecks,
                'generated_at' => $this->timeDate->nowDb()
            );
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('ChecklistService::getChecklistAnalytics - Error: ' . $e->getMessage());
            return array('error' => $e->getMessage());
        }
    }
    
    // Private helper methods
    
    /**
     * Create checklist items from template
     */
    private function createChecklistItems($checklistId, $templateId, $options = array())
    {
        $items = array();
        
        // Get template items
        $sql = "SELECT * FROM checklist_items 
                WHERE template_id = '{$templateId}' AND deleted = 0
                ORDER BY order_number ASC";
        
        $result = $this->db->query($sql);
        
        while ($templateItem = $this->db->fetchByAssoc($result)) {
            $item = BeanFactory::newBean('ChecklistItems');
            
            // Copy fields from template
            foreach (['title', 'description', 'type', 'order_number', 'is_required', 'due_days'] as $field) {
                if (isset($templateItem[$field])) {
                    $item->$field = $templateItem[$field];
                }
            }
            
            $item->checklist_id = $checklistId;
            $item->status = 'pending';
            
            // Calculate due date
            if (!empty($item->due_days)) {
                $dueDate = new DateTime();
                $dueDate->add(new DateInterval('P' . $item->due_days . 'D'));
                $item->due_date = $dueDate->format('Y-m-d');
            }
            
            $item->save();
            $items[] = $item;
            
            // Create associated task if configured
            if (!empty($options['create_tasks']) && !empty($templateItem['auto_create_task'])) {
                $this->createTaskFromItem($item, $options['deal_id'] ?? null);
            }
        }
        
        return $items;
    }
    
    /**
     * Update checklist progress
     */
    private function updateChecklistProgress($checklistId)
    {
        $checklist = BeanFactory::getBean('DealChecklists', $checklistId);
        if ($checklist) {
            $checklist->updateProgress();
        }
    }
    
    /**
     * Check if user has permission for checklist operations
     */
    private function hasChecklistPermission($checklist, $action = 'edit')
    {
        // Load related deal
        $deal = BeanFactory::getBean('Opportunities', $checklist->deal_id);
        if (!$deal) {
            return false;
        }
        
        // Check deal ACL
        if (!$deal->ACLAccess($action)) {
            return false;
        }
        
        // Check if user is assigned or admin
        if ($this->currentUser->is_admin || 
            $checklist->assigned_user_id === $this->currentUser->id ||
            $deal->assigned_user_id === $this->currentUser->id) {
            return true;
        }
        
        // Additional business logic for team permissions could go here
        
        return false;
    }
    
    /**
     * Update deal checklist fields
     */
    private function updateDealChecklistFields($dealId)
    {
        // Calculate completion percentage
        $sql = "SELECT 
                    AVG(progress) as avg_progress,
                    COUNT(*) as active_count,
                    SUM(CASE WHEN ci.due_date < CURDATE() AND ci.status != 'completed' THEN 1 ELSE 0 END) as overdue_count
                FROM deal_checklists dc
                LEFT JOIN checklist_items ci ON dc.id = ci.checklist_id AND ci.deleted = 0
                WHERE dc.deal_id = '{$dealId}' AND dc.deleted = 0 AND dc.status != 'cancelled'";
        
        $result = $this->db->query($sql);
        $stats = $this->db->fetchByAssoc($result);
        
        // Update deal custom fields
        $updateSql = "UPDATE opportunities_cstm SET 
                        checklist_completion_c = " . ($stats['avg_progress'] ?? 0) . ",
                        active_checklists_count_c = " . ($stats['active_count'] ?? 0) . ",
                        overdue_checklist_items_c = " . ($stats['overdue_count'] ?? 0) . "
                      WHERE id_c = '{$dealId}'";
        
        $this->db->query($updateSql);
    }
    
    /**
     * Log checklist activity
     */
    private function logChecklistActivity($action, $recordId, $data = array())
    {
        // Implementation for activity logging
        // This could write to a custom audit table or use SuiteCRM's audit functionality
        $GLOBALS['log']->info("ChecklistService Activity: {$action} on {$recordId}", $data);
    }
    
    /**
     * Get checklist item statistics
     */
    private function getChecklistItemStats($checklistId)
    {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN due_date < CURDATE() AND status != 'completed' THEN 1 ELSE 0 END) as overdue
                FROM checklist_items
                WHERE checklist_id = '{$checklistId}' AND deleted = 0";
        
        $result = $this->db->query($sql);
        return $this->db->fetchByAssoc($result);
    }
    
    /**
     * Get overdue items
     */
    private function getOverdueItems($checklistId)
    {
        $sql = "SELECT * FROM checklist_items
                WHERE checklist_id = '{$checklistId}' 
                AND deleted = 0
                AND status != 'completed'
                AND due_date < CURDATE()
                ORDER BY due_date ASC";
        
        $result = $this->db->query($sql);
        $items = array();
        
        while ($row = $this->db->fetchByAssoc($result)) {
            $items[] = $row;
        }
        
        return $items;
    }
    
    /**
     * Create deal-template relationship
     */
    private function createDealTemplateRelationship($dealId, $templateId)
    {
        $id = create_guid();
        $sql = "INSERT INTO deals_checklist_templates (id, deal_id, template_id, date_modified, deleted)
                VALUES ('{$id}', '{$dealId}', '{$templateId}', NOW(), 0)
                ON DUPLICATE KEY UPDATE date_modified = NOW(), deleted = 0";
        
        $this->db->query($sql);
    }
    
    /**
     * Delete checklist items
     */
    private function deleteChecklistItems($checklistId)
    {
        $sql = "UPDATE checklist_items SET deleted = 1 WHERE checklist_id = '{$checklistId}'";
        $this->db->query($sql);
    }
    
    /**
     * Update associated task
     */
    private function updateAssociatedTask($taskId, $itemStatus)
    {
        $task = BeanFactory::getBean('Tasks', $taskId);
        if ($task && !$task->deleted) {
            if ($itemStatus === 'completed') {
                $task->status = 'Completed';
            } elseif ($itemStatus === 'in_progress' && $task->status === 'Not Started') {
                $task->status = 'In Progress';
            }
            $task->save();
        }
    }
    
    /**
     * Check for stage advancement based on checklist completion
     */
    private function checkStageAdvancement($checklist)
    {
        // Get deal
        $deal = BeanFactory::getBean('Opportunities', $checklist->deal_id);
        if (!$deal) {
            return;
        }
        
        // Check if all required items in checklist are complete
        $sql = "SELECT COUNT(*) as incomplete_required
                FROM checklist_items
                WHERE checklist_id = '{$checklist->id}'
                AND deleted = 0
                AND is_required = 1
                AND status != 'completed'";
        
        $result = $this->db->query($sql);
        $row = $this->db->fetchByAssoc($result);
        
        if ($row['incomplete_required'] == 0 && $checklist->progress >= 100) {
            // All required items complete - check stage advancement rules
            $this->applyStageAdvancementRules($deal, $checklist);
        }
    }
    
    /**
     * Apply stage advancement rules
     */
    private function applyStageAdvancementRules($deal, $checklist)
    {
        // Define stage advancement rules based on checklist category
        $advancementRules = array(
            'due_diligence' => array(
                'from' => 'due_diligence',
                'to' => 'valuation_structuring'
            ),
            'financial' => array(
                'from' => 'valuation_structuring',
                'to' => 'loi_negotiation'
            ),
            'legal' => array(
                'from' => 'loi_negotiation',
                'to' => 'financing'
            )
        );
        
        // Get template category
        $template = BeanFactory::getBean('ChecklistTemplates', $checklist->template_id);
        if ($template && isset($advancementRules[$template->category])) {
            $rule = $advancementRules[$template->category];
            if ($deal->pipeline_stage_c === $rule['from']) {
                // Advance to next stage
                $deal->pipeline_stage_c = $rule['to'];
                $deal->stage_entered_date_c = $this->timeDate->nowDb();
                $deal->save();
                
                $GLOBALS['log']->info("ChecklistService: Advanced deal {$deal->id} from {$rule['from']} to {$rule['to']} based on checklist completion");
            }
        }
    }
    
    /**
     * Get recent checklist activity
     */
    private function getRecentChecklistActivity($checklistId, $limit = 10)
    {
        // This would typically query an audit table
        // For now, return empty array
        return array();
    }
    
    /**
     * Estimate completion date based on current progress
     */
    private function estimateCompletionDate($checklist)
    {
        if ($checklist->status === 'completed') {
            return $checklist->date_completed;
        }
        
        if ($checklist->progress == 0) {
            return null;
        }
        
        // Calculate days elapsed
        $startDate = new DateTime($checklist->date_started);
        $now = new DateTime();
        $daysElapsed = $startDate->diff($now)->days;
        
        // Estimate total days needed
        $estimatedTotalDays = ($daysElapsed / $checklist->progress) * 100;
        $remainingDays = $estimatedTotalDays - $daysElapsed;
        
        // Calculate estimated completion date
        $estimatedDate = clone $now;
        $estimatedDate->add(new DateInterval('P' . round($remainingDays) . 'D'));
        
        return $estimatedDate->format('Y-m-d');
    }
    
    /**
     * Get template statistics
     */
    private function getTemplateStatistics($templateId)
    {
        $template = BeanFactory::getBean('ChecklistTemplates', $templateId);
        if ($template) {
            return $template->getStatistics();
        }
        return array();
    }
    
    /**
     * Analyze bottlenecks in checklist completion
     */
    private function analyzeBottlenecks($dealId = null)
    {
        $where = "ci.deleted = 0 AND dc.deleted = 0";
        if ($dealId) {
            $where .= " AND dc.deal_id = '{$dealId}'";
        }
        
        // Find items that take longest to complete
        $sql = "SELECT 
                    ci.title,
                    AVG(DATEDIFF(ci.completed_date, ci.date_entered)) as avg_days_to_complete,
                    COUNT(*) as sample_size
                FROM checklist_items ci
                JOIN deal_checklists dc ON ci.checklist_id = dc.id
                WHERE {$where} AND ci.status = 'completed'
                GROUP BY ci.title
                HAVING sample_size > 5
                ORDER BY avg_days_to_complete DESC
                LIMIT 10";
        
        $result = $this->db->query($sql);
        $bottlenecks = array();
        
        while ($row = $this->db->fetchByAssoc($result)) {
            $bottlenecks[] = $row;
        }
        
        return $bottlenecks;
    }
    
    /**
     * Create task from checklist item
     */
    private function createTaskFromItem($item, $dealId)
    {
        $task = BeanFactory::newBean('Tasks');
        $task->name = $item->title;
        $task->description = $item->description;
        $task->parent_type = 'Opportunities';
        $task->parent_id = $dealId;
        $task->assigned_user_id = $this->currentUser->id;
        $task->status = 'Not Started';
        $task->priority = $item->is_required ? 'High' : 'Medium';
        
        if (!empty($item->due_date)) {
            $task->date_due = $item->due_date;
        }
        
        $task->save();
        
        // Link task to checklist item
        $item->task_id = $task->id;
        $item->save();
        
        return $task;
    }
}