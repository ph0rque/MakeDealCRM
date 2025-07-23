# Duplicate Detection E2E Tests

Comprehensive end-to-end tests for the duplicate detection feature in the Deals module using Playwright.

## Test Coverage

### 1. Real-time Duplicate Detection
- **Single field match**: Tests warning display when deal name matches
- **Multiple field matches**: Tests enhanced warnings for high-confidence duplicates
- **Field clearing**: Tests warning removal when fields are cleared
- **Rapid typing**: Tests debouncing to prevent excessive checks

### 2. User Actions
- **View duplicate**: Opens existing duplicate in new tab/modal
- **Merge duplicates**: Complete merge workflow with field selection
- **Proceed anyway**: Allows creating duplicate with confirmation

### 3. Visual Regression
- **Warning styles**: Screenshots for different confidence levels
- **Dark mode**: Tests appearance in dark theme
- **Responsive design**: Tests on different viewport sizes
- **Accessibility**: Proper ARIA attributes and keyboard navigation

### 4. Performance
- **Quick detection**: Verifies duplicate check completes within 2 seconds
- **Multiple duplicates**: Tests pagination with many matches
- **Debouncing**: Prevents excessive API calls during typing

## Test Files

### `duplicate-detection.spec.js`
Main test suite with all test scenarios:
- Real-time warning display
- Merge functionality
- Accessibility testing
- Visual regression tests
- Performance benchmarks

### `test-data-helper.js`
Database utilities for test data management:
- Creates test accounts, contacts, and deals
- Sets up duplicate scenarios
- Handles cleanup after tests
- Direct database access for reliable setup

### `visual-regression.config.js`
Configuration for visual testing:
- Screenshot comparison thresholds
- Viewport configurations
- Browser settings
- Baseline image management

## Running the Tests

### Prerequisites
```bash
# Install dependencies
npm install

# Install MySQL client for test data helper
npm install mysql2

# Ensure SuiteCRM is running
docker-compose up -d
```

### Run all duplicate detection tests
```bash
npm run test:e2e:duplicate-detection
```

### Run specific test
```bash
npx playwright test duplicate-detection.spec.js --grep "Real-time duplicate warning"
```

### Update visual baselines
```bash
UPDATE_BASELINES=true npx playwright test duplicate-detection.spec.js
```

### Run with UI mode for debugging
```bash
npx playwright test duplicate-detection.spec.js --ui
```

## Test Data Setup

Tests use the `DealsTestDataHelper` class to manage test data:

```javascript
const { createTestFixtures, cleanupTestFixtures } = require('./test-data-helper');

// In beforeAll
const { helper, fixtures } = await createTestFixtures();

// In afterAll
await cleanupTestFixtures(helper);
```

Test data is prefixed with `E2E_TEST_` for easy identification and cleanup.

## Visual Regression Testing

Visual tests capture screenshots of:
- Low confidence warnings (single match)
- High confidence warnings (multiple matches)
- Merge modal interface
- Confirmation dialogs
- Dark mode variants

Baselines are stored in `visual-baselines/` directory.

## Accessibility Testing

Tests verify:
- ARIA roles and labels on warnings
- Keyboard navigation through actions
- Screen reader announcements
- Focus management in modals

## Performance Benchmarks

- Duplicate check: < 2 seconds
- UI update: < 100ms after detection
- Debounce delay: 500ms
- Pagination: 5 items per page

## Environment Variables

- `DB_HOST`: Database host (default: localhost)
- `DB_USER`: Database user (default: root)
- `DB_PASSWORD`: Database password (default: root)
- `DB_NAME`: Database name (default: suitecrm)
- `UPDATE_BASELINES`: Update visual regression baselines

## Troubleshooting

### Tests fail with "element not found"
- Ensure SuiteCRM is running and accessible
- Check if UI selectors have changed
- Verify test data was created successfully

### Visual regression failures
- Review diff images in `visual-diffs/`
- Update baselines if changes are intentional
- Check for animation or dynamic content issues

### Database connection errors
- Verify database credentials
- Ensure MySQL is accessible from test environment
- Check if test user has proper permissions

## CI/CD Integration

Add to your CI pipeline:

```yaml
- name: Run E2E Tests
  run: |
    docker-compose up -d
    npm install
    npx playwright install
    npm run test:e2e:duplicate-detection
  env:
    DB_HOST: localhost
    DB_PASSWORD: ${{ secrets.DB_PASSWORD }}
```

## Contributing

When adding new tests:
1. Follow existing test patterns
2. Include visual regression where appropriate
3. Test accessibility features
4. Clean up test data properly
5. Document new scenarios in this README