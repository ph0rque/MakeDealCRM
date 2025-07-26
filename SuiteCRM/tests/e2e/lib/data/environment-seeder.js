/**
 * Test Environment Seeder and Cleanup Utilities
 * Manages test environment setup, seeding, and comprehensive cleanup
 */

const EnhancedTestDataManager = require('./enhanced-test-data-manager');
const BulkDataUtilities = require('./bulk-data-utilities');
const DataRelationshipManager = require('./relationship-manager');
const fs = require('fs').promises;
const path = require('path');

class EnvironmentSeeder {
  constructor(config = {}) {
    this.config = {
      seedDataPath: config.seedDataPath || path.join(__dirname, '../../test-data/seeds'),
      backupPath: config.backupPath || path.join(__dirname, '../../test-data/backups'),
      environmentName: config.environmentName || process.env.NODE_ENV || 'test',
      enableBackups: config.enableBackups !== false,
      enableSeeding: config.enableSeeding !== false,
      enableCleanupVerification: config.enableVerificationCleanup !== false,
      maxCleanupAttempts: config.maxCleanupAttempts || 3,
      cleanupTimeout: config.cleanupTimeout || 60000,
      ...config
    };

    this.dataManager = null;
    this.bulkUtilities = null;
    this.relationshipManager = null;
    this.seedingStats = {
      startTime: null,
      endTime: null,
      recordsCreated: {},
      errors: [],
      warnings: []
    };
  }

  /**
   * Initialize the environment seeder
   */
  async initialize() {
    console.log(`🌱 Initializing Environment Seeder for: ${this.config.environmentName}`);
    
    this.dataManager = new EnhancedTestDataManager(this.config);
    await this.dataManager.initialize();
    
    this.bulkUtilities = new BulkDataUtilities(this.config);
    await this.bulkUtilities.initialize();
    
    this.relationshipManager = new DataRelationshipManager(
      this.dataManager.connection, 
      this.config
    );

    // Ensure directories exist
    await this.ensureDirectories();
    
    console.log('✅ Environment Seeder initialized successfully');
  }

  /**
   * Seed the test environment with comprehensive data
   */
  async seedEnvironment(seedConfig = {}) {
    this.seedingStats.startTime = Date.now();
    
    const {
      profile = 'default',
      customData = {},
      skipIfExists = true,
      forceReseed = false
    } = seedConfig;

    try {
      console.log(`🌱 Starting environment seeding with profile: ${profile}`);

      // Check if environment is already seeded
      if (skipIfExists && !forceReseed && await this.isEnvironmentSeeded()) {
        console.log('Environment already seeded, skipping...');
        return await this.getSeedingReport();
      }

      // Create backup if enabled
      if (this.config.enableBackups) {
        await this.createEnvironmentBackup();
      }

      // Load seed profile
      const seedData = await this.loadSeedProfile(profile);
      
      // Merge with custom data
      const finalSeedData = this.mergeSeedData(seedData, customData);

      // Execute seeding phases
      await this.executeSeedingPhases(finalSeedData);

      // Verify seeded data
      await this.verifySeedData();

      // Create seeding manifest
      await this.createSeedingManifest(finalSeedData);

      this.seedingStats.endTime = Date.now();
      const report = await this.getSeedingReport();
      
      console.log('✅ Environment seeding completed successfully');
      console.log(`📊 Seeding Summary: ${report.totalRecords} records in ${report.duration}ms`);
      
      return report;

    } catch (error) {
      this.seedingStats.errors.push({
        phase: 'seeding',
        error: error.message,
        timestamp: new Date().toISOString()
      });
      
      console.error('❌ Environment seeding failed:', error);
      
      // Attempt cleanup on failure
      await this.cleanupFailedSeeding();
      throw error;
    }
  }

