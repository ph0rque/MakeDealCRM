import { FullConfig } from '@playwright/test';
import * as dotenv from 'dotenv';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Global setup function that runs once before all tests
 */
async function globalSetup(config: FullConfig) {
  console.log('\n🚀 Starting MakeDealCRM E2E Test Suite Setup...\n');

  // Load environment variables
  dotenv.config({ path: path.join(__dirname, '../../../.env') });
  dotenv.config({ path: path.join(__dirname, '../.env.test') });

  // Ensure required environment variables are set
  const requiredEnvVars = ['BASE_URL'];
  const missingVars = requiredEnvVars.filter(varName => !process.env[varName]);
  
  if (missingVars.length > 0 && !process.env.BASE_URL) {
    console.log('⚠️  No BASE_URL specified, using default: http://localhost:8080');
    process.env.BASE_URL = 'http://localhost:8080';
  }

  // Create necessary directories
  const directories = [
    'reports',
    'reports/html',
    'reports/screenshots',
    'reports/videos',
    'reports/traces',
    'test-results'
  ];

  directories.forEach(dir => {
    const fullPath = path.join(__dirname, '..', dir);
    if (!fs.existsSync(fullPath)) {
      fs.mkdirSync(fullPath, { recursive: true });
      console.log(`📁 Created directory: ${dir}`);
    }
  });

  // Log test configuration
  console.log('📋 Test Configuration:');
  console.log(`   - Base URL: ${process.env.BASE_URL || config.use?.baseURL}`);
  console.log(`   - Workers: ${config.workers}`);
  console.log(`   - Retries: ${config.retries}`);
  console.log(`   - Timeout: ${config.timeout}ms`);
  console.log(`   - Projects: ${config.projects.map(p => p.name).join(', ')}`);
  
  // Check if SuiteCRM is accessible
  try {
    const baseUrl = process.env.BASE_URL || config.use?.baseURL || 'http://localhost:8080';
    console.log(`\n🔍 Checking SuiteCRM availability at ${baseUrl}...`);
    
    const response = await fetch(baseUrl, { 
      method: 'HEAD',
      redirect: 'follow',
      signal: AbortSignal.timeout(10000)
    });
    
    if (response.ok || response.status === 302) {
      console.log('✅ SuiteCRM is accessible\n');
    } else {
      console.warn(`⚠️  SuiteCRM returned status ${response.status}\n`);
    }
  } catch (error) {
    console.error('❌ Failed to connect to SuiteCRM:', error.message);
    console.error('   Please ensure Docker container is running\n');
    // Don't fail setup - let individual tests handle connection errors
  }

  // Store the start time for the test run
  global.__TEST_RUN_START_TIME__ = Date.now();

  console.log('✅ Global setup completed\n');
}

export default globalSetup;