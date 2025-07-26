/**
 * Screenshot Helper
 * Provides screenshot capture utilities for E2E tests
 */

const path = require('path');
const fs = require('fs').promises;

class ScreenshotHelper {
  constructor(page) {
    this.page = page;
    this.screenshotDir = path.join(__dirname, '../../test-results/screenshots');
  }

  /**
   * Ensure screenshot directory exists
   * @returns {Promise<void>}
   */
  async ensureScreenshotDir() {
    try {
      await fs.mkdir(this.screenshotDir, { recursive: true });
    } catch (error) {
      console.error('Failed to create screenshot directory:', error);
    }
  }

  /**
   * Take a screenshot with automatic naming
   * @param {string} name - Screenshot name
   * @param {Object} options - Screenshot options
   * @returns {Promise<string>} Path to screenshot
   */
  async takeScreenshot(name, options = {}) {
    await this.ensureScreenshotDir();

    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    const filename = `${name}_${timestamp}.png`;
    const filepath = path.join(this.screenshotDir, filename);

    const defaultOptions = {
      path: filepath,
      fullPage: false,
      animations: 'disabled'
    };

    await this.page.screenshot({ ...defaultOptions, ...options });
    return filepath;
  }

  /**
   * Take a full page screenshot
   * @param {string} name - Screenshot name
   * @returns {Promise<string>} Path to screenshot
   */
  async takeFullPageScreenshot(name) {
    return await this.takeScreenshot(name, { fullPage: true });
  }

  /**
   * Take element screenshot
   * @param {string} selector - Element selector
   * @param {string} name - Screenshot name
   * @returns {Promise<string>} Path to screenshot
   */
  async takeElementScreenshot(selector, name) {
    await this.ensureScreenshotDir();

    const element = await this.page.$(selector);
    if (!element) {
      throw new Error(`Element not found: ${selector}`);
    }

    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    const filename = `${name}_element_${timestamp}.png`;
    const filepath = path.join(this.screenshotDir, filename);

    await element.screenshot({ path: filepath });
    return filepath;
  }

  /**
   * Take screenshot on test failure
   * @param {import('@playwright/test').TestInfo} testInfo - Test info object
   * @returns {Promise<void>}
   */
  async captureOnFailure(testInfo) {
    if (testInfo.status !== 'passed') {
      const screenshotName = `failure_${testInfo.title.replace(/\s+/g, '_')}`;
      const screenshotPath = await this.takeScreenshot(screenshotName);
      
      // Attach to test report
      await testInfo.attach('failure-screenshot', {
        body: await fs.readFile(screenshotPath),
        contentType: 'image/png'
      });
    }
  }

  /**
   * Compare screenshots
   * @param {string} selector - Element selector
   * @param {string} baselineName - Baseline screenshot name
   * @param {Object} options - Comparison options
   * @returns {Promise<boolean>} True if screenshots match
   */
  async compareScreenshots(selector, baselineName, options = {}) {
    const defaultOptions = {
      threshold: 0.2,
      maxDiffPixels: 100,
      animations: 'disabled'
    };

    const opts = { ...defaultOptions, ...options };

    try {
      await expect(this.page.locator(selector)).toHaveScreenshot(baselineName, opts);
      return true;
    } catch (error) {
      console.error('Screenshot comparison failed:', error);
      return false;
    }
  }

  /**
   * Take before and after screenshots
   * @param {string} name - Base name for screenshots
   * @param {Function} action - Action to perform between screenshots
   * @returns {Promise<Object>} Paths to before and after screenshots
   */
  async captureBeforeAfter(name, action) {
    const beforePath = await this.takeScreenshot(`${name}_before`);
    await action();
    await this.page.waitForLoadState('networkidle');
    const afterPath = await this.takeScreenshot(`${name}_after`);

    return { before: beforePath, after: afterPath };
  }

