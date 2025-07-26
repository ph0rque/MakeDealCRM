# Enhanced E2E Test Assertions

This document describes the comprehensive assertion system added to the E2E test suite, providing enhanced verification capabilities for UI updates, data persistence, audit logs, and visual consistency.

## 🚀 Features

### 1. Enhanced Assertions Helper (`lib/helpers/assertions.helper.js`)

Extended the existing assertions helper with comprehensive test utilities:

#### UI State Change Assertions
- `assertVisibilityChange(selector, expectedVisible, options)` - Verify element visibility changes
- `assertTextUpdate(selector, expectedText, options)` - Assert text content updates
- `assertFormState(formSelector, expectedState, options)` - Verify form states (enabled/disabled, validation)
- `assertLoadingState(containerSelector, shouldBeLoading, options)` - Assert loading spinners
- `assertModalState(shouldBeOpen, options)` - Verify modal/dialog states
- `assertDragDropState(dragSelector, dropSelector, options)` - Assert drag-drop readiness

#### Data Persistence Verification
- `assertDataPersistence(table, conditions, options)` - Verify data exists in database
- `assertRelationshipIntegrity(parentTable, childTable, relationshipField, parentId, options)` - Check relationship integrity
- `assertRecordFields(table, recordId, expectedFields, options)` - Verify specific field values

#### Audit Log Verification
- `assertAuditLogEntry(module, recordId, action, options)` - Verify audit log entries
- `assertActivityTimelineEntry(module, recordId, activityType, options)` - Check activity timeline
- `assertChangeHistory(recordId, fieldName, oldValue, newValue, options)` - Verify change tracking

#### Performance Assertions
- `assertPageLoadPerformance(options)` - Verify page load times
- `assertApiResponsePerformance(apiEndpoint, options)` - Check API response times
- `assertDomPerformance(operation, options)` - Measure DOM operations
- `assertMemoryUsage(options)` - Check memory consumption

### 2. Visual Regression Testing (`lib/helpers/visual-regression.helper.js`)

Comprehensive visual testing utilities:

#### Screenshot Comparison
- `assertPageScreenshot(testName, options)` - Full page screenshots
- `assertElementScreenshot(selector, testName, options)` - Element-specific screenshots
- `assertElementsConsistency(selectors, testName, options)` - Compare multiple elements

#### Cross-Browser Testing
- `assertCrossBrowserConsistency(testName, viewports, options)` - Test across viewports
- `assertResponsiveDesign(testName, breakpoints, options)` - Responsive design verification

#### Component State Testing
- `assertComponentStates(componentSelector, states, testName, options)` - Test UI component states
- `assertFormVisualStates(formSelector, testName, options)` - Form visual states
- `assertTableVisualConsistency(tableSelector, testName, options)` - Table/list consistency

#### Advanced Visual Testing
- `assertLoadingStateVisuals(containerSelector, triggerAction, testName, options)` - Loading state visuals
- `assertBeforeAfterComparison(testName, beforeAction, afterAction, options)` - Before/after comparisons

### 3. Custom Playwright Matchers (`lib/helpers/custom-matchers.js`)

Custom assertion matchers that integrate with Playwright's expect:

#### Database Matchers
```javascript
await expect(page).toHavePersistedInDatabase('opportunities', {
  id: dealId,
  name: dealName
}, {
  expectedFields: {
    status: 'active',
    amount: '1000000'
  }
});
```

#### Audit Log Matchers
```javascript
await expect(page).toHaveCorrectAuditLog('Deals', dealId, 'create', {
  fieldChanges: {
    status: { after: 'active' }
  }
});
```

#### UI Update Matchers
```javascript
await expect(element).toShowUIUpdate({
  text: 'Expected Text',
  visible: true,
  enabled: true
});
```

#### Visual Consistency Matchers
```javascript
await expect(page).toMaintainVisualConsistency('baseline-name', {
  threshold: 0.05
});
```

#### Performance Matchers
```javascript
await expect(page).toMeetPerformanceThresholds({
  loadTime: 3000,
  domContentLoaded: 2000
});
```

#### Form Validation Matchers
```javascript
await expect(form).toHaveValidationState({
  valid: false,
  fields: {
    email: { required: true, error: true }
  }
});
```

#### Drag-Drop Matchers
```javascript
await expect(dropZone).toHaveCompletedDragDrop({
  containsElement: '[data-id="item1"]',
  childCount: 3
});
```

## 📝 Usage Examples

### Basic Enhanced Assertions

```javascript
const { test } = require('@playwright/test');
const { expect } = require('../lib/helpers/custom-matchers');
const AssertionsHelper = require('../lib/helpers/assertions.helper');
const VisualRegressionHelper = require('../lib/helpers/visual-regression.helper');

test('Enhanced deal creation test', async ({ page }) => {
  const assertionsHelper = new AssertionsHelper(page);
  const visualHelper = new VisualRegressionHelper(page);
  
  // UI state assertions
  await assertionsHelper.assertVisibilityChange('h2:has-text("Create Deal")', true);
  
  // Form state verification
  await assertionsHelper.assertFormState('form', {
    enabled: true,
    fields: {
      name: { required: true }
    }
  });
  
  // Database persistence
  await expect(page).toHavePersistedInDatabase('opportunities', {
    name: 'Test Deal'
  });
  
  // Audit log verification
  await expect(page).toHaveCorrectAuditLog('Deals', dealId, 'create');
  
  // Visual regression
  await visualHelper.assertElementScreenshot('form', 'deal-creation-form');
});
```

