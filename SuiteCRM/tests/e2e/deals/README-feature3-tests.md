# Feature 3: Personal Due-Diligence Checklists - E2E Tests

This directory contains end-to-end tests for Feature 3 of the MakeDealCRM platform, which validates the Personal Due-Diligence Checklists functionality including template creation, application to deals, task generation, and progress tracking.

## Test Coverage

### Test Case 3.1: E2E Checklist Application and Task Generation
Based on the PRD requirements, this test verifies:

1. **Checklist Template Creation**: Creating reusable checklist templates
   - Template name: "E2E Financial Checklist"
   - Template items: "Review P&L" and "Verify Bank Statements"
   - Template categorization and persistence

2. **Template Application**: Applying templates to deals
   - Navigate to deal record
   - Apply template via "Apply Checklist Template" action
   - Verify template selection and application

3. **Task Auto-Generation**: Automatic task creation from checklist items
   - Verify tasks appear in Tasks subpanel
   - Verify task details match template items
   - Verify tasks are linked to the deal

4. **Progress Tracking**: Monitoring checklist completion
   - Mark individual tasks as completed
   - Verify progress updates (50% completion after 1 of 2 tasks)
   - Verify progress indicators display correctly

## Test Structure

```
deals/
├── feature3-checklist-due-diligence.spec.js  # Main test suite for Feature 3
├── helpers/
│   ├── auth.helper.js                         # Authentication utilities
│   └── navigation.helper.js                   # Navigation utilities
└── README-feature3-tests.md                   # This file
```

## Prerequisites

1. **SuiteCRM Installation**: Ensure SuiteCRM is running at `http://localhost:8080`
2. **Admin Credentials**: Default credentials are `admin/admin123`
3. **Playwright Installation**: Tests require Playwright to be installed
4. **Checklist Module**: Ensure checklist templates and checklists modules are available

## Running the Tests

### Run all Feature 3 tests:
```bash
cd /Users/andrewgauntlet/Desktop/MakeDealCRM/SuiteCRM/tests/e2e
./run-feature3-tests.sh
```

### Alternative manual run:
```bash
npx playwright test deals/feature3-checklist-due-diligence.spec.js
```

### Run with UI mode for debugging:
```bash
./run-feature3-tests.sh --ui
```

### Run specific test:
```bash
./run-feature3-tests.sh -g "Test Case 3.1"
```

### Run with different browser:
```bash
npx playwright test deals/feature3-checklist-due-diligence.spec.js --project=chromium
```

## Test Data

The tests create and manage the following test data:

### Checklist Template:
- **Name**: "E2E Financial Checklist"
- **Description**: "End-to-end test checklist for financial due diligence"
- **Category**: "Financial Due Diligence"
- **Type**: "Due Diligence"
- **Items**:
  1. **Review P&L**
     - Description: "Review profit and loss statements for the last 3 years"
     - Order: 1
     - Required: Yes
     - Due Days: 5
  2. **Verify Bank Statements**
     - Description: "Verify bank statements match reported financials"
     - Order: 2
     - Required: Yes
     - Due Days: 3

### Test Deal:
- **Name**: "E2E Diligence Deal"
- **TTM Revenue**: $5,000,000
- **TTM EBITDA**: $1,000,000
- **Asking Price**: $4,500,000
- **Target Multiple**: 4.5
- **Status**: "Due Diligence"

## Environment Variables

Customize test behavior with environment variables:

```bash
# Custom base URL
BASE_URL=http://your-suitecrm-instance.com ./run-feature3-tests.sh

# Custom credentials
SUITE_USERNAME=your_username SUITE_PASSWORD=your_password ./run-feature3-tests.sh
```

## Page Objects Used

The tests leverage the following page objects:

1. **ChecklistPage** (`../page-objects/ChecklistPage.js`)
   - Template creation and management
   - Checklist application to deals
   - Progress tracking methods

2. **DealPage** (`../page-objects/DealPage.js`)
   - Deal creation and navigation
   - Subpanel interactions
   - Deal detail view operations

## Test Scenarios

### Main Test: Test Case 3.1
**Scenario**: Create a checklist template, apply it to a deal, verify task generation, and track progress.

