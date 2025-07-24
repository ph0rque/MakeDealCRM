<?php
/**
 * Integration Test Template for MakeDeal CRM Modules
 * 
 * This template provides the standard structure for integration tests
 * with database transactions and external service mocking
 */

namespace Tests\Integration\Modules\{ModuleName};

use Tests\DatabaseTestCase;
use Modules\{ModuleName}\{ServiceName}Service;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Integration tests for {ServiceName} functionality
 * 
 * @group integration
 * @covers \Modules\{ModuleName}\{ServiceName}Service
 */
class {ServiceName}IntegrationTest extends DatabaseTestCase
{
    /**
     * @var {ServiceName}Service
     */
    private $service;
    
    /**
     * @var MockObject
     */
    private $mockExternalService;
    
    /**
     * Set up before each test
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Start database transaction
        $this->beginTransaction();
        
        // Mock external services
        $this->mockExternalService = $this->createMock(ExternalService::class);
        
        // Create service instance with mocked dependencies
        $this->service = new {ServiceName}Service(
            $this->getDatabase(),
            $this->mockExternalService
        );
        
        // Load test fixtures
        $this->loadFixtures();
    }
    
    /**
     * Tear down after each test
     */
    protected function tearDown(): void
    {
        // Rollback transaction
        $this->rollbackTransaction();
        
        parent::tearDown();
    }
    
    /**
     * Load test fixtures
     */
    private function loadFixtures(): void
    {
        // Insert test data
        $this->insertTestRecords('table_name', [
            ['id' => 1, 'name' => 'Test Record 1', 'status' => 'active'],
            ['id' => 2, 'name' => 'Test Record 2', 'status' => 'inactive'],
            ['id' => 3, 'name' => 'Test Record 3', 'status' => 'active']
        ]);
    }
    
    /**
     * Test create operation with database persistence
     * @test
     */
    public function testCreateWithPersistence(): void
    {
        // Arrange
        $data = [
            'name' => 'New Record',
            'description' => 'Test description',
            'status' => 'active'
        ];
        
        // Act
        $result = $this->service->create($data);
        
        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertEquals('New Record', $result['name']);
        
        // Verify database persistence
        $record = $this->findInDatabase('table_name', ['id' => $result['id']]);
        $this->assertNotNull($record);
        $this->assertEquals('New Record', $record['name']);
    }
    
    /**
     * Test update operation with validation
     * @test
     */
    public function testUpdateWithValidation(): void
    {
        // Arrange
        $id = 1;
        $updateData = [
            'name' => 'Updated Name',
            'status' => 'inactive'
        ];
        
        // Act
        $result = $this->service->update($id, $updateData);
        
        // Assert
        $this->assertTrue($result);
        
        // Verify changes in database
        $record = $this->findInDatabase('table_name', ['id' => $id]);
        $this->assertEquals('Updated Name', $record['name']);
        $this->assertEquals('inactive', $record['status']);
    }
    
    /**
     * Test delete operation with cascade
     * @test
     */
    public function testDeleteWithCascade(): void
    {
        // Arrange
        $id = 1;
        
        // Create related records
        $this->insertTestRecords('related_table', [
            ['id' => 1, 'parent_id' => $id, 'data' => 'Related 1'],
            ['id' => 2, 'parent_id' => $id, 'data' => 'Related 2']
        ]);
        
        // Act
        $result = $this->service->delete($id);
        
        // Assert
        $this->assertTrue($result);
        
        // Verify parent record deleted
        $record = $this->findInDatabase('table_name', ['id' => $id]);
        $this->assertNull($record);
        
        // Verify related records deleted
        $relatedCount = $this->countInDatabase('related_table', ['parent_id' => $id]);
        $this->assertEquals(0, $relatedCount);
    }
    
