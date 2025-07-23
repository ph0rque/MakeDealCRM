<?php

use SuiteCRM\Test\SuitePHPUnitFrameworkTestCase;

class DealTest extends SuitePHPUnitFrameworkTestCase
{
    protected $deal;

    protected function setUp(): void
    {
        parent::setUp();
        
        global $current_user;
        get_sugar_config_defaults();
        $current_user = BeanFactory::newBean('Users');
        
        // Include the Deal class
        require_once 'modules/Deals/Deal.php';
        $this->deal = new Deal();
    }

    protected function tearDown(): void
    {
        unset($this->deal);
        parent::tearDown();
    }

    public function testDealBeanExists()
    {
        $this->assertInstanceOf('Deal', $this->deal);
        $this->assertInstanceOf('Basic', $this->deal);
    }

    public function testDealTableName()
    {
        $this->assertEquals('deals', $this->deal->table_name);
        $this->assertEquals('Deals', $this->deal->module_name);
        $this->assertEquals('Deals', $this->deal->module_dir);
    }

    public function testDealRequiredFields()
    {
        $vardefs = $this->deal->field_defs;
        
        // Check required fields exist
        $this->assertArrayHasKey('name', $vardefs);
        $this->assertArrayHasKey('status', $vardefs);
        $this->assertArrayHasKey('deal_value', $vardefs);
        
        // Check name is required
        $this->assertTrue($vardefs['name']['required']);
    }

    public function testFinancialFieldsExist()
    {
        $vardefs = $this->deal->field_defs;
        
        $financialFields = [
            'asking_price_c',
            'ttm_revenue_c',
            'ttm_ebitda_c',
            'sde_c',
            'proposed_valuation_c',
            'target_multiple_c'
        ];
        
        foreach ($financialFields as $field) {
            $this->assertArrayHasKey($field, $vardefs, "Financial field {$field} should exist");
        }
    }

    public function testCapitalStackFields()
    {
        $vardefs = $this->deal->field_defs;
        
        $this->assertArrayHasKey('equity_c', $vardefs);
        $this->assertArrayHasKey('senior_debt_c', $vardefs);
        $this->assertArrayHasKey('seller_note_c', $vardefs);
    }

    public function testCalculateValuation()
    {
        $this->deal->ttm_ebitda_c = 1000000; // $1M EBITDA
        $this->deal->target_multiple_c = 5;
        
        $this->deal->calculateValuation();
        
        $this->assertEquals(5000000, $this->deal->proposed_valuation_c);
    }

    public function testCalculateValuationWithZeroEbitda()
    {
        $this->deal->ttm_ebitda_c = 0;
        $this->deal->target_multiple_c = 5;
        
        $this->deal->calculateValuation();
        
        $this->assertEquals(0, $this->deal->proposed_valuation_c);
    }

    public function testUpdateAtRiskStatus()
    {
        // Test normal status (< 14 days)
        $this->deal->date_modified = date('Y-m-d H:i:s');
        $this->deal->updateAtRiskStatus();
        $this->assertEquals('normal', $this->deal->at_risk_status);
        
        // Test warning status (14-29 days)
        $this->deal->date_modified = date('Y-m-d H:i:s', strtotime('-20 days'));
        $this->deal->updateAtRiskStatus();
        $this->assertEquals('warning', $this->deal->at_risk_status);
        
        // Test alert status (30+ days)
        $this->deal->date_modified = date('Y-m-d H:i:s', strtotime('-35 days'));
        $this->deal->updateAtRiskStatus();
        $this->assertEquals('alert', $this->deal->at_risk_status);
    }

    public function testDealStagesEnum()
    {
        $vardefs = $this->deal->field_defs;
        $options = $vardefs['status']['options'];
        
        $this->assertEquals('deals_status_dom', $options);
    }

    public function testRelationshipsExist()
    {
        $this->assertArrayHasKey('contacts', $this->deal->relationship_fields);
        $this->assertArrayHasKey('documents', $this->deal->relationship_fields);
        $this->assertArrayHasKey('tasks', $this->deal->relationship_fields);
        $this->assertArrayHasKey('notes', $this->deal->relationship_fields);
    }

    public function testBeforeSaveHook()
    {
        $this->deal->ttm_ebitda_c = 2000000;
        $this->deal->target_multiple_c = 4;
        
        $this->deal->save();
        
        // Check valuation was calculated
        $this->assertEquals(8000000, $this->deal->proposed_valuation_c);
        
        // Check at-risk status was set
        $this->assertEquals('normal', $this->deal->at_risk_status);
    }

    public function testDuplicateCheckFields()
    {
        // Test that duplicate check fields are properly defined
        $duplicateCheckFields = $this->deal->duplicate_check_fields;
        
        $this->assertContains('name', $duplicateCheckFields);
        $this->assertContains('deal_value', $duplicateCheckFields);
    }

    public function testACLImplementation()
    {
        // Test that Deal implements ACL
        $this->assertTrue($this->deal->bean_implements('ACL'));
    }

    public function testDealSourceEnum()
    {
        $vardefs = $this->deal->field_defs;
        
        $this->assertArrayHasKey('source', $vardefs);
        $this->assertEquals('deals_source_dom', $vardefs['source']['options']);
    }

    public function testEmailFieldsExist()
    {
        $vardefs = $this->deal->field_defs;
        
        // Verify email-related fields for email integration
        $this->assertArrayHasKey('emails', $this->deal->relationship_fields);
    }
}