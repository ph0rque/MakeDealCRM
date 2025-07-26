/**
 * Enhanced Assertions Helper
 * Provides comprehensive assertion utilities for E2E tests including:
 * - UI state change assertions
 * - Data persistence verification 
 * - Audit log verification
 * - Performance assertions
 * - Visual regression testing
 */

const { expect } = require('@playwright/test');
const mysql = require('mysql2/promise');
const path = require('path');
const fs = require('fs');

class AssertionsHelper {
  constructor(page) {
    this.page = page;
    this.dbConfig = {
      host: process.env.DB_HOST || 'localhost',
      user: process.env.DB_USER || 'suitecrm',
      password: process.env.DB_PASSWORD || 'suitecrm123',
      database: process.env.DB_NAME || 'suitecrm',
      port: process.env.DB_PORT || 3306
    };
    this.performanceThresholds = {
      pageLoad: 5000,
      apiResponse: 2000,
      domReady: 3000,
      firstPaint: 2000
    };
  }

  /**
   * Assert element is visible
   * @param {string} selector - Element selector
   * @param {string} message - Optional assertion message
   * @returns {Promise<void>}
   */
  async assertVisible(selector, message) {
    await expect(this.page.locator(selector), message).toBeVisible();
  }

  /**
   * Assert element is hidden
   * @param {string} selector - Element selector
   * @param {string} message - Optional assertion message
   * @returns {Promise<void>}
   */
  async assertHidden(selector, message) {
    await expect(this.page.locator(selector), message).toBeHidden();
  }

  /**
   * Assert element contains text
   * @param {string} selector - Element selector
   * @param {string} text - Expected text
   * @param {Object} options - Options
   * @returns {Promise<void>}
   */
  async assertText(selector, text, options = {}) {
    const { exact = false, message } = options;
    
    if (exact) {
      await expect(this.page.locator(selector), message).toHaveText(text);
    } else {
      await expect(this.page.locator(selector), message).toContainText(text);
    }
  }

  /**
   * Assert element has value
   * @param {string} selector - Element selector
   * @param {string} value - Expected value
   * @param {string} message - Optional assertion message
   * @returns {Promise<void>}
   */
  async assertValue(selector, value, message) {
    await expect(this.page.locator(selector), message).toHaveValue(value);
  }

  /**
   * Assert element is enabled
   * @param {string} selector - Element selector
   * @param {string} message - Optional assertion message
   * @returns {Promise<void>}
   */
  async assertEnabled(selector, message) {
    await expect(this.page.locator(selector), message).toBeEnabled();
  }

  /**
   * Assert element is disabled
   * @param {string} selector - Element selector
   * @param {string} message - Optional assertion message
   * @returns {Promise<void>}
   */
  async assertDisabled(selector, message) {
    await expect(this.page.locator(selector), message).toBeDisabled();
  }

  /**
   * Assert element count
   * @param {string} selector - Element selector
   * @param {number} count - Expected count
   * @param {string} message - Optional assertion message
   * @returns {Promise<void>}
   */
  async assertCount(selector, count, message) {
    await expect(this.page.locator(selector), message).toHaveCount(count);
  }

  /**
   * Assert URL matches
   * @param {string|RegExp} url - Expected URL or pattern
   * @param {string} message - Optional assertion message
   * @returns {Promise<void>}
   */
  async assertUrl(url, message) {
    await expect(this.page, message).toHaveURL(url);
  }

  /**
   * Assert page title
   * @param {string|RegExp} title - Expected title or pattern
   * @param {string} message - Optional assertion message
   * @returns {Promise<void>}
   */
  async assertTitle(title, message) {
    await expect(this.page, message).toHaveTitle(title);
  }

  /**
   * Assert element has class
   * @param {string} selector - Element selector
   * @param {string} className - Expected class name
   * @returns {Promise<void>}
   */
  async assertHasClass(selector, className) {
    const element = this.page.locator(selector);
    await expect(element).toHaveClass(new RegExp(className));
  }

