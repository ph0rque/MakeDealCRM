<?php
/**
 * Financial Data API
 * 
 * RESTful API endpoints for managing financial data in deals.
 * Provides edit, delete, and validation functionality.
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 * @version 1.0.0
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once('custom/modules/Deals/services/FinancialDataEditor.php');

class FinancialDataApi
{
    /**
     * Handle API requests
     */
    public function handleRequest()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $action = $_REQUEST['action'] ?? '';
        
        // Set JSON response header
        header('Content-Type: application/json');
        
        try {
            switch ($method) {
                case 'GET':
                    return $this->handleGet($action);
                case 'POST':
                    return $this->handlePost($action);
                case 'PUT':
                    return $this->handlePut($action);
                case 'DELETE':
                    return $this->handleDelete($action);
                default:
                    return $this->errorResponse('Method not allowed', 405);
            }
        } catch (Exception $e) {
            $GLOBALS['log']->error("FinancialDataApi: " . $e->getMessage());
            return $this->errorResponse('Internal server error', 500);
        }
    }
    
    /**
     * Handle GET requests
     * 
     * @param string $action Action to perform
     * @return string JSON response
     */
    private function handleGet($action)
    {
        switch ($action) {
            case 'get_financial_data':
                return $this->getFinancialData();
            case 'validate_field':
                return $this->validateField();
            case 'calculate_metrics':
                return $this->calculateMetrics();
            case 'get_field_schema':
                return $this->getFieldSchema();
            default:
                return $this->errorResponse('Invalid action', 400);
        }
    }
    
    /**
     * Handle POST requests
     * 
     * @param string $action Action to perform
     * @return string JSON response
     */
    private function handlePost($action)
    {
        switch ($action) {
            case 'update_financial_data':
                return $this->updateFinancialData();
            case 'batch_update':
                return $this->batchUpdateFinancialData();
            case 'import_financial_data':
                return $this->importFinancialData();
            default:
                return $this->errorResponse('Invalid action', 400);
        }
    }
    
    /**
     * Handle PUT requests
     * 
     * @param string $action Action to perform
     * @return string JSON response
     */
    private function handlePut($action)
    {
        switch ($action) {
            case 'update_field':
                return $this->updateSingleField();
            default:
                return $this->errorResponse('Invalid action', 400);
        }
    }
    
    /**
     * Handle DELETE requests
     * 
     * @param string $action Action to perform
     * @return string JSON response
     */
    private function handleDelete($action)
    {
        switch ($action) {
            case 'delete_financial_data':
                return $this->deleteFinancialData();
            case 'reset_field_group':
                return $this->resetFieldGroup();
            case 'reset_all':
                return $this->resetAllFinancialData();
            default:
                return $this->errorResponse('Invalid action', 400);
        }
    }
    
    /**
     * Get financial data for a deal
     * 
     * @return string JSON response
     */
    private function getFinancialData()
    {
        $dealId = $_REQUEST['deal_id'] ?? '';
        
        if (empty($dealId)) {
            return $this->errorResponse('Deal ID is required', 400);
        }
        
        // Load deal bean
        $deal = BeanFactory::getBean('Deals', $dealId);
        if (!$deal || !$deal->ACLAccess('view')) {
            return $this->errorResponse('Deal not found or access denied', 404);
        }
        
        $editor = new FinancialDataEditor($deal);
        $financialData = $editor->getFinancialData();
        
        return $this->successResponse($financialData);
    }
    
    /**
     * Update financial data
     * 
     * @return string JSON response
     */
    private function updateFinancialData()
    {
        $dealId = $_REQUEST['deal_id'] ?? '';
        $updateData = $this->getJsonInput();
        
        if (empty($dealId)) {
            return $this->errorResponse('Deal ID is required', 400);
        }
        
        if (empty($updateData)) {
            return $this->errorResponse('Update data is required', 400);
        }
        
        // Load deal bean
        $deal = BeanFactory::getBean('Deals', $dealId);
        if (!$deal || !$deal->ACLAccess('edit')) {
            return $this->errorResponse('Deal not found or access denied', 404);
        }
        
        $editor = new FinancialDataEditor($deal);
        $result = $editor->updateFinancialData($updateData);
        
        if ($result['success']) {
            return $this->successResponse($result);
        } else {
            return $this->errorResponse($result['errors'], 400, $result);
        }
    }
    
    /**
     * Batch update multiple deals
     * 
     * @return string JSON response
     */
    private function batchUpdateFinancialData()
    {
        $batchData = $this->getJsonInput();
        
        if (empty($batchData) || !is_array($batchData)) {
            return $this->errorResponse('Batch data is required as array', 400);
        }
        
        $results = array();
        
        foreach ($batchData as $dealUpdate) {
            $dealId = $dealUpdate['deal_id'] ?? '';
            $updateData = $dealUpdate['data'] ?? array();
            
            if (empty($dealId)) {
                $results[] = array(
                    'deal_id' => $dealId,
                    'success' => false,
                    'errors' => array('Deal ID is required')
                );
                continue;
            }
            
            $deal = BeanFactory::getBean('Deals', $dealId);
            if (!$deal || !$deal->ACLAccess('edit')) {
                $results[] = array(
                    'deal_id' => $dealId,
                    'success' => false,
                    'errors' => array('Deal not found or access denied')
                );
                continue;
            }
            
            $editor = new FinancialDataEditor($deal);
            $result = $editor->updateFinancialData($updateData);
            $result['deal_id'] = $dealId;
            $results[] = $result;
        }
        
        return $this->successResponse(array('batch_results' => $results));
    }
    
    /**
     * Delete financial data
     * 
     * @return string JSON response
     */
    private function deleteFinancialData()
    {
        $dealId = $_REQUEST['deal_id'] ?? '';
        $fieldsToReset = $_REQUEST['fields'] ?? array();
        $softDelete = $_REQUEST['soft_delete'] !== 'false';
        
        if (empty($dealId)) {
            return $this->errorResponse('Deal ID is required', 400);
        }
        
        // Load deal bean
        $deal = BeanFactory::getBean('Deals', $dealId);
        if (!$deal || !$deal->ACLAccess('edit')) {
            return $this->errorResponse('Deal not found or access denied', 404);
        }
        
        $editor = new FinancialDataEditor($deal);
        $result = $editor->deleteFinancialData($fieldsToReset, $softDelete);
        
        if ($result['success']) {
            return $this->successResponse($result);
        } else {
            return $this->errorResponse($result['errors'], 400, $result);
        }
    }
    
    /**
     * Reset specific field group
     * 
     * @return string JSON response
     */
    private function resetFieldGroup()
    {
        $dealId = $_REQUEST['deal_id'] ?? '';
        $fieldGroup = $_REQUEST['field_group'] ?? '';
        
        if (empty($dealId) || empty($fieldGroup)) {
            return $this->errorResponse('Deal ID and field group are required', 400);
        }
        
        if (!isset(FinancialDataEditor::FIELD_GROUPS[$fieldGroup])) {
            return $this->errorResponse('Invalid field group', 400);
        }
        
        $deal = BeanFactory::getBean('Deals', $dealId);
        if (!$deal || !$deal->ACLAccess('edit')) {
            return $this->errorResponse('Deal not found or access denied', 404);
        }
        
        $fieldsToReset = FinancialDataEditor::FIELD_GROUPS[$fieldGroup];
        $editor = new FinancialDataEditor($deal);
        $result = $editor->deleteFinancialData($fieldsToReset, true);
        
        if ($result['success']) {
            return $this->successResponse($result);
        } else {
            return $this->errorResponse($result['errors'], 400, $result);
        }
    }
    
    /**
     * Reset all financial data
     * 
     * @return string JSON response
     */
    private function resetAllFinancialData()
    {
        $dealId = $_REQUEST['deal_id'] ?? '';
        $confirmToken = $_REQUEST['confirm_token'] ?? '';
        
        if (empty($dealId)) {
            return $this->errorResponse('Deal ID is required', 400);
        }
        
        // Require confirmation token for full reset
        $expectedToken = md5($dealId . 'reset_all_financial_data' . date('Y-m-d'));
        if ($confirmToken !== $expectedToken) {
            return $this->errorResponse('Invalid confirmation token', 403);
        }
        
        $deal = BeanFactory::getBean('Deals', $dealId);
        if (!$deal || !$deal->ACLAccess('edit')) {
            return $this->errorResponse('Deal not found or access denied', 404);
        }
        
        $editor = new FinancialDataEditor($deal);
        $result = $editor->deleteFinancialData(array(), true);
        
        if ($result['success']) {
            return $this->successResponse($result);
        } else {
            return $this->errorResponse($result['errors'], 400, $result);
        }
    }
    
    /**
     * Update single field
     * 
     * @return string JSON response
     */
    private function updateSingleField()
    {
        $dealId = $_REQUEST['deal_id'] ?? '';
        $field = $_REQUEST['field'] ?? '';
        $value = $_REQUEST['value'] ?? '';
        
        if (empty($dealId) || empty($field)) {
            return $this->errorResponse('Deal ID and field are required', 400);
        }
        
        $deal = BeanFactory::getBean('Deals', $dealId);
        if (!$deal || !$deal->ACLAccess('edit')) {
            return $this->errorResponse('Deal not found or access denied', 404);
        }
        
        // Find which group the field belongs to
        $fieldGroup = null;
        foreach (FinancialDataEditor::FIELD_GROUPS as $group => $fields) {
            if (in_array($field, $fields)) {
                $fieldGroup = $group;
                break;
            }
        }
        
        if (!$fieldGroup) {
            return $this->errorResponse('Invalid field', 400);
        }
        
        $updateData = array($fieldGroup => array($field => $value));
        
        $editor = new FinancialDataEditor($deal);
        $result = $editor->updateFinancialData($updateData);
        
        if ($result['success']) {
            return $this->successResponse($result);
        } else {
            return $this->errorResponse($result['errors'], 400, $result);
        }
    }
    
    /**
     * Validate single field
     * 
     * @return string JSON response
     */
    private function validateField()
    {
        $field = $_REQUEST['field'] ?? '';
        $value = $_REQUEST['value'] ?? '';
        
        if (empty($field)) {
            return $this->errorResponse('Field is required', 400);
        }
        
        // Create temporary editor for validation
        $tempBean = BeanFactory::newBean('Deals');
        $editor = new FinancialDataEditor($tempBean);
        
        // Perform validation by attempting an update
        $fieldGroup = null;
        foreach (FinancialDataEditor::FIELD_GROUPS as $group => $fields) {
            if (in_array($field, $fields)) {
                $fieldGroup = $group;
                break;
            }
        }
        
        if (!$fieldGroup) {
            return $this->errorResponse('Invalid field', 400);
        }
        
        $testData = array($fieldGroup => array($field => $value));
        $result = $editor->updateFinancialData($testData, false);
        
        return $this->successResponse(array(
            'valid' => $result['success'],
            'errors' => $result['errors'] ?? array(),
            'warnings' => $result['warnings'] ?? array()
        ));
    }
    
    /**
     * Calculate metrics with current data
     * 
     * @return string JSON response
     */
    private function calculateMetrics()
    {
        $dealId = $_REQUEST['deal_id'] ?? '';
        $testData = $this->getJsonInput();
        
        if (empty($dealId)) {
            return $this->errorResponse('Deal ID is required', 400);
        }
        
        $deal = BeanFactory::getBean('Deals', $dealId);
        if (!$deal || !$deal->ACLAccess('view')) {
            return $this->errorResponse('Deal not found or access denied', 404);
        }
        
        $editor = new FinancialDataEditor($deal);
        
        // If test data provided, use it for calculation
        if (!empty($testData)) {
            // Merge test data with current data
            $currentData = $editor->getFinancialData();
            foreach ($testData as $group => $groupData) {
                if (isset($currentData[$group])) {
                    $currentData[$group] = array_merge($currentData[$group], $groupData);
                }
            }
            $financialData = $currentData;
        } else {
            $financialData = $editor->getFinancialData();
        }
        
        return $this->successResponse(array(
            'calculated_metrics' => $financialData['calculated_metrics']
        ));
    }
    
    /**
     * Get field schema for frontend
     * 
     * @return string JSON response
     */
    private function getFieldSchema()
    {
        $schema = array(
            'field_groups' => FinancialDataEditor::FIELD_GROUPS,
            'field_definitions' => array(
                'annual_revenue_c' => array(
                    'type' => 'currency',
                    'label' => 'Annual Revenue',
                    'required' => false,
                    'min' => 0,
                    'validation' => 'positive_number'
                ),
                'operating_expenses_c' => array(
                    'type' => 'currency',
                    'label' => 'Operating Expenses',
                    'required' => false,
                    'min' => 0,
                    'validation' => 'positive_number'
                ),
                'target_multiple_c' => array(
                    'type' => 'decimal',
                    'label' => 'Target Multiple',
                    'required' => false,
                    'min' => 0.1,
                    'max' => 50,
                    'step' => 0.1,
                    'validation' => 'multiple_range'
                ),
                'valuation_method_c' => array(
                    'type' => 'enum',
                    'label' => 'Valuation Method',
                    'options' => array('ebitda', 'sde', 'revenue'),
                    'default' => 'ebitda'
                ),
                // Add more field definitions as needed
            )
        );
        
        return $this->successResponse($schema);
    }
    
    /**
     * Import financial data from file or external source
     * 
     * @return string JSON response
     */
    private function importFinancialData()
    {
        $dealId = $_REQUEST['deal_id'] ?? '';
        $importType = $_REQUEST['import_type'] ?? '';
        
        if (empty($dealId)) {
            return $this->errorResponse('Deal ID is required', 400);
        }
        
        $deal = BeanFactory::getBean('Deals', $dealId);
        if (!$deal || !$deal->ACLAccess('edit')) {
            return $this->errorResponse('Deal not found or access denied', 404);
        }
        
        switch ($importType) {
            case 'csv':
                return $this->importFromCsv($deal);
            case 'json':
                return $this->importFromJson($deal);
            case 'template':
                return $this->importFromTemplate($deal);
            default:
                return $this->errorResponse('Invalid import type', 400);
        }
    }
    
    /**
     * Import from CSV file
     * 
     * @param SugarBean $deal Deal bean
     * @return string JSON response
     */
    private function importFromCsv($deal)
    {
        // Implementation for CSV import
        return $this->errorResponse('CSV import not yet implemented', 501);
    }
    
    /**
     * Import from JSON data
     * 
     * @param SugarBean $deal Deal bean
     * @return string JSON response
     */
    private function importFromJson($deal)
    {
        $jsonData = $this->getJsonInput();
        
        if (empty($jsonData)) {
            return $this->errorResponse('JSON data is required', 400);
        }
        
        $editor = new FinancialDataEditor($deal);
        $result = $editor->updateFinancialData($jsonData);
        
        if ($result['success']) {
            return $this->successResponse(array(
                'imported' => true,
                'result' => $result
            ));
        } else {
            return $this->errorResponse($result['errors'], 400, $result);
        }
    }
    
    /**
     * Import from predefined template
     * 
     * @param SugarBean $deal Deal bean
     * @return string JSON response
     */
    private function importFromTemplate($deal)
    {
        $templateName = $_REQUEST['template_name'] ?? '';
        
        // Define some common templates
        $templates = array(
            'manufacturing_template' => array(
                'assumptions' => array(
                    'target_multiple_c' => 4.5,
                    'valuation_method_c' => 'ebitda',
                    'growth_rate_c' => 0.02
                )
            ),
            'saas_template' => array(
                'assumptions' => array(
                    'target_multiple_c' => 6.0,
                    'valuation_method_c' => 'revenue',
                    'growth_rate_c' => 0.15
                )
            )
        );
        
        if (!isset($templates[$templateName])) {
            return $this->errorResponse('Template not found', 404);
        }
        
        $editor = new FinancialDataEditor($deal);
        $result = $editor->updateFinancialData($templates[$templateName]);
        
        if ($result['success']) {
            return $this->successResponse(array(
                'template_applied' => $templateName,
                'result' => $result
            ));
        } else {
            return $this->errorResponse($result['errors'], 400, $result);
        }
    }
    
    /**
     * Get JSON input from request body
     * 
     * @return array Decoded JSON data
     */
    private function getJsonInput()
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?: array();
    }
    
    /**
     * Return success response
     * 
     * @param mixed $data Response data
     * @return string JSON response
     */
    private function successResponse($data)
    {
        return json_encode(array(
            'success' => true,
            'data' => $data,
            'timestamp' => time()
        ));
    }
    
    /**
     * Return error response
     * 
     * @param mixed $error Error message or array
     * @param int $code HTTP status code
     * @param array $additionalData Additional data to include
     * @return string JSON response
     */
    private function errorResponse($error, $code = 400, $additionalData = array())
    {
        http_response_code($code);
        
        $response = array(
            'success' => false,
            'error' => $error,
            'timestamp' => time()
        );
        
        if (!empty($additionalData)) {
            $response = array_merge($response, $additionalData);
        }
        
        return json_encode($response);
    }
}

// Handle API request if called directly
if (isset($_REQUEST['api']) && $_REQUEST['api'] === 'financial_data') {
    $api = new FinancialDataApi();
    echo $api->handleRequest();
    exit;
}