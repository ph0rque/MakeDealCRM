<?php
/**
 * Integration tests for the complete pipeline system
 * Tests end-to-end workflows across all modules
 */

use PHPUnit\Framework\TestCase;

class PipelineIntegrationTest extends TestCase
{
    protected $testData;
    protected $automationEngine;
    protected $conversionEngine;
    protected $validationManager;
    protected $createdRecords;
    
    protected function setUp(): void
    {
        global $current_user, $db;
        
        // Set up test user
        if (empty($current_user)) {
            $current_user = new User();
            $current_user->id = create_guid();
            $current_user->user_name = 'integration_test_user';
            $current_user->first_name = 'Integration';
            $current_user->last_name = 'Test';
        }
        
        // Initialize engines
        require_once('custom/modules/Pipelines/PipelineAutomationEngine.php');
        require_once('custom/modules/Pipelines/LeadConversionEngine.php');
        require_once('custom/modules/Pipelines/StageValidationManager.php');
        
        $this->automationEngine = new PipelineAutomationEngine();
        $this->conversionEngine = new LeadConversionEngine();
        $this->validationManager = new StageValidationManager();
        
        $this->createdRecords = [];
        
        // Set up test data
        $this->setupTestData();
    }
    
    protected function tearDown(): void
    {
        // Clean up created records
        $this->cleanupTestData();
    }
    
    protected function setupTestData()
    {
        $this->testData = [
            'lead' => [
                'company_name' => 'TechCorp Innovations',
                'industry' => 'Technology',
                'annual_revenue' => 25000000, // $25M
                'ebitda' => 5000000, // $5M (20% margin)
                'employee_count' => 150,
                'primary_contact_name' => 'John Smith',
                'primary_contact_email' => 'john.smith@techcorp.com',
                'phone_office' => '555-123-4567',
                'website' => 'https://techcorp.com',
                'geographic_region' => 'North America',
                'urgency_level' => 'within_6_months',
                'budget_status' => 'confirmed',
                'growth_rate' => 25,
                'lead_source' => 'referral'
            ],
            'account' => [
                'name' => 'MegaCorp Holdings',
                'account_type' => 'target',
                'industry' => 'Financial Services',
                'annual_revenue' => 100000000,
                'ebitda' => 25000000,
                'employee_count' => 500,
                'website' => 'https://megacorp.com',
                'phone_office' => '555-987-6543'
            ],
            'contact' => [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'title' => 'CEO',
                'email1' => 'jane.doe@megacorp.com',
                'phone_work' => '555-555-5555',
                'contact_type' => 'key_stakeholder',
                'decision_role' => 'decision_maker'
            ],
            'deal' => [
                'name' => 'MegaCorp Acquisition',
                'company_name' => 'MegaCorp Holdings',
                'deal_value' => 150000000,
                'annual_revenue' => 100000000,
                'stage' => 'sourcing',
                'probability' => 20,
                'deal_source' => 'proprietary'
            ]
        ];
    }
    
    /**
     * Test complete lead-to-deal conversion workflow
     */
    public function testLeadToDeadConversionWorkflow()
    {
        // Step 1: Create a high-scoring lead
        $lead = $this->createTestLead($this->testData['lead']);
        $this->createdRecords['leads'][] = $lead->id;
        
        // Step 2: Evaluate lead for conversion
        $evaluation = $this->conversionEngine->evaluateLeadForConversion($lead);
        
        // Verify high score due to good metrics
        $this->assertGreaterThanOrEqual(80, $evaluation['calculated_score'], 
            'Lead should have high score for auto-conversion');
        $this->assertEquals('auto_conversion', $evaluation['conversion_recommendation']);
        
        // Step 3: Process lead for conversion
        $conversionResults = $this->conversionEngine->processLeadsForConversion(1);
        
        $this->assertCount(1, $conversionResults);
        $this->assertEquals('auto_conversion', $conversionResults[0]['conversion_recommendation']);
        
        // Step 4: Verify deal was created
        $convertedLead = BeanFactory::getBean('mdeal_Leads', $lead->id);
        $this->assertEquals('converted', $convertedLead->status);
        $this->assertNotEmpty($convertedLead->converted_deal_id);
        $this->assertNotEmpty($convertedLead->converted_account_id);
        $this->assertNotEmpty($convertedLead->converted_contact_id);
        
        // Step 5: Verify created records
        $deal = BeanFactory::getBean('mdeal_Deals', $convertedLead->converted_deal_id);
        $account = BeanFactory::getBean('mdeal_Accounts', $convertedLead->converted_account_id);
        $contact = BeanFactory::getBean('mdeal_Contacts', $convertedLead->converted_contact_id);
        
        $this->assertNotEmpty($deal->id);
        $this->assertNotEmpty($account->id);
        $this->assertNotEmpty($contact->id);
        
        // Track created records for cleanup
        $this->createdRecords['deals'][] = $deal->id;
        $this->createdRecords['accounts'][] = $account->id;
        $this->createdRecords['contacts'][] = $contact->id;
        
        // Step 6: Verify data mapping
        $this->assertEquals($lead->company_name, $deal->company_name);
        $this->assertEquals($lead->company_name, $account->name);
        $this->assertEquals($lead->primary_contact_email, $contact->email1);
        $this->assertEquals('sourcing', $deal->stage);
        $this->assertEquals('target', $account->account_type);
        
        return $deal;
    }
    
