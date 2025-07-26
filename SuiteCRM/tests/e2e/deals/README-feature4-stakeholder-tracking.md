# Feature 4: Simplified Stakeholder Tracking - E2E Tests

This directory contains end-to-end tests for Feature 4 of the MakeDealCRM platform, which enables simplified stakeholder tracking through role assignment and verification.

## Test Coverage

### Test Case 4.1: E2E Stakeholder Role Assignment and Verification
Based on the PRD requirements, this test suite verifies:

1. **Stakeholder Role Assignment**: Creating contacts with specific roles within deal context
   - Navigation to target deal ("E2E Stakeholder Deal")
   - Creating new contact from Contacts subpanel
   - Setting Contact Role to "Lender" 
   - Saving contact with role information

2. **Role Verification**: Ensuring roles are correctly displayed and persisted
   - Verifying contact appears in Contacts subpanel with correct role
   - Navigating to contact detail view
   - Verifying Contact Role field shows "Lender"
   - Data persistence across page refreshes

3. **Relationship Management**: Bidirectional relationship verification
   - Contact-to-Deal relationships
   - Deal-to-Contact relationships
   - Multiple stakeholders with different roles

4. **Data Integrity**: Ensuring stakeholder data remains consistent
   - Role information persistence
   - Relationship integrity
   - Error handling and validation

## Test Structure

```
deals/
├── feature4-stakeholder-tracking.spec.js    # Main test suite for Feature 4
├── helpers/
│   ├── auth.helper.js                       # Authentication utilities
│   └── navigation.helper.js                 # Navigation utilities
└── README-feature4-stakeholder-tracking.md  # This file
```

## Test Scenarios

### Primary Test Suite: Feature 4: Simplified Stakeholder Tracking

1. **Test Case 4.1: E2E Stakeholder Role Assignment and Verification**
   - Creates test deal "E2E Stakeholder Deal"
   - Adds contact "Jane Lender" with "Lender" role
   - Verifies role assignment and display
   - Confirms bidirectional relationships

2. **Stakeholder Relationship Persistence**
   - Verifies relationships survive page refreshes
   - Tests reverse relationship navigation
   - Confirms data integrity across modules

3. **Multiple Stakeholders with Different Roles**
   - Adds multiple contacts with different roles
   - Verifies role differentiation
   - Tests role-based organization

4. **Role Assignment Validation and Error Handling**
   - Tests required field validation
   - Handles missing or invalid data
   - Verifies error messages and recovery

### Advanced Test Suite: Feature 4: Advanced Stakeholder Management

1. **Role-based Filtering** (Implementation dependent)
2. **Stakeholder Role History** (Audit trail dependent)
3. **Bulk Stakeholder Operations** (References existing stakeholder-bulk.spec.js)

## Prerequisites

1. **SuiteCRM Installation**: Ensure SuiteCRM is running at `http://localhost:8080`
2. **Admin Credentials**: Default credentials are `admin/admin123`
3. **Playwright Installation**: Tests require Playwright to be installed
4. **Page Objects**: Tests use DealPage and ContactPage objects

## Running the Tests

### Quick Start - Run Feature 4 tests:
```bash
cd /Users/andrewgauntlet/Desktop/MakeDealCRM/SuiteCRM/tests/e2e
./run-feature4-tests.sh
```

### Manual Execution:
```bash
# Run all Feature 4 tests
npx playwright test deals/feature4-stakeholder-tracking.spec.js

# Run with UI mode for debugging
npx playwright test deals/feature4-stakeholder-tracking.spec.js --ui

# Run specific test case
npx playwright test deals/feature4-stakeholder-tracking.spec.js -g "Test Case 4.1"

# Run with specific browser
npx playwright test deals/feature4-stakeholder-tracking.spec.js --project=chromium
```

### Test Configuration Options:
```bash
# Custom base URL
BASE_URL=http://your-suitecrm-instance.com npx playwright test deals/feature4-stakeholder-tracking.spec.js

# Custom credentials
SUITE_USERNAME=your_username SUITE_PASSWORD=your_password npx playwright test deals/feature4-stakeholder-tracking.spec.js

# Enable detailed logging
DEBUG=pw:api npx playwright test deals/feature4-stakeholder-tracking.spec.js
```

## Test Data

The tests create and use the following test data:

### Test Deal
- **Name**: "E2E Stakeholder Deal"
- **Status**: Sourcing
- **Deal Value**: $5,000,000
- **Description**: Test deal for stakeholder role assignment verification

### Primary Test Contact
- **Name**: Jane Lender
- **Role**: Lender
- **Email**: jane.lender@stakeholdertest.com
- **Phone**: 555-987-6543
- **Title**: Senior Loan Officer

### Secondary Test Contact (Multi-stakeholder test)
- **Name**: Bob Buyer
- **Role**: Buyer
- **Email**: bob.buyer@stakeholdertest.com
- **Phone**: 555-123-9876

## Page Objects Used

### DealPage (page-objects/DealPage.js)
- `goto()` - Navigate to Deals module
- `searchDeals(searchTerm)` - Search for specific deals
- `openDeal(dealName)` - Open deal detail view
- `createDeal(dealData)` - Create new deal
- `getDealTitle()` - Get deal title from detail view

### ContactPage (page-objects/ContactPage.js)
- `goto()` - Navigate to Contacts module
- `searchContacts(searchTerm)` - Search for contacts
- `openContact(contactName)` - Open contact detail view
- `createContact(contactData)` - Create new contact
- `getContactRole()` - Get contact role value

