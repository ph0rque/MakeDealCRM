/**
 * Enhanced Test Data Manager
 * Comprehensive test data setup/teardown utilities for E2E tests
 * Extends the existing test-data-helper.js with advanced features
 */

const mysql = require('mysql2/promise');
const crypto = require('crypto');
const fs = require('fs').promises;
const path = require('path');

class EnhancedTestDataManager {
  constructor(config = {}) {
    this.config = {
      testPrefix: config.testPrefix || 'E2E_TEST_',
      batchSize: config.batchSize || 100,
      enableCaching: config.enableCaching !== false,
      enableMetrics: config.enableMetrics !== false,
      ...config
    };
    
    this.connection = null;
    this.testRunId = this.generateTestRunId();
    this.createdRecords = new Map(); // module -> Set<id>
    this.relationships = new Map(); // Track created relationships
    this.cache = new Map(); // Data caching for performance
    this.metrics = {
      startTime: Date.now(),
      operationCounts: {},
      executionTimes: {},
      memoryUsage: []
    };
    
    // Test data isolation
    this.isolationLevel = config.isolationLevel || 'test'; // 'test', 'suite', 'global'
    this.testContext = null;
    
    // Performance tracking
    this.performanceThresholds = {
      bulkInsert: 5000, // ms
      singleInsert: 1000, // ms
      cleanup: 10000 // ms
    };
  }

  /**
   * Initialize the test data manager
   */
  async initialize(testContext = {}) {
    this.testContext = testContext;
    await this.connect();
    await this.setupIsolation();
    this.recordMetric('initialize', Date.now() - this.metrics.startTime);
  }

  /**
   * Connect to database with enhanced configuration
   */
  async connect() {
    const dbConfig = {
      host: process.env.DB_HOST || 'localhost',
      user: process.env.DB_USER || 'root',
      password: process.env.DB_PASSWORD || 'root',
      database: process.env.DB_NAME || 'suitecrm',
      port: process.env.DB_PORT || 3306,
      // Performance optimizations
      acquireTimeout: 60000,
      timeout: 60000,
      reconnect: true,
      // Connection pooling for bulk operations
      connectionLimit: 10,
      queueLimit: 0
    };

    this.connection = await mysql.createConnection(dbConfig);
    
    // Test connection
    await this.connection.execute('SELECT 1');
    console.log(`✓ Database connected for test run: ${this.testRunId}`);
  }

  /**
   * Setup test data isolation
   */
  async setupIsolation() {
    switch (this.isolationLevel) {
      case 'test':
        // Each test gets its own isolated data set
        this.isolationId = `${this.testRunId}_${Date.now()}`;
        break;
      case 'suite':
        // Test suite shares data but isolated from other suites
        this.isolationId = this.testContext.suite || this.testRunId;
        break;
      case 'global':
        // All tests share the same data set
        this.isolationId = 'global';
        break;
    }

    // Initialize record tracking for this isolation level
    if (!this.createdRecords.has(this.isolationId)) {
      this.createdRecords.set(this.isolationId, new Map());
    }
  }

  /**
   * Generate unique test run ID with timestamp and hash
   */
  generateTestRunId() {
    const timestamp = Date.now();
    const hash = crypto.randomBytes(4).toString('hex');
    return `test_${timestamp}_${hash}`;
  }

