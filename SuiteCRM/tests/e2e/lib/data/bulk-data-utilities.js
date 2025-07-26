/**
 * Bulk Data Creation Utilities
 * High-performance bulk data operations for E2E testing
 */

const EnhancedTestDataManager = require('./enhanced-test-data-manager');

class BulkDataUtilities extends EnhancedTestDataManager {
  constructor(config = {}) {
    super(config);
    
    this.bulkConfig = {
      maxBatchSize: config.maxBatchSize || 500,
      parallelBatches: config.parallelBatches || 3,
      retryAttempts: config.retryAttempts || 3,
      retryDelay: config.retryDelay || 1000,
      enableCompression: config.enableCompression !== false,
      enableProgressBar: config.enableProgressBar !== false,
      ...config.bulkConfig
    };

    this.bulkStats = {
      totalRecordsCreated: 0,
      batchesProcessed: 0,
      failedBatches: 0,
      averageBatchTime: 0,
      totalBulkTime: 0
    };
  }

  /**
   * Create massive datasets for performance testing
   */
  async createPerformanceDataset(config) {
    const startTime = Date.now();
    
    const {
      accounts = 100,
      contactsPerAccount = 5,
      dealsPerAccount = 10,
      documentsPerDeal = 3,
      checklistsPerDeal = 8,
      enableRelationships = true,
      seedData = false
    } = config;

    console.log(`🚀 Creating performance dataset:`);
    console.log(`   - ${accounts} accounts`);
    console.log(`   - ${accounts * contactsPerAccount} contacts`);
    console.log(`   - ${accounts * dealsPerAccount} deals`);
    console.log(`   - ${accounts * dealsPerAccount * documentsPerDeal} documents`);
    console.log(`   - ${accounts * dealsPerAccount * checklistsPerDeal} checklist items`);

    const dataset = {
      accounts: [],
      contacts: [],
      deals: [],
      documents: [],
      checklists: []
    };

    try {
      // Phase 1: Create accounts
      console.log('📊 Phase 1: Creating accounts...');
      dataset.accounts = await this.createBulkAccounts(accounts);

      // Phase 2: Create contacts
      console.log('👥 Phase 2: Creating contacts...');
      dataset.contacts = await this.createBulkContactsForAccounts(
        dataset.accounts, 
        contactsPerAccount
      );

      // Phase 3: Create deals
      console.log('💼 Phase 3: Creating deals...');
      dataset.deals = await this.createBulkDealsForAccounts(
        dataset.accounts, 
        dealsPerAccount,
        enableRelationships ? dataset.contacts : []
      );

      // Phase 4: Create documents
      console.log('📄 Phase 4: Creating documents...');
      dataset.documents = await this.createBulkDocumentsForDeals(
        dataset.deals,
        documentsPerDeal
      );

      // Phase 5: Create checklists
      console.log('✅ Phase 5: Creating checklists...');
      dataset.checklists = await this.createBulkChecklistsForDeals(
        dataset.deals,
        checklistsPerDeal
      );

      // Seed additional data if requested
      if (seedData) {
        console.log('🌱 Phase 6: Seeding additional data...');
        await this.seedAdditionalData(dataset);
      }

      const totalTime = Date.now() - startTime;
      this.bulkStats.totalBulkTime = totalTime;

      const totalRecords = dataset.accounts.length + dataset.contacts.length + 
                          dataset.deals.length + dataset.documents.length + 
                          dataset.checklists.length;

      console.log(`✅ Performance dataset created:`);
      console.log(`   - Total records: ${totalRecords}`);
      console.log(`   - Total time: ${totalTime}ms`);
      console.log(`   - Records/second: ${Math.round(totalRecords / (totalTime / 1000))}`);

      return dataset;

    } catch (error) {
      console.error('❌ Performance dataset creation failed:', error);
      await this.cleanup({ forceCleanup: true });
      throw error;
    }
  }

  /**
   * Create bulk accounts with optimized batch processing
   */
  async createBulkAccounts(count) {
    const accountsData = Array.from({ length: count }, (_, i) => 
      this.generateAccountData({
        name: `${this.config.testPrefix}Perf Account ${i + 1}`,
        annual_revenue: this.generateRevenueBySize(i % 4), // Vary sizes
        industry: this.getRandomIndustry()
      })
    );

    return await this.processBulkData('accounts', accountsData, {
      batchSize: 100,
      enableOptimizations: true
    });
  }

