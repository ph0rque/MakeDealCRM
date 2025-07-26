/**
 * Test Environment Configuration System
 * Manages configuration for different test environments (local, CI/CD, staging)
 */

const fs = require('fs');
const path = require('path');

class TestEnvironmentConfig {
  constructor() {
    this.environment = process.env.NODE_ENV || 'test';
    this.configPath = path.join(__dirname, '../../config');
    this.config = {};
    this.secrets = {};
    
    this.loadConfiguration();
  }

  /**
   * Load configuration based on environment
   */
  loadConfiguration() {
    // Load base configuration
    this.loadBaseConfig();
    
    // Load environment-specific configuration
    this.loadEnvironmentConfig();
    
    // Load secrets
    this.loadSecrets();
    
    // Apply environment variables
    this.applyEnvironmentVariables();
    
    // Validate configuration
    this.validateConfiguration();
  }

  /**
   * Load base configuration
   */
  loadBaseConfig() {
    const baseConfigPath = path.join(this.configPath, 'base.json');
    try {
      if (fs.existsSync(baseConfigPath)) {
        this.config = JSON.parse(fs.readFileSync(baseConfigPath, 'utf-8'));
      } else {
        this.config = this.getDefaultBaseConfig();
      }
    } catch (error) {
      console.warn(`Failed to load base config: ${error.message}`);
      this.config = this.getDefaultBaseConfig();
    }
  }

  /**
   * Load environment-specific configuration
   */
  loadEnvironmentConfig() {
    const envConfigPath = path.join(this.configPath, `${this.environment}.json`);
    try {
      if (fs.existsSync(envConfigPath)) {
        const envConfig = JSON.parse(fs.readFileSync(envConfigPath, 'utf-8'));
        this.config = this.mergeConfigs(this.config, envConfig);
      }
    } catch (error) {
      console.warn(`Failed to load environment config for ${this.environment}: ${error.message}`);
    }
  }

  /**
   * Load secrets from secure location
   */
  loadSecrets() {
    const secretsPath = path.join(this.configPath, 'secrets.json');
    try {
      if (fs.existsSync(secretsPath)) {
        this.secrets = JSON.parse(fs.readFileSync(secretsPath, 'utf-8'));
      }
    } catch (error) {
      console.warn(`Failed to load secrets: ${error.message}`);
    }
  }

  /**
   * Apply environment variables to configuration
   */
  applyEnvironmentVariables() {
    const envMappings = {
      // Database configuration
      'DB_HOST': 'database.host',
      'DB_PORT': 'database.port',
      'DB_NAME': 'database.name',
      'DB_USER': 'database.user',
      'DB_PASSWORD': 'database.password',
      
      // Application configuration
      'BASE_URL': 'application.baseUrl',
      'API_URL': 'application.apiUrl',
      'ADMIN_USERNAME': 'application.admin.username',
      'ADMIN_PASSWORD': 'application.admin.password',
      
      // Test configuration
      'TEST_TIMEOUT': 'testing.timeout',
      'TEST_RETRIES': 'testing.retries',
      'TEST_WORKERS': 'testing.workers',
      'TEST_ISOLATION': 'testing.isolation.level',
      
      // Performance configuration
      'MAX_BATCH_SIZE': 'performance.maxBatchSize',
      'PARALLEL_BATCHES': 'performance.parallelBatches',
      'ENABLE_CACHING': 'performance.enableCaching',
      
      // Feature flags
      'ENABLE_VISUAL_REGRESSION': 'features.visualRegression',
      'ENABLE_ACCESSIBILITY_TESTS': 'features.accessibility',
      'ENABLE_PERFORMANCE_TESTS': 'features.performance',
      'ENABLE_API_TESTS': 'features.apiTesting',
      
      // CI/CD specific
      'CI': 'ci.enabled',
      'GITHUB_ACTIONS': 'ci.provider',
      'BUILD_NUMBER': 'ci.buildNumber',
      'BRANCH_NAME': 'ci.branch'
    };

    for (const [envVar, configPath] of Object.entries(envMappings)) {
      if (process.env[envVar]) {
        this.setNestedValue(this.config, configPath, this.parseEnvValue(process.env[envVar]));
      }
    }
  }

