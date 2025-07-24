<?php
/**
 * Integration tests for Leads module
 * Tests the complete lead management workflow
 */

use PHPUnit\Framework\TestCase;

class LeadsIntegrationTest extends TestCase
{
    protected $testData;
    protected $createdRecords;
    
    protected function setUp(): void
    {
        global $current_user, $db;
        
        // Set up test user
        if (empty($current_user)) {
            $current_user = new User();
            $current_user->id = create_guid();
            $current_user->user_name = 'leads_test_user';
            $current_user->first_name = 'Leads';
            $current_user->last_name = 'Test';
        }
        
        $this->createdRecords = [];
        $this->setupTestData();
    }
    
    protected function tearDown(): void
    {
        $this->cleanupTestData();
    }
    
    protected function setupTestData()
    {
        $this->testData = [
            'company_name' => 'Test Corporation',
            'industry' => 'Technology',
            'annual_revenue' => 50000000,
            'employee_count' => 250,
            'primary_contact_name' => 'John Test',
            'primary_contact_email' => 'john@testcorp.com',
            'phone_office' => '555-0001',
            'website' => 'https://testcorp.com',
            'geographic_region' => 'North America',
            'lead_source' => 'referral',
            'status' => 'new',
            'growth_rate' => 35,
            'ebitda' => 10000000,
            'urgency_level' => 'within_3_months',
            'budget_status' => 'confirmed'
        ];
    }
    
    /**
     * Test lead creation and field validation
     */
    public function testLeadCreation()
    {
        $lead = BeanFactory::newBean('mdeal_Leads');
        
        foreach ($this->testData as $field => $value) {
            $lead->$field = $value;
        }
        
        $lead->save();
        $this->createdRecords['leads'][] = $lead->id;
        
        // Verify lead was created
        $this->assertNotEmpty($lead->id);
        
        // Reload and verify data
        $savedLead = BeanFactory::getBean('mdeal_Leads', $lead->id);
        $this->assertEquals($this->testData['company_name'], $savedLead->company_name);
        $this->assertEquals($this->testData['annual_revenue'], $savedLead->annual_revenue);
        
        // Verify defaults
        $this->assertEquals('new', $savedLead->status);
        $this->assertEquals(0, $savedLead->lead_score);
        
        return $lead;
    }
    
    /**
     * Test lead scoring calculation
     */
    public function testLeadScoringCalculation()
    {
        $lead = $this->testLeadCreation();
        
        // Manually trigger scoring calculation
        require_once('custom/modules/mdeal_Leads/LeadLogicHooks.php');
        $hooks = new LeadLogicHooks();
        $hooks->calculateLeadScore($lead, 'after_save', []);
        
        // Reload lead
        $lead = BeanFactory::getBean('mdeal_Leads', $lead->id);
        
        // Verify score was calculated
        $this->assertGreaterThan(0, $lead->lead_score);
        $this->assertLessThanOrEqual(100, $lead->lead_score);
        
        // With good metrics, should have high score
        $this->assertGreaterThanOrEqual(70, $lead->lead_score);
        
        // Verify sub-scores
        $this->assertGreaterThan(0, $lead->company_size_score);
        $this->assertGreaterThan(0, $lead->financial_health_score);
    }
    
    /**
     * Test lead status transitions
     */
    public function testLeadStatusTransitions()
    {
        $lead = $this->testLeadCreation();
        
        // Test valid transitions
        $validTransitions = [
            'new' => 'qualifying',
            'qualifying' => 'qualified',
            'qualified' => 'proposal',
            'proposal' => 'converted'
        ];
        
        foreach ($validTransitions as $from => $to) {
            $lead->status = $from;
            $lead->save();
            
            $lead->status = $to;
            $lead->save();
            
            $this->assertEquals($to, $lead->status);
        }
        
        // Test invalid transition
        $lead->status = 'new';
        $lead->save();
        
        // Try to skip stages
        $lead->status = 'converted';
        $hooks = new LeadLogicHooks();
        $hooks->validateStatusTransition($lead, 'before_save', []);
        
        // Should prevent invalid transition
        $this->assertNotEquals('converted', $lead->status);
    }
    
