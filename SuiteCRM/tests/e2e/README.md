# SuiteCRM E2E Tests

This directory contains end-to-end tests for MakeDealCRM using Playwright.

## Directory Structure

```
e2e/
├── deals/                  # Deal module tests
├── lib/                    # Test utilities and helpers
│   ├── helpers/           # Helper classes
│   │   ├── auth.helper.js        # Authentication utilities
│   │   ├── navigation.helper.js  # Navigation utilities
│   │   ├── wait.helper.js        # Advanced wait utilities
│   │   ├── assertions.helper.js  # Custom assertions
│   │   └── screenshot.helper.js  # Screenshot utilities
│   ├── pages/             # Page objects
│   │   └── base.page.js         # Base page class
│   ├── fixtures/          # Test fixtures
│   │   └── test-fixtures.js     # Custom test fixtures
│   └── data/              # Test data management
│       └── test-data-manager.js  # Test data utilities
├── test-data/             # JSON test data files
├── test-results/          # Test execution results
├── playwright.config.js   # Playwright configuration
├── package.json          # NPM dependencies and scripts
├── .env.example          # Environment configuration template
└── README.md             # This file
```

## Setup

1. **Install dependencies:**
   ```bash
   npm install
   ```

2. **Install Playwright browsers:**
   ```bash
   npm run install:browsers
   ```

3. **Configure environment:**
   ```bash
   cp .env.example .env
   # Edit .env with your configuration
   ```

4. **Ensure Docker is running:**
   ```bash
   docker-compose up -d
   ```

## Running Tests

### Basic Commands

```bash
# Run all tests
npm test

# Run tests in UI mode
npm run test:ui

# Run tests with visible browser
npm run test:headed

# Debug tests
npm run test:debug
```

### Run Specific Test Suites

```bash
# Run only deal tests
npm run test:deals

# Run smoke tests
npm run test:smoke

# Run critical tests
npm run test:critical

# Run regression tests
npm run test:regression
```

### Run Tests on Specific Browsers

```bash
# Chrome only
npm run test:chrome

# Firefox only
npm run test:firefox

# Safari only
npm run test:webkit

# Mobile browsers
npm run test:mobile
```

### Other Commands

```bash
# Generate code from browser actions
npm run codegen

# Show test report
npm run report

# Update baseline screenshots
npm run test:update-baselines

# Clean test results
npm run clean
```

## Writing Tests

### Basic Test Structure

```javascript
const { test, expect } = require('../lib/fixtures/test-fixtures');
const DealPage = require('../lib/pages/deal.page');

test.describe('Deal Module', () => {
  let dealPage;

  test.beforeEach(async ({ authenticatedPage }) => {
    dealPage = new DealPage(authenticatedPage);
    await dealPage.navigate();
  });

  test('should create a new deal', async ({ testData }) => {
    const dealData = testData.generateDealData();
    await dealPage.createDeal(dealData);
    await expect(dealPage.getSuccessMessage()).toContain('Deal created');
  });
});
```

### Using Test Fixtures

```javascript
// Auto-login fixture
test('authenticated test', async ({ authenticatedPage }) => {
  // Already logged in
});

// Test data manager
test('test with data', async ({ testData }) => {
  const contact = testData.generateContactData();
  // Use contact data
});

// Screenshot on failure (automatic)
test('test with auto screenshot', async ({ page }) => {
  // Screenshot taken automatically on failure
});
```

### Using Helpers

```javascript
test('test with helpers', async ({ page }) => {
  const basePage = new BasePage(page);
  
  // Authentication
  await basePage.auth.loginAsAdmin();
  
  // Navigation
  await basePage.navigation.navigateToModule('Deals');
  
  // Waiting
  await basePage.wait.waitForElement('.deal-list');
  
  // Assertions
  await basePage.assertions.assertVisible('.deal-form');
  
  // Screenshots
  await basePage.screenshot.takeScreenshot('deal-list');
});
```

## Test Data Management

### Generating Test Data

```javascript
const testData = new TestDataManager();

// Generate single records
const deal = testData.generateDealData();
const contact = testData.generateContactData();
const account = testData.generateAccountData();

// Generate bulk data
const deals = testData.generateBulkData('deal', 10);

// Generate scenario data
const scenario = testData.generateScenarioData('deal-with-contacts');
```

### Cleanup

Test data is automatically cleaned up after each test when using the `testData` fixture.

## Configuration

### Environment Variables

See `.env.example` for all available configuration options.

Key variables:
- `BASE_URL`: Application URL (default: http://localhost:8080)
- `ADMIN_USERNAME`: Admin username
- `ADMIN_PASSWORD`: Admin password
- `HEADLESS`: Run browsers in headless mode
- `PARALLEL_WORKERS`: Number of parallel test workers

### Playwright Configuration

The `playwright.config.js` file contains:
- Browser configurations
- Timeout settings
- Reporter configurations
- Project definitions
- Web server settings

## Best Practices

1. **Use Page Objects:** Encapsulate page logic in page object classes
2. **Use Test Fixtures:** Leverage custom fixtures for common setup
3. **Generate Test Data:** Use TestDataManager for consistent test data
4. **Clean Up:** Ensure test data is cleaned up after tests
5. **Use Helpers:** Utilize helper classes for common operations
6. **Tag Tests:** Use tags (@smoke, @critical, etc.) for test organization
7. **Take Screenshots:** Capture screenshots for debugging
8. **Handle Waits:** Use smart wait strategies, avoid hard waits
9. **Write Readable Tests:** Use descriptive test names and comments
10. **Keep Tests Independent:** Each test should run independently

## Debugging

1. **Debug Mode:**
   ```bash
   npm run test:debug
   ```

2. **Headed Mode:**
   ```bash
   npm run test:headed
   ```

3. **Slow Motion:**
   Add `--slow-mo=1000` to any test command

4. **VS Code Debugging:**
   Use the Playwright Test extension for VS Code

5. **Console Logs:**
   Tests capture console logs automatically (see `consoleLogs` fixture)

## CI/CD Integration

```bash
# Run tests in CI mode
npm run test:ci
```

This command:
- Runs tests in headless mode
- Enables retries
- Generates reports in multiple formats
- Fails on test warnings

## Reports

Test reports are generated in the `test-results` directory:
- HTML Report: `test-results/html-report/index.html`
- JUnit XML: `test-results/junit.xml`
- JSON Report: `test-results/test-results.json`

View HTML report:
```bash
npm run report
```

## Troubleshooting

1. **Browser not installed:**
   ```bash
   npm run install:browsers
   ```

2. **Docker not running:**
   ```bash
   docker-compose up -d
   ```

3. **Port already in use:**
   Check if port 8080 is available or update BASE_URL

4. **Authentication failures:**
   Verify credentials in .env file

5. **Timeout issues:**
   Increase timeout in playwright.config.js

## Contributing

1. Follow the existing test patterns
2. Use meaningful test descriptions
3. Add appropriate tags to tests
4. Update documentation as needed
5. Ensure tests pass before committing