  /**
   * Validate configuration
   */
  validateConfiguration() {
    const requiredPaths = [
      'database.host',
      'database.name',
      'application.baseUrl'
    ];

    const missing = [];
    for (const path of requiredPaths) {
      if (!this.getNestedValue(this.config, path)) {
        missing.push(path);
      }
    }

    if (missing.length > 0) {
      throw new Error(`Missing required configuration: ${missing.join(', ')}`);
    }

    // Validate URLs
    try {
      new URL(this.config.application.baseUrl);
    } catch (error) {
      throw new Error(`Invalid base URL: ${this.config.application.baseUrl}`);
    }

    // Validate database port
    if (this.config.database.port && (this.config.database.port < 1 || this.config.database.port > 65535)) {
      throw new Error(`Invalid database port: ${this.config.database.port}`);
    }
  }

  /**
   * Get default base configuration
   */
  getDefaultBaseConfig() {
    return {
      database: {
        host: 'localhost',
        port: 3306,
        name: 'suitecrm',
        user: 'root',
        password: 'root',
        connectionLimit: 10,
        acquireTimeout: 60000,
        timeout: 60000
      },
      
      application: {
        baseUrl: 'http://localhost:8080',
        apiUrl: 'http://localhost:8080/api',
        admin: {
          username: 'admin',
          password: 'admin123'
        },
        testUser: {
          username: 'testuser',
          password: 'testpass123'
        }
      },
      
      testing: {
        timeout: 30000,
        retries: 0,
        workers: 1,
        fullyParallel: false,
        isolation: {
          level: 'test',
          enableNamespacing: true,
          enableTransactions: false,
          enableSnapshots: false
        },
        reporting: {
          html: true,
          junit: true,
          json: true,
          attachments: true
        }
      },
      
      performance: {
        maxBatchSize: 100,
        parallelBatches: 3,
        enableCaching: true,
        enableMetrics: true,
        benchmarkIterations: 1
      },
      
      data: {
        testPrefix: 'E2E_TEST_',
        enableCleanupVerification: true,
        maxCleanupAttempts: 3,
        cleanupTimeout: 60000,
        seedProfiles: {
          minimal: { enabled: true },
          default: { enabled: true },
          performance: { enabled: true },
          stress: { enabled: false }
        }
      },
      
      features: {
        visualRegression: false,
        accessibility: false,
        performance: true,
        apiTesting: true,
        mobileeTesting: false,
        crossBrowser: false
      },
      
      browser: {
        headless: true,
        slowMo: 0,
        timeout: 30000,
        viewport: {
          width: 1280,
          height: 720
        },
        video: 'retain-on-failure',
        screenshot: 'only-on-failure',
        trace: 'on-first-retry'
      },
      
      paths: {
        testData: './test-data',
        screenshots: './test-results/screenshots',
        reports: './test-results',
        logs: './test-results/logs'
      },
      
      logging: {
        level: 'info',
        enableConsole: true,
        enableFile: true,
        enableMetrics: true
      },
      
      ci: {
        enabled: false,
        provider: null,
        buildNumber: null,
        branch: null,
        pullRequest: null
      }
    };
  }

  /**
   * Get environment-specific configuration overrides
   */
  getEnvironmentDefaults() {
    const environments = {
      local: {
        testing: {
          workers: 1,
          fullyParallel: false,
          retries: 0
        },
        browser: {
          headless: false,
          slowMo: 100
        },
        logging: {
          level: 'debug'
        },
        features: {
          visualRegression: true,
          accessibility: true
        }
      },
      
      test: {
        testing: {
          workers: 2,
          fullyParallel: true,
          retries: 1
        },
        browser: {
          headless: true,
          slowMo: 0
        },
        features: {
          performance: true,
          apiTesting: true
        }
      },
      
      ci: {
        testing: {
          workers: 1,
          fullyParallel: false,
          retries: 2,
          timeout: 45000
        },
        browser: {
          headless: true,
          slowMo: 0,
          video: 'retain-on-failure',
          screenshot: 'only-on-failure'
        },
        ci: {
          enabled: true
        },
        features: {
          visualRegression: false,
          accessibility: false,
          performance: false
        },
        logging: {
          level: 'warn'
        }
      },
      
      staging: {
        application: {
          baseUrl: 'https://staging.suitecrm.example.com',
          apiUrl: 'https://staging.suitecrm.example.com/api'
        },
        testing: {
          workers: 3,
          fullyParallel: true,
          retries: 1,
          timeout: 60000
        },
        performance: {
          maxBatchSize: 50,
          parallelBatches: 2
        },
        features: {
          performance: true,
          apiTesting: true,
          crossBrowser: true
        }
      },
      
      production: {
        application: {
          baseUrl: 'https://app.suitecrm.example.com',
          apiUrl: 'https://app.suitecrm.example.com/api'
        },
        testing: {
          workers: 1,
          fullyParallel: false,
          retries: 3,
          timeout: 90000
        },
        performance: {
          maxBatchSize: 25,
          parallelBatches: 1
        },
        features: {
          performance: false,
          apiTesting: true,
          visualRegression: false
        },
        data: {
          enableCleanupVerification: true,
          seedProfiles: {
            minimal: { enabled: true },
            default: { enabled: false },
            performance: { enabled: false },
            stress: { enabled: false }
          }
        }
      }
    };

    return environments[this.environment] || {};
  }

