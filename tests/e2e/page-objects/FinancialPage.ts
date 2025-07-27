import { BasePage } from './BasePage';
import { Page } from '@playwright/test';

export class FinancialPage extends BasePage {
  // Selectors
  private readonly financialContainer = '.financial-container, #financial-panel';
  private readonly amountInput = 'input[name="amount"]';
  private readonly currencySelect = 'select[name="currency_id"]';
  private readonly probabilityInput = 'input[name="probability"]';
  private readonly closeDateInput = 'input[name="date_closed"]';
  private readonly revenueInput = 'input[name="expected_revenue"]';
  private readonly costInput = 'input[name="estimated_cost"]';
  private readonly marginDisplay = '.margin-display, .profit-margin';
  private readonly roiDisplay = '.roi-display, .return-investment';
  private readonly financialSummary = '.financial-summary';
  private readonly calculateButton = '.calculate-financials-btn';
  private readonly forecastChart = '.forecast-chart, #financial-chart';
  private readonly paymentTermsSelect = 'select[name="payment_terms"]';
  private readonly budgetInput = 'input[name="budget_allocated"]';
  private readonly actualSpendInput = 'input[name="actual_spend"]';

  constructor(page: Page) {
    super(page);
  }

  /**
   * Navigate to financial tab (usually within deal detail view)
   */
  async gotoFinancialTab() {
    const financialTab = 'a[href*="financial"], .tab-financial';
    await this.clickWithRetry(financialTab);
    await this.waitForElement(this.financialContainer);
  }

  /**
   * Update deal amount
   */
  async updateAmount(amount: string) {
    await this.clearInput(this.amountInput);
    await this.fillWithRetry(this.amountInput, amount);
    await this.triggerCalculation();
  }

  /**
   * Update currency
   */
  async updateCurrency(currencyCode: string) {
    await this.selectOption(this.currencySelect, currencyCode);
    await this.triggerCalculation();
  }

  /**
   * Update probability
   */
  async updateProbability(probability: string) {
    await this.clearInput(this.probabilityInput);
    await this.fillWithRetry(this.probabilityInput, probability);
    await this.triggerCalculation();
  }

  /**
   * Update close date
   */
  async updateCloseDate(date: string) {
    await this.clearInput(this.closeDateInput);
    await this.fillWithRetry(this.closeDateInput, date);
    await this.triggerCalculation();
  }

  /**
   * Update expected revenue
   */
  async updateExpectedRevenue(revenue: string) {
    if (await this.elementExists(this.revenueInput)) {
      await this.clearInput(this.revenueInput);
      await this.fillWithRetry(this.revenueInput, revenue);
      await this.triggerCalculation();
    }
  }

  /**
   * Update estimated cost
   */
  async updateEstimatedCost(cost: string) {
    if (await this.elementExists(this.costInput)) {
      await this.clearInput(this.costInput);
      await this.fillWithRetry(this.costInput, cost);
      await this.triggerCalculation();
    }
  }

  /**
   * Trigger financial calculations
   */
  async triggerCalculation() {
    if (await this.elementExists(this.calculateButton)) {
      await this.clickWithRetry(this.calculateButton);
    } else {
      // Trigger by pressing Tab to leave focus
      await this.pressKey('Tab');
    }
    await this.wait(1000); // Wait for calculations
  }

  /**
   * Get calculated margin
   */
  async getMargin(): Promise<string> {
    if (await this.elementExists(this.marginDisplay)) {
      return await this.getTextContent(this.marginDisplay);
    }
    return '';
  }

  /**
   * Get calculated ROI
   */
  async getROI(): Promise<string> {
    if (await this.elementExists(this.roiDisplay)) {
      return await this.getTextContent(this.roiDisplay);
    }
    return '';
  }

  /**
   * Get financial summary
   */
  async getFinancialSummary(): Promise<Record<string, string>> {
    const summary: Record<string, string> = {};
    
    if (await this.elementExists(this.financialSummary)) {
      const summaryItems = await this.page.$$eval(
        `${this.financialSummary} .summary-item`,
        items => items.map(item => {
          const label = item.querySelector('.label')?.textContent?.trim() || '';
          const value = item.querySelector('.value')?.textContent?.trim() || '';
          return { label, value };
        })
      );
      
      summaryItems.forEach(item => {
        if (item.label && item.value) {
          summary[item.label] = item.value;
        }
      });
    }
    
    return summary;
  }

  /**
   * Get weighted amount (amount * probability)
   */
  async getWeightedAmount(): Promise<string> {
    const amount = await this.getAttribute(this.amountInput, 'value') || '0';
    const probability = await this.getAttribute(this.probabilityInput, 'value') || '0';
    
    const weightedAmount = (parseFloat(amount) * parseFloat(probability)) / 100;
    return weightedAmount.toFixed(2);
  }

  /**
   * Set payment terms
   */
  async setPaymentTerms(terms: string) {
    if (await this.elementExists(this.paymentTermsSelect)) {
      await this.selectOption(this.paymentTermsSelect, terms);
    }
  }

  /**
   * Update budget allocation
   */
  async updateBudgetAllocation(budget: string) {
    if (await this.elementExists(this.budgetInput)) {
      await this.clearInput(this.budgetInput);
      await this.fillWithRetry(this.budgetInput, budget);
      await this.triggerCalculation();
    }
  }

