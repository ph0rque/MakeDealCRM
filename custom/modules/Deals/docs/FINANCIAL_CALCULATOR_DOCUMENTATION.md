# Financial Calculator Documentation

## Overview

The `FinancialCalculator` class is a centralized service for all financial calculations in the Deals module. It consolidates valuation, revenue multiples, EBITDA calculations, and other financial metrics into a single, well-organized class.

## Location

```
/custom/modules/Deals/services/FinancialCalculator.php
```

## Key Features

- **Centralized Calculations**: All financial formulas in one place
- **Consistent Formatting**: Built-in formatting methods for currency, percentages, and multiples
- **Configurable Defaults**: Default values for common parameters
- **Comprehensive Metrics**: Supports all major financial metrics used in deal evaluation

## Available Methods

### Revenue Calculations

#### `calculateTTMRevenue($monthlyRevenue, $annualRevenue)`
Calculates Trailing Twelve Months (TTM) revenue from monthly data or annual fallback.

**Parameters:**
- `$monthlyRevenue` (array): Array of monthly revenue values
- `$annualRevenue` (float): Annual revenue fallback value

**Returns:** float - TTM Revenue

### EBITDA Calculations

#### `calculateTTMEBITDA($ttmRevenue, $operatingExpenses, $addBacks)`
Calculates TTM EBITDA (Earnings Before Interest, Taxes, Depreciation, and Amortization).

**Parameters:**
- `$ttmRevenue` (float): TTM Revenue
- `$operatingExpenses` (float|null): Operating expenses (defaults to 70% of revenue)
- `$addBacks` (float): Add-back expenses

**Returns:** float - TTM EBITDA

#### `calculateEBITDAMargin($ebitda, $revenue)`
Calculates EBITDA margin as a percentage.

**Parameters:**
- `$ebitda` (float): EBITDA value
- `$revenue` (float): Revenue value

**Returns:** float - EBITDA margin percentage

### SDE Calculations

#### `calculateSDE($ebitda, $ownerCompensation, $ownerBenefits, $nonEssentialExpenses)`
Calculates Seller's Discretionary Earnings.

**Parameters:**
- `$ebitda` (float): EBITDA value
- `$ownerCompensation` (float): Owner's compensation
- `$ownerBenefits` (float): Owner's benefits
- `$nonEssentialExpenses` (float): Non-essential expenses

**Returns:** float - SDE

### Valuation Calculations

#### `calculateProposedValuation($financialMetric, $multiple, $method)`
Calculates proposed business valuation.

**Parameters:**
- `$financialMetric` (float): EBITDA or SDE value
- `$multiple` (float|null): Target multiple (defaults to 3.5)
- `$method` (string): Valuation method ('ebitda' or 'sde')

**Returns:** float - Proposed valuation

#### `calculateImpliedMultiple($askingPrice, $ebitda)`
Calculates implied multiple from asking price.

**Parameters:**
- `$askingPrice` (float): Asking price for the business
- `$ebitda` (float): EBITDA value

**Returns:** float - Implied multiple

### Debt & Investment Calculations

#### `calculateDebtServiceCoverageRatio($ebitda, $capitalExpenditures, $taxes, $totalDebtService)`
Calculates Debt Service Coverage Ratio (DSCR).

**Parameters:**
- `$ebitda` (float): EBITDA value
- `$capitalExpenditures` (float): Capital expenditures
- `$taxes` (float|null): Estimated taxes (defaults to 25% of EBITDA)
- `$totalDebtService` (float): Total debt service amount

**Returns:** float - DSCR

#### `calculateROI($annualCashFlow, $totalInvestment)`
Calculates Return on Investment.

**Parameters:**
- `$annualCashFlow` (float): Annual cash flow after normalized salary
- `$totalInvestment` (float): Total investment amount

**Returns:** float - ROI percentage

#### `calculatePaybackPeriod($totalInvestment, $annualCashFlow)`
Calculates payback period in years.

**Parameters:**
- `$totalInvestment` (float): Total investment amount
- `$annualCashFlow` (float): Annual cash flow

**Returns:** float - Payback period in years

### Capital Structure Calculations

#### `calculateCapitalStack($totalPrice, $equityPercentage, $seniorDebtPercentage, $sellerNotePercentage)`
Calculates capital stack breakdown.

**Parameters:**
- `$totalPrice` (float): Total acquisition price
- `$equityPercentage` (float): Equity percentage (0-1)
- `$seniorDebtPercentage` (float): Senior debt percentage (0-1)
- `$sellerNotePercentage` (float): Seller note percentage (0-1)

**Returns:** array - Capital stack breakdown with amounts and percentages

### Advanced Calculations

#### `calculateBreakEvenMultiple($annualCashFlow, $ebitda, $growthRate, $holdPeriod)`
Calculates the multiple at which investment equals cash flows over hold period.

**Parameters:**
- `$annualCashFlow` (float): Annual cash flow
- `$ebitda` (float): EBITDA value
- `$growthRate` (float|null): Annual growth rate (defaults to 3%)
- `$holdPeriod` (int|null): Hold period in years (defaults to 5)

**Returns:** float - Break-even multiple

#### `calculateNPV($cashFlows, $discountRate, $initialInvestment)`
Calculates Net Present Value.

**Parameters:**
- `$cashFlows` (array): Array of cash flows by year
- `$discountRate` (float): Discount rate
- `$initialInvestment` (float): Initial investment (positive value)

**Returns:** float - NPV

#### `calculateIRR($cashFlows)`
Calculates Internal Rate of Return using Newton-Raphson method.

**Parameters:**
- `$cashFlows` (array): Array of cash flows (including initial investment as negative)

