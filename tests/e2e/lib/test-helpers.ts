import { Page } from '@playwright/test';
import { faker } from 'faker';

/**
 * Test data generators
 */
export class TestDataGenerator {
  /**
   * Generate random deal data
   */
  static generateDealData(overrides: Partial<{
    name: string;
    amount: string;
    stage: string;
    probability: string;
    closeDate: string;
    description: string;
  }> = {}) {
    const stages = ['Prospecting', 'Qualification', 'Proposal', 'Negotiation', 'Closed Won', 'Closed Lost'];
    const closeDate = new Date();
    closeDate.setDate(closeDate.getDate() + Math.floor(Math.random() * 90) + 30); // 30-120 days from now
    
    return {
      name: overrides.name || `${faker.company.companyName()} Deal`,
      amount: overrides.amount || (Math.floor(Math.random() * 1000000) + 10000).toString(),
      stage: overrides.stage || stages[Math.floor(Math.random() * stages.length)],
      probability: overrides.probability || (Math.floor(Math.random() * 100) + 1).toString(),
      closeDate: overrides.closeDate || closeDate.toISOString().split('T')[0],
      description: overrides.description || faker.lorem.paragraph(),
      ...overrides
    };
  }

  /**
   * Generate random contact data
   */
  static generateContactData(overrides: Partial<{
    firstName: string;
    lastName: string;
    email: string;
    phone: string;
    company: string;
    title: string;
  }> = {}) {
    return {
      firstName: overrides.firstName || faker.name.firstName(),
      lastName: overrides.lastName || faker.name.lastName(),
      email: overrides.email || faker.internet.email(),
      phone: overrides.phone || faker.phone.phoneNumber(),
      company: overrides.company || faker.company.companyName(),
      title: overrides.title || faker.name.jobTitle(),
      ...overrides
    };
  }

  /**
   * Generate checklist items
   */
  static generateChecklistItems(count: number = 5): Array<{
    name: string;
    description: string;
    priority: 'high' | 'medium' | 'low';
  }> {
    const priorities: Array<'high' | 'medium' | 'low'> = ['high', 'medium', 'low'];
    
    return Array.from({ length: count }, () => ({
      name: faker.lorem.words(3),
      description: faker.lorem.sentence(),
      priority: priorities[Math.floor(Math.random() * priorities.length)]
    }));
  }

  /**
   * Generate stakeholder data
   */
  static generateStakeholderData(overrides: Partial<{
    contactName: string;
    role: string;
    influenceLevel: string;
    isPrimary: boolean;
  }> = {}) {
    const roles = ['Decision Maker', 'Influencer', 'End User', 'Gatekeeper', 'Champion'];
    const influenceLevels = ['High', 'Medium', 'Low'];
    
    return {
      contactName: overrides.contactName || `${faker.name.firstName()} ${faker.name.lastName()}`,
      role: overrides.role || roles[Math.floor(Math.random() * roles.length)],
      influenceLevel: overrides.influenceLevel || influenceLevels[Math.floor(Math.random() * influenceLevels.length)],
      isPrimary: overrides.isPrimary || false,
      ...overrides
    };
  }
}

/**
 * Database cleanup utilities
 */
export class DatabaseCleaner {
  /**
   * Clean up test deals
   */
  static async cleanupTestDeals(page: Page, testPrefix: string = 'Test_') {
    // This would need to be implemented based on your specific cleanup needs
    // Could use API calls or direct database access
    console.log(`Cleaning up deals with prefix: ${testPrefix}`);
  }

  /**
   * Clean up test contacts
   */
  static async cleanupTestContacts(page: Page, testPrefix: string = 'Test_') {
    console.log(`Cleaning up contacts with prefix: ${testPrefix}`);
  }

  /**
   * Reset test environment
   */
  static async resetTestEnvironment(page: Page) {
    await this.cleanupTestDeals(page);
    await this.cleanupTestContacts(page);
  }
}

/**
 * Wait utilities
 */
export class WaitHelpers {
  /**
   * Wait for element to contain specific text
   */
  static async waitForText(page: Page, selector: string, expectedText: string, timeout: number = 30000) {
    await page.waitForFunction(
      ({ selector, expectedText }) => {
        const element = document.querySelector(selector);
        return element && element.textContent?.includes(expectedText);
      },
      { selector, expectedText },
      { timeout }
    );
  }

  /**
   * Wait for page to be fully loaded
   */
  static async waitForPageLoad(page: Page) {
    await page.waitForLoadState('domcontentloaded');
    await page.waitForLoadState('networkidle');
    
    // Wait for any jQuery animations to complete
    await page.waitForFunction(() => {
      return !window.jQuery || window.jQuery.active === 0;
    }, { timeout: 10000 }).catch(() => {
      // Ignore timeout - jQuery might not be present
    });
  }

