<?php
/**
 * Secure Deal Class - Extends Opportunity
 * Provides enhanced pipeline management functionality with security improvements
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('modules/Opportunities/Opportunity.php');
require_once('include/database/DBManagerFactory.php');
require_once('custom/modules/Deals/DealsSecurityHelper.php');

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
     * Override save to handle pipeline stage changes
     */
    public function save($check_notify = false)
    {
        global $current_user;
        
        // Validate pipeline stage
        if (!empty($this->pipeline_stage_c)) {
            $this->pipeline_stage_c = $this->validatePipelineStage($this->pipeline_stage_c);
        }
        
        // Check if pipeline stage has changed
        if (!empty($this->pipeline_stage_c) && 
            (!empty($this->fetched_row['pipeline_stage_c']) && 
             $this->pipeline_stage_c != $this->fetched_row['pipeline_stage_c'])) {
            
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
        }
        
        // If this is a new record and pipeline_stage_c is not set
        if (empty($this->id) && empty($this->pipeline_stage_c)) {
            $this->pipeline_stage_c = 'sourcing';
            $this->stage_entered_date_c = date('Y-m-d H:i:s');
        }
        
        return parent::save($check_notify);
    }
    
    /**
     * Validate pipeline stage value
     */
    private function validatePipelineStage($stage)
    {
        return isset($this->pipeline_stages[$stage]) ? $stage : 'sourcing';
    }
    
    /**
     * Log pipeline stage changes using prepared statements
     */
    private function logStageChange($old_stage, $new_stage, $user_id)
    {
        global $db;
        
        // Check if table exists
        $tableCheck = "SHOW TABLES LIKE 'pipeline_stage_history'";
        $result = $db->query($tableCheck);
        
        if ($db->fetchByAssoc($result)) {
            $id = create_guid();
            
            // Use security helper for prepared statement
            $query = DealsSecurityHelper::prepareSQLQuery(
                "INSERT INTO pipeline_stage_history 
                 (id, deal_id, old_stage, new_stage, changed_by, date_changed, deleted) 
                 VALUES 
                 (:id, :deal_id, :old_stage, :new_stage, :changed_by, NOW(), 0)",
                array(
                    'id' => $id,
                    'deal_id' => $this->id,
                    'old_stage' => $old_stage,
                    'new_stage' => $new_stage,
                    'changed_by' => $user_id
                )
            );
            
            $db->query($query);
            
            // Log security event
            DealsSecurityHelper::logSecurityEvent('stage_change', 'Deal stage changed', array(
                'deal_id' => $this->id,
                'old_stage' => $old_stage,
                'new_stage' => $new_stage
            ));
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
        
        try {
            $stage_date = new DateTime($this->stage_entered_date_c);
            $now = new DateTime();
            $interval = $now->diff($stage_date);
            return $interval->days;
        } catch (Exception $e) {
            $GLOBALS['log']->error('Deal: Error calculating days in stage - ' . $e->getMessage());
            return 0;
        }
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
        
        // Sanitize and validate target stage
        $target_stage = DealsSecurityHelper::sanitizeInput($target_stage, 'sql');
        
        if (!isset($this->pipeline_stages[$target_stage])) {
            return array(
                'allowed' => false,
                'reason' => 'Invalid target stage'
            );
        }
        
        // Check WIP limits
        $stage_config = $this->getPipelineStageConfig($target_stage);
        if ($stage_config && !empty($stage_config['wip_limit'])) {
            // Use security helper for prepared query
            $query = DealsSecurityHelper::prepareSQLQuery(
                "SELECT COUNT(*) as count 
                 FROM opportunities 
                 WHERE pipeline_stage_c = :stage 
                 AND deleted = 0 
                 AND sales_stage NOT IN ('Closed Won', 'Closed Lost')",
                array('stage' => $target_stage)
            );
            
            $result = $db->query($query);
            $row = $db->fetchByAssoc($result);
            
            if ($row['count'] >= $stage_config['wip_limit']) {
                return array(
                    'allowed' => false,
                    'reason' => 'WIP limit reached for stage ' . DealsSecurityHelper::encodeOutput($stage_config['name'])
                );
            }
        }
        
        return array('allowed' => true);
    }
    
    /**
     * Get pipeline metrics using prepared statements
     */
    public function getPipelineMetrics()
    {
        global $db;
        
        $metrics = array();
        
        foreach ($this->pipeline_stages as $stage_key => $stage) {
            // Sanitize stage key
            $safe_stage_key = DealsSecurityHelper::sanitizeInput($stage_key, 'sql');
            
            // Use security helper for prepared query
            $query = DealsSecurityHelper::prepareSQLQuery(
                "SELECT 
                    COUNT(*) as count,
                    SUM(amount) as total_value,
                    AVG(DATEDIFF(NOW(), stage_entered_date_c)) as avg_days
                  FROM opportunities 
                  WHERE pipeline_stage_c = :stage 
                  AND deleted = 0 
                  AND sales_stage NOT IN ('Closed Won', 'Closed Lost')",
                array('stage' => $safe_stage_key)
            );
            
            $result = $db->query($query);
            $row = $db->fetchByAssoc($result);
            
            $metrics[$stage_key] = array(
                'count' => (int)$row['count'],
                'total_value' => (float)$row['total_value'],
                'avg_days' => (float)$row['avg_days'],
                'wip_limit' => $stage['wip_limit'],
                'utilization' => $stage['wip_limit'] ? 
                    round(($row['count'] / $stage['wip_limit']) * 100, 2) : 0
            );
        }
        
        return $metrics;
    }
    
    /**
     * Sanitize output for display
     */
    public function sanitizeForDisplay($field)
    {
        $value = $this->$field;
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}