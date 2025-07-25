/**
 * Financial Dashboard Initialization
 * Sets up and initializes the financial dashboard
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 */

(function(global) {
    'use strict';

    // Wait for DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        initializeFinancialDashboard();
    });

    function initializeFinancialDashboard() {
        // Check if we're on the financial dashboard page
        const dashboardContainer = document.getElementById('financial-dashboard');
        if (!dashboardContainer) return;

        // Get data from template
        const financialData = global.financialData || {};
        const comparablesData = global.comparablesData || {};
        const capitalStackData = global.capitalStackData || {};

        // Calculate all metrics
        const calculations = global.FinancialDashboard.CalculationEngine.calculateAll(financialData);

        // Initialize dashboard manager
        const dashboardManager = global.FinancialDashboard.manager;
        
        const widgetConfig = [
            // Key Metrics
            {
                type: 'keyMetric',
                id: 'asking-price',
                element: document.getElementById('asking-price-widget'),
                metricType: 'asking-price',
                label: 'Asking Price',
                format: 'currency',
                data: { value: financialData.askingPrice }
            },
            {
                type: 'keyMetric',
                id: 'ttm-revenue',
                element: document.getElementById('ttm-revenue-widget'),
                metricType: 'ttm-revenue',
                label: 'TTM Revenue',
                format: 'currency',
                data: { value: calculations.ttmRevenue.value }
            },
            {
                type: 'keyMetric',
                id: 'ttm-ebitda',
                element: document.getElementById('ttm-ebitda-widget'),
                metricType: 'ttm-ebitda',
                label: 'TTM EBITDA',
                format: 'currency',
                data: { value: calculations.ttmEbitda.value }
            },
            {
                type: 'keyMetric',
                id: 'sde',
                element: document.getElementById('sde-widget'),
                metricType: 'sde',
                label: 'SDE',
                format: 'currency',
                data: { value: calculations.sde.value }
            },
            {
                type: 'keyMetric',
                id: 'ebitda-margin',
                element: document.getElementById('ebitda-margin-widget'),
                metricType: 'ebitda-margin',
                label: 'EBITDA Margin',
                format: 'percentage',
                data: { value: calculations.ebitdaMargin.value }
            },
            {
                type: 'keyMetric',
                id: 'proposed-valuation',
                element: document.getElementById('proposed-valuation-widget'),
                metricType: 'proposed-valuation',
                label: 'Proposed Valuation',
                format: 'currency',
                data: { value: calculations.proposedValuation.value }
            },
            
            // Capital Stack
            {
                type: 'capitalStack',
                id: 'capital-stack',
                element: document.getElementById('capital-stack-container'),
                data: capitalStackData
            },
            
            // Debt Coverage
            {
                type: 'debtCoverage',
                id: 'debt-coverage',
                element: document.querySelector('.debt-coverage-container'),
                data: {
                    dscr: calculations.debtServiceCoverageRatio.value,
                    cashFlowAvailable: calculations.ttmEbitda.value - (financialData.capitalExpenditures || 0) - (financialData.estimatedTaxes || 0),
                    totalDebtService: calculateTotalDebtService(financialData.debtStructure)
                }
            },
            
            // Comparables
            {
                type: 'comparables',
                id: 'comparables',
                element: document.querySelector('.comparables-container'),
                data: comparablesData
            }
        ];

        // Initialize dashboard with widget configuration
        dashboardManager.initialize({ widgets: widgetConfig });

        // Update additional UI elements
        updateValuationAnalysis(calculations, financialData);
        updateROIMetrics(calculations);

        // Setup event handlers
        setupEventHandlers();
    }

    function calculateTotalDebtService(debtStructure) {
        if (!debtStructure) return 0;
        
        let totalService = 0;
        
        if (debtStructure.seniorDebt && debtStructure.seniorDebt.amount > 0) {
            const payment = calculateDebtPayment(
                debtStructure.seniorDebt.amount,
                debtStructure.seniorDebt.rate,
                debtStructure.seniorDebt.term
            );
            totalService += payment;
        }
        
        if (debtStructure.sellerNote && debtStructure.sellerNote.amount > 0) {
            const payment = calculateDebtPayment(
                debtStructure.sellerNote.amount,
                debtStructure.sellerNote.rate,
                debtStructure.sellerNote.term
            );
            totalService += payment;
        }
        
        return totalService;
    }

    function calculateDebtPayment(principal, annualRate, termYears) {
        if (principal <= 0 || annualRate <= 0 || termYears <= 0) return 0;
        
        const monthlyRate = annualRate / 12 / 100;
        const months = termYears * 12;
        
        const payment = principal * (monthlyRate * Math.pow(1 + monthlyRate, months)) / 
                       (Math.pow(1 + monthlyRate, months) - 1);
        
        return payment * 12; // Annual payment
    }

    function updateValuationAnalysis(calculations, financialData) {
        // Update implied multiple
        const impliedMultipleEl = document.getElementById('implied-multiple');
        if (impliedMultipleEl) {
            impliedMultipleEl.textContent = calculations.impliedMultiple.formatted;
        }
        
        // Update industry multiple
        const industryMultipleEl = document.getElementById('industry-multiple');
        if (industryMultipleEl) {
            industryMultipleEl.textContent = (financialData.industryMultiple || 3.5).toFixed(2) + 'x';
        }
        
        // Update target multiple
        const targetMultipleEl = document.getElementById('target-multiple');
        if (targetMultipleEl) {
            targetMultipleEl.textContent = (financialData.targetMultiple || 3.5).toFixed(2) + 'x';
        }
    }

    function updateROIMetrics(calculations) {
        // Update cash-on-cash return
        const cashOnCashEl = document.getElementById('cash-on-cash');
        if (cashOnCashEl) {
            cashOnCashEl.textContent = calculations.returnOnInvestment.formatted;
        }
        
        // Update break-even multiple
        const breakEvenEl = document.getElementById('break-even-multiple');
        if (breakEvenEl) {
            breakEvenEl.textContent = calculations.breakEvenMultiple.formatted;
        }
        
        // Update working capital requirement
        const workingCapitalEl = document.getElementById('working-capital');
        if (workingCapitalEl) {
            workingCapitalEl.textContent = formatCurrency(calculations.workingCapitalRequirement.value);
        }
    }

    function setupEventHandlers() {
        // Refresh button
        const refreshBtn = document.getElementById('refresh-dashboard');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function() {
                refreshDashboard();
            });
        }
        
        // Export button
        const exportBtn = document.getElementById('export-dashboard');
        if (exportBtn) {
            exportBtn.addEventListener('click', function() {
                exportDashboard();
            });
        }
        
        // What-If Calculator button
        const whatIfBtn = document.getElementById('what-if-calculator');
        if (whatIfBtn) {
            whatIfBtn.addEventListener('click', function() {
                openWhatIfCalculator();
            });
        }
    }

    function refreshDashboard() {
        // Show loading state
        const widgets = document.querySelectorAll('.metric-widget, .dashboard-section');
        widgets.forEach(widget => widget.classList.add('widget-loading'));
        
        // Simulate API call to refresh data
        setTimeout(() => {
            // In production, this would fetch fresh data from server
            global.FinancialDashboard.manager.refreshAllWidgets();
            
            // Remove loading state
            widgets.forEach(widget => widget.classList.remove('widget-loading'));
        }, 1000);
    }

    function exportDashboard() {
        const financialData = global.financialData || {};
        const calculations = global.FinancialDashboard.CalculationEngine.calculateAll(financialData);
        
        // Generate CSV
        const csv = global.FinancialDashboard.CalculationEngine.exportCalculations(financialData, 'csv');
        
        // Create download link
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'financial-dashboard-' + new Date().toISOString().split('T')[0] + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }

    function openWhatIfCalculator() {
        // Initialize what-if calculator widget in modal
        const modalEl = document.getElementById('what-if-modal');
        if (!modalEl) return;
        
        // Create widget if not exists
        let whatIfWidget = global.FinancialDashboard.manager.registry.getWidget('what-if-calculator');
        if (!whatIfWidget) {
            const container = document.querySelector('.what-if-container');
            whatIfWidget = global.FinancialDashboard.manager.registry.createWidget('whatIfCalculator', {
                id: 'what-if-calculator',
                element: container,
                data: global.financialData
            });
            whatIfWidget.initialize();
        }
        
        // Show modal
        $(modalEl).modal('show');
    }

    function formatCurrency(value) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(value);
    }

    // Export initialization function for testing
    global.FinancialDashboard.initialize = initializeFinancialDashboard;

})(window);