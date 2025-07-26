/**
 * Comprehensive Test Example
 * Demonstrates all the enhanced test data utilities and fixtures
 */

const { test, expect } = require('../lib/fixtures/enhanced-test-fixtures');

test.describe('Comprehensive Test Suite with Enhanced Utilities', () => {
  
  test('Enhanced data manager demonstration', async ({ 
    enhancedDataManager, 
    stateVerifier 
  }) => {
    // Create a deal with enhanced data manager
    const dealData = enhancedDataManager.generateDealData({
      name: 'Enhanced Manager Test Deal',
      amount: 250000,
      sales_stage: 'Negotiation'
    });

    const deal = await enhancedDataManager.createTestData('deals', dealData, {
      validateData: true,
      enableRelationships: true
    });

    expect(deal.id).toBeTruthy();
    
    // Verify database state
    const expectedState = { deals: 1 };
    const verificationResult = await stateVerifier.verifyDatabaseState(expectedState);
    expect(verificationResult.overallStatus).toBe('PASSED');

    // Get metrics
    const metrics = enhancedDataManager.getMetrics();
    expect(metrics.recordsCreated.deals).toBeGreaterThan(0);
  });

  test('Bulk data utilities demonstration', async ({ 
    bulkDataUtils,
    performanceTesting 
  }) => {
    // Create performance dataset
    const performanceResult = await performanceTesting.benchmark(
      'create-performance-dataset',
      async () => {
        return await bulkDataUtils.createPerformanceDataset({
          accounts: 10,
          contactsPerAccount: 2,
          dealsPerAccount: 3,
          documentsPerDeal: 1,
          checklistsPerDeal: 2
        });
      }
    );

    expect(performanceResult.summary.successfulIterations).toBe(1);
    
    // Verify the dataset was created correctly
    const stats = bulkDataUtils.getBulkStats();
    expect(stats.totalRecordsCreated).toBeGreaterThan(50);
    expect(stats.successRate).toBe(100);
  });

  test('Test isolation demonstration', async ({ 
    testIsolation, 
    enhancedDataManager 
  }) => {
    // Create isolated data
    const isolatedDeal = await testIsolation.createIsolatedData('deals', {
      name: 'Isolated Test Deal',
      amount: 100000
    });

    // Check isolation context
    const contextInfo = testIsolation.getContextInfo();
    expect(contextInfo.id).toBe(testIsolation.contextId);
    expect(isolatedDeal.name).toContain(contextInfo.namespace);
    
    // Create another isolated record
    const isolatedAccount = await testIsolation.createIsolatedData('accounts', {
      name: 'Isolated Test Account',
      industry: 'Technology'
    });

    expect(isolatedAccount.name).toContain(contextInfo.namespace);
  });

  test('Deal fixture demonstration', async ({ 
    dealFixture,
    relationshipManager 
  }) => {
    // Create deal with full relationships
    const { deal, account, contacts } = await dealFixture.createDealWithRelationships({
      name: 'Fixture Test Deal',
      amount: 500000,
      sales_stage: 'Qualification'
    });

    expect(deal.account_id).toBe(account.id);
    expect(contacts.length).toBe(2);

    // Verify relationships
    const integrityReport = await relationshipManager.getRelationshipIntegrityReport();
    expect(integrityReport.brokenForeignKeys.length).toBe(0);

    // Create pipeline scenario
    const pipeline = await dealFixture.createPipelineScenario(2);
    expect(Object.keys(pipeline)).toHaveLength(6); // 6 stages
    expect(pipeline.Prospecting).toHaveLength(2);
    expect(pipeline['Closed Won']).toHaveLength(2);
  });

  test('Seeded environment demonstration', async ({ 
    seededEnvironment,
    stateVerifier 
  }) => {
    // The environment is already seeded with default profile
    expect(seededEnvironment.success).toBe(true);
    expect(seededEnvironment.totalRecords).toBeGreaterThan(0);

    // Verify seeded data exists
    const expectedState = {
      accounts: 25,
      contacts: 100,
      deals: 75
    };

    const verificationResult = await stateVerifier.verifyDatabaseState(
      expectedState, 
      { strictMode: false }
    );
    
    expect(verificationResult.overallStatus).toMatch(/PASSED/);
  });

  test('API testing demonstration', async ({ 
    apiTestingFixture,
    enhancedDataManager 
  }) => {
    // Create authenticated API client
    const apiClient = await apiTestingFixture.createAuthenticatedClient();

    // Create deal via API
    const dealData = enhancedDataManager.generateDealData({
      name: 'API Created Deal'
    });

    const response = await apiClient.post('/deals', { data: dealData });
    expect(response.ok()).toBeTruthy();

    const createdDeal = await response.json();
    expect(createdDeal.name).toBe('API Created Deal');

    // Retrieve deal via API
    const getResponse = await apiClient.get(`/deals/${createdDeal.id}`);
    expect(getResponse.ok()).toBeTruthy();

    const retrievedDeal = await getResponse.json();
    expect(retrievedDeal.id).toBe(createdDeal.id);
  });

  test('Visual regression demonstration', async ({ 
    page,
    visualRegressionFixture,
    dealFixture 
  }) => {
    // Create test data
    const { deal } = await dealFixture.createDealWithRelationships();

    // Navigate to deals page
    await page.goto('/deals');
    await page.waitForSelector('.module-title-text:has-text("Deals")');

    // Take baseline screenshot
    await visualRegressionFixture.takeBaseline('deals-list-page');

    // Navigate to deal detail
    await page.click(`a:has-text("${deal.name}")`);
    await page.waitForSelector('.detail-view');

    // Take element screenshot
    await visualRegressionFixture.takeElementScreenshot(
      '.detail-view',
      'deal-detail-view'
    );

    // Compare current state
    await visualRegressionFixture.compareScreenshot('deal-detail-current');
  });

  test('Accessibility testing demonstration', async ({ 
    page,
    accessibilityFixture,
    dealFixture 
  }) => {
    // Create test data
    await dealFixture.createDealWithRelationships();

    // Navigate to deals page
    await page.goto('/deals');
    await page.waitForSelector('.module-title-text');

    // Check accessibility
    const a11yResults = await accessibilityFixture.checkA11y();
    
    // Log violations (don't fail test for demo)
    if (a11yResults.violations.length > 0) {
      console.log('Accessibility violations found:', a11yResults.violations.length);
    }

    // Test keyboard navigation
    const navigationResults = await accessibilityFixture.testKeyboardNavigation();
    expect(navigationResults.length).toBeGreaterThan(0);
  });

  test('Mobile testing demonstration', async ({ 
    mobileTestingFixture,
    dealFixture 
  }) => {
    const { page } = mobileTestingFixture;
    
    // Create test data
    const { deal } = await dealFixture.createDealWithRelationships();

    // Navigate to deals page on mobile
    await page.goto('/deals');
    await page.waitForSelector('.module-title-text');

    // Test mobile-specific interactions
    await mobileTestingFixture.swipe('left', 50);
    await page.waitForTimeout(1000); // Wait for animation

    // Test orientation change
    await mobileTestingFixture.rotateDevice('landscape');
    await page.waitForTimeout(500);

    // Verify deal is still visible
    await expect(page.locator(`text=${deal.name}`)).toBeVisible();

    // Rotate back to portrait
    await mobileTestingFixture.rotateDevice('portrait');
  });

  test('Performance benchmarking demonstration', async ({ 
    performanceTesting,
    bulkDataUtils 
  }) => {
    // Benchmark bulk account creation
    const accountBenchmark = await performanceTesting.benchmark(
      'bulk-account-creation',
      async () => {
        return await bulkDataUtils.createBulkAccounts(50);
      },
      3 // Run 3 iterations
    );

    expect(accountBenchmark.summary.successfulIterations).toBe(3);
    expect(accountBenchmark.summary.performance.avg).toBeLessThan(10000); // Less than 10 seconds

    // Benchmark deal creation with relationships
    const dealBenchmark = await performanceTesting.benchmark(
      'deal-with-relationships',
      async () => {
        const accounts = await bulkDataUtils.createBulkAccounts(5);
        const contacts = await bulkDataUtils.createBulkContactsForAccounts(accounts, 2);
        return await bulkDataUtils.createBulkDealsForAccounts(accounts, 3, contacts);
      }
    );

    expect(dealBenchmark.summary.successfulIterations).toBe(1);
    
    // Record custom timing
    const startTime = Date.now();
    await bulkDataUtils.createBulkAccounts(25);
    performanceTesting.recordTiming('custom-account-creation', Date.now() - startTime);
  });

  test('Complete workflow demonstration', async ({ 
    enhancedDataManager,
    relationshipManager,
    testIsolation,
    stateVerifier,
    page 
  }) => {
    // Step 1: Create isolated test data
    const dealData = await testIsolation.createIsolatedData('deals', {
      name: 'Workflow Test Deal',
      amount: 750000,
      sales_stage: 'Value Proposition'
    });

    const accountData = await testIsolation.createIsolatedData('accounts', {
      name: 'Workflow Test Account',
      industry: 'Healthcare'
    });

    // Step 2: Create relationships
    await relationshipManager.createRecordWithRelationships('deals', {
      id: dealData.id,
      account_id: accountData.id,
      name: dealData.name,
      amount: dealData.amount
    });

    // Step 3: Verify database state
    const expectedState = { deals: 1, accounts: 1 };
    const verificationResult = await stateVerifier.verifyDatabaseState(expectedState);
    expect(verificationResult.overallStatus).toBe('PASSED');

    // Step 4: Test UI workflow
    await page.goto('/deals');
    await page.waitForSelector('.module-title-text');

    // Search for the created deal
    await page.fill('input[name="search"]', dealData.name.split('_').pop()); // Remove namespace for search
    await page.press('input[name="search"]', 'Enter');
    await page.waitForSelector('.list-view');

    // Verify deal appears in results
    await expect(page.locator(`text=${dealData.name}`)).toBeVisible();

    // Click on deal to view details
    await page.click(`a:has-text("${dealData.name}")`);
    await page.waitForSelector('.detail-view');

    // Verify deal details
    await expect(page.locator(`text=${dealData.amount}`)).toBeVisible();
    await expect(page.locator('text=Value Proposition')).toBeVisible();

    // Step 5: Final verification
    const contextInfo = testIsolation.getContextInfo();
    expect(contextInfo.createdResourceCounts.deals).toBe(1);
    expect(contextInfo.createdResourceCounts.accounts).toBe(1);
  });

});