  /**
   * Capture screenshot series during an action
   * @param {string} name - Base name for screenshots
   * @param {Function} action - Action to perform
   * @param {number} interval - Interval between screenshots (ms)
   * @returns {Promise<Array>} Array of screenshot paths
   */
  async captureSequence(name, action, interval = 500) {
    const screenshots = [];
    let index = 0;
    
    const captureInterval = setInterval(async () => {
      const screenshotPath = await this.takeScreenshot(`${name}_seq_${index++}`);
      screenshots.push(screenshotPath);
    }, interval);

    try {
      await action();
    } finally {
      clearInterval(captureInterval);
    }

    return screenshots;
  }

  /**
   * Take responsive screenshots at different viewports
   * @param {string} name - Screenshot name
   * @param {Array} viewports - Array of viewport sizes
   * @returns {Promise<Object>} Object with viewport size as key and screenshot path as value
   */
  async takeResponsiveScreenshots(name, viewports = null) {
    const defaultViewports = [
      { width: 1920, height: 1080, name: 'desktop' },
      { width: 1366, height: 768, name: 'laptop' },
      { width: 768, height: 1024, name: 'tablet' },
      { width: 375, height: 667, name: 'mobile' }
    ];

    const viewportsToUse = viewports || defaultViewports;
    const screenshots = {};

    for (const viewport of viewportsToUse) {
      await this.page.setViewportSize({ 
        width: viewport.width, 
        height: viewport.height 
      });
      
      // Wait for any responsive adjustments
      await this.page.waitForTimeout(500);
      
      const screenshotPath = await this.takeScreenshot(
        `${name}_${viewport.name}_${viewport.width}x${viewport.height}`
      );
      
      screenshots[viewport.name] = screenshotPath;
    }

    return screenshots;
  }

  /**
   * Mask sensitive data before taking screenshot
   * @param {Array} selectors - Selectors of elements to mask
   * @param {string} name - Screenshot name
   * @returns {Promise<string>} Path to screenshot
   */
  async takeScreenshotWithMask(selectors, name) {
    // Add mask to sensitive elements
    for (const selector of selectors) {
      await this.page.evaluate((sel) => {
        const elements = document.querySelectorAll(sel);
        elements.forEach(el => {
          el.style.filter = 'blur(5px)';
          el.setAttribute('data-masked', 'true');
        });
      }, selector);
    }

    // Take screenshot
    const screenshotPath = await this.takeScreenshot(name);

    // Remove masks
    for (const selector of selectors) {
      await this.page.evaluate((sel) => {
        const elements = document.querySelectorAll(sel);
        elements.forEach(el => {
          el.style.filter = '';
          el.removeAttribute('data-masked');
        });
      }, selector);
    }

    return screenshotPath;
  }

  /**
   * Highlight elements before taking screenshot
   * @param {Array} selectors - Selectors of elements to highlight
   * @param {string} name - Screenshot name
   * @param {string} color - Highlight color
   * @returns {Promise<string>} Path to screenshot
   */
  async takeScreenshotWithHighlight(selectors, name, color = 'red') {
    // Add highlight to elements
    for (const selector of selectors) {
      await this.page.evaluate((sel, col) => {
        const elements = document.querySelectorAll(sel);
        elements.forEach(el => {
          el.style.outline = `3px solid ${col}`;
          el.style.outlineOffset = '2px';
          el.setAttribute('data-highlighted', 'true');
        });
      }, selector, color);
    }

    // Take screenshot
    const screenshotPath = await this.takeScreenshot(name);

    // Remove highlights
    for (const selector of selectors) {
      await this.page.evaluate((sel) => {
        const elements = document.querySelectorAll(sel);
        elements.forEach(el => {
          el.style.outline = '';
          el.style.outlineOffset = '';
          el.removeAttribute('data-highlighted');
        });
      }, selector);
    }

    return screenshotPath;
  }
}

module.exports = ScreenshotHelper;