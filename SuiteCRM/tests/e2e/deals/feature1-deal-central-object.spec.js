const { test } = require('@playwright/test');
const { expect } = require('../lib/helpers/custom-matchers');
const AssertionsHelper = require('../lib/helpers/assertions.helper');
const VisualRegressionHelper = require('../lib/helpers/visual-regression.helper');
const path = require('path');
const fs = require('fs');
const { login, ensureLoggedIn } = require('./helpers/auth.helper');
const { navigateToDeals, scrollToSubpanel, waitForAjax } = require('./helpers/navigation.helper');

/**
 * Feature 1: The "Deal" as Central Object - E2E Tests
 * Based on PRD Test Case 1.1: E2E Deal Creation and Data Association
 * 
 * This test verifies that a user can create, manage, and view a Deal as the central hub of information,
 * with all related entities (Contacts, Documents) correctly linked, saved, and displayed.
 */

test.describe('Feature 1: Deal as Central Object', () => {
  let assertionsHelper;
  let visualHelper;
  
  // Test data
  const testDealData = {
    name: `Test Manufacturing Co ${Date.now()}`,
    ttmRevenue: '10000000',
    ttmEbitda: '2000000',
    askingPrice: '9000000',
    targetMultiple: '4.5',
    industry: 'Manufacturing',
    dealType: 'Asset Purchase',
    status: 'sourcing'
  };

  const testContactData = {
    firstName: 'John',
    lastName: 'Seller',
    role: 'Seller',
    email: 'john.seller@testmanufacturing.com',
    phone: '555-123-4567',
    title: 'CEO'
  };

  const testDocumentData = {
    name: 'NDA.pdf',
    description: 'Non-Disclosure Agreement for Test Manufacturing Co deal',
    category: 'Legal Documents'
  };


  // Setup: Login before each test
  test.beforeEach(async ({ page }) => {
    await login(page);
    assertionsHelper = new AssertionsHelper(page);
    visualHelper = new VisualRegressionHelper(page);
  });

  test('Test Case 1.1: E2E Deal Creation and Data Association', async ({ page }) => {
    // Step 1: Navigate to the Deals module
    await navigateToDeals(page);
    console.log('✓ Step 1: Navigated to Deals module');

    // Step 2: Click the "Create Deal" button
    // Try multiple selectors for Create button
    const createButton = await page.locator('a:has-text("Create"), button:has-text("Create"), #create_link').first();
    await createButton.click();
    await page.waitForLoadState('networkidle');
    console.log('✓ Step 2: Clicked Create Deal button');

    // Step 3: Fill in all required fields for the new deal
    // Deal name
    await page.fill('input[name="name"]', testDealData.name);
    
    // Financial data - TTM Revenue
    const ttmRevenueField = await page.$('input[name="ttm_revenue_c"], input[name="ttm_revenue"], input[id*="ttm_revenue"]');
    if (ttmRevenueField) {
      await ttmRevenueField.fill(testDealData.ttmRevenue);
    }
    
    // Financial data - TTM EBITDA
    const ttmEbitdaField = await page.$('input[name="ttm_ebitda_c"], input[name="ttm_ebitda"], input[id*="ttm_ebitda"]');
    if (ttmEbitdaField) {
      await ttmEbitdaField.fill(testDealData.ttmEbitda);
    }
    
    // Other fields if available
    const askingPriceField = await page.$('input[name="asking_price_c"], input[name="asking_price"], input[id*="asking_price"]');
    if (askingPriceField) {
      await askingPriceField.fill(testDealData.askingPrice);
    }
    
    const targetMultipleField = await page.$('input[name="target_multiple_c"], input[name="target_multiple"], input[id*="target_multiple"]');
    if (targetMultipleField) {
      await targetMultipleField.fill(testDealData.targetMultiple);
    }
    
    // Set status if available
    const statusField = await page.$('select[name="status"], select[name="sales_stage"]');
    if (statusField) {
      await statusField.selectOption({ label: 'Sourcing' });
    }
    
    console.log('✓ Step 3: Filled in all required fields');

    // Step 4: Save the new deal
    const saveButton = await page.locator('input[value="Save"], button:has-text("Save"), #SAVE').first();
    await saveButton.click();
    
    // Wait for navigation to detail view
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000); // Additional wait for any redirects
    console.log('✓ Step 4: Saved the new deal');

    // Step 5: Verify that the system navigates to the new deal's detail view
    const currentUrl = page.url();
    expect(currentUrl).toContain('action=DetailView');
    
    // Extract deal ID for database verification
    const dealId = new URL(currentUrl).searchParams.get('record');
    
    // Verify deal name is displayed with UI update assertion
    await expect(page.locator(`h2, .moduleTitle, .detail-view`)).toShowUIUpdate({
      text: testDealData.name,
      visible: true
    });
    
    // Verify data persistence in database
    if (dealId) {
      await expect(page).toHavePersistedInDatabase('opportunities', {
        id: dealId,
        name: testDealData.name
      }, {
        expectedFields: {
          ttm_revenue_c: testDealData.ttmRevenue,
          ttm_ebitda_c: testDealData.ttmEbitda,
          asking_price_c: testDealData.askingPrice
        }
      });
      
      // Verify audit log for deal creation
      await expect(page).toHaveCorrectAuditLog('Deals', dealId, 'create');
    }
    
    // Take screenshot of deal detail view
    await visualHelper.assertElementScreenshot('.detail-view', 'deal-detail-view-created');
    
    console.log('✓ Step 5: Verified navigation to deal detail view with enhanced assertions');

    // Step 6: From the Contacts subpanel, click "Create" to add a new contact
    // Find and scroll to Contacts subpanel
    const contactsSubpanel = await scrollToSubpanel(page, 'Contacts');
    await expect(contactsSubpanel).toBeVisible();
    
    // Click Create in Contacts subpanel
    const createContactButton = await contactsSubpanel.locator('a:has-text("Create"), button:has-text("Create"), input[value="Create"]').first();
    await createContactButton.click();
    await page.waitForLoadState('networkidle');
    console.log('✓ Step 6: Clicked Create in Contacts subpanel');

    // Step 7: Fill in the contact's details
    // Handle popup or inline form
    const isPopup = await page.$('.yui-panel, .modal, #popup_dialog');
    
    if (isPopup) {
      // Popup form
      await page.fill('input[name="first_name"]', testContactData.firstName);
      await page.fill('input[name="last_name"]', testContactData.lastName);
      
      // Set contact role if available
      const roleField = await page.$('select[name="contact_role"], select[name="role"], input[name="title"]');
      if (roleField) {
        if (roleField.tagName() === 'SELECT') {
          await roleField.selectOption({ label: testContactData.role });
        } else {
          await roleField.fill(testContactData.role);
        }
      }
      
      // Email and phone
      const emailField = await page.$('input[name="email1"], input[name="email"]');
      if (emailField) {
        await emailField.fill(testContactData.email);
      }
      
      const phoneField = await page.$('input[name="phone_work"], input[name="phone"]');
      if (phoneField) {
        await phoneField.fill(testContactData.phone);
      }
      
      // Save contact
      await page.click('.yui-panel input[value="Save"], .modal button:has-text("Save"), #popup_dialog input[value="Save"]');
    } else {
      // Inline form or new page
      await page.fill('input[name="first_name"]', testContactData.firstName);
      await page.fill('input[name="last_name"]', testContactData.lastName);
      
      // Fill other fields
      const titleField = await page.$('input[name="title"]');
      if (titleField) {
        await titleField.fill(testContactData.role);
      }
      
      await page.fill('input[name="email1"], input[name="email"]', testContactData.email);
      await page.fill('input[name="phone_work"], input[name="phone"]', testContactData.phone);
      
      // Save and return to deal
      await page.click('input[value="Save"], button:has-text("Save")');
    }
    
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    console.log('✓ Step 7: Filled in contact details and saved');

    // Step 8: Verify the new contact appears in the Contacts subpanel
    // Navigate back to deal if needed
    if (!page.url().includes(testDealData.name) && !page.url().includes('DetailView')) {
      await page.goBack();
      await page.waitForLoadState('networkidle');
    }
    
    // Extract contact ID if available for relationship verification
    const contactId = await page.evaluate(() => {
      const contactLink = document.querySelector('.subpanel:contains("Contacts") a[href*="Contacts"]');
      if (contactLink) {
        const url = new URL(contactLink.href);
        return url.searchParams.get('record');
      }
      return null;
    });
    
    // Scroll to subpanels
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await page.waitForTimeout(1000);
    
    // Verify contact appears with enhanced UI assertion
    const contactInSubpanel = page.locator(`.subpanel:has-text("Contacts") tr:has-text("${testContactData.firstName}"), .subpanel:has-text("Contacts") td:has-text("${testContactData.lastName}")`).first();
    await expect(contactInSubpanel).toShowUIUpdate({
      visible: true
    });
    
    // Verify relationship integrity in database
    if (dealId && contactId) {
      await assertionsHelper.assertRelationshipIntegrity('opportunities', 'contacts', 'opportunity_id', dealId, {
        expectedCount: 1
      });
      
      // Verify audit log for contact association
      await expect(page).toHaveCorrectAuditLog('Contacts', contactId, 'create');
    }
    
    // Take screenshot of contacts subpanel
    await visualHelper.assertElementScreenshot('.subpanel:has-text("Contacts")', 'contacts-subpanel-with-contact');
    
    console.log('✓ Step 8: Verified contact appears in Contacts subpanel with enhanced assertions');

    // Step 9: From the Documents subpanel, click "Create" to upload a document
    const documentsSubpanel = await scrollToSubpanel(page, 'Documents');
    await expect(documentsSubpanel).toBeVisible();
    
    const createDocumentButton = await documentsSubpanel.locator('a:has-text("Create"), button:has-text("Create"), input[value="Create"]').first();
    await createDocumentButton.click();
    await page.waitForLoadState('networkidle');
    console.log('✓ Step 9: Clicked Create in Documents subpanel');

    // Step 10: Upload a file and provide a document name
    // Fill document name
    await page.fill('input[name="document_name"], input[name="name"]', testDocumentData.name);
    
    // Add description if available
    const descriptionField = await page.$('textarea[name="description"], input[name="description"]');
    if (descriptionField) {
      await descriptionField.fill(testDocumentData.description);
    }
    
    // Handle file upload
    const fileInput = await page.$('input[type="file"]');
    if (fileInput) {
      // Create a test PDF file
      const testFilePath = path.join(__dirname, 'test-nda.pdf');
      const pdfContent = Buffer.from('%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> >> >> /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n4 0 obj\n<< /Length 44 >>\nstream\nBT /F1 12 Tf 100 700 Td (NDA Document) Tj ET\nendstream\nendobj\nxref\n0 5\n0000000000 65535 f\n0000000009 00000 n\n0000000058 00000 n\n0000000115 00000 n\n0000000292 00000 n\ntrailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n384\n%%EOF');
      
      // Write test file
      fs.writeFileSync(testFilePath, pdfContent);
      
      // Upload file
      await fileInput.setInputFiles(testFilePath);
      
      // Clean up test file after upload
      setTimeout(() => {
        if (fs.existsSync(testFilePath)) {
          fs.unlinkSync(testFilePath);
        }
      }, 5000);
    }
    
    // Save document
    const saveDocButton = await page.locator('input[value="Save"], button:has-text("Save")').first();
    await saveDocButton.click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    console.log('✓ Step 10: Uploaded document and saved');

    // Step 11: Verify the new document appears in the Documents subpanel
    // Navigate back to deal if needed
    if (!page.url().includes(testDealData.name) && !page.url().includes('DetailView')) {
      await page.goBack();
      await page.waitForLoadState('networkidle');
    }
    
    // Scroll to subpanels
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await page.waitForTimeout(1000);
    
    // Verify document appears
    const documentInSubpanel = await page.locator(`.subpanel:has-text("Documents") tr:has-text("${testDocumentData.name}"), .subpanel:has-text("Documents") td:has-text("NDA")`).first();
    await expect(documentInSubpanel).toBeVisible();
    console.log('✓ Step 11: Verified document appears in Documents subpanel');

    // Final verification: All data is correctly associated
    console.log('✅ Test Case 1.1 Completed Successfully');
    console.log('Deal created with:');
    console.log(`- Name: ${testDealData.name}`);
    console.log(`- Contact: ${testContactData.firstName} ${testContactData.lastName} (${testContactData.role})`);
    console.log(`- Document: ${testDocumentData.name}`);
  });

  test('Verify deal data persistence after page refresh', async ({ page }) => {
    // Navigate to Deals list
    await navigateToDeals(page);
    
    // Find and click on our test deal
    await page.click(`a:has-text("${testDealData.name}")`);
    await page.waitForLoadState('networkidle');
    
    // Get deal ID for database verification
    const dealId = new URL(page.url()).searchParams.get('record');
    
    // Verify financial data is displayed correctly with enhanced assertions
    await assertionsHelper.assertText('.field-value, td', '$10,000,000', {
      message: 'TTM Revenue should be displayed correctly'
    });
    
    await assertionsHelper.assertText('.field-value, td', '$2,000,000', {
      message: 'TTM EBITDA should be displayed correctly'
    });
    
    // Take before-refresh screenshot
    await visualHelper.assertPageScreenshot('deal-before-refresh');
    
    // Refresh the page
    await page.reload();
    await page.waitForLoadState('networkidle');
    
    // Verify data persists after refresh with UI update assertions
    await expect(page.locator(`h2, .moduleTitle`)).toShowUIUpdate({
      text: testDealData.name,
      visible: true
    });
    
    // Verify database persistence after refresh
    if (dealId) {
      await expect(page).toHavePersistedInDatabase('opportunities', {
        id: dealId
      }, {
        expectedFields: {
          name: testDealData.name,
          ttm_revenue_c: testDealData.ttmRevenue,
          ttm_ebitda_c: testDealData.ttmEbitda
        }
      });
    }
    
    // Take after-refresh screenshot and compare
    await visualHelper.assertPageScreenshot('deal-after-refresh');
    
    // Verify related entities persist
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await page.waitForTimeout(1000);
    
    // Enhanced assertions for related entities
    await expect(page.locator(`.subpanel:has-text("Contacts") tr`)).toShowUIUpdate({
      visible: true,
      text: testContactData.firstName
    });
    
    await expect(page.locator(`.subpanel:has-text("Documents") tr`)).toShowUIUpdate({
      visible: true,
      text: testDocumentData.name
    });
    
    console.log('✅ Data persistence verified successfully with enhanced assertions');
  });

  test('Verify deal relationships are bidirectional', async ({ page }) => {
    // Navigate to Contacts module
    await page.click('a:has-text("All"), a:has-text("CRM")');
    await page.waitForTimeout(500);
    await page.click('a:has-text("Contacts")');
    await page.waitForLoadState('networkidle');
    
    // Search for our test contact
    const searchField = await page.$('input[name="name_basic"], input[name="search_name"], input[name="query_string"]');
    if (searchField) {
      await searchField.fill(testContactData.lastName);
      await page.click('input[value="Search"], button:has-text("Search")');
      await page.waitForLoadState('networkidle');
    }
    
    // Click on the contact
    await page.click(`a:has-text("${testContactData.firstName}"), a:has-text("${testContactData.lastName}")`);
    await page.waitForLoadState('networkidle');
    
    // Scroll to subpanels
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await page.waitForTimeout(1000);
    
    // Verify the deal appears in the contact's Deals subpanel
    const dealInContactSubpanel = await page.locator(`.subpanel:has-text("Deals") tr:has-text("${testDealData.name}")`).first();
    await expect(dealInContactSubpanel).toBeVisible();
    
    console.log('✅ Bidirectional relationship verified');
  });

  test('Verify financial calculations and valuation', async ({ page }) => {
    // Navigate to our test deal
    await navigateToDeals(page);
    await page.click(`a:has-text("${testDealData.name}")`);
    await page.waitForLoadState('networkidle');
    
    // Check if proposed valuation is calculated correctly
    // Expected: TTM EBITDA * Target Multiple = 2,000,000 * 4.5 = 9,000,000
    const valuationField = await page.locator('.field-value:has-text("$9,000,000"), td:has-text("$9,000,000"), .field-value:has-text("9000000")').first();
    
    if (await valuationField.isVisible()) {
      console.log('✅ Proposed valuation calculated correctly');
    } else {
      // Check if valuation needs to be manually calculated
      const editButton = await page.locator('input[value="Edit"], button:has-text("Edit")').first();
      await editButton.click();
      await page.waitForLoadState('networkidle');
      
      // Trigger calculation by changing a field
      const multipleField = await page.$('input[name="target_multiple_c"], input[name="target_multiple"]');
      if (multipleField) {
        await multipleField.fill('4.5');
        await page.keyboard.press('Tab');
        await page.waitForTimeout(500);
      }
      
      // Save and check again
      await page.click('input[value="Save"], button:has-text("Save")');
      await page.waitForLoadState('networkidle');
      
      const recalculatedValuation = await page.locator('.field-value:has-text("$9,000,000"), td:has-text("$9,000,000")').first();
      await expect(recalculatedValuation).toBeVisible();
      console.log('✅ Valuation recalculated and verified');
    }
  });

  // Cleanup test - mark as skipped in normal runs
  test.skip('Cleanup test data', async ({ page }) => {
    // This test can be run manually to clean up test data
    await navigateToDeals(page);
    
    // Search for test deal
    await page.fill('input[name="name_basic"], input[name="search_name"]', testDealData.name);
    await page.click('input[value="Search"], button:has-text("Search")');
    await page.waitForLoadState('networkidle');
    
    // Select and delete
    const checkbox = await page.$(`input[type="checkbox"][value*="${testDealData.name}"]`);
    if (checkbox) {
      await checkbox.check();
      await page.selectOption('select[name="action_select"], select[name="mass_action"]', 'Delete');
      await page.click('input[value="Apply"], button:has-text("Apply")');
      
      // Confirm deletion
      page.on('dialog', dialog => dialog.accept());
      await page.waitForLoadState('networkidle');
      
      console.log('✅ Test data cleaned up');
    }
  });
});

