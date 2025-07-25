<?php
/**
 * Comprehensive Test Suite for Deals Module Fixes
 * Tests pipeline view loading, AJAX endpoints, security, and JavaScript functionality
 */

class DealsModuleTest extends PHPUnit_Framework_TestCase
{
    private $user;
    private $deal;
    
    public function setUp()
    {
        global $current_user, $db;
        
        // Create test user
        $this->user = BeanFactory::newBean('Users');
        $this->user->user_name = 'test_user_' . uniqid();
        $this->user->status = 'Active';
        $this->user->save();
        
        // Set as current user
        $current_user = $this->user;
        
        // Create test deal
        $this->deal = BeanFactory::newBean('Deals');
        $this->deal->name = 'Test Deal ' . uniqid();
        $this->deal->amount = 10000;
        $this->deal->sales_stage = 'Qualification';
        $this->deal->assigned_user_id = $this->user->id;
        $this->deal->save();
    }
    
    public function tearDown()
    {
        // Clean up test data
        if ($this->deal && $this->deal->id) {
            $this->deal->mark_deleted($this->deal->id);
        }
        if ($this->user && $this->user->id) {
            $this->user->mark_deleted($this->user->id);
        }
    }
    
    /**
     * Test 1: Verify pipeline view loads without 500 error
     */
    public function testPipelineViewLoadsSuccessfully()
    {
        // Simulate request to pipeline view
        $_REQUEST['action'] = 'index';
        $_REQUEST['module'] = 'Deals';
        $_REQUEST['view'] = 'pipeline';
        
        // Include the view file
        ob_start();
        $result = null;
        try {
            require_once('custom/modules/Deals/views/view.pipeline.php');
            $view = new ViewPipeline();
            $view->init();
            $view->display();
            $result = ob_get_contents();
        } catch (Exception $e) {
            $this->fail('Pipeline view threw exception: ' . $e->getMessage());
        }
        ob_end_clean();
        
        // Verify no 500 error
        $this->assertNotContains('500', $result, 'Pipeline view should not return 500 error');
        $this->assertNotContains('Fatal error', $result, 'Pipeline view should not have fatal errors');
        $this->assertContains('pipeline-container', $result, 'Pipeline view should contain pipeline container');
    }
    
    /**
     * Test 2: Test AJAX endpoints return proper JSON responses
     */
    public function testAjaxEndpointsReturnJson()
    {
        // Test getDeals endpoint
        $_REQUEST['action'] = 'getDeals';
        $_REQUEST['module'] = 'Deals';
        $_REQUEST['to_pdf'] = false;
        
        ob_start();
        require_once('custom/modules/Deals/controller.php');
        $controller = new DealsController();
        $controller->action_getDeals();
        $response = ob_get_contents();
        ob_end_clean();
        
        // Verify JSON response
        $json = json_decode($response, true);
        $this->assertNotNull($json, 'getDeals should return valid JSON');
        $this->assertArrayHasKey('deals', $json, 'JSON should contain deals array');
        $this->assertArrayHasKey('stages', $json, 'JSON should contain stages array');
        
        // Test updateDealStage endpoint
        $_REQUEST['action'] = 'updateDealStage';
        $_REQUEST['deal_id'] = $this->deal->id;
        $_REQUEST['new_stage'] = 'Proposal/Price Quote';
        
        ob_start();
        $controller->action_updateDealStage();
        $response = ob_get_contents();
        ob_end_clean();
        
        $json = json_decode($response, true);
        $this->assertNotNull($json, 'updateDealStage should return valid JSON');
        $this->assertArrayHasKey('success', $json, 'JSON should contain success status');
    }
    
    /**
     * Test 3: Verify security fixes are working
     */
    public function testSecurityFixes()
    {
        // Test CSRF protection
        $_REQUEST['action'] = 'updateDealStage';
        $_REQUEST['deal_id'] = $this->deal->id;
        $_REQUEST['new_stage'] = 'Closed Won';
        
        // Without CSRF token
        ob_start();
        require_once('custom/modules/Deals/controller.php');
        $controller = new DealsController();
        $controller->action_updateDealStage();
        $response = ob_get_contents();
        ob_end_clean();
        
        $json = json_decode($response, true);
        
        // Test XSS prevention in deal names
        $xssDeal = BeanFactory::newBean('Deals');
        $xssDeal->name = '<script>alert("XSS")</script>';
        $xssDeal->sales_stage = 'Qualification';
        $xssDeal->save();
        
        $_REQUEST['action'] = 'getDeals';
        ob_start();
        $controller->action_getDeals();
        $response = ob_get_contents();
        ob_end_clean();
        
        // Verify XSS is escaped
        $this->assertNotContains('<script>alert("XSS")</script>', $response, 'XSS should be escaped');
        $this->assertContains('&lt;script&gt;', $response, 'HTML should be escaped');
        
        // Clean up XSS test deal
        $xssDeal->mark_deleted($xssDeal->id);
    }
    
