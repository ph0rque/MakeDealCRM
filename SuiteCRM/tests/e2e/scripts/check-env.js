#!/usr/bin/env node

/**
 * Environment Check Script
 * Validates environment configuration before running tests
 */

const fs = require('fs');
const path = require('path');
const http = require('http');

// Load environment variables
require('dotenv').config();

const requiredEnvVars = [
  'BASE_URL',
  'ADMIN_USERNAME',
  'ADMIN_PASSWORD'
];

const optionalEnvVars = [
  'API_URL',
  'TEST_USERNAME',
  'TEST_PASSWORD',
  'DB_HOST',
  'DB_PORT',
  'DB_NAME',
  'DB_USER',
  'DB_PASSWORD'
];

function checkEnvironmentVariables() {
  console.log('🔍 Checking environment variables...');
  
  const missing = [];
  const warnings = [];
  
  // Check required variables
  for (const envVar of requiredEnvVars) {
    if (!process.env[envVar]) {
      missing.push(envVar);
    } else {
      console.log(`✅ ${envVar}: ${process.env[envVar]}`);
    }
  }
  
  // Check optional variables
  for (const envVar of optionalEnvVars) {
    if (!process.env[envVar]) {
      warnings.push(envVar);
    } else {
      console.log(`✅ ${envVar}: ${process.env[envVar]}`);
    }
  }
  
  if (missing.length > 0) {
    console.error('❌ Missing required environment variables:');
    missing.forEach(envVar => console.error(`   - ${envVar}`));
    console.error('\\nPlease check your .env file or environment configuration.');
    return false;
  }
  
  if (warnings.length > 0) {
    console.warn('⚠️  Optional environment variables not set:');
    warnings.forEach(envVar => console.warn(`   - ${envVar}`));
  }
  
  return true;
}

function checkEnvFile() {
  console.log('\\n📄 Checking .env file...');
  
  const envPath = path.join(__dirname, '../.env');
  const envExamplePath = path.join(__dirname, '../.env.example');
  
  if (!fs.existsSync(envPath)) {
    if (fs.existsSync(envExamplePath)) {
      console.warn('⚠️  .env file not found. Copy .env.example to .env and configure it.');
      console.warn(`   cp ${envExamplePath} ${envPath}`);
    } else {
      console.error('❌ Neither .env nor .env.example found.');
    }
    return false;
  }
  
  console.log(`✅ .env file found at ${envPath}`);
  return true;
}

function checkApplicationAvailability() {
  console.log('\\n🌐 Checking application availability...');
  
  const baseUrl = process.env.BASE_URL || 'http://localhost:8080';
  const url = new URL(baseUrl);
  
  return new Promise((resolve) => {
    const options = {
      hostname: url.hostname,
      port: url.port || (url.protocol === 'https:' ? 443 : 80),
      path: url.pathname,
      method: 'GET',
      timeout: 10000
    };
    
    const req = http.request(options, (res) => {
      if (res.statusCode >= 200 && res.statusCode < 400) {
        console.log(`✅ Application accessible at ${baseUrl} (Status: ${res.statusCode})`);
        resolve(true);
      } else {
        console.error(`❌ Application returned status ${res.statusCode} at ${baseUrl}`);
        resolve(false);
      }
    });
    
    req.on('error', (err) => {
      console.error(`❌ Cannot connect to application at ${baseUrl}`);
      console.error(`   Error: ${err.message}`);
      console.error('   Make sure the application is running (try: docker-compose up)');
      resolve(false);
    });
    
    req.on('timeout', () => {
      console.error(`❌ Connection timeout to ${baseUrl}`);
      resolve(false);
    });
    
    req.end();
  });
}

function checkNodeModules() {
  console.log('\\n📦 Checking dependencies...');
  
  const nodeModulesPath = path.join(__dirname, '../node_modules');
  const packageJsonPath = path.join(__dirname, '../package.json');
  
  if (!fs.existsSync(nodeModulesPath)) {
    console.error('❌ node_modules not found. Run: npm install');
    return false;
  }
  
  if (!fs.existsSync(packageJsonPath)) {
    console.error('❌ package.json not found.');
    return false;
  }
  
  // Check for Playwright
  const playwrightPath = path.join(nodeModulesPath, '@playwright/test');
  if (!fs.existsSync(playwrightPath)) {
    console.error('❌ @playwright/test not installed. Run: npm install');
    return false;
  }
  
  console.log('✅ Dependencies installed');
  return true;
}

function checkPlaywrightBrowsers() {
  console.log('\\n🎭 Checking Playwright browsers...');
  
  const { execSync } = require('child_process');
  
  try {
    const output = execSync('npx playwright --version', { encoding: 'utf8' });
    console.log(`✅ Playwright version: ${output.trim()}`);
    
    // Check if browsers are installed
    try {
      execSync('npx playwright install --dry-run', { encoding: 'utf8', stdio: 'pipe' });
      console.log('✅ Playwright browsers are installed');
      return true;
    } catch (browserError) {
      console.warn('⚠️  Playwright browsers may not be installed. Run: npm run install:browsers');
      return true; // Not a fatal error
    }
  } catch (error) {
    console.error('❌ Playwright not found or not working properly');
    console.error('   Try running: npm install @playwright/test');
    return false;
  }
}

function checkTestDirectories() {
  console.log('\\n📁 Checking test directories...');
  
  const requiredDirs = [
    '../lib',
    '../lib/helpers',
    '../lib/pages',
    '../lib/fixtures',
    '../lib/data'
  ];
  
  let allExist = true;
  
  for (const dir of requiredDirs) {
    const dirPath = path.join(__dirname, dir);
    if (fs.existsSync(dirPath)) {
      console.log(`✅ ${dir.replace('../', '')} directory exists`);
    } else {
      console.error(`❌ ${dir.replace('../', '')} directory missing`);
      allExist = false;
    }
  }
  
  // Create test-results directory if it doesn't exist
  const testResultsPath = path.join(__dirname, '../test-results');
  if (!fs.existsSync(testResultsPath)) {
    fs.mkdirSync(testResultsPath, { recursive: true });
    console.log('✅ Created test-results directory');
  }
  
  return allExist;
}

async function main() {
  console.log('🚀 E2E Test Environment Check\\n');
  console.log('================================\\n');
  
  const checks = [
    checkEnvFile(),
    checkEnvironmentVariables(),
    checkNodeModules(),
    checkPlaywrightBrowsers(),
    checkTestDirectories()
  ];
  
  const applicationCheck = await checkApplicationAvailability();
  checks.push(applicationCheck);
  
  console.log('\\n================================');
  
  const allPassed = checks.every(check => check === true);
  
  if (allPassed) {
    console.log('🎉 All checks passed! Ready to run tests.');
    console.log('\\nRun tests with: npm test');
    process.exit(0);
  } else {
    console.log('❌ Some checks failed. Please fix the issues above before running tests.');
    process.exit(1);
  }
}

// Run the checks
if (require.main === module) {
  main().catch(error => {
    console.error('Error during environment check:', error);
    process.exit(1);
  });
}

module.exports = {
  checkEnvironmentVariables,
  checkEnvFile,
  checkApplicationAvailability,
  checkNodeModules,
  checkPlaywrightBrowsers,
  checkTestDirectories
};