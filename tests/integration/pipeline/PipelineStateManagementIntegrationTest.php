<?php

namespace Tests\Integration\Pipeline;

use Tests\DatabaseTestCase;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverWait;

/**
 * Integration tests for Pipeline State Management
 * Tests complex state scenarios, error recovery, and data consistency
 */
class PipelineStateManagementIntegrationTest extends DatabaseTestCase
{
    protected RemoteWebDriver $driver;
    protected WebDriverWait $wait;
    protected string $baseUrl = 'http://localhost:8080';
    
    protected array $complexStateScenarios = [
        'concurrent_moves',
        'batch_operations',
        'error_recovery',
        'offline_sync',
        'state_conflicts'
    ];

    protected function setUp(): void
    {
        parent::setUp();
        
        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability('chromeOptions', [
            'args' => ['--headless', '--no-sandbox', '--disable-dev-shm-usage']
        ]);
        
        $this->driver = RemoteWebDriver::create('http://localhost:4444/wd/hub', $capabilities);
        $this->wait = new WebDriverWait($this->driver, 15);
        
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
     * @group state-management
     * @group concurrent-operations
     */
    public function testConcurrentDealMovement(): void
    {
        $this->driver->get($this->baseUrl . '/index.php?module=Deals&action=pipeline');
        $this->waitForPipelineLoad();

        // Create multiple deals for concurrent testing
        $dealIds = [];
        for ($i = 0; $i < 5; $i++) {
            $dealIds[] = $this->createTestDeal([
                'name' => "Concurrent Test Deal $i",
                'pipeline_stage_c' => 'sourcing'
            ]);
        }

        // Simulate concurrent moves using JavaScript
        $this->driver->executeScript("
            window.concurrentResults = [];
            window.completedMoves = 0;
            
            const dealIds = " . json_encode($dealIds) . ";
            const targetStages = ['screening', 'analysis_outreach', 'due_diligence'];
            
            // Function to move deal and track results
            function moveDealConcurrently(dealId, targetStage, index) {
                return new Promise((resolve) => {
                    jQuery.ajax({
                        url: PipelineView.config.updateUrl,
                        type: 'POST',
                        data: {
                            deal_id: dealId,
                            new_stage: targetStage,
                            old_stage: 'sourcing'
                        },
                        success: function(response) {
                            window.concurrentResults[index] = {
                                dealId: dealId,
                                targetStage: targetStage,
                                success: response.success,
                                response: response
                            };
                            window.completedMoves++;
                            resolve();
                        },
                        error: function(xhr) {
                            window.concurrentResults[index] = {
                                dealId: dealId,
                                targetStage: targetStage,
                                success: false,
                                error: xhr.responseText
                            };
                            window.completedMoves++;
                            resolve();
                        }
                    });
                });
            }
            
            // Start all moves simultaneously
            const promises = dealIds.map((dealId, index) => {
                const targetStage = targetStages[index % targetStages.length];
                return moveDealConcurrently(dealId, targetStage, index);
            });
            
            Promise.all(promises).then(() => {
                window.allMovesComplete = true;
            });
        ");

        // Wait for all concurrent operations to complete
        $this->wait->until(function() {
            return $this->driver->executeScript('return window.allMovesComplete === true;');
        });

        // Verify results
        $results = $this->driver->executeScript('return window.concurrentResults;');
        $this->assertCount(5, $results, 'All concurrent operations should complete');

        $successCount = 0;
        foreach ($results as $result) {
            if ($result['success']) {
                $successCount++;
                
                // Verify database consistency
                $this->assertDatabaseHas('deals', [
                    'id' => $result['dealId'],
                    'pipeline_stage_c' => $result['targetStage']
                ]);
            }
        }

        $this->assertGreaterThan(0, $successCount, 'At least some concurrent operations should succeed');
        
        // Check for race condition handling
        $failedMoves = array_filter($results, fn($r) => !$r['success']);
        if (count($failedMoves) > 0) {
            // Failed moves should have appropriate error messages
            foreach ($failedMoves as $failed) {
                $this->assertNotEmpty($failed['error'], 'Failed moves should have error details');
            }
        }
    }

    /**
     * @test
     * @group integration
     * @group state-management
     * @group batch-operations
     */
    public function testBatchOperationConsistency(): void
    {
        $this->driver->get($this->baseUrl . '/index.php?module=Deals&action=pipeline');
        $this->waitForPipelineLoad();

        // Create deals for batch operation
        $batchDealIds = [];
        for ($i = 0; $i < 10; $i++) {
            $batchDealIds[] = $this->createTestDeal([
                'name' => "Batch Test Deal $i",
                'pipeline_stage_c' => 'sourcing'
            ]);
        }

        // Perform batch move operation
        $this->driver->executeScript("
            window.batchResult = null;
            
            jQuery.ajax({
                url: '/api/pipeline/bulk-move',
                type: 'POST',
                data: JSON.stringify({
                    deal_ids: " . json_encode($batchDealIds) . ",
                    from_stage: 'sourcing',
                    to_stage: 'screening'
                }),
                contentType: 'application/json',
                success: function(response) {
                    window.batchResult = response;
                    window.batchComplete = true;
                },
                error: function(xhr) {
                    window.batchResult = {
                        success: false,
                        error: xhr.responseText
                    };
                    window.batchComplete = true;
                }
            });
        ");

        // Wait for batch operation to complete
        $this->wait->until(function() {
            return $this->driver->executeScript('return window.batchComplete === true;');
        });

        $batchResult = $this->driver->executeScript('return window.batchResult;');
        $this->assertNotNull($batchResult, 'Batch operation should return result');

        if ($batchResult['success']) {
            // All deals should be moved successfully
            foreach ($batchDealIds as $dealId) {
                $this->assertDatabaseHas('deals', [
                    'id' => $dealId,
                    'pipeline_stage_c' => 'screening'
                ]);
            }
            
            // Verify batch result structure
            $this->assertArrayHasKey('moved_deals', $batchResult);
            $this->assertArrayHasKey('failed_deals', $batchResult);
            $this->assertCount(10, $batchResult['moved_deals']);
            $this->assertCount(0, $batchResult['failed_deals']);
        } else {
            // If batch failed, no deals should be moved (transaction rollback)
            foreach ($batchDealIds as $dealId) {
                $this->assertDatabaseHas('deals', [
                    'id' => $dealId,
                    'pipeline_stage_c' => 'sourcing'
                ]);
            }
        }
    }

    /**
     * @test
     * @group integration
     * @group state-management
     * @group error-recovery
     */
    public function testErrorRecoveryAndRollback(): void
    {
        $this->driver->get($this->baseUrl . '/index.php?module=Deals&action=pipeline');
        $this->waitForPipelineLoad();

        $dealId = $this->createTestDeal([
            'pipeline_stage_c' => 'sourcing',
            'amount' => 100000
        ]);

        // Store initial state
        $initialDeal = $this->getDatabaseRecord('deals', ['id' => $dealId]);
        
        // Simulate network error during move
        $this->driver->executeScript("
            window.errorRecoveryTest = {
                originalAjax: jQuery.ajax,
                results: []
            };
            
            // Override AJAX to simulate failure
            jQuery.ajax = function(options) {
                if (options.url === PipelineView.config.updateUrl) {
                    // Simulate network error
                    setTimeout(() => {
                        if (options.error) {
                            options.error({
                                status: 500,
                                statusText: 'Internal Server Error',
                                responseText: 'Database connection failed'
                            });
                        }
                    }, 100);
                    
                    return {
                        done: function() { return this; },
                        fail: function() { return this; },
                        always: function() { return this; }
                    };
                } else {
                    return window.errorRecoveryTest.originalAjax.apply(this, arguments);
                }
            };
        ");

        // Attempt to move deal (should fail)
        $dealCard = $this->driver->findElement(
            WebDriverBy::cssSelector('[data-deal-id="' . $dealId . '"]')
        );
        $screeningStage = $this->driver->findElement(
            WebDriverBy::cssSelector('[data-stage="screening"]')
        );

        // Perform drag and drop
        $this->driver->executeScript("
            var dragEvent = new DragEvent('dragstart', { dataTransfer: new DataTransfer() });
            var dropEvent = new DragEvent('drop', { dataTransfer: new DataTransfer() });
            
            arguments[0].dispatchEvent(dragEvent);
            arguments[1].dispatchEvent(dropEvent);
        ", [$dealCard, $screeningStage]);

        // Wait for error handling
        $this->wait->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('.error-message, .alert-danger')
            )
        );

        // Verify deal was reverted to original position
        $this->wait->until(function() use ($dealId) {
            $element = $this->driver->findElement(
                WebDriverBy::cssSelector('[data-deal-id="' . $dealId . '"]')
            );
            return $element->getAttribute('data-stage') === 'sourcing';
        });

        // Verify database was not changed
        $currentDeal = $this->getDatabaseRecord('deals', ['id' => $dealId]);
        $this->assertEquals($initialDeal['pipeline_stage_c'], $currentDeal['pipeline_stage_c']);
        
        // Verify error message is displayed
        $errorElement = $this->driver->findElement(
            WebDriverBy::cssSelector('.error-message, .alert-danger')
        );
        $this->assertStringContainsString('error', strtolower($errorElement->getText()));

        // Restore normal AJAX functionality
        $this->driver->executeScript("
            jQuery.ajax = window.errorRecoveryTest.originalAjax;
        ");
    }

    /**
     * @test
     * @group integration
     * @group state-management
     * @group optimistic-updates
     */
    public function testOptimisticUpdatesAndConfirmation(): void
    {
        $this->driver->get($this->baseUrl . '/index.php?module=Deals&action=pipeline');
        $this->waitForPipelineLoad();

        $dealId = $this->createTestDeal([
            'pipeline_stage_c' => 'sourcing'
        ]);

        // Monitor state changes during optimistic update
        $this->driver->executeScript("
            window.stateChanges = [];
            
            // Monitor DOM changes
            const dealCard = document.querySelector('[data-deal-id=\"$dealId\"]');
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'data-stage') {
                        window.stateChanges.push({
                            timestamp: Date.now(),
                            type: 'dom_change',
                            oldValue: mutation.oldValue,
                            newValue: mutation.target.getAttribute('data-stage')
                        });
                    }
                });
            });
            
            observer.observe(dealCard, { 
                attributes: true, 
                attributeOldValue: true,
                attributeFilter: ['data-stage'] 
            });
            
            // Monitor AJAX calls
            const originalAjax = jQuery.ajax;
            jQuery.ajax = function(options) {
                if (options.url === PipelineView.config.updateUrl) {
                    window.stateChanges.push({
                        timestamp: Date.now(),
                        type: 'ajax_start',
                        data: options.data
                    });
                    
                    const originalSuccess = options.success;
                    const originalError = options.error;
                    
                    options.success = function(response) {
                        window.stateChanges.push({
                            timestamp: Date.now(),
                            type: 'ajax_success',
                            response: response
                        });
                        if (originalSuccess) originalSuccess(response);
                    };
                    
                    options.error = function(xhr) {
                        window.stateChanges.push({
                            timestamp: Date.now(),
                            type: 'ajax_error',
                            error: xhr.responseText
                        });
                        if (originalError) originalError(xhr);
                    };
                }
                
                return originalAjax.apply(this, arguments);
            };
        ");

        // Perform drag and drop operation
        $dealCard = $this->driver->findElement(
            WebDriverBy::cssSelector('[data-deal-id="' . $dealId . '"]')
        );
        $screeningStage = $this->driver->findElement(
            WebDriverBy::cssSelector('[data-stage="screening"]')
        );

        $this->dragAndDrop($dealCard, $screeningStage);

        // Wait for operation to complete
        $this->wait->until(
            WebDriverExpectedCondition::invisibilityOfElementLocated(
                WebDriverBy::className('pipeline-loading')
            )
        );

        // Analyze state changes
        $stateChanges = $this->driver->executeScript('return window.stateChanges;');
        
        $this->assertNotEmpty($stateChanges, 'State changes should be recorded');
        
        // Verify optimistic update sequence
        $domChangeFound = false;
        $ajaxStartFound = false;
        $ajaxSuccessFound = false;
        
        foreach ($stateChanges as $change) {
            switch ($change['type']) {
                case 'dom_change':
                    $domChangeFound = true;
                    $this->assertEquals('screening', $change['newValue']);
                    break;
                case 'ajax_start':
                    $ajaxStartFound = true;
                    break;
                case 'ajax_success':
                    $ajaxSuccessFound = true;
                    $this->assertTrue($change['response']['success']);
                    break;
            }
        }
        
        $this->assertTrue($domChangeFound, 'DOM should be updated optimistically');
        $this->assertTrue($ajaxStartFound, 'AJAX request should be initiated');
        $this->assertTrue($ajaxSuccessFound, 'AJAX request should complete successfully');
        
        // Verify final state consistency
        $finalDeal = $this->getDatabaseRecord('deals', ['id' => $dealId]);
        $this->assertEquals('screening', $finalDeal['pipeline_stage_c']);
    }

