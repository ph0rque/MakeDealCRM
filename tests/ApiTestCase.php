<?php

namespace Tests;

/**
 * Base class for API testing
 * 
 * Provides HTTP client simulation and API testing utilities
 */
abstract class ApiTestCase extends DatabaseTestCase
{
    /**
     * @var array Default headers for API requests
     */
    protected array $defaultHeaders = [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ];
    
    /**
     * @var array Authentication token
     */
    protected ?string $authToken = null;

    /**
     * Set up API test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up default authentication
        $this->authenticate();
    }

    /**
     * Authenticate and get token
     */
    protected function authenticate(string $username = 'testuser', string $password = 'testpass'): void
    {
        // In real implementation, this would make actual auth request
        $this->authToken = 'test-token-' . md5($username . $password);
    }

    /**
     * Make GET request
     */
    protected function get(string $uri, array $query = [], array $headers = []): array
    {
        return $this->request('GET', $uri, ['query' => $query], $headers);
    }

    /**
     * Make POST request
     */
    protected function post(string $uri, array $data = [], array $headers = []): array
    {
        return $this->request('POST', $uri, ['json' => $data], $headers);
    }

    /**
     * Make PUT request
     */
    protected function put(string $uri, array $data = [], array $headers = []): array
    {
        return $this->request('PUT', $uri, ['json' => $data], $headers);
    }

    /**
     * Make DELETE request
     */
    protected function delete(string $uri, array $headers = []): array
    {
        return $this->request('DELETE', $uri, [], $headers);
    }

    /**
     * Make PATCH request
     */
    protected function patch(string $uri, array $data = [], array $headers = []): array
    {
        return $this->request('PATCH', $uri, ['json' => $data], $headers);
    }

    /**
     * Make API request
     */
    protected function request(string $method, string $uri, array $options = [], array $headers = []): array
    {
        // Merge headers
        $headers = array_merge($this->defaultHeaders, $headers);
        
        // Add auth token if available
        if ($this->authToken) {
            $headers['Authorization'] = 'Bearer ' . $this->authToken;
        }
        
        // In real implementation, this would use Guzzle or similar HTTP client
        // For testing, we'll simulate the response
        return $this->simulateApiResponse($method, $uri, $options, $headers);
    }

    /**
     * Simulate API response for testing
     */
    protected function simulateApiResponse(string $method, string $uri, array $options, array $headers): array
    {
        // Extract data from options
        $data = $options['json'] ?? [];
        $query = $options['query'] ?? [];
        
        // Route to appropriate handler
        $response = match ($uri) {
            '/api/auth/login' => $this->handleLogin($data),
            '/api/pipeline/stages' => $this->handleGetStages($query),
            '/api/pipeline/move-deal' => $this->handleMoveDeal($data),
            '/api/pipeline/bulk-move' => $this->handleBulkMove($data),
            '/api/pipeline/metrics' => $this->handleGetMetrics($query),
            default => ['status' => 404, 'error' => 'Not Found']
        };
        
        return $response;
    }

    /**
     * Assert API response structure
     */
    protected function assertApiSuccess(array $response, string $message = ''): void
    {
        $this->assertArrayHasKey('status', $response, $message);
        $this->assertEquals(200, $response['status'], $message);
        $this->assertArrayNotHasKey('error', $response, $message);
    }

    /**
     * Assert API error response
     */
    protected function assertApiError(array $response, int $expectedStatus, string $message = ''): void
    {
        $this->assertArrayHasKey('status', $response, $message);
        $this->assertEquals($expectedStatus, $response['status'], $message);
        $this->assertArrayHasKey('error', $response, $message);
    }

