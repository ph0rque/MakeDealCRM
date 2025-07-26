const https = require('https');
const http = require('http');
const mysql = require('mysql2/promise');

class HealthChecker {
  constructor() {
    this.baseUrl = process.env.BASE_URL || 'http://localhost:8080';
    this.dbConfig = {
      host: process.env.DB_HOST || 'localhost',
      port: process.env.DB_PORT || 3306,
      user: process.env.DB_USER || 'root',
      password: process.env.DB_PASSWORD || '',
      database: process.env.DB_NAME || 'suitecrm'
    };
    this.timeout = 30000; // 30 seconds
    this.healthReport = {
      timestamp: new Date().toISOString(),
      overall: 'unknown',
      checks: {},
      errors: [],
      duration: 0
    };
  }

  async runHealthChecks() {
    const startTime = Date.now();
    console.log('Starting health checks...');

    try {
      // Run all health checks
      await Promise.all([
        this.checkWebServer(),
        this.checkDatabase(),
        this.checkSuiteCRMStatus(),
        this.checkTestDependencies(),
        this.checkEnvironmentVariables()
      ]);

      // Determine overall health
      const allPassed = Object.values(this.healthReport.checks).every(check => check.status === 'pass');
      this.healthReport.overall = allPassed ? 'healthy' : 'unhealthy';

      this.healthReport.duration = Date.now() - startTime;
      
      // Output results
      this.outputResults();
      
      // Exit with appropriate code
      process.exit(allPassed ? 0 : 1);
      
    } catch (error) {
      this.healthReport.overall = 'error';
      this.healthReport.errors.push(error.message);
      this.healthReport.duration = Date.now() - startTime;
      
      console.error('Health check failed:', error.message);
      this.outputResults();
      process.exit(1);
    }
  }

  async checkWebServer() {
    const checkName = 'web_server';
    console.log('Checking web server...');

    try {
      const response = await this.makeHttpRequest(this.baseUrl);
      
      if (response.statusCode >= 200 && response.statusCode < 400) {
        this.healthReport.checks[checkName] = {
          status: 'pass',
          message: `Web server responding (${response.statusCode})`,
          details: {
            statusCode: response.statusCode,
            responseTime: response.responseTime,
            contentLength: response.contentLength
          }
        };
      } else {
        throw new Error(`HTTP ${response.statusCode}: ${response.statusMessage}`);
      }
    } catch (error) {
      this.healthReport.checks[checkName] = {
        status: 'fail',
        message: `Web server check failed: ${error.message}`,
        details: { error: error.message }
      };
    }
  }

  async checkDatabase() {
    const checkName = 'database';
    console.log('Checking database connection...');

    try {
      const connection = await mysql.createConnection(this.dbConfig);
      
      // Test connection with a simple query
      const [rows] = await connection.execute('SELECT 1 as test');
      
      // Check if SuiteCRM tables exist
      const [tables] = await connection.execute(`
        SELECT COUNT(*) as table_count 
        FROM information_schema.tables 
        WHERE table_schema = ? AND table_name IN ('users', 'config', 'modules')
      `, [this.dbConfig.database]);
      
      await connection.end();
      
      const tableCount = tables[0].table_count;
      
      if (tableCount >= 3) {
        this.healthReport.checks[checkName] = {
          status: 'pass',
          message: 'Database connection successful',
          details: {
            host: this.dbConfig.host,
            database: this.dbConfig.database,
            suitecrm_tables: tableCount
          }
        };
      } else {
        throw new Error('SuiteCRM database tables not found');
      }
      
    } catch (error) {
      this.healthReport.checks[checkName] = {
        status: 'fail',
        message: `Database check failed: ${error.message}`,
        details: { 
          error: error.message,
          config: {
            host: this.dbConfig.host,
            database: this.dbConfig.database
          }
        }
      };
    }
  }

  async checkSuiteCRMStatus() {
    const checkName = 'suitecrm_status';
    console.log('Checking SuiteCRM status...');

    try {
      // Check login page
      const loginResponse = await this.makeHttpRequest(`${this.baseUrl}/index.php`);
      
      if (loginResponse.statusCode !== 200) {
        throw new Error(`Login page returned ${loginResponse.statusCode}`);
      }

      // Check if response contains SuiteCRM indicators
      const hasLogin = loginResponse.body.includes('login') || 
                      loginResponse.body.includes('username') ||
                      loginResponse.body.includes('SuiteCRM');

      if (!hasLogin) {
        throw new Error('SuiteCRM login page not detected');
      }

      // Try to access API endpoint (if available)
      let apiStatus = 'not_tested';
      try {
        const apiResponse = await this.makeHttpRequest(`${this.baseUrl}/api/V8/meta/attributes`);
        apiStatus = apiResponse.statusCode === 200 ? 'available' : 'unavailable';
      } catch (e) {
        apiStatus = 'unavailable';
      }

      this.healthReport.checks[checkName] = {
        status: 'pass',
        message: 'SuiteCRM is accessible',
        details: {
          login_page: 'available',
          api_status: apiStatus,
          response_time: loginResponse.responseTime
        }
      };

    } catch (error) {
      this.healthReport.checks[checkName] = {
        status: 'fail',
        message: `SuiteCRM check failed: ${error.message}`,
        details: { error: error.message }
      };
    }
  }

