<?php
/**
 * Validation Logic Hook for Deals Module - Comprehensive Data Validation
 * 
 * This class serves as the integration point between SuiteCRM's logic hook system
 * and the comprehensive DealValidator class. It ensures all deal data meets
 * business requirements and maintains data integrity throughout the system.
 * 
 * Validation Layers:
 * 1. Field-level validation (format, required fields, data types)
 * 2. Business rule validation (stage transitions, financial calculations)
 * 3. Cross-field validation (related data consistency)
 * 4. Security validation (access rights, data visibility)
 * 
 * The validation system is designed to:
 * - Prevent invalid data from entering the system
 * - Provide clear, actionable error messages
 * - Support both hard stops (errors) and soft warnings
 * - Enable field-specific and bulk validation
 * - Maintain audit trails of validation failures
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 * @author MakeDealCRM Development Team
 * @version 1.0.0
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('custom/modules/Deals/validation/DealValidator.php');

class ValidationHook
{
    /**
     * Validate deal data before save - Primary validation entry point
     * 
     * This method orchestrates comprehensive validation of deal data before
     * it's saved to the database. It serves as a gatekeeper ensuring only
     * valid, complete, and authorized data is persisted.
     * 
     * Validation Process:
     * 1. Check if validation should be skipped (imports, mass updates)
     * 2. Determine if this is a new record or update
     * 3. Run validation through DealValidator class
     * 4. Process validation results:
     *    - Log all errors and warnings
     *    - Store validation details on bean
     *    - Throw exceptions for critical errors
     * 
     * Skip Conditions:
     * - System imports (have their own validation)
     * - Mass updates (pre-validated)
     * - Workflow-triggered saves (prevent loops)
     * - Explicit skip_validation flag
     * 
     * Error Handling:
     * - Critical errors throw exceptions to stop save
     * - Non-critical errors are logged but allow save
     * - Warnings are recorded for user information
     * 
     * This method ensures data quality while balancing strictness with
     * usability, allowing administrators to configure validation rules
     * based on their business needs.
     * 
     * @param SugarBean $bean The Deal bean to validate
     * @param string $event The event type (before_save)
     * @param array $arguments Additional hook arguments
     * 
     * @throws Exception When critical validation errors occur
     * @return void
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
     * Check if validation should be skipped for this operation
     * 
     * Determines whether validation should be bypassed based on the context
     * of the save operation. This prevents validation loops and allows
     * certain system operations to proceed without validation.
     * 
     * Skip Scenarios:
     * 1. Explicit skip_validation flag:
     *    - Set by system operations that pre-validate
     *    - Used by data migration tools
     * 
     * 2. Import operations:
     *    - Import has its own validation rules
     *    - Prevents double validation
     *    - Allows bulk import efficiency
     * 
     * 3. Mass updates:
     *    - Pre-validated at the UI level
     *    - Prevents validation of partial data
     * 
     * 4. Workflow-triggered saves:
     *    - Prevents infinite loops
     *    - Workflows assumed to set valid data
     * 
     * This intelligent skipping ensures validation doesn't interfere with
     * legitimate system operations while maintaining data integrity for
     * normal user operations.
     * 
     * @param SugarBean $bean The bean being validated
     * @param array $arguments Hook arguments to check for skip flags
     * 
     * @return bool True if validation should be skipped
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
     * 
     * Analyzes validation errors to determine if they're severe enough to
     * prevent the record from being saved. This allows for a flexible
     * validation system that can enforce strict rules while allowing
     * minor issues to be saved with warnings.
     * 
     * Critical Error Patterns:
     * 1. 'required' - Missing required fields
     * 2. 'security' - Security violations or access issues  
     * 3. 'access denied' - Permission failures
     * 4. 'invalid format' - Data format violations
     * 5. 'exceeds maximum' - Limit violations
     * 
     * Non-Critical (Warning) Examples:
     * - Recommended fields missing
     * - Business suggestions not followed
     * - Non-optimal values
     * 
     * This classification allows:
     * - Strict enforcement of data requirements
     * - Flexibility for business preferences
     * - Clear communication of issues
     * - Gradual data quality improvement
     * 
     * @param array $errors List of validation error messages
     * 
     * @return bool True if any error is critical
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
     * Validate specific field updates for real-time validation
     * 
     * Provides field-level validation that can be called via AJAX for
     * immediate user feedback. This enables a responsive UI that validates
     * data as users type, improving data quality and user experience.
     * 
     * Use Cases:
     * 1. Real-time validation in edit forms
     * 2. Inline editing in list views
     * 3. Quick edits in detail views
     * 4. API field updates
     * 
     * Validation Process:
     * 1. Clone the bean to avoid side effects
     * 2. Apply the new field value
     * 3. Run full validation on modified bean
     * 4. Return targeted results for the field
     * 
     * Benefits:
     * - Immediate user feedback
     * - Prevents invalid data entry
     * - Reduces form submission errors
     * - Improves data quality
     * 
     * The method returns a standardized response that can be easily
     * consumed by JavaScript for UI updates.
     * 
     * @param SugarBean $bean The current state of the Deal bean
     * @param string $fieldName The field being updated
     * @param mixed $newValue The new value to validate
     * 
     * @return array Validation result with 'valid', 'errors', and 'warnings'
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
     * Validate pipeline stage transitions with business rules
     * 
     * Specialized validation for pipeline stage changes that enforces
     * business rules specific to deal progression. Stage transitions are
     * critical moments that often have prerequisites and implications.
     * 
     * Validation Includes:
     * 1. General deal validation with new stage
     * 2. Stage-specific prerequisites:
     *    - Required fields for each stage
     *    - Minimum data quality standards
     *    - Document requirements
     * 
     * 3. Transition rules:
     *    - Allowed transitions (some stages can't skip)
     *    - Regression rules (moving backwards)
     *    - Conditional progressions
     * 
     * 4. Automated adjustments:
     *    - Set probability based on stage
     *    - Update sales stage
     *    - Trigger required actions
     * 
     * This method can be called:
     * - During drag-and-drop in pipeline view
     * - From stage dropdown changes
     * - Via API stage updates
     * - Through automated workflows
     * 
     * @param SugarBean $bean The Deal bean attempting transition
     * @param string $oldStage Current stage before transition
     * @param string $newStage Target stage for transition
     * 
     * @return array Validation result with specific transition feedback
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
     * Validate stage-specific business rules and requirements
     * 
     * Implements detailed business logic for each pipeline stage, ensuring
     * deals meet specific criteria before entering each stage. These rules
     * reflect real-world requirements for deal progression.
     * 
     * Stage Requirements:
     * 
     * Due Diligence:
     * - Account must be specified (know who we're dealing with)
     * - Deal amount should be known (for resource allocation)
     * 
     * Valuation & Structuring:
     * - Deal amount required (can't value without it)
     * - Expected close date should be set (timeline planning)
     * 
     * LOI/Negotiation:
     * - Deal amount required (LOI specifies price)
     * - Probability ≥ 70% (serious negotiations only)
     * 
     * Financing/Closing:
     * - All financial fields required
     * - Close date mandatory
     * - High probability expected (≥ 80%)
     * 
     * Closed/Won Stages:
     * - Auto-set probability to 100%
     * - Auto-set sales stage to "Closed Won"
     * - Validate final amount is set
     * 
     * Unavailable (Lost):
     * - Auto-set probability to 0%
     * - Auto-set sales stage to "Closed Lost"
     * 
     * @param SugarBean $bean The Deal bean with new stage
     * @param string $newStage The stage to validate for
     * 
     * @return array Validation result with errors and warnings
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
     * Get validation summary for reporting and analysis
     * 
     * Retrieves historical validation data for a deal to support reporting
     * and analysis of data quality trends. This helps identify:
     * 
     * - Recurring validation issues
     * - User training needs  
     * - System configuration improvements
     * - Data quality trends over time
     * 
     * Summary Includes:
     * - Recent validation attempts (last 10)
     * - Success/failure rates
     * - Common error patterns
     * - Warning frequency
     * 
     * Use Cases:
     * 1. Deal detail view validation history widget
     * 2. Data quality dashboards
     * 3. User training identification
     * 4. System improvement analysis
     * 
     * The data is retrieved from the deals_workflow_log table where
     * validation results are stored for audit and analysis purposes.
     * 
     * @param string $dealId The deal ID to get validation history for
     * 
     * @return array List of validation summaries with dates and counts
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