  /**
   * Wait for AJAX requests to complete
   */
  static async waitForAjax(page: Page, timeout: number = 30000) {
    await page.waitForFunction(
      () => {
        // Check for jQuery AJAX
        if (window.jQuery && window.jQuery.active > 0) {
          return false;
        }
        
        // Check for fetch requests
        if ((window as any).__fetchRequestCount > 0) {
          return false;
        }
        
        return true;
      },
      { timeout }
    ).catch(() => {
      // Ignore timeout
    });
  }
}

/**
 * Screenshot utilities
 */
export class ScreenshotHelpers {
  /**
   * Take screenshot with timestamp
   */
  static async takeTimestampedScreenshot(page: Page, name: string, fullPage: boolean = true) {
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    const filename = `${name}-${timestamp}.png`;
    
    await page.screenshot({
      path: `reports/screenshots/${filename}`,
      fullPage
    });
    
    return filename;
  }

  /**
   * Take screenshot on failure
   */
  static async screenshotOnFailure(page: Page, testName: string) {
    const filename = await this.takeTimestampedScreenshot(page, `FAILED-${testName}`);
    console.log(`Screenshot saved: ${filename}`);
    return filename;
  }
}

/**
 * Navigation helpers
 */
export class NavigationHelpers {
  /**
   * Navigate with retry
   */
  static async navigateWithRetry(page: Page, url: string, maxRetries: number = 3) {
    for (let i = 0; i < maxRetries; i++) {
      try {
        await page.goto(url);
        await WaitHelpers.waitForPageLoad(page);
        return;
      } catch (error) {
        if (i === maxRetries - 1) throw error;
        await page.waitForTimeout(2000);
      }
    }
  }

  /**
   * Refresh page with wait
   */
  static async refreshPage(page: Page) {
    await page.reload();
    await WaitHelpers.waitForPageLoad(page);
  }
}

/**
 * Form helpers
 */
export class FormHelpers {
  /**
   * Fill form with data
   */
  static async fillForm(page: Page, formData: Record<string, string>) {
    for (const [fieldName, value] of Object.entries(formData)) {
      const selector = `input[name="${fieldName}"], select[name="${fieldName}"], textarea[name="${fieldName}"]`;
      const element = page.locator(selector);
      
      if (await element.count() > 0) {
        const tagName = await element.evaluate(el => el.tagName.toLowerCase());
        
        if (tagName === 'select') {
          await element.selectOption(value);
        } else {
          await element.fill(value);
        }
      }
    }
  }

  /**
   * Submit form and wait for response
   */
  static async submitForm(page: Page, submitSelector: string = 'input[type="submit"], button[type="submit"]') {
    await Promise.all([
      page.waitForLoadState('networkidle'),
      page.click(submitSelector)
    ]);
  }
}

/**
 * Error handling helpers
 */
export class ErrorHelpers {
  /**
   * Check for JavaScript errors
   */
  static async checkForJSErrors(page: Page): Promise<string[]> {
    const errors: string[] = [];
    
    page.on('pageerror', (error) => {
      errors.push(error.message);
    });
    
    page.on('requestfailed', (request) => {
      errors.push(`Request failed: ${request.url()}`);
    });
    
    return errors;
  }

  /**
   * Handle unexpected dialogs
   */
  static setupDialogHandlers(page: Page) {
    page.on('dialog', async (dialog) => {
      console.log(`Dialog appeared: ${dialog.type()} - ${dialog.message()}`);
      await dialog.accept();
    });
  }
}

/**
 * Performance helpers
 */
export class PerformanceHelpers {
  /**
   * Measure page load time
   */
  static async measurePageLoadTime(page: Page): Promise<number> {
    const startTime = Date.now();
    await WaitHelpers.waitForPageLoad(page);
    return Date.now() - startTime;
  }

  /**
   * Get performance metrics
   */
  static async getPerformanceMetrics(page: Page) {
    return await page.evaluate(() => {
      const navigation = performance.getEntriesByType('navigation')[0] as PerformanceNavigationTiming;
      return {
        domContentLoaded: navigation.domContentLoadedEventEnd - navigation.navigationStart,
        loadComplete: navigation.loadEventEnd - navigation.navigationStart,
        firstByte: navigation.responseStart - navigation.navigationStart,
        domInteractive: navigation.domInteractive - navigation.navigationStart
      };
    });
  }
}

/**
 * API helpers
 */
export class ApiHelpers {
  /**
   * Make API request with authentication
   */
  static async makeAuthenticatedRequest(page: Page, endpoint: string, options: RequestInit = {}) {
    // Get session cookie or auth token from page
    const cookies = await page.context().cookies();
    const sessionCookie = cookies.find(c => c.name === 'PHPSESSID' || c.name === 'suitecrm_session');
    
    const headers = {
      'Content-Type': 'application/json',
      ...options.headers
    };
    
    if (sessionCookie) {
      headers['Cookie'] = `${sessionCookie.name}=${sessionCookie.value}`;
    }
    
    const baseUrl = process.env.BASE_URL || 'http://localhost:8080';
    const response = await fetch(`${baseUrl}${endpoint}`, {
      ...options,
      headers
    });
    
    return response;
  }
}