  /**
   * Update actual spend
   */
  async updateActualSpend(spend: string) {
    if (await this.elementExists(this.actualSpendInput)) {
      await this.clearInput(this.actualSpendInput);
      await this.fillWithRetry(this.actualSpendInput, spend);
      await this.triggerCalculation();
    }
  }

  /**
   * Get budget variance
   */
  async getBudgetVariance(): Promise<{
    allocated: number;
    actual: number;
    variance: number;
    percentage: number;
  }> {
    const allocated = parseFloat(await this.getAttribute(this.budgetInput, 'value') || '0');
    const actual = parseFloat(await this.getAttribute(this.actualSpendInput, 'value') || '0');
    const variance = allocated - actual;
    const percentage = allocated > 0 ? (variance / allocated) * 100 : 0;
    
    return {
      allocated,
      actual,
      variance,
      percentage: Math.round(percentage * 100) / 100
    };
  }

  /**
   * Export financial data
   */
  async exportFinancialData() {
    const exportButton = '.export-financial-btn, [data-action="export-financial"]';
    if (await this.elementExists(exportButton)) {
      await this.clickWithRetry(exportButton);
      await this.wait(2000); // Wait for download
    }
  }

  /**
   * Generate financial report
   */
  async generateReport(reportType: 'summary' | 'detailed' | 'forecast') {
    const reportButton = `.generate-report-btn[data-type="${reportType}"]`;
    if (await this.elementExists(reportButton)) {
      await this.clickWithRetry(reportButton);
      await this.waitForPageLoad();
    }
  }

  /**
   * View forecast chart
   */
  async viewForecastChart() {
    if (await this.elementExists(this.forecastChart)) {
      await this.scrollToElement(this.forecastChart);
      return true;
    }
    return false;
  }

  /**
   * Get chart data points (if chart is interactive)
   */
  async getChartDataPoints(): Promise<Array<{
    period: string;
    value: number;
  }>> {
    const dataPoints: Array<{ period: string; value: number }> = [];
    
    if (await this.elementExists(this.forecastChart)) {
      // This would depend on the specific chart implementation
      const chartData = await this.page.evaluate(() => {
        // Assuming chart data is available in a global variable or data attribute
        const chartElement = document.querySelector('.forecast-chart, #financial-chart');
        if (chartElement && (chartElement as any).chartData) {
          return (chartElement as any).chartData;
        }
        return null;
      });
      
      if (chartData && Array.isArray(chartData)) {
        return chartData;
      }
    }
    
    return dataPoints;
  }

  /**
   * Validate financial inputs
   */
  async validateFinancialInputs(): Promise<{
    isValid: boolean;
    errors: string[];
  }> {
    const errors: string[] = [];
    
    // Check amount
    const amount = await this.getAttribute(this.amountInput, 'value');
    if (!amount || parseFloat(amount) <= 0) {
      errors.push('Amount must be greater than 0');
    }
    
    // Check probability
    const probability = await this.getAttribute(this.probabilityInput, 'value');
    if (!probability || parseFloat(probability) < 0 || parseFloat(probability) > 100) {
      errors.push('Probability must be between 0 and 100');
    }
    
    // Check close date
    const closeDate = await this.getAttribute(this.closeDateInput, 'value');
    if (!closeDate) {
      errors.push('Close date is required');
    } else {
      const date = new Date(closeDate);
      if (date < new Date()) {
        errors.push('Close date cannot be in the past');
      }
    }
    
    return {
      isValid: errors.length === 0,
      errors
    };
  }

  /**
   * Calculate profit margin
   */
  async calculateProfitMargin(): Promise<number> {
    const revenue = parseFloat(await this.getAttribute(this.revenueInput, 'value') || '0');
    const cost = parseFloat(await this.getAttribute(this.costInput, 'value') || '0');
    
    if (revenue > 0) {
      return ((revenue - cost) / revenue) * 100;
    }
    
    return 0;
  }

  /**
   * Get all financial metrics
   */
  async getAllFinancialMetrics(): Promise<{
    amount: number;
    probability: number;
    weightedAmount: number;
    expectedRevenue: number;
    estimatedCost: number;
    profitMargin: number;
    roi: number;
    budgetVariance: {
      allocated: number;
      actual: number;
      variance: number;
      percentage: number;
    };
  }> {
    const amount = parseFloat(await this.getAttribute(this.amountInput, 'value') || '0');
    const probability = parseFloat(await this.getAttribute(this.probabilityInput, 'value') || '0');
    const expectedRevenue = parseFloat(await this.getAttribute(this.revenueInput, 'value') || '0');
    const estimatedCost = parseFloat(await this.getAttribute(this.costInput, 'value') || '0');
    
    return {
      amount,
      probability,
      weightedAmount: (amount * probability) / 100,
      expectedRevenue,
      estimatedCost,
      profitMargin: await this.calculateProfitMargin(),
      roi: expectedRevenue > 0 ? ((expectedRevenue - estimatedCost) / estimatedCost) * 100 : 0,
      budgetVariance: await this.getBudgetVariance()
    };
  }
}