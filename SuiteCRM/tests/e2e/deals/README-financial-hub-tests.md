# Financial Hub E2E Tests

This document describes the E2E tests for Feature 5: "At-a-Glance Financial & Valuation Hub" based on Test Case 5.1 from the PRD.

## Overview

The Financial Hub tests validate the integration between the dashboard widget and the what-if calculator, ensuring that users can:

1. View current financial metrics (TTM EBITDA, Multiple, Proposed Valuation)
2. Open the what-if calculator
3. Modify financial parameters (Multiple)
4. See instant calculations
5. Save changes and see them reflected in the main view
6. Verify persistence after page refresh

## Test Structure

### Main Test File
- `financial-hub.spec.js` - Contains the primary test case and additional test scenarios

### Helper Files
- `helpers/financial-calculation.helper.js` - Financial calculation utilities and validation functions
- `../page-objects/DealPage.js` - Enhanced with financial hub specific methods

## Test Case 5.1: Financial Hub What-if Calculator Integration

### Test Steps

1. **Setup**: Create deal "E2E Financial Deal" with:
   - TTM EBITDA: $1,000,000
   - Multiple: 4
   - Proposed Valuation: $4,000,000

2. **Open Financial Hub**: Access the dashboard widget showing financial metrics

3. **Verify Initial State**: Confirm proposed valuation displays as $4,000,000

4. **Open Calculator**: Launch the what-if calculator modal/panel

5. **Modify Multiple**: Change the multiple from 4 to 5

6. **Verify Instant Calculation**: Confirm calculator shows $5,000,000 immediately

7. **Save Changes**: Apply the new calculation to the deal

8. **Verify Main View Update**: Confirm main widget shows updated $5,000,000

9. **Test Persistence**: Refresh page and verify values persist

## Additional Test Scenarios

### Accessibility Tests
- Keyboard navigation to financial hub
- ARIA labels and screen reader compatibility
- Focus management in calculator modal

### Error Handling Tests
- Invalid multiple values (negative, zero, extremely high)
- Missing EBITDA data scenarios
- Network error handling during saves

### Performance Tests
- Calculator load time
- Calculation speed
- Large dataset handling

## Financial Calculation Helper

The `FinancialCalculationHelper` class provides:

### Core Calculations
```javascript
// Calculate valuation
const valuation = FinancialCalculationHelper.calculateValuation(ebitda, multiple);

// Calculate multiple from valuation and EBITDA
const multiple = FinancialCalculationHelper.calculateMultiple(valuation, ebitda);

// Format currency for display
const formatted = FinancialCalculationHelper.formatCurrency(4000000); // "$4,000,000"

// Parse currency strings
const parsed = FinancialCalculationHelper.parseCurrency("$4,000,000"); // 4000000
```

### Validation Functions
```javascript
// Validate EBITDA input
const ebitdaValidation = FinancialCalculationHelper.validateEbitda(1000000);

// Validate multiple input
const multipleValidation = FinancialCalculationHelper.validateMultiple(4);

// Validate calculation accuracy
const isValid = FinancialCalculationHelper.validateCalculation(1000000, 4, 4000000);
```

### Test Data Generation
```javascript
// Generate test scenarios
const scenarios = FinancialCalculationHelper.generateTestScenarios();

// Generate random financial data
const randomData = FinancialCalculationHelper.generateRandomFinancialData({
  minEbitda: 500000,
  maxEbitda: 10000000,
  minMultiple: 2,
  maxMultiple: 10
});
```

## Enhanced DealPage Methods

The DealPage object includes new methods for financial hub testing:

### Navigation Methods
- `openFinancialHub()` - Open the financial dashboard widget
- `openWhatIfCalculator()` - Launch the calculator modal

### Data Retrieval Methods
- `getProposedValuation()` - Get current proposed valuation
- `getTtmEbitda()` - Get TTM EBITDA value
- `getTargetMultiple()` - Get target multiple value
- `getCalculatorResult()` - Get calculated result from calculator

