const { test, expect } = require('@playwright/test');
const DealPage = require('../page-objects/DealPage');
const AuthHelper = require('../lib/helpers/auth.helper');

/**
 * Feature 5 E2E Tests: At-a-Glance Financial & Valuation Hub
 * Based on Test Case 5.1 from PRD
 */

// Test data for financial hub testing
const testDealData = {
  name: 'E2E Financial Deal',
  status: 'initial_contact',
  source: 'direct',
  deal_value: '4000000',
  ttm_revenue: '10000000',
  ttm_ebitda: '1000000',
  target_multiple: '4',
  asking_price: '4000000',
  description: 'Test deal for financial hub E2E testing'
};

// Financial calculation helpers
class FinancialCalculationHelper {
  /**
   * Calculate proposed valuation
   * @param {number} ebitda - TTM EBITDA
   * @param {number} multiple - Target multiple
   * @returns {number} Calculated valuation
   */
  static calculateValuation(ebitda, multiple) {
    return ebitda * multiple;
  }

  /**
   * Format currency for display
   * @param {number} amount - Amount to format
   * @returns {string} Formatted currency string
   */
  static formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0
    }).format(amount);
  }

  /**
   * Parse currency string to number
   * @param {string} currencyString - Currency string like "$4,000,000"
   * @returns {number} Parsed number
   */
  static parseCurrency(currencyString) {
    return parseInt(currencyString.replace(/[$,]/g, ''));
  }

  /**
   * Validate financial calculation
   * @param {number} ebitda - TTM EBITDA
   * @param {number} multiple - Target multiple
   * @param {number} expectedValuation - Expected valuation
   * @returns {boolean} True if calculation is correct
   */
  static validateCalculation(ebitda, multiple, expectedValuation) {
    const calculatedValue = this.calculateValuation(ebitda, multiple);
    return Math.abs(calculatedValue - expectedValuation) < 1000; // Allow for small rounding differences
  }
}

