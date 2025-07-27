import { BasePage } from './BasePage';
import { Page } from '@playwright/test';

export class DashboardPage extends BasePage {
  // Selectors
  private readonly dashboardTitle = 'h1:has-text("Dashboard")';
  private readonly userMenu = '#user-menu, .user-menu';
  private readonly logoutLink = 'a[href*="action=Logout"]';
  private readonly quickCreateButton = '#quickcreatetop, .quickcreate';
  private readonly globalSearch = 'input[name="query_string"]';
  private readonly mainMenu = '#main-menu, .navbar-nav';

  constructor(page: Page) {
    super(page);
  }

  /**
   * Navigate to dashboard
   */
  async goto() {
    await super.goto('/index.php?module=Home&action=index');
    await this.waitForPageLoad();
  }

  /**
   * Check if on dashboard
   */
  async isOnDashboard(): Promise<boolean> {
    return await this.elementExists(this.dashboardTitle) || 
           (await this.getCurrentUrl()).includes('module=Home');
  }

  /**
   * Logout
   */
  async logout() {
    await this.clickWithRetry(this.userMenu);
    await this.wait(500); // Wait for dropdown
    await this.clickWithRetry(this.logoutLink);
    await this.waitForNavigation();
  }

  /**
   * Navigate to module
   */
  async navigateToModule(moduleName: string) {
    const moduleLink = `a[href*="module=${moduleName}"]`;
    await this.clickWithRetry(moduleLink);
    await this.waitForNavigation();
  }

  /**
   * Use quick create
   */
  async quickCreate(moduleName: string) {
    await this.clickWithRetry(this.quickCreateButton);
    await this.wait(500); // Wait for dropdown
    const quickCreateLink = `a[href*="module=${moduleName}"][href*="action=EditView"]`;
    await this.clickWithRetry(quickCreateLink);
  }

  /**
   * Perform global search
   */
  async globalSearchFor(query: string) {
    await this.fillWithRetry(this.globalSearch, query);
    await this.pressKey('Enter');
    await this.waitForNavigation();
  }

  /**
   * Get username from dashboard
   */
  async getCurrentUsername(): Promise<string> {
    const userMenuText = await this.getTextContent(this.userMenu);
    return userMenuText.trim();
  }

  /**
   * Check if module is accessible from menu
   */
  async isModuleAccessible(moduleName: string): Promise<boolean> {
    return await this.elementExists(`a[href*="module=${moduleName}"]`);
  }

  /**
   * Get all available modules from menu
   */
  async getAvailableModules(): Promise<string[]> {
    const moduleLinks = await this.page.$$eval(
      'a[href*="module="]',
      links => links.map(link => {
        const href = link.getAttribute('href') || '';
        const match = href.match(/module=([^&]+)/);
        return match ? match[1] : '';
      }).filter(Boolean)
    );
    return [...new Set(moduleLinks)]; // Remove duplicates
  }
}