  /**
   * Get configuration value by path
   */
  get(path, defaultValue = undefined) {
    return this.getNestedValue(this.config, path) || defaultValue;
  }

  /**
   * Set configuration value by path
   */
  set(path, value) {
    this.setNestedValue(this.config, path, value);
  }

  /**
   * Get database configuration
   */
  getDatabaseConfig() {
    return {
      ...this.config.database,
      password: this.secrets.database?.password || this.config.database.password
    };
  }

  /**
   * Get application configuration
   */
  getApplicationConfig() {
    return {
      ...this.config.application,
      admin: {
        ...this.config.application.admin,
        password: this.secrets.admin?.password || this.config.application.admin.password
      }
    };
  }

  /**
   * Get Playwright configuration
   */
  getPlaywrightConfig() {
    return {
      testDir: './tests',
      timeout: this.config.testing.timeout,
      expect: {
        timeout: this.config.testing.timeout / 6
      },
      fullyParallel: this.config.testing.fullyParallel,
      forbidOnly: this.config.ci.enabled,
      retries: this.config.testing.retries,
      workers: this.config.testing.workers,
      
      reporter: this.getReporterConfig(),
      
      use: {
        baseURL: this.config.application.baseUrl,
        trace: this.config.browser.trace,
        screenshot: this.config.browser.screenshot,
        video: this.config.browser.video,
        actionTimeout: 0,
        navigationTimeout: this.config.browser.timeout,
        viewport: this.config.browser.viewport,
        ignoreHTTPSErrors: true,
        acceptDownloads: true
      },

      projects: this.getProjectConfig(),
      
      outputDir: this.config.paths.reports,
      
      webServer: this.getWebServerConfig()
    };
  }

  /**
   * Get reporter configuration
   */
  getReporterConfig() {
    const reporters = [];
    
    if (this.config.testing.reporting.html) {
      reporters.push(['html', { outputFolder: path.join(this.config.paths.reports, 'html-report') }]);
    }
    
    if (this.config.testing.reporting.junit) {
      reporters.push(['junit', { outputFile: path.join(this.config.paths.reports, 'junit.xml') }]);
    }
    
    if (this.config.testing.reporting.json) {
      reporters.push(['json', { outputFile: path.join(this.config.paths.reports, 'test-results.json') }]);
    }
    
    reporters.push(['list']);
    
    return reporters;
  }

  /**
   * Get project configuration for different browsers/devices
   */
  getProjectConfig() {
    const projects = [
      {
        name: 'chromium',
        use: { 
          ...require('@playwright/test').devices['Desktop Chrome'],
          launchOptions: {
            args: ['--disable-dev-shm-usage', '--no-sandbox']
          }
        }
      }
    ];

    if (this.config.features.crossBrowser) {
      projects.push(
        {
          name: 'firefox',
          use: { ...require('@playwright/test').devices['Desktop Firefox'] }
        },
        {
          name: 'webkit',
          use: { ...require('@playwright/test').devices['Desktop Safari'] }
        }
      );
    }

    if (this.config.features.mobileTesting) {
      projects.push(
        {
          name: 'Mobile Chrome',
          use: { ...require('@playwright/test').devices['Pixel 5'] }
        },
        {
          name: 'Mobile Safari',
          use: { ...require('@playwright/test').devices['iPhone 12'] }
        }
      );
    }

    if (this.config.features.apiTesting) {
      projects.push({
        name: 'api',
        use: {
          baseURL: this.config.application.apiUrl,
          extraHTTPHeaders: {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          }
        }
      });
    }

    return projects;
  }

