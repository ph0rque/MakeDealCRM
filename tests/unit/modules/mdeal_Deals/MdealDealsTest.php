<?php
/**
 * Unit Tests for mdeal_Deals Module
 * 
 * Tests the core functionality of the MakeDeal custom Deals module
 * including business logic, validation, and stage management
 */

namespace Tests\Unit\Modules\mdeal_Deals;

use Tests\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @covers \mdeal_Deals
 * @group mdeal
 */
class MdealDealsTest extends TestCase
{
    /**
     * @var MockObject
     */
    private $mockBean;
    
    /**
     * @var MockObject
     */
    private $mockDb;
    
    /**
     * Set up before each test
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock SugarBean
        $this->mockBean = $this->getMockBuilder('mdeal_Deals')
            ->disableOriginalConstructor()
            ->onlyMethods(['save', 'retrieve', 'mark_deleted'])
            ->getMock();
            
        // Mock database
        $this->mockDb = $this->createMock('DBManager');
    }
    
    /**
     * Test deal initialization with default values
     * @test
     */
    public function testDealInitialization(): void
    {
        $deal = new \mdeal_Deals();
        
        // Assert default values
        $this->assertEquals('mdeal_Deals', $deal->module_name);
        $this->assertEquals('mdeal_deals', $deal->table_name);
        $this->assertEquals('mdeal_Deals', $deal->object_name);
        $this->assertTrue($deal->new_schema);
        $this->assertTrue($deal->importable);
    }
    
    /**
     * Test deal stage progression rules
     * @test
     * @dataProvider stageProgressionProvider
     */
    public function testStageProgression($currentStage, $newStage, $isValid, $expectedError = null): void
    {
        $deal = new \mdeal_Deals();
        $deal->pipeline_stage_c = $currentStage;
        
        $result = $deal->canProgressToStage($newStage);
        
        $this->assertEquals($isValid, $result['valid']);
        
        if (!$isValid && $expectedError) {
            $this->assertEquals($expectedError, $result['error']);
        }
    }
    
    /**
     * Data provider for stage progression tests
     */
    public function stageProgressionProvider(): array
    {
        return [
            // Valid progressions
            ['sourcing', 'screening', true],
            ['screening', 'analysis_outreach', true],
            ['analysis_outreach', 'due_diligence', true],
            ['due_diligence', 'valuation_structuring', true],
            ['valuation_structuring', 'loi_negotiation', true],
            ['loi_negotiation', 'financing', true],
            ['financing', 'closing', true],
            ['closing', 'closed_owned_90_day', true],
            ['closed_owned_90_day', 'closed_owned_stable', true],
            
            // Any stage can move to unavailable
            ['sourcing', 'unavailable', true],
            ['due_diligence', 'unavailable', true],
            ['closing', 'unavailable', true],
            
            // Invalid progressions (skipping stages)
            ['sourcing', 'due_diligence', false, 'Cannot skip stages'],
            ['screening', 'closing', false, 'Cannot skip stages'],
            
            // Invalid progressions (moving backwards)
            ['due_diligence', 'sourcing', false, 'Cannot move backwards'],
            ['closing', 'financing', false, 'Cannot move backwards'],
            
            // Terminal stages cannot progress
            ['closed_owned_stable', 'closing', false, 'Cannot move from terminal stage'],
            ['unavailable', 'sourcing', false, 'Cannot move from terminal stage'],
        ];
    }
    
    /**
     * Test deal amount calculation with fees
     * @test
     */
    public function testDealAmountCalculations(): void
    {
        $deal = new \mdeal_Deals();
        
        // Set base amount
        $deal->amount = 1000000; // $1M
        $deal->acquisition_fee_percent_c = 2.5; // 2.5%
        $deal->management_fee_percent_c = 1.5; // 1.5%
        
        // Calculate fees
        $fees = $deal->calculateFees();
        
        $this->assertEquals(25000, $fees['acquisition_fee']);
        $this->assertEquals(15000, $fees['management_fee']);
        $this->assertEquals(40000, $fees['total_fees']);
        $this->assertEquals(1040000, $fees['total_deal_value']);
    }
    
