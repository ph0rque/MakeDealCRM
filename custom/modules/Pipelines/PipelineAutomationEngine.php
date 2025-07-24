<?php
/**
 * Pipeline Automation Engine for MakeDeal CRM
 * Handles automated deal progression, stage validation, and workflow triggers
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once('include/SugarBean.php');

class PipelineAutomationEngine
{
    protected $db;
    protected $stages;
    protected $wipLimits;
    protected $automationRules;
    protected $stageThresholds;
    
    public function __construct()
    {
        global $db;
        $this->db = $db;
        $this->loadPipelineConfiguration();
    }
    
    /**
     * Load pipeline configuration from database or config files
     */
    protected function loadPipelineConfiguration()
    {
        // Default M&A pipeline stages with WIP limits and thresholds
        $this->stages = [
            'sourcing' => [
                'order' => 1,
                'wip_limit' => 50,
                'warning_days' => 30,
                'critical_days' => 60,
                'required_fields' => ['deal_source', 'company_name', 'industry'],
                'auto_tasks' => ['initial_research', 'market_analysis']
            ],
            'screening' => [
                'order' => 2,
                'wip_limit' => 25,
                'warning_days' => 14,
                'critical_days' => 30,
                'required_fields' => ['annual_revenue', 'employee_count', 'geographic_focus'],
                'auto_tasks' => ['financial_screening', 'strategic_fit_analysis']
            ],
            'analysis_outreach' => [
                'order' => 3,
                'wip_limit' => 15,
                'warning_days' => 21,
                'critical_days' => 45,
                'required_fields' => ['primary_contact', 'decision_maker', 'key_stakeholders'],
                'auto_tasks' => ['stakeholder_mapping', 'initial_outreach']
            ],
            'term_sheet' => [
                'order' => 4,
                'wip_limit' => 10,
                'warning_days' => 30,
                'critical_days' => 60,
                'required_fields' => ['valuation_range', 'deal_structure', 'key_terms'],
                'auto_tasks' => ['term_sheet_preparation', 'negotiation_strategy']
            ],
            'due_diligence' => [
                'order' => 5,
                'wip_limit' => 8,
                'warning_days' => 45,
                'critical_days' => 90,
                'required_fields' => ['dd_checklist', 'external_advisors', 'data_room_access'],
                'auto_tasks' => ['dd_checklist_creation', 'advisor_coordination']
            ],
            'final_negotiation' => [
                'order' => 6,
                'wip_limit' => 5,
                'warning_days' => 30,
                'critical_days' => 60,
                'required_fields' => ['final_terms', 'closing_conditions', 'timeline'],
                'auto_tasks' => ['legal_documentation', 'regulatory_approval']
            ],
            'closing' => [
                'order' => 7,
                'wip_limit' => 5,
                'warning_days' => 21,
                'critical_days' => 45,
                'required_fields' => ['closing_date', 'funding_confirmed', 'all_approvals'],
                'auto_tasks' => ['closing_checklist', 'funds_transfer']
            ],
            'closed_won' => [
                'order' => 8,
                'wip_limit' => null,
                'warning_days' => null,
                'critical_days' => null,
                'required_fields' => [],
                'auto_tasks' => ['integration_planning', 'portfolio_onboarding']
            ],
            'closed_lost' => [
                'order' => 9,
                'wip_limit' => null,
                'warning_days' => null,
                'critical_days' => null,
                'required_fields' => ['loss_reason', 'lessons_learned'],
                'auto_tasks' => ['post_mortem_analysis']
            ],
            'unavailable' => [
                'order' => 10,
                'wip_limit' => null,
                'warning_days' => 180,
                'critical_days' => 365,
                'required_fields' => ['unavailable_reason', 'follow_up_date'],
                'auto_tasks' => ['follow_up_reminder']
            ]
        ];
        
        // Load automation rules
        $this->loadAutomationRules();
    }
    
    /**
     * Load automation rules from database or configuration
     */
    protected function loadAutomationRules()
    {
        $this->automationRules = [
            'lead_to_deal_conversion' => [
                'conditions' => [
                    'lead_score >= 80',
                    'contact_type IN ("decision_maker", "financial_approver")',
                    'annual_revenue >= 10000000'
                ],
                'action' => 'convert_to_deal',
                'target_stage' => 'sourcing'
            ],
            'auto_progression_screening' => [
                'conditions' => [
                    'all_required_fields_complete',
                    'financial_metrics_verified',
                    'initial_interest_confirmed'
                ],
                'action' => 'move_to_stage',
                'target_stage' => 'analysis_outreach',
                'delay_days' => 0
            ],
            'stale_deal_escalation' => [
                'conditions' => [
                    'days_in_stage > critical_threshold',
                    'no_activity_last_30_days'
                ],
                'action' => 'escalate_to_manager',
                'escalation_level' => 'high'
            ]
        ];
    }
    
    /**
     * Validate if a deal can transition to a new stage
     */
    public function validateStageTransition($deal, $fromStage, $toStage, $userId = null)
    {
        global $current_user;
        $user = $userId ? BeanFactory::getBean('Users', $userId) : $current_user;
        
        $validation = [
            'allowed' => true,
            'warnings' => [],
            'errors' => [],
            'override_required' => false
        ];
        
        // Check if target stage exists
        if (!isset($this->stages[$toStage])) {
            $validation['allowed'] = false;
            $validation['errors'][] = "Invalid target stage: {$toStage}";
            return $validation;
        }
        
        // Check if transition is sequential (can skip only one stage)
        $fromOrder = $this->stages[$fromStage]['order'] ?? 0;
        $toOrder = $this->stages[$toStage]['order'];
        
        if ($toOrder > $fromOrder + 2) {
            $validation['allowed'] = false;
            $validation['errors'][] = "Cannot skip more than one stage in progression";
            return $validation;
        }
        
        // Check required fields
        $missingFields = $this->checkRequiredFields($deal, $toStage);
        if (!empty($missingFields)) {
            $validation['allowed'] = false;
            $validation['errors'][] = "Missing required fields: " . implode(', ', $missingFields);
            return $validation;
        }
        
        // Check WIP limits
        $wipStatus = $this->checkWIPLimit($toStage, $user->id);
        if (!$wipStatus['allowed']) {
            $validation['warnings'][] = $wipStatus['message'];
            $validation['override_required'] = true;
            
            // Hard limit enforcement for critical stages
            if (in_array($toStage, ['due_diligence', 'final_negotiation', 'closing'])) {
                $validation['allowed'] = false;
                $validation['errors'][] = $wipStatus['message'];
            }
        }
        
        // Check deal health score
        $healthScore = $deal->health_score ?? 0;
        if ($healthScore < 50 && in_array($toStage, ['term_sheet', 'due_diligence'])) {
            $validation['warnings'][] = "Deal health score is low ({$healthScore}%). Consider improving before progression.";
        }
        
        return $validation;
    }
    
    /**
     * Execute stage transition with full automation
     */
    public function executeStageTransition($deal, $toStage, $userId = null, $reason = '', $override = false)
    {
        global $current_user;
        $user = $userId ? BeanFactory::getBean('Users', $userId) : $current_user;
        $fromStage = $deal->stage;
        
        // Validate transition
        $validation = $this->validateStageTransition($deal, $fromStage, $toStage, $userId);
        if (!$validation['allowed'] && !$override) {
            throw new Exception('Stage transition not allowed: ' . implode(', ', $validation['errors']));
        }
        
        try {
            // Begin transaction
            $this->db->query('START TRANSACTION');
            
            // Execute pre-transition actions
            $this->executeExitActions($deal, $fromStage);
            
            // Update deal stage and timing
            $deal->stage = $toStage;
            $deal->stage_entered_date = date('Y-m-d H:i:s');
            $deal->days_in_stage = 0;
            $deal->is_stale = 0;
            $deal->stale_reason = '';
            
            // Mark if this was an override
            if ($override) {
                $deal->wip_override = 1;
                $deal->wip_override_reason = $reason;
            }
            
            // Save deal
            $deal->save();
            
            // Log transition
            $this->logStageTransition($deal, $fromStage, $toStage, $user, $reason, $override);
            
            // Execute post-transition actions
            $this->executeEntryActions($deal, $toStage);
            
            // Update WIP tracking
            $this->updateWIPTracking($fromStage, $toStage, $user->id);
            
            // Process automation rules
            $this->processStageAutomation($deal, $toStage);
            
            // Commit transaction
            $this->db->query('COMMIT');
            
            $GLOBALS['log']->info("Deal {$deal->id} transitioned from {$fromStage} to {$toStage} by user {$user->id}");
            
            return [
                'success' => true,
                'message' => "Deal successfully moved to {$toStage}",
                'warnings' => $validation['warnings'] ?? []
            ];
            
        } catch (Exception $e) {
            // Rollback transaction
            $this->db->query('ROLLBACK');
            
            $GLOBALS['log']->error("Stage transition failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Check required fields for stage progression
     */
    protected function checkRequiredFields($deal, $stage)
    {
        $requiredFields = $this->stages[$stage]['required_fields'] ?? [];
        $missingFields = [];
        
        foreach ($requiredFields as $field) {
            if (empty($deal->$field)) {
                $missingFields[] = $field;
            }
        }
        
        return $missingFields;
    }
    
    /**
     * Check WIP (Work In Progress) limits
     */
    protected function checkWIPLimit($stage, $userId)
    {
        $wipLimit = $this->stages[$stage]['wip_limit'];
        
        if ($wipLimit === null) {
            return ['allowed' => true];
        }
        
        // Count current deals in stage for user
        $query = "SELECT COUNT(*) as count FROM mdeal_deals 
                  WHERE stage = ? AND assigned_user_id = ? AND deleted = 0";
        $result = $this->db->pQuery($query, [$stage, $userId]);
        $row = $this->db->fetchByAssoc($result);
        $currentCount = $row['count'] ?? 0;
        
        if ($currentCount >= $wipLimit) {
            return [
                'allowed' => false,
                'message' => "WIP limit exceeded for {$stage} stage ({$currentCount}/{$wipLimit}). Override required.",
                'current_count' => $currentCount,
                'limit' => $wipLimit
            ];
        }
        
        return [
            'allowed' => true,
            'current_count' => $currentCount,
            'limit' => $wipLimit
        ];
    }
    
    /**
     * Execute actions when exiting a stage
     */
    protected function executeExitActions($deal, $fromStage)
    {
        // Calculate stage duration
        if (!empty($deal->stage_entered_date)) {
            $stageStart = new DateTime($deal->stage_entered_date);
            $now = new DateTime();
            $daysInStage = $now->diff($stageStart)->days;
            
            // Store historical metrics
            $this->storeStageMetrics($deal, $fromStage, $daysInStage);
        }
        
        // Mark any stage-specific tasks as completed
        $this->completeAutoTasks($deal, $fromStage);
    }
    
    /**
     * Execute actions when entering a stage
     */
    protected function executeEntryActions($deal, $toStage)
    {
        // Create stage-specific tasks
        $this->createAutoTasks($deal, $toStage);
        
        // Send notifications
        $this->sendStageNotifications($deal, $toStage);
        
        // Update deal health score
        $this->updateDealHealthScore($deal);
    }
    
    /**
     * Create automatic tasks for a stage
     */
    protected function createAutoTasks($deal, $stage)
    {
        $autoTasks = $this->stages[$stage]['auto_tasks'] ?? [];
        
        foreach ($autoTasks as $taskType) {
            $taskConfig = $this->getTaskConfiguration($taskType, $stage);
            
            $task = BeanFactory::newBean('Tasks');
            $task->name = $taskConfig['name'];
            $task->description = $taskConfig['description'];
            $task->parent_type = 'mdeal_Deals';
            $task->parent_id = $deal->id;
            $task->assigned_user_id = $deal->assigned_user_id;
            $task->priority = $taskConfig['priority'];
            $task->status = 'Not Started';
            
            // Set due date based on stage timeline
            $dueDate = new DateTime();
            $dueDate->add(new DateInterval('P' . $taskConfig['due_days'] . 'D'));
            $task->date_due = $dueDate->format('Y-m-d');
            
            $task->save();
        }
    }
    
    /**
     * Get task configuration for auto-generated tasks
     */
    protected function getTaskConfiguration($taskType, $stage)
    {
        $taskConfigs = [
            'initial_research' => [
                'name' => 'Initial Company Research',
                'description' => 'Conduct preliminary research on target company including financials, market position, and key personnel.',
                'priority' => 'High',
                'due_days' => 3
            ],
            'market_analysis' => [
                'name' => 'Market Analysis',
                'description' => 'Analyze target market, competitive landscape, and growth potential.',
                'priority' => 'Medium',
                'due_days' => 5
            ],
            'financial_screening' => [
                'name' => 'Financial Screening',
                'description' => 'Review financial statements and assess financial health of target company.',
                'priority' => 'High',
                'due_days' => 7
            ],
            'strategic_fit_analysis' => [
                'name' => 'Strategic Fit Analysis',
                'description' => 'Evaluate strategic fit with our investment thesis and portfolio.',
                'priority' => 'High',
                'due_days' => 5
            ],
            'stakeholder_mapping' => [
                'name' => 'Stakeholder Mapping',
                'description' => 'Identify and map key stakeholders including decision makers and influencers.',
                'priority' => 'Medium',
                'due_days' => 3
            ],
            'initial_outreach' => [
                'name' => 'Initial Outreach',
                'description' => 'Make initial contact with target company leadership.',
                'priority' => 'High',
                'due_days' => 2
            ],
            'term_sheet_preparation' => [
                'name' => 'Term Sheet Preparation',
                'description' => 'Prepare detailed term sheet with valuation and deal structure.',
                'priority' => 'High',
                'due_days' => 10
            ],
            'negotiation_strategy' => [
                'name' => 'Negotiation Strategy',
                'description' => 'Develop negotiation strategy and identify key leverage points.',
                'priority' => 'Medium',
                'due_days' => 5
            ],
            'dd_checklist_creation' => [
                'name' => 'Due Diligence Checklist',
                'description' => 'Create comprehensive due diligence checklist and assign work streams.',
                'priority' => 'Critical',
                'due_days' => 3
            ],
            'advisor_coordination' => [
                'name' => 'External Advisor Coordination',
                'description' => 'Coordinate with external advisors (legal, accounting, technical) for due diligence.',
                'priority' => 'High',
                'due_days' => 5
            ],
            'legal_documentation' => [
                'name' => 'Legal Documentation',
                'description' => 'Prepare and review all legal documentation for closing.',
                'priority' => 'Critical',
                'due_days' => 15
            ],
            'regulatory_approval' => [
                'name' => 'Regulatory Approvals',
                'description' => 'Obtain all necessary regulatory approvals for transaction.',
                'priority' => 'Critical',
                'due_days' => 30
            ],
            'closing_checklist' => [
                'name' => 'Closing Checklist',
                'description' => 'Complete all closing requirements and coordinate final steps.',
                'priority' => 'Critical',
                'due_days' => 7
            ],
            'funds_transfer' => [
                'name' => 'Funds Transfer',
                'description' => 'Coordinate funds transfer and closing mechanics.',
                'priority' => 'Critical',
                'due_days' => 1
            ],
            'integration_planning' => [
                'name' => 'Integration Planning',
                'description' => 'Begin post-acquisition integration planning and execution.',
                'priority' => 'High',
                'due_days' => 14
            ],
            'portfolio_onboarding' => [
                'name' => 'Portfolio Company Onboarding',
                'description' => 'Onboard new portfolio company into management processes.',
                'priority' => 'Medium',
                'due_days' => 30
            ],
            'post_mortem_analysis' => [
                'name' => 'Deal Post-Mortem Analysis',
                'description' => 'Conduct post-mortem analysis to capture lessons learned.',
                'priority' => 'Medium',
                'due_days' => 10
            ],
            'follow_up_reminder' => [
                'name' => 'Follow-up Reminder',
                'description' => 'Set reminder to follow up when circumstances change.',
                'priority' => 'Low',
                'due_days' => 30
            ]
        ];
        
        return $taskConfigs[$taskType] ?? [
            'name' => ucwords(str_replace('_', ' ', $taskType)),
            'description' => 'Automated task for ' . $stage . ' stage.',
            'priority' => 'Medium',
            'due_days' => 7
        ];
    }
    
    /**
     * Process all automation rules for a deal in a specific stage
     */
    protected function processStageAutomation($deal, $stage)
    {
        foreach ($this->automationRules as $ruleName => $rule) {
            if ($this->evaluateRuleConditions($rule, $deal, $stage)) {
                $this->executeAutomationAction($rule, $deal, $ruleName);
            }
        }
    }
    
    /**
     * Evaluate automation rule conditions
     */
    protected function evaluateRuleConditions($rule, $deal, $stage)
    {
        $conditions = $rule['conditions'] ?? [];
        
        foreach ($conditions as $condition) {
            if (!$this->evaluateCondition($condition, $deal, $stage)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Evaluate a single condition
     */
    protected function evaluateCondition($condition, $deal, $stage)
    {
        // Handle simple field checks
        if ($condition === 'all_required_fields_complete') {
            return empty($this->checkRequiredFields($deal, $stage));
        }
        
        if ($condition === 'financial_metrics_verified') {
            return !empty($deal->annual_revenue) && !empty($deal->ebitda);
        }
        
        if ($condition === 'initial_interest_confirmed') {
            return !empty($deal->interest_level) && $deal->interest_level !== 'none';
        }
        
        if (preg_match('/days_in_stage > (\d+)/', $condition, $matches)) {
            return $deal->days_in_stage > intval($matches[1]);
        }
        
        if ($condition === 'no_activity_last_30_days') {
            return $this->hasNoRecentActivity($deal, 30);
        }
        
        // Handle complex field evaluations
        if (preg_match('/(.+) >= (.+)/', $condition, $matches)) {
            $field = trim($matches[1]);
            $value = trim($matches[2]);
            return ($deal->$field ?? 0) >= floatval($value);
        }
        
        return false;
    }
    
    /**
     * Check if deal has no recent activity
     */
    protected function hasNoRecentActivity($deal, $days)
    {
        $cutoffDate = date('Y-m-d', strtotime("-{$days} days"));
        
        // Check for recent tasks, calls, meetings, notes
        $query = "SELECT COUNT(*) as count FROM (
            SELECT date_modified FROM tasks WHERE parent_type = 'mdeal_Deals' AND parent_id = ? AND date_modified > ? AND deleted = 0
            UNION ALL
            SELECT date_modified FROM calls WHERE parent_type = 'mdeal_Deals' AND parent_id = ? AND date_modified > ? AND deleted = 0
            UNION ALL
            SELECT date_modified FROM meetings WHERE parent_type = 'mdeal_Deals' AND parent_id = ? AND date_modified > ? AND deleted = 0
            UNION ALL
            SELECT date_modified FROM notes WHERE parent_type = 'mdeal_Deals' AND parent_id = ? AND date_modified > ? AND deleted = 0
        ) activities";
        
        $result = $this->db->pQuery($query, [
            $deal->id, $cutoffDate,
            $deal->id, $cutoffDate,
            $deal->id, $cutoffDate,
            $deal->id, $cutoffDate
        ]);
        
        $row = $this->db->fetchByAssoc($result);
        return ($row['count'] ?? 0) == 0;
    }
    
    /**
     * Execute automation action
     */
    protected function executeAutomationAction($rule, $deal, $ruleName)
    {
        $action = $rule['action'];
        
        switch ($action) {
            case 'move_to_stage':
                if (isset($rule['target_stage'])) {
                    $this->executeStageTransition($deal, $rule['target_stage'], null, "Automated: {$ruleName}");
                }
                break;
                
            case 'escalate_to_manager':
                $this->escalateToManager($deal, $rule['escalation_level'] ?? 'medium');
                break;
                
            case 'send_notification':
                $this->sendAutomationNotification($deal, $rule);
                break;
        }
    }
    
    /**
     * Send stage transition notifications
     */
    protected function sendStageNotifications($deal, $stage)
    {
        // Notify assigned user
        if (!empty($deal->assigned_user_id)) {
            $this->sendStageNotification($deal, $stage, $deal->assigned_user_id);
        }
        
        // Notify team members for critical stages
        if (in_array($stage, ['due_diligence', 'final_negotiation', 'closing'])) {
            $this->notifyTeamMembers($deal, $stage);
        }
    }
    
    /**
     * Log stage transition for audit trail
     */
    protected function logStageTransition($deal, $fromStage, $toStage, $user, $reason, $override = false)
    {
        $transitionId = create_guid();
        $transitionType = $override ? 'override' : 'manual';
        
        $query = "INSERT INTO mdeal_pipeline_transitions 
                  (id, deal_id, from_stage, to_stage, transition_date, transition_by, transition_type, reason, created_date)
                  VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, NOW())";
        
        $this->db->pQuery($query, [
            $transitionId,
            $deal->id,
            $fromStage,
            $toStage,
            $user->id,
            $transitionType,
            $reason
        ]);
    }
    
    /**
     * Update WIP tracking
     */
    protected function updateWIPTracking($fromStage, $toStage, $userId)
    {
        // Decrease count for from stage
        if ($fromStage) {
            $this->updateWIPCount($fromStage, $userId, -1);
        }
        
        // Increase count for to stage
        $this->updateWIPCount($toStage, $userId, 1);
    }
    
    /**
     * Update WIP count for a specific stage and user
     */
    protected function updateWIPCount($stage, $userId, $delta)
    {
        $query = "INSERT INTO mdeal_pipeline_wip_tracking (id, stage, user_id, deal_count, wip_limit, last_updated)
                  VALUES (?, ?, ?, ?, ?, NOW())
                  ON DUPLICATE KEY UPDATE 
                  deal_count = deal_count + ?, last_updated = NOW()";
        
        $wipLimit = $this->stages[$stage]['wip_limit'] ?? 0;
        
        $this->db->pQuery($query, [
            create_guid(),
            $stage,
            $userId,
            max(0, $delta),
            $wipLimit,
            $delta
        ]);
    }
    
    /**
     * Store stage metrics for analytics
     */
    protected function storeStageMetrics($deal, $stage, $daysInStage)
    {
        $metricsId = create_guid();
        
        $query = "INSERT INTO mdeal_pipeline_stage_metrics 
                  (id, deal_id, stage, days_in_stage, exit_date, deal_value, assigned_user_id, created_date)
                  VALUES (?, ?, ?, ?, NOW(), ?, ?, NOW())";
        
        $this->db->pQuery($query, [
            $metricsId,
            $deal->id,
            $stage,
            $daysInStage,
            $deal->deal_value ?? 0,
            $deal->assigned_user_id
        ]);
    }
    
    /**
     * Update deal health score based on current stage and metrics
     */
    protected function updateDealHealthScore($deal)
    {
        $score = 50; // Base score
        
        // Stage progression bonus
        $stageOrder = $this->stages[$deal->stage]['order'] ?? 1;
        $score += min(30, $stageOrder * 3);
        
        // Recent activity bonus
        if (!$this->hasNoRecentActivity($deal, 7)) {
            $score += 10;
        }
        
        // Financial metrics bonus
        if (!empty($deal->deal_value) && $deal->deal_value > 1000000) {
            $score += 10;
        }
        
        // Time in stage penalty
        $warningDays = $this->stages[$deal->stage]['warning_days'] ?? 30;
        if ($deal->days_in_stage > $warningDays) {
            $score -= 15;
        }
        
        $deal->health_score = max(0, min(100, $score));
        $deal->save();
    }
    
    /**
     * Get pipeline statistics for reporting
     */
    public function getPipelineStatistics($userId = null)
    {
        $whereClause = $userId ? "AND assigned_user_id = ?" : "";
        $params = $userId ? [$userId] : [];
        
        $query = "SELECT 
                    stage,
                    COUNT(*) as deal_count,
                    SUM(deal_value) as total_value,
                    AVG(deal_value) as avg_value,
                    AVG(days_in_stage) as avg_days_in_stage,
                    COUNT(CASE WHEN is_stale = 1 THEN 1 END) as stale_count
                  FROM mdeal_deals 
                  WHERE deleted = 0 {$whereClause}
                  GROUP BY stage
                  ORDER BY FIELD(stage, 'sourcing', 'screening', 'analysis_outreach', 'term_sheet', 'due_diligence', 'final_negotiation', 'closing', 'closed_won', 'closed_lost', 'unavailable')";
        
        $result = $this->db->pQuery($query, $params);
        $statistics = [];
        
        while ($row = $this->db->fetchByAssoc($result)) {
            $stage = $row['stage'];
            $wipLimit = $this->stages[$stage]['wip_limit'];
            
            $statistics[$stage] = [
                'deal_count' => intval($row['deal_count']),
                'total_value' => floatval($row['total_value']),
                'avg_value' => floatval($row['avg_value']),
                'avg_days_in_stage' => floatval($row['avg_days_in_stage']),
                'stale_count' => intval($row['stale_count']),
                'wip_limit' => $wipLimit,
                'wip_utilization' => $wipLimit ? round((intval($row['deal_count']) / $wipLimit) * 100, 1) : null
            ];
        }
        
        return $statistics;
    }
}