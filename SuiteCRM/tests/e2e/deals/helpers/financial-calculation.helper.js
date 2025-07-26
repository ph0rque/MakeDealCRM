/**
 * Financial Calculation Helpers
 * Utility functions for financial calculations and validations in E2E tests
 */

class FinancialCalculationHelper {
  /**
   * Calculate proposed valuation based on EBITDA and multiple
   * @param {number} ebitda - TTM EBITDA amount
   * @param {number} multiple - Target multiple
   * @returns {number} Calculated valuation
   */
  static calculateValuation(ebitda, multiple) {
    if (!ebitda || !multiple || ebitda <= 0 || multiple <= 0) {
      throw new Error('EBITDA and multiple must be positive numbers');
    }
    return ebitda * multiple;
  }

  /**
   * Format currency for display (US format)
   * @param {number} amount - Amount to format
   * @param {boolean} includeCents - Whether to include cents
   * @returns {string} Formatted currency string
   */
  static formatCurrency(amount, includeCents = false) {
    if (typeof amount !== 'number' || isNaN(amount)) {
      return '$0';
    }

    const options = {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: includeCents ? 2 : 0,
      maximumFractionDigits: includeCents ? 2 : 0
    };

    return new Intl.NumberFormat('en-US', options).format(amount);
  }

  /**
   * Parse currency string to number
   * @param {string} currencyString - Currency string like "$4,000,000" or "4,000,000"
   * @returns {number} Parsed number
   */
  static parseCurrency(currencyString) {
    if (!currencyString || typeof currencyString !== 'string') {
      return 0;
    }

    // Remove currency symbols, commas, and spaces
    const cleanString = currencyString.replace(/[$,\s]/g, '');
    const parsed = parseFloat(cleanString);
    
    return isNaN(parsed) ? 0 : parsed;
  }

  /**
   * Validate financial calculation within tolerance
   * @param {number} ebitda - TTM EBITDA
   * @param {number} multiple - Target multiple
   * @param {number} expectedValuation - Expected valuation
   * @param {number} tolerance - Acceptable tolerance (default: 1000)
   * @returns {boolean} True if calculation is correct within tolerance
   */
  static validateCalculation(ebitda, multiple, expectedValuation, tolerance = 1000) {
    try {
      const calculatedValue = this.calculateValuation(ebitda, multiple);
      return Math.abs(calculatedValue - expectedValuation) <= tolerance;
    } catch (error) {
      return false;
    }
  }

  /**
   * Calculate multiple from valuation and EBITDA
   * @param {number} valuation - Proposed valuation
   * @param {number} ebitda - TTM EBITDA
   * @returns {number} Calculated multiple (rounded to 2 decimals)
   */
  static calculateMultiple(valuation, ebitda) {
    if (!ebitda || ebitda <= 0) {
      throw new Error('EBITDA must be a positive number');
    }
    if (!valuation || valuation <= 0) {
      throw new Error('Valuation must be a positive number');
    }
    
    const multiple = valuation / ebitda;
    return Math.round(multiple * 100) / 100; // Round to 2 decimals
  }

  /**
   * Generate test financial scenarios
   * @returns {Array} Array of test scenarios with EBITDA, multiple, and expected valuation
   */
  static generateTestScenarios() {
    return [
      {
        name: 'Small Business',
        ebitda: 500000,
        multiple: 3,
        expectedValuation: 1500000,
        description: 'Small business with 3x multiple'
      },
      {
        name: 'Mid-Market',
        ebitda: 2000000,
        multiple: 5,
        expectedValuation: 10000000,
        description: 'Mid-market company with 5x multiple'
      },
      {
        name: 'Large Enterprise',
        ebitda: 10000000,
        multiple: 8,
        expectedValuation: 80000000,
        description: 'Large enterprise with 8x multiple'
      },
      {
        name: 'High Growth',
        ebitda: 1000000,
        multiple: 12,
        expectedValuation: 12000000,
        description: 'High growth company with 12x multiple'
      },
      {
        name: 'Value Play',
        ebitda: 3000000,
        multiple: 2.5,
        expectedValuation: 7500000,
        description: 'Value investment with 2.5x multiple'
      }
    ];
  }

  /**
   * Validate EBITDA input
   * @param {string|number} ebitda - EBITDA value to validate
   * @returns {Object} Validation result with isValid and message
   */
  static validateEbitda(ebitda) {
    const parsed = typeof ebitda === 'string' ? this.parseCurrency(ebitda) : ebitda;
    
    if (isNaN(parsed) || parsed === null || parsed === undefined) {
      return { isValid: false, message: 'EBITDA must be a valid number' };
    }
    
    if (parsed < 0) {
      return { isValid: false, message: 'EBITDA cannot be negative' };
    }
    
    if (parsed === 0) {
      return { isValid: false, message: 'EBITDA must be greater than zero for valuation calculation' };
    }
    
    if (parsed > 1000000000) { // 1 billion limit
      return { isValid: false, message: 'EBITDA value seems unreasonably high' };
    }
    
    return { isValid: true, message: 'Valid EBITDA' };
  }