    /**
     * @test
     * @group integration
     * @group state-management
     * @group wip-limits
     */
    public function testWIPLimitStateManagement(): void
    {
        $this->driver->get($this->baseUrl . '/index.php?module=Deals&action=pipeline');
        $this->waitForPipelineLoad();

        // Fill screening stage to near capacity (limit is 15)
        $existingDeals = [];
        for ($i = 0; $i < 14; $i++) {
            $existingDeals[] = $this->createTestDeal([
                'name' => "WIP Limit Test Deal $i",
                'pipeline_stage_c' => 'screening'
            ]);
        }

        // Create deals to test WIP limit
        $testDeals = [];
        for ($i = 0; $i < 3; $i++) {
            $testDeals[] = $this->createTestDeal([
                'name' => "WIP Test Moving Deal $i",
                'pipeline_stage_c' => 'sourcing'
            ]);
        }

        // Refresh to show current state
        $this->driver->navigate()->refresh();
        $this->waitForPipelineLoad();

        // Try to move deals to screening stage
        foreach ($testDeals as $index => $dealId) {
            $dealCard = $this->driver->findElement(
                WebDriverBy::cssSelector('[data-deal-id="' . $dealId . '"]')
            );
            $screeningStage = $this->driver->findElement(
                WebDriverBy::cssSelector('[data-stage="screening"]')
            );

            // Attempt to drag and drop
            $this->dragAndDrop($dealCard, $screeningStage);

            // Wait for response
            sleep(1);

            if ($index === 0) {
                // First deal should succeed (14 + 1 = 15, at limit)
                $this->wait->until(function() use ($dealId) {
                    $card = $this->driver->findElement(
                        WebDriverBy::cssSelector('[data-deal-id="' . $dealId . '"]')
                    );
                    return $card->getAttribute('data-stage') === 'screening';
                });
                
                $this->assertDatabaseHas('deals', [
                    'id' => $dealId,
                    'pipeline_stage_c' => 'screening'
                ]);
            } else {
                // Subsequent deals should fail due to WIP limit
                $this->wait->until(
                    WebDriverExpectedCondition::presenceOfElementLocated(
                        WebDriverBy::className('wip-limit-warning')
                    )
                );
                
                // Deal should remain in source stage
                $this->assertDatabaseHas('deals', [
                    'id' => $dealId,
                    'pipeline_stage_c' => 'sourcing'
                ]);
            }
        }

        // Verify WIP limit indicator is shown
        $screeningStage = $this->driver->findElement(
            WebDriverBy::cssSelector('[data-stage="screening"]')
        );
        $wipIndicator = $screeningStage->findElement(
            WebDriverBy::className('wip-limit-indicator')
        );
        
        $this->assertTrue($wipIndicator->isDisplayed());
        $this->assertStringContainsString('over-limit', $wipIndicator->getAttribute('class'));
    }

