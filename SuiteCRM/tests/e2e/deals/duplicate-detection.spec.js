const { test, expect } = require('@playwright/test');

/**
 * Duplicate Detection E2E Tests for Deals Module
 * Tests real-time duplicate detection UI including warnings, merge actions, and user flows
 */

// Test data setup
const testDeals = {
  original: {
    name: 'Enterprise Software License - Acme Corp',
    account: 'Acme Corporation',
    email: 'contact@acmecorp.com',
    phone: '555-123-4567',
    amount: '150000',
    stage: 'Negotiation',
    closeDate: '2024-03-15'
  },
  duplicate: {
    name: 'Enterprise Software License - Acme Corp', // Same name
    account: 'Acme Corporation',
    email: 'contact@acmecorp.com', // Same email
    phone: '555-123-4567', // Same phone
    amount: '175000', // Different amount
    stage: 'Proposal', // Different stage
    closeDate: '2024-03-20' // Different close date
  },
  similar: {
    name: 'Software License - Acme Corp', // Similar name
    account: 'Acme Corp', // Similar account
    email: 'sales@acmecorp.com', // Different email
    phone: '555-123-4568', // Similar phone
    amount: '100000',
    stage: 'Qualification',
    closeDate: '2024-04-01'
  },
  different: {
    name: 'Cloud Services - Tech Solutions',
    account: 'Tech Solutions Inc',
    email: 'info@techsolutions.com',
    phone: '555-987-6543',
    amount: '75000',
    stage: 'Prospecting',
    closeDate: '2024-05-01'
  }
};