  /**
   * Assert element attribute
   * @param {string} selector - Element selector
   * @param {string} attribute - Attribute name
   * @param {string} value - Expected value
   * @returns {Promise<void>}
   */
  async assertAttribute(selector, attribute, value) {
    const element = this.page.locator(selector);
    await expect(element).toHaveAttribute(attribute, value);
  }

  /**
   * Assert checkbox is checked
   * @param {string} selector - Checkbox selector
   * @param {string} message - Optional assertion message
   * @returns {Promise<void>}
   */
  async assertChecked(selector, message) {
    await expect(this.page.locator(selector), message).toBeChecked();
  }

  /**
   * Assert checkbox is unchecked
   * @param {string} selector - Checkbox selector
   * @param {string} message - Optional assertion message
   * @returns {Promise<void>}
   */
  async assertUnchecked(selector, message) {
    await expect(this.page.locator(selector), message).not.toBeChecked();
  }

  /**
   * Assert select option is selected
   * @param {string} selector - Select element selector
   * @param {string} value - Expected selected value
   * @returns {Promise<void>}
   */
  async assertSelectedOption(selector, value) {
    const selectedValue = await this.page.locator(selector).inputValue();
    expect(selectedValue).toBe(value);
  }

  /**
   * Assert element is focused
   * @param {string} selector - Element selector
   * @returns {Promise<void>}
   */
  async assertFocused(selector) {
    await expect(this.page.locator(selector)).toBeFocused();
  }

  /**
   * Assert screenshot matches baseline
   * @param {string} name - Screenshot name
   * @param {Object} options - Screenshot options
   * @returns {Promise<void>}
   */
  async assertScreenshot(name, options = {}) {
    await expect(this.page).toHaveScreenshot(name, options);
  }

  /**
   * Assert element screenshot matches baseline
   * @param {string} selector - Element selector
   * @param {string} name - Screenshot name
   * @param {Object} options - Screenshot options
   * @returns {Promise<void>}
   */
  async assertElementScreenshot(selector, name, options = {}) {
    await expect(this.page.locator(selector)).toHaveScreenshot(name, options);
  }

  /**
   * Assert toast/notification message
   * @param {string} message - Expected message
   * @param {string} type - Message type (success, error, warning, info)
   * @returns {Promise<void>}
   */
  async assertToastMessage(message, type = 'success') {
    const toastSelectors = {
      success: '.alert-success, .toast-success, .notification-success',
      error: '.alert-danger, .toast-error, .notification-error',
      warning: '.alert-warning, .toast-warning, .notification-warning',
      info: '.alert-info, .toast-info, .notification-info'
    };

    const selector = toastSelectors[type] || toastSelectors.success;
    await this.assertVisible(selector);
    await this.assertText(selector, message);
  }

  /**
   * Assert no console errors
   * @param {Array} ignoredErrors - Array of error patterns to ignore
   * @returns {Promise<void>}
   */
  async assertNoConsoleErrors(ignoredErrors = []) {
    const messages = [];
    
    this.page.on('console', msg => {
      if (msg.type() === 'error') {
        const text = msg.text();
        const shouldIgnore = ignoredErrors.some(pattern => 
          text.includes(pattern) || new RegExp(pattern).test(text)
        );
        
        if (!shouldIgnore) {
          messages.push(text);
        }
      }
    });

    // Wait a bit for any console errors
    await this.page.waitForTimeout(1000);

    expect(messages, `Console errors found: ${messages.join(', ')}`).toHaveLength(0);
  }

  /**
   * Assert element matches accessibility standards
   * @param {string} selector - Element selector
   * @param {Object} options - Accessibility options
   * @returns {Promise<void>}
   */
  async assertAccessible(selector, options = {}) {
    const element = this.page.locator(selector);
    
    // Check for basic accessibility attributes
    const role = await element.getAttribute('role');
    const ariaLabel = await element.getAttribute('aria-label');
    const ariaLabelledBy = await element.getAttribute('aria-labelledby');
    
    if (options.requireLabel) {
      expect(
        ariaLabel || ariaLabelledBy,
        `Element ${selector} should have aria-label or aria-labelledby`
      ).toBeTruthy();
    }

    if (options.requireRole) {
      expect(role, `Element ${selector} should have a role attribute`).toBeTruthy();
    }
  }

