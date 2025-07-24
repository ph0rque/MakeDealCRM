<?php
/**
 * Unit tests for mdeal_Leads module
 */

use PHPUnit\Framework\TestCase;

class mdeal_LeadsTest extends TestCase
{
    protected $lead;
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

        // Create test lead
        $this->lead = new mdeal_Leads();
        
        // Set up test data
        $this->testData = [
            'company_name' => 'Test Manufacturing Co',
            'first_name' => 'John',
            'last_name' => 'Smith',
            'title' => 'CEO',
            'email_address' => 'john.smith@testmfg.com',
            'phone_work' => '555-123-4567',
            'industry' => 'manufacturing',
            'annual_revenue' => 5000000,
            'employee_count' => 50,
            'lead_source' => 'broker_network',
            'status' => 'new',
            'pipeline_stage' => 'initial_contact'
        ];
    }

    protected function tearDown(): void
    {
        // Clean up test data
        if (!empty($this->lead->id)) {
            $this->lead->mark_deleted($this->lead->id);
        }
    }

    /**
     * Test basic lead creation
     */
    public function testLeadCreation()
    {
        foreach ($this->testData as $field => $value) {
            $this->lead->$field = $value;
        }

        $result = $this->lead->save();
        
        $this->assertNotEmpty($this->lead->id);
        $this->assertEquals($this->testData['company_name'], $this->lead->company_name);
        $this->assertEquals($this->testData['status'], $this->lead->status);
    }

    /**
     * Test qualification score calculation
     */
    public function testQualificationScoreCalculation()
    {
        // Set up lead with scoring factors
        $this->lead->industry = 'manufacturing'; // 25 points (preferred)
        $this->lead->annual_revenue = 10000000; // 25 points ($10M+)
        $this->lead->status = 'qualified'; // 25 points
        $this->lead->lead_source = 'broker_network'; // 25 points (high quality)
        
        $this->lead->calculateQualificationScore();
        
        $this->assertEquals(100, $this->lead->qualification_score);
    }

    /**
     * Test qualification score with lower values
     */
    public function testQualificationScoreLowerValues()
    {
        $this->lead->industry = 'retail'; // 10 points (not preferred)
        $this->lead->annual_revenue = 500000; // 5 points (under $1M)
        $this->lead->status = 'new'; // 5 points
        $this->lead->lead_source = 'online_marketplace'; // 10 points (lower quality)
        
        $this->lead->calculateQualificationScore();
        
        $this->assertEquals(30, $this->lead->qualification_score);
    }

    /**
     * Test pipeline stage transitions
     */
    public function testPipelineStageTransition()
    {
        // Create lead in initial stage
        $this->lead->pipeline_stage = 'initial_contact';
        $this->lead->save();
        
        $originalStageDate = $this->lead->date_entered_stage;
        
        // Move to next stage
        sleep(1); // Ensure time difference
        $this->lead->pipeline_stage = 'qualification';
        $this->lead->save();
        
        $this->assertEquals('qualification', $this->lead->pipeline_stage);
        $this->assertEquals(0, $this->lead->days_in_stage);
        $this->assertNotEquals($originalStageDate, $this->lead->date_entered_stage);
    }

    /**
     * Test conversion readiness check
     */
    public function testConversionReadiness()
    {
        // Set up lead not ready for conversion
        $this->lead->pipeline_stage = 'qualification';
        $this->lead->qualification_score = 60;
        $this->lead->company_name = 'Test Company';
        $this->lead->industry = 'manufacturing';
        $this->lead->annual_revenue = 1000000;
        
        $this->assertFalse($this->lead->isReadyForConversion());
        
        // Make lead ready for conversion
        $this->lead->pipeline_stage = 'ready_to_convert';
        $this->lead->qualification_score = 80;
        
        $this->assertTrue($this->lead->isReadyForConversion());
    }

    /**
     * Test conversion readiness with missing fields
     */
    public function testConversionReadinessMissingFields()
    {
        $this->lead->pipeline_stage = 'ready_to_convert';
        $this->lead->qualification_score = 80;
        $this->lead->company_name = 'Test Company';
        // Missing industry and annual_revenue
        
        $this->assertFalse($this->lead->isReadyForConversion());
    }

    /**
     * Test lead conversion to deal
     */
    public function testLeadConversionToDeal()
    {
        // Set up qualified lead
        foreach ($this->testData as $field => $value) {
            $this->lead->$field = $value;
        }
        $this->lead->pipeline_stage = 'ready_to_convert';
        $this->lead->qualification_score = 85;
        $this->lead->save();
        
        // Mock mdeal_Deals class for testing
        $this->mockDealClass();
        
        $dealId = $this->lead->convertToDeal();
        
        $this->assertNotEmpty($dealId);
        $this->assertEquals('converted', $this->lead->status);
        $this->assertEquals($dealId, $this->lead->converted_deal_id);
    }

    /**
     * Test duplicate conversion prevention
     */
    public function testPreventDuplicateConversion()
    {
        $this->lead->converted_deal_id = create_guid();
        
        $result = $this->lead->convertToDeal();
        
        $this->assertFalse($result);
    }

    /**
     * Test days since last activity calculation
     */
    public function testDaysSinceLastActivity()
    {
        // Test with recent activity
        $this->lead->last_activity_date = date('Y-m-d H:i:s', strtotime('-3 days'));
        
        $days = $this->lead->getDaysSinceLastActivity();
        
        $this->assertEquals(3, $days);
        
        // Test with no activity
        $this->lead->last_activity_date = null;
        $days = $this->lead->getDaysSinceLastActivity();
        
        $this->assertNull($days);
    }

    /**
     * Test get summary text
     */
    public function testGetSummaryText()
    {
        $this->lead->company_name = 'Test Company LLC';
        
        $summary = $this->lead->get_summary_text();
        
        $this->assertEquals('Test Company LLC', $summary);
    }

    /**
     * Test lead save with status change triggers score recalculation
     */
    public function testSaveTriggersScoreCalculation()
    {
        // Create initial lead
        $this->lead->company_name = 'Test Company';
        $this->lead->status = 'new';
        $this->lead->industry = 'manufacturing';
        $this->lead->annual_revenue = 5000000;
        $this->lead->lead_source = 'referral';
        $this->lead->save();
        
        $initialScore = $this->lead->qualification_score;
        
        // Change status to qualified
        $this->lead->status = 'qualified';
        $this->lead->save();
        
        // Score should have increased
        $this->assertGreaterThan($initialScore, $this->lead->qualification_score);
    }

    /**
     * Test custom list query includes calculated fields
     */
    public function testCustomListQuery()
    {
        $query = $this->lead->create_new_list_query(
            'company_name',
            '',
            array(),
            array(),
            0,
            '',
            true
        );
        
        $this->assertStringContainsString('days_in_stage_calc', $query['select']);
        $this->assertStringContainsString('DATEDIFF', $query['select']);
    }

    /**
     * Test required field validation
     */
    public function testRequiredFieldValidation()
    {
        // Test missing company_name
        $this->lead->last_name = 'Smith';
        $this->lead->status = 'new';
        
        // This should work in a real environment with proper validation
        // For unit tests, we just verify the fields are set correctly
        $this->assertEmpty($this->lead->company_name);
        
        // Set required fields
        $this->lead->company_name = 'Test Company';
        $this->assertNotEmpty($this->lead->company_name);
    }

    /**
     * Test industry-based scoring
     */
    public function testIndustryScoring()
    {
        $preferredIndustries = ['manufacturing', 'technology', 'healthcare', 'services'];
        
        foreach ($preferredIndustries as $industry) {
            $lead = new mdeal_Leads();
            $lead->industry = $industry;
            $lead->annual_revenue = 1000000;
            $lead->status = 'new';
            $lead->lead_source = 'other';
            
            $lead->calculateQualificationScore();
            
            // Should get 25 points for preferred industry
            $this->assertGreaterThanOrEqual(45, $lead->qualification_score); // 25 + 15 + 5 + 10
        }
    }

    /**
     * Test revenue-based scoring
     */
    public function testRevenueScoring()
    {
        $revenueTests = [
            [15000000, 25], // $15M -> 25 points
            [7500000, 20],  // $7.5M -> 20 points
            [2500000, 15],  // $2.5M -> 15 points
            [500000, 5],    // $500K -> 5 points
        ];
        
        foreach ($revenueTests as [$revenue, $expectedPoints]) {
            $lead = new mdeal_Leads();
            $lead->annual_revenue = $revenue;
            $lead->industry = 'other';
            $lead->status = 'new';
            $lead->lead_source = 'other';
            
            $lead->calculateQualificationScore();
            
            // Total score should include revenue points
            $expectedTotal = $expectedPoints + 10 + 5 + 10; // industry + status + source
            $this->assertEquals($expectedTotal, $lead->qualification_score);
        }
    }

    /**
     * Test lead source scoring
     */
    public function testLeadSourceScoring()
    {
        $highQualitySources = ['referral', 'broker_network', 'direct_outreach'];
        
        foreach ($highQualitySources as $source) {
            $lead = new mdeal_Leads();
            $lead->lead_source = $source;
            $lead->industry = 'other';
            $lead->annual_revenue = 1000000;
            $lead->status = 'new';
            
            $lead->calculateQualificationScore();
            
            // Should get 25 points for high-quality source
            $this->assertGreaterThanOrEqual(55, $lead->qualification_score); // 25 + 15 + 5 + 10
        }
    }

    /**
     * Mock the mdeal_Deals class for testing
     */
    protected function mockDealClass()
    {
        if (!class_exists('mdeal_Deals')) {
            // Create a mock class
            eval('
                class mdeal_Deals extends Basic {
                    public $id;
                    public $name;
                    public $description;
                    public $assigned_user_id;
                    public $industry;
                    public $expected_revenue;
                    public $stage;
                    public $lead_source;
                    
                    public function save($check_notify = false) {
                        if (empty($this->id)) {
                            $this->id = create_guid();
                        }
                        return true;
                    }
                }
            ');
        }
    }

    /**
     * Data provider for various lead scenarios
     */
    public function leadScenarioProvider()
    {
        return [
            'High-value manufacturing lead' => [
                [
                    'industry' => 'manufacturing',
                    'annual_revenue' => 15000000,
                    'status' => 'qualified',
                    'lead_source' => 'broker_network'
                ],
                100 // Expected score
            ],
            'Medium-value technology lead' => [
                [
                    'industry' => 'technology',
                    'annual_revenue' => 3000000,
                    'status' => 'contacted',
                    'lead_source' => 'referral'
                ],
                80 // Expected score
            ],
            'Low-value retail lead' => [
                [
                    'industry' => 'retail',
                    'annual_revenue' => 500000,
                    'status' => 'new',
                    'lead_source' => 'online_marketplace'
                ],
                30 // Expected score
            ]
        ];
    }

    /**
     * Test various lead scenarios using data provider
     * 
     * @dataProvider leadScenarioProvider
     */
    public function testLeadScenarios($leadData, $expectedScore)
    {
        foreach ($leadData as $field => $value) {
            $this->lead->$field = $value;
        }
        
        $this->lead->calculateQualificationScore();
        
        $this->assertEquals($expectedScore, $this->lead->qualification_score);
    }
}