const { defineConfig, devices } = require('@playwright/test');
const path = require('path');

module.exports = defineConfig({
  testDir: './',
  testMatch: '**/*.spec.{js,ts}',
  timeout: 30 * 1000,
  expect: {
    timeout: 5000,
    // Enhanced screenshot comparison settings
    toHaveScreenshot: {
      threshold: 0.05,
      animations: 'disabled'
    },
    toMatchSnapshot: {
      threshold: 0.05
    }
  },
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  // Global setup for enhanced assertions
  globalSetup: require.resolve('./lib/config/global-setup.js'),
  reporter: [
    ['html', { 
      outputFolder: 'html-report',
      open: 'never',
      host: 'localhost',
      port: 9323
    }],
    ['junit', { 
      outputFile: 'test-results/junit.xml',
      includeProjectInTestName: true
    }],
    ['json', { 
      outputFile: 'test-results/test-results.json',
      includeProjectInTestName: true
    }],
    ['allure-playwright', {
      outputFolder: 'test-results/allure-results'
    }],
    ['github'],
    ['list'],
    ['./lib/reporters/custom-reporter.js', {
      outputFile: 'test-results/custom-report.json'
    }],
    ['./lib/reporters/performance-reporter.js', {
      outputFile: 'test-results/performance-report.json'
    }]
  ],
  
  // Global test configuration
  use: {
    baseURL: process.env.BASE_URL || 'http://localhost:8080',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    headless: process.env.CI ? true : false,
    actionTimeout: 0,
    navigationTimeout: 30000,
    
    // Test data
    testData: {
      admin: {
        username: process.env.ADMIN_USERNAME || 'admin',
        password: process.env.ADMIN_PASSWORD || 'admin123'
      },
      testUser: {
        username: process.env.TEST_USERNAME || 'testuser',
        password: process.env.TEST_PASSWORD || 'testpass123'
      }
    },
    
    // Custom test options
    locale: 'en-US',
    timezoneId: 'America/New_York',
    
    // Viewport settings
    viewport: { width: 1280, height: 720 },
    
    // Browser context options
    ignoreHTTPSErrors: true,
    acceptDownloads: true,
  },

  projects: [
    {
      name: 'chromium',
      use: { 
        ...devices['Desktop Chrome'],
        // Custom Chrome launch options
        launchOptions: {
          args: ['--disable-dev-shm-usage', '--no-sandbox']
        }
      },
    },
    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] },
    },
    {
      name: 'webkit',
      use: { ...devices['Desktop Safari'] },
    },
    {
      name: 'Mobile Chrome',
      use: { ...devices['Pixel 5'] },
    },
    {
      name: 'Mobile Safari',
      use: { ...devices['iPhone 12'] },
    },
    {
      name: 'api',
      use: {
        // API testing configuration
        baseURL: process.env.API_URL || 'http://localhost:8080/api',
        extraHTTPHeaders: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      },
    },
  ],

  webServer: {
    command: 'docker-compose up',
    port: 8080,
    timeout: 120 * 1000,
    reuseExistingServer: !process.env.CI,
    cwd: '../../../',
  },
  
  // Output folder for test artifacts
  outputDir: './test-results',
  
  // Enhanced test metadata
  metadata: {
    'Enhanced Assertions': 'Comprehensive UI, data, audit, and visual testing',
    'Database Testing': 'MySQL integration for data persistence verification',
    'Visual Regression': 'Cross-browser screenshot comparison',
    'Performance Monitoring': 'Load time and memory usage tracking',
    'Audit Compliance': 'Complete audit trail verification'
  },
  
  // Test metadata for reporting
  metadata: {
    testEnvironment: process.env.NODE_ENV || 'development',
    browser: 'chromium',
    buildNumber: process.env.BUILD_NUMBER || 'local',
    buildUrl: process.env.BUILD_URL || '',
    gitCommit: process.env.GIT_COMMIT || '',
    gitBranch: process.env.GIT_BRANCH || 'local'
  },
});