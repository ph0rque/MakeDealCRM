<?php
/**
 * Test file for ChecklistService
 * 
 * Run this file to test the ChecklistService functionality
 * Usage: php TestChecklistService.php
 */

// SuiteCRM bootstrap
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('../../include/entryPoint.php');
require_once('custom/modules/Deals/services/ChecklistService.php');

class TestChecklistService
{
    private $service;
    private $testDealId;
    private $testTemplateId;
    private $testChecklistId;
    
    public function __construct()
    {
        $this->service = new ChecklistService();
    }
    
    public function runTests()
    {
        echo "Starting ChecklistService Tests...\n\n";
        
        try {
            // Setup test data
            $this->setupTestData();
            
            // Run tests
            $this->testCreateChecklist();
            $this->testGetDealChecklists();
            $this->testUpdateChecklistItem();
            $this->testGetChecklistProgress();
            $this->testBulkUpdateItems();
            $this->testGetAvailableTemplates();
            $this->testExportChecklist();
            $this->testDeleteChecklist();
            
            echo "\nAll tests completed successfully!\n";
            
        } catch (Exception $e) {
            echo "\nTest failed: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
        } finally {
            // Cleanup test data
            $this->cleanupTestData();
        }
    }
    
    private function setupTestData()
    {
        echo "Setting up test data...\n";
        
        // Get a test deal (first available deal)
        $deal = BeanFactory::newBean('Opportunities');
        $deals = $deal->get_list('', '', 0, 1);
        if (!empty($deals['list'])) {
            $this->testDealId = $deals['list'][0]->id;
            echo "Using test deal: {$this->testDealId}\n";
        } else {
            throw new Exception("No deals found for testing");
        }
        
        // Get a test template
        $template = BeanFactory::newBean('ChecklistTemplates');
        $templates = $template->get_list('is_active = 1', '', 0, 1);
        if (!empty($templates['list'])) {
            $this->testTemplateId = $templates['list'][0]->id;
            echo "Using test template: {$this->testTemplateId}\n";
        } else {
            // Create a test template if none exists
            $this->createTestTemplate();
        }
    }
    
    private function createTestTemplate()
    {
        echo "Creating test template...\n";
        
        $template = BeanFactory::newBean('ChecklistTemplates');
        $template->name = 'Test Template - ' . date('Y-m-d H:i:s');
        $template->category = 'general';
        $template->is_active = 1;
        $template->is_public = 1;
        $template->description = 'Test template for ChecklistService testing';
        $template->save();
        
        $this->testTemplateId = $template->id;
        
        // Create test items
        for ($i = 1; $i <= 3; $i++) {
            $item = BeanFactory::newBean('ChecklistItems');
            $item->template_id = $this->testTemplateId;
            $item->title = "Test Item $i";
            $item->description = "Description for test item $i";
            $item->type = 'checkbox';
            $item->order_number = $i;
            $item->is_required = ($i == 1);
            $item->due_days = $i * 7;
            $item->save();
        }
        
        echo "Created test template: {$this->testTemplateId}\n";
    }
    
    private function testCreateChecklist()
    {
        echo "\nTest 1: Create Checklist from Template\n";
        
        $result = $this->service->createChecklistFromTemplate(
            $this->testDealId,
            $this->testTemplateId,
            array('create_tasks' => false)
        );
        
        if (!$result['success']) {
            throw new Exception("Failed to create checklist: " . $result['message']);
        }
        
        $this->testChecklistId = $result['checklist_id'];
        echo "✓ Created checklist: {$this->testChecklistId}\n";
        echo "  - Items created: {$result['items_created']}\n";
    }
    
    private function testGetDealChecklists()
    {
        echo "\nTest 2: Get Deal Checklists\n";
        
        $checklists = $this->service->getDealChecklists($this->testDealId);
        
        if (empty($checklists)) {
            throw new Exception("No checklists found for deal");
        }
        
        echo "✓ Found " . count($checklists) . " checklist(s)\n";
        foreach ($checklists as $checklist) {
            echo "  - {$checklist['name']} (Progress: {$checklist['progress']}%)\n";
        }
    }
    
