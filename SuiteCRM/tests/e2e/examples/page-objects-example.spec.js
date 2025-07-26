const { test, expect } = require('@playwright/test');
const { 
  LoginPage, 
  DealPage, 
  ContactPage, 
  DocumentPage, 
  ChecklistPage, 
  PipelinePage, 
  NavigationComponent 
} = require('../page-objects');

/**
 * Example test suite demonstrating Page Object Model usage
 */
test.describe('Page Objects Example Tests', () => {
  let loginPage;
  let dealPage;
  let contactPage;
  let documentPage;
  let checklistPage;
  let pipelinePage;
  let navigation;

  test.beforeEach(async ({ page }) => {
    // Initialize page objects
    loginPage = new LoginPage(page);
    dealPage = new DealPage(page);
    contactPage = new ContactPage(page);
    documentPage = new DocumentPage(page);
    checklistPage = new ChecklistPage(page);
    pipelinePage = new PipelinePage(page);
    navigation = new NavigationComponent(page);

    // Login before each test
    await loginPage.goto();
    await loginPage.loginAsAdmin();
    
    // Verify login was successful
    expect(await loginPage.isLoggedIn()).toBeTruthy();
  });

  test.afterEach(async () => {
    // Logout after each test
    await navigation.logout();
  });

  test('Complete deal workflow using page objects', async ({ page }) => {
    // Test data
    const dealData = {
      name: `POM Test Deal ${Date.now()}`,
      status: 'initial_contact',
      source: 'broker',
      dealValue: '2500000',
      ttmRevenue: '5000000',
      ttmEbitda: '1000000',
      targetMultiple: '5',
      description: 'Test deal created using Page Object Model'
    };

    const contactData = {
      firstName: 'Jane',
      lastName: 'Smith',
      title: 'CFO',
      email: 'jane.smith@testcompany.com',
      phoneWork: '555-0123',
      role: 'decision_maker',
      isDecisionMaker: true,
      stakeholderType: 'primary'
    };

    // Step 1: Navigate to Deals module
    await navigation.navigateToDeals();
    await expect(page).toHaveURL(/module=qd_Deals/);

    // Step 2: Create a new deal
    await dealPage.createDeal(dealData);
    
    // Verify deal was created
    const dealTitle = await dealPage.getDealTitle();
    expect(dealTitle).toContain(dealData.name);
    
    // Check calculated valuation
    const valuation = await dealPage.getFieldValue('Proposed Valuation');
    expect(valuation).toContain('$5,000,000'); // 1M EBITDA * 5 multiple

    // Step 3: Create and link a contact
    await navigation.navigateToContacts();
    await contactPage.createContact(contactData);
    
    // Verify contact was created
    const contactName = await contactPage.getContactFullName();
    expect(contactName).toContain('Jane Smith');
    
    // Link contact to deal
    await contactPage.addToDeal(dealData.name);
    
    // Step 4: Upload a document
    await navigation.navigateToDeals();
    await dealPage.openDeal(dealData.name);
    
    // Navigate to documents subpanel
    await page.click('a:has-text("Documents")');
    
    // Create a test document
    const documentData = {
      name: 'Test Financial Document',
      category: 'Financial',
      status: 'Active',
      description: 'Test document for POM example',
      type: 'pdf'
    };
    
    // Note: In real test, you would upload an actual file
    await page.click('.subpanel-header button:has-text("Create")');
    await documentPage.uploadDocument(documentData);

    // Step 5: Apply a checklist
    await checklistPage.gotoChecklists();
    
    // Apply a checklist template (assuming one exists)
    await checklistPage.applyTemplate('Due Diligence Checklist', {
      dealId: dealData.name,
      sendReminders: true,
      reminderDays: 7,
      notificationEmails: 'test@example.com'
    });

    // Complete some checklist items
    await checklistPage.completeItem(0, 'Initial review completed');
    await checklistPage.completeItem(1, 'Financial documents collected');
    
    // Verify progress
    const progress = await checklistPage.getChecklistProgress();
    expect(progress.percentage).toBeGreaterThan(0);

    // Step 6: Move deal through pipeline
    await navigation.navigateToDeals();
    await pipelinePage.goto();
    
    // Wait for pipeline to load
    await pipelinePage.waitForPipelineLoad();
    
    // Get initial stage information
    const stages = await pipelinePage.getStages();
    expect(stages.length).toBeGreaterThan(0);
    
    // Move deal to next stage
    await pipelinePage.dragDealToStage(dealData.name, 'contacted');
    
    // Verify notification
    expect(await pipelinePage.hasNotification('success')).toBeTruthy();
    const notificationMessage = await pipelinePage.getNotificationMessage();
    expect(notificationMessage).toContain('moved successfully');
    
    // Verify deal is in new stage
    const contactedDeals = await pipelinePage.getDealsInStage('contacted');
    const movedDeal = contactedDeals.find(d => d.title.includes(dealData.name));
    expect(movedDeal).toBeDefined();
  });

  test('Test navigation component', async ({ page }) => {
    // Test main navigation
    await navigation.navigateToAccounts();
    await expect(page).toHaveURL(/module=Accounts/);
    
    await navigation.navigateToDeals();
    await expect(page).toHaveURL(/module=Deals/);
    
    await navigation.navigateToLeads();
    await expect(page).toHaveURL(/module=Leads/);
    
    // Test quick create
    await navigation.quickCreate('Deal');
    await expect(page).toHaveURL(/action=EditView.*module=qd_Deals/);
    
    // Cancel and go back
    await page.click('input[value="Cancel"]');
    
    // Test global search
    await navigation.globalSearch('test search term');
    await expect(page).toHaveURL(/query_string=test\+search\+term/);
    
    // Test user menu
    await navigation.goToProfile();
    await expect(page).toHaveURL(/module=Users.*action=EditView/);
    
    // Go back
    await page.goBack();
    
    // Check notifications
    const notificationCount = await navigation.getNotificationCount();
    expect(notificationCount).toBeGreaterThanOrEqual(0);
  });

  test('Test pipeline drag and drop with WIP limits', async ({ page }) => {
    await navigation.navigateToDeals();
    await pipelinePage.goto();
    await pipelinePage.waitForPipelineLoad();
    
    // Check if qualified stage is at WIP limit
    const isAtLimit = await pipelinePage.isStageAtWipLimit('qualified');
    
    if (isAtLimit) {
      // Try to move a deal to qualified stage
      const deals = await pipelinePage.getDealsInStage('initial_contact');
      if (deals.length > 0) {
        await pipelinePage.dragDealToStage(deals[0].title, 'qualified');
        
        // Handle WIP warning
        await pipelinePage.handleWipWarning(false); // Cancel the move
        
        // Verify deal wasn't moved
        const qualifiedDeals = await pipelinePage.getDealsInStage('qualified');
        const movedDeal = qualifiedDeals.find(d => d.title === deals[0].title);
        expect(movedDeal).toBeUndefined();
      }
    }
  });

  test('Test document version control', async ({ page }) => {
    // Navigate to documents
    await navigation.navigateToModule('All', 'Documents');
    
    // Create a document
    const doc = {
      name: 'Version Control Test Doc',
      category: 'Contract',
      revision: '1.0',
      description: 'Testing version control'
    };
    
    await documentPage.uploadDocument(doc);
    
    // Check out document
    await documentPage.checkOutDocument();
    
    // Make changes and check in
    await documentPage.checkInDocument({
      comment: 'Updated terms and conditions',
      // In real test, would upload new version
    });
    
    // Check version history
    const versions = await documentPage.getVersionHistory();
    expect(versions.length).toBeGreaterThan(0);
    expect(versions[0].comment).toContain('Updated terms');
  });

  test('Test mobile pipeline interactions', async ({ page }) => {
    // Set mobile viewport
    await page.setViewportSize({ width: 375, height: 667 });
    
    await navigation.navigateToDeals();
    await pipelinePage.goto();
    await pipelinePage.waitForPipelineLoad();
    
    // Test mobile menu toggle
    await navigation.toggleMobileMenu();
    expect(await navigation.isMobileMenuOpen()).toBeTruthy();
    
    // Close menu
    await navigation.toggleMobileMenu();
    
    // Test mobile deal movement
    const deals = await pipelinePage.getDealsInStage('lead');
    if (deals.length > 0) {
      await pipelinePage.moveDealMobile(deals[0].title, 'contacted');
      
      // Verify move
      const contactedDeals = await pipelinePage.getDealsInStage('contacted');
      const movedDeal = contactedDeals.find(d => d.title === deals[0].title);
      expect(movedDeal).toBeDefined();
    }
  });

  test('Test error handling and validation', async ({ page }) => {
    await navigation.navigateToDeals();
    
    // Try to create deal without required fields
    await dealPage.clickElement(dealPage.selectors.createButton);
    await dealPage.saveDeal();
    
    // Should show validation errors
    const hasErrors = await dealPage.isVisible(dealPage.selectors.errorMessage);
    expect(hasErrors).toBeTruthy();
    
    // Fill required fields and save
    await dealPage.fillField(dealPage.selectors.nameInput, 'Valid Deal Name');
    await dealPage.selectOption(dealPage.selectors.statusSelect, 'sourcing');
    await dealPage.saveDeal();
    
    // Should succeed now
    expect(await dealPage.getDealTitle()).toContain('Valid Deal Name');
  });

  test('Test accessibility features', async ({ page }) => {
    await navigation.navigateToDeals();
    await pipelinePage.goto();
    await pipelinePage.waitForPipelineLoad();
    
    // Test keyboard navigation
    await pipelinePage.openKeyboardShortcuts();
    await page.keyboard.press('Escape');
    
    // Select and move deal with keyboard
    const deals = await pipelinePage.getDealsInStage('lead');
    if (deals.length > 0) {
      await pipelinePage.selectDealWithKeyboard(deals[0].title);
      await pipelinePage.moveDealWithKeyboard('ArrowRight');
      
      // Check ARIA announcement
      const announcement = await pipelinePage.getAriaAnnouncement();
      expect(announcement).toContain('moved to');
    }
  });
});

// Performance tests
test.describe('Page Object Performance Tests', () => {
  test('Measure page load times', async ({ page }) => {
    const loginPage = new LoginPage(page);
    const dealPage = new DealPage(page);
    const navigation = new NavigationComponent(page);
    
    // Login
    await loginPage.goto();
    await loginPage.loginAsAdmin();
    
    // Measure deals list load time
    const startTime = Date.now();
    await navigation.navigateToDeals();
    await dealPage.waitForElement(dealPage.selectors.listViewTable);
    const loadTime = Date.now() - startTime;
    
    console.log(`Deals list load time: ${loadTime}ms`);
    expect(loadTime).toBeLessThan(3000); // Should load in under 3 seconds
  });
});