    /**
     * Test bulk operations
     * @test
     */
    public function testBulkOperations(): void
    {
        // Arrange
        $ids = [1, 2, 3];
        $updateData = ['status' => 'archived'];
        
        // Act
        $result = $this->service->bulkUpdate($ids, $updateData);
        
        // Assert
        $this->assertEquals(3, $result['updated']);
        
        // Verify all records updated
        foreach ($ids as $id) {
            $record = $this->findInDatabase('table_name', ['id' => $id]);
            $this->assertEquals('archived', $record['status']);
        }
    }
    
    /**
     * Test transaction handling
     * @test
     */
    public function testTransactionHandling(): void
    {
        // Test successful transaction
        $result = $this->service->performComplexOperation([
            'step1' => 'create_record',
            'step2' => 'update_related',
            'step3' => 'send_notification'
        ]);
        
        $this->assertTrue($result['success']);
        $this->assertCount(3, $result['completed_steps']);
        
        // Test failed transaction with rollback
        $this->mockExternalService->expects($this->once())
            ->method('sendNotification')
            ->willThrowException(new \Exception('Service unavailable'));
        
        try {
            $this->service->performComplexOperation([
                'step1' => 'create_record',
                'step2' => 'update_related',
                'step3' => 'send_notification'
            ]);
        } catch (\Exception $e) {
            // Verify rollback occurred
            $recordCount = $this->countInDatabase('table_name', []);
            $this->assertEquals(3, $recordCount); // Original 3 records only
        }
    }
    
    /**
     * Test concurrent access handling
     * @test
     */
    public function testConcurrentAccess(): void
    {
        // Simulate concurrent updates
        $id = 1;
        
        // User 1 loads record
        $record1 = $this->service->find($id);
        $version1 = $record1['version'];
        
        // User 2 updates record (simulated)
        $this->updateInDatabase('table_name', 
            ['id' => $id], 
            ['name' => 'User 2 Update', 'version' => $version1 + 1]
        );
        
        // User 1 tries to update with stale version
        $this->expectException(ConcurrencyException::class);
        $this->service->update($id, ['name' => 'User 1 Update'], $version1);
    }
    
    /**
     * Test external service integration
     * @test
     */
    public function testExternalServiceIntegration(): void
    {
        // Mock external service response
        $this->mockExternalService->expects($this->once())
            ->method('fetchData')
            ->with('test-key')
            ->willReturn(['status' => 'success', 'data' => 'external data']);
        
        // Act
        $result = $this->service->syncWithExternal('test-key');
        
        // Assert
        $this->assertTrue($result['synced']);
        $this->assertEquals('external data', $result['data']);
        
        // Verify local cache updated
        $cached = $this->findInDatabase('cache_table', ['key' => 'test-key']);
        $this->assertNotNull($cached);
    }
    
    /**
     * Test performance with large datasets
     * @test
     * @group performance
     */
    public function testPerformanceWithLargeDataset(): void
    {
        // Insert large dataset
        $records = [];
        for ($i = 0; $i < 1000; $i++) {
            $records[] = [
                'id' => $i + 1000,
                'name' => "Record $i",
                'status' => $i % 2 === 0 ? 'active' : 'inactive'
            ];
        }
        $this->insertTestRecords('table_name', $records);
        
        // Test query performance
        $this->assertExecutionTime(function() {
            $results = $this->service->findByStatus('active', ['limit' => 100]);
            $this->assertCount(100, $results);
        }, 0.5, 'Query should complete within 500ms');
    }
    
    /**
     * Test error recovery
     * @test
     */
    public function testErrorRecovery(): void
    {
        // Simulate partial failure
        $items = [
            ['id' => 1, 'action' => 'update'],
            ['id' => 999, 'action' => 'update'], // Non-existent
            ['id' => 3, 'action' => 'update']
        ];
        
        $results = $this->service->processBatch($items);
        
        // Assert partial success
        $this->assertEquals(2, $results['successful']);
        $this->assertEquals(1, $results['failed']);
        $this->assertArrayHasKey(999, $results['errors']);
    }
}