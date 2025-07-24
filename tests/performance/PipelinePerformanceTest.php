<?php

namespace Tests\Performance;

use Tests\DatabaseTestCase;
use Tests\Fixtures\Pipeline\PipelineTestDataGenerator;

/**
 * Performance benchmark tests for Pipeline feature
 */
class PipelinePerformanceTest extends DatabaseTestCase
{
    private PipelineTestDataGenerator $generator;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new PipelineTestDataGenerator();
    }

    /**
     * Test loading pipeline with increasing number of deals
     */
    public function testPipelineLoadingPerformance(): void
    {
        $benchmarks = [
            ['deals' => 100, 'max_time' => 0.5],
            ['deals' => 500, 'max_time' => 1.0],
            ['deals' => 1000, 'max_time' => 2.0],
            ['deals' => 5000, 'max_time' => 5.0],
        ];
        
        foreach ($benchmarks as $benchmark) {
            // Reset database
            $this->resetDatabase();
            
            // Generate deals
            $deals = $this->generator->generateStressTestData($benchmark['deals']);
            
            // Insert deals
            foreach ($deals as $deal) {
                $this->insertDeal($deal);
            }
            
            // Benchmark pipeline loading
            $start = microtime(true);
            
            // Simulate loading pipeline data
            $this->loadPipelineData();
            
            $duration = microtime(true) - $start;
            
            $this->assertLessThan(
                $benchmark['max_time'],
                $duration,
                "Loading {$benchmark['deals']} deals took {$duration}s, expected < {$benchmark['max_time']}s"
            );
        }
    }

    /**
     * Test drag and drop operation performance
     */
    public function testDragDropPerformance(): void
    {
        // Setup: Create 1000 deals
        $deals = $this->generator->generateStressTestData(1000);
        foreach ($deals as $deal) {
            $this->insertDeal($deal);
        }
        
        // Benchmark single deal move
        $dealId = 500; // Middle deal
        
        $start = microtime(true);
        
        // Simulate drag-drop operation
        $this->performDragDrop($dealId, 'lead', 'contacted');
        
        $duration = microtime(true) - $start;
        
        // Single deal move should be < 100ms
        $this->assertLessThan(0.1, $duration, "Single deal move took {$duration}s, expected < 0.1s");
    }

    /**
     * Test bulk operations performance
     */
    public function testBulkOperationsPerformance(): void
    {
        // Create test data
        $deals = $this->generator->generateStressTestData(1000);
        foreach ($deals as $deal) {
            $this->insertDeal($deal);
        }
        
        $bulkSizes = [10, 50, 100, 200];
        
        foreach ($bulkSizes as $size) {
            $dealIds = range(1, $size);
            
            $start = microtime(true);
            
            // Simulate bulk move
            $this->performBulkMove($dealIds, 'lead', 'contacted');
            
            $duration = microtime(true) - $start;
            
            // Should scale linearly: ~10ms per deal
            $expectedMax = $size * 0.01;
            
            $this->assertLessThan(
                $expectedMax,
                $duration,
                "Bulk move of {$size} deals took {$duration}s, expected < {$expectedMax}s"
            );
        }
    }

    /**
     * Test stage metrics calculation performance
     */
    public function testMetricsCalculationPerformance(): void
    {
        // Generate realistic pipeline
        $deals = $this->generator->generateRealisticPipeline();
        foreach ($deals as $deal) {
            $this->insertDeal($deal);
        }
        
        $start = microtime(true);
        
        // Calculate all metrics
        $metrics = $this->calculatePipelineMetrics();
        
        $duration = microtime(true) - $start;
        
        // Metrics calculation should be < 500ms
        $this->assertLessThan(0.5, $duration, "Metrics calculation took {$duration}s, expected < 0.5s");
        
        // Verify metrics were calculated
        $this->assertNotEmpty($metrics);
        $this->assertArrayHasKey('stage_metrics', $metrics);
        $this->assertArrayHasKey('conversion_rates', $metrics);
    }

    /**
     * Test memory usage with large datasets
     */
    public function testMemoryUsage(): void
    {
        $memoryBenchmarks = [
            ['deals' => 100, 'max_memory_mb' => 10],
            ['deals' => 1000, 'max_memory_mb' => 50],
            ['deals' => 5000, 'max_memory_mb' => 100],
        ];
        
        foreach ($memoryBenchmarks as $benchmark) {
            // Reset
            $this->resetDatabase();
            gc_collect_cycles();
            
            $startMemory = memory_get_usage(true);
            
            // Generate and process deals
            $deals = $this->generator->generateStressTestData($benchmark['deals']);
            
            // Insert and load
            foreach ($deals as $deal) {
                $this->insertDeal($deal);
            }
            
            $this->loadPipelineData();
            
            $memoryUsed = (memory_get_usage(true) - $startMemory) / 1024 / 1024; // Convert to MB
            
            $this->assertLessThan(
                $benchmark['max_memory_mb'],
                $memoryUsed,
                "Memory usage for {$benchmark['deals']} deals was {$memoryUsed}MB, expected < {$benchmark['max_memory_mb']}MB"
            );
            
            // Clean up
            unset($deals);
            gc_collect_cycles();
        }
    }

    /**
     * Test query performance
     */
    public function testQueryPerformance(): void
    {
        // Create test data with history
        $deals = $this->generator->generateStressTestData(1000);
        foreach ($deals as $deal) {
            $dealId = $this->insertDeal($deal);
            
            // Add stage history (3 transitions per deal)
            for ($i = 0; $i < 3; $i++) {
                $this->insertStageHistory($dealId, 'lead', 'contacted', 1);
            }
        }
        
        // Test critical queries
        $queries = [
            [
                'name' => 'Load pipeline stages',
                'sql' => 'SELECT stage, COUNT(*) as count, SUM(amount) as total FROM deals GROUP BY stage',
                'max_time' => 0.05
            ],
            [
                'name' => 'Get stale deals',
                'sql' => 'SELECT * FROM deals WHERE time_in_stage > 7 AND stage NOT IN ("won", "lost")',
                'max_time' => 0.02
            ],
            [
                'name' => 'Stage history lookup',
                'sql' => 'SELECT * FROM deal_stage_history WHERE deal_id = ? ORDER BY changed_at DESC LIMIT 10',
                'params' => [500],
                'max_time' => 0.01
            ],
            [
                'name' => 'WIP limit check',
                'sql' => 'SELECT COUNT(*) FROM deals WHERE stage = ?',
                'params' => ['qualified'],
                'max_time' => 0.01
            ]
        ];
        
        foreach ($queries as $query) {
            $start = microtime(true);
            
            // Execute query
            $stmt = self::$pdo->prepare($query['sql']);
            $stmt->execute($query['params'] ?? []);
            $stmt->fetchAll();
            
            $duration = microtime(true) - $start;
            
            $this->assertLessThan(
                $query['max_time'],
                $duration,
                "Query '{$query['name']}' took {$duration}s, expected < {$query['max_time']}s"
            );
        }
    }

    /**
     * Test concurrent access performance
     */
    public function testConcurrentAccessPerformance(): void
    {
        // Create shared dataset
        $deals = $this->generator->generateStressTestData(500);
        foreach ($deals as $deal) {
            $this->insertDeal($deal);
        }
        
        // Simulate concurrent operations
        $operations = 50;
        $totalDuration = 0;
        
        for ($i = 0; $i < $operations; $i++) {
            $start = microtime(true);
            
            // Mix of read and write operations
            if ($i % 3 === 0) {
                // Write operation
                $this->performDragDrop($i + 1, 'lead', 'contacted');
            } else {
                // Read operation
                $this->loadPipelineData();
            }
            
            $totalDuration += microtime(true) - $start;
        }
        
        $avgDuration = $totalDuration / $operations;
        
        // Average operation should remain fast even under load
        $this->assertLessThan(0.05, $avgDuration, "Average operation took {$avgDuration}s, expected < 0.05s");
    }

    /**
     * Test rendering performance with complex pipeline
     */
    public function testRenderingPerformance(): void
    {
        // Create complex scenario
        $distribution = [
            'lead' => 200,
            'contacted' => 150,
            'qualified' => 100,
            'proposal' => 75,
            'negotiation' => 50,
            'won' => 100,
            'lost' => 75
        ];
        
        $deals = $this->generator->generatePipelineDistribution($distribution);
        
        // Add variety to deal data
        foreach ($deals as &$deal) {
            $deal['tags'] = $this->generator->generateTags();
            $deal['custom_fields'] = [
                'industry' => ['Technology', 'Healthcare', 'Finance'][rand(0, 2)],
                'size' => ['SMB', 'Mid-Market', 'Enterprise'][rand(0, 2)],
                'source' => ['Inbound', 'Outbound', 'Partner'][rand(0, 2)]
            ];
        }
        
        foreach ($deals as $deal) {
            $this->insertDeal($deal);
        }
        
        $start = microtime(true);
        
        // Simulate rendering pipeline view
        $renderData = $this->preparePipelineRenderData();
        
        $duration = microtime(true) - $start;
        
        // Should prepare render data quickly
        $this->assertLessThan(0.2, $duration, "Render preparation took {$duration}s, expected < 0.2s");
        
        // Verify render data
        $this->assertArrayHasKey('stages', $renderData);
        $this->assertArrayHasKey('deals', $renderData);
        $this->assertArrayHasKey('metrics', $renderData);
        $this->assertEquals(750, count($renderData['deals']));
    }

    /**
     * Test search and filter performance
     */
    public function testSearchFilterPerformance(): void
    {
        // Create diverse dataset
        $deals = $this->generator->generateStressTestData(2000);
        foreach ($deals as $deal) {
            $this->insertDeal($deal);
        }
        
        $searchScenarios = [
            ['type' => 'text_search', 'query' => 'Test Deal #5', 'max_time' => 0.05],
            ['type' => 'stage_filter', 'stage' => 'qualified', 'max_time' => 0.02],
            ['type' => 'owner_filter', 'owner_id' => 5, 'max_time' => 0.03],
            ['type' => 'amount_range', 'min' => 50000, 'max' => 100000, 'max_time' => 0.04],
            ['type' => 'combined', 'filters' => ['stage' => 'proposal', 'owner_id' => 3], 'max_time' => 0.05]
        ];
        
        foreach ($searchScenarios as $scenario) {
            $start = microtime(true);
            
            $results = $this->performSearch($scenario);
            
            $duration = microtime(true) - $start;
            
            $this->assertLessThan(
                $scenario['max_time'],
                $duration,
                "Search type '{$scenario['type']}' took {$duration}s, expected < {$scenario['max_time']}s"
            );
            
            $this->assertIsArray($results);
        }
    }

    // Helper methods
    
    private function resetDatabase(): void
    {
        self::$pdo->exec('DELETE FROM deal_stage_history');
        self::$pdo->exec('DELETE FROM deals');
    }
    
    private function loadPipelineData(): array
    {
        $stmt = self::$pdo->query('
            SELECT d.*, ps.display_name, ps.color, ps.wip_limit
            FROM deals d
            JOIN pipeline_stages ps ON d.stage = ps.name
            ORDER BY ps.order_index, d.created_at DESC
        ');
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    private function performDragDrop(int $dealId, string $fromStage, string $toStage): void
    {
        // Update deal stage
        $stmt = self::$pdo->prepare('
            UPDATE deals 
            SET stage = :to_stage, 
                stage_updated_at = CURRENT_TIMESTAMP,
                time_in_stage = 0
            WHERE id = :id
        ');
        
        $stmt->execute([
            'id' => $dealId,
            'to_stage' => $toStage
        ]);
        
        // Insert history
        $this->insertStageHistory($dealId, $fromStage, $toStage, 1);
    }
    
    private function performBulkMove(array $dealIds, string $fromStage, string $toStage): void
    {
        $placeholders = implode(',', array_fill(0, count($dealIds), '?'));
        
        $stmt = self::$pdo->prepare("
            UPDATE deals 
            SET stage = ?, 
                stage_updated_at = CURRENT_TIMESTAMP,
                time_in_stage = 0
            WHERE id IN ({$placeholders})
        ");
        
        $params = array_merge([$toStage], $dealIds);
        $stmt->execute($params);
        
        // Insert history for each
        foreach ($dealIds as $dealId) {
            $this->insertStageHistory($dealId, $fromStage, $toStage, 1);
        }
    }
    
    private function insertStageHistory(int $dealId, string $fromStage, string $toStage, int $userId): void
    {
        $stmt = self::$pdo->prepare('
            INSERT INTO deal_stage_history (deal_id, from_stage, to_stage, changed_by)
            VALUES (:deal_id, :from_stage, :to_stage, :changed_by)
        ');
        
        $stmt->execute([
            'deal_id' => $dealId,
            'from_stage' => $fromStage,
            'to_stage' => $toStage,
            'changed_by' => $userId
        ]);
    }
    
    private function calculatePipelineMetrics(): array
    {
        // Stage metrics
        $stageMetrics = self::$pdo->query('
            SELECT 
                stage,
                COUNT(*) as deal_count,
                SUM(amount) as total_value,
                AVG(amount) as avg_value,
                AVG(time_in_stage) as avg_time_in_stage
            FROM deals
            GROUP BY stage
        ')->fetchAll(\PDO::FETCH_ASSOC);
        
        // Conversion rates
        $conversionRates = [];
        $stages = ['lead', 'contacted', 'qualified', 'proposal', 'negotiation'];
        
        for ($i = 0; $i < count($stages) - 1; $i++) {
            $fromStage = $stages[$i];
            $toStage = $stages[$i + 1];
            
            $fromCount = self::$pdo->query("SELECT COUNT(*) FROM deals WHERE stage = '{$fromStage}'")->fetchColumn();
            $toCount = self::$pdo->query("SELECT COUNT(*) FROM deals WHERE stage IN ('" . implode("','", array_slice($stages, $i + 1)) . "', 'won')")->fetchColumn();
            
            $conversionRates["{$fromStage}_to_{$toStage}"] = $fromCount > 0 ? ($toCount / $fromCount) * 100 : 0;
        }
        
        return [
            'stage_metrics' => $stageMetrics,
            'conversion_rates' => $conversionRates
        ];
    }
    
    private function preparePipelineRenderData(): array
    {
        $stages = self::$pdo->query('SELECT * FROM pipeline_stages ORDER BY order_index')->fetchAll(\PDO::FETCH_ASSOC);
        $deals = $this->loadPipelineData();
        $metrics = $this->calculatePipelineMetrics();
        
        return [
            'stages' => $stages,
            'deals' => $deals,
            'metrics' => $metrics
        ];
    }
    
    private function performSearch(array $scenario): array
    {
        $sql = 'SELECT * FROM deals WHERE 1=1';
        $params = [];
        
        switch ($scenario['type']) {
            case 'text_search':
                $sql .= ' AND name LIKE :query';
                $params['query'] = '%' . $scenario['query'] . '%';
                break;
                
            case 'stage_filter':
                $sql .= ' AND stage = :stage';
                $params['stage'] = $scenario['stage'];
                break;
                
            case 'owner_filter':
                $sql .= ' AND owner_id = :owner_id';
                $params['owner_id'] = $scenario['owner_id'];
                break;
                
            case 'amount_range':
                $sql .= ' AND amount BETWEEN :min AND :max';
                $params['min'] = $scenario['min'];
                $params['max'] = $scenario['max'];
                break;
                
            case 'combined':
                foreach ($scenario['filters'] as $key => $value) {
                    $sql .= " AND {$key} = :{$key}";
                    $params[$key] = $value;
                }
                break;
        }
        
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}