    /**
     * Test lead to deal conversion
     */
    public function testLeadToDealConversion()
    {
        require_once('custom/modules/Pipelines/LeadConversionEngine.php');
        $conversionEngine = new LeadConversionEngine();
        
        // Create high-scoring lead
        $lead = $this->testLeadCreation();
        $lead->lead_score = 85;
        $lead->save();
        
        // Test conversion
        $result = $conversionEngine->convertLead($lead);
        
        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['deal_id']);
        $this->assertNotEmpty($result['account_id']);
        $this->assertNotEmpty($result['contact_id']);
        
        // Track created records
        $this->createdRecords['deals'][] = $result['deal_id'];
        $this->createdRecords['accounts'][] = $result['account_id'];
        $this->createdRecords['contacts'][] = $result['contact_id'];
        
        // Verify lead status
        $lead = BeanFactory::getBean('mdeal_Leads', $lead->id);
        $this->assertEquals('converted', $lead->status);
        $this->assertEquals($result['deal_id'], $lead->converted_deal_id);
        
        // Verify created records
        $deal = BeanFactory::getBean('mdeal_Deals', $result['deal_id']);
        $this->assertEquals($lead->company_name, $deal->company_name);
        $this->assertEquals('sourcing', $deal->stage);
        
        $account = BeanFactory::getBean('mdeal_Accounts', $result['account_id']);
        $this->assertEquals($lead->company_name, $account->name);
        
