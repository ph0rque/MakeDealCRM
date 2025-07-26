const BasePage = require('./BasePage');

/**
 * DealPage - Page object for deal creation, editing, and detail view interactions
 */
class DealPage extends BasePage {
  constructor(page) {
    super(page);
    
    // Selectors
    this.selectors = {
      // List view
      createButton: 'a:has-text("Create")',
      listViewTable: '.list-view-rounded-corners',
      summaryStats: '.summary-stats',
      stageFilters: '.stage-filters button',
      searchInput: 'input[name="basic_search"]',
      searchButton: 'input[value="Search"]',
      advancedSearchLink: 'a:has-text("Advanced")',
      massSelectAll: 'input[name="massall"]',
      massSelect: 'input[name="mass[]"]',
      actionSelect: 'select[name="action_select"]',
      goButton: 'input[value="Go"]',
      
      // Form fields
      nameInput: 'input[name="name"]',
      statusSelect: 'select[name="status"]',
      sourceSelect: 'select[name="source"]',
      dealValueInput: 'input[name="deal_value"]',
      ttmRevenueInput: 'input[name="ttm_revenue_c"]',
      ttmEbitdaInput: 'input[name="ttm_ebitda_c"]',
      targetMultipleInput: 'input[name="target_multiple_c"]',
      askingPriceInput: 'input[name="asking_price_c"]',
      descriptionTextarea: 'textarea[name="description"]',
      assignedUserSelect: 'select[name="assigned_user_id"]',
      
      // Buttons
      saveButton: 'input[value="Save"]',
      saveAndNewButton: 'input[value="Save and New"]',
      cancelButton: 'input[value="Cancel"]',
      editButton: 'input[value="Edit"]',
      deleteButton: 'input[value="Delete"]',
      duplicateButton: 'input[value="Duplicate"]',
      
      // Detail view
      detailViewTitle: 'h2',
      stageProgress: '.stage-progress',
      quickActionsPanel: '.quick-actions-panel',
      fieldLabel: '.field-label',
      fieldValue: '.field-value',
      activityTimeline: '.activity-timeline',
      timelineItem: '.timeline-item',
      
      // Subpanels
      subpanelTabs: '.subpanel-tabs a',
      documentsSubpanel: 'a:has-text("Documents")',
      contactsSubpanel: 'a:has-text("Contacts")',
      notesSubpanel: 'a:has-text("Notes")',
      emailsSubpanel: 'a:has-text("Emails")',
      subpanelCreateButton: '.subpanel-header button:has-text("Create")',
      subpanelSelectButton: '.subpanel-header button:has-text("Select")',
      
      // Quick actions
      sendEmailButton: 'button:has-text("Send Email")',
      logCallButton: 'button:has-text("Log Call")',
      scheduleMeetingButton: 'button:has-text("Schedule Meeting")',
      logNoteButton: 'button:has-text("Log Note")',
      
      // At-risk indicators
      warningBadge: '.badge-warning:has-text("Warning")',
      alertBadge: '.badge-danger:has-text("Alert")',
      
      // Duplicate detection
      duplicateCheckContainer: '.duplicate-check-container',
      duplicateWarning: '.duplicate-warning',
      
      // Validation
      errorMessage: '.error-message',
      successMessage: '.alert-success',
      
      // Advanced search
      advancedForm: 'form[name="advancedSearch"]',
      statusAdvancedSelect: 'select[name="status_advanced"]',
      dealValueRangeInput: 'input[name="deal_value_advanced_range_choice"]',
      
      // Email compose
      emailComposeModal: '#composeEmail',
      emailToField: 'input[name="to"]',
      emailSubjectField: 'input[name="subject"]',
      emailBodyField: 'textarea[name="body"]',
      emailSendButton: 'button:has-text("Send")',
      
      // Financial Hub & What-if Calculator
      financialHubWidget: '.financial-hub-widget, .financial-dashboard, .valuation-widget',
      financialHubButton: 'button:has-text("Financial Hub"), a:has-text("Financial Hub"), .financial-hub-toggle',
      whatIfCalculatorButton: 'button:has-text("What-if Calculator"), a:has-text("What-if Calculator"), button:has-text("Calculator"), .what-if-calculator-btn, .financial-calculator-btn',
      calculatorModal: '.what-if-calculator, .financial-calculator, .calculator-modal, div:has-text("What-if Calculator")',
      multipleInput: 'input[name*="multiple"], input[placeholder*="Multiple"], .calculator input[type="number"]:nth-of-type(2)',
      ebitdaInput: 'input[name*="ebitda"], input[placeholder*="EBITDA"], .calculator input[type="number"]:first-of-type',
      calculatorResult: '.calculator-result, .calculated-value, .valuation-result',
      calculatorSaveButton: 'button:has-text("Save"), button:has-text("Apply"), button:has-text("Update"), .calculator-save, .apply-changes',
      proposedValuationField: '.field-value:has-text("$"), .valuation-display, .proposed-valuation',
      
      // Financial fields
      ttmEbitdaDisplay: '.field-label:has-text("TTM EBITDA") + .field-value, .ttm-ebitda-value',
      targetMultipleDisplay: '.field-label:has-text("Target Multiple") + .field-value, .target-multiple-value',
      proposedValuationDisplay: '.field-label:has-text("Proposed Valuation") + .field-value, .proposed-valuation-value'
    };
  }

