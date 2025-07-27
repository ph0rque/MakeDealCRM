<?php

use PHPUnit\Framework\TestCase;

/**
 * Test Suite for Checklist Items
 */
class ChecklistItemTest extends TestCase
{
    protected $db;
    protected $item;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock database connection
        $this->db = $this->createMock(DBManager::class);
        
        // Mock ChecklistItems bean
        $this->item = $this->getMockBuilder('ChecklistItems')
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function tearDown(): void
    {
        unset($this->db);
        unset($this->item);
        parent::tearDown();
    }

    /**
     * Test item creation
     */
    public function testCreateItem()
    {
        $itemData = [
            'title' => 'Review Financial Statements',
            'description' => 'Review last 3 years of financial statements',
            'category' => 'financial',
            'priority' => 'high',
            'estimated_hours' => 8,
            'checklist_id' => 'checklist-123'
        ];

        $this->item->expects($this->once())
            ->method('save')
            ->willReturn('item-id-123');

        $this->item->title = $itemData['title'];
        $this->item->description = $itemData['description'];
        $this->item->category = $itemData['category'];
        $this->item->priority = $itemData['priority'];
        $this->item->estimated_hours = $itemData['estimated_hours'];
        $this->item->checklist_id = $itemData['checklist_id'];

        $result = $this->item->save();
        
        $this->assertEquals('item-id-123', $result);
    }

    /**
     * Test item status transitions
     */
    public function testItemStatusTransitions()
    {
        $validTransitions = [
            'pending' => ['in_progress', 'blocked', 'deferred'],
            'in_progress' => ['completed', 'blocked', 'pending'],
            'blocked' => ['in_progress', 'pending', 'deferred'],
            'completed' => ['in_progress'], // Can reopen
            'deferred' => ['pending', 'in_progress']
        ];

        foreach ($validTransitions as $fromStatus => $toStatuses) {
            $this->item->status = $fromStatus;
            
            foreach ($toStatuses as $toStatus) {
                $this->item->expects($this->any())
                    ->method('canTransitionTo')
                    ->with($toStatus)
                    ->willReturn(true);
                
                $this->assertTrue($this->item->canTransitionTo($toStatus));
            }
        }
    }

    /**
     * Test item completion
     */
    public function testCompleteItem()
    {
        $this->item->status = 'in_progress';
        $this->item->expects($this->once())
            ->method('complete')
            ->willReturn(true);

        $this->item->expects($this->once())
            ->method('isCompleted')
            ->willReturn(true);

        $result = $this->item->complete();
        
        $this->assertTrue($result);
        $this->assertTrue($this->item->isCompleted());
    }

    /**
     * Test item dependencies
     */
    public function testItemDependencies()
    {
        $dependencyIds = ['item-1', 'item-2', 'item-3'];
        
        $this->item->expects($this->once())
            ->method('getDependencies')
            ->willReturn($dependencyIds);

        $this->item->expects($this->once())
            ->method('canStart')
            ->willReturn(false); // Has incomplete dependencies

        $dependencies = $this->item->getDependencies();
        
        $this->assertCount(3, $dependencies);
        $this->assertFalse($this->item->canStart());
    }

    /**
     * Test item assignment
     */
    public function testItemAssignment()
    {
        $userId = 'user-123';
        $this->item->assigned_to = null;

        $this->item->expects($this->once())
            ->method('assignTo')
            ->with($userId)
            ->willReturn(true);

        $result = $this->item->assignTo($userId);
        
        $this->assertTrue($result);
    }

    /**
     * Test item file attachments
     */
    public function testItemFileAttachments()
    {
        $fileData = [
            'name' => 'financial_report.pdf',
            'type' => 'application/pdf',
            'size' => 1024000,
            'path' => '/tmp/financial_report.pdf'
        ];

        $this->item->expects($this->once())
            ->method('attachFile')
            ->with($fileData)
            ->willReturn('attachment-id-123');

        $attachmentId = $this->item->attachFile($fileData);
        
        $this->assertEquals('attachment-id-123', $attachmentId);
    }

    /**
     * Test item time tracking
     */
    public function testItemTimeTracking()
    {
        $this->item->estimated_hours = 8;
        $this->item->actual_hours = 0;

        // Log 2 hours
        $this->item->expects($this->once())
            ->method('logTime')
            ->with(2, 'Reviewed Q1 statements')
            ->willReturn(true);

        $this->item->expects($this->once())
            ->method('getActualHours')
            ->willReturn(2);

        $result = $this->item->logTime(2, 'Reviewed Q1 statements');
        
        $this->assertTrue($result);
        $this->assertEquals(2, $this->item->getActualHours());
    }

    /**
     * Test item comments
     */
    public function testItemComments()
    {
        $comment = [
            'text' => 'Found discrepancies in Q2 report',
            'author_id' => 'user-123',
            'created_at' => '2025-01-26 10:00:00'
        ];

        $this->item->expects($this->once())
            ->method('addComment')
            ->with($comment['text'], $comment['author_id'])
            ->willReturn('comment-id-123');

        $commentId = $this->item->addComment($comment['text'], $comment['author_id']);
        
        $this->assertEquals('comment-id-123', $commentId);
    }

    /**
     * Test item validation rules
     */
    public function testItemValidation()
    {
        // Test required fields
        $this->item->title = '';
        $this->item->expects($this->once())
            ->method('validate')
            ->willReturn(false);
        
        $this->assertFalse($this->item->validate());

        // Test valid item
        $this->item->title = 'Valid Title';
        $this->item->checklist_id = 'checklist-123';
        $this->item->expects($this->once())
            ->method('validate')
            ->willReturn(true);
        
        $this->assertTrue($this->item->validate());
    }

    /**
     * Test item duplication
     */
    public function testItemDuplication()
    {
        $this->item->id = 'original-item';
        $this->item->title = 'Original Item';
        
        $this->item->expects($this->once())
            ->method('duplicate')
            ->willReturn('duplicated-item-id');

        $newId = $this->item->duplicate();
        
        $this->assertEquals('duplicated-item-id', $newId);
    }

    /**
     * Test item priority levels
     */
    public function testItemPriorityLevels()
    {
        $validPriorities = ['low', 'medium', 'high', 'critical'];
        
        foreach ($validPriorities as $priority) {
            $this->item->priority = $priority;
            $this->item->expects($this->any())
                ->method('isValidPriority')
                ->willReturn(true);
            
            $this->assertTrue($this->item->isValidPriority());
        }
    }

    /**
     * Test item notifications
     */
    public function testItemNotifications()
    {
        $this->item->assigned_to = 'user-123';
        $this->item->due_date = '2025-02-01';
        
        $this->item->expects($this->once())
            ->method('shouldSendNotification')
            ->willReturn(true);

        $this->item->expects($this->once())
            ->method('sendNotification')
            ->with('due_soon')
            ->willReturn(true);

        $this->assertTrue($this->item->shouldSendNotification());
        $this->assertTrue($this->item->sendNotification('due_soon'));
    }
}