<?php
/**
 * Stage Transition Service
 * 
 * Handles business logic for pipeline stage transitions including:
 * - Validation of allowed transitions
 * - Business rule enforcement
 * - WIP limit checking
 * - Transaction safety with rollback capability
 * - Comprehensive audit trail
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/MVC/Controller/SugarController.php');

class StageTransitionService
{
    private $db;
    private $auditLogger;
    private $validationRules;
    
    // Define stage transition rules
    private $allowedTransitions = [
        'sourcing' => ['screening', 'unavailable'],
        'screening' => ['sourcing', 'analysis_outreach', 'unavailable'],
        'analysis_outreach' => ['screening', 'due_diligence', 'unavailable'],
        'due_diligence' => ['analysis_outreach', 'valuation_structuring', 'unavailable'],
        'valuation_structuring' => ['due_diligence', 'loi_negotiation', 'unavailable'],
        'loi_negotiation' => ['valuation_structuring', 'financing', 'unavailable'],
        'financing' => ['loi_negotiation', 'closing', 'unavailable'],
        'closing' => ['financing', 'closed_owned_90_day', 'unavailable'],
        'closed_owned_90_day' => ['closing', 'closed_owned_stable', 'unavailable'],
        'closed_owned_stable' => ['closed_owned_90_day'], // Final stage - no exits
        'unavailable' => [] // Dead end - no transitions allowed
    ];
    
    // WIP limits by stage
    private $wipLimits = [
        'sourcing' => 20,
        'screening' => 15,
        'analysis_outreach' => 10,
        'due_diligence' => 8,
        'valuation_structuring' => 6,
        'loi_negotiation' => 5,
        'financing' => 5,
        'closing' => 5,
        'closed_owned_90_day' => 10,
        'closed_owned_stable' => null, // No limit
        'unavailable' => null // No limit
    ];
    
    // Business rules for stage transitions
    private $businessRules = [
        'due_diligence' => [
            'required_fields' => ['account_id', 'amount'],
            'minimum_amount' => 50000,
            'maximum_stage_duration' => 30 // days
        ],
        'valuation_structuring' => [
            'required_fields' => ['account_id', 'amount', 'probability'],
            'minimum_probability' => 25,
            'required_documents' => ['financial_statements', 'business_plan']
        ],
        'loi_negotiation' => [
            'required_fields' => ['account_id', 'amount', 'probability', 'expected_close_date_c'],
            'minimum_probability' => 50,
            'required_approvals' => ['manager_approval']
        ],
        'financing' => [
            'required_fields' => ['account_id', 'amount', 'probability', 'expected_close_date_c'],
            'minimum_probability' => 75,
            'required_approvals' => ['senior_manager_approval']
        ],
        'closing' => [
            'required_fields' => ['account_id', 'amount', 'probability', 'expected_close_date_c'],
            'minimum_probability' => 90,
            'required_approvals' => ['director_approval']
        ]
    ];
    
    public function __construct()
    {
        global $db;
        $this->db = $db;
        $this->auditLogger = new StageTransitionAuditLogger();
        $this->validationRules = new StageValidationRules();
    }
    
    /**
     * Validate and execute a stage transition
     */
    public function transitionDeal($dealId, $fromStage, $toStage, $userId, $options = [])
    {
        try {
            // Start transaction
            $this->db->query('START TRANSACTION');
            
            // Load deal
            $deal = BeanFactory::getBean('Opportunities', $dealId);
            if (!$deal || $deal->deleted) {
                throw new StageTransitionException("Deal not found: $dealId");
            }
            
            // Comprehensive validation
            $validationResult = $this->validateTransition($deal, $fromStage, $toStage, $userId, $options);
            if (!$validationResult['valid']) {
                throw new StageTransitionException($validationResult['message'], $validationResult['errors']);
            }
            
            // Check WIP limits
            if (!$this->checkWIPLimit($toStage, $dealId)) {
                throw new StageTransitionException("WIP limit exceeded for stage: $toStage");
            }
            
            // Execute the transition
            $transitionResult = $this->executeTransition($deal, $fromStage, $toStage, $userId, $options);
            
            // Commit transaction
            $this->db->query('COMMIT');
            
            // Log successful transition
            $this->auditLogger->logTransition($dealId, $fromStage, $toStage, $userId, 'success', $transitionResult);
            
            return [
                'success' => true,
                'deal_id' => $dealId,
                'old_stage' => $fromStage,
                'new_stage' => $toStage,
                'transition_id' => $transitionResult['transition_id'],
                'stage_entered_date' => $transitionResult['stage_entered_date'],
                'message' => 'Stage transition completed successfully'
            ];
            
        } catch (StageTransitionException $e) {
            // Rollback transaction
            $this->db->query('ROLLBACK');
            
            // Log failed transition
            $this->auditLogger->logTransition($dealId, $fromStage, $toStage, $userId, 'failed', [
                'error' => $e->getMessage(),
                'errors' => $e->getErrors()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'errors' => $e->getErrors(),
                'deal_id' => $dealId
            ];
            
        } catch (Exception $e) {
            // Rollback transaction
            $this->db->query('ROLLBACK');
            
            // Log system error
            $this->auditLogger->logTransition($dealId, $fromStage, $toStage, $userId, 'error', [
                'system_error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'System error during transition: ' . $e->getMessage(),
                'deal_id' => $dealId
            ];
        }
    }
    
    /**
     * Validate a proposed stage transition
     */
    public function validateTransition($deal, $fromStage, $toStage, $userId, $options = [])
    {
        $errors = [];
        
        // Check if transition is allowed
        if (!$this->isTransitionAllowed($fromStage, $toStage)) {
            $errors[] = "Transition from '$fromStage' to '$toStage' is not allowed";
        }
        
        // Check user permissions
        if (!$this->hasTransitionPermission($deal, $userId, $toStage)) {
            $errors[] = "User does not have permission to move deal to '$toStage'";
        }
        
        // Validate business rules for target stage
        $businessRuleErrors = $this->validateBusinessRules($deal, $toStage);
        $errors = array_merge($errors, $businessRuleErrors);
        
        // Check for any blocking conditions
        $blockingErrors = $this->checkBlockingConditions($deal, $fromStage, $toStage);
        $errors = array_merge($errors, $blockingErrors);
        
        // Validate stage-specific requirements
        $stageErrors = $this->validationRules->validateStageRequirements($deal, $toStage);
        $errors = array_merge($errors, $stageErrors);
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'message' => empty($errors) ? 'Validation passed' : 'Validation failed: ' . implode(', ', $errors)
        ];
    }
    
    /**
     * Check if a transition is allowed based on rules
     */
    public function isTransitionAllowed($fromStage, $toStage)
    {
        if (!isset($this->allowedTransitions[$fromStage])) {
            return false;
        }
        
        return in_array($toStage, $this->allowedTransitions[$fromStage]);
    }
    
    /**
     * Check WIP limits for target stage
     */
    private function checkWIPLimit($stage, $excludeDealId = null)
    {
        $limit = $this->wipLimits[$stage] ?? null;
        if ($limit === null) {
            return true; // No limit
        }
        
        $excludeClause = $excludeDealId ? "AND d.id != '{$excludeDealId}'" : '';
        
        $query = "SELECT COUNT(*) as count 
                  FROM opportunities d 
                  LEFT JOIN opportunities_cstm c ON d.id = c.id_c 
                  WHERE d.deleted = 0 
                  AND c.pipeline_stage_c = '{$stage}' 
                  {$excludeClause}";
        
        $result = $this->db->query($query);
        $row = $this->db->fetchByAssoc($result);
        $currentCount = intval($row['count']);
        
        return $currentCount < $limit;
    }
    
    /**
     * Check user permissions for transition
     */
    private function hasTransitionPermission($deal, $userId, $targetStage)
    {
        // Basic ACL check
        if (!$deal->ACLAccess('edit')) {
            return false;
        }
        
        // Stage-specific permission checks
        $restrictedStages = ['financing', 'closing', 'closed_owned_90_day'];
        if (in_array($targetStage, $restrictedStages)) {
            return $this->hasAdvancedStagePermission($userId, $targetStage);
        }
        
        return true;
    }
    
    /**
     * Check advanced permissions for restricted stages
     */
    private function hasAdvancedStagePermission($userId, $stage)
    {
        global $current_user;
        
        // Load user
        $user = BeanFactory::getBean('Users', $userId);
        if (!$user) {
            return false;
        }
        
        // Check role-based permissions
        $requiredRoles = [
            'financing' => ['Manager', 'Senior Manager', 'Director'],
            'closing' => ['Senior Manager', 'Director'],
            'closed_owned_90_day' => ['Manager', 'Senior Manager', 'Director']
        ];
        
        if (isset($requiredRoles[$stage])) {
            return $this->userHasRole($user, $requiredRoles[$stage]);
        }
        
        return true;
    }
    
    /**
     * Check if user has required role
     */
    private function userHasRole($user, $requiredRoles)
    {
        // This would integrate with SuiteCRM's role system
        // For now, check if user is admin or has manager privileges
        if ($user->is_admin) {
            return true;
        }
        
        // Check custom role field or user attributes
        $userRole = $user->title ?? '';
        foreach ($requiredRoles as $role) {
            if (stripos($userRole, $role) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Validate business rules for target stage
     */
    private function validateBusinessRules($deal, $targetStage)
    {
        $errors = [];
        
        if (!isset($this->businessRules[$targetStage])) {
            return $errors; // No rules for this stage
        }
        
        $rules = $this->businessRules[$targetStage];
        
        // Check required fields
        if (isset($rules['required_fields'])) {
            foreach ($rules['required_fields'] as $field) {
                if (empty($deal->$field)) {
                    $errors[] = "Required field '$field' is missing for stage '$targetStage'";
                }
            }
        }
        
        // Check minimum amount
        if (isset($rules['minimum_amount']) && $deal->amount < $rules['minimum_amount']) {
            $errors[] = "Deal amount must be at least {$rules['minimum_amount']} for stage '$targetStage'";
        }
        
        // Check minimum probability
        if (isset($rules['minimum_probability']) && $deal->probability < $rules['minimum_probability']) {
            $errors[] = "Deal probability must be at least {$rules['minimum_probability']}% for stage '$targetStage'";
        }
        
        // Check required approvals
        if (isset($rules['required_approvals'])) {
            $approvalErrors = $this->checkRequiredApprovals($deal, $rules['required_approvals']);
            $errors = array_merge($errors, $approvalErrors);
        }
        
        // Check maximum stage duration
        if (isset($rules['maximum_stage_duration'])) {
            $durationError = $this->checkStageDuration($deal, $rules['maximum_stage_duration']);
            if ($durationError) {
                $errors[] = $durationError;
            }
        }
        
        return $errors;
    }
    
    /**
     * Check required approvals
     */
    private function checkRequiredApprovals($deal, $requiredApprovals)
    {
        $errors = [];
        
        foreach ($requiredApprovals as $approval) {
            $approvalField = $approval . '_c';
            if (empty($deal->$approvalField) || $deal->$approvalField !== 'approved') {
                $errors[] = "Required approval '$approval' is missing or not approved";
            }
        }
        
        return $errors;
    }
    
    /**
     * Check stage duration limits
     */
    private function checkStageDuration($deal, $maxDuration)
    {
        if (empty($deal->stage_entered_date_c)) {
            return null; // No stage entry date to check
        }
        
        $stageEntered = new DateTime($deal->stage_entered_date_c);
        $now = new DateTime();
        $daysInStage = $now->diff($stageEntered)->days;
        
        if ($daysInStage > $maxDuration) {
            return "Deal has been in current stage for $daysInStage days, exceeding maximum of $maxDuration days";
        }
        
        return null;
    }
    
    /**
     * Check for blocking conditions
     */
    private function checkBlockingConditions($deal, $fromStage, $toStage)
    {
        $errors = [];
        
        // Check for deal locks
        if ($this->isDealLocked($deal->id)) {
            $errors[] = "Deal is currently locked and cannot be moved";
        }
        
        // Check for pending workflows
        if ($this->hasPendingWorkflows($deal->id)) {
            $errors[] = "Deal has pending workflows that must complete before stage change";
        }
        
        // Check for required stage completion
        if (!$this->isStageCompleted($deal, $fromStage)) {
            $errors[] = "Current stage requirements not completed";
        }
        
        return $errors;
    }
    
    /**
     * Check if deal is locked
     */
    private function isDealLocked($dealId)
    {
        $query = "SELECT COUNT(*) as count FROM deal_locks 
                  WHERE deal_id = '$dealId' 
                  AND lock_expires > NOW() 
                  AND deleted = 0";
        
        if ($this->tableExists('deal_locks')) {
            $result = $this->db->query($query);
            $row = $this->db->fetchByAssoc($result);
            return intval($row['count']) > 0;
        }
        
        return false;
    }
    
    /**
     * Check for pending workflows
     */
    private function hasPendingWorkflows($dealId)
    {
        $query = "SELECT COUNT(*) as count FROM workflow_instances 
                  WHERE target_id = '$dealId' 
                  AND status IN ('pending', 'running') 
                  AND deleted = 0";
        
        if ($this->tableExists('workflow_instances')) {
            $result = $this->db->query($query);
            $row = $this->db->fetchByAssoc($result);
            return intval($row['count']) > 0;
        }
        
        return false;
    }
    
    /**
     * Check if current stage is completed
     */
    private function isStageCompleted($deal, $stage)
    {
        // This would check stage-specific completion criteria
        // For now, return true as default
        return true;
    }
    
    /**
     * Execute the actual transition
     */
    private function executeTransition($deal, $fromStage, $toStage, $userId, $options)
    {
        $transitionId = create_guid();
        $stageEnteredDate = date('Y-m-d H:i:s');
        
        // Update deal record
        $deal->pipeline_stage_c = $toStage;
        $deal->stage_entered_date_c = $stageEnteredDate;
        
        // Map to sales stage
        $salesStageMapping = $this->getPipelineToSalesStageMapping();
        if (isset($salesStageMapping[$toStage])) {
            $deal->sales_stage = $salesStageMapping[$toStage];
        }
        
        // Update position if provided
        if (isset($options['position'])) {
            $deal->position_c = intval($options['position']);
        }
        
        // Save deal
        $deal->save();
        
        // Create detailed transition record
        $this->createTransitionRecord($transitionId, $deal->id, $fromStage, $toStage, $userId, $options);
        
        return [
            'transition_id' => $transitionId,
            'stage_entered_date' => $stageEnteredDate,
            'updated_fields' => [
                'pipeline_stage_c' => $toStage,
                'stage_entered_date_c' => $stageEnteredDate,
                'sales_stage' => $deal->sales_stage
            ]
        ];
    }
    
    /**
     * Create detailed transition record
     */
    private function createTransitionRecord($transitionId, $dealId, $fromStage, $toStage, $userId, $options)
    {
        $query = "INSERT INTO pipeline_transitions 
                  (id, deal_id, from_stage, to_stage, user_id, transition_date, 
                   transition_reason, metadata, created_by, date_entered) 
                  VALUES 
                  (?, ?, ?, ?, ?, NOW(), ?, ?, ?, NOW())";
        
        $metadata = json_encode([
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'session_id' => session_id(),
            'options' => $options
        ]);
        
        $reason = $options['reason'] ?? 'Manual stage transition';
        
        if ($this->tableExists('pipeline_transitions')) {
            $this->db->pQuery($query, [
                $transitionId,
                $dealId,
                $fromStage,
                $toStage,
                $userId,
                $reason,
                $metadata,
                $userId
            ]);
        }
    }
    
    /**
     * Get pipeline to sales stage mapping
     */
    private function getPipelineToSalesStageMapping()
    {
        return [
            'sourcing' => 'Prospecting',
            'screening' => 'Qualification',
            'analysis_outreach' => 'Needs Analysis',
            'due_diligence' => 'Id. Decision Makers',
            'valuation_structuring' => 'Value Proposition',
            'loi_negotiation' => 'Negotiation/Review',
            'financing' => 'Proposal/Price Quote',
            'closing' => 'Negotiation/Review',
            'closed_owned_90_day' => 'Closed Won',
            'closed_owned_stable' => 'Closed Won',
            'unavailable' => 'Closed Lost'
        ];
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
     * Get allowed transitions for a stage
     */
    public function getAllowedTransitions($fromStage)
    {
        return $this->allowedTransitions[$fromStage] ?? [];
    }
    
    /**
     * Get WIP limit for a stage
     */
    public function getWIPLimit($stage)
    {
        return $this->wipLimits[$stage] ?? null;
    }
    
    /**
     * Get current WIP count for a stage
     */
    public function getCurrentWIPCount($stage)
    {
        $query = "SELECT COUNT(*) as count 
                  FROM opportunities d 
                  LEFT JOIN opportunities_cstm c ON d.id = c.id_c 
                  WHERE d.deleted = 0 
                  AND c.pipeline_stage_c = '{$stage}'";
        
        $result = $this->db->query($query);
        $row = $this->db->fetchByAssoc($result);
        return intval($row['count']);
    }
    
    /**
     * Get business rules for a stage
     */
    public function getBusinessRules($stage)
    {
        return $this->businessRules[$stage] ?? [];
    }
}

/**
 * Custom exception for stage transition errors
 */
class StageTransitionException extends Exception
{
    private $errors;
    
    public function __construct($message, $errors = [], $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }
    
    public function getErrors()
    {
        return $this->errors;
    }
}

/**
 * Stage validation rules class
 */
class StageValidationRules
{
    /**
     * Validate stage-specific requirements
     */
    public function validateStageRequirements($deal, $stage)
    {
        $errors = [];
        
        switch ($stage) {
            case 'due_diligence':
                $errors = array_merge($errors, $this->validateDueDiligenceRequirements($deal));
                break;
            case 'valuation_structuring':
                $errors = array_merge($errors, $this->validateValuationRequirements($deal));
                break;
            case 'loi_negotiation':
                $errors = array_merge($errors, $this->validateLOIRequirements($deal));
                break;
            case 'financing':
                $errors = array_merge($errors, $this->validateFinancingRequirements($deal));
                break;
            case 'closing':
                $errors = array_merge($errors, $this->validateClosingRequirements($deal));
                break;
        }
        
        return $errors;
    }
    
    private function validateDueDiligenceRequirements($deal)
    {
        $errors = [];
        
        if (empty($deal->description) || strlen($deal->description) < 50) {
            $errors[] = "Deal description must be at least 50 characters for Due Diligence stage";
        }
        
        return $errors;
    }
    
    private function validateValuationRequirements($deal)
    {
        $errors = [];
        
        if (empty($deal->expected_close_date_c)) {
            $errors[] = "Expected close date is required for Valuation & Structuring stage";
        }
        
        return $errors;
    }
    
    private function validateLOIRequirements($deal)
    {
        $errors = [];
        
        if (empty($deal->probability) || $deal->probability < 50) {
            $errors[] = "Deal probability must be at least 50% for LOI/Negotiation stage";
        }
        
        return $errors;
    }
    
    private function validateFinancingRequirements($deal)
    {
        $errors = [];
        
        if (empty($deal->probability) || $deal->probability < 75) {
            $errors[] = "Deal probability must be at least 75% for Financing stage";
        }
        
        return $errors;
    }
    
    private function validateClosingRequirements($deal)
    {
        $errors = [];
        
        if (empty($deal->probability) || $deal->probability < 90) {
            $errors[] = "Deal probability must be at least 90% for Closing stage";
        }
        
        if (empty($deal->expected_close_date_c)) {
            $errors[] = "Expected close date is required for Closing stage";
        } else {
            $closeDate = new DateTime($deal->expected_close_date_c);
            $now = new DateTime();
            if ($closeDate < $now) {
                $errors[] = "Expected close date cannot be in the past for Closing stage";
            }
        }
        
        return $errors;
    }
}

/**
 * Audit logger for stage transitions
 */
class StageTransitionAuditLogger
{
    private $db;
    
    public function __construct()
    {
        global $db;
        $this->db = $db;
    }
    
    /**
     * Log a stage transition
     */
    public function logTransition($dealId, $fromStage, $toStage, $userId, $status, $details)
    {
        $logId = create_guid();
        
        $query = "INSERT INTO pipeline_transition_audit 
                  (id, deal_id, from_stage, to_stage, user_id, status, details, 
                   ip_address, user_agent, session_id, timestamp, date_entered) 
                  VALUES 
                  (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $detailsJson = json_encode($details);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $sessionId = session_id();
        
        if ($this->tableExists('pipeline_transition_audit')) {
            $this->db->pQuery($query, [
                $logId,
                $dealId,
                $fromStage,
                $toStage,
                $userId,
                $status,
                $detailsJson,
                $ipAddress,
                $userAgent,
                $sessionId
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