  /**
   * Navigate to Deals list view
   */
  async goto() {
    await this.navigate('/index.php?module=qd_Deals&action=index');
    await this.waitForElement(this.selectors.listViewTable);
  }

  /**
   * Create a new deal
   * @param {Object} dealData - The deal data
   */
  async createDeal(dealData) {
    await this.clickElement(this.selectors.createButton);
    await this.waitForElement(this.selectors.nameInput);
    
    // Fill basic information
    if (dealData.name) await this.fillField(this.selectors.nameInput, dealData.name);
    if (dealData.status) await this.selectOption(this.selectors.statusSelect, dealData.status);
    if (dealData.source) await this.selectOption(this.selectors.sourceSelect, dealData.source);
    if (dealData.dealValue) await this.fillField(this.selectors.dealValueInput, dealData.dealValue);
    
    // Fill financial information
    if (dealData.ttmRevenue) await this.fillField(this.selectors.ttmRevenueInput, dealData.ttmRevenue);
    if (dealData.ttmEbitda) await this.fillField(this.selectors.ttmEbitdaInput, dealData.ttmEbitda);
    if (dealData.targetMultiple) await this.fillField(this.selectors.targetMultipleInput, dealData.targetMultiple);
    if (dealData.askingPrice) await this.fillField(this.selectors.askingPriceInput, dealData.askingPrice);
    
    // Additional fields
    if (dealData.description) await this.fillField(this.selectors.descriptionTextarea, dealData.description);
    if (dealData.assignedUserId) await this.selectOption(this.selectors.assignedUserSelect, dealData.assignedUserId);
    
    // Save
    await this.clickElement(this.selectors.saveButton);
    await this.waitForPageLoad();
  }

  /**
   * Edit an existing deal
   */
  async editDeal() {
    await this.clickElement(this.selectors.editButton);
    await this.waitForElement(this.selectors.nameInput);
  }

  /**
   * Save deal form
   */
  async saveDeal() {
    await this.clickElement(this.selectors.saveButton);
    await this.waitForPageLoad();
  }

  /**
   * Delete current deal
   */
  async deleteDeal() {
    await this.handleAlert(true); // Pre-setup alert handler
    await this.clickElement(this.selectors.deleteButton);
    await this.waitForPageLoad();
  }

  /**
   * Search for deals
   * @param {string} searchTerm - The search term
   */
  async searchDeals(searchTerm) {
    await this.fillField(this.selectors.searchInput, searchTerm);
    await this.clickElement(this.selectors.searchButton);
    await this.waitForPageLoad();
  }

  /**
   * Advanced search for deals
   * @param {Object} searchCriteria - The search criteria
   */
  async advancedSearch(searchCriteria) {
    await this.clickElement(this.selectors.advancedSearchLink);
    await this.waitForElement(this.selectors.advancedForm);
    
    if (searchCriteria.status) {
      await this.selectOption(this.selectors.statusAdvancedSelect, searchCriteria.status);
    }
    
    if (searchCriteria.dealValueMin) {
      await this.fillField(this.selectors.dealValueRangeInput, searchCriteria.dealValueMin);
    }
    
    await this.clickElement(this.selectors.searchButton);
    await this.waitForPageLoad();
  }

  /**
   * Filter deals by stage
   * @param {string} stage - The stage to filter by
   */
  async filterByStage(stage) {
    await this.clickElement(`button:has-text("${stage}")`);
    await this.page.waitForTimeout(500); // Wait for filter animation
  }

  /**
   * Get deal count in list view
   * @returns {Promise<number>} The number of deals
   */
  async getDealCount() {
    const rows = await this.page.$$('tr.listViewRow');
    return rows.length;
  }

  /**
   * Open a deal by name
   * @param {string} dealName - The deal name
   */
  async openDeal(dealName) {
    await this.clickElement(`a:has-text("${dealName}")`);
    await this.waitForElement(this.selectors.detailViewTitle);
  }

  /**
   * Get deal title from detail view
   * @returns {Promise<string>} The deal title
   */
  async getDealTitle() {
    return await this.getText(this.selectors.detailViewTitle);
  }

