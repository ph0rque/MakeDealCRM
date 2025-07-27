# MakeDealCRM E2E Test Suite

This is the comprehensive End-to-End (E2E) test suite for MakeDealCRM, built with Playwright and TypeScript. The test suite covers all major features and user workflows in the application.

## 📁 Directory Structure

```
tests/e2e/
├── auth/                    # Authentication tests
│   └── tests/
├── checklists/              # Checklist feature tests
│   ├── tests/
│   ├── helpers/
│   └── fixtures/
├── deals/                   # Deal management tests
│   ├── tests/
│   ├── helpers/
│   └── fixtures/
├── email-integration/       # Email integration tests
│   ├── tests/
│   ├── helpers/
│   └── fixtures/
├── financial/               # Financial calculations tests
│   ├── tests/
│   ├── helpers/
│   └── fixtures/
├── pipeline/                # Pipeline and drag-drop tests
│   ├── tests/
│   ├── helpers/
│   └── fixtures/
├── stakeholders/            # Stakeholder tracking tests
│   ├── tests/
│   ├── helpers/
│   └── fixtures/
├── common/                  # Shared utilities
│   ├── helpers/
│   └── fixtures/
├── config/                  # Configuration files
│   ├── global-setup.ts
│   └── global-teardown.ts
├── lib/                     # Core test libraries
│   ├── base-test.ts
│   └── test-helpers.ts
├── page-objects/            # Page Object Model classes
│   ├── BasePage.ts
│   ├── LoginPage.ts
│   ├── DashboardPage.ts
│   ├── DealsPage.ts
│   ├── PipelinePage.ts
│   ├── ChecklistsPage.ts
│   ├── StakeholdersPage.ts
│   ├── FinancialPage.ts
│   └── index.ts
├── reports/                 # Test reports and artifacts
├── playwright.config.ts     # Playwright configuration
├── package.json            # Dependencies and scripts
└── README.md               # This file
```

## 🚀 Getting Started

### Prerequisites

- Node.js 16+ 
- Docker (for running MakeDealCRM locally)
- MakeDealCRM running at `http://localhost:8080`

### Installation

1. Navigate to the test directory:
```bash
cd tests/e2e
```

2. Install dependencies:
```bash
npm install
```

3. Install Playwright browsers:
```bash
npm run install
```

### Environment Setup

Create a `.env.test` file in the test directory:

```env
BASE_URL=http://localhost:8080
SUITE_USERNAME=admin
SUITE_PASSWORD=admin
SLOW_MO=0
CI=false
```

## 🧪 Running Tests

### Basic Commands

```bash
# Run all tests
npm test

# Run tests with browser UI
npm run test:ui

# Run tests in headed mode (visible browser)
npm run test:headed

# Run tests in debug mode
npm run test:debug
```

### Feature-Specific Tests

```bash
# Run authentication tests
npm run test:auth

# Run deal management tests
npm run test:deals

# Run pipeline tests
npm run test:pipeline

# Run checklist tests
npm run test:checklists

# Run stakeholder tests
npm run test:stakeholders

# Run financial tests
npm run test:financial

# Run email integration tests
npm run test:email
```

### Test Categories

```bash
# Run smoke tests only
npm run test:smoke

# Run regression tests
npm run test:regression

# Run specific browser tests
npm run test:chromium
npm run test:firefox
npm run test:webkit

# Run mobile tests
npm run test:mobile
```

### Advanced Options

```bash
# Run specific test file
npx playwright test deals/tests/deals-crud.spec.ts

# Run tests matching pattern
npx playwright test --grep "should create"

# Run tests with specific tag
npx playwright test --grep "@smoke"

# Run tests with custom config
npx playwright test --config=custom.config.ts

# Run tests with workers
npx playwright test --workers=4

# Run tests with retries
npx playwright test --retries=2
```

## 📊 Reports and Artifacts

### Viewing Reports

```bash
# Open HTML report
npm run report

# Show trace viewer
npm run trace
```

### Report Locations

- **HTML Report**: `reports/html/index.html`
- **JSON Report**: `reports/test-results.json`
- **JUnit Report**: `reports/junit.xml`
- **Screenshots**: `reports/screenshots/`
- **Videos**: `test-results/`
- **Traces**: `test-results/`

## 🏗️ Architecture

### Page Object Model

The test suite uses the Page Object Model (POM) pattern:

```typescript
import { test, expect } from '../lib/base-test';

test('should create deal', async ({ dealsPage, authenticatedPage }) => {
  const dealData = {
    name: 'Test Deal',
    amount: '50000'
  };
  
  await dealsPage.createDeal(dealData);
  expect(await dealsPage.isOnDetailView()).toBe(true);
});
```

### Base Test Fixtures

Custom fixtures are available for all tests:

- `loginPage` - Login page operations
- `dashboardPage` - Dashboard navigation
- `dealsPage` - Deal management
- `pipelinePage` - Pipeline operations
- `checklistsPage` - Checklist management
- `stakeholdersPage` - Stakeholder tracking
- `financialPage` - Financial calculations
- `authenticatedPage` - Auto-login fixture

### Test Helpers

Utility classes for common operations:

