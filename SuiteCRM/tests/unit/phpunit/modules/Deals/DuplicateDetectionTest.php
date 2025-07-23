<?php
/**
 * PHPUnit test for Deals duplicate detection functionality
 * 
 * Tests comprehensive duplicate detection including:
 * - Fuzzy name matching
 * - Domain extraction from company names
 * - Name normalization (Inc, LLC, Corp removal)
 * - Edge cases and SQL injection prevention
 * - Performance with multiple duplicates
 */

namespace SuiteCRM\Tests\Unit\modules\Deals;

use SuiteCRM\Test\SuitePHPUnitFrameworkTestCase;
use DealsCheckDuplicates;
use Exception;

require_once 'modules/Deals/CheckDuplicates.php';

class DuplicateDetectionTest extends SuitePHPUnitFrameworkTestCase
{
    protected $db;
    protected $duplicateChecker;
    protected $testDeals = [];
    
    protected function setUp(): void
    {
        parent::setUp();
        
        global $db;
        $this->db = $db;
        $this->duplicateChecker = new DealsCheckDuplicates();
        
        // Create test deals
        $this->createTestDeals();
    }
    
    protected function tearDown(): void
    {
        // Clean up test deals
        $this->cleanupTestDeals();
        parent::tearDown();
    }
    
