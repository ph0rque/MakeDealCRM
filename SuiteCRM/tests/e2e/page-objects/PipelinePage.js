const BasePage = require('./BasePage');

/**
 * PipelinePage - Page object for the Kanban board and drag-and-drop operations
 */
class PipelinePage extends BasePage {
  constructor(page) {
    super(page);
    
    // Selectors
    this.selectors = {
      // Pipeline board
      pipelineBoard: '.pipeline-board',
      pipelineStage: '.pipeline-stage',
      stageColumn: '.stage-column',
      stageHeader: '.stage-header',
      stageName: '.stage-name',
      stageCount: '.stage-count',
      stageValue: '.stage-value',
      stageWipLimit: '.stage-wip-limit',
      
      // Deal cards
      dealCard: '.deal-card',
      dealTitle: '.deal-title',
      dealAmount: '.deal-amount',
      dealCompany: '.deal-company',
      dealOwner: '.deal-owner',
      dealDaysInStage: '.days-in-stage',
      staleIndicator: '.stale-indicator',
      atRiskIndicator: '.at-risk-indicator',
      priorityIndicator: '.priority-indicator',
      
      // Drag and drop
      dragHandle: '.drag-handle',
      draggingClass: '.dragging',
      dragOverClass: '.drag-over',
      validDropZone: '.valid-drop-zone',
      invalidDropZone: '.invalid-drop-zone',
      dropIndicator: '.drop-indicator',
      
      // Mobile specific
      mobileStageSelector: '.mobile-stage-selector',
      mobileStageSelectorButton: '.mobile-stage-selector button',
      touchDragHandle: '.touch-drag-handle',
      
      // Filters and controls
      filterPanel: '.filter-panel',
      ownerFilter: 'select[name="owner_filter"]',
      dateRangeFilter: 'select[name="date_range_filter"]',
      valueRangeFilter: 'select[name="value_range_filter"]',
      searchFilter: 'input[name="search_filter"]',
      clearFiltersButton: 'button:has-text("Clear Filters")',
      
      // View controls
      viewToggle: '.view-toggle',
      compactViewButton: 'button[data-view="compact"]',
      standardViewButton: 'button[data-view="standard"]',
      detailedViewButton: 'button[data-view="detailed"]',
      
      // Stage actions
      stageMenu: '.stage-menu',
      stageMenuButton: '.stage-menu-button',
      addDealToStageButton: '.add-deal-to-stage',
      stageSettingsButton: '.stage-settings',
      
      // WIP limit warning
      wipWarningDialog: '.wip-warning-dialog',
      wipWarningMessage: '.wip-warning-message',
      wipProceedButton: '.wip-warning-dialog button:has-text("Proceed")',
      wipCancelButton: '.wip-warning-dialog button:has-text("Cancel")',
      
      // Deal quick actions
      dealQuickActions: '.deal-quick-actions',
      quickEditButton: '.quick-edit',
      quickViewButton: '.quick-view',
      quickDeleteButton: '.quick-delete',
      
      // Notifications
      notification: '.notification',
      successNotification: '.notification.success',
      errorNotification: '.notification.error',
      warningNotification: '.notification.warning',
      
      // Loading states
      loadingOverlay: '.loading-overlay',
      skeletonCard: '.skeleton-card',
      
      // Summary bar
      summaryBar: '.pipeline-summary',
      totalDealsCount: '.total-deals-count',
      totalPipelineValue: '.total-pipeline-value',
      averageDealSize: '.average-deal-size',
      conversionRate: '.conversion-rate',
      
      // Stage transition rules
      transitionRulesModal: '.transition-rules-modal',
      allowedTransitions: '.allowed-transitions',
      blockedTransitions: '.blocked-transitions',
      
      // Performance indicators
      velocityIndicator: '.velocity-indicator',
      stageConversionRate: '.stage-conversion-rate',
      averageTimeInStage: '.average-time-in-stage',
      
      // Tooltips
      tooltip: '.tooltip',
      tooltipContent: '.tooltip-content',
      
      // Accessibility
      ariaLiveRegion: '[aria-live="polite"]',
      skipToContent: '.skip-to-content',
      keyboardShortcuts: '.keyboard-shortcuts'
    };
  }

