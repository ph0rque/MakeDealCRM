/**
 * Navigation Helper for Deals Module E2E Tests
 * Provides consistent navigation methods across all tests
 */

const { BASE_URL } = require('./auth.helper');

/**
 * Navigate to Deals module
 * @param {Page} page - Playwright page object
 * @param {Object} options - Navigation options
 */
async function navigateToDeals(page, options = {}) {
  const { view = 'index', timeout = 30000 } = options;
  
  try {
    // Method 1: Try direct menu navigation
    const dealsMenuVisible = await page.locator('a:has-text("DEALS"):visible, a:has-text("Deals"):visible').count();
    
    if (dealsMenuVisible > 0) {
      await page.click('a:has-text("DEALS"):visible, a:has-text("Deals"):visible');
      await page.waitForLoadState('networkidle', { timeout });
      
      // Check if we landed on the right page
      if (await isOnDealsModule(page)) {
        console.log('Navigated to Deals via menu');
        return true;
      }
    }
    
    // Method 2: Try via Sales dropdown
    const salesMenu = await page.locator('a:has-text("Sales"):visible, li:has-text("Sales")').first();
    if (await salesMenu.isVisible()) {
      await salesMenu.hover();
      await page.waitForTimeout(300);
      
      const dealsSubmenu = await page.locator('a:has-text("Deals"):visible').first();
      if (await dealsSubmenu.isVisible()) {
        await dealsSubmenu.click();
        await page.waitForLoadState('networkidle', { timeout });
        
        if (await isOnDealsModule(page)) {
          console.log('Navigated to Deals via Sales menu');
          return true;
        }
      }
    }
    
    // Method 3: Direct URL navigation
    const dealModules = ['Deals', 'mdeal_Deals']; // Try both possible module names
    
    for (const moduleName of dealModules) {
      const url = `${BASE_URL}/index.php?module=${moduleName}&action=${view}`;
      await page.goto(url, { waitUntil: 'networkidle', timeout });
      
      if (await isOnDealsModule(page)) {
        console.log(`Navigated to Deals via direct URL: ${moduleName}`);
        return true;
      }
    }
    
    throw new Error('Failed to navigate to Deals module');
    
  } catch (error) {
    console.error('Navigation to Deals failed:', error.message);
    
    // Take screenshot for debugging
    await page.screenshot({ 
      path: `test-results/navigation-failure-${Date.now()}.png`,
      fullPage: true 
    });
    
    throw error;
  }
}

/**
 * Navigate to Deal detail view
 * @param {Page} page - Playwright page object
 * @param {string} dealId - Deal record ID
 */
async function navigateToDealDetail(page, dealId) {
  const dealModules = ['Deals', 'mdeal_Deals'];
  
  for (const moduleName of dealModules) {
    const url = `${BASE_URL}/index.php?module=${moduleName}&action=DetailView&record=${dealId}`;
    await page.goto(url, { waitUntil: 'networkidle' });
    
    // Check if we're on detail view
    if (page.url().includes('DetailView') && page.url().includes(dealId)) {
      return true;
    }
  }
  
  throw new Error(`Failed to navigate to deal detail: ${dealId}`);
}

/**
 * Navigate to Deal edit view
 * @param {Page} page - Playwright page object
 * @param {string} dealId - Deal record ID (optional for new deal)
 */
async function navigateToDealEdit(page, dealId = null) {
  const dealModules = ['Deals', 'mdeal_Deals'];
  
  for (const moduleName of dealModules) {
    const url = dealId 
      ? `${BASE_URL}/index.php?module=${moduleName}&action=EditView&record=${dealId}`
      : `${BASE_URL}/index.php?module=${moduleName}&action=EditView`;
      
    await page.goto(url, { waitUntil: 'networkidle' });
    
    // Check if we're on edit view
    if (page.url().includes('EditView')) {
      return true;
    }
  }
  
  throw new Error('Failed to navigate to deal edit view');
}

/**
 * Navigate to Deal pipeline view
 * @param {Page} page - Playwright page object
 */
