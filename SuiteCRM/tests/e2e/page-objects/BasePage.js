const { expect } = require('@playwright/test');

/**
 * BasePage - Parent class for all page objects
 * Contains common functionality shared across all pages
 */
class BasePage {
  constructor(page) {
    this.page = page;
    this.baseURL = 'http://localhost:8080';
  }

  /**
   * Navigate to a specific URL path
   * @param {string} path - The path to navigate to
   */
  async navigate(path = '') {
    await this.page.goto(`${this.baseURL}${path}`);
  }

  /**
   * Wait for page to be fully loaded
   */
  async waitForPageLoad() {
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Wait for an element to be visible
   * @param {string} selector - The selector to wait for
   * @param {number} timeout - Timeout in milliseconds
   */
  async waitForElement(selector, timeout = 5000) {
    await this.page.waitForSelector(selector, { state: 'visible', timeout });
  }

  /**
   * Click an element with retry logic
   * @param {string} selector - The selector to click
   */
  async clickElement(selector) {
    await this.waitForElement(selector);
    await this.page.click(selector);
  }

  /**
   * Fill a form field
   * @param {string} selector - The selector of the input
   * @param {string} value - The value to fill
   */
  async fillField(selector, value) {
    await this.waitForElement(selector);
    await this.page.fill(selector, value);
  }

  /**
   * Select an option from a dropdown
   * @param {string} selector - The selector of the select element
   * @param {string} value - The value to select
   */
  async selectOption(selector, value) {
    await this.waitForElement(selector);
    await this.page.selectOption(selector, value);
  }

  /**
   * Get text content of an element
   * @param {string} selector - The selector to get text from
   * @returns {Promise<string>} The text content
   */
  async getText(selector) {
    await this.waitForElement(selector);
    return await this.page.textContent(selector);
  }

  /**
   * Check if an element is visible
   * @param {string} selector - The selector to check
   * @returns {Promise<boolean>} True if visible, false otherwise
   */
  async isVisible(selector) {
    try {
      await this.page.waitForSelector(selector, { state: 'visible', timeout: 3000 });
      return true;
    } catch {
      return false;
    }
  }

  /**
   * Take a screenshot
   * @param {string} name - The name of the screenshot file
   */
  async takeScreenshot(name) {
    await this.page.screenshot({ path: `screenshots/${name}.png`, fullPage: true });
  }

  /**
   * Handle alert dialogs
   * @param {boolean} accept - Whether to accept or dismiss the alert
   * @param {string} text - Optional text to enter in prompt
   */
  async handleAlert(accept = true, text = '') {
    this.page.on('dialog', async dialog => {
      if (text && dialog.type() === 'prompt') {
        await dialog.accept(text);
      } else if (accept) {
        await dialog.accept();
      } else {
        await dialog.dismiss();
      }
    });
  }

  /**
   * Wait for network idle
   */
  async waitForNetworkIdle() {
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Get all text content from multiple elements
   * @param {string} selector - The selector for multiple elements
   * @returns {Promise<string[]>} Array of text contents
   */
  async getAllTexts(selector) {
    await this.waitForElement(selector);
    return await this.page.$$eval(selector, elements => 
      elements.map(el => el.textContent.trim())
    );
  }

  /**
   * Check if element is enabled
   * @param {string} selector - The selector to check
   * @returns {Promise<boolean>} True if enabled, false otherwise
   */
  async isEnabled(selector) {
    await this.waitForElement(selector);
    return await this.page.isEnabled(selector);
  }

  /**
   * Press keyboard key
   * @param {string} key - The key to press
   */
  async pressKey(key) {
    await this.page.keyboard.press(key);
  }

  /**
   * Upload file
   * @param {string} selector - The file input selector
   * @param {string} filePath - The path to the file
   */
  async uploadFile(selector, filePath) {
    await this.waitForElement(selector);
    await this.page.setInputFiles(selector, filePath);
  }

  /**
   * Scroll to element
   * @param {string} selector - The selector to scroll to
   */
  async scrollToElement(selector) {
    await this.waitForElement(selector);
    await this.page.$eval(selector, element => element.scrollIntoView());
  }

  /**
   * Get attribute value
   * @param {string} selector - The selector
   * @param {string} attribute - The attribute name
   * @returns {Promise<string>} The attribute value
   */
  async getAttribute(selector, attribute) {
    await this.waitForElement(selector);
    return await this.page.getAttribute(selector, attribute);
  }
}

module.exports = BasePage;