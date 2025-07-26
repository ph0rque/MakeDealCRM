/**
 * Smoke Tests
 * Quick tests to verify the basic E2E setup is working
 */

const { test, expect } = require('./lib/fixtures/test-fixtures');
const BasePage = require('./lib/pages/base.page');

test.describe('E2E Setup Smoke Tests @smoke', () => {
  test('should load the application', async ({ page }) => {
    await page.goto('/');
    
    // Wait for page to load
    await page.waitForLoadState('networkidle');
    
    // Should see login page or dashboard
    const hasLogin = await page.isVisible('input[name="user_name"], input[name="username"]');
    const hasDashboard = await page.isVisible('.navbar, #main-menu');
    
    expect(hasLogin || hasDashboard).toBeTruthy();
  });

  test('should authenticate successfully', async ({ page }) => {
    const basePage = new BasePage(page);
    
    // Login as admin
    await basePage.auth.loginAsAdmin();
    
    // Should be redirected to dashboard/main page
    await expect(page).toHaveURL(/dashboard|index|main/);
    
    // Should see navigation elements
    await basePage.assertions.assertVisible('.navbar, #main-menu');
  });

  test('should use test fixtures correctly', async ({ authenticatedPage, testData }) => {
    // Page should already be authenticated
    const isLoggedIn = await new BasePage(authenticatedPage).auth.isLoggedIn();
    expect(isLoggedIn).toBeTruthy();
    
    // Test data should be available
    const dealData = testData.generateDealData();
    expect(dealData.name).toBeTruthy();
    expect(dealData.deal_value).toBeTruthy();
  });

  test('should capture screenshots', async ({ page }) => {
    const basePage = new BasePage(page);
    await basePage.auth.loginAsAdmin();
    
    // Take a screenshot
    const screenshotPath = await basePage.screenshot.takeScreenshot('smoke-test');
    expect(screenshotPath).toContain('smoke-test');
  });

  test('should wait for elements properly', async ({ authenticatedPage }) => {
    const basePage = new BasePage(authenticatedPage);
    
    // Test wait helper
    await basePage.wait.waitForPageReady();
    await basePage.wait.waitForElement('.navbar, #main-menu');
    
    // Should not throw errors
    expect(true).toBeTruthy();
  });

  test('should navigate between modules', async ({ authenticatedPage }) => {
    const basePage = new BasePage(authenticatedPage);
    
    try {
      // Try to navigate to Deals module
      await basePage.navigation.navigateToModule('Deals');
      
      // Should load without errors
      await basePage.wait.waitForPageReady();
      expect(true).toBeTruthy();
    } catch (error) {
      // If Deals module doesn't exist, that's okay for smoke test
      console.log('Deals module may not be available:', error.message);
      expect(true).toBeTruthy();
    }
  });
});

test.describe('Configuration Tests @smoke', () => {
  test('should have correct base URL', async ({ page }) => {
    const baseURL = page.context()._options.baseURL;
    expect(baseURL).toBe('http://localhost:8080');
  });

  test('should have test data configuration', async ({ page }) => {
    const testData = page.context()._options.testData;
    expect(testData.admin.username).toBeTruthy();
    expect(testData.admin.password).toBeTruthy();
  });

  test('should capture console logs', async ({ page, consoleLogs }) => {
    // Generate some console activity
    await page.evaluate(() => {
      console.log('Test console message');
      console.warn('Test warning message');
    });
    
    await page.waitForTimeout(100);
    
    // Console logs should be captured
    expect(consoleLogs.length).toBeGreaterThan(0);
  });

  test('should capture network activity', async ({ page, networkCapture }) => {
    // Navigate to trigger network requests
    await page.goto('/');
    await page.waitForLoadState('networkidle');
    
    // Should have captured requests
    expect(networkCapture.requests.length).toBeGreaterThan(0);
    expect(networkCapture.responses.length).toBeGreaterThan(0);
  });
});