  /**
   * Create bulk contacts linked to accounts
   */
  async createBulkContactsForAccounts(accounts, contactsPerAccount) {
    const contactsData = [];
    
    for (const account of accounts) {
      for (let i = 0; i < contactsPerAccount; i++) {
        contactsData.push(this.generateContactData({
          account_id: account.id,
          first_name: `Contact${i + 1}`,
          last_name: `For${account.name.replace(/[^a-zA-Z0-9]/g, '')}`,
          email1: `contact${i + 1}.${account.id}@testdomain.com`,
          title: this.getRandomJobTitle()
        }));
      }
    }

    return await this.processBulkData('contacts', contactsData, {
      batchSize: 200,
      createRelationships: true,
      relationshipData: { module: 'accounts', field: 'account_id' }
    });
  }

  /**
   * Create bulk deals with account and contact relationships
   */
  async createBulkDealsForAccounts(accounts, dealsPerAccount, contacts = []) {
    const dealsData = [];
    const contactsByAccount = this.groupContactsByAccount(contacts);
    
    for (const account of accounts) {
      const accountContacts = contactsByAccount[account.id] || [];
      
      for (let i = 0; i < dealsPerAccount; i++) {
        const dealValue = this.generateDealValueByStage(i % 6);
        const stage = this.getDealStageByIndex(i % 6);
        
        dealsData.push(this.generateDealData({
          name: `${account.name} - Deal ${i + 1}`,
          account_id: account.id,
          amount: dealValue,
          sales_stage: stage,
          probability: this.getProbabilityByStage(stage),
          contact_ids: accountContacts.slice(0, Math.min(2, accountContacts.length)).map(c => c.id),
          deal_type: i % 2 === 0 ? 'New Business' : 'Existing Business'
        }));
      }
    }

    return await this.processBulkData('deals', dealsData, {
      batchSize: 150,
      createRelationships: true,
      enableCustomFields: true
    });
  }

  /**
   * Create bulk documents for deals
   */
  async createBulkDocumentsForDeals(deals, documentsPerDeal) {
    const documentsData = [];
    const documentTypes = ['NDA', 'LOI', 'Purchase Agreement', 'Financial Statement', 'Due Diligence Report'];
    
    for (const deal of deals) {
      for (let i = 0; i < documentsPerDeal; i++) {
        const docType = documentTypes[i % documentTypes.length];
        documentsData.push(this.generateDocumentData({
          document_name: `${deal.name} - ${docType}`,
          deal_id: deal.id,
          filename: `${docType.toLowerCase().replace(/\s+/g, '_')}_${deal.id}_${i + 1}.pdf`,
          category_id: this.getDocumentCategory(docType),
          status_id: i === 0 ? 'Active' : 'Draft'
        }));
      }
    }

    return await this.processBulkData('documents', documentsData, {
      batchSize: 300,
      createRelationships: true
    });
  }

  /**
   * Create bulk checklist items for deals
   */
  async createBulkChecklistsForDeals(deals, checklistsPerDeal) {
    const checklistsData = [];
    const checklistTemplates = [
      'Initial Contact Made', 'NDA Signed', 'Financial Review', 'Management Presentation',
      'Site Visit Completed', 'LOI Submitted', 'Due Diligence Started', 'Legal Review'
    ];
    
    for (const deal of deals) {
      for (let i = 0; i < checklistsPerDeal && i < checklistTemplates.length; i++) {
        const template = checklistTemplates[i];
        checklistsData.push(this.generateChecklistData({
          name: `${deal.name} - ${template}`,
          deal_id: deal.id,
          description: `${template} for ${deal.name}`,
          status: this.getChecklistStatusByIndex(i),
          priority: this.getChecklistPriorityByIndex(i),
          due_date: this.getChecklistDueDate(deal.date_closed, i),
          order_index: i + 1
        }));
      }
    }

    return await this.processBulkData('checklists', checklistsData, {
      batchSize: 400,
      createRelationships: true
    });
  }

