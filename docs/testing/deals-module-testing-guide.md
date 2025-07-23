# Deals Module Testing Guide

## Overview

This document provides comprehensive testing documentation for the Deals module in MakeDealCRM. The test suite includes unit tests, integration tests, and end-to-end UI tests.

## Test Structure

```
SuiteCRM/tests/
├── unit/phpunit/modules/Deals/
│   ├── DealTest.php                         # Basic bean tests
│   ├── DealTestComprehensive.php           # Comprehensive bean tests
│   ├── DealsLogicHooksTest.php             # Basic logic hooks tests
│   └── DealsLogicHooksTestComprehensive.php # Comprehensive logic hooks tests
├── e2e/
│   ├── deals/
│   │   └── deals.spec.js                   # Playwright E2E tests
│   └── playwright.config.js                # Playwright configuration
├── fixtures/
│   └── DealsTestData.php                   # Test data generator
└── run-deals-tests.sh                      # Test runner script
```

## Running Tests

### Quick Start

Run all tests with the comprehensive test runner:

```bash
cd SuiteCRM/tests
./run-deals-tests.sh
```

### Individual Test Suites

#### PHPUnit Tests

```bash
# Run all Deals unit tests
./vendor/bin/phpunit tests/unit/phpunit/modules/Deals/

# Run specific test file
./vendor/bin/phpunit tests/unit/phpunit/modules/Deals/DealTest.php

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage-report tests/unit/phpunit/modules/Deals/
```

#### Playwright E2E Tests

```bash
# Install dependencies (first time)
npm install @playwright/test
npx playwright install

# Run E2E tests
npx playwright test --config=tests/e2e/playwright.config.js

# Run in headed mode (see browser)
npx playwright test --headed

# Run specific browser
npx playwright test --project=chromium

# Debug mode
npx playwright test --debug
```

### Test Data Management

```bash
# Create test data
php tests/fixtures/DealsTestData.php create

# Clean up test data
php tests/fixtures/DealsTestData.php cleanup
```

## Test Coverage

### Unit Tests

The unit tests cover:

1. **Deal Bean (DealTestComprehensive.php)**
   - Basic properties and configuration
   - Save functionality with at-risk calculations
   - Financial calculations (valuation, capital stack)
   - Relationship methods
   - Template parsing
   - Edge cases (zero values, negative EBITDA, large numbers)
   - Security and ACL
   - Import/export capabilities
   - Audit trail

2. **Logic Hooks (DealsLogicHooksTestComprehensive.php)**
   - Email import and parsing
   - Financial data extraction
   - Contact creation from emails
   - Document attachment handling
   - Duplicate detection
   - List view formatting
   - At-risk status calculations

### E2E Tests

The Playwright tests cover:

1. **CRUD Operations**
   - Create new deal with all fields
   - Edit existing deal
   - Delete deal
   - Mass update

2. **List View**
   - Sorting
   - Filtering
   - Search (basic and advanced)
   - Export to CSV
   - At-risk status indicators

3. **Relationships**
   - Link contacts
   - Attach documents
   - Create activities
   - Email integration

4. **Business Logic**
   - Financial calculations
   - Deal stage progression
   - At-risk status updates
   - Duplicate detection

5. **Performance**
   - Page load times
   - Large data sets
   - Concurrent operations

6. **Accessibility**
   - ARIA labels
   - Keyboard navigation
   - Screen reader compatibility

## Test Data

The test data generator creates:

- 8 deals in different stages
- 5 deals with edge case data
- Related contacts (1-3 per deal)
- Related documents (0-3 per deal)
- Related activities (calls, tasks, notes)
- Email templates for testing

## Writing New Tests

### PHPUnit Test Template

```php
<?php

use SuiteCRM\Test\SuitePHPUnitFrameworkTestCase;

class DealNewFeatureTest extends SuitePHPUnitFrameworkTestCase
{
    protected $deal;

    protected function setUp(): void
    {
        parent::setUp();
        require_once 'modules/Deals/Deal.php';
        $this->deal = new Deal();
    }

    protected function tearDown(): void
    {
        if ($this->deal && !empty($this->deal->id)) {
            $this->deal->mark_deleted($this->deal->id);
        }
        parent::tearDown();
    }

    public function testNewFeature()
    {
        // Your test code here
        $this->assertTrue(true);
    }
}
```

### Playwright Test Template

```javascript
const { test, expect } = require('@playwright/test');

test.describe('New Deal Feature', () => {
  test.beforeEach(async ({ page }) => {
    // Login
    await page.goto(process.env.SUITECRM_URL || 'http://localhost:8080');
    await page.fill('input[name="user_name"]', 'admin');
    await page.fill('input[name="password"]', 'admin');
    await page.click('input[type="submit"]');
    await page.waitForSelector('.dashletTable');
  });

  test('should test new feature', async ({ page }) => {
    await page.click('text=Deals');
    // Your test code here
    expect(true).toBe(true);
  });
});
```

## CI/CD Integration

### GitHub Actions Example

```yaml
name: Deals Module Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:5.7
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: suitecrm_test
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '7.4'
        extensions: mbstring, mysql, gd, zip
    
    - name: Install dependencies
      run: composer install
    
    - name: Run PHPUnit tests
      run: ./vendor/bin/phpunit tests/unit/phpunit/modules/Deals/
    
    - name: Setup Node.js
      uses: actions/setup-node@v2
      with:
        node-version: '16'
    
    - name: Install Playwright
      run: |
        npm install @playwright/test
        npx playwright install
    
    - name: Run E2E tests
      run: npx playwright test
      env:
        SUITECRM_URL: http://localhost:8080
        SUITECRM_USER: admin
        SUITECRM_PASS: admin
```

## Troubleshooting

### Common Issues

1. **PHPUnit Tests Fail**
   - Check PHP version compatibility
   - Ensure database is accessible
   - Verify file permissions
   - Check for missing dependencies

2. **Playwright Tests Fail**
   - Ensure SuiteCRM is running
   - Check correct URL in environment
   - Verify login credentials
   - Check browser installation

3. **Test Data Issues**
   - Run cleanup before create
   - Check database permissions
   - Verify user session

### Debug Commands

```bash
# PHPUnit verbose output
./vendor/bin/phpunit -v tests/unit/phpunit/modules/Deals/DealTest.php

# Playwright debug mode
npx playwright test --debug

# Show Playwright browser
npx playwright test --headed

# Generate Playwright trace
npx playwright test --trace on
```

## Best Practices

1. **Test Isolation**
   - Each test should be independent
   - Clean up created data
   - Don't rely on test order

2. **Test Data**
   - Use unique identifiers
   - Create minimal required data
   - Clean up after tests

3. **Assertions**
   - Be specific in assertions
   - Test one thing per test method
   - Use meaningful test names

4. **Performance**
   - Keep tests fast
   - Mock external dependencies
   - Use test databases

5. **Maintenance**
   - Update tests with code changes
   - Remove obsolete tests
   - Document test purposes

## Test Metrics

Target metrics for the Deals module:

- Code Coverage: > 80%
- Test Execution Time: < 5 minutes
- Test Reliability: > 95%
- Critical Path Coverage: 100%

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Playwright Documentation](https://playwright.dev/docs/intro)
- [SuiteCRM Testing Guide](https://docs.suitecrm.com/developer/testing/)
- [Deals Module Architecture](../technical/deals-module-architecture.md)