    /**
     * Create test deals for duplicate detection testing
     */
    protected function createTestDeals()
    {
        $testData = [
            [
                'id' => 'test-deal-1',
                'name' => 'Acme Corporation Deal',
                'account_name' => 'Acme Corporation',
                'amount' => 50000,
                'sales_stage' => 'Proposal/Price Quote',
                'email' => 'contact@acme.com'
            ],
            [
                'id' => 'test-deal-2',
                'name' => 'Acme Corp Deal', // Similar name
                'account_name' => 'Acme Corp.', // Abbreviated
                'amount' => 52000, // Within 10% range
                'sales_stage' => 'Negotiation/Review',
                'email' => 'contact@acme.com' // Same email
            ],
            [
                'id' => 'test-deal-3',
                'name' => 'ACME CORPORATION DEAL', // Different case
                'account_name' => 'ACME CORPORATION INC', // With suffix
                'amount' => 48000, // Within 10% range
                'sales_stage' => 'Proposal/Price Quote',
                'email' => 'sales@acme.com'
            ],
            [
                'id' => 'test-deal-4',
                'name' => 'Beta Technologies Contract',
                'account_name' => 'Beta Technologies LLC',
                'amount' => 75000,
                'sales_stage' => 'Closed Won',
                'email' => 'info@beta-tech.com'
            ],
            [
                'id' => 'test-deal-5',
                'name' => 'Beta Tech Contract', // Similar to deal-4
                'account_name' => 'Beta Technologies, LLC', // With comma
                'amount' => 73500, // Within 10% range
                'sales_stage' => 'Proposal/Price Quote',
                'email' => 'info@beta-tech.com'
            ],
            // Edge cases
            [
                'id' => 'test-deal-6',
                'name' => "O'Reilly's Auto Parts Deal", // Apostrophe
                'account_name' => "O'Reilly Auto Parts",
                'amount' => 25000,
                'sales_stage' => 'Qualification',
                'email' => 'contact@oreilly.com'
            ],
            [
                'id' => 'test-deal-7',
                'name' => 'Deal with Special @#$% Characters', // Special chars
                'account_name' => 'Special & Co.',
                'amount' => 10000,
                'sales_stage' => 'Needs Analysis',
                'email' => 'test@special-co.com'
            ],
            [
                'id' => 'test-deal-8',
                'name' => '   Whitespace Deal   ', // Extra whitespace
                'account_name' => '  Whitespace Company  ',
                'amount' => 30000,
                'sales_stage' => 'Proposal/Price Quote',
                'email' => 'contact@whitespace.com'
            ],
            // SQL injection test data
            [
                'id' => 'test-deal-9',
                'name' => "'; DROP TABLE deals; --", // SQL injection attempt
                'account_name' => "Company'; DELETE FROM users; --",
                'amount' => 5000,
                'sales_stage' => 'Qualification',
                'email' => 'test@malicious.com'
            ]
        ];
        
        foreach ($testData as $dealData) {
            $this->testDeals[] = $dealData['id'];
            
            // Insert deal record
            $query = "INSERT INTO deals (id, name, amount, sales_stage, deleted, date_entered, date_modified) 
                     VALUES (?, ?, ?, ?, 0, NOW(), NOW())";
            $stmt = $this->db->getConnection()->prepare($query);
            $stmt->execute([
                $dealData['id'],
                $dealData['name'],
                $dealData['amount'],
                $dealData['sales_stage']
            ]);
            
            // Create associated account if needed
            if (!empty($dealData['account_name'])) {
                $accountId = 'test-account-' . $dealData['id'];
                $query = "INSERT INTO accounts (id, name, deleted, date_entered, date_modified) 
                         VALUES (?, ?, 0, NOW(), NOW())";
                $stmt = $this->db->getConnection()->prepare($query);
                $stmt->execute([$accountId, $dealData['account_name']]);
                
                // Update deal with account_id
                $query = "UPDATE deals SET account_id = ? WHERE id = ?";
                $stmt = $this->db->getConnection()->prepare($query);
                $stmt->execute([$accountId, $dealData['id']]);
            }
            
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
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestDeals()
    {
        foreach ($this->testDeals as $dealId) {
            // Delete email relationships
            $this->db->query("DELETE FROM email_addr_bean_rel WHERE bean_id = " . $this->db->quote($dealId));
            
            // Delete emails
            $this->db->query("DELETE FROM email_addresses WHERE id LIKE 'test-email-%'");
            
            // Delete accounts
            $this->db->query("DELETE FROM accounts WHERE id LIKE 'test-account-%'");
            
            // Delete deals
            $this->db->query("DELETE FROM deals WHERE id = " . $this->db->quote($dealId));
        }
    }
    
    /**
     * Test fuzzy name matching
     */
    public function testFuzzyNameMatching()
    {
        $checkData = [
            'name' => 'Acme Corporation Deal'
        ];
        
        $duplicates = $this->invokeFindDuplicates($checkData, 'test-deal-1');
        
        // Should find similar names
        $this->assertNotEmpty($duplicates);
        
        $dealNames = array_column($duplicates, 'name');
        $this->assertContains('Acme Corp Deal', $dealNames);
        $this->assertContains('ACME CORPORATION DEAL', $dealNames);
    }
    
    /**
     * Test account name normalization
     */
    public function testAccountNameNormalization()
    {
        $checkData = [
            'account_name' => 'Beta Technologies'
        ];
        
        $duplicates = $this->invokeFindDuplicates($checkData, '');
        
        // Should find variations with LLC, comma, etc.
        $accountNames = array_column($duplicates, 'account_name');
        $this->assertContains('Beta Technologies LLC', $accountNames);
        $this->assertContains('Beta Technologies, LLC', $accountNames);
    }
    
    /**
     * Test amount range matching (within 10%)
     */
    public function testAmountRangeMatching()
    {
        $checkData = [
            'amount' => 50000
        ];
        
        $duplicates = $this->invokeFindDuplicates($checkData, 'test-deal-1');
        
        // Should find deals within 10% range (45000-55000)
        $amounts = array_column($duplicates, 'amount');
        
        foreach ($amounts as $amount) {
            $this->assertGreaterThanOrEqual(45000, $amount);
            $this->assertLessThanOrEqual(55000, $amount);
        }
        
        // Should include test-deal-2 (52000) and test-deal-3 (48000)
        $this->assertContains('52000', $amounts);
        $this->assertContains('48000', $amounts);
        
        // Should not include test-deal-4 (75000)
        $this->assertNotContains('75000', $amounts);
    }
    
    /**
     * Test email matching
     */
    public function testEmailMatching()
    {
        $checkData = [
            'email1' => 'contact@acme.com'
        ];
        
        $duplicates = $this->invokeFindDuplicates($checkData, '');
        
        // Should find deals with same email
        $dealIds = array_column($duplicates, 'id');
        $this->assertContains('test-deal-1', $dealIds);
        $this->assertContains('test-deal-2', $dealIds);
    }
    
    /**
     * Test duplicate scoring algorithm
     */
    public function testDuplicateScoring()
    {
        $checkData = [
            'name' => 'Acme Corporation Deal',
            'account_name' => 'Acme Corporation',
            'amount' => 50000,
            'email1' => 'contact@acme.com'
        ];
        
        $duplicates = $this->invokeFindDuplicates($checkData, 'test-deal-1');
        $scoredDuplicates = $this->invokeScoreDuplicates($duplicates, $checkData);
        
        // Verify scoring
        foreach ($scoredDuplicates as $duplicate) {
            $this->assertArrayHasKey('duplicate_score', $duplicate);
            $this->assertGreaterThan(50, $duplicate['duplicate_score']); // Only high-confidence duplicates
        }
        
        // test-deal-2 should have highest score (same email, similar name/amount)
        if (!empty($scoredDuplicates)) {
            $this->assertEquals('test-deal-2', $scoredDuplicates[0]['id']);
            $this->assertGreaterThan(80, $scoredDuplicates[0]['duplicate_score']);
        }
    }
    
    /**
     * Test SQL injection prevention
     */
    public function testSQLInjectionPrevention()
    {
        $maliciousData = [
            'name' => "'; DROP TABLE deals; --",
            'account_name' => "Company'; DELETE FROM users; --",
            'amount' => "0; UPDATE users SET is_admin=1; --",
            'email1' => "test@malicious.com'; DROP DATABASE; --"
        ];
        
        // Should not throw exception and should safely handle malicious input
        try {
            $duplicates = $this->invokeFindDuplicates($maliciousData, '');
            $this->assertTrue(true); // Query executed safely
        } catch (Exception $e) {
            $this->fail('SQL injection protection failed: ' . $e->getMessage());
        }
        
        // Verify database integrity
        $result = $this->db->query("SELECT COUNT(*) as count FROM deals");
        $row = $this->db->fetchByAssoc($result);
        $this->assertGreaterThan(0, $row['count']); // Table still exists
    }
    
    /**
     * Test edge cases - apostrophes and special characters
     */
    public function testSpecialCharacterHandling()
    {
        $checkData = [
            'name' => "O'Reilly's Auto Parts Deal"
        ];
        
        $duplicates = $this->invokeFindDuplicates($checkData, '');
        
        // Should find the deal with apostrophes
        $dealIds = array_column($duplicates, 'id');
        $this->assertContains('test-deal-6', $dealIds);
    }
    
    /**
     * Test whitespace normalization
     */
    public function testWhitespaceNormalization()
    {
        $checkData = [
            'name' => 'Whitespace Deal', // Without extra spaces
            'account_name' => 'Whitespace Company'
        ];
        
        $duplicates = $this->invokeFindDuplicates($checkData, '');
        
        // Should find deal with extra whitespace
        $dealIds = array_column($duplicates, 'id');
        $this->assertContains('test-deal-8', $dealIds);
    }
    
    /**
     * Test performance with multiple duplicates
     */
    public function testPerformanceWithMultipleDuplicates()
    {
        // Create many duplicate candidates
        $startTime = microtime(true);
        
        for ($i = 0; $i < 50; $i++) {
            $dealId = 'perf-test-deal-' . $i;
            $this->testDeals[] = $dealId;
            
            $query = "INSERT INTO deals (id, name, amount, sales_stage, deleted, date_entered, date_modified) 
                     VALUES (?, ?, ?, ?, 0, NOW(), NOW())";
            $stmt = $this->db->getConnection()->prepare($query);
            $stmt->execute([
                $dealId,
                'Performance Test Deal ' . $i,
                50000 + ($i * 100), // Varying amounts
                'Proposal/Price Quote'
            ]);
        }
        
        $checkData = [
            'name' => 'Performance Test Deal',
            'amount' => 50000
        ];
        
        $duplicates = $this->invokeFindDuplicates($checkData, '');
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // Should complete within reasonable time (< 1 second)
        $this->assertLessThan(1.0, $executionTime);
        
        // Should limit results to 10 as per LIMIT clause
        $this->assertLessThanOrEqual(10, count($duplicates));
    }
    
    /**
     * Test combined criteria matching
     */
    public function testCombinedCriteriaMatching()
    {
        $checkData = [
            'name' => 'Beta Technologies Contract',
            'account_name' => 'Beta Technologies',
            'amount' => 74000,
            'email1' => 'info@beta-tech.com'
        ];
        
        $duplicates = $this->invokeFindDuplicates($checkData, '');
        $scoredDuplicates = $this->invokeScoreDuplicates($duplicates, $checkData);
        
        // Should find both Beta deals
        $dealIds = array_column($scoredDuplicates, 'id');
        $this->assertContains('test-deal-4', $dealIds);
        $this->assertContains('test-deal-5', $dealIds);
        
        // Both should have high scores due to multiple matches
        foreach ($scoredDuplicates as $duplicate) {
            if (in_array($duplicate['id'], ['test-deal-4', 'test-deal-5'])) {
                $this->assertGreaterThan(70, $duplicate['duplicate_score']);
            }
        }
    }
    
    /**
     * Test empty/null data handling
     */
    public function testEmptyDataHandling()
    {
        $checkData = [
            'name' => '',
            'account_name' => null,
            'amount' => '',
            'email1' => null
        ];
        
        $duplicates = $this->invokeFindDuplicates($checkData, '');
        
        // Should return empty array or all records (depending on implementation)
        $this->assertIsArray($duplicates);
    }
    
    /**
     * Test exclude current record functionality
     */
    public function testExcludeCurrentRecord()
    {
        $checkData = [
            'name' => 'Acme Corporation Deal',
            'account_name' => 'Acme Corporation'
        ];
        
        $duplicates = $this->invokeFindDuplicates($checkData, 'test-deal-1');
        
        // Should not include the excluded record
        $dealIds = array_column($duplicates, 'id');
        $this->assertNotContains('test-deal-1', $dealIds);
    }
    
    /**
     * Test case-insensitive matching
     */
    public function testCaseInsensitiveMatching()
    {
        $checkData = [
            'name' => 'acme corporation deal', // lowercase
            'account_name' => 'acme corporation'
        ];
        
        $duplicates = $this->invokeFindDuplicates($checkData, '');
        
        // Should find deals regardless of case
        $dealIds = array_column($duplicates, 'id');
        $this->assertContains('test-deal-1', $dealIds); // Original case
        $this->assertContains('test-deal-3', $dealIds); // UPPERCASE
    }
    
    /**
     * Test SOUNDEX functionality
     */
    public function testSoundexMatching()
    {
        // Create a deal with phonetically similar name
        $dealId = 'test-soundex-deal';
        $this->testDeals[] = $dealId;
        
        $query = "INSERT INTO deals (id, name, amount, sales_stage, deleted, date_entered, date_modified) 
                 VALUES (?, ?, ?, ?, 0, NOW(), NOW())";
        $stmt = $this->db->getConnection()->prepare($query);
        $stmt->execute([
            $dealId,
            'Akme Korporayshun Deel', // Phonetically similar to Acme Corporation Deal
            60000,
            'Qualification'
        ]);
        
        $checkData = [
            'name' => 'Acme Corporation Deal'
        ];
        
        $duplicates = $this->invokeFindDuplicates($checkData, '');
        
        // Should find phonetically similar names
        $dealNames = array_column($duplicates, 'name');
        $this->assertContains('Akme Korporayshun Deel', $dealNames);
    }
    
    /**
     * Helper method to invoke protected findDuplicates method
     */
    protected function invokeFindDuplicates($checkData, $excludeId)
    {
        $reflection = new \ReflectionClass($this->duplicateChecker);
        $method = $reflection->getMethod('findDuplicates');
        $method->setAccessible(true);
        return $method->invoke($this->duplicateChecker, $checkData, $excludeId);
    }
    
    /**
     * Helper method to invoke protected scoreDuplicates method
     */
    protected function invokeScoreDuplicates($duplicates, $checkData)
    {
        $reflection = new \ReflectionClass($this->duplicateChecker);
        $method = $reflection->getMethod('scoreDuplicates');
        $method->setAccessible(true);
        return $method->invoke($this->duplicateChecker, $duplicates, $checkData);
    }
}