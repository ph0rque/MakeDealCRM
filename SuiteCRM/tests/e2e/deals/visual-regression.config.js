/**
 * Visual Regression Configuration for Duplicate Detection Tests
 * Uses Playwright's built-in screenshot comparison
 */

module.exports = {
  // Threshold for pixel differences (0-1, where 0 is exact match)
  threshold: 0.2,
  
  // Maximum allowed pixel difference
  maxDiffPixels: 100,
  
  // Animation handling
  animations: 'disabled',
  
  // Screenshot options
  screenshotOptions: {
    fullPage: false,
    animations: 'disabled',
    caret: 'hide'
  },
  
  // Specific configurations for duplicate warning screenshots
  duplicateWarningConfig: {
    // Clip to just the warning element
    clip: true,
    
    // Wait for animations to complete
    waitForAnimations: true,
    
    // Specific selectors to wait for
    waitForSelectors: [
      '.duplicate-warning',
      '.duplicate-match-fields',
      '.duplicate-action-buttons'
    ],
    
    // Elements to mask (dynamic content)
    mask: [
      '.timestamp',
      '.user-avatar',
      '.loading-spinner'
    ]
  },
  
  // Test scenarios for visual regression
  visualTests: [
    {
      name: 'duplicate-warning-single-match',
      description: 'Warning with single field match',
      selector: '.duplicate-warning',
      variants: ['light', 'dark']
    },
    {
      name: 'duplicate-warning-multiple-matches',
      description: 'Warning with multiple field matches',
      selector: '.duplicate-warning.high-confidence',
      variants: ['light', 'dark']
    },
    {
      name: 'duplicate-merge-modal',
      description: 'Merge duplicate records modal',
      selector: '.merge-modal',
      variants: ['light']
    },
    {
      name: 'duplicate-confirmation-modal',
      description: 'Confirmation modal when saving with duplicates',
      selector: '.duplicate-confirmation-modal',
      variants: ['light']
    },
    {
      name: 'duplicate-list-pagination',
      description: 'Pagination for multiple duplicates',
      selector: '.duplicate-list-container',
      variants: ['light']
    }
  ],
  
  // Viewport sizes for responsive testing
  viewports: [
    { name: 'desktop', width: 1920, height: 1080 },
    { name: 'laptop', width: 1366, height: 768 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'mobile', width: 375, height: 667 }
  ],
  
  // Browser configurations
  browsers: ['chromium', 'firefox', 'webkit'],
  
  // Storage for baseline images
  baselineDir: './tests/e2e/deals/visual-baselines',
  
  // Diff output directory
  diffDir: './tests/e2e/deals/visual-diffs',
  
  // Update baseline images
  updateBaseline: process.env.UPDATE_BASELINES === 'true'
};