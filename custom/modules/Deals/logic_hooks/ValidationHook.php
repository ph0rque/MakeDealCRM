<?php
/**
 * Validation Logic Hook for Deals Module
 * Integrates comprehensive validation with SuiteCRM's logic hook system
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('custom/modules/Deals/validation/DealValidator.php');

class ValidationHook
{
    /**
     * Validate deal data before save
     */
    public function validateDealBeforeSave($bean, $event, $arguments)
    {
        try {
            // Skip validation for certain system operations
            if ($this->shouldSkipValidation($bean, $arguments)) {
                return;
            }
            
            $isNew = empty($bean->id) || empty($bean->fetched_row);
            
            // Create validator instance
            $validator = new DealValidator();
            $validationResult = $validator->validateDeal($bean, $isNew);
            
            // Handle validation results
            if (!$validationResult['valid']) {
                $errorMessage = 'Deal validation failed: ' . implode(', ', $validationResult['errors']);
                $GLOBALS['log']->error($errorMessage);
                
                // In SuiteCRM, we can't directly stop the save from a logic hook
                // Instead, we'll log the error and set a flag
                $bean->validation_errors = $validationResult['errors'];
                $bean->validation_warnings = $validationResult['warnings'];
                
                // For critical errors, we could throw an exception
                if ($this->hasCriticalErrors($validationResult['errors'])) {
                    throw new Exception($errorMessage);
                }
            }
            
            // Log warnings
            if (!empty($validationResult['warnings'])) {
                $warningMessage = 'Deal validation warnings: ' . implode(', ', $validationResult['warnings']);
                $GLOBALS['log']->warn($warningMessage);
                $bean->validation_warnings = $validationResult['warnings'];
            }
            
            // Log successful validation
            if ($validationResult['valid']) {
                $GLOBALS['log']->debug("Deal validation passed for: {$bean->id}");
            }
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('ValidationHook::validateDealBeforeSave failed: ' . $e->getMessage());
            
            // For critical system errors, re-throw
            if (strpos($e->getMessage(), 'validation failed') !== false) {
                throw $e;
            }
        }
    }
    
    /**
     * Check if validation should be skipped
     */
    private function shouldSkipValidation($bean, $arguments)
    {
        // Skip validation for certain system operations
        if (!empty($arguments['skip_validation'])) {
            return true;
        }
        
        // Skip for import operations (they have their own validation)
        if (!empty($GLOBALS['importing'])) {
            return true;
        }
        
        // Skip for mass updates (they should be pre-validated)
        if (!empty($arguments['mass_update'])) {
            return true;
        }
        
        // Skip for workflow-triggered saves to avoid recursion
        if (!empty($arguments['workflow_triggered'])) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if errors are critical enough to stop the save process
     */
    private function hasCriticalErrors($errors)
    {
        $criticalPatterns = array(
            'required',
            'security',
            'access denied',
            'invalid format',
            'exceeds maximum'
        );
        
        foreach ($errors as $error) {
            $errorLower = strtolower($error);
            foreach ($criticalPatterns as $pattern) {
                if (strpos($errorLower, $pattern) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Validate specific field updates (can be called from AJAX)
     */
    public function validateFieldUpdate($bean, $fieldName, $newValue)
    {
        try {
            $validator = new DealValidator();
            
            // Create a temporary bean with the new value for validation
            $tempBean = clone $bean;
            $tempBean->$fieldName = $newValue;
            
            $validationResult = $validator->validateDeal($tempBean, false);
            
            return array(
                'valid' => $validationResult['valid'],
                'errors' => $validationResult['errors'],
                'warnings' => $validationResult['warnings']
            );
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('Field validation failed: ' . $e->getMessage());
            return array(
                'valid' => false,
                'errors' => array('Validation system error'),
                'warnings' => array()
            );
        }
    }
    
    /**
     * Validate stage transition specifically
     */
    public function validateStageTransition($bean, $oldStage, $newStage)
    {
        try {
            // Create temporary bean with new stage
            $tempBean = clone $bean;
            $tempBean->pipeline_stage_c = $newStage;
            
            // Add the old stage to fetched_row for transition validation
            if (!isset($tempBean->fetched_row)) {
                $tempBean->fetched_row = array();
            }
            $tempBean->fetched_row['pipeline_stage_c'] = $oldStage;
            
            $validator = new DealValidator();
            $validationResult = $validator->validateDeal($tempBean, false);
            
            // Additional stage-specific validation
            $stageValidation = $this->validateStageSpecificRules($tempBean, $newStage);
            
            if (!$stageValidation['valid']) {
                $validationResult['valid'] = false;
                $validationResult['errors'] = array_merge(
                    $validationResult['errors'], 
                    $stageValidation['errors']
                );
                $validationResult['warnings'] = array_merge(
                    $validationResult['warnings'], 
                    $stageValidation['warnings']
                );
            }
            
            return $validationResult;
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('Stage transition validation failed: ' . $e->getMessage());
            return array(
                'valid' => false,
                'errors' => array('Stage transition validation error'),
                'warnings' => array()
            );
        }
    }
    
    /**
     * Validate stage-specific business rules
     */
    private function validateStageSpecificRules($bean, $newStage)
    {
        $errors = array();
        $warnings = array();
        
        // Stage-specific validation rules
        switch ($newStage) {
            case 'due_diligence':
                if (empty($bean->account_id)) {
                    $errors[] = 'Account must be specified for Due Diligence stage';
                }
                if (empty($bean->amount) || $bean->amount <= 0) {
                    $warnings[] = 'Deal amount should be specified for Due Diligence stage';
                }
                break;
                
            case 'valuation_structuring':
                if (empty($bean->amount) || $bean->amount <= 0) {
                    $errors[] = 'Deal amount is required for Valuation & Structuring stage';
                }
                if (empty($bean->date_closed)) {
                    $warnings[] = 'Expected close date should be set for Valuation & Structuring stage';
                }
                break;
                
            case 'loi_negotiation':
                if (empty($bean->amount) || $bean->amount <= 0) {
                    $errors[] = 'Deal amount is required for LOI/Negotiation stage';
                }
                if (empty($bean->probability) || $bean->probability < 70) {
                    $warnings[] = 'Probability should be at least 70% for LOI/Negotiation stage';
                }
                break;
                
            case 'financing':
            case 'closing':
                if (empty($bean->amount) || $bean->amount <= 0) {
                    $errors[] = 'Deal amount is required for ' . ucfirst(str_replace('_', ' ', $newStage)) . ' stage';
                }
                if (empty($bean->date_closed)) {
                    $errors[] = 'Expected close date is required for ' . ucfirst(str_replace('_', ' ', $newStage)) . ' stage';
                }
                if (empty($bean->probability) || $bean->probability < 80) {
                    $warnings[] = 'Probability should be at least 80% for ' . ucfirst(str_replace('_', ' ', $newStage)) . ' stage';
                }
                break;
                
            case 'closed_owned_90_day':
            case 'closed_owned_stable':
                if (empty($bean->amount) || $bean->amount <= 0) {
                    $errors[] = 'Deal amount is required for closed deals';
                }
                if ($bean->probability != 100) {
                    $bean->probability = 100; // Auto-correct
                    $warnings[] = 'Probability set to 100% for closed/won deal';
                }
                if ($bean->sales_stage !== 'Closed Won') {
                    $bean->sales_stage = 'Closed Won'; // Auto-correct
                    $warnings[] = 'Sales stage set to Closed Won';
                }
                break;
                
            case 'unavailable':
                if ($bean->probability != 0) {
                    $bean->probability = 0; // Auto-correct
                    $warnings[] = 'Probability set to 0% for unavailable deal';
                }
                if ($bean->sales_stage !== 'Closed Lost') {
                    $bean->sales_stage = 'Closed Lost'; // Auto-correct
                    $warnings[] = 'Sales stage set to Closed Lost';
                }
                break;
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        );
    }
    
    /**
     * Get validation summary for reporting
     */
    public function getValidationSummary($dealId)
    {
        try {
            global $db;
            
            // Get recent validation logs for this deal
            $query = "SELECT event_data, date_created 
                      FROM deals_workflow_log 
                      WHERE deal_id = '{$dealId}' 
                      AND event_type = 'validation_result'
                      ORDER BY date_created DESC 
                      LIMIT 10";
            
            $result = $db->query($query);
            $validations = array();
            
            while ($row = $db->fetchByAssoc($result)) {
                $data = json_decode($row['event_data'], true);
                $validations[] = array(
                    'date' => $row['date_created'],
                    'valid' => $data['valid'] ?? false,
                    'error_count' => count($data['errors'] ?? []),
                    'warning_count' => count($data['warnings'] ?? [])
                );
            }
            
            return $validations;
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('Failed to get validation summary: ' . $e->getMessage());
            return array();
        }
    }
}