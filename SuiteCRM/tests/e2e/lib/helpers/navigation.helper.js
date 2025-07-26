/**
 * Navigation Helper
 * Provides common navigation utilities for E2E tests
 */

class NavigationHelper {
  constructor(page) {
    this.page = page;
  }

  /**
   * Navigate to a module
   * @param {string} moduleName - Name of the module (e.g., 'Deals', 'Contacts')
   * @returns {Promise<void>}
   */
  async navigateToModule(moduleName) {
    // First try direct navigation via URL
    const moduleUrl = `/index.php?module=${moduleName}&action=index`;
    await this.page.goto(moduleUrl);
    
    // Wait for module to load
    await this.page.waitForSelector('.module-title-text, .moduleTitle, h2', {
      state: 'visible',
      timeout: 10000
    });
  }

  /**
   * Navigate to module via menu
   * @param {string} menuCategory - Menu category (e.g., 'Sales', 'Marketing')
   * @param {string} moduleName - Module name
   * @returns {Promise<void>}
   */
  async navigateViaMenu(menuCategory, moduleName) {
    // Click on menu category
    await this.page.click(`a:has-text("${menuCategory}"), li:has-text("${menuCategory}") > a`);
    
    // Wait for submenu
    await this.page.waitForTimeout(500);
    
    // Click on module
    await this.page.click(`a:has-text("${moduleName}")`);
    
    // Wait for navigation
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Navigate to create form for a module
   * @param {string} moduleName - Module name
   * @returns {Promise<void>}
   */
  async navigateToCreate(moduleName) {
    await this.navigateToModule(moduleName);
    
    // Click create button
    await this.page.click('a:has-text("Create"), button:has-text("Create"), input[value="Create"]');
    
    // Wait for create form
    await this.page.waitForSelector('h2:has-text("Create"), .edit-view-row', {
      state: 'visible',
      timeout: 10000
    });
  }

  /**
   * Navigate to list view
   * @param {string} moduleName - Module name
   * @returns {Promise<void>}
   */
  async navigateToListView(moduleName) {
    await this.navigateToModule(moduleName);
    
    // Ensure we're in list view
    const listViewButton = await this.page.$('a:has-text("List"), button:has-text("List")');
    if (listViewButton) {
      await listViewButton.click();
      await this.page.waitForLoadState('networkidle');
    }
  }

  /**
   * Navigate to detail view of a record
   * @param {string} moduleName - Module name
   * @param {string} recordName - Record name/identifier
   * @returns {Promise<void>}
   */
  async navigateToDetailView(moduleName, recordName) {
    await this.navigateToListView(moduleName);
    
    // Click on record
    await this.page.click(`a:has-text("${recordName}"), td:has-text("${recordName}") a`);
    
    // Wait for detail view
    await this.page.waitForSelector('.detail-view, .detail-layout, h2', {
      state: 'visible',
      timeout: 10000
    });
  }

  /**
   * Navigate to edit view of a record
   * @param {string} moduleName - Module name
   * @param {string} recordName - Record name
   * @returns {Promise<void>}
   */
  async navigateToEditView(moduleName, recordName) {
    await this.navigateToDetailView(moduleName, recordName);
    
    // Click edit button
    await this.page.click('input[value="Edit"], button:has-text("Edit"), a:has-text("Edit")');
    
    // Wait for edit form
    await this.page.waitForSelector('.edit-view-row, form[name="EditView"]', {
      state: 'visible',
      timeout: 10000
    });
  }

  /**
   * Navigate to home/dashboard
   * @returns {Promise<void>}
   */
  async navigateToHome() {
    await this.page.goto('/');
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Navigate back
   * @returns {Promise<void>}
   */
  async goBack() {
    await this.page.goBack();
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Navigate forward
   * @returns {Promise<void>}
   */
  async goForward() {
    await this.page.goForward();
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Refresh current page
   * @returns {Promise<void>}
   */
  async refresh() {
    await this.page.reload();
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Get current module from URL or page
   * @returns {Promise<string>}
   */
  async getCurrentModule() {
    const url = this.page.url();
    const moduleMatch = url.match(/module=([^&]+)/);
    if (moduleMatch) {
      return moduleMatch[1];
    }
    
    // Try to get from page title
    const titleElement = await this.page.$('.module-title-text, .moduleTitle');
    if (titleElement) {
      const text = await titleElement.textContent();
      return text.trim();
    }
    
    return null;
  }

  /**
   * Wait for page to be ready
   * @returns {Promise<void>}
   */
  async waitForPageReady() {
    await this.page.waitForLoadState('networkidle');
    await this.page.waitForFunction(() => document.readyState === 'complete');
    
    // Wait for any loaders to disappear
    const loader = await this.page.$('.loading, .spinner, [class*="loader"]');
    if (loader) {
      await loader.waitForElementState('hidden');
    }
  }
}

module.exports = NavigationHelper;