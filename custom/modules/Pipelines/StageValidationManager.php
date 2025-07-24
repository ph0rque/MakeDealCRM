<?php
/**
 * Stage Validation Manager for Pipeline Business Logic
 * Handles stage-specific validation rules and requirements
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

class StageValidationManager
{
    protected $db;
    protected $validationRules;
    
    public function __construct()
    {
        global $db;
        $this->db = $db;
        $this->loadValidationRules();
    }
    
    /**
     * Load validation rules for each pipeline stage
     */
    protected function loadValidationRules()
    {
        $this->validationRules = [
            'sourcing' => [
                'required_fields' => [
                    'deal_source' => 'Deal source must be specified',
                    'company_name' => 'Company name is required',
                    'industry' => 'Industry classification is required',
                    'contact_person' => 'Primary contact person is required'
                ],
                'field_validations' => [
                    'deal_value' => ['min' => 1000000, 'message' => 'Deal value must be at least $1M'],
                    'company_name' => ['min_length' => 2, 'message' => 'Company name must be at least 2 characters']
                ],
                'business_rules' => [
                    'check_duplicate_company',
                    'validate_deal_source'
                ]
            ],
            
            'screening' => [
                'required_fields' => [
                    'annual_revenue' => 'Annual revenue is required for screening',
                    'employee_count' => 'Employee count is required',
                    'geographic_focus' => 'Geographic focus must be defined',
                    'business_model' => 'Business model description is required'
                ],
                'field_validations' => [
                    'annual_revenue' => ['min' => 500000, 'message' => 'Annual revenue must be at least $500K'],
                    'employee_count' => ['min' => 5, 'message' => 'Must have at least 5 employees'],
                    'deal_value' => ['max_multiple' => 10, 'base_field' => 'annual_revenue', 'message' => 'Deal value cannot exceed 10x annual revenue']
                ],
                'business_rules' => [
                    'check_financial_ratios',
                    'validate_market_size',
                    'assess_competitive_position'
                ],
                'checklist_requirements' => [
                    'financial_statements_reviewed' => 'Financial statements must be reviewed',
                    'market_research_completed' => 'Market research must be completed',
                    'management_team_assessed' => 'Management team assessment required'
                ]
            ],
            
            'analysis_outreach' => [
                'required_fields' => [
                    'primary_contact' => 'Primary contact must be identified',
                    'decision_maker' => 'Decision maker must be identified',
                    'key_stakeholders' => 'Key stakeholders must be mapped'
                ],
                'relationship_requirements' => [
                    'contacts_count' => ['min' => 2, 'message' => 'At least 2 contacts required'],
                    'decision_maker_contact' => ['required' => true, 'message' => 'Must have contact with decision maker']
                ],
                'business_rules' => [
                    'validate_stakeholder_mapping',
                    'check_initial_interest',
                    'assess_accessibility'
                ],
                'activity_requirements' => [
                    'initial_call_completed' => 'Initial call must be completed',
                    'nda_signed' => 'NDA must be signed before proceeding'
                ]
            ],
            
            'term_sheet' => [
                'required_fields' => [
                    'valuation_range' => 'Valuation range must be established',
                    'deal_structure' => 'Deal structure must be defined',
                    'key_terms' => 'Key terms must be outlined',
                    'financing_source' => 'Financing source must be confirmed'
                ],
                'field_validations' => [
                    'valuation_range' => ['format' => 'range', 'message' => 'Valuation must be in range format (e.g., $10M-$15M)'],
                    'deal_value' => ['within_range' => 'valuation_range', 'message' => 'Deal value must be within valuation range']
                ],
                'business_rules' => [
                    'validate_valuation_methodology',
                    'check_deal_structure_feasibility',
                    'verify_financing_capacity'
                ],
                'approval_requirements' => [
                    'investment_committee_review' => 'Investment committee review required',
                    'risk_assessment_completed' => 'Risk assessment must be completed'
                ]
            ],
            
            'due_diligence' => [
                'required_fields' => [
                    'dd_checklist' => 'Due diligence checklist must be created',
                    'external_advisors' => 'External advisors must be engaged',
                    'data_room_access' => 'Data room access must be confirmed',
                    'timeline_agreed' => 'DD timeline must be agreed'
                ],
                'checklist_requirements' => [
                    'financial_dd_started' => 'Financial due diligence must be initiated',
                    'legal_dd_started' => 'Legal due diligence must be initiated',
                    'commercial_dd_started' => 'Commercial due diligence must be initiated',
                    'management_presentations' => 'Management presentations must be scheduled'
                ],
                'business_rules' => [
                    'validate_dd_scope',
                    'check_advisor_qualifications',
                    'verify_information_access'
                ],
                'timeline_requirements' => [
                    'max_duration_days' => 90,
                    'milestone_tracking' => true
                ]
            ],
            
            'final_negotiation' => [
                'required_fields' => [
                    'final_terms' => 'Final terms must be negotiated',
                    'closing_conditions' => 'Closing conditions must be defined',
                    'timeline' => 'Closing timeline must be established',
                    'purchase_agreement' => 'Purchase agreement must be drafted'
                ],
                'approval_requirements' => [
                    'investment_committee_approval' => 'Final investment committee approval required',
                    'legal_review_completed' => 'Legal review must be completed',
                    'regulatory_clearance' => 'Regulatory clearance must be obtained'
                ],
                'business_rules' => [
                    'validate_final_terms',
                    'check_closing_conditions',
                    'verify_regulatory_requirements'
                ]
            ],
            
            'closing' => [
                'required_fields' => [
                    'closing_date' => 'Closing date must be set',
                    'funding_confirmed' => 'Funding must be confirmed',
                    'all_approvals' => 'All approvals must be obtained',
                    'escrow_instructions' => 'Escrow instructions must be prepared'
                ],
                'checklist_requirements' => [
                    'all_signatures_obtained' => 'All required signatures must be obtained',
                    'funds_transferred' => 'Funds must be transferred',
                    'closing_documents_executed' => 'All closing documents must be executed'
                ],
                'business_rules' => [
                    'validate_closing_readiness',
                    'check_all_conditions_met',
                    'verify_funds_availability'
                ]
            ]
        ];
    }
    
    /**
     * Validate deal for stage progression
     */
    public function validateDealForStage($deal, $targetStage)
    {
        $validation = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'missing_requirements' => [],
            'score' => 100
        ];
        
        if (!isset($this->validationRules[$targetStage])) {
            $validation['valid'] = false;
            $validation['errors'][] = "Unknown stage: {$targetStage}";
            return $validation;
        }
        
        $rules = $this->validationRules[$targetStage];
        
        // Check required fields
        $this->validateRequiredFields($deal, $rules, $validation);
        
        // Check field validations
        $this->validateFieldRules($deal, $rules, $validation);
        
        // Check business rules
        $this->validateBusinessRules($deal, $rules, $validation);
        
        // Check checklist requirements
        $this->validateChecklistRequirements($deal, $rules, $validation);
        
        // Check relationship requirements
        $this->validateRelationshipRequirements($deal, $rules, $validation);
        
        // Check approval requirements
        $this->validateApprovalRequirements($deal, $rules, $validation);
        
        // Check activity requirements
        $this->validateActivityRequirements($deal, $rules, $validation);
        
        // Calculate validation score
        $validation['score'] = $this->calculateValidationScore($validation);
        
        return $validation;
    }
    
    /**
     * Validate required fields
     */
    protected function validateRequiredFields($deal, $rules, &$validation)
    {
        $requiredFields = $rules['required_fields'] ?? [];
        
        foreach ($requiredFields as $field => $message) {
            if (empty($deal->$field)) {
                $validation['valid'] = false;
                $validation['errors'][] = $message;
                $validation['missing_requirements'][] = $field;
            }
        }
    }
    
    /**
     * Validate field-specific rules
     */
    protected function validateFieldRules($deal, $rules, &$validation)
    {
        $fieldValidations = $rules['field_validations'] ?? [];
        
        foreach ($fieldValidations as $field => $fieldRules) {
            $value = $deal->$field ?? null;
            
            if (isset($fieldRules['min']) && $value < $fieldRules['min']) {
                $validation['errors'][] = $fieldRules['message'];
            }
            
            if (isset($fieldRules['max']) && $value > $fieldRules['max']) {
                $validation['errors'][] = $fieldRules['message'];
            }
            
            if (isset($fieldRules['min_length']) && strlen($value) < $fieldRules['min_length']) {
                $validation['errors'][] = $fieldRules['message'];
            }
            
            if (isset($fieldRules['max_multiple'])) {
                $baseField = $fieldRules['base_field'];
                $baseValue = $deal->$baseField ?? 0;
                if ($baseValue > 0 && $value > ($baseValue * $fieldRules['max_multiple'])) {
                    $validation['errors'][] = $fieldRules['message'];
                }
            }
            
            if (isset($fieldRules['format']) && $fieldRules['format'] === 'range') {
                if (!preg_match('/\$?[\d,]+\s*-\s*\$?[\d,]+/', $value)) {
                    $validation['errors'][] = $fieldRules['message'];
                }
            }
        }
    }
    
    /**
     * Validate business rules
     */
    protected function validateBusinessRules($deal, $rules, &$validation)
    {
        $businessRules = $rules['business_rules'] ?? [];
        
        foreach ($businessRules as $rule) {
            $result = $this->executeBusinessRule($rule, $deal);
            
            if (!$result['valid']) {
                if ($result['severity'] === 'error') {
                    $validation['valid'] = false;
                    $validation['errors'][] = $result['message'];
                } else {
                    $validation['warnings'][] = $result['message'];
                }
            }
        }
    }
    
    /**
     * Execute specific business rule
     */
    protected function executeBusinessRule($rule, $deal)
    {
        switch ($rule) {
            case 'check_duplicate_company':
                return $this->checkDuplicateCompany($deal);
                
            case 'validate_deal_source':
                return $this->validateDealSource($deal);
                
            case 'check_financial_ratios':
                return $this->checkFinancialRatios($deal);
                
            case 'validate_market_size':
                return $this->validateMarketSize($deal);
                
            case 'assess_competitive_position':
                return $this->assessCompetitivePosition($deal);
                
            case 'validate_stakeholder_mapping':
                return $this->validateStakeholderMapping($deal);
                
            case 'check_initial_interest':
                return $this->checkInitialInterest($deal);
                
            case 'assess_accessibility':
                return $this->assessAccessibility($deal);
                
            case 'validate_valuation_methodology':
                return $this->validateValuationMethodology($deal);
                
            case 'check_deal_structure_feasibility':
                return $this->checkDealStructureFeasibility($deal);
                
            case 'verify_financing_capacity':
                return $this->verifyFinancingCapacity($deal);
                
            default:
                return ['valid' => true, 'message' => 'Rule not implemented'];
        }
    }
    
    /**
     * Check for duplicate company in pipeline
     */
    protected function checkDuplicateCompany($deal)
    {
        $query = "SELECT COUNT(*) as count FROM mdeal_deals 
                  WHERE company_name = ? AND id != ? AND deleted = 0 
                  AND stage NOT IN ('closed_won', 'closed_lost', 'unavailable')";
        
        $result = $this->db->pQuery($query, [$deal->company_name, $deal->id ?? '']);
        $row = $this->db->fetchByAssoc($result);
        
        if (($row['count'] ?? 0) > 0) {
            return [
                'valid' => false,
                'severity' => 'warning',
                'message' => 'Company already exists in active pipeline'
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Validate deal source
     */
    protected function validateDealSource($deal)
    {
        $validSources = ['referral', 'proprietary', 'broker', 'auction', 'inbound'];
        
        if (!in_array($deal->deal_source, $validSources)) {
            return [
                'valid' => false,
                'severity' => 'error',
                'message' => 'Invalid deal source specified'
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Check financial ratios
     */
    protected function checkFinancialRatios($deal)
    {
        $revenue = $deal->annual_revenue ?? 0;
        $ebitda = $deal->ebitda ?? 0;
        
        if ($revenue > 0 && $ebitda > 0) {
            $ebitdaMargin = ($ebitda / $revenue) * 100;
            
            if ($ebitdaMargin < 5) {
                return [
                    'valid' => false,
                    'severity' => 'warning',
                    'message' => 'EBITDA margin is below 5% - proceed with caution'
                ];
            }
        }
        
        return ['valid' => true];
    }
    
    /**
     * Validate market size
     */
    protected function validateMarketSize($deal)
    {
        $marketSize = $deal->market_size ?? 0;
        
        if ($marketSize < 100000000) { // $100M minimum market
            return [
                'valid' => false,
                'severity' => 'warning',
                'message' => 'Market size may be too small for target returns'
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Assess competitive position
     */
    protected function assessCompetitivePosition($deal)
    {
        $competitivePosition = $deal->competitive_position ?? '';
        
        if (in_array($competitivePosition, ['weak', 'poor'])) {
            return [
                'valid' => false,
                'severity' => 'warning',
                'message' => 'Weak competitive position may impact returns'
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Validate stakeholder mapping
     */
    protected function validateStakeholderMapping($deal)
    {
        // Check if we have contacts for key stakeholders
        $query = "SELECT COUNT(*) as count FROM mdeal_contacts 
                  WHERE account_id = ? AND deleted = 0 
                  AND decision_role IN ('decision_maker', 'financial_approver')";
        
        $result = $this->db->pQuery($query, [$deal->account_id ?? '']);
        $row = $this->db->fetchByAssoc($result);
        
        if (($row['count'] ?? 0) < 1) {
            return [
                'valid' => false,
                'severity' => 'error',
                'message' => 'Must have contact with decision maker or financial approver'
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Check initial interest
     */
    protected function checkInitialInterest($deal)
    {
        $interestLevel = $deal->interest_level ?? 'unknown';
        
        if (in_array($interestLevel, ['none', 'low'])) {
            return [
                'valid' => false,
                'severity' => 'warning',
                'message' => 'Low interest level - consider alternative approach'
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Assess accessibility
     */
    protected function assessAccessibility($deal)
    {
        $accessibility = $deal->accessibility_rating ?? 'unknown';
        
        if ($accessibility === 'difficult') {
            return [
                'valid' => false,
                'severity' => 'warning',
                'message' => 'Difficult accessibility may impact timeline'
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Validate valuation methodology
     */
    protected function validateValuationMethodology($deal)
    {
        $methodology = $deal->valuation_methodology ?? '';
        
        if (empty($methodology)) {
            return [
                'valid' => false,
                'severity' => 'error',
                'message' => 'Valuation methodology must be documented'
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Check deal structure feasibility
     */
    protected function checkDealStructureFeasibility($deal)
    {
        $structure = $deal->deal_structure ?? '';
        
        if (empty($structure)) {
            return [
                'valid' => false,
                'severity' => 'error',
                'message' => 'Deal structure must be defined'
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Verify financing capacity
     */
    protected function verifyFinancingCapacity($deal)
    {
        $dealValue = $deal->deal_value ?? 0;
        $financingSource = $deal->financing_source ?? '';
        
        if ($dealValue > 50000000 && empty($financing_source)) {
            return [
                'valid' => false,
                'severity' => 'error',
                'message' => 'Large deals require confirmed financing source'
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Validate checklist requirements
     */
    protected function validateChecklistRequirements($deal, $rules, &$validation)
    {
        $checklistReqs = $rules['checklist_requirements'] ?? [];
        
        foreach ($checklistReqs as $requirement => $message) {
            $completed = $this->isChecklistItemCompleted($deal->id, $requirement);
            
            if (!$completed) {
                $validation['warnings'][] = $message;
                $validation['missing_requirements'][] = $requirement;
            }
        }
    }
    
    /**
     * Validate relationship requirements
     */
    protected function validateRelationshipRequirements($deal, $rules, &$validation)
    {
        $relationshipReqs = $rules['relationship_requirements'] ?? [];
        
        if (isset($relationshipReqs['contacts_count'])) {
            $minContacts = $relationshipReqs['contacts_count']['min'];
            $contactCount = $this->getRelatedContactCount($deal->account_id ?? '');
            
            if ($contactCount < $minContacts) {
                $validation['errors'][] = $relationshipReqs['contacts_count']['message'];
            }
        }
        
        if (isset($relationshipReqs['decision_maker_contact']) && $relationshipReqs['decision_maker_contact']['required']) {
            $hasDecisionMaker = $this->hasDecisionMakerContact($deal->account_id ?? '');
            
            if (!$hasDecisionMaker) {
                $validation['errors'][] = $relationshipReqs['decision_maker_contact']['message'];
            }
        }
    }
    
    /**
     * Validate approval requirements
     */
    protected function validateApprovalRequirements($deal, $rules, &$validation)
    {
        $approvalReqs = $rules['approval_requirements'] ?? [];
        
        foreach ($approvalReqs as $approval => $message) {
            $approved = $this->isApprovalObtained($deal->id, $approval);
            
            if (!$approved) {
                $validation['errors'][] = $message;
                $validation['missing_requirements'][] = $approval;
            }
        }
    }
    
    /**
     * Validate activity requirements
     */
    protected function validateActivityRequirements($deal, $rules, &$validation)
    {
        $activityReqs = $rules['activity_requirements'] ?? [];
        
        foreach ($activityReqs as $activity => $message) {
            $completed = $this->isActivityCompleted($deal->id, $activity);
            
            if (!$completed) {
                $validation['warnings'][] = $message;
                $validation['missing_requirements'][] = $activity;
            }
        }
    }
    
    /**
     * Check if checklist item is completed
     */
    protected function isChecklistItemCompleted($dealId, $item)
    {
        // This would check against a checklist table
        // For now, return false to indicate not implemented
        return false;
    }
    
    /**
     * Get count of related contacts
     */
    protected function getRelatedContactCount($accountId)
    {
        if (empty($accountId)) {
            return 0;
        }
        
        $query = "SELECT COUNT(*) as count FROM mdeal_contacts 
                  WHERE account_id = ? AND deleted = 0";
        
        $result = $this->db->pQuery($query, [$accountId]);
        $row = $this->db->fetchByAssoc($result);
        
        return $row['count'] ?? 0;
    }
    
    /**
     * Check if we have decision maker contact
     */
    protected function hasDecisionMakerContact($accountId)
    {
        if (empty($accountId)) {
            return false;
        }
        
        $query = "SELECT COUNT(*) as count FROM mdeal_contacts 
                  WHERE account_id = ? AND deleted = 0 
                  AND decision_role = 'decision_maker'";
        
        $result = $this->db->pQuery($query, [$accountId]);
        $row = $this->db->fetchByAssoc($result);
        
        return ($row['count'] ?? 0) > 0;
    }
    
    /**
     * Check if approval is obtained
     */
    protected function isApprovalObtained($dealId, $approvalType)
    {
        // This would check against an approvals table
        // For now, return false to indicate not implemented
        return false;
    }
    
    /**
     * Check if activity is completed
     */
    protected function isActivityCompleted($dealId, $activityType)
    {
        // This would check against activities/tasks
        // For now, return false to indicate not implemented
        return false;
    }
    
    /**
     * Calculate validation score
     */
    protected function calculateValidationScore($validation)
    {
        $score = 100;
        $score -= count($validation['errors']) * 20; // Major deduction for errors
        $score -= count($validation['warnings']) * 5; // Minor deduction for warnings
        
        return max(0, $score);
    }
    
    /**
     * Get validation requirements for a stage
     */
    public function getStageRequirements($stage)
    {
        return $this->validationRules[$stage] ?? [];
    }
    
    /**
     * Get all available validation rules
     */
    public function getAllValidationRules()
    {
        return $this->validationRules;
    }
}