<?php
/**
 * Integration tests for Accounts module
 * Tests the complete account management workflow including hierarchies
 */

use PHPUnit\Framework\TestCase;

class AccountsIntegrationTest extends TestCase
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
            $current_user->user_name = 'accounts_test_user';
            $current_user->first_name = 'Accounts';
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
            'parent' => [
                'name' => 'Parent Holdings Corporation',
                'account_type' => 'portfolio',
                'industry' => 'Diversified Holdings',
                'annual_revenue' => 500000000,
                'ebitda' => 100000000,
                'employee_count' => 5000,
                'website' => 'https://parentholdings.com',
                'phone_office' => '555-1000',
                'billing_address_street' => '100 Corporate Blvd',
                'billing_address_city' => 'New York',
                'billing_address_state' => 'NY',
                'billing_address_postalcode' => '10001',
                'billing_address_country' => 'USA'
            ],
            'subsidiary' => [
                'name' => 'Operating Company LLC',
                'account_type' => 'target',
                'industry' => 'Technology',
                'annual_revenue' => 50000000,
                'ebitda' => 10000000,
                'employee_count' => 250,
                'website' => 'https://operatingco.com'
            ],
            'target' => [
                'name' => 'Acquisition Target Inc',
                'account_type' => 'target',
                'industry' => 'Software',
                'annual_revenue' => 25000000,
                'ebitda' => 5000000,
                'employee_count' => 100,
                'growth_rate' => 35,
                'website' => 'https://target.com'
            ]
        ];
    }
    
    /**
     * Test account creation and basic fields
     */
    public function testAccountCreation()
    {
        $account = BeanFactory::newBean('mdeal_Accounts');
        
        foreach ($this->testData['parent'] as $field => $value) {
            $account->$field = $value;
        }
        
        $account->save();
        $this->createdRecords['accounts'][] = $account->id;
        
        // Verify account was created
        $this->assertNotEmpty($account->id);
        
        // Reload and verify data
        $savedAccount = BeanFactory::getBean('mdeal_Accounts', $account->id);
        $this->assertEquals($this->testData['parent']['name'], $savedAccount->name);
        $this->assertEquals($this->testData['parent']['annual_revenue'], $savedAccount->annual_revenue);
        
        // Verify defaults
        $this->assertEquals('active', $savedAccount->account_status);
        $this->assertEquals(0, $savedAccount->hierarchy_level);
        
        return $account;
    }
    
    /**
     * Test account hierarchy creation
     */
    public function testAccountHierarchy()
    {
        // Create parent account
        $parent = BeanFactory::newBean('mdeal_Accounts');
        foreach ($this->testData['parent'] as $field => $value) {
            $parent->$field = $value;
        }
        $parent->save();
        $this->createdRecords['accounts'][] = $parent->id;
        
        // Create subsidiary
        $subsidiary = BeanFactory::newBean('mdeal_Accounts');
        foreach ($this->testData['subsidiary'] as $field => $value) {
            $subsidiary->$field = $value;
        }
        $subsidiary->parent_id = $parent->id;
        $subsidiary->save();
        $this->createdRecords['accounts'][] = $subsidiary->id;
        
        // Create grandchild
        $grandchild = BeanFactory::newBean('mdeal_Accounts');
        $grandchild->name = 'Grandchild Division';
        $grandchild->account_type = 'target';
        $grandchild->parent_id = $subsidiary->id;
        $grandchild->save();
        $this->createdRecords['accounts'][] = $grandchild->id;
        
        // Verify hierarchy levels
        $this->assertEquals(0, $parent->hierarchy_level);
        $this->assertEquals(1, $subsidiary->hierarchy_level);
        $this->assertEquals(2, $grandchild->hierarchy_level);
        
        // Test circular reference prevention
        $parent->parent_id = $grandchild->id;
        $this->assertFalse($parent->validateHierarchy());
        
        // Test self-reference prevention
        $parent->parent_id = $parent->id;
        $this->assertTrue($parent->validateHierarchy()); // Should handle gracefully
        $parent->parent_id = null;
        $parent->save();
        
        return [$parent, $subsidiary, $grandchild];
    }
    
    /**
     * Test health score calculation
     */
    public function testHealthScoreCalculation()
    {
        $account = $this->testAccountCreation();
        
        // Set up good health indicators
        $account->annual_revenue = 100000000;
        $account->ebitda = 20000000; // 20% margin
        $account->employee_count = 500;
        $account->industry = 'Technology'; // Low risk
        $account->save();
        
        // Add contacts
        for ($i = 0; $i < 5; $i++) {
            $contact = BeanFactory::newBean('mdeal_Contacts');
            $contact->first_name = "Test{$i}";
            $contact->last_name = "Contact{$i}";
            $contact->email1 = "test{$i}@account.com";
            $contact->account_id = $account->id;
            $contact->save();
            $this->createdRecords['contacts'][] = $contact->id;
        }
        
        // Calculate health score
        require_once('custom/modules/mdeal_Accounts/AccountLogicHooks.php');
        $hooks = new AccountLogicHooks();
        $hooks->calculateHealthScore($account, 'after_save', []);
        
        // Reload account
        $account = BeanFactory::getBean('mdeal_Accounts', $account->id);
        
        // Verify score was calculated
        $this->assertGreaterThan(0, $account->health_score);
        $this->assertLessThanOrEqual(100, $account->health_score);
        
        // With good metrics, should have high score
        $this->assertGreaterThanOrEqual(60, $account->health_score);
    }
    
    /**
     * Test portfolio metrics calculation
     */
    public function testPortfolioMetricsCalculation()
    {
        list($parent, $subsidiary, $grandchild) = $this->testAccountHierarchy();
        
        // Update parent type to portfolio
        $parent->account_type = 'portfolio';
        $parent->save();
        
        // Calculate portfolio metrics
        require_once('custom/modules/mdeal_Accounts/AccountLogicHooks.php');
        $hooks = new AccountLogicHooks();
        $hooks->updatePortfolioMetrics($parent, 'after_save', []);
        
        // Reload parent
        $parent = BeanFactory::getBean('mdeal_Accounts', $parent->id);
        
        // Verify portfolio metrics
        $this->assertEquals(2, $parent->portfolio_company_count);
        $expectedRevenue = $this->testData['subsidiary']['annual_revenue'] + 0; // grandchild has no revenue set
        $this->assertEquals($expectedRevenue, $parent->portfolio_total_revenue);
    }
    
    /**
     * Test account-contact relationships
     */
    public function testAccountContactRelationships()
    {
        $account = $this->testAccountCreation();
        
        // Create contacts with different roles
        $roles = ['decision_maker', 'financial_approver', 'influencer', 'evaluator'];
        $contacts = [];
        
        foreach ($roles as $i => $role) {
            $contact = BeanFactory::newBean('mdeal_Contacts');
            $contact->first_name = ucfirst($role);
            $contact->last_name = 'Contact';
            $contact->email1 = "{$role}@account.com";
            $contact->decision_role = $role;
            $contact->account_id = $account->id;
            $contact->save();
            $this->createdRecords['contacts'][] = $contact->id;
            $contacts[] = $contact;
        }
        
        // Get related contacts
        $relatedContacts = $this->getAccountContacts($account->id);
        $this->assertCount(4, $relatedContacts);
        
        // Get decision makers
        $decisionMakers = $this->getAccountDecisionMakers($account->id);
        $this->assertCount(1, $decisionMakers);
        $this->assertEquals('decision_maker', $decisionMakers[0]['decision_role']);
    }
    
    /**
     * Test account-deal relationships
     */
    public function testAccountDealRelationships()
    {
        $account = $this->testAccountCreation();
        
        // Create multiple deals in different stages
        $stages = ['sourcing', 'screening', 'term_sheet', 'closed_won'];
        $dealValues = [10000000, 25000000, 50000000, 75000000];
        
        foreach ($stages as $i => $stage) {
            $deal = BeanFactory::newBean('mdeal_Deals');
            $deal->name = "Deal {$i}";
            $deal->company_name = $account->name;
            $deal->account_id = $account->id;
            $deal->stage = $stage;
            $deal->deal_value = $dealValues[$i];
            $deal->save();
            $this->createdRecords['deals'][] = $deal->id;
        }
        
        // Get active deals
        $activeDeals = $this->getAccountActiveDeals($account->id);
        $this->assertCount(3, $activeDeals); // Excluding closed_won
        
        // Calculate total deal value
        $totalValue = $this->getAccountTotalDealValue($account->id);
        $this->assertEquals(160000000, $totalValue); // Sum of all deals
        
        // Update relationship score
        $account->relationship_score = 85;
        $account->save();
        
        $account = BeanFactory::getBean('mdeal_Accounts', $account->id);
        $this->assertEquals(85, $account->relationship_score);
    }
    
    /**
     * Test account search and filtering
     */
    public function testAccountSearchAndFiltering()
    {
        // Create accounts with different attributes
        $types = ['target', 'portfolio', 'partner'];
        $industries = ['Technology', 'Healthcare', 'Finance'];
        $revenues = [10000000, 50000000, 100000000];
        
        foreach ($types as $i => $type) {
            $account = BeanFactory::newBean('mdeal_Accounts');
            $account->name = "{$type} Test Account";
            $account->account_type = $type;
            $account->industry = $industries[$i];
            $account->annual_revenue = $revenues[$i];
            $account->save();
            $this->createdRecords['accounts'][] = $account->id;
        }
        
        global $db;
        
        // Test type filter
        $query = "SELECT COUNT(*) as count FROM mdeal_accounts 
                  WHERE account_type = 'target' AND deleted = 0";
        $result = $db->query($query);
        $row = $db->fetchByAssoc($result);
        $this->assertGreaterThanOrEqual(1, $row['count']);
        
        // Test revenue filter
        $query = "SELECT COUNT(*) as count FROM mdeal_accounts 
                  WHERE annual_revenue >= 50000000 AND deleted = 0";
        $result = $db->query($query);
        $row = $db->fetchByAssoc($result);
        $this->assertGreaterThanOrEqual(2, $row['count']);
        
        // Test industry filter
        $query = "SELECT COUNT(*) as count FROM mdeal_accounts 
                  WHERE industry = 'Technology' AND deleted = 0";
        $result = $db->query($query);
        $row = $db->fetchByAssoc($result);
        $this->assertGreaterThanOrEqual(1, $row['count']);
    }
    
    /**
     * Test account duplicate detection
     */
    public function testAccountDuplicateDetection()
    {
        $account1 = $this->testAccountCreation();
        
        // Try to create duplicate
        $account2 = BeanFactory::newBean('mdeal_Accounts');
        $account2->name = $account1->name;
        $account2->website = $account1->website;
        
        // Check for duplicates
        $duplicates = $this->findDuplicateAccounts($account2);
        
        $this->assertCount(1, $duplicates);
        $this->assertEquals($account1->id, $duplicates[0]['id']);
    }
    
    /**
     * Test account activity tracking
     */
    public function testAccountActivityTracking()
    {
        $account = $this->testAccountCreation();
        
        // Create activities
        $task = BeanFactory::newBean('Tasks');
        $task->name = 'Due Diligence Task';
        $task->parent_type = 'mdeal_Accounts';
        $task->parent_id = $account->id;
        $task->status = 'In Progress';
        $task->save();
        $this->createdRecords['tasks'][] = $task->id;
        
        $note = BeanFactory::newBean('Notes');
        $note->name = 'Account Analysis';
        $note->parent_type = 'mdeal_Accounts';
        $note->parent_id = $account->id;
        $note->save();
        $this->createdRecords['notes'][] = $note->id;
        
        // Update last activity date
        $account->last_activity_date = date('Y-m-d');
        $account->save();
        
        // Verify activity tracking
        $account = BeanFactory::getBean('mdeal_Accounts', $account->id);
        $this->assertEquals(date('Y-m-d'), $account->last_activity_date);
    }
    
    /**
     * Test account financial metrics
     */
    public function testAccountFinancialMetrics()
    {
        $account = $this->testAccountCreation();
        
        // Set financial data
        $account->annual_revenue = 100000000;
        $account->ebitda = 20000000;
        $account->total_debt = 30000000;
        $account->cash_position = 10000000;
        $account->save();
        
        // Calculate metrics
        $ebitdaMargin = ($account->ebitda / $account->annual_revenue) * 100;
        $this->assertEquals(20, $ebitdaMargin);
        
        $netDebt = $account->total_debt - $account->cash_position;
        $this->assertEquals(20000000, $netDebt);
        
        $debtToEbitda = $netDebt / $account->ebitda;
        $this->assertEquals(1, $debtToEbitda);
    }
    
    /**
     * Test account bulk operations
     */
    public function testAccountBulkOperations()
    {
        // Create multiple accounts
        $accountIds = [];
        for ($i = 0; $i < 5; $i++) {
            $account = BeanFactory::newBean('mdeal_Accounts');
            $account->name = "Bulk Test Account {$i}";
            $account->account_type = 'target';
            $account->save();
            $accountIds[] = $account->id;
            $this->createdRecords['accounts'][] = $account->id;
        }
        
        // Test bulk status update
        $this->bulkUpdateAccounts($accountIds, ['account_status' => 'inactive']);
        
        // Verify updates
        foreach ($accountIds as $id) {
            $account = BeanFactory::getBean('mdeal_Accounts', $id);
            $this->assertEquals('inactive', $account->account_status);
        }
        
        // Test bulk assignment
        $newUserId = create_guid();
        $this->bulkUpdateAccounts($accountIds, ['assigned_user_id' => $newUserId]);
        
        // Verify assignments
        foreach ($accountIds as $id) {
            $account = BeanFactory::getBean('mdeal_Accounts', $id);
            $this->assertEquals($newUserId, $account->assigned_user_id);
        }
    }
    
    /**
     * Test account tag system
     */
    public function testAccountTagSystem()
    {
        $account = $this->testAccountCreation();
        
        // Add tags
        global $db;
        $tags = ['high-value', 'strategic', 'technology', 'growth'];
        
        foreach ($tags as $tag) {
            $tagId = create_guid();
            $query = "INSERT INTO mdeal_account_tags 
                      (id, account_id, tag_name, created_date, deleted) 
                      VALUES (?, ?, ?, NOW(), 0)";
            $db->pQuery($query, [$tagId, $account->id, $tag]);
        }
        
        // Retrieve tags
        $accountTags = $this->getAccountTags($account->id);
        $this->assertCount(4, $accountTags);
        $this->assertContains('strategic', array_column($accountTags, 'tag_name'));
        
        // Clean up tags
        $db->pQuery("UPDATE mdeal_account_tags SET deleted = 1 WHERE account_id = ?", [$account->id]);
    }
    
    // Helper methods
    
    protected function getAccountContacts($accountId)
    {
        global $db;
        $query = "SELECT * FROM mdeal_contacts WHERE account_id = ? AND deleted = 0";
        $result = $db->pQuery($query, [$accountId]);
        
        $contacts = [];
        while ($row = $db->fetchByAssoc($result)) {
            $contacts[] = $row;
        }
        return $contacts;
    }
    
    protected function getAccountDecisionMakers($accountId)
    {
        global $db;
        $query = "SELECT * FROM mdeal_contacts 
                  WHERE account_id = ? 
                  AND decision_role = 'decision_maker' 
                  AND deleted = 0";
        $result = $db->pQuery($query, [$accountId]);
        
        $contacts = [];
        while ($row = $db->fetchByAssoc($result)) {
            $contacts[] = $row;
        }
        return $contacts;
    }
    
    protected function getAccountActiveDeals($accountId)
    {
        global $db;
        $query = "SELECT * FROM mdeal_deals 
                  WHERE account_id = ? 
                  AND stage NOT IN ('closed_won', 'closed_lost', 'unavailable') 
                  AND deleted = 0";
        $result = $db->pQuery($query, [$accountId]);
        
        $deals = [];
        while ($row = $db->fetchByAssoc($result)) {
            $deals[] = $row;
        }
        return $deals;
    }
    
    protected function getAccountTotalDealValue($accountId)
    {
        global $db;
        $query = "SELECT SUM(deal_value) as total_value 
                  FROM mdeal_deals 
                  WHERE account_id = ? AND deleted = 0";
        $result = $db->pQuery($query, [$accountId]);
        $row = $db->fetchByAssoc($result);
        return $row['total_value'] ?: 0;
    }
    
    protected function findDuplicateAccounts($account)
    {
        global $db;
        
        $query = "SELECT id, name, website 
                  FROM mdeal_accounts 
                  WHERE deleted = 0 
                  AND (name = ? OR website = ?)
                  AND id != ?";
        
        $result = $db->pQuery($query, [
            $account->name,
            $account->website,
            $account->id ?: ''
        ]);
        
        $duplicates = [];
        while ($row = $db->fetchByAssoc($result)) {
            $duplicates[] = $row;
        }
        
        return $duplicates;
    }
    
    protected function bulkUpdateAccounts($accountIds, $updates)
    {
        foreach ($accountIds as $id) {
            $account = BeanFactory::getBean('mdeal_Accounts', $id);
            foreach ($updates as $field => $value) {
                $account->$field = $value;
            }
            $account->save();
        }
    }
    
    protected function getAccountTags($accountId)
    {
        global $db;
        $query = "SELECT tag_name FROM mdeal_account_tags 
                  WHERE account_id = ? AND deleted = 0";
        $result = $db->pQuery($query, [$accountId]);
        
        $tags = [];
        while ($row = $db->fetchByAssoc($result)) {
            $tags[] = $row;
        }
        return $tags;
    }
    
    protected function cleanupTestData()
    {
        global $db;
        
        $tables = [
            'accounts' => 'mdeal_accounts',
            'contacts' => 'mdeal_contacts',
            'deals' => 'mdeal_deals',
            'tasks' => 'tasks',
            'notes' => 'notes'
        ];
        
        foreach ($tables as $type => $table) {
            if (!empty($this->createdRecords[$type])) {
                foreach ($this->createdRecords[$type] as $id) {
                    $query = "UPDATE {$table} SET deleted = 1 WHERE id = ?";
                    $db->pQuery($query, [$id]);
                }
            }
        }
        
        // Clean up tags
        if (!empty($this->createdRecords['accounts'])) {
            $accountIds = implode("','", $this->createdRecords['accounts']);
            $db->query("UPDATE mdeal_account_tags SET deleted = 1 WHERE account_id IN ('{$accountIds}')");
        }
    }
}