  /**
   * Custom assertion wrapper for complex checks
   * @param {Function} checkFunction - Function that returns boolean
   * @param {string} message - Assertion message
   * @param {Object} options - Options
   * @returns {Promise<void>}
   */
  async assertCustom(checkFunction, message, options = {}) {
    const { timeout = 5000, polling = 100 } = options;
    
    await expect(async () => {
      const result = await checkFunction();
      expect(result, message).toBeTruthy();
    }).toPass({ timeout, intervals: [polling] });
  }

  // ========================
  // UI STATE CHANGE ASSERTIONS
  // ========================

  /**
   * Assert that an element's visibility state changed
   * @param {string} selector - Element selector
   * @param {boolean} expectedVisible - Expected visibility state
   * @param {Object} options - Options
   * @returns {Promise<void>}
   */
  async assertVisibilityChange(selector, expectedVisible, options = {}) {
    const { timeout = 5000, message } = options;
    
    if (expectedVisible) {
      await expect(this.page.locator(selector), message || `Element ${selector} should become visible`)
        .toBeVisible({ timeout });
    } else {
      await expect(this.page.locator(selector), message || `Element ${selector} should become hidden`)
        .toBeHidden({ timeout });
    }
  }

  /**
   * Assert that text content has been updated
   * @param {string} selector - Element selector
   * @param {string} expectedText - Expected text content
   * @param {Object} options - Options
   * @returns {Promise<void>}
   */
  async assertTextUpdate(selector, expectedText, options = {}) {
    const { timeout = 5000, exact = false, message } = options;
    
    await expect(async () => {
      const element = this.page.locator(selector);
      if (exact) {
        await expect(element).toHaveText(expectedText);
      } else {
        await expect(element).toContainText(expectedText);
      }
    }, message || `Text should update to contain "${expectedText}"`)
      .toPass({ timeout, intervals: [100] });
  }

  /**
   * Assert form state changes (enabled/disabled, validation states)
   * @param {string} formSelector - Form selector
   * @param {Object} expectedState - Expected form state
   * @param {Object} options - Options
   * @returns {Promise<void>}
   */
  async assertFormState(formSelector, expectedState, options = {}) {
    const { timeout = 3000 } = options;
    const form = this.page.locator(formSelector);
    
    if (expectedState.enabled !== undefined) {
      if (expectedState.enabled) {
        await expect(form).toBeEnabled({ timeout });
      } else {
        await expect(form).toBeDisabled({ timeout });
      }
    }
    
    if (expectedState.valid !== undefined) {
      const validationClass = expectedState.valid ? ':not(.error):not(.invalid)' : '.error, .invalid';
      await expect(form.locator(validationClass)).toBeVisible({ timeout });
    }
    
    if (expectedState.fields) {
      for (const [fieldName, fieldState] of Object.entries(expectedState.fields)) {
        const field = form.locator(`[name="${fieldName}"]`);
        
        if (fieldState.value !== undefined) {
          await expect(field).toHaveValue(fieldState.value);
        }
        
        if (fieldState.required !== undefined) {
          if (fieldState.required) {
            await expect(field).toHaveAttribute('required');
          } else {
            await expect(field).not.toHaveAttribute('required');
          }
        }
      }
    }
  }

  /**
   * Assert loading states and spinners
   * @param {string} containerSelector - Container to check for loading state
   * @param {boolean} shouldBeLoading - Whether loading state should be active
   * @param {Object} options - Options
   * @returns {Promise<void>}
   */
  async assertLoadingState(containerSelector, shouldBeLoading, options = {}) {
    const { timeout = 3000, spinnerSelector = '.loading, .spinner, .fa-spinner' } = options;
    const container = this.page.locator(containerSelector);
    
    if (shouldBeLoading) {
      await expect(container.locator(spinnerSelector))
        .toBeVisible({ timeout });
    } else {
      await expect(container.locator(spinnerSelector))
        .toBeHidden({ timeout });
    }
  }

