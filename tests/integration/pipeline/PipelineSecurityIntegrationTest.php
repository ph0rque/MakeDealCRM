<?php

namespace Tests\Integration\Pipeline;

use Tests\DatabaseTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

/**
 * Integration tests for Pipeline Security
 * Tests authorization, input validation, and security vulnerabilities
 */
class PipelineSecurityIntegrationTest extends DatabaseTestCase
{
    protected Client $httpClient;
    protected string $baseUrl = 'http://localhost:8080';
    protected array $testUsers = [
        'admin' => ['role' => 'admin', 'permissions' => ['all']],
        'manager' => ['role' => 'manager', 'permissions' => ['read', 'update', 'delete']],
        'sales_rep' => ['role' => 'sales_rep', 'permissions' => ['read', 'update']],
        'guest' => ['role' => 'guest', 'permissions' => ['read']]
    ];

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->httpClient = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'http_errors' => false
        ]);
        
        $this->createTestUsers();
        $this->createTestDeals();
    }

    /**
     * @test
     * @group integration
     * @group security
     * @group authorization
     */
    public function testRoleBasedAccessControl(): void
    {
        foreach ($this->testUsers as $username => $userData) {
            $authHeaders = $this->getAuthHeaders($username);
            
            // Test pipeline stages access
            $response = $this->httpClient->get('/api/pipeline/stages', [
                'headers' => $authHeaders
            ]);
            
            if (in_array('read', $userData['permissions']) || in_array('all', $userData['permissions'])) {
                $this->assertEquals(200, $response->getStatusCode(), 
                    "User $username should have read access to pipeline stages");
            } else {
                $this->assertEquals(403, $response->getStatusCode(),
                    "User $username should not have access to pipeline stages");
            }
            
            // Test deal movement
            $response = $this->httpClient->post('/api/pipeline/move-deal', [
                'headers' => $authHeaders,
                'json' => [
                    'deal_id' => $this->createTestDeal(['assigned_user_id' => 'test-user-1']),
                    'from_stage' => 'sourcing',
                    'to_stage' => 'screening'
                ]
            ]);
            
            if (in_array('update', $userData['permissions']) || in_array('all', $userData['permissions'])) {
                $this->assertEquals(200, $response->getStatusCode(),
                    "User $username should be able to move deals");
            } else {
                $this->assertEquals(403, $response->getStatusCode(),
                    "User $username should not be able to move deals");
            }
        }
    }

    /**
     * @test
     * @group integration
     * @group security
     * @group deal-ownership
     */
    public function testDealOwnershipSecurity(): void
    {
        // Create deals owned by different users
        $user1Deal = $this->createTestDeal(['assigned_user_id' => 'user-1']);
        $user2Deal = $this->createTestDeal(['assigned_user_id' => 'user-2']);
        
        $user1Headers = $this->getAuthHeaders('user1');
        $user2Headers = $this->getAuthHeaders('user2');
        
        // User 1 should only access their own deals
        $response = $this->httpClient->get("/api/pipeline/deals/{$user1Deal}", [
            'headers' => $user1Headers
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        
        // User 1 should not access user 2's deals
        $response = $this->httpClient->get("/api/pipeline/deals/{$user2Deal}", [
            'headers' => $user1Headers
        ]);
        $this->assertEquals(403, $response->getStatusCode());
        
        // User 1 should not be able to move user 2's deals
        $response = $this->httpClient->post('/api/pipeline/move-deal', [
            'headers' => $user1Headers,
            'json' => [
                'deal_id' => $user2Deal,
                'from_stage' => 'sourcing',
                'to_stage' => 'screening'
            ]
        ]);
        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * @test
     * @group integration
     * @group security
     * @group sql-injection
     */
    public function testSQLInjectionPrevention(): void
    {
        $authHeaders = $this->getAuthHeaders('admin');
        
        $sqlInjectionPayloads = [
            "'; DROP TABLE deals; --",
            "' OR '1'='1",
            "' UNION SELECT * FROM users --",
            "'; UPDATE deals SET amount = 0; --",
            "1' OR '1'='1' --",
            "admin'/**/OR/**/1=1#",
            "' OR 1=1 LIMIT 1 OFFSET 1 --"
        ];
        
        foreach ($sqlInjectionPayloads as $payload) {
            // Test in deal_id parameter
            $response = $this->httpClient->post('/api/pipeline/move-deal', [
                'headers' => $authHeaders,
                'json' => [
                    'deal_id' => $payload,
                    'from_stage' => 'sourcing',
                    'to_stage' => 'screening'
                ]
            ]);
            
            // Should return validation error, not execute SQL
            $this->assertNotEquals(200, $response->getStatusCode(),
                "SQL injection payload should be rejected: $payload");
            
            $data = json_decode($response->getBody()->getContents(), true);
            $this->assertArrayHasKey('error', $data);
            
            // Test in stage parameter
            $response = $this->httpClient->post('/api/pipeline/move-deal', [
                'headers' => $authHeaders,
                'json' => [
                    'deal_id' => $this->createTestDeal(),
                    'from_stage' => $payload,
                    'to_stage' => 'screening'
                ]
            ]);
            
            $this->assertNotEquals(200, $response->getStatusCode(),
                "SQL injection in stage parameter should be rejected: $payload");
        }
        
        // Verify database integrity after injection attempts
        $dealCount = $this->getDatabaseRecordCount('deals');
        $this->assertGreaterThan(0, $dealCount, 'Deals table should not be dropped');
    }

    /**
     * @test
     * @group integration
     * @group security
     * @group xss-prevention
     */
    public function testXSSPrevention(): void
    {
        $authHeaders = $this->getAuthHeaders('admin');
        
        $xssPayloads = [
            '<script>alert("XSS")</script>',
            '"><script>alert("XSS")</script>',
            '<img src="x" onerror="alert(\'XSS\')">',
            'javascript:alert("XSS")',
            '<svg onload="alert(\'XSS\')">',
            '<iframe src="javascript:alert(\'XSS\')"></iframe>',
            '<object data="javascript:alert(\'XSS\')"></object>'
        ];
        
        foreach ($xssPayloads as $payload) {
            // Create deal with XSS payload in name
            $dealId = $this->createTestDeal(['name' => $payload]);
            
            // Get deal details
            $response = $this->httpClient->get("/api/pipeline/deals/{$dealId}", [
                'headers' => $authHeaders
            ]);
            
            $this->assertEquals(200, $response->getStatusCode());
            $data = json_decode($response->getBody()->getContents(), true);
            
            // Verify payload is sanitized
            $returnedName = $data['deal']['name'];
            $this->assertNotEquals($payload, $returnedName,
                'XSS payload should be sanitized');
            $this->assertStringNotContainsString('<script>', $returnedName,
                'Script tags should be removed');
            $this->assertStringNotContainsString('javascript:', $returnedName,
                'JavaScript protocol should be removed');
        }
    }

    /**
     * @test
     * @group integration
     * @group security
     * @group csrf-protection
     */
    public function testCSRFProtection(): void
    {
        $validToken = $this->getValidCSRFToken();
        $invalidTokens = [
            '',
            'invalid_token',
            'expired_token_12345',
            str_repeat('a', 64), // Valid length but wrong token
        ];
        
        foreach ($invalidTokens as $token) {
            $response = $this->httpClient->post('/api/pipeline/move-deal', [
                'headers' => array_merge(
                    $this->getAuthHeaders('admin'),
                    ['X-CSRF-Token' => $token]
                ),
                'json' => [
                    'deal_id' => $this->createTestDeal(),
                    'from_stage' => 'sourcing',
                    'to_stage' => 'screening'
                ]
            ]);
            
            $this->assertEquals(403, $response->getStatusCode(),
                "Request with invalid CSRF token should be rejected: $token");
            
            $data = json_decode($response->getBody()->getContents(), true);
            $this->assertArrayHasKey('error', $data);
            $this->assertStringContainsString('CSRF', $data['error']);
        }
        
        // Valid token should work
        $response = $this->httpClient->post('/api/pipeline/move-deal', [
            'headers' => array_merge(
                $this->getAuthHeaders('admin'),
                ['X-CSRF-Token' => $validToken]
            ),
            'json' => [
                'deal_id' => $this->createTestDeal(),
                'from_stage' => 'sourcing',
                'to_stage' => 'screening'
            ]
        ]);
        
        $this->assertEquals(200, $response->getStatusCode(),
            'Request with valid CSRF token should succeed');
    }

    /**
     * @test
     * @group integration
     * @group security
     * @group input-validation
     */
    public function testInputValidation(): void
    {
        $authHeaders = $this->getAuthHeaders('admin');
        
        // Test invalid stage names
        $invalidStages = [
            'invalid_stage',
            '',
            null,
            str_repeat('a', 256), // Too long
            '<script>alert("XSS")</script>',
            'DROP TABLE stages;'
        ];
        
        foreach ($invalidStages as $stage) {
            $response = $this->httpClient->post('/api/pipeline/move-deal', [
                'headers' => $authHeaders,
                'json' => [
                    'deal_id' => $this->createTestDeal(),
                    'from_stage' => 'sourcing',
                    'to_stage' => $stage
                ]
            ]);
            
            $this->assertNotEquals(200, $response->getStatusCode(),
                "Invalid stage should be rejected: " . json_encode($stage));
        }
        
        // Test invalid deal IDs
        $invalidDealIds = [
            '',
            null,
            'not-a-uuid',
            str_repeat('a', 256),
            '<script>alert("XSS")</script>',
            '../../etc/passwd'
        ];
        
        foreach ($invalidDealIds as $dealId) {
            $response = $this->httpClient->post('/api/pipeline/move-deal', [
                'headers' => $authHeaders,
                'json' => [
                    'deal_id' => $dealId,
                    'from_stage' => 'sourcing',
                    'to_stage' => 'screening'
                ]
            ]);
            
            $this->assertNotEquals(200, $response->getStatusCode(),
                "Invalid deal ID should be rejected: " . json_encode($dealId));
        }
    }

    /**
     * @test
     * @group integration
     * @group security
     * @group rate-limiting
     */
    public function testRateLimiting(): void
    {
        $authHeaders = $this->getAuthHeaders('admin');
        $endpoint = '/api/pipeline/stages';
        
        $requestCount = 0;
        $rateLimitHit = false;
        
        // Make rapid requests until rate limit is hit
        for ($i = 0; $i < 200; $i++) {
            $response = $this->httpClient->get($endpoint, [
                'headers' => $authHeaders
            ]);
            
            $requestCount++;
            
            if ($response->getStatusCode() === 429) {
                $rateLimitHit = true;
                
                // Check rate limit headers
                $headers = $response->getHeaders();
                $this->assertArrayHasKey('X-RateLimit-Limit', $headers);
                $this->assertArrayHasKey('X-RateLimit-Remaining', $headers);
                $this->assertArrayHasKey('Retry-After', $headers);
                
                break;
            }
            
            // Small delay to avoid overwhelming the server
            usleep(10000); // 10ms
        }
        
        $this->assertTrue($rateLimitHit, 
            'Rate limiting should be triggered after many requests');
        $this->assertLessThan(200, $requestCount,
            'Rate limit should be hit before 200 requests');
    }

    /**
     * @test
     * @group integration
     * @group security
     * @group session-management
     */
    public function testSessionSecurity(): void
    {
        // Test session timeout
        $authHeaders = $this->getAuthHeaders('admin');
        
        // Make a valid request
        $response = $this->httpClient->get('/api/pipeline/stages', [
            'headers' => $authHeaders
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        
        // Simulate expired session by modifying token
        $expiredHeaders = $authHeaders;
        $expiredHeaders['Authorization'] = 'Bearer ' . $this->generateExpiredToken();
        
        $response = $this->httpClient->get('/api/pipeline/stages', [
            'headers' => $expiredHeaders
        ]);
        $this->assertEquals(401, $response->getStatusCode());
        
        // Test session fixation prevention
        $token1 = $this->generateValidToken('admin');
        $token2 = $this->generateValidToken('admin');
        
        $this->assertNotEquals($token1, $token2,
            'Each login should generate a new session token');
    }

    /**
     * @test
     * @group integration
     * @group security
     * @group data-leakage
     */
    public function testDataLeakagePrevention(): void
    {
        // Create sensitive deal data
        $sensitiveDeal = $this->createTestDeal([
            'name' => 'Confidential Acquisition Deal',
            'amount' => 10000000,
            'notes' => 'Internal use only - contains sensitive financial data'
        ]);
        
        // Test with unauthorized user
        $guestHeaders = $this->getAuthHeaders('guest');
        
        $response = $this->httpClient->get("/api/pipeline/deals/{$sensitiveDeal}", [
            'headers' => $guestHeaders
        ]);
        
        if ($response->getStatusCode() === 403) {
            // Expected - no access
            $this->assertTrue(true);
        } else if ($response->getStatusCode() === 200) {
            // If access is granted, ensure sensitive data is filtered
            $data = json_decode($response->getBody()->getContents(), true);
            
            $this->assertArrayNotHasKey('notes', $data['deal'],
                'Sensitive notes should not be exposed to unauthorized users');
            
            // Check for other potentially sensitive fields
            $sensitiveFields = ['assigned_user_id', 'created_by', 'internal_notes'];
            foreach ($sensitiveFields as $field) {
                $this->assertArrayNotHasKey($field, $data['deal'],
                    "Sensitive field '$field' should not be exposed");
            }
        }
    }

    /**
     * @test
     * @group integration
     * @group security
     * @group file-upload
     */
    public function testFileUploadSecurity(): void
    {
        $authHeaders = $this->getAuthHeaders('admin');
        
        // Test malicious file types
        $maliciousFiles = [
            ['filename' => 'malware.exe', 'content' => 'MZ executable content', 'type' => 'application/x-msdownload'],
            ['filename' => 'script.php', 'content' => '<?php system($_GET["cmd"]); ?>', 'type' => 'application/x-php'],
            ['filename' => 'shell.jsp', 'content' => '<% Runtime.getRuntime().exec(request.getParameter("cmd")); %>', 'type' => 'application/x-jsp'],
            ['filename' => 'backdoor.asp', 'content' => '<% eval request("cmd") %>', 'type' => 'application/x-asp'],
        ];
        
        foreach ($maliciousFiles as $file) {
            $response = $this->httpClient->post('/api/pipeline/upload-attachment', [
                'headers' => $authHeaders,
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => $file['content'],
                        'filename' => $file['filename'],
                        'headers' => ['Content-Type' => $file['type']]
                    ],
                    [
                        'name' => 'deal_id',
                        'contents' => $this->createTestDeal()
                    ]
                ]
            ]);
            
            $this->assertNotEquals(200, $response->getStatusCode(),
                "Malicious file should be rejected: {$file['filename']}");
            
            if ($response->getStatusCode() === 400) {
                $data = json_decode($response->getBody()->getContents(), true);
                $this->assertArrayHasKey('error', $data);
                $this->assertStringContainsString('file type', strtolower($data['error']));
            }
        }
    }

    /**
     * Helper Methods
     */
    
    protected function createTestUsers(): void
    {
        foreach ($this->testUsers as $username => $userData) {
            $this->insertTestRecords('users', [[
                'id' => $this->generateUuid(),
                'user_name' => $username,
                'user_hash' => password_hash('password123', PASSWORD_DEFAULT),
                'status' => 'Active',
                'role_id' => $userData['role'],
                'date_entered' => date('Y-m-d H:i:s')
            ]]);
        }
    }

    protected function createTestDeals(): void
    {
        $stages = ['sourcing', 'screening', 'analysis_outreach'];
        
        for ($i = 0; $i < 15; $i++) {
            $this->createTestDeal([
                'name' => "Security Test Deal $i",
                'pipeline_stage_c' => $stages[$i % count($stages)],
                'amount' => rand(50000, 500000),
                'assigned_user_id' => 'test-user-' . ($i % 3 + 1)
            ]);
        }
    }

    protected function createTestDeal(array $data = []): string
    {
        $defaults = [
            'id' => $this->generateUuid(),
            'name' => 'Security Test Deal',
            'pipeline_stage_c' => 'sourcing',
            'amount' => 100000,
            'assigned_user_id' => 'test-user-1',
            'date_entered' => date('Y-m-d H:i:s'),
            'stage_entry_time' => date('Y-m-d H:i:s')
        ];
        
        $dealData = array_merge($defaults, $data);
        $this->insertTestRecords('deals', [$dealData]);
        
        return $dealData['id'];
    }

    protected function getAuthHeaders(string $username): array
    {
        $token = $this->generateValidToken($username);
        return [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];
    }

    protected function generateValidToken(string $username): string
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_name' => $username,
            'user_id' => 'test-user-1',
            'role' => $this->testUsers[$username]['role'] ?? 'guest',
            'exp' => time() + 3600,
            'iat' => time()
        ]);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, 'test-secret', true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }

    protected function generateExpiredToken(): string
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_name' => 'admin',
            'user_id' => 'test-user-1',
            'exp' => time() - 3600, // Expired 1 hour ago
            'iat' => time() - 7200
        ]);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, 'test-secret', true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }

    protected function getValidCSRFToken(): string
    {
        // In a real implementation, this would get a valid CSRF token from the session
        return hash('sha256', 'test-csrf-token-' . time());
    }

    protected function getDatabaseRecordCount(string $table): int
    {
        $query = "SELECT COUNT(*) as count FROM $table WHERE deleted = 0";
        $result = $this->database->query($query);
        $row = $result->fetch_assoc();
        return intval($row['count']);
    }

    protected function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}