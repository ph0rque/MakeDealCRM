<?php
/**
 * Comprehensive Validation Class for Deals Module
 * Uses SuiteCRM's validation patterns and framework
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/SugarLogger.php');
require_once('include/utils.php');

class DealValidator
{
    private $errors = array();
    private $warnings = array();
    
    /**
     * Validate deal data comprehensively
     */
    public function validateDeal($deal, $isNew = false)
    {
        $this->errors = array();
        $this->warnings = array();
        
        try {
            // Required field validation
            $this->validateRequiredFields($deal, $isNew);
            
            // Data type and format validation
            $this->validateDataTypes($deal);
            
            // Business rule validation
            $this->validateBusinessRules($deal);
            
            // Pipeline-specific validation
            $this->validatePipelineRules($deal);
            
            // Relationship validation
            $this->validateRelationships($deal);
            
            // Security validation
            $this->validateSecurity($deal);
            
            return array(
                'valid' => empty($this->errors),
                'errors' => $this->errors,
                'warnings' => $this->warnings
            );
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('Deal validation failed: ' . $e->getMessage());
            return array(
                'valid' => false,
                'errors' => array('Validation system error occurred'),
                'warnings' => array()
            );
        }
    }
    
    /**
     * Validate required fields
     */
    private function validateRequiredFields($deal, $isNew)
    {
        // Core required fields
        if (empty($deal->name)) {
            $this->errors[] = 'Deal name is required';
        }
        
        // New deal requirements
        if ($isNew) {
            if (empty($deal->assigned_user_id)) {
                $deal->assigned_user_id = $GLOBALS['current_user']->id;
                $this->warnings[] = 'Assigned user set to current user';
            }
            
            if (empty($deal->pipeline_stage_c)) {
                $deal->pipeline_stage_c = 'sourcing';
                $this->warnings[] = 'Pipeline stage set to default (sourcing)';
            }
        }
        
        // Conditional requirements based on stage
        if (!empty($deal->pipeline_stage_c)) {
            switch ($deal->pipeline_stage_c) {
                case 'due_diligence':
                case 'valuation_structuring':
                case 'loi_negotiation':
                    if (empty($deal->account_id)) {
                        $this->errors[] = 'Account is required for ' . $deal->pipeline_stage_c . ' stage';
                    }
                    if (empty($deal->amount)) {
                        $this->warnings[] = 'Deal amount should be specified for ' . $deal->pipeline_stage_c . ' stage';
                    }
                    break;
                    
                case 'financing':
                case 'closing':
                    if (empty($deal->amount)) {
                        $this->errors[] = 'Deal amount is required for ' . $deal->pipeline_stage_c . ' stage';
                    }
                    if (empty($deal->date_closed)) {
                        $this->errors[] = 'Expected close date is required for ' . $deal->pipeline_stage_c . ' stage';
                    }
                    break;
            }
        }
    }
    
    /**
     * Validate data types and formats
     */
    private function validateDataTypes($deal)
    {
        // Numeric validations
        if (!empty($deal->amount)) {
            if (!is_numeric($deal->amount)) {
                $this->errors[] = 'Deal amount must be a valid number';
            } elseif ((float)$deal->amount < 0) {
                $this->errors[] = 'Deal amount cannot be negative';
            } elseif ((float)$deal->amount > 999999999.99) {
                $this->errors[] = 'Deal amount exceeds maximum allowed value';
            }
        }
        
        if (!empty($deal->probability)) {
            if (!is_numeric($deal->probability)) {
                $this->errors[] = 'Probability must be a number';
            } elseif ((int)$deal->probability < 0 || (int)$deal->probability > 100) {
                $this->errors[] = 'Probability must be between 0 and 100';
            }
        }
        
        // Date validations
        if (!empty($deal->date_closed)) {
            if (!$this->isValidDate($deal->date_closed)) {
                $this->errors[] = 'Expected close date is not in valid format';
            } elseif (strtotime($deal->date_closed) < strtotime('-10 years')) {
                $this->errors[] = 'Expected close date cannot be more than 10 years in the past';
            } elseif (strtotime($deal->date_closed) > strtotime('+10 years')) {
                $this->warnings[] = 'Expected close date is more than 10 years in the future';
            }
        }
        
        if (!empty($deal->expected_close_date_c)) {
            if (!$this->isValidDate($deal->expected_close_date_c)) {
                $this->errors[] = 'Expected close date (custom) is not in valid format';
            }
        }
        
        // String length validations
        if (!empty($deal->name) && strlen($deal->name) > 255) {
            $this->errors[] = 'Deal name cannot exceed 255 characters';
        }
        
        if (!empty($deal->description) && strlen($deal->description) > 65535) {
            $this->errors[] = 'Description cannot exceed 65,535 characters';
        }
        
        if (!empty($deal->pipeline_notes_c) && strlen($deal->pipeline_notes_c) > 65535) {
            $this->errors[] = 'Pipeline notes cannot exceed 65,535 characters';
        }
        
        // GUID validations
        if (!empty($deal->account_id) && !$this->isValidGUID($deal->account_id)) {
            $this->errors[] = 'Invalid account ID format';
        }
        
        if (!empty($deal->assigned_user_id) && !$this->isValidGUID($deal->assigned_user_id)) {
            $this->errors[] = 'Invalid assigned user ID format';
        }
    }
    
    /**
     * Validate business rules
     */
    private function validateBusinessRules($deal)
    {
        // Pipeline stage validation
        if (!empty($deal->pipeline_stage_c)) {
            $validStages = array(
                'sourcing', 'screening', 'analysis_outreach', 'due_diligence',
                'valuation_structuring', 'loi_negotiation', 'financing', 'closing',
                'closed_owned_90_day', 'closed_owned_stable', 'unavailable'
            );
            
            if (!in_array($deal->pipeline_stage_c, $validStages)) {
                $this->errors[] = 'Invalid pipeline stage';
            }
        }
        
        // Deal source validation
        if (!empty($deal->deal_source_c)) {
            $validSources = array(
                'cold_call', 'existing_customer', 'self_generated', 'employee',
                'partner', 'public_relations', 'direct_mail', 'conference',
                'trade_show', 'web_site', 'word_of_mouth', 'email', 'campaign', 'other'
            );
            
            if (!in_array($deal->deal_source_c, $validSources)) {
                $this->errors[] = 'Invalid deal source';
            }
        }
        
        // Sales stage validation
        if (!empty($deal->sales_stage)) {
            $validSalesStages = array(
                'Prospecting', 'Qualification', 'Needs Analysis', 'Value Proposition',
                'Id. Decision Makers', 'Perception Analysis', 'Proposal/Price Quote',
                'Negotiation/Review', 'Closed Won', 'Closed Lost'
            );
            
            if (!in_array($deal->sales_stage, $validSalesStages)) {
                $this->errors[] = 'Invalid sales stage';
            }
        }
        
        // Probability and stage consistency
        if (!empty($deal->pipeline_stage_c) && !empty($deal->probability)) {
            $stageMinProbabilities = array(
                'sourcing' => 0,
                'screening' => 10,
                'analysis_outreach' => 20,
                'due_diligence' => 30,
                'valuation_structuring' => 50,
                'loi_negotiation' => 70,
                'financing' => 80,
                'closing' => 85,
                'closed_owned_90_day' => 100,
                'closed_owned_stable' => 100,
                'unavailable' => 0
            );
            
            $minProb = $stageMinProbabilities[$deal->pipeline_stage_c] ?? 0;
            if ((int)$deal->probability < $minProb) {
                $this->warnings[] = "Probability seems low for {$deal->pipeline_stage_c} stage (minimum recommended: {$minProb}%)";
            }
        }
        
        // Date consistency checks
        if (!empty($deal->date_closed) && !empty($deal->expected_close_date_c)) {
            $closeDate = strtotime($deal->date_closed);
            $expectedDate = strtotime($deal->expected_close_date_c);
            
            if (abs($closeDate - $expectedDate) > 86400 * 30) { // 30 days difference
                $this->warnings[] = 'Expected close date and close date differ by more than 30 days';
            }
        }
    }
    
    /**
     * Validate pipeline-specific rules
     */
    private function validatePipelineRules($deal)
    {
        global $db;
        
        // WIP limit validation
        if (!empty($deal->pipeline_stage_c) && !empty($deal->id)) {
            $wipLimits = array(
                'sourcing' => 20,
                'screening' => 15,
                'analysis_outreach' => 10,
                'due_diligence' => 8,
                'valuation_structuring' => 6,
                'loi_negotiation' => 5,
                'financing' => 5,
                'closing' => 5,
                'closed_owned_90_day' => 10
            );
            
            $limit = $wipLimits[$deal->pipeline_stage_c] ?? null;
            if ($limit) {
                $currentCount = $this->getCurrentStageCount($deal->pipeline_stage_c, $deal->id);
                if ($currentCount >= $limit) {
                    $this->warnings[] = "WIP limit for {$deal->pipeline_stage_c} stage is {$limit}, currently at {$currentCount}";
                }
            }
        }
        
        // Stage progression validation
        if (!empty($deal->pipeline_stage_c) && !empty($deal->fetched_row['pipeline_stage_c'])) {
            $oldStage = $deal->fetched_row['pipeline_stage_c'];
            $newStage = $deal->pipeline_stage_c;
            
            if ($oldStage !== $newStage) {
                $stageOrder = array(
                    'sourcing' => 1, 'screening' => 2, 'analysis_outreach' => 3,
                    'due_diligence' => 4, 'valuation_structuring' => 5, 'loi_negotiation' => 6,
                    'financing' => 7, 'closing' => 8, 'closed_owned_90_day' => 9,
                    'closed_owned_stable' => 10, 'unavailable' => 11
                );
                
                $oldOrder = $stageOrder[$oldStage] ?? 0;
                $newOrder = $stageOrder[$newStage] ?? 0;
                
                // Allow any movement but warn about unusual patterns
                if ($newOrder < $oldOrder && $newStage !== 'unavailable') {
                    $this->warnings[] = 'Deal is moving backward in the pipeline';
                }
            }
        }
    }
    
    /**
     * Validate relationships
     */
    private function validateRelationships($deal)
    {
        global $db;
        
        // Account validation
        if (!empty($deal->account_id)) {
            $accountQuery = "SELECT id, deleted FROM accounts WHERE id = '{$deal->account_id}'";
            $result = $db->query($accountQuery);
            $account = $db->fetchByAssoc($result);
            
            if (!$account) {
                $this->errors[] = 'Selected account does not exist';
            } elseif ($account['deleted'] == 1) {
                $this->errors[] = 'Selected account has been deleted';
            }
        }
        
        // Assigned user validation
        if (!empty($deal->assigned_user_id)) {
            $userQuery = "SELECT id, status, deleted FROM users WHERE id = '{$deal->assigned_user_id}'";
            $result = $db->query($userQuery);
            $user = $db->fetchByAssoc($result);
            
            if (!$user) {
                $this->errors[] = 'Assigned user does not exist';
            } elseif ($user['deleted'] == 1) {
                $this->errors[] = 'Assigned user has been deleted';
            } elseif ($user['status'] !== 'Active') {
                $this->warnings[] = 'Assigned user is not active';
            }
        }
        
        // Campaign validation (if set)
        if (!empty($deal->campaign_id)) {
            $campaignQuery = "SELECT id, deleted FROM campaigns WHERE id = '{$deal->campaign_id}'";
            $result = $db->query($campaignQuery);
            $campaign = $db->fetchByAssoc($result);
            
            if (!$campaign) {
                $this->errors[] = 'Selected campaign does not exist';
            } elseif ($campaign['deleted'] == 1) {
                $this->errors[] = 'Selected campaign has been deleted';
            }
        }
    }
    
    /**
     * Validate security constraints
     */
    private function validateSecurity($deal)
    {
        global $current_user;
        
        // Check if user has permission to save deals
        if (!ACLController::checkAccess('Deals', 'save', true)) {
            $this->errors[] = 'Insufficient permissions to save deals';
        }
        
        // Check assignment permissions
        if (!empty($deal->assigned_user_id) && $deal->assigned_user_id !== $current_user->id) {
            if (!ACLController::checkAccess('Users', 'access', true)) {
                $this->errors[] = 'Cannot assign deal to other users';
            }
        }
        
        // Validate input for potential security issues
        $securityFields = array('name', 'description', 'pipeline_notes_c');
        foreach ($securityFields as $field) {
            if (!empty($deal->$field)) {
                if ($this->containsSuspiciousContent($deal->$field)) {
                    $this->warnings[] = "Field '{$field}' contains potentially suspicious content";
                }
            }
        }
    }
    
    /**
     * Get current count of deals in a specific stage
     */
    private function getCurrentStageCount($stage, $excludeId = null)
    {
        global $db;
        
        $excludeClause = $excludeId ? "AND o.id != '{$excludeId}'" : '';
        $query = "SELECT COUNT(*) as count 
                  FROM opportunities o
                  JOIN opportunities_cstm oc ON o.id = oc.id_c
                  WHERE o.deleted = 0 
                  AND oc.pipeline_stage_c = '{$stage}'
                  AND o.sales_stage NOT IN ('Closed Won', 'Closed Lost')
                  {$excludeClause}";
        
        $result = $db->query($query);
        $row = $db->fetchByAssoc($result);
        
        return (int)($row['count'] ?? 0);
    }
    
    /**
     * Check if date is valid
     */
    private function isValidDate($date)
    {
        if (empty($date)) return false;
        
        $formats = array('Y-m-d', 'Y-m-d H:i:s', 'm/d/Y', 'd/m/Y', 'Y-m-d\TH:i:s');
        
        foreach ($formats as $format) {
            $d = DateTime::createFromFormat($format, $date);
            if ($d && $d->format($format) === $date) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if GUID is valid format
     */
    private function isValidGUID($guid)
    {
        return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $guid);
    }
    
    /**
     * Check for suspicious content
     */
    private function containsSuspiciousContent($content)
    {
        $suspiciousPatterns = array(
            '/<script[^>]*>.*?<\/script>/is',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe[^>]*>.*?<\/iframe>/is',
            '/eval\s*\(/i',
            '/expression\s*\(/i'
        );
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get validation errors
     */
    public function getErrors()
    {
        return $this->errors;
    }
    
    /**
     * Get validation warnings
     */
    public function getWarnings()
    {
        return $this->warnings;
    }
    
    /**
     * Check if validation passed
     */
    public function isValid()
    {
        return empty($this->errors);
    }
}