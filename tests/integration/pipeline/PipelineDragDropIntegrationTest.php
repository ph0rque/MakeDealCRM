<?php

namespace Tests\Integration\Pipeline;

use Tests\DatabaseTestCase;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\Interactions\WebDriverActions;
use Facebook\WebDriver\WebDriverWait;

/**
 * Integration tests for Pipeline Drag and Drop functionality
 * Tests end-to-end drag operations across all stages with validation
 */
class PipelineDragDropIntegrationTest extends DatabaseTestCase
{
    protected RemoteWebDriver $driver;
    protected WebDriverWait $wait;
    protected string $baseUrl = 'http://localhost:8080';
    
    protected array $stages = [
        'sourcing',
        'screening', 
        'analysis_outreach',
        'due_diligence',
        'valuation_structuring',
        'loi_negotiation',
        'financing',
        'closing',
        'closed_owned_90_day',
        'closed_owned_stable',
        'unavailable'
    ];

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup WebDriver for browser automation
        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability('chromeOptions', [
            'args' => ['--headless', '--no-sandbox', '--disable-dev-shm-usage']
        ]);
        
        $this->driver = RemoteWebDriver::create('http://localhost:4444/wd/hub', $capabilities);
        $this->wait = new WebDriverWait($this->driver, 10);
        
