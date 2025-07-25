<?php
/**
 * Secure controller for Deals module
 * Handles AJAX requests for Pipeline functionality with comprehensive security
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/MVC/Controller/SugarController.php');
require_once('custom/modules/Deals/DealsSecurityHelper.php');
require_once('custom/modules/Deals/api/StateSync.php');
require_once('custom/modules/Deals/api/StageTransitionService.php');
require_once('custom/modules/Deals/api/TimeTrackingService.php');
require_once('custom/modules/Deals/api/StakeholderIntegrationApi.php');

class DealsController extends SugarController
{
    /**
     * Default action - redirect to Pipeline view
     */
    public function action_index()
    {
        // Check access
        if (!DealsSecurityHelper::checkModuleAccess('Deals', 'list')) {
            sugar_die('Unauthorized access to Deals module');
        }
        
        // For AJAX requests, handle the redirect properly
        if (!empty($_REQUEST['ajax_load']) || !empty($_REQUEST['ajaxLoad'])) {
            // Load the pipeline view for AJAX
            $this->view = 'pipeline';
        } else {
            // For non-AJAX, use regular redirect
            sugar_redirect('index.php?module=Deals&action=pipeline');
        }
    }
    
    /**
     * Override list view to redirect to pipeline
     */
    public function action_listview()
    {
        // Redirect list view to pipeline as well
        $this->action_index();
    }
    
    /**
     * Display the Pipeline view
     */
    public function action_pipeline()
    {
        // Check access
        if (!DealsSecurityHelper::checkModuleAccess('Deals', 'list')) {
            sugar_die('Unauthorized access to Deals module');
        }
        
        $this->view = 'pipeline';
    }
    
    /**
     * Update deal pipeline stage via AJAX (Enhanced with validation)
     */
    public function action_updatePipelineStage()
    {
        global $current_user, $db;
        
        // Check CSRF token
        if (!$this->validateCSRFToken()) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Invalid security token']);
            return;
        }
        
        // Check permissions
        if (!DealsSecurityHelper::checkModuleAccess('Deals', 'edit')) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Unauthorized']);
            return;
        }
        
        // Rate limiting check
        if (!DealsSecurityHelper::checkRateLimit('updatePipelineStage', $current_user->id, 30, 60)) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Rate limit exceeded']);
            return;
        }
        
        // Get and sanitize parameters
        $dealId = DealsSecurityHelper::sanitizeInput($_POST['deal_id'] ?? '', 'sql');
        $newStage = DealsSecurityHelper::sanitizeInput($_POST['new_stage'] ?? '', 'sql');
        $oldStage = DealsSecurityHelper::sanitizeInput($_POST['old_stage'] ?? '', 'sql');
        $options = DealsSecurityHelper::sanitizeInput($_POST['options'] ?? [], 'default');
        
        // Validate inputs
        if (!$this->validateGUID($dealId) || !$this->validatePipelineStage($newStage)) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Invalid parameters']);
            return;
        }
        
        // Load the deal
        $deal = BeanFactory::getBean('Opportunities', $dealId);
        if (!$deal || $deal->deleted) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Deal not found']);
            return;
        }
        
        // Check record-level access
        if (!DealsSecurityHelper::checkRecordAccess($deal, 'edit')) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Access denied']);
            DealsSecurityHelper::logSecurityEvent('access_denied', 'Attempt to update pipeline stage', array(
                'deal_id' => $dealId,
                'user_id' => $current_user->id
            ));
            return;
        }
        
        // Use the enhanced Stage Transition Service
        $transitionService = new StageTransitionService();
        $result = $transitionService->transitionDeal($dealId, $oldStage, $newStage, $current_user->id, $options);
        
        // Log the action
        DealsSecurityHelper::logSecurityEvent('pipeline_stage_update', 'Pipeline stage updated', array(
            'deal_id' => $dealId,
            'old_stage' => $oldStage,
            'new_stage' => $newStage,
            'success' => $result['success']
        ));
        
        $this->sendJsonResponse($result);
    }
    
    /**
     * Toggle focus flag for a deal via AJAX
     */
    public function action_toggleFocus()
    {
        global $current_user, $db;
        
        // Check CSRF token
        if (!$this->validateCSRFToken()) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Invalid security token']);
            return;
        }
        
        // Check permissions
        if (!DealsSecurityHelper::checkModuleAccess('Deals', 'edit')) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Unauthorized']);
            return;
        }
        
        // Get and sanitize parameters
        $dealId = DealsSecurityHelper::sanitizeInput($_POST['deal_id'] ?? '', 'sql');
        $focusState = isset($_POST['focus_state']) ? (bool)$_POST['focus_state'] : false;
        
        // Validate deal ID
        if (!$this->validateGUID($dealId)) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Invalid deal ID']);
            return;
        }
        
        // Load the deal
        $deal = BeanFactory::getBean('Opportunities', $dealId);
        if (!$deal || $deal->deleted) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Deal not found']);
            return;
        }
        
        // Check record-level access
        if (!DealsSecurityHelper::checkRecordAccess($deal, 'edit')) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Access denied']);
            return;
        }
        
        // Ensure custom table record exists
        if (!$deal->id_c) {
            // Create custom table record if missing - using prepared statement
            $query = DealsSecurityHelper::prepareSQLQuery(
                "INSERT INTO opportunities_cstm (id_c) VALUES (:id) ON DUPLICATE KEY UPDATE id_c=id_c",
                array('id' => $deal->id)
            );
            $db->query($query);
            $deal->retrieve($deal->id); // Reload to get custom fields
        }
        
        // Update the focus flag
        $deal->focus_flag_c = $focusState ? 1 : 0;
        $deal->focus_date_c = $focusState ? date('Y-m-d H:i:s') : null;
        
        // If focusing, set order to be at the top of the stage
        if ($focusState) {
            // Get the highest focus_order in this stage using prepared statement
            $query = DealsSecurityHelper::prepareSQLQuery(
                "SELECT MAX(focus_order_c) as max_order 
                 FROM opportunities_cstm 
                 JOIN opportunities ON opportunities.id = opportunities_cstm.id_c 
                 WHERE pipeline_stage_c = :stage 
                 AND focus_flag_c = 1 
                 AND opportunities.deleted = 0",
                array('stage' => $deal->pipeline_stage_c)
            );
            
            $result = $db->query($query);
            $row = $db->fetchByAssoc($result);
            $maxOrder = $row['max_order'] ?? 0;
            
            $deal->focus_order_c = $maxOrder + 1;
        } else {
            $deal->focus_order_c = 0;
        }
        
        // Save the deal
        $deal->save();
        
        // Return success response
        $this->sendJsonResponse([
            'success' => true,
            'message' => $focusState ? 'Deal marked as focused' : 'Focus removed from deal',
            'focus_flag' => $deal->focus_flag_c,
            'focus_order' => $deal->focus_order_c
        ]);
    }
    
    /**
     * Quick search for contacts (used in stakeholder integration)
     */
    public function action_quicksearch()
    {
        global $db;
        
        // Check permissions
        if (!DealsSecurityHelper::checkModuleAccess('Contacts', 'list')) {
            $this->sendJsonResponse(['results' => []]);
            return;
        }
        
        // Sanitize input
        $query = DealsSecurityHelper::sanitizeInput($_GET['query'] ?? '', 'sql');
        $limit = intval($_GET['limit'] ?? 10);
        $limit = min($limit, 50); // Cap at 50 results
        
        if (strlen($query) < 2) {
            $this->sendJsonResponse(['results' => []]);
            return;
        }
        
        // Use prepared statement for search
        $sql = DealsSecurityHelper::prepareSQLQuery(
            "SELECT 
                c.id,
                CONCAT(c.first_name, ' ', c.last_name) as name,
                c.title,
                a.name as account_name
            FROM contacts c
            LEFT JOIN accounts a ON c.account_id = a.id AND a.deleted = 0
            WHERE c.deleted = 0
            AND (
                c.first_name LIKE :query1 OR
                c.last_name LIKE :query2 OR
                CONCAT(c.first_name, ' ', c.last_name) LIKE :query3 OR
                c.email1 LIKE :query4 OR
                a.name LIKE :query5
            )
            ORDER BY c.last_name, c.first_name
            LIMIT :limit",
            array(
                'query1' => '%' . $query . '%',
                'query2' => '%' . $query . '%',
                'query3' => '%' . $query . '%',
                'query4' => '%' . $query . '%',
                'query5' => '%' . $query . '%',
                'limit' => $limit
            )
        );
        
        $result = $db->query($sql);
        $contacts = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $contacts[] = [
                'id' => $row['id'],
                'name' => DealsSecurityHelper::encodeOutput(trim($row['name'])),
                'title' => DealsSecurityHelper::encodeOutput($row['title']),
                'account_name' => DealsSecurityHelper::encodeOutput($row['account_name'])
            ];
        }
        
        $this->sendJsonResponse(['results' => $contacts]);
    }
    
    /**
     * Validate GUID format
     */
    private function validateGUID($guid)
    {
        // SugarCRM GUID format: 36 chars, alphanumeric with hyphens
        if (preg_match('/^[a-zA-Z0-9]{8}-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{12}$/', $guid)) {
            return true;
        }
        return false;
    }
    
    /**
     * Validate pipeline stage value
     */
    private function validatePipelineStage($stage)
    {
        $validStages = [
            'sourcing',
            'screening', 
            'analysis_outreach',
            'due_diligence',
            'valuation_structuring',
            'loi_negotiation',
            'financing',
            'closing',
            'closed_owned_90_day',
            'closed_owned_stable',
            'unavailable'
        ];
        
        return in_array($stage, $validStages);
    }
    
    /**
     * Validate CSRF token
     */
    private function validateCSRFToken()
    {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return DealsSecurityHelper::validateCSRFToken($token);
    }
    
    /**
     * Get pipeline to sales stage mapping
     */
    private function getPipelineToSalesStageMapping()
    {
        return [
            'sourcing' => 'Prospecting',
            'screening' => 'Qualification',
            'analysis_outreach' => 'Needs Analysis',
            'due_diligence' => 'Id. Decision Makers',
            'valuation_structuring' => 'Value Proposition',
            'loi_negotiation' => 'Negotiation/Review',
            'financing' => 'Proposal/Price Quote',
            'closing' => 'Negotiation/Review',
            'closed_owned_90_day' => 'Closed Won',
            'closed_owned_stable' => 'Closed Won',
            'unavailable' => 'Closed Lost'
        ];
    }
    
    /**
     * Send JSON response with security headers
     */
    private function sendJsonResponse($data)
    {
        ob_clean();
        header('Content-Type: application/json');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Content-Security-Policy: default-src \'self\'');
        echo json_encode($data);
        sugar_cleanup(true);
    }
}