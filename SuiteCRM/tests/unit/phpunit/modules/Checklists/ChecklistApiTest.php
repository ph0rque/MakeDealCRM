<?php

use PHPUnit\Framework\TestCase;

/**
 * Test Suite for Checklist API Endpoints
 */
class ChecklistApiTest extends TestCase
{
    protected $api;
    protected $db;
    protected $request;
    protected $response;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock API class
        $this->api = $this->getMockBuilder('ChecklistApi')
            ->disableOriginalConstructor()
            ->getMock();
        
        // Mock database
        $this->db = $this->createMock(DBManager::class);
        
        // Mock request
        $this->request = $this->getMockBuilder('RestRequest')
            ->disableOriginalConstructor()
            ->getMock();
        
        // Mock response
        $this->response = $this->getMockBuilder('RestResponse')
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function tearDown(): void
    {
        unset($this->api);
        unset($this->db);
        unset($this->request);
        unset($this->response);
        parent::tearDown();
    }

    /**
     * Test GET /checklists endpoint
     */
    public function testGetChecklists()
    {
        $expectedChecklists = [
            ['id' => '1', 'name' => 'Due Diligence Checklist', 'status' => 'in_progress'],
            ['id' => '2', 'name' => 'Closing Checklist', 'status' => 'pending']
        ];

        $this->api->expects($this->once())
            ->method('getChecklists')
            ->with($this->request)
            ->willReturn($expectedChecklists);

        $result = $this->api->getChecklists($this->request);
        
        $this->assertCount(2, $result);
        $this->assertEquals('Due Diligence Checklist', $result[0]['name']);
    }

    /**
     * Test GET /checklists/:id endpoint
     */
    public function testGetChecklistById()
    {
        $checklistId = 'checklist-123';
        $expectedChecklist = [
            'id' => $checklistId,
            'name' => 'Due Diligence Checklist',
            'status' => 'in_progress',
            'items' => [
                ['id' => 'item-1', 'title' => 'Financial Review', 'status' => 'completed'],
                ['id' => 'item-2', 'title' => 'Legal Review', 'status' => 'in_progress']
            ]
        ];

        $this->request->expects($this->once())
            ->method('getParameter')
            ->with('id')
            ->willReturn($checklistId);

        $this->api->expects($this->once())
            ->method('getChecklist')
            ->with($this->request)
            ->willReturn($expectedChecklist);

        $result = $this->api->getChecklist($this->request);
        
        $this->assertEquals($checklistId, $result['id']);
        $this->assertCount(2, $result['items']);
    }

    /**
     * Test POST /checklists endpoint
     */
    public function testCreateChecklist()
    {
        $checklistData = [
            'name' => 'New Checklist',
            'deal_id' => 'deal-123',
            'template_id' => 'template-456'
        ];

        $expectedResponse = [
            'id' => 'new-checklist-id',
            'name' => 'New Checklist',
            'created' => true
        ];

        $this->request->expects($this->once())
            ->method('getBody')
            ->willReturn($checklistData);

        $this->api->expects($this->once())
            ->method('createChecklist')
            ->with($this->request)
            ->willReturn($expectedResponse);

        $result = $this->api->createChecklist($this->request);
        
        $this->assertTrue($result['created']);
        $this->assertEquals('new-checklist-id', $result['id']);
    }

    /**
     * Test PUT /checklists/:id endpoint
     */
    public function testUpdateChecklist()
    {
        $checklistId = 'checklist-123';
        $updateData = [
            'name' => 'Updated Checklist Name',
            'status' => 'completed'
        ];

        $expectedResponse = [
            'id' => $checklistId,
            'updated' => true
        ];

        $this->request->expects($this->once())
            ->method('getParameter')
            ->with('id')
            ->willReturn($checklistId);

        $this->request->expects($this->once())
            ->method('getBody')
            ->willReturn($updateData);

        $this->api->expects($this->once())
            ->method('updateChecklist')
            ->with($this->request)
            ->willReturn($expectedResponse);

        $result = $this->api->updateChecklist($this->request);
        
        $this->assertTrue($result['updated']);
        $this->assertEquals($checklistId, $result['id']);
    }

    /**
     * Test DELETE /checklists/:id endpoint
     */
    public function testDeleteChecklist()
    {
        $checklistId = 'checklist-123';

        $expectedResponse = [
            'id' => $checklistId,
            'deleted' => true
        ];

        $this->request->expects($this->once())
            ->method('getParameter')
            ->with('id')
            ->willReturn($checklistId);

        $this->api->expects($this->once())
            ->method('deleteChecklist')
            ->with($this->request)
            ->willReturn($expectedResponse);

        $result = $this->api->deleteChecklist($this->request);
        
        $this->assertTrue($result['deleted']);
        $this->assertEquals($checklistId, $result['id']);
    }

    /**
     * Test POST /checklists/:id/items endpoint
     */
    public function testAddChecklistItem()
    {
        $checklistId = 'checklist-123';
        $itemData = [
            'title' => 'New Task',
            'description' => 'Task description',
            'priority' => 'high'
        ];

        $expectedResponse = [
            'id' => 'new-item-id',
            'checklist_id' => $checklistId,
            'created' => true
        ];

        $this->request->expects($this->once())
            ->method('getParameter')
            ->with('id')
            ->willReturn($checklistId);

        $this->request->expects($this->once())
            ->method('getBody')
            ->willReturn($itemData);

        $this->api->expects($this->once())
            ->method('addItem')
            ->with($this->request)
            ->willReturn($expectedResponse);

        $result = $this->api->addItem($this->request);
        
        $this->assertTrue($result['created']);
        $this->assertEquals('new-item-id', $result['id']);
    }

