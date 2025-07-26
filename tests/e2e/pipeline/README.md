# Pipeline Drag-and-Drop E2E Tests

This directory contains end-to-end tests for the Pipeline drag-and-drop functionality, specifically implementing Test Case 2.1 from the E2E Testing PRD.

## Test Files

1. **pipeline-drag-drop-feature2.spec.ts** - Main test implementation for Feature 2 (Test Case 2.1)
2. **pipeline-drag-drop-enhanced.spec.ts** - Enhanced version with multiple drag approaches and edge cases
3. **drag-drop-helpers.ts** - Utility functions for reliable drag-and-drop operations
4. **pipeline-drag-drop.spec.ts** - Original comprehensive test suite (existing)

## Running the Tests

### Prerequisites

1. Ensure the application is running on http://localhost:8080
2. Have admin credentials (username: admin, password: admin)
3. Install Playwright dependencies:
   ```bash
   npm install @playwright/test
   npx playwright install
   ```

### Run All Pipeline Tests

```bash
npx playwright test tests/e2e/pipeline/
```

### Run Specific Test File

```bash
# Run the main Feature 2 test
npx playwright test tests/e2e/pipeline/pipeline-drag-drop-feature2.spec.ts

# Run the enhanced test suite
npx playwright test tests/e2e/pipeline/pipeline-drag-drop-enhanced.spec.ts
```

### Run with Different Options

```bash
# Run in headed mode (see the browser)
npx playwright test tests/e2e/pipeline/ --headed

# Run only in Chrome
npx playwright test tests/e2e/pipeline/ --project=chromium

# Run with debug mode
npx playwright test tests/e2e/pipeline/ --debug

# Generate HTML report
npx playwright test tests/e2e/pipeline/ --reporter=html
```

### Run Mobile Tests Only

```bash
npx playwright test tests/e2e/pipeline/ --grep "Mobile"
```

## Test Coverage

### Feature 2 Test Case 2.1 Implementation

The tests cover all requirements from the PRD:

1. ✅ Navigate to pipeline view
2. ✅ Locate test deal in "Screening" stage
3. ✅ Drag deal from "Screening" to "Analysis & Outreach"
4. ✅ Verify immediate UI update
5. ✅ Refresh page and verify persistence
6. ✅ Navigate to deal detail view
7. ✅ Verify stage field shows updated value
8. ✅ Check audit log for stage change entry

### Additional Test Coverage

- **Visual Feedback**: Verifies dragging states and drop zone highlights
- **Invalid Transitions**: Tests restrictions on stage movements
- **WIP Limits**: Handles Work-In-Progress limit warnings
- **Mobile Support**: Touch-based interactions for tablets and phones
- **Performance**: Tests rapid successive drag operations
- **Error Handling**: Graceful handling of failed moves

## Troubleshooting

### Common Issues

1. **Drag-and-drop not working**
   - The enhanced tests include multiple drag approaches (realistic mouse movements and HTML5 events)
   - If one method fails, the tests will try alternative approaches

2. **Elements not found**
   - Ensure the test deal exists by running the test setup
   - Check that the pipeline view is accessible at `/index.php?module=Deals&action=pipeline`

3. **Authentication issues**
   - Verify admin credentials are correct
   - Check that the login form fields match: `#user_name` and `#username_password`

4. **Timing issues**
   - The tests include proper waits for AJAX operations
   - Increase timeouts if running on slower systems

### Debug Mode

To troubleshoot failing tests:

```bash
# Run with trace enabled
npx playwright test tests/e2e/pipeline/pipeline-drag-drop-feature2.spec.ts --trace on

# View the trace
npx playwright show-trace trace.zip
```

## Test Data

The tests create and use the following test data:

- **Primary Test Deal**: "E2E Test Deal"
  - TTM Revenue: $1,000,000
  - TTM EBITDA: $250,000
  - Initial Stage: Screening

- **Secondary Test Deal**: "E2E Secondary Deal"
  - TTM Revenue: $750,000
  - TTM EBITDA: $200,000
  - Initial Stage: Screening

## CI/CD Integration

To run these tests in CI:

```yaml
# Example GitHub Actions workflow
- name: Run E2E Tests
  run: |
    npm ci
    npx playwright install --with-deps
    npx playwright test tests/e2e/pipeline/
  env:
    BASE_URL: http://localhost:8080
```

## Notes

- The drag-and-drop implementation is based on the code in `custom/modules/Deals/js/pipeline.js`
- Tests handle both desktop and mobile interactions
- Multiple drag approaches ensure compatibility across different browsers
- Tests clean up after themselves but may leave test deals in the system