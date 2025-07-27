<?php
/**
 * Test suite for FinancialCalculator class
 * 
 * This test verifies all financial calculation methods in the FinancialCalculator service.
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 */

if (!defined('sugarEntry')) define('sugarEntry', true);

require_once('custom/modules/Deals/services/FinancialCalculator.php');

class FinancialCalculatorTest
{
    private $calculator;
    private $testResults = array();
    
    public function __construct()
    {
        $this->calculator = new FinancialCalculator();
    }
    
    /**
     * Run all tests
     */
    public function runAllTests()
    {
        echo "<h2>Financial Calculator Test Suite</h2>\n";
        
        $this->testTTMRevenue();
        $this->testTTMEBITDA();
        $this->testSDE();
        $this->testEBITDAMargin();
        $this->testProposedValuation();
        $this->testImpliedMultiple();
        $this->testDebtServiceCoverageRatio();
        $this->testROI();
        $this->testWorkingCapital();
        $this->testBreakEvenMultiple();
        $this->testCapitalStack();
        $this->testPaybackPeriod();
        $this->testFormattingMethods();
        $this->testCompleteMetricsCalculation();
        
        $this->displayResults();
    }
    
    /**
     * Test TTM Revenue calculation
     */
    private function testTTMRevenue()
    {
        $testName = "TTM Revenue Calculation";
        
        // Test with monthly data
        $monthlyRevenue = array(
            10000, 12000, 11000, 13000, 14000, 15000,
            16000, 17000, 18000, 19000, 20000, 21000
        );
        $expectedTTM = array_sum($monthlyRevenue);
        $actualTTM = $this->calculator->calculateTTMRevenue($monthlyRevenue);
        
        $passed = abs($actualTTM - $expectedTTM) < 0.01;
        
        // Test with annual fallback
        $annualRevenue = 150000;
        $actualAnnual = $this->calculator->calculateTTMRevenue(array(), $annualRevenue);
        $passedAnnual = abs($actualAnnual - $annualRevenue) < 0.01;
        
        $this->testResults[$testName] = array(
            'passed' => $passed && $passedAnnual,
            'expected' => "Monthly: $expectedTTM, Annual: $annualRevenue",
            'actual' => "Monthly: $actualTTM, Annual: $actualAnnual"
        );
    }
    
    /**
     * Test TTM EBITDA calculation
     */
    private function testTTMEBITDA()
    {
        $testName = "TTM EBITDA Calculation";
        
        $ttmRevenue = 1000000;
        $operatingExpenses = 700000;
        $addBacks = 50000;
        
        $expectedEBITDA = $ttmRevenue - $operatingExpenses + $addBacks;
        $actualEBITDA = $this->calculator->calculateTTMEBITDA($ttmRevenue, $operatingExpenses, $addBacks);
        
        $this->testResults[$testName] = array(
            'passed' => abs($actualEBITDA - $expectedEBITDA) < 0.01,
            'expected' => $expectedEBITDA,
            'actual' => $actualEBITDA
        );
    }
    
    /**
     * Test SDE calculation
     */
    private function testSDE()
    {
        $testName = "SDE Calculation";
        
        $ebitda = 350000;
        $ownerCompensation = 120000;
        $ownerBenefits = 30000;
        $nonEssentialExpenses = 20000;
        
        $expectedSDE = $ebitda + $ownerCompensation + $ownerBenefits + $nonEssentialExpenses;
        $actualSDE = $this->calculator->calculateSDE($ebitda, $ownerCompensation, $ownerBenefits, $nonEssentialExpenses);
        
        $this->testResults[$testName] = array(
            'passed' => abs($actualSDE - $expectedSDE) < 0.01,
            'expected' => $expectedSDE,
            'actual' => $actualSDE
        );
    }
    
    /**
     * Test EBITDA Margin calculation
     */
    private function testEBITDAMargin()
    {
        $testName = "EBITDA Margin Calculation";
        
        $ebitda = 350000;
        $revenue = 1000000;
        
        $expectedMargin = ($ebitda / $revenue) * 100;
        $actualMargin = $this->calculator->calculateEBITDAMargin($ebitda, $revenue);
        
        $this->testResults[$testName] = array(
            'passed' => abs($actualMargin - $expectedMargin) < 0.1,
            'expected' => round($expectedMargin, 1) . '%',
            'actual' => $actualMargin . '%'
        );
    }
    
    /**
     * Test Proposed Valuation calculation
     */
    private function testProposedValuation()
    {
        $testName = "Proposed Valuation Calculation";
        
        $ebitda = 350000;
        $multiple = 4.5;
        
        $expectedValuation = $ebitda * $multiple;
        $actualValuation = $this->calculator->calculateProposedValuation($ebitda, $multiple);
        
        $this->testResults[$testName] = array(
            'passed' => abs($actualValuation - $expectedValuation) < 1,
            'expected' => $expectedValuation,
            'actual' => $actualValuation
        );
    }
    
