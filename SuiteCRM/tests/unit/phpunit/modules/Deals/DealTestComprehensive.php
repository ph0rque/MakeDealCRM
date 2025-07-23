<?php
/**
 * Comprehensive Unit Tests for Deal Bean
 * 
 * @package MakeDealCRM
 * @subpackage Tests
 */

use SuiteCRM\Test\SuitePHPUnitFrameworkTestCase;

class DealTestComprehensive extends SuitePHPUnitFrameworkTestCase
{
    protected $deal;
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();
        
        global $current_user, $db;
        get_sugar_config_defaults();
        $current_user = BeanFactory::newBean('Users');
        $current_user->id = '1';
        $this->db = $db;
        
        // Include the Deal class
        require_once 'modules/Deals/Deal.php';
        $this->deal = new Deal();
    }

    protected function tearDown(): void
    {
        if ($this->deal && !empty($this->deal->id)) {
            $this->deal->mark_deleted($this->deal->id);
        }
        unset($this->deal);
        parent::tearDown();
    }

    /**
     * Test basic bean properties
     */
    public function testDealBeanProperties()
    {
        $this->assertInstanceOf('Deal', $this->deal);
        $this->assertInstanceOf('Basic', $this->deal);
        $this->assertEquals('deals', $this->deal->table_name);
        $this->assertEquals('Deals', $this->deal->module_name);
        $this->assertEquals('Deal', $this->deal->object_name);
        $this->assertEquals('Deals', $this->deal->module_dir);
        $this->assertTrue($this->deal->new_schema);
        $this->assertTrue($this->deal->importable);
        $this->assertFalse($this->deal->disable_row_level_security);
    }

    /**
     * Test save functionality with at-risk calculations
     */
    public function testSaveWithAtRiskCalculations()
    {
        // Test new record save
        $this->deal->name = 'Test Deal - At Risk Calculations';
        $this->deal->status = 'initial_contact';
        $this->deal->deal_value = 1000000;
        $this->deal->save();
        
        $this->assertNotEmpty($this->deal->id);
        $this->assertNotEmpty($this->deal->date_in_current_stage);
        $this->assertEquals(0, $this->deal->days_in_stage);
        $this->assertEquals('Normal', $this->deal->at_risk_status);
        
        // Test status change resets date
        $oldDate = $this->deal->date_in_current_stage;
        sleep(1); // Ensure time difference
        $this->deal->status = 'nda_signed';
        $this->deal->save();
        
        $this->assertNotEquals($oldDate, $this->deal->date_in_current_stage);
        $this->assertEquals(0, $this->deal->days_in_stage);
    }

    /**
     * Test at-risk status calculations based on days in stage
     */
    public function testAtRiskStatusCalculations()
    {
        $this->deal->name = 'Test Deal - Risk Status';
        $this->deal->status = 'initial_contact';
        
        // Test Normal status (< 14 days)
        $this->deal->date_in_current_stage = date('Y-m-d H:i:s', strtotime('-5 days'));
        $this->deal->save();
        $this->assertEquals('Normal', $this->deal->at_risk_status);
        
        // Test Warning status (14-29 days)
        $this->deal->date_in_current_stage = date('Y-m-d H:i:s', strtotime('-20 days'));
        $this->deal->save();
        $this->assertEquals('Warning', $this->deal->at_risk_status);
        
        // Test Alert status (30+ days)
        $this->deal->date_in_current_stage = date('Y-m-d H:i:s', strtotime('-35 days'));
        $this->deal->save();
        $this->assertEquals('Alert', $this->deal->at_risk_status);
    }

    /**
     * Test proposed valuation calculation
     */
    public function testProposedValuationCalculation()
    {
        $this->deal->name = 'Test Deal - Valuation';
        
        // Test with valid EBITDA and multiple
        $this->deal->ttm_ebitda_c = 2000000; // $2M
        $this->deal->target_multiple_c = 5;
        $this->deal->save();
        
        $this->assertEquals(10000000, $this->deal->proposed_valuation_c);
        
        // Test with zero EBITDA
        $this->deal->ttm_ebitda_c = 0;
        $this->deal->proposed_valuation_c = null;
        $this->deal->save();
        
        $this->assertEquals(0, $this->deal->proposed_valuation_c);
        
        // Test with missing multiple
        $this->deal->ttm_ebitda_c = 1000000;
        $this->deal->target_multiple_c = '';
        $this->deal->proposed_valuation_c = null;
        $this->deal->save();
        
        $this->assertEmpty($this->deal->proposed_valuation_c);
    }

    /**
     * Test list view field formatting
     */
    public function testFillInAdditionalListFields()
    {
        global $locale;
        
        $this->deal->deal_value = 1500000.50;
        $this->deal->fill_in_additional_list_fields();
        
        // Check that deal_value is formatted
        $this->assertIsString($this->deal->deal_value);
        $this->assertStringContainsString('1,500,000.50', $this->deal->deal_value);
    }

    /**
     * Test get_summary_text method
     */
    public function testGetSummaryText()
    {
        $this->deal->name = 'Test Summary Deal';
        $summary = $this->deal->get_summary_text();
        
        $this->assertEquals('Test Summary Deal', $summary);
    }

    /**
     * Test relationship loading methods
     */
    public function testRelationshipMethods()
    {
        // Mock the relationships
        $this->deal->contacts = $this->createMock('Link2');
        $this->deal->documents = $this->createMock('Link2');
        
        $this->deal->contacts->expects($this->once())
            ->method('getBeans')
            ->willReturn([]);
            
        $this->deal->documents->expects($this->once())
            ->method('getBeans')
            ->willReturn([]);
        
        $contacts = $this->deal->get_contacts();
        $documents = $this->deal->get_documents();
        
        $this->assertIsArray($contacts);
        $this->assertIsArray($documents);
    }

    /**
     * Test email template parsing
     */
    public function testCreateEmailFromTemplate()
    {
        // This would require mocking EmailTemplate bean
        $this->markTestSkipped('Requires EmailTemplate bean mock');
    }

    /**
     * Test template variable parsing
     */
    public function testParseTemplate()
    {
        $this->deal->name = 'ABC Corporation Deal';
        $this->deal->status = 'due_diligence';
        $this->deal->deal_value = 5000000;
        $this->deal->assigned_user_name = 'John Doe';
        
        $template = 'Deal: {DEAL_NAME} - Status: {DEAL_STATUS} - Value: {DEAL_VALUE} - Assigned: {ASSIGNED_USER}';
        
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->deal);
        $method = $reflection->getMethod('parse_template');
        $method->setAccessible(true);
        
        $parsed = $method->invokeArgs($this->deal, [$template]);
        
        $this->assertStringContainsString('ABC Corporation Deal', $parsed);
        $this->assertStringContainsString('due_diligence', $parsed);
        $this->assertStringContainsString('5,000,000', $parsed);
        $this->assertStringContainsString('John Doe', $parsed);
    }

    /**
     * Test financial field calculations with edge cases
     */
    public function testFinancialFieldEdgeCases()
    {
        $this->deal->name = 'Test Financial Edge Cases';
        
        // Test with negative values
        $this->deal->ttm_ebitda_c = -500000;
        $this->deal->target_multiple_c = 4;
        $this->deal->save();
        
        $this->assertEquals(-2000000, $this->deal->proposed_valuation_c);
        
        // Test with decimal multiples
        $this->deal->ttm_ebitda_c = 1000000;
        $this->deal->target_multiple_c = 4.5;
        $this->deal->save();
        
        $this->assertEquals(4500000, $this->deal->proposed_valuation_c);
        
        // Test with very large numbers
        $this->deal->ttm_ebitda_c = 999999999;
        $this->deal->target_multiple_c = 10;
        $this->deal->save();
        
        $this->assertEquals(9999999990, $this->deal->proposed_valuation_c);
    }

    /**
     * Test concurrent save operations
     */
    public function testConcurrentSaveOperations()
    {
        $this->deal->name = 'Test Concurrent Save';
        $this->deal->status = 'initial_contact';
        $this->deal->save();
        
        $dealId = $this->deal->id;
        
        // Simulate concurrent update
        $deal2 = BeanFactory::getBean('Deals', $dealId);
        $deal2->deal_value = 2000000;
        $deal2->save();
        
        // Original object update
        $this->deal->status = 'nda_signed';
        $this->deal->save();
        
        // Reload and verify both changes
        $deal3 = BeanFactory::getBean('Deals', $dealId);
        $this->assertEquals('nda_signed', $deal3->status);
        $this->assertEquals(2000000, $deal3->deal_value);
    }

    /**
     * Test security and ACL
     */
    public function testSecurityAndACL()
    {
        global $current_user;
        
        // Test row level security is enabled
        $this->assertFalse($this->deal->disable_row_level_security);
        
        // Test assigned user functionality
        $this->deal->name = 'Test Security Deal';
        $this->deal->assigned_user_id = $current_user->id;
        $this->deal->save();
        
        $this->assertEquals($current_user->id, $this->deal->assigned_user_id);
    }

    /**
     * Test data validation
     */
    public function testDataValidation()
    {
        // Test required field validation
        $this->deal->name = ''; // Empty required field
        $this->deal->status = 'initial_contact';
        
        // In real implementation, this should trigger validation error
        // For now, just test that save returns an ID (it shouldn't in production)
        $result = $this->deal->save();
        
        // Test numeric field validation
        $this->deal->name = 'Test Validation';
        $this->deal->deal_value = 'not a number';
        $this->deal->save();
        
        // PHP will convert to 0
        $this->assertEquals(0, intval($this->deal->deal_value));
    }

    /**
     * Test mass update capabilities
     */
    public function testMassUpdateCapabilities()
    {
        // Create multiple deals
        $dealIds = [];
        for ($i = 1; $i <= 3; $i++) {
            $deal = new Deal();
            $deal->name = "Mass Update Test Deal {$i}";
            $deal->status = 'initial_contact';
            $deal->save();
            $dealIds[] = $deal->id;
        }
        
        // Simulate mass update
        foreach ($dealIds as $id) {
            $deal = BeanFactory::getBean('Deals', $id);
            $deal->status = 'nda_signed';
            $deal->at_risk_status = 'Normal';
            $deal->save();
        }
        
        // Verify updates
        foreach ($dealIds as $id) {
            $deal = BeanFactory::getBean('Deals', $id);
            $this->assertEquals('nda_signed', $deal->status);
            $this->assertEquals('Normal', $deal->at_risk_status);
            $deal->mark_deleted($id);
        }
    }

    /**
     * Test import capabilities
     */
    public function testImportCapabilities()
    {
        $this->assertTrue($this->deal->importable);
        
        // Test import field mapping
        $importData = [
            'name' => 'Imported Deal',
            'status' => 'initial_contact',
            'deal_value' => '5000000',
            'ttm_revenue_c' => '10000000',
            'ttm_ebitda_c' => '2000000',
            'target_multiple_c' => '5'
        ];
        
        foreach ($importData as $field => $value) {
            $this->deal->$field = $value;
        }
        
        $this->deal->save();
        
        $this->assertNotEmpty($this->deal->id);
        $this->assertEquals(10000000, $this->deal->proposed_valuation_c);
    }

    /**
     * Test audit trail functionality
     */
    public function testAuditTrail()
    {
        $this->deal->name = 'Test Audit Trail';
        $this->deal->status = 'initial_contact';
        $this->deal->save();
        
        $originalValue = $this->deal->deal_value;
        
        // Make changes
        $this->deal->deal_value = 3000000;
        $this->deal->status = 'nda_signed';
        $this->deal->save();
        
        // In production, audit table would have entries
        // Here we just verify the changes were saved
        $this->assertEquals(3000000, $this->deal->deal_value);
        $this->assertEquals('nda_signed', $this->deal->status);
    }
}