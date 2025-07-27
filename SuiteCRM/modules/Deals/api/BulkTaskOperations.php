<?php
/**
 * Bulk Task Operations for Task Generation Engine
 * 
 * Handles efficient bulk creation, updating, and deletion of tasks
 * with optimized database operations and batch processing.
 * 
 * @category  API
 * @package   Deals
 * @author    MakeDealCRM
 * @license   GPL-3.0+
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

class BulkTaskOperations
{
    private $logger;
    private $db;
    private $batchSize = 100;
    
    public function __construct()
    {
        global $log, $db;
        $this->logger = $log;
        $this->db = $db;
    }
    
    /**
     * Create multiple tasks in bulk
     * 
     * @param array $tasks Array of task data
     * @param string $dealId Deal ID
     * @param string $templateId Template ID
     * @param array $options Creation options
     * @return array Creation result
     */
    public function createTasks($tasks, $dealId, $templateId, $options = array())
    {
        try {
            $this->logger->info("BulkTaskOperations: Creating " . count($tasks) . " tasks in bulk");
            
            $generationId = create_guid();
            $createdTasks = array();
            $warnings = array();
            
            // Begin transaction
            $this->db->query("BEGIN");
            
            try {
                // Process tasks in batches
                $batches = array_chunk($tasks, $this->batchSize);
                
                foreach ($batches as $batchIndex => $batch) {
                    $this->logger->debug("BulkTaskOperations: Processing batch " . ($batchIndex + 1) . " of " . count($batches));
                    
                    $batchResult = $this->createTaskBatch($batch, $dealId, $templateId, $generationId, $options);
                    $createdTasks = array_merge($createdTasks, $batchResult['tasks']);
                    $warnings = array_merge($warnings, $batchResult['warnings']);
                }
                
                // Create generation record
                $this->createGenerationRecord($generationId, $dealId, $templateId, count($createdTasks));
                
                // Create task dependencies
                $this->createTaskDependencies($createdTasks);
                
                // Create reminders if specified
                $this->createTaskReminders($createdTasks);
                
                // Commit transaction
                $this->db->query("COMMIT");
                
                $this->logger->info("BulkTaskOperations: Successfully created " . count($createdTasks) . " tasks");
                
                return array(
                    'generation_id' => $generationId,
                    'tasks' => $createdTasks,
                    'warnings' => $warnings,
                    'batch_count' => count($batches)
                );
                
            } catch (Exception $e) {
                $this->db->query("ROLLBACK");
                throw $e;
            }
            
        } catch (Exception $e) {
            $this->logger->error("BulkTaskOperations: Error creating tasks - " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create a batch of tasks
     * 
     * @param array $batch Batch of tasks
     * @param string $dealId Deal ID
     * @param string $templateId Template ID
     * @param string $generationId Generation ID
     * @param array $options Creation options
     * @return array Batch creation result
     */
    private function createTaskBatch($batch, $dealId, $templateId, $generationId, $options)
    {
        global $current_user;
        
        $createdTasks = array();
        $warnings = array();
        $now = date('Y-m-d H:i:s');
        $userId = $current_user->id ?? 'system';
        
        // Prepare base SQL components
        $baseFields = array(
            'id', 'name', 'description', 'status', 'priority', 'deal_id',
            'template_id', 'generation_id', 'due_date', 'start_date',
            'assigned_user_id', 'created_by', 'date_entered', 'date_modified', 'deleted'
        );
        
        $fieldList = implode(', ', $baseFields);
        $placeholders = '(' . implode(',', array_fill(0, count($baseFields), '?')) . ')';
        
        // Build bulk insert arrays
        $insertValues = array();
        $insertParams = array();
        
        foreach ($batch as $task) {
            try {
                $taskId = $task['id'] ?? create_guid();
                $assignedUserId = $this->determineAssignedUser($task, $dealId, $options);
                
                $values = array(
                    $taskId,
                    $task['name'] ?? 'Untitled Task',
                    $task['description'] ?? '',
                    $task['status'] ?? 'pending',
                    $task['priority'] ?? 'medium',
                    $dealId,
                    $templateId,
                    $generationId,
                    $task['due_date'] ?? null,
                    $task['start_date'] ?? null,
                    $assignedUserId,
                    $userId,
                    $now,
                    $now,
                    0
                );
                
                $insertValues[] = $placeholders;
                $insertParams = array_merge($insertParams, $values);
                
                // Prepare task for result
                $createdTask = array_merge($task, array(
                    'id' => $taskId,
                    'deal_id' => $dealId,
                    'template_id' => $templateId,
                    'generation_id' => $generationId,
                    'assigned_user_id' => $assignedUserId,
                    'created_by' => $userId,
                    'date_entered' => $now,
                    'date_modified' => $now
                ));
                
                $createdTasks[] = $createdTask;
                
            } catch (Exception $e) {
                $warnings[] = array(
                    'task_name' => $task['name'] ?? 'Unknown',
                    'error' => $e->getMessage()
                );
                $this->logger->warn("BulkTaskOperations: Warning creating task - " . $e->getMessage());
            }
        }
        
        // Execute bulk insert if we have tasks to insert
        if (!empty($insertValues)) {
            $valuesList = implode(', ', $insertValues);
            $insertSQL = "INSERT INTO tasks ($fieldList) VALUES $valuesList";
            
            $result = $this->db->pQuery($insertSQL, $insertParams);
            
            if (!$result) {
                throw new Exception("Failed to execute bulk insert");
            }
        }
        
        return array(
            'tasks' => $createdTasks,
            'warnings' => $warnings
        );
    }
    
    /**
     * Create generation record for tracking
     * 
     * @param string $generationId Generation ID
     * @param string $dealId Deal ID
     * @param string $templateId Template ID
     * @param int $taskCount Number of tasks created
     */
    private function createGenerationRecord($generationId, $dealId, $templateId, $taskCount)
    {
        global $current_user;
        
        $userId = $current_user->id ?? 'system';
        $now = date('Y-m-d H:i:s');
        
        $query = "INSERT INTO task_generations 
                  (id, deal_id, template_id, task_count, status, 
                   created_by, date_created, deleted)
                  VALUES (?, ?, ?, ?, 'completed', ?, ?, 0)";
        
        $this->db->pQuery($query, array(
            $generationId,
            $dealId,
            $templateId,
            $taskCount,
            $userId,
            $now
        ));
    }
    
    /**
     * Create task dependencies in bulk
     * 
     * @param array $tasks Array of created tasks
     */
    private function createTaskDependencies($tasks)
    {
        $dependencies = array();
        
        // Collect all dependencies
        foreach ($tasks as $task) {
            if (isset($task['resolved_dependencies'])) {
                foreach ($task['resolved_dependencies'] as $dependency) {
                    $dependencies[] = array(
                        'id' => create_guid(),
                        'task_id' => $task['id'],
                        'dependent_task_id' => $dependency['task_id'] ?? null,
                        'dependency_type' => $dependency['type'],
                        'relationship' => $dependency['relationship'] ?? 'finish_to_start',
                        'lag_days' => $dependency['lag'] ?? 0,
                        'created_by' => $task['created_by'],
                        'date_created' => $task['date_entered']
                    );
                }
            }
        }
        
        if (empty($dependencies)) {
            return;
        }
        
        // Bulk insert dependencies
        $batches = array_chunk($dependencies, $this->batchSize);
        
        foreach ($batches as $batch) {
            $this->insertDependencyBatch($batch);
        }
    }
    
    /**
     * Insert batch of dependencies
     * 
     * @param array $batch Batch of dependencies
     */
    private function insertDependencyBatch($batch)
    {
        $fields = array(
            'id', 'task_id', 'dependent_task_id', 'dependency_type',
            'relationship', 'lag_days', 'created_by', 'date_created', 'deleted'
        );
        
        $fieldList = implode(', ', $fields);
        $placeholders = '(' . implode(',', array_fill(0, count($fields), '?')) . ')';
        
        $insertValues = array();
        $insertParams = array();
        
        foreach ($batch as $dependency) {
            $values = array(
                $dependency['id'],
                $dependency['task_id'],
                $dependency['dependent_task_id'],
                $dependency['dependency_type'],
                $dependency['relationship'],
                $dependency['lag_days'],
                $dependency['created_by'],
                $dependency['date_created'],
                0
            );
            
            $insertValues[] = $placeholders;
            $insertParams = array_merge($insertParams, $values);
        }
        
        $valuesList = implode(', ', $insertValues);
        $insertSQL = "INSERT INTO task_dependencies ($fieldList) VALUES $valuesList";
        
        $this->db->pQuery($insertSQL, $insertParams);
    }
    
    /**
     * Create task reminders in bulk
     * 
     * @param array $tasks Array of created tasks
     */
    private function createTaskReminders($tasks)
    {
        $reminders = array();
        
        // Collect all reminders
        foreach ($tasks as $task) {
            if (isset($task['reminders'])) {
                foreach ($task['reminders'] as $reminder) {
                    $reminders[] = array(
                        'id' => create_guid(),
                        'task_id' => $task['id'],
                        'reminder_date' => $reminder['date'],
                        'reminder_type' => $reminder['type'],
                        'message' => $reminder['message'],
                        'recipients' => json_encode($reminder['recipients']),
                        'status' => 'pending',
                        'created_by' => $task['created_by'],
                        'date_created' => $task['date_entered']
                    );
                }
            }
        }
        
        if (empty($reminders)) {
            return;
        }
        
        // Bulk insert reminders
        $batches = array_chunk($reminders, $this->batchSize);
        
        foreach ($batches as $batch) {
            $this->insertReminderBatch($batch);
        }
    }
    
    /**
     * Insert batch of reminders
     * 
     * @param array $batch Batch of reminders
     */
    private function insertReminderBatch($batch)
    {
        $fields = array(
            'id', 'task_id', 'reminder_date', 'reminder_type', 'message',
            'recipients', 'status', 'created_by', 'date_created', 'deleted'
        );
        
        $fieldList = implode(', ', $fields);
        $placeholders = '(' . implode(',', array_fill(0, count($fields), '?')) . ')';
        
        $insertValues = array();
        $insertParams = array();
        
        foreach ($batch as $reminder) {
            $values = array(
                $reminder['id'],
                $reminder['task_id'],
                $reminder['reminder_date'],
                $reminder['reminder_type'],
                $reminder['message'],
                $reminder['recipients'],
                $reminder['status'],
                $reminder['created_by'],
                $reminder['date_created'],
                0
            );
            
            $insertValues[] = $placeholders;
            $insertParams = array_merge($insertParams, $values);
        }
        
        $valuesList = implode(', ', $insertValues);
        $insertSQL = "INSERT INTO task_reminders ($fieldList) VALUES $valuesList";
        
        $this->db->pQuery($insertSQL, $insertParams);
    }
    
    /**
     * Update multiple tasks in bulk
     * 
     * @param array $updates Array of task updates
     * @param array $options Update options
     * @return array Update result
     */
    public function updateTasks($updates, $options = array())
    {
        try {
            $this->logger->info("BulkTaskOperations: Updating " . count($updates) . " tasks in bulk");
            
            $updatedCount = 0;
            $warnings = array();
            
            $this->db->query("BEGIN");
            
            try {
                $batches = array_chunk($updates, $this->batchSize);
                
                foreach ($batches as $batch) {
                    foreach ($batch as $update) {
                        if ($this->updateSingleTask($update)) {
                            $updatedCount++;
                        } else {
                            $warnings[] = array(
                                'task_id' => $update['id'],
                                'error' => 'Update failed'
                            );
                        }
                    }
                }
                
                $this->db->query("COMMIT");
                
                return array(
                    'updated_count' => $updatedCount,
                    'warnings' => $warnings
                );
                
            } catch (Exception $e) {
                $this->db->query("ROLLBACK");
                throw $e;
            }
            
        } catch (Exception $e) {
            $this->logger->error("BulkTaskOperations: Error updating tasks - " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Archive tasks by generation
     * 
     * @param string $generationId Generation ID
     * @return int Number of archived tasks
     */
    public function archiveTasksByGeneration($generationId)
    {
        $query = "UPDATE tasks SET deleted = 1, date_modified = NOW() 
                  WHERE generation_id = ? AND deleted = 0";
        
        $result = $this->db->pQuery($query, array($generationId));
        
        return $this->db->getAffectedRowCount($result);
    }
    
    /**
     * Delete tasks by generation
     * 
     * @param string $generationId Generation ID
     * @return int Number of deleted tasks
     */
    public function deleteTasksByGeneration($generationId)
    {
        // Delete associated records first
        $this->db->pQuery("DELETE FROM task_dependencies WHERE task_id IN (SELECT id FROM tasks WHERE generation_id = ?)", array($generationId));
        $this->db->pQuery("DELETE FROM task_reminders WHERE task_id IN (SELECT id FROM tasks WHERE generation_id = ?)", array($generationId));
        
        // Delete tasks
        $query = "DELETE FROM tasks WHERE generation_id = ?";
        $result = $this->db->pQuery($query, array($generationId));
        
        return $this->db->getAffectedRowCount($result);
    }
    
    /**
     * Determine assigned user for task
     * 
     * @param array $task Task data
     * @param string $dealId Deal ID
     * @param array $options Options
     * @return string User ID
     */
    private function determineAssignedUser($task, $dealId, $options)
    {
        global $current_user;
        
        // Priority order: task assignment > options > deal owner > current user
        if (isset($task['assigned_user_id'])) {
            return $task['assigned_user_id'];
        }
        
        if (isset($options['default_assigned_user'])) {
            return $options['default_assigned_user'];
        }
        
        // Get deal owner
        $query = "SELECT assigned_user_id FROM opportunities WHERE id = ? AND deleted = 0";
        $result = $this->db->pQuery($query, array($dealId));
        $row = $this->db->fetchByAssoc($result);
        
        if ($row && $row['assigned_user_id']) {
            return $row['assigned_user_id'];
        }
        
        return $current_user->id ?? 'system';
    }
    
    /**
     * Update single task
     * 
     * @param array $update Update data
     * @return bool Success
     */
    private function updateSingleTask($update)
    {
        if (!isset($update['id'])) {
            return false;
        }
        
        $taskId = $update['id'];
        unset($update['id']);
        
        $fields = array();
        $params = array();
        
        foreach ($update as $field => $value) {
            $fields[] = "$field = ?";
            $params[] = $value;
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $fields[] = "date_modified = NOW()";
        $params[] = $taskId;
        
        $fieldList = implode(', ', $fields);
        $query = "UPDATE tasks SET $fieldList WHERE id = ?";
        
        $result = $this->db->pQuery($query, $params);
        
        return $result && $this->db->getAffectedRowCount($result) > 0;
    }
}