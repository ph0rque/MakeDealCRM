<?php
/**
 * Task Generation Engine for Due Diligence Checklist System
 * 
 * Automatically generates tasks from templates with variable substitution,
 * scheduling, dependency management, and bulk operations.
 * 
 * @category  API
 * @package   Deals
 * @author    MakeDealCRM
 * @license   GPL-3.0+
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once 'custom/modules/Deals/api/TemplateParser.php';
require_once 'custom/modules/Deals/api/SchedulingSystem.php';
require_once 'custom/modules/Deals/api/DependencyManager.php';
require_once 'custom/modules/Deals/api/BulkTaskOperations.php';

class TaskGenerationEngine
{
    private $templateParser;
    private $schedulingSystem;
    private $dependencyManager;
    private $bulkOperations;
    private $db;
    private $logger;
    
    public function __construct()
    {
        global $db, $log;
        
        $this->db = $db;
        $this->logger = $log;
        
        // Initialize subsystems
        $this->templateParser = new TemplateParser();
        $this->schedulingSystem = new SchedulingSystem();
        $this->dependencyManager = new DependencyManager();
        $this->bulkOperations = new BulkTaskOperations();
    }
    
    /**
     * Generate tasks from a template for specific deal
     * 
     * @param string $templateId Template ID to use
     * @param string $dealId Deal ID to generate tasks for
     * @param array $variables Additional variables for substitution
     * @param array $options Generation options
     * @return array Generation result
     */
    public function generateTasksFromTemplate($templateId, $dealId, $variables = array(), $options = array())
    {
        try {
            $this->logger->info("TaskGenerationEngine: Starting task generation for template $templateId, deal $dealId");
            
            // Load template
            $template = $this->loadTemplate($templateId);
            if (!$template) {
                throw new Exception("Template not found: $templateId");
            }
            
            // Load deal data for variable substitution
            $dealData = $this->loadDealData($dealId);
            if (!$dealData) {
                throw new Exception("Deal not found: $dealId");
            }
            
            // Merge deal data with additional variables
            $allVariables = array_merge($dealData, $variables);
            
            // Parse template and substitute variables
            $parsedTemplate = $this->templateParser->parseTemplate($template, $allVariables);
            
            // Calculate schedules for tasks
            $scheduledTasks = $this->schedulingSystem->calculateSchedules(
                $parsedTemplate['tasks'], 
                $dealData, 
                $options
            );
            
            // Resolve dependencies
            $dependencyResolvedTasks = $this->dependencyManager->resolveDependencies(
                $scheduledTasks,
                $dealId
            );
            
            // Generate tasks in bulk
            $generationResult = $this->bulkOperations->createTasks(
                $dependencyResolvedTasks,
                $dealId,
                $templateId,
                $options
            );
            
            // Log generation success
            $this->logGeneration($templateId, $dealId, $generationResult);
            
            $this->logger->info("TaskGenerationEngine: Successfully generated " . count($generationResult['tasks']) . " tasks");
            
            return array(
                'success' => true,
                'template_id' => $templateId,
                'deal_id' => $dealId,
                'tasks_generated' => count($generationResult['tasks']),
                'tasks' => $generationResult['tasks'],
                'generation_id' => $generationResult['generation_id'],
                'warnings' => $generationResult['warnings'] ?? array(),
                'generation_date' => date('Y-m-d H:i:s')
            );
            
        } catch (Exception $e) {
            $this->logger->error("TaskGenerationEngine: Error generating tasks - " . $e->getMessage());
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'template_id' => $templateId,
                'deal_id' => $dealId
            );
        }
    }
    
    /**
     * Generate tasks from multiple templates
     * 
     * @param array $templateIds Array of template IDs
     * @param string $dealId Deal ID
     * @param array $variables Variables for substitution
     * @param array $options Generation options
     * @return array Batch generation result
     */
    public function generateTasksFromMultipleTemplates($templateIds, $dealId, $variables = array(), $options = array())
    {
        $results = array();
        $totalTasksGenerated = 0;
        $errors = array();
        
        foreach ($templateIds as $templateId) {
            $result = $this->generateTasksFromTemplate($templateId, $dealId, $variables, $options);
            
            if ($result['success']) {
                $totalTasksGenerated += $result['tasks_generated'];
            } else {
                $errors[] = array(
                    'template_id' => $templateId,
                    'error' => $result['error']
                );
            }
            
            $results[] = $result;
        }
        
        return array(
            'success' => empty($errors),
            'total_templates_processed' => count($templateIds),
            'total_tasks_generated' => $totalTasksGenerated,
            'results' => $results,
            'errors' => $errors
        );
    }
    
    /**
     * Generate conditional tasks based on deal characteristics
     * 
     * @param string $dealId Deal ID
     * @param array $conditions Conditional logic rules
     * @param array $options Generation options
     * @return array Conditional generation result
     */
    public function generateConditionalTasks($dealId, $conditions, $options = array())
    {
        try {
            // Load deal data
            $dealData = $this->loadDealData($dealId);
            
            // Evaluate conditions and determine applicable templates
            $applicableTemplates = $this->evaluateConditions($conditions, $dealData);
            
            if (empty($applicableTemplates)) {
                return array(
                    'success' => true,
                    'deal_id' => $dealId,
                    'applicable_templates' => array(),
                    'tasks_generated' => 0,
                    'message' => 'No templates matched the conditional criteria'
                );
            }
            
            // Generate tasks from applicable templates
            return $this->generateTasksFromMultipleTemplates(
                $applicableTemplates,
                $dealId,
                array(),
                $options
            );
            
        } catch (Exception $e) {
            $this->logger->error("TaskGenerationEngine: Error in conditional generation - " . $e->getMessage());
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'deal_id' => $dealId
            );
        }
    }
    
    /**
     * Regenerate tasks for a deal (update existing generation)
     * 
     * @param string $dealId Deal ID
     * @param string $generationId Previous generation ID
     * @param array $options Regeneration options
     * @return array Regeneration result
     */
    public function regenerateTasks($dealId, $generationId, $options = array())
    {
        try {
            // Load previous generation
            $previousGeneration = $this->loadGeneration($generationId);
            if (!$previousGeneration) {
                throw new Exception("Generation not found: $generationId");
            }
            
            // Archive or delete previous tasks if specified
            if ($options['replace_existing'] ?? false) {
                $this->bulkOperations->archiveTasksByGeneration($generationId);
            }
            
            // Regenerate using same template and updated deal data
            return $this->generateTasksFromTemplate(
                $previousGeneration['template_id'],
                $dealId,
                $options['variables'] ?? array(),
                $options
            );
            
        } catch (Exception $e) {
            $this->logger->error("TaskGenerationEngine: Error regenerating tasks - " . $e->getMessage());
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'deal_id' => $dealId,
                'generation_id' => $generationId
            );
        }
    }
    
    /**
     * Load template data
     * 
     * @param string $templateId Template ID
     * @return array|null Template data
     */
    private function loadTemplate($templateId)
    {
        $query = "SELECT ct.*, ctv.version_data 
                  FROM checklist_templates ct
                  LEFT JOIN checklist_template_versions ctv ON ct.active_version_id = ctv.id
                  WHERE ct.id = ? AND ct.deleted = 0";
        
        $result = $this->db->pQuery($query, array($templateId));
        $row = $this->db->fetchByAssoc($result);
        
        if (!$row) {
            return null;
        }
        
        // Parse version data JSON
        $versionData = json_decode($row['version_data'], true);
        
        return array(
            'id' => $row['id'],
            'name' => $row['name'],
            'category' => $row['category'],
            'description' => $row['description'],
            'version' => $row['version'],
            'tasks' => $versionData['tasks'] ?? array(),
            'variables' => $versionData['variables'] ?? array(),
            'metadata' => $versionData['metadata'] ?? array()
        );
    }
    
    /**
     * Load deal data for variable substitution
     * 
     * @param string $dealId Deal ID
     * @return array|null Deal data
     */
    private function loadDealData($dealId)
    {
        $query = "SELECT o.*, a.name as account_name, a.billing_address_city, a.billing_address_state,
                         a.billing_address_country, c.first_name as contact_first_name, 
                         c.last_name as contact_last_name, c.email1 as contact_email,
                         u.first_name as assigned_user_first_name, u.last_name as assigned_user_last_name
                  FROM opportunities o
                  LEFT JOIN accounts a ON o.account_id = a.id AND a.deleted = 0
                  LEFT JOIN contacts c ON o.contact_id = c.id AND c.deleted = 0  
                  LEFT JOIN users u ON o.assigned_user_id = u.id AND u.deleted = 0
                  WHERE o.id = ? AND o.deleted = 0";
        
        $result = $this->db->pQuery($query, array($dealId));
        $row = $this->db->fetchByAssoc($result);
        
        if (!$row) {
            return null;
        }
        
        // Format deal data for variable substitution
        return array(
            'deal_id' => $row['id'],
            'deal_name' => $row['name'],
            'deal_amount' => $row['amount'],
            'deal_stage' => $row['pipeline_stage_c'],
            'deal_close_date' => $row['date_closed'],
            'account_id' => $row['account_id'],
            'account_name' => $row['account_name'],
            'account_city' => $row['billing_address_city'],
            'account_state' => $row['billing_address_state'],
            'account_country' => $row['billing_address_country'],
            'contact_name' => trim($row['contact_first_name'] . ' ' . $row['contact_last_name']),
            'contact_first_name' => $row['contact_first_name'],
            'contact_last_name' => $row['contact_last_name'],
            'contact_email' => $row['contact_email'],
            'assigned_user' => trim($row['assigned_user_first_name'] . ' ' . $row['assigned_user_last_name']),
            'assigned_user_first_name' => $row['assigned_user_first_name'],
            'assigned_user_last_name' => $row['assigned_user_last_name'],
            'date_entered' => $row['date_entered'],
            'date_modified' => $row['date_modified']
        );
    }
    
    /**
     * Evaluate conditional logic to determine applicable templates
     * 
     * @param array $conditions Conditional rules
     * @param array $dealData Deal data
     * @return array Applicable template IDs
     */
    private function evaluateConditions($conditions, $dealData)
    {
        $applicableTemplates = array();
        
        foreach ($conditions as $condition) {
            $field = $condition['field'];
            $operator = $condition['operator'];
            $value = $condition['value'];
            $templateIds = $condition['template_ids'];
            
            $dealValue = $dealData[$field] ?? null;
            
            $conditionMet = false;
            
            switch ($operator) {
                case 'equals':
                    $conditionMet = ($dealValue == $value);
                    break;
                case 'not_equals':
                    $conditionMet = ($dealValue != $value);
                    break;
                case 'greater_than':
                    $conditionMet = (is_numeric($dealValue) && $dealValue > $value);
                    break;
                case 'less_than':
                    $conditionMet = (is_numeric($dealValue) && $dealValue < $value);
                    break;
                case 'contains':
                    $conditionMet = (strpos(strtolower($dealValue), strtolower($value)) !== false);
                    break;
                case 'starts_with':
                    $conditionMet = (strpos(strtolower($dealValue), strtolower($value)) === 0);
                    break;
                case 'in':
                    $conditionMet = in_array($dealValue, (array)$value);
                    break;
                case 'not_in':
                    $conditionMet = !in_array($dealValue, (array)$value);
                    break;
            }
            
            if ($conditionMet) {
                $applicableTemplates = array_merge($applicableTemplates, $templateIds);
            }
        }
        
        return array_unique($applicableTemplates);
    }
    
    /**
     * Log task generation activity
     * 
     * @param string $templateId Template ID
     * @param string $dealId Deal ID
     * @param array $result Generation result
     */
    private function logGeneration($templateId, $dealId, $result)
    {
        global $current_user;
        
        $logId = create_guid();
        $query = "INSERT INTO task_generation_log 
                  (id, template_id, deal_id, generation_id, tasks_generated, 
                   generated_by, date_generated, status, deleted)
                  VALUES (?, ?, ?, ?, ?, ?, NOW(), 'completed', 0)";
        
        $this->db->pQuery($query, array(
            $logId,
            $templateId,
            $dealId,
            $result['generation_id'],
            count($result['tasks']),
            $current_user->id ?? 'system'
        ));
    }
    
    /**
     * Load previous generation data
     * 
     * @param string $generationId Generation ID
     * @return array|null Generation data
     */
    private function loadGeneration($generationId)
    {
        $query = "SELECT * FROM task_generation_log 
                  WHERE generation_id = ? AND deleted = 0";
        
        $result = $this->db->pQuery($query, array($generationId));
        $row = $this->db->fetchByAssoc($result);
        
        return $row ?: null;
    }
}