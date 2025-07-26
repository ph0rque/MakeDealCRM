/**
 * Visual Regression Testing Helper
 * Provides utilities for screenshot comparison and visual consistency testing
 */

const { expect } = require('@playwright/test');
const path = require('path');
const fs = require('fs').promises;

class VisualRegressionHelper {
  constructor(page, options = {}) {
    this.page = page;
    this.baselineDir = options.baselineDir || path.join(__dirname, '../../test-results/visual-baselines');
    this.diffDir = options.diffDir || path.join(__dirname, '../../test-results/visual-diffs');
    this.threshold = options.threshold || 0.05; // 5% threshold by default
    this.pixelDiffThreshold = options.pixelDiffThreshold || 100;
    
    // Screenshot options
    this.defaultScreenshotOptions = {
      fullPage: false,
      animations: 'disabled',
      ...options.screenshotOptions
    };
  }

  /**
   * Initialize directories for visual testing
   */
  async initializeDirectories() {
    try {
      await fs.mkdir(this.baselineDir, { recursive: true });
      await fs.mkdir(this.diffDir, { recursive: true });
    } catch (error) {
      console.warn('Could not create visual test directories:', error.message);
    }
  }

  /**
   * Take and compare full page screenshot
   * @param {string} testName - Name for the screenshot
   * @param {Object} options - Screenshot options
   * @returns {Promise<void>}
   */
  async assertPageScreenshot(testName, options = {}) {
    const screenshotOptions = {
      ...this.defaultScreenshotOptions,
      fullPage: true,
      ...options
    };

    await this.stabilizePage();
    await expect(this.page).toHaveScreenshot(`${testName}.png`, screenshotOptions);
  }

  /**
   * Take and compare element screenshot
   * @param {string} selector - Element selector
   * @param {string} testName - Name for the screenshot
   * @param {Object} options - Screenshot options
   * @returns {Promise<void>}
   */
  async assertElementScreenshot(selector, testName, options = {}) {
    const element = this.page.locator(selector);
    const screenshotOptions = {
      ...this.defaultScreenshotOptions,
      ...options
    };

    await this.stabilizeElement(element);
    await expect(element).toHaveScreenshot(`${testName}.png`, screenshotOptions);
  }

  /**
   * Compare multiple elements for visual consistency
   * @param {string[]} selectors - Array of element selectors
   * @param {string} testName - Name for the comparison
   * @param {Object} options - Options
   * @returns {Promise<void>}
   */
  async assertElementsConsistency(selectors, testName, options = {}) {
    const { tolerance = 0.1 } = options;
    const screenshots = [];

    // Take screenshots of all elements
    for (let i = 0; i < selectors.length; i++) {
      const element = this.page.locator(selectors[i]);
      await this.stabilizeElement(element);
      
      const screenshotName = `${testName}-element-${i}.png`;
      await expect(element).toHaveScreenshot(screenshotName, {
        ...this.defaultScreenshotOptions,
        threshold: tolerance
      });
      
      screenshots.push(screenshotName);
    }

    console.log(`✓ Captured ${screenshots.length} element screenshots for consistency comparison`);
  }

  /**
   * Assert visual consistency across different browser viewports
   * @param {string} testName - Name for the test
   * @param {Array} viewports - Array of viewport configurations
   * @param {Object} options - Options
   * @returns {Promise<void>}
   */
  async assertCrossBrowserConsistency(testName, viewports, options = {}) {
    const originalViewport = this.page.viewportSize();
    
    for (const viewport of viewports) {
      await this.page.setViewportSize(viewport);
      await this.page.waitForLoadState('networkidle');
      
      const viewportName = `${viewport.width}x${viewport.height}`;
      await this.assertPageScreenshot(`${testName}-${viewportName}`, options);
    }
    
    // Restore original viewport
    if (originalViewport) {
      await this.page.setViewportSize(originalViewport);
    }
  }

  /**
   * Assert UI component in different states
   * @param {string} componentSelector - Component selector
   * @param {Object} states - Object mapping state names to state setters
   * @param {string} testName - Base name for screenshots
   * @param {Object} options - Options
   * @returns {Promise<void>}
   */
  async assertComponentStates(componentSelector, states, testName, options = {}) {
    const component = this.page.locator(componentSelector);
    
    for (const [stateName, stateSetter] of Object.entries(states)) {
      // Set the component state
      if (typeof stateSetter === 'function') {
        await stateSetter();
      } else if (typeof stateSetter === 'string') {
        await this.page.locator(stateSetter).click();
      }
      
      await this.stabilizeElement(component);
      
      // Take screenshot of the component in this state
      await expect(component).toHaveScreenshot(
        `${testName}-${stateName}.png`,
        {
          ...this.defaultScreenshotOptions,
          ...options
        }
      );
    }
  }