        $contact = BeanFactory::getBean('mdeal_Contacts', $result['contact_id']);
        $this->assertEquals($lead->primary_contact_email, $contact->email1);
    }
    
    /**
     * Test lead search and filtering
     */
    public function testLeadSearchAndFiltering()
    {
        // Create multiple test leads
        $industries = ['Technology', 'Healthcare', 'Finance'];
        $scores = [30, 60, 85];
        
        foreach ($industries as $i => $industry) {
            $lead = BeanFactory::newBean('mdeal_Leads');
            $lead->company_name = "Test {$industry} Corp";
            $lead->industry = $industry;
            $lead->lead_score = $scores[$i];
            $lead->status = $i === 0 ? 'new' : ($i === 1 ? 'qualifying' : 'qualified');
            $lead->save();
            $this->createdRecords['leads'][] = $lead->id;
        }
        
        global $db;
        
        // Test industry filter
        $query = "SELECT id FROM mdeal_leads WHERE industry = 'Technology' AND deleted = 0";
        $result = $db->query($query);
        $count = 0;
        while ($db->fetchByAssoc($result)) {
            $count++;
        }
        $this->assertGreaterThanOrEqual(1, $count);
        
        // Test score filter
        $query = "SELECT id FROM mdeal_leads WHERE lead_score >= 60 AND deleted = 0";
        $result = $db->query($query);
        $count = 0;
        while ($db->fetchByAssoc($result)) {
            $count++;
        }
        $this->assertGreaterThanOrEqual(2, $count);
        
        // Test status filter
        $query = "SELECT id FROM mdeal_leads WHERE status = 'qualified' AND deleted = 0";
        $result = $db->query($query);
        $count = 0;
        while ($db->fetchByAssoc($result)) {
            $count++;
        }
        $this->assertGreaterThanOrEqual(1, $count);
    }
    
    /**
     * Test lead duplicate detection
     */
    public function testLeadDuplicateDetection()
    {
        $lead1 = $this->testLeadCreation();
        
        // Try to create duplicate
        $lead2 = BeanFactory::newBean('mdeal_Leads');
        $lead2->company_name = $this->testData['company_name'];
        $lead2->primary_contact_email = $this->testData['primary_contact_email'];
        
        // Check for duplicates
        $duplicates = $this->findDuplicateLeads($lead2);
        
        $this->assertCount(1, $duplicates);
        $this->assertEquals($lead1->id, $duplicates[0]['id']);
    }
    
    /**
     * Test lead assignment and ownership
     */
    public function testLeadAssignment()
    {
        global $current_user;
        
        $lead = $this->testLeadCreation();
        
        // Verify initial assignment
        $this->assertEquals($current_user->id, $lead->assigned_user_id);
        $this->assertEquals($current_user->id, $lead->created_by);
        
        // Test reassignment
        $newUserId = create_guid();
        $lead->assigned_user_id = $newUserId;
        $lead->save();
        
        $lead = BeanFactory::getBean('mdeal_Leads', $lead->id);
        $this->assertEquals($newUserId, $lead->assigned_user_id);
    }
    
    /**
     * Test lead activity tracking
     */
    public function testLeadActivityTracking()
    {
        $lead = $this->testLeadCreation();
        
        // Create activities
        $call = BeanFactory::newBean('Calls');
        $call->name = 'Discovery Call';
        $call->parent_type = 'mdeal_Leads';
        $call->parent_id = $lead->id;
        $call->status = 'Planned';
        $call->save();
        $this->createdRecords['calls'][] = $call->id;
        
        $meeting = BeanFactory::newBean('Meetings');
        $meeting->name = 'Initial Meeting';
        $meeting->parent_type = 'mdeal_Leads';
        $meeting->parent_id = $lead->id;
        $meeting->status = 'Planned';
        $meeting->save();
        $this->createdRecords['meetings'][] = $meeting->id;
        
        // Update interaction count
        $lead->interaction_count = 2;
        $lead->last_interaction_date = date('Y-m-d');
        $lead->save();
        
        // Verify activity tracking
        $lead = BeanFactory::getBean('mdeal_Leads', $lead->id);
        $this->assertEquals(2, $lead->interaction_count);
        $this->assertEquals(date('Y-m-d'), $lead->last_interaction_date);
    }
    
    /**
     * Test lead metrics calculation
     */
    public function testLeadMetricsCalculation()
    {
        $lead = $this->testLeadCreation();
        
        // Calculate EBITDA margin
        $margin = ($lead->ebitda / $lead->annual_revenue) * 100;
        $this->assertEquals(20, $margin); // 10M / 50M = 20%
        
        // Calculate revenue per employee
        $revenuePerEmployee = $lead->annual_revenue / $lead->employee_count;
        $this->assertEquals(200000, $revenuePerEmployee);
        
        // Verify growth rate
        $this->assertEquals(35, $lead->growth_rate);
    }
    
    /**
     * Test lead bulk operations
     */
    public function testLeadBulkOperations()
    {
        // Create multiple leads
        $leadIds = [];
        for ($i = 0; $i < 5; $i++) {
            $lead = BeanFactory::newBean('mdeal_Leads');
            $lead->company_name = "Bulk Test Company {$i}";
            $lead->status = 'new';
            $lead->save();
            $leadIds[] = $lead->id;
            $this->createdRecords['leads'][] = $lead->id;
        }
        
        // Test bulk status update
        $this->bulkUpdateLeads($leadIds, ['status' => 'qualifying']);
        
        // Verify updates
        foreach ($leadIds as $id) {
            $lead = BeanFactory::getBean('mdeal_Leads', $id);
            $this->assertEquals('qualifying', $lead->status);
        }
        
        // Test bulk assignment
        $newUserId = create_guid();
        $this->bulkUpdateLeads($leadIds, ['assigned_user_id' => $newUserId]);
        
        // Verify assignments
        foreach ($leadIds as $id) {
            $lead = BeanFactory::getBean('mdeal_Leads', $id);
            $this->assertEquals($newUserId, $lead->assigned_user_id);
        }
    }
    
    // Helper methods
    
    protected function findDuplicateLeads($lead)
    {
        global $db;
        
        $query = "SELECT id, company_name, primary_contact_email 
                  FROM mdeal_leads 
                  WHERE deleted = 0 
                  AND (company_name = ? OR primary_contact_email = ?)
                  AND id != ?";
        
        $result = $db->pQuery($query, [
            $lead->company_name,
            $lead->primary_contact_email,
            $lead->id ?: ''
        ]);
        
        $duplicates = [];
        while ($row = $db->fetchByAssoc($result)) {
            $duplicates[] = $row;
        }
        
        return $duplicates;
    }
    
    protected function bulkUpdateLeads($leadIds, $updates)
    {
        foreach ($leadIds as $id) {
            $lead = BeanFactory::getBean('mdeal_Leads', $id);
            foreach ($updates as $field => $value) {
                $lead->$field = $value;
            }
            $lead->save();
        }
    }
    
    protected function cleanupTestData()
    {
        global $db;
        
        $tables = [
            'leads' => 'mdeal_leads',
            'deals' => 'mdeal_deals',
            'accounts' => 'mdeal_accounts',
            'contacts' => 'mdeal_contacts',
            'calls' => 'calls',
            'meetings' => 'meetings'
        ];
        
        foreach ($tables as $type => $table) {
            if (!empty($this->createdRecords[$type])) {
                foreach ($this->createdRecords[$type] as $id) {
                    $query = "UPDATE {$table} SET deleted = 1 WHERE id = ?";
                    $db->pQuery($query, [$id]);
                }
            }
        }
    }
}