  /**
   * Load seed profile configuration
   */
  async loadSeedProfile(profileName) {
    const profiles = {
      'minimal': {
        description: 'Minimal data set for basic testing',
        data: {
          users: 2,
          accounts: 5,
          contacts: 15,
          deals: 10,
          documents: 20,
          checklists: 50
        },
        relationships: {
          contactsPerAccount: 3,
          dealsPerAccount: 2,
          documentsPerDeal: 2,
          checklistsPerDeal: 5
        }
      },

      'default': {
        description: 'Standard data set for regular testing',
        data: {
          users: 5,
          accounts: 25,
          contacts: 100,
          deals: 75,
          documents: 150,
          checklists: 375
        },
        relationships: {
          contactsPerAccount: 4,
          dealsPerAccount: 3,
          documentsPerDeal: 2,
          checklistsPerDeal: 5
        }
      },

      'performance': {
        description: 'Large data set for performance testing',
        data: {
          users: 10,
          accounts: 100,
          contacts: 500,
          deals: 400,
          documents: 800,
          checklists: 2000
        },
        relationships: {
          contactsPerAccount: 5,
          dealsPerAccount: 4,
          documentsPerDeal: 2,
          checklistsPerDeal: 5
        }
      },

      'stress': {
        description: 'Very large data set for stress testing',
        data: {
          users: 20,
          accounts: 500,
          contacts: 2500,
          deals: 2000,
          documents: 4000,
          checklists: 10000
        },
        relationships: {
          contactsPerAccount: 5,
          dealsPerAccount: 4,
          documentsPerDeal: 2,
          checklistsPerDeal: 5
        }
      },

      'demo': {
        description: 'Realistic demo data with proper relationships',
        data: {
          users: 8,
          accounts: 50,
          contacts: 200,
          deals: 150,
          documents: 300,
          checklists: 750
        },
        relationships: {
          contactsPerAccount: 4,
          dealsPerAccount: 3,
          documentsPerDeal: 2,
          checklistsPerDeal: 5
        },
        scenarios: [
          'deal-pipeline',
          'complete-deal-lifecycle',
          'at-risk-deals',
          'duplicate-detection-test'
        ]
      },

      'custom': {
        description: 'Empty profile for custom configuration',
        data: {},
        relationships: {},
        scenarios: []
      }
    };

    const profile = profiles[profileName];
    if (!profile) {
      throw new Error(`Unknown seed profile: ${profileName}`);
    }

    // Try to load custom profile from file if it exists
    try {
      const customProfilePath = path.join(this.config.seedDataPath, `${profileName}.json`);
      const customProfile = JSON.parse(await fs.readFile(customProfilePath, 'utf-8'));
      return { ...profile, ...customProfile };
    } catch (error) {
      // Use default profile if custom file doesn't exist
      return profile;
    }
  }

  /**
   * Execute seeding in phases
   */
  async executeSeedingPhases(seedData) {
    const phases = [
      { name: 'users', dependencies: [] },
      { name: 'accounts', dependencies: ['users'] },
      { name: 'contacts', dependencies: ['users', 'accounts'] },
      { name: 'deals', dependencies: ['users', 'accounts', 'contacts'] },
      { name: 'documents', dependencies: ['users', 'deals'] },
      { name: 'checklists', dependencies: ['users', 'deals'] },
      { name: 'scenarios', dependencies: ['users', 'accounts', 'contacts', 'deals'] }
    ];

    const createdRecords = {};
    
    for (const phase of phases) {
      if (seedData.data[phase.name] || (phase.name === 'scenarios' && seedData.scenarios)) {
        console.log(`📦 Phase: ${phase.name}`);
        
        try {
          const phaseStartTime = Date.now();
          const records = await this.executePhase(phase, seedData, createdRecords);
          const phaseDuration = Date.now() - phaseStartTime;
          
          createdRecords[phase.name] = records;
          this.seedingStats.recordsCreated[phase.name] = Array.isArray(records) ? records.length : Object.keys(records).length;
          
          console.log(`   ✓ ${phase.name}: ${this.seedingStats.recordsCreated[phase.name]} records (${phaseDuration}ms)`);
          
        } catch (error) {
          this.seedingStats.errors.push({
            phase: phase.name,
            error: error.message,
            timestamp: new Date().toISOString()
          });
          throw new Error(`Phase ${phase.name} failed: ${error.message}`);
        }
      }
    }

    return createdRecords;
  }