  /**
   * Navigate to Pipeline view
   */
  async goto() {
    await this.navigate('/index.php?module=qd_Deals&action=pipeline');
    await this.waitForElement(this.selectors.pipelineBoard);
  }

  /**
   * Get all stages
   * @returns {Promise<Object[]>} Array of stage information
   */
  async getStages() {
    const stages = [];
    const stageElements = await this.page.$$(this.selectors.pipelineStage);
    
    for (const stageElement of stageElements) {
      const stage = {
        name: await stageElement.$eval(this.selectors.stageName, el => el.textContent.trim()),
        count: await stageElement.$eval(this.selectors.stageCount, el => parseInt(el.textContent) || 0),
        value: await stageElement.$eval(this.selectors.stageValue, el => el.textContent.trim()),
        dataStage: await stageElement.getAttribute('data-stage'),
        wipLimit: await stageElement.getAttribute('data-wip-limit')
      };
      stages.push(stage);
    }
    
    return stages;
  }

  /**
   * Get deals in a specific stage
   * @param {string} stageName - The stage name
   * @returns {Promise<Object[]>} Array of deals in the stage
   */
  async getDealsInStage(stageName) {
    const stageElement = await this.getStageElement(stageName);
    const deals = [];
    const dealCards = await stageElement.$$(this.selectors.dealCard);
    
    for (const card of dealCards) {
      const deal = {
        title: await card.$eval(this.selectors.dealTitle, el => el.textContent.trim()),
        amount: await card.$eval(this.selectors.dealAmount, el => el.textContent.trim()),
        company: await card.$eval(this.selectors.dealCompany, el => el.textContent.trim()).catch(() => ''),
        owner: await card.$eval(this.selectors.dealOwner, el => el.textContent.trim()).catch(() => ''),
        daysInStage: await card.$eval(this.selectors.dealDaysInStage, el => parseInt(el.textContent) || 0).catch(() => 0),
        isStale: await card.$(this.selectors.staleIndicator).then(el => el !== null).catch(() => false),
        isAtRisk: await card.$(this.selectors.atRiskIndicator).then(el => el !== null).catch(() => false),
        id: await card.getAttribute('data-deal-id')
      };
      deals.push(deal);
    }
    
    return deals;
  }

  /**
   * Get stage element by name
   * @param {string} stageName - The stage name
   * @returns {Promise<ElementHandle>} The stage element
   */
  async getStageElement(stageName) {
    return await this.page.$(`${this.selectors.pipelineStage}[data-stage="${stageName}"]`);
  }

  /**
   * Get deal card element
   * @param {string} dealName - The deal name
   * @returns {Promise<ElementHandle>} The deal card element
   */
  async getDealCard(dealName) {
    return await this.page.$(`${this.selectors.dealCard}:has(${this.selectors.dealTitle}:has-text("${dealName}"))`);
  }

  /**
   * Drag and drop deal between stages
   * @param {string} dealName - The deal name
   * @param {string} targetStageName - The target stage name
   */
  async dragDealToStage(dealName, targetStageName) {
    const dealCard = await this.getDealCard(dealName);
    const targetStage = await this.getStageElement(targetStageName);
    
    if (dealCard && targetStage) {
      // Start drag
      await dealCard.hover();
      await this.page.mouse.down();
      
      // Move to target
      await targetStage.hover();
      
      // Wait for drop zone indication
      await this.page.waitForSelector(this.selectors.validDropZone, { timeout: 2000 }).catch(() => {});
      
      // Drop
      await this.page.mouse.up();
      
      // Wait for animation and update
      await this.page.waitForTimeout(500);
      await this.waitForNetworkIdle();
    }
  }

  /**
   * Mobile-specific stage move
   * @param {string} dealName - The deal name
   * @param {string} targetStageName - The target stage name
   */
  async moveDealMobile(dealName, targetStageName) {
    const dealCard = await this.getDealCard(dealName);
    
    if (dealCard) {
      // Tap to select deal
      await dealCard.tap();
      await this.waitForElement(this.selectors.mobileStageSelector);
      
      // Select new stage
      await this.clickElement(`${this.selectors.mobileStageSelectorButton}[data-stage="${targetStageName}"]`);
      await this.waitForPageLoad();
    }
  }

