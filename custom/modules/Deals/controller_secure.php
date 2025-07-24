<?php
/**
 * Secure controller for Deals module
 * Handles AJAX requests for Pipeline functionality with proper security
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/MVC/Controller/SugarController.php');
require_once('include/database/DBManagerFactory.php');
require_once('include/utils.php');

class DealsController extends SugarController
{
    /**
     * Update deal pipeline stage via AJAX
     */
    public function action_updatePipelineStage()
    {
        global $current_user, $db;
        
        // Check permissions
        if (!$current_user->id) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Unauthorized']);
            return;
        }
        
        // Get and validate parameters
        $dealId = $this->validateGUID($_POST['deal_id'] ?? '');
        $newStage = $this->validatePipelineStage($_POST['new_stage'] ?? '');
        $oldStage = $this->validatePipelineStage($_POST['old_stage'] ?? '');
        
        if (!$dealId || !$newStage) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Missing required parameters']);
            return;
        }
        
        // Load the deal using BeanFactory (safe from SQL injection)
        $deal = BeanFactory::getBean('Opportunities', $dealId);
        if (!$deal || $deal->deleted) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Deal not found']);
            return;
        }
        
        // Check if user has access to this deal
        if (!$deal->ACLAccess('edit')) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Access denied']);
            return;
        }
        
        // Update the pipeline stage (using Bean properties is safe)
        $deal->pipeline_stage_c = $newStage;
        $deal->stage_entered_date_c = date('Y-m-d H:i:s');
        
        // Map pipeline stage to sales stage
        $salesStageMapping = $this->getPipelineToSalesStageMapping();
        if (isset($salesStageMapping[$newStage])) {
            $deal->sales_stage = $salesStageMapping[$newStage];
        }
        
        // Save the deal
        $deal->save();
        
        // Log the stage change
        $this->logStageChange($dealId, $oldStage, $newStage, $current_user->id);
        
        // Return success response with XSS-safe data
        $this->sendJsonResponse([
            'success' => true,
            'message' => 'Deal stage updated successfully',
            'stage_entered_date' => htmlspecialchars($deal->stage_entered_date_c, ENT_QUOTES, 'UTF-8'),
            'sales_stage' => htmlspecialchars($deal->sales_stage, ENT_QUOTES, 'UTF-8')
        ]);
    }
    
    /**
     * Validate GUID format
     */
    private function validateGUID($guid)
    {
        // SugarCRM GUID format: 36 chars, alphanumeric with hyphens
        if (preg_match('/^[a-zA-Z0-9]{8}-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{12}$/', $guid)) {
            return $guid;
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
        
        return in_array($stage, $validStages) ? $stage : false;
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
     * Log stage change in audit table using prepared statements
     */
    private function logStageChange($dealId, $oldStage, $newStage, $userId)
    {
        global $db;
        
        // First check if table exists
        $tableCheck = "SHOW TABLES LIKE ?";
        $stmt = $db->getConnection()->prepare($tableCheck);
        $tableName = 'pipeline_stage_history';
        $stmt->bind_param('s', $tableName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Generate UUID
            $id = create_guid();
            
            // Use prepared statement for insert
            $query = "INSERT INTO pipeline_stage_history 
                      (id, deal_id, old_stage, new_stage, changed_by, date_changed) 
                      VALUES 
                      (?, ?, ?, ?, ?, NOW())";
            
            $stmt = $db->getConnection()->prepare($query);
            $stmt->bind_param('sssss', $id, $dealId, $oldStage, $newStage, $userId);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    /**
     * Send JSON response with proper headers
     */
    private function sendJsonResponse($data)
    {
        ob_clean();
        header('Content-Type: application/json');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        echo json_encode($data);
        sugar_cleanup(true);
    }
}