  /**
   * Execute individual seeding phase
   */
  async executePhase(phase, seedData, createdRecords) {
    switch (phase.name) {
      case 'users':
        return await this.seedUsers(seedData.data.users);
        
      case 'accounts':
        return await this.seedAccounts(seedData.data.accounts);
        
      case 'contacts':
        return await this.seedContacts(
          seedData.data.contacts, 
          createdRecords.accounts,
          seedData.relationships.contactsPerAccount
        );
        
      case 'deals':
        return await this.seedDeals(
          seedData.data.deals,
          createdRecords.accounts,
          createdRecords.contacts,
          seedData.relationships
        );
        
      case 'documents':
        return await this.seedDocuments(
          seedData.data.documents,
          createdRecords.deals,
          seedData.relationships.documentsPerDeal
        );
        
      case 'checklists':
        return await this.seedChecklists(
          seedData.data.checklists,
          createdRecords.deals,
          seedData.relationships.checklistsPerDeal
        );
        
      case 'scenarios':
        return await this.seedScenarios(seedData.scenarios || []);
        
      default:
        throw new Error(`Unknown phase: ${phase.name}`);
    }
  }

  /**
   * Seed users
   */
  async seedUsers(count) {
    const users = [];
    for (let i = 0; i < count; i++) {
      users.push({
        id: this.dataManager.generateId('user'),
        user_name: `test_user_${i + 1}`,
        first_name: `Test`,
        last_name: `User ${i + 1}`,
        email1: `testuser${i + 1}@example.com`,
        status: 'Active',
        is_admin: i === 0 ? 1 : 0,
        date_entered: new Date().toISOString().slice(0, 19).replace('T', ' '),
        date_modified: new Date().toISOString().slice(0, 19).replace('T', ' '),
        deleted: 0
      });
    }

    return await this.bulkUtilities.processBulkData('users', users, { batchSize: 50 });
  }

  /**
   * Seed accounts with realistic distribution
   */
  async seedAccounts(count) {
    const industries = ['Technology', 'Healthcare', 'Manufacturing', 'Financial Services', 'Retail'];
    const sizes = ['Small', 'Medium', 'Large', 'Enterprise'];
    
    const accounts = [];
    for (let i = 0; i < count; i++) {
      const size = sizes[i % sizes.length];
      const industry = industries[i % industries.length];
      
      accounts.push(this.dataManager.generateAccountData({
        name: `${this.config.testPrefix}Seed Account ${i + 1}`,
        industry,
        account_type: size === 'Enterprise' ? 'Partner' : 'Customer',
        annual_revenue: this.getRevenueBySize(size),
        employees: this.getEmployeesBySize(size),
        description: `Seeded ${size.toLowerCase()} ${industry.toLowerCase()} account`
      }));
    }

    return await this.bulkUtilities.processBulkData('accounts', accounts, { batchSize: 100 });
  }

  /**
   * Seed contacts linked to accounts
   */
  async seedContacts(totalCount, accounts, contactsPerAccount) {
    const contacts = [];
    const titles = ['CEO', 'CFO', 'VP Sales', 'Director', 'Manager', 'Analyst'];
    
    let contactIndex = 0;
    for (const account of accounts) {
      const numContacts = Math.min(contactsPerAccount, totalCount - contactIndex);
      
      for (let i = 0; i < numContacts; i++) {
        const title = titles[i % titles.length];
        contacts.push(this.dataManager.generateContactData({
          account_id: account.id,
          first_name: `Contact${contactIndex + 1}`,
          last_name: account.name.replace(/[^a-zA-Z0-9]/g, '').slice(-10),
          title,
          email1: `contact${contactIndex + 1}@${account.name.toLowerCase().replace(/[^a-z0-9]/g, '')}.com`,
          description: `Seeded contact for ${account.name}`
        }));
        
        contactIndex++;
        if (contactIndex >= totalCount) break;
      }
      
      if (contactIndex >= totalCount) break;
    }

    return await this.bulkUtilities.processBulkData('contacts', contacts, { 
      batchSize: 200,
      createRelationships: true 
    });
  }

