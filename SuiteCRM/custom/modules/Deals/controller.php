<?php
/**
 * Custom controller for Deals module
 * Handles AJAX requests for Pipeline functionality
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/MVC/Controller/SugarController.php');

// Check if API files exist before including them
$apiPath = 'custom/modules/Deals/api/';
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
            $deal = BeanFactory::getBean('Deals', $dealId);
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
            $deal = BeanFactory::newBean('Deals');
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
            $deal = BeanFactory::getBean('Deals', $dealId);
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
            $deal = BeanFactory::getBean('Deals', $dealId);
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
            $deal = BeanFactory::newBean('Deals');
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
        $deal = BeanFactory::getBean('Deals', $dealId);
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
        $deal = BeanFactory::getBean('Deals', $dealId);
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
     * Display bulk stakeholder management view
     */
    public function action_stakeholder_bulk()
    {
        $this->view = 'stakeholder_bulk';
    }
    
    /**
     * Apply checklist template to deal
     */
    public function action_ApplyChecklistTemplate()
    {
        require_once('custom/modules/Deals/services/ChecklistService.php');
        
        $checklistService = new ChecklistService();
        
        $dealId = $_REQUEST['deal_id'] ?? '';
        $templateId = $_REQUEST['template_id'] ?? '';
        
        $result = $checklistService->createChecklistFromTemplate($dealId, $templateId, array(
            'create_tasks' => !empty($_REQUEST['create_tasks']),
            'assigned_user_id' => $_REQUEST['assigned_user_id'] ?? null
        ));
        
        $this->sendJsonResponse($result);
    }
    
    /**
     * Update checklist item status
     */
    public function action_UpdateChecklistItem()
    {
        require_once('custom/modules/Deals/services/ChecklistService.php');
        
        $checklistService = new ChecklistService();
        
        $itemId = $_REQUEST['item_id'] ?? '';
        $status = $_REQUEST['status'] ?? '';
        $notes = $_REQUEST['notes'] ?? '';
        
        $result = $checklistService->updateChecklistItem($itemId, $status, array(
            'notes' => $notes
        ));
        
        $this->sendJsonResponse($result);
    }
    
    /**
     * Get deal checklists
     */
    public function action_GetDealChecklists()
    {
        require_once('custom/modules/Deals/services/ChecklistService.php');
        
        $checklistService = new ChecklistService();
        
        $dealId = $_REQUEST['deal_id'] ?? '';
        $filters = array(
            'status' => $_REQUEST['status'] ?? null,
            'template_id' => $_REQUEST['template_id'] ?? null
        );
        
        $checklists = $checklistService->getDealChecklists($dealId, $filters);
        
        $this->sendJsonResponse(array(
            'success' => true,
            'checklists' => $checklists
        ));
    }
    
    /**
     * Get checklist progress
     */
    public function action_GetChecklistProgress()
    {
        require_once('custom/modules/Deals/services/ChecklistService.php');
        
        $checklistService = new ChecklistService();
        
        $checklistId = $_REQUEST['checklist_id'] ?? '';
        $progress = $checklistService->getChecklistProgress($checklistId);
        
        $this->sendJsonResponse($progress);
    }
    
    /**
     * Delete checklist
     */
    public function action_DeleteChecklist()
    {
        require_once('custom/modules/Deals/services/ChecklistService.php');
        
        $checklistService = new ChecklistService();
        
        $checklistId = $_REQUEST['checklist_id'] ?? '';
        $cascadeDelete = !empty($_REQUEST['cascade_delete']);
        
        $result = $checklistService->deleteChecklist($checklistId, $cascadeDelete);
        
        $this->sendJsonResponse($result);
    }
    
    /**
     * Get available templates
     */
    public function action_GetChecklistTemplates()
    {
        require_once('custom/modules/Deals/services/ChecklistService.php');
        
        $checklistService = new ChecklistService();
        
        $filters = array(
            'category' => $_REQUEST['category'] ?? null,
            'search' => $_REQUEST['search'] ?? null
        );
        
        $templates = $checklistService->getAvailableTemplates($filters);
        
        $this->sendJsonResponse(array(
            'success' => true,
            'templates' => $templates
        ));
    }
    
    /**
     * Clone checklist template
     */
    public function action_CloneChecklistTemplate()
    {
        require_once('custom/modules/Deals/services/ChecklistService.php');
        
        $checklistService = new ChecklistService();
        
        $templateId = $_REQUEST['template_id'] ?? '';
        $newName = $_REQUEST['new_name'] ?? '';
        $options = array(
            'category' => $_REQUEST['category'] ?? null,
            'is_public' => isset($_REQUEST['is_public']) ? (bool)$_REQUEST['is_public'] : null,
            'description' => $_REQUEST['description'] ?? null
        );
        
        $result = $checklistService->cloneTemplate($templateId, $newName, $options);
        
        $this->sendJsonResponse($result);
    }
    
    /**
     * Bulk update checklist items
     */
    public function action_BulkUpdateChecklistItems()
    {
        require_once('custom/modules/Deals/services/ChecklistService.php');
        
        $checklistService = new ChecklistService();
        
        $itemIds = json_decode($_REQUEST['item_ids'] ?? '[]', true);
        $updates = array(
            'status' => $_REQUEST['status'] ?? null,
            'assigned_user_id' => $_REQUEST['assigned_user_id'] ?? null,
            'notes' => $_REQUEST['notes'] ?? null
        );
        
        $result = $checklistService->bulkUpdateItems($itemIds, $updates);
        
        $this->sendJsonResponse($result);
    }
    
    /**
     * Export checklist
     */
    public function action_ExportChecklist()
    {
        require_once('custom/modules/Deals/services/ChecklistService.php');
        
        $checklistService = new ChecklistService();
        
        $checklistId = $_REQUEST['checklist_id'] ?? '';
        $format = $_REQUEST['format'] ?? 'pdf';
        
        $checklistService->exportChecklist($checklistId, $format);
    }
    
    /**
     * Get checklist analytics
     */
    public function action_GetChecklistAnalytics()
    {
        require_once('custom/modules/Deals/services/ChecklistService.php');
        
        $checklistService = new ChecklistService();
        
        $dealId = $_REQUEST['deal_id'] ?? null;
        $dateRange = array(
            'start' => $_REQUEST['start_date'] ?? null,
            'end' => $_REQUEST['end_date'] ?? null
        );
        
        $analytics = $checklistService->getChecklistAnalytics($dealId, $dateRange);
        
        $this->sendJsonResponse($analytics);
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
     * Export deal to PDF
     */
    public function action_exportPDF()
    {
        try {
            $dealId = $_REQUEST['deal_id'] ?? null;
            $options = $_REQUEST['options'] ?? [];
            
            if (!$dealId) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Deal ID is required']);
                return;
            }
            
            // Load the deal
            $deal = BeanFactory::getBean('Deals', $dealId);
            if (!$deal || $deal->deleted || !$deal->ACLAccess('export')) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Deal not found or access denied']);
                return;
            }
            
            // Check if export service exists
            if (file_exists('custom/modules/Deals/services/ExportService.php')) {
                require_once('custom/modules/Deals/services/ExportService.php');
                $exportService = new DueDiligenceExportService($deal);
                $result = $exportService->exportToPDF($options);
                
                if ($result['success']) {
                    // Return download URL
                    $this->sendJsonResponse([
                        'success' => true,
                        'download_url' => 'index.php?module=Deals&action=downloadExport&file=' . urlencode($result['filename'])
                    ]);
                } else {
                    $this->sendJsonResponse(['success' => false, 'message' => $result['error']]);
                }
            } else {
                // Fallback to simple export
                $this->simpleExportPDF($deal);
            }
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('Export PDF failed: ' . $e->getMessage());
            $this->sendJsonResponse(['success' => false, 'message' => 'Export failed']);
        }
    }
    
    /**
     * Export deal to Excel
     */
    public function action_exportExcel()
    {
        try {
            $dealId = $_REQUEST['deal_id'] ?? null;
            $options = $_REQUEST['options'] ?? [];
            
            if (!$dealId) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Deal ID is required']);
                return;
            }
            
            // Load the deal
            $deal = BeanFactory::getBean('Deals', $dealId);
            if (!$deal || $deal->deleted || !$deal->ACLAccess('export')) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Deal not found or access denied']);
                return;
            }
            
            // Check if export service exists
            if (file_exists('custom/modules/Deals/services/ExportService.php')) {
                require_once('custom/modules/Deals/services/ExportService.php');
                $exportService = new DueDiligenceExportService($deal);
                $result = $exportService->exportToExcel($options);
                
                if ($result['success']) {
                    // Return download URL
                    $this->sendJsonResponse([
                        'success' => true,
                        'download_url' => 'index.php?module=Deals&action=downloadExport&file=' . urlencode($result['filename'])
                    ]);
                } else {
                    $this->sendJsonResponse(['success' => false, 'message' => $result['error']]);
                }
            } else {
                // Fallback to simple export
                $this->simpleExportExcel($deal);
            }
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('Export Excel failed: ' . $e->getMessage());
            $this->sendJsonResponse(['success' => false, 'message' => 'Export failed']);
        }
    }
    
    /**
     * Download exported file
     */
    public function action_downloadExport()
    {
        $filename = $_REQUEST['file'] ?? '';
        if (!$filename) {
            sugar_die('File not specified');
        }
        
        $filepath = 'upload/exports/' . basename($filename);
        
        if (!file_exists($filepath)) {
            sugar_die('File not found');
        }
        
        // Send file for download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        
        readfile($filepath);
        
        // Clean up old files
        $this->cleanupOldExports();
        
        exit();
    }
    
    /**
     * Simple PDF export fallback
     */
    private function simpleExportPDF($deal)
    {
        // Create export directory if needed
        $exportDir = 'upload/exports';
        if (!file_exists($exportDir)) {
            mkdir($exportDir, 0755, true);
        }
        
        // Generate filename
        $filename = 'deal_' . $deal->id . '_' . date('Y-m-d_His') . '.pdf';
        $filepath = $exportDir . '/' . $filename;
        
        // Create simple PDF content
        $content = $this->generateSimplePDFContent($deal);
        
        // Write to file (in real implementation, use a PDF library)
        file_put_contents($filepath, $content);
        
        $this->sendJsonResponse([
            'success' => true,
            'download_url' => 'index.php?module=Deals&action=downloadExport&file=' . urlencode($filename)
        ]);
    }
    
    /**
     * Simple Excel export fallback
     */
    private function simpleExportExcel($deal)
    {
        // Create export directory if needed
        $exportDir = 'upload/exports';
        if (!file_exists($exportDir)) {
            mkdir($exportDir, 0755, true);
        }
        
        // Generate filename
        $filename = 'deal_' . $deal->id . '_' . date('Y-m-d_His') . '.csv';
        $filepath = $exportDir . '/' . $filename;
        
        // Create CSV content
        $content = $this->generateSimpleCSVContent($deal);
        
        // Write to file
        file_put_contents($filepath, $content);
        
        $this->sendJsonResponse([
            'success' => true,
            'download_url' => 'index.php?module=Deals&action=downloadExport&file=' . urlencode($filename)
        ]);
    }
    
    /**
     * Generate simple PDF content
     */
    private function generateSimplePDFContent($deal)
    {
        $content = "DEAL EXPORT REPORT\n";
        $content .= "==================\n\n";
        $content .= "Deal Name: " . $deal->name . "\n";
        $content .= "Deal Value: $" . number_format($deal->amount, 2) . "\n";
        $content .= "Stage: " . $deal->sales_stage . "\n";
        $content .= "Close Date: " . $deal->date_closed . "\n";
        $content .= "Probability: " . $deal->probability . "%\n\n";
        $content .= "Generated: " . date('Y-m-d H:i:s') . "\n";
        
        return $content;
    }
    
    /**
     * Generate simple CSV content
     */
    private function generateSimpleCSVContent($deal)
    {
        $headers = ['Field', 'Value'];
        $data = [
            ['Deal Name', $deal->name],
            ['Deal Value', '$' . number_format($deal->amount, 2)],
            ['Stage', $deal->sales_stage],
            ['Close Date', $deal->date_closed],
            ['Probability', $deal->probability . '%'],
            ['Assigned To', $deal->assigned_user_name],
            ['Created Date', $deal->date_entered],
            ['Modified Date', $deal->date_modified]
        ];
        
        $csv = implode(',', $headers) . "\n";
        foreach ($data as $row) {
            $csv .= '"' . implode('","', $row) . '"' . "\n";
        }
        
        return $csv;
    }
    
    /**
     * Clean up old export files
     */
    private function cleanupOldExports()
    {
        $exportDir = 'upload/exports';
        if (!is_dir($exportDir)) {
            return;
        }
        
        // Delete files older than 24 hours
        $files = glob($exportDir . '/*');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= 86400) { // 24 hours
                    unlink($file);
                }
            }
        }
    }
    
    /**
     * Update financial data via AJAX
     */
    public function action_updateFinancialData()
    {
        global $current_user;
        
        try {
            // Check permissions
            if (!$current_user->id) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Unauthorized']);
                return;
            }
            
            $dealId = $_POST['deal_id'] ?? '';
            $updateData = json_decode($_POST['update_data'] ?? '{}', true);
            $action = $_POST['action'] ?? 'update';
            
            if (!$dealId) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Deal ID required']);
                return;
            }
            
            // Load the deal
            $deal = BeanFactory::getBean('Deals', $dealId);
            if (!$deal || $deal->deleted || !$deal->ACLAccess('edit')) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Deal not found or access denied']);
                return;
            }
            
            // Load Financial Data Editor service
            if (file_exists('custom/modules/Deals/services/FinancialDataEditor.php')) {
                require_once('custom/modules/Deals/services/FinancialDataEditor.php');
                $editor = new FinancialDataEditor($deal);
                
                if ($action === 'delete') {
                    // Handle deletion
                    $result = $editor->deleteFinancialData(array_keys($updateData));
                } else {
                    // Handle update
                    $result = $editor->updateFinancialData($updateData);
                }
                
                if ($result['success']) {
                    // Get updated metrics
                    require_once('custom/modules/Deals/services/FinancialCalculator.php');
                    $calculator = new FinancialCalculator();
                    $metrics = $calculator->calculateMetrics($deal);
                    
                    $this->sendJsonResponse([
                        'success' => true,
                        'message' => 'Financial data updated successfully',
                        'updated_metrics' => $metrics
                    ]);
                } else {
                    $this->sendJsonResponse([
                        'success' => false,
                        'message' => 'Failed to update financial data',
                        'errors' => $result['errors'] ?? []
                    ]);
                }
            } else {
                // Fallback if service not available
                $this->updateFinancialDataDirectly($deal, $updateData);
            }
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('Update financial data failed: ' . $e->getMessage());
            $this->sendJsonResponse(['success' => false, 'message' => 'Internal error']);
        }
    }
    
    /**
     * Direct update of financial data (fallback method)
     */
    private function updateFinancialDataDirectly($deal, $updateData)
    {
        foreach ($updateData as $group => $fields) {
            foreach ($fields as $field => $value) {
                if (property_exists($deal, $field)) {
                    $deal->$field = $value;
                }
            }
        }
        
        $deal->save();
        
        $this->sendJsonResponse([
            'success' => true,
            'message' => 'Financial data updated successfully'
        ]);
    }
    
    /**
     * Checklist API endpoint
     */
    public function action_checklistApi()
    {
        global $current_user, $db;
        
        try {
            // Check permissions
            if (!$current_user->id) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Unauthorized']);
                return;
            }
            
            $action = $_POST['action'] ?? '';
            $dealId = $_POST['deal_id'] ?? '';
            
            if (!$dealId) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Deal ID required']);
                return;
            }
            
            // Load the deal
            $deal = BeanFactory::getBean('Deals', $dealId);
            if (!$deal || $deal->deleted || !$deal->ACLAccess('view')) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Deal not found or access denied']);
                return;
            }
            
            switch ($action) {
                case 'getChecklist':
                    $this->getChecklistData($deal);
                    break;
                    
                case 'toggleItem':
                    $this->toggleChecklistItem($deal);
                    break;
                    
                default:
                    $this->sendJsonResponse(['success' => false, 'message' => 'Invalid action']);
            }
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('Checklist API error: ' . $e->getMessage());
            $this->sendJsonResponse(['success' => false, 'message' => 'Internal error']);
        }
    }
    
    /**
     * Get checklist data for a deal
     */
    private function getChecklistData($deal)
    {
        // Get checklist service
        if (file_exists('custom/modules/Deals/services/ChecklistService.php')) {
            require_once('custom/modules/Deals/services/ChecklistService.php');
            $checklistService = new ChecklistService();
            $checklist = $checklistService->getChecklistForDeal($deal->id);
        } else {
            // Return sample data if service not available
            $checklist = $this->getSampleChecklistData($deal);
        }
        
        $this->sendJsonResponse(['success' => true, 'data' => $checklist]);
    }
    
    /**
     * Toggle checklist item completion
     */
    private function toggleChecklistItem($deal)
    {
        $itemId = $_POST['item_id'] ?? '';
        $completed = !empty($_POST['completed']);
        
        if (!$itemId) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Item ID required']);
            return;
        }
        
        // Update item in database
        if (file_exists('custom/modules/Deals/services/ChecklistService.php')) {
            require_once('custom/modules/Deals/services/ChecklistService.php');
            $checklistService = new ChecklistService();
            $result = $checklistService->updateItemStatus($deal->id, $itemId, $completed);
        } else {
            // Mock success
            $result = true;
        }
        
        if ($result) {
            $this->sendJsonResponse(['success' => true, 'message' => 'Item updated']);
        } else {
            $this->sendJsonResponse(['success' => false, 'message' => 'Failed to update item']);
        }
    }
    
    /**
     * Get sample checklist data
     */
    private function getSampleChecklistData($deal)
    {
        $stages = [
            'sourcing' => 'Initial Review',
            'screening' => 'Screening & Qualification',
            'analysis_outreach' => 'Analysis & Outreach',
            'due_diligence' => 'Due Diligence',
            'term_sheet' => 'Term Sheet',
            'final_negotiation' => 'Final Negotiation',
            'closing' => 'Closing'
        ];
        
        $currentStage = $deal->pipeline_stage_c ?? 'sourcing';
        
        $sections = [
            [
                'id' => 'sec1',
                'name' => 'Financial Review',
                'progress' => 75,
                'items' => [
                    ['id' => 'item1', 'description' => 'Review financial statements', 'completed' => true, 'required' => true],
                    ['id' => 'item2', 'description' => 'Analyze revenue trends', 'completed' => true, 'required' => true],
                    ['id' => 'item3', 'description' => 'Verify EBITDA calculations', 'completed' => true, 'required' => false],
                    ['id' => 'item4', 'description' => 'Review tax returns', 'completed' => false, 'required' => true]
                ]
            ],
            [
                'id' => 'sec2',
                'name' => 'Legal & Compliance',
                'progress' => 50,
                'items' => [
                    ['id' => 'item5', 'description' => 'Review contracts and agreements', 'completed' => true, 'required' => true],
                    ['id' => 'item6', 'description' => 'Check regulatory compliance', 'completed' => true, 'required' => true],
                    ['id' => 'item7', 'description' => 'Verify licenses and permits', 'completed' => false, 'required' => false],
                    ['id' => 'item8', 'description' => 'Review litigation history', 'completed' => false, 'required' => true]
                ]
            ],
            [
                'id' => 'sec3',
                'name' => 'Operational Review',
                'progress' => 25,
                'items' => [
                    ['id' => 'item9', 'description' => 'Assess management team', 'completed' => true, 'required' => true],
                    ['id' => 'item10', 'description' => 'Review customer base', 'completed' => false, 'required' => true],
                    ['id' => 'item11', 'description' => 'Analyze competitive position', 'completed' => false, 'required' => false],
                    ['id' => 'item12', 'description' => 'Review technology stack', 'completed' => false, 'required' => false]
                ]
            ]
        ];
        
        // Calculate overall progress
        $totalItems = 0;
        $completedItems = 0;
        foreach ($sections as $section) {
            foreach ($section['items'] as $item) {
                $totalItems++;
                if ($item['completed']) {
                    $completedItems++;
                }
            }
        }
        
        $overallProgress = $totalItems > 0 ? round(($completedItems / $totalItems) * 100) : 0;
        
        return [
            'deal_id' => $deal->id,
            'stage' => $currentStage,
            'overall_progress' => $overallProgress,
            'sections' => $sections
        ];
    }
    
    /**
     * AJAX handler for pipeline operations
     */
    public function action_AjaxHandler()
    {
        global $db, $current_user;
        
        // Get the JSON payload
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['action'])) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Invalid request']);
            return;
        }
        
        $action = $input['action'];
        $data = $input['data'] ?? [];
        
        switch ($action) {
            case 'executeStageTransition':
                $this->handleExecuteStageTransition($data);
                break;
                
            default:
                $this->sendJsonResponse(['success' => false, 'message' => 'Unknown action: ' . $action]);
        }
    }
    
    /**
     * Handle stage transition for a deal
     */
    private function handleExecuteStageTransition($data)
    {
        global $db, $current_user;
        
        try {
            $dealId = $db->quote($data['dealId'] ?? '');
            $fromStage = $db->quote($data['fromStage'] ?? '');
            $toStage = $db->quote($data['toStage'] ?? '');
            $reason = $db->quote($data['reason'] ?? '');
            $override = !empty($data['override']);
            
            if (!$dealId || !$toStage) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Missing required parameters']);
                return;
            }
            
            // Remove quotes from parameters
            $dealId = str_replace("'", "", $dealId);
            $fromStage = str_replace("'", "", $fromStage);
            $toStage = str_replace("'", "", $toStage);
            
            // Check if deal exists - use disable_row_level_security to ensure we can load it
            $deal = BeanFactory::getBean('Deals', $dealId, array('disable_row_level_security' => true));
            if (!$deal || empty($deal->id)) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Deal not found: ' . $dealId]);
                return;
            }
            
            // Ensure custom fields are loaded
            $deal->retrieve($dealId);
            
            // Update the deal's pipeline stage
            $oldStage = $deal->pipeline_stage_c;
            $deal->pipeline_stage_c = $toStage;
            $deal->stage_entered_date_c = date('Y-m-d H:i:s');
            $deal->save();
            
            // Log the transition
            $GLOBALS['log']->info("Deal stage transition: {$dealId} from {$fromStage} to {$toStage}");
            
            $this->sendJsonResponse([
                'success' => true, 
                'message' => 'Deal moved successfully',
                'dealId' => $dealId,
                'fromStage' => $fromStage,
                'toStage' => $toStage
            ]);
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('executeStageTransition failed: ' . $e->getMessage());
            $this->sendJsonResponse(['success' => false, 'message' => 'Failed to move deal']);
        }
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