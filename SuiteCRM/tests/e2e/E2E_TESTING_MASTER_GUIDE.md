# MakeDealCRM E2E Testing Master Guide

## Table of Contents

1. [Overview](#overview)
2. [Quick Start Guide](#quick-start-guide)
3. [Framework Architecture](#framework-architecture)
4. [Feature Test Coverage](#feature-test-coverage)
5. [Test Setup and Environment](#test-setup-and-environment)
6. [Writing and Maintaining Tests](#writing-and-maintaining-tests)
7. [Test Data Management](#test-data-management)
8. [Page Object Models](#page-object-models)
9. [CI/CD Integration](#cicd-integration)
10. [Performance and Debugging](#performance-and-debugging)
11. [Troubleshooting Guide](#troubleshooting-guide)
12. [Developer Onboarding](#developer-onboarding)
13. [Maintenance Procedures](#maintenance-procedures)

---

## Overview

The MakeDealCRM E2E testing suite is built with Playwright and provides comprehensive coverage for all five major features of the deal management system. This guide consolidates information from all individual README files and provides complete guidance for testing, maintenance, and development.

### System Architecture

```
MakeDealCRM/
├── SuiteCRM/
│   └── tests/
│       └── e2e/                    # Main E2E testing directory
│           ├── deals/              # Feature-specific tests
│           ├── lib/                # Shared utilities and helpers
│           ├── page-objects/       # Page Object Model implementations
│           ├── examples/           # Example tests and patterns
│           ├── scripts/            # Utility scripts
│           └── test-data/          # Test data files
```

### Supported Features

1. **Feature 1**: Deal as Central Object
2. **Feature 2**: Unified Deal & Portfolio Pipeline (Drag & Drop)
3. **Feature 3**: Personal Due-Diligence Checklists
4. **Feature 4**: Simplified Stakeholder Tracking
5. **Feature 5**: At-a-Glance Financial & Valuation Hub

---

## Quick Start Guide

### Prerequisites

- Docker Desktop installed and running
- Node.js 14+ installed
- Git repository cloned locally

### Installation

```bash
# Navigate to E2E test directory
cd /Users/andrewgauntlet/Desktop/MakeDealCRM/SuiteCRM/tests/e2e

# Install dependencies
npm install

# Install Playwright browsers
npm run install:browsers

# Setup environment
cp .env.example .env
# Edit .env with your configuration
```

### Environment Configuration

```bash
# .env file
BASE_URL=http://localhost:8080
ADMIN_USERNAME=admin
ADMIN_PASSWORD=admin123
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=root
DB_NAME=suitecrm
```

### Start Services

```bash
# Start Docker services
docker-compose up -d

# Verify services are running
npm run check:env
```

### Run Tests

```bash
# Run all tests
npm test

# Run specific feature tests
npm run test:deals
./run-feature1-tests.sh
./run-feature3-tests.sh
./run-feature4-tests.sh
./run-financial-hub-tests.sh

# Run with UI mode for debugging
npm run test:ui

# Run on specific browser
npm run test:chrome
npm run test:firefox
npm run test:webkit
```

---

## Framework Architecture

### Core Components

#### 1. Test Framework
- **Playwright Test**: Primary testing framework
- **JavaScript/Node.js**: Test implementation language
- **Page Object Model**: Test organization pattern
- **Custom Fixtures**: Enhanced test capabilities

#### 2. Test Organization

```
tests/e2e/
├── deals/                          # Feature tests
│   ├── feature1-deal-central-object.spec.js
│   ├── feature3-checklist-due-diligence.spec.js
│   ├── feature4-stakeholder-tracking.spec.js
│   ├── financial-hub.spec.js
│   ├── duplicate-detection.spec.js
│   └── helpers/                    # Feature-specific helpers
├── lib/                           # Shared utilities
│   ├── data/                      # Data management
│   ├── fixtures/                  # Test fixtures
│   ├── helpers/                   # Helper classes
│   └── pages/                     # Base page objects
├── page-objects/                  # Page Object Models
│   ├── BasePage.js
│   ├── DealPage.js
│   ├── ContactPage.js
│   ├── ChecklistPage.js
│   └── PipelinePage.js
└── examples/                      # Example implementations
```

#### 3. Configuration Files

- `playwright.config.js`: Main Playwright configuration
- `package.json`: Dependencies and npm scripts
- `.env`: Environment-specific settings
- `visual-regression.config.js`: Visual testing configuration

### Enhanced Features

#### Advanced Test Data Management
- **Enhanced Test Data Manager**: Comprehensive data creation and cleanup
- **Bulk Data Utilities**: High-performance dataset creation
- **Relationship Manager**: Complex data relationship handling
- **Environment Seeder**: Full environment setup and teardown
- **State Verification**: Database integrity checking
- **Test Isolation**: Prevents test interference

#### Performance Testing
- **Memory Management**: Automatic monitoring and optimization
- **Performance Benchmarking**: Built-in timing and metrics
- **Bulk Operations**: Efficient large-scale testing
- **Threshold Monitoring**: Performance regression detection

---

## Feature Test Coverage

### Feature 1: Deal as Central Object

**Test File**: `deals/feature1-deal-central-object.spec.js`

**Coverage**:
- ✅ Deal creation with financial fields
- ✅ Contact association with role assignment
- ✅ Document management and upload
- ✅ Data persistence verification
- ✅ Bidirectional relationship testing
- ✅ Edge case and error handling

**Key Test Scenarios**:
```javascript
// Main test case from PRD Test Case 1.1
test('Test Case 1.1: E2E Deal Creation and Data Association', async ({ page }) => {
  // Creates deal with TTM Revenue, EBITDA, asking price
  // Associates contact with "Seller" role
  // Uploads and verifies document attachment
  // Tests data persistence across page refreshes
});
```

**Business Requirements Coverage**:
- Deal acts as central hub for all related data
- Financial fields are properly captured and calculated
- Related entities (contacts, documents) are properly linked
- Data integrity is maintained across operations

### Feature 2: Unified Deal & Portfolio Pipeline

**Test Files**: `pipeline/pipeline-drag-drop.spec.ts` and related

**Coverage**:
- ✅ Drag and drop functionality between pipeline stages
- ✅ Mobile gesture support and touch interactions
- ✅ Work-in-progress (WIP) limit enforcement
- ✅ Stage transition validation
- ✅ Visual feedback during drag operations
- ✅ Error handling for invalid moves

**Key Features Tested**:
- Kanban board functionality
- Deal stage management
- Pipeline visualization
- Mobile responsiveness
- Performance with large datasets

### Feature 3: Personal Due-Diligence Checklists

**Test File**: `deals/feature3-checklist-due-diligence.spec.js`

**Coverage**:
- ✅ Checklist template creation and management
- ✅ Template application to deals
- ✅ Automatic task generation
- ✅ Progress tracking and completion monitoring
- ✅ Template reusability across deals
- ✅ Checklist item validation

**Test Scenarios**:
```javascript
test('Test Case 3.1: E2E Checklist Application and Task Generation', async ({ page }) => {
  // Creates "E2E Financial Checklist" template
  // Applies template to deal
  // Verifies auto-generated tasks
  // Tests progress tracking (50% completion)
});
```

**Business Value**:
- Standardized due diligence processes
- Automated task management
- Progress visibility and tracking
- Template reusability for efficiency

### Feature 4: Simplified Stakeholder Tracking

**Test File**: `deals/feature4-stakeholder-tracking.spec.js`

**Coverage**:
- ✅ Stakeholder role assignment and management
- ✅ Contact-to-deal relationship creation
- ✅ Bidirectional relationship verification
- ✅ Multiple stakeholder support
- ✅ Role-based organization
- ✅ Data persistence and integrity

**Key Stakeholder Roles Tested**:
- Lender
- Buyer
- Seller
- Decision Maker
- Advisor

**Integration Points**:
- Contact management system
- Deal management workflows
- Relationship tracking
- Role-based permissions

### Feature 5: At-a-Glance Financial & Valuation Hub

**Test File**: `deals/financial-hub.spec.js`

**Coverage**:
- ✅ Financial dashboard widget functionality
- ✅ What-if calculator integration
- ✅ Real-time calculation updates
- ✅ Data persistence across sessions
- ✅ Mathematical accuracy validation
- ✅ Error handling for invalid inputs
- ✅ Performance benchmarking

**Financial Calculations Tested**:
```javascript
// Valuation = TTM EBITDA × Multiple
// Example: $1,000,000 × 4 = $4,000,000
const calculatedValue = FinancialCalculationHelper.calculateValuation(1000000, 4);
expect(calculatedValue).toBe(4000000);
```

**Advanced Features**:
- Accessibility testing (keyboard navigation, ARIA labels)
- Performance monitoring (calculation speed < 2 seconds)
- Visual regression testing
- Multi-browser compatibility

### Cross-Feature Integration Tests

**Duplicate Detection**:
- Advanced duplicate detection using fuzzy matching
- Domain extraction and company name normalization
- Performance testing with large datasets
- Visual regression testing for UI components

---

## Test Setup and Environment

### Docker Environment

The application runs in Docker containers, providing consistency across development and CI environments.

```yaml
# docker-compose.yml services
services:
  suitecrm:
    ports:
      - "8080:80"
    environment:
      - DATABASE_URL=mysql://root:root@mysql:3306/suitecrm
  mysql:
    ports:
      - "3306:3306"
```

### Environment Profiles

Multiple environment configurations are supported:

#### Local Development
```javascript
// test-environment-config.js
const localConfig = {
  baseUrl: 'http://localhost:8080',
  database: {
    host: 'localhost',
    port: 3306,
    user: 'root',
    password: 'root'
  },
  features: {
    visualRegression: true,
    performance: true,
    accessibility: true
  }
};
```

#### CI/CD Environment
```javascript
const ciConfig = {
  baseUrl: process.env.CI_BASE_URL,
  headless: true,
  workers: 1,
  retries: 2,
  features: {
    visualRegression: false,
    performance: true,
    accessibility: false
  }
};
```

### Test Data Seeding

Multiple seeding profiles for different testing scenarios:

```javascript
// Environment seeding profiles
const seedProfiles = {
  minimal: {
    accounts: 5,
    contacts: 15,
    deals: 10,
    documents: 25,
    checklists: 32
  },
  performance: {
    accounts: 100,
    contacts: 500,
    deals: 400,
    documents: 1200,
    checklists: 800
  },
  stress: {
    accounts: 500,
    contacts: 2500,
    deals: 2000,
    documents: 6000,
    checklists: 4000
  }
};
```

### Database Management

#### Schema Verification
```javascript
// Automatic database state verification
const verifier = new StateVerificationHelpers(connection);
const result = await verifier.verifyDatabaseState({
  deals: 10,
  accounts: 5,
  contacts: 15
}, {
  includeRelationships: true,
  includeIntegrityChecks: true
});
```

#### Relationship Integrity
```javascript
// Relationship management and validation
const relationshipManager = new DataRelationshipManager(connection);
const integrityReport = await relationshipManager.getRelationshipIntegrityReport();
```

---

## Writing and Maintaining Tests

### Test Structure Best Practices

#### 1. Use Test Fixtures

```javascript
const { test, expect } = require('../lib/fixtures/enhanced-test-fixtures');

test('Deal creation with relationships', async ({ 
  dealFixture, 
  relationshipManager,
  stateVerifier 
}) => {
  const { deal, account, contacts } = await dealFixture.createDealWithRelationships({
    name: 'Test Deal',
    amount: 100000
  });
  
  // Verify relationships
  const integrityReport = await relationshipManager.getRelationshipIntegrityReport();
  expect(integrityReport.brokenForeignKeys.length).toBe(0);
});
```

#### 2. Page Object Pattern

```javascript
const { DealPage, ContactPage } = require('../page-objects');

test('Create deal with contact', async ({ page }) => {
  const dealPage = new DealPage(page);
  const contactPage = new ContactPage(page);
  
  // Create deal
  await dealPage.goto();
  await dealPage.createDeal({
    name: 'Test Deal',
    amount: 100000
  });
  
  // Add contact
  await dealPage.navigateToContactsSubpanel();
  await contactPage.createContactInSubpanel({
    firstName: 'John',
    lastName: 'Doe',
    role: 'decision_maker'
  });
});
```

#### 3. Data Management

```javascript
test('Performance test with bulk data', async ({ bulkDataUtils, performanceTesting }) => {
  // Create large dataset for performance testing
  const dataset = await bulkDataUtils.createPerformanceDataset({
    accounts: 100,
    contactsPerAccount: 5,
    dealsPerAccount: 10
  });
  
  // Run performance benchmark
  const benchmark = await performanceTesting.benchmark(
    'bulk-deal-search',
    async () => {
      return await dealPage.searchDeals('Test');
    },
    5 // iterations
  );
  
  expect(benchmark.summary.performance.avg).toBeLessThan(5000);
});
```

### Test Categories and Tagging

#### Test Tags
```javascript
test.describe('Deal Management @smoke @critical', () => {
  test('Create deal @regression', async ({ page }) => {
    // Critical path test
  });
  
  test('Advanced search @extended', async ({ page }) => {
    // Extended test suite
  });
});
```

#### Running Tagged Tests
```bash
# Run smoke tests
npm run test:smoke

# Run critical tests
npm run test:critical

# Run regression tests
npm run test:regression
```

### Error Handling and Retry Logic

```javascript
test('Robust test with retries', async ({ page }) => {
  await test.step('Navigate to deals', async () => {
    await page.goto('/deals');
    await page.waitForSelector('.deals-list', { timeout: 10000 });
  });
  
  await test.step('Create deal with retry', async () => {
    for (let attempt = 0; attempt < 3; attempt++) {
      try {
        await dealPage.createDeal(testData);
        break;
      } catch (error) {
        if (attempt === 2) throw error;
        await page.reload();
      }
    }
  });
});
```

---

## Test Data Management

### Enhanced Test Data Manager

The system provides sophisticated test data management capabilities:

#### Automatic Data Generation
```javascript
const manager = new EnhancedTestDataManager({
  testPrefix: 'E2E_TEST_',
  isolationLevel: 'test',
  enableCaching: true,
  enableMetrics: true
});

// Generate realistic test scenarios
const scenario = await manager.createTestScenario('complete-deal-lifecycle', {
  dealCount: 5,
  includeContacts: true,
  includeDocuments: true,
  includeChecklists: true
});
```

#### Bulk Data Operations
```javascript
const bulkUtils = new BulkDataUtilities({
  maxBatchSize: 500,
  parallelBatches: 3,
  enableProgressBar: true
});

// Create performance test dataset
const dataset = await bulkUtils.createPerformanceDataset({
  accounts: 100,
  contactsPerAccount: 5,
  dealsPerAccount: 10,
  documentsPerDeal: 3
});
```

#### Test Isolation
```javascript
const isolationManager = new TestIsolationManager(connection, {
  isolationLevel: 'test',
  enableNamespacing: true
});

// Create isolated test context
const contextId = await isolationManager.initializeIsolationContext(testInfo);
const isolatedDeal = await isolationManager.createIsolatedData(contextId, 'deals', {
  name: 'Isolated Test Deal'
});
```

### Data Cleanup and Verification

#### Automatic Cleanup
```javascript
test.afterEach(async ({ enhancedDataManager }) => {
  // Automatic cleanup with verification
  await enhancedDataManager.cleanup({
    verifyCleanup: true,
    maxAttempts: 3
  });
});
```

#### State Verification
```javascript
const stateVerifier = new StateVerificationHelpers(connection);
const result = await stateVerifier.verifyDatabaseState({
  deals: { expected: 10, tolerance: 0 },
  accounts: { expected: 5, tolerance: 1 }
}, {
  includeRelationships: true,
  includeIntegrityChecks: true,
  strictMode: false
});
```

---

## Page Object Models

### Architecture Overview

Page objects encapsulate page-specific logic and provide a clean interface for test interactions.

#### Base Page Structure
```javascript
class BasePage {
  constructor(page) {
    this.page = page;
    this.auth = new AuthHelper(page);
    this.navigation = new NavigationHelper(page);
    this.wait = new WaitHelper(page);
    this.assertions = new AssertionsHelper(page);
    this.screenshot = new ScreenshotHelper(page);
  }
  
  async goto(path = '') {
    await this.page.goto(`${this.baseUrl}${path}`);
    await this.waitForPageLoad();
  }
  
  async waitForPageLoad() {
    await this.page.waitForLoadState('networkidle');
    await this.wait.waitForElement('body');
  }
}
```

### Key Page Objects

#### DealPage
```javascript
class DealPage extends BasePage {
  // Enhanced with financial hub methods
  async openFinancialHub() {
    const selector = '.financial-hub-widget, .financial-dashboard, .valuation-widget';
    await this.page.click(selector);
  }
  
  async openWhatIfCalculator() {
    const calculatorButton = this.page.locator([
      'button:has-text("What-if Calculator")',
      'a:has-text("What-if Calculator")',
      'button:has-text("Calculator")'
    ].join(', '));
    
    await calculatorButton.click();
  }
  
  async updateMultipleInCalculator(newMultiple) {
    const multipleInput = this.page.locator([
      'input[name*="multiple"]',
      'input[placeholder*="Multiple"]',
      '.calculator input[type="number"]'
    ].join(', '));
    
    await multipleInput.fill(newMultiple.toString());
    await this.page.keyboard.press('Enter');
  }
}
```

#### ChecklistPage
```javascript
class ChecklistPage extends BasePage {
  async createTemplate(templateData) {
    await this.page.click('button:has-text("Create Template")');
    await this.fillTemplateForm(templateData);
    await this.addTemplateItems(templateData.items);
    await this.page.click('button:has-text("Save Template")');
  }
  
  async applyTemplate(templateName, dealName) {
    await this.page.click(`a:has-text("${dealName}")`);
    await this.page.click('button:has-text("Apply Checklist Template")');
    await this.page.selectOption('select[name="template"]', templateName);
    await this.page.click('button:has-text("Apply")');
  }
}
```

#### ContactPage
```javascript
class ContactPage extends BasePage {
  async createContactWithRole(contactData) {
    await this.fillContactForm(contactData);
    
    // Handle role assignment with multiple selector strategies
    const roleSelectors = [
      'select[name="contact_role"]',
      '#contact_role',
      '.contact-role-select'
    ];
    
    for (const selector of roleSelectors) {
      try {
        await this.page.selectOption(selector, contactData.role);
        break;
      } catch (error) {
        // Try next selector
      }
    }
  }
}
```

### Mobile and Responsive Testing

```javascript
// Mobile-specific page object methods
class PipelinePage extends BasePage {
  async dragDealToStageOnMobile(dealName, targetStage) {
    // Touch-based drag and drop for mobile devices
    const dealElement = this.page.locator(`[data-deal="${dealName}"]`);
    const stageElement = this.page.locator(`[data-stage="${targetStage}"]`);
    
    // Simulate touch events
    await dealElement.dispatchEvent('touchstart');
    await stageElement.dispatchEvent('touchmove');
    await stageElement.dispatchEvent('touchend');
  }
}
```

---

## CI/CD Integration

### GitHub Actions Configuration

```yaml
name: E2E Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  e2e-tests:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: suitecrm
        ports:
          - 3306:3306
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup Node.js
      uses: actions/setup-node@v3
      with:
        node-version: '18'
        cache: 'npm'
        cache-dependency-path: SuiteCRM/tests/e2e/package-lock.json
    
    - name: Start SuiteCRM Services
      run: |
        docker-compose up -d
        sleep 30  # Wait for services to be ready
    
    - name: Install dependencies
      run: |
        cd SuiteCRM/tests/e2e
        npm ci
        npx playwright install
    
    - name: Run E2E tests
      run: |
        cd SuiteCRM/tests/e2e
        npm run test:ci
      env:
        BASE_URL: http://localhost:8080
        ADMIN_USERNAME: admin
        ADMIN_PASSWORD: admin123
    
    - name: Upload test results
      uses: actions/upload-artifact@v3
      if: always()
      with:
        name: test-results
        path: SuiteCRM/tests/e2e/test-results/
    
    - name: Upload HTML report
      uses: actions/upload-artifact@v3
      if: always()
      with:
        name: html-report
        path: SuiteCRM/tests/e2e/playwright-report/
```

### Jenkins Pipeline

```groovy
pipeline {
    agent any
    
    stages {
        stage('Setup') {
            steps {
                sh 'docker-compose up -d'
                sh 'sleep 30'
            }
        }
        
        stage('Install Dependencies') {
            steps {
                dir('SuiteCRM/tests/e2e') {
                    sh 'npm ci'
                    sh 'npx playwright install'
                }
            }
        }
        
        stage('Feature Tests') {
            parallel {
                stage('Feature 1') {
                    steps {
                        dir('SuiteCRM/tests/e2e') {
                            sh './run-feature1-tests.sh'
                        }
                    }
                }
                stage('Feature 3') {
                    steps {
                        dir('SuiteCRM/tests/e2e') {
                            sh './run-feature3-tests.sh'
                        }
                    }
                }
                stage('Feature 4') {
                    steps {
                        dir('SuiteCRM/tests/e2e') {
                            sh './run-feature4-tests.sh'
                        }
                    }
                }
                stage('Financial Hub') {
                    steps {
                        dir('SuiteCRM/tests/e2e') {
                            sh './run-financial-hub-tests.sh'
                        }
                    }
                }
            }
        }
        
        stage('Integration Tests') {
            steps {
                dir('SuiteCRM/tests/e2e') {
                    sh 'npm run test:regression'
                }
            }
        }
    }
    
    post {
        always {
            publishHTML([
                allowMissing: false,
                alwaysLinkToLastBuild: true,
                keepAll: true,
                reportDir: 'SuiteCRM/tests/e2e/playwright-report',
                reportFiles: 'index.html',
                reportName: 'E2E Test Report'
            ])
            
            archiveArtifacts artifacts: 'SuiteCRM/tests/e2e/test-results/**/*'
        }
        cleanup {
            sh 'docker-compose down'
        }
    }
}
```

### Environment-Specific Configurations

#### Staging Environment
```javascript
// staging.config.js
module.exports = {
  ...baseConfig,
  use: {
    baseURL: 'https://staging.makedealecrm.com',
    ignoreHTTPSErrors: true,
  },
  webServer: undefined, // Don't start local server
  retries: 3,
  workers: 2
};
```

#### Production Smoke Tests
```javascript
// production.config.js
module.exports = {
  ...baseConfig,
  testMatch: '**/*.smoke.spec.js',
  use: {
    baseURL: 'https://app.makedealcrm.com',
  },
  retries: 5,
  workers: 1
};
```

---

## Performance and Debugging

### Performance Testing

#### Built-in Performance Monitoring
```javascript
test('Performance benchmark', async ({ performanceTesting, bulkDataUtils }) => {
  const benchmark = await performanceTesting.benchmark(
    'bulk-deal-creation',
    async () => {
      return await bulkDataUtils.createBulkDeals(100);
    },
    5 // iterations
  );

  expect(benchmark.summary.performance.avg).toBeLessThan(10000); // < 10 seconds
  expect(benchmark.summary.throughput).toBeGreaterThan(10); // > 10 deals/second
});
```

#### Memory Management
```javascript
test('Memory usage monitoring', async ({ enhancedDataManager }) => {
  const manager = new EnhancedTestDataManager({
    enableMemoryMonitoring: true,
    maxMemoryUsage: '512MB'
  });
  
  // Monitor memory during bulk operations
  const memoryUsage = await manager.monitorMemoryUsage(async () => {
    return await manager.createTestScenario('stress-test');
  });
  
  expect(memoryUsage.peak).toBeLessThan(512 * 1024 * 1024); // 512MB
});
```

### Debugging Tools

#### Debug Mode
```bash
# Run tests with debug mode
npm run test:debug

# Run specific test with debugging
npx playwright test --debug deals/feature1-deal-central-object.spec.js

# Run with headed browser
npm run test:headed
```

#### Screenshot and Video Capture
```javascript
test('Test with debugging artifacts', async ({ page }) => {
  // Automatic screenshot on failure
  await page.screenshot({ path: 'debug-screenshot.png', fullPage: true });
  
  // Custom screenshot at specific points
  await dealPage.takeScreenshot('before-deal-creation');
  
  // Video recording (automatic on failure)
  // Videos are saved to test-results/video/
});
```

#### Trace Viewer
```bash
# Run with trace collection
npx playwright test --trace=on

# View trace after test
npx playwright show-trace test-results/trace.zip
```

#### Console Logs and Network Monitoring
```javascript
test('Debug with console and network monitoring', async ({ page }) => {
  // Capture console logs
  page.on('console', msg => console.log('CONSOLE:', msg.text()));
  
  // Monitor network requests
  page.on('request', request => {
    console.log('REQUEST:', request.url());
  });
  
  page.on('response', response => {
    console.log('RESPONSE:', response.status(), response.url());
  });
});
```

### Performance Optimization

#### Test Execution Optimization
```javascript
// playwright.config.js
module.exports = defineConfig({
  // Optimize for CI environments
  workers: process.env.CI ? 1 : 4,
  fullyParallel: !process.env.CI,
  
  // Optimize timeouts
  timeout: 30 * 1000,
  expect: { timeout: 5000 },
  
  // Optimize browser settings
  use: {
    // Disable images and CSS for faster loading
    extraHTTPHeaders: process.env.CI ? {
      'Accept': 'text/html'
    } : {}
  }
});
```

---

## Troubleshooting Guide

### Common Issues and Solutions

#### 1. Test Environment Issues

**Issue**: Docker services not starting
```bash
# Solution
docker-compose down
docker system prune -f
docker-compose up -d --build
```

**Issue**: Database connection errors
```bash
# Check database status
docker-compose ps
docker logs suitecrm_mysql_1

# Reset database
docker-compose down -v
docker-compose up -d
```

**Issue**: Port conflicts
```bash
# Check port usage
lsof -i :8080
lsof -i :3306

# Update ports in docker-compose.yml or .env
```

#### 2. Test Execution Issues

**Issue**: Browser not installed
```bash
# Install browsers
npm run install:browsers

# Or install specific browser
npx playwright install chromium
```

**Issue**: Test timeouts
```javascript
// Increase timeouts in playwright.config.js
module.exports = defineConfig({
  timeout: 60 * 1000, // 60 seconds
  expect: { timeout: 10000 }, // 10 seconds
  use: {
    navigationTimeout: 60000 // 60 seconds
  }
});
```

**Issue**: Element not found
```javascript
// Use multiple selector strategies
const element = page.locator([
  '#primary-selector',
  '.fallback-selector',
  'button:has-text("Button Text")'
].join(', '));

// Add explicit waits
await page.waitForSelector('.dynamic-element', { timeout: 10000 });
```

#### 3. Data Management Issues

**Issue**: Test data conflicts
```javascript
// Use unique identifiers
const timestamp = Date.now();
const testData = {
  name: `Test Deal ${timestamp}`,
  email: `test.${timestamp}@example.com`
};
```

**Issue**: Cleanup failures
```javascript
// Enhanced cleanup with retry logic
test.afterEach(async ({ enhancedDataManager }) => {
  await enhancedDataManager.cleanup({
    verifyCleanup: true,
    maxAttempts: 3,
    cleanupTimeout: 30000
  });
});
```

#### 4. Performance Issues

**Issue**: Slow test execution
```bash
# Run with fewer workers
npm test -- --workers=1

# Use headed mode to observe
npm run test:headed

# Profile memory usage
NODE_OPTIONS="--max-old-space-size=4096" npm test
```

**Issue**: Memory leaks
```javascript
// Monitor memory usage
const manager = new EnhancedTestDataManager({
  enableMemoryMonitoring: true,
  maxMemoryUsage: '1GB'
});
```

#### 5. Visual Regression Issues

**Issue**: Screenshot differences
```bash
# Update baselines
UPDATE_BASELINES=true npm test

# Compare differences
npx playwright show-report
```

**Issue**: Cross-platform differences
```javascript
// Use threshold for minor differences
expect(await page.screenshot()).toMatchSnapshot('screenshot.png', {
  threshold: 0.2, // 20% difference allowed
  maxDiffPixels: 100
});
```

### Debug Workflows

#### Step-by-Step Debugging Process

1. **Identify the Issue**
   ```bash
   # Run with verbose output
   DEBUG=pw:api npm test
   
   # Run single test
   npx playwright test -g "failing test name"
   ```

2. **Examine Artifacts**
   ```bash
   # View HTML report
   npm run report
   
   # Check screenshots
   open test-results/screenshots/
   
   # Watch video recordings
   open test-results/videos/
   ```

3. **Interactive Debugging**
   ```javascript
   test('Debug specific issue', async ({ page }) => {
     await page.pause(); // Pauses execution
     
     // Step through code
     await page.goto('/deals');
     await page.pause();
     
     await dealPage.createDeal(testData);
     await page.pause();
   });
   ```

4. **Environment Verification**
   ```bash
   # Check environment
   npm run check:env
   
   # Verify services
   curl http://localhost:8080/health
   
   # Check database
   mysql -h localhost -u root -proot suitecrm -e "SHOW TABLES;"
   ```

### Error Recovery Patterns

#### Automatic Retry Logic
```javascript
async function withRetry(operation, maxAttempts = 3) {
  for (let attempt = 1; attempt <= maxAttempts; attempt++) {
    try {
      return await operation();
    } catch (error) {
      if (attempt === maxAttempts) throw error;
      
      console.log(`Attempt ${attempt} failed, retrying...`);
      await page.reload();
      await page.waitForLoadState('networkidle');
    }
  }
}

// Usage
await withRetry(async () => {
  await dealPage.createDeal(testData);
});
```

#### Graceful Degradation
```javascript
test('Robust test with fallbacks', async ({ page }) => {
  try {
    // Primary approach
    await dealPage.openFinancialHub();
  } catch (error) {
    // Fallback approach
    console.log('Financial hub not available, using alternative');
    await dealPage.navigateToFinancialData();
  }
});
```

---

## Developer Onboarding

### New Developer Checklist

#### Prerequisites Setup
- [ ] Install Docker Desktop
- [ ] Install Node.js 18+
- [ ] Install Git
- [ ] Clone repository
- [ ] Install VS Code + Playwright extension (recommended)

#### Environment Setup
```bash
# 1. Navigate to project
cd /path/to/MakeDealCRM/SuiteCRM/tests/e2e

# 2. Install dependencies
npm install

# 3. Install browsers
npm run install:browsers

# 4. Setup environment file
cp .env.example .env
# Edit .env with local settings

# 5. Start services
docker-compose up -d

# 6. Verify setup
npm run check:env

# 7. Run smoke tests
npm run test:smoke
```

#### Learning Path

**Week 1: Foundation**
- Read this master guide
- Run existing tests to understand structure
- Review page object implementations
- Practice basic Playwright commands

**Week 2: Test Writing**
- Write simple test following existing patterns
- Use page objects and fixtures
- Practice debugging techniques
- Create pull request for review

**Week 3: Advanced Features**
- Learn test data management
- Practice performance testing
- Implement visual regression tests
- Work on complex scenarios

**Week 4: Integration**
- Contribute to feature test suites
- Implement CI/CD improvements
- Mentor other developers
- Lead test review sessions

### Code Review Guidelines

#### Test Code Standards

**Structure Requirements**:
```javascript
// ✅ Good test structure
test.describe('Feature Name', () => {
  test.beforeEach(async ({ authenticatedPage }) => {
    // Setup code
  });
  
  test('should perform specific action @smoke', async ({ page }) => {
    // Test implementation with clear steps
    await test.step('Navigate to feature', async () => {
      // Step implementation
    });
    
    await test.step('Perform action', async () => {
      // Action implementation
    });
    
    await test.step('Verify result', async () => {
      // Assertion implementation
    });
  });
  
  test.afterEach(async ({ dataManager }) => {
    // Cleanup code
  });
});
```

**Naming Conventions**:
- Test files: `feature-name.spec.js`
- Page objects: `FeaturePage.js`
- Helpers: `feature.helper.js`
- Test data: `feature-test-data.json`

**Documentation Requirements**:
- Each test file must have header comments
- Complex logic must be documented
- Page object methods must have JSDoc
- README files for new features

#### Review Checklist

**Functionality**:
- [ ] Tests cover all acceptance criteria
- [ ] Error scenarios are handled
- [ ] Data cleanup is implemented
- [ ] Assertions are meaningful

**Performance**:
- [ ] No unnecessary waits or delays
- [ ] Efficient selector strategies used
- [ ] Memory usage is reasonable
- [ ] Bulk operations are optimized

**Maintainability**:
- [ ] Page objects are used consistently
- [ ] Code is DRY (Don't Repeat Yourself)
- [ ] Clear variable and method names
- [ ] Proper error handling

**Standards Compliance**:
- [ ] Follows existing patterns
- [ ] Proper test isolation
- [ ] Appropriate use of fixtures
- [ ] Documentation is complete

### Training Resources

#### Internal Documentation
- This master guide
- Individual feature README files
- Code examples in `/examples` directory
- Page object documentation

#### External Resources
- [Playwright Documentation](https://playwright.dev/)
- [JavaScript Testing Best Practices](https://github.com/goldbergyoni/javascript-testing-best-practices)
- [Page Object Model Pattern](https://playwright.dev/docs/pom)
- [Docker for Developers](https://docs.docker.com/get-started/)

#### Hands-on Exercises

**Exercise 1: Write a Simple Test**
```javascript
// Task: Create a test that verifies deal search functionality
test('Deal search returns relevant results', async ({ page }) => {
  // Implementation required
});
```

**Exercise 2: Create a Page Object**
```javascript
// Task: Create a page object for the Reports module
class ReportsPage extends BasePage {
  // Implementation required
}
```

**Exercise 3: Test Data Scenario**
```javascript
// Task: Create a test scenario with complex relationships
const scenario = await dataManager.createTestScenario('complex-deal-network', {
  // Configuration required
});
```

---

## Maintenance Procedures

### Regular Maintenance Tasks

#### Daily Tasks (Automated)
- Run smoke tests on all environments
- Monitor test execution metrics
- Check for flaky tests
- Update test data if needed

#### Weekly Tasks
- Review test execution reports
- Update browser versions
- Clean up test artifacts
- Review and merge test improvements

#### Monthly Tasks
- Update dependencies
- Review test coverage metrics
- Optimize performance bottlenecks
- Update documentation

#### Quarterly Tasks
- Major framework updates
- Architecture reviews
- Training sessions
- Tool evaluations

### Test Data Maintenance

#### Cleanup Procedures
```bash
# Daily automated cleanup
npm run cleanup:data

# Manual cleanup for specific environments
node scripts/cleanup-test-data.js --environment=staging

# Deep cleanup with verification
node scripts/deep-cleanup.js --verify=true
```

#### Data Refresh Procedures
```bash
# Refresh test environment with latest data
npm run generate:data -- --profile=default

# Create performance test dataset
npm run generate:data -- --profile=performance

# Seed demo environment
npm run generate:data -- --profile=demo
```

### Performance Monitoring

#### Metrics Collection
```javascript
// Performance metrics are automatically collected
const metrics = performanceTesting.getMetrics();
console.log(`Average test duration: ${metrics.averageDuration}ms`);
console.log(`Memory usage: ${metrics.memoryUsage}MB`);
console.log(`Success rate: ${metrics.successRate}%`);
```

#### Performance Thresholds
```javascript
// Set performance thresholds
const thresholds = {
  testDuration: 30000, // 30 seconds max
  memoryUsage: 512, // 512MB max
  successRate: 95 // 95% minimum
};
```

#### Performance Optimization
1. **Identify bottlenecks** using built-in metrics
2. **Optimize selectors** for faster element location
3. **Reduce unnecessary waits** and delays
4. **Use parallel execution** where appropriate
5. **Optimize test data** creation and cleanup

### Browser and Dependency Updates

#### Browser Updates
```bash
# Update Playwright and browsers
npm update @playwright/test
npm run install:browsers

# Test after updates
npm run test:smoke
```

#### Dependency Management
```bash
# Check for outdated dependencies
npm outdated

# Update dependencies
npm update

# Check for security vulnerabilities
npm audit
npm audit fix
```

### Documentation Updates

#### When to Update Documentation
- New features or tests added
- API changes or improvements
- Configuration changes
- Process improvements
- Architecture changes

#### Documentation Review Process
1. **Identify changes** that require documentation updates
2. **Update relevant files** (README, guides, examples)
3. **Review for accuracy** and completeness
4. **Test examples** and procedures
5. **Get peer review** before merging

### Monitoring and Alerting

#### Test Execution Monitoring
```javascript
// Example monitoring setup
const monitoring = {
  successRate: {
    threshold: 95,
    action: 'alert'
  },
  duration: {
    threshold: 1800000, // 30 minutes
    action: 'investigate'
  },
  memoryUsage: {
    threshold: 1024, // 1GB
    action: 'optimize'
  }
};
```

#### Alert Configuration
- **Slack notifications** for test failures
- **Email alerts** for performance degradation
- **Dashboard updates** for metrics tracking
- **Automated tickets** for recurring issues

---

## Conclusion

This comprehensive E2E Testing Master Guide provides complete guidance for testing, maintaining, and developing the MakeDealCRM application. The testing framework is designed to be:

- **Comprehensive**: Covers all five major features
- **Maintainable**: Uses page objects and clean architecture
- **Scalable**: Supports performance testing and bulk operations
- **Reliable**: Includes robust error handling and retry logic
- **Developer-friendly**: Provides clear documentation and examples

### Key Benefits

1. **Consistent Testing**: Standardized patterns across all features
2. **High Coverage**: Comprehensive test coverage for critical business functions
3. **Performance Monitoring**: Built-in performance testing and optimization
4. **Easy Maintenance**: Clear documentation and automated cleanup
5. **CI/CD Ready**: Full integration with modern deployment pipelines

### Next Steps

1. **Review and implement** any missing test scenarios
2. **Customize configurations** for your specific environment
3. **Train team members** using the developer onboarding guide
4. **Set up monitoring** and alerting for continuous improvement
5. **Regular maintenance** following the procedures outlined

For questions or support, refer to the individual feature documentation or contact the testing team.

---

*Last updated: July 2025*
*Version: 1.0.0*