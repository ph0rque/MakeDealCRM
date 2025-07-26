# Feature 1: Deal as Central Object - E2E Tests

This directory contains end-to-end tests for Feature 1 of the MakeDealCRM platform, which establishes the Deal as the central object with all related entities properly linked.

## Test Coverage

### Test Case 1.1: E2E Deal Creation and Data Association
Based on the PRD requirements, this test verifies:

1. **Deal Creation**: Creating a new deal with all required financial fields
   - Deal name
   - TTM Revenue
   - TTM EBITDA
   - Asking Price
   - Target Multiple

2. **Contact Association**: Adding contacts to the deal
   - Creating new contact from Deal's Contacts subpanel
   - Setting contact role (e.g., "Seller")
   - Verifying bidirectional relationship

3. **Document Management**: Attaching documents to the deal
   - Uploading documents from Deal's Documents subpanel
   - Document categorization
   - File upload handling

4. **Data Persistence**: Ensuring all data is saved correctly
   - Deal details persist after page refresh
   - Related entities remain associated
   - Financial calculations are preserved

## Test Structure

```
deals/
├── feature1-deal-central-object.spec.js  # Main test suite for Feature 1
├── helpers/
│   ├── auth.helper.js                    # Authentication utilities
│   └── navigation.helper.js              # Navigation utilities
└── README-feature1-tests.md              # This file
```

## Prerequisites

1. **SuiteCRM Installation**: Ensure SuiteCRM is running at `http://localhost:8080`
2. **Admin Credentials**: Default credentials are `admin/admin123`
3. **Playwright Installation**: Tests require Playwright to be installed

## Running the Tests

### Run all Feature 1 tests:
```bash
cd /Users/andrewgauntlet/Desktop/MakeDealCRM/SuiteCRM/tests/e2e
npx playwright test deals/feature1-deal-central-object.spec.js
```

### Run with UI mode for debugging:
```bash
npx playwright test deals/feature1-deal-central-object.spec.js --ui
```

### Run specific test:
```bash
npx playwright test deals/feature1-deal-central-object.spec.js -g "Test Case 1.1"
```

### Run with specific browser:
```bash
npx playwright test deals/feature1-deal-central-object.spec.js --project=chromium
```

## Test Data

The tests create the following test data:

- **Deal**: "Test Manufacturing Co [timestamp]"
  - TTM Revenue: $10,000,000
  - TTM EBITDA: $2,000,000
  - Asking Price: $9,000,000
  - Target Multiple: 4.5

- **Contact**: John Seller
  - Role: Seller
  - Email: john.seller@testmanufacturing.com
  - Phone: 555-123-4567

- **Document**: NDA.pdf
  - Type: Legal Documents
  - Description: Non-Disclosure Agreement

## Environment Variables

You can customize test behavior with environment variables:

```bash
# Custom base URL
BASE_URL=http://your-suitecrm-instance.com npx playwright test

# Custom credentials
SUITE_USERNAME=your_username SUITE_PASSWORD=your_password npx playwright test
```

## Debugging Failed Tests

1. **Screenshots**: Failed tests automatically capture screenshots in `test-results/`
2. **Videos**: Enable video recording by running with `--video=on`
3. **Trace Viewer**: Run with `--trace=on` to capture detailed execution traces

### View test reports:
```bash
npx playwright show-report
```

## Test Assertions

The test suite verifies:

1. ✅ Deal is created with all required fields
2. ✅ System navigates to deal detail view after creation
3. ✅ Contact can be added from Contacts subpanel
4. ✅ Contact appears in subpanel with correct role
5. ✅ Document can be uploaded from Documents subpanel
6. ✅ Document appears in subpanel after upload
7. ✅ All data persists after page refresh
8. ✅ Relationships are bidirectional (contact shows deal, deal shows contact)
9. ✅ Financial calculations are correct (EBITDA × Multiple = Valuation)

## Edge Cases Covered

- Missing required fields validation
- Concurrent editing scenarios
- Large file uploads
- Special characters in names
- Multiple related entities

## Troubleshooting

### Common Issues:

1. **Login Fails**: Verify credentials and that SuiteCRM is accessible
2. **Elements Not Found**: Check if UI has changed, update selectors
3. **Timeouts**: Increase timeout values in slow environments
4. **File Upload Issues**: Ensure test has write permissions for temp files

### Debug Mode:
```bash
# Run with debug output
DEBUG=pw:api npx playwright test deals/feature1-deal-central-object.spec.js
```

## Maintenance

When updating tests:

1. Run tests after any SuiteCRM UI changes
2. Update selectors if elements change
3. Add new assertions for new features
4. Keep test data unique using timestamps
5. Clean up test data in teardown (optional)

## CI/CD Integration

For continuous integration:

```yaml
# Example GitHub Actions configuration
- name: Run Feature 1 E2E Tests
  run: |
    cd SuiteCRM/tests/e2e
    npm ci
    npx playwright install
    npx playwright test deals/feature1-deal-central-object.spec.js
```

## Next Steps

After Feature 1 tests pass, proceed to:
- Feature 2: Unified Deal & Portfolio Pipeline
- Feature 3: Personal Due-Diligence Checklists
- Feature 4: Simplified Stakeholder Tracking
- Feature 5: At-a-Glance Financial & Valuation Hub