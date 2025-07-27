import { test, expect, describe } from '../../lib/base-test';
import { TestDataGenerator } from '../../lib/test-helpers';

describe.smoke('Deals CRUD Operations', () => {
  test('should create a new deal', async ({ dealsPage, authenticatedPage }) => {
    const dealData = TestDataGenerator.generateDealData({
      name: `Test Deal ${Date.now()}`
    });
    
    await dealsPage.createDeal(dealData);
    expect(await dealsPage.isOnDetailView()).toBe(true);
    
    const details = await dealsPage.getDealDetails();
    expect(details['Deal Name'] || details['Name']).toBe(dealData.name);
  });

  test('should view deal in list', async ({ dealsPage, authenticatedPage }) => {
    await dealsPage.goto();
    expect(await dealsPage.isOnListView()).toBe(true);
    
    const deals = await dealsPage.getAllDeals();
    expect(deals.length).toBeGreaterThan(0);
  });

  test('should search for deals', async ({ dealsPage, authenticatedPage }) => {
    const dealData = TestDataGenerator.generateDealData({
      name: `Searchable Deal ${Date.now()}`
    });
    
    await dealsPage.createDeal(dealData);
    await dealsPage.goto();
    await dealsPage.searchDeal(dealData.name);
    
    expect(await dealsPage.dealExists(dealData.name)).toBe(true);
  });

  test('should edit deal details', async ({ dealsPage, authenticatedPage }) => {
    const originalData = TestDataGenerator.generateDealData({
      name: `Original Deal ${Date.now()}`
    });
    
    await dealsPage.createDeal(originalData);
    
    const updatedData = {
      name: `Updated Deal ${Date.now()}`,
      amount: '75000'
    };
    
    await dealsPage.editDeal(originalData.name, updatedData);
    
    const details = await dealsPage.getDealDetails();
    expect(details['Deal Name'] || details['Name']).toBe(updatedData.name);
    expect(details['Amount']).toContain('75000');
  });

  test('should delete a deal', async ({ dealsPage, authenticatedPage }) => {
    const dealData = TestDataGenerator.generateDealData({
      name: `Delete Me Deal ${Date.now()}`
    });
    
    await dealsPage.createDeal(dealData);
    await dealsPage.deleteDeal(dealData.name);
    
    await dealsPage.goto();
    expect(await dealsPage.dealExists(dealData.name)).toBe(false);
  });
});

describe.regression('Deals Validation', () => {
  test('should validate required fields', async ({ dealsPage, authenticatedPage }) => {
    await dealsPage.gotoCreateDeal();
    
    // Try to save without required fields
    await dealsPage.page.click('input[type="submit"][value="Save"]');
    
    // Should remain on edit view if validation fails
    expect(await dealsPage.isOnEditView()).toBe(true);
  });

  test('should handle large amounts correctly', async ({ dealsPage, authenticatedPage }) => {
    const dealData = TestDataGenerator.generateDealData({
      name: `Large Amount Deal ${Date.now()}`,
      amount: '999999999.99'
    });
    
    await dealsPage.createDeal(dealData);
    
    const details = await dealsPage.getDealDetails();
    expect(details['Amount']).toContain('999999999.99');
  });

  test('should validate probability range', async ({ dealsPage, authenticatedPage }) => {
    const dealData = TestDataGenerator.generateDealData({
      name: `Probability Test ${Date.now()}`,
      probability: '150' // Invalid - over 100%
    });
    
    await dealsPage.gotoCreateDeal();
    await dealsPage.page.fill('input[name="name"]', dealData.name);
    await dealsPage.page.fill('input[name="probability"]', dealData.probability);
    await dealsPage.page.click('input[type="submit"][value="Save"]');
    
    // Should either prevent invalid value or correct it
    const finalProbability = await dealsPage.page.inputValue('input[name="probability"]');
    expect(parseInt(finalProbability)).toBeLessThanOrEqual(100);
  });
});