/**
 * Base Page Object
 * Parent class for all page objects
 */

const AuthHelper = require('../helpers/auth.helper');
const NavigationHelper = require('../helpers/navigation.helper');
const WaitHelper = require('../helpers/wait.helper');
const AssertionsHelper = require('../helpers/assertions.helper');
const ScreenshotHelper = require('../helpers/screenshot.helper');

class BasePage {
  constructor(page) {
    this.page = page;
    
    // Initialize helpers
    this.auth = new AuthHelper(page);
    this.navigation = new NavigationHelper(page);
    this.wait = new WaitHelper(page);
    this.assertions = new AssertionsHelper(page);
    this.screenshot = new ScreenshotHelper(page);
    
    // Common selectors
    this.selectors = {
      // Navigation
      navbar: '.navbar, #main-menu',
      sidebar: '.sidebar, .left-nav',
      breadcrumb: '.breadcrumb, .module-path',
      
      // Forms
      saveButton: 'input[value="Save"], button:has-text("Save")',
      cancelButton: 'input[value="Cancel"], button:has-text("Cancel")',
      deleteButton: 'input[value="Delete"], button:has-text("Delete")',
      editButton: 'input[value="Edit"], button:has-text("Edit")',
      
      // Messages
      successMessage: '.alert-success, .success-message',
      errorMessage: '.alert-danger, .error-message',
      warningMessage: '.alert-warning, .warning-message',
      infoMessage: '.alert-info, .info-message',
      
      // Loading
      loader: '.loading, .loader, .spinner',
      
      // Modals
      modal: '.modal, [role="dialog"]',
      modalTitle: '.modal-title, .modal-header h2',
      modalClose: '.modal-close, button[data-dismiss="modal"]',
      modalConfirm: '.modal-footer button:has-text("Confirm"), .modal-footer button:has-text("OK")',
      
      // Tables
      dataTable: '.list-view-rounded-corners, .dataTable, table.list',
      tableRow: 'tr.listViewRow, tbody tr',
      tableHeader: 'th, thead td',
      
      // Pagination
      pagination: '.pagination, .paginationWrapper',
      nextPage: '.pagination .next, a:has-text("Next")',
      prevPage: '.pagination .prev, a:has-text("Previous")',
      
      // Search
      searchInput: 'input[name="basic_search"], input[name="query_string"], .search-input',
      searchButton: 'input[value="Search"], button:has-text("Search")',
      advancedSearchLink: 'a:has-text("Advanced"), button:has-text("Advanced Search")'
    };
  }

  /**
   * Navigate to this page
   * Should be overridden in child classes
   * @returns {Promise<void>}
   */
  async navigate() {
    throw new Error('navigate() method must be implemented in child class');
  }

  /**
   * Wait for page to load
   * @returns {Promise<void>}
   */
  async waitForPageLoad() {
    await this.wait.waitForPageReady();
  }

  /**
   * Get page title
   * @returns {Promise<string>}
   */
  async getPageTitle() {
    return await this.page.title();
  }

  /**
   * Get page URL
   * @returns {string}
   */
  getPageUrl() {
    return this.page.url();
  }

  /**
   * Fill form field
   * @param {string} fieldName - Field name attribute
   * @param {string} value - Value to fill
   * @returns {Promise<void>}
   */
  async fillField(fieldName, value) {
    const selector = `input[name="${fieldName}"], textarea[name="${fieldName}"], select[name="${fieldName}"]`;
    await this.wait.waitForElement(selector);
    
    const element = await this.page.$(selector);
    const tagName = await element.evaluate(el => el.tagName.toLowerCase());
    
    if (tagName === 'select') {
      await element.selectOption(value);
    } else {
      await element.fill(value);
    }
  }

  /**
   * Click element
   * @param {string} selector - Element selector
   * @returns {Promise<void>}
   */
  async click(selector) {
    await this.wait.waitForElement(selector);
    await this.page.click(selector);
  }

  /**
   * Double click element
   * @param {string} selector - Element selector
   * @returns {Promise<void>}
   */
  async doubleClick(selector) {
    await this.wait.waitForElement(selector);
    await this.page.dblclick(selector);
  }

  /**
   * Right click element
   * @param {string} selector - Element selector
   * @returns {Promise<void>}
   */
  async rightClick(selector) {
    await this.wait.waitForElement(selector);
    await this.page.click(selector, { button: 'right' });
  }

  /**
   * Hover over element
   * @param {string} selector - Element selector
   * @returns {Promise<void>}
   */
  async hover(selector) {
    await this.wait.waitForElement(selector);
    await this.page.hover(selector);
  }

  /**
   * Get element text
   * @param {string} selector - Element selector
   * @returns {Promise<string>}
   */
  async getText(selector) {
    await this.wait.waitForElement(selector);
    return await this.page.textContent(selector);
  }

  /**
   * Get element value
   * @param {string} selector - Element selector
   * @returns {Promise<string>}
   */
  async getValue(selector) {
    await this.wait.waitForElement(selector);
    return await this.page.inputValue(selector);
  }

