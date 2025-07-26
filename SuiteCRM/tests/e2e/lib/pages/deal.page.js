/**
 * Deal Page Object
 * Encapsulates all Deal module page interactions
 */

const BasePage = require('./base.page');

class DealPage extends BasePage {
  constructor(page) {
    super(page);
    
    // Deal-specific selectors
    this.dealSelectors = {
      // List view
      dealList: '.list-view-rounded-corners',
      summaryStats: '.summary-stats',
      stageFilter: '.stage-filter-button',
      pipelineView: '.pipeline-view',
      
      // Form fields
      nameField: 'input[name="name"]',
      statusField: 'select[name="status"]',
      sourceField: 'select[name="source"]',
      dealValueField: 'input[name="deal_value"]',
      ttmRevenueField: 'input[name="ttm_revenue_c"]',
      ttmEbitdaField: 'input[name="ttm_ebitda_c"]',
      targetMultipleField: 'input[name="target_multiple_c"]',
      askingPriceField: 'input[name="asking_price_c"]',
      descriptionField: 'textarea[name="description"]',
      
      // Detail view
      stageProgress: '.stage-progress',
      quickActionsPanel: '.quick-actions-panel',
      financialInfo: '.financial-information',
      valuationDisplay: '.field-value:has-text("$")',
      
      // Duplicate detection
      duplicateContainer: '.duplicate-check-container',
      duplicateWarning: '.duplicate-warning',
      
      // At-risk indicators
      warningBadge: '.badge-warning',
      alertBadge: '.badge-danger',
      
      // Activity timeline
      activityTimeline: '.activity-timeline',
      timelineItem: '.timeline-item',
      
      // Subpanels
      documentsSubpanel: 'a:has-text("Documents")',
      contactsSubpanel: 'a:has-text("Contacts")',
      activitiesSubpanel: 'a:has-text("Activities")'
    };
  }

  /**
   * Navigate to Deals module
   * @returns {Promise<void>}
   */
  async navigate() {
    await this.navigation.navigateToModule('Deals');
    await this.wait.waitForElement(this.dealSelectors.dealList);
  }

  /**
   * Navigate to create deal form
   * @returns {Promise<void>}
   */
  async navigateToCreate() {
    await this.navigate();
    await this.click('a:has-text("Create"), button:has-text("Create")');
    await this.wait.waitForElement('h2:has-text("Create Deal")');
  }

  /**
   * Create a new deal
   * @param {Object} dealData - Deal data
   * @returns {Promise<void>}
   */
  async createDeal(dealData) {
    await this.navigateToCreate();
    
    // Fill basic information
    if (dealData.name) {
      await this.fillField('name', dealData.name);
    }
    if (dealData.status) {
      await this.fillField('status', dealData.status);
    }
    if (dealData.source) {
      await this.fillField('source', dealData.source);
    }
    if (dealData.deal_value) {
      await this.fillField('deal_value', dealData.deal_value);
    }
    
    // Fill financial information
    if (dealData.ttm_revenue) {
      await this.fillField('ttm_revenue_c', dealData.ttm_revenue);
    }
    if (dealData.ttm_ebitda) {
      await this.fillField('ttm_ebitda_c', dealData.ttm_ebitda);
    }
    if (dealData.target_multiple) {
      await this.fillField('target_multiple_c', dealData.target_multiple);
    }
    if (dealData.asking_price) {
      await this.fillField('asking_price_c', dealData.asking_price);
    }
    
    // Fill description
    if (dealData.description) {
      await this.fillField('description', dealData.description);
    }
    
    // Save
    await this.save();
    
    // Wait for detail view
    await this.wait.waitForElement('h2:has-text("' + dealData.name + '")');
  }

  /**
   * Update deal status
   * @param {string} dealName - Deal name
   * @param {string} newStatus - New status
   * @returns {Promise<void>}
   */
  async updateDealStatus(dealName, newStatus) {
    await this.navigation.navigateToEditView('Deals', dealName);
    await this.fillField('status', newStatus);
    await this.save();
  }

  /**
   * Search for deals
   * @param {string} searchText - Search text
   * @returns {Promise<void>}
   */
  async searchDeals(searchText) {
    await this.navigate();
    await this.search(searchText);
  }

  /**
   * Filter deals by stage
   * @param {string} stage - Stage name
   * @returns {Promise<void>}
   */
  async filterByStage(stage) {
    await this.navigate();
    await this.click(`button:has-text("${stage}")`);
    await this.wait.waitForPageReady();
  }

  /**
   * Get deal count from summary stats
   * @returns {Promise<number>}
   */
  async getDealCount() {
    await this.wait.waitForElement(this.dealSelectors.summaryStats);
    const statsText = await this.getText(this.dealSelectors.summaryStats);
    const match = statsText.match(/(\d+)\s+deals?/i);
    return match ? parseInt(match[1]) : 0;
  }

  /**
   * Check if duplicate warning is shown
   * @returns {Promise<boolean>}
   */
  async isDuplicateWarningVisible() {
    await this.wait(1000); // Wait for duplicate check
    return await this.isVisible(this.dealSelectors.duplicateContainer);
  }

  /**
   * Get at-risk deal count
   * @returns {Promise<Object>}
   */
  async getAtRiskDealCount() {
    await this.navigate();
    
    const warningCount = await this.page.$$(this.dealSelectors.warningBadge).then(els => els.length);
    const alertCount = await this.page.$$(this.dealSelectors.alertBadge).then(els => els.length);
    
    return {
      warning: warningCount,
      alert: alertCount,
      total: warningCount + alertCount
    };
  }