  /**
   * Check if duplicate warning is shown
   * @returns {Promise<boolean>} True if duplicate warning is visible
   */
  async isDuplicateWarningShown() {
    return await this.isVisible(this.selectors.duplicateWarning);
  }

  /**
   * Get field value from detail view
   * @param {string} fieldLabel - The field label
   * @returns {Promise<string>} The field value
   */
  async getFieldValue(fieldLabel) {
    const fieldLabelElement = await this.page.$(`${this.selectors.fieldLabel}:has-text("${fieldLabel}")`);
    if (fieldLabelElement) {
      const fieldContainer = await fieldLabelElement.$('xpath=..');
      const valueElement = await fieldContainer.$(this.selectors.fieldValue);
      return await valueElement.textContent();
    }
    return '';
  }

  /**
   * Add a contact to the deal
   * @param {string} contactName - The contact name
   */
  async addContact(contactName) {
    await this.clickElement(this.selectors.contactsSubpanel);
    await this.clickElement(this.selectors.subpanelSelectButton);
    
    // Search and select contact
    await this.fillField('input[name="name_advanced"]', contactName);
    await this.clickElement(this.selectors.searchButton);
    await this.page.waitForTimeout(1000);
    
    // Select first result
    await this.clickElement('input[name="mass[]"]:first-child');
    await this.clickElement('input[value="Select"]');
    await this.waitForPageLoad();
  }

  /**
   * Send email from deal
   * @param {Object} emailData - The email data
   */
  async sendEmail(emailData) {
    await this.clickElement(this.selectors.sendEmailButton);
    await this.waitForElement(this.selectors.emailComposeModal);
    
    if (emailData.to) await this.fillField(this.selectors.emailToField, emailData.to);
    if (emailData.subject) await this.fillField(this.selectors.emailSubjectField, emailData.subject);
    if (emailData.body) await this.fillField(this.selectors.emailBodyField, emailData.body);
    
    await this.clickElement(this.selectors.emailSendButton);
    await this.waitForPageLoad();
  }

  /**
   * Log a note
   * @param {string} noteText - The note text
   */
  async logNote(noteText) {
    await this.clickElement(this.selectors.logNoteButton);
    await this.fillField('textarea[name="note_description"]', noteText);
    await this.clickElement(this.selectors.saveButton);
    await this.waitForPageLoad();
  }

  /**
   * Check if at-risk indicator is shown
   * @returns {Promise<boolean>} True if at-risk indicator is visible
   */
  async hasAtRiskIndicator() {
    const hasWarning = await this.isVisible(this.selectors.warningBadge);
    const hasAlert = await this.isVisible(this.selectors.alertBadge);
    return hasWarning || hasAlert;
  }

  /**
   * Get stage progress percentage
   * @returns {Promise<number>} The progress percentage
   */
  async getStageProgress() {
    const progressBar = await this.page.$(this.selectors.stageProgress + ' .progress-bar');
    if (progressBar) {
      const style = await progressBar.getAttribute('style');
      const match = style.match(/width:\s*(\d+)%/);
      return match ? parseInt(match[1]) : 0;
    }
    return 0;
  }

  /**
   * Perform mass update on deals
   * @param {string[]} dealIds - Array of deal IDs
   * @param {Object} updateData - The data to update
   */
  async massUpdate(dealIds, updateData) {
    // Select deals
    for (const dealId of dealIds) {
      await this.clickElement(`input[name="mass[]"][value="${dealId}"]`);
    }
    
    // Select mass update action
    await this.selectOption(this.selectors.actionSelect, 'mass_update');
    await this.clickElement(this.selectors.goButton);
    
    // Update fields
    if (updateData.status) {
      await this.selectOption(this.selectors.statusSelect, updateData.status);
    }
    
    await this.clickElement('input[value="Update"]');
    await this.waitForPageLoad();
  }

  /**
   * Export deals
   */
  async exportDeals() {
    await this.clickElement(this.selectors.massSelectAll);
    await this.selectOption(this.selectors.actionSelect, 'export');
    
    const downloadPromise = this.page.waitForEvent('download');
    await this.clickElement(this.selectors.goButton);
    
    const download = await downloadPromise;
    return download;
  }

  /**
   * Get summary statistics
   * @returns {Promise<Object>} The summary statistics
   */
  async getSummaryStats() {
    const stats = {};
    const statElements = await this.page.$$(`${this.selectors.summaryStats} .stat-item`);
    
    for (const element of statElements) {
      const label = await element.$eval('.stat-label', el => el.textContent.trim());
      const value = await element.$eval('.stat-value', el => el.textContent.trim());
      stats[label] = value;
    }
    
    return stats;
  }

