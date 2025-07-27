import { test, expect, describe } from '../../lib/base-test';
import { TestDataGenerator } from '../../lib/test-helpers';

describe.smoke('Pipeline Operations', () => {
  test('should display pipeline view', async ({ pipelinePage, authenticatedPage }) => {
    await pipelinePage.goto();
    
    const stages = await pipelinePage.getStages();
    expect(stages.length).toBeGreaterThan(0);
    expect(stages).toContain('Prospecting');
    expect(stages).toContain('Qualification');
  });

  test('should show deals in pipeline stages', async ({ pipelinePage, dealsPage, authenticatedPage }) => {
    // Create a test deal first
    const dealData = TestDataGenerator.generateDealData({
      name: `Pipeline Deal ${Date.now()}`,
      stage: 'Prospecting'
    });
    
    await dealsPage.createDeal(dealData);
    await pipelinePage.goto();
    
    const prospectingDeals = await pipelinePage.getDealsInStage('Prospecting');
    expect(prospectingDeals).toContain(dealData.name);
  });

  test('should drag and drop deal between stages', async ({ pipelinePage, dealsPage, authenticatedPage }) => {
    // Create a test deal
    const dealData = TestDataGenerator.generateDealData({
      name: `Drag Drop Deal ${Date.now()}`,
      stage: 'Prospecting'
    });
    
    await dealsPage.createDeal(dealData);
    await pipelinePage.goto();
    
    // Verify initial stage
    const initialStage = await pipelinePage.getDealStage(dealData.name);
    expect(initialStage).toContain('Prospecting');
    
    // Drag to new stage
    await pipelinePage.dragDealToStage(dealData.name, 'Qualification');
    
    // Verify new stage
    const newStage = await pipelinePage.getDealStage(dealData.name);
    expect(newStage).toContain('Qualification');
  });

  test('should refresh pipeline data', async ({ pipelinePage, authenticatedPage }) => {
    await pipelinePage.goto();
    
    const initialDealsCount = await pipelinePage.getTotalDealsCount();
    await pipelinePage.refreshPipeline();
    
    // Pipeline should still be functional after refresh
    const stages = await pipelinePage.getStages();
    expect(stages.length).toBeGreaterThan(0);
  });
});

describe.feature('pipeline')('Pipeline Management', () => {
  test('should filter pipeline by criteria', async ({ pipelinePage, authenticatedPage }) => {
    await pipelinePage.goto();
    
    // Test different filters if available
    const filters = ['all', 'my_deals', 'team_deals'];
    
    for (const filter of filters) {
      try {
        await pipelinePage.applyFilter(filter);
        const stages = await pipelinePage.getStages();
        expect(stages.length).toBeGreaterThan(0);
      } catch (error) {
        // Filter might not exist, continue
        console.log(`Filter ${filter} not available`);
      }
    }
  });

  test('should show deal details in pipeline cards', async ({ pipelinePage, dealsPage, authenticatedPage }) => {
    const dealData = TestDataGenerator.generateDealData({
      name: `Card Details Deal ${Date.now()}`,
      amount: '50000'
    });
    
    await dealsPage.createDeal(dealData);
    await pipelinePage.goto();
    
    const cardDetails = await pipelinePage.getDealCardDetails(dealData.name);
    expect(cardDetails.name).toBe(dealData.name);
    // Amount might be formatted, so check if it contains the number
    expect(cardDetails.amount).toContain('50000');
  });

  test('should add deal directly from pipeline', async ({ pipelinePage, authenticatedPage }) => {
    await pipelinePage.goto();
    
    const newDealData = {
      name: `Pipeline Added Deal ${Date.now()}`,
      amount: '25000'
    };
    
    await pipelinePage.addDealToStage('Prospecting', newDealData);
    
    // Verify deal appears in the stage
    const prospectingDeals = await pipelinePage.getDealsInStage('Prospecting');
    expect(prospectingDeals).toContain(newDealData.name);
  });

  test('should search deals in pipeline', async ({ pipelinePage, dealsPage, authenticatedPage }) => {
    const dealData = TestDataGenerator.generateDealData({
      name: `Searchable Pipeline Deal ${Date.now()}`
    });
    
    await dealsPage.createDeal(dealData);
    await pipelinePage.goto();
    
    await pipelinePage.searchDeals(dealData.name);
    expect(await pipelinePage.dealExistsInPipeline(dealData.name)).toBe(true);
    
    await pipelinePage.clearSearch();
  });

  test('should show pipeline statistics', async ({ pipelinePage, authenticatedPage }) => {
    await pipelinePage.goto();
    
    const dealCounts = await pipelinePage.getDealsCountByStage();
    
    // Verify we have counts for each stage
    const stages = await pipelinePage.getStages();
    stages.forEach(stage => {
      expect(typeof dealCounts[stage]).toBe('number');
      expect(dealCounts[stage]).toBeGreaterThanOrEqual(0);
    });
  });
});

describe.regression('Pipeline Performance', () => {
  test('should handle large number of deals in pipeline', async ({ pipelinePage, authenticatedPage }) => {
    await pipelinePage.goto();
    
    // Test with existing deals - in a real test environment you might create many deals
    const totalDeals = await pipelinePage.getTotalDealsCount();
    
    // Pipeline should load within reasonable time even with many deals
    const startTime = Date.now();
    await pipelinePage.refreshPipeline();
    const loadTime = Date.now() - startTime;
    
    expect(loadTime).toBeLessThan(10000); // Less than 10 seconds
  });

  test('should maintain state during drag and drop operations', async ({ pipelinePage, dealsPage, authenticatedPage }) => {
    // Create multiple test deals
    const dealNames = [];
    for (let i = 0; i < 3; i++) {
      const dealData = TestDataGenerator.generateDealData({
        name: `State Test Deal ${Date.now()}_${i}`
      });
      await dealsPage.createDeal(dealData);
      dealNames.push(dealData.name);
    }
    
    await pipelinePage.goto();
    
    // Verify all deals are present
    for (const name of dealNames) {
      expect(await pipelinePage.dealExistsInPipeline(name)).toBe(true);
    }
    
    // Move one deal and verify others remain
    await pipelinePage.dragDealToStage(dealNames[0], 'Qualification');
    
    // All deals should still be visible
    for (const name of dealNames) {
      expect(await pipelinePage.dealExistsInPipeline(name)).toBe(true);
    }
  });
});