**Steps**:
1. Navigate to Checklist Templates module
2. Create "E2E Financial Checklist" template with 2 items
3. Save the template
4. Navigate to "E2E Diligence Deal" (create if doesn't exist)
5. Apply checklist template to deal
6. Verify checklist instance in Checklists subpanel
7. Verify auto-created tasks in Tasks subpanel
8. Mark "Review P&L" task as completed
9. Verify progress shows 50% completion

### Additional Tests

1. **Template Persistence**: Verify templates can be reused across deals
2. **Full Completion**: Test 100% completion when all tasks are done
3. **Edge Cases**: Handle validation errors and invalid scenarios

## Debugging Failed Tests

1. **Screenshots**: Failed tests automatically capture screenshots in `test-results/`
2. **Videos**: Enable video recording with `--video=on`
3. **Trace Viewer**: Run with `--trace=on` for detailed execution traces
4. **HTML Report**: View comprehensive results with `npx playwright show-report`

### Common Debugging Commands:
```bash
# Run with debug output
DEBUG=pw:api ./run-feature3-tests.sh

# Run with headed browser (visible)
./run-feature3-tests.sh --headed

# Run with slowmo for better observation
npx playwright test deals/feature3-checklist-due-diligence.spec.js --slowmo=1000
```

## Test Assertions

The test suite verifies:

1. ✅ Checklist template created with correct items
2. ✅ Template saved and appears in templates list
3. ✅ Template can be applied to deal via UI action
4. ✅ Applied template creates checklist instance
5. ✅ Checklist instance appears in Checklists subpanel
6. ✅ Tasks auto-generated in Tasks subpanel
7. ✅ Task details match template items
8. ✅ Tasks are properly linked to deal
9. ✅ Task completion updates checklist progress
10. ✅ Progress indicators show correct percentage (50%)

## Expected UI Elements

The tests expect the following UI elements to be present:

### Checklist Templates Module:
- "Create Template" button
- Template form fields (name, description, category, type)
- Checklist items section with "Add Item" functionality
- "Save Template" button

### Deal Detail View:
- "Apply Checklist Template" action/button
- Checklists subpanel
- Tasks subpanel
- Progress indicators (progress bar or percentage text)

### Task Management:
- Task detail forms with status dropdown
- "Completed" status option
- Save functionality for task updates

## Troubleshooting

### Common Issues:

1. **Template Creation Fails**:
   - Verify Checklist Templates module is installed
   - Check user permissions for template creation
   - Ensure required fields are properly filled

2. **Template Application Fails**:
   - Check if "Apply Template" action is available
   - Verify deal exists and is accessible
   - Look for JavaScript errors in browser console

3. **Tasks Not Generated**:
   - Verify task auto-generation is enabled
   - Check if Tasks module is properly configured
   - Ensure checklist-to-task relationship is working

4. **Progress Not Updating**:
   - Check if task status changes trigger checklist updates
   - Verify progress calculation logic
   - Look for AJAX/JavaScript errors

### Debugging Steps:
1. Run test with `--headed` flag to observe browser behavior
2. Check network tab for failed API calls
3. Verify SuiteCRM logs for server-side errors
4. Use `--trace=on` to capture detailed execution traces

## Maintenance

When updating tests:

1. **Module Changes**: Update selectors if Checklist UI changes
2. **Workflow Changes**: Adjust test steps if template application process changes
3. **New Features**: Add tests for new checklist functionality
4. **Data Cleanup**: Ensure test data is properly cleaned up
5. **Performance**: Monitor test execution time and optimize if needed

## Integration with CI/CD

For continuous integration:

```yaml
# Example GitHub Actions configuration
- name: Run Feature 3 E2E Tests
  run: |
    cd SuiteCRM/tests/e2e
    npm ci
    npx playwright install
    ./run-feature3-tests.sh
```

## Next Steps

After Feature 3 tests pass, proceed to:
- Feature 4: Simplified Stakeholder Tracking
- Feature 5: At-a-Glance Financial & Valuation Hub
- Integration tests combining multiple features
- Performance and load testing

## Test Data Cleanup

The tests include a cleanup method (marked as `test.skip`) that can be run manually:

```bash
# Enable cleanup test
npx playwright test deals/feature3-checklist-due-diligence.spec.js -g "Cleanup test data"
```

This will remove:
- Test checklist template "E2E Financial Checklist"
- Test deal "E2E Diligence Deal"
- Associated tasks and checklist instances

## Support

For issues with Feature 3 tests:
1. Check this README for troubleshooting steps
2. Review test output and screenshots
3. Verify SuiteCRM checklist functionality manually
4. Check SuiteCRM logs for server-side errors