  /**
   * Assert modal/dialog state changes
   * @param {boolean} shouldBeOpen - Whether modal should be open
   * @param {Object} options - Options
   * @returns {Promise<void>}
   */
  async assertModalState(shouldBeOpen, options = {}) {
    const { 
      timeout = 3000, 
      modalSelector = '.modal, .dialog, .popup, .yui-panel',
      backdropSelector = '.modal-backdrop, .dialog-backdrop'
    } = options;
    
    if (shouldBeOpen) {
      await expect(this.page.locator(modalSelector)).toBeVisible({ timeout });
      if (backdropSelector) {
        await expect(this.page.locator(backdropSelector)).toBeVisible({ timeout });
      }
    } else {
      await expect(this.page.locator(modalSelector)).toBeHidden({ timeout });
      if (backdropSelector) {
        await expect(this.page.locator(backdropSelector)).toBeHidden({ timeout });
      }
    }
  }

  /**
   * Assert drag and drop state changes
   * @param {string} dragSelector - Draggable element selector
   * @param {string} dropSelector - Drop target selector
   * @param {Object} options - Options
   * @returns {Promise<void>}
   */
  async assertDragDropState(dragSelector, dropSelector, options = {}) {
    const { timeout = 3000 } = options;
    
    // Check if draggable element has drag state classes
    const dragElement = this.page.locator(dragSelector);
    await expect(dragElement).toHaveClass(/draggable|sortable/, { timeout });
    
    // Check if drop target has appropriate drop state
    const dropElement = this.page.locator(dropSelector);
    await expect(dropElement).toHaveClass(/droppable|drop-target/, { timeout });
  }

  // ========================
  // DATA PERSISTENCE VERIFICATION
  // ========================

  /**
   * Get database connection
   * @returns {Promise<mysql.Connection>}
   */
  async getDbConnection() {
    try {
      return await mysql.createConnection(this.dbConfig);
    } catch (error) {
      console.warn('Database connection failed:', error.message);
      throw error;
    }
  }

  /**
   * Assert data exists in database
   * @param {string} table - Table name
   * @param {Object} conditions - Where conditions
   * @param {Object} options - Options
   * @returns {Promise<void>}
   */
  async assertDataPersistence(table, conditions, options = {}) {
    const { timeout = 10000, message } = options;
    
    await expect(async () => {
      const connection = await this.getDbConnection();
      
      try {
        const whereClause = Object.keys(conditions)
          .map(key => `${key} = ?`)
          .join(' AND ');
        
        const query = `SELECT COUNT(*) as count FROM ${table} WHERE ${whereClause}`;
        const [rows] = await connection.execute(query, Object.values(conditions));
        
        expect(rows[0].count, message || `Data should exist in ${table}`).
          toBeGreaterThan(0);
      } finally {
        await connection.end();
      }
    }).toPass({ timeout, intervals: [1000] });
  }

  /**
   * Assert relationship integrity between entities
   * @param {string} parentTable - Parent table name
   * @param {string} childTable - Child table name
   * @param {string} relationshipField - Foreign key field
   * @param {string} parentId - Parent record ID
   * @param {Object} options - Options
   * @returns {Promise<void>}
   */
  async assertRelationshipIntegrity(parentTable, childTable, relationshipField, parentId, options = {}) {
    const { expectedCount, message } = options;
    
    const connection = await this.getDbConnection();
    
    try {
      // Check parent exists
      const [parentRows] = await connection.execute(
        `SELECT COUNT(*) as count FROM ${parentTable} WHERE id = ?`,
        [parentId]
      );
      
      expect(parentRows[0].count, `Parent record should exist in ${parentTable}`)
        .toBeGreaterThan(0);
      
      // Check children have correct relationship
      const [childRows] = await connection.execute(
        `SELECT COUNT(*) as count FROM ${childTable} WHERE ${relationshipField} = ?`,
        [parentId]
      );
      
      if (expectedCount !== undefined) {
        expect(childRows[0].count, message || `Should have ${expectedCount} related records`)
          .toBe(expectedCount);
      } else {
        expect(childRows[0].count, message || `Should have related records`)
          .toBeGreaterThan(0);
      }
    } finally {
      await connection.end();
    }
  }

