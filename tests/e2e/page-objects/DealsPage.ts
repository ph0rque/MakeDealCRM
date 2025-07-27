import { BasePage } from './BasePage';
import { Page } from '@playwright/test';

export class DealsPage extends BasePage {
  // Selectors
  private readonly createDealButton = 'a[href*="module=Deals&action=EditView"]:has-text("Create")';
  private readonly dealNameInput = 'input[name="name"]';
  private readonly dealAmountInput = 'input[name="amount"]';
  private readonly dealStageSelect = 'select[name="sales_stage"]';
  private readonly dealProbabilityInput = 'input[name="probability"]';
  private readonly dealCloseDate = 'input[name="date_closed"]';
  private readonly saveButton = 'input[type="submit"][value="Save"]';
  private readonly cancelButton = 'input[type="button"][value="Cancel"]';
  private readonly listViewTable = 'table.list.view';
  private readonly searchInput = 'input[name="name_basic"]';
  private readonly searchButton = 'input[type="submit"][value="Search"]';
  private readonly clearButton = 'input[type="submit"][value="Clear"]';

  constructor(page: Page) {
    super(page);
  }

  /**
   * Navigate to Deals list view
   */
  async goto() {
    await super.goto('/index.php?module=Deals&action=index');
    await this.waitForPageLoad();
  }

  /**
   * Navigate to create deal form
   */
  async gotoCreateDeal() {
    await super.goto('/index.php?module=Deals&action=EditView');
    await this.waitForPageLoad();
  }

  /**
   * Create a new deal
   */
  async createDeal(dealData: {
    name: string;
    amount?: string;
    stage?: string;
    probability?: string;
    closeDate?: string;
  }) {
    await this.gotoCreateDeal();
    
    // Fill in deal details
    await this.fillWithRetry(this.dealNameInput, dealData.name);
    
    if (dealData.amount) {
      await this.fillWithRetry(this.dealAmountInput, dealData.amount);
    }
    
    if (dealData.stage) {
      await this.selectOption(this.dealStageSelect, dealData.stage);
    }
    
    if (dealData.probability) {
      await this.fillWithRetry(this.dealProbabilityInput, dealData.probability);
    }
    
    if (dealData.closeDate) {
      await this.fillWithRetry(this.dealCloseDate, dealData.closeDate);
    }
    
    // Save the deal
    await this.clickWithRetry(this.saveButton);
    await this.waitForNavigation();
  }

  /**
   * Search for a deal
   */
  async searchDeal(dealName: string) {
    await this.fillWithRetry(this.searchInput, dealName);
    await this.clickWithRetry(this.searchButton);
    await this.waitForPageLoad();
  }

  /**
   * Clear search
   */
  async clearSearch() {
    await this.clickWithRetry(this.clearButton);
    await this.waitForPageLoad();
  }

  /**
   * Open deal detail view
   */
  async openDeal(dealName: string) {
    const dealLink = `a:has-text("${dealName}")`;
    await this.clickWithRetry(dealLink);
    await this.waitForNavigation();
  }

  /**
   * Check if deal exists in list
   */
  async dealExists(dealName: string): Promise<boolean> {
    return await this.containsText(dealName);
  }

  /**
   * Get all deals from current page
   */
  async getAllDeals(): Promise<string[]> {
    const deals = await this.page.$$eval(
      `${this.listViewTable} tbody tr td a[href*="module=Deals&action=DetailView"]`,
      links => links.map(link => link.textContent?.trim() || '')
    );
    return deals.filter(Boolean);
  }

  /**
   * Delete deal
   */
  async deleteDeal(dealName: string) {
    await this.openDeal(dealName);
    const deleteButton = 'input[type="submit"][value="Delete"]';
    await this.clickWithRetry(deleteButton);
    await this.handleDialog(true); // Accept confirmation
    await this.waitForNavigation();
  }

  /**
   * Edit deal
   */
  async editDeal(dealName: string, updates: Partial<{
    name: string;
    amount: string;
    stage: string;
    probability: string;
    closeDate: string;
  }>) {
    await this.openDeal(dealName);
    const editButton = 'input[type="button"][value="Edit"]';
    await this.clickWithRetry(editButton);
    await this.waitForPageLoad();
    
    // Update fields
    if (updates.name) {
      await this.clearInput(this.dealNameInput);
      await this.fillWithRetry(this.dealNameInput, updates.name);
    }
    
    if (updates.amount) {
      await this.clearInput(this.dealAmountInput);
      await this.fillWithRetry(this.dealAmountInput, updates.amount);
    }
    
    if (updates.stage) {
      await this.selectOption(this.dealStageSelect, updates.stage);
    }
    
    if (updates.probability) {
      await this.clearInput(this.dealProbabilityInput);
      await this.fillWithRetry(this.dealProbabilityInput, updates.probability);
    }
    
    if (updates.closeDate) {
      await this.clearInput(this.dealCloseDate);
      await this.fillWithRetry(this.dealCloseDate, updates.closeDate);
    }
    
    // Save changes
    await this.clickWithRetry(this.saveButton);
    await this.waitForNavigation();
  }

  /**
   * Get deal details
   */
  async getDealDetails(): Promise<Record<string, string>> {
    const details: Record<string, string> = {};
    
    // Extract field values from detail view
    const fields = await this.page.$$eval(
      '.detail-view-field',
      elements => elements.map(el => ({
        label: el.querySelector('.label')?.textContent?.trim() || '',
        value: el.querySelector('.value')?.textContent?.trim() || ''
      }))
    );
    
    fields.forEach(field => {
      if (field.label && field.value) {
        details[field.label] = field.value;
      }
    });
    
    return details;
  }

  /**
   * Navigate to pipeline view
   */
  async gotoPipelineView() {
    await super.goto('/index.php?module=Deals&action=pipeline');
    await this.waitForPageLoad();
  }

  /**
   * Check if on list view
   */
  async isOnListView(): Promise<boolean> {
    return await this.elementExists(this.listViewTable);
  }

  /**
   * Check if on detail view
   */
  async isOnDetailView(): Promise<boolean> {
    return await this.elementExists('.detail-view-field');
  }

  /**
   * Check if on edit view
   */
  async isOnEditView(): Promise<boolean> {
    return await this.elementExists(this.dealNameInput) && 
           await this.elementExists(this.saveButton);
  }
}