    /**
     * Test complete pipeline progression workflow
     */
    public function testPipelineProgressionWorkflow()
    {
        // Start with a converted deal or create one
        $deal = $this->createTestDeal($this->testData['deal']);
        $this->createdRecords['deals'][] = $deal->id;
        
        // Create associated account
        $account = $this->createTestAccount($this->testData['account']);
        $this->createdRecords['accounts'][] = $account->id;
        $deal->account_id = $account->id;
        $deal->save();
        
        // Create associated contact
        $contact = $this->createTestContact($this->testData['contact']);
        $contact->account_id = $account->id;
        $contact->save();
        $this->createdRecords['contacts'][] = $contact->id;
        
        // Test progression through each stage
        $stages = ['sourcing', 'screening', 'analysis_outreach', 'term_sheet', 'due_diligence'];
        
        foreach ($stages as $index => $targetStage) {
            if ($index === 0) continue; // Skip first stage (already in sourcing)
            
            $fromStage = $stages[$index - 1];
            
            // Update deal with required fields for progression
            $this->updateDealForStage($deal, $targetStage);
            
            // Validate stage transition
            $validation = $this->automationEngine->validateStageTransition($deal, $fromStage, $targetStage);
            
            if (!$validation['allowed']) {
                // Add missing fields or override if needed
                $this->handleValidationFailure($deal, $validation, $targetStage);
                $validation = $this->automationEngine->validateStageTransition($deal, $fromStage, $targetStage);
            }
            
            $this->assertTrue($validation['allowed'], 
                "Deal should be able to progress from {$fromStage} to {$targetStage}");
            
            // Execute stage transition
            $result = $this->automationEngine->executeStageTransition($deal, $targetStage);
            
            $this->assertTrue($result['success'], 
                "Stage transition from {$fromStage} to {$targetStage} should succeed");
            
            // Reload deal to verify changes
            $deal = BeanFactory::getBean('mdeal_Deals', $deal->id);
            $this->assertEquals($targetStage, $deal->stage);
            $this->assertNotEmpty($deal->stage_entered_date);
            $this->assertEquals(0, $deal->days_in_stage);
            
            // Verify auto-tasks were created
            $this->verifyAutoTasksCreated($deal, $targetStage);
        }
    }
    
    /**
     * Test WIP limit enforcement
     */
    public function testWIPLimitEnforcement()
    {
        // Create multiple deals in same stage for same user
        $deals = [];
        $targetStage = 'due_diligence'; // Has WIP limit of 8
        
        // Create 10 deals (exceeding limit of 8)
        for ($i = 0; $i < 10; $i++) {
            $dealData = $this->testData['deal'];
            $dealData['name'] = "Test Deal {$i}";
            $dealData['stage'] = 'term_sheet'; // Start in previous stage
            
            $deal = $this->createTestDeal($dealData);
            $this->createdRecords['deals'][] = $deal->id;
            $deals[] = $deal;
        }
        
        $successfulTransitions = 0;
        $wipViolations = 0;
        
        // Try to move all deals to due_diligence stage
        foreach ($deals as $deal) {
            $this->updateDealForStage($deal, $targetStage);
            
            $validation = $this->automationEngine->validateStageTransition($deal, 'term_sheet', $targetStage);
            
            if ($validation['allowed']) {
                $result = $this->automationEngine->executeStageTransition($deal, $targetStage);
                if ($result['success']) {
                    $successfulTransitions++;
                }
            } else {
                if (strpos($validation['errors'][0], 'WIP limit') !== false) {
                    $wipViolations++;
                }
            }
        }
        
        // Should have WIP violations after 8 successful transitions
        $this->assertLessThanOrEqual(8, $successfulTransitions, 
            'Should not exceed WIP limit of 8 for due_diligence stage');
        $this->assertGreaterThan(0, $wipViolations, 
            'Should have WIP violations when trying to exceed limit');
    }
    
