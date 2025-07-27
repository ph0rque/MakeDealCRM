<?php

use PHPUnit\Framework\TestCase;

/**
 * Test Suite for Checklist Templates
 */
class ChecklistTemplateTest extends TestCase
{
    protected $db;
    protected $template;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock database connection
        $this->db = $this->createMock(DBManager::class);
        
        // Mock ChecklistTemplates bean
        $this->template = $this->getMockBuilder('ChecklistTemplates')
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function tearDown(): void
    {
        unset($this->db);
        unset($this->template);
        parent::tearDown();
    }

    /**
     * Test template creation
     */
    public function testCreateTemplate()
    {
        $templateData = [
            'name' => 'Due Diligence Template',
            'description' => 'Standard due diligence checklist',
            'category' => 'due_diligence',
            'is_active' => true,
            'is_public' => true,
            'version' => '1.0'
        ];

        $this->template->expects($this->once())
            ->method('save')
            ->willReturn('test-id-123');

        $this->template->name = $templateData['name'];
        $this->template->description = $templateData['description'];
        $this->template->category = $templateData['category'];
        $this->template->is_active = $templateData['is_active'];
        $this->template->is_public = $templateData['is_public'];
        $this->template->version = $templateData['version'];

        $result = $this->template->save();
        
        $this->assertEquals('test-id-123', $result);
    }

    /**
     * Test template validation
     */
    public function testTemplateValidation()
    {
        // Test empty name validation
        $this->template->name = '';
        $this->template->expects($this->once())
            ->method('validate')
            ->willReturn(false);
        
        $this->assertFalse($this->template->validate());
    }

    /**
     * Test template versioning
     */
    public function testTemplateVersioning()
    {
        $this->template->expects($this->once())
            ->method('createNewVersion')
            ->with('2.0')
            ->willReturn(true);

        $this->template->version = '1.0';
        $result = $this->template->createNewVersion('2.0');
        
        $this->assertTrue($result);
    }

    /**
     * Test template cloning
     */
    public function testTemplateCloning()
    {
        $this->template->expects($this->once())
            ->method('cloneTemplate')
            ->with('Cloned Template')
            ->willReturn('new-template-id');

        $this->template->id = 'original-id';
        $this->template->name = 'Original Template';
        
        $newId = $this->template->cloneTemplate('Cloned Template');
        
        $this->assertEquals('new-template-id', $newId);
    }

    /**
     * Test template application to deal
     */
    public function testApplyTemplateToDeal()
    {
        $dealId = 'deal-123';
        $expectedChecklistId = 'checklist-456';
        
        $this->template->expects($this->once())
            ->method('applyToDeal')
            ->with($dealId)
            ->willReturn($expectedChecklistId);

        $result = $this->template->applyToDeal($dealId);
        
        $this->assertEquals($expectedChecklistId, $result);
    }

    /**
     * Test template category validation
     */
    public function testTemplateCategoryValidation()
    {
        $validCategories = [
            'due_diligence',
            'closing',
            'post_merger',
            'regulatory',
            'financial',
            'legal',
            'technical',
            'hr',
            'general'
        ];

        foreach ($validCategories as $category) {
            $this->template->category = $category;
            $this->template->expects($this->any())
                ->method('isValidCategory')
                ->willReturn(true);
            
            $this->assertTrue($this->template->isValidCategory());
        }
    }

    /**
     * Test template permissions
     */
    public function testTemplatePermissions()
    {
        // Test public template access
        $this->template->is_public = true;
        $this->template->created_by = 'user-1';
        
        $this->template->expects($this->once())
            ->method('canAccess')
            ->with('user-2')
            ->willReturn(true);
        
        $this->assertTrue($this->template->canAccess('user-2'));
    }

    /**
     * Test template search
     */
    public function testTemplateSearch()
    {
        $searchParams = [
            'category' => 'due_diligence',
            'is_active' => true,
            'search_term' => 'financial'
        ];

        $expectedResults = [
            ['id' => '1', 'name' => 'Financial Due Diligence'],
            ['id' => '2', 'name' => 'Financial Audit Checklist']
        ];

        $this->template->expects($this->once())
            ->method('searchTemplates')
            ->with($searchParams)
            ->willReturn($expectedResults);

        $results = $this->template->searchTemplates($searchParams);
        
        $this->assertCount(2, $results);
        $this->assertEquals('Financial Due Diligence', $results[0]['name']);
    }

    /**
     * Test template export
     */
    public function testTemplateExport()
    {
        $expectedExport = [
            'name' => 'Test Template',
            'description' => 'Test Description',
            'category' => 'general',
            'items' => [
                ['title' => 'Item 1', 'description' => 'Description 1'],
                ['title' => 'Item 2', 'description' => 'Description 2']
            ]
        ];

        $this->template->expects($this->once())
            ->method('exportTemplate')
            ->willReturn($expectedExport);

        $export = $this->template->exportTemplate();
        
        $this->assertArrayHasKey('items', $export);
        $this->assertCount(2, $export['items']);
    }

    /**
     * Test template import
     */
    public function testTemplateImport()
    {
        $importData = [
            'name' => 'Imported Template',
            'description' => 'Imported Description',
            'category' => 'general',
            'items' => [
                ['title' => 'Item 1', 'description' => 'Description 1'],
                ['title' => 'Item 2', 'description' => 'Description 2']
            ]
        ];

        $this->template->expects($this->once())
            ->method('importTemplate')
            ->with($importData)
            ->willReturn('imported-template-id');

        $templateId = $this->template->importTemplate($importData);
        
        $this->assertEquals('imported-template-id', $templateId);
    }
}