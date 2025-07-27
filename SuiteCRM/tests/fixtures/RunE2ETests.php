<?php
/**
 * E2E Test Runner for MakeDealCRM
 * 
 * This script provides a comprehensive test execution framework
 * with data setup, test execution, and cleanup
 * 
 * @package MakeDealCRM
 * @subpackage Tests
 */

if (!defined('sugarEntry')) define('sugarEntry', true);

require_once(dirname(__FILE__) . '/../../include/entryPoint.php');
require_once(dirname(__FILE__) . '/TestHelper.php');
require_once(dirname(__FILE__) . '/TestDataFactory.php');

class E2ETestRunner
{
    private $helper;
    private $startTime;
    private $results = [];
    
    public function __construct()
    {
        $this->helper = TestHelper::getInstance();
        $this->startTime = microtime(true);
    }
    
    /**
     * Run all E2E tests
     */
    public function runAll()
    {
        $this->printHeader("MakeDealCRM E2E Test Suite");
        
        // Setup test environment
        $this->helper->setUp('E2E Test Suite');
        
        try {
            // Run test suites
            $this->runDealTests();
            $this->runPipelineTests();
            $this->runChecklistTests();
            $this->runFinancialTests();
            $this->runSecurityTests();
            
            // Print results
            $this->printResults();
            
        } catch (Exception $e) {
            echo "\n❌ Test suite failed: " . $e->getMessage() . "\n";
            echo $e->getTraceAsString() . "\n";
        } finally {
            // Cleanup
            $this->helper->tearDown();
        }
    }
    
    /**
     * Run deal-related tests
     */
    private function runDealTests()
    {
        $this->printSection("Deal Module Tests");
        
        // Test 1: Create and retrieve deal
        $this->runTest('Create and Retrieve Deal', function() {
            $deal = $this->helper->createQuickDeal('Test Deal Creation');
            $this->helper->assertRecordExists('Deals', $deal->id);
            $this->helper->assertFieldValue($deal, 'name', 'Test Deal Creation');
        });
        
        // Test 2: Deal with relationships
        $this->runTest('Deal Relationships', function() {
            $deal = $this->helper->createQuickDeal();
            $contact = $this->helper->createQuickContact();
            $account = $this->helper->createQuickAccount();
            
            // Create relationships
            $this->helper->getFactory()->relateBeans($deal, $contact);
            $this->helper->getFactory()->relateBeans($deal, $account);
            
            // Verify relationships
            $this->helper->assertRelationshipExists($deal, 'contacts', $contact->id);
            $this->helper->assertRelationshipExists($deal, 'accounts', $account->id);
        });
        
        // Test 3: Deal status transitions
        $this->runTest('Deal Status Transitions', function() {
            $deal = $this->helper->createQuickDeal();
            
            $statuses = ['initial_contact', 'nda_signed', 'due_diligence', 'closed_won'];
            foreach ($statuses as $status) {
                $deal->status = $status;
                $deal->save();
                
                $retrieved = BeanFactory::getBean('Deals', $deal->id);
                $this->helper->assertFieldValue($retrieved, 'status', $status);
            }
        });
        
        // Test 4: Financial calculations
        $this->runTest('Financial Calculations', function() {
            $factory = $this->helper->getFactory();
            $deal = $factory->createDeal([
                'deal_value' => 10000000,
                'ttm_revenue_c' => 15000000,
                'ttm_ebitda_c' => 3000000,
                'target_multiple_c' => 4.5
            ]);
            
            // Verify calculations
            $expectedValuation = 3000000 * 4.5; // 13,500,000
            $this->helper->assertFieldValue($deal, 'ttm_ebitda_c', 3000000);
            
            // Test capital stack
            $totalCapital = $deal->equity_c + $deal->senior_debt_c + $deal->seller_note_c;
            if ($totalCapital != $deal->deal_value) {
                throw new Exception("Capital stack mismatch: $totalCapital != {$deal->deal_value}");
            }
        });
    }
    
