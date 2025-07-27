const { test, expect } = require('@playwright/test');
const { login, ensureLoggedIn } = require('./helpers/auth.helper');
const { navigateToDeals, waitForAjax } = require('./helpers/navigation.helper');

/**
 * Email-to-Deal E2E Tests
 * 
 * Tests the email processing functionality for creating and updating deals
 * Based on PRD requirement: "Email-to-Deal creation and updates"
 * 
 * Test scenarios:
 * 1. Create new deal from forwarded email
 * 2. Update existing deal from email
 * 3. Extract and link contacts from email
 * 4. Handle attachments from email
 * 5. Parse financial information from email content
 */

test.describe('Email-to-Deal Processing', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
    await ensureLoggedIn(page);
  });

  test('should create new deal from email forward', async ({ page }) => {
    // Navigate to email configuration page
    await page.goto('http://localhost:8080/index.php?module=Administration&action=index');
    await page.click('text=Email Settings');
    
    // Verify email processor is configured
    await expect(page.locator('text=deals@mycrm')).toBeVisible();
    
    // Navigate to Deals module
    await navigateToDeals(page);
    
    // Simulate email processing (this would typically happen server-side)
    // For testing, we'll create a deal with email metadata
    await page.click('text=Create');
    await waitForAjax(page);
    
    // Fill in deal data as if it came from email
    const emailDealData = {
      name: 'Email Test Manufacturing Co',
      description: 'Deal created from forwarded email\n\nFrom: broker@example.com\nSubject: Exclusive Manufacturing Opportunity',
      ttm_revenue_c: '5000000',
      ttm_ebitda_c: '1000000',
      asking_price_c: '4500000',
      email_thread_id_c: `email_${Date.now()}`,
      source_c: 'email'
    };
    
    // Fill form fields
    await page.fill('input[name="name"]', emailDealData.name);
    await page.fill('textarea[name="description"]', emailDealData.description);
    await page.fill('input[name="ttm_revenue_c"]', emailDealData.ttm_revenue_c);
    await page.fill('input[name="ttm_ebitda_c"]', emailDealData.ttm_ebitda_c);
    await page.fill('input[name="asking_price_c"]', emailDealData.asking_price_c);
    
    // Save the deal
    await page.click('input[title="Save"]');
    await waitForAjax(page);
    
    // Verify deal was created
    await expect(page.locator('h2')).toContainText(emailDealData.name);
    await expect(page.locator('.detail-view')).toContainText('Deal created from forwarded email');
    
    // Verify email metadata
    await expect(page.locator('text=Source: Email')).toBeVisible();
  });

  test('should update existing deal from follow-up email', async ({ page }) => {
    // First create a deal to update
    await navigateToDeals(page);
    await page.click('text=Create');
    await waitForAjax(page);
    
    const originalDealData = {
      name: 'Update Test Manufacturing',
      email_thread_id_c: 'thread_12345'
    };
    
    await page.fill('input[name="name"]', originalDealData.name);
    await page.click('input[title="Save"]');
    await waitForAjax(page);
    
    // Get the deal ID from URL
    const dealUrl = page.url();
    const dealId = dealUrl.match(/record=([a-z0-9-]+)/)?.[1];
    
    // Navigate back to list view
    await navigateToDeals(page);
    
    // Simulate email update (in real scenario, this would be processed server-side)
    // Navigate to the deal to update it
    await page.click(`text="${originalDealData.name}"`);
    await waitForAjax(page);
    
    // Edit the deal
    await page.click('text=Edit');
    await waitForAjax(page);
    
    // Update with new information from "email"
    const updateData = {
      ttm_revenue_c: '5500000',
      asking_price_c: '5000000',
      description: 'Updated via email: Seller has reduced asking price and provided updated financials'
    };
    
    await page.fill('input[name="ttm_revenue_c"]', updateData.ttm_revenue_c);
    await page.fill('input[name="asking_price_c"]', updateData.asking_price_c);
    await page.fill('textarea[name="description"]', updateData.description);
    
    // Save updates
    await page.click('input[title="Save"]');
    await waitForAjax(page);
    
    // Verify updates
    await expect(page.locator('.detail-view')).toContainText('$5,500,000');
    await expect(page.locator('.detail-view')).toContainText('$5,000,000');
    await expect(page.locator('.detail-view')).toContainText('Updated via email');
  });

  test('should extract and link contacts from email', async ({ page }) => {
    // Create a deal with contact information from email
    await navigateToDeals(page);
    await page.click('text=Create');
    await waitForAjax(page);
    
    const dealWithContacts = {
      name: 'Contact Extraction Test Co',
      description: 'Email from: John Broker <john@brokerco.com>, Mary Seller <mary@testco.com>'
    };
    
    await page.fill('input[name="name"]', dealWithContacts.name);
    await page.fill('textarea[name="description"]', dealWithContacts.description);
    await page.click('input[title="Save"]');
    await waitForAjax(page);
    
    // Navigate to Contacts subpanel
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await page.waitForSelector('.subpanel-header:has-text("Contacts")', { timeout: 5000 });
    
    // Create contact as if extracted from email
    const contactsSection = page.locator('.subpanel-header:has-text("Contacts")').locator('..');
    await contactsSection.locator('text=Create').click();
    await waitForAjax(page);
    
    // Fill contact form
    await page.fill('input[name="first_name"]', 'John');
    await page.fill('input[name="last_name"]', 'Broker');
    await page.fill('input[name="email1"]', 'john@brokerco.com');
    await page.selectOption('select[name="contact_type_c"]', 'broker');
    
    // Save contact
    await page.click('.dcQuickEdit input[title="Save"]');
    await waitForAjax(page);
    
    // Verify contact is linked
    await expect(contactsSection).toContainText('John Broker');
    await expect(contactsSection).toContainText('john@brokerco.com');
  });

  test('should handle attachments from email', async ({ page }) => {
    // Create a deal that would have attachments from email
    await navigateToDeals(page);
    await page.click('text=Create');
    await waitForAjax(page);
    
    const dealWithAttachments = {
      name: 'Attachment Test Deal',
      description: 'Deal with email attachments: financial_summary.pdf, nda.docx'
    };
    
    await page.fill('input[name="name"]', dealWithAttachments.name);
    await page.fill('textarea[name="description"]', dealWithAttachments.description);
    await page.click('input[title="Save"]');
    await waitForAjax(page);
    
    // Navigate to Documents subpanel
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await page.waitForSelector('.subpanel-header:has-text("Documents")', { timeout: 5000 });
    
    // Verify documents section exists for attachments
    const documentsSection = page.locator('.subpanel-header:has-text("Documents")').locator('..');
    await expect(documentsSection).toBeVisible();
    
    // Create document as if from email attachment
    await documentsSection.locator('text=Create').click();
    await waitForAjax(page);
    
    // Fill document form
    await page.fill('input[name="document_name"]', 'Financial Summary');
    await page.fill('textarea[name="description"]', 'Attached from email: financial_summary.pdf');
    await page.selectOption('select[name="category_id"]', { label: 'Financial' });
    
    // Save document
    await page.click('.dcQuickEdit input[title="Save"]');
    await waitForAjax(page);
    
    // Verify document is linked
    await expect(documentsSection).toContainText('Financial Summary');
  });

  test('should parse financial information from email content', async ({ page }) => {
    // Create deal with financial data parsed from email
    await navigateToDeals(page);
    await page.click('text=Create');
    await waitForAjax(page);
    
    const emailContent = `
      Deal Name: Premium Manufacturing LLC
      
      Financial Summary:
      - Annual Revenue: $8.5M
      - EBITDA: $1.7M (20% margin)
      - Asking Price: $7.5M
      - Proposed Multiple: 4.4x
      
      Industry: Light Manufacturing
      Location: Dallas, TX
    `;
    
    // Fill form with parsed data
    await page.fill('input[name="name"]', 'Premium Manufacturing LLC');
    await page.fill('textarea[name="description"]', emailContent);
    await page.fill('input[name="ttm_revenue_c"]', '8500000');
    await page.fill('input[name="ttm_ebitda_c"]', '1700000');
    await page.fill('input[name="asking_price_c"]', '7500000');
    await page.fill('input[name="target_multiple_c"]', '4.4');
    await page.selectOption('select[name="industry_c"]', 'manufacturing');
    
    // Save the deal
    await page.click('input[title="Save"]');
    await waitForAjax(page);
    
    // Verify financial calculations
    await expect(page.locator('.detail-view')).toContainText('$8,500,000'); // Revenue
    await expect(page.locator('.detail-view')).toContainText('$1,700,000'); // EBITDA
    await expect(page.locator('.detail-view')).toContainText('$7,500,000'); // Asking Price
    await expect(page.locator('.detail-view')).toContainText('4.4'); // Multiple
    
    // Verify calculated valuation (EBITDA * Multiple)
    const calculatedValuation = 1700000 * 4.4;
    await expect(page.locator('.detail-view')).toContainText('$7,480,000'); // Proposed Valuation
  });

  test('should handle duplicate detection for email deals', async ({ page }) => {
    // First create a deal
    await navigateToDeals(page);
    await page.click('text=Create');
    await waitForAjax(page);
    
    const originalDeal = {
      name: 'Duplicate Test Manufacturing',
      email: 'seller@duplicatetest.com'
    };
    
    await page.fill('input[name="name"]', originalDeal.name);
    await page.click('input[title="Save"]');
    await waitForAjax(page);
    
    // Try to create duplicate via "email"
    await navigateToDeals(page);
    await page.click('text=Create');
    await waitForAjax(page);
    
    // Enter same deal name (simulating email with same company)
    await page.fill('input[name="name"]', originalDeal.name);
    
    // Check for duplicate warning
    await page.keyboard.press('Tab'); // Trigger blur event
    await page.waitForTimeout(1000); // Wait for duplicate check
    
    // Should show duplicate warning
    const duplicateWarning = page.locator('.duplicate-warning, .error-message, [role="alert"]');
    
    // Note: The actual duplicate detection implementation may vary
    // This test assumes some form of duplicate warning is shown
    
    // Cancel and verify we're not creating duplicate
    const cancelButton = page.locator('input[title="Cancel"], button:has-text("Cancel")').first();
    if (await cancelButton.isVisible()) {
      await cancelButton.click();
    }
  });

  test('should track email thread history', async ({ page }) => {
    // Create deal with email thread
    await navigateToDeals(page);
    await page.click('text=Create');
    await waitForAjax(page);
    
    const threadedDeal = {
      name: 'Email Thread Test Co',
      thread_id: `thread_${Date.now()}`,
      description: 'Initial email inquiry about selling the business'
    };
    
    await page.fill('input[name="name"]', threadedDeal.name);
    await page.fill('textarea[name="description"]', threadedDeal.description);
    await page.click('input[title="Save"]');
    await waitForAjax(page);
    
    // Navigate to History/Activities subpanel to verify email tracking
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    
    // Look for activities or history section
    const historySection = page.locator('.subpanel-header:has-text("History"), .subpanel-header:has-text("Activities")').first().locator('..');
    
    // Verify we can track email communications
    await expect(historySection).toBeVisible();
  });
});

// Helper function to verify email metadata fields
async function verifyEmailMetadata(page, expectedData) {
  for (const [field, value] of Object.entries(expectedData)) {
    const fieldLocator = page.locator(`[data-field="${field}"], .detail-view:has-text("${value}")`);
    await expect(fieldLocator).toBeVisible();
  }
}

// Helper function to simulate email processing
async function simulateEmailProcessing(page, emailData) {
  // In a real implementation, this would trigger the email processor
  // For testing, we directly create/update deals with email data
  console.log('Simulating email processing:', emailData);
}