  /**
   * Process bulk data with advanced optimizations
   */
  async processBulkData(module, dataArray, options = {}) {
    const startTime = Date.now();
    const {
      batchSize = this.bulkConfig.maxBatchSize,
      enableOptimizations = true,
      createRelationships = false,
      enableCustomFields = false,
      parallelProcessing = true
    } = options;

    try {
      // Pre-process data for optimizations
      if (enableOptimizations) {
        dataArray = await this.optimizeDataForBulkInsert(module, dataArray);
      }

      // Create batches
      const batches = this.createBatches(dataArray, batchSize);
      console.log(`   Processing ${dataArray.length} ${module} records in ${batches.length} batches...`);

      let results = [];

      if (parallelProcessing && batches.length > 1) {
        // Process batches in parallel (limited concurrency)
        results = await this.processParallelBatches(module, batches, options);
      } else {
        // Process batches sequentially
        results = await this.processSequentialBatches(module, batches, options);
      }

      // Post-process results
      const flatResults = results.flat();
      
      // Create relationships if needed
      if (createRelationships) {
        await this.createBulkRelationships(module, flatResults, options);
      }

      // Handle custom fields
      if (enableCustomFields) {
        await this.processBulkCustomFields(module, flatResults, dataArray);
      }

      const duration = Date.now() - startTime;
      this.updateBulkStats(dataArray.length, duration);

      console.log(`   ✓ ${module}: ${flatResults.length} records in ${duration}ms (${Math.round(flatResults.length / (duration / 1000))} records/sec)`);

      return flatResults;

    } catch (error) {
      console.error(`   ❌ ${module} bulk processing failed:`, error.message);
      throw error;
    }
  }

  /**
   * Process batches in parallel with concurrency control
   */
  async processParallelBatches(module, batches, options) {
    const concurrency = Math.min(this.bulkConfig.parallelBatches, batches.length);
    const results = [];
    
    // Process batches in chunks to control concurrency
    for (let i = 0; i < batches.length; i += concurrency) {
      const batchChunk = batches.slice(i, i + concurrency);
      
      const chunkPromises = batchChunk.map(async (batch, index) => {
        const batchNumber = i + index + 1;
        return this.processSingleBatch(module, batch, batchNumber, options);
      });

      const chunkResults = await Promise.all(chunkPromises);
      results.push(...chunkResults);

      // Progress indicator
      const progress = Math.round(((i + concurrency) / batches.length) * 100);
      process.stdout.write(`\r   Progress: ${Math.min(progress, 100)}%`);
    }
    
    console.log(); // New line after progress
    return results;
  }

  /**
   * Process batches sequentially
   */
  async processSequentialBatches(module, batches, options) {
    const results = [];
    
    for (let i = 0; i < batches.length; i++) {
      const batch = batches[i];
      const batchResult = await this.processSingleBatch(module, batch, i + 1, options);
      results.push(batchResult);

      // Progress indicator
      const progress = Math.round(((i + 1) / batches.length) * 100);
      process.stdout.write(`\r   Progress: ${progress}%`);
    }
    
    console.log(); // New line after progress
    return results;
  }

  /**
   * Process a single batch with retry logic
   */
  async processSingleBatch(module, batch, batchNumber, options) {
    let lastError;
    
    for (let attempt = 1; attempt <= this.bulkConfig.retryAttempts; attempt++) {
      try {
        const batchStartTime = Date.now();
        
        // Prepare batch insert query
        const { query, values } = this.prepareBatchInsertQuery(module, batch);
        
        // Execute batch insert
        const [result] = await this.connection.execute(query, values);
        
        // Track batch metrics
        const batchDuration = Date.now() - batchStartTime;
        this.recordBatchMetrics(module, batch.length, batchDuration);
        
        // Return created records with IDs
        return batch.map((item, index) => ({
          ...item,
          insertId: result.insertId + index // Assumes auto-increment IDs
        }));

      } catch (error) {
        lastError = error;
        console.warn(`\n   ⚠️ Batch ${batchNumber} attempt ${attempt} failed: ${error.message}`);
        
        if (attempt < this.bulkConfig.retryAttempts) {
          await this.delay(this.bulkConfig.retryDelay * attempt);
        }
      }
    }
    
    // All retries failed
    this.bulkStats.failedBatches++;
    throw new Error(`Batch ${batchNumber} failed after ${this.bulkConfig.retryAttempts} attempts: ${lastError.message}`);
  }