  /**
   * Seed deals with realistic pipeline distribution
   */
  async seedDeals(totalCount, accounts, contacts, relationships) {
    const stages = ['Prospecting', 'Qualification', 'Needs Analysis', 'Value Proposition', 'Negotiation', 'Closed Won'];
    const types = ['New Business', 'Existing Business', 'Renewal'];
    
    const deals = [];
    const contactsByAccount = this.groupContactsByAccount(contacts);
    
    let dealIndex = 0;
    for (const account of accounts) {
      const numDeals = Math.min(relationships.dealsPerAccount, totalCount - dealIndex);
      const accountContacts = contactsByAccount[account.id] || [];
      
      for (let i = 0; i < numDeals; i++) {
        const stage = stages[dealIndex % stages.length];
        const type = types[dealIndex % types.length];
        const amount = this.generateDealAmountByStage(stage);
        
        // Select random contacts for this deal
        const dealContacts = accountContacts
          .sort(() => 0.5 - Math.random())
          .slice(0, Math.min(2, accountContacts.length));
        
        deals.push(this.dataManager.generateDealData({
          name: `${account.name} - Deal ${i + 1}`,
          account_id: account.id,
          contact_ids: dealContacts.map(c => c.id),
          amount,
          sales_stage: stage,
          deal_type: type,
          probability: this.getProbabilityByStage(stage),
          description: `Seeded ${stage.toLowerCase()} deal for ${account.name}`,
          date_closed: this.generateCloseDateByStage(stage)
        }));
        
        dealIndex++;
        if (dealIndex >= totalCount) break;
      }
      
      if (dealIndex >= totalCount) break;
    }

    return await this.bulkUtilities.processBulkData('deals', deals, {
      batchSize: 150,
      createRelationships: true,
      enableCustomFields: true
    });
  }

  /**
   * Seed documents for deals
   */
  async seedDocuments(totalCount, deals, documentsPerDeal) {
    const documentTypes = ['NDA', 'LOI', 'Purchase Agreement', 'Financial Statement', 'Due Diligence Report'];
    
    const documents = [];
    let docIndex = 0;
    
    for (const deal of deals) {
      const numDocs = Math.min(documentsPerDeal, totalCount - docIndex);
      
      for (let i = 0; i < numDocs; i++) {
        const docType = documentTypes[i % documentTypes.length];
        
        documents.push(this.dataManager.generateDocumentData({
          document_name: `${deal.name} - ${docType}`,
          deal_id: deal.id,
          filename: `${docType.toLowerCase().replace(/\s+/g, '_')}_${deal.id}.pdf`,
          category_id: this.getDocumentCategory(docType),
          description: `Seeded ${docType.toLowerCase()} for ${deal.name}`
        }));
        
        docIndex++;
        if (docIndex >= totalCount) break;
      }
      
      if (docIndex >= totalCount) break;
    }

    return await this.bulkUtilities.processBulkData('documents', documents, { 
      batchSize: 300,
      createRelationships: true 
    });
  }

  /**
   * Seed checklists for deals
   */
  async seedChecklists(totalCount, deals, checklistsPerDeal) {
    const checklistTemplates = [
      'Initial Contact Made', 'NDA Signed', 'Financial Review', 'Management Presentation',
      'Site Visit Completed', 'LOI Submitted', 'Due Diligence Started', 'Legal Review'
    ];
    
    const checklists = [];
    let checklistIndex = 0;
    
    for (const deal of deals) {
      const numChecklists = Math.min(checklistsPerDeal, totalCount - checklistIndex);
      
      for (let i = 0; i < numChecklists && i < checklistTemplates.length; i++) {
        const template = checklistTemplates[i];
        
        checklists.push(this.dataManager.generateChecklistData({
          name: `${deal.name} - ${template}`,
          deal_id: deal.id,
          description: `Seeded checklist: ${template} for ${deal.name}`,
          status: this.getChecklistStatusByProgress(i, numChecklists),
          priority: i < 3 ? 'High' : i < 6 ? 'Medium' : 'Low',
          due_date: this.generateChecklistDueDate(deal.date_closed, i),
          order_index: i + 1
        }));
        
        checklistIndex++;
        if (checklistIndex >= totalCount) break;
      }
      
      if (checklistIndex >= totalCount) break;
    }

    return await this.bulkUtilities.processBulkData('checklists', checklists, {
      batchSize: 400,
      createRelationships: true
    });
  }

