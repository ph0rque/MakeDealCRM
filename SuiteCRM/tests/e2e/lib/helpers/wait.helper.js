/**
 * Wait Helper
 * Provides advanced waiting utilities for E2E tests
 */

class WaitHelper {
  constructor(page) {
    this.page = page;
  }

  /**
   * Wait for element to be visible and stable
   * @param {string} selector - Element selector
   * @param {Object} options - Wait options
   * @returns {Promise<import('playwright').ElementHandle>}
   */
  async waitForElement(selector, options = {}) {
    const defaults = {
      state: 'visible',
      timeout: 10000,
      stable: true
    };
    const opts = { ...defaults, ...options };

    const element = await this.page.waitForSelector(selector, {
      state: opts.state,
      timeout: opts.timeout
    });

    if (opts.stable && element) {
      // Wait for element to be stable (not moving)
      await element.waitForElementState('stable');
    }

    return element;
  }

  /**
   * Wait for multiple elements
   * @param {string} selector - Element selector
   * @param {Object} options - Wait options
   * @returns {Promise<Array>}
   */
  async waitForElements(selector, options = {}) {
    const defaults = {
      minCount: 1,
      timeout: 10000
    };
    const opts = { ...defaults, ...options };

    await this.page.waitForSelector(selector, {
      state: 'visible',
      timeout: opts.timeout
    });

    // Wait for minimum count
    await this.page.waitForFunction(
      ({ selector, minCount }) => {
        const elements = document.querySelectorAll(selector);
        return elements.length >= minCount;
      },
      { selector, minCount: opts.minCount },
      { timeout: opts.timeout }
    );

    return await this.page.$$(selector);
  }

  /**
   * Wait for element to contain text
   * @param {string} selector - Element selector
   * @param {string} text - Expected text
   * @param {Object} options - Wait options
   * @returns {Promise<void>}
   */
  async waitForText(selector, text, options = {}) {
    const defaults = {
      timeout: 10000,
      exact: false
    };
    const opts = { ...defaults, ...options };

    await this.page.waitForFunction(
      ({ selector, text, exact }) => {
        const element = document.querySelector(selector);
        if (!element) return false;
        const content = element.textContent || '';
        return exact ? content.trim() === text : content.includes(text);
      },
      { selector, text, exact: opts.exact },
      { timeout: opts.timeout }
    );
  }

  /**
   * Wait for element to disappear
   * @param {string} selector - Element selector
   * @param {Object} options - Wait options
   * @returns {Promise<void>}
   */
  async waitForElementToDisappear(selector, options = {}) {
    const defaults = {
      timeout: 10000
    };
    const opts = { ...defaults, ...options };

    await this.page.waitForSelector(selector, {
      state: 'hidden',
      timeout: opts.timeout
    });
  }

  /**
   * Wait for network to be idle
   * @param {Object} options - Wait options
   * @returns {Promise<void>}
   */
  async waitForNetworkIdle(options = {}) {
    const defaults = {
      timeout: 10000,
      waitUntil: 'networkidle'
    };
    const opts = { ...defaults, ...options };

    await this.page.waitForLoadState(opts.waitUntil, {
      timeout: opts.timeout
    });
  }

  /**
   * Wait for URL to match pattern
   * @param {string|RegExp} urlPattern - URL pattern
   * @param {Object} options - Wait options
   * @returns {Promise<void>}
   */
  async waitForUrl(urlPattern, options = {}) {
    const defaults = {
      timeout: 10000
    };
    const opts = { ...defaults, ...options };

    await this.page.waitForURL(urlPattern, {
      timeout: opts.timeout
    });
  }

  /**
   * Wait for function to return true
   * @param {Function} predicate - Function to evaluate
   * @param {Object} options - Wait options
   * @returns {Promise<void>}
   */
  async waitForCondition(predicate, options = {}) {
    const defaults = {
      timeout: 10000,
      polling: 100
    };
    const opts = { ...defaults, ...options };

    await this.page.waitForFunction(predicate, null, {
      timeout: opts.timeout,
      polling: opts.polling
    });
  }

  /**
   * Wait for animation to complete
   * @param {string} selector - Element selector
   * @returns {Promise<void>}
   */
  async waitForAnimation(selector) {
    const element = await this.page.$(selector);
    if (element) {
      await element.waitForElementState('stable');
      await this.page.waitForTimeout(300); // Additional wait for CSS transitions
    }
  }

  /**
   * Wait for AJAX requests to complete
   * @param {Object} options - Wait options
   * @returns {Promise<void>}
   */
  async waitForAjax(options = {}) {
    const defaults = {
      timeout: 10000
    };
    const opts = { ...defaults, ...options };

    // Wait for jQuery AJAX if available
    await this.page.waitForFunction(
      () => {
        if (typeof jQuery !== 'undefined') {
          return jQuery.active === 0;
        }
        return true;
      },
      null,
      { timeout: opts.timeout }
    );

    // Also wait for network idle
    await this.waitForNetworkIdle({ timeout: opts.timeout });
  }

  /**
   * Wait for loader to disappear
   * @returns {Promise<void>}
   */
  async waitForLoaderToDisappear() {
    const loaderSelectors = [
      '.loading',
      '.loader',
      '.spinner',
      '[class*="loading"]',
      '[class*="spinner"]',
      '.ajax-loading',
      '#ajaxloading'
    ];

    for (const selector of loaderSelectors) {
      const loader = await this.page.$(selector);
      if (loader) {
        await this.waitForElementToDisappear(selector);
      }
    }
  }

  /**
   * Smart wait - combines multiple wait strategies
   * @returns {Promise<void>}
   */
  async waitForPageReady() {
    // Wait for basic page load
    await this.waitForNetworkIdle();
    
    // Wait for loaders to disappear
    await this.waitForLoaderToDisappear();
    
    // Wait for AJAX
    await this.waitForAjax();
    
    // Small additional wait for any final renders
    await this.page.waitForTimeout(100);
  }

  /**
   * Retry an action until it succeeds
   * @param {Function} action - Action to retry
   * @param {Object} options - Retry options
   * @returns {Promise<any>}
   */
  async retry(action, options = {}) {
    const defaults = {
      retries: 3,
      delay: 1000,
      timeout: 30000
    };
    const opts = { ...defaults, ...options };

    const startTime = Date.now();
    let lastError;

    for (let i = 0; i < opts.retries; i++) {
      try {
        return await action();
      } catch (error) {
        lastError = error;
        
        if (Date.now() - startTime > opts.timeout) {
          throw new Error(`Retry timeout exceeded: ${error.message}`);
        }

        if (i < opts.retries - 1) {
          await this.page.waitForTimeout(opts.delay);
        }
      }
    }

    throw lastError;
  }
}

module.exports = WaitHelper;