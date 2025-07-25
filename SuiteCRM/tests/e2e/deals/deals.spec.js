const { test, expect } = require('@playwright/test');

// Test data
const testDeal = {
  name: 'Test Manufacturing Co',
  status: 'initial_contact',
  source: 'broker',
  deal_value: '5000000',
  ttm_revenue: '10000000',
  ttm_ebitda: '2000000',
  target_multiple: '4.5',
  asking_price: '9000000'
};

// Helper function to login
async function login(page) {
  await page.goto('http://localhost:8080');
  await page.fill('input[name="user_name"]', 'admin');
  await page.fill('input[name="username_password"]', 'admin123');
  await page.click('input[type="submit"]');
  await page.waitForSelector('.navbar-brand', { timeout: 10000 });
}

// Helper function to navigate to Deals module
async function navigateToDeals(page) {
  await page.click('a:has-text("Sales")');
  await page.click('a:has-text("Deals")');
  await page.waitForSelector('.module-title-text:has-text("Deals")', { timeout: 5000 });
}

test.describe('Deals Module Tests', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('Create a new deal', async ({ page }) => {
    await navigateToDeals(page);
    
    // Click Create button
    await page.click('a:has-text("Create")');
    await page.waitForSelector('h2:has-text("Create Deal")');
    
    // Fill in basic information
    await page.fill('input[name="name"]', testDeal.name);
    await page.selectOption('select[name="status"]', testDeal.status);
    await page.selectOption('select[name="source"]', testDeal.source);
    await page.fill('input[name="deal_value"]', testDeal.deal_value);
    
    // Fill in financial information
    await page.fill('input[name="ttm_revenue_c"]', testDeal.ttm_revenue);
    await page.fill('input[name="ttm_ebitda_c"]', testDeal.ttm_ebitda);
    await page.fill('input[name="target_multiple_c"]', testDeal.target_multiple);
    await page.fill('input[name="asking_price_c"]', testDeal.asking_price);
    
    // Save the deal
    await page.click('input[value="Save"]');
    
    // Verify we're on the detail view
    await page.waitForSelector('h2:has-text("' + testDeal.name + '")');
    
    // Verify calculated valuation
    const valuation = await page.textContent('.field-value:has-text("$9,000,000")');
    expect(valuation).toBeTruthy();
  });

  test('Duplicate detection works', async ({ page }) => {
    await navigateToDeals(page);
    await page.click('a:has-text("Create")');
    
    // Enter existing deal name
    await page.fill('input[name="name"]', testDeal.name);
    
    // Wait for duplicate check
    await page.waitForTimeout(1000);
    
    // Check for duplicate warning
    const duplicateWarning = await page.locator('.duplicate-check-container').isVisible();
    expect(duplicateWarning).toBeTruthy();
  });

  test('List view displays deals correctly', async ({ page }) => {
    await navigateToDeals(page);
    
    // Check for list view elements
    await expect(page.locator('.list-view-rounded-corners')).toBeVisible();
    
    // Check for summary statistics
    await expect(page.locator('.summary-stats')).toBeVisible();
    
    // Check for our test deal in the list
    await expect(page.locator('td:has-text("' + testDeal.name + '")')).toBeVisible();
  });

  test('Quick filter by stage works', async ({ page }) => {
    await navigateToDeals(page);
    
    // Click on a stage filter
    await page.click('button:has-text("Initial Contact")');
    
    // Verify filtered results
    await page.waitForTimeout(500);
    const visibleDeals = await page.locator('tr.listViewRow').count();
    expect(visibleDeals).toBeGreaterThan(0);
  });

  test('Detail view shows all information', async ({ page }) => {
    await navigateToDeals(page);
    
    // Click on a deal
    await page.click('a:has-text("' + testDeal.name + '")');
    
    // Check for stage progress bar
    await expect(page.locator('.stage-progress')).toBeVisible();
    
    // Check for quick actions panel
    await expect(page.locator('.quick-actions-panel')).toBeVisible();
    
    // Check for financial information
    await expect(page.locator('.field-label:has-text("TTM Revenue")')).toBeVisible();
    await expect(page.locator('.field-label:has-text("TTM EBITDA")')).toBeVisible();
    await expect(page.locator('.field-label:has-text("Proposed Valuation")')).toBeVisible();
  });

  test('At-risk status indicator displays correctly', async ({ page }) => {
    await navigateToDeals(page);
    
    // Look for at-risk indicators
    const warningBadges = await page.locator('.badge-warning:has-text("Warning")').count();
    const alertBadges = await page.locator('.badge-danger:has-text("Alert")').count();
    
    // Should have at least one at-risk deal
    expect(warningBadges + alertBadges).toBeGreaterThan(0);
  });

  test('Edit deal updates calculations', async ({ page }) => {
    await navigateToDeals(page);
    await page.click('a:has-text("' + testDeal.name + '")');
    
    // Click Edit button
    await page.click('input[value="Edit"]');
    
    // Change EBITDA and multiple
    await page.fill('input[name="ttm_ebitda_c"]', '3000000');
    await page.fill('input[name="target_multiple_c"]', '5');
    
    // Save
    await page.click('input[value="Save"]');
    
    // Check updated valuation (3M * 5 = 15M)
    await page.waitForSelector('.field-value:has-text("$15,000,000")');
  });

  test('Mass update functionality', async ({ page }) => {
    await navigateToDeals(page);
    
    // Select multiple deals
    await page.check('input[name="mass[]"]:first-child');
    await page.check('input[name="mass[]"]:nth-child(2)');
    
    // Click mass update
    await page.selectOption('select[name="action_select"]', 'mass_update');
    await page.click('input[value="Go"]');
    
    // Update stage
    await page.selectOption('select[name="status"]', 'nda_signed');
    await page.click('input[value="Update"]');
    
    // Verify update
    await expect(page.locator('div.alert-success')).toBeVisible();
  });

  test('Email integration - compose email', async ({ page }) => {
    await navigateToDeals(page);
    await page.click('a:has-text("' + testDeal.name + '")');
    
    // Click Send Email quick action
    await page.click('button:has-text("Send Email")');
    
    // Verify email compose window
    await expect(page.locator('#composeEmail')).toBeVisible();
  });

  test('Document attachment', async ({ page }) => {
    await navigateToDeals(page);
    await page.click('a:has-text("' + testDeal.name + '")');
    
    // Navigate to Documents subpanel
    await page.click('a:has-text("Documents")');
    
    // Click Create
    await page.click('.subpanel-header button:has-text("Create")');
    
    // Fill document details
    await page.fill('input[name="document_name"]', 'Test Financial Statement');
    
    // Upload file (mock)
    const fileInput = await page.locator('input[type="file"]');
    await fileInput.setInputFiles({
      name: 'financials.pdf',
      mimeType: 'application/pdf',
      buffer: Buffer.from('mock pdf content')
    });
    
    // Save
    await page.click('input[value="Save"]');
    
    // Verify document appears in subpanel
    await expect(page.locator('td:has-text("Test Financial Statement")')).toBeVisible();
  });

  test('Search functionality', async ({ page }) => {
    await navigateToDeals(page);
    
    // Basic search
    await page.fill('input[name="basic_search"]', testDeal.name);
    await page.click('input[value="Search"]');
    
    // Verify results
    await expect(page.locator('td:has-text("' + testDeal.name + '")')).toBeVisible();
    
    // Advanced search
    await page.click('a:has-text("Advanced")');
    await page.selectOption('select[name="status_advanced"]', 'initial_contact');
    await page.fill('input[name="deal_value_advanced_range_choice"]', '1000000');
    await page.click('input[value="Search"]');
    
    // Verify filtered results
    const resultCount = await page.locator('tr.listViewRow').count();
    expect(resultCount).toBeGreaterThan(0);
  });

  test('Export functionality', async ({ page }) => {
    await navigateToDeals(page);
    
    // Select all
    await page.check('input[name="massall"]');
    
    // Export
    await page.selectOption('select[name="action_select"]', 'export');
    
    // Set up download promise before clicking
    const downloadPromise = page.waitForEvent('download');
    await page.click('input[value="Go"]');
    
    // Wait for download
    const download = await downloadPromise;
    expect(download.suggestedFilename()).toContain('.csv');
  });

  test('Related contacts management', async ({ page }) => {
    await navigateToDeals(page);
    await page.click('a:has-text("' + testDeal.name + '")');
    
    // Navigate to Contacts subpanel
    await page.click('a:has-text("Contacts")');
    
    // Select existing contact
    await page.click('.subpanel-header button:has-text("Select")');
    
    // Search and select
    await page.fill('input[name="name_advanced"]', 'John');
    await page.click('input[value="Search"]');
    await page.click('input[name="mass[]"]:first-child');
    await page.click('input[value="Select"]');
    
    // Verify contact added
    await expect(page.locator('.subpanel td:has-text("John")')).toBeVisible();
  });

  test('Activity timeline displays correctly', async ({ page }) => {
    await navigateToDeals(page);
    await page.click('a:has-text("' + testDeal.name + '")');
    
    // Check for activity timeline
    await expect(page.locator('.activity-timeline')).toBeVisible();
    
    // Create a note
    await page.click('button:has-text("Log Note")');
    await page.fill('textarea[name="note_description"]', 'Test note for activity timeline');
    await page.click('input[value="Save"]');
    
    // Verify note appears in timeline
    await page.waitForTimeout(1000);
    await expect(page.locator('.timeline-item:has-text("Test note")')).toBeVisible();
  });

  test('Accessibility - keyboard navigation', async ({ page }) => {
    await navigateToDeals(page);
    
    // Tab through main elements
    await page.keyboard.press('Tab');
    await page.keyboard.press('Tab');
    await page.keyboard.press('Tab');
    
    // Press Enter on Create button
    const activeElement = await page.evaluate(() => document.activeElement.textContent);
    if (activeElement.includes('Create')) {
      await page.keyboard.press('Enter');
      await expect(page.locator('h2:has-text("Create Deal")')).toBeVisible();
    }
  });

  test('Performance - list view loads quickly', async ({ page }) => {
    const startTime = Date.now();
    await navigateToDeals(page);
    const endTime = Date.now();
    
    const loadTime = endTime - startTime;
    expect(loadTime).toBeLessThan(3000); // Should load in under 3 seconds
  });

  test('Responsive design - mobile view', async ({ page }) => {
    // Set mobile viewport
    await page.setViewportSize({ width: 375, height: 667 });
    
    await navigateToDeals(page);
    
    // Check mobile menu
    await page.click('.navbar-toggle');
    await expect(page.locator('.navbar-collapse')).toBeVisible();
    
    // Check list view adapts
    await expect(page.locator('.list-view-rounded-corners')).toBeVisible();
  });
});

test.describe('Deal Workflow Tests', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('Complete deal lifecycle', async ({ page }) => {
    await navigateToDeals(page);
    
    // Create new deal
    const dealName = 'Lifecycle Test Co ' + Date.now();
    await page.click('a:has-text("Create")');
    await page.fill('input[name="name"]', dealName);
    await page.selectOption('select[name="status"]', 'sourcing');
    await page.click('input[value="Save"]');
    
    // Progress through stages
    const stages = [
      'initial_contact',
      'nda_signed',
      'info_received',
      'initial_analysis',
      'loi_submitted',
      'loi_accepted',
      'due_diligence',
      'final_negotiation',
      'closed_won'
    ];
    
    for (const stage of stages) {
      await page.click('input[value="Edit"]');
      await page.selectOption('select[name="status"]', stage);
      await page.click('input[value="Save"]');
      
      // Verify stage updated
      await expect(page.locator('.field-value:has-text("' + stage.replace(/_/g, ' ').toUpperCase() + '")')).toBeVisible();
    }
  });
});