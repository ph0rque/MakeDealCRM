<?php

namespace Tests\Integration\Pipeline;

use Tests\DatabaseTestCase;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverWait;
use Facebook\WebDriver\WebDriverDimension;

/**
 * Integration tests for Pipeline Responsive Design
 * Tests adaptive layouts and functionality across different devices
 */
class PipelineResponsiveIntegrationTest extends DatabaseTestCase
{
    protected RemoteWebDriver $driver;
    protected WebDriverWait $wait;
    protected string $baseUrl = 'http://localhost:8080';
    
    protected array $viewports = [
        'desktop_large' => ['width' => 1920, 'height' => 1080],
        'desktop_medium' => ['width' => 1366, 'height' => 768],
        'tablet_landscape' => ['width' => 1024, 'height' => 768],
        'tablet_portrait' => ['width' => 768, 'height' => 1024],
        'mobile_large' => ['width' => 414, 'height' => 896],
        'mobile_medium' => ['width' => 375, 'height' => 667],
        'mobile_small' => ['width' => 320, 'height' => 568]
    ];

    protected function setUp(): void
    {
        parent::setUp();
        
        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability('chromeOptions', [
            'args' => ['--headless', '--no-sandbox', '--disable-dev-shm-usage']
        ]);
        
        $this->driver = RemoteWebDriver::create('http://localhost:4444/wd/hub', $capabilities);
        $this->wait = new WebDriverWait($this->driver, 10);
        
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
     * @group responsive
     * @group desktop
     */
    public function testDesktopLayout(): void
    {
        // Test large desktop
        $this->setViewport('desktop_large');
        $this->driver->get($this->baseUrl . '/index.php?module=Deals&action=pipeline');
        $this->waitForPipelineLoad();

        // All stages should be visible horizontally
        $stages = $this->driver->findElements(WebDriverBy::className('pipeline-stage'));
        $this->assertGreaterThanOrEqual(11, count($stages));

        // Verify horizontal scrolling is not needed
        $pipelineContainer = $this->driver->findElement(WebDriverBy::className('pipeline-container'));
        $this->assertFalse($this->hasHorizontalScroll($pipelineContainer));

        // Deal cards should be full size
        $dealCard = $this->driver->findElement(WebDriverBy::className('deal-card'));
        $cardHeight = $dealCard->getSize()->getHeight();
        $this->assertGreaterThan(120, $cardHeight, 'Deal cards should be full size on desktop');

        // Test medium desktop
        $this->setViewport('desktop_medium');
        $this->driver->navigate()->refresh();
        $this->waitForPipelineLoad();

        // Should still show all stages but may be more compact
        $stages = $this->driver->findElements(WebDriverBy::className('pipeline-stage'));
        $this->assertGreaterThanOrEqual(11, count($stages));
    }

    /**
     * @test
     * @group integration
     * @group responsive
     * @group tablet
     */
    public function testTabletLayout(): void
    {
        // Test tablet landscape
        $this->setViewport('tablet_landscape');
        $this->driver->get($this->baseUrl . '/index.php?module=Deals&action=pipeline');
        $this->waitForPipelineLoad();

        // Should have horizontal scrolling for stages
        $pipelineContainer = $this->driver->findElement(WebDriverBy::className('pipeline-container'));
        $this->assertTrue($this->hasHorizontalScroll($pipelineContainer));

        // Stage width should be optimized for tablet
        $stage = $this->driver->findElement(WebDriverBy::className('pipeline-stage'));
        $stageWidth = $stage->getSize()->getWidth();
        $this->assertGreaterThan(200, $stageWidth);
        $this->assertLessThan(350, $stageWidth);

        // Test tablet portrait
        $this->setViewport('tablet_portrait');
        $this->driver->navigate()->refresh();
        $this->waitForPipelineLoad();

        // Should maintain horizontal scroll but with narrower stages
        $this->assertTrue($this->hasHorizontalScroll($pipelineContainer));
        
        $stage = $this->driver->findElement(WebDriverBy::className('pipeline-stage'));
        $portraitStageWidth = $stage->getSize()->getWidth();
        $this->assertLessThan($stageWidth, $portraitStageWidth);
    }

    /**
     * @test
     * @group integration
     * @group responsive
     * @group mobile
     */
    public function testMobileLayout(): void
    {
        $this->setViewport('mobile_large');
        $this->driver->get($this->baseUrl . '/index.php?module=Deals&action=pipeline');
        $this->waitForPipelineLoad();

        // Mobile should use horizontal scrolling with touch-optimized stages
        $pipelineContainer = $this->driver->findElement(WebDriverBy::className('pipeline-container'));
        $this->assertTrue($this->hasHorizontalScroll($pipelineContainer));

        // Check for mobile-specific elements
        $this->assertElementExists('.mobile-pipeline-controls');
        $this->assertElementExists('.stage-navigator');

        // Deal cards should be compact
        $dealCard = $this->driver->findElement(WebDriverBy::className('deal-card'));
        $cardHeight = $dealCard->getSize()->getHeight();
        $this->assertLessThan(100, $cardHeight, 'Deal cards should be compact on mobile');

        // Touch targets should be larger
        $dragHandle = $dealCard->findElement(WebDriverBy::className('drag-handle'));
        $handleSize = $dragHandle->getSize();
        $this->assertGreaterThan(44, $handleSize->getHeight(), 'Touch targets should be at least 44px');
        $this->assertGreaterThan(44, $handleSize->getWidth(), 'Touch targets should be at least 44px');
    }

    /**
     * @test
     * @group integration
     * @group responsive
     * @group mobile
     * @group touch
     */
    public function testMobileTouchInteractions(): void
    {
        $this->setViewport('mobile_medium');
        $this->driver->get($this->baseUrl . '/index.php?module=Deals&action=pipeline');
        $this->waitForPipelineLoad();

        // Test swipe navigation between stages
        $currentStage = $this->driver->findElement(WebDriverBy::className('pipeline-stage'));
        $initialTransform = $this->getTransformX($currentStage);

        // Simulate swipe left
        $this->simulateSwipe('left', $currentStage);
        
        $newTransform = $this->getTransformX($currentStage);
        $this->assertNotEquals($initialTransform, $newTransform, 'Swipe should change stage position');

        // Test pull-to-refresh
        $this->simulatePullToRefresh();
        
        $this->wait->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::className('refresh-indicator')
            )
        );