  /**
   * Prepare optimized batch insert query
   */
  prepareBatchInsertQuery(module, batch) {
    if (batch.length === 0) {
      throw new Error('Cannot prepare query for empty batch');
    }

    const firstItem = batch[0];
    const columns = Object.keys(firstItem).filter(key => !key.startsWith('_'));
    const placeholders = `(${columns.map(() => '?').join(',')})`;
    const allPlaceholders = batch.map(() => placeholders).join(',');
    
    const query = `INSERT INTO ${module} (${columns.join(',')}) VALUES ${allPlaceholders}`;
    
    const values = batch.flatMap(item => 
      columns.map(column => item[column])
    );

    return { query, values };
  }

  /**
   * Optimize data structure for bulk insertion
   */
  async optimizeDataForBulkInsert(module, dataArray) {
    // Remove null/undefined values
    // Convert dates to proper format
    // Validate required fields
    // Apply any module-specific optimizations
    
    return dataArray.map(item => {
      const optimized = {};
      
      for (const [key, value] of Object.entries(item)) {
        if (value !== null && value !== undefined && !key.startsWith('_')) {
          // Convert dates
          if (key.includes('date') && value instanceof Date) {
            optimized[key] = value.toISOString().slice(0, 19).replace('T', ' ');
          } else if (typeof value === 'string' && value.length > 0) {
            optimized[key] = value;
          } else if (typeof value === 'number') {
            optimized[key] = value;
          } else if (typeof value === 'string') {
            optimized[key] = value;
          }
        }
      }
      
      return optimized;
    });
  }

  /**
   * Create bulk relationships efficiently
   */
  async createBulkRelationships(module, records, options) {
    const { relationshipData } = options;
    if (!relationshipData) return;

    const relationships = [];
    
    for (const record of records) {
      if (record[relationshipData.field]) {
        relationships.push({
          id: this.generateId('rel'),
          [`${module.slice(0, -1)}_id`]: record.id,
          [relationshipData.field]: record[relationshipData.field],
          date_modified: new Date().toISOString().slice(0, 19).replace('T', ' '),
          deleted: 0
        });
      }
    }

    if (relationships.length > 0) {
      const relationshipTable = `${module}_${relationshipData.module}`;
      await this.processBulkData(relationshipTable, relationships, {
        batchSize: 500,
        enableOptimizations: false,
        createRelationships: false
      });
    }
  }

  /**
   * Seed additional realistic data
   */
  async seedAdditionalData(dataset) {
    // Create some activities/tasks
    const activities = [];
    const tasks = [];
    
    for (const deal of dataset.deals.slice(0, 20)) {
      // Add some call activities
      activities.push({
        id: this.generateId('activity'),
        name: `Follow-up call for ${deal.name}`,
        activity_type: 'Call',
        status: 'Completed',
        parent_type: 'Deals',
        parent_id: deal.id,
        date_start: new Date(Date.now() - Math.random() * 7 * 24 * 60 * 60 * 1000).toISOString(),
        assigned_user_id: '1',
        created_by: '1',
        deleted: 0
      });

      // Add some tasks
      tasks.push({
        id: this.generateId('task'),
        name: `Review financials for ${deal.name}`,
        status: 'In Progress',
        priority: 'High',
        parent_type: 'Deals',
        parent_id: deal.id,
        date_due: new Date(Date.now() + Math.random() * 14 * 24 * 60 * 60 * 1000).toISOString(),
        assigned_user_id: '1',
        created_by: '1',
        deleted: 0
      });
    }

    if (activities.length > 0) {
      await this.processBulkData('activities', activities, { batchSize: 100 });
    }
    
    if (tasks.length > 0) {
      await this.processBulkData('tasks', tasks, { batchSize: 100 });
    }
  }