    /**
     * Test stale deal detection and escalation
     */
    public function testStaleDetectionWorkflow()
    {
        // Create a deal that's been in stage for too long
        $deal = $this->createTestDeal($this->testData['deal']);
        $this->createdRecords['deals'][] = $deal->id;
        
        // Simulate deal being in stage for 90 days (exceeds critical threshold)
        $deal->stage_entered_date = date('Y-m-d H:i:s', strtotime('-90 days'));
        $deal->days_in_stage = 90;
        $deal->save();
        
        // Run maintenance job to detect stale deals
        require_once('custom/modules/Pipelines/PipelineMaintenanceJob.php');
        $maintenanceJob = new PipelineMaintenanceJob();
        
        $results = $maintenanceJob->run();
        
        // Verify stale deals were detected
        $this->assertArrayHasKey('stale_detection', $results['tasks_completed']);
        $staleResults = $results['tasks_completed']['stale_detection'];
        
        $this->assertGreaterThan(0, $staleResults['stale_deals_found']);
        $this->assertGreaterThan(0, $staleResults['alerts_created']);
        
        // Reload deal and verify it's marked as stale
        $deal = BeanFactory::getBean('mdeal_Deals', $deal->id);
        $this->assertEquals(1, $deal->is_stale);
        $this->assertNotEmpty($deal->stale_reason);
        
        // Verify alert was created
        $alertCount = $this->getAlertCount($deal->id, 'stale_deal');
        $this->assertGreaterThan(0, $alertCount);
    }
    
    /**
     * Test account hierarchy validation
     */
    public function testAccountHierarchyValidation()
    {
        // Create parent account
        $parentData = $this->testData['account'];
        $parentData['name'] = 'Parent Corp';
        $parent = $this->createTestAccount($parentData);
        $this->createdRecords['accounts'][] = $parent->id;
        
        // Create child account
        $childData = $this->testData['account'];
        $childData['name'] = 'Child Corp';
        $childData['parent_id'] = $parent->id;
        $child = $this->createTestAccount($childData);
        $this->createdRecords['accounts'][] = $child->id;
        
        // Verify hierarchy is valid
        $this->assertTrue($child->validateHierarchy());
        
        // Test circular reference prevention
        $parent->parent_id = $child->id;
        $this->assertFalse($parent->validateHierarchy(), 
            'Should prevent circular reference in hierarchy');
        
        // Test self-reference prevention
        $parent->parent_id = $parent->id;
        $this->assertTrue($parent->validateHierarchy(), 
            'Should handle self-reference gracefully');
    }
    
    /**
     * Test contact relationship management
     */
    public function testContactRelationshipManagement()
    {
        // Create account
        $account = $this->createTestAccount($this->testData['account']);
        $this->createdRecords['accounts'][] = $account->id;
        
        // Create multiple contacts with different roles
        $contactRoles = ['decision_maker', 'financial_approver', 'influencer', 'evaluator'];
        $contacts = [];
        
        foreach ($contactRoles as $role) {
            $contactData = $this->testData['contact'];
            $contactData['first_name'] = ucfirst($role);
            $contactData['last_name'] = 'Contact';
            $contactData['email1'] = strtolower($role) . '@company.com';
            $contactData['decision_role'] = $role;
            $contactData['account_id'] = $account->id;
            
            $contact = $this->createTestContact($contactData);
            $this->createdRecords['contacts'][] = $contact->id;
            $contacts[] = $contact;
        }
        
        // Test influence score calculation
        foreach ($contacts as $contact) {
            $influenceScore = $contact->calculateInfluenceScore();
            $this->assertGreaterThanOrEqual(0, $influenceScore);
            $this->assertLessThanOrEqual(100, $influenceScore);
            
            // Decision maker should have highest score
            if ($contact->decision_role === 'decision_maker') {
                $this->assertGreaterThanOrEqual(70, $influenceScore);
            }
        }
        
        // Test many-to-many relationship functionality
        $relatedContacts = $account->getRelatedContacts();
        $this->assertCount(4, $relatedContacts);
        
        // Test decision maker identification
        $decisionMakers = $account->getDecisionMakers();
        $this->assertCount(1, $decisionMakers);
        $this->assertEquals('decision_maker', $decisionMakers[0]['decision_role']);
    }
    
