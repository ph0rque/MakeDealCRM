/**
 * Authentication Helper for E2E Tests
 * Provides consistent login functionality across all tests
 */

const BASE_URL = process.env.BASE_URL || 'http://localhost:8080';
const USERNAME = process.env.SUITE_USERNAME || 'admin';
const PASSWORD = process.env.SUITE_PASSWORD || 'admin123';

/**
 * Login to SuiteCRM
 * @param {Page} page - Playwright page object
 * @param {Object} options - Optional login parameters
 * @returns {Promise<boolean>} - Success status
 */
async function login(page, options = {}) {
  const { username = USERNAME, password = PASSWORD, timeout = 30000 } = options;
  
  try {
    // Navigate to login page
    await page.goto(BASE_URL, { waitUntil: 'networkidle', timeout });
    
    // Check if already logged in
    const isLoggedIn = await page.$('.navbar-brand, #toolbar, .desktop-toolbar');
    if (isLoggedIn) {
      console.log('Already logged in');
      return true;
    }
    
    // Fill login form
    await page.fill('input[name="user_name"]', username, { timeout: 5000 });
    await page.fill('input[name="username_password"]', password, { timeout: 5000 });
    
    // Submit form
    const submitButton = await page.locator('input[type="submit"], input#bigbutton, button[type="submit"]').first();
    await submitButton.click();
    
    // Wait for login to complete
    await Promise.race([
      page.waitForSelector('.navbar-brand', { timeout }),
      page.waitForSelector('#toolbar', { timeout }),
      page.waitForSelector('.desktop-toolbar', { timeout }),
      page.waitForURL('**/index.php**', { timeout })
    ]);
    
    // Verify login success
    const currentUrl = page.url();
    if (currentUrl.includes('action=Login') || currentUrl.includes('login')) {
      throw new Error('Login failed - still on login page');
    }
    
    console.log('Login successful');
    return true;
    
  } catch (error) {
    console.error('Login failed:', error.message);
    
    // Take screenshot for debugging
    await page.screenshot({ 
      path: `test-results/login-failure-${Date.now()}.png`,
      fullPage: true 
    });
    
    throw error;
  }
}

/**
 * Logout from SuiteCRM
 * @param {Page} page - Playwright page object
 */
async function logout(page) {
  try {
    // Click user menu
    const userMenu = await page.$('#globalLinks, .user-menu, .desktop-toolbar .user');
    if (userMenu) {
      await userMenu.click();
      await page.waitForTimeout(500);
    }
    
    // Click logout
    await page.click('a:has-text("Log Out"), a:has-text("Logout"), a[href*="action=Logout"]');
    await page.waitForLoadState('networkidle');
    
    console.log('Logout successful');
  } catch (error) {
    console.error('Logout failed:', error.message);
  }
}

/**
 * Ensure user is logged in, login if necessary
 * @param {Page} page - Playwright page object
 * @param {Object} options - Optional parameters
 */
async function ensureLoggedIn(page, options = {}) {
  const currentUrl = page.url();
  
  // Check if on login page or not on the site
  if (!currentUrl.includes(BASE_URL) || currentUrl.includes('action=Login')) {
    return await login(page, options);
  }
  
  // Check for login indicators
  const isLoggedIn = await page.$('.navbar-brand, #toolbar, .desktop-toolbar');
  if (!isLoggedIn) {
    return await login(page, options);
  }
  
  return true;
}

/**
 * Get current user information
 * @param {Page} page - Playwright page object
 * @returns {Promise<Object>} User info object
 */
async function getCurrentUser(page) {
  try {
    const userInfo = await page.evaluate(() => {
      // Try to get user info from global JS variables
      if (window.current_user) {
        return {
          id: window.current_user,
          name: window.current_user_name || 'Unknown'
        };
      }
      
      // Try to get from DOM
      const userElement = document.querySelector('.user-name, #username_link, .globalLinks-user');
      if (userElement) {
        return {
          id: null,
          name: userElement.textContent.trim()
        };
      }
      
      return null;
    });
    
    return userInfo;
  } catch (error) {
    console.error('Failed to get current user:', error.message);
    return null;
  }
}

module.exports = {
  login,
  logout,
  ensureLoggedIn,
  getCurrentUser,
  BASE_URL,
  USERNAME,
  PASSWORD
};