  /**
   * Seed test scenarios
   */
  async seedScenarios(scenarios) {
    const scenarioResults = {};
    
    for (const scenarioName of scenarios) {
      console.log(`   Creating scenario: ${scenarioName}`);
      try {
        const result = await this.dataManager.createTestScenario(scenarioName);
        scenarioResults[scenarioName] = result;
      } catch (error) {
        this.seedingStats.warnings.push({
          scenario: scenarioName,
          warning: `Failed to create scenario: ${error.message}`,
          timestamp: new Date().toISOString()
        });
      }
    }
    
    return scenarioResults;
  }

  /**
   * Comprehensive environment cleanup
   */
  async cleanupEnvironment(options = {}) {
    const {
      verifyCleanup = this.config.enableCleanupVerification,
      createBackupBeforeCleanup = false,
      maxAttempts = this.config.maxCleanupAttempts,
      timeout = this.config.cleanupTimeout
    } = options;

    console.log('🧹 Starting comprehensive environment cleanup...');
    
    const cleanupStartTime = Date.now();
    let attempt = 1;
    let lastError = null;

    // Create backup if requested
    if (createBackupBeforeCleanup) {
      await this.createEnvironmentBackup('pre-cleanup');
    }

    while (attempt <= maxAttempts) {
      try {
        console.log(`Cleanup attempt ${attempt}/${maxAttempts}`);
        
        // Set cleanup timeout
        const cleanupPromise = this.performCleanup();
        const timeoutPromise = new Promise((_, reject) => 
          setTimeout(() => reject(new Error('Cleanup timeout')), timeout)
        );
        
        await Promise.race([cleanupPromise, timeoutPromise]);
        
        // Verify cleanup if enabled
        if (verifyCleanup) {
          await this.verifyCleanupComplete();
        }

        const cleanupDuration = Date.now() - cleanupStartTime;
        console.log(`✅ Environment cleanup completed in ${cleanupDuration}ms`);
        
        return {
          success: true,
          duration: cleanupDuration,
          attempts: attempt,
          verified: verifyCleanup
        };

      } catch (error) {
        lastError = error;
        console.warn(`❌ Cleanup attempt ${attempt} failed: ${error.message}`);
        
        if (attempt < maxAttempts) {
          console.log(`Retrying in 5 seconds...`);
          await new Promise(resolve => setTimeout(resolve, 5000));
        }
        
        attempt++;
      }
    }

    // All attempts failed
    console.error(`❌ Environment cleanup failed after ${maxAttempts} attempts`);
    throw new Error(`Cleanup failed: ${lastError?.message}`);
  }

  /**
   * Perform the actual cleanup operations
   */
  async performCleanup() {
    // Phase 1: Clean test data using data managers
    console.log('Phase 1: Cleaning test data...');
    if (this.dataManager) {
      await this.dataManager.cleanup({ forceCleanup: true });
    }
    
    if (this.bulkUtilities) {
      await this.bulkUtilities.cleanup({ forceCleanup: true });
    }

    if (this.relationshipManager) {
      await this.relationshipManager.cleanupAllRelationships();
    }

    // Phase 2: Clean by prefix (backup method)
    console.log('Phase 2: Cleaning by prefix...');
    await this.cleanupByPrefix();

    // Phase 3: Clean orphaned relationships
    console.log('Phase 3: Cleaning orphaned relationships...');
    await this.cleanupOrphanedRelationships();

    // Phase 4: Clean custom fields
    console.log('Phase 4: Cleaning custom fields...');
    await this.cleanupCustomFields();

    // Phase 5: Clean temporary files
    console.log('Phase 5: Cleaning temporary files...');
    await this.cleanupTemporaryFiles();

    // Phase 6: Reset auto-increment counters (optional)
    if (this.config.resetAutoIncrement) {
      console.log('Phase 6: Resetting auto-increment counters...');
      await this.resetAutoIncrementCounters();
    }
  }

  /**
   * Cleanup by prefix (comprehensive backup method)
   */
  async cleanupByPrefix() {
    const tables = [
      'deals', 'accounts', 'contacts', 'documents', 'checklists',
      'activities', 'tasks', 'users', 'notes', 'emails'
    ];

    for (const table of tables) {
      try {
        // Soft delete records with test prefix
        const [result] = await this.dataManager.connection.execute(
          `UPDATE ${table} SET deleted = 1, date_modified = NOW() WHERE name LIKE ? OR first_name LIKE ? OR document_name LIKE ?`,
          [`${this.config.testPrefix || 'E2E_TEST_'}%`, `${this.config.testPrefix || 'E2E_TEST_'}%`, `${this.config.testPrefix || 'E2E_TEST_'}%`]
        );
        
        if (result.affectedRows > 0) {
          console.log(`   Cleaned ${result.affectedRows} records from ${table}`);
        }
      } catch (error) {
        console.warn(`   Failed to cleanup ${table}: ${error.message}`);
      }
    }
  }