async function navigateToPipeline(page) {
  try {
    // Try direct URL first
    await page.goto(`${BASE_URL}/index.php?module=Deals&action=Pipeline`, { 
      waitUntil: 'networkidle' 
    });
    
    // Verify we're on pipeline view
    const pipelineIndicators = await page.locator('.pipeline-view, #pipelineView, .stage-column, .deal-card').count();
    if (pipelineIndicators > 0) {
      console.log('Navigated to pipeline view');
      return true;
    }
    
    // Try alternative module name
    await page.goto(`${BASE_URL}/index.php?module=mdeal_Deals&action=Pipeline`, { 
      waitUntil: 'networkidle' 
    });
    
    const altPipelineIndicators = await page.locator('.pipeline-view, #pipelineView, .stage-column').count();
    if (altPipelineIndicators > 0) {
      console.log('Navigated to pipeline view (mdeal_Deals)');
      return true;
    }
    
    throw new Error('Pipeline view not found');
    
  } catch (error) {
    console.error('Failed to navigate to pipeline:', error.message);
    throw error;
  }
}

/**
 * Check if currently on Deals module
 * @param {Page} page - Playwright page object
 * @returns {Promise<boolean>}
 */
async function isOnDealsModule(page) {
  const url = page.url();
  
  // Check URL
  if (url.includes('module=Deals') || url.includes('module=mdeal_Deals')) {
    return true;
  }
  
  // Check page content
  const moduleTitle = await page.locator('.module-title-text:has-text("Deals"), .moduleTitle:has-text("Deals"), h2:has-text("Deals")').count();
  if (moduleTitle > 0) {
    return true;
  }
  
  // Check for deals-specific elements
  const dealsElements = await page.locator('.list-view-rounded-corners:has-text("Deal"), .listViewBody:has-text("Deal"), [class*="deal"]').count();
  return dealsElements > 0;
}

/**
 * Navigate to a specific subpanel
 * @param {Page} page - Playwright page object
 * @param {string} subpanelName - Name of the subpanel (e.g., 'Contacts', 'Documents')
 */
async function scrollToSubpanel(page, subpanelName) {
  // Scroll to bottom to load all subpanels
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  await page.waitForTimeout(1000);
  
  // Find subpanel
  const subpanel = await page.locator(
    `.subpanel:has-text("${subpanelName}"), ` +
    `#subpanel_${subpanelName.toLowerCase()}, ` +
    `div[id*="${subpanelName.toLowerCase()}"]:has-text("${subpanelName}")`
  ).first();
  
  if (await subpanel.isVisible()) {
    // Scroll to subpanel
    await subpanel.scrollIntoViewIfNeeded();
    await page.waitForTimeout(500);
    return subpanel;
  }
  
  throw new Error(`Subpanel "${subpanelName}" not found`);
}

/**
 * Get current module and action from URL
 * @param {Page} page - Playwright page object
 * @returns {Object} Object with module and action
 */
function getCurrentLocation(page) {
  const url = new URL(page.url());
  const params = new URLSearchParams(url.search);
  
  return {
    module: params.get('module') || '',
    action: params.get('action') || 'index',
    record: params.get('record') || null
  };
}

/**
 * Wait for SuiteCRM ajax operations to complete
 * @param {Page} page - Playwright page object
 * @param {number} timeout - Maximum wait time in ms
 */
async function waitForAjax(page, timeout = 5000) {
  try {
    // Wait for any active AJAX requests to complete
    await page.waitForFunction(
      () => {
        // Check jQuery active requests
        if (typeof jQuery !== 'undefined' && jQuery.active) {
          return jQuery.active === 0;
        }
        // Check native fetch
        if (window.fetch && window.fetch.activeFetches) {
          return window.fetch.activeFetches === 0;
        }
        return true;
      },
      { timeout }
    );
    
    // Additional wait for UI updates
    await page.waitForTimeout(300);
  } catch (error) {
    console.log('Ajax wait timeout - continuing');
  }
}

module.exports = {
  navigateToDeals,
  navigateToDealDetail,
  navigateToDealEdit,
  navigateToPipeline,
  isOnDealsModule,
  scrollToSubpanel,
  getCurrentLocation,
  waitForAjax
};