### Interaction Methods
- `updateMultipleInCalculator(newMultiple)` - Update multiple in calculator
- `saveCalculatorChanges()` - Save calculator changes

### Testing Methods
- `verifyFinancialHubAccessibility()` - Test keyboard accessibility
- `verifyCalculatorAccessibility()` - Test ARIA labels
- `testCalculatorErrorHandling(invalidValue)` - Test error scenarios
- `measureCalculatorPerformance(newMultiple)` - Measure calculation speed

## Selectors Strategy

The tests use multiple selector strategies to handle different UI implementations:

### Financial Hub Widget
```javascript
'.financial-hub-widget, .financial-dashboard, .valuation-widget'
```

### What-if Calculator
```javascript
'button:has-text("What-if Calculator"), a:has-text("What-if Calculator"), button:has-text("Calculator")'
```

### Calculator Modal
```javascript
'.what-if-calculator, .financial-calculator, .calculator-modal'
```

### Input Fields
```javascript
'input[name*="multiple"], input[placeholder*="Multiple"], .calculator input[type="number"]'
```

## Running the Tests

### Individual Test File
```bash
npm run test deals/financial-hub.spec.js
```

### With Debugging
```bash
npm run test:debug deals/financial-hub.spec.js
```

### Headed Mode (Visual)
```bash
npm run test:headed deals/financial-hub.spec.js
```

### Specific Test Case
```bash
npx playwright test --grep "Test Case 5.1"
```

## Test Data Management

### Test Deal Data
```javascript
const testDealData = {
  name: 'E2E Financial Deal',
  status: 'initial_contact',
  source: 'direct',
  deal_value: '4000000',
  ttm_revenue: '10000000',
  ttm_ebitda: '1000000',
  target_multiple: '4',
  asking_price: '4000000',
  description: 'Test deal for financial hub E2E testing'
};
```

### Cleanup Strategy
- Automatic cleanup in `afterEach` hook
- Search and delete test deals by name
- Error handling for cleanup failures

## Troubleshooting

### Common Issues

1. **Calculator not opening**: Check selector variations for what-if calculator button
2. **Values not updating**: Verify calculation triggers (Enter key, blur events)
3. **Persistence failing**: Check if save operation completes before refresh
4. **Selector failures**: UI implementation may differ from expected selectors

### Debugging Tips

1. Use `--headed` mode to see browser interactions
2. Add screenshots at key points for visual verification
3. Check browser console for JavaScript errors
4. Verify network requests during save operations

### Selector Debugging
```javascript
// Test multiple selector strategies
const calculatorButton = page.locator(
  'button:has-text("What-if Calculator"), ' +
  'a:has-text("What-if Calculator"), ' + 
  'button:has-text("Calculator")'
);

console.log(`Found ${await calculatorButton.count()} calculator buttons`);
```

## Integration with CI/CD

### Environment Variables
- `BASE_URL`: Application base URL (default: http://localhost:8080)
- `ADMIN_USERNAME`: Admin user credentials
- `ADMIN_PASSWORD`: Admin password

### Test Tags
Tests include tags for different execution contexts:
- `@financial-hub` - All financial hub tests
- `@critical` - Critical path tests
- `@accessibility` - Accessibility-specific tests
- `@performance` - Performance-related tests

### Reporting
Tests generate:
- HTML reports with screenshots
- JUnit XML for CI integration
- JSON results for processing
- Video recordings of failures

## Future Enhancements

### Planned Improvements
1. Multi-currency support testing
2. Complex deal structures with multiple valuation methods
3. Integration with external financial data sources
4. Real-time collaboration testing
5. Mobile-responsive calculator testing

### Test Coverage Expansion
1. Edge cases for very large/small numbers
2. Decimal precision testing
3. Formula validation with accounting standards
4. Integration with other CRM modules
5. Bulk calculation operations