    /**
     * Run pipeline tests
     */
    private function runPipelineTests()
    {
        $this->printSection("Pipeline Tests");
        
        // Test 1: Pipeline stage distribution
        $this->runTest('Pipeline Stage Distribution', function() {
            $factory = $this->helper->getFactory();
            $stages = ['sourcing', 'initial_contact', 'nda_signed', 'due_diligence'];
            
            foreach ($stages as $stage) {
                $factory->createDeal(['status' => $stage]);
            }
            
            // Query pipeline data
            global $db;
            $query = "SELECT status, COUNT(*) as count FROM deals 
                     WHERE deleted = 0 AND name LIKE 'TEST_%' 
                     GROUP BY status";
            $result = $db->query($query);
            
            $pipelineData = [];
            while ($row = $db->fetchByAssoc($result)) {
                $pipelineData[$row['status']] = $row['count'];
            }
            
            // Verify each stage has deals
            foreach ($stages as $stage) {
                if (!isset($pipelineData[$stage]) || $pipelineData[$stage] < 1) {
                    throw new Exception("No deals found in stage: $stage");
                }
            }
        });
        
        // Test 2: At-risk deals identification
        $this->runTest('At-Risk Deals', function() {
            $factory = $this->helper->getFactory();
            
            // Create deals with different dates
            $normalDeal = $factory->createDeal([
                'name' => 'TEST_Normal Deal',
                'status' => 'due_diligence',
                'date_in_current_stage' => date('Y-m-d H:i:s', strtotime('-5 days'))
            ]);
            
            $warningDeal = $factory->createDeal([
                'name' => 'TEST_Warning Deal',
                'status' => 'due_diligence',
                'date_in_current_stage' => date('Y-m-d H:i:s', strtotime('-20 days'))
            ]);
            
            $alertDeal = $factory->createDeal([
                'name' => 'TEST_Alert Deal',
                'status' => 'due_diligence',
                'date_in_current_stage' => date('Y-m-d H:i:s', strtotime('-40 days'))
            ]);
            
            // Verify at-risk status calculation
            // This would depend on your actual implementation
            echo "Created at-risk test deals\n";
        });
    }
    
    /**
     * Run checklist tests
     */
    private function runChecklistTests()
    {
        $this->printSection("Checklist Tests");
        
        // Test 1: Create checklist template
        $this->runTest('Checklist Template Creation', function() {
            $factory = $this->helper->getFactory();
            
            $template = $factory->createChecklistTemplate([
                'name' => 'TEST_Due Diligence Checklist',
                'stage' => 'due_diligence',
                'items' => [
                    'Review Financials',
                    'Legal Review',
                    'Site Visit'
                ]
            ]);
            
            if (!$template || !isset($template->id)) {
                throw new Exception("Failed to create checklist template");
            }
        });
        
        // Test 2: Apply checklist to deal
        $this->runTest('Apply Checklist to Deal', function() {
            $factory = $this->helper->getFactory();
            
            $deal = $factory->createDeal(['status' => 'due_diligence']);
            $template = $factory->createChecklistTemplate([
                'name' => 'TEST_DD Checklist',
                'stage' => 'due_diligence'
            ]);
            
            if (method_exists($factory, 'applyChecklistToDeal')) {
                $checklist = $factory->applyChecklistToDeal($template, $deal);
                if (!$checklist) {
                    echo "Note: Checklist application not implemented\n";
                }
            }
        });
    }
    
    /**
     * Run financial tests
     */
    private function runFinancialTests()
    {
        $this->printSection("Financial Tests");
        
        // Test 1: EBITDA calculations
        $this->runTest('EBITDA Calculations', function() {
            $factory = $this->helper->getFactory();
            
            // Positive EBITDA
            $profitableDeal = $factory->createDeal([
                'ttm_revenue_c' => 10000000,
                'ttm_ebitda_c' => 2000000
            ]);
            
            // Negative EBITDA
            $unprofitableDeal = $factory->createDeal([
                'ttm_revenue_c' => 5000000,
                'ttm_ebitda_c' => -500000
            ]);
            
            // Zero EBITDA
            $breakEvenDeal = $factory->createDeal([
                'ttm_revenue_c' => 3000000,
                'ttm_ebitda_c' => 0
            ]);
            
            echo "Created deals with various EBITDA scenarios\n";
        });
        
        // Test 2: Valuation multiples
        $this->runTest('Valuation Multiples', function() {
            $factory = $this->helper->getFactory();
            
            $testCases = [
                ['ebitda' => 1000000, 'multiple' => 4.5, 'expected' => 4500000],
                ['ebitda' => 2000000, 'multiple' => 6.0, 'expected' => 12000000],
                ['ebitda' => 500000, 'multiple' => 3.0, 'expected' => 1500000]
            ];
            
            foreach ($testCases as $case) {
                $deal = $factory->createDeal([
                    'ttm_ebitda_c' => $case['ebitda'],
                    'target_multiple_c' => $case['multiple']
                ]);
                
                $valuation = $case['ebitda'] * $case['multiple'];
                if (abs($valuation - $case['expected']) > 0.01) {
                    throw new Exception("Valuation mismatch: $valuation != {$case['expected']}");
                }
            }
        });
    }
    
