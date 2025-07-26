# Page Object Models for MakeDealCRM

This directory contains Page Object Model (POM) implementations for MakeDealCRM's E2E testing with Playwright.

## Overview

The Page Object Model pattern helps to:
- Encapsulate page-specific selectors and actions
- Reduce code duplication across tests
- Make tests more maintainable and readable
- Provide a clear separation between test logic and page interactions

## Page Objects

### BasePage
The parent class for all page objects containing common functionality:
- Navigation helpers
- Element interaction methods
- Wait utilities
- Screenshot capabilities

### LoginPage
Handles authentication-related operations:
- Login with credentials
- Logout functionality
- Error message handling
- Remember me and language selection

### NavigationComponent
Manages the main navigation menu:
- Module navigation
- Quick create actions
- Global search
- User menu operations

### DealPage
Manages deal-related operations:
- Create, edit, and delete deals
- Search and filter functionality
- Mass updates
- Subpanel interactions

### ContactPage
Handles contact management:
- Create and manage contacts
- Link contacts to deals
- Stakeholder management
- Bulk operations

### DocumentPage
Manages document operations:
- Upload and download documents
- Version control
- Document templates
- Metadata management

### ChecklistPage
Handles checklist functionality:
- Create checklist templates
- Apply templates to deals
- Track completion progress
- Manage checklist items

### PipelinePage
Manages the Kanban board:
- Drag and drop operations
- Stage transitions
- WIP limit enforcement
- Mobile gesture support

## Usage Examples

### Basic Test Structure

```javascript
const { test, expect } = require('@playwright/test');
const { LoginPage, DealPage, NavigationComponent } = require('./page-objects');

test.describe('Deal Management', () => {
  let loginPage;
  let dealPage;
  let navigation;

  test.beforeEach(async ({ page }) => {
    loginPage = new LoginPage(page);
    dealPage = new DealPage(page);
    navigation = new NavigationComponent(page);
    
    // Login
    await loginPage.goto();
    await loginPage.loginAsAdmin();
  });

  test('Create a new deal', async () => {
    // Navigate to deals
    await navigation.navigateToDeals();
    
    // Create deal
    await dealPage.createDeal({
      name: 'Test Deal',
      status: 'initial_contact',
      dealValue: '1000000',
      ttmRevenue: '5000000',
      ttmEbitda: '1000000'
    });
    
    // Verify creation
    const title = await dealPage.getDealTitle();
    expect(title).toContain('Test Deal');
  });
});
```

### Pipeline Drag and Drop

```javascript
test('Drag deal between stages', async ({ page }) => {
  const pipelinePage = new PipelinePage(page);
  
  await navigation.navigateToDeals();
  await pipelinePage.goto();
  
  // Drag deal from Lead to Contacted
  await pipelinePage.dragDealToStage('Acme Corp Deal', 'contacted');
  
  // Verify move
  const dealsInContacted = await pipelinePage.getDealsInStage('contacted');
  expect(dealsInContacted.some(d => d.title === 'Acme Corp Deal')).toBeTruthy();
});
```

### Document Upload

```javascript
test('Upload document to deal', async ({ page }) => {
  const documentPage = new DocumentPage(page);
  
  await navigation.navigateToDeals();
  await dealPage.openDeal('Test Deal');
  
  // Navigate to documents subpanel
  await page.click('a:has-text("Documents")');
  
  // Upload document
  await documentPage.uploadDocument({
    name: 'Financial Statement',
    filePath: './test-files/financials.pdf',
    category: 'Financial',
    description: 'Q4 2023 Financial Statement'
  });
  
  // Verify upload
  expect(await documentPage.getDocumentStatus()).toBe('Active');
});
```

### Contact Management

```javascript
test('Add stakeholder to deal', async ({ page }) => {
  const contactPage = new ContactPage(page);
  
  // Create contact
  await navigation.navigateToContacts();
  await contactPage.createContact({
    firstName: 'John',
    lastName: 'Doe',
    title: 'CEO',
    email: 'john.doe@example.com',
    role: 'decision_maker',
    isDecisionMaker: true
  });
  
  // Link to deal
  await contactPage.addToDeal('Test Deal');
  
  // Verify
  const relatedDeals = await contactPage.getRelatedDealsCount();
  expect(relatedDeals).toBeGreaterThan(0);
});
```

### Checklist Application

```javascript
test('Apply checklist template', async ({ page }) => {
  const checklistPage = new ChecklistPage(page);
  
  // Apply template
  await checklistPage.applyTemplate('Due Diligence Checklist', {
    dealId: 'deal123',
    sendReminders: true,
    reminderDays: 3
  });
  
  // Complete items
  await checklistPage.completeItem(0, 'Completed financial review');
  await checklistPage.completeItem(1, 'Legal documents verified');
  
  // Check progress
  const progress = await checklistPage.getChecklistProgress();
  expect(progress.percentage).toBeGreaterThan(0);
});
```

## Best Practices

1. **Always use page objects** instead of direct selectors in tests
2. **Keep page objects focused** on a single page or component
3. **Use descriptive method names** that express business actions
4. **Handle waits within page objects** to make tests more stable
5. **Return promises** from async methods for proper test flow
6. **Validate inputs** in page object methods when appropriate
7. **Use the BasePage** parent class for common functionality

## Selector Strategy

The page objects use a consistent selector strategy:
- **CSS selectors** for most elements
- **Text-based selectors** for user-visible content
- **Data attributes** for test-specific targeting
- **ARIA attributes** for accessibility testing

## Maintenance

When updating page objects:
1. Update selectors if UI changes
2. Add new methods for new functionality
3. Keep backward compatibility when possible
4. Update this documentation
5. Run all affected tests

## Configuration

Page objects use the base URL from Playwright config:
- Default: `http://localhost:8080`
- Can be overridden with `BASE_URL` environment variable

## Debugging

To debug page object interactions:
1. Use `page.pause()` to pause execution
2. Add `await page.screenshot()` to capture state
3. Use Playwright Inspector with `--debug` flag
4. Check selector validity with browser DevTools

## Contributing

When adding new page objects:
1. Extend `BasePage` for common functionality
2. Follow existing naming conventions
3. Document public methods
4. Add usage examples to this README
5. Ensure all methods handle errors appropriately