  /**
   * Validate multiple input
   * @param {string|number} multiple - Multiple value to validate
   * @returns {Object} Validation result with isValid and message
   */
  static validateMultiple(multiple) {
    const parsed = typeof multiple === 'string' ? parseFloat(multiple) : multiple;
    
    if (isNaN(parsed) || parsed === null || parsed === undefined) {
      return { isValid: false, message: 'Multiple must be a valid number' };
    }
    
    if (parsed <= 0) {
      return { isValid: false, message: 'Multiple must be greater than zero' };
    }
    
    if (parsed > 50) { // Reasonable upper limit
      return { isValid: false, message: 'Multiple seems unreasonably high (>50x)' };
    }
    
    if (parsed < 0.1) {
      return { isValid: false, message: 'Multiple seems unreasonably low (<0.1x)' };
    }
    
    return { isValid: true, message: 'Valid multiple' };
  }

  /**
   * Generate random financial data for testing
   * @param {Object} options - Options for generation
   * @returns {Object} Generated financial data
   */
  static generateRandomFinancialData(options = {}) {
    const {
      minEbitda = 100000,
      maxEbitda = 10000000,
      minMultiple = 1,
      maxMultiple = 15
    } = options;

    const ebitda = Math.floor(Math.random() * (maxEbitda - minEbitda) + minEbitda);
    const multiple = Math.round((Math.random() * (maxMultiple - minMultiple) + minMultiple) * 10) / 10;
    const valuation = this.calculateValuation(ebitda, multiple);

    return {
      ebitda,
      multiple,
      valuation,
      formattedEbitda: this.formatCurrency(ebitda),
      formattedValuation: this.formatCurrency(valuation)
    };
  }

  /**
   * Compare two financial values with tolerance
   * @param {number} value1 - First value
   * @param {number} value2 - Second value
   * @param {number} tolerancePercent - Tolerance as percentage (default: 1%)
   * @returns {boolean} True if values are within tolerance
   */
  static compareWithTolerance(value1, value2, tolerancePercent = 1) {
    if (value1 === 0 && value2 === 0) return true;
    if (value1 === 0 || value2 === 0) return false;
    
    const difference = Math.abs(value1 - value2);
    const average = (value1 + value2) / 2;
    const percentDifference = (difference / average) * 100;
    
    return percentDifference <= tolerancePercent;
  }

  /**
   * Extract financial data from page text
   * @param {string} pageText - Text content from page
   * @returns {Object} Extracted financial data
   */
  static extractFinancialData(pageText) {
    const currencyRegex = /\$?([\d,]+(?:\.\d{2})?)/g;
    const matches = pageText.match(currencyRegex) || [];
    
    const values = matches.map(match => this.parseCurrency(match));
    
    return {
      foundValues: values,
      potentialEbitda: values.filter(v => v >= 10000 && v <= 100000000),
      potentialMultiples: values.filter(v => v >= 0.1 && v <= 50),
      potentialValuations: values.filter(v => v >= 100000 && v <= 1000000000)
    };
  }

  /**
   * Create test assertions for financial calculations
   * @param {Object} testData - Test data with ebitda, multiple, expected valuation
   * @returns {Object} Assertion methods
   */
  static createAssertions(testData) {
    return {
      /**
       * Assert that calculated valuation matches expected
       */
      assertCalculation: () => {
        const calculated = this.calculateValuation(testData.ebitda, testData.multiple);
        const isValid = Math.abs(calculated - testData.expectedValuation) < 1000;
        
        if (!isValid) {
          throw new Error(
            `Calculation mismatch: Expected ${testData.expectedValuation}, ` +
            `got ${calculated} (EBITDA: ${testData.ebitda}, Multiple: ${testData.multiple})`
          );
        }
        return true;
      },

      /**
       * Assert that parsed currency value matches expected
       */
      assertCurrencyParsing: (currencyString, expectedValue) => {
        const parsed = this.parseCurrency(currencyString);
        const isValid = Math.abs(parsed - expectedValue) < 1000;
        
        if (!isValid) {
          throw new Error(
            `Currency parsing mismatch: Expected ${expectedValue}, ` +
            `got ${parsed} from "${currencyString}"`
          );
        }
        return true;
      },

      /**
       * Assert that multiple calculation is correct
       */
      assertMultipleCalculation: (valuation, ebitda, expectedMultiple) => {
        const calculated = this.calculateMultiple(valuation, ebitda);
        const isValid = Math.abs(calculated - expectedMultiple) < 0.1;
        
        if (!isValid) {
          throw new Error(
            `Multiple calculation mismatch: Expected ${expectedMultiple}, ` +
            `got ${calculated} (Valuation: ${valuation}, EBITDA: ${ebitda})`
          );
        }
        return true;
      }
    };
  }
}

module.exports = FinancialCalculationHelper;