    /**
     * Test deal validation rules
     * @test
     */
    public function testDealValidation(): void
    {
        $deal = new \mdeal_Deals();
        
        // Test empty deal
        $errors = $deal->validateDeal();
        $this->assertContains('Deal name is required', $errors);
        $this->assertContains('Pipeline stage is required', $errors);
        
        // Test with minimal valid data
        $deal->name = 'Test Deal';
        $deal->pipeline_stage_c = 'sourcing';
        $deal->amount = 100000;
        
        $errors = $deal->validateDeal();
        $this->assertEmpty($errors);
        
        // Test invalid amount
        $deal->amount = -1000;
        $errors = $deal->validateDeal();
        $this->assertContains('Deal amount must be positive', $errors);
        
        // Test invalid stage
        $deal->pipeline_stage_c = 'invalid_stage';
        $errors = $deal->validateDeal();
        $this->assertContains('Invalid pipeline stage', $errors);
    }
    
    /**
     * Test deal ownership and assignment
     * @test
     */
    public function testDealOwnership(): void
    {
        $deal = new \mdeal_Deals();
        
        // Test initial assignment
        $userId = 'user-123';
        $deal->assigned_user_id = $userId;
        
        $this->assertEquals($userId, $deal->assigned_user_id);
        
        // Test ownership change tracking
        $newUserId = 'user-456';
        $changeResult = $deal->changeOwnership($newUserId, 'Reassigned due to territory change');
        
        $this->assertTrue($changeResult);
        $this->assertEquals($newUserId, $deal->assigned_user_id);
        $this->assertNotEmpty($deal->ownership_history_c);
    }
    
    /**
     * Test deal priority calculation
     * @test
     * @dataProvider priorityCalculationProvider
     */
    public function testPriorityCalculation($amount, $daysInStage, $probability, $expectedPriority): void
    {
        $deal = new \mdeal_Deals();
        $deal->amount = $amount;
        $deal->days_in_current_stage_c = $daysInStage;
        $deal->probability_c = $probability;
        
        $priority = $deal->calculatePriority();
        
        $this->assertEquals($expectedPriority, $priority);
    }
    
    /**
     * Data provider for priority calculations
     */
    public function priorityCalculationProvider(): array
    {
        return [
            // High priority: high value, stale, high probability
            [5000000, 10, 80, 'critical'],
            [1000000, 8, 70, 'high'],
            
            // Medium priority: moderate values
            [500000, 5, 50, 'medium'],
            [250000, 4, 40, 'medium'],
            
            // Low priority: low values, fresh
            [50000, 1, 20, 'low'],
            [100000, 2, 10, 'low'],
        ];
    }
    
    /**
     * Test deal metrics calculation
     * @test
     */
    public function testDealMetrics(): void
    {
        $deal = new \mdeal_Deals();
        $deal->amount = 500000;
        $deal->probability_c = 60;
        $deal->pipeline_stage_c = 'due_diligence';
        $deal->stage_entered_date_c = date('Y-m-d H:i:s', strtotime('-5 days'));
        
        $metrics = $deal->calculateMetrics();
        
        // Expected value = amount * probability
        $this->assertEquals(300000, $metrics['expected_value']);
        
        // Days in stage
        $this->assertEquals(5, $metrics['days_in_stage']);
        
        // Stage velocity (stages progressed / days)
        $this->assertGreaterThan(0, $metrics['velocity']);
        
        // Risk score based on time in stage
        $this->assertEquals('medium', $metrics['risk_level']);
    }
    
    /**
     * Test deal duplication
     * @test
     */
    public function testDealDuplication(): void
    {
        $original = new \mdeal_Deals();
        $original->id = 'deal-123';
        $original->name = 'Original Deal';
        $original->amount = 750000;
        $original->pipeline_stage_c = 'loi_negotiation';
        $original->assigned_user_id = 'user-123';
        
        $duplicate = $original->duplicateDeal();
        
        // Assert duplication
        $this->assertNull($duplicate->id); // New ID will be generated
        $this->assertEquals('Original Deal (Copy)', $duplicate->name);
        $this->assertEquals(750000, $duplicate->amount);
        $this->assertEquals('sourcing', $duplicate->pipeline_stage_c); // Reset to first stage
        $this->assertEquals('user-123', $duplicate->assigned_user_id); // Keep owner
        $this->assertEmpty($duplicate->stage_history_c); // Clear history
    }
    
