<?php
/**
 * Pipeline Maintenance Job
 * Scheduled job for pipeline automation, stale detection, and metrics updates
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once('custom/modules/Pipelines/PipelineAutomationEngine.php');
require_once('custom/modules/Pipelines/StageValidationManager.php');
require_once('custom/modules/Pipelines/LeadConversionEngine.php');

class PipelineMaintenanceJob
{
    protected $db;
    protected $automationEngine;
    protected $validationManager;
    protected $conversionEngine;
    protected $startTime;
    protected $jobId;
    
    public function __construct()
    {
        global $db;
        $this->db = $db;
        $this->startTime = microtime(true);
        $this->jobId = create_guid();
        
        $this->automationEngine = new PipelineAutomationEngine();
        $this->validationManager = new StageValidationManager();
        $this->conversionEngine = new LeadConversionEngine();
    }
    
    /**
     * Main job execution method
     */
    public function run($options = [])
    {
        $GLOBALS['log']->info("Starting Pipeline Maintenance Job {$this->jobId}");
        
        $results = [
            'job_id' => $this->jobId,
            'start_time' => date('Y-m-d H:i:s'),
            'tasks_completed' => [],
            'errors' => [],
            'statistics' => []
        ];
        
        try {
            // Task 1: Update days in stage for all active deals
            $results['tasks_completed']['update_days_in_stage'] = $this->updateDaysInStage();
            
            // Task 2: Detect and handle stale deals
            $results['tasks_completed']['stale_detection'] = $this->detectStaleDeals();
            
            // Task 3: Process lead conversions
            $results['tasks_completed']['lead_conversion'] = $this->processLeadConversions();
            
            // Task 4: Execute automation rules
            $results['tasks_completed']['automation_rules'] = $this->executeAutomationRules();
            
            // Task 5: Update WIP tracking
            $results['tasks_completed']['wip_tracking'] = $this->updateWIPTracking();
            
            // Task 6: Update pipeline analytics
            $results['tasks_completed']['pipeline_analytics'] = $this->updatePipelineAnalytics();
            
            // Task 7: Send notifications and alerts
            $results['tasks_completed']['notifications'] = $this->sendNotifications();
            
            // Task 8: Clean up old data
            $results['tasks_completed']['cleanup'] = $this->cleanupOldData();
            
            // Generate statistics
            $results['statistics'] = $this->generateJobStatistics();
            
        } catch (Exception $e) {
            $results['errors'][] = $e->getMessage();
            $GLOBALS['log']->error("Pipeline Maintenance Job failed: " . $e->getMessage());
        }
        
        $results['end_time'] = date('Y-m-d H:i:s');
        $results['duration_seconds'] = round(microtime(true) - $this->startTime, 2);
        
        // Log job completion
        $this->logJobExecution($results);
        
        $GLOBALS['log']->info("Completed Pipeline Maintenance Job {$this->jobId} in {$results['duration_seconds']} seconds");
        
        return $results;
    }
    
    /**
     * Update days in stage for all active deals
     */
    protected function updateDaysInStage()
    {
        $GLOBALS['log']->info("Updating days in stage for active deals");
        
        $query = "UPDATE mdeal_deals 
                  SET days_in_stage = DATEDIFF(NOW(), stage_entered_date) 
                  WHERE deleted = 0 
                  AND stage NOT IN ('closed_won', 'closed_lost', 'unavailable')
                  AND stage_entered_date IS NOT NULL";
        
        $result = $this->db->query($query);
        $affected = $this->db->getAffectedRowCount($result);
        
        return [
            'deals_updated' => $affected,
            'status' => 'completed'
        ];
    }
    
    /**
     * Detect and handle stale deals
     */
    protected function detectStaleDeals()
    {
        $GLOBALS['log']->info("Detecting stale deals");
        
        $staleDeals = [];
        $alertsCreated = 0;
        
        // Get deals that might be stale
        $query = "SELECT d.*, ps.warning_days, ps.critical_days 
                  FROM mdeal_deals d
                  JOIN mdeal_pipeline_stages ps ON d.stage = ps.name
                  WHERE d.deleted = 0 
                  AND ps.deleted = 0 
                  AND d.stage NOT IN ('closed_won', 'closed_lost', 'unavailable')
                  AND (
                      (ps.warning_days IS NOT NULL AND d.days_in_stage >= ps.warning_days) OR
                      (ps.critical_days IS NOT NULL AND d.days_in_stage >= ps.critical_days)
                  )";
        
        $result = $this->db->query($query);
        
        while ($row = $this->db->fetchByAssoc($result)) {
            $deal = BeanFactory::newBean('mdeal_Deals');
            $deal->populateFromRow($row);
            
            $staleness = $this->calculateStaleness($deal, $row);
            
            // Update deal stale status
            if ($staleness['is_stale']) {
                $deal->is_stale = 1;
                $deal->stale_reason = $staleness['reason'];
                $deal->save();
                
                $staleDeals[] = [
                    'deal_id' => $deal->id,
                    'deal_name' => $deal->name,
                    'stage' => $deal->stage,
                    'days_in_stage' => $deal->days_in_stage,
                    'severity' => $staleness['severity'],
                    'reason' => $staleness['reason']
                ];
                
                // Create alert if needed
                if ($staleness['create_alert']) {
                    $this->createStaleAlert($deal, $staleness);
                    $alertsCreated++;
                }
                
                // Execute escalation actions
                if ($staleness['escalate']) {
                    $this->executeStaleEscalation($deal, $staleness);
                }
            }
        }
        
        return [
            'stale_deals_found' => count($staleDeals),
            'alerts_created' => $alertsCreated,
            'stale_deals' => $staleDeals,
            'status' => 'completed'
        ];
    }
    
    /**
     * Calculate deal staleness
     */
    protected function calculateStaleness($deal, $stageConfig)
    {
        $warningDays = $stageConfig['warning_days'];
        $criticalDays = $stageConfig['critical_days'];
        $daysInStage = $deal->days_in_stage;
        
        $staleness = [
            'is_stale' => false,
            'severity' => 'normal',
            'reason' => '',
            'create_alert' => false,
            'escalate' => false
        ];
        
        if ($criticalDays && $daysInStage >= $criticalDays) {
            $staleness['is_stale'] = true;
            $staleness['severity'] = 'critical';
            $staleness['reason'] = "Deal has been in {$deal->stage} stage for {$daysInStage} days (critical threshold: {$criticalDays} days)";
            $staleness['create_alert'] = true;
            $staleness['escalate'] = true;
        } elseif ($warningDays && $daysInStage >= $warningDays) {
            $staleness['is_stale'] = true;
            $staleness['severity'] = 'warning';
            $staleness['reason'] = "Deal has been in {$deal->stage} stage for {$daysInStage} days (warning threshold: {$warningDays} days)";
            $staleness['create_alert'] = true;
        }
        
        // Check for no recent activity
        $lastActivity = $this->getLastActivityDate($deal->id);
        if ($lastActivity) {
            $daysSinceActivity = $this->daysBetween($lastActivity, new DateTime());
            if ($daysSinceActivity > 30) {
                $staleness['is_stale'] = true;
                $staleness['reason'] .= " No activity for {$daysSinceActivity} days.";
                $staleness['create_alert'] = true;
            }
        }
        
        return $staleness;
    }
    
    /**
     * Process lead conversions
     */
    protected function processLeadConversions()
    {
        $GLOBALS['log']->info("Processing lead conversions");
        
        $batchSize = 25; // Process 25 leads at a time
        $results = $this->conversionEngine->processLeadsForConversion($batchSize);
        
        $conversions = 0;
        $reviews = 0;
        $qualifications = 0;
        
        foreach ($results as $result) {
            switch ($result['conversion_recommendation']) {
                case 'auto_conversion':
                    $conversions++;
                    break;
                case 'review_conversion':
                    $reviews++;
                    break;
                case 'qualification_required':
                    $qualifications++;
                    break;
            }
        }
        
        return [
            'leads_processed' => count($results),
            'auto_conversions' => $conversions,
            'review_required' => $reviews,
            'qualification_required' => $qualifications,
            'status' => 'completed'
        ];
    }
    
    /**
     * Execute automation rules
     */
    protected function executeAutomationRules()
    {
        $GLOBALS['log']->info("Executing automation rules");
        
        $rulesExecuted = 0;
        $totalExecutions = 0;
        $errors = 0;
        
        // Get active automation rules
        $query = "SELECT * FROM mdeal_pipeline_automation_rules 
                  WHERE is_active = 1 AND deleted = 0 
                  AND auto_execute = 1
                  ORDER BY priority ASC";
        
        $result = $this->db->query($query);
        
        while ($row = $this->db->fetchByAssoc($result)) {
            try {
                $executions = $this->executeAutomationRule($row);
                $rulesExecuted++;
                $totalExecutions += $executions;
                
                // Update rule execution count and last execution time
                $this->updateRuleExecution($row['id'], $executions);
                
            } catch (Exception $e) {
                $errors++;
                $GLOBALS['log']->error("Automation rule {$row['id']} failed: " . $e->getMessage());
                $this->logAutomationError($row['id'], $e->getMessage());
            }
        }
        
        return [
            'rules_executed' => $rulesExecuted,
            'total_executions' => $totalExecutions,
            'errors' => $errors,
            'status' => 'completed'
        ];
    }
    
    /**
     * Execute a specific automation rule
     */
    protected function executeAutomationRule($rule)
    {
        $ruleType = $rule['rule_type'];
        $conditions = json_decode($rule['conditions'], true);
        $actions = json_decode($rule['actions'], true);
        $executions = 0;
        
        switch ($ruleType) {
            case 'stage_transition':
                $executions = $this->executeStageTransitionRule($rule, $conditions, $actions);
                break;
                
            case 'stale_detection':
                $executions = $this->executeStaleDetectionRule($rule, $conditions, $actions);
                break;
                
            case 'escalation':
                $executions = $this->executeEscalationRule($rule, $conditions, $actions);
                break;
                
            case 'notification':
                $executions = $this->executeNotificationRule($rule, $conditions, $actions);
                break;
        }
        
        return $executions;
    }
    
    /**
     * Update WIP tracking
     */
    protected function updateWIPTracking()
    {
        $GLOBALS['log']->info("Updating WIP tracking");
        
        // Use stored procedure if available, otherwise manual update
        try {
            $this->db->query("CALL sp_update_wip_tracking()");
            $status = 'completed_via_procedure';
        } catch (Exception $e) {
            // Fallback to manual update
            $status = $this->manualWIPUpdate();
        }
        
        return [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Manual WIP tracking update
     */
    protected function manualWIPUpdate()
    {
        $query = "INSERT INTO mdeal_pipeline_wip_tracking (
                    id, stage, user_id, deal_count, wip_limit, utilization_percent, last_updated
                  )
                  SELECT 
                    UUID() as id,
                    d.stage,
                    d.assigned_user_id,
                    COUNT(d.id) as deal_count,
                    COALESCE(ps.wip_limit, 0) as wip_limit,
                    CASE 
                        WHEN ps.wip_limit > 0 THEN ROUND((COUNT(d.id) / ps.wip_limit) * 100, 2)
                        ELSE 0
                    END as utilization_percent,
                    NOW() as last_updated
                  FROM mdeal_deals d
                  JOIN mdeal_pipeline_stages ps ON d.stage = ps.name
                  WHERE d.deleted = 0 
                  AND ps.deleted = 0 
                  AND ps.is_active = 1
                  AND d.stage NOT IN ('closed_won', 'closed_lost', 'unavailable')
                  GROUP BY d.stage, d.assigned_user_id, ps.wip_limit
                  ON DUPLICATE KEY UPDATE
                    deal_count = VALUES(deal_count),
                    wip_limit = VALUES(wip_limit),
                    utilization_percent = VALUES(utilization_percent),
                    last_updated = VALUES(last_updated)";
        
        $this->db->query($query);
        return 'completed_manual';
    }
    
    /**
     * Update pipeline analytics
     */
    protected function updatePipelineAnalytics()
    {
        $GLOBALS['log']->info("Updating pipeline analytics");
        
        // Use stored procedure if available
        try {
            $this->db->query("CALL sp_update_pipeline_analytics()");
            $status = 'completed_via_procedure';
        } catch (Exception $e) {
            // Fallback to manual update
            $status = $this->manualAnalyticsUpdate();
        }
        
        return [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Manual analytics update
     */
    protected function manualAnalyticsUpdate()
    {
        $query = "INSERT INTO mdeal_pipeline_analytics (
                    id, metric_date, stage, user_id, deals_in_stage, 
                    total_deal_value, avg_deal_value, avg_days_in_stage,
                    stale_deals_count, created_date
                  )
                  SELECT 
                    UUID() as id,
                    CURDATE() as metric_date,
                    d.stage,
                    d.assigned_user_id as user_id,
                    COUNT(d.id) as deals_in_stage,
                    SUM(d.deal_value) as total_deal_value,
                    AVG(d.deal_value) as avg_deal_value,
                    AVG(d.days_in_stage) as avg_days_in_stage,
                    SUM(CASE WHEN d.is_stale = 1 THEN 1 ELSE 0 END) as stale_deals_count,
                    NOW() as created_date
                  FROM mdeal_deals d
                  WHERE d.deleted = 0 
                  AND d.stage NOT IN ('closed_won', 'closed_lost')
                  GROUP BY d.stage, d.assigned_user_id
                  ON DUPLICATE KEY UPDATE
                    deals_in_stage = VALUES(deals_in_stage),
                    total_deal_value = VALUES(total_deal_value),
                    avg_deal_value = VALUES(avg_deal_value),
                    avg_days_in_stage = VALUES(avg_days_in_stage),
                    stale_deals_count = VALUES(stale_deals_count)";
        
        $this->db->query($query);
        return 'completed_manual';
    }
    
    /**
     * Send notifications and alerts
     */
    protected function sendNotifications()
    {
        $GLOBALS['log']->info("Sending notifications and alerts");
        
        $notificationsSent = 0;
        
        // Get pending alerts
        $query = "SELECT * FROM mdeal_pipeline_alerts 
                  WHERE is_acknowledged = 0 
                  AND assigned_to IS NOT NULL
                  ORDER BY severity DESC, created_date ASC
                  LIMIT 50"; // Process max 50 alerts per run
        
        $result = $this->db->query($query);
        
        while ($row = $this->db->fetchByAssoc($result)) {
            try {
                $this->sendAlertNotification($row);
                $notificationsSent++;
                
                // Update alert as notified
                $this->markAlertNotified($row['id']);
                
            } catch (Exception $e) {
                $GLOBALS['log']->error("Failed to send alert notification {$row['id']}: " . $e->getMessage());
            }
        }
        
        return [
            'notifications_sent' => $notificationsSent,
            'status' => 'completed'
        ];
    }
    
    /**
     * Clean up old data
     */
    protected function cleanupOldData()
    {
        $GLOBALS['log']->info("Cleaning up old data");
        
        $cleaned = [];
        
        // Clean up old pipeline transitions (keep 2 years)
        $query = "DELETE FROM mdeal_pipeline_transitions 
                  WHERE created_date < DATE_SUB(NOW(), INTERVAL 2 YEAR)";
        $result = $this->db->query($query);
        $cleaned['old_transitions'] = $this->db->getAffectedRowCount($result);
        
        // Clean up old automation logs (keep 6 months)
        $query = "DELETE FROM mdeal_pipeline_automation_log 
                  WHERE created_date < DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        $result = $this->db->query($query);
        $cleaned['old_automation_logs'] = $this->db->getAffectedRowCount($result);
        
        // Clean up old lead scoring history (keep 1 year)
        $query = "DELETE FROM mdeal_lead_scoring_history 
                  WHERE created_date < DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        $result = $this->db->query($query);
        $cleaned['old_scoring_history'] = $this->db->getAffectedRowCount($result);
        
        // Clean up old analytics data (keep 3 years)
        $query = "DELETE FROM mdeal_pipeline_analytics 
                  WHERE created_date < DATE_SUB(NOW(), INTERVAL 3 YEAR)";
        $result = $this->db->query($query);
        $cleaned['old_analytics'] = $this->db->getAffectedRowCount($result);
        
        return [
            'cleaned_records' => $cleaned,
            'status' => 'completed'
        ];
    }
    
    /**
     * Generate job statistics
     */
    protected function generateJobStatistics()
    {
        return [
            'total_active_deals' => $this->getActiveDealCount(),
            'pipeline_health_score' => $this->calculatePipelineHealthScore(),
            'conversion_rate' => $this->calculateConversionRate(),
            'average_deal_duration' => $this->calculateAverageDealDuration(),
            'wip_violations' => $this->getWIPViolations(),
            'stale_deal_percentage' => $this->getStaleDealPercentage()
        ];
    }
    
    /**
     * Get active deal count
     */
    protected function getActiveDealCount()
    {
        $query = "SELECT COUNT(*) as count FROM mdeal_deals 
                  WHERE deleted = 0 AND stage NOT IN ('closed_won', 'closed_lost', 'unavailable')";
        $result = $this->db->query($query);
        $row = $this->db->fetchByAssoc($result);
        return $row['count'] ?? 0;
    }
    
    /**
     * Calculate pipeline health score
     */
    protected function calculatePipelineHealthScore()
    {
        $query = "SELECT AVG(health_score) as avg_score FROM mdeal_deals 
                  WHERE deleted = 0 AND stage NOT IN ('closed_won', 'closed_lost', 'unavailable')
                  AND health_score IS NOT NULL";
        $result = $this->db->query($query);
        $row = $this->db->fetchByAssoc($result);
        return round($row['avg_score'] ?? 0, 2);
    }
    
    /**
     * Calculate conversion rate
     */
    protected function calculateConversionRate()
    {
        $query = "SELECT 
                    COUNT(CASE WHEN stage = 'closed_won' THEN 1 END) as won,
                    COUNT(CASE WHEN stage IN ('closed_won', 'closed_lost') THEN 1 END) as total_closed
                  FROM mdeal_deals 
                  WHERE deleted = 0 
                  AND date_entered >= DATE_SUB(NOW(), INTERVAL 12 MONTH)";
        
        $result = $this->db->query($query);
        $row = $this->db->fetchByAssoc($result);
        
        if ($row['total_closed'] > 0) {
            return round(($row['won'] / $row['total_closed']) * 100, 2);
        }
        
        return 0;
    }
    
    /**
     * Calculate average deal duration
     */
    protected function calculateAverageDealDuration()
    {
        $query = "SELECT AVG(DATEDIFF(close_date, date_entered)) as avg_duration 
                  FROM mdeal_deals 
                  WHERE deleted = 0 
                  AND stage = 'closed_won'
                  AND close_date IS NOT NULL 
                  AND date_entered IS NOT NULL
                  AND close_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)";
        
        $result = $this->db->query($query);
        $row = $this->db->fetchByAssoc($result);
        return round($row['avg_duration'] ?? 0, 1);
    }
    
    /**
     * Get WIP violations count
     */
    protected function getWIPViolations()
    {
        $query = "SELECT COUNT(*) as violations FROM mdeal_pipeline_wip_tracking 
                  WHERE utilization_percent > 100";
        $result = $this->db->query($query);
        $row = $this->db->fetchByAssoc($result);
        return $row['violations'] ?? 0;
    }
    
    /**
     * Get stale deal percentage
     */
    protected function getStaleDealPercentage()
    {
        $query = "SELECT 
                    COUNT(CASE WHEN is_stale = 1 THEN 1 END) as stale,
                    COUNT(*) as total
                  FROM mdeal_deals 
                  WHERE deleted = 0 AND stage NOT IN ('closed_won', 'closed_lost', 'unavailable')";
        
        $result = $this->db->query($query);
        $row = $this->db->fetchByAssoc($result);
        
        if ($row['total'] > 0) {
            return round(($row['stale'] / $row['total']) * 100, 2);
        }
        
        return 0;
    }
    
    /**
     * Create stale alert
     */
    protected function createStaleAlert($deal, $staleness)
    {
        $alertId = create_guid();
        
        $query = "INSERT INTO mdeal_pipeline_alerts (
                    id, alert_type, deal_id, stage, alert_message, severity, 
                    assigned_to, due_date, created_date
                  ) VALUES (?, 'stale_deal', ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 3 DAY), NOW())";
        
        $this->db->pQuery($query, [
            $alertId,
            $deal->id,
            $deal->stage,
            $staleness['reason'],
            $staleness['severity'],
            $deal->assigned_user_id
        ]);
    }
    
    /**
     * Execute stale escalation
     */
    protected function executeStaleEscalation($deal, $staleness)
    {
        // Create escalation task for manager
        $task = BeanFactory::newBean('Tasks');
        $task->name = "Escalation: Stale Deal - {$deal->name}";
        $task->description = "Deal has become stale: {$staleness['reason']}\n\nPlease review and take appropriate action.";
        $task->parent_type = 'mdeal_Deals';
        $task->parent_id = $deal->id;
        $task->assigned_user_id = $this->getManagerUser($deal->assigned_user_id);
        $task->priority = 'High';
        $task->status = 'Not Started';
        $task->date_due = date('Y-m-d', strtotime('+2 days'));
        $task->save();
    }
    
    /**
     * Get manager user for escalation
     */
    protected function getManagerUser($userId)
    {
        // This would implement logic to find the user's manager
        // For now, return the same user
        return $userId;
    }
    
    /**
     * Get last activity date for a deal
     */
    protected function getLastActivityDate($dealId)
    {
        $query = "SELECT MAX(activity_date) as last_activity FROM (
                    SELECT date_modified as activity_date FROM tasks 
                    WHERE parent_type = 'mdeal_Deals' AND parent_id = ? AND deleted = 0
                    UNION ALL
                    SELECT date_modified as activity_date FROM calls 
                    WHERE parent_type = 'mdeal_Deals' AND parent_id = ? AND deleted = 0
                    UNION ALL
                    SELECT date_modified as activity_date FROM meetings 
                    WHERE parent_type = 'mdeal_Deals' AND parent_id = ? AND deleted = 0
                    UNION ALL
                    SELECT date_modified as activity_date FROM notes 
                    WHERE parent_type = 'mdeal_Deals' AND parent_id = ? AND deleted = 0
                    UNION ALL
                    SELECT date_modified as activity_date FROM emails 
                    WHERE parent_type = 'mdeal_Deals' AND parent_id = ? AND deleted = 0
                  ) activities";
        
        $result = $this->db->pQuery($query, [$dealId, $dealId, $dealId, $dealId, $dealId]);
        $row = $this->db->fetchByAssoc($result);
        
        if ($row['last_activity']) {
            return new DateTime($row['last_activity']);
        }
        
        return null;
    }
    
    /**
     * Calculate days between dates
     */
    protected function daysBetween($date1, $date2)
    {
        return $date1->diff($date2)->days;
    }
    
    /**
     * Update rule execution statistics
     */
    protected function updateRuleExecution($ruleId, $executions)
    {
        $query = "UPDATE mdeal_pipeline_automation_rules 
                  SET execution_count = execution_count + ?, last_execution = NOW()
                  WHERE id = ?";
        
        $this->db->pQuery($query, [$executions, $ruleId]);
    }
    
    /**
     * Log automation error
     */
    protected function logAutomationError($ruleId, $error)
    {
        $logId = create_guid();
        
        $query = "INSERT INTO mdeal_pipeline_automation_log (
                    id, rule_id, target_id, target_type, execution_date,
                    execution_status, result_message, created_date
                  ) VALUES (?, ?, '', 'error', NOW(), 'error', ?, NOW())";
        
        $this->db->pQuery($query, [$logId, $ruleId, $error]);
    }
    
    /**
     * Send alert notification
     */
    protected function sendAlertNotification($alert)
    {
        // This would implement email notifications
        $GLOBALS['log']->info("Sending alert notification for alert {$alert['id']}");
    }
    
    /**
     * Mark alert as notified
     */
    protected function markAlertNotified($alertId)
    {
        $query = "UPDATE mdeal_pipeline_alerts 
                  SET is_acknowledged = 1, acknowledged_date = NOW()
                  WHERE id = ?";
        
        $this->db->pQuery($query, [$alertId]);
    }
    
    /**
     * Log job execution for audit trail
     */
    protected function logJobExecution($results)
    {
        $logId = create_guid();
        
        $query = "INSERT INTO mdeal_pipeline_job_log (
                    id, job_type, job_id, start_time, end_time, duration_seconds,
                    tasks_completed, errors_count, status, results_data, created_date
                  ) VALUES (?, 'maintenance', ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $status = empty($results['errors']) ? 'success' : 'partial_success';
        $tasksCompleted = count($results['tasks_completed']);
        $errorsCount = count($results['errors']);
        
        $this->db->pQuery($query, [
            $logId,
            $this->jobId,
            $results['start_time'],
            $results['end_time'],
            $results['duration_seconds'],
            $tasksCompleted,
            $errorsCount,
            $status,
            json_encode($results)
        ]);
    }
}

// Create additional table for job logging if it doesn't exist
$createJobLogTable = "
CREATE TABLE IF NOT EXISTS mdeal_pipeline_job_log (
    id CHAR(36) NOT NULL PRIMARY KEY,
    job_type VARCHAR(50) NOT NULL,
    job_id CHAR(36) NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    duration_seconds DECIMAL(8,2) NOT NULL,
    tasks_completed INT DEFAULT 0,
    errors_count INT DEFAULT 0,
    status ENUM('success', 'partial_success', 'failed') NOT NULL,
    results_data TEXT NULL,
    created_date DATETIME NULL,
    
    INDEX idx_job_log_type (job_type, start_time),
    INDEX idx_job_log_status (status, start_time),
    INDEX idx_job_log_date (start_time)
)";

// This would be executed during installation
// $GLOBALS['db']->query($createJobLogTable);