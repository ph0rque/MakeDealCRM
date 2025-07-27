# MakeDealCRM Test Data Management System

This directory contains a comprehensive test data management system for E2E testing of MakeDealCRM.

## Overview

The test data management system provides:
- **Test Data Factory**: Generates realistic test data for all modules
- **Test Data Seeder**: Seeds the database with test scenarios
- **Test Helper**: Utilities for test setup, execution, and teardown
- **E2E Test Runner**: Comprehensive test execution framework
- **JSON Fixtures**: Predefined test data configurations

## Components

### 1. TestDataFactory.php
Main factory class for creating test data programmatically.

**Features:**
- Creates test records for all modules (Deals, Contacts, Accounts, etc.)
- Supports multiple scenarios (default, pipeline, at-risk, financial, etc.)
- Tracks all created records for cleanup
- Generates realistic data with proper relationships

**Usage:**
```php
$factory = new TestDataFactory();
$deal = $factory->createDeal(['name' => 'Test Deal', 'status' => 'due_diligence']);
$contact = $factory->createContact(['first_name' => 'John', 'last_name' => 'Doe']);
$factory->relateBeans($deal, $contact);
```

### 2. TestDataSeeder.php
CLI tool for seeding test data into the database.

**Commands:**
```bash
# Seed default test data
php TestDataSeeder.php seed default

# Seed all scenarios
php TestDataSeeder.php seed all

# Clean up all test data
php TestDataSeeder.php cleanup

# Reset (cleanup + reseed)
php TestDataSeeder.php reset pipeline

# List available scenarios
php TestDataSeeder.php list
```

**Available Scenarios:**
- `default`: General test data with variety of records
- `pipeline`: Deals distributed across pipeline stages
- `at_risk`: Deals with various at-risk statuses
- `financial`: Different financial scenarios
- `checklist`: Deals with checklists applied
- `edge_cases`: Edge cases and boundary conditions
- `all`: All scenarios combined

### 3. TestHelper.php
Singleton helper class for test execution.

**Features:**
- Test environment setup/teardown
- User creation and role assignment
- Cache management
- Assertions and validations
- Database utilities

**Usage:**
```php
$helper = TestHelper::getInstance();
$helper->setUp('My Test');

// Create test user
$user = $helper->createTestUser('Sales Manager');
$helper->loginAs($user);

// Run test
$deal = $helper->createQuickDeal();
$helper->assertRecordExists('Deals', $deal->id);
$helper->assertFieldValue($deal, 'status', 'initial_contact');

$helper->tearDown();
```

### 4. RunE2ETests.php
Comprehensive E2E test runner with built-in test suites.

**Test Suites:**
- Deal Module Tests
- Pipeline Tests
- Checklist Tests
- Financial Tests
- Security Tests

**Usage:**
```bash
php RunE2ETests.php
```

### 5. JSON Fixture Files

#### data/deals.json
Predefined deal configurations including:
- Sample deals with complete financial data
- At-risk deal scenarios
- Pipeline stage distribution

#### data/checklists.json
Checklist templates for each deal stage:
- Initial Contact Checklist
- NDA Process Checklist
- Due Diligence Checklist
- LOI Preparation Checklist
- Closing Checklist

#### data/email_templates.json
Email templates for deal communication:
- Initial outreach templates
- NDA process templates
- Due diligence updates
- LOI/Offer templates
- Closing coordination templates

## Best Practices

### 1. Test Data Isolation
- All test data is prefixed with `TEST_` for easy identification
- Use unique test run IDs to group related data
- Always cleanup after tests to prevent data pollution

### 2. Realistic Data Generation
- Use factory methods to generate realistic data
- Maintain proper relationships between records
- Include edge cases and boundary conditions

### 3. Performance Considerations
- Batch create operations when possible
- Use transactions for test isolation
- Clear cache after bulk operations

### 4. Security Testing
- Include XSS and SQL injection test cases
- Test with special characters and Unicode
- Verify proper data sanitization

## Integration with E2E Tests

### Playwright Integration
```javascript
const { createTestFixtures, cleanupTestFixtures } = require('./test-data-helper');

test.beforeEach(async () => {
  const { helper, fixtures } = await createTestFixtures();
  // Use fixtures in your tests
});

test.afterEach(async ({ helper }) => {
  await cleanupTestFixtures(helper);
});
```

### PHPUnit Integration
```php
class DealTest extends TestCase
{
    private $helper;
    
    protected function setUp(): void
    {
        $this->helper = TestHelper::getInstance();
        $this->helper->setUp('Deal Test');
    }
    
    protected function tearDown(): void
    {
        $this->helper->tearDown();
    }
}
```

## Maintenance

### Adding New Test Scenarios
1. Add scenario to `TestDataFactory::createFullTestScenario()`
2. Update scenario list in `TestDataSeeder`
3. Document the scenario in this README

### Updating Fixture Data
1. Edit JSON files in the `data/` directory
2. Validate JSON syntax
3. Test with seeder to ensure compatibility

### Extending Test Helper
1. Add new utility methods to `TestHelper`
2. Follow singleton pattern
3. Ensure methods are chainable where appropriate

## Troubleshooting

### Common Issues

**Issue**: Test data not cleaning up properly
**Solution**: Check that all created records are tracked in factory

**Issue**: Relationships not working
**Solution**: Ensure beans are saved before creating relationships

**Issue**: Permission errors
**Solution**: Run tests as admin user or with proper permissions

**Issue**: Cache issues
**Solution**: Call `TestHelper::clearCache()` after bulk operations

## Environment Variables

Set these in your test environment:
```bash
export SUITE_TEST_PREFIX="TEST_"
export SUITE_TEST_CLEANUP="true"
export SUITE_TEST_USER="admin"
```

## Contributing

When adding new test data features:
1. Follow existing patterns and conventions
2. Document new methods and scenarios
3. Include cleanup logic for any new data types
4. Test the cleanup process thoroughly
5. Update this README with examples

## Support

For issues or questions:
1. Check test logs in `data/test_results_*.json`
2. Review error messages in console output
3. Verify SuiteCRM permissions and configuration
4. Ensure database connectivity