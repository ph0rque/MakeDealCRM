/**
 * Custom Playwright Matchers
 * Extends Playwright's expect functionality with domain-specific assertions
 */

const { expect } = require('@playwright/test');
const mysql = require('mysql2/promise');

/**
 * Extend Playwright's expect with custom matchers
 */
expect.extend({
  /**
   * Assert that audit log entry exists for the given record
   * @param {Locator|Page} received - Playwright locator or page
   * @param {string} module - Module name
   * @param {string} recordId - Record ID
   * @param {string} action - Action type
   * @param {Object} options - Options
   */
  async toHaveCorrectAuditLog(received, module, recordId, action, options = {}) {
    const { userId, fieldChanges, timeout = 10000 } = options;
    
    const dbConfig = {
      host: process.env.DB_HOST || 'localhost',
      user: process.env.DB_USER || 'suitecrm',
      password: process.env.DB_PASSWORD || 'suitecrm123',
      database: process.env.DB_NAME || 'suitecrm',
      port: process.env.DB_PORT || 3306
    };

    let connection;
    let auditEntries = [];
    
    try {
      // Wait for audit log entry to be created
      const startTime = Date.now();
      while (Date.now() - startTime < timeout) {
        try {
          connection = await mysql.createConnection(dbConfig);
          
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
          
          query += ' ORDER BY date_created DESC';
          
          const [rows] = await connection.execute(query, params);
          
          if (rows.length > 0) {
            auditEntries = rows;
            break;
          }
          
          await connection.end();
          connection = null;
          
          // Wait before retrying
          await new Promise(resolve => setTimeout(resolve, 500));
        } catch (error) {
          if (connection) {
            await connection.end();
            connection = null;
          }
          // Continue retrying on database errors
        }
      }
      
      if (auditEntries.length === 0) {
        return {
          message: () => `Expected audit log entry for ${action} on ${module}:${recordId} but none was found`,
          pass: false
        };
      }
      
      // Validate field changes if specified
      if (fieldChanges) {
        for (const [fieldName, expectedChange] of Object.entries(fieldChanges)) {
          const fieldEntry = auditEntries.find(entry => entry.field_name === fieldName);
          
          if (!fieldEntry) {
            return {
              message: () => `Expected audit log entry for field ${fieldName} but none was found`,
              pass: false
            };
          }
          
          if (expectedChange.before !== undefined && fieldEntry.before_value !== expectedChange.before) {
            return {
              message: () => `Expected before_value to be ${expectedChange.before} but got ${fieldEntry.before_value}`,
              pass: false
            };
          }
          
          if (expectedChange.after !== undefined && fieldEntry.after_value !== expectedChange.after) {
            return {
              message: () => `Expected after_value to be ${expectedChange.after} but got ${fieldEntry.after_value}`,
              pass: false
            };
          }
        }
      }
      
      return {
        message: () => `Found expected audit log entry for ${action} on ${module}:${recordId}`,
        pass: true
      };
      
    } catch (error) {
      return {
        message: () => `Error checking audit log: ${error.message}`,
        pass: false
      };
    } finally {
      if (connection) {
        await connection.end();
      }
    }
  },

  /**
   * Assert that data persists in database
   * @param {Locator|Page} received - Playwright locator or page
   * @param {string} table - Table name
   * @param {Object} conditions - Where conditions
   * @param {Object} options - Options
   */
  async toHavePersistedInDatabase(received, table, conditions, options = {}) {
    const { expectedFields, timeout = 10000 } = options;
    
    const dbConfig = {
      host: process.env.DB_HOST || 'localhost',
      user: process.env.DB_USER || 'suitecrm',
      password: process.env.DB_PASSWORD || 'suitecrm123',
      database: process.env.DB_NAME || 'suitecrm',
      port: process.env.DB_PORT || 3306
    };

    let connection;
    
    try {
      const startTime = Date.now();
      let records = [];
      
      while (Date.now() - startTime < timeout) {
        try {
          connection = await mysql.createConnection(dbConfig);
          
          const whereClause = Object.keys(conditions)
            .map(key => `${key} = ?`)
            .join(' AND ');
          
          const selectFields = expectedFields ? 
            Object.keys(expectedFields).join(', ') : 
            '*';
          
          const query = `SELECT ${selectFields} FROM ${table} WHERE ${whereClause}`;
          const [rows] = await connection.execute(query, Object.values(conditions));
          
          if (rows.length > 0) {
            records = rows;
            break;
          }
          
          await connection.end();
          connection = null;
          
          // Wait before retrying
          await new Promise(resolve => setTimeout(resolve, 500));
        } catch (error) {
          if (connection) {
            await connection.end();
            connection = null;
          }
          // Continue retrying on database errors
        }
      }
      
      if (records.length === 0) {
        return {
          message: () => `Expected record to exist in ${table} with conditions ${JSON.stringify(conditions)} but none was found`,
          pass: false
        };
      }
      
      // Validate expected field values
      if (expectedFields) {
        const record = records[0];
        for (const [fieldName, expectedValue] of Object.entries(expectedFields)) {
          if (record[fieldName] !== expectedValue) {
            return {
              message: () => `Expected field ${fieldName} to be ${expectedValue} but got ${record[fieldName]}`,
              pass: false
            };
          }
        }
      }
      
      return {
        message: () => `Found expected record in ${table}`,
        pass: true
      };
      
    } catch (error) {
      return {
        message: () => `Error checking database persistence: ${error.message}`,
        pass: false
      };
    } finally {
      if (connection) {
        await connection.end();
      }
    }
  },

  /**
   * Assert that UI shows expected update
   * @param {Locator} received - Playwright locator
   * @param {Object} expectedUpdate - Expected update details
   * @param {Object} options - Options
   */
  async toShowUIUpdate(received, expectedUpdate, options = {}) {
    const { timeout = 5000, pollingInterval = 100 } = options;
    
    try {
      await expect(async () => {
        if (expectedUpdate.text) {
          if (expectedUpdate.exact) {
            await expect(received).toHaveText(expectedUpdate.text);
          } else {
            await expect(received).toContainText(expectedUpdate.text);
          }
        }
        
        if (expectedUpdate.visible !== undefined) {
          if (expectedUpdate.visible) {
            await expect(received).toBeVisible();
          } else {
            await expect(received).toBeHidden();
          }
        }
        
        if (expectedUpdate.enabled !== undefined) {
          if (expectedUpdate.enabled) {
            await expect(received).toBeEnabled();
          } else {
            await expect(received).toBeDisabled();
          }
        }
        
        if (expectedUpdate.value !== undefined) {
          await expect(received).toHaveValue(expectedUpdate.value);
        }
        
        if (expectedUpdate.count !== undefined) {
          await expect(received).toHaveCount(expectedUpdate.count);
        }
        
        if (expectedUpdate.attribute) {
          const { name, value } = expectedUpdate.attribute;
          await expect(received).toHaveAttribute(name, value);
        }
        
        if (expectedUpdate.class) {
          await expect(received).toHaveClass(new RegExp(expectedUpdate.class));
        }
      }).toPass({ timeout, intervals: [pollingInterval] });
      
      return {
        message: () => `UI update verified successfully`,
        pass: true
      };
      
    } catch (error) {
      return {
        message: () => `Expected UI update not found: ${error.message}`,
        pass: false
      };
    }
  },

  /**
   * Assert visual consistency with baseline
   * @param {Locator|Page} received - Playwright locator or page
   * @param {string} baselineName - Baseline screenshot name
   * @param {Object} options - Options
   */
  async toMaintainVisualConsistency(received, baselineName, options = {}) {
    const { threshold = 0.05, animations = 'disabled', fullPage = false } = options;
    
    try {
      // Stabilize the element/page before screenshot
      if (received.page) {
        // This is a page
        await received.waitForLoadState('networkidle');
        await received.evaluate(() => document.fonts.ready);
      } else {
        // This is a locator
        await received.waitFor({ state: 'visible' });
      }
      
      // Wait for animations to complete
      await received.page().waitForTimeout(500);
      
      const screenshotOptions = {
        threshold,
        animations,
        fullPage
      };
      
      if (received.page) {
        // Page screenshot
        await expect(received).toHaveScreenshot(`${baselineName}.png`, screenshotOptions);
      } else {
        // Element screenshot
        await expect(received).toHaveScreenshot(`${baselineName}.png`, screenshotOptions);
      }
      
      return {
        message: () => `Visual consistency maintained for ${baselineName}`,
        pass: true
      };
      
    } catch (error) {
      return {
        message: () => `Visual consistency check failed for ${baselineName}: ${error.message}`,
        pass: false
      };
    }
  },

  /**
   * Assert performance metrics meet thresholds
   * @param {Page} received - Playwright page
   * @param {Object} thresholds - Performance thresholds
   * @param {Object} options - Options
   */
  async toMeetPerformanceThresholds(received, thresholds, options = {}) {
    const { timeout = 10000 } = options;
    
    try {
      // Get performance metrics
      const metrics = await received.evaluate(() => {
        const perf = performance.getEntriesByType('navigation')[0];
        const paintEntries = performance.getEntriesByType('paint');
        
        return {
          loadTime: perf ? perf.loadEventEnd - perf.navigationStart : 0,
          domContentLoaded: perf ? perf.domContentLoadedEventEnd - perf.navigationStart : 0,
          firstPaint: paintEntries.find(entry => entry.name === 'first-paint')?.startTime || 0,
          firstContentfulPaint: paintEntries.find(entry => entry.name === 'first-contentful-paint')?.startTime || 0,
          // Memory info if available
          ...(performance.memory ? {
            usedJSHeapSize: performance.memory.usedJSHeapSize,
            totalJSHeapSize: performance.memory.totalJSHeapSize
          } : {})
        };
      });
      
      const failedThresholds = [];
      
      // Check each threshold
      for (const [metric, threshold] of Object.entries(thresholds)) {
        if (metrics[metric] !== undefined && metrics[metric] > threshold) {
          failedThresholds.push({
            metric,
            actual: metrics[metric],
            threshold
          });
        }
      }
      
      if (failedThresholds.length > 0) {
        const failures = failedThresholds
          .map(f => `${f.metric}: ${f.actual}ms > ${f.threshold}ms`)
          .join(', ');
        
        return {
          message: () => `Performance thresholds exceeded: ${failures}`,
          pass: false
        };
      }
      
      return {
        message: () => `All performance thresholds met`,
        pass: true
      };
      
    } catch (error) {
      return {
        message: () => `Error checking performance thresholds: ${error.message}`,
        pass: false
      };
    }
  },

  /**
   * Assert form validation state
   * @param {Locator} received - Form locator
   * @param {Object} expectedValidation - Expected validation state
   * @param {Object} options - Options
   */
  async toHaveValidationState(received, expectedValidation, options = {}) {
    const { timeout = 3000 } = options;
    
    try {
      await expect(async () => {
        if (expectedValidation.valid !== undefined) {
          if (expectedValidation.valid) {
            await expect(received).not.toHaveClass(/error|invalid|danger/);
          } else {
            await expect(received).toHaveClass(/error|invalid|danger/);
          }
        }
        
        if (expectedValidation.fields) {
          for (const [fieldName, fieldValidation] of Object.entries(expectedValidation.fields)) {
            const field = received.locator(`[name="${fieldName}"]`);
            
            if (fieldValidation.required !== undefined) {
              if (fieldValidation.required) {
                await expect(field).toHaveAttribute('required');
              } else {
                await expect(field).not.toHaveAttribute('required');
              }
            }
            
            if (fieldValidation.error !== undefined) {
              const errorSelector = fieldValidation.error ? 
                '.error, .invalid, .danger, [class*="error"]' : 
                ':not(.error):not(.invalid):not(.danger)';
              
              await expect(field.or(field.locator('~ ' + errorSelector))).toBeVisible();
            }
          }
        }
        
        if (expectedValidation.errorMessage) {
          await expect(received.locator('.error-message, .validation-message'))
            .toContainText(expectedValidation.errorMessage);
        }
      }).toPass({ timeout, intervals: [100] });
      
      return {
        message: () => `Form validation state verified`,
        pass: true
      };
      
    } catch (error) {
      return {
        message: () => `Form validation state check failed: ${error.message}`,
        pass: false
      };
    }
  },

  /**
   * Assert drag and drop operation completed
   * @param {Locator} received - Target drop zone locator
   * @param {Object} expectedResult - Expected result of drag and drop
   * @param {Object} options - Options
   */
  async toHaveCompletedDragDrop(received, expectedResult, options = {}) {
    const { timeout = 5000 } = options;
    
    try {
      await expect(async () => {
        if (expectedResult.containsElement) {
          await expect(received.locator(expectedResult.containsElement)).toBeVisible();
        }
        
        if (expectedResult.hasClass) {
          await expect(received).toHaveClass(new RegExp(expectedResult.hasClass));
        }
        
        if (expectedResult.childCount !== undefined) {
          await expect(received.locator('> *')).toHaveCount(expectedResult.childCount);
        }
        
        if (expectedResult.dataAttribute) {
          const { name, value } = expectedResult.dataAttribute;
          await expect(received).toHaveAttribute(name, value);
        }
      }).toPass({ timeout, intervals: [100] });
      
      return {
        message: () => `Drag and drop operation completed successfully`,
        pass: true
      };
      
    } catch (error) {
      return {
        message: () => `Drag and drop operation verification failed: ${error.message}`,
        pass: false
      };
    }
  }
});

module.exports = { expect };