  /**
   * Handle WIP limit warning
   * @param {boolean} proceed - Whether to proceed or cancel
   */
  async handleWipWarning(proceed = true) {
    if (await this.isVisible(this.selectors.wipWarningDialog)) {
      if (proceed) {
        await this.clickElement(this.selectors.wipProceedButton);
      } else {
        await this.clickElement(this.selectors.wipCancelButton);
      }
      await this.page.waitForTimeout(300);
    }
  }

  /**
   * Filter deals
   * @param {Object} filters - Filter criteria
   */
  async filterDeals(filters) {
    if (filters.owner) await this.selectOption(this.selectors.ownerFilter, filters.owner);
    if (filters.dateRange) await this.selectOption(this.selectors.dateRangeFilter, filters.dateRange);
    if (filters.valueRange) await this.selectOption(this.selectors.valueRangeFilter, filters.valueRange);
    if (filters.search) await this.fillField(this.selectors.searchFilter, filters.search);
    
    await this.page.waitForTimeout(500); // Wait for debounce
    await this.waitForNetworkIdle();
  }

  /**
   * Clear all filters
   */
  async clearFilters() {
    await this.clickElement(this.selectors.clearFiltersButton);
    await this.waitForNetworkIdle();
  }

  /**
   * Change view mode
   * @param {string} viewMode - The view mode (compact, standard, detailed)
   */
  async changeViewMode(viewMode) {
    const buttonSelector = {
      compact: this.selectors.compactViewButton,
      standard: this.selectors.standardViewButton,
      detailed: this.selectors.detailedViewButton
    }[viewMode];
    
    if (buttonSelector) {
      await this.clickElement(buttonSelector);
      await this.page.waitForTimeout(300); // Wait for view transition
    }
  }

  /**
   * Get pipeline summary statistics
   * @returns {Promise<Object>} Summary statistics
   */
  async getSummaryStats() {
    return {
      totalDeals: await this.getText(this.selectors.totalDealsCount),
      totalValue: await this.getText(this.selectors.totalPipelineValue),
      averageDealSize: await this.getText(this.selectors.averageDealSize),
      conversionRate: await this.getText(this.selectors.conversionRate)
    };
  }

  /**
   * Check if stage has reached WIP limit
   * @param {string} stageName - The stage name
   * @returns {Promise<boolean>} True if at WIP limit
   */
  async isStageAtWipLimit(stageName) {
    const stage = await this.getStageElement(stageName);
    if (stage) {
      const wipLimit = parseInt(await stage.getAttribute('data-wip-limit') || '0');
      const currentCount = await stage.$$eval(this.selectors.dealCard, cards => cards.length);
      return wipLimit > 0 && currentCount >= wipLimit;
    }
    return false;
  }

  /**
   * Get stale deals (in stage > 7 days)
   * @returns {Promise<Object[]>} Array of stale deals
   */
  async getStaleDeals() {
    const staleDeals = [];
    const staleCards = await this.page.$$(`${this.selectors.dealCard}:has(${this.selectors.staleIndicator})`);
    
    for (const card of staleCards) {
      const deal = {
        title: await card.$eval(this.selectors.dealTitle, el => el.textContent.trim()),
        daysInStage: await card.$eval(this.selectors.dealDaysInStage, el => parseInt(el.textContent) || 0),
        stage: await card.evaluate(el => el.closest('.pipeline-stage').getAttribute('data-stage'))
      };
      staleDeals.push(deal);
    }
    
    return staleDeals;
  }

  /**
   * Quick edit deal from pipeline
   * @param {string} dealName - The deal name
   */
  async quickEditDeal(dealName) {
    const dealCard = await this.getDealCard(dealName);
    if (dealCard) {
      await dealCard.hover();
      await this.clickElement(`${this.selectors.dealQuickActions} ${this.selectors.quickEditButton}`);
      await this.waitForElement('.quick-edit-modal');
    }
  }

