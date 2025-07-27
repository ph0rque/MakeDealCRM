<?php
/**
 * Financial Calculator Service
 * 
 * Centralized service for all financial calculations in the Deals module.
 * Handles valuation, revenue multiples, EBITDA calculations, and other financial metrics.
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 * @version 1.0.0
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

class FinancialCalculator
{
    /**
     * Default configuration values
     */
    const DEFAULT_TAX_RATE = 0.25; // 25%
    const DEFAULT_OPERATING_EXPENSE_RATIO = 0.70; // 70% of revenue
    const DEFAULT_WORKING_CAPITAL_RATIO = 0.10; // 10% of revenue
    const DEFAULT_GROWTH_RATE = 0.03; // 3% annual growth
    const DEFAULT_HOLD_PERIOD = 5; // 5 years
    const DEFAULT_NORMALIZED_SALARY = 50000; // $50,000
    const DEFAULT_INDUSTRY_MULTIPLE = 3.5; // 3.5x EBITDA
    
    /**
     * Currency formatting precision
     */
    const CURRENCY_PRECISION = 2;
    const PERCENTAGE_PRECISION = 1;
    const MULTIPLE_PRECISION = 2;
    
    /**
     * Calculate TTM (Trailing Twelve Months) Revenue
     * 
     * @param array $monthlyRevenue Array of monthly revenue values (last 12 months)
     * @param float $annualRevenue Annual revenue fallback value
     * @return float TTM Revenue
     */
    public function calculateTTMRevenue($monthlyRevenue = array(), $annualRevenue = 0)
    {
        if (!empty($monthlyRevenue) && is_array($monthlyRevenue)) {
            // Sum the last 12 months of revenue
            $ttmRevenue = 0;
            $months = array_slice($monthlyRevenue, -12);
            foreach ($months as $revenue) {
                $ttmRevenue += floatval($revenue);
            }
            return $ttmRevenue;
        }
        
        // Fallback to annual revenue if monthly data not available
        return floatval($annualRevenue);
    }
    
    /**
     * Calculate TTM EBITDA (Earnings Before Interest, Taxes, Depreciation, and Amortization)
     * 
     * @param float $ttmRevenue TTM Revenue
     * @param float $operatingExpenses Operating expenses
     * @param float $addBacks Add-back expenses
     * @return float TTM EBITDA
     */
    public function calculateTTMEBITDA($ttmRevenue, $operatingExpenses = null, $addBacks = 0)
    {
        // If operating expenses not provided, use default ratio
        if ($operatingExpenses === null) {
            $operatingExpenses = $ttmRevenue * self::DEFAULT_OPERATING_EXPENSE_RATIO;
        }
        
        // EBITDA = Revenue - Operating Expenses + Add-backs
        $ebitda = $ttmRevenue - floatval($operatingExpenses) + floatval($addBacks);
        
        return $ebitda;
    }
    
    /**
     * Calculate SDE (Seller's Discretionary Earnings)
     * 
     * @param float $ebitda EBITDA value
     * @param float $ownerCompensation Owner's compensation
     * @param float $ownerBenefits Owner's benefits
     * @param float $nonEssentialExpenses Non-essential expenses
     * @return float SDE
     */
    public function calculateSDE($ebitda, $ownerCompensation = 0, $ownerBenefits = 0, $nonEssentialExpenses = 0)
    {
        // SDE = EBITDA + Owner Compensation + Owner Benefits + Non-essential Expenses
        $sde = floatval($ebitda) + 
               floatval($ownerCompensation) + 
               floatval($ownerBenefits) + 
               floatval($nonEssentialExpenses);
        
        return $sde;
    }
    
    /**
     * Calculate EBITDA Margin
     * 
     * @param float $ebitda EBITDA value
     * @param float $revenue Revenue value
     * @return float EBITDA margin as percentage
     */
    public function calculateEBITDAMargin($ebitda, $revenue)
    {
        if ($revenue <= 0) {
            return 0;
        }
        
        $margin = (floatval($ebitda) / floatval($revenue)) * 100;
        return round($margin, self::PERCENTAGE_PRECISION);
    }
    
    /**
     * Calculate Proposed Valuation
     * 
     * @param float $financialMetric EBITDA or SDE value
     * @param float $multiple Target multiple
     * @param string $method Valuation method ('ebitda' or 'sde')
     * @return float Proposed valuation
     */
    public function calculateProposedValuation($financialMetric, $multiple = null, $method = 'ebitda')
    {
        // Use default multiple if not provided
        if ($multiple === null) {
            $multiple = self::DEFAULT_INDUSTRY_MULTIPLE;
        }
        
        $valuation = floatval($financialMetric) * floatval($multiple);
        return round($valuation, self::CURRENCY_PRECISION);
    }
    
    /**
     * Calculate Implied Multiple from asking price
     * 
     * @param float $askingPrice Asking price for the business
     * @param float $ebitda EBITDA value
     * @return float Implied multiple
     */
    public function calculateImpliedMultiple($askingPrice, $ebitda)
    {
        if ($ebitda <= 0) {
            return 0;
        }
        
        $multiple = floatval($askingPrice) / floatval($ebitda);
        return round($multiple, self::MULTIPLE_PRECISION);
    }
    
    /**
     * Calculate Debt Service Coverage Ratio (DSCR)
     * 
     * @param float $ebitda EBITDA value
     * @param float $capitalExpenditures Capital expenditures
     * @param float $taxes Estimated taxes
     * @param float $totalDebtService Total debt service amount
     * @return float DSCR
     */
    public function calculateDebtServiceCoverageRatio($ebitda, $capitalExpenditures = 0, $taxes = null, $totalDebtService = 0)
    {
        // Calculate taxes if not provided
        if ($taxes === null) {
            $taxes = $ebitda * self::DEFAULT_TAX_RATE;
        }
        
        // Net Cash Flow = EBITDA - CapEx - Taxes
        $netCashFlow = floatval($ebitda) - floatval($capitalExpenditures) - floatval($taxes);
        
        if ($totalDebtService <= 0) {
            return 0;
        }
        
        $dscr = $netCashFlow / floatval($totalDebtService);
        return round($dscr, self::MULTIPLE_PRECISION);
    }
    
    /**
     * Calculate Total Debt Service
     * 
     * @param float $seniorDebt Senior debt amount
     * @param float $seniorDebtRate Interest rate for senior debt
     * @param int $seniorDebtTerm Term in years for senior debt
     * @param float $sellerNote Seller note amount
     * @param float $sellerNoteRate Interest rate for seller note
     * @param int $sellerNoteTerm Term in years for seller note
     * @return float Annual debt service
     */
    public function calculateTotalDebtService($seniorDebt = 0, $seniorDebtRate = 0.05, $seniorDebtTerm = 5,
                                             $sellerNote = 0, $sellerNoteRate = 0.06, $sellerNoteTerm = 3)
    {
        $totalDebtService = 0;
        
        // Calculate senior debt service
        if ($seniorDebt > 0 && $seniorDebtTerm > 0) {
            $totalDebtService += $this->calculateAnnualDebtPayment(
                $seniorDebt, 
                $seniorDebtRate, 
                $seniorDebtTerm
            );
        }
        
        // Calculate seller note service
        if ($sellerNote > 0 && $sellerNoteTerm > 0) {
            $totalDebtService += $this->calculateAnnualDebtPayment(
                $sellerNote, 
                $sellerNoteRate, 
                $sellerNoteTerm
            );
        }
        
        return $totalDebtService;
    }
    
    /**
     * Calculate annual debt payment using amortization formula
     * 
     * @param float $principal Principal amount
     * @param float $rate Annual interest rate
     * @param int $term Term in years
     * @return float Annual payment
     */
    private function calculateAnnualDebtPayment($principal, $rate, $term)
    {
        if ($rate == 0) {
            // No interest, simple division
            return $principal / $term;
        }
        
        // PMT = P * (r * (1 + r)^n) / ((1 + r)^n - 1)
        $payment = $principal * ($rate * pow(1 + $rate, $term)) / (pow(1 + $rate, $term) - 1);
        
        return $payment;
    }
    
    /**
     * Calculate Return on Investment (ROI)
     * 
     * @param float $annualCashFlow Annual cash flow after normalized salary
     * @param float $totalInvestment Total investment amount
     * @return float ROI as percentage
     */
    public function calculateROI($annualCashFlow, $totalInvestment)
    {
        if ($totalInvestment <= 0) {
            return 0;
        }
        
        $roi = (floatval($annualCashFlow) / floatval($totalInvestment)) * 100;
        return round($roi, self::PERCENTAGE_PRECISION);
    }
    
    /**
     * Calculate annual cash flow
     * 
     * @param float $sde SDE value
     * @param float $normalizedSalary Normalized owner salary
     * @return float Annual cash flow
     */
    public function calculateAnnualCashFlow($sde, $normalizedSalary = null)
    {
        if ($normalizedSalary === null) {
            $normalizedSalary = self::DEFAULT_NORMALIZED_SALARY;
        }
        
        return floatval($sde) - floatval($normalizedSalary);
    }
    
    /**
     * Calculate Working Capital Requirement
     * 
     * @param float $currentAssets Current assets
     * @param float $currentLiabilities Current liabilities
     * @param float $revenue Annual revenue (used if balance sheet items not available)
     * @return float Working capital requirement
     */
    public function calculateWorkingCapitalRequirement($currentAssets = null, $currentLiabilities = null, $revenue = 0)
    {
        // If balance sheet items available, use them
        if ($currentAssets !== null && $currentLiabilities !== null) {
            return floatval($currentAssets) - floatval($currentLiabilities);
        }
        
        // Otherwise, use percentage of revenue
        return floatval($revenue) * self::DEFAULT_WORKING_CAPITAL_RATIO;
    }
    
    /**
     * Calculate Break-Even Multiple
     * 
     * @param float $annualCashFlow Annual cash flow
     * @param float $ebitda EBITDA value
     * @param float $growthRate Annual growth rate
     * @param int $holdPeriod Hold period in years
     * @return float Break-even multiple
     */
    public function calculateBreakEvenMultiple($annualCashFlow, $ebitda, $growthRate = null, $holdPeriod = null)
    {
        if ($ebitda <= 0) {
            return 0;
        }
        
        if ($growthRate === null) {
            $growthRate = self::DEFAULT_GROWTH_RATE;
        }
        
        if ($holdPeriod === null) {
            $holdPeriod = self::DEFAULT_HOLD_PERIOD;
        }
        
        // Calculate total cash flows over hold period with growth
        $totalCashFlows = 0;
        for ($year = 1; $year <= $holdPeriod; $year++) {
            $totalCashFlows += $annualCashFlow * pow(1 + $growthRate, $year);
        }
        
        $breakEvenMultiple = $totalCashFlows / floatval($ebitda);
        return round($breakEvenMultiple, self::MULTIPLE_PRECISION);
    }
    
    /**
     * Calculate Capital Stack components
     * 
     * @param float $totalPrice Total acquisition price
     * @param float $equityPercentage Equity percentage (0-1)
     * @param float $seniorDebtPercentage Senior debt percentage (0-1)
     * @param float $sellerNotePercentage Seller note percentage (0-1)
     * @return array Capital stack breakdown
     */
    public function calculateCapitalStack($totalPrice, $equityPercentage = 0.30, 
                                         $seniorDebtPercentage = 0.50, $sellerNotePercentage = 0.20)
    {
        $totalPrice = floatval($totalPrice);
        
        // Ensure percentages add up to 100%
        $totalPercentage = $equityPercentage + $seniorDebtPercentage + $sellerNotePercentage;
        if ($totalPercentage > 1.01 || $totalPercentage < 0.99) {
            // Normalize if not equal to 100%
            $equityPercentage = $equityPercentage / $totalPercentage;
            $seniorDebtPercentage = $seniorDebtPercentage / $totalPercentage;
            $sellerNotePercentage = $sellerNotePercentage / $totalPercentage;
        }
        
        return array(
            'total_price' => $totalPrice,
            'equity' => round($totalPrice * $equityPercentage, self::CURRENCY_PRECISION),
            'equity_percentage' => round($equityPercentage * 100, self::PERCENTAGE_PRECISION),
            'senior_debt' => round($totalPrice * $seniorDebtPercentage, self::CURRENCY_PRECISION),
            'senior_debt_percentage' => round($seniorDebtPercentage * 100, self::PERCENTAGE_PRECISION),
            'seller_note' => round($totalPrice * $sellerNotePercentage, self::CURRENCY_PRECISION),
            'seller_note_percentage' => round($sellerNotePercentage * 100, self::PERCENTAGE_PRECISION)
        );
    }
    
    /**
     * Calculate Revenue Multiple
     * 
     * @param float $price Business price
     * @param float $revenue Annual revenue
     * @return float Revenue multiple
     */
    public function calculateRevenueMultiple($price, $revenue)
    {
        if ($revenue <= 0) {
            return 0;
        }
        
        $multiple = floatval($price) / floatval($revenue);
        return round($multiple, self::MULTIPLE_PRECISION);
    }
    
    /**
     * Calculate SDE Multiple
     * 
     * @param float $price Business price
     * @param float $sde SDE value
     * @return float SDE multiple
     */
    public function calculateSDEMultiple($price, $sde)
    {
        if ($sde <= 0) {
            return 0;
        }
        
        $multiple = floatval($price) / floatval($sde);
        return round($multiple, self::MULTIPLE_PRECISION);
    }
    
    /**
     * Calculate Payback Period
     * 
     * @param float $totalInvestment Total investment amount
     * @param float $annualCashFlow Annual cash flow
     * @return float Payback period in years
     */
    public function calculatePaybackPeriod($totalInvestment, $annualCashFlow)
    {
        if ($annualCashFlow <= 0) {
            return 0;
        }
        
        $paybackPeriod = floatval($totalInvestment) / floatval($annualCashFlow);
        return round($paybackPeriod, 1);
    }
    
    /**
     * Calculate Net Present Value (NPV)
     * 
     * @param array $cashFlows Array of cash flows by year
     * @param float $discountRate Discount rate
     * @param float $initialInvestment Initial investment (negative value)
     * @return float NPV
     */
    public function calculateNPV($cashFlows, $discountRate, $initialInvestment)
    {
        $npv = -abs($initialInvestment); // Initial investment is negative
        
        foreach ($cashFlows as $year => $cashFlow) {
            $npv += $cashFlow / pow(1 + $discountRate, $year);
        }
        
        return round($npv, self::CURRENCY_PRECISION);
    }
    
    /**
     * Calculate Internal Rate of Return (IRR)
     * Uses Newton-Raphson method for approximation
     * 
     * @param array $cashFlows Array of cash flows (including initial investment as negative)
     * @return float IRR as percentage
     */
    public function calculateIRR($cashFlows)
    {
        if (empty($cashFlows)) {
            return 0;
        }
        
        // Initial guess
        $rate = 0.1;
        $tolerance = 0.00001;
        $maxIterations = 100;
        
        for ($i = 0; $i < $maxIterations; $i++) {
            $npv = 0;
            $dnpv = 0;
            
            foreach ($cashFlows as $t => $cashFlow) {
                $npv += $cashFlow / pow(1 + $rate, $t);
                $dnpv -= $t * $cashFlow / pow(1 + $rate, $t + 1);
            }
            
            if (abs($npv) < $tolerance) {
                return round($rate * 100, self::PERCENTAGE_PRECISION);
            }
            
            $rate = $rate - $npv / $dnpv;
        }
        
        return round($rate * 100, self::PERCENTAGE_PRECISION);
    }
    
    /**
     * Format currency value
     * 
     * @param float $value Value to format
     * @param string $symbol Currency symbol
     * @return string Formatted currency
     */
    public function formatCurrency($value, $symbol = '$')
    {
        return $symbol . number_format($value, self::CURRENCY_PRECISION);
    }
    
    /**
     * Format percentage value
     * 
     * @param float $value Value to format
     * @return string Formatted percentage
     */
    public function formatPercentage($value)
    {
        return number_format($value, self::PERCENTAGE_PRECISION) . '%';
    }
    
    /**
     * Format multiple value
     * 
     * @param float $value Value to format
     * @return string Formatted multiple
     */
    public function formatMultiple($value)
    {
        return number_format($value, self::MULTIPLE_PRECISION) . 'x';
    }
    
    /**
     * Get all financial metrics for a deal
     * 
     * @param array $dealData Array containing all deal financial data
     * @return array Calculated financial metrics
     */
    public function calculateAllMetrics($dealData)
    {
        $metrics = array();
        
        // Revenue metrics
        $metrics['ttm_revenue'] = $this->calculateTTMRevenue(
            $dealData['monthly_revenue'] ?? array(),
            $dealData['annual_revenue'] ?? 0
        );
        
        // EBITDA metrics
        $metrics['ttm_ebitda'] = $this->calculateTTMEBITDA(
            $metrics['ttm_revenue'],
            $dealData['operating_expenses'] ?? null,
            $dealData['add_backs'] ?? 0
        );
        
        $metrics['ebitda_margin'] = $this->calculateEBITDAMargin(
            $metrics['ttm_ebitda'],
            $metrics['ttm_revenue']
        );
        
        // SDE metrics
        $metrics['sde'] = $this->calculateSDE(
            $metrics['ttm_ebitda'],
            $dealData['owner_compensation'] ?? 0,
            $dealData['owner_benefits'] ?? 0,
            $dealData['non_essential_expenses'] ?? 0
        );
        
        // Valuation metrics
        $metrics['proposed_valuation'] = $this->calculateProposedValuation(
            $dealData['valuation_method'] === 'sde' ? $metrics['sde'] : $metrics['ttm_ebitda'],
            $dealData['target_multiple'] ?? null,
            $dealData['valuation_method'] ?? 'ebitda'
        );
        
        if (!empty($dealData['asking_price'])) {
            $metrics['implied_multiple'] = $this->calculateImpliedMultiple(
                $dealData['asking_price'],
                $metrics['ttm_ebitda']
            );
            
            $metrics['revenue_multiple'] = $this->calculateRevenueMultiple(
                $dealData['asking_price'],
                $metrics['ttm_revenue']
            );
            
            $metrics['sde_multiple'] = $this->calculateSDEMultiple(
                $dealData['asking_price'],
                $metrics['sde']
            );
        }
        
        // Cash flow metrics
        $metrics['annual_cash_flow'] = $this->calculateAnnualCashFlow(
            $metrics['sde'],
            $dealData['normalized_salary'] ?? null
        );
        
        // Investment metrics
        $totalInvestment = $dealData['equity_investment'] ?? $dealData['asking_price'] ?? 0;
        if ($totalInvestment > 0) {
            $metrics['roi'] = $this->calculateROI(
                $metrics['annual_cash_flow'],
                $totalInvestment
            );
            
            $metrics['payback_period'] = $this->calculatePaybackPeriod(
                $totalInvestment,
                $metrics['annual_cash_flow']
            );
        }
        
        // Debt metrics
        if (!empty($dealData['debt_structure'])) {
            $totalDebtService = $this->calculateTotalDebtService(
                $dealData['debt_structure']['senior_debt'] ?? 0,
                $dealData['debt_structure']['senior_debt_rate'] ?? 0.05,
                $dealData['debt_structure']['senior_debt_term'] ?? 5,
                $dealData['debt_structure']['seller_note'] ?? 0,
                $dealData['debt_structure']['seller_note_rate'] ?? 0.06,
                $dealData['debt_structure']['seller_note_term'] ?? 3
            );
            
            $metrics['dscr'] = $this->calculateDebtServiceCoverageRatio(
                $metrics['ttm_ebitda'],
                $dealData['capital_expenditures'] ?? 0,
                $dealData['estimated_taxes'] ?? null,
                $totalDebtService
            );
            
            $metrics['total_debt_service'] = $totalDebtService;
        }
        
        // Working capital
        $metrics['working_capital_requirement'] = $this->calculateWorkingCapitalRequirement(
            $dealData['current_assets'] ?? null,
            $dealData['current_liabilities'] ?? null,
            $metrics['ttm_revenue']
        );
        
        // Break-even analysis
        $metrics['break_even_multiple'] = $this->calculateBreakEvenMultiple(
            $metrics['annual_cash_flow'],
            $metrics['ttm_ebitda'],
            $dealData['growth_rate'] ?? null,
            $dealData['hold_period'] ?? null
        );
        
        // Capital stack
        if (!empty($dealData['asking_price'])) {
            $metrics['capital_stack'] = $this->calculateCapitalStack(
                $dealData['asking_price'],
                $dealData['equity_percentage'] ?? 0.30,
                $dealData['senior_debt_percentage'] ?? 0.50,
                $dealData['seller_note_percentage'] ?? 0.20
            );
        }
        
        return $metrics;
    }
}