### Complex Integration Testing

```javascript
test('Pipeline drag-drop with comprehensive verification', async ({ page }) => {
  const assertionsHelper = new AssertionsHelper(page);
  const visualHelper = new VisualRegressionHelper(page);
  
  // Performance monitoring
  await expect(page).toMeetPerformanceThresholds({
    loadTime: 5000
  });
  
  // Drag-drop operation
  await dealCard.dragTo(targetStage);
  
  // Verify UI update
  await expect(targetStage).toHaveCompletedDragDrop({
    containsElement: `[data-deal-id="${dealId}"]`
  });
  
  // Database persistence
  await expect(page).toHavePersistedInDatabase('opportunities', {
    id: dealId
  }, {
    expectedFields: {
      sales_stage: 'new-stage'
    }
  });
  
  // Audit trail
  await expect(page).toHaveCorrectAuditLog('Deals', dealId, 'update', {
    fieldChanges: {
      sales_stage: { before: 'old-stage', after: 'new-stage' }
    }
  });
  
  // Visual consistency
  await visualHelper.assertElementScreenshot('.pipeline', 'pipeline-after-drag');
});
```

## 🔧 Configuration

### Database Configuration
Set environment variables for database connection:
```bash
DB_HOST=localhost
DB_USER=suitecrm
DB_PASSWORD=suitecrm123
DB_NAME=suitecrm
DB_PORT=3306
```

### Visual Testing Configuration
Configure visual regression settings:
```javascript
const visualHelper = new VisualRegressionHelper(page, {
  baselineDir: './test-results/visual-baselines',
  threshold: 0.05,
  screenshotOptions: {
    animations: 'disabled',
    fullPage: false
  }
});
```

### Performance Thresholds
Customize performance expectations:
```javascript
const assertionsHelper = new AssertionsHelper(page);
assertionsHelper.performanceThresholds = {
  pageLoad: 3000,
  apiResponse: 1500,
  domReady: 2000,
  firstPaint: 1000
};
```

## 📊 Test Coverage

The enhanced assertions cover:

### UI Testing
- ✅ Element visibility changes
- ✅ Text content updates
- ✅ Form state transitions
- ✅ Loading states
- ✅ Modal/dialog interactions
- ✅ Drag-drop operations

### Data Integrity
- ✅ Database persistence
- ✅ Relationship integrity
- ✅ Field value verification
- ✅ Transaction consistency

### Audit & Compliance
- ✅ Audit log entries
- ✅ Activity timeline tracking
- ✅ Change history
- ✅ User action tracking

### Performance
- ✅ Page load times
- ✅ API response times
- ✅ DOM manipulation performance
- ✅ Memory usage monitoring

### Visual Consistency
- ✅ Screenshot comparison
- ✅ Cross-browser testing
- ✅ Responsive design
- ✅ Component state visuals

## 🚀 Running Enhanced Tests

### Individual Test Files
```bash
# Run with enhanced assertions
npm run test -- deals/enhanced-integration.spec.js

# Run with visual regression
npm run test -- deals/enhanced-integration.spec.js --update-snapshots

# Run with performance monitoring
npm run test -- deals/enhanced-integration.spec.js --trace=on
```

### Test Suites
```bash
# Run all enhanced tests
npm run test -- --grep "Enhanced"

# Run visual regression tests
npm run test -- --grep "Visual Regression"

# Run integration tests
npm run test -- --grep "Integration"
```

## 📈 Benefits

### 1. Comprehensive Coverage
- Tests UI, data, audit logs, and visuals in one suite
- Catches regressions across multiple layers
- Ensures end-to-end consistency

### 2. Improved Reliability
- Database verification prevents data corruption
- Audit log checking ensures compliance
- Performance monitoring catches slowdowns

### 3. Better Debugging
- Detailed error messages with context
- Visual screenshots for UI issues
- Performance metrics for optimization

### 4. Maintenance Efficiency
- Reusable assertion helpers
- Consistent testing patterns
- Centralized configuration

### 5. Quality Assurance
- Cross-browser consistency
- Performance benchmarks
- Comprehensive audit trails

## 🔍 Troubleshooting

### Database Connection Issues
```javascript
// Check database configuration
node -e "console.log(require('mysql2').createConnection({
  host: process.env.DB_HOST || 'localhost',
  user: process.env.DB_USER || 'suitecrm'
}).connect())"
```

### Visual Test Failures
```bash
# Update baselines if UI changes are intentional
npm run test -- --update-snapshots

# Compare failed screenshots
open test-results/visual-diffs/
```

### Performance Test Failures
```javascript
// Adjust thresholds for slower environments
await expect(page).toMeetPerformanceThresholds({
  loadTime: 10000, // Increased threshold
  domContentLoaded: 5000
});
```

This enhanced assertion system provides comprehensive testing coverage for the SuiteCRM E2E test suite, ensuring reliability, performance, and visual consistency across all test scenarios.