  /**
   * Clean orphaned relationships
   */
  async cleanupOrphanedRelationships() {
    const relationshipTables = [
      'deals_accounts', 'deals_contacts', 'accounts_contacts',
      'documents_deals', 'documents_accounts', 'documents_contacts'
    ];

    for (const table of relationshipTables) {
      try {
        // This would need specific logic for each relationship table
        console.log(`   Checking ${table} for orphaned relationships...`);
        // Implementation would depend on specific table structure
      } catch (error) {
        console.warn(`   Failed to cleanup ${table}: ${error.message}`);
      }
    }
  }

  /**
   * Clean custom fields tables
   */
  async cleanupCustomFields() {
    const customFieldTables = [
      'deals_cstm', 'accounts_cstm', 'contacts_cstm', 
      'documents_cstm', 'checklists_cstm'
    ];

    for (const table of customFieldTables) {
      try {
        await this.dataManager.connection.execute(
          `DELETE FROM ${table} WHERE id_c LIKE ?`,
          [`${this.config.testPrefix || 'E2E_TEST_'}%`]
        );
        console.log(`   Cleaned custom fields from ${table}`);
      } catch (error) {
        // Table might not exist, which is fine
        if (!error.message.includes("doesn't exist")) {
          console.warn(`   Failed to cleanup ${table}: ${error.message}`);
        }
      }
    }
  }

  /**
   * Clean temporary files
   */
  async cleanupTemporaryFiles() {
    const tempDirs = [
      path.join(__dirname, '../../test-results'),
      path.join(__dirname, '../../test-data/temp'),
      '/tmp/suitecrm-test-uploads'
    ];

    for (const dir of tempDirs) {
      try {
        const files = await fs.readdir(dir);
        const testFiles = files.filter(file => file.includes('test') || file.includes(this.config.testPrefix));
        
        for (const file of testFiles) {
          await fs.unlink(path.join(dir, file));
        }
        
        if (testFiles.length > 0) {
          console.log(`   Cleaned ${testFiles.length} temporary files from ${dir}`);
        }
      } catch (error) {
        // Directory might not exist, which is fine
      }
    }
  }

  /**
   * Verify cleanup completion
   */
  async verifyCleanupComplete() {
    console.log('Verifying cleanup completion...');
    
    const tables = ['deals', 'accounts', 'contacts', 'documents', 'checklists'];
    let remainingRecords = 0;

    for (const table of tables) {
      const [rows] = await this.dataManager.connection.execute(
        `SELECT COUNT(*) as count FROM ${table} WHERE deleted = 0 AND (name LIKE ? OR first_name LIKE ? OR document_name LIKE ?)`,
        [`${this.config.testPrefix}%`, `${this.config.testPrefix}%`, `${this.config.testPrefix}%`]
      );
      
      const count = rows[0].count;
      if (count > 0) {
        remainingRecords += count;
        console.warn(`   ${table}: ${count} test records still exist`);
      }
    }

    if (remainingRecords > 0) {
      throw new Error(`Cleanup verification failed: ${remainingRecords} test records still exist`);
    }

    console.log('✅ Cleanup verification passed');
  }

  // Utility methods for data generation and management

  mergeSeedData(baseData, customData) {
    return {
      ...baseData,
      data: { ...baseData.data, ...customData.data },
      relationships: { ...baseData.relationships, ...customData.relationships },
      scenarios: [...(baseData.scenarios || []), ...(customData.scenarios || [])]
    };
  }

  getRevenueBySize(size) {
    const revenues = {
      'Small': 1000000,
      'Medium': 10000000,
      'Large': 100000000,
      'Enterprise': 1000000000
    };
    return revenues[size] || 1000000;
  }

