<?php
/**
 * Integration test for CheckDuplicates AJAX action
 * Tests the complete duplicate detection workflow including AJAX handling
 */

namespace SuiteCRM\Tests\Unit\modules\Deals;

use SuiteCRM\Test\SuitePHPUnitFrameworkTestCase;
use DealsCheckDuplicates;

require_once 'modules/Deals/CheckDuplicates.php';
require_once 'tests/unit/phpunit/modules/Deals/fixtures/DuplicateDetectionFixtures.php';

use SuiteCRM\Tests\Unit\modules\Deals\fixtures\DuplicateDetectionFixtures;

class CheckDuplicatesIntegrationTest extends SuitePHPUnitFrameworkTestCase
{
    protected $db;
    protected $testDeals = [];
    protected $oldPost;
    protected $oldCurrentUser;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        global $db, $current_user;
        $this->db = $db;
        
        // Backup globals
        $this->oldPost = $_POST;
        $this->oldCurrentUser = $current_user;
        
        // Mock current user
        $current_user = new \stdClass();
        $current_user->id = 'test-user-id';
        $current_user->name = 'Test User';
        
        // Create test data
        $this->setupTestData();
    }
    
    protected function tearDown(): void
    {
        // Restore globals
        $_POST = $this->oldPost;
        $GLOBALS['current_user'] = $this->oldCurrentUser;
        
        // Clean up test data
        $this->cleanupTestData();
        
        parent::tearDown();
    }
    
    /**
     * Setup comprehensive test data
     */
    protected function setupTestData()
    {
        // Create test users
        $this->createTestUser('test-user-1', 'John', 'Doe');
        $this->createTestUser('test-user-2', 'Jane', 'Smith');
        
        // Create test accounts
        $accounts = [
            ['id' => 'test-acc-1', 'name' => 'Acme Corporation'],
            ['id' => 'test-acc-2', 'name' => 'Beta Technologies LLC'],
            ['id' => 'test-acc-3', 'name' => 'Gamma Industries Inc.']
        ];
        
        foreach ($accounts as $account) {
            $query = "INSERT INTO accounts (id, name, deleted, date_entered, date_modified) 
                     VALUES (?, ?, 0, NOW(), NOW())";
            $stmt = $this->db->getConnection()->prepare($query);
            $stmt->execute([$account['id'], $account['name']]);
        }
        
        // Create test deals with various scenarios
        $deals = [
            // Exact duplicate scenario
            [
                'id' => 'test-int-deal-1',
                'name' => 'Enterprise Software License Q4 2024',
                'account_id' => 'test-acc-1',
                'amount' => 250000,
                'sales_stage' => 'Proposal/Price Quote',
                'assigned_user_id' => 'test-user-1',
                'email' => 'sales@acme.com'
            ],
            [
                'id' => 'test-int-deal-2',
                'name' => 'Enterprise Software License Q4 2024', // Exact duplicate name
                'account_id' => 'test-acc-1', // Same account
                'amount' => 250000, // Same amount
                'sales_stage' => 'Negotiation/Review',
                'assigned_user_id' => 'test-user-2',
                'email' => 'sales@acme.com' // Same email
            ],
            // Fuzzy duplicate scenario
            [
                'id' => 'test-int-deal-3',
                'name' => 'Ent Software Lic Q4 24', // Abbreviated
                'account_id' => 'test-acc-1',
                'amount' => 245000, // Within 10%
                'sales_stage' => 'Qualification',
                'assigned_user_id' => 'test-user-1',
                'email' => 'contact@acme.com' // Different email
            ],
            // Different company, similar deal
            [
                'id' => 'test-int-deal-4',
                'name' => 'Enterprise Software License Q4 2024',
                'account_id' => 'test-acc-2', // Different account
                'amount' => 350000,
                'sales_stage' => 'Closed Won',
                'assigned_user_id' => 'test-user-2',
                'email' => 'sales@beta-tech.com'
            ]
        ];
        
        foreach ($deals as $deal) {
            $this->createTestDeal($deal);
        }
    }
    
    /**
     * Create a test user
     */
    protected function createTestUser($id, $firstName, $lastName)
    {
        $query = "INSERT INTO users (id, first_name, last_name, user_name, deleted, date_entered, date_modified) 
                 VALUES (?, ?, ?, ?, 0, NOW(), NOW())";
        $stmt = $this->db->getConnection()->prepare($query);
        $stmt->execute([$id, $firstName, $lastName, strtolower($firstName . '.' . $lastName)]);
    }
    
    /**
     * Create a test deal with all relationships
     */
    protected function createTestDeal($dealData)
    {
        $this->testDeals[] = $dealData['id'];
        
        // Insert deal
        $query = "INSERT INTO deals (id, name, account_id, amount, sales_stage, assigned_user_id, deleted, date_entered, date_modified) 
                 VALUES (?, ?, ?, ?, ?, ?, 0, NOW(), NOW())";
        $stmt = $this->db->getConnection()->prepare($query);
        $stmt->execute([
            $dealData['id'],
            $dealData['name'],
            $dealData['account_id'],
            $dealData['amount'],
            $dealData['sales_stage'],
            $dealData['assigned_user_id']
        ]);
        
        // Add email if provided
        if (!empty($dealData['email'])) {
            $emailId = 'test-email-' . $dealData['id'];
            $query = "INSERT INTO email_addresses (id, email_address, deleted, date_entered, date_modified) 
                     VALUES (?, ?, 0, NOW(), NOW())";
            $stmt = $this->db->getConnection()->prepare($query);
            $stmt->execute([$emailId, $dealData['email']]);
            
            // Link email to deal
            $query = "INSERT INTO email_addr_bean_rel (id, email_address_id, bean_id, bean_module, deleted, date_created, date_modified) 
                     VALUES (?, ?, ?, 'Deals', 0, NOW(), NOW())";
            $stmt = $this->db->getConnection()->prepare($query);
            $stmt->execute(['test-rel-' . $dealData['id'], $emailId, $dealData['id']]);
        }
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData()
    {
        // Clean up email relationships
        $this->db->query("DELETE FROM email_addr_bean_rel WHERE bean_id LIKE 'test-int-deal-%'");
        
        // Clean up emails
        $this->db->query("DELETE FROM email_addresses WHERE id LIKE 'test-email-test-int-deal-%'");
        
        // Clean up deals
        foreach ($this->testDeals as $dealId) {
            $this->db->query("DELETE FROM deals WHERE id = " . $this->db->quote($dealId));
        }
        
        // Clean up accounts
        $this->db->query("DELETE FROM accounts WHERE id LIKE 'test-acc-%'");
        
        // Clean up users
        $this->db->query("DELETE FROM users WHERE id LIKE 'test-user-%'");
    }
    
    /**
     * Test complete AJAX workflow
     */
    public function testCompleteAjaxWorkflow()
    {
        // Simulate AJAX request
        $_POST = [
            'check_data' => json_encode([
                'name' => 'Enterprise Software License Q4 2024',
                'account_name' => 'Acme Corporation',
                'amount' => 250000,
                'email1' => 'sales@acme.com'
            ]),
            'record_id' => ''
        ];
        
        // Capture output
        ob_start();
        $handler = new DealsCheckDuplicates();
        $handler->process();
        $output = ob_get_clean();
        
        // Parse JSON response
        $response = json_decode($output, true);
        
        // Verify response structure
        $this->assertIsArray($response);
        $this->assertArrayHasKey('duplicates', $response);
        $this->assertArrayHasKey('success', $response);
        $this->assertTrue($response['success']);
        
        // Verify duplicates found
        $this->assertNotEmpty($response['duplicates']);
        
        // Check duplicate details
        $duplicateIds = array_column($response['duplicates'], 'id');
        $this->assertContains('test-int-deal-1', $duplicateIds);
        $this->assertContains('test-int-deal-2', $duplicateIds);
        $this->assertContains('test-int-deal-3', $duplicateIds); // Fuzzy match
        
        // Verify all required fields in response
        foreach ($response['duplicates'] as $duplicate) {
            $this->assertArrayHasKey('id', $duplicate);
            $this->assertArrayHasKey('name', $duplicate);
            $this->assertArrayHasKey('account_name', $duplicate);
            $this->assertArrayHasKey('amount', $duplicate);
            $this->assertArrayHasKey('amount_formatted', $duplicate);
            $this->assertArrayHasKey('sales_stage', $duplicate);
            $this->assertArrayHasKey('assigned_user_name', $duplicate);
            $this->assertArrayHasKey('score', $duplicate);
            $this->assertArrayHasKey('date_entered', $duplicate);
            
            // Verify formatting
            $this->assertMatchesRegularExpression('/^\$[\d,]+\.\d{2}$/', $duplicate['amount_formatted']);
            $this->assertIsNumeric($duplicate['score']);
            $this->assertGreaterThan(50, $duplicate['score']); // Only high-confidence duplicates
        }
    }
    
    /**
     * Test with current record exclusion
     */
    public function testWithCurrentRecordExclusion()
    {
        $_POST = [
            'check_data' => json_encode([
                'name' => 'Enterprise Software License Q4 2024',
                'account_name' => 'Acme Corporation'
            ]),
            'record_id' => 'test-int-deal-1' // Exclude this record
        ];
        
        ob_start();
        $handler = new DealsCheckDuplicates();
        $handler->process();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        // Should not include the excluded record
        $duplicateIds = array_column($response['duplicates'], 'id');
        $this->assertNotContains('test-int-deal-1', $duplicateIds);
        $this->assertContains('test-int-deal-2', $duplicateIds);
    }
    
    /**
     * Test empty check data handling
     */
    public function testEmptyCheckData()
    {
        $_POST = [
            'check_data' => json_encode([]),
            'record_id' => ''
        ];
        
        ob_start();
        $handler = new DealsCheckDuplicates();
        $handler->process();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        // Should return empty duplicates array
        $this->assertIsArray($response);
        $this->assertEmpty($response['duplicates']);
        $this->assertTrue($response['success']);
    }
    
    /**
     * Test malformed JSON handling
     */
    public function testMalformedJsonHandling()
    {
        $_POST = [
            'check_data' => '{invalid json',
            'record_id' => ''
        ];
        
        ob_start();
        $handler = new DealsCheckDuplicates();
        $handler->process();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        // Should handle gracefully
        $this->assertIsArray($response);
        $this->assertEmpty($response['duplicates']);
        $this->assertTrue($response['success']);
    }
    
    /**
     * Test SQL injection in AJAX parameters
     */
    public function testSqlInjectionInAjaxParameters()
    {
        $injectionStrings = DuplicateDetectionFixtures::getSQLInjectionStrings();
        
        foreach ($injectionStrings as $injection) {
            $_POST = [
                'check_data' => json_encode([
                    'name' => $injection,
                    'account_name' => $injection,
                    'amount' => $injection,
                    'email1' => $injection
                ]),
                'record_id' => $injection
            ];
            
            ob_start();
            $handler = new DealsCheckDuplicates();
            $handler->process();
            $output = ob_get_clean();
            
            // Should not cause errors
            $response = json_decode($output, true);
            $this->assertIsArray($response, "Failed with injection: $injection");
            $this->assertArrayHasKey('success', $response);
        }
        
        // Verify database integrity
        $result = $this->db->query("SELECT COUNT(*) as count FROM deals");
        $row = $this->db->fetchByAssoc($result);
        $this->assertGreaterThan(0, $row['count']); // Table still exists
    }
    
    /**
     * Test performance with bulk data
     */
    public function testPerformanceWithBulkData()
    {
        // Create bulk test data
        $bulkDeals = DuplicateDetectionFixtures::generateBulkTestData(100);
        
        foreach ($bulkDeals as $deal) {
            $this->createTestDeal($deal);
        }
        
        $_POST = [
            'check_data' => json_encode([
                'name' => 'Acme Software License',
                'account_name' => 'Acme Corporation',
                'amount' => 50000
            ]),
            'record_id' => ''
        ];
        
        $startTime = microtime(true);
        
        ob_start();
        $handler = new DealsCheckDuplicates();
        $handler->process();
        $output = ob_get_clean();
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // Should complete within reasonable time
        $this->assertLessThan(2.0, $executionTime);
        
        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        
        // Should limit results
        $this->assertLessThanOrEqual(10, count($response['duplicates']));
    }
    
    /**
     * Test scoring accuracy
     */
    public function testScoringAccuracy()
    {
        $_POST = [
            'check_data' => json_encode([
                'name' => 'Enterprise Software License Q4 2024',
                'account_name' => 'Acme Corporation',
                'amount' => 250000,
                'email1' => 'sales@acme.com'
            ]),
            'record_id' => ''
        ];
        
        ob_start();
        $handler = new DealsCheckDuplicates();
        $handler->process();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $duplicates = $response['duplicates'];
        
        // Sort by ID for consistent testing
        usort($duplicates, function($a, $b) {
            return strcmp($a['id'], $b['id']);
        });
        
        // test-int-deal-1 and test-int-deal-2 should have highest scores (exact matches)
        $exactMatches = array_filter($duplicates, function($d) {
            return in_array($d['id'], ['test-int-deal-1', 'test-int-deal-2']);
        });
        
        foreach ($exactMatches as $match) {
            $this->assertGreaterThan(90, $match['score'], "Exact match should have score > 90");
        }
        
        // test-int-deal-3 should have lower score (fuzzy match)
        $fuzzyMatch = array_filter($duplicates, function($d) {
            return $d['id'] === 'test-int-deal-3';
        });
        
        if (!empty($fuzzyMatch)) {
            $fuzzyMatch = reset($fuzzyMatch);
            $this->assertLessThan(90, $fuzzyMatch['score'], "Fuzzy match should have score < 90");
            $this->assertGreaterThan(50, $fuzzyMatch['score'], "Fuzzy match should have score > 50");
        }
    }
    
    /**
     * Test assigned user name formatting
     */
    public function testAssignedUserNameFormatting()
    {
        $_POST = [
            'check_data' => json_encode([
                'name' => 'Enterprise Software License'
            ]),
            'record_id' => ''
        ];
        
        ob_start();
        $handler = new DealsCheckDuplicates();
        $handler->process();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        foreach ($response['duplicates'] as $duplicate) {
            // Verify user name is properly formatted
            $this->assertNotEmpty($duplicate['assigned_user_name']);
            $this->assertStringNotContainsString('  ', $duplicate['assigned_user_name']); // No double spaces
            
            // Should be "FirstName LastName" format
            if (strpos($duplicate['id'], 'test-int-deal-') === 0) {
                $this->assertMatchesRegularExpression('/^[A-Z][a-z]+ [A-Z][a-z]+$/', $duplicate['assigned_user_name']);
            }
        }
    }
}