        // Wait for refresh to complete
        $this->wait->until(
            WebDriverExpectedCondition::invisibilityOfElementLocated(
                WebDriverBy::className('refresh-indicator')
            )
        );
    }

    /**
     * @test
     * @group integration
     * @group responsive
     * @group compact-view
     */
    public function testCompactViewToggle(): void
    {
        $this->setViewport('desktop_medium');
        $this->driver->get($this->baseUrl . '/index.php?module=Deals&action=pipeline');
        $this->waitForPipelineLoad();

        // Test compact view toggle
        $compactToggle = $this->driver->findElement(WebDriverBy::id('compact-view-toggle'));
        $compactToggle->click();

        // Wait for view to change
        $this->wait->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::className('pipeline-compact')
            )
        );

        // Deal cards should be smaller in compact view
        $dealCard = $this->driver->findElement(WebDriverBy::className('deal-card'));
        $compactHeight = $dealCard->getSize()->getHeight();
        
        // Toggle back to normal view
        $compactToggle->click();
        
        $this->wait->until(
            WebDriverExpectedCondition::invisibilityOfElementLocated(
                WebDriverBy::className('pipeline-compact')
            )
        );

        $normalHeight = $dealCard->getSize()->getHeight();
        $this->assertGreaterThan($compactHeight, $normalHeight, 'Normal view should have larger cards');

        // Preference should be saved
        $this->driver->navigate()->refresh();
        $this->waitForPipelineLoad();
        
        // Should not be in compact mode after refresh
        $this->assertElementNotExists('.pipeline-compact');
    }

    /**
     * @test
     * @group integration
     * @group responsive
     * @group keyboard
     * @group accessibility
     */
    public function testKeyboardNavigation(): void
    {
        $this->setViewport('desktop_large');
        $this->driver->get($this->baseUrl . '/index.php?module=Deals&action=pipeline');
        $this->waitForPipelineLoad();

        // Focus first deal card
        $firstDealCard = $this->driver->findElement(WebDriverBy::className('deal-card'));
        $firstDealCard->click();

        // Test Tab navigation
        $this->driver->getKeyboard()->sendKeys(\Facebook\WebDriver\WebDriverKeys::TAB);
        
        $focusedElement = $this->driver->switchTo()->activeElement();
        $this->assertElementHasClass($focusedElement, 'deal-card');

        // Test Arrow key navigation
        $this->driver->getKeyboard()->sendKeys(\Facebook\WebDriver\WebDriverKeys::ARROW_RIGHT);
        
        $newFocusedElement = $this->driver->switchTo()->activeElement();
        $this->assertNotEquals($focusedElement, $newFocusedElement);

        // Test Enter to select
        $this->driver->getKeyboard()->sendKeys(\Facebook\WebDriver\WebDriverKeys::ENTER);
        
        $this->wait->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::className('deal-selected')
            )
        );

        // Test keyboard-initiated drag (Ctrl+M for move mode)
        $this->driver->getKeyboard()->sendKeys(
            \Facebook\WebDriver\WebDriverKeys::CONTROL . 'm'
        );
        
        $this->assertElementExists('.keyboard-move-mode');
    }

    /**
     * @test
     * @group integration
     * @group responsive
     * @group performance
     */
    public function testResponsivePerformance(): void
    {
        foreach ($this->viewports as $viewportName => $dimensions) {
            $startTime = microtime(true);
            
            $this->setViewport($viewportName);
            $this->driver->get($this->baseUrl . '/index.php?module=Deals&action=pipeline');
            $this->waitForPipelineLoad();
            
            $loadTime = microtime(true) - $startTime;
            
            // Should load within 3 seconds on any viewport
            $this->assertLessThan(
                3.0, 
                $loadTime, 
                "Pipeline load time exceeded 3 seconds on $viewportName viewport"
            );
            
            // Test viewport change performance
            $changeStartTime = microtime(true);
            
            // Simulate orientation change for mobile
            if (strpos($viewportName, 'mobile') !== false) {
                $this->simulateOrientationChange();
            }
            
            $changeTime = microtime(true) - $changeStartTime;
            
            // Viewport changes should be smooth (under 500ms)
            $this->assertLessThan(
                0.5, 
                $changeTime, 
                "Viewport change took too long on $viewportName"
            );
        }
    }

    /**
     * @test
     * @group integration
     * @group responsive
     * @group cross-browser
     */
    public function testCrossBrowserConsistency(): void
    {
        $browsers = ['chrome', 'firefox', 'safari'];
        $results = [];
        
        foreach ($browsers as $browser) {
            if ($this->isBrowserAvailable($browser)) {
                $this->switchToBrowser($browser);
                
                $this->setViewport('desktop_medium');
                $this->driver->get($this->baseUrl . '/index.php?module=Deals&action=pipeline');
                $this->waitForPipelineLoad();
                
                // Measure key elements
                $stage = $this->driver->findElement(WebDriverBy::className('pipeline-stage'));
                $dealCard = $this->driver->findElement(WebDriverBy::className('deal-card'));
                
                $results[$browser] = [
                    'stage_width' => $stage->getSize()->getWidth(),
                    'stage_height' => $stage->getSize()->getHeight(),
                    'card_width' => $dealCard->getSize()->getWidth(),
                    'card_height' => $dealCard->getSize()->getHeight()
                ];
            }
        }
        
        // Verify consistency (within 10% variance)
        if (count($results) > 1) {
            $this->assertCrossBrowserConsistency($results, 0.1);
        }
    }

    /**
     * Helper Methods
     */
    
    protected function setViewport(string $viewportName): void
    {
        $dimensions = $this->viewports[$viewportName];
        $this->driver->manage()->window()->setSize(
            new WebDriverDimension($dimensions['width'], $dimensions['height'])
        );
        
        // Wait for viewport change to take effect
        sleep(1);
    }

    protected function hasHorizontalScroll($element): bool
    {
        $scrollWidth = $this->driver->executeScript(
            'return arguments[0].scrollWidth', 
            [$element]
        );
        $clientWidth = $this->driver->executeScript(
            'return arguments[0].clientWidth', 
            [$element]
        );
        
        return $scrollWidth > $clientWidth;
    }

    protected function getTransformX($element): float
    {
        $transform = $element->getCSSValue('transform');
        if ($transform === 'none') {
            return 0;
        }
        
        preg_match('/translate\(([^,]+)px/', $transform, $matches);
        return isset($matches[1]) ? floatval($matches[1]) : 0;
    }

    protected function simulateSwipe(string $direction, $element): void
    {
        $startX = $direction === 'left' ? 300 : 100;
        $endX = $direction === 'left' ? 100 : 300;
        
        $this->driver->executeScript("
            var element = arguments[0];
            var startX = arguments[1];
            var endX = arguments[2];
            
            var startEvent = new TouchEvent('touchstart', {
                touches: [{clientX: startX, clientY: 200}]
            });
            var moveEvent = new TouchEvent('touchmove', {
                touches: [{clientX: endX, clientY: 200}]
            });
            var endEvent = new TouchEvent('touchend', {});
            
            element.dispatchEvent(startEvent);
            setTimeout(() => element.dispatchEvent(moveEvent), 50);
            setTimeout(() => element.dispatchEvent(endEvent), 100);
        ", [$element, $startX, $endX]);
        
        sleep(1); // Wait for animation
    }

    protected function simulatePullToRefresh(): void
    {
        $this->driver->executeScript("
            var startEvent = new TouchEvent('touchstart', {
                touches: [{clientX: 200, clientY: 100}]
            });
            var moveEvent = new TouchEvent('touchmove', {
                touches: [{clientX: 200, clientY: 200}]
            });
            var endEvent = new TouchEvent('touchend', {});
            
            document.dispatchEvent(startEvent);
            setTimeout(() => document.dispatchEvent(moveEvent), 50);
            setTimeout(() => document.dispatchEvent(endEvent), 100);
        ");
        
        sleep(1);
    }

    protected function simulateOrientationChange(): void
    {
        $currentSize = $this->driver->manage()->window()->getSize();
        
        // Swap width and height to simulate rotation
        $newSize = new WebDriverDimension(
            $currentSize->getHeight(),
            $currentSize->getWidth()
        );
        
        $this->driver->manage()->window()->setSize($newSize);
        sleep(1);
    }

    protected function isBrowserAvailable(string $browser): bool
    {
        // This would check if browser is available in test environment
        // For now, assume Chrome is always available
        return $browser === 'chrome';
    }

    protected function switchToBrowser(string $browser): void
    {
        // This would switch to different browser driver
        // Implementation depends on test setup
    }

    protected function assertCrossBrowserConsistency(array $results, float $tolerance): void
    {
        $browsers = array_keys($results);
        $baseline = $results[$browsers[0]];
        
        for ($i = 1; $i < count($browsers); $i++) {
            $comparison = $results[$browsers[$i]];
            
            foreach ($baseline as $metric => $value) {
                $difference = abs($value - $comparison[$metric]) / $value;
                $this->assertLessThan(
                    $tolerance,
                    $difference,
                    "Cross-browser inconsistency in $metric between {$browsers[0]} and {$browsers[$i]}"
                );
            }
        }
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

    protected function assertElementNotExists(string $selector): void
    {
        try {
            $this->driver->findElement(WebDriverBy::cssSelector($selector));
            $this->fail("Element '$selector' should not exist");
        } catch (\Facebook\WebDriver\Exception\NoSuchElementException $e) {
            $this->assertTrue(true); // Element correctly not found
        }
    }

    protected function assertElementHasClass($element, string $className): void
    {
        if (is_string($element)) {
            $element = $this->driver->findElement(WebDriverBy::cssSelector($element));
        }
        
        $classes = $element->getAttribute('class');
        $this->assertStringContains($className, $classes);
    }

    protected function createTestDeals(): void
    {
        $stages = ['sourcing', 'screening', 'analysis_outreach', 'due_diligence'];
        
        for ($i = 0; $i < 20; $i++) {
            $this->insertTestRecords('deals', [[
                'id' => $this->generateUuid(),
                'name' => "Responsive Test Deal $i",
                'pipeline_stage_c' => $stages[$i % count($stages)],
                'amount' => rand(50000, 500000),
                'assigned_user_id' => 'test-user-1',
                'date_entered' => date('Y-m-d H:i:s')
            ]]);
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
        
        $this->wait->until(
            WebDriverExpectedCondition::invisibilityOfElementLocated(
                WebDriverBy::className('pipeline-loading')
            )
        );
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