  /**
   * Check if element exists
   * @param {string} selector - Element selector
   * @returns {Promise<boolean>}
   */
  async exists(selector) {
    const element = await this.page.$(selector);
    return element !== null;
  }

  /**
   * Check if element is visible
   * @param {string} selector - Element selector
   * @returns {Promise<boolean>}
   */
  async isVisible(selector) {
    return await this.page.isVisible(selector);
  }

  /**
   * Check if element is enabled
   * @param {string} selector - Element selector
   * @returns {Promise<boolean>}
   */
  async isEnabled(selector) {
    return await this.page.isEnabled(selector);
  }

  /**
   * Save form
   * @returns {Promise<void>}
   */
  async save() {
    await this.click(this.selectors.saveButton);
    await this.wait.waitForPageReady();
  }

  /**
   * Cancel form
   * @returns {Promise<void>}
   */
  async cancel() {
    await this.click(this.selectors.cancelButton);
    await this.wait.waitForPageReady();
  }

  /**
   * Delete record
   * @param {boolean} confirm - Whether to confirm deletion
   * @returns {Promise<void>}
   */
  async delete(confirm = true) {
    await this.click(this.selectors.deleteButton);
    
    if (confirm) {
      // Handle confirmation dialog
      this.page.once('dialog', async dialog => {
        await dialog.accept();
      });
    }
    
    await this.wait.waitForPageReady();
  }

  /**
   * Search for text
   * @param {string} searchText - Text to search
   * @returns {Promise<void>}
   */
  async search(searchText) {
    await this.fillField('basic_search', searchText);
    await this.click(this.selectors.searchButton);
    await this.wait.waitForPageReady();
  }

  /**
   * Get success message
   * @returns {Promise<string>}
   */
  async getSuccessMessage() {
    await this.wait.waitForElement(this.selectors.successMessage);
    return await this.getText(this.selectors.successMessage);
  }

  /**
   * Get error message
   * @returns {Promise<string>}
   */
  async getErrorMessage() {
    await this.wait.waitForElement(this.selectors.errorMessage);
    return await this.getText(this.selectors.errorMessage);
  }

  /**
   * Close modal
   * @returns {Promise<void>}
   */
  async closeModal() {
    if (await this.isVisible(this.selectors.modal)) {
      await this.click(this.selectors.modalClose);
      await this.wait.waitForElementToDisappear(this.selectors.modal);
    }
  }

  /**
   * Confirm modal
   * @returns {Promise<void>}
   */
  async confirmModal() {
    await this.click(this.selectors.modalConfirm);
    await this.wait.waitForElementToDisappear(this.selectors.modal);
  }

  /**
   * Get table data
   * @returns {Promise<Array>}
   */
  async getTableData() {
    await this.wait.waitForElement(this.selectors.dataTable);
    
    return await this.page.evaluate((rowSelector) => {
      const rows = document.querySelectorAll(rowSelector);
      return Array.from(rows).map(row => {
        const cells = row.querySelectorAll('td');
        return Array.from(cells).map(cell => cell.textContent.trim());
      });
    }, this.selectors.tableRow);
  }

  /**
   * Select table row by text
   * @param {string} text - Text to find in row
   * @returns {Promise<void>}
   */
  async selectTableRow(text) {
    const row = this.page.locator(this.selectors.tableRow).filter({ hasText: text });
    await row.click();
  }

  /**
   * Go to next page
   * @returns {Promise<void>}
   */
  async nextPage() {
    if (await this.isEnabled(this.selectors.nextPage)) {
      await this.click(this.selectors.nextPage);
      await this.wait.waitForPageReady();
    }
  }

  /**
   * Go to previous page
   * @returns {Promise<void>}
   */
  async previousPage() {
    if (await this.isEnabled(this.selectors.prevPage)) {
      await this.click(this.selectors.prevPage);
      await this.wait.waitForPageReady();
    }
  }

  /**
   * Take screenshot of current page
   * @param {string} name - Screenshot name
   * @returns {Promise<string>} Screenshot path
   */
  async takeScreenshot(name) {
    return await this.screenshot.takeScreenshot(name);
  }

  /**
   * Scroll to element
   * @param {string} selector - Element selector
   * @returns {Promise<void>}
   */
  async scrollToElement(selector) {
    await this.wait.waitForElement(selector);
    await this.page.locator(selector).scrollIntoViewIfNeeded();
  }

  /**
   * Execute JavaScript in page context
   * @param {Function} func - Function to execute
   * @param {...any} args - Arguments to pass
   * @returns {Promise<any>}
   */
  async executeScript(func, ...args) {
    return await this.page.evaluate(func, ...args);
  }

  /**
   * Wait for specific time
   * @param {number} ms - Milliseconds to wait
   * @returns {Promise<void>}
   */
  async wait(ms) {
    await this.page.waitForTimeout(ms);
  }

  /**
   * Reload page
   * @returns {Promise<void>}
   */
  async reload() {
    await this.page.reload();
    await this.wait.waitForPageReady();
  }
}

module.exports = BasePage;