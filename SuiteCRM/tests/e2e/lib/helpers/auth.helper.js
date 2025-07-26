/**
 * Authentication Helper
 * Provides common authentication utilities for E2E tests
 */

class AuthHelper {
  constructor(page) {
    this.page = page;
  }

  /**
   * Login with credentials
   * @param {string} username - Username
   * @param {string} password - Password
   * @returns {Promise<void>}
   */
  async login(username, password) {
    // Navigate to login page if not already there
    const currentUrl = this.page.url();
    if (!currentUrl.includes('login') && !currentUrl.includes('Login')) {
      await this.page.goto('/');
    }

    // Wait for login form
    await this.page.waitForSelector('input[name="user_name"], input[name="username"]', { 
      state: 'visible',
      timeout: 10000 
    });

    // Fill credentials
    const usernameField = await this.page.$('input[name="user_name"]') || await this.page.$('input[name="username"]');
    const passwordField = await this.page.$('input[name="username_password"]') || await this.page.$('input[name="password"]');
    
    await usernameField.fill(username);
    await passwordField.fill(password);

    // Submit form
    const submitButton = await this.page.$('input[type="submit"]') || await this.page.$('button[type="submit"]');
    await submitButton.click();

    // Wait for navigation
    await this.page.waitForNavigation({ 
      waitUntil: 'networkidle',
      timeout: 15000 
    });

    // Verify login success
    await this.page.waitForSelector('.navbar-brand, #main-menu, .dashboard', { 
      state: 'visible',
      timeout: 10000 
    });
  }

  /**
   * Login as admin user
   * @returns {Promise<void>}
   */
  async loginAsAdmin() {
    const { admin } = this.page.context()._options.testData;
    await this.login(admin.username, admin.password);
  }

  /**
   * Login as test user
   * @returns {Promise<void>}
   */
  async loginAsTestUser() {
    const { testUser } = this.page.context()._options.testData;
    await this.login(testUser.username, testUser.password);
  }

  /**
   * Logout
   * @returns {Promise<void>}
   */
  async logout() {
    // Click user menu
    const userMenu = await this.page.$('.user-menu, #userMenuButton, a[href*="Logout"]');
    if (userMenu) {
      await userMenu.click();
      
      // Look for logout link
      const logoutLink = await this.page.$('a[href*="Logout"], a:has-text("Log Out"), a:has-text("Logout")');
      if (logoutLink) {
        await logoutLink.click();
        await this.page.waitForNavigation({ waitUntil: 'networkidle' });
      }
    }
  }

  /**
   * Check if user is logged in
   * @returns {Promise<boolean>}
   */
  async isLoggedIn() {
    try {
      await this.page.waitForSelector('.navbar-brand, #main-menu', { 
        state: 'visible',
        timeout: 3000 
      });
      return true;
    } catch {
      return false;
    }
  }

  /**
   * Get current user info
   * @returns {Promise<Object>}
   */
  async getCurrentUser() {
    const userInfo = await this.page.evaluate(() => {
      // Try to get user info from various possible locations
      const userElement = document.querySelector('.user-name, .current-user, #user-name');
      if (userElement) {
        return {
          name: userElement.textContent.trim(),
          role: document.querySelector('.user-role')?.textContent?.trim() || 'Unknown'
        };
      }
      return null;
    });
    return userInfo;
  }

  /**
   * Setup authentication state
   * Can be used in beforeEach hooks
   * @param {string} userType - 'admin' or 'test'
   * @returns {Promise<void>}
   */
  async setupAuth(userType = 'admin') {
    const isLoggedIn = await this.isLoggedIn();
    if (!isLoggedIn) {
      if (userType === 'admin') {
        await this.loginAsAdmin();
      } else {
        await this.loginAsTestUser();
      }
    }
  }
}

module.exports = AuthHelper;