  /**
   * Get web server configuration
   */
  getWebServerConfig() {
    if (this.environment === 'local') {
      return {
        command: 'docker-compose up',
        port: new URL(this.config.application.baseUrl).port || 80,
        timeout: 120 * 1000,
        reuseExistingServer: !this.config.ci.enabled,
        cwd: '../../../'
      };
    }
    
    return undefined;
  }

  /**
   * Get test data configuration
   */
  getTestDataConfig() {
    return {
      ...this.config.data,
      isolation: this.config.testing.isolation,
      performance: this.config.performance
    };
  }

  /**
   * Check if feature is enabled
   */
  isFeatureEnabled(feature) {
    return this.config.features[feature] === true;
  }

  /**
   * Get environment-specific test tags
   */
  getTestTags() {
    const tags = [`@${this.environment}`];
    
    if (this.config.ci.enabled) {
      tags.push('@ci');
    }
    
    if (this.isFeatureEnabled('performance')) {
      tags.push('@performance');
    }
    
    if (this.isFeatureEnabled('accessibility')) {
      tags.push('@accessibility');
    }
    
    return tags;
  }

  /**
   * Generate environment report
   */
  generateEnvironmentReport() {
    return {
      environment: this.environment,
      timestamp: new Date().toISOString(),
      configuration: {
        database: {
          ...this.config.database,
          password: '[REDACTED]'
        },
        application: {
          ...this.config.application,
          admin: {
            ...this.config.application.admin,
            password: '[REDACTED]'
          }
        },
        testing: this.config.testing,
        features: this.config.features,
        ci: this.config.ci
      },
      enabledFeatures: Object.entries(this.config.features)
        .filter(([_, enabled]) => enabled)
        .map(([feature, _]) => feature),
      paths: this.config.paths
    };
  }

  // Utility methods

  mergeConfigs(base, override) {
    const result = { ...base };
    
    for (const [key, value] of Object.entries(override)) {
      if (value && typeof value === 'object' && !Array.isArray(value)) {
        result[key] = this.mergeConfigs(result[key] || {}, value);
      } else {
        result[key] = value;
      }
    }
    
    return result;
  }

  getNestedValue(obj, path) {
    return path.split('.').reduce((current, key) => current?.[key], obj);
  }

  setNestedValue(obj, path, value) {
    const keys = path.split('.');
    const lastKey = keys.pop();
    const target = keys.reduce((current, key) => {
      if (!current[key] || typeof current[key] !== 'object') {
        current[key] = {};
      }
      return current[key];
    }, obj);
    
    target[lastKey] = value;
  }

  parseEnvValue(value) {
    // Convert string values to appropriate types
    if (value === 'true') return true;
    if (value === 'false') return false;
    if (!isNaN(value) && !isNaN(parseFloat(value))) return parseFloat(value);
    return value;
  }

  /**
   * Create configuration files for different environments
   */
  static createConfigFiles(configPath) {
    const environments = ['local', 'test', 'ci', 'staging', 'production'];
    
    if (!fs.existsSync(configPath)) {
      fs.mkdirSync(configPath, { recursive: true });
    }

    // Create base config
    const baseConfig = new TestEnvironmentConfig().getDefaultBaseConfig();
    fs.writeFileSync(
      path.join(configPath, 'base.json'),
      JSON.stringify(baseConfig, null, 2)
    );

    // Create environment-specific configs
    const envConfig = new TestEnvironmentConfig();
    for (const env of environments) {
      const envOverrides = envConfig.getEnvironmentDefaults()[env] || {};
      fs.writeFileSync(
        path.join(configPath, `${env}.json`),
        JSON.stringify(envOverrides, null, 2)
      );
    }

    // Create secrets template
    const secretsTemplate = {
      database: {
        password: 'your-db-password'
      },
      admin: {
        password: 'your-admin-password'
      }
    };
    
    fs.writeFileSync(
      path.join(configPath, 'secrets.json.template'),
      JSON.stringify(secretsTemplate, null, 2)
    );

    console.log(`Configuration files created in ${configPath}`);
  }
}

// Create singleton instance
const config = new TestEnvironmentConfig();

module.exports = config;
module.exports.TestEnvironmentConfig = TestEnvironmentConfig;