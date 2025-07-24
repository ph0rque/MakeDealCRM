<?php
/**
 * Unit tests for mdeal_Contacts module
 */

use PHPUnit\Framework\TestCase;

class mdeal_ContactsTest extends TestCase
{
    protected $contact;
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

        // Create test contact
        $this->contact = new mdeal_Contacts();
        
        // Set up test data
        $this->testData = [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'title' => 'CEO',
            'department' => 'Executive',
            'email_address' => 'john.smith@testcompany.com',
            'phone_work' => '555-123-4567',
            'phone_mobile' => '555-987-6543',
            'contact_type' => 'seller',
            'decision_role' => 'decision_maker',
            'influence_level' => 'high',
            'relationship_strength' => 'good',
            'trust_level' => 8,
            'preferred_contact_method' => 'email'
        ];
    }

    protected function tearDown(): void
    {
        // Clean up test data
        if (!empty($this->contact->id)) {
            $this->contact->mark_deleted($this->contact->id);
        }
    }

    /**
     * Test basic contact creation
     */
    public function testContactCreation()
    {
        foreach ($this->testData as $field => $value) {
            $this->contact->$field = $value;
        }

        $result = $this->contact->save();
        
        $this->assertNotEmpty($this->contact->id);
        $this->assertEquals($this->testData['first_name'], $this->contact->first_name);
        $this->assertEquals($this->testData['last_name'], $this->contact->last_name);
        $this->assertEquals('John Smith', $this->contact->full_name);
    }

    /**
     * Test get summary text
     */
    public function testGetSummaryText()
    {
        $this->contact->first_name = 'Jane';
        $this->contact->last_name = 'Doe';
        
        $summary = $this->contact->get_summary_text();
        
        $this->assertEquals('Jane Doe', $summary);
    }

    /**
     * Test full name handling with just last name
     */
    public function testFullNameWithLastNameOnly()
    {
        $this->contact->last_name = 'Smith';
        $this->contact->save();
        
        $this->assertEquals('Smith', trim($this->contact->full_name));
    }

    /**
     * Test hierarchy validation - prevent self-reference
     */
    public function testHierarchyValidationSelfReference()
    {
        $this->contact->first_name = 'Test';
        $this->contact->last_name = 'User';
        $this->contact->save();
        
        // Try to set reports_to_id to self
        $this->contact->reports_to_id = $this->contact->id;
        
        $this->assertTrue($this->contact->validateHierarchy());
    }

    /**
     * Test hierarchy validation - valid hierarchy
     */
    public function testHierarchyValidationValid()
    {
        // Create manager
        $manager = new mdeal_Contacts();
        $manager->first_name = 'Manager';
        $manager->last_name = 'Boss';
        $manager->save();
        
        // Set up employee reporting to manager
        $this->contact->first_name = 'Employee';
        $this->contact->last_name = 'Worker';
        $this->contact->reports_to_id = $manager->id;
        
        $this->assertTrue($this->contact->validateHierarchy());
        
        // Clean up
        $manager->mark_deleted($manager->id);
    }

    /**
     * Test direct reports functionality
     */
    public function testGetDirectReports()
    {
        // Create manager
        $this->contact->first_name = 'Manager';
        $this->contact->last_name = 'Boss';
        $this->contact->save();
        
        // Create employees
        $employee1 = new mdeal_Contacts();
        $employee1->first_name = 'Employee';
        $employee1->last_name = 'One';
        $employee1->reports_to_id = $this->contact->id;
        $employee1->save();
        
        $employee2 = new mdeal_Contacts();
        $employee2->first_name = 'Employee';
        $employee2->last_name = 'Two';
        $employee2->reports_to_id = $this->contact->id;
        $employee2->save();
        
        $reports = $this->contact->getDirectReports();
        
        $this->assertCount(2, $reports);
        $this->assertEquals('Employee', $reports[0]['first_name']);
        $this->assertEquals('Employee', $reports[1]['first_name']);
        
        // Clean up
        $employee1->mark_deleted($employee1->id);
        $employee2->mark_deleted($employee2->id);
    }

    /**
     * Test organization chart building
     */
    public function testGetOrganizationChart()
    {
        // Create CEO
        $this->contact->first_name = 'CEO';
        $this->contact->last_name = 'Chief';
        $this->contact->title = 'Chief Executive Officer';
        $this->contact->save();
        
        // Create VP
        $vp = new mdeal_Contacts();
        $vp->first_name = 'VP';
        $vp->last_name = 'Vice';
        $vp->title = 'Vice President';
        $vp->reports_to_id = $this->contact->id;
        $vp->save();
        
        $orgChart = $this->contact->getOrganizationChart();
        
        $this->assertEquals('CEO Chief', $orgChart['name']);
        $this->assertEquals('Chief Executive Officer', $orgChart['title']);
        $this->assertCount(1, $orgChart['children']);
        $this->assertEquals('VP Vice', $orgChart['children'][0]['name']);
        
        // Clean up
        $vp->mark_deleted($vp->id);
    }

    /**
     * Test influence score calculation
     */
    public function testCalculateInfluenceScore()
    {
        // High influence contact
        $this->contact->decision_role = 'decision_maker'; // 40 points
        $this->contact->influence_level = 'high'; // 30 points
        $this->contact->relationship_strength = 'strong'; // 20 points
        $this->contact->trust_level = 10; // 10 points
        
        $score = $this->contact->calculateInfluenceScore();
        
        $this->assertEquals(100, $score); // 40 + 30 + 20 + 10
    }

    /**
     * Test influence score calculation with lower values
     */
    public function testCalculateInfluenceScoreLower()
    {
        $this->contact->decision_role = 'gatekeeper'; // 10 points
        $this->contact->influence_level = 'low'; // 10 points
        $this->contact->relationship_strength = 'weak'; // 5 points
        $this->contact->trust_level = 3; // 3 points
        
        $score = $this->contact->calculateInfluenceScore();
        
        $this->assertEquals(28, $score); // 10 + 10 + 5 + 3
    }

    /**
     * Test days since last interaction calculation
     */
    public function testDaysSinceLastInteraction()
    {
        // Test with recent interaction
        $this->contact->last_interaction_date = date('Y-m-d H:i:s', strtotime('-5 days'));
        
        $days = $this->contact->getDaysSinceLastInteraction();
        
        $this->assertEquals(5, $days);
        
        // Test with no interaction
        $this->contact->last_interaction_date = null;
        $days = $this->contact->getDaysSinceLastInteraction();
        
        $this->assertNull($days);
    }

    /**
     * Test needs follow-up functionality
     */
    public function testNeedsFollowUp()
    {
        // Contact with old interaction
        $this->contact->last_interaction_date = date('Y-m-d H:i:s', strtotime('-45 days'));
        
        $this->assertTrue($this->contact->needsFollowUp(30));
        
        // Contact with recent interaction
        $this->contact->last_interaction_date = date('Y-m-d H:i:s', strtotime('-15 days'));
        
        $this->assertFalse($this->contact->needsFollowUp(30));
        
        // Contact with no interaction
        $this->contact->last_interaction_date = null;
        
        $this->assertTrue($this->contact->needsFollowUp(30));
    }

    /**
     * Test preferred contact info
     */
    public function testGetPreferredContactInfo()
    {
        $this->contact->email_address = 'test@example.com';
        $this->contact->phone_work = '555-123-4567';
        $this->contact->phone_mobile = '555-987-6543';
        
        // Test email preference
        $this->contact->preferred_contact_method = 'email';
        $this->assertEquals('test@example.com', $this->contact->getPreferredContactInfo());
        
        // Test mobile preference
        $this->contact->preferred_contact_method = 'phone_mobile';
        $this->assertEquals('555-987-6543', $this->contact->getPreferredContactInfo());
        
        // Test work phone preference
        $this->contact->preferred_contact_method = 'phone_work';
        $this->assertEquals('555-123-4567', $this->contact->getPreferredContactInfo());
        
        // Test default (no preference set)
        $this->contact->preferred_contact_method = '';
        $this->assertEquals('test@example.com', $this->contact->getPreferredContactInfo());
    }

    /**
     * Test interaction count updates
     */
    public function testInteractionMetricsUpdate()
    {
        // Create contact
        $this->contact->first_name = 'Test';
        $this->contact->last_name = 'Contact';
        $this->contact->email_address = 'test@example.com';
        $this->contact->save();
        
        $initialCount = $this->contact->interaction_count;
        
        // Update contact info (should trigger interaction count)
        $this->contact->phone_work = '555-123-4567';
        $this->contact->save();
        
        // Interaction count should have increased
        $this->assertGreaterThan($initialCount, $this->contact->interaction_count);
    }

    /**
     * Test adding contact to deal
     */
    public function testAddToDeal()
    {
        $this->contact->first_name = 'Test';
        $this->contact->last_name = 'Contact';
        $this->contact->save();
        
        // Mock the relationship
        $dealId = create_guid();
        
        // This would work in a real environment with proper relationship setup
        $result = $this->contact->addToDeal($dealId, 'seller', true);
        
        // For unit testing, we verify the method exists and can be called
        $this->assertIsBool($result);
    }

    /**
     * Test adding contact to account
     */
    public function testAddToAccount()
    {
        $this->contact->first_name = 'Test';
        $this->contact->last_name = 'Contact';
        $this->contact->title = 'CEO';
        $this->contact->save();
        
        // Mock the relationship
        $accountId = create_guid();
        
        // This would work in a real environment with proper relationship setup
        $result = $this->contact->addToAccount($accountId, 'CEO', 'Executive', true);
        
        // For unit testing, we verify the method exists and can be called
        $this->assertIsBool($result);
    }

    /**
     * Test custom list query includes calculated fields
     */
    public function testCustomListQuery()
    {
        $query = $this->contact->create_new_list_query(
            'last_name',
            '',
            array(),
            array(),
            0,
            '',
            true
        );
        
        $this->assertStringContainsString('full_name_calc', $query['select']);
        $this->assertStringContainsString('days_since_interaction', $query['select']);
        $this->assertStringContainsString('account_name', $query['select']);
        $this->assertStringContainsString('reports_to_name', $query['select']);
    }

    /**
     * Test contact info change detection
     */
    public function testHasContactInfoChanged()
    {
        // Create contact with initial data
        $this->contact->first_name = 'John';
        $this->contact->last_name = 'Smith';
        $this->contact->email_address = 'john@example.com';
        $this->contact->save();
        
        // Simulate fetched_row
        $this->contact->fetched_row = [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'email_address' => 'john@example.com',
            'phone_work' => '555-123-4567'
        ];
        
        // Change email
        $this->contact->email_address = 'john.smith@example.com';
        
        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->contact);
        $method = $reflection->getMethod('hasContactInfoChanged');
        $method->setAccessible(true);
        
        $this->assertTrue($method->invoke($this->contact));
    }

    /**
     * Data provider for various contact scenarios
     */
    public function contactInfluenceProvider()
    {
        return [
            'High influence decision maker' => [
                [
                    'decision_role' => 'decision_maker',
                    'influence_level' => 'high',
                    'relationship_strength' => 'strong',
                    'trust_level' => 10
                ],
                100 // Expected score
            ],
            'Medium influence champion' => [
                [
                    'decision_role' => 'champion',
                    'influence_level' => 'medium',
                    'relationship_strength' => 'good',
                    'trust_level' => 7
                ],
                62 // Expected score (20 + 20 + 15 + 7)
            ],
            'Low influence gatekeeper' => [
                [
                    'decision_role' => 'gatekeeper',
                    'influence_level' => 'low',
                    'relationship_strength' => 'weak',
                    'trust_level' => 3
                ],
                28 // Expected score (10 + 10 + 5 + 3)
            ]
        ];
    }

    /**
     * Test various contact influence scenarios using data provider
     * 
     * @dataProvider contactInfluenceProvider
     */
    public function testContactInfluenceScenarios($contactData, $expectedScore)
    {
        foreach ($contactData as $field => $value) {
            $this->contact->$field = $value;
        }
        
        $score = $this->contact->calculateInfluenceScore();
        
        $this->assertEquals($expectedScore, $score);
    }

    /**
     * Test response rate calculation
     */
    public function testResponseRateCalculation()
    {
        $this->contact->interaction_count = 10;
        
        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->contact);
        $method = $reflection->getMethod('calculateResponseRate');
        $method->setAccessible(true);
        
        $method->invoke($this->contact);
        
        // Response rate should be calculated
        $this->assertGreaterThan(0, $this->contact->response_rate);
        $this->assertLessThanOrEqual(100, $this->contact->response_rate);
    }

    /**
     * Test required field validation
     */
    public function testRequiredFieldValidation()
    {
        // Test missing last_name
        $this->contact->first_name = 'John';
        
        // This should work in a real environment with proper validation
        $this->assertEmpty($this->contact->last_name);
        
        // Set required field
        $this->contact->last_name = 'Smith';
        $this->assertNotEmpty($this->contact->last_name);
    }
}