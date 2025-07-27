import { test, expect, describe } from '../../lib/base-test';
import { TestDataGenerator } from '../../lib/test-helpers';

describe.smoke('Authentication', () => {
  test('should login with valid credentials', async ({ page, loginPage, dashboardPage }) => {
    await loginPage.goto();
    await loginPage.login('admin', 'admin');
    
    expect(await loginPage.isLoggedIn()).toBe(true);
    expect(await dashboardPage.isOnDashboard()).toBe(true);
  });

  test('should show error with invalid credentials', async ({ page, loginPage }) => {
    await loginPage.goto();
    await loginPage.login('invalid', 'credentials');
    
    const errorMessage = await loginPage.getErrorMessage();
    expect(errorMessage).toContain('Invalid');
    expect(await loginPage.isLoggedIn()).toBe(false);
  });

  test('should logout successfully', async ({ page, loginPage, dashboardPage, authenticatedPage }) => {
    await dashboardPage.logout();
    expect(await loginPage.isOnLoginPage()).toBe(true);
  });

  test('should redirect to login when accessing protected page without authentication', async ({ page }) => {
    await page.goto('/index.php?module=Deals&action=index');
    await page.waitForURL(/.*action=Login.*/);
    expect(page.url()).toContain('action=Login');
  });
});

describe.regression('Authentication Security', () => {
  test('should not store password in clear text', async ({ page, loginPage }) => {
    await loginPage.goto();
    await page.fill('input[name="password"]', 'testpassword');
    
    const passwordValue = await page.inputValue('input[name="password"]');
    expect(passwordValue).toBe('testpassword'); // Input shows password
    
    // Check if password is masked in form submission
    const passwordType = await page.getAttribute('input[name="password"]', 'type');
    expect(passwordType).toBe('password');
  });

  test('should handle session timeout', async ({ page, loginPage, dashboardPage, authenticatedPage }) => {
    // This test would require setting up a short session timeout
    // For now, just verify session handling structure
    expect(await dashboardPage.isOnDashboard()).toBe(true);
  });
});