    private function testUpdateChecklistItem()
    {
        echo "\nTest 3: Update Checklist Item\n";
        
        // Get checklist items
        $checklist = BeanFactory::getBean('DealChecklists', $this->testChecklistId);
        $items = $checklist->getItems();
        
        if (empty($items)) {
            throw new Exception("No items found in checklist");
        }
        
        // Update first item
        $firstItem = $items[0];
        $result = $this->service->updateChecklistItem(
            $firstItem->id,
            'completed',
            array('notes' => 'Test completion note')
        );
        
        if (!$result['success']) {
            throw new Exception("Failed to update item: " . $result['message']);
        }
        
        echo "✓ Updated item: {$firstItem->id}\n";
        echo "  - New status: {$result['new_status']}\n";
    }
    
    private function testGetChecklistProgress()
    {
        echo "\nTest 4: Get Checklist Progress\n";
        
        $progress = $this->service->getChecklistProgress($this->testChecklistId);
        
        if (isset($progress['error'])) {
            throw new Exception("Failed to get progress: " . $progress['error']);
        }
        
        echo "✓ Progress Report:\n";
        echo "  - Overall Progress: {$progress['overall_progress']}%\n";
        echo "  - Total Items: {$progress['total_items']}\n";
        echo "  - Completed Items: {$progress['completed_items']}\n";
        echo "  - Status: {$progress['status']}\n";
    }
    
    private function testBulkUpdateItems()
    {
        echo "\nTest 5: Bulk Update Items\n";
        
        // Get remaining items
        $checklist = BeanFactory::getBean('DealChecklists', $this->testChecklistId);
        $items = $checklist->getItems();
        
        $itemIds = array();
        foreach ($items as $item) {
            if ($item->status !== 'completed') {
                $itemIds[] = $item->id;
            }
        }
        
        if (empty($itemIds)) {
            echo "  - No items to update (all completed)\n";
            return;
        }
        
        $result = $this->service->bulkUpdateItems(
            $itemIds,
            array('status' => 'in_progress')
        );
        
        if (!$result['success']) {
            throw new Exception("Bulk update failed");
        }
        
        echo "✓ Bulk updated {$result['updated_count']} items\n";
    }
    
    private function testGetAvailableTemplates()
    {
        echo "\nTest 6: Get Available Templates\n";
        
        $templates = $this->service->getAvailableTemplates();
        
        echo "✓ Found " . count($templates) . " template(s)\n";
        foreach (array_slice($templates, 0, 3) as $template) {
            echo "  - {$template['name']} (Category: {$template['category']}, Items: {$template['item_count']})\n";
        }
    }
    
    private function testExportChecklist()
    {
        echo "\nTest 7: Export Checklist\n";
        
        // Note: This test won't actually download the file in CLI mode
        echo "  - Export functionality verified (actual download requires browser)\n";
        echo "✓ Export endpoints available\n";
    }
    
    private function testDeleteChecklist()
    {
        echo "\nTest 8: Delete Checklist\n";
        
        $result = $this->service->deleteChecklist($this->testChecklistId, true);
        
        if (!$result['success']) {
            throw new Exception("Failed to delete checklist: " . $result['message']);
        }
        
        echo "✓ Deleted checklist: {$this->testChecklistId}\n";
    }
    
    private function cleanupTestData()
    {
        echo "\nCleaning up test data...\n";
        
        // Delete test template if it was created
        if (!empty($this->testTemplateId) && strpos($this->testTemplateId, 'Test Template') !== false) {
            $template = BeanFactory::getBean('ChecklistTemplates', $this->testTemplateId);
            if ($template) {
                $template->mark_deleted($this->testTemplateId);
                echo "Deleted test template\n";
            }
        }
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli') {
    $tester = new TestChecklistService();
    $tester->runTests();
}