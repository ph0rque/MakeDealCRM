/**
 * Test Fixtures
 * Common test setup and teardown configurations
 */

const { test: base } = require('@playwright/test');
const BasePage = require('../pages/base.page');
const TestDataManager = require('../data/test-data-manager');
const fs = require('fs').promises;
const path = require('path');

// Extend base test with custom fixtures
exports.test = base.extend({
  // Auto-login fixture
  authenticatedPage: async ({ page }, use) => {
    const basePage = new BasePage(page);
    await basePage.auth.loginAsAdmin();
    await use(page);
  },

  // Test data manager fixture
  testData: async ({}, use) => {
    const dataManager = new TestDataManager();
    await use(dataManager);
    // Cleanup test data after test
    await dataManager.cleanup();
  },

  // Screenshot on failure fixture
  autoScreenshot: [async ({ page }, use, testInfo) => {
    // Use the page
    await use(page);
    
    // Take screenshot on failure
    if (testInfo.status !== 'passed') {
      const screenshotPath = path.join(
        testInfo.outputDir,
        `failure-${testInfo.title.replace(/\s+/g, '-')}.png`
      );
      await page.screenshot({ path: screenshotPath, fullPage: true });
      await testInfo.attach('failure-screenshot', {
        path: screenshotPath,
        contentType: 'image/png'
      });
    }
  }, { auto: true }],

  // Console log capture fixture
  consoleLogs: async ({ page }, use) => {
    const logs = [];
    
    page.on('console', msg => {
      logs.push({
        type: msg.type(),
        text: msg.text(),
        location: msg.location(),
        timestamp: new Date().toISOString()
      });
    });

    await use(logs);
  },

  // Network capture fixture
  networkCapture: async ({ page }, use) => {
    const requests = [];
    const responses = [];
    
    page.on('request', request => {
      requests.push({
        url: request.url(),
        method: request.method(),
        headers: request.headers(),
        postData: request.postData(),
        timestamp: new Date().toISOString()
      });
    });

    page.on('response', response => {
      responses.push({
        url: response.url(),
        status: response.status(),
        headers: response.headers(),
        timestamp: new Date().toISOString()
      });
    });

    await use({ requests, responses });
  },

  // Performance metrics fixture
  performanceMetrics: async ({ page }, use) => {
    const metrics = {
      navigation: [],
      resources: []
    };

    // Capture navigation timing
    page.on('load', async () => {
      const navTiming = await page.evaluate(() => {
        const timing = performance.timing;
        return {
          domContentLoaded: timing.domContentLoadedEventEnd - timing.domContentLoadedEventStart,
          loadComplete: timing.loadEventEnd - timing.loadEventStart,
          totalTime: timing.loadEventEnd - timing.navigationStart
        };
      });
      metrics.navigation.push(navTiming);
    });

    await use(metrics);
  },

  // Test context fixture
  testContext: async ({ page, context }, use) => {
    // Set extra HTTP headers
    await context.setExtraHTTPHeaders({
      'X-Test-Run': 'true',
      'X-Test-ID': `test-${Date.now()}`
    });

    // Set default timeout
    page.setDefaultTimeout(30000);
    page.setDefaultNavigationTimeout(30000);

    await use({ page, context });
  },

  // Database state fixture
  dbState: async ({}, use) => {
    // Save current database state
    const state = {
      saved: false,
      restored: false
    };

    // Use in test
    await use(state);

    // Restore database state if needed
    if (state.saved && !state.restored) {
      // Implement database restore logic
    }
  },

  // API client fixture
  apiClient: async ({ request }, use) => {
    const client = {
      get: (url, options = {}) => request.get(url, options),
      post: (url, options = {}) => request.post(url, options),
      put: (url, options = {}) => request.put(url, options),
      delete: (url, options = {}) => request.delete(url, options),
      patch: (url, options = {}) => request.patch(url, options)
    };

    await use(client);
  },

  // Mobile viewport fixture
  mobileViewport: async ({ page }, use) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await use(page);
  },

  // Tablet viewport fixture
  tabletViewport: async ({ page }, use) => {
    await page.setViewportSize({ width: 768, height: 1024 });
    await use(page);
  },

  // Desktop viewport fixture
  desktopViewport: async ({ page }, use) => {
    await page.setViewportSize({ width: 1920, height: 1080 });
    await use(page);
  },

  // Slow motion fixture for debugging
  slowMotion: async ({ page }, use) => {
    // Enable slow motion
    page.context().setDefaultTimeout(60000);
    
    // Slow down all actions
    const originalClick = page.click;
    page.click = async (...args) => {
      await page.waitForTimeout(500);
      return originalClick.apply(page, args);
    };

    const originalFill = page.fill;
    page.fill = async (...args) => {
      await page.waitForTimeout(300);
      return originalFill.apply(page, args);
    };

    await use(page);
  }
});

// Re-export expect
exports.expect = base.expect;

// Custom test hooks
exports.test.beforeEach(async ({ page }) => {
  // Clear cookies and local storage
  await page.context().clearCookies();
  await page.evaluate(() => {
    localStorage.clear();
    sessionStorage.clear();
  });
});

exports.test.afterEach(async ({ page }, testInfo) => {
  // Log test duration
  console.log(`Test "${testInfo.title}" took ${testInfo.duration}ms`);
  
  // Save page content on failure
  if (testInfo.status !== 'passed') {
    const htmlPath = path.join(
      testInfo.outputDir,
      `failure-${testInfo.title.replace(/\s+/g, '-')}.html`
    );
    const content = await page.content();
    await fs.writeFile(htmlPath, content);
    await testInfo.attach('failure-html', {
      path: htmlPath,
      contentType: 'text/html'
    });
  }
});