  /**
   * Assert record field values in database
   * @param {string} table - Table name
   * @param {string} recordId - Record ID
   * @param {Object} expectedFields - Expected field values
   * @param {Object} options - Options
   * @returns {Promise<void>}
   */
  async assertRecordFields(table, recordId, expectedFields, options = {}) {
    const { message } = options;
    
    const connection = await this.getDbConnection();
    
    try {
      const fields = Object.keys(expectedFields).join(', ');
      const [rows] = await connection.execute(
        `SELECT ${fields} FROM ${table} WHERE id = ?`,
        [recordId]
      );
      
      expect(rows.length, `Record should exist in ${table}`).toBeGreaterThan(0);
      
      const record = rows[0];
      for (const [field, expectedValue] of Object.entries(expectedFields)) {
        expect(record[field], message || `Field ${field} should have correct value`)
          .toBe(expectedValue);
      }
    } finally {
      await connection.end();
    }
  }

  // ========================
  // AUDIT LOG VERIFICATION
  // ========================

  /**
   * Assert audit log entry exists
   * @param {string} module - Module name (e.g., 'Deals', 'Contacts')
   * @param {string} recordId - Record ID
   * @param {string} action - Action type (create, update, delete)
   * @param {Object} options - Options
   * @returns {Promise<void>}
   */
  async assertAuditLogEntry(module, recordId, action, options = {}) {
    const { timeout = 10000, userId, fieldChanges } = options;
    
    await expect(async () => {
      const connection = await this.getDbConnection();
      
      try {
        let query = `
          SELECT * FROM audit 
          WHERE parent_id = ? 
          AND module_name = ? 
          AND action = ?
        `;
        const params = [recordId, module, action];
        
        if (userId) {
          query += ' AND created_by = ?';
          params.push(userId);
        }
        
        query += ' ORDER BY date_created DESC LIMIT 1';
        
        const [rows] = await connection.execute(query, params);
        
        expect(rows.length, `Audit log entry should exist for ${action} on ${module}:${recordId}`)
          .toBeGreaterThan(0);
        
        if (fieldChanges) {
          const auditEntry = rows[0];
          for (const [field, expectedChange] of Object.entries(fieldChanges)) {
            expect(auditEntry.field_name).toBe(field);
            if (expectedChange.before !== undefined) {
              expect(auditEntry.before_value).toBe(expectedChange.before);
            }
            if (expectedChange.after !== undefined) {
              expect(auditEntry.after_value).toBe(expectedChange.after);
            }
          }
        }
      } finally {
        await connection.end();
      }
    }).toPass({ timeout, intervals: [1000] });
  }

  /**
   * Assert activity timeline entry exists
   * @param {string} module - Module name
   * @param {string} recordId - Record ID
   * @param {string} activityType - Activity type
   * @param {Object} options - Options
   * @returns {Promise<void>}
   */
  async assertActivityTimelineEntry(module, recordId, activityType, options = {}) {
    const { timeout = 5000, description } = options;
    
    await expect(async () => {
      const connection = await this.getDbConnection();
      
      try {
        let query = `
          SELECT * FROM activities 
          WHERE parent_type = ? 
          AND parent_id = ? 
          AND activity_type = ?
        `;
        const params = [module, recordId, activityType];
        
        if (description) {
          query += ' AND description LIKE ?';
          params.push(`%${description}%`);
        }
        
        query += ' ORDER BY date_created DESC LIMIT 1';
        
        const [rows] = await connection.execute(query, params);
        
        expect(rows.length, `Activity timeline entry should exist for ${activityType}`)
          .toBeGreaterThan(0);
      } finally {
        await connection.end();
      }
    }).toPass({ timeout, intervals: [1000] });
  }