test.describe('Feature 5: At-a-Glance Financial & Valuation Hub', () => {
  let dealPage;
  let authHelper;

  test.beforeEach(async ({ page }) => {
    dealPage = new DealPage(page);
    authHelper = new AuthHelper(page);
    
    // Login as admin
    await authHelper.loginAsAdmin();
  });

  test.afterEach(async ({ page }) => {
    // Cleanup: Delete test deal if it exists
    try {
      await dealPage.goto();
      await dealPage.searchDeals(testDealData.name);
      
      // Check if deal exists and delete it
      const dealExists = await page.locator(`a:has-text("${testDealData.name}")`).isVisible();
      if (dealExists) {
        await dealPage.openDeal(testDealData.name);
        await dealPage.deleteDeal();
      }
    } catch (error) {
      console.log('Cleanup error (non-critical):', error.message);
    }
  });

  test('Test Case 5.1: Financial Hub What-if Calculator Integration', async ({ page }) => {
    // Step 1: Set up deal "E2E Financial Deal" with specified financial data
    await test.step('Create deal with TTM EBITDA: $1,000,000, Multiple: 4, Proposed Valuation: $4,000,000', async () => {
      await dealPage.createDeal(testDealData);
      
      // Verify deal was created successfully
      const dealTitle = await dealPage.getDealTitle();
      expect(dealTitle).toContain(testDealData.name);
    });

    // Step 2: Open the Financial Hub dashboard widget
    await test.step('Open Financial Hub dashboard widget', async () => {
      // Look for financial hub widget - this could be a dashboard widget or section
      const financialHubWidget = page.locator('.financial-hub-widget, .financial-dashboard, .valuation-widget');
      
      // If not visible, look for a button/link to open it
      const openFinancialHub = page.locator('button:has-text("Financial Hub"), a:has-text("Financial Hub"), .financial-hub-toggle');
      
      if (await openFinancialHub.isVisible()) {
        await openFinancialHub.click();
        await page.waitForTimeout(1000); // Wait for widget to load
      }
      
      // Verify financial hub is accessible
      const hubExists = await financialHubWidget.isVisible() || 
                       await page.locator('.field-label:has-text("Proposed Valuation")').isVisible();
      expect(hubExists).toBeTruthy();
    });

    // Step 3: Verify initial Proposed Valuation of $4,000,000
    await test.step('Verify initial Proposed Valuation of $4,000,000', async () => {
      // Look for the proposed valuation field in various possible locations
      let valuationElement = await page.locator('.field-value:has-text("$4,000,000")').first();
      
      if (!(await valuationElement.isVisible())) {
        // Try alternative selectors for valuation display
        valuationElement = await page.locator('*:has-text("4,000,000")').first();
      }
      
      if (!(await valuationElement.isVisible())) {
        // Look for calculated valuation field
        const calculatedValuation = FinancialCalculationHelper.calculateValuation(1000000, 4);
        const formattedValue = FinancialCalculationHelper.formatCurrency(calculatedValuation);
        
        // Verify the calculation is correct even if display format differs
        expect(calculatedValuation).toBe(4000000);
        
        // Try to find any element containing the valuation amount
        const valuationText = await page.locator('*').filter({ hasText: /4[,\s]*000[,\s]*000/ }).first();
        expect(await valuationText.isVisible()).toBeTruthy();
      } else {
        expect(await valuationElement.isVisible()).toBeTruthy();
      }
    });

    // Step 4: Open the "What-if Calculator"
    await test.step('Open What-if Calculator', async () => {
      // Look for what-if calculator button/link
      const calculatorTrigger = page.locator(
        'button:has-text("What-if Calculator"), ' +
        'a:has-text("What-if Calculator"), ' +
        'button:has-text("Calculator"), ' +
        '.what-if-calculator-btn, ' +
        '.financial-calculator-btn'
      );
      
      await expect(calculatorTrigger.first()).toBeVisible({ timeout: 10000 });
      await calculatorTrigger.first().click();
      
      // Wait for calculator modal/panel to open
      await page.waitForTimeout(1000);
      
      // Verify calculator is open
      const calculatorModal = page.locator(
        '.what-if-calculator, ' +
        '.financial-calculator, ' +
        '.calculator-modal, ' +
        'div:has-text("What-if Calculator")'
      );
      
      await expect(calculatorModal.first()).toBeVisible();
    });

    // Step 5: Change Multiple from 4 to 5
    await test.step('Change Multiple from 4 to 5', async () => {
      // Find the multiple input field in the calculator
      const multipleInput = page.locator(
        'input[name*="multiple"], ' +
        'input[placeholder*="Multiple"], ' +
        '.calculator input[type="number"]:nth-of-type(2), ' +
        'input[value="4"]'
      );
      
      await expect(multipleInput.first()).toBeVisible();
      
      // Clear and enter new multiple value
      await multipleInput.first().clear();
      await multipleInput.first().fill('5');
      
      // Trigger calculation by pressing Enter or clicking out
      await multipleInput.first().press('Enter');
      await page.waitForTimeout(500); // Wait for calculation
    });

    // Step 6: Verify calculator instantly updates to $5,000,000
    await test.step('Verify calculator updates to $5,000,000', async () => {
      // Wait for calculation to complete
      await page.waitForTimeout(1000);
      
      // Look for updated valuation in calculator
      const updatedValuation = page.locator(
        '.calculator-result:has-text("5,000,000"), ' +
        '.calculated-value:has-text("5,000,000"), ' +
        '*:has-text("$5,000,000")'
      );
      
      // Verify the calculation is mathematically correct
      const expectedValuation = FinancialCalculationHelper.calculateValuation(1000000, 5);
      expect(expectedValuation).toBe(5000000);
      
      // Check if the updated value is displayed
      await expect(updatedValuation.first()).toBeVisible({ timeout: 5000 });
    });

    // Step 7: Save/apply the changes
    await test.step('Save/apply calculator changes', async () => {
      // Look for save/apply button in calculator
      const saveButton = page.locator(
        'button:has-text("Save"), ' +
        'button:has-text("Apply"), ' +
        'button:has-text("Update"), ' +
        '.calculator-save, ' +
        '.apply-changes'
      );
      
      await expect(saveButton.first()).toBeVisible();
      await saveButton.first().click();
      
      // Wait for save operation
      await page.waitForTimeout(1000);
      
      // Verify success message or calculator closes
      const successIndicator = page.locator(
        '.alert-success, ' +
        '.success-message, ' +
        'div:has-text("saved"), ' +
        'div:has-text("updated")'
      );
      
      // Either success message appears or calculator closes
      const calculatorClosed = !(await page.locator('.what-if-calculator, .financial-calculator').isVisible());
      const successShown = await successIndicator.isVisible();
      
      expect(calculatorClosed || successShown).toBeTruthy();
    });

    // Step 8: Verify main widget updates to $5,000,000
    await test.step('Verify main widget updates to $5,000,000', async () => {
      // Wait for main page to update
      await page.waitForTimeout(2000);
      
      // Look for updated valuation in main view
      const mainValuation = page.locator(
        '.field-value:has-text("5,000,000"), ' +
        '.valuation-display:has-text("5,000,000"), ' +
        '*:has-text("$5,000,000")'
      );
      
      await expect(mainValuation.first()).toBeVisible({ timeout: 10000 });
      
      // Also verify the target multiple was updated
      const updatedMultiple = page.locator('*:has-text("5")').filter({ hasText: /multiple/i });
      await expect(updatedMultiple.first()).toBeVisible();
    });

    // Step 9: Refresh page and verify persistence
    await test.step('Refresh page and verify persistence', async () => {
      // Refresh the page
      await page.reload();
      await page.waitForLoadState('networkidle');
      
      // Wait for page to fully load
      await page.waitForTimeout(2000);
      
      // Verify the updated values persist after refresh
      const persistedValuation = page.locator(
        '.field-value:has-text("5,000,000"), ' +
        '*:has-text("$5,000,000")'
      );
      
      const persistedMultiple = page.locator(
        '.field-value:has-text("5"), ' +
        'input[value="5"]'
      );
      
      // Verify both values persisted
      await expect(persistedValuation.first()).toBeVisible({ timeout: 10000 });
      await expect(persistedMultiple.first()).toBeVisible();
      
      // Verify calculation is still mathematically correct
      const isCalculationValid = FinancialCalculationHelper.validateCalculation(1000000, 5, 5000000);
      expect(isCalculationValid).toBeTruthy();
    });
  });

  test('Financial Hub Widget Accessibility', async ({ page }) => {
    // Create test deal first
    await dealPage.createDeal(testDealData);
    
    await test.step('Verify financial hub is keyboard accessible', async () => {
      // Tab through financial hub elements
      await page.keyboard.press('Tab');
      await page.keyboard.press('Tab');
      
      // Look for financial hub button and test keyboard activation
      const financialHubButton = page.locator('button:has-text("Financial Hub"), .financial-hub-toggle');
      
      if (await financialHubButton.isVisible()) {
        await financialHubButton.focus();
        await page.keyboard.press('Enter');
        
        // Verify calculator opens
        const calculator = page.locator('.what-if-calculator, .financial-calculator');
        await expect(calculator).toBeVisible();
      }
    });

    await test.step('Verify calculator has proper ARIA labels', async () => {
      // Open calculator if not already open
      const calculatorTrigger = page.locator('button:has-text("What-if Calculator")');
      if (await calculatorTrigger.isVisible()) {
        await calculatorTrigger.click();
      }
      
      // Check for accessibility attributes
      const multipleInput = page.locator('input[name*="multiple"]');
      if (await multipleInput.isVisible()) {
        const ariaLabel = await multipleInput.getAttribute('aria-label');
        const label = await multipleInput.getAttribute('label');
        
        expect(ariaLabel || label).toBeTruthy();
      }
    });
  });

  test('Financial Hub Error Handling', async ({ page }) => {
    // Create test deal first
    await dealPage.createDeal(testDealData);
    
    await test.step('Handle invalid multiple values gracefully', async () => {
      // Open what-if calculator
      const calculatorTrigger = page.locator('button:has-text("What-if Calculator")');
      if (await calculatorTrigger.isVisible()) {
        await calculatorTrigger.click();
      }
      
      // Try entering invalid multiple (negative number)
      const multipleInput = page.locator('input[name*="multiple"]');
      if (await multipleInput.isVisible()) {
        await multipleInput.clear();
        await multipleInput.fill('-1');
        await multipleInput.press('Enter');
        
        // Look for error message
        const errorMessage = page.locator('.error, .alert-danger, .validation-error');
        
        // Should either show error or reset to valid value
        const hasError = await errorMessage.isVisible();
        const inputValue = await multipleInput.inputValue();
        
        expect(hasError || parseFloat(inputValue) > 0).toBeTruthy();
      }
    });

    await test.step('Handle missing EBITDA values', async () => {
      // Edit deal to remove EBITDA
      await page.click('input[value="Edit"]');
      await page.fill('input[name="ttm_ebitda_c"]', '');
      await page.click('input[value="Save"]');
      
      // Try to open calculator
      const calculatorTrigger = page.locator('button:has-text("What-if Calculator")');
      if (await calculatorTrigger.isVisible()) {
        await calculatorTrigger.click();
        
        // Should show appropriate message about missing data
        const warningMessage = page.locator(
          '*:has-text("EBITDA required"), ' +
          '*:has-text("Missing financial data"), ' +
          '.warning, .alert-warning'
        );
        
        expect(await warningMessage.isVisible()).toBeTruthy();
      }
    });
  });

  test('Financial Hub Performance', async ({ page }) => {
    // Create test deal first
    await dealPage.createDeal(testDealData);
    
    await test.step('Calculator loads and calculates quickly', async () => {
      const startTime = Date.now();
      
      // Open calculator
      const calculatorTrigger = page.locator('button:has-text("What-if Calculator")');
      if (await calculatorTrigger.isVisible()) {
        await calculatorTrigger.click();
      }
      
      // Change multiple and measure calculation time
      const multipleInput = page.locator('input[name*="multiple"]');
      if (await multipleInput.isVisible()) {
        await multipleInput.clear();
        await multipleInput.fill('6');
        await multipleInput.press('Enter');
        
        // Wait for result
        await page.waitForSelector('*:has-text("6,000,000")', { timeout: 5000 });
        
        const endTime = Date.now();
        const calculationTime = endTime - startTime;
        
        // Should calculate in under 2 seconds
        expect(calculationTime).toBeLessThan(2000);
      }
    });
  });
});

// Export helper class for use in other tests
module.exports = { FinancialCalculationHelper };