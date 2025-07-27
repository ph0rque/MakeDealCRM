<?php
/**
 * Task Generation API for Due Diligence Checklist System
 * 
 * Provides RESTful endpoints for task auto-generation functionality
 * including template-based generation, conditional logic, and bulk operations.
 * 
 * @category  API
 * @package   Deals
 * @author    MakeDealCRM
 * @license   GPL-3.0+
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once 'include/api/SugarApi.php';
require_once 'custom/modules/Deals/api/TaskGenerationEngine.php';
require_once 'modules/ACL/ACLController.php';

class TaskGenerationApi extends SugarApi
{
    private $engine;
    
    public function __construct()
    {
        $this->engine = new TaskGenerationEngine();
    }
    
    /**
     * Register API endpoints
     */
    public function registerApiRest()
    {
        return array(
            'generateTasks' => array(
                'reqType' => 'POST',
                'path' => array('Deals', 'tasks', 'generate'),
                'pathVars' => array('module', 'tasks', 'generate'),
                'method' => 'generateTasks',
                'shortHelp' => 'Generate tasks from template',
                'longHelp' => 'Generate checklist tasks from a template for a specific deal',
            ),
            'generateTasksConditional' => array(
                'reqType' => 'POST',
                'path' => array('Deals', 'tasks', 'generate', 'conditional'),
                'pathVars' => array('module', 'tasks', 'generate', 'conditional'),
                'method' => 'generateTasksConditional',
                'shortHelp' => 'Generate tasks based on conditions',
                'longHelp' => 'Generate tasks using conditional logic based on deal characteristics',
            ),
            'generateTasksBulk' => array(
                'reqType' => 'POST',
                'path' => array('Deals', 'tasks', 'generate', 'bulk'),
                'pathVars' => array('module', 'tasks', 'generate', 'bulk'),
                'method' => 'generateTasksBulk',
                'shortHelp' => 'Generate tasks from multiple templates',
                'longHelp' => 'Generate tasks from multiple templates in a single operation',
            ),
            'regenerateTasks' => array(
                'reqType' => 'POST',
                'path' => array('Deals', 'tasks', 'regenerate'),
                'pathVars' => array('module', 'tasks', 'regenerate'),
                'method' => 'regenerateTasks',
                'shortHelp' => 'Regenerate tasks',
                'longHelp' => 'Regenerate tasks from a previous generation with updated data',
            ),
            'getGenerations' => array(
                'reqType' => 'GET',
                'path' => array('Deals', 'tasks', 'generations'),
                'pathVars' => array('module', 'tasks', 'generations'),
                'method' => 'getGenerations',
                'shortHelp' => 'Get task generations',
                'longHelp' => 'Retrieve task generation history for a deal',
            ),
            'previewGeneration' => array(
                'reqType' => 'POST',
                'path' => array('Deals', 'tasks', 'preview'),
                'pathVars' => array('module', 'tasks', 'preview'),
                'method' => 'previewGeneration',
                'shortHelp' => 'Preview task generation',
                'longHelp' => 'Preview what tasks would be generated without creating them',
            ),
            'getGenerationStatus' => array(
                'reqType' => 'GET',
                'path' => array('Deals', 'tasks', 'generation', '?'),
                'pathVars' => array('module', 'tasks', 'generation', 'generation_id'),
                'method' => 'getGenerationStatus',
                'shortHelp' => 'Get generation status',
                'longHelp' => 'Get the status and details of a specific task generation',
            ),
        );
    }
    
    /**
     * Generate tasks from template
     * 
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function generateTasks($api, $args)
    {
        $this->requireArgs($args, array('module', 'template_id', 'deal_id'));
        
        // Check permissions
        if (!ACLController::checkAccess('Deals', 'edit', true)) {
            throw new SugarApiExceptionNotAuthorized('No access to create tasks for Deals');
        }
        
        $templateId = $args['template_id'];
        $dealId = $args['deal_id'];
        $variables = $args['variables'] ?? array();
        $options = $args['options'] ?? array();
        
        // Validate deal exists and user has access
        $this->validateDealAccess($dealId);
        
        try {
            $result = $this->engine->generateTasksFromTemplate(
                $templateId,
                $dealId,
                $variables,
                $options
            );
            
            return $result;
            
        } catch (Exception $e) {
            throw new SugarApiExceptionError($e->getMessage());
        }
    }
    
    /**
     * Generate tasks based on conditional logic
     * 
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function generateTasksConditional($api, $args)
    {
        $this->requireArgs($args, array('module', 'deal_id', 'conditions'));
        
        // Check permissions
        if (!ACLController::checkAccess('Deals', 'edit', true)) {
            throw new SugarApiExceptionNotAuthorized('No access to create tasks for Deals');
        }
        
        $dealId = $args['deal_id'];
        $conditions = $args['conditions'];
        $options = $args['options'] ?? array();
        
        // Validate deal exists and user has access
        $this->validateDealAccess($dealId);
        
        try {
            $result = $this->engine->generateConditionalTasks(
                $dealId,
                $conditions,
                $options
            );
            
            return $result;
            
        } catch (Exception $e) {
            throw new SugarApiExceptionError($e->getMessage());
        }
    }
    
    /**
     * Generate tasks from multiple templates
     * 
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function generateTasksBulk($api, $args)
    {
        $this->requireArgs($args, array('module', 'template_ids', 'deal_id'));
        
        // Check permissions
        if (!ACLController::checkAccess('Deals', 'edit', true)) {
            throw new SugarApiExceptionNotAuthorized('No access to create tasks for Deals');
        }
        
        $templateIds = $args['template_ids'];
        $dealId = $args['deal_id'];
        $variables = $args['variables'] ?? array();
        $options = $args['options'] ?? array();
        
        // Validate input
        if (!is_array($templateIds) || empty($templateIds)) {
            throw new SugarApiExceptionMissingParameter('template_ids must be a non-empty array');
        }
        
        // Validate deal exists and user has access
        $this->validateDealAccess($dealId);
        
        try {
            $result = $this->engine->generateTasksFromMultipleTemplates(
                $templateIds,
                $dealId,
                $variables,
                $options
            );
            
            return $result;
            
        } catch (Exception $e) {
            throw new SugarApiExceptionError($e->getMessage());
        }
    }
    
    /**
     * Regenerate tasks from previous generation
     * 
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function regenerateTasks($api, $args)
    {
        $this->requireArgs($args, array('module', 'deal_id', 'generation_id'));
        
        // Check permissions
        if (!ACLController::checkAccess('Deals', 'edit', true)) {
            throw new SugarApiExceptionNotAuthorized('No access to create tasks for Deals');
        }
        
        $dealId = $args['deal_id'];
        $generationId = $args['generation_id'];
        $options = $args['options'] ?? array();
        
        // Validate deal exists and user has access
        $this->validateDealAccess($dealId);
        
        try {
            $result = $this->engine->regenerateTasks(
                $dealId,
                $generationId,
                $options
            );
            
            return $result;
            
        } catch (Exception $e) {
            throw new SugarApiExceptionError($e->getMessage());
        }
    }
    
    /**
     * Get task generation history
     * 
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function getGenerations($api, $args)
    {
        $this->requireArgs($args, array('module'));
        
        // Check permissions
        if (!ACLController::checkAccess('Deals', 'list', true)) {
            throw new SugarApiExceptionNotAuthorized('No access to view Deal tasks');
        }
        
        $dealId = $args['deal_id'] ?? null;
        $limit = min((int)($args['limit'] ?? 20), 100);
        $offset = (int)($args['offset'] ?? 0);
        
        global $db;
        
        // Build query
        $whereClause = "tg.deleted = 0";
        $params = array();
        
        if ($dealId) {
            $this->validateDealAccess($dealId);
            $whereClause .= " AND tg.deal_id = ?";
            $params[] = $dealId;
        }
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total FROM task_generations tg WHERE $whereClause";
        $countResult = $db->pQuery($countQuery, $params);
        $countRow = $db->fetchByAssoc($countResult);
        $total = (int)$countRow['total'];
        
        // Get generations with details
        $query = "SELECT tg.*, 
                         ct.name as template_name,
                         ct.category as template_category,
                         o.name as deal_name,
                         u.first_name, u.last_name
                  FROM task_generations tg
                  LEFT JOIN checklist_templates ct ON tg.template_id = ct.id
                  LEFT JOIN opportunities o ON tg.deal_id = o.id AND o.deleted = 0
                  LEFT JOIN users u ON tg.created_by = u.id AND u.deleted = 0
                  WHERE $whereClause
                  ORDER BY tg.date_created DESC
                  LIMIT $limit OFFSET $offset";
        
        $result = $db->pQuery($query, $params);
        $generations = array();
        
        while ($row = $db->fetchByAssoc($result)) {
            $generations[] = $this->formatGenerationData($row);
        }
        
        return array(
            'success' => true,
            'records' => $generations,
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
            'has_more' => ($offset + $limit) < $total
        );
    }
    
    /**
     * Preview task generation without creating tasks
     * 
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function previewGeneration($api, $args)
    {
        $this->requireArgs($args, array('module', 'template_id', 'deal_id'));
        
        // Check permissions
        if (!ACLController::checkAccess('Deals', 'view', true)) {
            throw new SugarApiExceptionNotAuthorized('No access to view Deal tasks');
        }
        
        $templateId = $args['template_id'];
        $dealId = $args['deal_id'];
        $variables = $args['variables'] ?? array();
        $options = array_merge($args['options'] ?? array(), array('preview_only' => true));
        
        // Validate deal exists and user has access
        $this->validateDealAccess($dealId);
        
        try {
            // Use a modified engine instance for preview
            $previewResult = $this->generatePreview($templateId, $dealId, $variables, $options);
            
            return array(
                'success' => true,
                'preview' => true,
                'template_id' => $templateId,
                'deal_id' => $dealId,
                'tasks_preview' => $previewResult['tasks'],
                'task_count' => count($previewResult['tasks']),
                'warnings' => $previewResult['warnings'] ?? array(),
                'variables_used' => $previewResult['variables_used'] ?? array()
            );
            
        } catch (Exception $e) {
            throw new SugarApiExceptionError($e->getMessage());
        }
    }
    
    /**
     * Get status of specific generation
     * 
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function getGenerationStatus($api, $args)
    {
        $this->requireArgs($args, array('module', 'generation_id'));
        
        // Check permissions
        if (!ACLController::checkAccess('Deals', 'view', true)) {
            throw new SugarApiExceptionNotAuthorized('No access to view Deal tasks');
        }
        
        $generationId = $args['generation_id'];
        
        global $db;
        
        // Get generation details
        $query = "SELECT tg.*, 
                         ct.name as template_name,
                         o.name as deal_name,
                         u.first_name, u.last_name,
                         COUNT(t.id) as actual_task_count,
                         SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                         SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
                         SUM(CASE WHEN t.deleted = 1 THEN 1 ELSE 0 END) as deleted_tasks
                  FROM task_generations tg
                  LEFT JOIN checklist_templates ct ON tg.template_id = ct.id
                  LEFT JOIN opportunities o ON tg.deal_id = o.id AND o.deleted = 0
                  LEFT JOIN users u ON tg.created_by = u.id AND u.deleted = 0
                  LEFT JOIN tasks t ON tg.id = t.generation_id
                  WHERE tg.id = ? AND tg.deleted = 0
                  GROUP BY tg.id";
        
        $result = $db->pQuery($query, array($generationId));
        $row = $db->fetchByAssoc($result);
        
        if (!$row) {
            throw new SugarApiExceptionNotFound('Generation not found');
        }
        
        // Check deal access
        if ($row['deal_id']) {
            $this->validateDealAccess($row['deal_id']);
        }
        
        return array(
            'success' => true,
            'generation' => $this->formatGenerationData($row, true)
        );
    }
    
    /**
     * Validate deal access
     * 
     * @param string $dealId Deal ID
     * @throws SugarApiExceptionNotFound
     * @throws SugarApiExceptionNotAuthorized
     */
    private function validateDealAccess($dealId)
    {
        $deal = BeanFactory::getBean('Deals', $dealId);
        
        if (empty($deal->id)) {
            throw new SugarApiExceptionNotFound('Deal not found');
        }
        
        if (!$deal->ACLAccess('view')) {
            throw new SugarApiExceptionNotAuthorized('No access to this Deal');
        }
    }
    
    /**
     * Generate preview without creating actual tasks
     * 
     * @param string $templateId Template ID
     * @param string $dealId Deal ID
     * @param array $variables Variables
     * @param array $options Options
     * @return array Preview result
     */
    private function generatePreview($templateId, $dealId, $variables, $options)
    {
        // Create a preview engine that doesn't actually create tasks
        $previewEngine = new TaskGenerationEngine();
        
        // Use reflection to access private methods for preview
        $reflectionClass = new ReflectionClass($previewEngine);
        
        // Load template
        $loadTemplateMethod = $reflectionClass->getMethod('loadTemplate');
        $loadTemplateMethod->setAccessible(true);
        $template = $loadTemplateMethod->invoke($previewEngine, $templateId);
        
        if (!$template) {
            throw new Exception("Template not found: $templateId");
        }
        
        // Load deal data
        $loadDealDataMethod = $reflectionClass->getMethod('loadDealData');
        $loadDealDataMethod->setAccessible(true);
        $dealData = $loadDealDataMethod->invoke($previewEngine, $dealId);
        
        if (!$dealData) {
            throw new Exception("Deal not found: $dealId");
        }
        
        // Merge variables
        $allVariables = array_merge($dealData, $variables);
        
        // Parse template
        $templateParser = new TemplateParser();
        $parsedTemplate = $templateParser->parseTemplate($template, $allVariables);
        
        // Calculate schedules
        $schedulingSystem = new SchedulingSystem();
        $scheduledTasks = $schedulingSystem->calculateSchedules(
            $parsedTemplate['tasks'],
            $dealData,
            $options
        );
        
        // Resolve dependencies
        $dependencyManager = new DependencyManager();
        $dependencyResolvedTasks = $dependencyManager->resolveDependencies(
            $scheduledTasks,
            $dealId
        );
        
        return array(
            'tasks' => $dependencyResolvedTasks,
            'variables_used' => $parsedTemplate['parsed_variables'] ?? array(),
            'warnings' => array()
        );
    }
    
    /**
     * Format generation data for API response
     * 
     * @param array $row Database row
     * @param bool $includeStats Include task statistics
     * @return array Formatted data
     */
    private function formatGenerationData($row, $includeStats = false)
    {
        $data = array(
            'id' => $row['id'],
            'deal_id' => $row['deal_id'],
            'deal_name' => $row['deal_name'],
            'template_id' => $row['template_id'],
            'template_name' => $row['template_name'],
            'template_category' => $row['template_category'],
            'task_count' => (int)$row['task_count'],
            'status' => $row['status'],
            'created_by' => array(
                'id' => $row['created_by'],
                'name' => trim($row['first_name'] . ' ' . $row['last_name'])
            ),
            'date_created' => $row['date_created']
        );
        
        if ($includeStats) {
            $data['statistics'] = array(
                'actual_task_count' => (int)($row['actual_task_count'] ?? 0),
                'completed_tasks' => (int)($row['completed_tasks'] ?? 0),
                'in_progress_tasks' => (int)($row['in_progress_tasks'] ?? 0),
                'deleted_tasks' => (int)($row['deleted_tasks'] ?? 0),
                'completion_percentage' => $row['actual_task_count'] > 0 
                    ? round(($row['completed_tasks'] / $row['actual_task_count']) * 100, 2)
                    : 0
            );
        }
        
        return $data;
    }
}