test.describe('Duplicate Detection UI Tests', () => {
  test.beforeEach(async ({ page }) => {
    // Login to SuiteCRM
    await page.goto('http://localhost:8080');
    await page.fill('#user_name', 'admin');
    await page.fill('#username_password', 'admin123');
    await page.click('#bigbutton');
    
    // Navigate to Deals module
    await page.hover('text=Sales');
    await page.click('text=Deals');
    
    // Clean up test data from previous runs
    await cleanupTestDeals(page);
  });

  test.afterEach(async ({ page }) => {
    // Clean up test data after each test
    await cleanupTestDeals(page);
  });

  test('Real-time duplicate warning displays on name match', async ({ page }) => {
    // Create original deal first
    await createDeal(page, testDeals.original);
    
    // Start creating duplicate
    await page.click('text=Create Deal');
    await page.waitForSelector('#name');
    
    // Type the duplicate name
    await page.fill('#name', testDeals.duplicate.name);
    
    // Tab out or click elsewhere to trigger duplicate check
    await page.click('#amount');
    
    // Verify duplicate warning appears
    await expect(page.locator('.duplicate-warning')).toBeVisible({ timeout: 5000 });
    await expect(page.locator('.duplicate-warning')).toContainText('Potential duplicate detected');
    await expect(page.locator('.duplicate-count')).toContainText('1 potential duplicate found');
    
    // Verify warning shows matched fields
    await expect(page.locator('.duplicate-match-field')).toContainText('Name: Enterprise Software License - Acme Corp');
    
    // Take screenshot for visual regression
    await page.screenshot({ path: 'duplicate-warning-name.png', fullPage: true });
  });

  test('Multiple field matches show enhanced warning', async ({ page }) => {
    // Create original deal
    await createDeal(page, testDeals.original);
    
    // Start creating duplicate with multiple matching fields
    await page.click('text=Create Deal');
    await page.waitForSelector('#name');
    
    // Fill multiple matching fields
    await page.fill('#name', testDeals.duplicate.name);
    await page.fill('#email_c', testDeals.duplicate.email);
    await page.fill('#phone_c', testDeals.duplicate.phone);
    
    // Trigger duplicate check
    await page.click('#amount');
    await page.waitForTimeout(500); // Allow debounce
    
    // Verify enhanced warning
    await expect(page.locator('.duplicate-warning.high-confidence')).toBeVisible();
    await expect(page.locator('.duplicate-warning')).toContainText('Strong match detected');
    
    // Verify all matched fields are shown
    await expect(page.locator('.duplicate-match-fields')).toContainText('Name');
    await expect(page.locator('.duplicate-match-fields')).toContainText('Email');
    await expect(page.locator('.duplicate-match-fields')).toContainText('Phone');
    
    // Verify confidence score
    await expect(page.locator('.match-confidence')).toContainText('90%');
  });

  test('View duplicate action opens detail view', async ({ page }) => {
    // Create original deal
    await createDeal(page, testDeals.original);
    
    // Start creating duplicate
    await page.click('text=Create Deal');
    await page.fill('#name', testDeals.duplicate.name);
    await page.click('#amount');
    
    // Wait for duplicate warning
    await page.waitForSelector('.duplicate-warning');
    
    // Click view duplicate action
    await page.click('.duplicate-action-view');
    
    // Verify detail view opens in new tab/modal
    const [newPage] = await Promise.all([
      page.waitForEvent('popup'),
      page.click('.duplicate-action-view')
    ]);
    
    // Verify we're on the detail view
    await expect(newPage).toHaveURL(/.*action=DetailView/);
    await expect(newPage.locator('#name')).toContainText(testDeals.original.name);
    
    await newPage.close();
  });

  test('Merge action functionality', async ({ page }) => {
    // Create original deal
    await createDeal(page, testDeals.original);
    
    // Start creating duplicate with different values
    await page.click('text=Create Deal');
    await page.fill('#name', testDeals.duplicate.name);
    await page.fill('#amount', testDeals.duplicate.amount);
    await page.selectOption('#sales_stage', testDeals.duplicate.stage);
    await page.click('#email_c');
    
    // Wait for duplicate warning
    await page.waitForSelector('.duplicate-warning');
    
    // Click merge action
    await page.click('.duplicate-action-merge');
    
    // Verify merge modal opens
    await expect(page.locator('.merge-modal')).toBeVisible();
    await expect(page.locator('.merge-modal h2')).toContainText('Merge Duplicate Records');
    
    // Verify both records are shown with differences highlighted
    await expect(page.locator('.merge-field-difference[data-field="amount"]')).toBeVisible();
    await expect(page.locator('.merge-field-difference[data-field="sales_stage"]')).toBeVisible();
    
    // Select values to keep (keep higher amount from duplicate)
    await page.click('.merge-select[data-field="amount"][data-record="duplicate"]');
    await page.click('.merge-select[data-field="sales_stage"][data-record="original"]');
    
    // Perform merge
    await page.click('.merge-confirm-button');
    
    // Verify merge success message
    await expect(page.locator('.merge-success')).toBeVisible();
    await expect(page.locator('.merge-success')).toContainText('Records successfully merged');
    
    // Verify we're redirected to the merged record
    await expect(page).toHaveURL(/.*action=DetailView/);
    await expect(page.locator('#amount')).toContainText('175,000'); // Higher amount was kept
  });

  test('Rapid typing and field clearing edge cases', async ({ page }) => {
    // Create original deal
    await createDeal(page, testDeals.original);
    
    await page.click('text=Create Deal');
    
    // Test rapid typing (should debounce)
    const nameField = page.locator('#name');
    
    // Type rapidly
    for (let i = 0; i < testDeals.duplicate.name.length; i++) {
      await nameField.type(testDeals.duplicate.name[i], { delay: 50 });
    }
    
    // Should only show one warning, not multiple
    await page.waitForTimeout(600); // Wait for debounce
    const warnings = await page.locator('.duplicate-warning').count();
    expect(warnings).toBe(1);
    
    // Clear field
    await nameField.clear();
    
    // Warning should disappear
    await expect(page.locator('.duplicate-warning')).not.toBeVisible();
    
    // Type different value
    await nameField.fill(testDeals.different.name);
    await page.click('#amount');
    
    // No warning should appear
    await page.waitForTimeout(500);
    await expect(page.locator('.duplicate-warning')).not.toBeVisible();
  });

  test('Form submission with duplicates - proceed anyway', async ({ page }) => {
    // Create original deal
    await createDeal(page, testDeals.original);
    
    // Create duplicate
    await page.click('text=Create Deal');
    await page.fill('#name', testDeals.duplicate.name);
    await page.fill('#email_c', testDeals.duplicate.email);
    await page.fill('#amount', testDeals.duplicate.amount);
    await page.selectOption('#sales_stage', testDeals.duplicate.stage);
    
    // Wait for duplicate warning
    await page.waitForSelector('.duplicate-warning');
    
    // Try to save
    await page.click('input[value="Save"]');
    
    // Verify confirmation dialog
    await expect(page.locator('.duplicate-confirmation-modal')).toBeVisible();
    await expect(page.locator('.duplicate-confirmation-modal')).toContainText('Are you sure you want to create this deal?');
    await expect(page.locator('.duplicate-confirmation-modal')).toContainText('1 potential duplicate found');
    
    // Click proceed anyway
    await page.click('.duplicate-proceed-button');
    
    // Verify deal is saved
    await expect(page).toHaveURL(/.*action=DetailView/);
    await expect(page.locator('.module-title-text')).toContainText(testDeals.duplicate.name);
  });

  test('Accessibility - duplicate warnings are screen reader friendly', async ({ page }) => {
    // Create original deal
    await createDeal(page, testDeals.original);
    
    // Start creating duplicate
    await page.click('text=Create Deal');
    await page.fill('#name', testDeals.duplicate.name);
    await page.click('#amount');
    
    // Wait for duplicate warning
    await page.waitForSelector('.duplicate-warning');
    
    // Check ARIA attributes
    const warning = page.locator('.duplicate-warning');
    await expect(warning).toHaveAttribute('role', 'alert');
    await expect(warning).toHaveAttribute('aria-live', 'polite');
    
    // Check action buttons have proper labels
    await expect(page.locator('.duplicate-action-view')).toHaveAttribute('aria-label', 'View duplicate record');
    await expect(page.locator('.duplicate-action-merge')).toHaveAttribute('aria-label', 'Merge with duplicate record');
    
    // Test keyboard navigation
    await page.keyboard.press('Tab');
    await expect(page.locator('.duplicate-action-view')).toBeFocused();
    
    await page.keyboard.press('Tab');
    await expect(page.locator('.duplicate-action-merge')).toBeFocused();
    
    // Can activate with Enter key
    await page.keyboard.press('Enter');
    await expect(page.locator('.merge-modal')).toBeVisible();
  });

  test('Visual regression - duplicate warning styles', async ({ page }) => {
    // Create original deal
    await createDeal(page, testDeals.original);
    
    // Test different warning levels
    await page.click('text=Create Deal');
    
    // Low confidence match (name only)
    await page.fill('#name', testDeals.duplicate.name);
    await page.click('#amount');
    await page.waitForSelector('.duplicate-warning');
    
    // Screenshot low confidence warning
    await page.screenshot({ 
      path: 'duplicate-warning-low-confidence.png',
      clip: await page.locator('.duplicate-warning').boundingBox()
    });
    
    // High confidence match (multiple fields)
    await page.fill('#email_c', testDeals.duplicate.email);
    await page.fill('#phone_c', testDeals.duplicate.phone);
    await page.click('#amount');
    await page.waitForTimeout(500);
    
    // Screenshot high confidence warning
    await page.screenshot({ 
      path: 'duplicate-warning-high-confidence.png',
      clip: await page.locator('.duplicate-warning').boundingBox()
    });
    
    // Test dark mode if available
    const darkModeToggle = await page.locator('.dark-mode-toggle').count();
    if (darkModeToggle > 0) {
      await page.click('.dark-mode-toggle');
      await page.waitForTimeout(300);
      
      await page.screenshot({ 
        path: 'duplicate-warning-dark-mode.png',
        clip: await page.locator('.duplicate-warning').boundingBox()
      });
    }
  });

  test('Performance - duplicate check completes quickly', async ({ page }) => {
    // Create multiple deals for performance testing
    for (let i = 0; i < 5; i++) {
      await createDeal(page, {
        ...testDeals.original,
        name: `${testDeals.original.name} ${i}`
      });
    }
    
    // Start creating new deal
    await page.click('text=Create Deal');
    
    // Measure duplicate check time
    const startTime = Date.now();
    await page.fill('#name', testDeals.original.name);
    await page.click('#amount');
    
    // Wait for duplicate warning
    await page.waitForSelector('.duplicate-warning', { timeout: 3000 });
    const endTime = Date.now();
    
    // Duplicate check should complete within 2 seconds
    expect(endTime - startTime).toBeLessThan(2000);
    
    // Verify correct number of duplicates found
    await expect(page.locator('.duplicate-count')).toContainText('5 potential duplicates found');
  });

  test('Multiple duplicate warnings with pagination', async ({ page }) => {
    // Create many duplicate deals
    for (let i = 0; i < 12; i++) {
      await createDeal(page, {
        ...testDeals.original,
        name: `Enterprise Software License - Acme Corp ${i}`,
        amount: `${150000 + (i * 10000)}`
      });
    }
    
    // Start creating new duplicate
    await page.click('text=Create Deal');
    await page.fill('#name', 'Enterprise Software License - Acme Corp');
    await page.click('#amount');
    
    // Wait for duplicate warning
    await page.waitForSelector('.duplicate-warning');
    
    // Verify pagination appears
    await expect(page.locator('.duplicate-list-pagination')).toBeVisible();
    await expect(page.locator('.duplicate-count')).toContainText('12 potential duplicates found');
    
    // Verify only first 5 are shown by default
    const visibleDuplicates = await page.locator('.duplicate-list-item').count();
    expect(visibleDuplicates).toBe(5);
    
    // Test pagination
    await page.click('.duplicate-pagination-next');
    await expect(page.locator('.duplicate-list-item').first()).toContainText('Acme Corp 5');
    
    // Test "Show All" option
    await page.click('.duplicate-show-all');
    const allDuplicates = await page.locator('.duplicate-list-item').count();
    expect(allDuplicates).toBe(12);
  });
});