    /**
     * @test
     * @group integration
     * @group state-management
     * @group focus-order
     */
    public function testFocusOrderStateManagement(): void
    {
        $this->driver->get($this->baseUrl . '/index.php?module=Deals&action=pipeline');
        $this->waitForPipelineLoad();

        // Create deals and set focus flags
        $focusedDeals = [];
        for ($i = 0; $i < 5; $i++) {
            $dealId = $this->createTestDeal([
                'name' => "Focus Test Deal $i",
                'pipeline_stage_c' => 'sourcing'
            ]);
            
            // Set focus flag via JavaScript
            $this->driver->executeScript("
                PipelineView.toggleFocus('$dealId', true);
            ");
            
            $focusedDeals[] = $dealId;
            
            // Wait for focus operation to complete
            $this->wait->until(function() use ($dealId) {
                $card = $this->driver->findElement(
                    WebDriverBy::cssSelector('[data-deal-id="' . $dealId . '"]')
                );
                return $card->getAttribute('data-focused') === 'true';
            });
        }

        // Verify focus order is maintained
        $sourcingStage = $this->driver->findElement(
            WebDriverBy::cssSelector('[data-stage="sourcing"]')
        );
        $dealCards = $sourcingStage->findElements(
            WebDriverBy::className('focused-deal')
        );

        $this->assertCount(5, $dealCards, 'All focused deals should be visible');

        // Verify order by checking focus_order attributes
        $focusOrders = [];
        foreach ($dealCards as $card) {
            $focusOrders[] = intval($card->getAttribute('data-focus-order'));
        }

        $sortedOrders = $focusOrders;
        sort($sortedOrders);
        $this->assertEquals($sortedOrders, $focusOrders, 'Focused deals should be in correct order');

        // Move a focused deal and verify order is maintained
        $firstFocusedCard = $dealCards[0];
        $screeningStage = $this->driver->findElement(
            WebDriverBy::cssSelector('[data-stage="screening"]')
        );

        $this->dragAndDrop($firstFocusedCard, $screeningStage);

        // Wait for move to complete
        $this->wait->until(
            WebDriverExpectedCondition::invisibilityOfElementLocated(
                WebDriverBy::className('pipeline-loading')
            )
        );

        // Verify focused deal maintains its focus state in new stage
        $movedCard = $this->driver->findElement(
            WebDriverBy::cssSelector('[data-stage="screening"] .focused-deal')
        );
        $this->assertTrue($movedCard->isDisplayed());
        $this->assertGreaterThan(0, intval($movedCard->getAttribute('data-focus-order')));
    }

    /**
     * Helper Methods
     */
    
    protected function createTestDeals(): void
    {
        $stages = ['sourcing', 'screening', 'analysis_outreach'];
        
        for ($i = 0; $i < 20; $i++) {
            $this->createTestDeal([
                'name' => "State Management Test Deal $i",
                'pipeline_stage_c' => $stages[$i % count($stages)],
                'amount' => rand(50000, 500000),
                'assigned_user_id' => 'test-user-1'
            ]);
        }
    }

    protected function createTestDeal(array $data = []): string
    {
        $defaults = [
            'id' => $this->generateUuid(),
            'name' => 'State Test Deal',
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

    protected function dragAndDrop($sourceElement, $targetElement): void
    {
        $this->driver->executeScript("
            var dragEvent = new DragEvent('dragstart', { 
                dataTransfer: new DataTransfer(),
                bubbles: true 
            });
            var dropEvent = new DragEvent('drop', { 
                dataTransfer: new DataTransfer(),
                bubbles: true 
            });
            
            arguments[0].dispatchEvent(dragEvent);
            arguments[1].dispatchEvent(dropEvent);
        ", [$sourceElement, $targetElement]);
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