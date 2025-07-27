import { BasePage } from './BasePage';
import { Page } from '@playwright/test';

export class LoginPage extends BasePage {
  // Selectors
  private readonly usernameInput = 'input[name="username"]';
  private readonly passwordInput = 'input[name="password"]';
  private readonly loginButton = 'input[type="submit"][value="Log In"]';
  private readonly errorMessage = '.error-message';
  private readonly forgotPasswordLink = 'a[href*="forgotpassword"]';

  constructor(page: Page) {
    super(page);
  }

  /**
   * Navigate to login page
   */
  async goto() {
    await super.goto('/index.php?action=Login&module=Users');
    await this.waitForPageLoad();
  }

  /**
   * Perform login
   */
  async login(username: string, password: string) {
    await this.fillWithRetry(this.usernameInput, username);
    await this.fillWithRetry(this.passwordInput, password);
    await this.clickWithRetry(this.loginButton);
    
    // Wait for either dashboard or error message
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Check if login was successful
   */
  async isLoggedIn(): Promise<boolean> {
    const url = await this.getCurrentUrl();
    return !url.includes('action=Login') && !url.includes('action=Logout');
  }

  /**
   * Get error message
   */
  async getErrorMessage(): Promise<string> {
    if (await this.elementExists(this.errorMessage)) {
      return await this.getTextContent(this.errorMessage);
    }
    return '';
  }

  /**
   * Click forgot password link
   */
  async clickForgotPassword() {
    await this.clickWithRetry(this.forgotPasswordLink);
  }

  /**
   * Check if on login page
   */
  async isOnLoginPage(): Promise<boolean> {
    return await this.elementExists(this.usernameInput) && 
           await this.elementExists(this.passwordInput);
  }

  /**
   * Clear login form
   */
  async clearForm() {
    await this.clearInput(this.usernameInput);
    await this.clearInput(this.passwordInput);
  }
}