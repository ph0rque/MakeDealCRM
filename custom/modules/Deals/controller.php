<?php
/**
 * Custom controller for Deals module
 * Handles AJAX requests for Pipeline functionality
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/MVC/Controller/SugarController.php');
require_once('custom/modules/Deals/api/StateSync.php');
require_once('custom/modules/Deals/api/StageTransitionService.php');
require_once('custom/modules/Deals/api/TimeTrackingService.php');

class DealsController extends SugarController
{
    /**
     * Default action - redirect to Pipeline view
     */
    public function action_index()
    {
        // Redirect to the pipeline view as the default view for Deals
        sugar_redirect('index.php?module=Deals&action=pipeline');
    }
    
    /**
     * Display the Pipeline view
     */
    public function action_pipeline()
    {
        $this->view = 'pipeline';
    }
    
    /**
     * Update deal pipeline stage via AJAX (Enhanced with validation)
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
        $options = $_POST['options'] ?? [];
        
        if (!$dealId || !$newStage) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Missing required parameters']);
            return;
        }
        
        // Use the enhanced Stage Transition Service
        $transitionService = new StageTransitionService();
        $result = $transitionService->transitionDeal($dealId, $oldStage, $newStage, $current_user->id, $options);
        
        $this->sendJsonResponse($result);
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