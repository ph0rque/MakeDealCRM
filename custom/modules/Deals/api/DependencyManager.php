<?php
/**
 * Dependency Manager for Task Generation Engine
 * 
 * Handles task dependencies, dependency resolution, validation,
 * and ensures proper task ordering and prerequisites.
 * 
 * @category  API
 * @package   Deals
 * @author    MakeDealCRM
 * @license   GPL-3.0+
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

class DependencyManager
{
    private $logger;
    private $db;
    
    public function __construct()
    {
        global $log, $db;
        $this->logger = $log;
        $this->db = $db;
    }
    
    /**
     * Resolve dependencies for all tasks
     * 
     * @param array $tasks Array of tasks with potential dependencies
     * @param string $dealId Deal ID for context
     * @return array Tasks with resolved dependencies
     */
    public function resolveDependencies($tasks, $dealId)
    {
        try {
            $this->logger->info("DependencyManager: Resolving dependencies for " . count($tasks) . " tasks");
            
            // Build task lookup map
            $taskMap = $this->buildTaskMap($tasks);
            
            // Resolve internal template dependencies
            $resolvedTasks = $this->resolveInternalDependencies($tasks, $taskMap);
            
            // Resolve external dependencies (existing tasks)
            $resolvedTasks = $this->resolveExternalDependencies($resolvedTasks, $dealId);
            
            // Validate dependency chain
            $this->validateDependencyChain($resolvedTasks);
            
            // Sort tasks by dependency order
            $sortedTasks = $this->sortTasksByDependencies($resolvedTasks);
            
            // Adjust schedules based on dependencies
            $finalTasks = $this->adjustSchedulesForDependencies($sortedTasks);
            
            $this->logger->info("DependencyManager: Successfully resolved dependencies");
            
            return $finalTasks;
            
        } catch (Exception $e) {
            $this->logger->error("DependencyManager: Error resolving dependencies - " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Build task lookup map for quick reference
     * 
     * @param array $tasks Array of tasks
     * @return array Task lookup map
     */
    private function buildTaskMap($tasks)
    {
        $taskMap = array();
        
        foreach ($tasks as $task) {
            $templateTaskId = $task['template_task_id'] ?? $task['id'];
            $taskMap[$templateTaskId] = $task;
        }
        
        return $taskMap;
    }
    
    /**
     * Resolve internal dependencies between tasks in the same template
     * 
     * @param array $tasks Array of tasks
     * @param array $taskMap Task lookup map
     * @return array Tasks with resolved internal dependencies
     */
    private function resolveInternalDependencies($tasks, $taskMap)
    {
        $resolvedTasks = array();
        
        foreach ($tasks as $task) {
            $resolvedTask = $task;
            $resolvedDependencies = array();
            
            if (isset($task['dependencies'])) {
                foreach ($task['dependencies'] as $dependency) {
                    $dependencyType = $dependency['type'] ?? 'task';
                    
                    if ($dependencyType === 'task') {
                        $dependentTaskId = $dependency['task_id'];
                        
                        if (isset($taskMap[$dependentTaskId])) {
                            $resolvedDependencies[] = array(
                                'type' => 'internal_task',
                                'task_id' => $taskMap[$dependentTaskId]['id'],
                                'template_task_id' => $dependentTaskId,
                                'name' => $taskMap[$dependentTaskId]['name'],
                                'relationship' => $dependency['relationship'] ?? 'finish_to_start',
                                'lag' => $dependency['lag'] ?? 0
                            );
                        } else {
                            $this->logger->warn("DependencyManager: Referenced task '{$dependentTaskId}' not found in template");
                        }
                    }
                }
            }
            
            $resolvedTask['resolved_dependencies'] = $resolvedDependencies;
            $resolvedTasks[] = $resolvedTask;
        }
        
        return $resolvedTasks;
    }
    
    /**
     * Resolve external dependencies on existing tasks
     * 
     * @param array $tasks Array of tasks
     * @param string $dealId Deal ID
     * @return array Tasks with resolved external dependencies
     */
    private function resolveExternalDependencies($tasks, $dealId)
    {
        $resolvedTasks = array();
        
        foreach ($tasks as $task) {
            $resolvedTask = $task;
            $externalDependencies = array();
            
            if (isset($task['dependencies'])) {
                foreach ($task['dependencies'] as $dependency) {
                    $dependencyType = $dependency['type'] ?? 'task';
                    
                    if ($dependencyType === 'external_task') {
                        $externalTask = $this->findExternalTask($dependency, $dealId);
                        
                        if ($externalTask) {
                            $externalDependencies[] = array(
                                'type' => 'external_task',
                                'task_id' => $externalTask['id'],
                                'name' => $externalTask['name'],
                                'status' => $externalTask['status'],
                                'due_date' => $externalTask['due_date'],
                                'relationship' => $dependency['relationship'] ?? 'finish_to_start',
                                'lag' => $dependency['lag'] ?? 0
                            );
                        } else {
                            $this->logger->warn("DependencyManager: External task not found: " . json_encode($dependency));
                        }
                    } elseif ($dependencyType === 'milestone') {
                        $milestone = $this->findMilestone($dependency, $dealId);
                        
                        if ($milestone) {
                            $externalDependencies[] = array(
                                'type' => 'milestone',
                                'milestone_id' => $milestone['id'],
                                'name' => $milestone['name'],
                                'date' => $milestone['date'],
                                'status' => $milestone['status'],
                                'relationship' => $dependency['relationship'] ?? 'finish_to_start',
                                'lag' => $dependency['lag'] ?? 0
                            );
                        }
                    }
                }
            }
            
            // Merge with existing resolved dependencies
            $resolvedTask['resolved_dependencies'] = array_merge(
                $resolvedTask['resolved_dependencies'] ?? array(),
                $externalDependencies
            );
            
            $resolvedTasks[] = $resolvedTask;
        }
        
        return $resolvedTasks;
    }
    
    /**
     * Find external task based on dependency criteria
     * 
     * @param array $dependency Dependency configuration
     * @param string $dealId Deal ID
     * @return array|null External task data
     */
    private function findExternalTask($dependency, $dealId)
    {
        $criteria = $dependency['criteria'] ?? array();
        
        // Build query based on criteria
        $whereClause = "t.deal_id = ? AND t.deleted = 0";
        $params = array($dealId);
        
        if (isset($criteria['name'])) {
            $whereClause .= " AND t.name = ?";
            $params[] = $criteria['name'];
        }
        
        if (isset($criteria['name_pattern'])) {
            $whereClause .= " AND t.name LIKE ?";
            $params[] = '%' . $criteria['name_pattern'] . '%';
        }
        
        if (isset($criteria['category'])) {
            $whereClause .= " AND t.category = ?";
            $params[] = $criteria['category'];
        }
        
        if (isset($criteria['status'])) {
            if (is_array($criteria['status'])) {
                $placeholders = str_repeat('?,', count($criteria['status']) - 1) . '?';
                $whereClause .= " AND t.status IN ($placeholders)";
                $params = array_merge($params, $criteria['status']);
            } else {
                $whereClause .= " AND t.status = ?";
                $params[] = $criteria['status'];
            }
        }
        
        $query = "SELECT t.id, t.name, t.status, t.due_date, t.completed_date
                  FROM tasks t
                  WHERE $whereClause
                  ORDER BY t.due_date ASC
                  LIMIT 1";
        
        $result = $this->db->pQuery($query, $params);
        $row = $this->db->fetchByAssoc($result);
        
        return $row ?: null;
    }
    
    /**
     * Find milestone based on dependency criteria
     * 
     * @param array $dependency Dependency configuration
     * @param string $dealId Deal ID
     * @return array|null Milestone data
     */
    private function findMilestone($dependency, $dealId)
    {
        $criteria = $dependency['criteria'] ?? array();
        
        // For now, return deal stage milestones
        // This could be extended to support custom milestones
        $milestones = array(
            'due_diligence_complete' => array(
                'id' => 'milestone_dd_complete',
                'name' => 'Due Diligence Complete',
                'date' => $this->getMilestoneDate($dealId, 'due_diligence'),
                'status' => 'pending'
            ),
            'valuation_complete' => array(
                'id' => 'milestone_valuation_complete', 
                'name' => 'Valuation Complete',
                'date' => $this->getMilestoneDate($dealId, 'valuation_structuring'),
                'status' => 'pending'
            )
        );
        
        $milestoneKey = $criteria['milestone'] ?? null;
        
        return isset($milestones[$milestoneKey]) ? $milestones[$milestoneKey] : null;
    }
    
    /**
     * Get milestone date from deal stage history
     * 
     * @param string $dealId Deal ID
     * @param string $stage Stage key
     * @return string|null Milestone date
     */
    private function getMilestoneDate($dealId, $stage)
    {
        $query = "SELECT h.date_changed
                  FROM pipeline_stage_history h
                  WHERE h.deal_id = ? AND h.old_stage = ? 
                  ORDER BY h.date_changed DESC
                  LIMIT 1";
        
        $result = $this->db->pQuery($query, array($dealId, $stage));
        $row = $this->db->fetchByAssoc($result);
        
        return $row ? $row['date_changed'] : null;
    }
    
    /**
     * Validate dependency chain for circular dependencies
     * 
     * @param array $tasks Array of tasks
     * @throws Exception If circular dependency found
     */
    private function validateDependencyChain($tasks)
    {
        $taskMap = array();
        $dependencyGraph = array();
        
        // Build dependency graph
        foreach ($tasks as $task) {
            $taskId = $task['id'];
            $taskMap[$taskId] = $task;
            $dependencyGraph[$taskId] = array();
            
            if (isset($task['resolved_dependencies'])) {
                foreach ($task['resolved_dependencies'] as $dependency) {
                    if ($dependency['type'] === 'internal_task') {
                        $dependencyGraph[$taskId][] = $dependency['task_id'];
                    }
                }
            }
        }
        
        // Check for circular dependencies using DFS
        $visited = array();
        $recursionStack = array();
        
        foreach (array_keys($dependencyGraph) as $taskId) {
            if (!isset($visited[$taskId])) {
                if ($this->hasCycle($taskId, $dependencyGraph, $visited, $recursionStack)) {
                    throw new Exception("Circular dependency detected in task chain");
                }
            }
        }
    }
    
    /**
     * Check for cycles in dependency graph using DFS
     * 
     * @param string $taskId Current task ID
     * @param array $graph Dependency graph
     * @param array $visited Visited nodes
     * @param array $recursionStack Recursion stack
     * @return bool Whether cycle exists
     */
    private function hasCycle($taskId, $graph, &$visited, &$recursionStack)
    {
        $visited[$taskId] = true;
        $recursionStack[$taskId] = true;
        
        if (isset($graph[$taskId])) {
            foreach ($graph[$taskId] as $dependentTaskId) {
                if (!isset($visited[$dependentTaskId])) {
                    if ($this->hasCycle($dependentTaskId, $graph, $visited, $recursionStack)) {
                        return true;
                    }
                } elseif (isset($recursionStack[$dependentTaskId])) {
                    return true;
                }
            }
        }
        
        unset($recursionStack[$taskId]);
        return false;
    }
    
    /**
     * Sort tasks by dependency order using topological sort
     * 
     * @param array $tasks Array of tasks
     * @return array Sorted tasks
     */
    private function sortTasksByDependencies($tasks)
    {
        $taskMap = array();
        $dependencyGraph = array();
        $inDegree = array();
        
        // Initialize structures
        foreach ($tasks as $task) {
            $taskId = $task['id'];
            $taskMap[$taskId] = $task;
            $dependencyGraph[$taskId] = array();
            $inDegree[$taskId] = 0;
        }
        
        // Build dependency graph and calculate in-degrees
        foreach ($tasks as $task) {
            $taskId = $task['id'];
            
            if (isset($task['resolved_dependencies'])) {
                foreach ($task['resolved_dependencies'] as $dependency) {
                    if ($dependency['type'] === 'internal_task') {
                        $dependentTaskId = $dependency['task_id'];
                        
                        if (isset($dependencyGraph[$dependentTaskId])) {
                            $dependencyGraph[$dependentTaskId][] = $taskId;
                            $inDegree[$taskId]++;
                        }
                    }
                }
            }
        }
        
        // Topological sort using Kahn's algorithm
        $queue = array();
        $sorted = array();
        
        // Find all nodes with no incoming edges
        foreach ($inDegree as $taskId => $degree) {
            if ($degree === 0) {
                $queue[] = $taskId;
            }
        }
        
        while (!empty($queue)) {
            $currentTaskId = array_shift($queue);
            $sorted[] = $taskMap[$currentTaskId];
            
            // Process all dependent tasks
            foreach ($dependencyGraph[$currentTaskId] as $dependentTaskId) {
                $inDegree[$dependentTaskId]--;
                
                if ($inDegree[$dependentTaskId] === 0) {
                    $queue[] = $dependentTaskId;
                }
            }
        }
        
        // Add any remaining tasks (shouldn't happen if validation passed)
        foreach ($tasks as $task) {
            $found = false;
            foreach ($sorted as $sortedTask) {
                if ($sortedTask['id'] === $task['id']) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $sorted[] = $task;
            }
        }
        
        return $sorted;
    }
    
    /**
     * Adjust task schedules based on dependencies
     * 
     * @param array $tasks Sorted tasks
     * @return array Tasks with adjusted schedules
     */
    private function adjustSchedulesForDependencies($tasks)
    {
        $adjustedTasks = array();
        $taskScheduleMap = array();
        
        foreach ($tasks as $task) {
            $adjustedTask = $task;
            $originalDueDate = new DateTime($task['due_date']);
            $adjustedDueDate = clone $originalDueDate;
            $earliestStartDate = null;
            
            if (isset($task['resolved_dependencies'])) {
                foreach ($task['resolved_dependencies'] as $dependency) {
                    $dependencyDate = $this->getDependencyDate($dependency, $taskScheduleMap);
                    
                    if ($dependencyDate) {
                        $requiredStartDate = $this->calculateRequiredStartDate(
                            $dependencyDate,
                            $dependency['relationship'],
                            $dependency['lag']
                        );
                        
                        if ($earliestStartDate === null || $requiredStartDate > $earliestStartDate) {
                            $earliestStartDate = $requiredStartDate;
                        }
                    }
                }
            }
            
            // Adjust due date if necessary
            if ($earliestStartDate && $earliestStartDate > $adjustedDueDate) {
                $adjustedDueDate = clone $earliestStartDate;
                $adjustedTask['due_date'] = $adjustedDueDate->format('Y-m-d H:i:s');
                $adjustedTask['schedule_adjusted'] = true;
                $adjustedTask['original_due_date'] = $originalDueDate->format('Y-m-d H:i:s');
                $adjustedTask['adjustment_reason'] = 'Dependency constraint';
            }
            
            // Store in schedule map for future dependency calculations
            $taskScheduleMap[$task['id']] = $adjustedDueDate;
            
            $adjustedTasks[] = $adjustedTask;
        }
        
        return $adjustedTasks;
    }
    
    /**
     * Get dependency date for schedule calculation
     * 
     * @param array $dependency Dependency information
     * @param array $taskScheduleMap Task schedule map
     * @return DateTime|null Dependency date
     */
    private function getDependencyDate($dependency, $taskScheduleMap)
    {
        switch ($dependency['type']) {
            case 'internal_task':
                $taskId = $dependency['task_id'];
                return isset($taskScheduleMap[$taskId]) ? $taskScheduleMap[$taskId] : null;
                
            case 'external_task':
                $dueDate = $dependency['due_date'];
                return $dueDate ? new DateTime($dueDate) : null;
                
            case 'milestone':
                $milestoneDate = $dependency['date'];
                return $milestoneDate ? new DateTime($milestoneDate) : null;
                
            default:
                return null;
        }
    }
    
    /**
     * Calculate required start date based on dependency relationship
     * 
     * @param DateTime $dependencyDate Dependency completion date
     * @param string $relationship Dependency relationship type
     * @param int $lag Lag time in days
     * @return DateTime Required start date
     */
    private function calculateRequiredStartDate($dependencyDate, $relationship, $lag)
    {
        $requiredDate = clone $dependencyDate;
        
        switch ($relationship) {
            case 'finish_to_start':
                // Task can start after dependency finishes
                $requiredDate->modify("+{$lag} days");
                break;
                
            case 'start_to_start':
                // Task can start when dependency starts
                $requiredDate->modify("+{$lag} days");
                break;
                
            case 'finish_to_finish':
                // Task must finish when dependency finishes
                $requiredDate->modify("+{$lag} days");
                break;
                
            case 'start_to_finish':
                // Task must finish after dependency starts
                $requiredDate->modify("+{$lag} days");
                break;
                
            default:
                $requiredDate->modify("+{$lag} days");
        }
        
        return $requiredDate;
    }
}