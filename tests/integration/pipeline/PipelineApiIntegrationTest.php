<?php

namespace Tests\Integration\Pipeline;

use Tests\ApiTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

/**
 * Integration tests for Pipeline API endpoints
 * Tests all API functionality with real HTTP requests
 */
class PipelineApiIntegrationTest extends ApiTestCase
{
    protected Client $httpClient;
    protected string $baseUrl = 'http://localhost:8080';
    protected array $authHeaders;
    
    protected array $stages = [
        'sourcing', 'screening', 'analysis_outreach', 'due_diligence',
        'valuation_structuring', 'loi_negotiation', 'financing', 'closing',
        'closed_owned_90_day', 'closed_owned_stable', 'unavailable'
    ];

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->httpClient = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'http_errors' => false
        ]);
        
        $this->createTestDeals();
        $this->authHeaders = $this->getAuthHeaders();
    }

    /**
     * @test
     * @group integration
     * @group api
     * @group pipeline-stages
     */
    public function testGetPipelineStagesEndpoint(): void
    {
        $response = $this->httpClient->get('/api/pipeline/stages', [
            'headers' => $this->authHeaders
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getBody()->getContents(), true);
        
        // Verify response structure
        $this->assertArrayHasKey('stages', $data);
        $this->assertArrayHasKey('metadata', $data);
        
        // Verify all stages are present
        $stageNames = array_column($data['stages'], 'name');
        foreach ($this->stages as $expectedStage) {
            $this->assertContains($expectedStage, $stageNames);
        }
        
        // Verify stage metrics are included
        foreach ($data['stages'] as $stage) {
            $this->assertArrayHasKey('name', $stage);
            $this->assertArrayHasKey('label', $stage);
            $this->assertArrayHasKey('deal_count', $stage);
            $this->assertArrayHasKey('total_value', $stage);
            $this->assertArrayHasKey('wip_limit', $stage);
            $this->assertArrayHasKey('avg_time_in_stage', $stage);
        }
        
        // Test performance - should respond within 200ms
        $startTime = microtime(true);
        $this->httpClient->get('/api/pipeline/stages', [
            'headers' => $this->authHeaders
        ]);
        $responseTime = (microtime(true) - $startTime) * 1000;
        
        $this->assertLessThan(200, $responseTime, 'API response time should be under 200ms');
    }

    /**
     * @test
     * @group integration
     * @group api
     * @group deal-movement
     */
    public function testMoveDealEndpoint(): void
    {
        $dealId = $this->createTestDeal(['pipeline_stage_c' => 'sourcing']);
        
        $payload = [
            'deal_id' => $dealId,
            'from_stage' => 'sourcing',
            'to_stage' => 'screening'
        ];
        
        $response = $this->httpClient->post('/api/pipeline/move-deal', [
            'headers' => $this->authHeaders,
            'json' => $payload
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getBody()->getContents(), true);
        
        // Verify response structure
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('deal', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertTrue($data['success']);
        
        // Verify deal data in response
        $this->assertEquals($dealId, $data['deal']['id']);
        $this->assertEquals('screening', $data['deal']['pipeline_stage_c']);
        
        // Verify database was updated
        $this->assertDatabaseHas('deals', [
            'id' => $dealId,
            'pipeline_stage_c' => 'screening'
        ]);
        
        // Verify audit trail was created
        $this->assertDatabaseHas('deals_audit', [
            'parent_id' => $dealId,
            'field_name' => 'pipeline_stage_c',
            'data_value' => 'screening'
        ]);
    }

    /**
     * @test
     * @group integration
     * @group api
     * @group validation
     */
    public function testMoveDealValidation(): void
    {
        $dealId = $this->createTestDeal(['pipeline_stage_c' => 'sourcing']);
        
        // Test invalid stage transition
        $payload = [
            'deal_id' => $dealId,
            'from_stage' => 'sourcing',
            'to_stage' => 'due_diligence' // Skip stages
        ];
        
        $response = $this->httpClient->post('/api/pipeline/move-deal', [
            'headers' => $this->authHeaders,
            'json' => $payload
        ]);
        
        $this->assertEquals(400, $response->getStatusCode());
        
        $data = json_decode($response->getBody()->getContents(), true);
        
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('code', $data);
        $this->assertEquals('INVALID_TRANSITION', $data['code']);
        $this->assertStringContains('Invalid stage transition', $data['error']);
        
        // Test nonexistent deal
        $payload = [
            'deal_id' => 'nonexistent-id',
            'from_stage' => 'sourcing',
            'to_stage' => 'screening'
        ];
        
        $response = $this->httpClient->post('/api/pipeline/move-deal', [
            'headers' => $this->authHeaders,
            'json' => $payload
        ]);
        
        $this->assertEquals(404, $response->getStatusCode());
        
        $data = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals('DEAL_NOT_FOUND', $data['code']);
        
        // Test WIP limit enforcement
        $this->fillStageToCapacity('screening', 15);
        
        $payload = [
            'deal_id' => $dealId,
            'from_stage' => 'sourcing',
            'to_stage' => 'screening'
        ];
        
        $response = $this->httpClient->post('/api/pipeline/move-deal', [
            'headers' => $this->authHeaders,
            'json' => $payload
        ]);
        
        $this->assertEquals(409, $response->getStatusCode());
        
        $data = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals('WIP_LIMIT_EXCEEDED', $data['code']);
    }

    /**
     * @test
     * @group integration
     * @group api
     * @group bulk-operations
     */
    public function testBulkMoveDealEndpoint(): void
    {
        $dealIds = [
            $this->createTestDeal(['pipeline_stage_c' => 'sourcing']),
            $this->createTestDeal(['pipeline_stage_c' => 'sourcing']),
            $this->createTestDeal(['pipeline_stage_c' => 'sourcing'])
        ];
        
        $payload = [
            'deal_ids' => $dealIds,
            'from_stage' => 'sourcing',
            'to_stage' => 'screening'
        ];
        
        $response = $this->httpClient->post('/api/pipeline/bulk-move', [
            'headers' => $this->authHeaders,
            'json' => $payload
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getBody()->getContents(), true);
        
        // Verify response structure
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('moved_deals', $data);
        $this->assertArrayHasKey('failed_deals', $data);
        $this->assertArrayHasKey('summary', $data);
        
        // Verify all deals were moved successfully
        $this->assertCount(3, $data['moved_deals']);
        $this->assertCount(0, $data['failed_deals']);
        $this->assertEquals(3, $data['summary']['total']);
        $this->assertEquals(3, $data['summary']['successful']);
        $this->assertEquals(0, $data['summary']['failed']);
        
        // Verify database updates
        foreach ($dealIds as $dealId) {
            $this->assertDatabaseHas('deals', [
                'id' => $dealId,
                'pipeline_stage_c' => 'screening'
            ]);
        }
    }

    /**
     * @test
     * @group integration
     * @group api
     * @group bulk-operations
     * @group partial-failure
     */
    public function testBulkMovePartialFailure(): void
    {
        // Fill screening stage to near capacity
        $this->fillStageToCapacity('screening', 13);
        
        $dealIds = [
            $this->createTestDeal(['pipeline_stage_c' => 'sourcing']),
            $this->createTestDeal(['pipeline_stage_c' => 'sourcing']),
            $this->createTestDeal(['pipeline_stage_c' => 'sourcing']),
            $this->createTestDeal(['pipeline_stage_c' => 'sourcing'])
        ];
        
        $payload = [
            'deal_ids' => $dealIds,
            'from_stage' => 'sourcing',
            'to_stage' => 'screening'
        ];
        
        $response = $this->httpClient->post('/api/pipeline/bulk-move', [
            'headers' => $this->authHeaders,
            'json' => $payload
        ]);
        
        $this->assertEquals(207, $response->getStatusCode()); // Multi-status
        
        $data = json_decode($response->getBody()->getContents(), true);
        
        // Should have some successful and some failed moves
        $this->assertCount(2, $data['moved_deals']); // Only 2 fit within WIP limit
        $this->assertCount(2, $data['failed_deals']); // 2 failed due to WIP limit
        
        // Verify failure reasons
        foreach ($data['failed_deals'] as $failedDeal) {
            $this->assertEquals('WIP_LIMIT_EXCEEDED', $failedDeal['reason']);
        }
        
        // Verify database consistency
        $movedCount = 0;
        foreach ($dealIds as $dealId) {
            $deal = $this->getDatabaseRecord('deals', ['id' => $dealId]);
            if ($deal['pipeline_stage_c'] === 'screening') {
                $movedCount++;
            }
        }
        $this->assertEquals(2, $movedCount);
    }

    /**
     * @test
     * @group integration
     * @group api
     * @group metrics
     */
    public function testPipelineMetricsEndpoint(): void
    {
        $response = $this->httpClient->get('/api/pipeline/metrics', [
            'headers' => $this->authHeaders,
            'query' => [
                'period' => '30days',
                'include_trends' => 'true'
            ]
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getBody()->getContents(), true);
        
        // Verify response structure
        $this->assertArrayHasKey('conversion_rates', $data);
        $this->assertArrayHasKey('avg_time_per_stage', $data);
        $this->assertArrayHasKey('deal_velocity', $data);
        $this->assertArrayHasKey('trends', $data);
        $this->assertArrayHasKey('period', $data);
        
        // Verify conversion rates structure
        foreach ($this->stages as $stage) {
            if ($stage !== 'unavailable') {
                $this->assertArrayHasKey($stage, $data['conversion_rates']);
                $this->assertIsFloat($data['conversion_rates'][$stage]);
                $this->assertGreaterThanOrEqual(0, $data['conversion_rates'][$stage]);
                $this->assertLessThanOrEqual(100, $data['conversion_rates'][$stage]);
            }
        }
        
        // Verify time metrics
        foreach ($data['avg_time_per_stage'] as $stage => $avgTime) {
            $this->assertIsFloat($avgTime);
            $this->assertGreaterThanOrEqual(0, $avgTime);
        }
        
        // Verify trends data
        $this->assertArrayHasKey('weekly', $data['trends']);
        $this->assertArrayHasKey('monthly', $data['trends']);
    }

    /**
     * @test
     * @group integration
     * @group api
     * @group filters
     */
    public function testPipelineFiltering(): void
    {
        // Create deals with specific attributes
        $highValueDealId = $this->createTestDeal([
            'pipeline_stage_c' => 'sourcing',
            'amount' => 1000000,
            'assigned_user_id' => 'user-1'
        ]);
        
        $lowValueDealId = $this->createTestDeal([
            'pipeline_stage_c' => 'sourcing',
            'amount' => 50000,
            'assigned_user_id' => 'user-2'
        ]);
        
        // Test amount filter
        $response = $this->httpClient->get('/api/pipeline/deals', [
            'headers' => $this->authHeaders,
            'query' => [
                'min_amount' => 500000
            ]
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getBody()->getContents(), true);
        
        // Should only return high-value deal
        $returnedIds = array_column($data['deals'], 'id');
        $this->assertContains($highValueDealId, $returnedIds);
        $this->assertNotContains($lowValueDealId, $returnedIds);
        
        // Test user filter
        $response = $this->httpClient->get('/api/pipeline/deals', [
            'headers' => $this->authHeaders,
            'query' => [
                'assigned_user' => 'user-2'
            ]
        ]);
        
        $data = json_decode($response->getBody()->getContents(), true);
        
        $returnedIds = array_column($data['deals'], 'id');
        $this->assertContains($lowValueDealId, $returnedIds);
        $this->assertNotContains($highValueDealId, $returnedIds);
    }

    /**
     * @test
     * @group integration
     * @group api
     * @group performance
     */
    public function testApiPerformanceWithLargeDataset(): void
    {
        // Create large dataset
        $this->createLargeTestDataset(500);
        
        // Test stages endpoint performance
        $startTime = microtime(true);
        $response = $this->httpClient->get('/api/pipeline/stages', [
            'headers' => $this->authHeaders
        ]);
        $stagesTime = (microtime(true) - $startTime) * 1000;
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertLessThan(300, $stagesTime, 'Stages endpoint should respond within 300ms with 500 deals');
        
        // Test deals endpoint performance
        $startTime = microtime(true);
        $response = $this->httpClient->get('/api/pipeline/deals', [
            'headers' => $this->authHeaders,
            'query' => ['limit' => 100]
        ]);
        $dealsTime = (microtime(true) - $startTime) * 1000;
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertLessThan(500, $dealsTime, 'Deals endpoint should respond within 500ms with pagination');
        
        // Test metrics endpoint performance
        $startTime = microtime(true);
        $response = $this->httpClient->get('/api/pipeline/metrics', [
            'headers' => $this->authHeaders
        ]);
        $metricsTime = (microtime(true) - $startTime) * 1000;
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertLessThan(1000, $metricsTime, 'Metrics endpoint should respond within 1 second with 500 deals');
    }

    /**
     * @test
     * @group integration
     * @group api
     * @group authentication
     */
    public function testApiAuthentication(): void
    {
        // Test without authentication
        $response = $this->httpClient->get('/api/pipeline/stages');
        $this->assertEquals(401, $response->getStatusCode());
        
        // Test with invalid token
        $response = $this->httpClient->get('/api/pipeline/stages', [
            'headers' => [
                'Authorization' => 'Bearer invalid-token'
            ]
        ]);
        $this->assertEquals(401, $response->getStatusCode());
        
        // Test with expired token
        $expiredToken = $this->generateExpiredToken();
        $response = $this->httpClient->get('/api/pipeline/stages', [
            'headers' => [
                'Authorization' => 'Bearer ' . $expiredToken
            ]
        ]);
        $this->assertEquals(401, $response->getStatusCode());
        
        // Test with valid token
        $response = $this->httpClient->get('/api/pipeline/stages', [
            'headers' => $this->authHeaders
        ]);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @test
     * @group integration
     * @group api
     * @group rate-limiting
     */
    public function testAPIRateLimiting(): void
    {
        $requests = [];
        
        // Make 100 rapid requests
        for ($i = 0; $i < 100; $i++) {
            $startTime = microtime(true);
            $response = $this->httpClient->get('/api/pipeline/stages', [
                'headers' => $this->authHeaders
            ]);
            $endTime = microtime(true);
            
            $requests[] = [
                'status' => $response->getStatusCode(),
                'time' => $endTime - $startTime,
                'headers' => $response->getHeaders()
            ];
            
            // Stop if we hit rate limit
            if ($response->getStatusCode() === 429) {
                break;
            }
        }
        
        // Verify rate limiting is applied
        $rateLimited = array_filter($requests, fn($req) => $req['status'] === 429);
        $this->assertGreaterThan(0, count($rateLimited), 'Rate limiting should be applied');
        
        // Verify rate limit headers are present
        foreach ($rateLimited as $request) {
            $this->assertArrayHasKey('X-RateLimit-Limit', $request['headers']);
            $this->assertArrayHasKey('X-RateLimit-Remaining', $request['headers']);
            $this->assertArrayHasKey('Retry-After', $request['headers']);
        }
    }

    /**
     * Helper Methods
     */
    
    protected function getAuthHeaders(): array
    {
        $token = $this->generateValidToken();
        return [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];
    }

    protected function generateValidToken(): string
    {
        // Generate a valid JWT token for testing
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_id' => 'test-user-1',
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

    protected function createTestDeals(): void
    {
        $dealData = [
            ['name' => 'API Test Deal 1', 'pipeline_stage_c' => 'sourcing', 'amount' => 100000],
            ['name' => 'API Test Deal 2', 'pipeline_stage_c' => 'screening', 'amount' => 250000],
            ['name' => 'API Test Deal 3', 'pipeline_stage_c' => 'analysis_outreach', 'amount' => 150000],
        ];
        
        foreach ($dealData as $data) {
            $this->createTestDeal($data);
        }
    }

    protected function createTestDeal(array $data): string
    {
        $defaults = [
            'id' => $this->generateUuid(),
            'name' => 'API Test Deal',
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

    protected function fillStageToCapacity(string $stage, int $capacity): void
    {
        for ($i = 0; $i < $capacity; $i++) {
            $this->createTestDeal([
                'name' => "Capacity Test Deal $i",
                'pipeline_stage_c' => $stage
            ]);
        }
    }

    protected function createLargeTestDataset(int $count): void
    {
        $dealsPerStage = intval($count / count($this->stages));
        
        foreach ($this->stages as $stage) {
            for ($i = 0; $i < $dealsPerStage; $i++) {
                $this->createTestDeal([
                    'name' => "Load Test Deal {$stage}_{$i}",
                    'pipeline_stage_c' => $stage,
                    'amount' => rand(50000, 1000000)
                ]);
            }
        }
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