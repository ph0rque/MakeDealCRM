<?php

namespace Tests\Integration\Pipeline;

use Tests\DatabaseTestCase;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverWait;
use Facebook\WebDriver\WebDriverKeys;

/**
 * Integration tests for Pipeline Accessibility
 * Tests WCAG 2.1 compliance and keyboard navigation
 */
class PipelineAccessibilityIntegrationTest extends DatabaseTestCase
{
    protected RemoteWebDriver $driver;
    protected WebDriverWait $wait;
    protected string $baseUrl = 'http://localhost:8080';
    
    protected array $wcagRequirements = [
        'keyboard_navigation' => true,
        'focus_indicators' => true,
        'aria_labels' => true,
        'color_contrast' => 4.5, // WCAG AA ratio
        'screen_reader_support' => true
    ];

    protected function setUp(): void
    {
        parent::setUp();
        
        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability('chromeOptions', [
            'args' => [
                '--headless',
                '--no-sandbox', 
                '--disable-dev-shm-usage',
                '--enable-accessibility'
            ]
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
     * @group accessibility
     * @group keyboard-navigation
     */
    public function testKeyboardNavigation(): void
    {
        $this->driver->get($this->baseUrl . '/index.php?module=Deals&action=pipeline');
        $this->waitForPipelineLoad();

        // Focus should start on first interactive element
        $this->driver->getKeyboard()->sendKeys(WebDriverKeys::TAB);
        $focusedElement = $this->driver->switchTo()->activeElement();
        $this->assertTrue($focusedElement->isDisplayed());

        // Tab should move between deal cards
        $initialElement = $focusedElement;
        $this->driver->getKeyboard()->sendKeys(WebDriverKeys::TAB);
        $newFocusedElement = $this->driver->switchTo()->activeElement();
        
        $this->assertNotEquals(
            $initialElement->getAttribute('data-deal-id'), 
            $newFocusedElement->getAttribute('data-deal-id'),
            'Tab key should move focus between elements'
        );

        // Arrow keys should work for navigation
        $this->driver->getKeyboard()->sendKeys(WebDriverKeys::ARROW_RIGHT);
        $arrowFocusedElement = $this->driver->switchTo()->activeElement();
        
        // Should move to adjacent element or stage
        $this->assertNotEquals(
            $newFocusedElement->getAttribute('data-deal-id'),
            $arrowFocusedElement->getAttribute('data-deal-id')
        );

        // Enter key should select/activate element
        $dealCard = $this->driver->findElement(WebDriverBy::className('deal-card'));
        $dealCard->click(); // Focus the card
        
        $this->driver->getKeyboard()->sendKeys(WebDriverKeys::ENTER);
        
        // Should trigger selection or detail view
        $this->wait->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::className('deal-selected')
            )
        );
    }

    /**
     * @test
     * @group integration
     * @group accessibility
     * @group keyboard-drag-drop
     */
    public function testKeyboardDragAndDrop(): void
    {
        $this->driver->get($this->baseUrl . '/index.php?module=Deals&action=pipeline');
        $this->waitForPipelineLoad();

        // Find a deal card to move
        $dealCard = $this->driver->findElement(WebDriverBy::className('deal-card'));
        $dealId = $dealCard->getAttribute('data-deal-id');
        $originalStage = $dealCard->getAttribute('data-stage');
        
        // Focus the deal card
        $dealCard->click();
        
        // Use keyboard shortcut to enter move mode (Ctrl+M)
        $this->driver->getKeyboard()->sendKeys(
            WebDriverKeys::CONTROL . 'm'
        );
        
        // Should enter keyboard move mode
        $this->wait->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::className('keyboard-move-mode')
            )
        );
        
        // Use arrow keys to select target stage
        $this->driver->getKeyboard()->sendKeys(WebDriverKeys::ARROW_RIGHT);
        
        // Press Enter to confirm move
        $this->driver->getKeyboard()->sendKeys(WebDriverKeys::ENTER);
        
        // Wait for move to complete
        $this->wait->until(
            WebDriverExpectedCondition::invisibilityOfElementLocated(
                WebDriverBy::className('keyboard-move-mode')
            )
        );
        
        // Verify deal moved to new stage
        $movedCard = $this->driver->findElement(
            WebDriverBy::cssSelector('[data-deal-id="' . $dealId . '"]')
        );
        $newStage = $movedCard->getAttribute('data-stage');
        
        $this->assertNotEquals($originalStage, $newStage, 'Deal should have moved to new stage');
        
