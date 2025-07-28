<?php
/**
 * Custom controller for Deals module
 * Handles AJAX requests for Pipeline functionality
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

// Adjust paths for custom module location
require_once('include/MVC/Controller/SugarController.php');

// Check if API files exist before including them
$apiPath = dirname(dirname(dirname(dirname(__FILE__)))) . '/custom/modules/Deals/api/';
if (file_exists($apiPath . 'StateSync.php')) {
    require_once($apiPath . 'StateSync.php');
}
if (file_exists($apiPath . 'StageTransitionService.php')) {
    require_once($apiPath . 'StageTransitionService.php');
}
if (file_exists($apiPath . 'TimeTrackingService.php')) {
    require_once($apiPath . 'TimeTrackingService.php');
}
if (file_exists($apiPath . 'StakeholderIntegrationApi.php')) {
    require_once($apiPath . 'StakeholderIntegrationApi.php');
}

class DealsController extends SugarController
{
    /**
     * Default action - redirect to Pipeline view
     */
    public function action_index()
    {
        // Always load the pipeline view directly
        $this->view = 'pipeline';
    }
    
    /**
     * Override list view to redirect to pipeline (handles both listview and ListView)
     */
    public function action_listview()
    {
        // Load the pipeline view directly instead of redirecting
        $this->view = 'pipeline';
    }
    
    /**
     * Display the Pipeline view
     */
    public function action_pipeline()
    {
        $this->view = 'pipeline';
    }
    
    /**
     * Display the Financial Dashboard view
     */
    public function action_financialdashboard()
    {
        $this->view = 'financialdashboard';
    }
    
    /**
     * Update deal pipeline stage via AJAX (Enhanced with validation)
     */
    public function action_updatePipelineStage()
    {
        global $current_user, $db;
        
        try {
            // Check permissions
            if (!$current_user->id) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Unauthorized']);
                return;
            }
            
            // Validate and sanitize parameters
            $dealId = $this->validateAndSanitize($_POST['deal_id'] ?? '', 'guid');
            $newStage = $this->validateAndSanitize($_POST['new_stage'] ?? '', 'pipeline_stage');
            $oldStage = $this->validateAndSanitize($_POST['old_stage'] ?? '', 'pipeline_stage');
            $options = $this->validateOptionsArray($_POST['options'] ?? []);
            
            if (!$dealId || !$newStage) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Missing or invalid required parameters']);
                return;
            }
            
            // Check if deal exists and user has access
            $deal = BeanFactory::getBean('Opportunities', $dealId);
            if (!$deal || $deal->deleted || !$deal->ACLAccess('edit')) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Deal not found or access denied']);
                return;
            }
            
            // Use the enhanced Stage Transition Service
            $transitionService = new StageTransitionService();
            $result = $transitionService->transitionDeal($dealId, $oldStage, $newStage, $current_user->id, $options);
            
            // Log the action
            $GLOBALS['log']->info("Deal stage updated: {$dealId} from {$oldStage} to {$newStage} by {$current_user->id}");
            
            $this->sendJsonResponse($result);
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('Update pipeline stage failed: ' . $e->getMessage());
            $this->sendJsonResponse(['success' => false, 'message' => 'Internal error occurred']);
        }
    }
    
    /**
     * Create new deal via AJAX
     */
    public function action_createDeal()
    {
        global $current_user;
        
        try {
            // Check permissions
            if (!$current_user->id || !ACLController::checkAccess('Deals', 'edit', true)) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Unauthorized']);
                return;
            }
            
            // Validate required fields
            $name = $this->validateAndSanitize($_POST['name'] ?? '', 'string');
            if (empty($name)) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Deal name is required']);
                return;
            }
            
            // Create new deal
            $deal = BeanFactory::newBean('Opportunities');
            $deal->name = $name;
            $deal->amount = $this->validateAndSanitize($_POST['amount'] ?? '', 'currency');
            $deal->account_id = $this->validateAndSanitize($_POST['account_id'] ?? '', 'guid');
            $deal->assigned_user_id = $_POST['assigned_user_id'] ?? $current_user->id;
            $deal->date_closed = $this->validateAndSanitize($_POST['date_closed'] ?? '', 'date');
            $deal->probability = $this->validateAndSanitize($_POST['probability'] ?? 50, 'integer');
            $deal->sales_stage = $_POST['sales_stage'] ?? 'Prospecting';
            $deal->lead_source = $this->validateAndSanitize($_POST['lead_source'] ?? '', 'string');
            $deal->description = $this->validateAndSanitize($_POST['description'] ?? '', 'text');
            
            // Pipeline-specific fields
            $deal->pipeline_stage_c = $this->validateAndSanitize($_POST['pipeline_stage_c'] ?? 'sourcing', 'pipeline_stage');
            $deal->expected_close_date_c = $this->validateAndSanitize($_POST['expected_close_date_c'] ?? '', 'date');
            $deal->deal_source_c = $this->validateAndSanitize($_POST['deal_source_c'] ?? '', 'string');
            $deal->pipeline_notes_c = $this->validateAndSanitize($_POST['pipeline_notes_c'] ?? '', 'text');
            
            // Save the deal
            $dealId = $deal->save();
            
            if ($dealId) {
                $GLOBALS['log']->info("New deal created: {$dealId} by {$current_user->id}");
                $this->sendJsonResponse([
                    'success' => true,
                    'message' => 'Deal created successfully',
                    'deal_id' => $dealId,
                    'deal_name' => $deal->name
                ]);
            } else {
                throw new Exception('Failed to save deal');
            }
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('Create deal failed: ' . $e->getMessage());
            $this->sendJsonResponse(['success' => false, 'message' => 'Failed to create deal: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Update deal via AJAX
     */
    public function action_updateDeal()
    {
        global $current_user;
        
        try {
            // Check permissions
            if (!$current_user->id) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Unauthorized']);
                return;
            }
            
            $dealId = $this->validateAndSanitize($_POST['deal_id'] ?? '', 'guid');
            if (!$dealId) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Deal ID is required']);
                return;
            }
            
            // Load the deal
            $deal = BeanFactory::getBean('Opportunities', $dealId);
            if (!$deal || $deal->deleted || !$deal->ACLAccess('edit')) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Deal not found or access denied']);
                return;
            }
            
            // Update fields if provided
            if (isset($_POST['name'])) {
                $deal->name = $this->validateAndSanitize($_POST['name'], 'string');
            }
            if (isset($_POST['amount'])) {
                $deal->amount = $this->validateAndSanitize($_POST['amount'], 'currency');
            }
            if (isset($_POST['account_id'])) {
                $deal->account_id = $this->validateAndSanitize($_POST['account_id'], 'guid');
            }
            if (isset($_POST['assigned_user_id'])) {
                $deal->assigned_user_id = $this->validateAndSanitize($_POST['assigned_user_id'], 'guid');
            }
            if (isset($_POST['date_closed'])) {
                $deal->date_closed = $this->validateAndSanitize($_POST['date_closed'], 'date');
            }
            if (isset($_POST['probability'])) {
                $deal->probability = $this->validateAndSanitize($_POST['probability'], 'integer');
            }
            if (isset($_POST['sales_stage'])) {
                $deal->sales_stage = $this->validateAndSanitize($_POST['sales_stage'], 'string');
            }
            if (isset($_POST['description'])) {
                $deal->description = $this->validateAndSanitize($_POST['description'], 'text');
            }
            
            // Pipeline-specific updates
            if (isset($_POST['pipeline_stage_c'])) {
                $deal->pipeline_stage_c = $this->validateAndSanitize($_POST['pipeline_stage_c'], 'pipeline_stage');
            }
            if (isset($_POST['expected_close_date_c'])) {
                $deal->expected_close_date_c = $this->validateAndSanitize($_POST['expected_close_date_c'], 'date');
            }
            if (isset($_POST['deal_source_c'])) {
                $deal->deal_source_c = $this->validateAndSanitize($_POST['deal_source_c'], 'string');
            }
            if (isset($_POST['pipeline_notes_c'])) {
                $deal->pipeline_notes_c = $this->validateAndSanitize($_POST['pipeline_notes_c'], 'text');
            }
            
            // Save the deal
            $result = $deal->save();
            
            if ($result) {
                $GLOBALS['log']->info("Deal updated: {$dealId} by {$current_user->id}");
                $this->sendJsonResponse([
                    'success' => true,
                    'message' => 'Deal updated successfully',
                    'deal_id' => $dealId
                ]);
            } else {
                throw new Exception('Failed to save deal updates');
            }
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('Update deal failed: ' . $e->getMessage());
            $this->sendJsonResponse(['success' => false, 'message' => 'Failed to update deal: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Delete deal via AJAX
     */
    public function action_deleteDeal()
    {
        global $current_user;
        
        try {
            // Check permissions
            if (!$current_user->id) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Unauthorized']);
                return;
            }
            
            $dealId = $this->validateAndSanitize($_POST['deal_id'] ?? '', 'guid');
            if (!$dealId) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Deal ID is required']);
                return;
            }
            
            // Load the deal
            $deal = BeanFactory::getBean('Opportunities', $dealId);
            if (!$deal || $deal->deleted || !$deal->ACLAccess('delete')) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Deal not found or access denied']);
                return;
            }
            
            $dealName = $deal->name;
            
            // Delete the deal
            $result = $deal->mark_deleted($dealId);
            
            if ($result) {
                $GLOBALS['log']->info("Deal deleted: {$dealId} ({$dealName}) by {$current_user->id}");
                $this->sendJsonResponse([
                    'success' => true,
                    'message' => 'Deal deleted successfully',
                    'deal_id' => $dealId
                ]);
            } else {
                throw new Exception('Failed to delete deal');
            }
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('Delete deal failed: ' . $e->getMessage());
            $this->sendJsonResponse(['success' => false, 'message' => 'Failed to delete deal: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Search deals via AJAX
     */
    public function action_searchDeals()
    {
        global $current_user;
        
        try {
            // Check permissions
            if (!$current_user->id || !ACLController::checkAccess('Deals', 'list', true)) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Unauthorized']);
                return;
            }
            
            $searchParams = array();
            
            // Build search parameters
            if (!empty($_GET['name'])) {
                $searchParams['name'] = $this->validateAndSanitize($_GET['name'], 'string');
            }
            if (!empty($_GET['account_name'])) {
                $searchParams['account_name'] = $this->validateAndSanitize($_GET['account_name'], 'string');
            }
            if (!empty($_GET['pipeline_stage'])) {
                $searchParams['pipeline_stage'] = $this->validateAndSanitize($_GET['pipeline_stage'], 'pipeline_stage');
            }
            if (!empty($_GET['min_amount'])) {
                $searchParams['min_amount'] = $this->validateAndSanitize($_GET['min_amount'], 'currency');
            }
            if (!empty($_GET['max_amount'])) {
                $searchParams['max_amount'] = $this->validateAndSanitize($_GET['max_amount'], 'currency');
            }
            if (!empty($_GET['start_date'])) {
                $searchParams['start_date'] = $this->validateAndSanitize($_GET['start_date'], 'date');
            }
            if (!empty($_GET['end_date'])) {
                $searchParams['end_date'] = $this->validateAndSanitize($_GET['end_date'], 'date');
            }
            if (!empty($_GET['assigned_user_id'])) {
                $searchParams['assigned_user_id'] = $this->validateAndSanitize($_GET['assigned_user_id'], 'guid');
            }
            
            $limit = $this->validateAndSanitize($_GET['limit'] ?? 50, 'integer');
            $searchParams['limit'] = min($limit, 500); // Cap at 500 results
            
            // Perform search
            $deal = BeanFactory::newBean('Opportunities');
            $results = $deal->searchDeals($searchParams);
            
            $this->sendJsonResponse([
                'success' => true,
                'count' => count($results),
                'deals' => $results
            ]);
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('Search deals failed: ' . $e->getMessage());
            $this->sendJsonResponse(['success' => false, 'message' => 'Search failed']);
        }
    }
    
    /**
     * Toggle focus flag for a deal via AJAX
     */
    public function action_toggleFocus()
    {
        global $current_user, $db;
        
        // Check permissions
        if (!$current_user->id) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Unauthorized']);
            return;
        }
        
        // Get parameters
        $dealId = $db->quote($_POST['deal_id'] ?? '');
        $focusState = isset($_POST['focus_state']) ? (bool)$_POST['focus_state'] : false;
        
        if (!$dealId) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Missing deal ID']);
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
        
        // Ensure custom table record exists
        if (!$deal->id_c) {
            // Create custom table record if missing
            $db->query("INSERT INTO opportunities_cstm (id_c) VALUES ('{$deal->id}') ON DUPLICATE KEY UPDATE id_c=id_c");
            $deal->retrieve($deal->id); // Reload to get custom fields
        }
        
        // Update the focus flag
        $deal->focus_flag_c = $focusState ? 1 : 0;
        $deal->focus_date_c = $focusState ? date('Y-m-d H:i:s') : null;
        
        // If focusing, set order to be at the top of the stage
        if ($focusState) {
            // Get the highest focus_order in this stage
            $query = "SELECT MAX(focus_order_c) as max_order 
                      FROM opportunities_cstm 
                      JOIN opportunities ON opportunities.id = opportunities_cstm.id_c 
                      WHERE pipeline_stage_c = '{$deal->pipeline_stage_c}' 
                      AND focus_flag_c = 1 
                      AND opportunities.deleted = 0";
            
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
     * Update focus order for reordering focused deals
     */
    public function action_updateFocusOrder()
    {
        global $current_user, $db;
        
        // Check permissions
        if (!$current_user->id) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Unauthorized']);
            return;
        }
        
        // Get parameters
        $dealId = $db->quote($_POST['deal_id'] ?? '');
        $newOrder = intval($_POST['new_order'] ?? 0);
        $stage = $db->quote($_POST['stage'] ?? '');
        
        if (!$dealId || !$stage) {
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
        
        // Update the focus order
        $oldOrder = $deal->focus_order_c;
        $deal->focus_order_c = $newOrder;
        $deal->save();
        
        // Reorder other focused deals in the stage
        if ($oldOrder != $newOrder) {
            if ($newOrder > $oldOrder) {
                // Moving down - decrease order of deals between old and new position
                $query = "UPDATE opportunities_cstm 
                          JOIN opportunities ON opportunities.id = opportunities_cstm.id_c 
                          SET focus_order_c = focus_order_c - 1 
                          WHERE pipeline_stage_c = '$stage' 
                          AND focus_flag_c = 1 
                          AND focus_order_c > $oldOrder 
                          AND focus_order_c <= $newOrder 
                          AND id_c != '$dealId' 
                          AND opportunities.deleted = 0";
            } else {
                // Moving up - increase order of deals between new and old position
                $query = "UPDATE opportunities_cstm 
                          JOIN opportunities ON opportunities.id = opportunities_cstm.id_c 
                          SET focus_order_c = focus_order_c + 1 
                          WHERE pipeline_stage_c = '$stage' 
                          AND focus_flag_c = 1 
                          AND focus_order_c >= $newOrder 
                          AND focus_order_c < $oldOrder 
                          AND id_c != '$dealId' 
                          AND opportunities.deleted = 0";
            }
            
            $db->query($query);
        }
        
        // Return success response
        $this->sendJsonResponse([
            'success' => true,
            'message' => 'Focus order updated successfully',
            'new_order' => $deal->focus_order_c
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
     * State sync endpoint
     */
    public function action_stateSync()
    {
        $stateSyncAPI = new StateSyncAPI();
        $stateSyncAPI->action_sync();
    }
    
    /**
     * Get current state endpoint
     */
    public function action_getState()
    {
        $stateSyncAPI = new StateSyncAPI();
        $stateSyncAPI->action_getState();
    }
    
    /**
     * Reset state endpoint (admin only)
     */
    public function action_resetState()
    {
        $stateSyncAPI = new StateSyncAPI();
        $stateSyncAPI->action_resetState();
    }
    
    /**
     * Get state metrics endpoint
     */
    public function action_getStateMetrics()
    {
        $stateSyncAPI = new StateSyncAPI();
        $stateSyncAPI->action_getMetrics();
    }
    
    /**
     * Get WIP limits configuration
     */
    public function action_getWIPLimits()
    {
        global $current_user, $db;
        
        // Check permissions
        if (!$current_user->id) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Unauthorized']);
            return;
        }
        
        // Default WIP limits
        $defaultLimits = [
            'sourcing' => 20,
            'screening' => 15,
            'analysis_outreach' => 10,
            'due_diligence' => 8,
            'valuation_structuring' => 6,
            'loi_negotiation' => 5,
            'financing' => 5,
            'closing' => 5,
            'closed_owned_90_day' => 10,
            'closed_owned_stable' => null,
            'unavailable' => null
        ];
        
        // Try to load custom limits from config or database
        $customLimits = $this->loadWIPLimitsFromConfig();
        $limits = array_merge($defaultLimits, $customLimits);
        
        $this->sendJsonResponse([
            'success' => true,
            'limits' => $limits,
            'default_limits' => $defaultLimits
        ]);
    }
    
    /**
     * Save WIP limits configuration
     */
    public function action_saveWIPLimits()
    {
        global $current_user, $db;
        
        // Check permissions - only admin can modify limits
        if (!$current_user->id || !$current_user->is_admin) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Admin access required']);
            return;
        }
        
        // Get the limits data
        $limitsJson = $_POST['limits'] ?? '';
        if (!$limitsJson) {
            $this->sendJsonResponse(['success' => false, 'message' => 'No limits data provided']);
            return;
        }
        
        $limits = json_decode($limitsJson, true);
        if (!is_array($limits)) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Invalid limits data format']);
            return;
        }
        
        // Validate and sanitize limits
        $validatedLimits = $this->validateWIPLimits($limits);
        
        // Save to configuration
        $success = $this->saveWIPLimitsToConfig($validatedLimits);
        
        if ($success) {
            // Log the configuration change
            $this->logWIPEvent('limits_updated', [
                'user_id' => $current_user->id,
                'timestamp' => date('Y-m-d H:i:s'),
                'limits' => $validatedLimits
            ]);
            
            $this->sendJsonResponse([
                'success' => true,
                'message' => 'WIP limits saved successfully',
                'limits' => $validatedLimits
            ]);
        } else {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Failed to save WIP limits'
            ]);
        }
    }
    
    /**
     * Log WIP-related events
     */
    public function action_logWIPEvent()
    {
        global $current_user;
        
        // Check permissions
        if (!$current_user->id) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Unauthorized']);
            return;
        }
        
        $eventType = $_POST['event_type'] ?? '';
        $eventData = $_POST['event_data'] ?? '';
        
        if (!$eventType) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Event type required']);
            return;
        }
        
        $success = $this->logWIPEvent($eventType, json_decode($eventData, true));
        
        $this->sendJsonResponse([
            'success' => $success,
            'message' => $success ? 'Event logged' : 'Failed to log event'
        ]);
    }
    
    /**
     * Get WIP violation report
     */
    public function action_getWIPReport()
    {
        global $current_user, $db;
        
        // Check permissions
        if (!$current_user->id) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Unauthorized']);
            return;
        }
        
        $days = intval($_GET['days'] ?? 7);
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Get current stage counts
        $stageCounts = $this->getCurrentStageCounts();
        
        // Get WIP limits
        $limits = array_merge([
            'sourcing' => 20,
            'screening' => 15,
            'analysis_outreach' => 10,
            'due_diligence' => 8,
            'valuation_structuring' => 6,
            'loi_negotiation' => 5,
            'financing' => 5,
            'closing' => 5,
            'closed_owned_90_day' => 10,
            'closed_owned_stable' => null,
            'unavailable' => null
        ], $this->loadWIPLimitsFromConfig());
        
        // Check for current violations
        $currentViolations = [];
        foreach ($stageCounts as $stage => $count) {
            $limit = $limits[$stage] ?? null;
            if ($limit && $count > $limit) {
                $currentViolations[] = [
                    'stage' => $stage,
                    'count' => $count,
                    'limit' => $limit,
                    'excess' => $count - $limit
                ];
            }
        }
        
        // Get historical violations from log table
        $historicalViolations = [];
        $query = "SELECT event_data, created_date 
                  FROM pipeline_wip_events 
                  WHERE event_type = 'limit_violation' 
                  AND created_date >= '$cutoffDate' 
                  ORDER BY created_date DESC";
        
        // Check if the table exists first
        $tableCheck = "SHOW TABLES LIKE 'pipeline_wip_events'";
        $result = $db->query($tableCheck);
        if ($db->fetchByAssoc($result)) {
            $result = $db->query($query);
            while ($row = $db->fetchByAssoc($result)) {
                $data = json_decode($row['event_data'], true);
                $data['date'] = $row['created_date'];
                $historicalViolations[] = $data;
            }
        }
        
        $report = [
            'period_days' => $days,
            'current_violations' => $currentViolations,
            'historical_violations' => $historicalViolations,
            'stage_counts' => $stageCounts,
            'limits' => $limits,
            'generated_at' => date('Y-m-d H:i:s')
        ];
        
        $this->sendJsonResponse([
            'success' => true,
            'report' => $report
        ]);
    }
    
    /**
     * Validate WIP limits data
     */
    private function validateWIPLimits($limits)
    {
        $validStages = [
            'sourcing', 'screening', 'analysis_outreach', 'due_diligence',
            'valuation_structuring', 'loi_negotiation', 'financing', 'closing',
            'closed_owned_90_day', 'closed_owned_stable', 'unavailable'
        ];
        
        $validated = [];
        foreach ($limits as $stage => $limit) {
            if (in_array($stage, $validStages)) {
                if ($limit === null || $limit === '') {
                    $validated[$stage] = null;
                } else {
                    $validated[$stage] = max(0, intval($limit));
                }
            }
        }
        
        return $validated;
    }
    
    /**
     * Load WIP limits from configuration
     */
    private function loadWIPLimitsFromConfig()
    {
        global $sugar_config;
        
        // Try to load from sugar_config first
        if (isset($sugar_config['wip_limits']) && is_array($sugar_config['wip_limits'])) {
            return $sugar_config['wip_limits'];
        }
        
        // Try to load from custom config file
        $configFile = 'custom/modules/Deals/config/wip_limits.php';
        if (file_exists($configFile)) {
            include $configFile;
            if (isset($wip_limits) && is_array($wip_limits)) {
                return $wip_limits;
            }
        }
        
        return [];
    }
    
    /**
     * Save WIP limits to configuration
     */
    private function saveWIPLimitsToConfig($limits)
    {
        $configDir = 'custom/modules/Deals/config';
        $configFile = $configDir . '/wip_limits.php';
        
        // Create config directory if it doesn't exist
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }
        
        // Generate PHP config file content
        $content = "<?php\n";
        $content .= "/**\n";
        $content .= " * WIP Limits Configuration\n";
        $content .= " * Generated: " . date('Y-m-d H:i:s') . "\n";
        $content .= " */\n\n";
        $content .= "if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');\n\n";
        $content .= "\$wip_limits = " . var_export($limits, true) . ";\n";
        
        return file_put_contents($configFile, $content) !== false;
    }
    
    /**
     * Get current stage counts
     */
    private function getCurrentStageCounts()
    {
        global $db;
        
        $query = "SELECT 
                    c.pipeline_stage_c as stage,
                    COUNT(*) as count
                  FROM opportunities o
                  JOIN opportunities_cstm c ON o.id = c.id_c
                  WHERE o.deleted = 0 
                  AND o.sales_stage NOT IN ('Closed Won', 'Closed Lost')
                  AND c.pipeline_stage_c IS NOT NULL
                  GROUP BY c.pipeline_stage_c";
        
        $result = $db->query($query);
        $counts = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $counts[$row['stage']] = intval($row['count']);
        }
        
        return $counts;
    }
    
    /**
     * Log WIP events to database
     */
    private function logWIPEvent($eventType, $eventData)
    {
        global $db, $current_user;
        
        // Check if table exists, create if needed
        $this->ensureWIPEventTable();
        
        $eventDataJson = json_encode($eventData);
        $userId = $current_user->id ?? 'system';
        $timestamp = date('Y-m-d H:i:s');
        
        $query = "INSERT INTO pipeline_wip_events 
                  (id, event_type, event_data, user_id, created_date) 
                  VALUES 
                  (UUID(), '$eventType', '$eventDataJson', '$userId', '$timestamp')";
        
        return $db->query($query) !== false;
    }
    
    /**
     * Ensure WIP event log table exists
     */
    private function ensureWIPEventTable()
    {
        global $db;
        
        $createTable = "CREATE TABLE IF NOT EXISTS pipeline_wip_events (
            id CHAR(36) NOT NULL PRIMARY KEY,
            event_type VARCHAR(50) NOT NULL,
            event_data LONGTEXT,
            user_id CHAR(36) NOT NULL,
            created_date DATETIME NOT NULL,
            KEY idx_event_type (event_type),
            KEY idx_user_id (user_id),
            KEY idx_created_date (created_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->query($createTable);
    }

    /**
     * Stakeholder integration actions
     */
    public function action_stakeholders()
    {
        $api = new StakeholderIntegrationApi();
        $method = $_SERVER['REQUEST_METHOD'];
        $args = array_merge($_GET, $_POST);
        
        // Add action parameter for API method routing
        if ($method === 'GET' && isset($args['action']) && $args['action'] === 'communication') {
            $args['deal_id'] = $args['deal_id'] ?? '';
            $response = $api->getStakeholderCommunication(null, $args);
        } elseif ($method === 'GET' && isset($args['deal_id'])) {
            $response = $api->getStakeholders(null, $args);
        } elseif ($method === 'POST') {
            $response = $api->addStakeholder(null, $args);
        } elseif ($method === 'PUT' && isset($args['relationship_id'])) {
            $response = $api->updateStakeholderRole(null, $args);
        } elseif ($method === 'DELETE' && isset($args['relationship_id'])) {
            $response = $api->removeStakeholder(null, $args);
        } else {
            $response = array('success' => false, 'error' => 'Invalid request');
        }
        
        $this->sendJsonResponse($response);
    }
    
    /**
     * Quick search for contacts (used in stakeholder integration)
     */
    public function action_quicksearch()
    {
        global $db;
        
        $query = $db->quote($_GET['query'] ?? '');
        $limit = intval($_GET['limit'] ?? 10);
        
        if (strlen($query) < 2) {
            $this->sendJsonResponse(['results' => []]);
            return;
        }
        
        $sql = "SELECT 
                    c.id,
                    CONCAT(c.first_name, ' ', c.last_name) as name,
                    c.title,
                    a.name as account_name
                FROM contacts c
                LEFT JOIN accounts a ON c.account_id = a.id AND a.deleted = 0
                WHERE c.deleted = 0
                AND (
                    c.first_name LIKE '%{$query}%' OR
                    c.last_name LIKE '%{$query}%' OR
                    CONCAT(c.first_name, ' ', c.last_name) LIKE '%{$query}%' OR
                    c.email1 LIKE '%{$query}%' OR
                    a.name LIKE '%{$query}%'
                )
                ORDER BY c.last_name, c.first_name
                LIMIT {$limit}";
        
        $result = $db->query($sql);
        $contacts = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $contacts[] = [
                'id' => $row['id'],
                'name' => trim($row['name']),
                'title' => $row['title'],
                'account_name' => $row['account_name']
            ];
        }
        
        $this->sendJsonResponse(['results' => $contacts]);
    }

    /**
     * Checklist API action handler
     * Routes checklist-related API calls to the ChecklistApi class
     */
    public function action_checklistApi()
    {
        global $current_user;
        
        // Check permissions
        if (!$current_user->id) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Unauthorized']);
            return;
        }
        
        // Include the ChecklistApi class
        $apiPath = dirname(__FILE__) . '/api/ChecklistApi.php';
        if (!file_exists($apiPath)) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Checklist API not found']);
            return;
        }
        
        require_once($apiPath);
        
        // Initialize the ChecklistApi
        $checklistApi = new ChecklistApi();
        
        // Get the action from request
        $action = $_REQUEST['checklist_action'] ?? $_REQUEST['action'] ?? '';
        $dealId = $_REQUEST['deal_id'] ?? '';
        
        try {
            switch ($action) {
                case 'load':
                case 'get':
                    $response = $checklistApi->getChecklist($dealId);
                    break;
                    
                case 'create_task':
                    $taskData = json_decode($_REQUEST['task_data'] ?? '{}', true);
                    $response = $checklistApi->createTask($dealId, $taskData);
                    break;
                    
                case 'update_task':
                    $taskData = json_decode($_REQUEST['task_data'] ?? '{}', true);
                    $response = $checklistApi->updateTask($taskData);
                    break;
                    
                case 'delete_task':
                    $taskId = $_REQUEST['task_id'] ?? '';
                    $response = $checklistApi->deleteTask($taskId);
                    break;
                    
                case 'update_task_status':
                    $taskId = $_REQUEST['task_id'] ?? '';
                    $status = $_REQUEST['status'] ?? '';
                    $response = $checklistApi->updateTaskStatus($taskId, $status);
                    break;
                    
                case 'create_category':
                    $categoryData = json_decode($_REQUEST['category_data'] ?? '{}', true);
                    $response = $checklistApi->createCategory($categoryData);
                    break;
                    
                case 'get_templates':
                    $response = $checklistApi->getTemplates();
                    break;
                    
                case 'apply_template':
                    $templateId = $_REQUEST['template_id'] ?? '';
                    $response = $checklistApi->applyTemplate($dealId, $templateId);
                    break;
                    
                default:
                    $response = ['success' => false, 'error' => 'Invalid action: ' . $action];
            }
            
            $this->sendJsonResponse($response);
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('Checklist API error: ' . $e->getMessage());
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Display bulk stakeholder management view
     */
    public function action_stakeholder_bulk()
    {
        $this->view = 'stakeholder_bulk';
    }

    /**
     * Validate and sanitize input data
     */
    private function validateAndSanitize($value, $type)
    {
        global $db;
        
        switch ($type) {
            case 'guid':
                if (empty($value) || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $value)) {
                    return null;
                }
                return $db->quote($value);
                
            case 'string':
                return !empty($value) ? $db->quote(trim($value)) : null;
                
            case 'text':
                return !empty($value) ? $db->quote(trim($value)) : null;
                
            case 'integer':
                return is_numeric($value) ? (int)$value : 0;
                
            case 'currency':
                return is_numeric($value) ? (float)$value : 0.00;
                
            case 'date':
                if (empty($value)) return null;
                $date = date('Y-m-d', strtotime($value));
                return $date !== '1970-01-01' ? $db->quote($date) : null;
                
            case 'datetime':
                if (empty($value)) return null;
                $datetime = date('Y-m-d H:i:s', strtotime($value));
                return $datetime !== '1970-01-01 00:00:00' ? $db->quote($datetime) : null;
                
            case 'pipeline_stage':
                $validStages = array(
                    'sourcing', 'screening', 'analysis_outreach', 'due_diligence',
                    'valuation_structuring', 'loi_negotiation', 'financing', 'closing',
                    'closed_owned_90_day', 'closed_owned_stable', 'unavailable'
                );
                return in_array($value, $validStages) ? $db->quote($value) : null;
                
            default:
                return $db->quote(trim($value));
        }
    }
    
    /**
     * Validate options array
     */
    private function validateOptionsArray($options)
    {
        if (!is_array($options)) {
            return array();
        }
        
        $validatedOptions = array();
        
        // Only allow specific option keys for security
        $allowedKeys = array('force_move', 'skip_validation', 'notify_users', 'update_probability');
        
        foreach ($options as $key => $value) {
            if (in_array($key, $allowedKeys)) {
                $validatedOptions[$key] = $value;
            }
        }
        
        return $validatedOptions;
    }
    
    /**
     * Send JSON response with error handling
     */
    private function sendJsonResponse($data)
    {
        try {
            // Clean any output buffers
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Set appropriate headers
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
            
            // Ensure data is properly encoded
            $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            
            if ($jsonData === false) {
                // JSON encoding failed
                $jsonData = json_encode(array(
                    'success' => false,
                    'message' => 'JSON encoding error: ' . json_last_error_msg()
                ));
            }
            
            echo $jsonData;
            
            // Clean shutdown
            if (function_exists('sugar_cleanup')) {
                sugar_cleanup(true);
            }
            exit();
            
        } catch (Exception $e) {
            // Last resort error handling
            header('Content-Type: application/json');
            echo json_encode(array(
                'success' => false,
                'message' => 'Response error occurred'
            ));
            exit();
        }
    }
}