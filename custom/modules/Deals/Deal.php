<?php
/**
 * Deal Class - Extends Opportunity
 * Provides enhanced pipeline management functionality with comprehensive CRUD operations
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('modules/Opportunities/Opportunity.php');
require_once('include/SugarLogger.php');
require_once('modules/ACL/ACLController.php');
require_once('include/utils.php');

class Deal extends Opportunity
{
    public $module_dir = 'Deals';
    public $module_name = 'Deals';
    public $object_name = 'Deal';
    public $table_name = 'opportunities';
    public $new_schema = true;
    public $importable = true;
    
    // Pipeline stages configuration
    public $pipeline_stages = array(
        'sourcing' => array(
            'name' => 'Sourcing',
            'order' => 1,
            'wip_limit' => 20,
            'sales_stage' => 'Prospecting'
        ),
        'screening' => array(
            'name' => 'Screening',
            'order' => 2,
            'wip_limit' => 15,
            'sales_stage' => 'Qualification'
        ),
        'analysis_outreach' => array(
            'name' => 'Analysis & Outreach',
            'order' => 3,
            'wip_limit' => 10,
            'sales_stage' => 'Needs Analysis'
        ),
        'due_diligence' => array(
            'name' => 'Due Diligence',
            'order' => 4,
            'wip_limit' => 8,
            'sales_stage' => 'Id. Decision Makers'
        ),
        'valuation_structuring' => array(
            'name' => 'Valuation & Structuring',
            'order' => 5,
            'wip_limit' => 6,
            'sales_stage' => 'Value Proposition'
        ),
        'loi_negotiation' => array(
            'name' => 'LOI / Negotiation',
            'order' => 6,
            'wip_limit' => 5,
            'sales_stage' => 'Negotiation/Review'
        ),
        'financing' => array(
            'name' => 'Financing',
            'order' => 7,
            'wip_limit' => 5,
            'sales_stage' => 'Proposal/Price Quote'
        ),
        'closing' => array(
            'name' => 'Closing',
            'order' => 8,
            'wip_limit' => 5,
            'sales_stage' => 'Negotiation/Review'
        ),
        'closed_owned_90_day' => array(
            'name' => 'Closed/Owned – 90-Day Plan',
            'order' => 9,
            'wip_limit' => 10,
            'sales_stage' => 'Closed Won'
        ),
        'closed_owned_stable' => array(
            'name' => 'Closed/Owned – Stable Operations',
            'order' => 10,
            'wip_limit' => null,
            'sales_stage' => 'Closed Won'
        ),
        'unavailable' => array(
            'name' => 'Unavailable',
            'order' => 11,
            'wip_limit' => null,
            'sales_stage' => 'Closed Lost'
        )
    );
    
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * Override save to handle pipeline stage changes with comprehensive validation
     */
    public function save($check_notify = false)
    {
        global $current_user;
        
        try {
            // Validate before saving
            $validationResult = $this->validateDeal();
            if (!$validationResult['valid']) {
                $GLOBALS['log']->error('Deal save failed validation: ' . implode(', ', $validationResult['errors']));
                throw new Exception('Validation failed: ' . implode(', ', $validationResult['errors']));
            }
            
            // Check if pipeline stage has changed
            if (!empty($this->pipeline_stage_c) && 
                (!empty($this->fetched_row['pipeline_stage_c']) && 
                 $this->pipeline_stage_c != $this->fetched_row['pipeline_stage_c'])) {
                
                // Validate stage transition
                $transitionResult = $this->validateStageTransition(
                    $this->fetched_row['pipeline_stage_c'], 
                    $this->pipeline_stage_c
                );
                
                if (!$transitionResult['allowed']) {
                    throw new Exception('Stage transition not allowed: ' . $transitionResult['reason']);
                }
                
                // Update stage entered date
                $this->stage_entered_date_c = date('Y-m-d H:i:s');
                
                // Map pipeline stage to sales stage
                if (isset($this->pipeline_stages[$this->pipeline_stage_c])) {
                    $this->sales_stage = $this->pipeline_stages[$this->pipeline_stage_c]['sales_stage'];
                }
                
                // Log stage change
                $this->logStageChange(
                    $this->fetched_row['pipeline_stage_c'], 
                    $this->pipeline_stage_c,
                    $current_user->id
                );
                
                // Trigger workflow engine hooks
                $this->triggerWorkflowHooks('stage_change', [
                    'old_stage' => $this->fetched_row['pipeline_stage_c'],
                    'new_stage' => $this->pipeline_stage_c,
                    'user_id' => $current_user->id
                ]);
            }
            
            // If this is a new record and pipeline_stage_c is not set
            if (empty($this->id) && empty($this->pipeline_stage_c)) {
                $this->pipeline_stage_c = 'sourcing';
                $this->stage_entered_date_c = date('Y-m-d H:i:s');
                
                // Set default values for new deals
                $this->setDefaultValues();
            }
            
            // Call parent save with error handling
            $result = parent::save($check_notify);
            
            // Log successful save
            $GLOBALS['log']->info('Deal saved successfully: ' . $this->id . ' - ' . $this->name);
            
            // Trigger post-save hooks
            $this->triggerWorkflowHooks('post_save', [
                'deal_id' => $this->id,
                'is_new' => empty($this->fetched_row),
                'user_id' => $current_user->id
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('Deal save failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Log pipeline stage changes
     */
    private function logStageChange($old_stage, $new_stage, $user_id)
    {
        global $db;
        
        // Check if table exists
        $tableCheck = "SHOW TABLES LIKE 'pipeline_stage_history'";
        $result = $db->query($tableCheck);
        
        if ($db->fetchByAssoc($result)) {
            $id = create_guid();
            $query = "INSERT INTO pipeline_stage_history 
                      (id, deal_id, old_stage, new_stage, changed_by, date_changed, deleted) 
                      VALUES 
                      ('$id', '{$this->id}', '$old_stage', '$new_stage', '$user_id', NOW(), 0)";
            $db->query($query);
        }
    }
    
    /**
     * Get days in current stage
     */
    public function getDaysInStage()
    {
        if (empty($this->stage_entered_date_c)) {
            return 0;
        }
        
        $stage_date = new DateTime($this->stage_entered_date_c);
        $now = new DateTime();
        $interval = $now->diff($stage_date);
        
        return $interval->days;
    }
    
    /**
     * Get pipeline stage configuration
     */
    public function getPipelineStageConfig($stage = null)
    {
        if ($stage === null) {
            return $this->pipeline_stages;
        }
        
        return isset($this->pipeline_stages[$stage]) ? $this->pipeline_stages[$stage] : null;
    }
    
    /**
     * Check if deal can move to a specific stage
     */
    public function canMoveToStage($target_stage)
    {
        global $db;
        
        // Check WIP limits
        $stage_config = $this->getPipelineStageConfig($target_stage);
        if ($stage_config && !empty($stage_config['wip_limit'])) {
            $query = "SELECT COUNT(*) as count 
                      FROM opportunities 
                      WHERE pipeline_stage_c = '$target_stage' 
                      AND deleted = 0 
                      AND sales_stage NOT IN ('Closed Won', 'Closed Lost')";
            $result = $db->query($query);
            $row = $db->fetchByAssoc($result);
            
            if ($row['count'] >= $stage_config['wip_limit']) {
                return array(
                    'allowed' => false,
                    'reason' => 'WIP limit reached for stage ' . $stage_config['name']
                );
            }
        }
        
        return array('allowed' => true);
    }
    
    /**
     * Get pipeline metrics with enhanced error handling
     */
    public function getPipelineMetrics()
    {
        global $db;
        
        try {
            $metrics = array();
            
            foreach ($this->pipeline_stages as $stage_key => $stage) {
                $stage_key_safe = $db->quote($stage_key);
                $query = "SELECT 
                            COUNT(*) as count,
                            SUM(amount) as total_value,
                            AVG(DATEDIFF(NOW(), stage_entered_date_c)) as avg_days
                          FROM opportunities 
                          WHERE pipeline_stage_c = $stage_key_safe 
                          AND deleted = 0 
                          AND sales_stage NOT IN ('Closed Won', 'Closed Lost')";
                
                $result = $db->query($query);
                if (!$result) {
                    $GLOBALS['log']->error('Failed to query pipeline metrics for stage: ' . $stage_key);
                    continue;
                }
                
                $row = $db->fetchByAssoc($result);
                
                $metrics[$stage_key] = array(
                    'count' => (int)($row['count'] ?? 0),
                    'total_value' => (float)($row['total_value'] ?? 0),
                    'avg_days' => (float)($row['avg_days'] ?? 0),
                    'wip_limit' => $stage['wip_limit'],
                    'utilization' => $stage['wip_limit'] ? 
                        round(($row['count'] / $stage['wip_limit']) * 100, 2) : 0
                );
            }
            
            return $metrics;
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('Error getting pipeline metrics: ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Validate deal data before saving
     */
    public function validateDeal()
    {
        $errors = array();
        $valid = true;
        
        // Required field validation
        if (empty($this->name)) {
            $errors[] = 'Deal name is required';
            $valid = false;
        }
        
        // Amount validation
        if (!empty($this->amount) && !is_numeric($this->amount)) {
            $errors[] = 'Deal amount must be numeric';
            $valid = false;
        }
        
        // Pipeline stage validation
        if (!empty($this->pipeline_stage_c) && !isset($this->pipeline_stages[$this->pipeline_stage_c])) {
            $errors[] = 'Invalid pipeline stage';
            $valid = false;
        }
        
        // Date validation
        if (!empty($this->date_closed) && !$this->isValidDate($this->date_closed)) {
            $errors[] = 'Invalid close date format';
            $valid = false;
        }
        
        // Probability validation
        if (!empty($this->probability) && ($this->probability < 0 || $this->probability > 100)) {
            $errors[] = 'Probability must be between 0 and 100';
            $valid = false;
        }
        
        return array(
            'valid' => $valid,
            'errors' => $errors
        );
    }
    
    /**
     * Validate stage transition
     */
    public function validateStageTransition($oldStage, $newStage)
    {
        // Check WIP limits first
        $wipResult = $this->canMoveToStage($newStage);
        if (!$wipResult['allowed']) {
            return $wipResult;
        }
        
        // Check stage progression rules
        if (!empty($oldStage) && !empty($newStage)) {
            $oldOrder = $this->pipeline_stages[$oldStage]['order'] ?? 0;
            $newOrder = $this->pipeline_stages[$newStage]['order'] ?? 0;
            
            // Allow backward movement (regression)
            // Allow forward movement to any stage
            // This provides flexibility while maintaining audit trail
        }
        
        return array('allowed' => true);
    }
    
    /**
     * Set default values for new deals
     */
    private function setDefaultValues()
    {
        global $current_user;
        
        if (empty($this->assigned_user_id)) {
            $this->assigned_user_id = $current_user->id;
        }
        
        if (empty($this->probability)) {
            // Set default probability based on stage
            $stageConfig = $this->getPipelineStageConfig($this->pipeline_stage_c);
            $this->probability = $this->getDefaultProbabilityForStage($this->pipeline_stage_c);
        }
        
        if (empty($this->currency_id)) {
            $this->currency_id = $current_user->getPreference('currency') ?? '-99';
        }
    }
    
    /**
     * Get default probability for a pipeline stage
     */
    private function getDefaultProbabilityForStage($stage)
    {
        $probabilities = array(
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
        
        return $probabilities[$stage] ?? 50;
    }
    
    /**
     * Trigger workflow engine hooks
     */
    private function triggerWorkflowHooks($event, $data = array())
    {
        try {
            // Call SuiteCRM workflow engine
            if (file_exists('modules/WorkFlow/WorkFlow.php')) {
                require_once('modules/WorkFlow/WorkFlow.php');
                $workflow = new WorkFlow();
                $workflow->fire_workflow($this, $event, $data);
            }
            
            // Call custom logic hooks
            $this->call_custom_logic_hooks($event, $data);
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('Workflow hook failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Validate date format
     */
    private function isValidDate($date)
    {
        $formats = array('Y-m-d', 'Y-m-d H:i:s', 'm/d/Y', 'd/m/Y');
        
        foreach ($formats as $format) {
            $d = DateTime::createFromFormat($format, $date);
            if ($d && $d->format($format) === $date) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Enhanced delete with cascade handling
     */
    public function mark_deleted($id)
    {
        global $db;
        
        try {
            // Load the record first
            $this->retrieve($id);
            if (empty($this->id)) {
                throw new Exception('Deal not found: ' . $id);
            }
            
            // Check delete permissions
            if (!$this->ACLAccess('delete')) {
                throw new Exception('Access denied for delete operation');
            }
            
            // Log the deletion
            $GLOBALS['log']->info('Deleting deal: ' . $this->id . ' - ' . $this->name);
            
            // Handle cascading relationships
            $this->handleCascadeDelete();
            
            // Trigger pre-delete hooks
            $this->triggerWorkflowHooks('pre_delete', array('deal_id' => $this->id));
            
            // Call parent delete
            $result = parent::mark_deleted($id);
            
            // Trigger post-delete hooks
            $this->triggerWorkflowHooks('post_delete', array('deal_id' => $this->id));
            
            return $result;
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('Deal deletion failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Handle cascade delete for related records
     */
    private function handleCascadeDelete()
    {
        global $db;
        
        try {
            // Delete related checklist items
            $query = "UPDATE checklist_items SET deleted = 1, date_modified = NOW() 
                      WHERE deal_id = '{$this->id}' AND deleted = 0";
            $db->query($query);
            
            // Delete pipeline stage history
            $query = "UPDATE pipeline_stage_history SET deleted = 1, date_modified = NOW() 
                      WHERE deal_id = '{$this->id}' AND deleted = 0";
            $db->query($query);
            
            // Note: We don't delete related accounts, contacts, etc. as they may be used by other records
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('Cascade delete failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Enhanced search functionality
     */
    public function searchDeals($searchParams = array())
    {
        global $db;
        
        try {
            $whereClause = array('opportunities.deleted = 0');
            $joinClause = '';
            
            // Join with custom table for pipeline fields
            $joinClause .= " LEFT JOIN opportunities_cstm ON opportunities.id = opportunities_cstm.id_c ";
            
            // Name search
            if (!empty($searchParams['name'])) {
                $name = $db->quote('%' . $searchParams['name'] . '%');
                $whereClause[] = "opportunities.name LIKE $name";
            }
            
            // Account search
            if (!empty($searchParams['account_name'])) {
                $joinClause .= " LEFT JOIN accounts ON opportunities.account_id = accounts.id AND accounts.deleted = 0 ";
                $accountName = $db->quote('%' . $searchParams['account_name'] . '%');
                $whereClause[] = "accounts.name LIKE $accountName";
            }
            
            // Pipeline stage search
            if (!empty($searchParams['pipeline_stage'])) {
                $stage = $db->quote($searchParams['pipeline_stage']);
                $whereClause[] = "opportunities_cstm.pipeline_stage_c = $stage";
            }
            
            // Amount range search
            if (!empty($searchParams['min_amount'])) {
                $minAmount = (float)$searchParams['min_amount'];
                $whereClause[] = "opportunities.amount >= $minAmount";
            }
            
            if (!empty($searchParams['max_amount'])) {
                $maxAmount = (float)$searchParams['max_amount'];
                $whereClause[] = "opportunities.amount <= $maxAmount";
            }
            
            // Date range search
            if (!empty($searchParams['start_date'])) {
                $startDate = $db->quote($searchParams['start_date']);
                $whereClause[] = "opportunities.date_entered >= $startDate";
            }
            
            if (!empty($searchParams['end_date'])) {
                $endDate = $db->quote($searchParams['end_date']);
                $whereClause[] = "opportunities.date_entered <= $endDate";
            }
            
            // Assigned user search
            if (!empty($searchParams['assigned_user_id'])) {
                $userId = $db->quote($searchParams['assigned_user_id']);
                $whereClause[] = "opportunities.assigned_user_id = $userId";
            }
            
            // Build the query
            $query = "SELECT opportunities.*, opportunities_cstm.*, accounts.name as account_name
                      FROM opportunities 
                      $joinClause
                      WHERE " . implode(' AND ', $whereClause) . "
                      ORDER BY opportunities.date_modified DESC";
            
            // Add limit if specified
            if (!empty($searchParams['limit'])) {
                $limit = (int)$searchParams['limit'];
                $query .= " LIMIT $limit";
            }
            
            $result = $db->query($query);
            $deals = array();
            
            while ($row = $db->fetchByAssoc($result)) {
                $deals[] = $row;
            }
            
            return $deals;
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('Deal search failed: ' . $e->getMessage());
            return array();
        }
    }
}