        // Verify database was updated
        $this->assertDatabaseHas('deals', [
            'id' => $dealId,
            'pipeline_stage_c' => $newStage
        ]);
    }

    /**
     * @test
     * @group integration
     * @group accessibility
     * @group focus-indicators
     */
    public function testFocusIndicators(): void
    {
        $this->driver->get($this->baseUrl . '/index.php?module=Deals&action=pipeline');
        $this->waitForPipelineLoad();

        // Check that all interactive elements have focus indicators
        $interactiveElements = $this->driver->findElements(
            WebDriverBy::cssSelector('button, .deal-card, input, select, a[href]')
        );
        
        foreach ($interactiveElements as $element) {
            if (!$element->isDisplayed()) continue;
            
            // Focus the element
            $element->click();
            
            // Check for focus indicator styles
            $outlineWidth = $element->getCSSValue('outline-width');
            $outlineStyle = $element->getCSSValue('outline-style');
            $boxShadow = $element->getCSSValue('box-shadow');
            
            $hasFocusIndicator = (
                ($outlineWidth !== 'none' && $outlineWidth !== '0px') ||
                ($outlineStyle !== 'none') ||
                (!empty($boxShadow) && $boxShadow !== 'none')
            );
            
            $this->assertTrue(
                $hasFocusIndicator,
                'Element should have visible focus indicator: ' . $element->getTagName()
            );
        }
    }

    /**
     * @test
     * @group integration
     * @group accessibility
     * @group aria-labels
     */
    public function testAriaLabelsAndRoles(): void
    {
        $this->driver->get($this->baseUrl . '/index.php?module=Deals&action=pipeline');
        $this->waitForPipelineLoad();

        // Check pipeline container has proper role
        $pipelineContainer = $this->driver->findElement(WebDriverBy::id('pipeline-container'));
        $containerRole = $pipelineContainer->getAttribute('role');
        $this->assertEquals('application', $containerRole, 'Pipeline should have application role');

        // Check stages have proper roles and labels
        $stages = $this->driver->findElements(WebDriverBy::className('pipeline-stage'));
        foreach ($stages as $stage) {
            $role = $stage->getAttribute('role');
            $label = $stage->getAttribute('aria-label');
            
            $this->assertEquals('region', $role, 'Stage should have region role');
            $this->assertNotEmpty($label, 'Stage should have descriptive aria-label');
            $this->assertStringContains('stage', strtolower($label));
        }

        // Check deal cards have proper roles and labels
        $dealCards = $this->driver->findElements(WebDriverBy::className('deal-card'));
        foreach ($dealCards as $dealCard) {
            $role = $dealCard->getAttribute('role');
            $label = $dealCard->getAttribute('aria-label');
            
            $this->assertContains($role, ['button', 'listitem'], 'Deal card should have appropriate role');
            $this->assertNotEmpty($label, 'Deal card should have descriptive aria-label');
        }

        // Check buttons have labels
        $buttons = $this->driver->findElements(WebDriverBy::tagName('button'));
        foreach ($buttons as $button) {
            if (!$button->isDisplayed()) continue;
            
            $ariaLabel = $button->getAttribute('aria-label');
            $text = $button->getText();
            $title = $button->getAttribute('title');
            
            $hasLabel = !empty($ariaLabel) || !empty($text) || !empty($title);
            $this->assertTrue($hasLabel, 'Button should have accessible label');
        }
    }

    /**
     * @test
     * @group integration
     * @group accessibility
     * @group color-contrast
     */
    public function testColorContrast(): void
    {
        $this->driver->get($this->baseUrl . '/index.php?module=Deals&action=pipeline');
        $this->waitForPipelineLoad();

        // Get color values for key elements
        $textElements = [
            '.deal-card .deal-name' => 'Deal name text',
            '.deal-card .deal-amount' => 'Deal amount text',
            '.stage-header h3' => 'Stage header text',
            '.deal-count' => 'Deal count text',
            'button' => 'Button text'
        ];

        foreach ($textElements as $selector => $description) {
            $elements = $this->driver->findElements(WebDriverBy::cssSelector($selector));
            
            foreach ($elements as $element) {
                if (!$element->isDisplayed()) continue;
                
                $textColor = $this->getRgbColor($element->getCSSValue('color'));
                $backgroundColor = $this->getBackgroundColor($element);
                
                if ($textColor && $backgroundColor) {
                    $contrastRatio = $this->calculateContrastRatio($textColor, $backgroundColor);
                    
                    $this->assertGreaterThanOrEqual(
                        $this->wcagRequirements['color_contrast'],
                        $contrastRatio,
                        "$description should meet WCAG AA contrast ratio (4.5:1)"
                    );
                }
            }
        }
    }

    /**
     * @test
     * @group integration
     * @group accessibility
     * @group screen-reader
     */
    public function testScreenReaderSupport(): void
    {
        $this->driver->get($this->baseUrl . '/index.php?module=Deals&action=pipeline');
        $this->waitForPipelineLoad();

        // Check for proper heading structure
        $headings = $this->driver->findElements(WebDriverBy::cssSelector('h1, h2, h3, h4, h5, h6'));
        $headingLevels = [];
        
        foreach ($headings as $heading) {
            if ($heading->isDisplayed()) {
                $level = intval(substr($heading->getTagName(), 1));
                $headingLevels[] = $level;
            }
        }
        
        // Should have logical heading hierarchy
        $this->assertNotEmpty($headingLevels, 'Page should have headings for screen readers');
        $this->assertEquals(1, min($headingLevels), 'Page should start with h1');

        // Check for live regions for dynamic updates
        $liveRegions = $this->driver->findElements(WebDriverBy::cssSelector('[aria-live]'));
        $this->assertGreaterThan(0, count($liveRegions), 'Should have live regions for dynamic updates');

        // Check for skip links
        $skipLinks = $this->driver->findElements(WebDriverBy::cssSelector('.skip-link, [href="#main-content"]'));
        if (count($skipLinks) > 0) {
            foreach ($skipLinks as $skipLink) {
                $href = $skipLink->getAttribute('href');
                $this->assertStringStartsWith('#', $href, 'Skip link should target page section');
            }
        }
    }

    /**
     * @test
     * @group integration
     * @group accessibility
     * @group alt-text
     */
    public function testImageAltText(): void
    {
        $this->driver->get($this->baseUrl . '/index.php?module=Deals&action=pipeline');
        $this->waitForPipelineLoad();

        // Check all images have alt text
        $images = $this->driver->findElements(WebDriverBy::tagName('img'));
        
        foreach ($images as $image) {
            if (!$image->isDisplayed()) continue;
            
            $alt = $image->getAttribute('alt');
            $src = $image->getAttribute('src');
            
            // Decorative images should have empty alt text
            if ($this->isDecorativeImage($src)) {
                $this->assertEquals('', $alt, 'Decorative images should have empty alt text');
            } else {
                $this->assertNotEmpty($alt, 'Informative images must have descriptive alt text');
            }
        }

        // Check icon fonts/SVGs have proper labels
        $icons = $this->driver->findElements(WebDriverBy::cssSelector('.glyphicon, .fa, svg'));
        
        foreach ($icons as $icon) {
            if (!$icon->isDisplayed()) continue;
            
            $ariaLabel = $icon->getAttribute('aria-label');
            $ariaHidden = $icon->getAttribute('aria-hidden');
            $title = $icon->getAttribute('title');
            
            // Icons should either be hidden from screen readers or have labels
            $isAccessible = ($ariaHidden === 'true') || !empty($ariaLabel) || !empty($title);
            $this->assertTrue($isAccessible, 'Icons should be accessible to screen readers');
        }
    }

    /**
     * @test
     * @group integration
     * @group accessibility
     * @group form-labels
     */
    public function testFormAccessibility(): void
    {
        $this->driver->get($this->baseUrl . '/index.php?module=Deals&action=pipeline');
        $this->waitForPipelineLoad();

        // Find all form inputs
        $inputs = $this->driver->findElements(WebDriverBy::cssSelector('input, select, textarea'));
        
        foreach ($inputs as $input) {
            if (!$input->isDisplayed()) continue;
            
            $id = $input->getAttribute('id');
            $ariaLabel = $input->getAttribute('aria-label');
            $ariaLabelledBy = $input->getAttribute('aria-labelledby');
            $title = $input->getAttribute('title');
            $placeholder = $input->getAttribute('placeholder');
            
            // Look for associated label
            $label = null;
            if (!empty($id)) {
                $labels = $this->driver->findElements(WebDriverBy::cssSelector("label[for='$id']"));
                $label = count($labels) > 0 ? $labels[0] : null;
            }
            
            $hasLabel = $label || !empty($ariaLabel) || !empty($ariaLabelledBy) || !empty($title);
            
            // Placeholder is not sufficient as a label
            if (!$hasLabel && !empty($placeholder)) {
                $this->markTestIncomplete(
                    'Input has only placeholder text - should have proper label: ' . $input->getAttribute('name')
                );
            }
            
            $this->assertTrue($hasLabel, 'Form input should have accessible label');
        }
    }

    /**
     * @test
     * @group integration
     * @group accessibility
     * @group error-handling
     */
    public function testAccessibleErrorHandling(): void
    {
        $this->driver->get($this->baseUrl . '/index.php?module=Deals&action=pipeline');
        $this->waitForPipelineLoad();

        // Trigger an error scenario (try invalid stage transition)
        $dealCard = $this->driver->findElement(WebDriverBy::className('deal-card'));
        $dealId = $dealCard->getAttribute('data-deal-id');
        
        // Try to move to invalid stage through JavaScript
        $this->driver->executeScript("
            PipelineView.draggedData = {
                dealId: '$dealId',
                sourceStage: 'sourcing'
            };
            PipelineView.moveCard(
                document.querySelector('[data-deal-id=\"$dealId\"]'),
                document.querySelector('[data-stage=\"unavailable\"]'),
                'unavailable'
            );
        ");

        // Wait for error message
        $this->wait->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('.error-message, .alert-danger, [role="alert"]')
            )
        );

        // Check error message accessibility
        $errorMessages = $this->driver->findElements(
            WebDriverBy::cssSelector('.error-message, .alert-danger, [role="alert"]')
        );
        
        foreach ($errorMessages as $errorMessage) {
            if (!$errorMessage->isDisplayed()) continue;
            
            $role = $errorMessage->getAttribute('role');
            $ariaLive = $errorMessage->getAttribute('aria-live');
            
            // Error messages should announce to screen readers
            $this->assertTrue(
                $role === 'alert' || $ariaLive === 'assertive' || $ariaLive === 'polite',
                'Error messages should announce to screen readers'
            );
            
            // Should have meaningful text
            $text = $errorMessage->getText();
            $this->assertNotEmpty($text, 'Error message should have descriptive text');
        }
    }

    /**
     * Helper Methods
     */
    
    protected function createTestDeals(): void
    {
        $stages = ['sourcing', 'screening', 'analysis_outreach'];
        
        for ($i = 0; $i < 12; $i++) {
            $this->insertTestRecords('deals', [[
                'id' => $this->generateUuid(),
                'name' => "Accessibility Test Deal $i",
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

    protected function getRgbColor(string $colorString): ?array
    {
        if (preg_match('/rgba?\((\d+),\s*(\d+),\s*(\d+)/', $colorString, $matches)) {
            return [
                'r' => intval($matches[1]),
                'g' => intval($matches[2]),
                'b' => intval($matches[3])
            ];
        }
        return null;
    }

    protected function getBackgroundColor($element): ?array
    {
        $current = $element;
        
        // Walk up the DOM tree to find background color
        while ($current) {
            $bgColor = $current->getCSSValue('background-color');
            
            if ($bgColor && $bgColor !== 'rgba(0, 0, 0, 0)' && $bgColor !== 'transparent') {
                return $this->getRgbColor($bgColor);
            }
            
            try {
                $current = $current->findElement(WebDriverBy::xpath('..'));
            } catch (\Exception $e) {
                break;
            }
        }
        
        // Default to white background
        return ['r' => 255, 'g' => 255, 'b' => 255];
    }

    protected function calculateContrastRatio(array $color1, array $color2): float
    {
        $l1 = $this->getRelativeLuminance($color1);
        $l2 = $this->getRelativeLuminance($color2);
        
        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);
        
        return ($lighter + 0.05) / ($darker + 0.05);
    }

    protected function getRelativeLuminance(array $rgb): float
    {
        $rsRGB = $rgb['r'] / 255;
        $gsRGB = $rgb['g'] / 255;
        $bsRGB = $rgb['b'] / 255;
        
        $r = $rsRGB <= 0.03928 ? $rsRGB / 12.92 : pow(($rsRGB + 0.055) / 1.055, 2.4);
        $g = $gsRGB <= 0.03928 ? $gsRGB / 12.92 : pow(($gsRGB + 0.055) / 1.055, 2.4);
        $b = $bsRGB <= 0.03928 ? $bsRGB / 12.92 : pow(($bsRGB + 0.055) / 1.055, 2.4);
        
        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    protected function isDecorativeImage(string $src): bool
    {
        $decorativePatterns = [
            '/spacer\.',
            '/blank\.',
            '/decoration/',
            '/ornament/',
            '/divider/'
        ];
        
        foreach ($decorativePatterns as $pattern) {
            if (preg_match($pattern, $src)) {
                return true;
            }
        }
        
        return false;
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