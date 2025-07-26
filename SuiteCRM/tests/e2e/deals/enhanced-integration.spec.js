/**
 * Enhanced Integration Tests
 * Tests complex workflows with comprehensive assertions for UI updates, 
 * data persistence, audit logs, and visual consistency
 */

const { test } = require('@playwright/test');
const { expect } = require('../lib/helpers/custom-matchers');
const AssertionsHelper = require('../lib/helpers/assertions.helper');
const VisualRegressionHelper = require('../lib/helpers/visual-regression.helper');
const { login } = require('./helpers/auth.helper');
const { navigateToDeals } = require('./helpers/navigation.helper');

test.describe('Enhanced Integration Tests with Comprehensive Assertions', () => {
  let assertionsHelper;
  let visualHelper;
  
  test.beforeEach(async ({ page }) => {
    await login(page);
    assertionsHelper = new AssertionsHelper(page);
    visualHelper = new VisualRegressionHelper(page);
  });

  test('Pipeline drag-drop with audit log verification', async ({ page }) => {
    // Navigate to pipeline view
    await navigateToDeals(page);
    await page.click('a[href*="pipeline"], button:has-text("Pipeline")');
    await page.waitForLoadState('networkidle');
    
    // Assert page performance
    await expect(page).toMeetPerformanceThresholds({
      loadTime: 5000,
      domContentLoaded: 3000
    });
    
    // Take initial screenshot of pipeline
    await visualHelper.assertPageScreenshot('pipeline-initial-state');
    
    // Find a deal to move
    const dealCard = page.locator('.deal-card, .pipeline-item, .kanban-item').first();
    await assertionsHelper.assertVisible(dealCard, 'Deal card should be visible in pipeline');
    
    // Get deal information for audit verification
    const dealId = await dealCard.getAttribute('data-deal-id') || 
                   await dealCard.evaluate(el => {
                     const link = el.querySelector('a[href*="record"]');
                     if (link) {
                       const url = new URL(link.href);
                       return url.searchParams.get('record');
                     }
                     return null;
                   });
    
    const originalStage = await dealCard.evaluate(el => {
      const stageContainer = el.closest('.pipeline-stage, .kanban-column');
      return stageContainer ? stageContainer.getAttribute('data-stage') : null;
    });
    
    // Find target stage (different from current)
    const targetStage = page.locator('.pipeline-stage, .kanban-column').nth(1);
    const targetStageName = await targetStage.getAttribute('data-stage');
    
    // Assert drag-drop readiness
    await assertionsHelper.assertDragDropState(dealCard, targetStage);
    
    // Take screenshot before drag-drop
    await visualHelper.assertElementScreenshot('.pipeline-container, .kanban-board', 'pipeline-before-drag');
    
    // Perform drag and drop
    await dealCard.dragTo(targetStage);
    
    // Wait for UI updates and verify
    await expect(targetStage.locator('.deal-card, .pipeline-item')).toShowUIUpdate({
      visible: true,
      count: await targetStage.locator('.deal-card, .pipeline-item').count() + 1
    });
    
    // Verify drag-drop completion
    await expect(targetStage).toHaveCompletedDragDrop({
      containsElement: `[data-deal-id="${dealId}"]`,
      hasClass: 'has-deals'
    });
    
    // Take screenshot after drag-drop
    await visualHelper.assertElementScreenshot('.pipeline-container, .kanban-board', 'pipeline-after-drag');
    
    // Verify data persistence in database
    if (dealId && targetStageName) {
      await expect(page).toHavePersistedInDatabase('opportunities', {
        id: dealId
      }, {
        expectedFields: {
          sales_stage: targetStageName
        }
      });
      
      // Verify audit log for stage change
      await expect(page).toHaveCorrectAuditLog('Deals', dealId, 'update', {
        fieldChanges: {
          sales_stage: {
            before: originalStage,
            after: targetStageName
          }
        }
      });
      
      // Verify activity timeline entry
      await assertionsHelper.assertActivityTimelineEntry('Deals', dealId, 'stage_change', {
        description: `moved from ${originalStage} to ${targetStageName}`
      });
    }
    
    // Test visual consistency across browsers
    await visualHelper.assertCrossBrowserConsistency('pipeline-after-drag', [
      { name: 'chrome', width: 1280, height: 720 },
      { name: 'mobile', width: 375, height: 667 }
    ]);
    
    console.log('✅ Pipeline drag-drop test completed with comprehensive verification');
  });

  test('Deal creation with complete workflow verification', async ({ page }) => {
    // Test data
    const testDeal = {
      name: `Integration Test Deal ${Date.now()}`,
      ttmRevenue: '8000000',
      ttmEbitda: '1600000',
      targetMultiple: '5.0',
      askingPrice: '8000000',
      status: 'sourcing'
    };
    
    const testContact = {
      firstName: 'Jane',
      lastName: 'Doe',
      email: 'jane.doe@example.com',
      role: 'Buyer'
    };
    
    // Navigate to deals and measure performance
    await assertionsHelper.assertDomPerformance(async () => {
      await navigateToDeals(page);
    }, {
      threshold: 3000,
      operationName: 'Navigate to deals module'
    });
    
    // Create new deal
    await page.click('a:has-text("Create"), button:has-text("Create")');
    
    // Verify form loading state
    await assertionsHelper.assertLoadingState('form, .edit-view', false);
    
    // Fill form with UI state tracking
    await page.fill('input[name="name"]', testDeal.name);
    
    // Verify form validation state
    await expect(page.locator('form')).toHaveValidationState({
      fields: {
        name: { required: true, error: false }
      }
    });
    
    // Fill financial fields
    const ttmRevenueField = page.locator('input[name*="ttm_revenue"]').first();
    if (await ttmRevenueField.isVisible()) {
      await ttmRevenueField.fill(testDeal.ttmRevenue);
    }
    
    const ttmEbitdaField = page.locator('input[name*="ttm_ebitda"]').first();
    if (await ttmEbitdaField.isVisible()) {
      await ttmEbitdaField.fill(testDeal.ttmEbitda);
    }
    
    const targetMultipleField = page.locator('input[name*="target_multiple"]').first();
    if (await targetMultipleField.isVisible()) {
      await targetMultipleField.fill(testDeal.targetMultiple);
    }
    
    // Test form visual states
    await visualHelper.assertFormVisualStates('form', 'deal-creation-form');
    
    // Save deal
    await page.click('input[value="Save"], button:has-text("Save")');
    
    // Wait for navigation and get deal ID
    await page.waitForLoadState('networkidle');
    const dealId = new URL(page.url()).searchParams.get('record');
    
    // Verify deal creation with comprehensive checks
    if (dealId) {
      // Database persistence
      await expect(page).toHavePersistedInDatabase('opportunities', {
        id: dealId,
        name: testDeal.name
      }, {
        expectedFields: {
          ttm_revenue_c: testDeal.ttmRevenue,
          ttm_ebitda_c: testDeal.ttmEbitda
        }
      });
      
      // Audit log verification
      await expect(page).toHaveCorrectAuditLog('Deals', dealId, 'create');
      
      // UI state verification
      await expect(page.locator('h2, .moduleTitle')).toShowUIUpdate({
        text: testDeal.name,
        visible: true
      });
    }
    
    // Add contact to deal
    const contactsSubpanel = page.locator('.subpanel:has-text("Contacts")').first();
    if (await contactsSubpanel.isVisible()) {
      await contactsSubpanel.locator('a:has-text("Create"), button:has-text("Create")').first().click();
      
      // Fill contact form
      await page.fill('input[name="first_name"]', testContact.firstName);
      await page.fill('input[name="last_name"]', testContact.lastName);
      await page.fill('input[name="email1"], input[name="email"]', testContact.email);
      
      // Save contact
      await page.click('input[value="Save"], button:has-text("Save")');
      await page.waitForLoadState('networkidle');
      
      // Get contact ID
      const contactId = await page.evaluate(() => {
        const url = new URL(window.location.href);
        return url.searchParams.get('record');
      });
      
      // Verify contact-deal relationship
      if (dealId && contactId) {
        await assertionsHelper.assertRelationshipIntegrity(
          'opportunities', 
          'contacts', 
          'opportunity_id', 
          dealId
        );
        
        // Verify contact audit log
        await expect(page).toHaveCorrectAuditLog('Contacts', contactId, 'create');
      }
    }
    
    // Navigate back to deal
    await navigateToDeals(page);
    await page.click(`a:has-text("${testDeal.name}")`);
    await page.waitForLoadState('networkidle');
    
    // Verify complete deal view with visual regression
    await visualHelper.assertElementScreenshot('.detail-view', 'complete-deal-with-contact');
    
    // Test responsive design
    await visualHelper.assertResponsiveDesign('deal-detail-responsive');
    
    // Verify performance metrics
    await assertionsHelper.assertMemoryUsage({ maxHeapSize: 80 * 1024 * 1024 }); // 80MB
    
    console.log('✅ Complete deal creation workflow verified with all assertions');
  });

  test('Multi-user concurrent editing with conflict resolution', async ({ page, context }) => {
    // Create a test deal first
    await navigateToDeals(page);
    
    const dealName = `Concurrent Edit Test ${Date.now()}`;
    await page.click('a:has-text("Create")');
    await page.fill('input[name="name"]', dealName);
    await page.fill('input[name="description"], textarea[name="description"]', 'Original description');
    await page.click('input[value="Save"]');
    await page.waitForLoadState('networkidle');
    
    const dealId = new URL(page.url()).searchParams.get('record');
    
    // Open second tab with same deal
    const page2 = await context.newPage();
    await login(page2);
    await navigateToDeals(page2);
    await page2.click(`a:has-text("${dealName}")`);
    await page2.waitForLoadState('networkidle');
    
    // Edit in first tab
    await page.click('input[value="Edit"], button:has-text("Edit")');
    await page.fill('input[name="description"], textarea[name="description"]', 'Updated from tab 1');
    
    // Edit in second tab simultaneously
    await page2.click('input[value="Edit"], button:has-text("Edit")');
    await page2.fill('input[name="description"], textarea[name="description"]', 'Updated from tab 2');
    
    // Save from first tab
    await page.click('input[value="Save"]');
    await page.waitForLoadState('networkidle');
    
    // Verify first edit in database
    if (dealId) {
      await expect(page).toHavePersistedInDatabase('opportunities', {
        id: dealId
      }, {
        expectedFields: {
          description: 'Updated from tab 1'
        }
      });
      
      // Verify audit log for first edit
      await expect(page).toHaveCorrectAuditLog('Deals', dealId, 'update', {
        fieldChanges: {
          description: {
            before: 'Original description',
            after: 'Updated from tab 1'
          }
        }
      });
    }
    
    // Try to save from second tab (should handle conflict)
    await page2.click('input[value="Save"]');
    await page2.waitForLoadState('networkidle');
    
    // Check for conflict resolution UI
    const conflictMessage = page2.locator('.conflict-warning, .error, .alert');
    if (await conflictMessage.isVisible()) {
      await visualHelper.assertElementScreenshot(conflictMessage, 'conflict-resolution-ui');
      console.log('✅ Conflict resolution UI displayed correctly');
    }
    
    // Refresh second tab and verify final state
    await page2.reload();
    await page2.waitForLoadState('networkidle');
    
    // Verify final state consistency
    await assertionsHelper.assertText('.field-value, td', 'Updated from tab 1', {
      message: 'Final description should reflect the first edit'
    });
    
    await page2.close();
    
    console.log('✅ Concurrent editing test completed with conflict resolution verification');
  });

  test('Complex workflow with performance monitoring', async ({ page }) => {
    // Monitor page performance throughout complex workflow
    const performanceMetrics = [];
    
    // Start with deals list
    const startTime = Date.now();
    await navigateToDeals(page);
    performanceMetrics.push({
      operation: 'Navigate to deals',
      duration: Date.now() - startTime
    });
    
    // Verify initial load performance
    await expect(page).toMeetPerformanceThresholds({
      loadTime: 4000,
      domContentLoaded: 2500
    });
    
    // Create deal with performance monitoring
    const createStart = Date.now();
    await page.click('a:has-text("Create")');
    await page.waitForLoadState('networkidle');
    performanceMetrics.push({
      operation: 'Open create form',
      duration: Date.now() - createStart
    });
    
    // Fill form
    const testDeal = {
      name: `Performance Test Deal ${Date.now()}`,
      description: 'Testing performance with complex workflow'
    };
    
    await page.fill('input[name="name"]', testDeal.name);
    await page.fill('textarea[name="description"], input[name="description"]', testDeal.description);
    
    // Save with performance tracking
    const saveStart = Date.now();
    await page.click('input[value="Save"]');
    await page.waitForLoadState('networkidle');
    performanceMetrics.push({
      operation: 'Save deal',
      duration: Date.now() - saveStart
    });
    
    const dealId = new URL(page.url()).searchParams.get('record');
    
    // Perform multiple operations and track performance
    const operations = [
      {
        name: 'Edit deal',
        action: async () => {
          await page.click('input[value="Edit"]');
          await page.waitForLoadState('networkidle');
          await page.fill('textarea[name="description"], input[name="description"]', 'Updated description');
          await page.click('input[value="Save"]');
          await page.waitForLoadState('networkidle');
        }
      },
      {
        name: 'Navigate to list',
        action: async () => {
          await navigateToDeals(page);
        }
      },
      {
        name: 'Search for deal',
        action: async () => {
          await page.fill('input[name="name_basic"], input[name="basic_search"]', testDeal.name);
          await page.click('input[value="Search"]');
          await page.waitForLoadState('networkidle');
        }
      }
    ];
    
    for (const operation of operations) {
      const opStart = Date.now();
      await operation.action();
      performanceMetrics.push({
        operation: operation.name,
        duration: Date.now() - opStart
      });
    }
    
    // Verify all operations completed within acceptable time
    const totalDuration = performanceMetrics.reduce((sum, metric) => sum + metric.duration, 0);
    console.log('Performance metrics:', performanceMetrics);
    console.log('Total workflow duration:', totalDuration, 'ms');
    
    // Assert total workflow performance
    expect(totalDuration).toBeLessThan(15000); // 15 seconds max for entire workflow
    
    // Verify data consistency after all operations
    if (dealId) {
      await expect(page).toHavePersistedInDatabase('opportunities', {
        id: dealId,
        name: testDeal.name
      }, {
        expectedFields: {
          description: 'Updated description'
        }
      });
      
      // Verify all audit entries exist
      await expect(page).toHaveCorrectAuditLog('Deals', dealId, 'create');
      await expect(page).toHaveCorrectAuditLog('Deals', dealId, 'update');
    }
    
    // Final memory check
    await assertionsHelper.assertMemoryUsage({ maxHeapSize: 100 * 1024 * 1024 }); // 100MB
    
    console.log('✅ Complex workflow completed with performance monitoring and data consistency verification');
  });
});