test.describe('Environment-specific tests', () => {
  
  test.skip(({ }, testInfo) => {
    return !testInfo.project.name.includes('performance');
  }, 'Performance stress test', async ({ 
    bulkDataUtils,
    performanceTesting 
  }) => {
    const stressTest = await performanceTesting.benchmark(
      'stress-test-dataset',
      async () => {
        return await bulkDataUtils.createPerformanceDataset({
          accounts: 100,
          contactsPerAccount: 5,
          dealsPerAccount: 10,
          documentsPerDeal: 3,
          checklistsPerDeal: 8
        });
      }
    );

    expect(stressTest.summary.successfulIterations).toBe(1);
    
    const stats = bulkDataUtils.getBulkStats();
    expect(stats.totalRecordsCreated).toBeGreaterThan(2000);
    expect(stats.recordsPerSecond).toBeGreaterThan(10);
  });

  test.skip(({ }, testInfo) => {
    return testInfo.project.name !== 'api';
  }, 'API-only test', async ({ 
    apiTestingFixture,
    bulkDataUtils 
  }) => {
    const client = await apiTestingFixture.createAuthenticatedClient();

    // Test bulk creation via API
    const accounts = bulkDataUtils.generateBulkData('account', 10);
    
    for (const account of accounts) {
      const response = await client.post('/accounts', { data: account });
      expect(response.ok()).toBeTruthy();
    }

    // Verify all accounts were created
    const listResponse = await client.get('/accounts?limit=50');
    expect(listResponse.ok()).toBeTruthy();
    
    const accountsList = await listResponse.json();
    expect(accountsList.data.length).toBeGreaterThanOrEqual(10);
  });

});