    /**
     * Test deal search functionality
     * @test
     */
    public function testDealSearch(): void
    {
        $searchParams = [
            'name' => 'Acme',
            'min_amount' => 100000,
            'max_amount' => 1000000,
            'stages' => ['due_diligence', 'loi_negotiation'],
            'assigned_users' => ['user-123', 'user-456'],
            'date_range' => 'last_30_days'
        ];
        
        $query = \mdeal_Deals::buildSearchQuery($searchParams);
        
        // Assert query contains correct conditions
        $this->assertStringContainsString("name LIKE '%Acme%'", $query);
        $this->assertStringContainsString("amount >= 100000", $query);
        $this->assertStringContainsString("amount <= 1000000", $query);
        $this->assertStringContainsString("pipeline_stage_c IN ('due_diligence','loi_negotiation')", $query);
        $this->assertStringContainsString("assigned_user_id IN ('user-123','user-456')", $query);
    }
    
    /**
     * Test deal activity tracking
     * @test
     */
    public function testActivityTracking(): void
    {
        $deal = new \mdeal_Deals();
        $deal->id = 'deal-123';
        
        // Log activity
        $activity = $deal->logActivity('email_sent', [
            'to' => 'client@example.com',
            'subject' => 'LOI Draft',
            'timestamp' => time()
        ]);
        
        $this->assertTrue($activity);
        
        // Get recent activities
        $recentActivities = $deal->getRecentActivities(10);
        $this->assertCount(1, $recentActivities);
        $this->assertEquals('email_sent', $recentActivities[0]['type']);
    }
    
    /**
     * Test deal notifications
     * @test
     */
    public function testDealNotifications(): void
    {
        $deal = new \mdeal_Deals();
        $deal->id = 'deal-123';
        $deal->name = 'Big Deal';
        $deal->assigned_user_id = 'user-123';
        $deal->pipeline_stage_c = 'closing';
        $deal->days_in_current_stage_c = 8; // Stale
        
        $notifications = $deal->getRequiredNotifications();
        
        // Should have stale deal notification
        $this->assertContains('stale_deal_warning', array_column($notifications, 'type'));
        
        // Should have high value deal notification if amount is high
        $deal->amount = 5000000;
        $notifications = $deal->getRequiredNotifications();
        $this->assertContains('high_value_deal_alert', array_column($notifications, 'type'));
    }
    
    /**
     * Test deal permissions
     * @test
     */
    public function testDealPermissions(): void
    {
        $deal = new \mdeal_Deals();
        $deal->assigned_user_id = 'user-123';
        $deal->team_id = 'team-456';
        $deal->pipeline_stage_c = 'closing';
        
        // Test owner permissions
        $this->assertTrue($deal->userCanEdit('user-123'));
        $this->assertTrue($deal->userCanChangeStage('user-123'));
        
        // Test team member permissions
        $this->assertTrue($deal->userCanView('user-789', ['team-456']));
        $this->assertFalse($deal->userCanDelete('user-789', ['team-456']));
        
        // Test stage-specific permissions
        $this->assertFalse($deal->userCanChangeStage('user-789', ['team-456'])); // Only owner can change closing stage
    }
    
    /**
     * Test deal export functionality
     * @test
     */
    public function testDealExport(): void
    {
        $deal = new \mdeal_Deals();
        $deal->id = 'deal-123';
        $deal->name = 'Export Test Deal';
        $deal->amount = 250000;
        $deal->pipeline_stage_c = 'due_diligence';
        
        $exportData = $deal->exportToArray();
        
        // Assert all required fields are present
        $requiredFields = ['id', 'name', 'amount', 'pipeline_stage_c', 'created_date', 'modified_date'];
        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $exportData);
        }
        
        // Test CSV export format
        $csvData = $deal->exportToCsv();
        $this->assertStringContainsString('Export Test Deal', $csvData);
        $this->assertStringContainsString('250000', $csvData);
    }
}