const { test, expect } = require('@playwright/test');

test('verify Deals module navigation', async ({ page }) => {
  // Navigate to the application
  await page.goto('http://localhost:8080');
  
  // Wait for any redirects
  await page.waitForLoadState('networkidle');
  
  // Login if needed
  const isLoginPage = await page.isVisible('input[name="user_name"]');
  if (isLoginPage) {
    await page.fill('input[name="user_name"]', 'admin');
    await page.fill('input[name="username_password"]', 'admin123');
    await page.click('input[type="submit"]');
    await page.waitForLoadState('networkidle');
  }
  
  // Navigate to Deals module
  console.log('Looking for Sales menu...');
  // Try different selectors for Sales menu
  const salesMenuSelectors = [
    '#grouptab_0', // Group tab ID
    'li.topnav a:has-text("Sales")',
    '.nav.navbar-nav a:has-text("Sales")',
    '#toolbar ul.nav > li > a:has-text("Sales")'
  ];
  
  let salesMenuFound = false;
  for (const selector of salesMenuSelectors) {
    if (await page.isVisible(selector)) {
      console.log('Found Sales menu with selector:', selector);
      await page.hover(selector);
      salesMenuFound = true;
      break;
    }
  }
  
  if (!salesMenuFound) {
    console.log('Sales menu not found, taking screenshot...');
    await page.screenshot({ path: 'no-sales-menu.png', fullPage: true });
    throw new Error('Sales menu not found!');
  }
  
  await page.waitForTimeout(500); // Wait for submenu
  
  // Check if Deals menu item exists
  const dealsMenuVisible = await page.isVisible('a[href*="module=Deals"]:has-text("Deals")');
  console.log('Deals menu item visible:', dealsMenuVisible);
  
  // Check if Opportunities menu item exists
  const oppsMenuVisible = await page.isVisible('a[href*="module=Opportunities"]:has-text("Opportunities")');
  console.log('Opportunities menu item visible:', oppsMenuVisible);
  
  if (dealsMenuVisible) {
    // Click on Deals
    await page.click('a[href*="module=Deals"]:has-text("Deals")');
    await page.waitForLoadState('networkidle');
    
    // Verify we're on Deals module
    const url = page.url();
    console.log('Current URL:', url);
    expect(url).toContain('module=Deals');
    
    // Take screenshot
    await page.screenshot({ path: 'deals-module.png', fullPage: true });
    console.log('Screenshot saved to deals-module.png');
  } else {
    throw new Error('Deals menu item not found!');
  }
});