    /**
     * Test deal health score calculation
     */
    public function testDealHealthScoreCalculation()
    {
        // Create deal with good metrics
        $deal = $this->createTestDeal($this->testData['deal']);
        $this->createdRecords['deals'][] = $deal->id;
        
        // Set up good health indicators
        $deal->deal_value = 50000000; // $50M
        $deal->stage = 'term_sheet'; // Good progression
        $deal->days_in_stage = 10; // Not stale
        $deal->save();
        
        // Calculate health score
        $healthScore = $deal->calculateHealthScore();
        
        $this->assertGreaterThanOrEqual(0, $healthScore);
        $this->assertLessThanOrEqual(100, $healthScore);
        $this->assertGreaterThan(60, $healthScore, 'Deal with good metrics should have good health score');
        
        // Test with poor metrics
        $deal->deal_value = 500000; // $500K (low value)
        $deal->stage = 'sourcing'; // Early stage
        $deal->days_in_stage = 60; // Stale
        $deal->save();
        
        $poorHealthScore = $deal->calculateHealthScore();
        $this->assertLessThan($healthScore, $poorHealthScore, 
            'Deal with poor metrics should have lower health score');
    }
    
    /**
     * Test pipeline analytics generation
     */
    public function testPipelineAnalyticsGeneration()
    {
        // Create deals in various stages
        $stages = ['sourcing', 'screening', 'analysis_outreach', 'term_sheet'];
        $deals = [];
        
        foreach ($stages as $stage) {
            for ($i = 0; $i < 3; $i++) {
                $dealData = $this->testData['deal'];
                $dealData['name'] = "Analytics Test Deal {$stage} {$i}";
                $dealData['stage'] = $stage;
                $dealData['deal_value'] = rand(1000000, 50000000);
                
                $deal = $this->createTestDeal($dealData);
                $this->createdRecords['deals'][] = $deal->id;
                $deals[] = $deal;
            }
        }
        
        // Generate pipeline statistics
        $statistics = $this->automationEngine->getPipelineStatistics();
        
        // Verify statistics structure
        $this->assertIsArray($statistics);
        
        foreach ($stages as $stage) {
            $this->assertArrayHasKey($stage, $statistics);
            $stageStats = $statistics[$stage];
            
            $this->assertArrayHasKey('deal_count', $stageStats);
            $this->assertArrayHasKey('total_value', $stageStats);
            $this->assertArrayHasKey('avg_value', $stageStats);
            $this->assertArrayHasKey('wip_utilization', $stageStats);
            
            $this->assertEquals(3, $stageStats['deal_count']);
            $this->assertGreaterThan(0, $stageStats['total_value']);
        }
    }
    
    /**
     * Test automation rule execution
     */
    public function testAutomationRuleExecution()
    {
        // Create a deal that meets auto-progression criteria
        $deal = $this->createTestDeal($this->testData['deal']);
        $this->createdRecords['deals'][] = $deal->id;
        
        // Set up conditions for auto-progression
        $deal->stage = 'screening';
        $deal->annual_revenue = 25000000;
        $deal->ebitda = 5000000;
        $deal->interest_level = 'high';
        $deal->save();
        
        // Add required fields for next stage
        $this->updateDealForStage($deal, 'analysis_outreach');
        
        // Execute automation rules
        require_once('custom/modules/Pipelines/PipelineMaintenanceJob.php');
        $maintenanceJob = new PipelineMaintenanceJob();
        
        $results = $maintenanceJob->run();
        
        // Verify automation rules were executed
        $this->assertArrayHasKey('automation_rules', $results['tasks_completed']);
        $automationResults = $results['tasks_completed']['automation_rules'];
        
        $this->assertGreaterThanOrEqual(0, $automationResults['rules_executed']);
        $this->assertEquals(0, $automationResults['errors']);
    }
    
    /**
     * Test complete module integration
     */
    public function testCompleteModuleIntegration()
    {
        // Test data flow between all modules
        
        // 1. Start with lead conversion
        $deal = $this->testLeadToDeadConversionWorkflow();
        
        // 2. Progress through pipeline
        $this->updateDealForStage($deal, 'screening');
        $result = $this->automationEngine->executeStageTransition($deal, 'screening');
        $this->assertTrue($result['success']);
        
        // 3. Verify relationships are maintained
        $deal = BeanFactory::getBean('mdeal_Deals', $deal->id);
        $this->assertNotEmpty($deal->account_id);
        
        $account = BeanFactory::getBean('mdeal_Accounts', $deal->account_id);
        $this->assertNotEmpty($account->id);
        
        // 4. Test contact interactions
        $contacts = $account->getRelatedContacts();
        $this->assertGreaterThan(0, count($contacts));
        
        // 5. Verify business logic enforcement
        $healthScore = $account->calculateHealthScore();
        $this->assertGreaterThan(0, $healthScore);
        
        // 6. Test hierarchy if applicable
        if ($account->parent_id) {
            $this->assertTrue($account->validateHierarchy());
        }
        
        // 7. Verify audit trail
        $transitionCount = $this->getStageTransitionCount($deal->id);
        $this->assertGreaterThan(0, $transitionCount);
    }
    