    /**
     * Test Implied Multiple calculation
     */
    private function testImpliedMultiple()
    {
        $testName = "Implied Multiple Calculation";
        
        $askingPrice = 1500000;
        $ebitda = 350000;
        
        $expectedMultiple = $askingPrice / $ebitda;
        $actualMultiple = $this->calculator->calculateImpliedMultiple($askingPrice, $ebitda);
        
        $this->testResults[$testName] = array(
            'passed' => abs($actualMultiple - $expectedMultiple) < 0.01,
            'expected' => round($expectedMultiple, 2) . 'x',
            'actual' => $actualMultiple . 'x'
        );
    }
    
    /**
     * Test Debt Service Coverage Ratio calculation
     */
    private function testDebtServiceCoverageRatio()
    {
        $testName = "DSCR Calculation";
        
        $ebitda = 350000;
        $capEx = 50000;
        $taxes = 87500; // 25% of EBITDA
        $totalDebtService = 180000;
        
        $netCashFlow = $ebitda - $capEx - $taxes;
        $expectedDSCR = $netCashFlow / $totalDebtService;
        $actualDSCR = $this->calculator->calculateDebtServiceCoverageRatio($ebitda, $capEx, $taxes, $totalDebtService);
        
        $this->testResults[$testName] = array(
            'passed' => abs($actualDSCR - $expectedDSCR) < 0.01,
            'expected' => round($expectedDSCR, 2) . 'x',
            'actual' => $actualDSCR . 'x'
        );
    }
    
    /**
     * Test ROI calculation
     */
    private function testROI()
    {
        $testName = "ROI Calculation";
        
        $annualCashFlow = 250000;
        $totalInvestment = 1500000;
        
        $expectedROI = ($annualCashFlow / $totalInvestment) * 100;
        $actualROI = $this->calculator->calculateROI($annualCashFlow, $totalInvestment);
        
        $this->testResults[$testName] = array(
            'passed' => abs($actualROI - $expectedROI) < 0.1,
            'expected' => round($expectedROI, 1) . '%',
            'actual' => $actualROI . '%'
        );
    }
    
    /**
     * Test Working Capital calculation
     */
    private function testWorkingCapital()
    {
        $testName = "Working Capital Calculation";
        
        // Test with balance sheet items
        $currentAssets = 300000;
        $currentLiabilities = 200000;
        $expectedWC = $currentAssets - $currentLiabilities;
        $actualWC = $this->calculator->calculateWorkingCapitalRequirement($currentAssets, $currentLiabilities);
        
        // Test with revenue fallback
        $revenue = 1000000;
        $expectedWCRevenue = $revenue * 0.1;
        $actualWCRevenue = $this->calculator->calculateWorkingCapitalRequirement(null, null, $revenue);
        
        $this->testResults[$testName] = array(
            'passed' => abs($actualWC - $expectedWC) < 0.01 && abs($actualWCRevenue - $expectedWCRevenue) < 0.01,
            'expected' => "Balance Sheet: $expectedWC, Revenue-based: $expectedWCRevenue",
            'actual' => "Balance Sheet: $actualWC, Revenue-based: $actualWCRevenue"
        );
    }
    
    /**
     * Test Break-Even Multiple calculation
     */
    private function testBreakEvenMultiple()
    {
        $testName = "Break-Even Multiple Calculation";
        
        $annualCashFlow = 250000;
        $ebitda = 350000;
        $growthRate = 0.03;
        $holdPeriod = 5;
        
        // Calculate expected total cash flows
        $totalCashFlows = 0;
        for ($year = 1; $year <= $holdPeriod; $year++) {
            $totalCashFlows += $annualCashFlow * pow(1 + $growthRate, $year);
        }
        $expectedMultiple = $totalCashFlows / $ebitda;
        
        $actualMultiple = $this->calculator->calculateBreakEvenMultiple($annualCashFlow, $ebitda, $growthRate, $holdPeriod);
        
        $this->testResults[$testName] = array(
            'passed' => abs($actualMultiple - $expectedMultiple) < 0.01,
            'expected' => round($expectedMultiple, 2) . 'x',
            'actual' => $actualMultiple . 'x'
        );
    }
    
    /**
     * Test Capital Stack calculation
     */
    private function testCapitalStack()
    {
        $testName = "Capital Stack Calculation";
        
        $totalPrice = 1500000;
        $equityPct = 0.30;
        $seniorDebtPct = 0.50;
        $sellerNotePct = 0.20;
        
        $capitalStack = $this->calculator->calculateCapitalStack($totalPrice, $equityPct, $seniorDebtPct, $sellerNotePct);
        
        $expectedEquity = $totalPrice * $equityPct;
        $expectedSeniorDebt = $totalPrice * $seniorDebtPct;
        $expectedSellerNote = $totalPrice * $sellerNotePct;
        
        $passed = abs($capitalStack['equity'] - $expectedEquity) < 1 &&
                  abs($capitalStack['senior_debt'] - $expectedSeniorDebt) < 1 &&
                  abs($capitalStack['seller_note'] - $expectedSellerNote) < 1;
        
        $this->testResults[$testName] = array(
            'passed' => $passed,
            'expected' => "Equity: $expectedEquity, Senior Debt: $expectedSeniorDebt, Seller Note: $expectedSellerNote",
            'actual' => "Equity: {$capitalStack['equity']}, Senior Debt: {$capitalStack['senior_debt']}, Seller Note: {$capitalStack['seller_note']}"
        );
    }
    