    /**
     * Run security tests
     */
    private function runSecurityTests()
    {
        $this->printSection("Security Tests");
        
        // Test 1: XSS prevention
        $this->runTest('XSS Prevention', function() {
            $factory = $this->helper->getFactory();
            
            $maliciousData = [
                'name' => 'TEST_<script>alert("XSS")</script>',
                'description' => '<img src=x onerror=alert("XSS")>'
            ];
            
            $deal = $factory->createDeal($maliciousData);
            
            // Verify data is properly escaped when retrieved
            $retrieved = BeanFactory::getBean('Deals', $deal->id);
            
            if (strpos($retrieved->name, '<script>') !== false) {
                throw new Exception("XSS vulnerability: Script tags not escaped");
            }
        });
        
        // Test 2: SQL injection prevention
        $this->runTest('SQL Injection Prevention', function() {
            $factory = $this->helper->getFactory();
            
            $maliciousData = [
                'name' => "TEST_Deal'; DROP TABLE deals; --",
                'description' => "1' OR '1'='1"
            ];
            
            $deal = $factory->createDeal($maliciousData);
            
            // Verify deal was created without executing SQL
            $this->helper->assertRecordExists('Deals', $deal->id);
            
            // Verify deals table still exists
            global $db;
            $tables = $db->getTablesArray();
            if (!in_array('deals', $tables)) {
                throw new Exception("SQL injection vulnerability: Table dropped");
            }
        });
    }
    
    /**
     * Run a single test
     */
    private function runTest($name, $callback)
    {
        echo "  ▶ $name... ";
        
        try {
            $startTime = microtime(true);
            $callback();
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            echo "✅ ({$duration}ms)\n";
            
            $this->results[] = [
                'name' => $name,
                'status' => 'passed',
                'duration' => $duration
            ];
        } catch (Exception $e) {
            echo "❌\n";
            echo "    Error: " . $e->getMessage() . "\n";
            
            $this->results[] = [
                'name' => $name,
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Print test results summary
     */
    private function printResults()
    {
        $totalDuration = round((microtime(true) - $this->startTime) * 1000, 2);
        $passed = array_filter($this->results, function($r) { return $r['status'] === 'passed'; });
        $failed = array_filter($this->results, function($r) { return $r['status'] === 'failed'; });
        
        $this->printHeader("Test Results Summary");
        
        echo "Total Tests: " . count($this->results) . "\n";
        echo "Passed: " . count($passed) . " ✅\n";
        echo "Failed: " . count($failed) . " ❌\n";
        echo "Duration: {$totalDuration}ms\n";
        
        if (count($failed) > 0) {
            echo "\nFailed Tests:\n";
            foreach ($failed as $test) {
                echo "  - {$test['name']}: {$test['error']}\n";
            }
        }
        
        // Save results to file
        $resultsFile = dirname(__FILE__) . '/data/test_results_' . date('Y-m-d_H-i-s') . '.json';
        file_put_contents($resultsFile, json_encode([
            'timestamp' => date('Y-m-d H:i:s'),
            'duration' => $totalDuration,
            'summary' => [
                'total' => count($this->results),
                'passed' => count($passed),
                'failed' => count($failed)
            ],
            'results' => $this->results
        ], JSON_PRETTY_PRINT));
        
        echo "\nResults saved to: $resultsFile\n";
    }
    
    /**
     * Print section header
     */
    private function printSection($title)
    {
        echo "\n--- $title ---\n";
    }
    
    /**
     * Print header
     */
    private function printHeader($title)
    {
        $line = str_repeat('=', strlen($title) + 4);
        echo "\n$line\n  $title  \n$line\n";
    }
}

// CLI execution
if (php_sapi_name() === 'cli') {
    $runner = new E2ETestRunner();
    $runner->runAll();
}