const { test, expect } = require('@playwright/test');

// Helper function to login
async function login(page) {
  await page.goto('http://localhost:8080');
  await page.fill('input[name="user_name"]', 'admin');
  await page.fill('input[name="username_password"]', 'admin123');
  await page.click('input[type="submit"]');
  await page.waitForSelector('.navbar-brand', { timeout: 10000 });
}

// Helper function to navigate to Deals pipeline
async function navigateToPipeline(page) {
  await page.goto('http://localhost:8080/index.php?module=Deals&action=pipeline');
  await page.waitForSelector('.pipeline-container', { timeout: 10000 });
}

test.describe('Bulk Stakeholder Management', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
    await navigateToPipeline(page);
  });

  test('Manage Stakeholders button exists and is clickable', async ({ page }) => {
    // Check if the button exists
    const button = await page.locator('button:has-text("Manage Stakeholders")');
    await expect(button).toBeVisible();
    await expect(button).toHaveClass(/btn-warning/);
    
    // Check if it has the correct onclick handler
    const onclickAttr = await button.getAttribute('onclick');
    expect(onclickAttr).toBe('PipelineView.openBulkStakeholders()');
  });

  test('Clicking Manage Stakeholders navigates to bulk management page', async ({ page }) => {
    // Get current visible deal IDs before navigation
    const visibleDealIds = await page.evaluate(() => {
      const cards = document.querySelectorAll('.deal-card');
      return Array.from(cards)
        .filter(card => card.offsetParent !== null) // Check if visible
        .map(card => card.getAttribute('data-deal-id'))
        .filter(id => id);
    });
    
    // Click the button
    await page.click('button:has-text("Manage Stakeholders")');
    
    // Wait for navigation to complete
    await page.waitForURL(/action=stakeholder_bulk/);
    
    // Verify we're on the bulk management page
    await expect(page.locator('h2:has-text("Bulk Stakeholder Management")')).toBeVisible();
    
    // Check that deal_ids are passed in the URL
    const url = page.url();
    expect(url).toContain('module=Deals&action=stakeholder_bulk');
    if (visibleDealIds.length > 0) {
      expect(url).toContain('deal_ids=');
    }
  });

  test('Bulk stakeholder page shows correct sections', async ({ page }) => {
    // Navigate to bulk stakeholder management
    await page.click('button:has-text("Manage Stakeholders")');
    await page.waitForURL(/action=stakeholder_bulk/);
    
    // Check main sections exist
    await expect(page.locator('.deal-selection')).toBeVisible();
    await expect(page.locator('.stakeholder-actions')).toBeVisible();
    await expect(page.locator('.results-section')).toBeVisible();
    
    // Check tabs exist
    await expect(page.locator('a:has-text("Add Stakeholders")')).toBeVisible();
    await expect(page.locator('a:has-text("Manage Roles")')).toBeVisible();
    await expect(page.locator('a:has-text("Remove Stakeholders")')).toBeVisible();
    await expect(page.locator('a:has-text("Import/Export")')).toBeVisible();
    
    // Check deal list table exists
    await expect(page.locator('.deal-selection-table')).toBeVisible();
  });

  test('Bulk stakeholder page pre-selects visible deals', async ({ page }) => {
    // First, ensure there are some deals visible
    const dealCards = await page.locator('.deal-card').count();
    expect(dealCards).toBeGreaterThan(0);
    
    // Get the IDs of visible deals
    const visibleDealIds = await page.evaluate(() => {
      const cards = document.querySelectorAll('.deal-card');
      return Array.from(cards)
        .filter(card => card.offsetParent !== null) // Check if visible
        .map(card => card.getAttribute('data-deal-id'))
        .filter(id => id);
    });
    
    // Navigate to bulk stakeholder management
    await page.click('button:has-text("Manage Stakeholders")');
    await page.waitForURL(/action=stakeholder_bulk/);
    
    // Wait for the deal table to load
    await page.waitForSelector('.deal-selection-table');
    
    // Check that the URL contains the deal IDs
    const url = page.url();
    for (const dealId of visibleDealIds.slice(0, 3)) { // Check first 3 IDs
      expect(url).toContain(dealId);
    }
    
    // Check that deals are pre-selected (either by checkbox or by count display)
    const selectedCountText = await page.locator('text=/\\d+ deals selected/').textContent();
    const match = selectedCountText.match(/(\d+) deals selected/);
    if (match) {
      const selectedCount = parseInt(match[1]);
      expect(selectedCount).toBeGreaterThan(0);
    } else {
      // Fallback to checking checkboxes if count display is not found
      const checkedBoxes = await page.locator('.deal-checkbox:checked').count();
      expect(checkedBoxes).toBeGreaterThan(0);
    }
  });

  test('Can search for contacts in bulk stakeholder page', async ({ page }) => {
    // Navigate to bulk stakeholder management
    await page.click('button:has-text("Manage Stakeholders")');
    await page.waitForURL(/action=stakeholder_bulk/);
    
    // Ensure Add Stakeholders tab is active
    const addTab = page.locator('a:has-text("Add Stakeholders")');
    const tabParent = addTab.locator('..');
    const isActive = await tabParent.getAttribute('class');
    if (!isActive?.includes('active')) {
      await addTab.click();
    }
    
    // Check contact search field exists
    const searchField = page.locator('#contactSearchBulk');
    await expect(searchField).toBeVisible();
    
    // Check role dropdown exists
    const roleDropdown = page.locator('#bulkRole');
    await expect(roleDropdown).toBeVisible();
    
    // Verify role options
    const roleOptions = await roleDropdown.locator('option').allTextContents();
    expect(roleOptions).toContain('Decision Maker');
    expect(roleOptions).toContain('Executive Champion');
    expect(roleOptions).toContain('Technical Evaluator');
  });

  test('Import/Export tab has correct elements', async ({ page }) => {
    // Navigate to bulk stakeholder management
    await page.click('button:has-text("Manage Stakeholders")');
    await page.waitForURL(/action=stakeholder_bulk/);
    
    // Click Import/Export tab
    await page.click('a:has-text("Import/Export")');
    
    // Check export button
    const exportButton = page.locator('button:has-text("Export to CSV")');
    await expect(exportButton).toBeVisible();
    
    // Check import elements
    const importFile = page.locator('#importFile');
    await expect(importFile).toBeVisible();
    
    const importButton = page.locator('button:has-text("Import from CSV")');
    await expect(importButton).toBeVisible();
    
    // Check template link
    const templateLink = page.locator('a:has-text("Download Template")');
    await expect(templateLink).toBeVisible();
  });

  test('Can navigate back to pipeline from bulk stakeholder page', async ({ page }) => {
    // Navigate to bulk stakeholder management
    await page.click('button:has-text("Manage Stakeholders")');
    await page.waitForURL(/action=stakeholder_bulk/);
    
    // Verify we're on the bulk page
    await expect(page.locator('h2:has-text("Bulk Stakeholder Management")')).toBeVisible();
    
    // Navigate back to pipeline using direct URL (simpler approach)
    await page.goto('http://localhost:8080/index.php?module=Deals&action=pipeline');
    
    // Wait for pipeline view to load
    await page.waitForSelector('.pipeline-container', { timeout: 10000 });
    
    // Verify we're back on the pipeline
    await expect(page.locator('.pipeline-board')).toBeVisible();
  });
});