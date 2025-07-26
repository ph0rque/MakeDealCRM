/**
 * Deal Creation Test Example
 * Demonstrates the E2E test structure and utilities
 */

const { test, expect } = require('../lib/fixtures/test-fixtures');
const DealPage = require('../lib/pages/deal.page');

test.describe('Deal Creation Examples', () => {
  let dealPage;

  test.beforeEach(async ({ authenticatedPage }) => {
    dealPage = new DealPage(authenticatedPage);
  });

  test('should create a basic deal @smoke @critical', async ({ testData }) => {
    // Generate test data
    const dealData = testData.generateDealData({
      name: 'Example Manufacturing Co',
      status: 'initial_contact'
    });

    // Create deal
    await dealPage.createDeal(dealData);

    // Verify creation
    await dealPage.assertions.assertText('h2', dealData.name);
    await dealPage.assertions.assertVisible('.stage-progress');
    
    // Track for cleanup
    testData.trackRecord('Deals', dealData.name);
  });

  test('should show duplicate warning for existing deal', async ({ testData }) => {
    const dealData = testData.generateDealData();
    
    // Create first deal
    await dealPage.createDeal(dealData);
    
    // Try to create duplicate
    await dealPage.navigateToCreate();
    await dealPage.fillField('name', dealData.name);
    
    // Wait for duplicate check
    await dealPage.wait(1000);
    
    // Verify duplicate warning
    const isDuplicateVisible = await dealPage.isDuplicateWarningVisible();
    expect(isDuplicateVisible).toBeTruthy();
  });

  test('should calculate valuation correctly', async ({ testData }) => {
    const dealData = testData.generateDealData({
      ttm_ebitda: '2000000',
      target_multiple: '5.0'
    });

    await dealPage.createDeal(dealData);
    
    // Verify calculated valuation (2M * 5 = 10M)
    const valuation = await dealPage.getDealValuation(dealData.name);
    expect(valuation).toContain('$10,000,000');
  });

  test('should update deal status through workflow @regression', async ({ testData }) => {
    const dealData = testData.generateDealData({
      status: 'sourcing'
    });

    await dealPage.createDeal(dealData);
    
    // Progress through stages
    const stages = ['initial_contact', 'nda_signed', 'info_received'];
    
    for (const stage of stages) {
      await dealPage.updateDealStatus(dealData.name, stage);
      
      // Verify stage updated
      const progress = await dealPage.getStageProgress(dealData.name);
      expect(progress.text).toContain(stage.replace(/_/g, ' '));
    }
  });

  test('should filter deals by stage', async ({ testData }) => {
    // Create deals in different stages
    const deals = [
      testData.generateDealData({ status: 'initial_contact' }),
      testData.generateDealData({ status: 'nda_signed' }),
      testData.generateDealData({ status: 'due_diligence' })
    ];

    for (const deal of deals) {
      await dealPage.createDeal(deal);
    }

    // Filter by stage
    await dealPage.filterByStage('Initial Contact');
    
    // Verify filtered results
    await dealPage.assertions.assertVisible('td:has-text("' + deals[0].name + '")');
    await dealPage.assertions.assertHidden('td:has-text("' + deals[1].name + '")');
  });

  test('should add activity to deal', async ({ testData }) => {
    const dealData = testData.generateDealData();
    await dealPage.createDeal(dealData);
    
    // Add note
    const noteDescription = 'Test note added via automation';
    await dealPage.addActivity(dealData.name, 'note', noteDescription);
    
    // Verify activity appears in timeline
    await dealPage.assertions.assertVisible(
      '.timeline-item:has-text("' + noteDescription + '")'
    );
  });

  test('should export deals', async ({ testData }) => {
    // Create test deals
    const deals = testData.generateBulkData('deal', 3);
    for (const deal of deals) {
      await dealPage.createDeal(deal);
    }

    // Export deals
    const download = await dealPage.exportDeals();
    
    // Verify download
    expect(download.suggestedFilename()).toContain('.csv');
  });

  test('should handle mobile viewport @mobile', async ({ mobileViewport, testData }) => {
    const dealData = testData.generateDealData();
    
    // Create deal on mobile
    await dealPage.createDeal(dealData);
    
    // Verify mobile-friendly display
    await dealPage.assertions.assertVisible('.stage-progress');
    
    // Take mobile screenshot
    await dealPage.screenshot.takeScreenshot('deal-mobile-view');
  });

  test('should capture performance metrics', async ({ performanceMetrics, testData }) => {
    const startTime = Date.now();
    
    await dealPage.navigate();
    
    const loadTime = Date.now() - startTime;
    expect(loadTime).toBeLessThan(3000); // Should load within 3 seconds
    
    console.log('Navigation metrics:', performanceMetrics.navigation);
  });

  test('should handle network errors gracefully', async ({ networkCapture }) => {
    // Simulate network error
    await dealPage.page.route('**/api/**', route => {
      route.abort('failed');
    });

    await dealPage.navigate();
    
    // Verify error handling
    await dealPage.assertions.assertVisible('.error-message, .alert-danger');
    
    console.log('Network requests:', networkCapture.requests.length);
  });

  test.describe('Visual Testing', () => {
    test('should match deal form baseline', async () => {
      await dealPage.navigateToCreate();
      
      // Compare screenshot
      await expect(dealPage.page.locator('.edit-view')).toHaveScreenshot('deal-form.png');
    });

    test('should match deal list baseline', async ({ testData }) => {
      // Create consistent test data
      const deals = testData.generateBulkData('deal', 5);
      for (const deal of deals) {
        await dealPage.createDeal(deal);
      }

      await dealPage.navigate();
      
      // Compare list view
      await expect(dealPage.page.locator('.list-view')).toHaveScreenshot('deal-list.png');
    });
  });

  test.describe('Accessibility Testing', () => {
    test('should be keyboard navigable', async () => {
      await dealPage.navigate();
      
      // Tab through elements
      await dealPage.page.keyboard.press('Tab');
      await dealPage.page.keyboard.press('Tab');
      await dealPage.page.keyboard.press('Tab');
      
      // Check focus
      const activeElement = await dealPage.page.evaluate(() => 
        document.activeElement.tagName.toLowerCase()
      );
      expect(['a', 'button', 'input'].includes(activeElement)).toBeTruthy();
    });

    test('should have proper ARIA labels', async () => {
      await dealPage.navigateToCreate();
      
      // Check form accessibility
      await dealPage.assertions.assertAccessible('form', {
        requireLabel: true
      });
    });
  });
});

test.describe('Deal Module Integration', () => {
  test('should integrate with contacts module', async ({ authenticatedPage, testData }) => {
    const dealPage = new DealPage(authenticatedPage);
    
    // Create deal and contact
    const dealData = testData.generateDealData();
    const contactData = testData.generateContactData();
    
    await dealPage.createDeal(dealData);
    
    // Link contact (this would require ContactPage implementation)
    await dealPage.linkContact(dealData.name, contactData.first_name);
    
    // Verify relationship
    await dealPage.assertions.assertVisible(
      `.subpanel td:has-text("${contactData.first_name}")`
    );
  });
});