  getEmployeesBySize(size) {
    const employees = {
      'Small': '1-50',
      'Medium': '51-200',
      'Large': '201-1000',
      'Enterprise': '1000+'
    };
    return employees[size] || '1-50';
  }

  generateDealAmountByStage(stage) {
    const baseAmounts = {
      'Prospecting': 50000,
      'Qualification': 75000,
      'Needs Analysis': 100000,
      'Value Proposition': 150000,
      'Negotiation': 200000,
      'Closed Won': 250000
    };
    
    const base = baseAmounts[stage] || 50000;
    return base + Math.floor(Math.random() * base * 0.5);
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

  generateCloseDateByStage(stage) {
    const daysFromNow = {
      'Prospecting': 90,
      'Qualification': 75,
      'Needs Analysis': 60,
      'Value Proposition': 45,
      'Negotiation': 30,
      'Closed Won': -30 // Closed deals are in the past
    };
    
    const days = daysFromNow[stage] || 60;
    return new Date(Date.now() + days * 24 * 60 * 60 * 1000).toISOString().slice(0, 10);
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

  getChecklistStatusByProgress(index, total) {
    const progress = index / total;
    if (progress < 0.3) return 'Completed';
    if (progress < 0.7) return 'In Progress';
    return 'Not Started';
  }

  generateChecklistDueDate(dealCloseDate, index) {
    const dealClose = new Date(dealCloseDate);
    const daysOffset = (index - 4) * 7;
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

  async isEnvironmentSeeded() {
    try {
      const manifestPath = path.join(this.config.seedDataPath, 'seeding-manifest.json');
      await fs.access(manifestPath);
      return true;
    } catch {
      return false;
    }
  }

  async createSeedingManifest(seedData) {
    const manifest = {
      timestamp: new Date().toISOString(),
      environment: this.config.environmentName,
      profile: seedData.description || 'Custom',
      recordCounts: this.seedingStats.recordsCreated,
      duration: this.seedingStats.endTime - this.seedingStats.startTime,
      errors: this.seedingStats.errors,
      warnings: this.seedingStats.warnings
    };

    const manifestPath = path.join(this.config.seedDataPath, 'seeding-manifest.json');
    await fs.writeFile(manifestPath, JSON.stringify(manifest, null, 2));
  }

  async getSeedingReport() {
    const totalRecords = Object.values(this.seedingStats.recordsCreated)
      .reduce((sum, count) => sum + count, 0);
    
    return {
      environment: this.config.environmentName,
      totalRecords,
      recordsCreated: this.seedingStats.recordsCreated,
      duration: this.seedingStats.endTime - this.seedingStats.startTime,
      errors: this.seedingStats.errors,
      warnings: this.seedingStats.warnings,
      success: this.seedingStats.errors.length === 0
    };
  }

  async ensureDirectories() {
    const dirs = [this.config.seedDataPath, this.config.backupPath];
    
    for (const dir of dirs) {
      try {
        await fs.mkdir(dir, { recursive: true });
      } catch (error) {
        console.warn(`Failed to create directory ${dir}: ${error.message}`);
      }
    }
  }

  async createEnvironmentBackup(suffix = '') {
    // Implementation would depend on backup strategy
    console.log(`Creating environment backup${suffix ? ` (${suffix})` : ''}...`);
    // This could involve database dumps, file backups, etc.
  }

  async verifySeedData() {
    console.log('Verifying seeded data integrity...');
    
    if (this.relationshipManager) {
      const integrityReport = await this.relationshipManager.getRelationshipIntegrityReport();
      
      if (integrityReport.brokenForeignKeys.length > 0) {
        this.seedingStats.warnings.push({
          type: 'integrity',
          warning: `${integrityReport.brokenForeignKeys.length} broken foreign keys found`,
          timestamp: new Date().toISOString()
        });
      }
    }
  }

  async cleanupFailedSeeding() {
    console.log('Cleaning up after failed seeding...');
    try {
      await this.performCleanup();
    } catch (error) {
      console.error('Failed to cleanup after seeding failure:', error.message);
    }
  }

  async disconnect() {
    if (this.dataManager) {
      await this.dataManager.disconnect();
    }
    if (this.bulkUtilities) {
      await this.bulkUtilities.disconnect();
    }
  }
}

module.exports = EnvironmentSeeder;