**Returns:** float - IRR percentage

### Formatting Methods

#### `formatCurrency($value, $symbol)`
Formats value as currency.

**Parameters:**
- `$value` (float): Value to format
- `$symbol` (string): Currency symbol (default: '$')

**Returns:** string - Formatted currency

#### `formatPercentage($value)`
Formats value as percentage.

**Parameters:**
- `$value` (float): Value to format

**Returns:** string - Formatted percentage

#### `formatMultiple($value)`
Formats value as multiple.

**Parameters:**
- `$value` (float): Value to format

**Returns:** string - Formatted multiple (e.g., "3.50x")

### Comprehensive Calculation

#### `calculateAllMetrics($dealData)`
Calculates all financial metrics for a deal.

**Parameters:**
- `$dealData` (array): Array containing all deal financial data

**Expected array keys:**
- `asking_price`: Deal asking price
- `annual_revenue`: Annual revenue
- `monthly_revenue`: Array of monthly revenue values
- `operating_expenses`: Operating expenses
- `add_backs`: Add-back expenses
- `owner_compensation`: Owner's compensation
- `owner_benefits`: Owner's benefits
- `non_essential_expenses`: Non-essential expenses
- `target_multiple`: Target valuation multiple
- `valuation_method`: 'ebitda' or 'sde'
- `normalized_salary`: Normalized owner salary
- `growth_rate`: Expected growth rate
- `hold_period`: Investment hold period
- `debt_structure`: Array with debt details
- `current_assets`: Current assets
- `current_liabilities`: Current liabilities
- `equity_investment`: Equity investment amount

**Returns:** array - All calculated metrics

## Usage Examples

### Basic Usage

```php
require_once('custom/modules/Deals/services/FinancialCalculator.php');
$calculator = new FinancialCalculator();

// Calculate TTM Revenue
$monthlyRevenue = array(10000, 12000, 11000, 13000, 14000, 15000, 
                       16000, 17000, 18000, 19000, 20000, 21000);
$ttmRevenue = $calculator->calculateTTMRevenue($monthlyRevenue);

// Calculate EBITDA
$operatingExpenses = 700000;
$addBacks = 50000;
$ttmEbitda = $calculator->calculateTTMEBITDA($ttmRevenue, $operatingExpenses, $addBacks);

// Calculate valuation
$targetMultiple = 4.5;
$proposedValuation = $calculator->calculateProposedValuation($ttmEbitda, $targetMultiple);

// Format results
echo "TTM Revenue: " . $calculator->formatCurrency($ttmRevenue) . "\n";
echo "TTM EBITDA: " . $calculator->formatCurrency($ttmEbitda) . "\n";
echo "Proposed Valuation: " . $calculator->formatCurrency($proposedValuation) . "\n";
```

### Integration with Deal Bean

```php
// In Deal bean save method
public function save($check_notify = false)
{
    require_once('custom/modules/Deals/services/FinancialCalculator.php');
    $calculator = new FinancialCalculator();
    
    // Calculate proposed valuation
    if (!empty($this->ttm_ebitda_c) && !empty($this->target_multiple_c)) {
        $this->proposed_valuation_c = $calculator->calculateProposedValuation(
            $this->ttm_ebitda_c,
            $this->target_multiple_c,
            'ebitda'
        );
    }
    
    return parent::save($check_notify);
}
```

### Logic Hook Implementation

```php
// In DealsLogicHooks class
public function calculateFinancialMetrics($bean, $event, $arguments)
{
    require_once('custom/modules/Deals/services/FinancialCalculator.php');
    $calculator = new FinancialCalculator();
    
    // Prepare deal data
    $dealData = array(
        'asking_price' => $bean->amount,
        'annual_revenue' => $bean->annual_revenue_c,
        // ... other fields
    );
    
    // Calculate all metrics
    $metrics = $calculator->calculateAllMetrics($dealData);
    
    // Update bean with calculated values
    if (isset($metrics['ttm_ebitda'])) {
        $bean->ttm_ebitda_calculated_c = $metrics['ttm_ebitda'];
    }
    // ... update other fields
}
```

## Default Values

The FinancialCalculator uses the following default values:

- **Tax Rate**: 25%
- **Operating Expense Ratio**: 70% of revenue
- **Working Capital Ratio**: 10% of revenue
- **Growth Rate**: 3% annual
- **Hold Period**: 5 years
- **Normalized Salary**: $50,000
- **Industry Multiple**: 3.5x EBITDA

## Testing

A comprehensive test suite is available at:
```
/custom/modules/Deals/tests/FinancialCalculatorTest.php
```

Run the test by accessing:
```
http://localhost:8080/custom/modules/Deals/tests/FinancialCalculatorTest.php
```

## Best Practices

1. **Always use the calculator for financial calculations** - Don't duplicate formulas
2. **Check for zero values** - The calculator handles division by zero gracefully
3. **Use formatting methods** - Ensure consistent display of financial data
4. **Leverage calculateAllMetrics()** - For comprehensive calculations in one call
5. **Update test suite** - When adding new calculations

## Future Enhancements

Potential additions to the FinancialCalculator:

1. **Industry-specific calculations** - Different formulas by industry type
2. **Scenario analysis** - Multiple calculation scenarios
3. **Historical trend analysis** - Year-over-year comparisons
4. **Risk scoring** - Financial health indicators
5. **Export functionality** - Generate financial reports

## Support

For questions or issues with the FinancialCalculator:

1. Check the test suite for usage examples
2. Review the inline documentation in the class
3. Ensure all required data fields are populated
4. Verify calculations match business requirements