<?php

use SuiteCRM\Test\SuitePHPUnitFrameworkTestCase;

require_once 'modules/Deals/logic_hooks/DealsLogicHooks.php';

class DealsLogicHooksTest extends SuitePHPUnitFrameworkTestCase
{
    protected $logicHooks;
    protected $mockDeal;
    protected $mockEmail;

    protected function setUp(): void
    {
        parent::setUp();
        
        global $current_user;
        $current_user = BeanFactory::newBean('Users');
        
        $this->logicHooks = new DealsLogicHooks();
        
        // Create mock Deal
        $this->mockDeal = $this->getMockBuilder('Deal')
            ->disableOriginalConstructor()
            ->getMock();
            
        // Create mock Email
        $this->mockEmail = $this->getMockBuilder('Email')
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testAfterEmailImportCreatesNewDeal()
    {
        $this->mockEmail->name = 'Deal: Test Company ABC - Acquisition Opportunity';
        $this->mockEmail->from_addr = 'broker@example.com';
        $this->mockEmail->to_addrs = 'deals@mycrm.com';
        $this->mockEmail->description = 'New opportunity for Test Company ABC. Revenue: $5M, EBITDA: $1M';
        
        // Test that a new deal would be created
        $result = $this->logicHooks->extractDealInfo($this->mockEmail);
        
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('source', $result);
        $this->assertEquals('Test Company ABC', $result['name']);
        $this->assertEquals('email', $result['source']);
    }

    public function testExtractFinancialData()
    {
        $emailBody = 'Company revenue is $5,000,000 with EBITDA of $1,200,000. Asking price: $6M';
        
        $financials = $this->logicHooks->extractFinancials($emailBody);
        
        $this->assertEquals(5000000, $financials['ttm_revenue_c']);
        $this->assertEquals(1200000, $financials['ttm_ebitda_c']);
        $this->assertEquals(6000000, $financials['asking_price_c']);
    }

    public function testExtractContactsFromEmail()
    {
        $emailAddresses = 'john.doe@company.com, jane.smith@broker.com';
        
        $contacts = $this->logicHooks->extractContacts($emailAddresses);
        
        $this->assertCount(2, $contacts);
        $this->assertEquals('john.doe@company.com', $contacts[0]);
        $this->assertEquals('jane.smith@broker.com', $contacts[1]);
    }

    public function testDuplicateDetection()
    {
        $dealData = [
            'name' => 'Test Company XYZ',
            'deal_value' => 5000000
        ];
        
        // Mock the database query for duplicate check
        $isDuplicate = $this->logicHooks->checkForDuplicates($dealData);
        
        // In a real test, we'd mock the database response
        $this->assertIsBool($isDuplicate);
    }

    public function testCalculateAtRiskDays()
    {
        $dateModified = date('Y-m-d H:i:s', strtotime('-15 days'));
        
        $days = $this->logicHooks->calculateDaysInStage($dateModified);
        
        $this->assertEquals(15, $days);
    }

    public function testEmailParsingPatterns()
    {
        $testCases = [
            'Revenue: $5M' => 5000000,
            'Revenue $5,000,000' => 5000000,
            'Annual Revenue: 5 million' => 5000000,
            'TTM Revenue: $5.5M' => 5500000,
        ];
        
        foreach ($testCases as $text => $expected) {
            $result = $this->logicHooks->parseRevenue($text);
            $this->assertEquals($expected, $result, "Failed to parse: {$text}");
        }
    }

    public function testAttachmentHandling()
    {
        $this->mockEmail->id = 'test-email-id';
        
        // Mock attachments
        $attachments = [
            ['name' => 'financials.pdf', 'id' => 'attach-1'],
            ['name' => 'presentation.pptx', 'id' => 'attach-2']
        ];
        
        $processedAttachments = $this->logicHooks->processAttachments($this->mockEmail->id);
        
        // In real implementation, this would create Document records
        $this->assertIsArray($processedAttachments);
    }

    public function testDealStageMapping()
    {
        $emailKeywords = [
            'NDA' => 'nda_signed',
            'letter of intent' => 'loi_submitted',
            'due diligence' => 'due_diligence',
            'initial contact' => 'initial_contact'
        ];
        
        foreach ($emailKeywords as $keyword => $expectedStage) {
            $stage = $this->logicHooks->detectDealStage("This email contains {$keyword} information");
            $this->assertEquals($expectedStage, $stage);
        }
    }

    public function testFinancialValidation()
    {
        $this->mockDeal->ttm_revenue_c = 5000000;
        $this->mockDeal->ttm_ebitda_c = 6000000; // EBITDA > Revenue (invalid)
        
        $isValid = $this->logicHooks->validateFinancials($this->mockDeal);
        
        $this->assertFalse($isValid, 'EBITDA should not exceed revenue');
    }

    public function testCapitalStackCalculation()
    {
        $this->mockDeal->equity_c = 2000000;
        $this->mockDeal->senior_debt_c = 3000000;
        $this->mockDeal->seller_note_c = 1000000;
        $this->mockDeal->proposed_valuation_c = 6000000;
        
        $total = $this->logicHooks->calculateTotalCapital($this->mockDeal);
        
        $this->assertEquals(6000000, $total);
        $this->assertEquals($this->mockDeal->proposed_valuation_c, $total);
    }
}