```typescript
import { TestDataGenerator, WaitHelpers, ScreenshotHelpers } from '../lib/test-helpers';

// Generate test data
const dealData = TestDataGenerator.generateDealData();

// Wait for custom conditions
await WaitHelpers.waitForText(page, '.status', 'Complete');

// Take screenshots
await ScreenshotHelpers.takeTimestampedScreenshot(page, 'deal-created');
```

## 🏷️ Test Categories and Tags

### Test Categories

- **@smoke** - Critical functionality tests (fast)
- **@regression** - Comprehensive functionality tests
- **@critical** - Business-critical feature tests
- **@feature-{name}** - Feature-specific tests

### Usage in Tests

```typescript
import { describe } from '../lib/base-test';

describe.smoke('Critical User Flows', () => {
  // Smoke tests here
});

describe.feature('pipeline')('Pipeline Features', () => {
  // Pipeline-specific tests here
});
```

## 🔧 Configuration

### Playwright Configuration

Key configuration options in `playwright.config.ts`:

- **Base URL**: `http://localhost:8080`
- **Timeout**: 30 seconds per action
- **Retries**: 2 on CI, 0 locally
- **Workers**: 1 on CI, unlimited locally
- **Browsers**: Chrome, Firefox, Safari, Mobile

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `BASE_URL` | Application URL | `http://localhost:8080` |
| `SUITE_USERNAME` | Login username | `admin` |
| `SUITE_PASSWORD` | Login password | `admin` |
| `SLOW_MO` | Slow motion delay (ms) | `0` |
| `CI` | CI environment flag | `false` |

## 📝 Writing Tests

### Test Structure

```typescript
import { test, expect, describe } from '../../lib/base-test';
import { TestDataGenerator } from '../../lib/test-helpers';

describe.smoke('Feature Name', () => {
  test('should perform action', async ({ 
    page, 
    dealsPage, 
    authenticatedPage 
  }) => {
    // Arrange
    const testData = TestDataGenerator.generateDealData();
    
    // Act
    await dealsPage.createDeal(testData);
    
    // Assert
    expect(await dealsPage.isOnDetailView()).toBe(true);
  });
});
```

### Best Practices

1. **Use Page Objects**: Always use page objects for UI interactions
2. **Generate Test Data**: Use `TestDataGenerator` for consistent test data
3. **Clean Up**: Use database cleanup helpers when needed
4. **Wait Appropriately**: Use proper wait strategies
5. **Handle Flakiness**: Use retries and wait helpers
6. **Tag Tests**: Use appropriate test categories
7. **Screenshot on Failure**: Automatic screenshots are captured
8. **Isolate Tests**: Each test should be independent

### Data Generation

```typescript
// Generate deal data
const dealData = TestDataGenerator.generateDealData({
  name: 'Custom Deal Name',
  amount: '100000'
});

// Generate contact data
const contactData = TestDataGenerator.generateContactData();

// Generate checklist items
const checklistItems = TestDataGenerator.generateChecklistItems(5);
```

## 🐛 Debugging

### Debug Mode

```bash
# Run in debug mode (step through tests)
npm run test:debug

# Run specific test in debug mode
npx playwright test deals/tests/deals-crud.spec.ts --debug
```

### Screenshots and Videos

- Screenshots are automatically taken on test failures
- Videos are recorded for failed tests
- Traces are captured for debugging

### Console Logs

```typescript
// Enable console logging in tests
test('should log information', async ({ page }) => {
  page.on('console', msg => console.log(msg.text()));
  // ... test code
});
```

## 🔄 CI/CD Integration

### GitHub Actions

```yaml
- name: Run E2E Tests
  run: |
    cd tests/e2e
    npm ci
    npm run install
    npm test
  env:
    BASE_URL: http://localhost:8080
    CI: true
```

### Docker

```bash
# Run tests in Docker
docker run --rm \
  -v $(pwd):/workspace \
  -w /workspace/tests/e2e \
  mcr.microsoft.com/playwright:v1.45.0-focal \
  npm test
```

## 📈 Maintenance

### Updating Dependencies

```bash
# Update Playwright
npm update @playwright/test

# Update browsers
npx playwright install
```

### Adding New Tests

1. Create test file in appropriate feature directory
2. Use existing page objects or create new ones
3. Follow naming convention: `feature-name.spec.ts`
4. Add appropriate tags and categories
5. Update this README if needed

### Cleanup

```bash
# Clean test artifacts
npm run clean

# Remove old screenshots and videos
find reports/screenshots -mtime +7 -delete
find test-results -mtime +7 -delete
```

## 🆘 Troubleshooting

### Common Issues

1. **Tests timing out**: Increase timeout or check selectors
2. **Flaky tests**: Add proper waits and use retry logic
3. **Authentication failures**: Check credentials and session handling
4. **Browser crashes**: Update browsers and check system resources
5. **Docker issues**: Ensure SuiteCRM container is running

### Getting Help

1. Check test reports and screenshots
2. Run tests with `--debug` flag
3. Review console logs and network requests
4. Check Playwright documentation
5. File issues with detailed error information

## 📚 Resources

- [Playwright Documentation](https://playwright.dev/)
- [SuiteCRM Documentation](https://docs.suitecrm.com/)
- [TypeScript Documentation](https://www.typescriptlang.org/docs/)
- [Page Object Model Guide](https://playwright.dev/docs/pom)