<?php
/**
 * State Synchronization API for Pipeline System
 * 
 * Handles multi-user state synchronization, conflict resolution,
 * and persistent state storage
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/MVC/Controller/SugarController.php');

class StateSyncAPI extends SugarController
{
    private $conflictResolver;
    private $stateStorage;
    
    public function __construct()
    {
        parent::__construct();
        $this->conflictResolver = new StateConflictResolver();
        $this->stateStorage = new StateStorage();
    }

    /**
     * Handle state synchronization requests
     */
    public function action_sync()
    {
        global $current_user, $db;
        
        // Validate authentication
        if (!$current_user->id) {
            $this->sendResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        // Get request data
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['changes'])) {
            $this->sendResponse(['error' => 'Invalid request data'], 400);
            return;
        }

        $sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? '';
        $changes = $input['changes'];
        $currentVersion = $input['currentVersion'] ?? 1;
        $userId = $input['userId'] ?? $current_user->id;

        try {
            // Get current server state
            $serverState = $this->stateStorage->getCurrentState($userId);
            
            // Check for conflicts
            $conflicts = $this->conflictResolver->detectConflicts(
                $changes, 
                $serverState, 
                $currentVersion
            );

            // Apply non-conflicting changes
            $appliedChanges = [];
            $rejectedChanges = [];

            foreach ($changes as $change) {
                if ($this->canApplyChange($change, $conflicts)) {
                    $this->applyChange($change, $serverState, $userId);
                    $appliedChanges[] = $change;
                } else {
                    $rejectedChanges[] = $change;
                }
            }

            // Get changes from other users since last sync
            $newChanges = $this->stateStorage->getChangesSince(
                $currentVersion, 
                $userId, 
                $sessionId
            );

            // Update version
            $newVersion = $this->stateStorage->incrementVersion();

            // Log sync activity
            $this->logSyncActivity($userId, $sessionId, count($appliedChanges), count($conflicts));

            $this->sendResponse([
                'success' => true,
                'version' => $newVersion,
                'conflicts' => $conflicts,
                'changes' => $newChanges,
                'applied' => count($appliedChanges),
                'rejected' => count($rejectedChanges),
                'timestamp' => time()
            ]);

        } catch (Exception $e) {
            error_log("State sync error: " . $e->getMessage());
            $this->sendResponse(['error' => 'Sync failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get current state for initial load
     */
    public function action_getState()
    {
        global $current_user;
        
        if (!$current_user->id) {
            $this->sendResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        try {
            $state = $this->stateStorage->getCurrentState($current_user->id);
            $version = $this->stateStorage->getCurrentVersion();

            $this->sendResponse([
                'success' => true,
                'state' => $state,
                'version' => $version,
                'timestamp' => time()
            ]);

        } catch (Exception $e) {
            error_log("Get state error: " . $e->getMessage());
            $this->sendResponse(['error' => 'Failed to get state'], 500);
        }
    }

    /**
     * Reset state (admin only)
     */
    public function action_resetState()
    {
        global $current_user;
        
        if (!$current_user->id || !$current_user->is_admin) {
            $this->sendResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        try {
            $this->stateStorage->resetState();
            
            $this->sendResponse([
                'success' => true,
                'message' => 'State reset successfully'
            ]);

        } catch (Exception $e) {
            error_log("State reset error: " . $e->getMessage());
            $this->sendResponse(['error' => 'Failed to reset state'], 500);
        }
    }

    /**
     * Get sync metrics and statistics
     */
    public function action_getMetrics()
    {
        global $current_user, $db;
        
        if (!$current_user->id) {
            $this->sendResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        try {
            $metrics = $this->stateStorage->getMetrics($current_user->id);
            
            $this->sendResponse([
                'success' => true,
                'metrics' => $metrics
            ]);

        } catch (Exception $e) {
            error_log("Get metrics error: " . $e->getMessage());
            $this->sendResponse(['error' => 'Failed to get metrics'], 500);
        }
    }

    /**
     * Apply a state change
     */
    private function applyChange($change, &$serverState, $userId)
    {
        $action = $change['action'];
        
        switch ($action['type']) {
            case 'DEAL_MOVED':
                $this->applyDealMove($action['payload'], $userId);
                break;
                
            case 'DEAL_UPDATED':
                $this->applyDealUpdate($action['payload'], $userId);
                break;
                
            case 'DEAL_FOCUS_TOGGLED':
                $this->applyFocusToggle($action['payload'], $userId);
                break;
                
            default:
                // Handle other action types
                break;
        }

        // Store the change in history
        $this->stateStorage->storeChange($change, $userId);
    }

    /**
     * Apply deal move change
     */
    private function applyDealMove($payload, $userId)
    {
        global $db;
        
        $dealId = $db->quote($payload['dealId']);
        $toStage = $db->quote($payload['toStage']);
        $position = intval($payload['position'] ?? 0);

        // Load the deal
        $deal = BeanFactory::getBean('Opportunities', $dealId);
        if (!$deal || $deal->deleted) {
            throw new Exception("Deal not found: $dealId");
        }

        // Check permissions
        if (!$deal->ACLAccess('edit')) {
            throw new Exception("Access denied for deal: $dealId");
        }

        // Update pipeline stage
        $deal->pipeline_stage_c = $toStage;
        $deal->stage_entered_date_c = date('Y-m-d H:i:s');
        $deal->position_c = $position;
        
        // Map to sales stage
        $salesStageMapping = $this->getPipelineToSalesStageMapping();
        if (isset($salesStageMapping[$toStage])) {
            $deal->sales_stage = $salesStageMapping[$toStage];
        }

        $deal->save();

        // Log the change
        $this->logDealChange($dealId, 'moved', [
            'from_stage' => $payload['fromStage'] ?? null,
            'to_stage' => $toStage,
            'position' => $position
        ], $userId);
    }

    /**
     * Apply deal update change
     */
    private function applyDealUpdate($payload, $userId)
    {
        global $db;
        
        $dealId = $db->quote($payload['id']);
        $updates = $payload['updates'];

        // Load the deal
        $deal = BeanFactory::getBean('Opportunities', $dealId);
        if (!$deal || $deal->deleted) {
            throw new Exception("Deal not found: $dealId");
        }

        // Check permissions
        if (!$deal->ACLAccess('edit')) {
            throw new Exception("Access denied for deal: $dealId");
        }

        // Apply allowed updates
        $allowedFields = [
            'name', 'amount', 'probability', 'expected_close_date_c',
            'description', 'pipeline_stage_c'
        ];

        $appliedUpdates = [];
        foreach ($updates as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $deal->$field = $value;
                $appliedUpdates[$field] = $value;
            }
        }

        if (!empty($appliedUpdates)) {
            $deal->save();
            
            // Log the change
            $this->logDealChange($dealId, 'updated', $appliedUpdates, $userId);
        }
    }

    /**
     * Apply focus toggle change
     */
    private function applyFocusToggle($payload, $userId)
    {
        global $db;
        
        $dealId = $db->quote($payload['dealId']);
        $focused = (bool)$payload['focused'];
        $focusOrder = intval($payload['focusOrder'] ?? 0);

        // Load the deal
        $deal = BeanFactory::getBean('Opportunities', $dealId);
        if (!$deal || $deal->deleted) {
            throw new Exception("Deal not found: $dealId");
        }

        // Check permissions
        if (!$deal->ACLAccess('edit')) {
            throw new Exception("Access denied for deal: $dealId");
        }

        // Update focus
        $deal->focus_flag_c = $focused ? 1 : 0;
        $deal->focus_date_c = $focused ? date('Y-m-d H:i:s') : null;
        $deal->focus_order_c = $focused ? $focusOrder : 0;

        $deal->save();

        // Log the change
        $this->logDealChange($dealId, 'focus_toggled', [
            'focused' => $focused,
            'focus_order' => $focusOrder
        ], $userId);
    }

    /**
     * Check if a change can be applied given current conflicts
     */
    private function canApplyChange($change, $conflicts)
    {
        foreach ($conflicts as $conflict) {
            if ($this->changesConflict($change, $conflict)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if two changes conflict
     */
    private function changesConflict($change1, $conflict)
    {
        // Simple conflict detection - same entity, different values
        $action1 = $change1['action'];
        $action2 = $conflict['clientChange']['action'];

        if ($action1['type'] !== $action2['type']) {
            return false;
        }

        // Check if they affect the same deal
        $dealId1 = $this->getDealIdFromAction($action1);
        $dealId2 = $this->getDealIdFromAction($action2);

        return $dealId1 === $dealId2;
    }

    /**
     * Extract deal ID from action
     */
    private function getDealIdFromAction($action)
    {
        switch ($action['type']) {
            case 'DEAL_MOVED':
            case 'DEAL_FOCUS_TOGGLED':
                return $action['payload']['dealId'] ?? null;
                
            case 'DEAL_UPDATED':
                return $action['payload']['id'] ?? null;
                
            default:
                return null;
        }
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
     * Log deal change for audit trail
     */
    private function logDealChange($dealId, $changeType, $details, $userId)
    {
        global $db;
        
        $query = "INSERT INTO pipeline_change_log 
                  (id, deal_id, change_type, details, user_id, timestamp) 
                  VALUES 
                  (UUID(), '$dealId', '$changeType', '" . 
                  $db->quote(json_encode($details)) . "', '$userId', NOW())";
        
        // Execute only if table exists
        $tableCheck = "SHOW TABLES LIKE 'pipeline_change_log'";
        $result = $db->query($tableCheck);
        if ($db->fetchByAssoc($result)) {
            $db->query($query);
        }
    }

    /**
     * Log sync activity for monitoring
     */
    private function logSyncActivity($userId, $sessionId, $changesApplied, $conflictsFound)
    {
        global $db;
        
        $query = "INSERT INTO pipeline_sync_log 
                  (id, user_id, session_id, changes_applied, conflicts_found, timestamp) 
                  VALUES 
                  (UUID(), '$userId', '$sessionId', $changesApplied, $conflictsFound, NOW())";
        
        // Execute only if table exists
        $tableCheck = "SHOW TABLES LIKE 'pipeline_sync_log'";
        $result = $db->query($tableCheck);
        if ($db->fetchByAssoc($result)) {
            $db->query($query);
        }
    }

    /**
     * Send JSON response
     */
    private function sendResponse($data, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        sugar_cleanup(true);
    }
}

/**
 * State Conflict Resolution Class
 */
class StateConflictResolver
{
    /**
     * Detect conflicts between client changes and server state
     */
    public function detectConflicts($clientChanges, $serverState, $clientVersion)
    {
        $conflicts = [];
        
        foreach ($clientChanges as $change) {
            $conflict = $this->detectSingleConflict($change, $serverState, $clientVersion);
            if ($conflict) {
                $conflicts[] = $conflict;
            }
        }
        
        return $conflicts;
    }

    /**
     * Detect conflict for a single change
     */
    private function detectSingleConflict($change, $serverState, $clientVersion)
    {
        $action = $change['action'];
        
        switch ($action['type']) {
            case 'DEAL_MOVED':
                return $this->detectMoveConflict($change, $serverState, $clientVersion);
                
            case 'DEAL_UPDATED':
                return $this->detectUpdateConflict($change, $serverState, $clientVersion);
                
            case 'DEAL_FOCUS_TOGGLED':
                return $this->detectFocusConflict($change, $serverState, $clientVersion);
                
            default:
                return null;
        }
    }

    /**
     * Detect move conflicts
     */
    private function detectMoveConflict($change, $serverState, $clientVersion)
    {
        $dealId = $change['action']['payload']['dealId'];
        
        // Check if deal was modified on server since client's last sync
        $serverDeal = $this->getServerDealState($dealId);
        if (!$serverDeal) {
            return null;
        }

        if ($serverDeal['last_modified_version'] > $clientVersion) {
            return [
                'type' => 'move_conflict',
                'dealId' => $dealId,
                'clientChange' => $change,
                'serverState' => $serverDeal,
                'resolution' => 'server_wins' // Default resolution
            ];
        }

        return null;
    }

    /**
     * Detect update conflicts
     */
    private function detectUpdateConflict($change, $serverState, $clientVersion)
    {
        $dealId = $change['action']['payload']['id'];
        
        // Check if deal was modified on server since client's last sync
        $serverDeal = $this->getServerDealState($dealId);
        if (!$serverDeal) {
            return null;
        }

        if ($serverDeal['last_modified_version'] > $clientVersion) {
            return [
                'type' => 'update_conflict',
                'dealId' => $dealId,
                'clientChange' => $change,
                'serverState' => $serverDeal,
                'resolution' => 'merge' // Try to merge non-conflicting fields
            ];
        }

        return null;
    }

    /**
     * Detect focus conflicts
     */
    private function detectFocusConflict($change, $serverState, $clientVersion)
    {
        $dealId = $change['action']['payload']['dealId'];
        
        // Check if deal focus was modified on server since client's last sync
        $serverDeal = $this->getServerDealState($dealId);
        if (!$serverDeal) {
            return null;
        }

        if ($serverDeal['focus_modified_version'] > $clientVersion) {
            return [
                'type' => 'focus_conflict',
                'dealId' => $dealId,
                'clientChange' => $change,
                'serverState' => $serverDeal,
                'resolution' => 'client_wins' // Focus changes are user-specific
            ];
        }

        return null;
    }

    /**
     * Get current server state for a deal
     */
    private function getServerDealState($dealId)
    {
        global $db;
        
        $query = "SELECT 
                    d.id,
                    d.date_modified,
                    c.pipeline_stage_c,
                    c.focus_flag_c,
                    c.focus_order_c,
                    c.stage_entered_date_c
                  FROM opportunities d
                  LEFT JOIN opportunities_cstm c ON d.id = c.id_c
                  WHERE d.id = '$dealId' AND d.deleted = 0";
                  
        $result = $db->query($query);
        $row = $db->fetchByAssoc($result);
        
        if ($row) {
            // Get version info from state storage
            $stateStorage = new StateStorage();
            $versionInfo = $stateStorage->getDealVersionInfo($dealId);
            
            return array_merge($row, $versionInfo);
        }
        
        return null;
    }
}

/**
 * State Storage Class
 */
class StateStorage
{
    private $stateTable = 'pipeline_state_store';
    private $changesTable = 'pipeline_state_changes';
    private $versionsTable = 'pipeline_state_versions';

    /**
     * Get current state for a user
     */
    public function getCurrentState($userId)
    {
        global $db;
        
        // This would typically return a comprehensive state object
        // For now, we'll return the deals data
        $query = "SELECT 
                    d.id,
                    d.name,
                    d.amount,
                    d.sales_stage,
                    c.pipeline_stage_c,
                    c.stage_entered_date_c,
                    c.focus_flag_c,
                    c.focus_order_c,
                    c.position_c
                  FROM opportunities d
                  LEFT JOIN opportunities_cstm c ON d.id = c.id_c
                  WHERE d.deleted = 0
                  AND d.sales_stage NOT IN ('Closed Won', 'Closed Lost')
                  ORDER BY c.focus_order_c ASC, d.date_modified DESC";
                  
        $result = $db->query($query);
        $deals = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $deals[$row['id']] = $row;
        }
        
        return [
            'deals' => $deals,
            'timestamp' => time()
        ];
    }

    /**
     * Get current state version
     */
    public function getCurrentVersion()
    {
        global $db;
        
        $query = "SELECT version FROM {$this->versionsTable} ORDER BY id DESC LIMIT 1";
        $result = $db->query($query);
        $row = $db->fetchByAssoc($result);
        
        return $row ? intval($row['version']) : 1;
    }

    /**
     * Increment state version
     */
    public function incrementVersion()
    {
        global $db;
        
        $newVersion = $this->getCurrentVersion() + 1;
        
        $query = "INSERT INTO {$this->versionsTable} (id, version, timestamp) 
                  VALUES (UUID(), $newVersion, NOW())";
        
        // Execute only if table exists
        if ($this->tableExists($this->versionsTable)) {
            $db->query($query);
        }
        
        return $newVersion;
    }

    /**
     * Get changes since a version for other users
     */
    public function getChangesSince($version, $excludeUserId, $excludeSessionId)
    {
        global $db;
        
        if (!$this->tableExists($this->changesTable)) {
            return [];
        }
        
        $query = "SELECT * FROM {$this->changesTable} 
                  WHERE version > $version 
                  AND user_id != '$excludeUserId' 
                  AND session_id != '$excludeSessionId'
                  ORDER BY version ASC";
                  
        $result = $db->query($query);
        $changes = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $changes[] = [
                'action' => json_decode($row['action_data'], true),
                'version' => $row['version'],
                'timestamp' => $row['timestamp'],
                'userId' => $row['user_id']
            ];
        }
        
        return $changes;
    }

    /**
     * Store a state change
     */
    public function storeChange($change, $userId)
    {
        global $db;
        
        if (!$this->tableExists($this->changesTable)) {
            return;
        }
        
        $version = $this->getCurrentVersion();
        $actionData = $db->quote(json_encode($change['action']));
        $sessionId = $change['sessionId'] ?? '';
        
        $query = "INSERT INTO {$this->changesTable} 
                  (id, user_id, session_id, action_data, version, timestamp) 
                  VALUES 
                  (UUID(), '$userId', '$sessionId', '$actionData', $version, NOW())";
        
        $db->query($query);
    }

    /**
     * Get deal version information
     */
    public function getDealVersionInfo($dealId)
    {
        global $db;
        
        if (!$this->tableExists($this->changesTable)) {
            return [
                'last_modified_version' => 1,
                'focus_modified_version' => 1
            ];
        }
        
        $query = "SELECT 
                    MAX(CASE WHEN action_data LIKE '%DEAL_MOVED%' OR action_data LIKE '%DEAL_UPDATED%' 
                        THEN version ELSE 0 END) as last_modified_version,
                    MAX(CASE WHEN action_data LIKE '%DEAL_FOCUS_TOGGLED%' 
                        THEN version ELSE 0 END) as focus_modified_version
                  FROM {$this->changesTable} 
                  WHERE action_data LIKE '%\"dealId\":\"$dealId\"%' 
                  OR action_data LIKE '%\"id\":\"$dealId\"%'";
                  
        $result = $db->query($query);
        $row = $db->fetchByAssoc($result);
        
        return [
            'last_modified_version' => intval($row['last_modified_version'] ?? 1),
            'focus_modified_version' => intval($row['focus_modified_version'] ?? 1)
        ];
    }

    /**
     * Get synchronization metrics
     */
    public function getMetrics($userId)
    {
        global $db;
        
        $metrics = [
            'total_changes' => 0,
            'user_changes' => 0,
            'conflicts_resolved' => 0,
            'sync_frequency' => 0,
            'last_sync' => null
        ];

        // Get total changes
        if ($this->tableExists($this->changesTable)) {
            $query = "SELECT COUNT(*) as total FROM {$this->changesTable}";
            $result = $db->query($query);
            $row = $db->fetchByAssoc($result);
            $metrics['total_changes'] = intval($row['total']);

            // Get user-specific changes
            $query = "SELECT COUNT(*) as user_total FROM {$this->changesTable} WHERE user_id = '$userId'";
            $result = $db->query($query);
            $row = $db->fetchByAssoc($result);
            $metrics['user_changes'] = intval($row['user_total']);
        }

        // Get sync stats
        if ($this->tableExists('pipeline_sync_log')) {
            $query = "SELECT 
                        COUNT(*) as sync_count,
                        SUM(conflicts_found) as total_conflicts,
                        MAX(timestamp) as last_sync_time
                      FROM pipeline_sync_log 
                      WHERE user_id = '$userId'";
            $result = $db->query($query);
            $row = $db->fetchByAssoc($result);
            
            $metrics['conflicts_resolved'] = intval($row['total_conflicts'] ?? 0);
            $metrics['sync_frequency'] = intval($row['sync_count'] ?? 0);
            $metrics['last_sync'] = $row['last_sync_time'];
        }

        return $metrics;
    }

    /**
     * Reset all state data (admin only)
     */
    public function resetState()
    {
        global $db;
        
        $tables = [$this->stateTable, $this->changesTable, $this->versionsTable];
        
        foreach ($tables as $table) {
            if ($this->tableExists($table)) {
                $db->query("TRUNCATE TABLE $table");
            }
        }
        
        // Reset version to 1
        if ($this->tableExists($this->versionsTable)) {
            $db->query("INSERT INTO {$this->versionsTable} (id, version, timestamp) VALUES (UUID(), 1, NOW())");
        }
    }

    /**
     * Check if table exists
     */
    private function tableExists($tableName)
    {
        global $db;
        
        $query = "SHOW TABLES LIKE '$tableName'";
        $result = $db->query($query);
        return (bool)$db->fetchByAssoc($result);
    }
}