  // Utility methods for data generation
  generateRevenueBySize(sizeIndex) {
    const sizes = [1000000, 5000000, 25000000, 100000000]; // Small, Medium, Large, Enterprise
    return sizes[sizeIndex] + Math.floor(Math.random() * sizes[sizeIndex] * 0.5);
  }

  getRandomIndustry() {
    const industries = [
      'Technology', 'Healthcare', 'Manufacturing', 'Financial Services',
      'Retail', 'Real Estate', 'Energy', 'Transportation', 'Education'
    ];
    return industries[Math.floor(Math.random() * industries.length)];
  }

  getRandomJobTitle() {
    const titles = [
      'CEO', 'CFO', 'CTO', 'VP Sales', 'VP Marketing', 'Director of Operations',
      'Senior Manager', 'Business Development Manager', 'Account Executive'
    ];
    return titles[Math.floor(Math.random() * titles.length)];
  }

  getDealStageByIndex(index) {
    const stages = ['Prospecting', 'Qualification', 'Needs Analysis', 'Value Proposition', 'Negotiation', 'Closed Won'];
    return stages[index];
  }

  generateDealValueByStage(stageIndex) {
    const baseValues = [10000, 25000, 50000, 100000, 250000, 500000];
    const base = baseValues[stageIndex];
    return base + Math.floor(Math.random() * base);
  }

  getProbabilityByStage(stage) {
    const probabilities = {
      'Prospecting': 10,
      'Qualification': 25,
      'Needs Analysis': 40,
      'Value Proposition': 60,
      'Negotiation': 80,
      'Closed Won': 100
    };
    return probabilities[stage] || 10;
  }

  getDocumentCategory(docType) {
    const categories = {
      'NDA': 'legal',
      'LOI': 'agreements',
      'Purchase Agreement': 'agreements',
      'Financial Statement': 'financial',
      'Due Diligence Report': 'analysis'
    };
    return categories[docType] || 'general';
  }

  getChecklistStatusByIndex(index) {
    const statuses = ['Completed', 'In Progress', 'Not Started'];
    return index < 3 ? 'Completed' : index < 5 ? 'In Progress' : 'Not Started';
  }

  getChecklistPriorityByIndex(index) {
    const priorities = ['High', 'Medium', 'Low'];
    return priorities[index % 3];
  }

  getChecklistDueDate(dealCloseDate, index) {
    const dealClose = new Date(dealCloseDate);
    const daysOffset = (index - 4) * 7; // Spread tasks around deal close date
    return new Date(dealClose.getTime() + daysOffset * 24 * 60 * 60 * 1000).toISOString().slice(0, 10);
  }

  groupContactsByAccount(contacts) {
    return contacts.reduce((groups, contact) => {
      const accountId = contact.account_id;
      if (!groups[accountId]) {
        groups[accountId] = [];
      }
      groups[accountId].push(contact);
      return groups;
    }, {});
  }

  createBatches(array, batchSize) {
    const batches = [];
    for (let i = 0; i < array.length; i += batchSize) {
      batches.push(array.slice(i, i + batchSize));
    }
    return batches;
  }

  updateBulkStats(recordCount, duration) {
    this.bulkStats.totalRecordsCreated += recordCount;
    this.bulkStats.batchesProcessed++;
    
    // Update average batch time
    const totalBatchTime = this.bulkStats.averageBatchTime * (this.bulkStats.batchesProcessed - 1) + duration;
    this.bulkStats.averageBatchTime = totalBatchTime / this.bulkStats.batchesProcessed;
  }

  recordBatchMetrics(module, recordCount, duration) {
    this.recordMetric(`batch_${module}`, duration);
    this.recordMetric(`batch_${module}_rate`, recordCount / (duration / 1000));
  }

  delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  getBulkStats() {
    return {
      ...this.bulkStats,
      recordsPerSecond: this.bulkStats.totalBulkTime > 0 
        ? Math.round(this.bulkStats.totalRecordsCreated / (this.bulkStats.totalBulkTime / 1000))
        : 0,
      successRate: this.bulkStats.batchesProcessed > 0
        ? Math.round(((this.bulkStats.batchesProcessed - this.bulkStats.failedBatches) / this.bulkStats.batchesProcessed) * 100)
        : 0
    };
  }
}

module.exports = BulkDataUtilities;