test.describe('Visual Regression Integration Tests', () => {
  let visualHelper;
  
  test.beforeEach(async ({ page }) => {
    await login(page);
    visualHelper = new VisualRegressionHelper(page);
  });

  test('Cross-browser visual consistency', async ({ page }) => {
    await navigateToDeals(page);
    
    // Test list view consistency
    await visualHelper.assertCrossBrowserConsistency('deals-list-cross-browser', [
      { name: 'desktop', width: 1280, height: 720 },
      { name: 'tablet', width: 768, height: 1024 },
      { name: 'mobile', width: 375, height: 667 }
    ]);
    
    // Create deal form consistency
    await page.click('a:has-text("Create")');
    await page.waitForLoadState('networkidle');
    
    await visualHelper.assertCrossBrowserConsistency('deals-create-form-cross-browser', [
      { name: 'desktop', width: 1280, height: 720 },
      { name: 'mobile', width: 375, height: 667 }
    ]);
    
    console.log('✅ Cross-browser visual consistency verified');
  });

  test('Component state visual testing', async ({ page }) => {
    await navigateToDeals(page);
    await page.click('a:has-text("Create")');
    await page.waitForLoadState('networkidle');
    
    // Test form in different states
    const formStates = {
      empty: async () => {
        // Form is already empty
      },
      filled: async () => {
        await page.fill('input[name="name"]', 'Visual Test Deal');
        await page.fill('textarea[name="description"], input[name="description"]', 'Test description');
      },
      error: async () => {
        await page.fill('input[name="name"]', ''); // Clear required field
        await page.click('input[value="Save"]'); // Trigger validation
      }
    };
    
    await visualHelper.assertComponentStates(
      'form',
      formStates,
      'deal-form-states'
    );
    
    console.log('✅ Component state visual testing completed');
  });
});