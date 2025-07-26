const BasePage = require('./BasePage');

/**
 * LoginPage - Page object for authentication
 */
class LoginPage extends BasePage {
  constructor(page) {
    super(page);
    
    // Selectors
    this.selectors = {
      usernameInput: 'input[name="user_name"]',
      passwordInput: 'input[name="username_password"]',
      loginButton: 'input[type="submit"][value="Log In"]',
      errorMessage: '.alert-danger',
      forgotPasswordLink: 'a:has-text("Forgot Password?")',
      rememberMeCheckbox: 'input[name="remember_me"]',
      languageDropdown: 'select[name="login_language"]',
      themeDropdown: 'select[name="login_theme"]',
      navbarBrand: '.navbar-brand',
      logoutLink: 'a[href*="Logout"]'
    };
  }

  /**
   * Navigate to login page
   */
  async goto() {
    await this.navigate('/index.php?action=Login&module=Users');
    await this.waitForElement(this.selectors.usernameInput);
  }

  /**
   * Perform login with credentials
   * @param {string} username - The username
   * @param {string} password - The password
   */
  async login(username, password) {
    await this.fillField(this.selectors.usernameInput, username);
    await this.fillField(this.selectors.passwordInput, password);
    await this.clickElement(this.selectors.loginButton);
    
    // Wait for either dashboard or error message
    await this.page.waitForSelector(`${this.selectors.navbarBrand}, ${this.selectors.errorMessage}`, {
      state: 'visible',
      timeout: 10000
    });
  }

  /**
   * Quick login helper with default admin credentials
   */
  async loginAsAdmin() {
    await this.login('admin', 'admin123');
  }

  /**
   * Check if login was successful
   * @returns {Promise<boolean>} True if logged in successfully
   */
  async isLoggedIn() {
    return await this.isVisible(this.selectors.navbarBrand);
  }

  /**
   * Get error message text
   * @returns {Promise<string>} The error message text
   */
  async getErrorMessage() {
    if (await this.isVisible(this.selectors.errorMessage)) {
      return await this.getText(this.selectors.errorMessage);
    }
    return '';
  }

  /**
   * Click forgot password link
   */
  async clickForgotPassword() {
    await this.clickElement(this.selectors.forgotPasswordLink);
  }

  /**
   * Set remember me checkbox
   * @param {boolean} remember - Whether to check or uncheck
   */
  async setRememberMe(remember = true) {
    const checkbox = await this.page.$(this.selectors.rememberMeCheckbox);
    const isChecked = await checkbox.isChecked();
    
    if (remember && !isChecked) {
      await checkbox.check();
    } else if (!remember && isChecked) {
      await checkbox.uncheck();
    }
  }

  /**
   * Select login language
   * @param {string} language - The language code
   */
  async selectLanguage(language) {
    await this.selectOption(this.selectors.languageDropdown, language);
  }

  /**
   * Select login theme
   * @param {string} theme - The theme name
   */
  async selectTheme(theme) {
    await this.selectOption(this.selectors.themeDropdown, theme);
  }

  /**
   * Logout from the application
   */
  async logout() {
    if (await this.isVisible(this.selectors.logoutLink)) {
      await this.clickElement(this.selectors.logoutLink);
      await this.waitForElement(this.selectors.usernameInput);
    }
  }

  /**
   * Check if on login page
   * @returns {Promise<boolean>} True if on login page
   */
  async isOnLoginPage() {
    return await this.isVisible(this.selectors.usernameInput) && 
           await this.isVisible(this.selectors.passwordInput);
  }

  /**
   * Get current page title
   * @returns {Promise<string>} The page title
   */
  async getPageTitle() {
    return await this.page.title();
  }
}

module.exports = LoginPage;