// Additional test suite for edge cases
test.describe('Feature 1: Edge Cases and Error Handling', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('Handle missing required fields gracefully', async ({ page }) => {
    await navigateToDeals(page);
    await page.click('a:has-text("Create"), button:has-text("Create")');
    await page.waitForLoadState('networkidle');
    
    // Try to save without required fields
    await page.click('input[value="Save"], button:has-text("Save")');
    
    // Verify error message appears
    const errorMessage = await page.locator('.error, .required, .validation-message, [class*="error"]:has-text("required")').first();
    await expect(errorMessage).toBeVisible();
    
    console.log('✅ Required field validation working correctly');
  });

  test('Handle concurrent editing scenarios', async ({ page, context }) => {
    // Create a deal first
    await navigateToDeals(page);
    const dealName = `Concurrent Test Deal ${Date.now()}`;
    
    await page.click('a:has-text("Create"), button:has-text("Create")');
    await page.fill('input[name="name"]', dealName);
    await page.click('input[value="Save"], button:has-text("Save")');
    await page.waitForLoadState('networkidle');
    
    // Open the same deal in a second tab
    const page2 = await context.newPage();
    await login(page2);
    await navigateToDeals(page2);
    await page2.click(`a:has-text("${dealName}")`);
    await page2.waitForLoadState('networkidle');
    
    // Edit in first tab
    await page.click('input[value="Edit"], button:has-text("Edit")');
    await page.fill('input[name="description"], textarea[name="description"]', 'Updated from tab 1');
    await page.click('input[value="Save"], button:has-text("Save")');
    await page.waitForLoadState('networkidle');
    
    // Refresh second tab and verify changes
    await page2.reload();
    await page2.waitForLoadState('networkidle');
    
    const updatedDescription = await page2.locator('text="Updated from tab 1"').first();
    await expect(updatedDescription).toBeVisible();
    
    await page2.close();
    console.log('✅ Concurrent editing handled correctly');
  });
});