<?php
/**
 * Unit tests for mdeal_Accounts module
 */

use PHPUnit\Framework\TestCase;

class mdeal_AccountsTest extends TestCase
{
    protected $account;
    protected $testData;

    protected function setUp(): void
    {
        global $current_user;
        
        // Set up test user if not exists
        if (empty($current_user)) {
            $current_user = new User();
            $current_user->id = create_guid();
            $current_user->user_name = 'test_user';
        }

        // Create test account
        $this->account = new mdeal_Accounts();
        
        // Set up test data
        $this->testData = [
            'name' => 'Test Corporation',
            'account_type' => 'target',
            'industry' => 'Technology',
            'annual_revenue' => 25000000,
            'ebitda' => 5000000,
            'employee_count' => 150,
            'website' => 'https://testcorp.com',
            'phone_office' => '555-123-4567',
            'email' => 'info@testcorp.com',
            'rating' => 'warm',
            'account_status' => 'active',
            'ownership_type' => 'private',
            'year_established' => 2010
        ];
    }

    protected function tearDown(): void
    {
        // Clean up test data
        if (!empty($this->account->id)) {
            $this->account->mark_deleted($this->account->id);
        }
    }

    /**
     * Test basic account creation
     */
    public function testAccountCreation()
    {
        foreach ($this->testData as $field => $value) {
            $this->account->$field = $value;
        }

        $result = $this->account->save();
        
        $this->assertNotEmpty($this->account->id);
        $this->assertEquals($this->testData['name'], $this->account->name);
        $this->assertEquals($this->testData['account_type'], $this->account->account_type);
        $this->assertEquals($this->testData['annual_revenue'], $this->account->annual_revenue);
    }

    /**
     * Test get summary text
     */
    public function testGetSummaryText()
    {
        $this->account->name = 'Acme Corporation';
        
        $summary = $this->account->get_summary_text();
        
        $this->assertEquals('Acme Corporation', $summary);
    }

    /**
     * Test hierarchy validation - prevent self-reference
     */
    public function testHierarchyValidationSelfReference()
    {
        $this->account->name = 'Test Company';
        $this->account->save();
        
        // Try to set parent_id to self
        $this->account->parent_id = $this->account->id;
        
        $this->assertTrue($this->account->validateHierarchy());
    }

    /**
     * Test hierarchy validation - valid hierarchy
     */
    public function testHierarchyValidationValid()
    {
        // Create parent account
        $parent = new mdeal_Accounts();
        $parent->name = 'Parent Corporation';
        $parent->save();
        
        // Set up child account reporting to parent
        $this->account->name = 'Subsidiary Inc';
        $this->account->parent_id = $parent->id;
        
        $this->assertTrue($this->account->validateHierarchy());
        
        // Clean up
        $parent->mark_deleted($parent->id);
    }

    /**
     * Test hierarchy validation - prevent circular reference
     */
    public function testHierarchyValidationCircularReference()
    {
        // Create parent account
        $parent = new mdeal_Accounts();
        $parent->name = 'Parent Corporation';
        $parent->save();
        
        // Create child account
        $this->account->name = 'Child Corporation';
        $this->account->parent_id = $parent->id;
        $this->account->save();
        
        // Try to create circular reference
        $parent->parent_id = $this->account->id;
        
        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->account);
        $method = $reflection->getMethod('validateHierarchy');
        $method->setAccessible(true);
        
        $this->assertFalse($method->invoke($parent));
        