  /**
   * Open Financial Hub widget
   * @returns {Promise<void>}
   */
  async openFinancialHub() {
    // Check if financial hub is already visible
    const hubVisible = await this.isVisible(this.selectors.financialHubWidget);
    if (hubVisible) {
      return;
    }

    // Look for button to open financial hub
    const hubButton = await this.page.$(this.selectors.financialHubButton);
    if (hubButton) {
      await this.clickElement(this.selectors.financialHubButton);
      await this.page.waitForTimeout(1000);
    }
  }

  /**
   * Open What-if Calculator
   * @returns {Promise<void>}
   */
  async openWhatIfCalculator() {
    await this.clickElement(this.selectors.whatIfCalculatorButton);
    await this.waitForElement(this.selectors.calculatorModal);
  }

  /**
   * Get current proposed valuation
   * @returns {Promise<string>} The proposed valuation text
   */
  async getProposedValuation() {
    return await this.getText(this.selectors.proposedValuationDisplay);
  }

  /**
   * Update multiple in what-if calculator
   * @param {string|number} newMultiple - The new multiple value
   * @returns {Promise<void>}
   */
  async updateMultipleInCalculator(newMultiple) {
    await this.waitForElement(this.selectors.multipleInput);
    await this.page.fill(this.selectors.multipleInput, '');
    await this.fillField(this.selectors.multipleInput, newMultiple.toString());
    await this.page.keyboard.press('Enter');
    await this.page.waitForTimeout(500); // Wait for calculation
  }

  /**
   * Get calculator result value
   * @returns {Promise<string>} The calculated valuation
   */
  async getCalculatorResult() {
    await this.waitForElement(this.selectors.calculatorResult);
    return await this.getText(this.selectors.calculatorResult);
  }

  /**
   * Save calculator changes
   * @returns {Promise<void>}
   */
  async saveCalculatorChanges() {
    await this.clickElement(this.selectors.calculatorSaveButton);
    await this.page.waitForTimeout(1000);
  }

  /**
   * Get TTM EBITDA value
   * @returns {Promise<string>} The TTM EBITDA value
   */
  async getTtmEbitda() {
    return await this.getText(this.selectors.ttmEbitdaDisplay);
  }

  /**
   * Get target multiple value
   * @returns {Promise<string>} The target multiple value
   */
  async getTargetMultiple() {
    return await this.getText(this.selectors.targetMultipleDisplay);
  }

  /**
   * Verify financial hub accessibility
   * @returns {Promise<boolean>} True if accessible via keyboard
   */
  async verifyFinancialHubAccessibility() {
    try {
      // Test keyboard navigation to financial hub
      await this.page.keyboard.press('Tab');
      await this.page.keyboard.press('Tab');
      
      const hubButton = await this.page.$(this.selectors.financialHubButton);
      if (hubButton) {
        await hubButton.focus();
        await this.page.keyboard.press('Enter');
        
        const calculatorVisible = await this.isVisible(this.selectors.calculatorModal);
        return calculatorVisible;
      }
      return false;
    } catch (error) {
      return false;
    }
  }

  /**
   * Check if calculator has proper ARIA labels
   * @returns {Promise<boolean>} True if properly labeled
   */
  async verifyCalculatorAccessibility() {
    await this.openWhatIfCalculator();
    
    const multipleInput = await this.page.$(this.selectors.multipleInput);
    if (multipleInput) {
      const ariaLabel = await multipleInput.getAttribute('aria-label');
      const label = await multipleInput.getAttribute('label');
      return !!(ariaLabel || label);
    }
    return false;
  }

  /**
   * Test calculator with invalid input
   * @param {string} invalidValue - Invalid value to test
   * @returns {Promise<boolean>} True if error handling works
   */
  async testCalculatorErrorHandling(invalidValue) {
    await this.openWhatIfCalculator();
    
    const multipleInput = await this.page.$(this.selectors.multipleInput);
    if (multipleInput) {
      await multipleInput.fill(invalidValue);
      await this.page.keyboard.press('Enter');
      await this.page.waitForTimeout(500);
      
      // Check for error message or input correction
      const errorVisible = await this.isVisible('.error, .alert-danger, .validation-error');
      const inputValue = await multipleInput.inputValue();
      
      return errorVisible || (parseFloat(inputValue) > 0);
    }
    return false;
  }

  /**
   * Measure calculator performance
   * @param {string|number} newMultiple - Multiple to test with
   * @returns {Promise<number>} Time taken in milliseconds
   */
  async measureCalculatorPerformance(newMultiple) {
    await this.openWhatIfCalculator();
    
    const startTime = Date.now();
    await this.updateMultipleInCalculator(newMultiple);
    
    // Wait for result to appear
    await this.waitForElement(this.selectors.calculatorResult);
    const endTime = Date.now();
    
    return endTime - startTime;
  }
}

module.exports = DealPage;