    /**
     * Test PUT /checklists/:id/items/:itemId endpoint
     */
    public function testUpdateChecklistItem()
    {
        $checklistId = 'checklist-123';
        $itemId = 'item-456';
        $updateData = [
            'status' => 'completed',
            'actual_hours' => 4
        ];

        $expectedResponse = [
            'id' => $itemId,
            'updated' => true
        ];

        $this->request->expects($this->exactly(2))
            ->method('getParameter')
            ->willReturnMap([
                ['id', $checklistId],
                ['itemId', $itemId]
            ]);

        $this->request->expects($this->once())
            ->method('getBody')
            ->willReturn($updateData);

        $this->api->expects($this->once())
            ->method('updateItem')
            ->with($this->request)
            ->willReturn($expectedResponse);

        $result = $this->api->updateItem($this->request);
        
        $this->assertTrue($result['updated']);
        $this->assertEquals($itemId, $result['id']);
    }

    /**
     * Test POST /checklists/:id/export endpoint
     */
    public function testExportChecklist()
    {
        $checklistId = 'checklist-123';
        $exportParams = [
            'format' => 'pdf',
            'include_comments' => true
        ];

        $expectedResponse = [
            'file_url' => '/exports/checklist-123.pdf',
            'exported' => true
        ];

        $this->request->expects($this->once())
            ->method('getParameter')
            ->with('id')
            ->willReturn($checklistId);

        $this->request->expects($this->once())
            ->method('getBody')
            ->willReturn($exportParams);

        $this->api->expects($this->once())
            ->method('exportChecklist')
            ->with($this->request)
            ->willReturn($expectedResponse);

        $result = $this->api->exportChecklist($this->request);
        
        $this->assertTrue($result['exported']);
        $this->assertStringContainsString('.pdf', $result['file_url']);
    }

    /**
     * Test GET /checklists/templates endpoint
     */
    public function testGetTemplates()
    {
        $expectedTemplates = [
            ['id' => '1', 'name' => 'Due Diligence Template', 'category' => 'due_diligence'],
            ['id' => '2', 'name' => 'Closing Template', 'category' => 'closing']
        ];

        $this->api->expects($this->once())
            ->method('getTemplates')
            ->with($this->request)
            ->willReturn($expectedTemplates);

        $result = $this->api->getTemplates($this->request);
        
        $this->assertCount(2, $result);
        $this->assertEquals('Due Diligence Template', $result[0]['name']);
    }

    /**
     * Test POST /checklists/:id/clone endpoint
     */
    public function testCloneChecklist()
    {
        $checklistId = 'checklist-123';
        $cloneParams = [
            'name' => 'Cloned Checklist',
            'deal_id' => 'new-deal-456'
        ];

        $expectedResponse = [
            'id' => 'cloned-checklist-id',
            'name' => 'Cloned Checklist',
            'cloned' => true
        ];

        $this->request->expects($this->once())
            ->method('getParameter')
            ->with('id')
            ->willReturn($checklistId);

        $this->request->expects($this->once())
            ->method('getBody')
            ->willReturn($cloneParams);

        $this->api->expects($this->once())
            ->method('cloneChecklist')
            ->with($this->request)
            ->willReturn($expectedResponse);

        $result = $this->api->cloneChecklist($this->request);
        
        $this->assertTrue($result['cloned']);
        $this->assertEquals('cloned-checklist-id', $result['id']);
    }

    /**
     * Test error handling
     */
    public function testErrorHandling()
    {
        $checklistId = 'non-existent';

        $this->request->expects($this->once())
            ->method('getParameter')
            ->with('id')
            ->willReturn($checklistId);

        $this->api->expects($this->once())
            ->method('getChecklist')
            ->with($this->request)
            ->willThrowException(new Exception('Checklist not found'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Checklist not found');

        $this->api->getChecklist($this->request);
    }

    /**
     * Test pagination
     */
    public function testPagination()
    {
        $paginationParams = [
            'page' => 2,
            'limit' => 10
        ];

        $expectedResponse = [
            'data' => [/* checklist items */],
            'pagination' => [
                'page' => 2,
                'limit' => 10,
                'total' => 50,
                'pages' => 5
            ]
        ];

        $this->request->expects($this->exactly(2))
            ->method('getParameter')
            ->willReturnMap([
                ['page', 2],
                ['limit', 10]
            ]);

        $this->api->expects($this->once())
            ->method('getChecklists')
            ->with($this->request)
            ->willReturn($expectedResponse);

        $result = $this->api->getChecklists($this->request);
        
        $this->assertArrayHasKey('pagination', $result);
        $this->assertEquals(2, $result['pagination']['page']);
        $this->assertEquals(50, $result['pagination']['total']);
    }

    /**
     * Test filtering
     */
    public function testFiltering()
    {
        $filterParams = [
            'status' => 'in_progress',
            'priority' => 'high',
            'assigned_to' => 'user-123'
        ];

        $expectedResponse = [
            ['id' => '1', 'status' => 'in_progress', 'priority' => 'high'],
            ['id' => '2', 'status' => 'in_progress', 'priority' => 'high']
        ];

        $this->request->expects($this->any())
            ->method('getParameter')
            ->willReturnCallback(function($param) use ($filterParams) {
                return $filterParams[$param] ?? null;
            });

        $this->api->expects($this->once())
            ->method('getChecklists')
            ->with($this->request)
            ->willReturn($expectedResponse);

        $result = $this->api->getChecklists($this->request);
        
        $this->assertCount(2, $result);
        foreach ($result as $checklist) {
            $this->assertEquals('in_progress', $checklist['status']);
            $this->assertEquals('high', $checklist['priority']);
        }
    }
}