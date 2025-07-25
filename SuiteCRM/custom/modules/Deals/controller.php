<?php
/**
 * Custom controller for Deals module
 */

require_once('include/MVC/Controller/SugarController.php');

class DealsController extends SugarController
{
    /**
     * Pipeline action - displays deals in pipeline view
     */
    public function action_pipeline()
    {
        // Use the advanced pipeline view with drag and drop functionality
        $this->view = 'pipeline_advanced';
    }
    
    /**
     * Update pipeline stage via AJAX
     */
    public function action_updatePipelineStage()
    {
        global $db, $current_user;
        
        // Set JSON header
        header('Content-Type: application/json');
        
        // Get parameters
        $dealId = $_REQUEST['deal_id'] ?? '';
        $newStage = $_REQUEST['new_stage'] ?? '';
        $oldStage = $_REQUEST['old_stage'] ?? '';
        
        // Validate input
        if (empty($dealId) || empty($newStage)) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing required parameters'
            ]);
            exit;
        }
        
        // Load the deal - Deals module uses Opportunities table
        $deal = BeanFactory::getBean('Opportunities', $dealId);
        
        if (!$deal || empty($deal->id)) {
            echo json_encode([
                'success' => false,
                'message' => 'Deal not found'
            ]);
            exit;
        }
        
        // Update the pipeline stage
        $deal->load_relationship('opportunities_cstm');
        
        // Update custom field
        $updateQuery = "UPDATE opportunities_cstm 
                       SET pipeline_stage_c = '" . $db->quote($newStage) . "',
                           stage_entered_date_c = NOW()
                       WHERE id_c = '" . $db->quote($dealId) . "'";
        
        $db->query($updateQuery);
        
        // Map pipeline stage to sales stage if needed
        $stageMapping = [
            'sourcing' => 'Prospecting',
            'screening' => 'Qualification',
            'analysis_outreach' => 'Needs Analysis',
            'due_diligence' => 'Id. Decision Makers',
            'valuation_structuring' => 'Value Proposition',
            'loi_negotiation' => 'Proposal/Price Quote',
            'financing' => 'Negotiation/Review',
            'closing' => 'Negotiation/Review',
            'closed_owned_90_day' => 'Closed Won',
            'closed_owned_stable' => 'Closed Won',
            'unavailable' => 'Closed Lost'
        ];
        
        if (isset($stageMapping[$newStage])) {
            $deal->sales_stage = $stageMapping[$newStage];
            $deal->save();
        }
        
        // Log the activity
        $this->logStageChange($dealId, $oldStage, $newStage);
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Deal moved successfully',
            'stage_entered_date' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    /**
     * Toggle focus flag on a deal
     */
    public function action_toggleFocus()
    {
        global $db, $current_user;
        
        header('Content-Type: application/json');
        
        $dealId = $_REQUEST['deal_id'] ?? '';
        $focusState = $_REQUEST['focus_state'] ?? 'false';
        $focusState = ($focusState === 'true' || $focusState === '1');
        
        if (empty($dealId)) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing deal ID'
            ]);
            exit;
        }
        
        // Update focus flag
        $focusOrder = 0;
        if ($focusState) {
            // Get next focus order
            $query = "SELECT MAX(focus_order_c) as max_order FROM opportunities_cstm WHERE focus_flag_c = 1";
            $result = $db->query($query);
            $row = $db->fetchByAssoc($result);
            $focusOrder = ($row['max_order'] ?? 0) + 1;
        }
        
        $updateQuery = "UPDATE opportunities_cstm 
                       SET focus_flag_c = " . ($focusState ? '1' : '0') . ",
                           focus_order_c = " . $focusOrder . ",
                           focus_date_c = " . ($focusState ? 'NOW()' : 'NULL') . "
                       WHERE id_c = '" . $db->quote($dealId) . "'";
        
        $db->query($updateQuery);
        
        echo json_encode([
            'success' => true,
            'message' => $focusState ? 'Deal marked as focused' : 'Focus removed from deal',
            'focus_order' => $focusOrder
        ]);
        exit;
    }
    
    /**
     * Update focus order for drag and drop within focused deals
     */
    public function action_updateFocusOrder()
    {
        global $db;
        
        header('Content-Type: application/json');
        
        $dealId = $_REQUEST['deal_id'] ?? '';
        $newOrder = intval($_REQUEST['new_order'] ?? 0);
        $stage = $_REQUEST['stage'] ?? '';
        
        if (empty($dealId)) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing deal ID'
            ]);
            exit;
        }
        
        // Update focus order
        $updateQuery = "UPDATE opportunities_cstm 
                       SET focus_order_c = " . $newOrder . "
                       WHERE id_c = '" . $db->quote($dealId) . "'";
        
        $db->query($updateQuery);
        
        echo json_encode([
            'success' => true,
            'new_order' => $newOrder
        ]);
        exit;
    }
    
    /**
     * Log stage change activity
     */
    private function logStageChange($dealId, $oldStage, $newStage)
    {
        global $db, $current_user;
        
        // Create an audit record
        $auditData = [
            'id' => create_guid(),
            'parent_id' => $dealId,
            'date_created' => date('Y-m-d H:i:s'),
            'created_by' => $current_user->id,
            'field_name' => 'pipeline_stage_c',
            'data_type' => 'varchar',
            'before_value_string' => $oldStage,
            'after_value_string' => $newStage
        ];
        
        // You can implement actual audit logging here if needed
    }
}