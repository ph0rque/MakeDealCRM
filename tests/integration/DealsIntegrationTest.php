<?php
/**
 * Integration tests for Deals module
 * Tests the complete deal management and pipeline workflow
 */

use PHPUnit\Framework\TestCase;

class DealsIntegrationTest extends TestCase
{
    protected $testData;
    protected $createdRecords;
    protected $automationEngine;
    protected $validationManager;
    
    protected function setUp(): void
    {
        global $current_user, $db;
        
        // Set up test user
        if (empty($current_user)) {
            $current_user = new User();
            $current_user->id = create_guid();
            $current_user->user_name = 'deals_test_user';
            $current_user->first_name = 'Deals';
            $current_user->last_name = 'Test';
        }
        
        // Initialize engines
        require_once('custom/modules/Pipelines/PipelineAutomationEngine.php');
        require_once('custom/modules/Pipelines/StageValidationManager.php');
        
        $this->automationEngine = new PipelineAutomationEngine();
        $this->validationManager = new StageValidationManager();
        
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
            'deal' => [
                'name' => 'Test Acquisition Deal',
                'company_name' => 'Target Corporation',
                'stage' => 'sourcing',
                'deal_value' => 50000000,
                'annual_revenue' => 40000000,
                'ebitda' => 8000000,
                'employee_count' => 200,
                'industry' => 'Technology',
                'deal_source' => 'proprietary',
                'probability' => 20,
                'target_close_date' => date('Y-m-d', strtotime('+6 months'))
            ],
            'account' => [
                'name' => 'Target Corporation',
                'account_type' => 'target',
                'industry' => 'Technology',
                'annual_revenue' => 40000000,
                'employee_count' => 200
            ],
            'contact' => [
                'first_name' => 'John',
                'last_name' => 'CEO',
                'title' => 'Chief Executive Officer',
                'email1' => 'john.ceo@target.com',
                'decision_role' => 'decision_maker'
            ]
        ];
    }
    
    /**
     * Test deal creation with all relationships
     */
    public function testDealCreationWithRelationships()
    {
        // Create account
        $account = BeanFactory::newBean('mdeal_Accounts');
        foreach ($this->testData['account'] as $field => $value) {
            $account->$field = $value;
        }
        $account->save();
        $this->createdRecords['accounts'][] = $account->id;
        
        // Create contact
        $contact = BeanFactory::newBean('mdeal_Contacts');
        foreach ($this->testData['contact'] as $field => $value) {
            $contact->$field = $value;
        }
        $contact->account_id = $account->id;
        $contact->save();
        $this->createdRecords['contacts'][] = $contact->id;
        
        // Create deal
        $deal = BeanFactory::newBean('mdeal_Deals');
        foreach ($this->testData['deal'] as $field => $value) {
            $deal->$field = $value;
        }
        $deal->account_id = $account->id;
        $deal->save();
        $this->createdRecords['deals'][] = $deal->id;
        
        // Verify deal was created
        $this->assertNotEmpty($deal->id);
        
        // Reload and verify data
        $savedDeal = BeanFactory::getBean('mdeal_Deals', $deal->id);
        $this->assertEquals($this->testData['deal']['name'], $savedDeal->name);
        $this->assertEquals($this->testData['deal']['deal_value'], $savedDeal->deal_value);
        $this->assertEquals($account->id, $savedDeal->account_id);
        
        // Verify defaults
        $this->assertEquals('sourcing', $savedDeal->stage);
        $this->assertNotEmpty($savedDeal->stage_entered_date);
        $this->assertEquals(0, $savedDeal->days_in_stage);
        
        return [$deal, $account, $contact];
    }
    
    /**
     * Test stage progression through pipeline
     */
    public function testStageProgression()
    {
        list($deal, $account, $contact) = $this->testDealCreationWithRelationships();
        
        $stages = [
            'sourcing' => 'screening',
            'screening' => 'analysis_outreach',
            'analysis_outreach' => 'term_sheet',
            'term_sheet' => 'due_diligence'
        ];
        
        foreach ($stages as $fromStage => $toStage) {
            // Add required fields for next stage
            $this->addRequiredFieldsForStage($deal, $toStage);
            
            // Validate transition
            $validation = $this->automationEngine->validateStageTransition($deal, $fromStage, $toStage);
            
            $this->assertTrue($validation['allowed'], 
                "Should allow transition from {$fromStage} to {$toStage}");
            
            // Execute transition
            $result = $this->automationEngine->executeStageTransition($deal, $toStage);
            
            $this->assertTrue($result['success']);
            
            // Reload deal
            $deal = BeanFactory::getBean('mdeal_Deals', $deal->id);
            $this->assertEquals($toStage, $deal->stage);
            
            // Verify stage tracking
            $this->assertNotEmpty($deal->stage_entered_date);
            $this->assertEquals(0, $deal->days_in_stage);
            
            // Verify auto-tasks were created
            $this->verifyAutoTasksCreated($deal, $toStage);
        }
    }
    
    /**
     * Test deal health score calculation
     */
    public function testDealHealthScoreCalculation()
    {
        list($deal, $account, $contact) = $this->testDealCreationWithRelationships();
        
        // Progress to later stage
        $deal->stage = 'term_sheet';
        $deal->probability = 50;
        $deal->days_in_stage = 10;
        $deal->save();
        
        // Add recent activity
        $deal->last_activity_date = date('Y-m-d');
        $deal->save();
        
        // Calculate health score
        require_once('custom/modules/mdeal_Deals/DealLogicHooks.php');
        $hooks = new DealLogicHooks();
        $hooks->calculateDealHealth($deal, 'after_save', []);
        
        // Reload deal
        $deal = BeanFactory::getBean('mdeal_Deals', $deal->id);
        
        // Verify score was calculated
        $this->assertGreaterThan(0, $deal->health_score);
        $this->assertLessThanOrEqual(100, $deal->health_score);
        
        // Good stage progression and recent activity should give good score
        $this->assertGreaterThanOrEqual(60, $deal->health_score);
    }
    
    /**
     * Test deal valuation calculations
     */
    public function testDealValuationCalculations()
    {
        list($deal, $account, $contact) = $this->testDealCreationWithRelationships();
        
        // Set valuation metrics
        $deal->annual_revenue = 50000000;
        $deal->ebitda = 10000000;
        $deal->deal_value = 150000000;
        $deal->save();
        
        // Calculate multiples
        $revenueMultiple = $deal->deal_value / $deal->annual_revenue;
        $this->assertEquals(3, $revenueMultiple);
        
        $ebitdaMultiple = $deal->deal_value / $deal->ebitda;
        $this->assertEquals(15, $ebitdaMultiple);
        
        // Update valuation fields
        $deal->revenue_multiple = $revenueMultiple;
        $deal->ebitda_multiple = $ebitdaMultiple;
        $deal->save();
        
        // Verify calculations
        $deal = BeanFactory::getBean('mdeal_Deals', $deal->id);
        $this->assertEquals(3, $deal->revenue_multiple);
        $this->assertEquals(15, $deal->ebitda_multiple);
    }
    
    /**
     * Test stale deal detection
     */
    public function testStaleDealDetection()
    {
        list($deal, $account, $contact) = $this->testDealCreationWithRelationships();
        
        // Simulate deal being in stage for too long
        $deal->stage_entered_date = date('Y-m-d H:i:s', strtotime('-90 days'));
        $deal->days_in_stage = 90;
        $deal->save();
        
        // Check staleness
        require_once('custom/modules/mdeal_Deals/DealLogicHooks.php');
        $hooks = new DealLogicHooks();
        $hooks->updateDaysInStage($deal, 'after_save', []);
        
        // Reload deal
        $deal = BeanFactory::getBean('mdeal_Deals', $deal->id);
        
        // Should be marked as stale (>60 days in sourcing)
        $this->assertEquals(1, $deal->is_stale);
        $this->assertNotEmpty($deal->stale_reason);
    }
    
    /**
     * Test deal activity tracking
     */
    public function testDealActivityTracking()
    {
        list($deal, $account, $contact) = $this->testDealCreationWithRelationships();
        
        // Create various activities
        $meeting = BeanFactory::newBean('Meetings');
        $meeting->name = 'Management Presentation';
        $meeting->parent_type = 'mdeal_Deals';
        $meeting->parent_id = $deal->id;
        $meeting->status = 'Held';
        $meeting->date_start = date('Y-m-d H:i:s');
        $meeting->save();
        $this->createdRecords['meetings'][] = $meeting->id;
        
        $call = BeanFactory::newBean('Calls');
        $call->name = 'Due Diligence Call';
        $call->parent_type = 'mdeal_Deals';
        $call->parent_id = $deal->id;
        $call->status = 'Held';
        $call->date_start = date('Y-m-d H:i:s');
        $call->save();
        $this->createdRecords['calls'][] = $call->id;
        
        $task = BeanFactory::newBean('Tasks');
        $task->name = 'Review Financial Statements';
        $task->parent_type = 'mdeal_Deals';
        $task->parent_id = $deal->id;
        $task->status = 'Completed';
        $task->save();
        $this->createdRecords['tasks'][] = $task->id;
        
        // Update activity metrics
        $deal->activity_count = 3;
        $deal->last_activity_date = date('Y-m-d');
        $deal->last_activity_type = 'meeting';
        $deal->save();
        
        // Verify activity tracking
        $deal = BeanFactory::getBean('mdeal_Deals', $deal->id);
        $this->assertEquals(3, $deal->activity_count);
        $this->assertEquals(date('Y-m-d'), $deal->last_activity_date);
    }
    
    /**
     * Test deal team management
     */
    public function testDealTeamManagement()
    {
        list($deal, $account, $contact) = $this->testDealCreationWithRelationships();
        
        // Create deal team members
        global $db;
        $teamRoles = [
            ['user_id' => create_guid(), 'role' => 'lead_partner'],
            ['user_id' => create_guid(), 'role' => 'analyst'],
            ['user_id' => create_guid(), 'role' => 'legal_counsel']
        ];
        
        foreach ($teamRoles as $member) {
            $id = create_guid();
            $query = "INSERT INTO mdeal_deal_team 
                      (id, deal_id, user_id, role, deleted) 
                      VALUES (?, ?, ?, ?, 0)";
            $db->pQuery($query, [$id, $deal->id, $member['user_id'], $member['role']]);
        }
        
        // Retrieve team
        $teamMembers = $this->getDealTeam($deal->id);
        $this->assertCount(3, $teamMembers);
        
        // Verify roles
        $roles = array_column($teamMembers, 'role');
        $this->assertContains('lead_partner', $roles);
        $this->assertContains('analyst', $roles);
        $this->assertContains('legal_counsel', $roles);
        
        // Clean up team
        $db->pQuery("UPDATE mdeal_deal_team SET deleted = 1 WHERE deal_id = ?", [$deal->id]);
    }
    
    /**
     * Test deal document management
     */
    public function testDealDocumentManagement()
    {
        list($deal, $account, $contact) = $this->testDealCreationWithRelationships();
        
        // Create documents
        $docTypes = ['nda', 'financial_statements', 'legal_due_diligence'];
        
        foreach ($docTypes as $type) {
            $doc = BeanFactory::newBean('Documents');
            $doc->document_name = ucwords(str_replace('_', ' ', $type));
            $doc->category_id = $type;
            $doc->status_id = 'Active';
            $doc->save();
            $this->createdRecords['documents'][] = $doc->id;
            
            // Link to deal
            global $db;
            $id = create_guid();
            $query = "INSERT INTO documents_mdeal_deals 
                      (id, document_id, mdeal_deals_id, date_modified, deleted) 
                      VALUES (?, ?, ?, NOW(), 0)";
            $db->pQuery($query, [$id, $doc->id, $deal->id]);
        }
        
        // Verify document count
        $documents = $this->getDealDocuments($deal->id);
        $this->assertCount(3, $documents);
    }
    
    /**
     * Test deal closing workflow
     */
    public function testDealClosingWorkflow()
    {
        list($deal, $account, $contact) = $this->testDealCreationWithRelationships();
        
        // Progress to closing stage
        $deal->stage = 'closing';
        $deal->closing_date = date('Y-m-d', strtotime('+30 days'));
        $deal->funding_confirmed = 1;
        $deal->all_approvals = 1;
        $deal->escrow_instructions = 'Standard escrow terms';
        $deal->save();
        
        // Complete closing checklist
        $checklistItems = [
            'legal_review_complete',
            'financing_secured',
            'regulatory_approvals',
            'board_approval',
            'closing_documents_signed'
        ];
        
        foreach ($checklistItems as $item) {
            $deal->$item = 1;
        }
        $deal->save();
        
        // Execute closing
        $result = $this->automationEngine->executeStageTransition($deal, 'closed_won');
        $this->assertTrue($result['success']);
        
        // Verify closed deal
        $deal = BeanFactory::getBean('mdeal_Deals', $deal->id);
        $this->assertEquals('closed_won', $deal->stage);
        $this->assertEquals(100, $deal->probability);
        $this->assertNotEmpty($deal->actual_close_date);
    }
    
    /**
     * Test deal loss tracking
     */
    public function testDealLossTracking()
    {
        list($deal, $account, $contact) = $this->testDealCreationWithRelationships();
        
        // Mark as lost
        $deal->stage = 'closed_lost';
        $deal->probability = 0;
        $deal->loss_reason = 'price_too_high';
        $deal->competitor_won = 'Competitor Corp';
        $deal->lessons_learned = 'Need to engage earlier in process';
        $deal->save();
        
        // Verify loss tracking
        $deal = BeanFactory::getBean('mdeal_Deals', $deal->id);
        $this->assertEquals('closed_lost', $deal->stage);
        $this->assertEquals(0, $deal->probability);
        $this->assertEquals('price_too_high', $deal->loss_reason);
        $this->assertNotEmpty($deal->competitor_won);
        $this->assertNotEmpty($deal->lessons_learned);
    }
    
    /**
     * Test deal search and filtering
     */
    public function testDealSearchAndFiltering()
    {
        // Create deals in different stages
        $stages = ['sourcing', 'screening', 'term_sheet', 'due_diligence', 'closed_won'];
        $values = [10000000, 25000000, 50000000, 75000000, 100000000];
        
        foreach ($stages as $i => $stage) {
            $deal = BeanFactory::newBean('mdeal_Deals');
            $deal->name = "Search Test Deal {$i}";
            $deal->company_name = "Company {$i}";
            $deal->stage = $stage;
            $deal->deal_value = $values[$i];
            $deal->save();
            $this->createdRecords['deals'][] = $deal->id;
        }
        
        global $db;
        
        // Test stage filter
        $query = "SELECT COUNT(*) as count FROM mdeal_deals 
                  WHERE stage = 'term_sheet' AND deleted = 0";
        $result = $db->query($query);
        $row = $db->fetchByAssoc($result);
        $this->assertGreaterThanOrEqual(1, $row['count']);
        
        // Test value filter
        $query = "SELECT COUNT(*) as count FROM mdeal_deals 
                  WHERE deal_value >= 50000000 AND deleted = 0";
        $result = $db->query($query);
        $row = $db->fetchByAssoc($result);
        $this->assertGreaterThanOrEqual(3, $row['count']);
        
        // Test active deals
        $query = "SELECT COUNT(*) as count FROM mdeal_deals 
                  WHERE stage NOT IN ('closed_won', 'closed_lost', 'unavailable') 
                  AND deleted = 0";
        $result = $db->query($query);
        $row = $db->fetchByAssoc($result);
        $this->assertGreaterThanOrEqual(4, $row['count']);
    }
    
    /**
     * Test deal bulk operations
     */
    public function testDealBulkOperations()
    {
        // Create multiple deals
        $dealIds = [];
        for ($i = 0; $i < 5; $i++) {
            $deal = BeanFactory::newBean('mdeal_Deals');
            $deal->name = "Bulk Test Deal {$i}";
            $deal->company_name = "Bulk Company {$i}";
            $deal->stage = 'sourcing';
            $deal->save();
            $dealIds[] = $deal->id;
            $this->createdRecords['deals'][] = $deal->id;
        }
        
        // Test bulk stage update
        $this->bulkUpdateDeals($dealIds, ['stage' => 'screening']);
        
        // Verify updates
        foreach ($dealIds as $id) {
            $deal = BeanFactory::getBean('mdeal_Deals', $id);
            $this->assertEquals('screening', $deal->stage);
        }
        
        // Test bulk assignment
        $newUserId = create_guid();
        $this->bulkUpdateDeals($dealIds, ['assigned_user_id' => $newUserId]);
        
        // Verify assignments
        foreach ($dealIds as $id) {
            $deal = BeanFactory::getBean('mdeal_Deals', $id);
            $this->assertEquals($newUserId, $deal->assigned_user_id);
        }
    }
    
    // Helper methods
    
    protected function addRequiredFieldsForStage($deal, $stage)
    {
        $requirements = $this->validationManager->getStageRequirements($stage);
        $requiredFields = $requirements['required_fields'] ?? [];
        
        foreach ($requiredFields as $field => $description) {
            if (empty($deal->$field)) {
                switch ($field) {
                    case 'primary_contact':
                        $deal->$field = 'John Smith';
                        break;
                    case 'decision_maker':
                        $deal->$field = 'Jane Doe';
                        break;
                    case 'key_stakeholders':
                        $deal->$field = 'Board of Directors';
                        break;
                    case 'valuation_range':
                        $deal->$field = '$40M-$60M';
                        break;
                    case 'deal_structure':
                        $deal->$field = 'Asset Purchase';
                        break;
                    case 'key_terms':
                        $deal->$field = 'Standard terms with earnout';
                        break;
                    case 'financing_source':
                        $deal->$field = 'Internal funds';
                        break;
                    case 'dd_checklist':
                        $deal->$field = 'Standard DD checklist';
                        break;
                    case 'external_advisors':
                        $deal->$field = 'Legal: Smith & Co, Financial: ABC Advisory';
                        break;
                    case 'data_room_access':
                        $deal->$field = 1;
                        break;
                    case 'timeline_agreed':
                        $deal->$field = 1;
                        break;
                    default:
                        $deal->$field = 'Test value';
                }
            }
        }
        
        $deal->save();
    }
    
    protected function verifyAutoTasksCreated($deal, $stage)
    {
        global $db;
        
        $query = "SELECT COUNT(*) as count FROM tasks 
                  WHERE parent_type = 'mdeal_Deals' 
                  AND parent_id = ? 
                  AND deleted = 0";
        
        $result = $db->pQuery($query, [$deal->id]);
        $row = $db->fetchByAssoc($result);
        
        $this->assertGreaterThan(0, $row['count'], 
            "Auto-tasks should be created for stage {$stage}");
    }
    
    protected function getDealTeam($dealId)
    {
        global $db;
        $query = "SELECT * FROM mdeal_deal_team WHERE deal_id = ? AND deleted = 0";
        $result = $db->pQuery($query, [$dealId]);
        
        $team = [];
        while ($row = $db->fetchByAssoc($result)) {
            $team[] = $row;
        }
        return $team;
    }
    
    protected function getDealDocuments($dealId)
    {
        global $db;
        $query = "SELECT d.* FROM documents d 
                  JOIN documents_mdeal_deals dd ON d.id = dd.document_id 
                  WHERE dd.mdeal_deals_id = ? 
                  AND dd.deleted = 0 
                  AND d.deleted = 0";
        $result = $db->pQuery($query, [$dealId]);
        
        $documents = [];
        while ($row = $db->fetchByAssoc($result)) {
            $documents[] = $row;
        }
        return $documents;
    }
    
    protected function bulkUpdateDeals($dealIds, $updates)
    {
        foreach ($dealIds as $id) {
            $deal = BeanFactory::getBean('mdeal_Deals', $id);
            foreach ($updates as $field => $value) {
                $deal->$field = $value;
            }
            $deal->save();
        }
    }
    
    protected function cleanupTestData()
    {
        global $db;
        
        $tables = [
            'deals' => 'mdeal_deals',
            'accounts' => 'mdeal_accounts',
            'contacts' => 'mdeal_contacts',
            'meetings' => 'meetings',
            'calls' => 'calls',
            'tasks' => 'tasks',
            'documents' => 'documents'
        ];
        
        foreach ($tables as $type => $table) {
            if (!empty($this->createdRecords[$type])) {
                foreach ($this->createdRecords[$type] as $id) {
                    $query = "UPDATE {$table} SET deleted = 1 WHERE id = ?";
                    $db->pQuery($query, [$id]);
                }
            }
        }
        
        // Clean up relationships
        if (!empty($this->createdRecords['deals'])) {
            $dealIds = implode("','", $this->createdRecords['deals']);
            $db->query("UPDATE mdeal_deal_team SET deleted = 1 WHERE deal_id IN ('{$dealIds}')");
            $db->query("UPDATE documents_mdeal_deals SET deleted = 1 WHERE mdeal_deals_id IN ('{$dealIds}')");
            $db->query("UPDATE mdeal_pipeline_transitions SET deleted = 1 WHERE deal_id IN ('{$dealIds}')");
            $db->query("UPDATE tasks SET deleted = 1 WHERE parent_type = 'mdeal_Deals' AND parent_id IN ('{$dealIds}')");
        }
    }
}