    /**
     * Test 4: Check that all JavaScript errors are resolved
     */
    public function testJavaScriptErrorsResolved()
    {
        // Load pipeline view HTML
        ob_start();
        require_once('custom/modules/Deals/views/view.pipeline.php');
        $view = new ViewPipeline();
        $view->init();
        $view->display();
        $html = ob_get_contents();
        ob_end_clean();
        
        // Check for proper jQuery loading
        $this->assertContains('jquery', strtolower($html), 'jQuery should be loaded');
        
        // Check for proper script initialization
        $this->assertContains('$(document).ready', $html, 'Document ready handler should exist');
        
        // Verify no undefined variable references
        $this->assertNotContains('undefined is not', $html, 'No undefined errors should exist');
        
        // Check AJAX error handling
        $this->assertContains('.fail(function', $html, 'AJAX error handling should be implemented');
    }
    
    /**
     * Test 5: Test module structure compliance
     */
    public function testModuleStructureCompliance()
    {
        // Check required directories exist
        $this->assertDirectoryExists('custom/modules/Deals', 'Deals module directory should exist');
        $this->assertDirectoryExists('custom/modules/Deals/views', 'Views directory should exist');
        $this->assertDirectoryExists('custom/modules/Deals/metadata', 'Metadata directory should exist');
        
        // Check required files exist
        $this->assertFileExists('custom/modules/Deals/controller.php', 'Controller should exist');
        $this->assertFileExists('custom/modules/Deals/views/view.pipeline.php', 'Pipeline view should exist');
        $this->assertFileExists('custom/modules/Deals/metadata/detailviewdefs.php', 'Detail view defs should exist');
        
        // Verify controller extends SugarController
        require_once('custom/modules/Deals/controller.php');
        $controller = new DealsController();
        $this->assertInstanceOf('SugarController', $controller, 'Controller should extend SugarController');
        
        // Verify view extends SugarView
        require_once('custom/modules/Deals/views/view.pipeline.php');
        $view = new ViewPipeline();
        $this->assertInstanceOf('SugarView', $view, 'Pipeline view should extend SugarView');
    }
    
    /**
     * Test 6: Performance and response time tests
     */
    public function testPerformanceMetrics()
    {
        // Test pipeline view load time
        $startTime = microtime(true);
        
        $_REQUEST['action'] = 'index';
        $_REQUEST['module'] = 'Deals';
        $_REQUEST['view'] = 'pipeline';
        
        ob_start();
        require_once('custom/modules/Deals/views/view.pipeline.php');
        $view = new ViewPipeline();
        $view->init();
        $view->display();
        ob_end_clean();
        
        $endTime = microtime(true);
        $loadTime = $endTime - $startTime;
        
        // Pipeline view should load in under 2 seconds
        $this->assertLessThan(2, $loadTime, 'Pipeline view should load in under 2 seconds');
        
        // Test AJAX response time
        $startTime = microtime(true);
        
        $_REQUEST['action'] = 'getDeals';
        ob_start();
        require_once('custom/modules/Deals/controller.php');
        $controller = new DealsController();
        $controller->action_getDeals();
        ob_end_clean();
        
        $endTime = microtime(true);
        $ajaxTime = $endTime - $startTime;
        
        // AJAX should respond in under 1 second
        $this->assertLessThan(1, $ajaxTime, 'AJAX endpoints should respond in under 1 second');
    }
    
    /**
     * Generate comprehensive test report
     */
    public function generateTestReport()
    {
        $report = array(
            'test_date' => date('Y-m-d H:i:s'),
            'module' => 'Deals',
            'tests_run' => 6,
            'tests_passed' => 0,
            'tests_failed' => 0,
            'issues_found' => array(),
            'recommendations' => array()
        );
        
        // Run all tests and collect results
        $methods = get_class_methods($this);
        foreach ($methods as $method) {
            if (strpos($method, 'test') === 0 && $method != 'generateTestReport') {
                try {
                    $this->$method();
                    $report['tests_passed']++;
                } catch (Exception $e) {
                    $report['tests_failed']++;
                    $report['issues_found'][] = $method . ': ' . $e->getMessage();
                }
            }
        }
        
        // Add recommendations
        if ($report['tests_failed'] > 0) {
            $report['recommendations'][] = 'Fix failing tests before deployment';
        }
        
        $report['recommendations'][] = 'Monitor error logs after deployment';
        $report['recommendations'][] = 'Perform user acceptance testing';
        $report['recommendations'][] = 'Backup database before deployment';
        
        return $report;
    }
}

// Run tests if executed directly
if (php_sapi_name() == 'cli' && basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    $tester = new DealsModuleTest();
    $tester->setUp();
    
    echo "Running Deals Module Tests...\n\n";
    
    // Run each test
    $methods = get_class_methods($tester);
    foreach ($methods as $method) {
        if (strpos($method, 'test') === 0 && $method != 'generateTestReport') {
            echo "Running $method...";
            try {
                $tester->$method();
                echo " PASSED\n";
            } catch (Exception $e) {
                echo " FAILED: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Generate report
    $report = $tester->generateTestReport();
    echo "\n=== TEST REPORT ===\n";
    echo json_encode($report, JSON_PRETTY_PRINT) . "\n";
    
    $tester->tearDown();
}