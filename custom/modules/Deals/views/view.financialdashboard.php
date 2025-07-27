<?php
/**
 * Financial Dashboard View
 * Displays financial metrics and valuation calculations for deals
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

// Adjust paths for custom module location
$suitecrm_root = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/SuiteCRM';
require_once($suitecrm_root . '/include/MVC/View/views/view.detail.php');

class DealsViewFinancialdashboard extends ViewDetail
{
    public function __construct()
    {
        parent::__construct();
    }

    public function preDisplay()
    {
        parent::preDisplay();
        
        // Add custom CSS and JS
        $this->addDashboardAssets();
    }

    public function display()
    {
        global $current_user, $app_strings, $mod_strings;

        // Check permissions
        if (!$this->bean->ACLAccess('view')) {
            ACLController::displayNoAccess();
            sugar_die('');
        }

        // Get deal financial data
        $financialData = $this->getFinancialData();
        
        // Get comparables data
        $comparablesData = $this->getComparablesData();
        
        // Get capital stack data
        $capitalStackData = $this->getCapitalStackData();

        // Assign variables to template
        $this->ss->assign('DEAL_ID', $this->bean->id);
        $this->ss->assign('DEAL_NAME', $this->bean->name);
        $this->ss->assign('FINANCIAL_DATA', json_encode($financialData));
        $this->ss->assign('COMPARABLES_DATA', json_encode($comparablesData));
        $this->ss->assign('CAPITAL_STACK_DATA', json_encode($capitalStackData));
        $this->ss->assign('CURRENCY_SYMBOL', '$');
        
        // Display the template
        echo $this->ss->fetch('custom/modules/Deals/tpls/financial-dashboard.tpl');
    }

    /**
     * Add CSS and JavaScript files for the dashboard
     */
    private function addDashboardAssets()
    {
        echo '<link rel="stylesheet" type="text/css" href="custom/modules/Deals/css/financial-dashboard.css">';
        echo '<link rel="stylesheet" type="text/css" href="custom/modules/Deals/css/financial-editor.css">';
        echo '<script type="text/javascript" src="custom/modules/Deals/js/financial-dashboard-framework.js"></script>';
        echo '<script type="text/javascript" src="custom/modules/Deals/js/financial-calculation-engine.js"></script>';
        echo '<script type="text/javascript" src="custom/modules/Deals/js/financial-dashboard-widgets.js"></script>';
        echo '<script type="text/javascript" src="custom/modules/Deals/js/financial-editor.js"></script>';
        echo '<script type="text/javascript" src="custom/modules/Deals/js/financial-dashboard-init.js"></script>';
        
        // Initialize the editor
        echo '<script type="text/javascript">
            $(document).ready(function() {
                if (typeof FinancialEditor !== "undefined") {
                    FinancialEditor.init("' . $this->bean->id . '");
                }
            });
        </script>';
    }

    /**
     * Get financial data for the deal
     */
    private function getFinancialData()
    {
        require_once('custom/modules/Deals/services/FinancialCalculator.php');
        $calculator = new FinancialCalculator();
        
        // Gather raw data
        $rawData = array(
            'dealId' => $this->bean->id,
            'asking_price' => floatval($this->bean->amount),
            'annual_revenue' => $this->getCustomFieldValue('annual_revenue_c'),
            'monthly_revenue' => $this->getMonthlyRevenueData(),
            'operating_expenses' => $this->getCustomFieldValue('operating_expenses_c'),
            'add_backs' => $this->getCustomFieldValue('add_backs_c'),
            'owner_compensation' => $this->getCustomFieldValue('owner_compensation_c'),
            'owner_benefits' => $this->getCustomFieldValue('owner_benefits_c'),
            'non_essential_expenses' => $this->getCustomFieldValue('non_essential_expenses_c'),
            'target_multiple' => $this->getCustomFieldValue('target_multiple_c', 3.5),
            'industry_multiple' => $this->getIndustryMultiple(),
            'valuation_method' => $this->getCustomFieldValue('valuation_method_c', 'ebitda'),
            'capital_expenditures' => $this->getCustomFieldValue('capital_expenditures_c'),
            'estimated_taxes' => $this->getCustomFieldValue('estimated_taxes_c'),
            'normalized_salary' => $this->getCustomFieldValue('normalized_salary_c', 50000),
            'growth_rate' => $this->getCustomFieldValue('growth_rate_c', 0.03),
            'hold_period' => $this->getCustomFieldValue('hold_period_c', 5),
            'debt_structure' => $this->getDebtStructure(),
            'current_assets' => $this->getCustomFieldValue('current_assets_c'),
            'current_liabilities' => $this->getCustomFieldValue('current_liabilities_c'),
            'equity_investment' => $this->getCustomFieldValue('equity_c'),
        );
        
        // Calculate all metrics using FinancialCalculator
        $calculatedMetrics = $calculator->calculateAllMetrics($rawData);
        
        // Merge raw data with calculated metrics for dashboard
        $data = array_merge($rawData, array(
            'calculated_metrics' => $calculatedMetrics,
            'formatted_metrics' => array(
                'ttm_revenue' => $calculator->formatCurrency($calculatedMetrics['ttm_revenue'] ?? 0),
                'ttm_ebitda' => $calculator->formatCurrency($calculatedMetrics['ttm_ebitda'] ?? 0),
                'sde' => $calculator->formatCurrency($calculatedMetrics['sde'] ?? 0),
                'ebitda_margin' => $calculator->formatPercentage($calculatedMetrics['ebitda_margin'] ?? 0),
                'proposed_valuation' => $calculator->formatCurrency($calculatedMetrics['proposed_valuation'] ?? 0),
                'implied_multiple' => $calculator->formatMultiple($calculatedMetrics['implied_multiple'] ?? 0),
                'roi' => $calculator->formatPercentage($calculatedMetrics['roi'] ?? 0),
                'dscr' => number_format($calculatedMetrics['dscr'] ?? 0, 2) . 'x',
                'payback_period' => number_format($calculatedMetrics['payback_period'] ?? 0, 1) . ' years'
            )
        ));

        return $data;
    }

    /**
     * Get monthly revenue data
     */
    private function getMonthlyRevenueData()
    {
        global $db;
        
        $monthlyRevenue = array();
        
        // Query for monthly revenue data if available
        $sql = "SELECT month, revenue 
                FROM deals_monthly_revenue 
                WHERE deal_id = '{$this->bean->id}' 
                AND deleted = 0 
                ORDER BY month DESC 
                LIMIT 12";
        
        $result = $db->query($sql);
        
        while ($row = $db->fetchByAssoc($result)) {
            $monthlyRevenue[] = floatval($row['revenue']);
        }
        
        // If no monthly data, generate from annual
        if (empty($monthlyRevenue)) {
            $annualRevenue = $this->getCustomFieldValue('annual_revenue_c');
            if ($annualRevenue > 0) {
                $monthlyAmount = $annualRevenue / 12;
                for ($i = 0; $i < 12; $i++) {
                    $monthlyRevenue[] = $monthlyAmount;
                }
            }
        }
        
        return array_reverse($monthlyRevenue);
    }

    /**
     * Get comparables data
     */
    private function getComparablesData()
    {
        global $db;
        
        $comparables = array();
        
        // Get industry and size range
        $industry = $this->bean->industry;
        $dealSize = floatval($this->bean->amount);
        $sizeMin = $dealSize * 0.5;
        $sizeMax = $dealSize * 2.0;
        
        // Query for comparable deals
        $sql = "SELECT 
                    o.name,
                    o.amount,
                    oc.annual_revenue_c as revenue,
                    oc.ebitda_c as ebitda,
                    oc.sale_multiple_c as multiple,
                    o.date_closed
                FROM opportunities o
                INNER JOIN opportunities_cstm oc ON o.id = oc.id_c
                WHERE o.deleted = 0
                AND o.sales_stage = 'Closed Won'
                AND o.industry = '{$db->quote($industry)}'
                AND o.amount BETWEEN {$sizeMin} AND {$sizeMax}
                AND o.id != '{$this->bean->id}'
                ORDER BY o.date_closed DESC
                LIMIT 10";
        
        $result = $db->query($sql);
        
        while ($row = $db->fetchByAssoc($result)) {
            $comparables[] = array(
                'name' => $row['name'],
                'amount' => floatval($row['amount']),
                'revenue' => floatval($row['revenue']),
                'ebitda' => floatval($row['ebitda']),
                'multiple' => floatval($row['multiple']),
                'date' => $row['date_closed']
            );
        }
        
        // Calculate median multiples
        $multiples = array_column($comparables, 'multiple');
        $medianMultiple = $this->calculateMedian($multiples);
        
        return array(
            'deals' => $comparables,
            'medianMultiple' => $medianMultiple,
            'count' => count($comparables)
        );
    }

    /**
     * Get capital stack data
     */
    private function getCapitalStackData()
    {
        $askingPrice = floatval($this->bean->amount);
        
        // Get debt structure
        $debtStructure = $this->getDebtStructure();
        
        // Calculate equity requirement
        $totalDebt = 0;
        if (isset($debtStructure['seniorDebt'])) {
            $totalDebt += $debtStructure['seniorDebt']['amount'];
        }
        if (isset($debtStructure['sellerNote'])) {
            $totalDebt += $debtStructure['sellerNote']['amount'];
        }
        
        $equity = $askingPrice - $totalDebt;
        
        $capitalStack = array(
            'totalDealValue' => $askingPrice,
            'equity' => array(
                'amount' => $equity,
                'percentage' => $askingPrice > 0 ? ($equity / $askingPrice) * 100 : 0
            ),
            'seniorDebt' => $debtStructure['seniorDebt'] ?? array(
                'amount' => 0,
                'percentage' => 0,
                'rate' => 0,
                'term' => 0
            ),
            'sellerNote' => $debtStructure['sellerNote'] ?? array(
                'amount' => 0,
                'percentage' => 0,
                'rate' => 0,
                'term' => 0
            )
        );
        
        return $capitalStack;
    }

    /**
     * Get debt structure from custom fields
     */
    private function getDebtStructure()
    {
        $askingPrice = floatval($this->bean->amount);
        
        $structure = array();
        
        // Senior Debt
        $seniorDebtAmount = $this->getCustomFieldValue('senior_debt_amount_c');
        if ($seniorDebtAmount > 0) {
            $structure['seniorDebt'] = array(
                'amount' => $seniorDebtAmount,
                'percentage' => $askingPrice > 0 ? ($seniorDebtAmount / $askingPrice) * 100 : 0,
                'rate' => $this->getCustomFieldValue('senior_debt_rate_c', 6.5),
                'term' => $this->getCustomFieldValue('senior_debt_term_c', 5)
            );
        }
        
        // Seller Note
        $sellerNoteAmount = $this->getCustomFieldValue('seller_note_amount_c');
        if ($sellerNoteAmount > 0) {
            $structure['sellerNote'] = array(
                'amount' => $sellerNoteAmount,
                'percentage' => $askingPrice > 0 ? ($sellerNoteAmount / $askingPrice) * 100 : 0,
                'rate' => $this->getCustomFieldValue('seller_note_rate_c', 5.0),
                'term' => $this->getCustomFieldValue('seller_note_term_c', 3)
            );
        }
        
        return $structure;
    }

    /**
     * Get industry multiple based on industry type
     */
    private function getIndustryMultiple()
    {
        // This would typically come from a database of industry benchmarks
        $industryMultiples = array(
            'Manufacturing' => 4.5,
            'Retail' => 3.0,
            'Services' => 3.5,
            'Technology' => 5.5,
            'Healthcare' => 4.0,
            'Construction' => 3.0,
            'Distribution' => 3.5,
            'Food & Beverage' => 3.0,
            'Real Estate' => 6.0,
            'Other' => 3.5
        );
        
        $industry = $this->bean->industry;
        return isset($industryMultiples[$industry]) ? $industryMultiples[$industry] : 3.5;
    }

    /**
     * Get custom field value with default
     */
    private function getCustomFieldValue($field, $default = 0)
    {
        if (isset($this->bean->$field)) {
            return floatval($this->bean->$field);
        }
        return $default;
    }

    /**
     * Calculate median of array
     */
    private function calculateMedian($array)
    {
        if (empty($array)) return 0;
        
        sort($array);
        $count = count($array);
        $middle = floor(($count - 1) / 2);
        
        if ($count % 2) {
            return $array[$middle];
        } else {
            return ($array[$middle] + $array[$middle + 1]) / 2;
        }
    }
}
?>