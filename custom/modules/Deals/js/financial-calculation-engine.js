/**
 * Financial Calculation Engine
 * Core calculation engine for TTM Revenue, TTM EBITDA, SDE, and valuation metrics
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 */

(function(global) {
    'use strict';

    // Ensure namespace exists
    global.FinancialDashboard = global.FinancialDashboard || {};

    /**
     * Financial Calculation Engine
     * Handles all financial metric calculations with configurable formulas
     */
    class FinancialCalculationEngine {
        constructor() {
            this.cache = new Map();
            this.cacheTimeout = 5 * 60 * 1000; // 5 minutes
            this.formulas = this.initializeFormulas();
            this.precision = {
                currency: 0,
                percentage: 1,
                multiple: 2
            };
        }

        initializeFormulas() {
            return {
                ttmRevenue: {
                    name: 'TTM Revenue',
                    calculate: (data) => {
                        // Sum of last 12 months revenue
                        if (data.monthlyRevenue && Array.isArray(data.monthlyRevenue)) {
                            return data.monthlyRevenue.slice(-12).reduce((sum, month) => sum + (month || 0), 0);
                        }
                        return data.annualRevenue || 0;
                    },
                    validate: (data) => {
                        return data && (data.monthlyRevenue || data.annualRevenue);
                    }
                },
                
                ttmEbitda: {
                    name: 'TTM EBITDA',
                    calculate: (data) => {
                        // Revenue - Operating Expenses (excluding interest, taxes, depreciation, amortization)
                        const revenue = this.calculate('ttmRevenue', data);
                        const operatingExpenses = data.operatingExpenses || (revenue * 0.7); // Default 70% of revenue
                        const addBacks = data.addBacks || 0;
                        return revenue - operatingExpenses + addBacks;
                    },
                    validate: (data) => {
                        return data && this.formulas.ttmRevenue.validate(data);
                    }
                },
                
                sde: {
                    name: 'Seller\'s Discretionary Earnings',
                    calculate: (data) => {
                        // EBITDA + Owner Compensation + Owner Benefits + Non-essential expenses
                        const ebitda = this.calculate('ttmEbitda', data);
                        const ownerCompensation = data.ownerCompensation || 0;
                        const ownerBenefits = data.ownerBenefits || 0;
                        const nonEssentialExpenses = data.nonEssentialExpenses || 0;
                        return ebitda + ownerCompensation + ownerBenefits + nonEssentialExpenses;
                    },
                    validate: (data) => {
                        return data && this.formulas.ttmEbitda.validate(data);
                    }
                },
                
                ebitdaMargin: {
                    name: 'EBITDA Margin',
                    calculate: (data) => {
                        const revenue = this.calculate('ttmRevenue', data);
                        const ebitda = this.calculate('ttmEbitda', data);
                        return revenue > 0 ? (ebitda / revenue) * 100 : 0;
                    },
                    validate: (data) => {
                        return data && this.formulas.ttmRevenue.validate(data);
                    }
                },
                
                proposedValuation: {
                    name: 'Proposed Valuation',
                    calculate: (data) => {
                        // Multiple * EBITDA or SDE based on business type
                        const multiple = data.targetMultiple || data.industryMultiple || 3.5;
                        const valuationBasis = data.valuationMethod === 'sde' ? 
                            this.calculate('sde', data) : 
                            this.calculate('ttmEbitda', data);
                        return valuationBasis * multiple;
                    },
                    validate: (data) => {
                        return data && (data.targetMultiple || data.industryMultiple);
                    }
                },
                
                impliedMultiple: {
                    name: 'Implied Multiple',
                    calculate: (data) => {
                        const askingPrice = data.askingPrice || 0;
                        const ebitda = this.calculate('ttmEbitda', data);
                        return ebitda > 0 ? askingPrice / ebitda : 0;
                    },
                    validate: (data) => {
                        return data && data.askingPrice && this.formulas.ttmEbitda.validate(data);
                    }
                },
                
                debtServiceCoverageRatio: {
                    name: 'Debt Service Coverage Ratio',
                    calculate: (data) => {
                        // (EBITDA - CapEx - Taxes) / Total Debt Service
                        const ebitda = this.calculate('ttmEbitda', data);
                        const capEx = data.capitalExpenditures || 0;
                        const taxes = data.estimatedTaxes || (ebitda * 0.25); // Default 25% tax rate
                        const debtService = this.calculateTotalDebtService(data);
                        
                        const netCashFlow = ebitda - capEx - taxes;
                        return debtService > 0 ? netCashFlow / debtService : 0;
                    },
                    validate: (data) => {
                        return data && this.formulas.ttmEbitda.validate(data) && data.debtStructure;
                    }
                },
                
                returnOnInvestment: {
                    name: 'Return on Investment',
                    calculate: (data) => {
                        // (Annual Cash Flow / Total Investment) * 100
                        const sde = this.calculate('sde', data);
                        const normalizedSalary = data.normalizedSalary || 50000; // Default normalized salary
                        const annualCashFlow = sde - normalizedSalary;
                        const totalInvestment = data.equityInvestment || data.askingPrice || 0;
                        
                        return totalInvestment > 0 ? (annualCashFlow / totalInvestment) * 100 : 0;
                    },
                    validate: (data) => {
                        return data && (data.equityInvestment || data.askingPrice);
                    }
                },
                
                workingCapitalRequirement: {
                    name: 'Working Capital Requirement',
                    calculate: (data) => {
                        // (Current Assets - Current Liabilities) or percentage of revenue
                        if (data.currentAssets && data.currentLiabilities) {
                            return data.currentAssets - data.currentLiabilities;
                        }
                        // Default: 10% of annual revenue
                        const revenue = this.calculate('ttmRevenue', data);
                        return revenue * 0.1;
                    },
                    validate: (data) => {
                        return data && this.formulas.ttmRevenue.validate(data);
                    }
                },
                
                breakEvenMultiple: {
                    name: 'Break-Even Multiple',
                    calculate: (data) => {
                        // Multiple at which investment equals cash flows over hold period
                        const holdPeriod = data.holdPeriod || 5; // Default 5 years
                        const annualCashFlow = this.calculate('sde', data) - (data.normalizedSalary || 50000);
                        const growthRate = data.growthRate || 0.03; // Default 3% growth
                        
                        let totalCashFlows = 0;
                        for (let i = 1; i <= holdPeriod; i++) {
                            totalCashFlows += annualCashFlow * Math.pow(1 + growthRate, i);
                        }
                        
                        const ebitda = this.calculate('ttmEbitda', data);
                        return ebitda > 0 ? totalCashFlows / ebitda : 0;
                    },
                    validate: (data) => {
                        return data && this.formulas.ttmEbitda.validate(data);
                    }
                }
            };
        }

        calculate(metric, data) {
            // Check cache first
            const cacheKey = this.getCacheKey(metric, data);
            const cached = this.getFromCache(cacheKey);
            if (cached !== null) {
                return cached;
            }

            // Validate data
            if (!this.formulas[metric]) {
                throw new Error(`Unknown metric: ${metric}`);
            }

            const formula = this.formulas[metric];
            if (!formula.validate(data)) {
                console.warn(`Invalid data for metric ${metric}:`, data);
                return 0;
            }

            try {
                // Perform calculation
                const result = formula.calculate(data);
                
                // Apply precision rounding
                const rounded = this.applyPrecision(result, metric);
                
                // Cache result
                this.setCache(cacheKey, rounded);
                
                return rounded;
            } catch (error) {
                console.error(`Error calculating ${metric}:`, error);
                return 0;
            }
        }

        calculateAll(data) {
            const results = {};
            
            Object.keys(this.formulas).forEach(metric => {
                try {
                    results[metric] = {
                        value: this.calculate(metric, data),
                        name: this.formulas[metric].name,
                        formatted: this.formatValue(this.calculate(metric, data), metric)
                    };
                } catch (error) {
                    results[metric] = {
                        value: 0,
                        name: this.formulas[metric].name,
                        formatted: 'N/A',
                        error: error.message
                    };
                }
            });
            
            return results;
        }

        calculateTotalDebtService(data) {
            if (!data.debtStructure) return 0;
            
            let totalDebtService = 0;
            
            // Senior Debt
            if (data.debtStructure.seniorDebt) {
                const principal = data.debtStructure.seniorDebt.amount || 0;
                const rate = data.debtStructure.seniorDebt.rate || 0;
                const term = data.debtStructure.seniorDebt.term || 0;
                totalDebtService += this.calculateDebtPayment(principal, rate, term);
            }
            
            // Seller Note
            if (data.debtStructure.sellerNote) {
                const principal = data.debtStructure.sellerNote.amount || 0;
                const rate = data.debtStructure.sellerNote.rate || 0;
                const term = data.debtStructure.sellerNote.term || 0;
                totalDebtService += this.calculateDebtPayment(principal, rate, term);
            }
            
            // Other Debt
            if (data.debtStructure.otherDebt) {
                totalDebtService += data.debtStructure.otherDebt.annualPayment || 0;
            }
            
            return totalDebtService;
        }

        calculateDebtPayment(principal, annualRate, termYears) {
            if (principal <= 0 || annualRate <= 0 || termYears <= 0) return 0;
            
            const monthlyRate = annualRate / 12 / 100;
            const months = termYears * 12;
            
            const payment = principal * (monthlyRate * Math.pow(1 + monthlyRate, months)) / 
                           (Math.pow(1 + monthlyRate, months) - 1);
            
            return payment * 12; // Annual payment
        }

        applyPrecision(value, metric) {
            let precision = 0;
            
            if (metric.includes('Margin') || metric.includes('Ratio') || metric.includes('Return')) {
                precision = this.precision.percentage;
            } else if (metric.includes('Multiple')) {
                precision = this.precision.multiple;
            } else {
                precision = this.precision.currency;
            }
            
            return Math.round(value * Math.pow(10, precision)) / Math.pow(10, precision);
        }

        formatValue(value, metric) {
            if (metric.includes('Margin') || metric.includes('Return')) {
                return value.toFixed(1) + '%';
            } else if (metric.includes('Multiple') || metric.includes('Ratio')) {
                return value.toFixed(2) + 'x';
            } else {
                return '$' + value.toLocaleString('en-US', { maximumFractionDigits: 0 });
            }
        }

        // Caching methods
        getCacheKey(metric, data) {
            return metric + '_' + JSON.stringify(data);
        }

        getFromCache(key) {
            const cached = this.cache.get(key);
            if (cached && Date.now() - cached.timestamp < this.cacheTimeout) {
                return cached.value;
            }
            return null;
        }

        setCache(key, value) {
            this.cache.set(key, {
                value: value,
                timestamp: Date.now()
            });
        }

        clearCache() {
            this.cache.clear();
        }

        // Custom formula methods
        addCustomFormula(name, formula) {
            if (typeof formula.calculate !== 'function' || typeof formula.validate !== 'function') {
                throw new Error('Custom formula must have calculate and validate functions');
            }
            
            this.formulas[name] = {
                name: formula.name || name,
                calculate: formula.calculate,
                validate: formula.validate
            };
        }

        removeCustomFormula(name) {
            if (this.formulas[name]) {
                delete this.formulas[name];
            }
        }

        // Validation methods
        validateDealData(data) {
            const errors = [];
            
            if (!data.annualRevenue && !data.monthlyRevenue) {
                errors.push('Revenue data is required');
            }
            
            if (data.askingPrice && data.askingPrice <= 0) {
                errors.push('Asking price must be greater than 0');
            }
            
            if (data.targetMultiple && (data.targetMultiple < 0 || data.targetMultiple > 20)) {
                errors.push('Target multiple must be between 0 and 20');
            }
            
            return {
                valid: errors.length === 0,
                errors: errors
            };
        }

        // Export calculations
        exportCalculations(data, format = 'json') {
            const calculations = this.calculateAll(data);
            
            if (format === 'csv') {
                let csv = 'Metric,Value,Formatted\n';
                Object.keys(calculations).forEach(metric => {
                    const calc = calculations[metric];
                    csv += `"${calc.name}",${calc.value},"${calc.formatted}"\n`;
                });
                return csv;
            }
            
            return JSON.stringify(calculations, null, 2);
        }
    }

    // Export to global namespace
    global.FinancialDashboard.CalculationEngine = new FinancialCalculationEngine();

})(window);