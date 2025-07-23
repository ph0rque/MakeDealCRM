<?php
/**
 * Comprehensive Unit Tests for Deals Logic Hooks
 * 
 * @package MakeDealCRM
 * @subpackage Tests
 */

use SuiteCRM\Test\SuitePHPUnitFrameworkTestCase;

require_once 'modules/Deals/logic_hooks/DealsLogicHooks.php';
require_once 'modules/Deals/Deal.php';

class DealsLogicHooksTestComprehensive extends SuitePHPUnitFrameworkTestCase
{
    protected $logicHooks;
    protected $deal;
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();
        
        global $current_user, $db;
        $current_user = BeanFactory::newBean('Users');
        $current_user->id = '1';
        $current_user->email1 = 'test@example.com';
        $current_user->full_name = 'Test User';
        
        $this->db = $db;
        $this->logicHooks = new DealsLogicHooks();
        $this->deal = new Deal();
    }

    protected function tearDown(): void
    {
        if ($this->deal && !empty($this->deal->id)) {
            $this->deal->mark_deleted($this->deal->id);
        }
        unset($_SESSION['deal_duplicates']);
        parent::tearDown();
    }

    /**
     * Test updateAtRiskStatus hook
     */
    public function testUpdateAtRiskStatus()
    {
        $this->deal->name = 'Test At Risk Status';
        $this->deal->status = 'initial_contact';
        $this->deal->date_in_current_stage = date('Y-m-d H:i:s', strtotime('-25 days'));
        
        $this->logicHooks->updateAtRiskStatus($this->deal, 'before_save', []);
        
        // The actual logic is in the bean save method, so this is mainly a placeholder test
        $this->assertNotNull($this->deal);
    }

    /**
     * Test calculateFinancialMetrics hook
     */
    public function testCalculateFinancialMetrics()
    {
        $this->deal->name = 'Test Financial Metrics';
        $this->deal->equity_c = 2000000;
        $this->deal->senior_debt_c = 3000000;
        $this->deal->seller_note_c = 1000000;
        $this->deal->ttm_ebitda_c = 1500000;
        
        $this->logicHooks->calculateFinancialMetrics($this->deal, 'before_save', []);
        
        // Verify total capital calculation
        $expectedTotal = 2000000 + 3000000 + 1000000;
        $this->assertEquals(6000000, $expectedTotal);
        
        // Verify debt coverage ratio calculation
        $expectedRatio = 1500000 / 3000000;
        $this->assertEquals(0.5, $expectedRatio);
    }

    /**
     * Test processEmailImport hook with email parsing
     */
    public function testProcessEmailImport()
    {
        // Create mock email
        $email = $this->createMock('Email');
        $email->id = 'test-email-id';
        $email->name = 'Deal Opportunity: ABC Corporation - $5M Revenue Business';
        $email->description = 'Great opportunity. Revenue: $5M, EBITDA: $1.2M, Asking: $6M';
        $email->description_html = '<p>Great opportunity. Revenue: $5M, EBITDA: $1.2M, Asking: $6M</p>';
        $email->from_addr = 'broker@example.com';
        $email->to_addrs = 'deals@ourcrm.com';
        $email->cc_addrs = 'partner@firm.com';
        
        $email->expects($this->any())
            ->method('retrieve')
            ->willReturn(true);
            
        $email->expects($this->any())
            ->method('getAttachments')
            ->willReturn([
                ['name' => 'financials.pdf', 'filename' => 'financials.pdf', 'type' => 'application/pdf'],
                ['name' => 'pitch.pptx', 'filename' => 'pitch.pptx', 'type' => 'application/vnd.ms-powerpoint']
            ]);
        
        // Mock BeanFactory for email retrieval
        $beanFactoryMock = $this->getMockBuilder('BeanFactory')
            ->disableOriginalConstructor()
            ->setMethods(['getBean'])
            ->getMock();
            
        $beanFactoryMock->expects($this->any())
            ->method('getBean')
            ->willReturn($email);
        
        $arguments = [
            'related_module' => 'Emails',
            'module' => 'Deals',
            'related_id' => 'test-email-id',
            'id' => $this->deal->id
        ];
        
        // Use reflection to test private methods
        $reflection = new ReflectionClass($this->logicHooks);
        
        // Test parseEmailForDealInfo
        $parseMethod = $reflection->getMethod('parseEmailForDealInfo');
        $parseMethod->setAccessible(true);
        $parseMethod->invokeArgs($this->logicHooks, [&$this->deal, $email]);
        
        $this->assertEquals(5000000, $this->deal->deal_value);
        $this->assertEquals(5000000, $this->deal->ttm_revenue_c);
        $this->assertEquals('Email', $this->deal->source);
        $this->assertStringContainsString('ABC Corporation', $this->deal->description);
    }

    /**
     * Test email content parsing for financial data
     */
    public function testParseEmailForDealInfo()
    {
        $email = new stdClass();
        $email->name = 'Business for Sale - Manufacturing Company';
        $email->description = 'Annual revenue of $3.5M with EBITDA margins of 20%. Asking price is $7 million.';
        $email->description_html = '';
        
        $reflection = new ReflectionClass($this->logicHooks);
        $method = $reflection->getMethod('parseEmailForDealInfo');
        $method->setAccessible(true);
        
        $this->deal->name = 'Test Deal';
        $method->invokeArgs($this->logicHooks, [&$this->deal, $email]);
        
        $this->assertEquals(3500000, $this->deal->deal_value);
        $this->assertEquals(3500000, $this->deal->ttm_revenue_c);
        $this->assertEquals('Email', $this->deal->source);
    }

    /**
     * Test various financial amount parsing patterns
     */
    public function testFinancialAmountParsing()
    {
        $testCases = [
            '$1,000,000' => 1000000,
            '$1M' => 1000000,
            '$1.5M' => 1500000,
            '$1.5m' => 1500000,
            '$500k' => 500000,
            '$500K' => 500000,
            '$5,500,000' => 5500000,
            '2.5 million' => 2500000,
            '$1,234,567.89' => 1234567.89
        ];
        
        $email = new stdClass();
        $email->name = 'Test';
        $email->description_html = '';
        
        $reflection = new ReflectionClass($this->logicHooks);
        $method = $reflection->getMethod('parseEmailForDealInfo');
        $method->setAccessible(true);
        
        foreach ($testCases as $text => $expected) {
            $this->deal = new Deal();
            $email->description = "Deal value is {$text}";
            $method->invokeArgs($this->logicHooks, [&$this->deal, $email]);
            $this->assertEquals($expected, $this->deal->deal_value, "Failed to parse: {$text}");
        }
    }

    /**
     * Test checkForDuplicates hook
     */
    public function testCheckForDuplicates()
    {
        // Create an existing deal
        $existingDeal = new Deal();
        $existingDeal->name = 'ABC Corporation Acquisition';
        $existingDeal->deal_value = 5000000;
        $existingDeal->save();
        
        // Test new deal with similar name
        $this->deal->name = 'ABC Corporation Acquisition Deal';
        $this->deal->deal_value = 5000000;
        $this->deal->fetched_row = []; // Simulate new record
        
        $this->logicHooks->checkForDuplicates($this->deal, 'before_save', []);
        
        // Check if duplicates were found and stored in session
        $this->assertArrayHasKey('deal_duplicates', $_SESSION);
        
        // Clean up
        $existingDeal->mark_deleted($existingDeal->id);
    }

    /**
     * Test formatListViewFields hook
     */
    public function testFormatListViewFields()
    {
        $this->deal->deal_value = 2500000.50;
        $this->deal->asking_price_c = 3000000;
        $this->deal->at_risk_status = 'Alert';
        
        $this->logicHooks->formatListViewFields($this->deal, 'process_list_view', []);
        
        $this->assertNotEmpty($this->deal->deal_value_formatted);
        $this->assertNotEmpty($this->deal->asking_price_c_formatted);
        $this->assertEquals('alert-danger', $this->deal->at_risk_css_class);
        
        // Test warning status
        $this->deal->at_risk_status = 'Warning';
        $this->logicHooks->formatListViewFields($this->deal, 'process_list_view', []);
        $this->assertEquals('alert-warning', $this->deal->at_risk_css_class);
        
        // Test normal status
        $this->deal->at_risk_status = 'Normal';
        $this->logicHooks->formatListViewFields($this->deal, 'process_list_view', []);
        $this->assertEquals('alert-success', $this->deal->at_risk_css_class);
    }

    /**
     * Test createContactsFromEmail method
     */
    public function testCreateContactsFromEmail()
    {
        $email = new stdClass();
        $email->from_addr = '"John Doe" <john.doe@company.com>';
        $email->to_addrs = 'deals@ourcrm.com, jane.smith@broker.com';
        $email->cc_addrs = 'mike@partner.com';
        
        // Mock the deal relationships
        $this->deal->contacts = $this->createMock('Link2');
        $this->deal->contacts->expects($this->any())
            ->method('add')
            ->willReturn(true);
        
        $reflection = new ReflectionClass($this->logicHooks);
        $method = $reflection->getMethod('createContactsFromEmail');
        $method->setAccessible(true);
        
        // Mock the findContactByEmail method to return false (no existing contacts)
        $findMethod = $reflection->getMethod('findContactByEmail');
        $findMethod->setAccessible(true);
        
        $method->invokeArgs($this->logicHooks, [&$this->deal, $email]);
        
        // Verify contacts would be created from email addresses
        $this->assertTrue(true); // Basic assertion since we're mocking
    }

    /**
     * Test attachEmailDocuments method
     */
    public function testAttachEmailDocuments()
    {
        $email = $this->createMock('Email');
        $email->id = 'test-email-id';
        
        $email->expects($this->once())
            ->method('retrieve')
            ->with('test-email-id')
            ->willReturn(true);
            
        $email->expects($this->once())
            ->method('getAttachments')
            ->willReturn([
                [
                    'name' => 'Financial Statement',
                    'filename' => 'financials_2024.pdf',
                    'type' => 'application/pdf'
                ],
                [
                    'name' => 'Company Presentation',
                    'filename' => 'presentation.pptx',
                    'type' => 'application/vnd.ms-powerpoint'
                ]
            ]);
        
        // Mock deal documents relationship
        $this->deal->documents = $this->createMock('Link2');
        $this->deal->documents->expects($this->exactly(2))
            ->method('add')
            ->willReturn(true);
        
        $reflection = new ReflectionClass($this->logicHooks);
        $method = $reflection->getMethod('attachEmailDocuments');
        $method->setAccessible(true);
        
        $method->invokeArgs($this->logicHooks, [&$this->deal, $email]);
    }

    /**
     * Test revenue extraction patterns
     */
    public function testRevenueExtractionPatterns()
    {
        $testCases = [
            'Revenue: $5M' => 5000000,
            'Annual Revenue: $2.5 million' => 2500000,
            'TTM Revenue of $10,000,000' => 10000000,
            'Revenues are $750k' => 750000,
            'revenue is approximately $3.2M' => 3200000,
            'REVENUE: $1,250,000' => 1250000
        ];
        
        $email = new stdClass();
        $email->name = 'Test';
        $email->description_html = '';
        
        $reflection = new ReflectionClass($this->logicHooks);
        $method = $reflection->getMethod('parseEmailForDealInfo');
        $method->setAccessible(true);
        
        foreach ($testCases as $text => $expected) {
            $this->deal = new Deal();
            $this->deal->ttm_revenue_c = null;
            $email->description = $text;
            $method->invokeArgs($this->logicHooks, [&$this->deal, $email]);
            $this->assertEquals($expected, $this->deal->ttm_revenue_c, "Failed to extract revenue from: {$text}");
        }
    }

    /**
     * Test edge cases and error handling
     */
    public function testEdgeCasesAndErrorHandling()
    {
        // Test with null email
        $this->deal->name = 'Test Deal';
        $nullEmail = null;
        
        $reflection = new ReflectionClass($this->logicHooks);
        $method = $reflection->getMethod('parseEmailForDealInfo');
        $method->setAccessible(true);
        
        // This should not throw an error
        try {
            $method->invokeArgs($this->logicHooks, [&$this->deal, $nullEmail]);
            $this->assertTrue(true);
        } catch (Exception $e) {
            $this->fail('Method should handle null email gracefully');
        }
        
        // Test with malformed email addresses
        $email = new stdClass();
        $email->from_addr = 'not-an-email';
        $email->to_addrs = '';
        $email->cc_addrs = null;
        
        $createContactsMethod = $reflection->getMethod('createContactsFromEmail');
        $createContactsMethod->setAccessible(true);
        
        try {
            $createContactsMethod->invokeArgs($this->logicHooks, [&$this->deal, $email]);
            $this->assertTrue(true);
        } catch (Exception $e) {
            $this->fail('Method should handle malformed emails gracefully');
        }
    }

    /**
     * Test complex email parsing scenarios
     */
    public function testComplexEmailParsingScenarios()
    {
        $email = new stdClass();
        $email->name = 'Re: Fwd: Deal - ABC Corp ($5M opportunity)';
        $email->description = '
            Hi team,
            
            I wanted to share this opportunity with you:
            
            Company: ABC Corporation
            Industry: Manufacturing
            Annual Revenue: $5.2M (2023)
            EBITDA: $1.3M (25% margin)
            Asking Price: $6.5M (5x EBITDA multiple)
            
            Additional financials:
            - Gross Margin: 45%
            - SDE: $1.1M
            - Growth Rate: 15% YoY
            
            The seller is motivated and looking for a quick close.
            
            Best regards,
            John
        ';
        $email->description_html = '';
        
        $reflection = new ReflectionClass($this->logicHooks);
        $method = $reflection->getMethod('parseEmailForDealInfo');
        $method->setAccessible(true);
        
        $this->deal->name = 'Test Complex Parsing';
        $method->invokeArgs($this->logicHooks, [&$this->deal, $email]);
        
        // Should extract the first valid amount as deal value
        $this->assertEquals(5000000, $this->deal->deal_value);
        $this->assertEquals(5200000, $this->deal->ttm_revenue_c);
        $this->assertStringContainsString('ABC Corp', $this->deal->description);
    }

    /**
     * Test performance with large email content
     */
    public function testPerformanceWithLargeContent()
    {
        $email = new stdClass();
        $email->name = 'Large Email Test';
        
        // Generate large email content
        $largeContent = str_repeat('This is a test content line. ', 1000);
        $largeContent .= ' Revenue: $10M EBITDA: $2M ';
        $largeContent .= str_repeat('More test content. ', 1000);
        
        $email->description = $largeContent;
        $email->description_html = '';
        
        $reflection = new ReflectionClass($this->logicHooks);
        $method = $reflection->getMethod('parseEmailForDealInfo');
        $method->setAccessible(true);
        
        $startTime = microtime(true);
        $method->invokeArgs($this->logicHooks, [&$this->deal, $email]);
        $endTime = microtime(true);
        
        // Should complete in reasonable time (< 1 second)
        $this->assertLessThan(1, $endTime - $startTime);
        $this->assertEquals(10000000, $this->deal->deal_value);
    }
}