# Duplicate Detection Test Suite for Deals Module

## Overview

This comprehensive test suite validates the duplicate detection functionality in the Deals module, ensuring robust handling of various scenarios including fuzzy matching, edge cases, SQL injection prevention, and performance optimization.

## Test Files

### 1. `DuplicateDetectionTest.php`
Main unit test file covering:
- **Fuzzy Name Matching**: Tests phonetic and similarity-based matching
- **Domain Extraction**: Validates company name normalization (Inc, LLC, Corp removal)
- **Name Normalization**: Tests handling of different name formats
- **Amount Range Matching**: Validates 10% range matching for deal amounts
- **Email Matching**: Tests duplicate detection by email address
- **SQL Injection Prevention**: Comprehensive security testing
- **Performance Testing**: Validates performance with large datasets
- **Edge Cases**: Special characters, whitespace, Unicode, etc.

### 2. `CheckDuplicatesIntegrationTest.php`
Integration test for the AJAX action:
- **Complete AJAX Workflow**: Tests end-to-end duplicate checking
- **Response Formatting**: Validates JSON response structure
- **User Assignment**: Tests proper user name formatting
- **Error Handling**: Tests malformed input handling
- **Bulk Data Performance**: Tests with realistic data volumes

### 3. `fixtures/DuplicateDetectionFixtures.php`
Test data fixtures providing:
- Company name variations (Acme Corp, Acme Corporation, etc.)
- Edge case company names (special characters, Unicode)
- SQL injection test strings
- Bulk data generation for performance testing
- Test email addresses with various formats

## Running the Tests

### Quick Start
```bash
# Run all duplicate detection tests
./tests/unit/phpunit/modules/Deals/run-duplicate-detection-tests.sh

# Run with verbose output
./tests/unit/phpunit/modules/Deals/run-duplicate-detection-tests.sh --verbose
```

### Using PHPUnit Directly
```bash
# Run all tests
vendor/bin/phpunit -c tests/unit/phpunit/modules/Deals/phpunit-duplicate-detection.xml

# Run specific test class
vendor/bin/phpunit tests/unit/phpunit/modules/Deals/DuplicateDetectionTest.php

# Run specific test method
vendor/bin/phpunit --filter testFuzzyNameMatching tests/unit/phpunit/modules/Deals/DuplicateDetectionTest.php
```

## Test Coverage

The test suite provides comprehensive coverage of:

### Core Functionality
- ✅ Fuzzy name matching with similar_text() and SOUNDEX
- ✅ Account name normalization and variation matching
- ✅ Amount range matching (±10% tolerance)
- ✅ Email-based duplicate detection
- ✅ Multi-criteria scoring algorithm
- ✅ Current record exclusion

### Security
- ✅ SQL injection prevention in all input fields
- ✅ XSS prevention in responses
- ✅ Safe handling of malformed input
- ✅ Database integrity protection

### Edge Cases
- ✅ Special characters (apostrophes, ampersands, etc.)
- ✅ Unicode and international characters
- ✅ Whitespace normalization
- ✅ Empty/null value handling
- ✅ Very long input strings
- ✅ Case-insensitive matching

### Performance
- ✅ Efficient query execution with large datasets
- ✅ Result limiting (max 10 duplicates)
- ✅ Optimized scoring algorithm
- ✅ Sub-second response times

## Test Scenarios

### 1. Exact Duplicate Detection
Tests when all fields match exactly:
```php
- Name: "Enterprise Software License Q4 2024"
- Account: "Acme Corporation"
- Amount: $250,000
- Email: sales@acme.com
Expected: 100% match score
```

### 2. Fuzzy Duplicate Detection
Tests variations and abbreviations:
```php
- "Acme Corp" vs "Acme Corporation"
- "Ent Software Lic" vs "Enterprise Software License"
- $245,000 vs $250,000 (within 10%)
Expected: 70-90% match score
```

### 3. Security Testing
Tests malicious input handling:
```php
- SQL injection: "'; DROP TABLE deals; --"
- XSS attempts: "<script>alert('XSS')</script>"
- Path traversal: "../../../etc/passwd"
Expected: Safe handling, no security breaches
```

### 4. Performance Testing
Tests with bulk data:
```php
- 1000+ deal records
- Complex matching criteria
- Multiple concurrent checks
Expected: < 1 second response time
```

## Scoring Algorithm

The duplicate detection uses a weighted scoring system:

| Criteria | Weight | Description |
|----------|--------|-------------|
| Name Match | 40% | Similar text comparison + SOUNDEX |
| Account Match | 30% | Exact or normalized match |
| Amount Match | 20% | Within 10% range |
| Email Match | 10% | Exact email match |

Only duplicates with >50% score are returned.

## Database Schema Requirements

The tests expect the following tables:
- `deals` - Main deals table
- `accounts` - Company accounts
- `users` - System users
- `email_addresses` - Email storage
- `email_addr_bean_rel` - Email relationships

## Continuous Integration

The test suite is designed for CI/CD integration:
- JUnit XML output for test results
- Code coverage reports in HTML format
- Test execution logs
- Performance metrics

## Troubleshooting

### Common Issues

1. **Database Connection Errors**
   - Ensure test database is configured
   - Check database permissions

2. **Test Data Cleanup**
   - Tests clean up after themselves
   - Manual cleanup: DELETE FROM deals WHERE id LIKE 'test-%'

3. **Performance Test Failures**
   - Increase PHP memory_limit if needed
   - Check database indexes on deals table

## Contributing

When adding new tests:
1. Follow existing naming conventions
2. Include both positive and negative test cases
3. Add edge cases for new functionality
4. Update fixtures as needed
5. Ensure tests are isolated and repeatable

## Test Metrics

Current test suite statistics:
- **Total Tests**: 15+
- **Total Assertions**: 100+
- **Code Coverage**: Target 80%+
- **Execution Time**: < 30 seconds