  /**
   * Add new deal to stage
   * @param {string} stageName - The stage name
   */
  async addDealToStage(stageName) {
    const stage = await this.getStageElement(stageName);
    if (stage) {
      const menuButton = await stage.$(this.selectors.stageMenuButton);
      await menuButton.click();
      await this.clickElement(this.selectors.addDealToStageButton);
      await this.waitForPageLoad();
    }
  }

  /**
   * Check if deal can transition to stage
   * @param {string} dealName - The deal name
   * @param {string} targetStageName - The target stage name
   * @returns {Promise<boolean>} True if transition is allowed
   */
  async canTransitionToStage(dealName, targetStageName) {
    const dealCard = await this.getDealCard(dealName);
    if (dealCard) {
      await dealCard.hover();
      await this.page.mouse.down();
      
      const targetStage = await this.getStageElement(targetStageName);
      await targetStage.hover();
      
      const isValidDropZone = await this.page.$(this.selectors.validDropZone) !== null;
      
      await this.page.mouse.up();
      return isValidDropZone;
    }
    return false;
  }

  /**
   * Get stage performance metrics
   * @param {string} stageName - The stage name
   * @returns {Promise<Object>} Stage metrics
   */
  async getStageMetrics(stageName) {
    const stage = await this.getStageElement(stageName);
    if (stage) {
      return {
        velocity: await stage.$eval(this.selectors.velocityIndicator, el => el.textContent.trim()).catch(() => ''),
        conversionRate: await stage.$eval(this.selectors.stageConversionRate, el => el.textContent.trim()).catch(() => ''),
        averageTime: await stage.$eval(this.selectors.averageTimeInStage, el => el.textContent.trim()).catch(() => '')
      };
    }
    return {};
  }

  /**
   * Keyboard navigation - select deal
   * @param {string} dealName - The deal name
   */
  async selectDealWithKeyboard(dealName) {
    const dealCard = await this.getDealCard(dealName);
    if (dealCard) {
      await dealCard.focus();
      await this.pressKey('Enter');
    }
  }

  /**
   * Keyboard navigation - move deal
   * @param {string} direction - The direction (ArrowLeft, ArrowRight)
   */
  async moveDealWithKeyboard(direction) {
    await this.pressKey(direction);
    await this.pressKey('Enter');
    await this.waitForNetworkIdle();
  }

  /**
   * Check if pipeline is loading
   * @returns {Promise<boolean>} True if loading
   */
  async isLoading() {
    return await this.isVisible(this.selectors.loadingOverlay) || 
           await this.isVisible(this.selectors.skeletonCard);
  }

  /**
   * Wait for pipeline to load
   */
  async waitForPipelineLoad() {
    await this.page.waitForSelector(this.selectors.loadingOverlay, { state: 'hidden' });
    await this.page.waitForSelector(this.selectors.skeletonCard, { state: 'hidden' });
    await this.waitForElement(this.selectors.dealCard);
  }

  /**
   * Get notification message
   * @returns {Promise<string>} The notification message
   */
  async getNotificationMessage() {
    if (await this.isVisible(this.selectors.notification)) {
      return await this.getText(this.selectors.notification);
    }
    return '';
  }

  /**
   * Check if notification is shown
   * @param {string} type - The notification type (success, error, warning)
   * @returns {Promise<boolean>} True if notification is shown
   */
  async hasNotification(type) {
    const selector = {
      success: this.selectors.successNotification,
      error: this.selectors.errorNotification,
      warning: this.selectors.warningNotification
    }[type];
    
    return selector ? await this.isVisible(selector) : false;
  }

  /**
   * Get accessibility announcement
   * @returns {Promise<string>} The aria-live announcement
   */
  async getAriaAnnouncement() {
    return await this.getText(this.selectors.ariaLiveRegion);
  }

  /**
   * Open keyboard shortcuts help
   */
  async openKeyboardShortcuts() {
    await this.pressKey('?');
    await this.waitForElement(this.selectors.keyboardShortcuts);
  }
}

module.exports = PipelinePage;