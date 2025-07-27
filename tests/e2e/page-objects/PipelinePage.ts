import { BasePage } from './BasePage';
import { Page } from '@playwright/test';

export class PipelinePage extends BasePage {
  // Selectors
  private readonly pipelineContainer = '.pipeline-container, #pipeline-view';
  private readonly stageColumns = '.pipeline-stage, .kanban-column';
  private readonly dealCards = '.deal-card, .pipeline-deal';
  private readonly dragHandle = '.drag-handle, .deal-card';
  private readonly stageHeader = '.stage-header';
  private readonly addDealButton = '.add-deal-btn, [data-action="add-deal"]';
  private readonly refreshButton = '.refresh-pipeline, [data-action="refresh"]';
  private readonly filterSelect = 'select[name="pipeline_filter"]';
  private readonly viewModeToggle = '.view-mode-toggle';

  constructor(page: Page) {
    super(page);
  }

  /**
   * Navigate to pipeline view
   */
  async goto() {
    await super.goto('/index.php?module=Deals&action=pipeline');
    await this.waitForPageLoad();
    await this.waitForPipelineLoad();
  }

  /**
   * Wait for pipeline to load completely
   */
  async waitForPipelineLoad() {
    await this.waitForElement(this.pipelineContainer);
    await this.page.waitForFunction(
      () => document.querySelectorAll('.pipeline-stage, .kanban-column').length > 0,
      { timeout: 30000 }
    );
  }

  /**
   * Get all pipeline stages
   */
  async getStages(): Promise<string[]> {
    const stages = await this.page.$$eval(
      `${this.stageHeader}`,
      headers => headers.map(h => h.textContent?.trim() || '')
    );
    return stages.filter(Boolean);
  }

  /**
   * Get deals in a specific stage
   */
  async getDealsInStage(stageName: string): Promise<string[]> {
    const stageSelector = `[data-stage="${stageName}"], :has-text("${stageName}")`;
    const dealsSelector = `${stageSelector} ${this.dealCards}`;
    
    const deals = await this.page.$$eval(
      dealsSelector,
      cards => cards.map(card => {
        const nameEl = card.querySelector('.deal-name, .card-title');
        return nameEl?.textContent?.trim() || '';
      })
    );
    return deals.filter(Boolean);
  }

  /**
   * Drag deal from one stage to another
   */
  async dragDealToStage(dealName: string, targetStage: string) {
    // Find the deal card
    const dealCard = this.page.locator(`${this.dealCards}:has-text("${dealName}")`);
    
    // Find the target stage column
    const targetColumn = this.page.locator(`${this.stageColumns}:has-text("${targetStage}")`);
    
    // Perform drag and drop
    await dealCard.dragTo(targetColumn);
    
    // Wait for the move to complete
    await this.page.waitForTimeout(2000);
    
    // Wait for any AJAX requests to complete
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Click on a deal card
   */
  async clickDeal(dealName: string) {
    const dealCard = `${this.dealCards}:has-text("${dealName}")`;
    await this.clickWithRetry(dealCard);
  }

  /**
   * Add new deal from pipeline
   */
  async addDealToStage(stageName: string, dealData: {
    name: string;
    amount?: string;
  }) {
    const stageAddButton = `[data-stage="${stageName}"] ${this.addDealButton}`;
    await this.clickWithRetry(stageAddButton);
    
    // Fill in the quick create form
    await this.fillWithRetry('input[name="name"]', dealData.name);
    if (dealData.amount) {
      await this.fillWithRetry('input[name="amount"]', dealData.amount);
    }
    
    // Save the deal
    await this.clickWithRetry('input[type="submit"][value="Save"]');
    await this.waitForPipelineLoad();
  }

  /**
   * Refresh pipeline
   */
  async refreshPipeline() {
    await this.clickWithRetry(this.refreshButton);
    await this.waitForPipelineLoad();
  }

  /**
   * Apply filter
   */
  async applyFilter(filterValue: string) {
    await this.selectOption(this.filterSelect, filterValue);
    await this.waitForPipelineLoad();
  }

  /**
   * Get deal details from card
   */
  async getDealCardDetails(dealName: string): Promise<Record<string, string>> {
    const dealCard = this.page.locator(`${this.dealCards}:has-text("${dealName}")`);
    
    const details: Record<string, string> = {
      name: dealName
    };
    
    // Extract amount if visible
    const amountEl = dealCard.locator('.deal-amount, .amount');
    if (await amountEl.isVisible()) {
      details.amount = await amountEl.textContent() || '';
    }
    
    // Extract probability if visible
    const probabilityEl = dealCard.locator('.deal-probability, .probability');
    if (await probabilityEl.isVisible()) {
      details.probability = await probabilityEl.textContent() || '';
    }
    
    // Extract close date if visible
    const closeDateEl = dealCard.locator('.deal-close-date, .close-date');
    if (await closeDateEl.isVisible()) {
      details.closeDate = await closeDateEl.textContent() || '';
    }
    
    return details;
  }

  /**
   * Check if deal exists in pipeline
   */
  async dealExistsInPipeline(dealName: string): Promise<boolean> {
    return await this.elementExists(`${this.dealCards}:has-text("${dealName}")`);
  }

  /**
   * Get current stage of a deal
   */
  async getDealStage(dealName: string): Promise<string> {
    const dealCard = this.page.locator(`${this.dealCards}:has-text("${dealName}")`);
    const stageColumn = dealCard.locator('xpath=ancestor::*[contains(@class, "pipeline-stage") or contains(@class, "kanban-column")]');
    const stageHeader = stageColumn.locator('.stage-header, .column-header');
    
    return await stageHeader.textContent() || '';
  }

  /**
   * Toggle view mode (if available)
   */
  async toggleViewMode() {
    if (await this.elementExists(this.viewModeToggle)) {
      await this.clickWithRetry(this.viewModeToggle);
      await this.waitForPipelineLoad();
    }
  }

  /**
   * Get total deals count
   */
  async getTotalDealsCount(): Promise<number> {
    return await this.page.locator(this.dealCards).count();
  }

  /**
   * Get deals count by stage
   */
  async getDealsCountByStage(): Promise<Record<string, number>> {
    const stages = await this.getStages();
    const counts: Record<string, number> = {};
    
    for (const stage of stages) {
      const deals = await this.getDealsInStage(stage);
      counts[stage] = deals.length;
    }
    
    return counts;
  }

  /**
   * Search deals in pipeline
   */
  async searchDeals(searchTerm: string) {
    const searchInput = 'input[name="pipeline_search"], .pipeline-search input';
    if (await this.elementExists(searchInput)) {
      await this.fillWithRetry(searchInput, searchTerm);
      await this.pressKey('Enter');
      await this.waitForPipelineLoad();
    }
  }

  /**
   * Clear search
   */
  async clearSearch() {
    const clearButton = '.clear-search, [data-action="clear-search"]';
    if (await this.elementExists(clearButton)) {
      await this.clickWithRetry(clearButton);
      await this.waitForPipelineLoad();
    }
  }
}