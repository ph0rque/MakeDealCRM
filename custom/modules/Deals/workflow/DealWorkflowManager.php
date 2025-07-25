<?php
/**
 * Deals Workflow Manager
 * Integrates with SuiteCRM's workflow engine for automated processes
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/SugarLogger.php');
require_once('modules/ACL/ACLController.php');

class DealWorkflowManager
{
    private $logHook = true;
    
    public function __construct()
    {
        $this->logHook = !empty($GLOBALS['sugar_config']['logger']['level']) && 
                        $GLOBALS['sugar_config']['logger']['level'] === 'debug';
    }
    
    /**
     * Handle deal creation workflow
     */
    public function onDealCreate($deal, $event, $arguments = array())
    {
        try {
            if ($this->logHook) {
                $GLOBALS['log']->debug("DealWorkflowManager::onDealCreate triggered for deal: {$deal->id}");
            }
            
            // Auto-assign team based on deal source
            $this->autoAssignTeam($deal);
            
            // Set default probability based on pipeline stage
            $this->setDefaultProbability($deal);
            
            // Create initial checklist items based on stage
            $this->createInitialChecklistItems($deal);
            
            // Send notifications to relevant stakeholders
            $this->sendCreationNotifications($deal);
            
            // Log the workflow execution
            $this->logWorkflowExecution($deal->id, 'deal_create', array(
                'stage' => $deal->pipeline_stage_c,
                'amount' => $deal->amount,
                'assigned_user' => $deal->assigned_user_id
            ));
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('Deal creation workflow failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle pipeline stage change workflow
     */
    public function onStageChange($deal, $event, $arguments = array())
    {
        try {
            $oldStage = $arguments['old_stage'] ?? '';
            $newStage = $arguments['new_stage'] ?? $deal->pipeline_stage_c;
            
            if ($this->logHook) {
                $GLOBALS['log']->debug("DealWorkflowManager::onStageChange triggered: {$oldStage} -> {$newStage}");
            }
            
            // Update probability based on new stage
            $this->updateProbabilityForStage($deal, $newStage);
            
            // Create stage-specific tasks
            $this->createStageSpecificTasks($deal, $newStage);
            
            // Update related checklist items
            $this->updateChecklistForStage($deal, $newStage);
            
            // Send stage change notifications
            $this->sendStageChangeNotifications($deal, $oldStage, $newStage);
            
            // Handle special stage transitions
            $this->handleSpecialTransitions($deal, $oldStage, $newStage);
            
            // Update related reports and dashboards
            $this->updateReportingData($deal, $oldStage, $newStage);
            
            // Log the workflow execution
            $this->logWorkflowExecution($deal->id, 'stage_change', array(
                'old_stage' => $oldStage,
                'new_stage' => $newStage,
                'user_id' => $arguments['user_id'] ?? ''
            ));
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('Stage change workflow failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle deal update workflow
     */
    public function onDealUpdate($deal, $event, $arguments = array())
    {
        try {
            if ($this->logHook) {
                $GLOBALS['log']->debug("DealWorkflowManager::onDealUpdate triggered for deal: {$deal->id}");
            }
            
            // Check for significant changes
            $significantChanges = $this->detectSignificantChanges($deal);
            
            if (!empty($significantChanges)) {
                // Send update notifications
                $this->sendUpdateNotifications($deal, $significantChanges);
                
                // Update related records
                $this->updateRelatedRecords($deal, $significantChanges);
                
                // Log significant changes
                $this->logWorkflowExecution($deal->id, 'deal_update', array(
                    'changes' => $significantChanges
                ));
            }
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('Deal update workflow failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle deal deletion workflow
     */
    public function onDealDelete($deal, $event, $arguments = array())
    {
        try {
            if ($this->logHook) {
                $GLOBALS['log']->debug("DealWorkflowManager::onDealDelete triggered for deal: {$deal->id}");
            }
            
            // Archive related records instead of deleting
            $this->archiveRelatedRecords($deal);
            
            // Send deletion notifications
            $this->sendDeletionNotifications($deal);
            
            // Update reporting data
            $this->updateReportingForDeletion($deal);
            
            // Log the workflow execution
            $this->logWorkflowExecution($deal->id, 'deal_delete', array(
                'name' => $deal->name,
                'stage' => $deal->pipeline_stage_c,
                'amount' => $deal->amount
            ));
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('Deal deletion workflow failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Auto-assign team based on deal source or criteria
     */
    private function autoAssignTeam($deal)
    {
        global $db;
        
        // Team assignment rules based on deal source
        $teamRules = array(
            'web_site' => 'web_leads_team',
            'campaign' => 'marketing_team',
            'partner' => 'partner_team',
            'employee' => 'internal_team'
        );
        
        $source = $deal->deal_source_c ?? '';
        if (isset($teamRules[$source])) {
            $teamName = $teamRules[$source];
            
            // Find team by name
            $query = "SELECT id FROM teams WHERE name = '{$teamName}' AND deleted = 0";
            $result = $db->query($query);
            $team = $db->fetchByAssoc($result);
            
            if ($team) {
                $deal->team_id = $team['id'];
                if ($this->logHook) {
                    $GLOBALS['log']->debug("Auto-assigned deal to team: {$teamName}");
                }
            }
        }
    }
    
    /**
     * Set default probability based on pipeline stage
     */
    private function setDefaultProbability($deal)
    {
        if (empty($deal->probability) || $deal->probability == 0) {
            $stageProbabilities = array(
                'sourcing' => 10,
                'screening' => 20,
                'analysis_outreach' => 30,
                'due_diligence' => 50,
                'valuation_structuring' => 70,
                'loi_negotiation' => 80,
                'financing' => 85,
                'closing' => 90,
                'closed_owned_90_day' => 100,
                'closed_owned_stable' => 100,
                'unavailable' => 0
            );
            
            $stage = $deal->pipeline_stage_c ?? 'sourcing';
            $deal->probability = $stageProbabilities[$stage] ?? 50;
            
            if ($this->logHook) {
                $GLOBALS['log']->debug("Set default probability: {$deal->probability}% for stage: {$stage}");
            }
        }
    }
    
    /**
     * Create initial checklist items for new deals
     */
    private function createInitialChecklistItems($deal)
    {
        global $db;
        
        // Get default checklist template for the stage
        $stage = $deal->pipeline_stage_c ?? 'sourcing';
        $templateQuery = "SELECT id, checklist_items FROM checklist_templates 
                         WHERE stage = '{$stage}' AND is_default = 1 AND deleted = 0";
        
        $result = $db->query($templateQuery);
        $template = $db->fetchByAssoc($result);
        
        if ($template) {
            $items = json_decode($template['checklist_items'], true);
            
            foreach ($items as $item) {
                $checklistId = create_guid();
                $insertQuery = "INSERT INTO checklist_items 
                               (id, deal_id, template_id, title, description, is_required, sort_order, date_entered, deleted)
                               VALUES 
                               ('{$checklistId}', '{$deal->id}', '{$template['id']}', 
                                '{$item['title']}', '{$item['description']}', 
                                {$item['is_required']}, {$item['sort_order']}, NOW(), 0)";
                
                $db->query($insertQuery);
            }
            
            if ($this->logHook) {
                $GLOBALS['log']->debug("Created " . count($items) . " initial checklist items for deal");
            }
        }
    }
    
    /**
     * Create stage-specific tasks
     */
    private function createStageSpecificTasks($deal, $newStage)
    {
        global $db, $current_user;
        
        $stageTasks = array(
            'due_diligence' => array(
                array('name' => 'Review financial statements', 'priority' => 'High'),
                array('name' => 'Verify legal documentation', 'priority' => 'High'),
                array('name' => 'Conduct management interviews', 'priority' => 'Medium')
            ),
            'valuation_structuring' => array(
                array('name' => 'Prepare valuation model', 'priority' => 'High'),
                array('name' => 'Structure deal terms', 'priority' => 'High'),
                array('name' => 'Legal structure review', 'priority' => 'Medium')
            ),
            'loi_negotiation' => array(
                array('name' => 'Draft Letter of Intent', 'priority' => 'High'),
                array('name' => 'Schedule negotiation meetings', 'priority' => 'High'),
                array('name' => 'Review legal implications', 'priority' => 'Medium')
            ),
            'financing' => array(
                array('name' => 'Secure financing approval', 'priority' => 'High'),
                array('name' => 'Finalize loan documents', 'priority' => 'High'),
                array('name' => 'Coordinate with lenders', 'priority' => 'Medium')
            ),
            'closing' => array(
                array('name' => 'Prepare closing checklist', 'priority' => 'High'),
                array('name' => 'Schedule closing meeting', 'priority' => 'High'),
                array('name' => 'Final document review', 'priority' => 'High')
            )
        );
        
        if (isset($stageTasks[$newStage])) {
            foreach ($stageTasks[$newStage] as $taskData) {
                $taskId = create_guid();
                $dueDate = date('Y-m-d', strtotime('+7 days')); // Default 7 days
                
                $insertQuery = "INSERT INTO tasks 
                               (id, name, status, priority, assigned_user_id, parent_type, parent_id, 
                                date_due, date_entered, date_modified, created_by, modified_user_id, deleted)
                               VALUES 
                               ('{$taskId}', '{$taskData['name']}', 'Not Started', '{$taskData['priority']}',
                                '{$deal->assigned_user_id}', 'Opportunities', '{$deal->id}',
                                '{$dueDate}', NOW(), NOW(), '{$current_user->id}', '{$current_user->id}', 0)";
                
                $db->query($insertQuery);
            }
            
            if ($this->logHook) {
                $GLOBALS['log']->debug("Created " . count($stageTasks[$newStage]) . " tasks for stage: {$newStage}");
            }
        }
    }
    
    /**
     * Update probability based on stage
     */
    private function updateProbabilityForStage($deal, $newStage)
    {
        $stageProbabilities = array(
            'sourcing' => 10,
            'screening' => 20,
            'analysis_outreach' => 30,
            'due_diligence' => 50,
            'valuation_structuring' => 70,
            'loi_negotiation' => 80,
            'financing' => 85,
            'closing' => 90,
            'closed_owned_90_day' => 100,
            'closed_owned_stable' => 100,
            'unavailable' => 0
        );
        
        $suggestedProbability = $stageProbabilities[$newStage] ?? 50;
        
        // Only update if current probability is significantly different
        if (abs($deal->probability - $suggestedProbability) > 10) {
            $deal->probability = $suggestedProbability;
            
            if ($this->logHook) {
                $GLOBALS['log']->debug("Updated probability to {$suggestedProbability}% for stage: {$newStage}");
            }
        }
    }
    
    /**
     * Send notifications for various events
     */
    private function sendCreationNotifications($deal)
    {
        // Implementation would depend on SuiteCRM's notification system
        // This is a placeholder for the notification logic
        
        if ($this->logHook) {
            $GLOBALS['log']->debug("Sending creation notifications for deal: {$deal->id}");
        }
    }
    
    private function sendStageChangeNotifications($deal, $oldStage, $newStage)
    {
        if ($this->logHook) {
            $GLOBALS['log']->debug("Sending stage change notifications: {$oldStage} -> {$newStage}");
        }
    }
    
    private function sendUpdateNotifications($deal, $changes)
    {
        if ($this->logHook) {
            $GLOBALS['log']->debug("Sending update notifications for changes: " . implode(', ', array_keys($changes)));
        }
    }
    
    private function sendDeletionNotifications($deal)
    {
        if ($this->logHook) {
            $GLOBALS['log']->debug("Sending deletion notifications for deal: {$deal->id}");
        }
    }
    
    /**
     * Detect significant changes in deal updates
     */
    private function detectSignificantChanges($deal)
    {
        $changes = array();
        $significantFields = array('amount', 'date_closed', 'assigned_user_id', 'probability', 'sales_stage');
        
        foreach ($significantFields as $field) {
            if (isset($deal->fetched_row[$field]) && 
                $deal->$field != $deal->fetched_row[$field]) {
                $changes[$field] = array(
                    'old' => $deal->fetched_row[$field],
                    'new' => $deal->$field
                );
            }
        }
        
        return $changes;
    }
    
    /**
     * Handle special stage transitions
     */
    private function handleSpecialTransitions($deal, $oldStage, $newStage)
    {
        // Handle closing transitions
        if ($newStage === 'closed_owned_90_day' || $newStage === 'closed_owned_stable') {
            $this->handleWonDeal($deal);
        }
        
        // Handle lost deals
        if ($newStage === 'unavailable') {
            $this->handleLostDeal($deal, $oldStage);
        }
        
        // Handle regression (moving backward)
        $stageOrder = array(
            'sourcing' => 1, 'screening' => 2, 'analysis_outreach' => 3,
            'due_diligence' => 4, 'valuation_structuring' => 5, 'loi_negotiation' => 6,
            'financing' => 7, 'closing' => 8, 'closed_owned_90_day' => 9,
            'closed_owned_stable' => 10, 'unavailable' => 11
        );
        
        $oldOrder = $stageOrder[$oldStage] ?? 0;
        $newOrder = $stageOrder[$newStage] ?? 0;
        
        if ($newOrder < $oldOrder && $newStage !== 'unavailable') {
            $this->handleStageRegression($deal, $oldStage, $newStage);
        }
    }
    
    /**
     * Handle won deals
     */
    private function handleWonDeal($deal)
    {
        // Set sales stage to Closed Won
        $deal->sales_stage = 'Closed Won';
        
        // Set probability to 100
        $deal->probability = 100;
        
        // Create post-close follow-up tasks
        $this->createPostCloseFollowUpTasks($deal);
        
        if ($this->logHook) {
            $GLOBALS['log']->debug("Handled won deal: {$deal->id}");
        }
    }
    
    /**
     * Handle lost deals
     */
    private function handleLostDeal($deal, $oldStage)
    {
        // Set sales stage to Closed Lost
        $deal->sales_stage = 'Closed Lost';
        
        // Set probability to 0
        $deal->probability = 0;
        
        // Cancel open tasks
        $this->cancelOpenTasks($deal);
        
        if ($this->logHook) {
            $GLOBALS['log']->debug("Handled lost deal: {$deal->id}");
        }
    }
    
    /**
     * Handle stage regression
     */
    private function handleStageRegression($deal, $oldStage, $newStage)
    {
        // Log the regression for analysis
        $this->logWorkflowExecution($deal->id, 'stage_regression', array(
            'old_stage' => $oldStage,
            'new_stage' => $newStage,
            'reason' => 'Manual stage regression'
        ));
        
        if ($this->logHook) {
            $GLOBALS['log']->debug("Handled stage regression: {$oldStage} -> {$newStage}");
        }
    }
    
    /**
     * Update checklist for stage changes
     */
    private function updateChecklistForStage($deal, $newStage)
    {
        // Implementation would update checklist items based on new stage requirements
        if ($this->logHook) {
            $GLOBALS['log']->debug("Updated checklist for stage: {$newStage}");
        }
    }
    
    /**
     * Update reporting data
     */
    private function updateReportingData($deal, $oldStage, $newStage)
    {
        // Implementation would update dashboard and reporting metrics
        if ($this->logHook) {
            $GLOBALS['log']->debug("Updated reporting data for stage change");
        }
    }
    
    /**
     * Archive related records instead of hard delete
     */
    private function archiveRelatedRecords($deal)
    {
        global $db;
        
        // Archive instead of delete related records
        $tables = array('checklist_items', 'pipeline_stage_history');
        
        foreach ($tables as $table) {
            $query = "UPDATE {$table} SET deleted = 1, date_modified = NOW() 
                      WHERE deal_id = '{$deal->id}' AND deleted = 0";
            $db->query($query);
        }
        
        if ($this->logHook) {
            $GLOBALS['log']->debug("Archived related records for deal: {$deal->id}");
        }
    }
    
    /**
     * Create post-close follow-up tasks
     */
    private function createPostCloseFollowUpTasks($deal)
    {
        // Implementation would create follow-up tasks for closed deals
        if ($this->logHook) {
            $GLOBALS['log']->debug("Created post-close follow-up tasks");
        }
    }
    
    /**
     * Cancel open tasks for lost deals
     */
    private function cancelOpenTasks($deal)
    {
        global $db;
        
        $query = "UPDATE tasks SET status = 'Cancelled', date_modified = NOW() 
                  WHERE parent_type = 'Opportunities' AND parent_id = '{$deal->id}' 
                  AND status NOT IN ('Completed', 'Cancelled') AND deleted = 0";
        
        $db->query($query);
        
        if ($this->logHook) {
            $GLOBALS['log']->debug("Cancelled open tasks for lost deal");
        }
    }
    
    /**
     * Update related records
     */
    private function updateRelatedRecords($deal, $changes)
    {
        // Implementation would update related records based on deal changes
        if ($this->logHook) {
            $GLOBALS['log']->debug("Updated related records for deal changes");
        }
    }
    
    /**
     * Update reporting for deletion
     */
    private function updateReportingForDeletion($deal)
    {
        // Implementation would update metrics after deal deletion
        if ($this->logHook) {
            $GLOBALS['log']->debug("Updated reporting data for deal deletion");
        }
    }
    
    /**
     * Log workflow execution for audit trail
     */
    private function logWorkflowExecution($dealId, $eventType, $data = array())
    {
        global $db, $current_user;
        
        try {
            $logId = create_guid();
            $eventData = json_encode($data);
            $userId = $current_user->id ?? 'system';
            
            $query = "INSERT INTO deals_workflow_log 
                      (id, deal_id, event_type, event_data, user_id, date_created, deleted)
                      VALUES 
                      ('{$logId}', '{$dealId}', '{$eventType}', '{$eventData}', '{$userId}', NOW(), 0)";
            
            // Create table if it doesn't exist
            $this->ensureWorkflowLogTable();
            
            $db->query($query);
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('Failed to log workflow execution: ' . $e->getMessage());
        }
    }
    
    /**
     * Ensure workflow log table exists
     */
    private function ensureWorkflowLogTable()
    {
        global $db;
        
        $createTable = "CREATE TABLE IF NOT EXISTS deals_workflow_log (
            id CHAR(36) NOT NULL PRIMARY KEY,
            deal_id CHAR(36) NOT NULL,
            event_type VARCHAR(50) NOT NULL,
            event_data LONGTEXT,
            user_id CHAR(36) NOT NULL,
            date_created DATETIME NOT NULL,
            deleted TINYINT(1) DEFAULT 0,
            KEY idx_deal_id (deal_id),
            KEY idx_event_type (event_type),
            KEY idx_date_created (date_created)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->query($createTable);
    }
}