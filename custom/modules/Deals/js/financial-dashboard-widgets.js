/**
 * Financial Dashboard Widget Implementations
 * Specific widget classes for different financial metrics
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 */

(function(global) {
    'use strict';

    // Ensure namespace exists
    global.FinancialDashboard = global.FinancialDashboard || {};

    const { DashboardWidget } = global.FinancialDashboard;

    /**
     * Key Metric Widget
     * Displays a single financial metric with formatting
     */
    class KeyMetricWidget extends DashboardWidget {
        constructor(config) {
            super(config);
            this.metricType = config.metricType;
            this.label = config.label;
            this.format = config.format || 'currency';
        }

        render() {
            if (!this.element) return;

            const value = this.data.value || 0;
            const formattedValue = this.formatMetricValue(value);
            
            this.element.innerHTML = `
                <div class="metric-widget ${this.metricType}">
                    <div class="metric-label">${this.label}</div>
                    <div class="metric-value">${formattedValue}</div>
                </div>
            `;

            // Add trend indicator if available
            if (this.data.trend) {
                this.addTrendIndicator(this.data.trend);
            }
        }

        formatMetricValue(value) {
            switch (this.format) {
                case 'currency':
                    return this.formatCurrency(value);
                case 'percentage':
                    return this.formatPercent(value);
                case 'multiple':
                    return value.toFixed(2) + 'x';
                default:
                    return this.formatNumber(value);
            }
        }

        addTrendIndicator(trend) {
            const trendHtml = `
                <div class="metric-trend ${trend > 0 ? 'positive' : 'negative'}">
                    <i class="glyphicon glyphicon-${trend > 0 ? 'arrow-up' : 'arrow-down'}"></i>
                    ${Math.abs(trend).toFixed(1)}%
                </div>
            `;
            this.element.querySelector('.metric-widget').insertAdjacentHTML('beforeend', trendHtml);
        }
    }

    /**
     * Capital Stack Widget
     * Visualizes the capital structure of a deal
     */
    class CapitalStackWidget extends DashboardWidget {
        constructor(config) {
            super(config);
            this.colors = {
                equity: '#28a745',
                seniorDebt: '#17a2b8',
                sellerNote: '#ffc107'
            };
        }

        render() {
            if (!this.element || !this.data) return;

            const { equity, seniorDebt, sellerNote, totalDealValue } = this.data;
            
            // Create visual stack
            const visualHtml = this.createStackVisual();
            
            // Create detail list
            const detailsHtml = this.createStackDetails();

            this.element.innerHTML = `
                <div class="capital-stack-container">
                    <div class="stack-visual">
                        ${visualHtml}
                    </div>
                    <div class="stack-details">
                        ${detailsHtml}
                    </div>
                </div>
            `;
        }

        createStackVisual() {
            const { equity, seniorDebt, sellerNote, totalDealValue } = this.data;
            
            if (!totalDealValue || totalDealValue === 0) return '<div class="no-data">No capital structure data</div>';

            const components = [
                { name: 'equity', value: equity.amount, color: this.colors.equity },
                { name: 'senior-debt', value: seniorDebt.amount, color: this.colors.seniorDebt },
                { name: 'seller-note', value: sellerNote.amount, color: this.colors.sellerNote }
            ];

            let html = '<div class="stack-bars">';
            
            components.forEach(comp => {
                if (comp.value > 0) {
                    const height = (comp.value / totalDealValue) * 100;
                    html += `
                        <div class="stack-bar ${comp.name}" 
                             style="height: ${height}%; background-color: ${comp.color};"
                             title="${this.formatCurrency(comp.value)}">
                        </div>
                    `;
                }
            });

            html += '</div>';
            return html;
        }

        createStackDetails() {
            const { equity, seniorDebt, sellerNote, totalDealValue } = this.data;

            const items = [
                { label: 'Equity', data: equity, class: 'equity' },
                { label: 'Senior Debt', data: seniorDebt, class: 'senior-debt' },
                { label: 'Seller Note', data: sellerNote, class: 'seller-note' }
            ];

            let html = '';
            
            items.forEach(item => {
                html += `
                    <div class="stack-item ${item.class}">
                        <span class="stack-label">${item.label}</span>
                        <span class="stack-amount">${this.formatCurrency(item.data.amount)}</span>
                        <span class="stack-percentage">${item.data.percentage.toFixed(1)}%</span>
                    </div>
                `;
            });

            html += `
                <div class="stack-total">
                    <span class="stack-label">Total Deal Value</span>
                    <span class="stack-amount">${this.formatCurrency(totalDealValue)}</span>
                </div>
            `;

            return html;
        }
    }

    /**
     * Debt Coverage Widget
     * Shows DSCR and related metrics
     */
    class DebtCoverageWidget extends DashboardWidget {
        render() {
            if (!this.element || !this.data) return;

            const { dscr, cashFlowAvailable, totalDebtService } = this.data;
            const status = this.getDSCRStatus(dscr);
            const coverageCushion = cashFlowAvailable - totalDebtService;

            this.element.innerHTML = `
                <div class="debt-coverage-container">
                    <div class="coverage-metric">
                        <div class="metric-label">DSCR</div>
                        <div class="metric-value">${dscr.toFixed(2)}x</div>
                        <div class="metric-status ${status.class}">${status.text}</div>
                    </div>
                    <div class="coverage-details">
                        <div class="coverage-item">
                            <span class="label">Cash Flow Available</span>
                            <span class="value">${this.formatCurrency(cashFlowAvailable)}</span>
                        </div>
                        <div class="coverage-item">
                            <span class="label">Total Debt Service</span>
                            <span class="value">${this.formatCurrency(totalDebtService)}</span>
                        </div>
                        <div class="coverage-item">
                            <span class="label">Coverage Cushion</span>
                            <span class="value ${coverageCushion >= 0 ? 'positive' : 'negative'}">
                                ${this.formatCurrency(Math.abs(coverageCushion))}
                            </span>
                        </div>
                    </div>
                </div>
            `;
        }

        getDSCRStatus(dscr) {
            if (dscr >= 1.25) {
                return { class: 'good', text: 'Strong Coverage' };
            } else if (dscr >= 1.0) {
                return { class: 'warning', text: 'Adequate Coverage' };
            } else {
                return { class: 'danger', text: 'Insufficient Coverage' };
            }
        }
    }

    /**
     * Comparables Widget
     * Displays comparable deals and statistics
     */
    class ComparablesWidget extends DashboardWidget {
        render() {
            if (!this.element || !this.data) return;

            const { deals, medianMultiple, count } = this.data;

            this.element.innerHTML = `
                <div class="comparables-container">
                    <div class="comparables-summary">
                        <div class="summary-metric">
                            <span class="label">Median Multiple</span>
                            <span class="value">${medianMultiple.toFixed(2)}x</span>
                        </div>
                        <div class="summary-metric">
                            <span class="label">Comparable Deals</span>
                            <span class="value">${count}</span>
                        </div>
                    </div>
                    <div class="comparables-list">
                        ${this.renderComparablesList(deals)}
                    </div>
                </div>
            `;
        }

        renderComparablesList(deals) {
            if (!deals || deals.length === 0) {
                return '<div class="no-data">No comparable deals found</div>';
            }

            let html = '';
            deals.forEach(deal => {
                html += `
                    <div class="comparable-item">
                        <div class="comparable-name">${deal.name}</div>
                        <div class="comparable-metrics">
                            <span class="metric">
                                <span class="label">Price:</span> ${this.formatCurrency(deal.amount)}
                            </span>
                            <span class="metric">
                                <span class="label">Revenue:</span> ${this.formatCurrency(deal.revenue)}
                            </span>
                            <span class="metric">
                                <span class="label">Multiple:</span> ${deal.multiple.toFixed(2)}x
                            </span>
                        </div>
                    </div>
                `;
            });

            return html;
        }
    }

    /**
     * What-If Calculator Widget
     * Interactive calculator for scenario analysis
     */
    class WhatIfCalculatorWidget extends DashboardWidget {
        constructor(config) {
            super(config);
            this.scenarios = [];
            this.currentScenario = null;
        }

        render() {
            if (!this.element) return;

            this.element.innerHTML = `
                <div class="what-if-container">
                    <div class="what-if-inputs">
                        <h5>Adjust Parameters</h5>
                        <div class="input-group">
                            <label>Purchase Price</label>
                            <input type="number" id="what-if-price" class="form-control" value="${this.data.askingPrice || 0}">
                        </div>
                        <div class="input-group">
                            <label>Target Multiple</label>
                            <input type="number" id="what-if-multiple" class="form-control" step="0.1" value="${this.data.targetMultiple || 3.5}">
                        </div>
                        <div class="input-group">
                            <label>Revenue Growth %</label>
                            <input type="number" id="what-if-growth" class="form-control" step="0.1" value="${this.data.growthRate || 3}">
                        </div>
                        <div class="input-group">
                            <label>Equity % <span id="equity-percent-display">30%</span></label>
                            <input type="range" id="what-if-equity" min="0" max="100" value="30">
                        </div>
                        <button class="btn btn-primary" onclick="FinancialDashboard.calculateWhatIf()">Calculate</button>
                    </div>
                    <div class="what-if-results">
                        <h5>Projected Results</h5>
                        <div id="what-if-results-container">
                            <!-- Results populated by calculation -->
                        </div>
                    </div>
                </div>
            `;

            this.setupEventListeners();
        }

        setupEventListeners() {
            const equitySlider = document.getElementById('what-if-equity');
            const equityDisplay = document.getElementById('equity-percent-display');
            
            if (equitySlider && equityDisplay) {
                equitySlider.addEventListener('input', (e) => {
                    equityDisplay.textContent = e.target.value + '%';
                });
            }
        }

        calculateScenario() {
            const price = parseFloat(document.getElementById('what-if-price').value) || 0;
            const multiple = parseFloat(document.getElementById('what-if-multiple').value) || 3.5;
            const growthRate = parseFloat(document.getElementById('what-if-growth').value) / 100 || 0.03;
            const equityPercent = parseFloat(document.getElementById('what-if-equity').value) / 100 || 0.3;

            // Get base metrics from calculation engine
            const baseData = Object.assign({}, this.data, {
                askingPrice: price,
                targetMultiple: multiple,
                growthRate: growthRate,
                equityInvestment: price * equityPercent
            });

            const calculations = global.FinancialDashboard.CalculationEngine.calculateAll(baseData);
            
            this.displayResults(calculations, {
                price: price,
                equityPercent: equityPercent,
                debtPercent: 1 - equityPercent
            });
        }

        displayResults(calculations, scenario) {
            const resultsContainer = document.getElementById('what-if-results-container');
            if (!resultsContainer) return;

            let html = `
                <div class="scenario-results">
                    <div class="result-item">
                        <span class="label">Equity Required</span>
                        <span class="value">${this.formatCurrency(scenario.price * scenario.equityPercent)}</span>
                    </div>
                    <div class="result-item">
                        <span class="label">Debt Required</span>
                        <span class="value">${this.formatCurrency(scenario.price * scenario.debtPercent)}</span>
                    </div>
                    <div class="result-item">
                        <span class="label">Implied Multiple</span>
                        <span class="value">${calculations.impliedMultiple.formatted}</span>
                    </div>
                    <div class="result-item">
                        <span class="label">DSCR</span>
                        <span class="value">${calculations.debtServiceCoverageRatio.formatted}</span>
                    </div>
                    <div class="result-item">
                        <span class="label">ROI</span>
                        <span class="value">${calculations.returnOnInvestment.formatted}</span>
                    </div>
                    <div class="result-item">
                        <span class="label">Break-Even Multiple</span>
                        <span class="value">${calculations.breakEvenMultiple.formatted}</span>
                    </div>
                </div>
            `;

            resultsContainer.innerHTML = html;
        }
    }

    // Register widget types
    if (global.FinancialDashboard.manager) {
        const registry = global.FinancialDashboard.manager.registry;
        registry.registerType('keyMetric', KeyMetricWidget);
        registry.registerType('capitalStack', CapitalStackWidget);
        registry.registerType('debtCoverage', DebtCoverageWidget);
        registry.registerType('comparables', ComparablesWidget);
        registry.registerType('whatIfCalculator', WhatIfCalculatorWidget);
    }

    // Export widget classes
    global.FinancialDashboard.KeyMetricWidget = KeyMetricWidget;
    global.FinancialDashboard.CapitalStackWidget = CapitalStackWidget;
    global.FinancialDashboard.DebtCoverageWidget = DebtCoverageWidget;
    global.FinancialDashboard.ComparablesWidget = ComparablesWidget;
    global.FinancialDashboard.WhatIfCalculatorWidget = WhatIfCalculatorWidget;

    // Global function for what-if calculation
    global.FinancialDashboard.calculateWhatIf = function() {
        const widget = global.FinancialDashboard.manager.registry.widgets.get('what-if-calculator');
        if (widget) {
            widget.calculateScenario();
        }
    };

})(window);