        // Create test data
        $this->createTestDeals();
        $this->loginTestUser();
    }

    protected function tearDown(): void
    {
        if ($this->driver) {
            $this->driver->quit();
        }
        parent::tearDown();
    }

    /**
     * @test
     * @group integration
     * @group drag-drop
     */
    public function testBasicDragAndDropFunctionality(): void
    {
        $this->driver->get($this->baseUrl . '/index.php?module=Deals&action=pipeline');
        
        // Wait for pipeline to load
        $this->wait->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::className('pipeline-stage')
            )
        );

        // Find a deal in sourcing stage
        $sourcingStage = $this->driver->findElement(
            WebDriverBy::cssSelector('[data-stage="sourcing"]')
        );
        $dealCard = $sourcingStage->findElement(
            WebDriverBy::className('deal-card')
        );
        $dealId = $dealCard->getAttribute('data-deal-id');

        // Find screening stage
        $screeningStage = $this->driver->findElement(
            WebDriverBy::cssSelector('[data-stage="screening"]')
        );

        // Perform drag and drop
        $actions = new WebDriverActions($this->driver);
        $actions->dragAndDrop($dealCard, $screeningStage)->perform();

        // Wait for AJAX completion
        $this->wait->until(
            WebDriverExpectedCondition::invisibilityOfElementLocated(
                WebDriverBy::className('pipeline-loading')
            )
        );

        // Verify deal moved to screening stage
        $this->assertElementExists('[data-stage="screening"] [data-deal-id="' . $dealId . '"]');
        
        // Verify database was updated
        $this->assertDatabaseHas('deals', [
            'id' => $dealId,
            'pipeline_stage_c' => 'screening'
        ]);

        // Verify stage history was recorded
        $this->assertDatabaseHas('deals_audit', [
            'parent_id' => $dealId,
            'field_name' => 'pipeline_stage_c',
            'data_value' => 'screening'
        ]);
    }

    /**
     * @test
     * @group integration
     * @group drag-drop
     * @group wip-limits
     */
    public function testWIPLimitEnforcement(): void
    {
        // Fill screening stage to capacity (15 deals)
        $this->fillStageToCapacity('screening', 15);
        
        $this->driver->get($this->baseUrl . '/index.php?module=Deals&action=pipeline');
        $this->waitForPipelineLoad();

        // Try to drag another deal to screening
        $sourcingStage = $this->driver->findElement(
            WebDriverBy::cssSelector('[data-stage="sourcing"]')
        );
        $dealCard = $sourcingStage->findElement(
            WebDriverBy::className('deal-card')
        );
        $dealId = $dealCard->getAttribute('data-deal-id');

        $screeningStage = $this->driver->findElement(
            WebDriverBy::cssSelector('[data-stage="screening"]')
        );

        // Attempt drag and drop
        $actions = new WebDriverActions($this->driver);
        $actions->dragAndDrop($dealCard, $screeningStage)->perform();

        // Should see WIP limit warning
        $this->wait->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::className('wip-limit-warning')
            )
        );

        // Deal should not have moved
        $this->assertElementExists('[data-stage="sourcing"] [data-deal-id="' . $dealId . '"]');
        
        // Database should remain unchanged
        $this->assertDatabaseHas('deals', [
            'id' => $dealId,
            'pipeline_stage_c' => 'sourcing'
        ]);
    }

    /**
     * @test 
     * @group integration
     * @group drag-drop
     * @group bulk-operations
     */
    public function testBulkDragAndDrop(): void
    {
        $this->driver->get($this->baseUrl . '/index.php?module=Deals&action=pipeline');
        $this->waitForPipelineLoad();

        // Select multiple deals using Ctrl+click
        $sourcingStage = $this->driver->findElement(
            WebDriverBy::cssSelector('[data-stage="sourcing"]')
        );
        $dealCards = $sourcingStage->findElements(
            WebDriverBy::className('deal-card')
        );

        $selectedDeals = [];
        $actions = new WebDriverActions($this->driver);
        
        // Select first 3 deals
        for ($i = 0; $i < min(3, count($dealCards)); $i++) {
            $dealId = $dealCards[$i]->getAttribute('data-deal-id');
            $selectedDeals[] = $dealId;
            
            if ($i === 0) {
                $dealCards[$i]->click();
            } else {
                $actions->keyDown(\Facebook\WebDriver\WebDriverKeys::CONTROL)
                       ->click($dealCards[$i])
                       ->keyUp(\Facebook\WebDriver\WebDriverKeys::CONTROL)
                       ->perform();
            }
        }

        // Verify selection visual feedback
        foreach ($selectedDeals as $dealId) {
            $this->assertElementHasClass('[data-deal-id="' . $dealId . '"]', 'selected');
        }

        // Drag selection to screening stage
        $screeningStage = $this->driver->findElement(
            WebDriverBy::cssSelector('[data-stage="screening"]')
        );
        
        $actions->dragAndDrop($dealCards[0], $screeningStage)->perform();

        // Wait for bulk operation completion
        $this->wait->until(
            WebDriverExpectedCondition::invisibilityOfElementLocated(
                WebDriverBy::className('bulk-operation-progress')
            )
        );

        // Verify all selected deals moved
        foreach ($selectedDeals as $dealId) {
            $this->assertElementExists('[data-stage="screening"] [data-deal-id="' . $dealId . '"]');
            $this->assertDatabaseHas('deals', [
                'id' => $dealId,
                'pipeline_stage_c' => 'screening'
            ]);
        }
    }

    /**
     * @test
     * @group integration
     * @group mobile
     * @group touch-gestures
     */
    public function testMobileTouchGestures(): void
    {
        // Switch to mobile viewport
        $this->driver->manage()->window()->setSize(new \Facebook\WebDriver\WebDriverDimension(375, 667));
        
        $this->driver->get($this->baseUrl . '/index.php?module=Deals&action=pipeline');
        $this->waitForPipelineLoad();

        // Simulate touch events for drag operation
        $sourcingStage = $this->driver->findElement(
            WebDriverBy::cssSelector('[data-stage="sourcing"]')
        );
        $dealCard = $sourcingStage->findElement(
            WebDriverBy::className('deal-card')
        );
        $dealId = $dealCard->getAttribute('data-deal-id');

        // Long press to initiate drag (touch and hold)
        $this->simulateTouchEvent($dealCard, 'touchstart');
        sleep(1); // Hold for 1 second
        
        // Should see drag feedback
        $this->assertElementHasClass('[data-deal-id="' . $dealId . '"]', 'dragging');

        // Drag to screening stage
        $screeningStage = $this->driver->findElement(
            WebDriverBy::cssSelector('[data-stage="screening"]')
        );
        
        $this->simulateTouchMove($dealCard, $screeningStage);
        $this->simulateTouchEvent($screeningStage, 'touchend');

        // Verify move completed
        $this->wait->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('[data-stage="screening"] [data-deal-id="' . $dealId . '"]')
            )
        );
        
        $this->assertDatabaseHas('deals', [
            'id' => $dealId,
            'pipeline_stage_c' => 'screening'
        ]);
    }

    /**
     * @test
     * @group integration
     * @group validation
     * @group stage-transitions
     */
    public function testStageTransitionValidation(): void
    {
        $this->driver->get($this->baseUrl . '/index.php?module=Deals&action=pipeline');
        $this->waitForPipelineLoad();

        // Try to skip stages (invalid transition)
        $sourcingStage = $this->driver->findElement(
            WebDriverBy::cssSelector('[data-stage="sourcing"]')
        );
        $dealCard = $sourcingStage->findElement(
            WebDriverBy::className('deal-card')
        );
        $dealId = $dealCard->getAttribute('data-deal-id');

        // Try to drag directly to due_diligence (skipping screening and analysis)
        $dueDiligenceStage = $this->driver->findElement(
            WebDriverBy::cssSelector('[data-stage="due_diligence"]')
        );

        $actions = new WebDriverActions($this->driver);
        $actions->dragAndDrop($dealCard, $dueDiligenceStage)->perform();

        // Should see validation error
        $this->wait->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::className('transition-error')
            )
        );

        $errorMessage = $this->driver->findElement(
            WebDriverBy::className('transition-error')
        )->getText();
        
        $this->assertStringContains('Invalid stage transition', $errorMessage);

        // Deal should remain in original stage
        $this->assertElementExists('[data-stage="sourcing"] [data-deal-id="' . $dealId . '"]');
        
        $this->assertDatabaseHas('deals', [
            'id' => $dealId,
            'pipeline_stage_c' => 'sourcing'
        ]);
    }

    /**
     * @test
     * @group integration
     * @group time-tracking
     */
    public function testTimeTrackingAccuracy(): void
    {
        $dealId = $this->createTestDeal([
            'pipeline_stage_c' => 'sourcing',
            'stage_entry_time' => date('Y-m-d H:i:s', strtotime('-2 days'))
        ]);

        $this->driver->get($this->baseUrl . '/index.php?module=Deals&action=pipeline');
        $this->waitForPipelineLoad();

        // Verify time indicator shows correct duration
        $dealCard = $this->driver->findElement(
            WebDriverBy::cssSelector('[data-deal-id="' . $dealId . '"]')
        );
        
        $timeIndicator = $dealCard->findElement(
            WebDriverBy::className('time-in-stage')
        );
        
        $timeText = $timeIndicator->getText();
        $this->assertStringContains('2 days', $timeText);

        // Move deal to next stage
        $screeningStage = $this->driver->findElement(
            WebDriverBy::cssSelector('[data-stage="screening"]')
        );
        
        $actions = new WebDriverActions($this->driver);
        $actions->dragAndDrop($dealCard, $screeningStage)->perform();

        $this->wait->until(
            WebDriverExpectedCondition::invisibilityOfElementLocated(
                WebDriverBy::className('pipeline-loading')
            )
        );

        // Verify time tracking was reset
        $newTimeIndicator = $this->driver->findElement(
            WebDriverBy::cssSelector('[data-stage="screening"] [data-deal-id="' . $dealId . '"] .time-in-stage')
        );
        
        $newTimeText = $newTimeIndicator->getText();
        $this->assertStringContains('0 days', $newTimeText);
        
        // Verify database tracking
        $this->assertDatabaseHas('deals_stage_history', [
            'deal_id' => $dealId,
            'from_stage' => 'sourcing',
            'to_stage' => 'screening',
            'time_in_stage_days' => 2
        ]);
    }

    /**
     * @test
     * @group integration
     * @group alerts
     * @group stale-deals
     */
    public function testStaleDealsAlerts(): void
    {
        // Create deal that's been in stage for 8 days (over 7-day threshold)
        $staleDealId = $this->createTestDeal([
            'pipeline_stage_c' => 'analysis_outreach',
            'stage_entry_time' => date('Y-m-d H:i:s', strtotime('-8 days'))
        ]);

        $this->driver->get($this->baseUrl . '/index.php?module=Deals&action=pipeline');
        $this->waitForPipelineLoad();

        // Verify stale deal has warning indicator
        $staleDealCard = $this->driver->findElement(
            WebDriverBy::cssSelector('[data-deal-id="' . $staleDealId . '"]')
        );
        
        $this->assertElementHasClass('[data-deal-id="' . $staleDealId . '"]', 'stale-deal');
        
        $alertIcon = $staleDealCard->findElement(
            WebDriverBy::className('stale-alert')
        );
        $this->assertTrue($alertIcon->isDisplayed());

        // Test alert tooltip
        $actions = new WebDriverActions($this->driver);
        $actions->moveToElement($alertIcon)->perform();
        
        $this->wait->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::className('stale-tooltip')
            )
        );
        
        $tooltip = $this->driver->findElement(
            WebDriverBy::className('stale-tooltip')
        );
        $this->assertStringContains('8 days in stage', $tooltip->getText());
    }

    /**
     * @test
     * @group integration
     * @group performance
     */
    public function testLargeDatasetPerformance(): void
    {
        // Create 500 test deals across stages
        $this->createLargeTestDataset(500);
        
        $startTime = microtime(true);
        
        $this->driver->get($this->baseUrl . '/index.php?module=Deals&action=pipeline');
        $this->waitForPipelineLoad();
        
        $loadTime = microtime(true) - $startTime;
        
        // Pipeline should load within 3 seconds even with 500 deals
        $this->assertLessThan(3.0, $loadTime, 'Pipeline load time exceeded 3 seconds with 500 deals');

        // Test drag performance
        $sourcingStage = $this->driver->findElement(
            WebDriverBy::cssSelector('[data-stage="sourcing"]')
        );
        $dealCard = $sourcingStage->findElement(
            WebDriverBy::className('deal-card')
        );
        
        $screeningStage = $this->driver->findElement(
            WebDriverBy::cssSelector('[data-stage="screening"]')
        );

        $dragStartTime = microtime(true);
        
        $actions = new WebDriverActions($this->driver);
        $actions->dragAndDrop($dealCard, $screeningStage)->perform();
        
        $this->wait->until(
            WebDriverExpectedCondition::invisibilityOfElementLocated(
                WebDriverBy::className('pipeline-loading')
            )
        );
        
        $dragTime = microtime(true) - $dragStartTime;
        
        // Drag operation should complete within 1 second
        $this->assertLessThan(1.0, $dragTime, 'Drag operation took longer than 1 second');
    }

    /**
     * Helper Methods
     */
    
    protected function createTestDeals(): void
    {
        $dealData = [
            ['name' => 'Test Deal 1', 'pipeline_stage_c' => 'sourcing', 'amount' => 100000],
            ['name' => 'Test Deal 2', 'pipeline_stage_c' => 'sourcing', 'amount' => 250000],
            ['name' => 'Test Deal 3', 'pipeline_stage_c' => 'screening', 'amount' => 150000],
            ['name' => 'Test Deal 4', 'pipeline_stage_c' => 'analysis_outreach', 'amount' => 300000],
        ];
        
        foreach ($dealData as $data) {
            $this->createTestDeal($data);
        }
    }

    protected function createTestDeal(array $data): string
    {
        $defaults = [
            'id' => $this->generateUuid(),
            'name' => 'Test Deal',
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
        $stages = array_keys($this->stages);
        $dealsPerStage = intval($count / count($stages));
        
        foreach ($stages as $stage) {
            for ($i = 0; $i < $dealsPerStage; $i++) {
                $this->createTestDeal([
                    'name' => "Load Test Deal {$stage}_{$i}",
                    'pipeline_stage_c' => $stage,
                    'amount' => rand(50000, 1000000)
                ]);
            }
        }
    }

    protected function loginTestUser(): void
    {
        $this->driver->get($this->baseUrl . '/index.php');
        
        $usernameField = $this->driver->findElement(WebDriverBy::name('user_name'));
        $passwordField = $this->driver->findElement(WebDriverBy::name('user_password'));
        $loginButton = $this->driver->findElement(WebDriverBy::name('Login'));
        
        $usernameField->sendKeys('admin');
        $passwordField->sendKeys('admin');
        $loginButton->click();
        
        // Wait for login to complete
        $this->wait->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::id('moduleTab')
            )
        );
    }

    protected function waitForPipelineLoad(): void
    {
        $this->wait->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::className('pipeline-stage')
            )
        );
        
        // Wait for initial data load
        $this->wait->until(
            WebDriverExpectedCondition::invisibilityOfElementLocated(
                WebDriverBy::className('pipeline-loading')
            )
        );
    }

    protected function assertElementExists(string $selector): void
    {
        try {
            $element = $this->driver->findElement(WebDriverBy::cssSelector($selector));
            $this->assertTrue($element->isDisplayed());
        } catch (\Exception $e) {
            $this->fail("Element '$selector' not found: " . $e->getMessage());
        }
    }

    protected function assertElementHasClass(string $selector, string $className): void
    {
        $element = $this->driver->findElement(WebDriverBy::cssSelector($selector));
        $classes = $element->getAttribute('class');
        $this->assertStringContains($className, $classes);
    }

    protected function simulateTouchEvent($element, string $eventType): void
    {
        $this->driver->executeScript("
            var element = arguments[0];
            var event = new TouchEvent('$eventType', {
                bubbles: true,
                cancelable: true,
                touches: [{
                    clientX: element.getBoundingClientRect().left + element.offsetWidth/2,
                    clientY: element.getBoundingClientRect().top + element.offsetHeight/2
                }]
            });
            element.dispatchEvent(event);
        ", [$element]);
    }

    protected function simulateTouchMove($fromElement, $toElement): void
    {
        $this->driver->executeScript("
            var from = arguments[0];
            var to = arguments[1];
            var event = new TouchEvent('touchmove', {
                bubbles: true,
                cancelable: true,
                touches: [{
                    clientX: to.getBoundingClientRect().left + to.offsetWidth/2,
                    clientY: to.getBoundingClientRect().top + to.offsetHeight/2
                }]
            });
            document.dispatchEvent(event);
        ", [$fromElement, $toElement]);
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