    /**
     * Assert API response has data
     */
    protected function assertApiHasData(array $response, array $expectedKeys, string $message = ''): void
    {
        $this->assertArrayHasKey('data', $response, $message);
        
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $response['data'], $message);
        }
    }

    /**
     * Assert pagination structure
     */
    protected function assertHasPagination(array $response, string $message = ''): void
    {
        $this->assertArrayHasKey('data', $response, $message);
        $this->assertArrayHasKey('meta', $response, $message);
        
        $meta = $response['meta'];
        $this->assertArrayHasKey('current_page', $meta, $message);
        $this->assertArrayHasKey('total_pages', $meta, $message);
        $this->assertArrayHasKey('total_items', $meta, $message);
        $this->assertArrayHasKey('per_page', $meta, $message);
    }

    // Mock API handlers for testing

    private function handleLogin(array $data): array
    {
        if ($data['username'] === 'testuser' && $data['password'] === 'testpass') {
            return [
                'status' => 200,
                'data' => [
                    'token' => 'test-token-' . md5($data['username'] . $data['password']),
                    'user' => [
                        'id' => 1,
                        'username' => $data['username'],
                        'role' => 'admin'
                    ]
                ]
            ];
        }
        
        return [
            'status' => 401,
            'error' => 'Invalid credentials'
        ];
    }

    private function handleGetStages(array $query): array
    {
        // Get stages from database
        $stages = self::$pdo->query('
            SELECT ps.*, COUNT(d.id) as deal_count, COALESCE(SUM(d.amount), 0) as total_value
            FROM pipeline_stages ps
            LEFT JOIN deals d ON ps.name = d.stage
            GROUP BY ps.id
            ORDER BY ps.order_index
        ')->fetchAll(\PDO::FETCH_ASSOC);
        
        return [
            'status' => 200,
            'data' => [
                'stages' => $stages
            ]
        ];
    }

    private function handleMoveDeal(array $data): array
    {
        // Validate required fields
        if (!isset($data['deal_id']) || !isset($data['to_stage'])) {
            return [
                'status' => 400,
                'error' => 'Missing required fields'
            ];
        }
        
        // Check if deal exists
        $deal = $this->getDeal($data['deal_id']);
        if (!$deal) {
            return [
                'status' => 404,
                'error' => 'Deal not found'
            ];
        }
        
        // Validate stage transition
        $validTransitions = [
            'lead' => ['contacted', 'lost'],
            'contacted' => ['qualified', 'lost'],
            'qualified' => ['proposal', 'lost'],
            'proposal' => ['negotiation', 'lost'],
            'negotiation' => ['won', 'lost']
        ];
        
        if (!in_array($data['to_stage'], $validTransitions[$deal['stage']] ?? [])) {
            return [
                'status' => 400,
                'error' => 'Invalid stage transition'
            ];
        }
        
        // Check WIP limit
        if (!isset($data['override_wip']) || !$data['override_wip']) {
            $stmt = self::$pdo->prepare('
                SELECT COUNT(*) as count, ps.wip_limit
                FROM deals d
                JOIN pipeline_stages ps ON ps.name = :stage
                WHERE d.stage = :stage
                GROUP BY ps.wip_limit
            ');
            $stmt->execute(['stage' => $data['to_stage']]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($result && $result['wip_limit'] && $result['count'] >= $result['wip_limit']) {
                return [
                    'status' => 409,
                    'error' => 'WIP limit reached',
                    'data' => [
                        'current_count' => (int)$result['count'],
                        'wip_limit' => (int)$result['wip_limit']
                    ]
                ];
            }
        }
        
        // Update deal
        $stmt = self::$pdo->prepare('
            UPDATE deals 
            SET stage = :stage, 
                stage_updated_at = CURRENT_TIMESTAMP,
                time_in_stage = 0
            WHERE id = :id
        ');
        
        $stmt->execute([
            'id' => $data['deal_id'],
            'stage' => $data['to_stage']
        ]);
        
        // Insert history
        $stmt = self::$pdo->prepare('
            INSERT INTO deal_stage_history (deal_id, from_stage, to_stage, changed_by)
            VALUES (:deal_id, :from_stage, :to_stage, :changed_by)
        ');
        
        $stmt->execute([
            'deal_id' => $data['deal_id'],
            'from_stage' => $deal['stage'],
            'to_stage' => $data['to_stage'],
            'changed_by' => $data['user_id'] ?? 1
        ]);
        
        return [
            'status' => 200,
            'message' => 'Deal moved successfully',
            'data' => [
                'deal_id' => $data['deal_id'],
                'from_stage' => $deal['stage'],
                'to_stage' => $data['to_stage']
            ]
        ];
    }

    private function handleBulkMove(array $data): array
    {
        if (!isset($data['deal_ids']) || !is_array($data['deal_ids'])) {
            return [
                'status' => 400,
                'error' => 'Invalid deal_ids'
            ];
        }
        
        $moved = 0;
        $failed = 0;
        $errors = [];
        
        foreach ($data['deal_ids'] as $dealId) {
            $result = $this->handleMoveDeal([
                'deal_id' => $dealId,
                'to_stage' => $data['to_stage'],
                'user_id' => $data['user_id'] ?? 1,
                'override_wip' => $data['override_wip'] ?? false
            ]);
            
            if ($result['status'] === 200) {
                $moved++;
            } else {
                $failed++;
                $errors[$dealId] = $result['error'];
            }
        }
        
        $status = $failed === 0 ? 200 : ($moved === 0 ? 400 : 207);
        
        return [
            'status' => $status,
            'data' => [
                'moved_count' => $moved,
                'failed_count' => $failed,
                'errors' => $errors
            ]
        ];
    }

    private function handleGetMetrics(array $query): array
    {
        // Calculate basic metrics
        $stages = ['lead', 'contacted', 'qualified', 'proposal', 'negotiation', 'won', 'lost'];
        $stageMetrics = [];
        
        foreach ($stages as $stage) {
            $stmt = self::$pdo->prepare('
                SELECT 
                    COUNT(*) as count,
                    COALESCE(SUM(amount), 0) as total_value,
                    COALESCE(AVG(amount), 0) as avg_value,
                    COALESCE(AVG(time_in_stage), 0) as avg_time
                FROM deals
                WHERE stage = :stage
            ');
            $stmt->execute(['stage' => $stage]);
            $stageMetrics[$stage] = $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        
        // Calculate conversion rates
        $conversionRates = [
            'lead_to_contacted' => $this->calculateConversionRate('lead', 'contacted'),
            'contacted_to_qualified' => $this->calculateConversionRate('contacted', 'qualified'),
            'overall_win_rate' => $this->calculateWinRate()
        ];
        
        return [
            'status' => 200,
            'data' => [
                'stage_metrics' => $stageMetrics,
                'conversion_rates' => $conversionRates,
                'velocity_metrics' => [
                    'avg_sales_cycle' => 21.5,
                    'avg_time_per_stage' => array_map(fn($m) => $m['avg_time'], $stageMetrics),
                    'bottleneck_stages' => ['proposal', 'negotiation']
                ],
                'win_loss_analysis' => [
                    'win_count' => $stageMetrics['won']['count'],
                    'loss_count' => $stageMetrics['lost']['count'],
                    'win_value' => $stageMetrics['won']['total_value'],
                    'loss_value' => $stageMetrics['lost']['total_value']
                ]
            ]
        ];
    }

    private function calculateConversionRate(string $fromStage, string $toStage): float
    {
        $fromCount = self::$pdo->query("SELECT COUNT(*) FROM deals WHERE stage = '{$fromStage}'")->fetchColumn();
        $toCount = self::$pdo->query("SELECT COUNT(*) FROM deals WHERE stage = '{$toStage}'")->fetchColumn();
        
        return $fromCount > 0 ? round(($toCount / $fromCount) * 100, 2) : 0;
    }

    private function calculateWinRate(): float
    {
        $totalClosed = self::$pdo->query("SELECT COUNT(*) FROM deals WHERE stage IN ('won', 'lost')")->fetchColumn();
        $won = self::$pdo->query("SELECT COUNT(*) FROM deals WHERE stage = 'won'")->fetchColumn();
        
        return $totalClosed > 0 ? round(($won / $totalClosed) * 100, 2) : 0;
    }
}