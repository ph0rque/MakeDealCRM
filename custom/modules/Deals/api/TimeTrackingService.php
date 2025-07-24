<?php
/**
 * Time Tracking Service for Pipeline Deals
 * 
 * Provides comprehensive time-in-stage tracking with:
 * - Real-time time calculations
 * - Alert mechanism for overdue deals
 * - Notification system
 * - Performance analytics
 * - SLA monitoring
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/MVC/Controller/SugarController.php');

class TimeTrackingService
{
    private $db;
    private $alertManager;
    private $notificationService;
    
    // Stage time thresholds (in days)
    private $stageThresholds = [
        'sourcing' => [
            'warning' => 7,
            'critical' => 14,
            'overdue' => 21
        ],
        'screening' => [
            'warning' => 5,
            'critical' => 10,
            'overdue' => 15
        ],
        'analysis_outreach' => [
            'warning' => 7,
            'critical' => 14,
            'overdue' => 21
        ],
        'due_diligence' => [
            'warning' => 10,
            'critical' => 20,
            'overdue' => 30
        ],
        'valuation_structuring' => [
            'warning' => 7,
            'critical' => 14,
            'overdue' => 21
        ],
        'loi_negotiation' => [
            'warning' => 5,
            'critical' => 10,
            'overdue' => 15
        ],
        'financing' => [
            'warning' => 10,
            'critical' => 20,
            'overdue' => 30
        ],
        'closing' => [
            'warning' => 7,
            'critical' => 14,
            'overdue' => 21
        ],
        'closed_owned_90_day' => [
            'warning' => 30,
            'critical' => 60,
            'overdue' => 90
        ]
    ];
    
    // Alert escalation levels
    private $escalationLevels = [
        'warning' => ['assigned_user', 'manager'],
        'critical' => ['assigned_user', 'manager', 'senior_manager'],
        'overdue' => ['assigned_user', 'manager', 'senior_manager', 'director']
    ];
    
    public function __construct()
    {
        global $db;
        $this->db = $db;
        $this->alertManager = new StageAlertManager();
        $this->notificationService = new DealNotificationService();
    }
    
    /**
     * Calculate time in stage for a deal
     */
    public function calculateTimeInStage($dealId)
    {
        $deal = BeanFactory::getBean('Opportunities', $dealId);
        if (!$deal || $deal->deleted) {
            return null;
        }
        
        $stageEnteredDate = $deal->stage_entered_date_c ?: $deal->date_entered;
        if (!$stageEnteredDate) {
            return null;
        }
        
        $entered = new DateTime($stageEnteredDate);
        $now = new DateTime();
        $interval = $now->diff($entered);
        
        $daysInStage = $interval->days;
        $hoursInStage = $interval->h + ($interval->days * 24);
        $totalHours = round($hoursInStage + ($interval->i / 60), 2);
        
        $stage = $deal->pipeline_stage_c ?: 'sourcing';
        $thresholds = $this->stageThresholds[$stage] ?? null;
        
        $alertLevel = 'normal';
        if ($thresholds) {
            if ($daysInStage >= $thresholds['overdue']) {
                $alertLevel = 'overdue';
            } elseif ($daysInStage >= $thresholds['critical']) {
                $alertLevel = 'critical';
            } elseif ($daysInStage >= $thresholds['warning']) {
                $alertLevel = 'warning';
            }
        }
        
        return [
            'deal_id' => $dealId,
            'stage' => $stage,
            'stage_entered_date' => $stageEnteredDate,
            'days_in_stage' => $daysInStage,
            'hours_in_stage' => $totalHours,
            'alert_level' => $alertLevel,
            'thresholds' => $thresholds,
            'next_threshold' => $this->getNextThreshold($daysInStage, $thresholds),
            'is_overdue' => $alertLevel === 'overdue',
            'is_at_risk' => in_array($alertLevel, ['critical', 'overdue'])
        ];
    }
    
    /**
     * Get next threshold for a deal
     */
    private function getNextThreshold($currentDays, $thresholds)
    {
        if (!$thresholds) {
            return null;
        }
        
        if ($currentDays < $thresholds['warning']) {
            return [
                'level' => 'warning',
                'days' => $thresholds['warning'],
                'days_remaining' => $thresholds['warning'] - $currentDays
            ];
        } elseif ($currentDays < $thresholds['critical']) {
            return [
                'level' => 'critical',
                'days' => $thresholds['critical'],
                'days_remaining' => $thresholds['critical'] - $currentDays
            ];
        } elseif ($currentDays < $thresholds['overdue']) {
            return [
                'level' => 'overdue',
                'days' => $thresholds['overdue'],
                'days_remaining' => $thresholds['overdue'] - $currentDays
            ];
        }
        
        return null; // Already past all thresholds
    }
    
    /**
     * Get all deals with time tracking data
     */
    public function getAllDealsWithTimeTracking($filters = [])
    {
        $whereConditions = ["d.deleted = 0"];
        
        // Apply filters
        if (!empty($filters['stage'])) {
            $stage = $this->db->quote($filters['stage']);
            $whereConditions[] = "c.pipeline_stage_c = $stage";
        }
        
        if (!empty($filters['alert_level'])) {
            // This will be filtered in PHP after calculation
        }
        
        if (!empty($filters['assigned_user_id'])) {
            $userId = $this->db->quote($filters['assigned_user_id']);
            $whereConditions[] = "d.assigned_user_id = $userId";
        }
        
        $whereClause = implode(" AND ", $whereConditions);
        
        $query = "SELECT 
                    d.id,
                    d.name,
                    d.amount,
                    d.probability,
                    d.assigned_user_id,
                    d.date_entered,
                    c.pipeline_stage_c,
                    c.stage_entered_date_c,
                    c.focus_flag_c,
                    a.name as account_name,
                    CONCAT(u.first_name, ' ', u.last_name) as assigned_user_name
                  FROM opportunities d
                  LEFT JOIN opportunities_cstm c ON d.id = c.id_c
                  LEFT JOIN accounts a ON d.account_id = a.id AND a.deleted = 0
                  LEFT JOIN users u ON d.assigned_user_id = u.id AND u.deleted = 0
                  WHERE $whereClause
                  AND d.sales_stage NOT IN ('Closed Won', 'Closed Lost')
                  ORDER BY c.stage_entered_date_c ASC";
        
        $result = $this->db->query($query);
        $deals = [];
        
        while ($row = $this->db->fetchByAssoc($result)) {
            $timeData = $this->calculateTimeInStageFromRow($row);
            
            // Apply alert level filter if specified
            if (!empty($filters['alert_level']) && $timeData['alert_level'] !== $filters['alert_level']) {
                continue;
            }
            
            $deals[] = array_merge($row, $timeData);
        }
        
        return $deals;
    }
    
    /**
     * Calculate time data from database row
     */
    private function calculateTimeInStageFromRow($row)
    {
        $stageEnteredDate = $row['stage_entered_date_c'] ?: $row['date_entered'];
        $stage = $row['pipeline_stage_c'] ?: 'sourcing';
        
        if (!$stageEnteredDate) {
            return [
                'days_in_stage' => 0,
                'hours_in_stage' => 0,
                'alert_level' => 'normal',
                'is_overdue' => false,
                'is_at_risk' => false
            ];
        }
        
        $entered = new DateTime($stageEnteredDate);
        $now = new DateTime();
        $interval = $now->diff($entered);
        $daysInStage = $interval->days;
        
        $thresholds = $this->stageThresholds[$stage] ?? null;
        $alertLevel = 'normal';
        
        if ($thresholds) {
            if ($daysInStage >= $thresholds['overdue']) {
                $alertLevel = 'overdue';
            } elseif ($daysInStage >= $thresholds['critical']) {
                $alertLevel = 'critical';
            } elseif ($daysInStage >= $thresholds['warning']) {
                $alertLevel = 'warning';
            }
        }
        
        return [
            'days_in_stage' => $daysInStage,
            'hours_in_stage' => round($interval->h + ($interval->days * 24) + ($interval->i / 60), 2),
            'alert_level' => $alertLevel,
            'thresholds' => $thresholds,
            'next_threshold' => $this->getNextThreshold($daysInStage, $thresholds),
            'is_overdue' => $alertLevel === 'overdue',
            'is_at_risk' => in_array($alertLevel, ['critical', 'overdue'])
        ];
    }
    
    /**
     * Process time-based alerts for all deals
     */
    public function processTimeAlerts()
    {
        $deals = $this->getAllDealsWithTimeTracking();
        $alertsSent = 0;
        $alertsProcessed = 0;
        
        foreach ($deals as $deal) {
            $alertsProcessed++;
            
            if ($deal['alert_level'] !== 'normal') {
                $alertSent = $this->processDealAlert($deal);
                if ($alertSent) {
                    $alertsSent++;
                }
            }
        }
        
        // Log alert processing summary
        $this->logAlertProcessing($alertsProcessed, $alertsSent);
        
        return [
            'processed' => $alertsProcessed,
            'alerts_sent' => $alertsSent,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Process alert for a specific deal
     */
    private function processDealAlert($deal)
    {
        $alertLevel = $deal['alert_level'];
        $dealId = $deal['id'];
        
        // Check if alert was already sent recently
        if ($this->wasAlertSentRecently($dealId, $alertLevel)) {
            return false;
        }
        
        // Create alert record
        $alertId = $this->alertManager->createAlert($deal, $alertLevel);
        
        // Send notifications to appropriate recipients
        $recipients = $this->getAlertRecipients($deal, $alertLevel);
        $notificationsSent = 0;
        
        foreach ($recipients as $recipient) {
            $sent = $this->notificationService->sendTimeAlert($deal, $recipient, $alertLevel);
            if ($sent) {
                $notificationsSent++;
            }
        }
        
        // Log alert
        $this->logDealAlert($dealId, $alertLevel, $notificationsSent);
        
        return $notificationsSent > 0;
    }
    
    /**
     * Check if alert was sent recently
     */
    private function wasAlertSentRecently($dealId, $alertLevel)
    {
        $hours = $this->getAlertCooldownHours($alertLevel);
        
        $query = "SELECT COUNT(*) as count 
                  FROM deal_time_alerts 
                  WHERE deal_id = '$dealId' 
                  AND alert_level = '$alertLevel' 
                  AND created_date > DATE_SUB(NOW(), INTERVAL $hours HOUR)
                  AND deleted = 0";
        
        if ($this->tableExists('deal_time_alerts')) {
            $result = $this->db->query($query);
            $row = $this->db->fetchByAssoc($result);
            return intval($row['count']) > 0;
        }
        
        return false;
    }
    
    /**
     * Get cooldown hours for alert type
     */
    private function getAlertCooldownHours($alertLevel)
    {
        $cooldowns = [
            'warning' => 24,    // 1 day
            'critical' => 12,   // 12 hours
            'overdue' => 6      // 6 hours
        ];
        
        return $cooldowns[$alertLevel] ?? 24;
    }
    
    /**
     * Get alert recipients based on escalation rules
     */
    private function getAlertRecipients($deal, $alertLevel)
    {
        $recipients = [];
        $roles = $this->escalationLevels[$alertLevel] ?? ['assigned_user'];
        
        foreach ($roles as $role) {
            switch ($role) {
                case 'assigned_user':
                    if ($deal['assigned_user_id']) {
                        $recipients[] = [
                            'type' => 'user',
                            'id' => $deal['assigned_user_id'],
                            'name' => $deal['assigned_user_name'],
                            'role' => 'assigned_user'
                        ];
                    }
                    break;
                    
                case 'manager':
                    $manager = $this->getManagerForUser($deal['assigned_user_id']);
                    if ($manager) {
                        $recipients[] = $manager;
                    }
                    break;
                    
                case 'senior_manager':
                    $seniorManager = $this->getSeniorManagerForUser($deal['assigned_user_id']);
                    if ($seniorManager) {
                        $recipients[] = $seniorManager;
                    }
                    break;
                    
                case 'director':
                    $director = $this->getDirectorForUser($deal['assigned_user_id']);
                    if ($director) {
                        $recipients[] = $director;
                    }
                    break;
            }
        }
        
        return array_unique($recipients, SORT_REGULAR);
    }
    
    /**
     * Get manager for a user
     */
    private function getManagerForUser($userId)
    {
        $query = "SELECT 
                    u.id,
                    CONCAT(u.first_name, ' ', u.last_name) as name,
                    u.email1 as email
                  FROM users u
                  JOIN users subordinate ON subordinate.reports_to_id = u.id
                  WHERE subordinate.id = '$userId' 
                  AND u.deleted = 0 
                  AND subordinate.deleted = 0";
        
        $result = $this->db->query($query);
        $row = $this->db->fetchByAssoc($result);
        
        if ($row) {
            return [
                'type' => 'user',
                'id' => $row['id'],
                'name' => $row['name'],
                'email' => $row['email'],
                'role' => 'manager'
            ];
        }
        
        return null;
    }
    
    /**
     * Get senior manager (placeholder - would integrate with org chart)
     */
    private function getSeniorManagerForUser($userId)
    {
        // This would integrate with organizational hierarchy
        // For now, return null
        return null;
    }
    
    /**
     * Get director (placeholder - would integrate with org chart)
     */
    private function getDirectorForUser($userId)
    {
        // This would integrate with organizational hierarchy
        // For now, return null
        return null;
    }
    
    /**
     * Get time tracking statistics
     */
    public function getTimeTrackingStatistics($filters = [])
    {
        $deals = $this->getAllDealsWithTimeTracking($filters);
        
        $stats = [
            'total_deals' => count($deals),
            'alert_summary' => [
                'normal' => 0,
                'warning' => 0,
                'critical' => 0,
                'overdue' => 0
            ],
            'average_time_by_stage' => [],
            'longest_in_stage' => [],
            'sla_performance' => []
        ];
        
        $stageGroups = [];
        
        foreach ($deals as $deal) {
            // Count alert levels
            $stats['alert_summary'][$deal['alert_level']]++;
            
            // Group by stage for averages
            $stage = $deal['pipeline_stage_c'] ?: 'sourcing';
            if (!isset($stageGroups[$stage])) {
                $stageGroups[$stage] = [];
            }
            $stageGroups[$stage][] = $deal['days_in_stage'];
        }
        
        // Calculate averages
        foreach ($stageGroups as $stage => $days) {
            $stats['average_time_by_stage'][$stage] = [
                'average_days' => round(array_sum($days) / count($days), 1),
                'median_days' => $this->calculateMedian($days),
                'deal_count' => count($days),
                'max_days' => max($days),
                'min_days' => min($days)
            ];
        }
        
        // Find longest deals per stage
        foreach ($deals as $deal) {
            $stage = $deal['pipeline_stage_c'] ?: 'sourcing';
            if (!isset($stats['longest_in_stage'][$stage]) || 
                $deal['days_in_stage'] > $stats['longest_in_stage'][$stage]['days']) {
                $stats['longest_in_stage'][$stage] = [
                    'deal_id' => $deal['id'],
                    'deal_name' => $deal['name'],
                    'days' => $deal['days_in_stage'],
                    'alert_level' => $deal['alert_level']
                ];
            }
        }
        
        // Calculate SLA performance
        $stats['sla_performance'] = $this->calculateSLAPerformance($deals);
        
        return $stats;
    }
    
    /**
     * Calculate median of array
     */
    private function calculateMedian($array)
    {
        sort($array);
        $count = count($array);
        $middle = floor($count / 2);
        
        if ($count % 2 == 0) {
            return ($array[$middle - 1] + $array[$middle]) / 2;
        } else {
            return $array[$middle];
        }
    }
    
    /**
     * Calculate SLA performance metrics
     */
    private function calculateSLAPerformance($deals)
    {
        $performance = [];
        
        foreach ($this->stageThresholds as $stage => $thresholds) {
            $stageDeals = array_filter($deals, function($deal) use ($stage) {
                return ($deal['pipeline_stage_c'] ?: 'sourcing') === $stage;
            });
            
            if (empty($stageDeals)) {
                continue;
            }
            
            $totalDeals = count($stageDeals);
            $withinSLA = 0;
            $nearSLA = 0;
            $overSLA = 0;
            
            foreach ($stageDeals as $deal) {
                $days = $deal['days_in_stage'];
                if ($days < $thresholds['warning']) {
                    $withinSLA++;
                } elseif ($days < $thresholds['overdue']) {
                    $nearSLA++;
                } else {
                    $overSLA++;
                }
            }
            
            $performance[$stage] = [
                'total_deals' => $totalDeals,
                'within_sla' => $withinSLA,
                'within_sla_percent' => round(($withinSLA / $totalDeals) * 100, 1),
                'near_sla' => $nearSLA,
                'near_sla_percent' => round(($nearSLA / $totalDeals) * 100, 1),
                'over_sla' => $overSLA,
                'over_sla_percent' => round(($overSLA / $totalDeals) * 100, 1),
                'thresholds' => $thresholds
            ];
        }
        
        return $performance;
    }
    
    /**
     * Get deals approaching thresholds
     */
    public function getDealsApproachingThresholds($daysAhead = 2)
    {
        $deals = $this->getAllDealsWithTimeTracking();
        $approaching = [];
        
        foreach ($deals as $deal) {
            $nextThreshold = $deal['next_threshold'];
            if ($nextThreshold && $nextThreshold['days_remaining'] <= $daysAhead) {
                $approaching[] = [
                    'deal' => $deal,
                    'threshold' => $nextThreshold,
                    'days_until_threshold' => $nextThreshold['days_remaining']
                ];
            }
        }
        
        // Sort by days remaining
        usort($approaching, function($a, $b) {
            return $a['days_until_threshold'] - $b['days_until_threshold'];
        });
        
        return $approaching;
    }
    
    /**
     * Update stage thresholds
     */
    public function updateStageThresholds($stage, $thresholds)
    {
        if (!isset($this->stageThresholds[$stage])) {
            throw new Exception("Invalid stage: $stage");
        }
        
        $requiredKeys = ['warning', 'critical', 'overdue'];
        foreach ($requiredKeys as $key) {
            if (!isset($thresholds[$key]) || !is_numeric($thresholds[$key])) {
                throw new Exception("Invalid threshold value for $key");
            }
        }
        
        // Validate threshold order
        if ($thresholds['warning'] >= $thresholds['critical'] || 
            $thresholds['critical'] >= $thresholds['overdue']) {
            throw new Exception("Thresholds must be in ascending order: warning < critical < overdue");
        }
        
        $this->stageThresholds[$stage] = $thresholds;
        
        // Save to database or configuration
        $this->saveThresholdConfiguration();
        
        return true;
    }
    
    /**
     * Save threshold configuration
     */
    private function saveThresholdConfiguration()
    {
        // This would save thresholds to database or config file
        // For now, they're hardcoded in the class
    }
    
    /**
     * Log alert processing
     */
    private function logAlertProcessing($processed, $sent)
    {
        $query = "INSERT INTO alert_processing_log 
                  (id, processed_count, alerts_sent, processing_date, date_entered) 
                  VALUES 
                  (UUID(), $processed, $sent, NOW(), NOW())";
        
        if ($this->tableExists('alert_processing_log')) {
            $this->db->query($query);
        }
    }
    
    /**
     * Log individual deal alert
     */
    private function logDealAlert($dealId, $alertLevel, $notificationsSent)
    {
        $query = "INSERT INTO deal_time_alerts 
                  (id, deal_id, alert_level, notifications_sent, created_date, date_entered) 
                  VALUES 
                  (UUID(), '$dealId', '$alertLevel', $notificationsSent, NOW(), NOW())";
        
        if ($this->tableExists('deal_time_alerts')) {
            $this->db->query($query);
        }
    }
    
    /**
     * Check if table exists
     */
    private function tableExists($tableName)
    {
        $query = "SHOW TABLES LIKE '$tableName'";
        $result = $this->db->query($query);
        return (bool)$this->db->fetchByAssoc($result);
    }
    
    /**
     * Get stage thresholds
     */
    public function getStageThresholds($stage = null)
    {
        if ($stage) {
            return $this->stageThresholds[$stage] ?? null;
        }
        return $this->stageThresholds;
    }
}

/**
 * Stage Alert Manager
 */
class StageAlertManager
{
    private $db;
    
    public function __construct()
    {
        global $db;
        $this->db = $db;
    }
    
    /**
     * Create alert record
     */
    public function createAlert($deal, $alertLevel)
    {
        $alertId = create_guid();
        
        $query = "INSERT INTO pipeline_stage_alerts 
                  (id, deal_id, deal_name, stage, alert_level, days_in_stage, 
                   assigned_user_id, created_date, date_entered) 
                  VALUES 
                  (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        if ($this->tableExists('pipeline_stage_alerts')) {
            $this->db->pQuery($query, [
                $alertId,
                $deal['id'],
                $deal['name'],
                $deal['pipeline_stage_c'] ?: 'sourcing',
                $alertLevel,
                $deal['days_in_stage'],
                $deal['assigned_user_id']
            ]);
        }
        
        return $alertId;
    }
    
    /**
     * Check if table exists
     */
    private function tableExists($tableName)
    {
        $query = "SHOW TABLES LIKE '$tableName'";
        $result = $this->db->query($query);
        return (bool)$this->db->fetchByAssoc($result);
    }
}

/**
 * Deal Notification Service
 */
class DealNotificationService
{
    private $db;
    
    public function __construct()
    {
        global $db;
        $this->db = $db;
    }
    
    /**
     * Send time-based alert notification
     */
    public function sendTimeAlert($deal, $recipient, $alertLevel)
    {
        $subject = $this->getAlertSubject($deal, $alertLevel);
        $body = $this->getAlertBody($deal, $recipient, $alertLevel);
        
        // Send email notification
        $emailSent = $this->sendEmail($recipient, $subject, $body);
        
        // Send in-app notification
        $inAppSent = $this->sendInAppNotification($recipient, $deal, $alertLevel);
        
        // Log notification
        $this->logNotification($deal['id'], $recipient, $alertLevel, $emailSent, $inAppSent);
        
        return $emailSent || $inAppSent;
    }
    
    /**
     * Get alert subject
     */
    private function getAlertSubject($deal, $alertLevel)
    {
        $levelText = ucfirst($alertLevel);
        $stageName = $this->getStageDisplayName($deal['pipeline_stage_c'] ?: 'sourcing');
        
        return "$levelText Alert: Deal '{$deal['name']}' in $stageName stage for {$deal['days_in_stage']} days";
    }
    
    /**
     * Get alert body
     */
    private function getAlertBody($deal, $recipient, $alertLevel)
    {
        $stageName = $this->getStageDisplayName($deal['pipeline_stage_c'] ?: 'sourcing');
        $levelText = ucfirst($alertLevel);
        
        $body = "Dear {$recipient['name']},\n\n";
        $body .= "This is a $levelText alert for the following deal:\n\n";
        $body .= "Deal: {$deal['name']}\n";
        $body .= "Stage: $stageName\n";
        $body .= "Time in Stage: {$deal['days_in_stage']} days\n";
        $body .= "Amount: $" . number_format($deal['amount'] ?: 0) . "\n";
        $body .= "Assigned to: {$deal['assigned_user_name']}\n";
        
        if ($deal['account_name']) {
            $body .= "Account: {$deal['account_name']}\n";
        }
        
        $body .= "\nThresholds for $stageName stage:\n";
        if ($deal['thresholds']) {
            $body .= "- Warning: {$deal['thresholds']['warning']} days\n";
            $body .= "- Critical: {$deal['thresholds']['critical']} days\n";
            $body .= "- Overdue: {$deal['thresholds']['overdue']} days\n";
        }
        
        $body .= "\nPlease review this deal and take appropriate action.\n\n";
        $body .= "Best regards,\n";
        $body .= "Pipeline Monitoring System";
        
        return $body;
    }
    
    /**
     * Get stage display name
     */
    private function getStageDisplayName($stage)
    {
        $displayNames = [
            'sourcing' => 'Sourcing',
            'screening' => 'Screening',
            'analysis_outreach' => 'Analysis & Outreach',
            'due_diligence' => 'Due Diligence',
            'valuation_structuring' => 'Valuation & Structuring',
            'loi_negotiation' => 'LOI / Negotiation',
            'financing' => 'Financing',
            'closing' => 'Closing',
            'closed_owned_90_day' => 'Closed/Owned – 90-Day Plan',
            'closed_owned_stable' => 'Closed/Owned – Stable Operations',
            'unavailable' => 'Unavailable'
        ];
        
        return $displayNames[$stage] ?? ucfirst(str_replace('_', ' ', $stage));
    }
    
    /**
     * Send email notification
     */
    private function sendEmail($recipient, $subject, $body)
    {
        if (empty($recipient['email'])) {
            return false;
        }
        
        // Use SuiteCRM's email system
        if (class_exists('SugarPHPMailer')) {
            try {
                $mailer = new SugarPHPMailer();
                $mailer->setMailerForSystem();
                $mailer->AddAddress($recipient['email'], $recipient['name']);
                $mailer->Subject = $subject;
                $mailer->Body = $body;
                $mailer->IsHTML(false);
                
                return $mailer->Send();
            } catch (Exception $e) {
                error_log("Failed to send email alert: " . $e->getMessage());
                return false;
            }
        }
        
        // Fallback to PHP mail
        return mail($recipient['email'], $subject, $body);
    }
    
    /**
     * Send in-app notification
     */
    private function sendInAppNotification($recipient, $deal, $alertLevel)
    {
        if ($recipient['type'] !== 'user') {
            return false;
        }
        
        $notificationId = create_guid();
        $levelText = ucfirst($alertLevel);
        $message = "$levelText: Deal '{$deal['name']}' has been in stage for {$deal['days_in_stage']} days";
        
        $query = "INSERT INTO notifications 
                  (id, assigned_user_id, name, description, type, severity, 
                   parent_type, parent_id, is_read, date_entered, created_by) 
                  VALUES 
                  (?, ?, ?, ?, 'time_alert', ?, 'Opportunities', ?, 0, NOW(), ?)";
        
        if ($this->tableExists('notifications')) {
            $this->db->pQuery($query, [
                $notificationId,
                $recipient['id'],
                "Deal Time Alert",
                $message,
                $alertLevel,
                $deal['id'],
                'system'
            ]);
            return true;
        }
        
        return false;
    }
    
    /**
     * Log notification
     */
    private function logNotification($dealId, $recipient, $alertLevel, $emailSent, $inAppSent)
    {
        $logId = create_guid();
        
        $query = "INSERT INTO notification_log 
                  (id, deal_id, recipient_id, recipient_type, alert_level, 
                   email_sent, in_app_sent, sent_date, date_entered) 
                  VALUES 
                  (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        if ($this->tableExists('notification_log')) {
            $this->db->pQuery($query, [
                $logId,
                $dealId,
                $recipient['id'],
                $recipient['type'],
                $alertLevel,
                $emailSent ? 1 : 0,
                $inAppSent ? 1 : 0
            ]);
        }
    }
    
    /**
     * Check if table exists
     */
    private function tableExists($tableName)
    {
        $query = "SHOW TABLES LIKE '$tableName'";
        $result = $this->db->query($query);
        return (bool)$this->db->fetchByAssoc($result);
    }
}