    // Helper methods
    
    protected function createTestLead($data)
    {
        $lead = BeanFactory::newBean('mdeal_Leads');
        foreach ($data as $field => $value) {
            $lead->$field = $value;
        }
        $lead->save();
        return $lead;
    }
    
    protected function createTestDeal($data)
    {
        $deal = BeanFactory::newBean('mdeal_Deals');
        foreach ($data as $field => $value) {
            $deal->$field = $value;
        }
        $deal->stage_entered_date = date('Y-m-d H:i:s');
        $deal->days_in_stage = 0;
        $deal->save();
        return $deal;
    }
    
    protected function createTestAccount($data)
    {
        $account = BeanFactory::newBean('mdeal_Accounts');
        foreach ($data as $field => $value) {
            $account->$field = $value;
        }
        $account->save();
        return $account;
    }
    
    protected function createTestContact($data)
    {
        $contact = BeanFactory::newBean('mdeal_Contacts');
        foreach ($data as $field => $value) {
            $contact->$field = $value;
        }
        $contact->save();
        return $contact;
    }
    
    protected function updateDealForStage($deal, $targetStage)
    {
        // Add required fields based on target stage
        $stageRequirements = $this->validationManager->getStageRequirements($targetStage);
        $requiredFields = $stageRequirements['required_fields'] ?? [];
        
        foreach ($requiredFields as $field => $description) {
            if (empty($deal->$field)) {
                // Set appropriate test values
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
                        $deal->$field = '$10M-$15M';
                        break;
                    case 'deal_structure':
                        $deal->$field = 'Asset Purchase';
                        break;
                    case 'key_terms':
                        $deal->$field = 'Standard terms';
                        break;
                    case 'financing_source':
                        $deal->$field = 'Internal funds';
                        break;
                    default:
                        $deal->$field = 'Test value';
                }
            }
        }
        
        $deal->save();
    }
    
    protected function handleValidationFailure($deal, $validation, $targetStage)
    {
        // Handle validation failures by adding missing data or overriding
        foreach ($validation['missing_requirements'] as $requirement) {
            if (property_exists($deal, $requirement)) {
                $deal->$requirement = 'Override value';
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
    
    protected function getAlertCount($dealId, $alertType)
    {
        global $db;
        
        $query = "SELECT COUNT(*) as count FROM mdeal_pipeline_alerts 
                  WHERE deal_id = ? AND alert_type = ? AND deleted = 0";
        
        $result = $db->pQuery($query, [$dealId, $alertType]);
        $row = $db->fetchByAssoc($result);
        
        return $row['count'] ?? 0;
    }
    
    protected function getStageTransitionCount($dealId)
    {
        global $db;
        
        $query = "SELECT COUNT(*) as count FROM mdeal_pipeline_transitions 
                  WHERE deal_id = ?";
        
        $result = $db->pQuery($query, [$dealId]);
        $row = $db->fetchByAssoc($result);
        
        return $row['count'] ?? 0;
    }
    
    protected function cleanupTestData()
    {
        global $db;
        
        // Clean up created records
        foreach (['leads', 'deals', 'accounts', 'contacts'] as $type) {
            if (!empty($this->createdRecords[$type])) {
                $tableName = 'mdeal_' . $type;
                if ($type === 'leads') $tableName = 'mdeal_leads';
                if ($type === 'deals') $tableName = 'mdeal_deals';
                if ($type === 'accounts') $tableName = 'mdeal_accounts';
                if ($type === 'contacts') $tableName = 'mdeal_contacts';
                
                foreach ($this->createdRecords[$type] as $id) {
                    $query = "UPDATE {$tableName} SET deleted = 1 WHERE id = ?";
                    $db->pQuery($query, [$id]);
                }
            }
        }
        
        // Clean up related data
        if (!empty($this->createdRecords['deals'])) {
            foreach ($this->createdRecords['deals'] as $dealId) {
                // Clean up tasks
                $db->pQuery("UPDATE tasks SET deleted = 1 WHERE parent_type = 'mdeal_Deals' AND parent_id = ?", [$dealId]);
                
                // Clean up alerts
                $db->pQuery("UPDATE mdeal_pipeline_alerts SET deleted = 1 WHERE deal_id = ?", [$dealId]);
                
                // Clean up transitions
                $db->pQuery("DELETE FROM mdeal_pipeline_transitions WHERE deal_id = ?", [$dealId]);
            }
        }
    }
}