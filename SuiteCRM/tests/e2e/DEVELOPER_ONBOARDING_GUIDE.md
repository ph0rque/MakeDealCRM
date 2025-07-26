# Developer Onboarding Guide - E2E Testing

## Welcome to the MakeDealCRM E2E Testing Team!

This guide will help you get up to speed quickly with our E2E testing framework, tools, and practices. By the end of this guide, you'll be able to write, run, and maintain E2E tests for the MakeDealCRM platform.

---

## Table of Contents

1. [Prerequisites and Setup](#prerequisites-and-setup)
2. [Understanding the Framework](#understanding-the-framework)
3. [Your First Week - Learning Path](#your-first-week---learning-path)
4. [Writing Your First Test](#writing-your-first-test)
5. [Best Practices and Standards](#best-practices-and-standards)
6. [Debugging and Troubleshooting](#debugging-and-troubleshooting)
7. [Code Review Process](#code-review-process)
8. [Advanced Topics](#advanced-topics)
9. [Resources and Support](#resources-and-support)

---

## Prerequisites and Setup

### Required Software

#### Essential Tools
```bash
# 1. Node.js (version 18 or higher)
node --version  # Should be 18.x or higher

# 2. Docker Desktop
docker --version
docker-compose --version

# 3. Git
git --version

# 4. Your preferred IDE (VS Code recommended)
# Install these VS Code extensions:
# - Playwright Test for VSCode
# - JavaScript Debugger
# - Docker
```

#### Development Environment Setup

**Step 1: Clone and Navigate**
```bash
# Clone the repository
git clone https://github.com/your-org/MakeDealCRM.git
cd MakeDealCRM/SuiteCRM/tests/e2e
```

**Step 2: Install Dependencies**
```bash
# Install Node.js dependencies
npm install

# Install Playwright browsers
npm run install:browsers

# Verify installation
npx playwright --version
```

**Step 3: Environment Configuration**
```bash
# Create environment file
cp .env.example .env

# Edit .env with your settings
# Typical local settings:
BASE_URL=http://localhost:8080
ADMIN_USERNAME=admin
ADMIN_PASSWORD=admin123
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=root
DB_NAME=suitecrm
```

**Step 4: Start Services**
```bash
# Navigate to project root
cd ../../../

# Start Docker services
docker-compose up -d

# Wait for services to be ready (usually 30-60 seconds)
# Check if services are running
docker-compose ps

# Navigate back to E2E directory
cd SuiteCRM/tests/e2e
```

**Step 5: Verify Setup**
```bash
# Run environment check
npm run check:env

# Run a simple smoke test
npm run test:smoke

# If everything works, you should see test results!
```

### IDE Configuration

#### VS Code Setup (Recommended)

**Install Required Extensions:**
1. Playwright Test for VSCode
2. JavaScript (ES6) code snippets
3. Docker
4. Git Graph
5. Auto Rename Tag

**Configure VS Code Settings:**
```json
// .vscode/settings.json
{
  "playwright.testFolder": ".",
  "playwright.testMatch": "**/*.spec.{js,ts}",
  "playwright.showTrace": true,
  "editor.formatOnSave": true,
  "editor.codeActionsOnSave": {
    "source.fixAll": true
  },
  "javascript.preferences.includePackageJsonAutoImports": "auto"
}
```

**Debug Configuration:**
```json
// .vscode/launch.json
{
  "version": "0.2.0",
  "configurations": [
    {
      "name": "Debug Playwright Tests",
      "type": "node",
      "request": "launch",
      "program": "${workspaceFolder}/node_modules/@playwright/test/cli.js",
      "args": ["test", "--debug"],
      "cwd": "${workspaceFolder}",
      "console": "integratedTerminal"
    }
  ]
}
```

---

## Understanding the Framework

### Architecture Overview

Our E2E testing framework is built on **Playwright** and follows these key patterns:

#### 1. Test Organization Structure
```
tests/e2e/
├── deals/                          # Feature-specific tests
│   ├── feature1-deal-central-object.spec.js
│   ├── feature3-checklist-due-diligence.spec.js
│   ├── feature4-stakeholder-tracking.spec.js
│   ├── financial-hub.spec.js
│   └── helpers/                    # Feature-specific helpers
├── lib/                           # Shared utilities
│   ├── data/                      # Data management
│   ├── fixtures/                  # Custom test fixtures
│   ├── helpers/                   # Helper classes
│   └── pages/                     # Base page objects
├── page-objects/                  # Page Object Models
├── examples/                      # Learning examples
└── scripts/                       # Utility scripts
```

#### 2. Key Concepts

**Page Object Model (POM)**
- Encapsulates page-specific logic and selectors
- Provides reusable methods for interacting with pages
- Makes tests more maintainable and readable

**Test Fixtures**
- Custom Playwright fixtures for common setup/teardown
- Automatic authentication, data management, cleanup
- Consistent test environment

**Data Management**
- Automated test data creation and cleanup
- Isolation between tests
- Performance-optimized bulk operations

### Framework Components Deep Dive

#### Page Objects
```javascript
// Example: Basic page object structure
class DealPage extends BasePage {
  constructor(page) {
    super(page);
    this.selectors = {
      createButton: 'button:has-text("Create Deal")',
      dealForm: '#deal-form',
      nameField: 'input[name="name"]'
    };
  }
  
  async createDeal(dealData) {
    await this.page.click(this.selectors.createButton);
    await this.fillDealForm(dealData);
    await this.page.click('button:has-text("Save")');
  }
  
  async fillDealForm(data) {
    await this.page.fill(this.selectors.nameField, data.name);
    // ... other fields
  }
}
```

#### Test Fixtures
```javascript
// Example: Using custom fixtures
const { test, expect } = require('../lib/fixtures/enhanced-test-fixtures');

test('Deal creation test', async ({ 
  authenticatedPage,    // Auto-logged in page
  dealFixture,         // Deal-specific utilities
  testData            // Test data manager
}) => {
  const dealData = testData.generateDealData();
  const deal = await dealFixture.createDeal(dealData);
  
  expect(deal.id).toBeDefined();
  // Automatic cleanup happens after test
});
```

#### Data Management
```javascript
// Example: Test data management
test('Test with managed data', async ({ enhancedDataManager }) => {
  // Create test data with automatic cleanup
  const scenario = await enhancedDataManager.createTestScenario('deal-with-contacts', {
    dealCount: 1,
    contactCount: 2,
    includeDocuments: true
  });
  
  // Use the data in your test
  const deal = scenario.deals[0];
  const contacts = scenario.contacts;
  
  // Test logic here...
  
  // Cleanup happens automatically in afterEach
});
```

### The Five Features We Test

#### Feature 1: Deal as Central Object
- **Purpose**: Deals serve as the central hub for all related data
- **Tests**: Deal creation, contact association, document management
- **File**: `deals/feature1-deal-central-object.spec.js`

#### Feature 2: Unified Deal & Portfolio Pipeline
- **Purpose**: Kanban-style pipeline with drag & drop functionality
- **Tests**: Pipeline visualization, drag & drop, stage transitions
- **Files**: `pipeline/` directory

#### Feature 3: Personal Due-Diligence Checklists
- **Purpose**: Template-based checklists with automatic task generation
- **Tests**: Template creation, application, progress tracking
- **File**: `deals/feature3-checklist-due-diligence.spec.js`

#### Feature 4: Simplified Stakeholder Tracking
- **Purpose**: Role-based stakeholder management
- **Tests**: Role assignment, relationship management
- **File**: `deals/feature4-stakeholder-tracking.spec.js`

#### Feature 5: At-a-Glance Financial & Valuation Hub
- **Purpose**: Financial dashboard with what-if calculator
- **Tests**: Dashboard display, calculator functionality, calculations
- **File**: `deals/financial-hub.spec.js`

---

## Your First Week - Learning Path

### Day 1: Environment and Basics

**Morning (2-3 hours)**
1. Complete the setup section above
2. Read the [main README](README.md)
3. Run the existing test suites to see them in action:
```bash
npm run test:smoke
npm run test:deals
```

**Afternoon (2-3 hours)**
1. Explore the codebase structure
2. Read through a simple test file: `deals/feature1-deal-central-object.spec.js`
3. Review a page object: `page-objects/DealPage.js`
4. Watch tests run in headed mode:
```bash
npm run test:headed -- deals/feature1-deal-central-object.spec.js
```

**Day 1 Homework**
- Read Playwright documentation: [Getting Started](https://playwright.dev/docs/intro)
- Explore the browser DevTools during test execution

### Day 2: Understanding Tests

**Morning (2-3 hours)**
1. Study the test structure patterns
2. Learn about test fixtures and their usage
3. Understand data management concepts
4. Review test execution and reporting

**Afternoon (2-3 hours)**
1. Run individual test files with debugging:
```bash
npx playwright test --debug deals/feature1-deal-central-object.spec.js
```
2. Practice using the Playwright Inspector
3. Examine test artifacts (screenshots, videos, traces)

**Day 2 Assignment**
Create a simple test that:
- Opens the application
- Logs in as admin
- Navigates to any module
- Takes a screenshot

### Day 3: Page Objects and Helpers

**Morning (2-3 hours)**
1. Deep dive into page object patterns
2. Study the BasePage class and inheritance
3. Learn about selector strategies and maintenance
4. Practice creating simple page object methods

**Afternoon (2-3 hours)**
1. Review helper classes (auth, navigation, wait, assertions)
2. Understand how helpers integrate with page objects
3. Practice debugging failing selectors

**Day 3 Assignment**
Extend an existing page object with a new method:
- Add a method to `DealPage` that counts the number of deals in the list
- Write a test that uses this new method

### Day 4: Test Data and Fixtures

**Morning (2-3 hours)**
1. Learn about test data management
2. Understand test isolation and cleanup
3. Study the enhanced data manager
4. Practice creating test scenarios

**Afternoon (2-3 hours)**
1. Work with custom fixtures
2. Learn about bulk data operations
3. Practice test data cleanup verification

**Day 4 Assignment**
Write a test that:
- Uses the enhanced data manager to create a deal with contacts
- Verifies the relationships were created correctly
- Ensures cleanup happens properly

### Day 5: Writing and Running Tests

**Morning (2-3 hours)**
1. Write your first complete feature test
2. Follow coding standards and best practices
3. Practice test organization and naming
4. Learn about test tags and categorization

**Afternoon (2-3 hours)**
1. Practice debugging test failures
2. Learn to read test reports
3. Understand CI/CD integration basics
4. Code review preparation

**Day 5 Assignment**
Write a comprehensive test for a simple feature:
- Choose a small feature that isn't fully covered
- Write test following all best practices
- Include proper error handling and assertions
- Submit for code review

### Week 1 Goals Checklist

By the end of your first week, you should be able to:
- [ ] Set up and run the complete test environment
- [ ] Understand the basic architecture and patterns
- [ ] Read and understand existing tests
- [ ] Write simple tests using page objects
- [ ] Use test fixtures and data management
- [ ] Debug basic test failures
- [ ] Submit code for review

---

## Writing Your First Test

### Step-by-Step Tutorial

Let's write a test that verifies the deal search functionality:

#### Step 1: Plan Your Test
```javascript
// What are we testing?
// 1. Navigate to deals page
// 2. Enter search term
// 3. Verify search results are relevant
// 4. Clear search and verify all deals return
```

#### Step 2: Create the Test File
```javascript
// tests/deals/deal-search.spec.js
const { test, expect } = require('../lib/fixtures/enhanced-test-fixtures');
const { DealPage } = require('../page-objects');

test.describe('Deal Search Functionality', () => {
  let dealPage;
  
  test.beforeEach(async ({ authenticatedPage }) => {
    dealPage = new DealPage(authenticatedPage);
    await dealPage.goto();
  });
  
  test('should filter deals by search term @smoke', async ({ testData }) => {
    // Step 1: Create test data
    const searchableDeal = await testData.createDeal({
      name: 'Searchable Manufacturing Deal',
      amount: 100000
    });
    
    const otherDeal = await testData.createDeal({
      name: 'Other Company Deal',
      amount: 200000
    });
    
    // Step 2: Perform search
    await dealPage.performSearch('Manufacturing');
    
    // Step 3: Verify results
    const searchResults = await dealPage.getSearchResults();
    expect(searchResults.length).toBeGreaterThan(0);
    expect(searchResults.some(deal => deal.name.includes('Manufacturing'))).toBeTruthy();
    
    // Step 4: Verify other deal is not in results
    expect(searchResults.some(deal => deal.name.includes('Other Company'))).toBeFalsy();
    
    // Step 5: Clear search and verify all deals return
    await dealPage.clearSearch();
    const allResults = await dealPage.getSearchResults();
    expect(allResults.length).toBeGreaterThanOrEqual(2);
  });
});
```

#### Step 3: Implement Required Page Object Methods
```javascript
// page-objects/DealPage.js (add these methods)
async performSearch(searchTerm) {
  const searchInput = this.page.locator('input[name="search"], #search, .search-input');
  await searchInput.fill(searchTerm);
  await this.page.keyboard.press('Enter');
  await this.page.waitForLoadState('networkidle');
}

async getSearchResults() {
  const resultRows = this.page.locator('.deal-row, .list-item, [data-deal-id]');
  await resultRows.first().waitFor({ timeout: 5000 });
  
  const results = [];
  const count = await resultRows.count();
  
  for (let i = 0; i < count; i++) {
    const row = resultRows.nth(i);
    const name = await row.locator('.deal-name, .name-column').textContent();
    results.push({ name: name.trim() });
  }
  
  return results;
}

async clearSearch() {
  const searchInput = this.page.locator('input[name="search"], #search, .search-input');
  await searchInput.clear();
  await this.page.keyboard.press('Enter');
  await this.page.waitForLoadState('networkidle');
}
```

#### Step 4: Run and Debug Your Test
```bash
# Run your test in headed mode to see what happens
npx playwright test --headed tests/deals/deal-search.spec.js

# If it fails, run with debug mode
npx playwright test --debug tests/deals/deal-search.spec.js

# Run just the smoke tests to make sure it's tagged correctly
npm run test:smoke
```

#### Step 5: Refine and Improve
```javascript
// Improved version with better error handling and assertions
test('should filter deals by search term @smoke', async ({ testData, page }) => {
  // Create test data with unique identifiers
  const timestamp = Date.now();
  const searchableDeal = await testData.createDeal({
    name: `E2E_Searchable_Deal_${timestamp}`,
    amount: 100000
  });
  
  const otherDeal = await testData.createDeal({
    name: `E2E_Other_Deal_${timestamp}`,
    amount: 200000
  });
  
  // Wait for data to be available
  await page.waitForTimeout(1000);
  
  // Perform search with error handling
  await test.step('Perform search', async () => {
    await dealPage.performSearch('Searchable');
    
    // Wait for search results to load
    await expect(page.locator('.loading, .spinner')).toHaveCount(0, { timeout: 10000 });
  });
  
  // Verify results with detailed assertions
  await test.step('Verify search results', async () => {
    const searchResults = await dealPage.getSearchResults();
    
    // At least one result should be found
    expect(searchResults.length).toBeGreaterThan(0);
    
    // The searchable deal should be in results
    const hasSearchableDeal = searchResults.some(deal => 
      deal.name.includes('E2E_Searchable_Deal')
    );
    expect(hasSearchableDeal).toBeTruthy();
    
    // The other deal should NOT be in results
    const hasOtherDeal = searchResults.some(deal => 
      deal.name.includes('E2E_Other_Deal')
    );
    expect(hasOtherDeal).toBeFalsy();
  });
  
  // Clear search and verify
  await test.step('Clear search and verify all results return', async () => {
    await dealPage.clearSearch();
    const allResults = await dealPage.getSearchResults();
    
    // Should have at least our two test deals
    expect(allResults.length).toBeGreaterThanOrEqual(2);
    
    // Both deals should now be visible
    const testDeals = allResults.filter(deal => 
      deal.name.includes(`E2E_`) && deal.name.includes(`_${timestamp}`)
    );
    expect(testDeals.length).toBe(2);
  });
});
```

### Common Patterns You'll Use

#### Authentication Pattern
```javascript
// Most tests start with authentication
test.beforeEach(async ({ authenticatedPage }) => {
  // authenticatedPage is already logged in
  page = authenticatedPage;
});

// Or manual login
test.beforeEach(async ({ page }) => {
  const loginPage = new LoginPage(page);
  await loginPage.goto();
  await loginPage.loginAsAdmin();
});
```

#### Data Creation Pattern
```javascript
// Using test data manager
test('My test', async ({ testData }) => {
  const deal = await testData.createDeal({
    name: 'Test Deal',
    amount: 100000
  });
  
  // Use deal in test...
});

// Using enhanced data manager for complex scenarios
test('Complex test', async ({ enhancedDataManager }) => {
  const scenario = await enhancedDataManager.createTestScenario('deal-with-contacts');
  
  // Use scenario.deals, scenario.contacts, etc.
});
```

#### Assertion Patterns
```javascript
// Basic assertions
expect(value).toBe(expectedValue);
expect(array).toHaveLength(3);
expect(string).toContain('substring');

// Playwright-specific assertions
await expect(page.locator('.success-message')).toBeVisible();
await expect(page).toHaveTitle(/Deal Management/);
await expect(page.locator('.deal-count')).toHaveText('5 deals');

// Custom assertions
await dealPage.assertDealExists(dealData.name);
await dealPage.assertDealNotVisible(dealData.name);
```

#### Error Handling Pattern
```javascript
test('Test with error handling', async ({ page }) => {
  // Try-catch for optional elements
  try {
    await page.click('.optional-button', { timeout: 2000 });
  } catch (error) {
    console.log('Optional button not found, continuing...');
  }
  
  // Conditional logic based on element existence
  const modalVisible = await page.locator('.modal').isVisible();
  if (modalVisible) {
    await page.click('.modal .close-button');
  }
  
  // Retry pattern for flaky operations
  await page.waitForFunction(() => {
    const element = document.querySelector('.dynamic-content');
    return element && element.textContent.includes('Expected Text');
  }, { timeout: 10000 });
});
```

---

## Best Practices and Standards

### Code Standards

#### Test Structure Standards
```javascript
// ✅ Good test structure
test.describe('Feature Name', () => {
  let featurePage;
  
  test.beforeEach(async ({ authenticatedPage }) => {
    featurePage = new FeaturePage(authenticatedPage);
    await featurePage.goto();
  });
  
  test('should perform specific action @smoke', async ({ testData }) => {
    // Arrange
    const testEntity = await testData.createEntity();
    
    // Act
    await test.step('Perform main action', async () => {
      await featurePage.performAction(testEntity);
    });
    
    // Assert
    await test.step('Verify results', async () => {
      await expect(featurePage.getResult()).toContain('expected value');
    });
  });
  
  test.afterEach(async ({ testData }) => {
    // Cleanup if needed (usually automatic with fixtures)
    await testData.cleanup();
  });
});
```

#### Naming Conventions

**Test Files**: `feature-name.spec.js`
```javascript
// ✅ Good names
deals/feature1-deal-central-object.spec.js
deals/financial-hub.spec.js
pipeline/drag-drop-functionality.spec.js

// ❌ Bad names
test1.spec.js
dealTests.spec.js
my-test.spec.js
```

**Test Names**: Descriptive and behavior-focused
```javascript
// ✅ Good test names
test('should create deal with all required financial fields @smoke')
test('should display validation error for missing deal name')
test('should filter deals by search term and clear results')

// ❌ Bad test names
test('deal creation')
test('test deal stuff')
test('it works')
```

**Page Object Methods**: Action-focused and clear
```javascript
// ✅ Good method names
async createDealWithContacts(dealData, contacts)
async navigateToFinancialHub()
async verifyDealAppearsInPipeline(dealName, stage)

// ❌ Bad method names
async doStuff()
async clickButton()
async test()
```

#### Documentation Requirements

**File Headers**
```javascript
/**
 * Feature 1: Deal as Central Object - E2E Tests
 * 
 * Tests the core functionality where deals serve as the central hub
 * for all related data including contacts, documents, and activities.
 * 
 * Test Coverage:
 * - Deal creation with financial data
 * - Contact association and role assignment
 * - Document upload and management
 * - Data persistence and integrity
 * 
 * @author Your Name
 * @since 2025-07-01
 */
```

**Complex Methods**
```javascript
/**
 * Creates a deal with full relationship network including contacts and documents
 * 
 * @param {Object} dealData - Deal information
 * @param {string} dealData.name - Deal name
 * @param {number} dealData.amount - Deal value
 * @param {Array} contacts - Array of contacts to associate
 * @param {Array} documents - Array of documents to upload
 * @returns {Promise<Object>} Created deal with relationships
 */
async createDealWithRelationships(dealData, contacts = [], documents = []) {
  // Implementation...
}
```

### Testing Standards

#### Test Categories and Tags
```javascript
// Use tags to categorize tests
test('Critical path test @smoke @critical', async () => {});
test('Extended functionality @regression', async () => {});
test('Performance test @performance', async () => {});
test('Accessibility test @accessibility', async () => {});
```

#### Data Management Standards
```javascript
// ✅ Always use unique identifiers
const timestamp = Date.now();
const dealData = {
  name: `E2E_Test_Deal_${timestamp}`,
  email: `test.${timestamp}@example.com`
};

// ✅ Use appropriate cleanup strategies
test.afterEach(async ({ testData }) => {
  await testData.cleanup(); // Automatic cleanup
});

// ✅ Isolate test data
test('Isolated test', async ({ testIsolation }) => {
  const contextId = await testIsolation.initializeIsolationContext(testInfo);
  // Test with isolated data...
});
```

#### Assertion Standards
```javascript
// ✅ Meaningful assertions with clear error messages
await expect(dealPage.getDealTitle()).toContain(
  dealData.name,
  `Deal title should contain "${dealData.name}"`
);

// ✅ Multiple specific assertions over one complex assertion
await expect(dealPage.getContactCount()).toBe(2);
await expect(dealPage.getDocumentCount()).toBe(1);
await expect(dealPage.getStatusText()).toBe('Active');

// ❌ Avoid vague assertions
expect(result).toBeTruthy(); // What specifically should be true?
```

#### Error Handling Standards
```javascript
// ✅ Graceful degradation for optional functionality
try {
  await page.click('.optional-feature-button', { timeout: 2000 });
} catch (error) {
  console.log('Optional feature not available, continuing with core test');
}

// ✅ Informative error messages
if (!(await dealPage.isDealVisible(dealData.name))) {
  throw new Error(`Deal "${dealData.name}" not found in deal list. Available deals: ${await dealPage.getVisibleDealNames()}`);
}

// ✅ Retry for flaky operations
await page.waitForFunction(
  () => document.querySelector('.dynamic-element')?.textContent?.includes('loaded'),
  { timeout: 10000 }
);
```

### Performance Standards

#### Execution Time Guidelines
- **Smoke tests**: < 5 minutes total
- **Individual feature tests**: < 10 minutes per feature
- **Full regression suite**: < 30 minutes
- **Individual test cases**: < 2 minutes each

#### Memory Usage Guidelines
```javascript
// Monitor memory usage in long-running tests
test('Memory-intensive test', async ({ memoryManager }) => {
  await memoryManager.monitorMemoryUsage(async () => {
    // Test implementation
  });
  
  // Memory usage should not exceed 512MB
  expect(memoryManager.getPeakUsage()).toBeLessThan(512 * 1024 * 1024);
});
```

#### Optimization Techniques
```javascript
// ✅ Use efficient selectors
await page.locator('[data-testid="deal-create-button"]').click();

// ✅ Wait for specific conditions, not arbitrary timeouts
await page.waitForSelector('.deals-list', { state: 'visible' });

// ❌ Avoid unnecessary waits
await page.waitForTimeout(5000); // Only use when absolutely necessary
```

---

## Debugging and Troubleshooting

### Common Issues and Solutions

#### 1. Element Not Found Errors

**Problem**: `Error: Element not found`

**Debugging Steps**:
1. **Use headed mode** to see what's happening:
```bash
npx playwright test --headed your-test.spec.js
```

2. **Take screenshots** at the point of failure:
```javascript
await page.screenshot({ path: 'debug-screenshot.png', fullPage: true });
```

3. **Check multiple selector strategies**:
```javascript
// Try different selectors
const button = page.locator([
  'button:has-text("Create")',
  '#create-button',
  '.create-btn',
  '[data-testid="create-button"]'
].join(', '));
```

4. **Wait for elements properly**:
```javascript
// Wait for element to be visible
await page.waitForSelector('.target-element', { state: 'visible', timeout: 10000 });

// Wait for network requests to complete
await page.waitForLoadState('networkidle');
```

#### 2. Test Data Issues

**Problem**: Tests failing due to data conflicts or cleanup issues

**Solutions**:
```javascript
// Use unique identifiers
const timestamp = Date.now();
const testData = {
  name: `Test_${timestamp}`,
  email: `test.${timestamp}@example.com`
};

// Verify cleanup
test.afterEach(async ({ testData }) => {
  const cleanupResult = await testData.cleanup({ verify: true });
  if (!cleanupResult.success) {
    console.warn('Cleanup issues:', cleanupResult.issues);
  }
});

// Use isolated test contexts
test('Isolated test', async ({ testIsolation }) => {
  const contextId = await testIsolation.initializeIsolationContext(testInfo);
  // All data created will be automatically isolated and cleaned up
});
```

#### 3. Timing Issues

**Problem**: Tests are flaky due to timing issues

**Solutions**:
```javascript
// ✅ Wait for specific conditions
await page.waitForFunction(() => {
  const element = document.querySelector('.status');
  return element && element.textContent === 'Loaded';
});

// ✅ Use Playwright's auto-waiting
await expect(page.locator('.result')).toHaveText('Expected Text');

// ✅ Wait for network activity to complete
await page.waitForLoadState('networkidle');

// ❌ Avoid fixed timeouts unless absolutely necessary
await page.waitForTimeout(5000); // Only as last resort
```

#### 4. Authentication Issues

**Problem**: Tests failing at login or session timeouts

**Solutions**:
```javascript
// Use the authentication fixture
test('Authenticated test', async ({ authenticatedPage }) => {
  // Already logged in, skip manual authentication
});

// Handle session timeouts
test.beforeEach(async ({ page }) => {
  // Check if still authenticated
  await page.goto('/');
  const isLoggedIn = await page.locator('.user-menu').isVisible();
  
  if (!isLoggedIn) {
    const loginPage = new LoginPage(page);
    await loginPage.loginAsAdmin();
  }
});
```

### Debugging Tools and Techniques

#### 1. Playwright Inspector
```bash
# Launch test with debugging
npx playwright test --debug your-test.spec.js

# Use page.pause() in your test to pause execution
test('Debug test', async ({ page }) => {
  await page.goto('/deals');
  await page.pause(); // Execution pauses here
  await page.click('.create-button');
});
```

#### 2. Trace Viewer
```bash
# Run with trace collection
npx playwright test --trace=on your-test.spec.js

# View the trace after test completes
npx playwright show-trace test-results/trace.zip
```

#### 3. Console and Network Monitoring
```javascript
test('Debug with console monitoring', async ({ page }) => {
  // Monitor console messages
  page.on('console', msg => {
    console.log(`CONSOLE ${msg.type()}: ${msg.text()}`);
  });
  
  // Monitor network requests
  page.on('request', request => {
    console.log(`REQUEST: ${request.method()} ${request.url()}`);
  });
  
  page.on('response', response => {
    console.log(`RESPONSE: ${response.status()} ${response.url()}`);
  });
  
  // Your test logic here
});
```

#### 4. Step-by-Step Debugging
```javascript
test('Step-by-step debug', async ({ page }) => {
  await test.step('Navigate to deals', async () => {
    await page.goto('/deals');
    await page.screenshot({ path: 'step1-navigation.png' });
  });
  
  await test.step('Click create button', async () => {
    await page.click('.create-button');
    await page.screenshot({ path: 'step2-form-opened.png' });
  });
  
  await test.step('Fill form', async () => {
    await page.fill('[name="name"]', 'Test Deal');
    await page.screenshot({ path: 'step3-form-filled.png' });
  });
});
```

### Getting Help

#### 1. Using Built-in Diagnostics
```bash
# Check environment health
npm run check:env

# Validate selectors across all page objects
node scripts/validate-selectors.js

# Run diagnostic tests
npm run test:diagnostics
```

#### 2. Logging and Debugging
```javascript
// Enable debug logging
DEBUG=pw:api npm test

// Use the built-in test logger
test('Logged test', async ({ page }, testInfo) => {
  console.log(`Running test: ${testInfo.title}`);
  
  // Log important steps
  await page.goto('/deals');
  console.log('Navigated to deals page');
  
  const dealCount = await page.locator('.deal-row').count();
  console.log(`Found ${dealCount} deals on page`);
});
```

#### 3. Community Resources
- **Internal documentation**: All README files in the project
- **Playwright docs**: https://playwright.dev/docs/
- **Team chat**: Ask in the #qa-automation channel
- **Stack Overflow**: Tag questions with `playwright` and `e2e-testing`

---

## Code Review Process

### Before Submitting for Review

#### Self-Review Checklist
- [ ] **Tests pass locally**: Run `npm test` and ensure all tests pass
- [ ] **Code follows standards**: Naming conventions, documentation, structure
- [ ] **No hardcoded values**: Use configuration and test data properly
- [ ] **Proper error handling**: Graceful failures and informative messages
- [ ] **Performance considerations**: Tests complete within reasonable time
- [ ] **Clean up code**: Remove debugging statements, unused imports

#### Pre-Review Testing
```bash
# Run full test suite
npm test

# Run linting
npm run lint

# Run specific tests that might be affected
npm run test:smoke
npm run test:regression

# Check for selector issues
node scripts/validate-selectors.js
```

### Review Guidelines

#### What Reviewers Look For

**1. Test Quality**
- Tests cover the specified requirements
- Edge cases are considered
- Assertions are meaningful and specific
- Test data management is proper

**2. Code Quality**
- Follows established patterns
- Uses page objects correctly
- Proper error handling
- Clear and maintainable code

**3. Performance**
- Tests execute efficiently
- No unnecessary waits or delays
- Memory usage is reasonable
- Selectors are optimized

**4. Documentation**
- Code is well-commented
- Complex logic is explained
- README updates if needed

#### Example Review Comments

**Good Feedback Examples**:
```
✅ "Consider using a more specific selector here - '.deal-form input[name="amount"]' 
   would be more reliable than just 'input[name="amount"]'"

✅ "Great use of test.step() to organize the test logic! This makes it much easier 
   to understand what failed if the test breaks."

✅ "The error handling here is excellent - the test will provide useful information 
   if it fails."
```

**Improvement Suggestions**:
```
🔄 "This test might be flaky due to timing - consider using 
   waitForSelector() instead of the fixed timeout."

🔄 "Could this be extracted into a page object method? It looks like 
   something that might be reused in other tests."

🔄 "The test data cleanup looks incomplete - what happens if the test 
   fails before reaching the cleanup code?"
```

### Submitting Your Pull Request

#### PR Title Format
```
feat(e2e): Add search functionality tests for deals module
fix(e2e): Fix flaky stakeholder tracking test
docs(e2e): Update page object documentation
```

#### PR Description Template
```markdown
## Description
Brief description of what this PR adds/changes/fixes.

## Type of Change
- [ ] New test implementation
- [ ] Bug fix in existing tests
- [ ] Performance improvement
- [ ] Documentation update
- [ ] Refactoring

## Test Coverage
- [ ] All new tests pass locally
- [ ] Smoke tests pass
- [ ] No regression in existing tests

## Screenshots/Videos
Include screenshots or videos if the changes affect UI interactions.

## Additional Notes
Any special considerations, dependencies, or follow-up work needed.
```

#### Review Process Steps
1. **Submit PR** with clear title and description
2. **Automated checks** run (linting, basic tests)
3. **Peer review** by team members (usually 2 reviewers)
4. **Address feedback** and update PR
5. **Final approval** and merge

---

## Advanced Topics

### Custom Fixtures Development

#### Creating a Custom Fixture
```javascript
// lib/fixtures/custom-fixtures.js
const { test: base, expect } = require('@playwright/test');

const test = base.extend({
  // Custom fixture for deal management
  dealManager: async ({ page }, use) => {
    const dealManager = new DealManager(page);
    await dealManager.initialize();
    
    await use(dealManager);
    
    // Cleanup
    await dealManager.cleanup();
  },
  
  // Custom fixture for performance monitoring
  performanceMonitor: async ({ }, use) => {
    const monitor = new PerformanceMonitor();
    monitor.startMonitoring();
    
    await use(monitor);
    
    const metrics = monitor.getMetrics();
    console.log('Performance metrics:', metrics);
  }
});

module.exports = { test, expect };
```

### Advanced Data Management

#### Creating Complex Test Scenarios
```javascript
// lib/data/scenario-builder.js
class TestScenarioBuilder {
  constructor(dataManager) {
    this.dataManager = dataManager;
    this.scenario = {
      accounts: [],
      contacts: [],
      deals: [],
      documents: []
    };
  }
  
  async buildComplexDealNetwork() {
    // Create main account
    const mainAccount = await this.dataManager.createAccount({
      name: 'Main Corp',
      industry: 'Manufacturing'
    });
    this.scenario.accounts.push(mainAccount);
    
    // Create contacts with different roles
    const contacts = await Promise.all([
      this.dataManager.createContact({
        firstName: 'John',
        lastName: 'CEO',
        role: 'decision_maker',
        accountId: mainAccount.id
      }),
      this.dataManager.createContact({
        firstName: 'Jane',
        lastName: 'CFO',
        role: 'influencer',
        accountId: mainAccount.id
      })
    ]);
    this.scenario.contacts.push(...contacts);
    
    // Create deal with relationships
    const deal = await this.dataManager.createDeal({
      name: 'Complex Deal',
      accountId: mainAccount.id,
      amount: 1000000,
      stage: 'negotiation'
    });
    this.scenario.deals.push(deal);
    
    // Create relationships
    await this.dataManager.createRelationships(deal.id, contacts.map(c => c.id));
    
    return this.scenario;
  }
}
```

### Performance Testing

#### Load Testing with Playwright
```javascript
test.describe('Performance Tests @performance', () => {
  test('Load test - Create 100 deals simultaneously', async ({ browser }) => {
    const contexts = [];
    const startTime = Date.now();
    
    try {
      // Create multiple browser contexts
      for (let i = 0; i < 10; i++) {
        const context = await browser.newContext();
        contexts.push(context);
      }
      
      // Run concurrent operations
      const promises = contexts.map(async (context, index) => {
        const page = await context.newPage();
        const dealPage = new DealPage(page);
        
        await dealPage.goto();
        
        // Create 10 deals per context (100 total)
        for (let j = 0; j < 10; j++) {
          await dealPage.createDeal({
            name: `Load Test Deal ${index}-${j}`,
            amount: 50000 + (index * 1000) + j
          });
        }
        
        return index;
      });
      
      await Promise.all(promises);
      
      const endTime = Date.now();
      const duration = endTime - startTime;
      
      // Performance assertions
      expect(duration).toBeLessThan(60000); // Should complete in under 1 minute
      console.log(`Created 100 deals in ${duration}ms`);
      
    } finally {
      // Cleanup contexts
      await Promise.all(contexts.map(context => context.close()));
    }
  });
});
```

### Visual Regression Testing

#### Setting Up Visual Tests
```javascript
test.describe('Visual Regression Tests @visual', () => {
  test('Deal form visual consistency', async ({ page }) => {
    await page.goto('/deals/create');
    
    // Wait for form to be fully loaded
    await page.waitForSelector('.deal-form', { state: 'visible' });
    
    // Take screenshot and compare to baseline
    await expect(page).toHaveScreenshot('deal-form.png', {
      fullPage: true,
      threshold: 0.2, // Allow 20% difference
      maxDiffPixels: 100
    });
  });
  
  test('Deal pipeline visual consistency', async ({ page }) => {
    // Seed with consistent test data
    await seedPipelineData();
    
    await page.goto('/deals/pipeline');
    await page.waitForSelector('.pipeline-board', { state: 'visible' });
    
    // Hide dynamic elements
    await page.addStyleTag({
      content: '.timestamp, .last-modified { visibility: hidden !important; }'
    });
    
    await expect(page).toHaveScreenshot('pipeline-board.png');
  });
});
```

### Cross-Browser Testing

#### Browser-Specific Tests
```javascript
// playwright.config.js - Browser-specific configuration
projects: [
  {
    name: 'chromium',
    use: { ...devices['Desktop Chrome'] },
    testMatch: '**/*.spec.js'
  },
  {
    name: 'firefox',
    use: { ...devices['Desktop Firefox'] },
    testMatch: ['**/*.spec.js', '!**/*.chrome-only.spec.js']
  },
  {
    name: 'webkit',
    use: { ...devices['Desktop Safari'] },
    testMatch: ['**/*.spec.js', '!**/*.chrome-only.spec.js']
  }
]

// Browser-specific test
test.describe('Cross-browser compatibility', () => {
  test('Deal creation works in all browsers', async ({ page, browserName }) => {
    await page.goto('/deals');
    
    // Browser-specific handling
    if (browserName === 'webkit') {
      // Safari-specific workarounds
      await page.waitForTimeout(1000);
    }
    
    const dealPage = new DealPage(page);
    await dealPage.createDeal({
      name: `Browser Test Deal - ${browserName}`,
      amount: 100000
    });
    
    // Verify creation
    await expect(dealPage.getSuccessMessage()).toBeVisible();
  });
});
```

---

## Resources and Support

### Internal Resources

#### Documentation
- **[Master E2E Testing Guide](E2E_TESTING_MASTER_GUIDE.md)**: Comprehensive overview
- **[Feature Test Coverage Map](FEATURE_TEST_COVERAGE_MAP.md)**: Requirement traceability
- **[Maintenance Guide](MAINTENANCE_AND_OPERATIONS_GUIDE.md)**: Operations procedures
- **Individual Feature READMEs**: Detailed feature documentation

#### Code Examples
- **`examples/` directory**: Working examples of common patterns
- **`examples/comprehensive-test-example.spec.js`**: Full-featured test example
- **`examples/page-objects-example.spec.js`**: Page object usage examples

#### Tools and Scripts
- **`scripts/check-env.js`**: Environment validation
- **`scripts/validate-selectors.js`**: Selector health check
- **`scripts/generate-test-data.js`**: Test data generation
- **`scripts/cleanup-test-data.js`**: Data cleanup utilities

### External Resources

#### Playwright Documentation
- **[Playwright Docs](https://playwright.dev/docs/)**: Official documentation
- **[API Reference](https://playwright.dev/docs/api/class-playwright)**: Complete API docs
- **[Best Practices](https://playwright.dev/docs/best-practices)**: Official best practices
- **[Debugging Guide](https://playwright.dev/docs/debug)**: Debugging techniques

#### Testing Best Practices
- **[JavaScript Testing Best Practices](https://github.com/goldbergyoni/javascript-testing-best-practices)**: General testing guidance
- **[Page Object Model](https://playwright.dev/docs/pom)**: POM pattern documentation
- **[Test Organization](https://playwright.dev/docs/test-organize)**: Test structure guidance

### Getting Help

#### Team Communication
- **Daily Standups**: Share challenges and progress
- **#qa-automation Slack Channel**: Quick questions and discussions
- **Weekly E2E Reviews**: In-depth technical discussions
- **Pair Programming**: Schedule sessions with experienced team members

#### Escalation Process
1. **Self-service**: Check documentation and examples
2. **Peer help**: Ask in team chat or pair with colleague
3. **Lead consultation**: Schedule time with team lead
4. **External resources**: Community forums and documentation

#### Common Questions and Answers

**Q: My test is flaky and sometimes fails. What should I do?**
A: 
1. Run the test multiple times to confirm flakiness
2. Add more explicit waits and robust selectors
3. Check for race conditions and timing issues
4. Use the debugging tools to understand what's happening
5. Ask for code review if you can't identify the issue

**Q: How do I test a feature that requires special setup?**
A:
1. Check if there's already a test fixture for it
2. Create test data using the enhanced data manager
3. Consider creating a custom fixture if it's reusable
4. Document any special requirements in the test

**Q: My selectors keep breaking when the UI changes. How can I make them more robust?**
A:
1. Use multiple selector strategies (text, attributes, CSS classes)
2. Work with developers to add stable `data-testid` attributes
3. Use Playwright's built-in locator strategies
4. Avoid selectors that depend on exact positioning or styling

**Q: How do I contribute to the testing framework itself?**
A:
1. Discuss your idea with the team first
2. Look at existing patterns and follow them
3. Add comprehensive tests for new framework features
4. Update documentation
5. Get thorough code review before merging

### Continuous Learning

#### Recommended Learning Path

**Month 1: Foundation**
- Master basic Playwright concepts
- Understand our specific patterns and practices
- Write simple tests with guidance
- Participate in code reviews as observer

**Month 2: Feature Development**
- Take ownership of test development for specific features
- Learn advanced Playwright features
- Contribute to framework improvements
- Mentor newer team members

**Month 3: Architecture and Leadership**
- Design new testing approaches and patterns
- Lead complex test development projects
- Contribute to testing strategy and planning
- Present learnings to the broader team

#### Skills Development
- **Technical Skills**: Playwright, JavaScript, Node.js, Docker, Git
- **Testing Skills**: Test design, debugging, performance testing
- **Domain Knowledge**: SuiteCRM, CRM concepts, business processes
- **Soft Skills**: Communication, collaboration, mentoring

---

## Welcome to the Team!

Congratulations on making it through the onboarding guide! You now have the foundation needed to contribute effectively to our E2E testing efforts.

### Next Steps
1. **Complete your first week assignments** as outlined in the learning path
2. **Schedule a check-in** with your mentor or team lead
3. **Join our team meetings** and start participating in discussions
4. **Pick up your first real task** from the team backlog
5. **Keep learning** and don't hesitate to ask questions

### Remember
- **Quality over speed**: It's better to write one solid test than five flaky ones
- **Ask questions**: Everyone on the team is here to help you succeed
- **Share knowledge**: Document what you learn and help others
- **Stay curious**: The testing landscape is always evolving

Welcome to the team, and happy testing! 🚀

---

*Last updated: July 2025*
*Document version: 1.0.0*