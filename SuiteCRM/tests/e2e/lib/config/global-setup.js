/**
 * Global Test Setup
 * Configures custom matchers and global test utilities
 */

const { expect } = require('@playwright/test');
require('../helpers/custom-matchers');

// Global test configuration
module.exports = async () => {
  console.log('🔧 Setting up global test configuration...');
  
  // Initialize visual regression directories
  const VisualRegressionHelper = require('../helpers/visual-regression.helper');
  const visualHelper = new VisualRegressionHelper(null);
  await visualHelper.initializeDirectories();
  
  console.log('✅ Global test setup completed');
};