## Debugging Failed Tests

### Automatic Debug Information
1. **Screenshots**: Failed tests automatically capture screenshots in `test-results/`
2. **Videos**: Enable with `--video=on`
3. **Trace Viewer**: Enable with `--trace=on`

### Manual Debugging Steps
```bash
# View test reports
npx playwright show-report

# Run in debug mode
npx playwright test --debug deals/feature4-stakeholder-tracking.spec.js

# Run single test with full output
npx playwright test deals/feature4-stakeholder-tracking.spec.js -g "Test Case 4.1" --reporter=line
```

### Common Issues and Solutions

1. **Contact Role Field Not Found**
   - **Issue**: Role selector varies across SuiteCRM versions
   - **Solution**: Test includes multiple selector fallbacks
   - **Debug**: Check browser inspector for actual field names

2. **Subpanel Not Loading**
   - **Issue**: Contacts subpanel may load asynchronously
   - **Solution**: Tests include wait conditions and scrolling
   - **Debug**: Increase timeout values if needed

3. **Test Deal Creation Fails**
   - **Issue**: Required fields may vary by configuration
   - **Solution**: Setup function handles missing optional fields
   - **Debug**: Check SuiteCRM deal configuration

4. **Permission Issues**
   - **Issue**: User may lack permissions for contact/deal creation
   - **Solution**: Ensure admin credentials are used
   - **Debug**: Check user roles and permissions

## Environment Variables

```bash
# Core configuration
BASE_URL=http://localhost:8080          # SuiteCRM base URL
SUITE_USERNAME=admin                    # Login username
SUITE_PASSWORD=admin123                 # Login password

# Test behavior
HEADLESS=false                          # Run in headed mode for debugging
SLOW_MO=1000                           # Slow down actions for debugging
```

## Test Assertions

The test suite verifies:

1. ✅ Navigation to target deal succeeds
2. ✅ Contact creation from subpanel works
3. ✅ Contact role assignment is successful
4. ✅ Contact appears in deal's Contacts subpanel
5. ✅ Role information is displayed correctly in subpanel
6. ✅ Contact detail view navigation works
7. ✅ Contact Role field shows correct value on detail page
8. ✅ Data persists across page refreshes
9. ✅ Bidirectional relationships are maintained
10. ✅ Multiple stakeholders can have different roles
11. ✅ Validation and error handling work correctly

## Data Cleanup

### Automated Cleanup
- Tests are designed to be re-runnable
- Test data uses unique identifiers where possible
- Setup functions check for existing data

### Manual Cleanup
```bash
# Run cleanup test to remove all test data
npx playwright test deals/feature4-stakeholder-tracking.spec.js -g "Cleanup"
```

## Integration with CI/CD

### Example GitHub Actions Configuration
```yaml
- name: Run Feature 4 E2E Tests
  run: |
    cd SuiteCRM/tests/e2e
    npm ci
    npx playwright install
    ./run-feature4-tests.sh
  env:
    BASE_URL: ${{ secrets.SUITECRM_URL }}
    SUITE_USERNAME: ${{ secrets.SUITECRM_USERNAME }}
    SUITE_PASSWORD: ${{ secrets.SUITECRM_PASSWORD }}
```

### Example Jenkins Pipeline
```groovy
stage('Feature 4 E2E Tests') {
    steps {
        dir('SuiteCRM/tests/e2e') {
            sh 'npm ci'
            sh 'npx playwright install'
            sh './run-feature4-tests.sh'
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
                reportName: 'Feature 4 E2E Test Report'
            ])
        }
    }
}
```

## Troubleshooting

### Selector Issues
If tests fail due to element not found:

1. **Inspect the actual HTML** using browser dev tools
2. **Update selectors** in the test file to match current UI
3. **Check for dynamic IDs** that may change between versions
4. **Use more robust selectors** like text content or aria labels

### Timing Issues
If tests fail due to timing:

1. **Increase timeouts** in playwright.config.js
2. **Add explicit waits** for specific conditions
3. **Use networkidle** wait conditions for AJAX operations
4. **Add small delays** after form submissions

### Data Issues
If tests fail due to data problems:

1. **Run cleanup test** to remove stale data
2. **Check database** for orphaned records
3. **Verify permissions** for test user account
4. **Reset test environment** if needed

## Maintenance

When updating tests:

1. **Run tests after SuiteCRM upgrades** to catch UI changes
2. **Update selectors** if elements change
3. **Add new assertions** for new features
4. **Keep test data current** with business requirements
5. **Monitor test execution times** and optimize as needed

## Reporting Issues

When reporting test failures:

1. **Include full error message** from test output
2. **Attach screenshots** from test-results directory
3. **Provide environment details** (SuiteCRM version, browser, OS)
4. **Include steps to reproduce** the issue
5. **Share relevant configuration** (environment variables, etc.)

## Next Steps

After Feature 4 tests pass successfully:

1. **Verify all stakeholder workflows** work as expected
2. **Test integration** with other features (deals, contacts)
3. **Run performance tests** with large numbers of stakeholders
4. **Proceed to Feature 5**: At-a-Glance Financial & Valuation Hub
5. **Consider additional stakeholder features** like bulk operations

## Related Documentation

- [Feature 1: Deal as Central Object](README-feature1-tests.md)
- [Stakeholder Bulk Operations](../stakeholder-bulk.spec.js)
- [Page Objects Documentation](../page-objects/README.md)
- [Main E2E Testing Guide](../README.md)