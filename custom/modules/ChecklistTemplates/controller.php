<?php
/**
 * Controller for ChecklistTemplates Module
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/MVC/Controller/SugarController.php');

class ChecklistTemplatesController extends SugarController
{
    /**
     * Get template list for AJAX
     */
    public function action_GetTemplateList()
    {
        global $db, $current_user;
        
        $this->view = 'json';
        
        // Build query to get active templates
        $sql = "SELECT 
                    ct.id,
                    ct.name,
                    ct.description,
                    ct.category,
                    ct.item_count,
                    ct.created_by,
                    u.user_name as created_by_name
                FROM checklist_templates ct
                LEFT JOIN users u ON ct.created_by = u.id
                WHERE ct.deleted = 0 
                AND ct.is_active = 1
                AND (ct.is_public = 1 OR ct.created_by = '{$current_user->id}')
                ORDER BY ct.category, ct.name";
        
        $result = $db->query($sql);
        $templates = array();
        
        while ($row = $db->fetchByAssoc($result)) {
            $templates[] = array(
                'id' => $row['id'],
                'name' => $row['name'],
                'description' => $row['description'],
                'category' => $row['category'],
                'item_count' => (int)$row['item_count'],
                'created_by' => $row['created_by'],
                'created_by_name' => $row['created_by_name']
            );
        }
        
        echo json_encode(array(
            'success' => true,
            'templates' => $templates
        ));
        exit;
    }
    
    /**
     * Preview template
     */
    public function action_Preview()
    {
        global $db;
        
        $templateId = $db->quote($_REQUEST['template_id']);
        
        $template = BeanFactory::getBean('ChecklistTemplates', $templateId);
        
        if (!$template || $template->deleted) {
            sugar_die('Template not found');
        }
        
        // Get template items
        $sql = "SELECT * FROM checklist_items 
                WHERE template_id = '{$templateId}' 
                AND deleted = 0
                ORDER BY order_number ASC";
        
        $result = $db->query($sql);
        $items = array();
        
        while ($row = $db->fetchByAssoc($result)) {
            $items[] = $row;
        }
        
        // Display preview
        include('custom/modules/ChecklistTemplates/views/preview.php');
    }
    
    /**
     * Clone template
     */
    public function action_Clone()
    {
        $templateId = $_REQUEST['record'];
        
        $template = BeanFactory::getBean('ChecklistTemplates', $templateId);
        
        if (!$template || $template->deleted) {
            SugarApplication::appendErrorMessage('Template not found');
            SugarApplication::redirect('index.php?module=ChecklistTemplates&action=index');
        }
        
        $newName = 'Copy of ' . $template->name;
        $clone = $template->cloneTemplate($newName);
        
        SugarApplication::appendSuccessMessage('Template cloned successfully');
        SugarApplication::redirect('index.php?module=ChecklistTemplates&action=DetailView&record=' . $clone->id);
    }
}