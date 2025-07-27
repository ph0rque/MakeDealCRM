<?php
/**
 * Checklist REST API Endpoints
 * 
 * Provides RESTful API access to ChecklistService functionality
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/api/SugarApi.php');
require_once('include/SugarObjects/SugarConfig.php');
require_once('custom/modules/Deals/services/ChecklistService.php');

class ChecklistApi extends SugarApi
{
    private $checklistService;
    
    public function __construct()
    {
        $this->checklistService = new ChecklistService();
    }
    
    /**
     * Register API endpoints
     */
    public function registerApiRest()
    {
        return array(
            // Checklist CRUD operations
            'createChecklist' => array(
                'reqType' => 'POST',
                'path' => array('Deals', '?', 'checklists'),
                'pathVars' => array('module', 'record'),
                'method' => 'createChecklist',
                'shortHelp' => 'Create a checklist from template for a deal',
                'longHelp' => 'custom/modules/Deals/api/help/checklist_create.html',
            ),
            'getChecklists' => array(
                'reqType' => 'GET',
                'path' => array('Deals', '?', 'checklists'),
                'pathVars' => array('module', 'record'),
                'method' => 'getChecklists',
                'shortHelp' => 'Get all checklists for a deal',
                'longHelp' => 'custom/modules/Deals/api/help/checklist_list.html',
            ),
            'getChecklistProgress' => array(
                'reqType' => 'GET',
                'path' => array('Checklists', '?', 'progress'),
                'pathVars' => array('module', 'record'),
                'method' => 'getChecklistProgress',
                'shortHelp' => 'Get detailed progress report for a checklist',
                'longHelp' => 'custom/modules/Deals/api/help/checklist_progress.html',
            ),
            'deleteChecklist' => array(
                'reqType' => 'DELETE',
                'path' => array('Checklists', '?'),
                'pathVars' => array('module', 'record'),
                'method' => 'deleteChecklist',
                'shortHelp' => 'Delete a checklist',
                'longHelp' => 'custom/modules/Deals/api/help/checklist_delete.html',
            ),
            
            // Checklist item operations
            'updateChecklistItem' => array(
                'reqType' => 'PUT',
                'path' => array('ChecklistItems', '?'),
                'pathVars' => array('module', 'record'),
                'method' => 'updateChecklistItem',
                'shortHelp' => 'Update checklist item status',
                'longHelp' => 'custom/modules/Deals/api/help/checklist_item_update.html',
            ),
            'bulkUpdateItems' => array(
                'reqType' => 'POST',
                'path' => array('ChecklistItems', 'bulk'),
                'pathVars' => array('module', 'collection'),
                'method' => 'bulkUpdateItems',
                'shortHelp' => 'Bulk update checklist items',
                'longHelp' => 'custom/modules/Deals/api/help/checklist_bulk_update.html',
            ),
            
            // Template operations
            'getTemplates' => array(
                'reqType' => 'GET',
                'path' => array('ChecklistTemplates'),
                'pathVars' => array('module'),
                'method' => 'getTemplates',
                'shortHelp' => 'Get available checklist templates',
                'longHelp' => 'custom/modules/Deals/api/help/template_list.html',
            ),
            'cloneTemplate' => array(
                'reqType' => 'POST',
                'path' => array('ChecklistTemplates', '?', 'clone'),
                'pathVars' => array('module', 'record', 'action'),
                'method' => 'cloneTemplate',
                'shortHelp' => 'Clone a checklist template',
                'longHelp' => 'custom/modules/Deals/api/help/template_clone.html',
            ),
            
            // Export operations
            'exportChecklist' => array(
                'reqType' => 'GET',
                'path' => array('Checklists', '?', 'export'),
                'pathVars' => array('module', 'record', 'action'),
                'method' => 'exportChecklist',
                'shortHelp' => 'Export checklist to PDF or Excel',
                'longHelp' => 'custom/modules/Deals/api/help/checklist_export.html',
            ),
            
            // Analytics
            'getChecklistAnalytics' => array(
                'reqType' => 'GET',
                'path' => array('Deals', 'checklists', 'analytics'),
                'pathVars' => array('module', 'collection', 'action'),
                'method' => 'getChecklistAnalytics',
                'shortHelp' => 'Get checklist analytics and metrics',
                'longHelp' => 'custom/modules/Deals/api/help/checklist_analytics.html',
            ),
        );
    }
    
    /**
     * Create checklist from template
     */
    public function createChecklist($api, $args)
    {
        $this->requireArgs($args, array('module', 'record', 'template_id'));
        
        $dealId = $args['record'];
        $templateId = $args['template_id'];
        $options = array(
            'create_tasks' => !empty($args['create_tasks']),
            'assigned_user_id' => $args['assigned_user_id'] ?? null
        );
        
        return $this->checklistService->createChecklistFromTemplate($dealId, $templateId, $options);
    }
    
    /**
     * Get all checklists for a deal
     */
    public function getChecklists($api, $args)
    {
        $this->requireArgs($args, array('module', 'record'));
        
        $dealId = $args['record'];
        $filters = array(
            'status' => $args['status'] ?? null,
            'template_id' => $args['template_id'] ?? null
        );
        
        $checklists = $this->checklistService->getDealChecklists($dealId, $filters);
        
        return array(
            'records' => $checklists,
            'count' => count($checklists)
        );
    }
    
    /**
     * Get checklist progress
     */
    public function getChecklistProgress($api, $args)
    {
        $this->requireArgs($args, array('module', 'record'));
        
        $checklistId = $args['record'];
        return $this->checklistService->getChecklistProgress($checklistId);
    }
    
    /**
     * Delete checklist
     */
    public function deleteChecklist($api, $args)
    {
        $this->requireArgs($args, array('module', 'record'));
        
        $checklistId = $args['record'];
        $cascadeDelete = !empty($args['cascade_delete']);
        
        return $this->checklistService->deleteChecklist($checklistId, $cascadeDelete);
    }
    
    /**
     * Update checklist item
     */
    public function updateChecklistItem($api, $args)
    {
        $this->requireArgs($args, array('module', 'record', 'status'));
        
        $itemId = $args['record'];
        $status = $args['status'];
        $data = array(
            'notes' => $args['notes'] ?? null,
            'assigned_user_id' => $args['assigned_user_id'] ?? null
        );
        
        return $this->checklistService->updateChecklistItem($itemId, $status, $data);
    }
    
    /**
     * Bulk update checklist items
     */
    public function bulkUpdateItems($api, $args)
    {
        $this->requireArgs($args, array('item_ids'));
        
        $itemIds = $args['item_ids'];
        if (!is_array($itemIds)) {
            $itemIds = array($itemIds);
        }
        
        $updates = array(
            'status' => $args['status'] ?? null,
            'assigned_user_id' => $args['assigned_user_id'] ?? null,
            'notes' => $args['notes'] ?? null
        );
        
        return $this->checklistService->bulkUpdateItems($itemIds, $updates);
    }
    
    /**
     * Get available templates
     */
    public function getTemplates($api, $args)
    {
        $filters = array(
            'category' => $args['category'] ?? null,
            'search' => $args['search'] ?? null
        );
        
        $templates = $this->checklistService->getAvailableTemplates($filters);
        
        return array(
            'records' => $templates,
            'count' => count($templates)
        );
    }
    
    /**
     * Clone template
     */
    public function cloneTemplate($api, $args)
    {
        $this->requireArgs($args, array('module', 'record', 'new_name'));
        
        $templateId = $args['record'];
        $newName = $args['new_name'];
        $options = array(
            'category' => $args['category'] ?? null,
            'is_public' => isset($args['is_public']) ? (bool)$args['is_public'] : null,
            'description' => $args['description'] ?? null
        );
        
        return $this->checklistService->cloneTemplate($templateId, $newName, $options);
    }
    
    /**
     * Export checklist
     */
    public function exportChecklist($api, $args)
    {
        $this->requireArgs($args, array('module', 'record'));
        
        $checklistId = $args['record'];
        $format = $args['format'] ?? 'pdf';
        
        // Set appropriate headers based on format
        if ($format === 'pdf') {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="checklist_' . $checklistId . '.pdf"');
        } else {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="checklist_' . $checklistId . '.csv"');
        }
        
        return $this->checklistService->exportChecklist($checklistId, $format);
    }
    
    /**
     * Get checklist analytics
     */
    public function getChecklistAnalytics($api, $args)
    {
        $dealId = $args['deal_id'] ?? null;
        $dateRange = array(
            'start' => $args['start_date'] ?? null,
            'end' => $args['end_date'] ?? null
        );
        
        return $this->checklistService->getChecklistAnalytics($dealId, $dateRange);
    }
}