  /**
   * Generate unique identifier with isolation support
   */
  generateId(prefix = 'test') {
    return `${this.config.testPrefix}${prefix}_${this.isolationId}_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  }

  /**
   * Create test data with enhanced features
   */
  async createTestData(module, data, options = {}) {
    const startTime = Date.now();
    
    try {
      const {
        skipCache = false,
        enableRelationships = true,
        validateData = true,
        returnFullRecord = false
      } = options;

      // Check cache first
      const cacheKey = this.getCacheKey(module, data);
      if (this.config.enableCaching && !skipCache && this.cache.has(cacheKey)) {
        return this.cache.get(cacheKey);
      }

      // Validate data if requested
      if (validateData) {
        await this.validateData(module, data);
      }

      // Generate ID and prepare data
      const id = data.id || this.generateId(module.toLowerCase());
      const preparedData = await this.prepareData(module, { ...data, id });

      // Insert main record
      const result = await this.insertRecord(module, preparedData);

      // Handle custom fields
      if (preparedData._customFields) {
        await this.insertCustomFields(module, id, preparedData._customFields);
      }

      // Handle relationships
      if (enableRelationships && preparedData._relationships) {
        await this.createRelationships(module, id, preparedData._relationships);
      }

      // Track created record
      this.trackRecord(module, id);

      // Cache result
      if (this.config.enableCaching) {
        this.cache.set(cacheKey, result);
      }

      // Record metrics
      this.recordMetric(`create_${module}`, Date.now() - startTime);
      this.incrementOperationCount(`create_${module}`);

      return returnFullRecord ? await this.getRecord(module, id) : result;

    } catch (error) {
      this.recordMetric(`create_${module}_error`, Date.now() - startTime);
      throw new Error(`Failed to create ${module} test data: ${error.message}`);
    }
  }

  /**
   * Bulk create test data for performance testing
   */
  async createBulkTestData(module, dataArray, options = {}) {
    const startTime = Date.now();
    const {
      batchSize = this.config.batchSize,
      enableProgressTracking = true,
      validateAll = false
    } = options;

    try {
      const results = [];
      const batches = this.createBatches(dataArray, batchSize);

      console.log(`Creating ${dataArray.length} ${module} records in ${batches.length} batches...`);

      for (let i = 0; i < batches.length; i++) {
        const batch = batches[i];
        const batchResults = await this.processBatch(module, batch, options);
        results.push(...batchResults);

        if (enableProgressTracking) {
          const progress = Math.round(((i + 1) / batches.length) * 100);
          console.log(`Progress: ${progress}% (${results.length}/${dataArray.length})`);
        }
      }

      const duration = Date.now() - startTime;
      this.recordMetric(`bulk_create_${module}`, duration);
      this.recordMetric(`bulk_create_${module}_rate`, dataArray.length / (duration / 1000));

      if (duration > this.performanceThresholds.bulkInsert) {
        console.warn(`⚠️ Bulk insert performance warning: ${duration}ms (threshold: ${this.performanceThresholds.bulkInsert}ms)`);
      }

      return results;

    } catch (error) {
      throw new Error(`Bulk create failed for ${module}: ${error.message}`);
    }
  }

  /**
   * Create test scenario with related data
   */
  async createTestScenario(scenarioName, options = {}) {
    const startTime = Date.now();
    
    const scenarios = {
      'complete-deal-lifecycle': async () => {
        const account = await this.createTestData('accounts', this.generateAccountData());
        const contacts = await this.createBulkTestData('contacts', 
          Array.from({length: 3}, () => this.generateContactData({ account_id: account.id }))
        );
        const deal = await this.createTestData('deals', this.generateDealData({ 
          account_id: account.id,
          contact_ids: contacts.map(c => c.id)
        }));
        
        // Create deal documents
        const documents = await this.createBulkTestData('documents',
          Array.from({length: 5}, () => this.generateDocumentData({ deal_id: deal.id }))
        );

        // Create checklist items
        const checklists = await this.createBulkTestData('checklists',
          Array.from({length: 10}, () => this.generateChecklistData({ deal_id: deal.id }))
        );

        return { account, contacts, deal, documents, checklists };
      },

      'pipeline-performance-test': async () => {
        const stages = ['sourcing', 'initial_contact', 'nda_signed', 'due_diligence', 'loi_submitted', 'closed_won'];
        const dealsPerStage = options.dealsPerStage || 20;
        
        const pipeline = {};
        for (const stage of stages) {
          pipeline[stage] = await this.createBulkTestData('deals',
            Array.from({length: dealsPerStage}, () => this.generateDealData({ status: stage }))
          );
        }

        return pipeline;
      },

      'duplicate-detection-test': async () => {
        const baseAccount = await this.createTestData('accounts', this.generateAccountData());
        const baseContact = await this.createTestData('contacts', this.generateContactData());

        // Create exact duplicates
        const exactDuplicates = await this.createBulkTestData('deals', [
          this.generateDealData({ name: 'Duplicate Deal Test', email: 'test@duplicate.com' }),
          this.generateDealData({ name: 'Duplicate Deal Test', email: 'test@duplicate.com' })
        ]);

        // Create near duplicates
        const nearDuplicates = await this.createBulkTestData('deals', [
          this.generateDealData({ name: 'Similar Deal Test', email: 'similar1@test.com' }),
          this.generateDealData({ name: 'Similar Deal Testing', email: 'similar2@test.com' })
        ]);

        return { baseAccount, baseContact, exactDuplicates, nearDuplicates };
      },

      'relationship-stress-test': async () => {
        const accounts = await this.createBulkTestData('accounts', 
          Array.from({length: 10}, () => this.generateAccountData())
        );
        
        const contacts = await this.createBulkTestData('contacts',
          Array.from({length: 50}, () => this.generateContactData({ 
            account_id: accounts[Math.floor(Math.random() * accounts.length)].id 
          }))
        );

        const deals = await this.createBulkTestData('deals',
          Array.from({length: 100}, () => {
            const randomAccount = accounts[Math.floor(Math.random() * accounts.length)];
            const randomContacts = contacts
              .filter(c => c.account_id === randomAccount.id)
              .slice(0, Math.floor(Math.random() * 3) + 1);
            
            return this.generateDealData({
              account_id: randomAccount.id,
              contact_ids: randomContacts.map(c => c.id)
            });
          })
        );

        return { accounts, contacts, deals };
      }
    };

    const scenarioGenerator = scenarios[scenarioName];
    if (!scenarioGenerator) {
      throw new Error(`Unknown scenario: ${scenarioName}`);
    }

    console.log(`🎬 Creating test scenario: ${scenarioName}`);
    const result = await scenarioGenerator();
    
    const duration = Date.now() - startTime;
    this.recordMetric(`scenario_${scenarioName}`, duration);
    
    console.log(`✅ Scenario '${scenarioName}' created in ${duration}ms`);
    return result;
  }

  /**
   * Database state verification helpers
   */
  async verifyDatabaseState(expectedState) {
    const actualState = await this.getCurrentDatabaseState();
    const differences = this.compareDatabaseStates(expectedState, actualState);
    
    if (differences.length > 0) {
      throw new Error(`Database state verification failed: ${JSON.stringify(differences, null, 2)}`);
    }
    
    return true;
  }

  async getCurrentDatabaseState() {
    const state = {};
    const modules = ['deals', 'accounts', 'contacts', 'documents'];
    
    for (const module of modules) {
      const [rows] = await this.connection.execute(
        `SELECT COUNT(*) as count FROM ${module} WHERE deleted = 0 AND name LIKE ?`,
        [`${this.config.testPrefix}%`]
      );
      state[module] = rows[0].count;
    }
    
    return state;
  }

  compareDatabaseStates(expected, actual) {
    const differences = [];
    
    for (const [module, expectedCount] of Object.entries(expected)) {
      const actualCount = actual[module] || 0;
      if (expectedCount !== actualCount) {
        differences.push({
          module,
          expected: expectedCount,
          actual: actualCount,
          difference: actualCount - expectedCount
        });
      }
    }
    
    return differences;
  }

  /**
   * Performance benchmarking utilities
   */
  async benchmarkOperation(operationName, operation, iterations = 1) {
    const results = [];
    
    console.log(`🔬 Benchmarking '${operationName}' (${iterations} iterations)...`);
    
    for (let i = 0; i < iterations; i++) {
      const startTime = Date.now();
      const startMemory = process.memoryUsage();
      
      try {
        const result = await operation();
        const endTime = Date.now();
        const endMemory = process.memoryUsage();
        
        results.push({
          iteration: i + 1,
          duration: endTime - startTime,
          memoryDelta: {
            rss: endMemory.rss - startMemory.rss,
            heapUsed: endMemory.heapUsed - startMemory.heapUsed,
            heapTotal: endMemory.heapTotal - startMemory.heapTotal
          },
          result
        });
      } catch (error) {
        results.push({
          iteration: i + 1,
          error: error.message
        });
      }
    }
    
    const benchmark = this.analyzeBenchmarkResults(operationName, results);
    console.log(`📊 Benchmark results for '${operationName}':`, benchmark.summary);
    
    return benchmark;
  }

  analyzeBenchmarkResults(operationName, results) {
    const validResults = results.filter(r => !r.error);
    const durations = validResults.map(r => r.duration);
    const memoryDeltas = validResults.map(r => r.memoryDelta);
    
    const summary = {
      operationName,
      totalIterations: results.length,
      successfulIterations: validResults.length,
      failedIterations: results.length - validResults.length,
      performance: {
        min: Math.min(...durations),
        max: Math.max(...durations),
        avg: durations.reduce((a, b) => a + b, 0) / durations.length,
        median: this.calculateMedian(durations)
      },
      memory: {
        avgRssDelta: memoryDeltas.reduce((a, b) => a + b.rss, 0) / memoryDeltas.length,
        avgHeapDelta: memoryDeltas.reduce((a, b) => a + b.heapUsed, 0) / memoryDeltas.length
      }
    };
    
    return { summary, rawResults: results };
  }

  calculateMedian(numbers) {
    const sorted = numbers.slice().sort((a, b) => a - b);
    const middle = Math.floor(sorted.length / 2);
    return sorted.length % 2 === 0 
      ? (sorted[middle - 1] + sorted[middle]) / 2 
      : sorted[middle];
  }

  /**
   * Memory-efficient data management
   */
  enableMemoryOptimization() {
    // Clear cache periodically
    setInterval(() => {
      if (this.cache.size > 1000) {
        console.log('🧹 Clearing cache to free memory...');
        this.cache.clear();
      }
    }, 30000);

    // Monitor memory usage
    setInterval(() => {
      const usage = process.memoryUsage();
      this.metrics.memoryUsage.push({
        timestamp: Date.now(),
        rss: usage.rss,
        heapUsed: usage.heapUsed,
        heapTotal: usage.heapTotal
      });

      // Keep only last 100 measurements
      if (this.metrics.memoryUsage.length > 100) {
        this.metrics.memoryUsage = this.metrics.memoryUsage.slice(-100);
      }
    }, 5000);
  }

  /**
   * Comprehensive cleanup with isolation support
   */
  async cleanup(options = {}) {
    const startTime = Date.now();
    const {
      skipVerification = false,
      forceCleanup = false,
      cleanupTimeout = 30000
    } = options;

    try {
      console.log(`🧹 Starting cleanup for isolation: ${this.isolationId}`);
      
      // Get records for current isolation level
      const recordsToClean = this.createdRecords.get(this.isolationId) || new Map();
      let totalCleaned = 0;

      // Clean in reverse dependency order
      const cleanupOrder = ['documents', 'checklists', 'deals', 'contacts', 'accounts', 'users'];
      
      for (const module of cleanupOrder) {
        const moduleRecords = recordsToClean.get(module);
        if (moduleRecords && moduleRecords.size > 0) {
          const cleaned = await this.cleanupModule(module, Array.from(moduleRecords));
          totalCleaned += cleaned;
          console.log(`✓ Cleaned ${cleaned} ${module} records`);
        }
      }

      // Clean relationships
      await this.cleanupRelationships();

      // Clean custom fields
      await this.cleanupCustomFields();

      // Verify cleanup if requested
      if (!skipVerification) {
        await this.verifyCleanup();
      }

      // Clear tracking data
      this.createdRecords.delete(this.isolationId);
      this.relationships.clear();
      this.cache.clear();

      const duration = Date.now() - startTime;
      this.recordMetric('cleanup', duration);
      
      console.log(`✅ Cleanup completed: ${totalCleaned} records cleaned in ${duration}ms`);
      
      if (duration > this.performanceThresholds.cleanup) {
        console.warn(`⚠️ Cleanup performance warning: ${duration}ms (threshold: ${this.performanceThresholds.cleanup}ms)`);
      }

      return { totalCleaned, duration };

    } catch (error) {
      console.error('❌ Cleanup failed:', error);
      
      if (forceCleanup) {
        console.log('🔥 Attempting force cleanup...');
        await this.forceCleanup();
      }
      
      throw error;
    }
  }

  async cleanupModule(module, ids) {
    if (ids.length === 0) return 0;

    // Use soft delete for SuiteCRM modules
    const [result] = await this.connection.execute(
      `UPDATE ${module} SET deleted = 1, date_modified = NOW() WHERE id IN (${ids.map(() => '?').join(',')})`,
      ids
    );

    return result.affectedRows;
  }

  async forceCleanup() {
    // Emergency cleanup using prefix matching
    const tables = ['deals', 'accounts', 'contacts', 'documents'];
    
    for (const table of tables) {
      try {
        await this.connection.execute(
          `UPDATE ${table} SET deleted = 1 WHERE name LIKE ?`,
          [`${this.config.testPrefix}%`]
        );
      } catch (error) {
        console.error(`Force cleanup failed for ${table}:`, error.message);
      }
    }
  }

  /**
   * Get comprehensive test metrics
   */
  getMetrics() {
    const currentTime = Date.now();
    const totalDuration = currentTime - this.metrics.startTime;
    
    return {
      testRunId: this.testRunId,
      isolationLevel: this.isolationLevel,
      isolationId: this.isolationId,
      duration: totalDuration,
      operationCounts: this.metrics.operationCounts,
      executionTimes: this.metrics.executionTimes,
      averageExecutionTimes: this.calculateAverageExecutionTimes(),
      memoryUsage: this.getMemoryUsageSummary(),
      cacheStats: {
        size: this.cache.size,
        hitRate: this.calculateCacheHitRate()
      },
      recordsCreated: this.getRecordCounts(),
      performanceWarnings: this.getPerformanceWarnings()
    };
  }

  // Helper methods for data generation and utilities
  generateAccountData(overrides = {}) {
    const id = this.generateId('account');
    return {
      id,
      name: `${this.config.testPrefix}Test Account ${id}`,
      account_type: 'Customer',
      industry: 'Technology',
      annual_revenue: Math.floor(Math.random() * 10000000).toString(),
      phone_office: this.generatePhone(),
      website: `https://test-${id}.com`,
      date_entered: new Date().toISOString().slice(0, 19).replace('T', ' '),
      date_modified: new Date().toISOString().slice(0, 19).replace('T', ' '),
      created_by: '1',
      modified_user_id: '1',
      deleted: 0,
      ...overrides
    };
  }

  generateContactData(overrides = {}) {
    const id = this.generateId('contact');
    return {
      id,
      first_name: `TestFirst${id}`,
      last_name: `TestLast${id}`,
      email1: `test-${id}@example.com`,
      phone_work: this.generatePhone(),
      title: 'Test Manager',
      department: 'Testing',
      date_entered: new Date().toISOString().slice(0, 19).replace('T', ' '),
      date_modified: new Date().toISOString().slice(0, 19).replace('T', ' '),
      created_by: '1',
      modified_user_id: '1',
      deleted: 0,
      ...overrides
    };
  }

  generateDealData(overrides = {}) {
    const id = this.generateId('deal');
    return {
      id,
      name: `${this.config.testPrefix}Test Deal ${id}`,
      amount: Math.floor(Math.random() * 1000000) + 10000,
      sales_stage: 'Prospecting',
      probability: Math.floor(Math.random() * 100),
      date_closed: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10),
      deal_type: 'New Business',
      lead_source: 'Website',
      description: `Test deal created at ${new Date().toISOString()}`,
      date_entered: new Date().toISOString().slice(0, 19).replace('T', ' '),
      date_modified: new Date().toISOString().slice(0, 19).replace('T', ' '),
      created_by: '1',
      modified_user_id: '1',
      assigned_user_id: '1',
      deleted: 0,
      ...overrides
    };
  }

  generateDocumentData(overrides = {}) {
    const id = this.generateId('document');
    return {
      id,
      document_name: `${this.config.testPrefix}Test Document ${id}`,
      filename: `test-doc-${id}.pdf`,
      file_mime_type: 'application/pdf',
      category_id: 'test_category',
      status_id: 'Active',
      date_entered: new Date().toISOString().slice(0, 19).replace('T', ' '),
      date_modified: new Date().toISOString().slice(0, 19).replace('T', ' '),
      created_by: '1',
      modified_user_id: '1',
      deleted: 0,
      ...overrides
    };
  }

  generateChecklistData(overrides = {}) {
    const id = this.generateId('checklist');
    return {
      id,
      name: `${this.config.testPrefix}Test Checklist Item ${id}`,
      description: `Test checklist item created at ${new Date().toISOString()}`,
      status: 'Not Started',
      priority: 'Medium',
      due_date: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10),
      date_entered: new Date().toISOString().slice(0, 19).replace('T', ' '),
      date_modified: new Date().toISOString().slice(0, 19).replace('T', ' '),
      created_by: '1',
      modified_user_id: '1',
      assigned_user_id: '1',
      deleted: 0,
      ...overrides
    };
  }

  generatePhone() {
    return `555-${Math.floor(Math.random() * 900) + 100}-${Math.floor(Math.random() * 9000) + 1000}`;
  }

  // Additional helper methods would be implemented here...
  // (Continuing with remaining utility methods)

  recordMetric(operation, value) {
    if (!this.config.enableMetrics) return;
    
    if (!this.metrics.executionTimes[operation]) {
      this.metrics.executionTimes[operation] = [];
    }
    this.metrics.executionTimes[operation].push(value);
  }

  incrementOperationCount(operation) {
    if (!this.config.enableMetrics) return;
    
    this.metrics.operationCounts[operation] = (this.metrics.operationCounts[operation] || 0) + 1;
  }

  trackRecord(module, id) {
    const isolationRecords = this.createdRecords.get(this.isolationId) || new Map();
    const moduleRecords = isolationRecords.get(module) || new Set();
    moduleRecords.add(id);
    isolationRecords.set(module, moduleRecords);
    this.createdRecords.set(this.isolationId, isolationRecords);
  }

  async disconnect() {
    if (this.connection) {
      await this.connection.end();
      this.connection = null;
    }
  }
}

module.exports = EnhancedTestDataManager;