<?php
/**
 * Checklist Progress Tracking Service
 * 
 * Implements comprehensive progress tracking algorithms for Due Diligence checklists:
 * - Percentage completion calculations
 * - Weighted progress based on task importance
 * - Time-based progress metrics
 * - Completion forecasting algorithms
 * - Milestone tracking and alerts
 * - Visual progress indicators for UI
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/MVC/Controller/SugarController.php');

class ChecklistProgressService
{
    private $db;
    private $alertService;
    
    // Progress calculation weights by task priority
    private $priorityWeights = [
        'high' => 3.0,
        'medium' => 2.0,
        'low' => 1.0,
        'critical' => 4.0
    ];
    
    // Milestone thresholds for alerts
    private $milestoneThresholds = [
        25, 50, 75, 90, 100
    ];
    
    // Task completion categories for weighted scoring
    private $taskCategories = [
        'financial_review' => 0.30,    // 30% weight
        'legal_review' => 0.25,        // 25% weight
        'operational_review' => 0.20,  // 20% weight
        'market_analysis' => 0.15,     // 15% weight
        'technical_review' => 0.10     // 10% weight
    ];
    
    public function __construct()
    {
        global $db;
        $this->db = $db;
        $this->alertService = new ChecklistAlertService();
    }
    
    /**
     * Calculate comprehensive progress metrics for a checklist
     */
    public function calculateChecklistProgress($checklistId, $options = [])
    {
        $checklist = $this->getChecklistWithTasks($checklistId);
        if (!$checklist) {
            return null;
        }
        
        $tasks = $checklist['tasks'];
        $totalTasks = count($tasks);
        
        if ($totalTasks === 0) {
            return $this->createEmptyProgressMetrics($checklistId);
        }
        
        // Basic completion calculations
        $completedTasks = array_filter($tasks, function($task) {
            return $task['status'] === 'completed';
        });
        $completedCount = count($completedTasks);
        
        $inProgressTasks = array_filter($tasks, function($task) {
            return $task['status'] === 'in_progress';
        });
        $inProgressCount = count($inProgressTasks);
        
        // Calculate basic percentage
        $basicPercentage = round(($completedCount / $totalTasks) * 100, 2);
        
        // Calculate weighted progress
        $weightedProgress = $this->calculateWeightedProgress($tasks);
        
        // Calculate time-based progress
        $timeProgress = $this->calculateTimeBasedProgress($checklist, $tasks);
        
        // Generate completion forecast
        $forecast = $this->generateCompletionForecast($checklist, $tasks);
        
        // Check milestone achievements
        $milestones = $this->checkMilestoneAchievements($checklistId, $weightedProgress['percentage']);
        
        // Calculate task distribution
        $taskDistribution = $this->calculateTaskDistribution($tasks);
        
        // Risk assessment
        $riskAssessment = $this->calculateProgressRisk($checklist, $tasks, $timeProgress);
        
        return [
            'checklist_id' => $checklistId,
            'basic_progress' => [
                'completed_tasks' => $completedCount,
                'in_progress_tasks' => $inProgressCount,
                'pending_tasks' => $totalTasks - $completedCount - $inProgressCount,
                'total_tasks' => $totalTasks,
                'percentage' => $basicPercentage
            ],
            'weighted_progress' => $weightedProgress,
            'time_progress' => $timeProgress,
            'forecast' => $forecast,
            'milestones' => $milestones,
            'task_distribution' => $taskDistribution,
            'risk_assessment' => $riskAssessment,
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Calculate weighted progress based on task importance
     */
    private function calculateWeightedProgress($tasks)
    {
        $totalWeight = 0;
        $completedWeight = 0;
        $inProgressWeight = 0;
        
        foreach ($tasks as $task) {
            $priority = $task['priority'] ?? 'medium';
            $category = $task['category'] ?? 'operational_review';
            
            // Base weight from priority
            $baseWeight = $this->priorityWeights[$priority] ?? 2.0;
            
            // Category multiplier
            $categoryWeight = $this->taskCategories[$category] ?? 0.2;
            
            // Final task weight
            $taskWeight = $baseWeight * $categoryWeight;
            $totalWeight += $taskWeight;
            
            if ($task['status'] === 'completed') {
                $completedWeight += $taskWeight;
            } elseif ($task['status'] === 'in_progress') {
                // In-progress tasks contribute partial weight
                $progressPercent = $task['completion_percentage'] ?? 50;
                $inProgressWeight += $taskWeight * ($progressPercent / 100);
            }
        }
        
        $totalCompletedWeight = $completedWeight + $inProgressWeight;
        $weightedPercentage = $totalWeight > 0 ? round(($totalCompletedWeight / $totalWeight) * 100, 2) : 0;
        
        return [
            'percentage' => $weightedPercentage,
            'completed_weight' => $completedWeight,
            'in_progress_weight' => $inProgressWeight,
            'total_weight' => $totalWeight,
            'weight_distribution' => $this->getWeightDistribution($tasks)
        ];
    }
    
    /**
     * Calculate time-based progress metrics
     */
    private function calculateTimeBasedProgress($checklist, $tasks)
    {
        $startDate = new DateTime($checklist['created_date']);
        $targetDate = isset($checklist['target_completion_date']) 
            ? new DateTime($checklist['target_completion_date']) 
            : null;
        $currentDate = new DateTime();
        
        // Calculate elapsed time
        $elapsedDays = $currentDate->diff($startDate)->days;
        $elapsedHours = round($elapsedDays * 24 + $currentDate->diff($startDate)->h, 1);
        
        // Calculate timeline progress
        $timelineProgress = null;
        $daysRemaining = null;
        $isOnTrack = null;
        
        if ($targetDate) {
            $totalDays = $targetDate->diff($startDate)->days;
            $daysRemaining = $targetDate->diff($currentDate)->days;
            
            if ($totalDays > 0) {
                $timeElapsedPercent = round(($elapsedDays / $totalDays) * 100, 2);
                $completedPercent = $this->getBasicCompletionPercentage($tasks);
                
                // Determine if on track (completed % should be >= elapsed time %)
                $isOnTrack = $completedPercent >= ($timeElapsedPercent * 0.9); // 10% buffer
                
                $timelineProgress = [
                    'elapsed_percent' => $timeElapsedPercent,
                    'completed_percent' => $completedPercent,
                    'variance' => $completedPercent - $timeElapsedPercent,
                    'is_on_track' => $isOnTrack
                ];
            }
        }
        
        // Calculate average completion time per task
        $completedTasks = array_filter($tasks, function($task) {
            return $task['status'] === 'completed' && isset($task['completed_date']);
        });
        
        $avgCompletionTime = $this->calculateAverageTaskCompletionTime($completedTasks);
        
        return [
            'elapsed_days' => $elapsedDays,
            'elapsed_hours' => $elapsedHours,
            'days_remaining' => $daysRemaining,
            'timeline_progress' => $timelineProgress,
            'is_on_track' => $isOnTrack,
            'average_task_completion_time' => $avgCompletionTime,
            'velocity' => $this->calculateVelocity($tasks, $elapsedDays)
        ];
    }
    
    /**
     * Generate completion forecast based on current progress
     */
    private function generateCompletionForecast($checklist, $tasks)
    {
        $completedTasks = array_filter($tasks, function($task) {
            return $task['status'] === 'completed';
        });
        
        $totalTasks = count($tasks);
        $completedCount = count($completedTasks);
        $remainingTasks = $totalTasks - $completedCount;
        
        if ($remainingTasks === 0) {
            return [
                'forecast_completion_date' => date('Y-m-d'),
                'confidence_level' => 100,
                'method' => 'already_completed'
            ];
        }
        
        // Calculate velocity (tasks completed per day)
        $startDate = new DateTime($checklist['created_date']);
        $currentDate = new DateTime();
        $elapsedDays = max($currentDate->diff($startDate)->days, 1);
        
        $velocity = $completedCount / $elapsedDays;
        
        if ($velocity <= 0) {
            return [
                'forecast_completion_date' => null,
                'confidence_level' => 0,
                'method' => 'insufficient_data',
                'warning' => 'No completed tasks to calculate velocity'
            ];
        }
        
        // Linear forecast
        $daysToComplete = round($remainingTasks / $velocity);
        $forecastDate = clone $currentDate;
        $forecastDate->add(new DateInterval("P{$daysToComplete}D"));
        
        // Calculate confidence based on consistency
        $confidence = $this->calculateForecastConfidence($tasks, $velocity);
        
        // Alternative forecasting methods
        $alternativeForecasts = [
            'optimistic' => $this->calculateOptimisticForecast($remainingTasks, $velocity, $currentDate),
            'pessimistic' => $this->calculatePessimisticForecast($remainingTasks, $velocity, $currentDate),
            'monte_carlo' => $this->calculateMonteCarloForecast($tasks, $checklist)
        ];
        
        return [
            'forecast_completion_date' => $forecastDate->format('Y-m-d'),
            'days_to_completion' => $daysToComplete,
            'confidence_level' => $confidence,
            'method' => 'linear_velocity',
            'velocity' => round($velocity, 3),
            'alternative_forecasts' => $alternativeForecasts
        ];
    }
    
    /**
     * Check milestone achievements and trigger alerts
     */
    private function checkMilestoneAchievements($checklistId, $currentPercentage)
    {
        $achievedMilestones = [];
        $nextMilestone = null;
        
        // Get previously achieved milestones
        $previousMilestones = $this->getPreviousMilestones($checklistId);
        
        foreach ($this->milestoneThresholds as $threshold) {
            if ($currentPercentage >= $threshold) {
                $achievedMilestones[] = $threshold;
                
                // Check if this is a new milestone achievement
                if (!in_array($threshold, $previousMilestones)) {
                    $this->triggerMilestoneAlert($checklistId, $threshold, $currentPercentage);
                    $this->recordMilestoneAchievement($checklistId, $threshold);
                }
            } else {
                if ($nextMilestone === null) {
                    $nextMilestone = [
                        'threshold' => $threshold,
                        'progress_needed' => $threshold - $currentPercentage,
                        'percentage_to_go' => round(($threshold - $currentPercentage), 2)
                    ];
                }
            }
        }
        
        return [
            'achieved' => $achievedMilestones,
            'next_milestone' => $nextMilestone,
            'latest_achievement' => !empty($achievedMilestones) ? max($achievedMilestones) : 0,
            'milestones_remaining' => count($this->milestoneThresholds) - count($achievedMilestones)
        ];
    }
    
    /**
     * Calculate task distribution by status and category
     */
    private function calculateTaskDistribution($tasks)
    {
        $statusDistribution = [];
        $categoryDistribution = [];
        $priorityDistribution = [];
        
        foreach ($tasks as $task) {
            // Status distribution
            $status = $task['status'] ?? 'pending';
            $statusDistribution[$status] = ($statusDistribution[$status] ?? 0) + 1;
            
            // Category distribution
            $category = $task['category'] ?? 'operational_review';
            $categoryDistribution[$category] = ($categoryDistribution[$category] ?? 0) + 1;
            
            // Priority distribution
            $priority = $task['priority'] ?? 'medium';
            $priorityDistribution[$priority] = ($priorityDistribution[$priority] ?? 0) + 1;
        }
        
        return [
            'by_status' => $statusDistribution,
            'by_category' => $categoryDistribution,
            'by_priority' => $priorityDistribution,
            'total_tasks' => count($tasks)
        ];
    }
    
    /**
     * Calculate progress risk assessment
     */
    private function calculateProgressRisk($checklist, $tasks, $timeProgress)
    {
        $risks = [];
        $overallRiskLevel = 'low';
        
        // Time-based risks
        if (isset($timeProgress['is_on_track']) && !$timeProgress['is_on_track']) {
            $risks[] = [
                'type' => 'timeline_delay',
                'level' => 'high',
                'description' => 'Project is behind schedule based on elapsed time vs completion rate',
                'impact' => 'high'
            ];
            $overallRiskLevel = 'high';
        }
        
        // Task distribution risks
        $highPriorityTasks = array_filter($tasks, function($task) {
            return ($task['priority'] ?? 'medium') === 'high' && $task['status'] !== 'completed';
        });
        
        if (count($highPriorityTasks) > 0) {
            $risks[] = [
                'type' => 'high_priority_pending',
                'level' => 'medium',
                'description' => count($highPriorityTasks) . ' high-priority tasks are still pending',
                'impact' => 'medium'
            ];
            
            if ($overallRiskLevel === 'low') {
                $overallRiskLevel = 'medium';
            }
        }
        
        // Velocity risks
        if (isset($timeProgress['velocity']) && $timeProgress['velocity'] < 0.5) {
            $risks[] = [
                'type' => 'low_velocity',
                'level' => 'medium',
                'description' => 'Task completion velocity is below optimal threshold',
                'impact' => 'medium'
            ];
            
            if ($overallRiskLevel === 'low') {
                $overallRiskLevel = 'medium';
            }
        }
        
        // Blocked tasks risk
        $blockedTasks = array_filter($tasks, function($task) {
            return ($task['status'] ?? 'pending') === 'blocked';
        });
        
        if (count($blockedTasks) > 0) {
            $risks[] = [
                'type' => 'blocked_tasks',
                'level' => 'high',
                'description' => count($blockedTasks) . ' tasks are blocked and need attention',
                'impact' => 'high'
            ];
            $overallRiskLevel = 'high';
        }
        
        return [
            'overall_level' => $overallRiskLevel,
            'risk_score' => $this->calculateRiskScore($risks),
            'risks' => $risks,
            'mitigation_suggestions' => $this->getMitigationSuggestions($risks)
        ];
    }
    
    /**
     * Get checklist with all tasks
     */
    private function getChecklistWithTasks($checklistId)
    {
        // This would typically query the database
        // For now, return mock data structure
        $query = "SELECT * FROM checklist_instances WHERE id = ? AND deleted = 0";
        
        if (!$this->tableExists('checklist_instances')) {
            return $this->getMockChecklistData($checklistId);
        }
        
        $result = $this->db->pQuery($query, [$checklistId]);
        $checklist = $this->db->fetchByAssoc($result);
        
        if (!$checklist) {
            return null;
        }
        
        // Get tasks for this checklist
        $tasksQuery = "SELECT * FROM checklist_tasks WHERE checklist_id = ? AND deleted = 0 ORDER BY order_index";
        $tasksResult = $this->db->pQuery($tasksQuery, [$checklistId]);
        
        $tasks = [];
        while ($task = $this->db->fetchByAssoc($tasksResult)) {
            $tasks[] = $task;
        }
        
        $checklist['tasks'] = $tasks;
        return $checklist;
    }
    
    /**
     * Create empty progress metrics for empty checklist
     */
    private function createEmptyProgressMetrics($checklistId)
    {
        return [
            'checklist_id' => $checklistId,
            'basic_progress' => [
                'completed_tasks' => 0,
                'in_progress_tasks' => 0,
                'pending_tasks' => 0,
                'total_tasks' => 0,
                'percentage' => 0
            ],
            'weighted_progress' => [
                'percentage' => 0,
                'completed_weight' => 0,
                'in_progress_weight' => 0,
                'total_weight' => 0
            ],
            'time_progress' => [
                'elapsed_days' => 0,
                'is_on_track' => null
            ],
            'forecast' => null,
            'milestones' => ['achieved' => [], 'next_milestone' => null],
            'risk_assessment' => ['overall_level' => 'low', 'risks' => []],
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Helper methods for calculations
     */
    private function getBasicCompletionPercentage($tasks)
    {
        $total = count($tasks);
        if ($total === 0) return 0;
        
        $completed = count(array_filter($tasks, function($task) {
            return $task['status'] === 'completed';
        }));
        
        return round(($completed / $total) * 100, 2);
    }
    
    private function calculateAverageTaskCompletionTime($completedTasks)
    {
        if (empty($completedTasks)) {
            return null;
        }
        
        $totalTime = 0;
        $count = 0;
        
        foreach ($completedTasks as $task) {
            if (isset($task['started_date']) && isset($task['completed_date'])) {
                $start = new DateTime($task['started_date']);
                $end = new DateTime($task['completed_date']);
                $totalTime += $end->diff($start)->days;
                $count++;
            }
        }
        
        return $count > 0 ? round($totalTime / $count, 1) : null;
    }
    
    private function calculateVelocity($tasks, $elapsedDays)
    {
        if ($elapsedDays <= 0) return 0;
        
        $completedCount = count(array_filter($tasks, function($task) {
            return $task['status'] === 'completed';
        }));
        
        return round($completedCount / $elapsedDays, 3);
    }
    
    private function getWeightDistribution($tasks)
    {
        $distribution = [];
        
        foreach ($this->taskCategories as $category => $weight) {
            $categoryTasks = array_filter($tasks, function($task) use ($category) {
                return ($task['category'] ?? 'operational_review') === $category;
            });
            
            $completed = count(array_filter($categoryTasks, function($task) {
                return $task['status'] === 'completed';
            }));
            
            $distribution[$category] = [
                'total_tasks' => count($categoryTasks),
                'completed_tasks' => $completed,
                'completion_rate' => count($categoryTasks) > 0 ? round(($completed / count($categoryTasks)) * 100, 1) : 0,
                'weight' => $weight
            ];
        }
        
        return $distribution;
    }
    
    private function calculateForecastConfidence($tasks, $velocity)
    {
        // Base confidence on velocity consistency and data points
        $completedTasks = array_filter($tasks, function($task) {
            return $task['status'] === 'completed';
        });
        
        $dataPoints = count($completedTasks);
        
        // Lower confidence with fewer data points
        if ($dataPoints < 3) return 30;
        if ($dataPoints < 5) return 50;
        if ($dataPoints < 10) return 70;
        
        return 85; // High confidence with substantial data
    }
    
    private function calculateOptimisticForecast($remainingTasks, $velocity, $currentDate)
    {
        $optimisticVelocity = $velocity * 1.5; // 50% improvement
        $days = round($remainingTasks / $optimisticVelocity);
        $date = clone $currentDate;
        $date->add(new DateInterval("P{$days}D"));
        
        return [
            'date' => $date->format('Y-m-d'),
            'days' => $days,
            'assumption' => '50% velocity improvement'
        ];
    }
    
    private function calculatePessimisticForecast($remainingTasks, $velocity, $currentDate)
    {
        $pessimisticVelocity = $velocity * 0.7; // 30% slower
        $days = round($remainingTasks / $pessimisticVelocity);
        $date = clone $currentDate;
        $date->add(new DateInterval("P{$days}D"));
        
        return [
            'date' => $date->format('Y-m-d'),
            'days' => $days,
            'assumption' => '30% velocity reduction'
        ];
    }
    
    private function calculateMonteCarloForecast($tasks, $checklist)
    {
        // Simplified Monte Carlo simulation
        // In production, this would run thousands of iterations
        return [
            'date' => null,
            'confidence_intervals' => [
                '50%' => 'Not implemented',
                '80%' => 'Not implemented',
                '95%' => 'Not implemented'
            ],
            'note' => 'Monte Carlo simulation requires historical completion data'
        ];
    }
    
    private function getPreviousMilestones($checklistId)
    {
        $query = "SELECT milestone_threshold FROM checklist_milestones 
                  WHERE checklist_id = ? AND deleted = 0";
        
        if (!$this->tableExists('checklist_milestones')) {
            return [];
        }
        
        $result = $this->db->pQuery($query, [$checklistId]);
        $milestones = [];
        
        while ($row = $this->db->fetchByAssoc($result)) {
            $milestones[] = intval($row['milestone_threshold']);
        }
        
        return $milestones;
    }
    
    private function triggerMilestoneAlert($checklistId, $threshold, $currentPercentage)
    {
        $this->alertService->sendMilestoneAlert($checklistId, $threshold, $currentPercentage);
    }
    
    private function recordMilestoneAchievement($checklistId, $threshold)
    {
        $id = create_guid();
        $query = "INSERT INTO checklist_milestones 
                  (id, checklist_id, milestone_threshold, achieved_date, date_entered) 
                  VALUES (?, ?, ?, NOW(), NOW())";
        
        if ($this->tableExists('checklist_milestones')) {
            $this->db->pQuery($query, [$id, $checklistId, $threshold]);
        }
    }
    
    private function calculateRiskScore($risks)
    {
        $score = 0;
        $weights = ['low' => 1, 'medium' => 3, 'high' => 5];
        
        foreach ($risks as $risk) {
            $score += $weights[$risk['level']] ?? 1;
        }
        
        return min($score, 10); // Cap at 10
    }
    
    private function getMitigationSuggestions($risks)
    {
        $suggestions = [];
        
        foreach ($risks as $risk) {
            switch ($risk['type']) {
                case 'timeline_delay':
                    $suggestions[] = 'Consider reallocating resources or adjusting timeline expectations';
                    break;
                case 'high_priority_pending':
                    $suggestions[] = 'Focus immediate attention on high-priority tasks';
                    break;
                case 'low_velocity':
                    $suggestions[] = 'Review task complexity and resource allocation';
                    break;
                case 'blocked_tasks':
                    $suggestions[] = 'Address blocking issues immediately to prevent cascade delays';
                    break;
            }
        }
        
        return array_unique($suggestions);
    }
    
    private function tableExists($tableName)
    {
        $query = "SHOW TABLES LIKE '$tableName'";
        $result = $this->db->query($query);
        return (bool)$this->db->fetchByAssoc($result);
    }
    
    /**
     * Mock data for development/testing
     */
    private function getMockChecklistData($checklistId)
    {
        return [
            'id' => $checklistId,
            'name' => 'Financial Due Diligence Checklist',
            'created_date' => '2025-01-15 09:00:00',
            'target_completion_date' => '2025-02-15 17:00:00',
            'tasks' => [
                [
                    'id' => 'task_1',
                    'name' => 'Review Financial Statements',
                    'status' => 'completed',
                    'priority' => 'high',
                    'category' => 'financial_review',
                    'started_date' => '2025-01-15 10:00:00',
                    'completed_date' => '2025-01-18 16:00:00',
                    'completion_percentage' => 100
                ],
                [
                    'id' => 'task_2',
                    'name' => 'Analyze Cash Flow',
                    'status' => 'in_progress',
                    'priority' => 'high',
                    'category' => 'financial_review',
                    'started_date' => '2025-01-19 09:00:00',
                    'completion_percentage' => 75
                ],
                [
                    'id' => 'task_3',
                    'name' => 'Legal Structure Review',
                    'status' => 'pending',
                    'priority' => 'medium',
                    'category' => 'legal_review',
                    'completion_percentage' => 0
                ],
                [
                    'id' => 'task_4',
                    'name' => 'Market Analysis',
                    'status' => 'pending',
                    'priority' => 'low',
                    'category' => 'market_analysis',
                    'completion_percentage' => 0
                ]
            ]
        ];
    }
}

/**
 * Checklist Alert Service for milestone notifications
 */
class ChecklistAlertService
{
    private $db;
    
    public function __construct()
    {
        global $db;
        $this->db = $db;
    }
    
    /**
     * Send milestone achievement alert
     */
    public function sendMilestoneAlert($checklistId, $threshold, $currentPercentage)
    {
        // Get checklist details
        $checklist = $this->getChecklistInfo($checklistId);
        if (!$checklist) return false;
        
        // Create alert message
        $message = "Checklist '{$checklist['name']}' has reached {$threshold}% completion milestone!";
        $details = "Current progress: {$currentPercentage}%";
        
        // Send notification to assigned users
        $this->sendNotification($checklist['assigned_user_id'], $message, $details, 'milestone_achievement');
        
        // Log the alert
        $this->logMilestoneAlert($checklistId, $threshold, $currentPercentage);
        
        return true;
    }
    
    private function getChecklistInfo($checklistId)
    {
        // Mock implementation - would query actual checklist data
        return [
            'name' => 'Due Diligence Checklist',
            'assigned_user_id' => '1'
        ];
    }
    
    private function sendNotification($userId, $message, $details, $type)
    {
        // Implementation would integrate with SuiteCRM notification system
        error_log("Milestone Alert: $message - $details");
    }
    
    private function logMilestoneAlert($checklistId, $threshold, $percentage)
    {
        $id = create_guid();
        $query = "INSERT INTO checklist_alert_log 
                  (id, checklist_id, alert_type, threshold, percentage, created_date) 
                  VALUES (?, ?, 'milestone', ?, ?, NOW())";
        
        if ($this->tableExists('checklist_alert_log')) {
            $this->db->pQuery($query, [$id, $checklistId, $threshold, $percentage]);
        }
    }
    
    private function tableExists($tableName)
    {
        $query = "SHOW TABLES LIKE '$tableName'";
        $result = $this->db->query($query);
        return (bool)$this->db->fetchByAssoc($result);
    }
}