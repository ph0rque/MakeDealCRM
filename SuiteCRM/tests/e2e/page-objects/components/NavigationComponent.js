const BasePage = require('../BasePage');

/**
 * NavigationComponent - Page object for main navigation menu
 */
class NavigationComponent extends BasePage {
  constructor(page) {
    super(page);
    
    // Selectors
    this.selectors = {
      // Main navigation
      navbar: '.navbar',
      navbarBrand: '.navbar-brand',
      mobileMenuToggle: '.navbar-toggle',
      navbarCollapse: '.navbar-collapse',
      
      // Menu items
      salesMenu: 'a:has-text("Sales")',
      marketingMenu: 'a:has-text("Marketing")',
      supportMenu: 'a:has-text("Support")',
      activitiesMenu: 'a:has-text("Activities")',
      collaborationMenu: 'a:has-text("Collaboration")',
      allMenu: 'a:has-text("All")',
      
      // Submenu items
      dealsMenuItem: 'a[href*="module=Deals"]:has-text("Deals")',
      accountsMenuItem: 'a[href*="module=Accounts"]:has-text("Accounts")',
      contactsMenuItem: 'a[href*="module=Contacts"]:has-text("Contacts")',
      opportunitiesMenuItem: 'a[href*="module=Opportunities"]:has-text("Opportunities")',
      leadsMenuItem: 'a[href*="module=Leads"]:has-text("Leads")',
      
      // User menu
      userMenuToggle: '.desktop-toolbar .globalLinks-desktop',
      userProfileLink: 'a:has-text("Profile")',
      userPreferencesLink: 'a:has-text("Preferences")',
      logoutLink: 'a[href*="Logout"]',
      
      // Quick actions
      quickCreateButton: 'a[data-toggle="dropdown"]:has-text("Create")',
      globalSearchInput: 'input[name="query_string"]',
      globalSearchButton: 'button[type="submit"]:has(.glyphicon-search)',
      
      // Notifications
      notificationIcon: '.desktop-notifications',
      notificationBadge: '.desktop-notifications .badge',
      notificationDropdown: '.desktop-notifications .dropdown-menu'
    };
  }

  /**
   * Navigate to Deals module
   */
  async navigateToDeals() {
    await this.hoverOverMenu(this.selectors.salesMenu);
    await this.clickElement(this.selectors.dealsMenuItem);
    await this.waitForPageLoad();
  }

  /**
   * Navigate to Accounts module
   */
  async navigateToAccounts() {
    await this.hoverOverMenu(this.selectors.salesMenu);
    await this.clickElement(this.selectors.accountsMenuItem);
    await this.waitForPageLoad();
  }

  /**
   * Navigate to Contacts module
   */
  async navigateToContacts() {
    await this.hoverOverMenu(this.selectors.salesMenu);
    await this.clickElement(this.selectors.contactsMenuItem);
    await this.waitForPageLoad();
  }

  /**
   * Navigate to Opportunities module
   */
  async navigateToOpportunities() {
    await this.hoverOverMenu(this.selectors.salesMenu);
    await this.clickElement(this.selectors.opportunitiesMenuItem);
    await this.waitForPageLoad();
  }

  /**
   * Navigate to Leads module
   */
  async navigateToLeads() {
    await this.hoverOverMenu(this.selectors.salesMenu);
    await this.clickElement(this.selectors.leadsMenuItem);
    await this.waitForPageLoad();
  }

  /**
   * Hover over a menu item
   * @param {string} selector - The menu selector
   */
  async hoverOverMenu(selector) {
    await this.waitForElement(selector);
    await this.page.hover(selector);
    await this.page.waitForTimeout(500); // Wait for submenu animation
  }

  /**
   * Open quick create menu
   */
  async openQuickCreate() {
    await this.clickElement(this.selectors.quickCreateButton);
    await this.page.waitForTimeout(300); // Wait for dropdown animation
  }

  /**
   * Select from quick create menu
   * @param {string} moduleName - The module name to create
   */
  async quickCreate(moduleName) {
    await this.openQuickCreate();
    await this.clickElement(`a:has-text("${moduleName}")`);
    await this.waitForPageLoad();
  }

  /**
   * Perform global search
   * @param {string} searchTerm - The search term
   */
  async globalSearch(searchTerm) {
    await this.fillField(this.selectors.globalSearchInput, searchTerm);
    await this.clickElement(this.selectors.globalSearchButton);
    await this.waitForPageLoad();
  }

  /**
   * Open user menu
   */
  async openUserMenu() {
    await this.clickElement(this.selectors.userMenuToggle);
    await this.page.waitForTimeout(300); // Wait for dropdown animation
  }

  /**
   * Navigate to user profile
   */
  async goToProfile() {
    await this.openUserMenu();
    await this.clickElement(this.selectors.userProfileLink);
    await this.waitForPageLoad();
  }

  /**
   * Navigate to user preferences
   */
  async goToPreferences() {
    await this.openUserMenu();
    await this.clickElement(this.selectors.userPreferencesLink);
    await this.waitForPageLoad();
  }

  /**
   * Logout from application
   */
  async logout() {
    await this.openUserMenu();
    await this.clickElement(this.selectors.logoutLink);
    await this.waitForPageLoad();
  }

  /**
   * Check if navigation is visible
   * @returns {Promise<boolean>} True if navigation is visible
   */
  async isNavigationVisible() {
    return await this.isVisible(this.selectors.navbar);
  }

  /**
   * Toggle mobile menu
   */
  async toggleMobileMenu() {
    if (await this.isVisible(this.selectors.mobileMenuToggle)) {
      await this.clickElement(this.selectors.mobileMenuToggle);
      await this.page.waitForTimeout(300); // Wait for animation
    }
  }

  /**
   * Check if mobile menu is open
   * @returns {Promise<boolean>} True if mobile menu is open
   */
  async isMobileMenuOpen() {
    const navbarCollapse = await this.page.$(this.selectors.navbarCollapse);
    const classes = await navbarCollapse.getAttribute('class');
    return classes.includes('in');
  }

  /**
   * Get notification count
   * @returns {Promise<number>} The notification count
   */
  async getNotificationCount() {
    if (await this.isVisible(this.selectors.notificationBadge)) {
      const badgeText = await this.getText(this.selectors.notificationBadge);
      return parseInt(badgeText) || 0;
    }
    return 0;
  }

  /**
   * Open notifications dropdown
   */
  async openNotifications() {
    await this.clickElement(this.selectors.notificationIcon);
    await this.waitForElement(this.selectors.notificationDropdown);
  }

  /**
   * Check if user is logged in by checking navigation visibility
   * @returns {Promise<boolean>} True if logged in
   */
  async isLoggedIn() {
    return await this.isVisible(this.selectors.navbarBrand);
  }

  /**
   * Get current module name from URL
   * @returns {Promise<string>} The current module name
   */
  async getCurrentModule() {
    const url = await this.page.url();
    const match = url.match(/module=([^&]+)/);
    return match ? match[1] : '';
  }

  /**
   * Navigate to a specific module by name
   * @param {string} menuName - The top-level menu name
   * @param {string} moduleName - The module name
   */
  async navigateToModule(menuName, moduleName) {
    const menuSelector = `a:has-text("${menuName}")`;
    const moduleSelector = `a:has-text("${moduleName}")`;
    
    await this.hoverOverMenu(menuSelector);
    await this.clickElement(moduleSelector);
    await this.waitForPageLoad();
  }
}

module.exports = NavigationComponent;