  /**
   * Assert form visual states (empty, filled, error, etc.)
   * @param {string} formSelector - Form selector
   * @param {string} testName - Base name for screenshots
   * @param {Object} options - Options
   * @returns {Promise<void>}
   */
  async assertFormVisualStates(formSelector, testName, options = {}) {
    const form = this.page.locator(formSelector);
    
    const states = {
      empty: async () => {
        await form.evaluate(form => form.reset());
      },
      filled: async () => {
        const inputs = form.locator('input[type="text"], input[type="email"], textarea');
        const inputCount = await inputs.count();
        
        for (let i = 0; i < inputCount; i++) {
          const input = inputs.nth(i);
          const fieldType = await input.getAttribute('type') || await input.tagName();
          
          if (fieldType === 'email') {
            await input.fill('test@example.com');
          } else {
            await input.fill('Test value');
          }
        }
      },
      error: async () => {
        // Try to trigger validation errors
        const requiredInputs = form.locator('input[required], textarea[required]');
        const inputCount = await requiredInputs.count();
        
        if (inputCount > 0) {
          await requiredInputs.first().fill('');
          await requiredInputs.first().blur();
        }
      }
    };
    
    await this.assertComponentStates(formSelector, states, testName, options);
  }

  /**
   * Assert table/list visual consistency
   * @param {string} tableSelector - Table selector
   * @param {string} testName - Base name for screenshots
   * @param {Object} options - Options
   * @returns {Promise<void>}
   */
  async assertTableVisualConsistency(tableSelector, testName, options = {}) {
    const table = this.page.locator(tableSelector);
    await this.stabilizeElement(table);
    
    // Screenshot the entire table
    await expect(table).toHaveScreenshot(`${testName}-full.png`, {
      ...this.defaultScreenshotOptions,
      ...options
    });
    
    // Screenshot table headers
    const headers = table.locator('thead, th');
    const headerCount = await headers.count();
    
    if (headerCount > 0) {
      await expect(headers.first()).toHaveScreenshot(`${testName}-headers.png`, {
        ...this.defaultScreenshotOptions,
        ...options
      });
    }
    
    // Screenshot first few rows for consistency
    const rows = table.locator('tbody tr, tr').first();
    const rowCount = await table.locator('tbody tr, tr').count();
    
    if (rowCount > 0) {
      await expect(rows).toHaveScreenshot(`${testName}-row-sample.png`, {
        ...this.defaultScreenshotOptions,
        ...options
      });
    }
  }

  /**
   * Assert loading state visuals
   * @param {string} containerSelector - Container selector
   * @param {Function} triggerAction - Function to trigger loading
   * @param {string} testName - Base name for screenshots
   * @param {Object} options - Options
   * @returns {Promise<void>}
   */
  async assertLoadingStateVisuals(containerSelector, triggerAction, testName, options = {}) {
    const container = this.page.locator(containerSelector);
    
    // Screenshot initial state
    await expect(container).toHaveScreenshot(`${testName}-initial.png`, {
      ...this.defaultScreenshotOptions,
      ...options
    });
    
    // Trigger action that causes loading
    await triggerAction();
    
    // Wait for loading indicator and screenshot
    try {
      await this.page.waitForSelector(
        `${containerSelector} .loading, ${containerSelector} .spinner, ${containerSelector} .fa-spinner`,
        { timeout: 2000 }
      );
      
      await expect(container).toHaveScreenshot(`${testName}-loading.png`, {
        ...this.defaultScreenshotOptions,
        ...options
      });
    } catch (error) {
      console.log('Loading state not detected, skipping loading screenshot');
    }
    
    // Wait for completion and screenshot final state
    await this.page.waitForLoadState('networkidle');
    await expect(container).toHaveScreenshot(`${testName}-completed.png`, {
      ...this.defaultScreenshotOptions,
      ...options
    });
  }

  /**
   * Assert responsive design across breakpoints
   * @param {string} testName - Base name for screenshots
   * @param {Array} breakpoints - Array of breakpoint configurations
   * @param {Object} options - Options
   * @returns {Promise<void>}
   */
  async assertResponsiveDesign(testName, breakpoints = null, options = {}) {
    const defaultBreakpoints = [
      { name: 'mobile', width: 375, height: 667 },
      { name: 'tablet', width: 768, height: 1024 },
      { name: 'desktop', width: 1280, height: 720 },
      { name: 'large', width: 1920, height: 1080 }
    ];
    
    const testBreakpoints = breakpoints || defaultBreakpoints;
    const originalViewport = this.page.viewportSize();
    
    for (const breakpoint of testBreakpoints) {
      await this.page.setViewportSize({ 
        width: breakpoint.width, 
        height: breakpoint.height 
      });
      
      await this.page.waitForLoadState('networkidle');
      await this.page.waitForTimeout(500); // Allow CSS transitions to complete
      
      await this.assertPageScreenshot(`${testName}-${breakpoint.name}`, {
        fullPage: true,
        ...options
      });
    }
    
    // Restore original viewport
    if (originalViewport) {
      await this.page.setViewportSize(originalViewport);
    }
  }

