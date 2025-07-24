<?php
/**
 * Integration tests for Contacts module
 * Tests the complete contact management workflow
 */

use PHPUnit\Framework\TestCase;

class ContactsIntegrationTest extends TestCase
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
            $current_user->user_name = 'contacts_test_user';
            $current_user->first_name = 'Contacts';
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
            'contact' => [
                'first_name' => 'John',
                'last_name' => 'Smith',
                'title' => 'CEO',
                'department' => 'Executive',
                'phone_work' => '555-1234',
                'phone_mobile' => '555-5678',
                'email1' => 'john.smith@company.com',
                'contact_type' => 'key_stakeholder',
                'decision_role' => 'decision_maker',
                'linkedin_url' => 'https://linkedin.com/in/johnsmith'
            ],
            'account' => [
                'name' => 'Test Corporation',
                'account_type' => 'target',
                'industry' => 'Technology',
                'annual_revenue' => 50000000,
                'employee_count' => 250,
                'website' => 'https://testcorp.com'
            ]
        ];
    }
    
    /**
     * Test contact creation with account relationship
     */
    public function testContactCreationWithAccount()
    {
        // Create account first
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
        
        // Verify contact was created
        $this->assertNotEmpty($contact->id);
        
        // Reload and verify data
        $savedContact = BeanFactory::getBean('mdeal_Contacts', $contact->id);
        $this->assertEquals($this->testData['contact']['first_name'], $savedContact->first_name);
        $this->assertEquals($this->testData['contact']['last_name'], $savedContact->last_name);
        $this->assertEquals($account->id, $savedContact->account_id);
        
        // Verify account relationship
        $contactAccount = BeanFactory::getBean('mdeal_Accounts', $savedContact->account_id);
        $this->assertEquals($account->name, $contactAccount->name);
        
        return [$contact, $account];
    }
    
    /**
     * Test influence score calculation
     */
    public function testInfluenceScoreCalculation()
    {
        list($contact, $account) = $this->testContactCreationWithAccount();
        
        // Add engagement history
        $contact->interaction_count = 10;
        $contact->last_interaction_date = date('Y-m-d');
        $contact->response_rate = 80;
        $contact->save();
        
        // Calculate influence score
        require_once('custom/modules/mdeal_Contacts/ContactLogicHooks.php');
        $hooks = new ContactLogicHooks();
        $hooks->calculateInfluenceScore($contact, 'after_save', []);
        
        // Reload contact
        $contact = BeanFactory::getBean('mdeal_Contacts', $contact->id);
        
        // Verify score was calculated
        $this->assertGreaterThan(0, $contact->influence_score);
        $this->assertLessThanOrEqual(100, $contact->influence_score);
        
        // Decision maker with high engagement should have high score
        $this->assertGreaterThanOrEqual(70, $contact->influence_score);
    }
    
    /**
     * Test contact-to-contact relationships
     */
    public function testContactToContactRelationships()
    {
        list($contact1, $account) = $this->testContactCreationWithAccount();
        
        // Create second contact
        $contact2 = BeanFactory::newBean('mdeal_Contacts');
        $contact2->first_name = 'Jane';
        $contact2->last_name = 'Doe';
        $contact2->title = 'CFO';
        $contact2->email1 = 'jane.doe@company.com';
        $contact2->account_id = $account->id;
        $contact2->save();
        $this->createdRecords['contacts'][] = $contact2->id;
        
        // Create relationship
        global $db;
        $relId = create_guid();
        $query = "INSERT INTO mdeal_contacts_contacts 
                  (id, contact_id_a, contact_id_b, relationship_type, deleted) 
                  VALUES (?, ?, ?, ?, 0)";
        $db->pQuery($query, [$relId, $contact1->id, $contact2->id, 'reports_to']);
        
        // Verify relationship
        $query = "SELECT * FROM mdeal_contacts_contacts 
                  WHERE contact_id_a = ? AND contact_id_b = ? AND deleted = 0";
        $result = $db->pQuery($query, [$contact1->id, $contact2->id]);
        $row = $db->fetchByAssoc($result);
        
        $this->assertNotEmpty($row);
        $this->assertEquals('reports_to', $row['relationship_type']);
        
        // Clean up relationship
        $db->pQuery("UPDATE mdeal_contacts_contacts SET deleted = 1 WHERE id = ?", [$relId]);
    }
    
    /**
     * Test contact activity tracking
     */
    public function testContactActivityTracking()
    {
        list($contact, $account) = $this->testContactCreationWithAccount();
        
        // Create email activity
        $email = BeanFactory::newBean('Emails');
        $email->name = 'Introduction Email';
        $email->parent_type = 'mdeal_Contacts';
        $email->parent_id = $contact->id;
        $email->status = 'sent';
        $email->save();
        $this->createdRecords['emails'][] = $email->id;
        
        // Create call activity
        $call = BeanFactory::newBean('Calls');
        $call->name = 'Follow-up Call';
        $call->parent_type = 'mdeal_Contacts';
        $call->parent_id = $contact->id;
        $call->status = 'Held';
        $call->save();
        $this->createdRecords['calls'][] = $call->id;
        
        // Update contact engagement
        $contact->interaction_count = 2;
        $contact->last_interaction_date = date('Y-m-d');
        $contact->last_interaction_type = 'call';
        $contact->save();
        
        // Verify activity tracking
        $contact = BeanFactory::getBean('mdeal_Contacts', $contact->id);
        $this->assertEquals(2, $contact->interaction_count);
        $this->assertEquals(date('Y-m-d'), $contact->last_interaction_date);
        $this->assertEquals('call', $contact->last_interaction_type);
    }
    
    /**
     * Test contact search and filtering
     */
    public function testContactSearchAndFiltering()
    {
        // Create account
        $account = BeanFactory::newBean('mdeal_Accounts');
        $account->name = 'Search Test Corp';
        $account->save();
        $this->createdRecords['accounts'][] = $account->id;
        
        // Create multiple contacts with different roles
        $roles = ['decision_maker', 'influencer', 'evaluator'];
        $departments = ['Executive', 'Finance', 'Operations'];
        
        foreach ($roles as $i => $role) {
            $contact = BeanFactory::newBean('mdeal_Contacts');
            $contact->first_name = "Test{$i}";
            $contact->last_name = "Contact{$i}";
            $contact->email1 = "test{$i}@company.com";
            $contact->decision_role = $role;
            $contact->department = $departments[$i];
            $contact->account_id = $account->id;
            $contact->save();
            $this->createdRecords['contacts'][] = $contact->id;
        }
        
        global $db;
        
        // Test role filter
        $query = "SELECT COUNT(*) as count FROM mdeal_contacts 
                  WHERE decision_role = 'decision_maker' AND deleted = 0";
        $result = $db->query($query);
        $row = $db->fetchByAssoc($result);
        $this->assertGreaterThanOrEqual(1, $row['count']);
        
        // Test department filter
        $query = "SELECT COUNT(*) as count FROM mdeal_contacts 
                  WHERE department = 'Executive' AND deleted = 0";
        $result = $db->query($query);
        $row = $db->fetchByAssoc($result);
        $this->assertGreaterThanOrEqual(1, $row['count']);
        
        // Test account filter
        $query = "SELECT COUNT(*) as count FROM mdeal_contacts 
                  WHERE account_id = ? AND deleted = 0";
        $result = $db->pQuery($query, [$account->id]);
        $row = $db->fetchByAssoc($result);
        $this->assertEquals(3, $row['count']);
    }
    
    /**
     * Test contact duplicate detection
     */
    public function testContactDuplicateDetection()
    {
        list($contact1, $account) = $this->testContactCreationWithAccount();
        
        // Try to create duplicate
        $contact2 = BeanFactory::newBean('mdeal_Contacts');
        $contact2->first_name = $contact1->first_name;
        $contact2->last_name = $contact1->last_name;
        $contact2->email1 = $contact1->email1;
        
        // Check for duplicates
        $duplicates = $this->findDuplicateContacts($contact2);
        
        $this->assertCount(1, $duplicates);
        $this->assertEquals($contact1->id, $duplicates[0]['id']);
    }
    
    /**
     * Test contact communication preferences
     */
    public function testContactCommunicationPreferences()
    {
        list($contact, $account) = $this->testContactCreationWithAccount();
        
        // Set communication preferences
        $contact->preferred_contact_method = 'email';
        $contact->best_time_to_contact = 'morning';
        $contact->timezone_c = 'America/New_York';
        $contact->do_not_call = 0;
        $contact->email_opt_out = 0;
        $contact->save();
        
        // Reload and verify
        $contact = BeanFactory::getBean('mdeal_Contacts', $contact->id);
        $this->assertEquals('email', $contact->preferred_contact_method);
        $this->assertEquals('morning', $contact->best_time_to_contact);
        $this->assertEquals('America/New_York', $contact->timezone_c);
        $this->assertEquals(0, $contact->do_not_call);
        $this->assertEquals(0, $contact->email_opt_out);
    }
    
    /**
     * Test contact-deal relationships
     */
    public function testContactDealRelationships()
    {
        list($contact, $account) = $this->testContactCreationWithAccount();
        
        // Create deal
        $deal = BeanFactory::newBean('mdeal_Deals');
        $deal->name = 'Test Deal';
        $deal->company_name = $account->name;
        $deal->account_id = $account->id;
        $deal->stage = 'sourcing';
        $deal->deal_value = 10000000;
        $deal->save();
        $this->createdRecords['deals'][] = $deal->id;
        
        // Create contact-deal relationship
        global $db;
        $relId = create_guid();
        $query = "INSERT INTO mdeal_contacts_deals 
                  (id, contact_id, deal_id, role_in_deal, is_primary, deleted) 
                  VALUES (?, ?, ?, ?, ?, 0)";
        $db->pQuery($query, [$relId, $contact->id, $deal->id, 'decision_maker', 1]);
        
        // Verify relationship
        $query = "SELECT * FROM mdeal_contacts_deals 
                  WHERE contact_id = ? AND deal_id = ? AND deleted = 0";
        $result = $db->pQuery($query, [$contact->id, $deal->id]);
        $row = $db->fetchByAssoc($result);
        
        $this->assertNotEmpty($row);
        $this->assertEquals('decision_maker', $row['role_in_deal']);
        $this->assertEquals(1, $row['is_primary']);
        
        // Clean up relationship
        $db->pQuery("UPDATE mdeal_contacts_deals SET deleted = 1 WHERE id = ?", [$relId]);
    }
    
    /**
     * Test contact engagement history
     */
    public function testContactEngagementHistory()
    {
        list($contact, $account) = $this->testContactCreationWithAccount();
        
        // Simulate engagement over time
        $engagementData = [
            ['date' => '-30 days', 'type' => 'email', 'response' => true],
            ['date' => '-20 days', 'type' => 'call', 'response' => true],
            ['date' => '-10 days', 'type' => 'meeting', 'response' => true],
            ['date' => '-5 days', 'type' => 'email', 'response' => false],
            ['date' => '-1 day', 'type' => 'call', 'response' => true]
        ];
        
        $totalInteractions = count($engagementData);
        $responses = array_filter($engagementData, function($e) { return $e['response']; });
        $responseRate = (count($responses) / $totalInteractions) * 100;
        
        // Update contact metrics
        $contact->interaction_count = $totalInteractions;
        $contact->response_rate = $responseRate;
        $contact->last_interaction_date = date('Y-m-d', strtotime('-1 day'));
        $contact->last_interaction_type = 'call';
        $contact->save();
        
        // Verify metrics
        $contact = BeanFactory::getBean('mdeal_Contacts', $contact->id);
        $this->assertEquals(5, $contact->interaction_count);
        $this->assertEquals(80, $contact->response_rate);
    }
    
    /**
     * Test contact bulk operations
     */
    public function testContactBulkOperations()
    {
        // Create account
        $account = BeanFactory::newBean('mdeal_Accounts');
        $account->name = 'Bulk Test Corp';
        $account->save();
        $this->createdRecords['accounts'][] = $account->id;
        
        // Create multiple contacts
        $contactIds = [];
        for ($i = 0; $i < 5; $i++) {
            $contact = BeanFactory::newBean('mdeal_Contacts');
            $contact->first_name = "Bulk{$i}";
            $contact->last_name = "Test{$i}";
            $contact->email1 = "bulk{$i}@test.com";
            $contact->account_id = $account->id;
            $contact->save();
            $contactIds[] = $contact->id;
            $this->createdRecords['contacts'][] = $contact->id;
        }
        
        // Test bulk assignment
        $newUserId = create_guid();
        $this->bulkUpdateContacts($contactIds, ['assigned_user_id' => $newUserId]);
        
        // Verify updates
        foreach ($contactIds as $id) {
            $contact = BeanFactory::getBean('mdeal_Contacts', $id);
            $this->assertEquals($newUserId, $contact->assigned_user_id);
        }
        
        // Test bulk account change
        $newAccount = BeanFactory::newBean('mdeal_Accounts');
        $newAccount->name = 'New Bulk Corp';
        $newAccount->save();
        $this->createdRecords['accounts'][] = $newAccount->id;
        
        $this->bulkUpdateContacts($contactIds, ['account_id' => $newAccount->id]);
        
        // Verify account changes
        foreach ($contactIds as $id) {
            $contact = BeanFactory::getBean('mdeal_Contacts', $id);
            $this->assertEquals($newAccount->id, $contact->account_id);
        }
    }
    
    // Helper methods
    
    protected function findDuplicateContacts($contact)
    {
        global $db;
        
        $query = "SELECT id, first_name, last_name, email1 
                  FROM mdeal_contacts 
                  WHERE deleted = 0 
                  AND email1 = ?
                  AND id != ?";
        
        $result = $db->pQuery($query, [
            $contact->email1,
            $contact->id ?: ''
        ]);
        
        $duplicates = [];
        while ($row = $db->fetchByAssoc($result)) {
            $duplicates[] = $row;
        }
        
        return $duplicates;
    }
    
    protected function bulkUpdateContacts($contactIds, $updates)
    {
        foreach ($contactIds as $id) {
            $contact = BeanFactory::getBean('mdeal_Contacts', $id);
            foreach ($updates as $field => $value) {
                $contact->$field = $value;
            }
            $contact->save();
        }
    }
    
    protected function cleanupTestData()
    {
        global $db;
        
        $tables = [
            'contacts' => 'mdeal_contacts',
            'accounts' => 'mdeal_accounts',
            'deals' => 'mdeal_deals',
            'emails' => 'emails',
            'calls' => 'calls'
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
        if (!empty($this->createdRecords['contacts'])) {
            $contactIds = implode("','", $this->createdRecords['contacts']);
            $db->query("UPDATE mdeal_contacts_contacts SET deleted = 1 WHERE contact_id_a IN ('{$contactIds}') OR contact_id_b IN ('{$contactIds}')");
            $db->query("UPDATE mdeal_contacts_deals SET deleted = 1 WHERE contact_id IN ('{$contactIds}')");
        }
    }
}