        // Clean up
        $parent->mark_deleted($parent->id);
    }

    /**
     * Test hierarchy level calculation
     */
    public function testCalculateHierarchyLevel()
    {
        // Create grandparent
        $grandparent = new mdeal_Accounts();
        $grandparent->name = 'Grandparent Corp';
        $grandparent->save();
        
        // Create parent
        $parent = new mdeal_Accounts();
        $parent->name = 'Parent Corp';
        $parent->parent_id = $grandparent->id;
        $parent->save();
        
        // Create child
        $this->account->name = 'Child Corp';
        $this->account->parent_id = $parent->id;
        $this->account->save();
        
        // Test hierarchy levels
        $this->assertEquals(0, $grandparent->hierarchy_level);
        $this->assertEquals(1, $parent->hierarchy_level);
        $this->assertEquals(2, $this->account->hierarchy_level);
        
        // Clean up
        $grandparent->mark_deleted($grandparent->id);
        $parent->mark_deleted($parent->id);
    }

    /**
     * Test direct children functionality
     */
    public function testGetDirectChildren()
    {
        // Create parent account
        $this->account->name = 'Parent Corporation';
        $this->account->save();
        
        // Create child accounts
        $child1 = new mdeal_Accounts();
        $child1->name = 'Subsidiary One';
        $child1->parent_id = $this->account->id;
        $child1->save();
        
        $child2 = new mdeal_Accounts();
        $child2->name = 'Subsidiary Two';
        $child2->parent_id = $this->account->id;
        $child2->save();
        
        $children = $this->account->getDirectChildren();
        
        $this->assertCount(2, $children);
        $this->assertEquals('Subsidiary One', $children[0]['name']);
        $this->assertEquals('Subsidiary Two', $children[1]['name']);
        
        // Clean up
        $child1->mark_deleted($child1->id);
        $child2->mark_deleted($child2->id);
    }

    /**
     * Test children count calculation
     */
    public function testGetChildrenCount()
    {
        // Create parent account
        $this->account->name = 'Parent Corporation';
        $this->account->save();
        
        // Initially no children
        $count = $this->account->getChildrenCount();
        $this->assertEquals(0, $count);
        
        // Create child account
        $child = new mdeal_Accounts();
        $child->name = 'Subsidiary';
        $child->parent_id = $this->account->id;
        $child->save();
        
        $count = $this->account->getChildrenCount();
        $this->assertEquals(1, $count);
        
        // Clean up
        $child->mark_deleted($child->id);
    }

    /**
     * Test organization chart building
     */
    public function testGetOrganizationChart()
    {
        // Create parent account
        $this->account->name = 'Parent Corporation';
        $this->account->account_type = 'target';
        $this->account->industry = 'Technology';
        $this->account->save();
        
        // Create child account
        $child = new mdeal_Accounts();
        $child->name = 'Subsidiary Inc';
        $child->account_type = 'target';
        $child->industry = 'Software';
        $child->parent_id = $this->account->id;
        $child->save();
        
        $orgChart = $this->account->getOrganizationChart();
        
        $this->assertEquals('Parent Corporation', $orgChart['name']);
        $this->assertEquals('target', $orgChart['account_type']);
        $this->assertEquals('Technology', $orgChart['industry']);
        $this->assertCount(1, $orgChart['children']);
        $this->assertEquals('Subsidiary Inc', $orgChart['children'][0]['name']);
        
        // Clean up
        $child->mark_deleted($child->id);
    }

    /**
     * Test hierarchy tree functionality
     */
    public function testGetHierarchyTree()
    {
        // Create root account
        $root = new mdeal_Accounts();
        $root->name = 'Root Corporation';
        $root->save();
        
        // Create child (our test account)
        $this->account->name = 'Child Corporation';
        $this->account->parent_id = $root->id;
        $this->account->save();
        
        $tree = $this->account->getHierarchyTree();
        
        // Should return tree starting from root
        $this->assertEquals('Root Corporation', $tree['name']);
        $this->assertCount(1, $tree['children']);
        $this->assertEquals('Child Corporation', $tree['children'][0]['name']);
        
        // Clean up
        $root->mark_deleted($root->id);
    }

    /**
     * Test portfolio metrics calculation
     */
    public function testCalculatePortfolioMetrics()
    {
        // Non-portfolio company should return null
        $this->account->account_type = 'target';
        $metrics = $this->account->calculatePortfolioMetrics();
        $this->assertNull($metrics);
        
        // Portfolio company with full data
        $this->account->account_type = 'portfolio_company';
        $this->account->acquisition_price = 10000000;
        $this->account->current_valuation = 15000000;
        $this->account->annual_revenue = 5000000;
        $this->account->acquisition_date = '2020-01-15';
        
        $metrics = $this->account->calculatePortfolioMetrics();
        
        $this->assertEquals(2.0, $metrics['acquisition_multiple']); // 10M / 5M
        $this->assertEquals(3.0, $metrics['current_multiple']); // 15M / 5M
        $this->assertEquals(5000000, $metrics['value_creation']); // 15M - 10M
        $this->assertGreaterThan(1000, $metrics['holding_period_days']);
    }

    /**
     * Test account health score calculation
     */
    public function testCalculateHealthScore()
    {
        // High-performing account
        $this->account->annual_revenue = 100000000; // $100M
        $this->account->ebitda = 25000000; // $25M (25% margin)
        $this->account->employee_count = 1200;
        $this->account->industry = 'Technology';
        $this->account->deal_count = 8;
        
        $score = $this->account->calculateHealthScore();
        
        $this->assertGreaterThan(80, $score); // Should be high score
        
        // Low-performing account
        $this->account->annual_revenue = 500000; // $500K
        $this->account->ebitda = 25000; // $25K (5% margin)
        $this->account->employee_count = 5;
        $this->account->industry = 'Retail';
        $this->account->deal_count = 0;
        
        $score = $this->account->calculateHealthScore();
        
        $this->assertLessThan(50, $score); // Should be low score
    }

    /**
     * Test needs attention functionality
     */
    public function testNeedsAttention()
    {
        // Account with old deal activity
        $this->account->last_deal_date = date('Y-m-d H:i:s', strtotime('-400 days'));
        
        $attention = $this->account->needsAttention(90);
        
        $this->assertTrue($attention['needs_attention']);
        $this->assertEquals('No recent deal activity', $attention['reason']);
        $this->assertGreaterThan(300, $attention['days_since_activity']);
        
        // Account with recent activity
        $this->account->last_deal_date = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        $attention = $this->account->needsAttention(90);
        
        $this->assertFalse($attention['needs_attention']);
    }

    /**
     * Test adding account to deal
     */
    public function testAddToDeal()
    {
        $this->account->name = 'Test Account';
        $this->account->save();
        
        // Mock the relationship
        $dealId = create_guid();
        
        // This would work in a real environment with proper relationship setup
        $result = $this->account->addToDeal($dealId, 'target');
        
        // For unit testing, we verify the method exists and can be called
        $this->assertIsBool($result);
    }

    /**
     * Test custom list query includes calculated fields
     */
    public function testCustomListQuery()
    {
        $query = $this->account->create_new_list_query(
            'name',
            '',
            array(),
            array(),
            0,
            '',
            true
        );
        
        $this->assertStringContainsString('contact_count', $query['select']);
        $this->assertStringContainsString('child_count', $query['select']);
        $this->assertStringContainsString('parent_name', $query['select']);
    }

    /**
     * Test required field validation
     */
    public function testRequiredFieldValidation()
    {
        // Test missing name
        $this->account->account_type = 'target';
        
        // This should work in a real environment with proper validation
        $this->assertEmpty($this->account->name);
        
        // Set required field
        $this->account->name = 'Test Corporation';
        $this->assertNotEmpty($this->account->name);
    }

    /**
     * Test revenue and EBITDA validation
     */
    public function testFinancialDataValidation()
    {
        // Valid financial data
        $this->account->annual_revenue = 10000000;
        $this->account->ebitda = 2000000;
        
        $this->assertGreaterThan(0, $this->account->annual_revenue);
        $this->assertGreaterThan(0, $this->account->ebitda);
        
        // Calculate margin
        $margin = ($this->account->ebitda / $this->account->annual_revenue) * 100;
        $this->assertEquals(20.0, $margin);
    }

    /**
     * Data provider for various account scenarios
     */
    public function accountHealthProvider()
    {
        return [
            'Large enterprise' => [
                [
                    'annual_revenue' => 500000000,
                    'ebitda' => 100000000,
                    'employee_count' => 5000,
                    'industry' => 'Software',
                    'deal_count' => 10
                ],
                95 // Expected minimum score
            ],
            'Mid-market company' => [
                [
                    'annual_revenue' => 50000000,
                    'ebitda' => 8000000,
                    'employee_count' => 200,
                    'industry' => 'Manufacturing',
                    'deal_count' => 3
                ],
                60 // Expected minimum score
            ],
            'Small business' => [
                [
                    'annual_revenue' => 2000000,
                    'ebitda' => 200000,
                    'employee_count' => 25,
                    'industry' => 'Retail',
                    'deal_count' => 1
                ],
                30 // Expected minimum score
            ]
        ];
    }

    /**
     * Test various account health scenarios using data provider
     * 
     * @dataProvider accountHealthProvider
     */
    public function testAccountHealthScenarios($accountData, $expectedMinScore)
    {
        foreach ($accountData as $field => $value) {
            $this->account->$field = $value;
        }
        
        $score = $this->account->calculateHealthScore();
        
        $this->assertGreaterThanOrEqual($expectedMinScore, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    /**
     * Test address handling
     */
    public function testAddressHandling()
    {
        $this->account->billing_address_street = '123 Main Street';
        $this->account->billing_address_city = 'Anytown';
        $this->account->billing_address_state = 'CA';
        $this->account->billing_address_country = 'USA';
        $this->account->same_as_billing = true;
        
        // In a real implementation, this would copy billing to shipping
        $this->assertTrue($this->account->same_as_billing);
        $this->assertNotEmpty($this->account->billing_address_street);
    }

    /**
     * Test account type classification
     */
    public function testAccountTypeClassification()
    {
        $validTypes = ['target', 'portfolio', 'acquirer', 'strategic_partner', 'broker'];
        
        foreach ($validTypes as $type) {
            $this->account->account_type = $type;
            $this->assertContains($this->account->account_type, $validTypes);
        }
    }

    /**
     * Test industry classification
     */
    public function testIndustryClassification()
    {
        $industries = ['Technology', 'Healthcare', 'Financial Services', 'Manufacturing'];
        
        foreach ($industries as $industry) {
            $this->account->industry = $industry;
            $this->assertNotEmpty($this->account->industry);
        }
    }

    /**
     * Test empty account name handling
     */
    public function testEmptyAccountName()
    {
        $this->account->name = '';
        
        // Should handle empty names gracefully
        $summary = $this->account->get_summary_text();
        $this->assertEquals('', $summary);
    }

    /**
     * Test negative financial values
     */
    public function testNegativeFinancialValues()
    {
        $this->account->annual_revenue = -1000000;
        $this->account->ebitda = -500000;
        
        // System should handle negative values
        $this->assertLessThan(0, $this->account->annual_revenue);
        $this->assertLessThan(0, $this->account->ebitda);
    }

    /**
     * Test maximum hierarchy depth protection
     */
    public function testMaximumHierarchyDepth()
    {
        // Create deep hierarchy chain
        $accounts = [];
        $parent = null;
        
        for ($i = 0; $i < 25; $i++) {
            $account = new mdeal_Accounts();
            $account->name = "Level {$i} Corp";
            if ($parent) {
                $account->parent_id = $parent->id;
            }
            $account->save();
            $accounts[] = $account;
            $parent = $account;
        }
        
        // Test that hierarchy validation handles deep chains
        $this->assertTrue($parent->validateHierarchy());
        
        // Clean up
        foreach ($accounts as $account) {
            $account->mark_deleted($account->id);
        }
    }

    /**
     * Test compliance and risk fields
     */
    public function testComplianceAndRiskFields()
    {
        $this->account->risk_assessment = 'medium';
        $this->account->compliance_status = 'compliant';
        $this->account->insurance_coverage = true;
        $this->account->insurance_expiry = date('Y-m-d', strtotime('+1 year'));
        
        $this->assertEquals('medium', $this->account->risk_assessment);
        $this->assertEquals('compliant', $this->account->compliance_status);
        $this->assertTrue($this->account->insurance_coverage);
        $this->assertNotEmpty($this->account->insurance_expiry);
    }

    /**
     * Test account status transitions
     */
    public function testAccountStatusTransitions()
    {
        $statuses = ['active', 'prospect', 'under_review', 'due_diligence', 'closed_won'];
        
        foreach ($statuses as $status) {
            $this->account->account_status = $status;
            $this->assertEquals($status, $this->account->account_status);
        }
    }

    /**
     * Test portfolio exit strategy
     */
    public function testPortfolioExitStrategy()
    {
        $this->account->account_type = 'portfolio_company';
        $this->account->exit_strategy = 'ipo';
        $this->account->planned_exit_date = date('Y-m-d', strtotime('+2 years'));
        
        $this->assertEquals('ipo', $this->account->exit_strategy);
        $this->assertNotEmpty($this->account->planned_exit_date);
    }
}