// Helper functions

async function createDeal(page, dealData) {
  await page.click('text=Create Deal');
  await page.waitForSelector('#name');
  
  await page.fill('#name', dealData.name);
  await page.fill('#amount', dealData.amount);
  await page.selectOption('#sales_stage', dealData.stage);
  
  if (dealData.account) {
    await page.fill('#account_name', dealData.account);
  }
  
  if (dealData.email) {
    await page.fill('#email_c', dealData.email);
  }
  
  if (dealData.phone) {
    await page.fill('#phone_c', dealData.phone);
  }
  
  if (dealData.closeDate) {
    await page.fill('#date_closed', dealData.closeDate);
  }
  
  await page.click('input[value="Save"]');
  await page.waitForURL(/.*action=DetailView/);
  
  // Return to list view
  await page.click('text=Deals');
}

async function cleanupTestDeals(page) {
  // Navigate to list view
  await page.click('text=Deals');
  await page.waitForSelector('.list');
  
  // Search for test deals
  const testNames = [
    'Enterprise Software License - Acme Corp',
    'Software License - Acme Corp',
    'Cloud Services - Tech Solutions'
  ];
  
  for (const name of testNames) {
    // Search for deals with this name
    await page.fill('input[name="name_basic"]', name);
    await page.click('input[value="Search"]');
    await page.waitForTimeout(500);
    
    // Select all results
    const checkboxes = await page.locator('input[type="checkbox"][name="mass[]"]').count();
    if (checkboxes > 0) {
      await page.click('#massall');
      
      // Delete selected
      await page.selectOption('select[name="action"]', 'Delete');
      await page.click('#actionLinkTop > input');
      
      // Confirm deletion
      await page.click('text=Ok');
      await page.waitForTimeout(500);
    }
    
    // Clear search
    await page.click('text=Clear');
  }
}

// Visual regression test configuration
test.use({
  // Consistent viewport for visual tests
  viewport: { width: 1280, height: 720 },
  
  // Disable animations for consistent screenshots
  launchOptions: {
    args: ['--disable-web-animations']
  }
});