  /**
   * Add activity to deal
   * @param {string} dealName - Deal name
   * @param {string} activityType - Activity type (note, call, meeting)
   * @param {string} description - Activity description
   * @returns {Promise<void>}
   */
  async addActivity(dealName, activityType, description) {
    await this.navigation.navigateToDetailView('Deals', dealName);
    
    switch (activityType) {
      case 'note':
        await this.click('button:has-text("Log Note")');
        await this.fillField('note_description', description);
        break;
      case 'call':
        await this.click('button:has-text("Log Call")');
        await this.fillField('call_description', description);
        break;
      case 'meeting':
        await this.click('button:has-text("Schedule Meeting")');
        await this.fillField('meeting_description', description);
        break;
    }
    
    await this.save();
  }

  /**
   * Attach document to deal
   * @param {string} dealName - Deal name
   * @param {string} documentName - Document name
   * @param {string} filePath - File path
   * @returns {Promise<void>}
   */
  async attachDocument(dealName, documentName, filePath) {
    await this.navigation.navigateToDetailView('Deals', dealName);
    await this.click(this.dealSelectors.documentsSubpanel);
    await this.click('.subpanel-header button:has-text("Create")');
    
    await this.fillField('document_name', documentName);
    
    const fileInput = await this.page.$('input[type="file"]');
    await fileInput.setInputFiles(filePath);
    
    await this.save();
  }

  /**
   * Link contact to deal
   * @param {string} dealName - Deal name
   * @param {string} contactName - Contact name
   * @returns {Promise<void>}
   */
  async linkContact(dealName, contactName) {
    await this.navigation.navigateToDetailView('Deals', dealName);
    await this.click(this.dealSelectors.contactsSubpanel);
    await this.click('.subpanel-header button:has-text("Select")');
    
    await this.fillField('name_advanced', contactName);
    await this.click('input[value="Search"]');
    await this.click('input[name="mass[]"]:first-child');
    await this.click('input[value="Select"]');
    
    await this.wait.waitForPageReady();
  }

  /**
   * Get deal valuation
   * @param {string} dealName - Deal name
   * @returns {Promise<string>}
   */
  async getDealValuation(dealName) {
    await this.navigation.navigateToDetailView('Deals', dealName);
    const valuationElement = await this.page.$(this.dealSelectors.valuationDisplay);
    return await valuationElement.textContent();
  }

  /**
   * Export deals
   * @param {boolean} selectAll - Whether to select all deals
   * @returns {Promise<void>}
   */
  async exportDeals(selectAll = true) {
    await this.navigate();
    
    if (selectAll) {
      await this.click('input[name="massall"]');
    }
    
    await this.page.selectOption('select[name="action_select"]', 'export');
    
    const downloadPromise = this.page.waitForEvent('download');
    await this.click('input[value="Go"]');
    
    const download = await downloadPromise;
    return download;
  }

  /**
   * Perform mass update on deals
   * @param {Array<string>} dealNames - Deal names to update
   * @param {Object} updateData - Update data
   * @returns {Promise<void>}
   */
  async massUpdateDeals(dealNames, updateData) {
    await this.navigate();
    
    // Select deals
    for (const dealName of dealNames) {
      const row = this.page.locator('tr').filter({ hasText: dealName });
      await row.locator('input[name="mass[]"]').check();
    }
    
    // Perform mass update
    await this.page.selectOption('select[name="action_select"]', 'mass_update');
    await this.click('input[value="Go"]');
    
    // Update fields
    for (const [field, value] of Object.entries(updateData)) {
      await this.fillField(field, value);
    }
    
    await this.click('input[value="Update"]');
    await this.wait.waitForPageReady();
  }

  /**
   * Get stage progress
   * @param {string} dealName - Deal name
   * @returns {Promise<Object>}
   */
  async getStageProgress(dealName) {
    await this.navigation.navigateToDetailView('Deals', dealName);
    
    const progressBar = await this.page.$(this.dealSelectors.stageProgress);
    const progressText = await progressBar.textContent();
    const progressStyle = await progressBar.getAttribute('style');
    
    const percentMatch = progressStyle.match(/width:\s*(\d+)%/);
    const percent = percentMatch ? parseInt(percentMatch[1]) : 0;
    
    return {
      text: progressText.trim(),
      percentage: percent
    };
  }

  /**
   * Switch to pipeline view
   * @returns {Promise<void>}
   */
  async switchToPipelineView() {
    await this.navigate();
    await this.click('button:has-text("Pipeline View"), a:has-text("Pipeline")');
    await this.wait.waitForElement(this.dealSelectors.pipelineView);
  }

  /**
   * Drag deal to different stage in pipeline
   * @param {string} dealName - Deal name
   * @param {string} targetStage - Target stage
   * @returns {Promise<void>}
   */
  async dragDealToStage(dealName, targetStage) {
    await this.switchToPipelineView();
    
    const dealCard = this.page.locator('.deal-card').filter({ hasText: dealName });
    const targetColumn = this.page.locator('.pipeline-column').filter({ hasText: targetStage });
    
    await dealCard.dragTo(targetColumn);
    await this.wait.waitForPageReady();
  }
}

module.exports = DealPage;