const { test, expect } = require('@playwright/test');

test('simple login test', async ({ page }) => {
  // Navigate to the application
  await page.goto('http://localhost:8080');
  
  // Wait for any redirects
  await page.waitForLoadState('networkidle');
  
  // Check if we're on login page
  const isLoginPage = await page.isVisible('input[name="user_name"]');
  
  if (isLoginPage) {
    console.log('On login page, attempting to login...');
    
    // Fill login form
    await page.fill('input[name="user_name"]', 'admin');
    await page.fill('input[name="username_password"]', 'admin123');
    
    // Click login button
    await page.click('input[type="submit"]');
    
    // Wait for navigation
    await page.waitForLoadState('networkidle');
    
    // Check if login was successful
    const isLoggedIn = await page.isVisible('.navbar, #main-menu, .dashboard');
    expect(isLoggedIn).toBeTruthy();
    
    console.log('Login successful!');
  } else {
    console.log('Already logged in or on different page');
  }
  
  // Take a screenshot for verification
  await page.screenshot({ path: 'login-test.png', fullPage: true });
});