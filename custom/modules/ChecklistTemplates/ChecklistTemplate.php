<?php
/**
 * ChecklistTemplate Bean Class
 * 
 * @package MakeDealCRM
 * @subpackage ChecklistTemplates
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class ChecklistTemplate extends Basic
{
    public $module_dir = 'ChecklistTemplates';
    public $object_name = 'ChecklistTemplate';
    public $table_name = 'checklist_templates';
    public $importable = true;
    public $disable_row_level_security = false;
    
    public $id;
    public $name;
    public $date_entered;
    public $date_modified;
    public $modified_user_id;
    public $created_by;
    public $description;
    public $deleted;
    public $assigned_user_id;
    
    // Custom fields
    public $category;
    public $is_active;
    public $is_public;
    public $version;
    public $template_data;
    public $item_count;
    
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * Get available template categories
     */
    public static function getCategories()
    {
        return array(
            'general' => 'General',
            'due_diligence' => 'Due Diligence',
            'financial' => 'Financial Due Diligence',
            'legal' => 'Legal Due Diligence',
            'operational' => 'Operational Review',
            'compliance' => 'Compliance',
            'quick_screen' => 'Quick Screen'
        );
    }
    
    /**
     * Clone template
     */
    public function cloneTemplate($newName)
    {
        $clone = new ChecklistTemplate();
        
        // Copy all fields except ID
        foreach ($this->field_defs as $field => $def) {
            if ($field != 'id' && isset($this->$field)) {
                $clone->$field = $this->$field;
            }
        }
        
        $clone->id = create_guid();
        $clone->name = $newName;
        $clone->version = 1;
        $clone->date_entered = null;
        $clone->date_modified = null;
        
        $clone->save();
        
        // Clone associated items
        $this->load_relationship('checklist_items');
        $items = $this->checklist_items->getBeans();
        
        foreach ($items as $item) {
            $itemClone = new ChecklistItem();
            foreach ($item->field_defs as $field => $def) {
                if ($field != 'id' && $field != 'template_id' && isset($item->$field)) {
                    $itemClone->$field = $item->$field;
                }
            }
            $itemClone->id = create_guid();
            $itemClone->template_id = $clone->id;
            $itemClone->save();
        }
        
        return $clone;
    }
    
    /**
     * Apply template to create checklist for a deal
     * @deprecated Use ChecklistService->createChecklistFromTemplate() instead
     */
    public function applyToDeaI($dealId)
    {
        // This method is deprecated - use ChecklistService instead
        require_once('custom/modules/Deals/services/ChecklistService.php');
        $checklistService = new ChecklistService();
        
        $result = $checklistService->createChecklistFromTemplate($dealId, $this->id, array(
            'create_tasks' => true
        ));
        
        if ($result['success']) {
            return BeanFactory::getBean('DealChecklists', $result['checklist_id']);
        }
        
        throw new Exception($result['message']);
    }
    
    /**
     * Create task from checklist item
     */
    private function createTaskFromItem($item, $dealId)
    {
        global $current_user;
        
        $task = new Task();
        $task->name = $item->title;
        $task->description = $item->description;
        $task->parent_type = 'Deals';
        $task->parent_id = $dealId;
        $task->assigned_user_id = $current_user->id;
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
    
    /**
     * Get template statistics
     */
    public function getStatistics()
    {
        global $db;
        
        $stats = array(
            'times_used' => 0,
            'average_completion_time' => 0,
            'completion_rate' => 0
        );
        
        // Get usage count
        $sql = "SELECT COUNT(*) as count FROM deal_checklists 
                WHERE template_id = '{$this->id}' AND deleted = 0";
        $result = $db->query($sql);
        if ($row = $db->fetchByAssoc($result)) {
            $stats['times_used'] = $row['count'];
        }
        
        // Get completion statistics
        $sql = "SELECT 
                    AVG(DATEDIFF(date_completed, date_entered)) as avg_days,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                    COUNT(*) as total
                FROM deal_checklists 
                WHERE template_id = '{$this->id}' AND deleted = 0";
        $result = $db->query($sql);
        if ($row = $db->fetchByAssoc($result)) {
            $stats['average_completion_time'] = round($row['avg_days'] ?: 0);
            $stats['completion_rate'] = $row['total'] > 0 ? 
                round(($row['completed'] / $row['total']) * 100) : 0;
        }
        
        return $stats;
    }
}