  async checkTestDependencies() {
    const checkName = 'test_dependencies';
    console.log('Checking test dependencies...');

    try {
      const dependencies = {
        playwright: false,
        mysql2: false,
        dotenv: false
      };

      // Check if required modules can be loaded
      try {
        require('@playwright/test');
        dependencies.playwright = true;
      } catch (e) {
        // Module not available
      }

      try {
        require('mysql2');
        dependencies.mysql2 = true;
      } catch (e) {
        // Module not available
      }

      try {
        require('dotenv');
        dependencies.dotenv = true;
      } catch (e) {
        // Module not available
      }

      const allDependenciesAvailable = Object.values(dependencies).every(Boolean);

      if (allDependenciesAvailable) {
        this.healthReport.checks[checkName] = {
          status: 'pass',
          message: 'All test dependencies available',
          details: dependencies
        };
      } else {
        const missing = Object.entries(dependencies)
          .filter(([_, available]) => !available)
          .map(([name, _]) => name);
        
        throw new Error(`Missing dependencies: ${missing.join(', ')}`);
      }

    } catch (error) {
      this.healthReport.checks[checkName] = {
        status: 'fail',
        message: `Dependency check failed: ${error.message}`,
        details: { error: error.message }
      };
    }
  }

  async checkEnvironmentVariables() {
    const checkName = 'environment_variables';
    console.log('Checking environment variables...');

    try {
      const requiredVars = [
        'BASE_URL'
      ];

      const optionalVars = [
        'DB_HOST',
        'DB_NAME', 
        'DB_USER',
        'ADMIN_USERNAME',
        'ADMIN_PASSWORD'
      ];

      const envStatus = {
        required: {},
        optional: {},
        missing_required: [],
        missing_optional: []
      };

      // Check required variables
      requiredVars.forEach(varName => {
        const value = process.env[varName];
        envStatus.required[varName] = !!value;
        if (!value) {
          envStatus.missing_required.push(varName);
        }
      });

      // Check optional variables
      optionalVars.forEach(varName => {
        const value = process.env[varName];
        envStatus.optional[varName] = !!value;
        if (!value) {
          envStatus.missing_optional.push(varName);
        }
      });

      if (envStatus.missing_required.length > 0) {
        throw new Error(`Missing required environment variables: ${envStatus.missing_required.join(', ')}`);
      }

      let message = 'Environment variables configured';
      if (envStatus.missing_optional.length > 0) {
        message += ` (${envStatus.missing_optional.length} optional vars missing)`;
      }

      this.healthReport.checks[checkName] = {
        status: 'pass',
        message,
        details: envStatus
      };

    } catch (error) {
      this.healthReport.checks[checkName] = {
        status: 'fail',
        message: `Environment check failed: ${error.message}`,
        details: { error: error.message }
      };
    }
  }

  async makeHttpRequest(url) {
    return new Promise((resolve, reject) => {
      const startTime = Date.now();
      const client = url.startsWith('https') ? https : http;
      
      const request = client.get(url, {
        timeout: this.timeout,
        headers: {
          'User-Agent': 'E2E Health Check'
        }
      }, (response) => {
        let body = '';
        
        response.on('data', chunk => {
          body += chunk;
        });
        
        response.on('end', () => {
          resolve({
            statusCode: response.statusCode,
            statusMessage: response.statusMessage,
            responseTime: Date.now() - startTime,
            contentLength: body.length,
            body: body
          });
        });
      });

      request.on('error', (error) => {
        reject(error);
      });

      request.on('timeout', () => {
        request.destroy();
        reject(new Error(`Request timeout after ${this.timeout}ms`));
      });
    });
  }

  outputResults() {
    console.log('\n=== HEALTH CHECK RESULTS ===');
    console.log(`Overall Status: ${this.healthReport.overall.toUpperCase()}`);
    console.log(`Duration: ${this.healthReport.duration}ms`);
    console.log(`Timestamp: ${this.healthReport.timestamp}`);
    
    console.log('\nIndividual Checks:');
    Object.entries(this.healthReport.checks).forEach(([name, check]) => {
      const status = check.status === 'pass' ? '✅' : '❌';
      console.log(`  ${status} ${name}: ${check.message}`);
      
      if (check.status === 'fail' || (check.details && Object.keys(check.details).length > 0)) {
        console.log(`      Details: ${JSON.stringify(check.details, null, 8)}`);
      }
    });

    if (this.healthReport.errors.length > 0) {
      console.log('\nErrors:');
      this.healthReport.errors.forEach(error => {
        console.log(`  ❌ ${error}`);
      });
    }

    // Save report to file
    const fs = require('fs');
    const path = require('path');
    
    const reportDir = path.resolve(__dirname, '../test-results');
    if (!fs.existsSync(reportDir)) {
      fs.mkdirSync(reportDir, { recursive: true });
    }
    
    const reportPath = path.join(reportDir, 'health-check.json');
    fs.writeFileSync(reportPath, JSON.stringify(this.healthReport, null, 2));
    
    console.log(`\nHealth check report saved to: ${reportPath}`);
  }
}

// Run if called directly
if (require.main === module) {
  const healthChecker = new HealthChecker();
  healthChecker.runHealthChecks();
}

module.exports = HealthChecker;