<?php
/**
 * Financial Data Editor Service
 * 
 * Handles editing, validation, and deletion of financial data for deals.
 * Integrates with FinancialCalculator for real-time calculations.
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 * @version 1.0.0
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once('custom/modules/Deals/services/FinancialCalculator.php');

class FinancialDataEditor
{
    private $bean;
    private $calculator;
    private $errors = array();
    private $warnings = array();
    
    /**
     * Field groups for organized editing
     */
    const FIELD_GROUPS = array(
        'revenue_metrics' => array(
            'annual_revenue_c',
            'monthly_revenue_data'
        ),
        'profitability_metrics' => array(
            'operating_expenses_c',
            'add_backs_c',
            'owner_compensation_c',
            'owner_benefits_c',
            'non_essential_expenses_c'
        ),
        'valuation_settings' => array(
            'target_multiple_c',
            'valuation_method_c',
            'industry_multiple_c'
        ),
        'capital_stack' => array(
            'senior_debt_amount_c',
            'senior_debt_rate_c',
            'senior_debt_term_c',
            'seller_note_amount_c',
            'seller_note_rate_c',
            'seller_note_term_c'
        ),
        'balance_sheet' => array(
            'current_assets_c',
            'current_liabilities_c',
            'capital_expenditures_c'
        ),
        'assumptions' => array(
            'normalized_salary_c',
            'growth_rate_c',
            'hold_period_c',
            'estimated_taxes_c'
        )
    );
    
    /**
     * Constructor
     * 
     * @param SugarBean $bean Deal bean instance
     */
    public function __construct($bean)
    {
        $this->bean = $bean;
        $this->calculator = new FinancialCalculator();
    }
    
    /**
     * Get all financial data organized by field groups
     * 
     * @return array Organized financial data
     */
    public function getFinancialData()
    {
        $data = array();
        
        foreach (self::FIELD_GROUPS as $group => $fields) {
            $data[$group] = array();
            foreach ($fields as $field) {
                if ($field === 'monthly_revenue_data') {
                    $data[$group][$field] = $this->getMonthlyRevenueData();
                } else {
                    $data[$group][$field] = $this->getFieldValue($field);
                }
            }
        }
        
        // Add calculated metrics
        $data['calculated_metrics'] = $this->calculateMetrics($data);
        
        return $data;
    }
    
    /**
     * Update financial data with validation
     * 
     * @param array $updateData Data to update
     * @param bool $skipValidation Skip validation (for bulk operations)
     * @return array Result with success status and messages
     */
    public function updateFinancialData($updateData, $skipValidation = false)
    {
        $this->errors = array();
        $this->warnings = array();
        
        // Validate input data
        if (!$skipValidation) {
            $this->validateUpdateData($updateData);
            if (!empty($this->errors)) {
                return array(
                    'success' => false,
                    'errors' => $this->errors,
                    'warnings' => $this->warnings
                );
            }
        }
        
        // Start transaction
        global $db;
        $db->query('START TRANSACTION');
        
        try {
            // Update fields
            foreach ($updateData as $group => $groupData) {
                if ($group === 'monthly_revenue_data') {
                    $this->updateMonthlyRevenue($groupData);
                } elseif (isset(self::FIELD_GROUPS[$group])) {
                    foreach ($groupData as $field => $value) {
                        if (in_array($field, self::FIELD_GROUPS[$group])) {
                            $this->updateField($field, $value);
                        }
                    }
                }
            }
            
            // Save the bean
            $this->bean->save();
            
            // Recalculate metrics
            $updatedMetrics = $this->calculateMetrics($this->getFinancialData());
            
            // Log the update
            $this->logFinancialUpdate($updateData, $updatedMetrics);
            
            $db->query('COMMIT');
            
            return array(
                'success' => true,
                'updated_metrics' => $updatedMetrics,
                'warnings' => $this->warnings,
                'bean_id' => $this->bean->id
            );
            
        } catch (Exception $e) {
            $db->query('ROLLBACK');
            $GLOBALS['log']->error("FinancialDataEditor: Update failed - " . $e->getMessage());
            
            return array(
                'success' => false,
                'errors' => array('system_error' => 'Update failed: ' . $e->getMessage()),
                'warnings' => $this->warnings
            );
        }
    }
    
    /**
     * Delete/reset financial data
     * 
     * @param array $fieldsToReset Fields to reset (empty = reset all)
     * @param bool $softDelete Keep historical data but mark as inactive
     * @return array Result with success status
     */
    public function deleteFinancialData($fieldsToReset = array(), $softDelete = true)
    {
        $this->errors = array();
        
        // If no specific fields, reset all financial fields
        if (empty($fieldsToReset)) {
            foreach (self::FIELD_GROUPS as $group => $fields) {
                $fieldsToReset = array_merge($fieldsToReset, $fields);
            }
        }
        
        global $db;
        $db->query('START TRANSACTION');
        
        try {
            // Backup current data if soft delete
            if ($softDelete) {
                $this->backupFinancialData();
            }
            
            // Reset fields
            foreach ($fieldsToReset as $field) {
                if ($field === 'monthly_revenue_data') {
                    $this->deleteMonthlyRevenue($softDelete);
                } else {
                    $this->resetField($field);
                }
            }
            
            // Save changes
            $this->bean->save();
            
            // Log the deletion
            $this->logFinancialDeletion($fieldsToReset, $softDelete);
            
            $db->query('COMMIT');
            
            return array(
                'success' => true,
                'fields_reset' => $fieldsToReset,
                'soft_delete' => $softDelete
            );
            
        } catch (Exception $e) {
            $db->query('ROLLBACK');
            $GLOBALS['log']->error("FinancialDataEditor: Delete failed - " . $e->getMessage());
            
            return array(
                'success' => false,
                'errors' => array('system_error' => 'Delete failed: ' . $e->getMessage())
            );
        }
    }
    
    /**
     * Validate update data
     * 
     * @param array $updateData Data to validate
     */
    private function validateUpdateData($updateData)
    {
        foreach ($updateData as $group => $groupData) {
            if (!isset(self::FIELD_GROUPS[$group]) && $group !== 'monthly_revenue_data') {
                $this->errors[] = "Invalid field group: {$group}";
                continue;
            }
            
            foreach ($groupData as $field => $value) {
                $this->validateField($field, $value);
            }
        }
        
        // Cross-field validation
        $this->validateBusinessLogic($updateData);
    }
    
    /**
     * Validate individual field
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     */
    private function validateField($field, $value)
    {
        // Skip empty values for optional fields
        if ($value === '' || $value === null) {
            return;
        }
        
        switch ($field) {
            case 'annual_revenue_c':
            case 'operating_expenses_c':
            case 'add_backs_c':
            case 'owner_compensation_c':
            case 'owner_benefits_c':
            case 'non_essential_expenses_c':
            case 'current_assets_c':
            case 'current_liabilities_c':
            case 'capital_expenditures_c':
            case 'normalized_salary_c':
            case 'estimated_taxes_c':
            case 'senior_debt_amount_c':
            case 'seller_note_amount_c':
                if (!is_numeric($value) || $value < 0) {
                    $this->errors[] = "Field {$field} must be a positive number";
                }
                break;
                
            case 'target_multiple_c':
            case 'industry_multiple_c':
                if (!is_numeric($value) || $value <= 0 || $value > 50) {
                    $this->errors[] = "Multiple {$field} must be between 0.1 and 50";
                }
                break;
                
            case 'senior_debt_rate_c':
            case 'seller_note_rate_c':
                if (!is_numeric($value) || $value < 0 || $value > 100) {
                    $this->errors[] = "Interest rate {$field} must be between 0% and 100%";
                }
                break;
                
            case 'senior_debt_term_c':
            case 'seller_note_term_c':
            case 'hold_period_c':
                if (!is_numeric($value) || $value <= 0 || $value > 30) {
                    $this->errors[] = "Term {$field} must be between 1 and 30 years";
                }
                break;
                
            case 'growth_rate_c':
                if (!is_numeric($value) || $value < -1 || $value > 5) {
                    $this->errors[] = "Growth rate must be between -100% and 500%";
                }
                break;
                
            case 'valuation_method_c':
                if (!in_array($value, array('ebitda', 'sde', 'revenue'))) {
                    $this->errors[] = "Valuation method must be 'ebitda', 'sde', or 'revenue'";
                }
                break;
        }
    }
    
    /**
     * Validate business logic across fields
     * 
     * @param array $updateData Data being updated
     */
    private function validateBusinessLogic($updateData)
    {
        $flatData = array();
        foreach ($updateData as $group => $groupData) {
            $flatData = array_merge($flatData, $groupData);
        }
        
        // Check if operating expenses exceed revenue
        if (isset($flatData['annual_revenue_c']) && isset($flatData['operating_expenses_c'])) {
            if ($flatData['operating_expenses_c'] > $flatData['annual_revenue_c']) {
                $this->warnings[] = "Operating expenses exceed annual revenue";
            }
        }
        
        // Check debt service coverage
        if (isset($flatData['senior_debt_amount_c']) || isset($flatData['seller_note_amount_c'])) {
            $totalDebt = ($flatData['senior_debt_amount_c'] ?? 0) + ($flatData['seller_note_amount_c'] ?? 0);
            $ebitda = $this->calculateEBITDA($flatData);
            
            if ($totalDebt > 0 && $ebitda > 0) {
                $estimatedDSCR = $ebitda / ($totalDebt * 0.15); // Rough estimate
                if ($estimatedDSCR < 1.25) {
                    $this->warnings[] = "Estimated DSCR may be below acceptable levels (< 1.25x)";
                }
            }
        }
        
        // Check working capital
        if (isset($flatData['current_assets_c']) && isset($flatData['current_liabilities_c'])) {
            $workingCapital = $flatData['current_assets_c'] - $flatData['current_liabilities_c'];
            if ($workingCapital < 0) {
                $this->warnings[] = "Negative working capital detected";
            }
        }
    }
    
    /**
     * Calculate EBITDA for validation
     * 
     * @param array $data Financial data
     * @return float EBITDA value
     */
    private function calculateEBITDA($data)
    {
        $revenue = $data['annual_revenue_c'] ?? $this->getFieldValue('annual_revenue_c', 0);
        $expenses = $data['operating_expenses_c'] ?? $this->getFieldValue('operating_expenses_c', 0);
        $addBacks = $data['add_backs_c'] ?? $this->getFieldValue('add_backs_c', 0);
        
        return $revenue - $expenses + $addBacks;
    }
    
    /**
     * Calculate all financial metrics
     * 
     * @param array $data Financial data
     * @return array Calculated metrics
     */
    private function calculateMetrics($data)
    {
        // Flatten data for calculator
        $flatData = array();
        foreach ($data as $group => $groupData) {
            if ($group !== 'calculated_metrics') {
                $flatData = array_merge($flatData, $groupData);
            }
        }
        
        // Add deal amount as asking price
        $flatData['asking_price'] = floatval($this->bean->amount);
        
        return $this->calculator->calculateAllMetrics($flatData);
    }
    
    /**
     * Get monthly revenue data
     * 
     * @return array Monthly revenue data
     */
    private function getMonthlyRevenueData()
    {
        global $db;
        
        $sql = "SELECT month, revenue 
                FROM deals_monthly_revenue 
                WHERE deal_id = '{$this->bean->id}' 
                AND deleted = 0 
                ORDER BY month DESC 
                LIMIT 12";
        
        $result = $db->query($sql);
        $monthlyData = array();
        
        while ($row = $db->fetchByAssoc($result)) {
            $monthlyData[] = array(
                'month' => $row['month'],
                'revenue' => floatval($row['revenue'])
            );
        }
        
        return array_reverse($monthlyData);
    }
    
    /**
     * Update monthly revenue data
     * 
     * @param array $monthlyData Monthly revenue data
     */
    private function updateMonthlyRevenue($monthlyData)
    {
        global $db;
        
        foreach ($monthlyData as $entry) {
            if (empty($entry['month']) || !isset($entry['revenue'])) {
                continue;
            }
            
            $month = $db->quote($entry['month']);
            $revenue = floatval($entry['revenue']);
            $dealId = $db->quote($this->bean->id);
            
            // Check if record exists
            $checkSql = "SELECT id FROM deals_monthly_revenue 
                        WHERE deal_id = {$dealId} AND month = {$month} AND deleted = 0";
            $existing = $db->query($checkSql);
            
            if ($db->fetchByAssoc($existing)) {
                // Update existing
                $updateSql = "UPDATE deals_monthly_revenue 
                            SET revenue = {$revenue}, 
                                date_modified = NOW(),
                                modified_user_id = '{$GLOBALS['current_user']->id}'
                            WHERE deal_id = {$dealId} AND month = {$month}";
                $db->query($updateSql);
            } else {
                // Insert new
                $id = create_guid();
                $insertSql = "INSERT INTO deals_monthly_revenue 
                            (id, deal_id, month, revenue, date_entered, date_modified, created_by, modified_user_id) 
                            VALUES ('{$id}', {$dealId}, {$month}, {$revenue}, NOW(), NOW(), 
                                   '{$GLOBALS['current_user']->id}', '{$GLOBALS['current_user']->id}')";
                $db->query($insertSql);
            }
        }
    }
    
    /**
     * Delete monthly revenue data
     * 
     * @param bool $softDelete Soft delete flag
     */
    private function deleteMonthlyRevenue($softDelete)
    {
        global $db;
        
        $dealId = $db->quote($this->bean->id);
        
        if ($softDelete) {
            $sql = "UPDATE deals_monthly_revenue 
                   SET deleted = 1, date_modified = NOW(), modified_user_id = '{$GLOBALS['current_user']->id}'
                   WHERE deal_id = {$dealId}";
        } else {
            $sql = "DELETE FROM deals_monthly_revenue WHERE deal_id = {$dealId}";
        }
        
        $db->query($sql);
    }
    
    /**
     * Get field value with default
     * 
     * @param string $field Field name
     * @param mixed $default Default value
     * @return mixed Field value
     */
    private function getFieldValue($field, $default = null)
    {
        return isset($this->bean->$field) ? $this->bean->$field : $default;
    }
    
    /**
     * Update field value
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     */
    private function updateField($field, $value)
    {
        $this->bean->$field = $value;
    }
    
    /**
     * Reset field to default value
     * 
     * @param string $field Field name
     */
    private function resetField($field)
    {
        $defaults = array(
            'target_multiple_c' => 3.5,
            'valuation_method_c' => 'ebitda',
            'normalized_salary_c' => 50000,
            'growth_rate_c' => 0.03,
            'hold_period_c' => 5,
            'senior_debt_rate_c' => 6.5,
            'senior_debt_term_c' => 5,
            'seller_note_rate_c' => 5.0,
            'seller_note_term_c' => 3
        );
        
        $this->bean->$field = isset($defaults[$field]) ? $defaults[$field] : null;
    }
    
    /**
     * Backup financial data before deletion
     */
    private function backupFinancialData()
    {
        global $db;
        
        $data = $this->getFinancialData();
        $backupData = array(
            'deal_id' => $this->bean->id,
            'backup_date' => date('Y-m-d H:i:s'),
            'backup_user' => $GLOBALS['current_user']->id,
            'data' => json_encode($data)
        );
        
        $id = create_guid();
        $sql = "INSERT INTO deals_financial_backup 
                (id, deal_id, backup_date, backup_user, data, date_entered) 
                VALUES ('{$id}', '{$backupData['deal_id']}', '{$backupData['backup_date']}', 
                       '{$backupData['backup_user']}', '" . $db->quote($backupData['data']) . "', NOW())";
        
        $db->query($sql);
    }
    
    /**
     * Log financial update activity
     * 
     * @param array $updateData Updated data
     * @param array $metrics Calculated metrics
     */
    private function logFinancialUpdate($updateData, $metrics)
    {
        $GLOBALS['log']->info("FinancialDataEditor: Updated financial data for deal {$this->bean->id}");
        
        // You could extend this to create audit trail records
    }
    
    /**
     * Log financial deletion activity
     * 
     * @param array $fieldsReset Fields that were reset
     * @param bool $softDelete Soft delete flag
     */
    private function logFinancialDeletion($fieldsReset, $softDelete)
    {
        $action = $softDelete ? 'soft deleted' : 'permanently deleted';
        $GLOBALS['log']->info("FinancialDataEditor: {$action} financial data for deal {$this->bean->id}");
    }
    
    /**
     * Get validation errors
     * 
     * @return array Validation errors
     */
    public function getErrors()
    {
        return $this->errors;
    }
    
    /**
     * Get validation warnings
     * 
     * @return array Validation warnings
     */
    public function getWarnings()
    {
        return $this->warnings;
    }
}