  /**
   * Assert change history tracking
   * @param {string} recordId - Record ID
   * @param {string} fieldName - Field that changed
   * @param {string} oldValue - Previous value
   * @param {string} newValue - New value
   * @param {Object} options - Options
   * @returns {Promise<void>}
   */
  async assertChangeHistory(recordId, fieldName, oldValue, newValue, options = {}) {
    const { timeout = 5000 } = options;
    
    await expect(async () => {
      const connection = await this.getDbConnection();
      
      try {
        const [rows] = await connection.execute(`
          SELECT * FROM audit 
          WHERE parent_id = ? 
          AND field_name = ? 
          AND before_value = ? 
          AND after_value = ?
          ORDER BY date_created DESC 
          LIMIT 1
        `, [recordId, fieldName, oldValue, newValue]);
        
        expect(rows.length, `Change history should exist for ${fieldName} change`)
          .toBeGreaterThan(0);
      } finally {
        await connection.end();
      }
    }).toPass({ timeout, intervals: [1000] });
  }

  // ========================
  // PERFORMANCE ASSERTIONS
  // ========================

  /**
   * Assert page load performance
   * @param {Object} options - Options
   * @returns {Promise<void>}
   */
  async assertPageLoadPerformance(options = {}) {
    const { threshold = this.performanceThresholds.pageLoad } = options;
    
    const timing = await this.page.evaluate(() => {
      const perf = performance.getEntriesByType('navigation')[0];
      return {
        loadComplete: perf.loadEventEnd - perf.navigationStart,
        domReady: perf.domContentLoadedEventEnd - perf.navigationStart,
        firstPaint: performance.getEntriesByName('first-paint')[0]?.startTime || 0
      };
    });
    
    expect(timing.loadComplete, `Page should load within ${threshold}ms`)
      .toBeLessThan(threshold);
    
    if (timing.firstPaint > 0) {
      expect(timing.firstPaint, `First paint should occur within ${this.performanceThresholds.firstPaint}ms`)
        .toBeLessThan(this.performanceThresholds.firstPaint);
    }
  }

  /**
   * Assert API response performance
   * @param {string} apiEndpoint - API endpoint pattern to monitor
   * @param {Object} options - Options
   * @returns {Promise<void>}
   */
  async assertApiResponsePerformance(apiEndpoint, options = {}) {
    const { threshold = this.performanceThresholds.apiResponse } = options;
    
    // Set up response monitoring
    const responsePromise = this.page.waitForResponse(
      response => response.url().includes(apiEndpoint),
      { timeout: threshold + 1000 }
    );
    
    const startTime = Date.now();
    const response = await responsePromise;
    const endTime = Date.now();
    
    const responseTime = endTime - startTime;
    
    expect(responseTime, `API ${apiEndpoint} should respond within ${threshold}ms`)
      .toBeLessThan(threshold);
    
    expect(response.status(), `API ${apiEndpoint} should return successful status`)
      .toBeGreaterThanOrEqual(200);
    expect(response.status(), `API ${apiEndpoint} should return successful status`)
      .toBeLessThan(400);
  }

  /**
   * Assert DOM manipulation performance
   * @param {Function} operation - Operation to measure
   * @param {Object} options - Options
   * @returns {Promise<void>}
   */
  async assertDomPerformance(operation, options = {}) {
    const { threshold = 1000, operationName = 'DOM operation' } = options;
    
    const startTime = performance.now();
    await operation();
    const endTime = performance.now();
    
    const duration = endTime - startTime;
    
    expect(duration, `${operationName} should complete within ${threshold}ms`)
      .toBeLessThan(threshold);
  }

  /**
   * Assert memory usage is within acceptable limits
   * @param {Object} options - Options
   * @returns {Promise<void>}
   */
  async assertMemoryUsage(options = {}) {
    const { maxHeapSize = 100 * 1024 * 1024 } = options; // 100MB default
    
    const memoryInfo = await this.page.evaluate(() => {
      if (performance.memory) {
        return {
          usedJSMemory: performance.memory.usedJSMemory,
          totalJSMemory: performance.memory.totalJSMemory,
          jsMemoryLimit: performance.memory.jsMemoryLimit
        };
      }
      return null;
    });
    
    if (memoryInfo) {
      expect(memoryInfo.usedJSMemory, `Used JS memory should be within limits`)
        .toBeLessThan(maxHeapSize);
    }
  }

}

module.exports = AssertionsHelper;