<?php
/**
 * Checklist API for Deals module
 * Provides CRUD operations for checklists and templates
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class ChecklistApi
{
    private $db;
    private $log;
    
    public function __construct()
    {
        global $db, $log;
        $this->db = $db;
        $this->log = $log;
    }
    
    /**
     * Get checklist data for a deal
     */
    public function getChecklist($dealId)
    {
        if (empty($dealId)) {
            return ['success' => false, 'error' => 'Deal ID is required'];
        }
        
        try {
            // Get applied templates for this deal
            $templates = $this->getAppliedTemplates($dealId);
            
            // Get all checklist items for this deal
            $items = $this->getChecklistItems($dealId);
            
            // Calculate overall progress
            $totalItems = count($items);
            $completedItems = 0;
            $categories = [];
            
            foreach ($items as $item) {
                if ($item['completion_status'] === 'completed') {
                    $completedItems++;
                }
                
                // Group by category/template
                $category = $item['template_name'] ?? 'General';
                if (!isset($categories[$category])) {
                    $categories[$category] = [
                        'id' => $item['template_id'] ?? 'general',
                        'name' => $category,
                        'items' => [],
                        'completed' => 0,
                        'total' => 0
                    ];
                }
                
                $categories[$category]['items'][] = $item;
                $categories[$category]['total']++;
                if ($item['completion_status'] === 'completed') {
                    $categories[$category]['completed']++;
                }
            }
            
            // Calculate category progress
            foreach ($categories as &$category) {
                $category['progress'] = $category['total'] > 0 
                    ? round(($category['completed'] / $category['total']) * 100) 
                    : 0;
                $category['status'] = $category['progress'] === 100 ? 'completed' : 
                    ($category['progress'] > 0 ? 'in_progress' : 'pending');
            }
            
            $overallProgress = $totalItems > 0 
                ? round(($completedItems / $totalItems) * 100) 
                : 0;
            
            return [
                'success' => true,
                'data' => [
                    'checklist' => [
                        'id' => 'checklist_' . $dealId,
                        'deal_id' => $dealId,
                        'name' => 'Deal Checklist',
                        'status' => $overallProgress === 100 ? 'completed' : 'in_progress',
                        'progress' => $overallProgress,
                        'total_tasks' => $totalItems,
                        'completed_tasks' => $completedItems,
                        'categories' => array_values($categories),
                        'tasks' => $items,
                        'templates' => $templates
                    ]
                ]
            ];
            
        } catch (Exception $e) {
            $this->log->error('ChecklistApi::getChecklist error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to load checklist: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get applied templates for a deal
     */
    private function getAppliedTemplates($dealId)
    {
        $sql = "SELECT 
                    dct.id,
                    dct.template_id,
                    ct.name as template_name,
                    ct.description,
                    dct.applied_date,
                    dct.completion_percentage,
                    dct.status,
                    dct.due_date
                FROM deals_checklist_templates dct
                JOIN checklist_templates ct ON ct.id = dct.template_id
                WHERE dct.deal_id = '{$this->db->quote($dealId)}'
                AND dct.deleted = 0
                AND ct.deleted = 0
                ORDER BY dct.applied_date DESC";
        
        $result = $this->db->query($sql);
        $templates = [];
        
        while ($row = $this->db->fetchByAssoc($result)) {
            $templates[] = $row;
        }
        
        return $templates;
    }
    
    /**
     * Get checklist items for a deal
     */
    private function getChecklistItems($dealId)
    {
        $sql = "SELECT 
                    dci.id,
                    dci.item_id,
                    dci.template_instance_id,
                    ci.name as item_name,
                    ci.description,
                    ct.name as template_name,
                    ct.id as template_id,
                    dci.completion_status,
                    dci.completion_date,
                    dci.due_date,
                    dci.priority,
                    dci.notes,
                    dci.assigned_user_id,
                    CONCAT(u.first_name, ' ', u.last_name) as assigned_to,
                    ci.estimated_hours,
                    dci.actual_hours,
                    ci.requires_document,
                    dci.document_requested,
                    dci.document_received
                FROM deals_checklist_items dci
                LEFT JOIN checklist_items ci ON ci.id = dci.item_id
                LEFT JOIN deals_checklist_templates dct ON dct.id = dci.template_instance_id
                LEFT JOIN checklist_templates ct ON ct.id = dct.template_id
                LEFT JOIN users u ON u.id = dci.assigned_user_id
                WHERE dci.deal_id = '{$this->db->quote($dealId)}'
                AND dci.deleted = 0
                ORDER BY ct.name, ci.sort_order";
        
        $result = $this->db->query($sql);
        $items = [];
        
        while ($row = $this->db->fetchByAssoc($result)) {
            $items[] = [
                'id' => $row['id'],
                'item_id' => $row['item_id'],
                'category' => $row['template_id'] ?? 'general',
                'name' => $row['item_name'],
                'description' => $row['description'],
                'status' => $row['completion_status'],
                'completion_status' => $row['completion_status'],
                'assigned_to' => $row['assigned_to'] ?? 'Unassigned',
                'assigned_user_id' => $row['assigned_user_id'],
                'due_date' => $row['due_date'],
                'priority' => $row['priority'],
                'notes' => $row['notes'],
                'estimated_hours' => $row['estimated_hours'],
                'actual_hours' => $row['actual_hours'],
                'requires_document' => $row['requires_document'],
                'document_requested' => $row['document_requested'],
                'document_received' => $row['document_received'],
                'template_name' => $row['template_name'],
                'template_id' => $row['template_id']
            ];
        }
        
        // If no items exist, return default template items
        if (empty($items)) {
            return $this->getDefaultChecklistItems($dealId);
        }
        
        return $items;
    }
    
    /**
     * Get default checklist items for a new deal
     */
    private function getDefaultChecklistItems($dealId)
    {
        // Return some default items for demonstration
        return [
            [
                'id' => 'default_1',
                'category' => 'financial',
                'name' => 'Financial Review',
                'status' => 'pending',
                'assigned_to' => 'Financial Team',
                'due_date' => date('Y-m-d', strtotime('+7 days')),
                'priority' => 'high',
                'description' => 'Review financial statements and projections'
            ],
            [
                'id' => 'default_2',
                'category' => 'legal',
                'name' => 'Legal Documentation',
                'status' => 'pending',
                'assigned_to' => 'Legal Team',
                'due_date' => date('Y-m-d', strtotime('+14 days')),
                'priority' => 'medium',
                'description' => 'Review contracts and legal agreements'
            ],
            [
                'id' => 'default_3',
                'category' => 'technical',
                'name' => 'Technical Assessment',
                'status' => 'pending',
                'assigned_to' => 'Technical Team',
                'due_date' => date('Y-m-d', strtotime('+21 days')),
                'priority' => 'medium',
                'description' => 'Evaluate technical infrastructure and capabilities'
            ]
        ];
    }
    
    /**
     * Create a new task
     */
    public function createTask($dealId, $taskData)
    {
        global $current_user;
        
        if (empty($dealId) || empty($taskData['name'])) {
            return ['success' => false, 'error' => 'Deal ID and task name are required'];
        }
        
        try {
            $taskId = create_guid();
            $now = date('Y-m-d H:i:s');
            
            $sql = "INSERT INTO deals_checklist_items (
                        id, deal_id, item_id, completion_status, due_date, 
                        priority, notes, assigned_user_id, created_by, 
                        date_entered, date_modified, deleted
                    ) VALUES (
                        '{$taskId}',
                        '{$this->db->quote($dealId)}',
                        '{$taskId}', -- Using task ID as item ID for custom tasks
                        'pending',
                        " . ($taskData['due_date'] ? "'{$this->db->quote($taskData['due_date'])}'" : "NULL") . ",
                        '{$this->db->quote($taskData['priority'] ?? 'medium')}',
                        '{$this->db->quote($taskData['description'] ?? '')}',
                        " . ($taskData['assigned_user_id'] ? "'{$this->db->quote($taskData['assigned_user_id'])}'" : "NULL") . ",
                        '{$current_user->id}',
                        '{$now}',
                        '{$now}',
                        0
                    )";
            
            $this->db->query($sql);
            
            // Also create a checklist item record for the custom task
            $itemSql = "INSERT INTO checklist_items (
                            id, name, description, created_by, date_entered, 
                            date_modified, deleted
                        ) VALUES (
                            '{$taskId}',
                            '{$this->db->quote($taskData['name'])}',
                            '{$this->db->quote($taskData['description'] ?? '')}',
                            '{$current_user->id}',
                            '{$now}',
                            '{$now}',
                            0
                        )";
            
            $this->db->query($itemSql);
            
            return [
                'success' => true,
                'message' => 'Task created successfully',
                'task_id' => $taskId
            ];
            
        } catch (Exception $e) {
            $this->log->error('ChecklistApi::createTask error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to create task: ' . $e->getMessage()];
        }
    }
    
    /**
     * Update a task
     */
    public function updateTask($taskData)
    {
        global $current_user;
        
        if (empty($taskData['task_id'])) {
            return ['success' => false, 'error' => 'Task ID is required'];
        }
        
        try {
            $updates = [];
            $now = date('Y-m-d H:i:s');
            
            // Build update fields
            if (isset($taskData['status'])) {
                $updates[] = "completion_status = '{$this->db->quote($taskData['status'])}'";
                if ($taskData['status'] === 'completed') {
                    $updates[] = "completion_date = '{$now}'";
                }
            }
            
            if (isset($taskData['assigned_to'])) {
                $updates[] = "assigned_user_id = '{$this->db->quote($taskData['assigned_user_id'] ?? '')}'";
            }
            
            if (isset($taskData['due_date'])) {
                $updates[] = "due_date = '{$this->db->quote($taskData['due_date'])}'";
            }
            
            if (isset($taskData['priority'])) {
                $updates[] = "priority = '{$this->db->quote($taskData['priority'])}'";
            }
            
            if (isset($taskData['notes']) || isset($taskData['description'])) {
                $notes = $taskData['notes'] ?? $taskData['description'] ?? '';
                $updates[] = "notes = '{$this->db->quote($notes)}'";
            }
            
            $updates[] = "date_modified = '{$now}'";
            $updates[] = "modified_user_id = '{$current_user->id}'";
            
            $sql = "UPDATE deals_checklist_items 
                    SET " . implode(', ', $updates) . "
                    WHERE id = '{$this->db->quote($taskData['task_id'])}'
                    AND deleted = 0";
            
            $this->db->query($sql);
            
            // Update the checklist item if name changed
            if (isset($taskData['name'])) {
                $itemSql = "UPDATE checklist_items 
                           SET name = '{$this->db->quote($taskData['name'])}',
                               date_modified = '{$now}',
                               modified_user_id = '{$current_user->id}'
                           WHERE id = (
                               SELECT item_id FROM deals_checklist_items 
                               WHERE id = '{$this->db->quote($taskData['task_id'])}'
                           )";
                
                $this->db->query($itemSql);
            }
            
            return [
                'success' => true,
                'message' => 'Task updated successfully'
            ];
            
        } catch (Exception $e) {
            $this->log->error('ChecklistApi::updateTask error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to update task: ' . $e->getMessage()];
        }
    }
    
    /**
     * Delete a task
     */
    public function deleteTask($taskId)
    {
        if (empty($taskId)) {
            return ['success' => false, 'error' => 'Task ID is required'];
        }
        
        try {
            $sql = "UPDATE deals_checklist_items 
                    SET deleted = 1 
                    WHERE id = '{$this->db->quote($taskId)}'";
            
            $this->db->query($sql);
            
            return [
                'success' => true,
                'message' => 'Task deleted successfully'
            ];
            
        } catch (Exception $e) {
            $this->log->error('ChecklistApi::deleteTask error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to delete task: ' . $e->getMessage()];
        }
    }
    
    /**
     * Update task status
     */
    public function updateTaskStatus($taskId, $status)
    {
        global $current_user;
        
        if (empty($taskId) || empty($status)) {
            return ['success' => false, 'error' => 'Task ID and status are required'];
        }
        
        $validStatuses = ['pending', 'in_progress', 'completed', 'not_applicable', 'blocked'];
        if (!in_array($status, $validStatuses)) {
            return ['success' => false, 'error' => 'Invalid status'];
        }
        
        try {
            $now = date('Y-m-d H:i:s');
            $completionDate = $status === 'completed' ? ", completion_date = '{$now}'" : "";
            
            $sql = "UPDATE deals_checklist_items 
                    SET completion_status = '{$this->db->quote($status)}'
                        {$completionDate},
                        date_modified = '{$now}',
                        modified_user_id = '{$current_user->id}'
                    WHERE id = '{$this->db->quote($taskId)}'
                    AND deleted = 0";
            
            $this->db->query($sql);
            
            return [
                'success' => true,
                'message' => 'Task status updated successfully'
            ];
            
        } catch (Exception $e) {
            $this->log->error('ChecklistApi::updateTaskStatus error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to update task status: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get available templates
     */
    public function getTemplates()
    {
        try {
            $sql = "SELECT 
                        id,
                        name,
                        description,
                        category,
                        estimated_duration_days
                    FROM checklist_templates
                    WHERE is_active = 1
                    AND deleted = 0
                    ORDER BY category, name";
            
            $result = $this->db->query($sql);
            $templates = [];
            
            while ($row = $this->db->fetchByAssoc($result)) {
                $templates[] = $row;
            }
            
            return [
                'success' => true,
                'templates' => $templates
            ];
            
        } catch (Exception $e) {
            $this->log->error('ChecklistApi::getTemplates error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to load templates: ' . $e->getMessage()];
        }
    }
    
    /**
     * Apply a template to a deal
     */
    public function applyTemplate($dealId, $templateId)
    {
        global $current_user;
        
        if (empty($dealId) || empty($templateId)) {
            return ['success' => false, 'error' => 'Deal ID and template ID are required'];
        }
        
        try {
            // Check if template already applied
            $checkSql = "SELECT id FROM deals_checklist_templates 
                        WHERE deal_id = '{$this->db->quote($dealId)}'
                        AND template_id = '{$this->db->quote($templateId)}'
                        AND deleted = 0";
            
            $result = $this->db->query($checkSql);
            if ($this->db->fetchByAssoc($result)) {
                return ['success' => false, 'error' => 'Template already applied to this deal'];
            }
            
            // Create template instance
            $instanceId = create_guid();
            $now = date('Y-m-d H:i:s');
            
            $sql = "INSERT INTO deals_checklist_templates (
                        id, deal_id, template_id, applied_date, status,
                        created_by, date_entered, date_modified, deleted
                    ) VALUES (
                        '{$instanceId}',
                        '{$this->db->quote($dealId)}',
                        '{$this->db->quote($templateId)}',
                        '{$now}',
                        'active',
                        '{$current_user->id}',
                        '{$now}',
                        '{$now}',
                        0
                    )";
            
            $this->db->query($sql);
            
            // Get template items and create deal checklist items
            $itemsSql = "SELECT * FROM checklist_items 
                        WHERE template_id = '{$this->db->quote($templateId)}'
                        AND deleted = 0
                        ORDER BY sort_order";
            
            $itemsResult = $this->db->query($itemsSql);
            $createdItems = 0;
            
            while ($item = $this->db->fetchByAssoc($itemsResult)) {
                $dealItemId = create_guid();
                
                $insertSql = "INSERT INTO deals_checklist_items (
                                id, deal_id, item_id, template_instance_id,
                                completion_status, priority, estimated_hours,
                                created_by, date_entered, date_modified, deleted
                            ) VALUES (
                                '{$dealItemId}',
                                '{$this->db->quote($dealId)}',
                                '{$item['id']}',
                                '{$instanceId}',
                                'pending',
                                'medium',
                                {$item['estimated_hours']},
                                '{$current_user->id}',
                                '{$now}',
                                '{$now}',
                                0
                            )";
                
                $this->db->query($insertSql);
                $createdItems++;
            }
            
            return [
                'success' => true,
                'message' => "Template applied successfully. Created {$createdItems} checklist items.",
                'instance_id' => $instanceId
            ];
            
        } catch (Exception $e) {
            $this->log->error('ChecklistApi::applyTemplate error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to apply template: ' . $e->getMessage()];
        }
    }
    
    /**
     * Create a category (placeholder for future implementation)
     */
    public function createCategory($categoryData)
    {
        // For now, categories are based on templates
        return [
            'success' => false,
            'error' => 'Category creation is managed through templates'
        ];
    }
}