    /**
     * Test Payback Period calculation
     */
    private function testPaybackPeriod()
    {
        $testName = "Payback Period Calculation";
        
        $totalInvestment = 1500000;
        $annualCashFlow = 300000;
        
        $expectedPayback = $totalInvestment / $annualCashFlow;
        $actualPayback = $this->calculator->calculatePaybackPeriod($totalInvestment, $annualCashFlow);
        
        $this->testResults[$testName] = array(
            'passed' => abs($actualPayback - $expectedPayback) < 0.1,
            'expected' => round($expectedPayback, 1) . ' years',
            'actual' => $actualPayback . ' years'
        );
    }
    
    /**
     * Test formatting methods
     */
    private function testFormattingMethods()
    {
        $testName = "Formatting Methods";
        
        $currency = 1234567.89;
        $percentage = 12.345;
        $multiple = 3.456;
        
        $formattedCurrency = $this->calculator->formatCurrency($currency);
        $formattedPercentage = $this->calculator->formatPercentage($percentage);
        $formattedMultiple = $this->calculator->formatMultiple($multiple);
        
        $passed = $formattedCurrency === '$1,234,567.89' &&
                  $formattedPercentage === '12.3%' &&
                  $formattedMultiple === '3.46x';
        
        $this->testResults[$testName] = array(
            'passed' => $passed,
            'expected' => "Currency: $1,234,567.89, Percentage: 12.3%, Multiple: 3.46x",
            'actual' => "Currency: $formattedCurrency, Percentage: $formattedPercentage, Multiple: $formattedMultiple"
        );
    }
    
    /**
     * Test complete metrics calculation
     */
    private function testCompleteMetricsCalculation()
    {
        $testName = "Complete Metrics Calculation";
        
        $dealData = array(
            'asking_price' => 1500000,
            'annual_revenue' => 1000000,
            'operating_expenses' => 700000,
            'add_backs' => 50000,
            'owner_compensation' => 120000,
            'owner_benefits' => 30000,
            'non_essential_expenses' => 20000,
            'target_multiple' => 4.5,
            'valuation_method' => 'ebitda',
            'normalized_salary' => 50000,
            'equity_investment' => 450000,
            'debt_structure' => array(
                'senior_debt' => 750000,
                'senior_debt_rate' => 0.05,
                'senior_debt_term' => 5,
                'seller_note' => 300000,
                'seller_note_rate' => 0.06,
                'seller_note_term' => 3
            )
        );
        
        $metrics = $this->calculator->calculateAllMetrics($dealData);
        
        $passed = isset($metrics['ttm_revenue']) &&
                  isset($metrics['ttm_ebitda']) &&
                  isset($metrics['sde']) &&
                  isset($metrics['ebitda_margin']) &&
                  isset($metrics['proposed_valuation']) &&
                  isset($metrics['implied_multiple']) &&
                  isset($metrics['roi']) &&
                  isset($metrics['capital_stack']);
        
        $this->testResults[$testName] = array(
            'passed' => $passed,
            'expected' => 'All metrics calculated',
            'actual' => $passed ? 'All metrics calculated successfully' : 'Some metrics missing'
        );
    }
    
    /**
     * Display test results
     */
    private function displayResults()
    {
        $totalTests = count($this->testResults);
        $passedTests = 0;
        
        echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>\n";
        echo "<tr><th>Test Name</th><th>Status</th><th>Expected</th><th>Actual</th></tr>\n";
        
        foreach ($this->testResults as $testName => $result) {
            $status = $result['passed'] ? '✅ PASS' : '❌ FAIL';
            $statusColor = $result['passed'] ? 'green' : 'red';
            
            if ($result['passed']) {
                $passedTests++;
            }
            
            echo "<tr>";
            echo "<td>$testName</td>";
            echo "<td style='color: $statusColor; font-weight: bold;'>$status</td>";
            echo "<td>{$result['expected']}</td>";
            echo "<td>{$result['actual']}</td>";
            echo "</tr>\n";
        }
        
        echo "</table>\n";
        
        $successRate = ($passedTests / $totalTests) * 100;
        echo "<h3>Test Summary: $passedTests / $totalTests passed (" . round($successRate, 1) . "%)</h3>\n";
        
        if ($passedTests === $totalTests) {
            echo "<p style='color: green; font-weight: bold;'>✅ All tests passed! FinancialCalculator is working correctly.</p>\n";
        } else {
            echo "<p style='color: red; font-weight: bold;'>❌ Some tests failed. Please review the implementation.</p>\n";
        }
    }
}

// Run tests if executed directly
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    echo "<html><head><title>Financial Calculator Test Suite</title></head><body>\n";
    
    $tester = new FinancialCalculatorTest();
    $tester->runAllTests();
    
    echo "</body></html>\n";
}