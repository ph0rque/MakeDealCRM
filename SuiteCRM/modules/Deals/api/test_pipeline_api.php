<?php
/**
 * Pipeline API Test Script
 * 
 * Tests all Pipeline API endpoints
 * Run from command line: php test_pipeline_api.php
 */

// SuiteCRM bootstrap
define('sugarEntry', true);
require_once dirname(__FILE__) . '/../../../../include/entryPoint.php';
require_once 'include/utils.php';
require_once 'custom/modules/Deals/api/PipelineApi.php';

// Test class
class PipelineApiTest
{
    private $api;
    private $testDealId;
    
    public function __construct()
    {
        $this->api = new PipelineApi();
    }
    
    public function runTests()
    {
        echo "\n=== Pipeline API Test Suite ===\n\n";
        
        // Create test deal
        $this->createTestDeal();
        
        // Run tests
        $this->testGetPipelineStages();
        $this->testGetPipelineDeals();
        $this->testMoveDeal();
        $this->testToggleFocus();
        $this->testGetPipelineMetrics();
        
        // Cleanup
        $this->cleanupTestDeal();
        
        echo "\n=== All tests completed ===\n";
    }
    
    private function createTestDeal()
    {
        echo "Creating test deal...\n";
        
        global $current_user;
        $deal = BeanFactory::newBean('Deals');
        $deal->name = 'API Test Deal ' . time();
        $deal->amount = 50000;
        $deal->pipeline_stage = 'prospecting';
        $deal->pipeline_focus = 0;
        $deal->assigned_user_id = $current_user->id;
        $deal->save();
        
        $this->testDealId = $deal->id;
        echo "✓ Test deal created: {$this->testDealId}\n\n";
    }
    
    private function cleanupTestDeal()
    {
        echo "\nCleaning up test deal...\n";
        
        $deal = BeanFactory::getBean('Deals', $this->testDealId);
        if ($deal) {
            $deal->mark_deleted($this->testDealId);
            echo "✓ Test deal deleted\n";
        }
    }
    
    private function testGetPipelineStages()
    {
        echo "Testing GET /pipeline/stages...\n";
        
        try {
            $args = array('module' => 'Deals');
            $result = $this->api->getPipelineStages(null, $args);
            
            assert($result['success'] === true, 'Success flag should be true');
            assert(is_array($result['stages']), 'Stages should be an array');
            assert(isset($result['total_deals']), 'Total deals should be present');
            
            echo "✓ Stages retrieved: " . count($result['stages']) . "\n";
            echo "✓ Total deals: " . $result['total_deals'] . "\n\n";
        } catch (Exception $e) {
            echo "✗ Error: " . $e->getMessage() . "\n\n";
        }
    }
    
    private function testGetPipelineDeals()
    {
        echo "Testing GET /pipeline/deals...\n";
        
        try {
            $args = array(
                'module' => 'Deals',
                'stage' => 'prospecting',
                'offset' => 0,
                'limit' => 10
            );
            $result = $this->api->getPipelineDeals(null, $args);
            
            assert($result['success'] === true, 'Success flag should be true');
            assert(is_array($result['records']), 'Records should be an array');
            assert(isset($result['total']), 'Total should be present');
            
            echo "✓ Deals retrieved: " . count($result['records']) . "\n";
            echo "✓ Total matching: " . $result['total'] . "\n\n";
        } catch (Exception $e) {
            echo "✗ Error: " . $e->getMessage() . "\n\n";
        }
    }
    
    private function testMoveDeal()
    {
        echo "Testing POST /pipeline/move...\n";
        
        try {
            $args = array(
                'module' => 'Deals',
                'deal_id' => $this->testDealId,
                'new_stage' => 'qualification'
            );
            $result = $this->api->moveDeal(null, $args);
            
            assert($result['success'] === true, 'Success flag should be true');
            assert($result['old_stage'] === 'prospecting', 'Old stage should be prospecting');
            assert($result['new_stage'] === 'qualification', 'New stage should be qualification');
            
            echo "✓ Deal moved from {$result['old_stage']} to {$result['new_stage']}\n\n";
        } catch (Exception $e) {
            echo "✗ Error: " . $e->getMessage() . "\n\n";
        }
    }
    
    private function testToggleFocus()
    {
        echo "Testing POST /pipeline/focus...\n";
        
        try {
            // Toggle on
            $args = array(
                'module' => 'Deals',
                'deal_id' => $this->testDealId
            );
            $result = $this->api->toggleFocus(null, $args);
            
            assert($result['success'] === true, 'Success flag should be true');
            assert($result['focus'] === true, 'Focus should be true');
            
            echo "✓ Focus toggled on\n";
            
            // Toggle off
            $result = $this->api->toggleFocus(null, $args);
            assert($result['focus'] === false, 'Focus should be false');
            
            echo "✓ Focus toggled off\n\n";
        } catch (Exception $e) {
            echo "✗ Error: " . $e->getMessage() . "\n\n";
        }
    }
    
    private function testGetPipelineMetrics()
    {
        echo "Testing GET /pipeline/metrics...\n";
        
        try {
            $args = array('module' => 'Deals');
            $result = $this->api->getPipelineMetrics(null, $args);
            
            assert($result['success'] === true, 'Success flag should be true');
            assert(is_array($result['metrics']), 'Metrics should be an array');
            assert(isset($result['metrics']['conversion_rates']), 'Conversion rates should be present');
            assert(isset($result['metrics']['average_time_in_stage']), 'Average time should be present');
            assert(isset($result['metrics']['total_pipeline_value']), 'Pipeline value should be present');
            
            echo "✓ Metrics retrieved successfully\n";
            echo "✓ Total pipeline value: $" . number_format($result['metrics']['total_pipeline_value'], 2) . "\n";
            echo "✓ Average deal size: $" . number_format($result['metrics']['average_deal_size'], 2) . "\n\n";
        } catch (Exception $e) {
            echo "✗ Error: " . $e->getMessage() . "\n\n";
        }
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli') {
    // Set current user for tests
    global $current_user;
    $current_user = BeanFactory::getBean('Users', '1');
    
    $tester = new PipelineApiTest();
    $tester->runTests();
} else {
    echo "This script should be run from the command line.\n";
}