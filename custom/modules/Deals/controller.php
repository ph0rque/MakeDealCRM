<?php
/**
 * Custom controller for Deals module
 * Handles AJAX requests for Pipeline functionality
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/MVC/Controller/SugarController.php');

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
        
        // Get parameters
        $dealId = $db->quote($_POST['deal_id'] ?? '');
        $newStage = $db->quote($_POST['new_stage'] ?? '');
        $oldStage = $db->quote($_POST['old_stage'] ?? '');
        
        if (!$dealId || !$newStage) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Missing required parameters']);
            return;
        }
        
        // Load the deal
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
        
        // Update the pipeline stage
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
        
        // Return success response
        $this->sendJsonResponse([
            'success' => true,
            'message' => 'Deal stage updated successfully',
            'stage_entered_date' => $deal->stage_entered_date_c,
            'sales_stage' => $deal->sales_stage
        ]);
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
     * Log stage change in audit table
     */
    private function logStageChange($dealId, $oldStage, $newStage, $userId)
    {
        global $db;
        
        $query = "INSERT INTO pipeline_stage_history 
                  (id, deal_id, old_stage, new_stage, changed_by, date_changed) 
                  VALUES 
                  (UUID(), '$dealId', '$oldStage', '$newStage', '$userId', NOW())";
        
        // Execute only if table exists
        $tableCheck = "SHOW TABLES LIKE 'pipeline_stage_history'";
        $result = $db->query($tableCheck);
        if ($db->fetchByAssoc($result)) {
            $db->query($query);
        }
    }
    
    /**
     * Send JSON response
     */
    private function sendJsonResponse($data)
    {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($data);
        sugar_cleanup(true);
    }
}