  /**
   * Compare two page states visually
   * @param {string} testName - Base name for screenshots
   * @param {Function} beforeAction - Action to perform before first screenshot
   * @param {Function} afterAction - Action to perform before second screenshot
   * @param {Object} options - Options
   * @returns {Promise<void>}
   */
  async assertBeforeAfterComparison(testName, beforeAction, afterAction, options = {}) {
    // Take before screenshot
    if (beforeAction) {
      await beforeAction();
    }
    await this.stabilizePage();
    await expect(this.page).toHaveScreenshot(`${testName}-before.png`, {
      ...this.defaultScreenshotOptions,
      ...options
    });
    
    // Perform action and take after screenshot
    if (afterAction) {
      await afterAction();
    }
    await this.stabilizePage();
    await expect(this.page).toHaveScreenshot(`${testName}-after.png`, {
      ...this.defaultScreenshotOptions,
      ...options
    });
  }

  /**
   * Stabilize page for consistent screenshots
   * @param {Object} options - Stabilization options
   */
  async stabilizePage(options = {}) {
    const { waitForAnimations = true, waitForFonts = true, stabilizeTime = 500 } = options;
    
    // Wait for network to be idle
    await this.page.waitForLoadState('networkidle');
    
    // Wait for fonts to load
    if (waitForFonts) {
      await this.page.evaluate(() => document.fonts.ready);
    }
    
    // Wait for animations to complete
    if (waitForAnimations) {
      await this.page.evaluate(() => {
        const animatedElements = document.querySelectorAll('*');
        return Promise.all(
          Array.from(animatedElements).map(element => {
            return element.getAnimations ? 
              Promise.all(element.getAnimations().map(anim => anim.finished)) :
              Promise.resolve();
          })
        );
      });
    }
    
    // Final stabilization wait
    await this.page.waitForTimeout(stabilizeTime);
  }

  /**
   * Stabilize specific element for consistent screenshots
   * @param {Locator} element - Element locator
   * @param {Object} options - Stabilization options
   */
  async stabilizeElement(element, options = {}) {
    const { waitForVisible = true, stabilizeTime = 200 } = options;
    
    if (waitForVisible) {
      await element.waitFor({ state: 'visible' });
    }
    
    // Wait for element animations
    await element.evaluate(el => {
      if (el.getAnimations) {
        return Promise.all(el.getAnimations().map(anim => anim.finished));
      }
    });
    
    await this.page.waitForTimeout(stabilizeTime);
  }

  /**
   * Mask dynamic content in screenshots
   * @param {string[]} selectors - Selectors for elements to mask
   * @param {Object} options - Mask options
   */
  async maskDynamicContent(selectors, options = {}) {
    const { color = 'black' } = options;
    
    for (const selector of selectors) {
      await this.page.addStyleTag({
        content: `
          ${selector} {
            background-color: ${color} !important;
            color: transparent !important;
            border-color: ${color} !important;
          }
          ${selector} * {
            color: transparent !important;
            background-color: ${color} !important;
          }
        `
      });
    }
  }

  /**
   * Generate visual test report
   * @param {string} testSuiteName - Test suite name
   * @param {Array} testResults - Array of test results
   * @returns {Promise<string>} - Path to generated report
   */
  async generateVisualTestReport(testSuiteName, testResults) {
    const reportPath = path.join(this.diffDir, `${testSuiteName}-visual-report.html`);
    
    const htmlContent = `
<!DOCTYPE html>
<html>
<head>
    <title>Visual Test Report - ${testSuiteName}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-result { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
        .passed { border-color: green; background-color: #f0fff0; }
        .failed { border-color: red; background-color: #fff0f0; }
        .screenshot { max-width: 300px; margin: 10px; }
        .comparison { display: flex; gap: 10px; }
    </style>
</head>
<body>
    <h1>Visual Test Report: ${testSuiteName}</h1>
    <p>Generated on: ${new Date().toISOString()}</p>
    
    ${testResults.map(result => `
        <div class="test-result ${result.passed ? 'passed' : 'failed'}">
            <h3>${result.testName}</h3>
            <p>Status: ${result.passed ? 'PASSED' : 'FAILED'}</p>
            ${result.screenshots ? `
                <div class="comparison">
                    ${result.screenshots.map(screenshot => `
                        <div>
                            <h4>${screenshot.name}</h4>
                            <img src="${screenshot.path}" alt="${screenshot.name}" class="screenshot" />
                        </div>
                    `).join('')}
                </div>
            ` : ''}
            ${result.error ? `<p>Error: ${result.error}</p>` : ''}
        </div>
    `).join('')}
</body>
</html>
    `;
    
    await fs.writeFile(reportPath, htmlContent, 'utf8');
    return reportPath;
  }
}

module.exports = VisualRegressionHelper;