/**
 * Test Data Manager
 * Manages test data creation, retrieval, and cleanup
 */

const fs = require('fs').promises;
const path = require('path');
const crypto = require('crypto');

class TestDataManager {
  constructor() {
    this.createdRecords = [];
    this.testRunId = this.generateTestRunId();
    this.dataDir = path.join(__dirname, '../../test-data');
  }

  /**
   * Generate unique test run ID
   * @returns {string}
   */
  generateTestRunId() {
    return `test_${Date.now()}_${crypto.randomBytes(4).toString('hex')}`;
  }

  /**
   * Generate unique identifier
   * @param {string} prefix - Prefix for the identifier
   * @returns {string}
   */
  generateId(prefix = 'test') {
    return `${prefix}_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  }

  /**
   * Generate test email
   * @param {string} prefix - Email prefix
   * @returns {string}
   */
  generateEmail(prefix = 'test') {
    return `${prefix}_${this.generateId()}@example.com`;
  }

  /**
   * Generate test phone number
   * @returns {string}
   */
  generatePhone() {
    const areaCode = Math.floor(Math.random() * 900) + 100;
    const prefix = Math.floor(Math.random() * 900) + 100;
    const lineNumber = Math.floor(Math.random() * 9000) + 1000;
    return `(${areaCode}) ${prefix}-${lineNumber}`;
  }

  /**
   * Generate test company name
   * @returns {string}
   */
  generateCompanyName() {
    const prefixes = ['Test', 'Demo', 'Sample', 'Example'];
    const types = ['Corp', 'LLC', 'Inc', 'Company', 'Group', 'Solutions'];
    const prefix = prefixes[Math.floor(Math.random() * prefixes.length)];
    const type = types[Math.floor(Math.random() * types.length)];
    return `${prefix} ${this.generateId('Co')} ${type}`;
  }

  /**
   * Generate test person name
   * @returns {Object}
   */
  generatePersonName() {
    const firstNames = ['John', 'Jane', 'Bob', 'Alice', 'Charlie', 'Emma', 'David', 'Sophia'];
    const lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis'];
    
    const firstName = firstNames[Math.floor(Math.random() * firstNames.length)];
    const lastName = lastNames[Math.floor(Math.random() * lastNames.length)];
    
    return {
      firstName,
      lastName,
      fullName: `${firstName} ${lastName}`
    };
  }

  /**
   * Generate test address
   * @returns {Object}
   */
  generateAddress() {
    const streets = ['Main St', 'Oak Ave', 'Elm St', 'Park Blvd', 'Market St'];
    const cities = ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix'];
    const states = ['NY', 'CA', 'IL', 'TX', 'AZ'];
    
    const streetNumber = Math.floor(Math.random() * 9999) + 1;
    const streetIndex = Math.floor(Math.random() * streets.length);
    const cityIndex = Math.floor(Math.random() * cities.length);
    
    return {
      street: `${streetNumber} ${streets[streetIndex]}`,
      city: cities[cityIndex],
      state: states[cityIndex],
      postalCode: Math.floor(Math.random() * 90000) + 10000,
      country: 'USA'
    };
  }

  /**
   * Generate test deal data
   * @param {Object} overrides - Override default values
   * @returns {Object}
   */
  generateDealData(overrides = {}) {
    const dealValue = Math.floor(Math.random() * 10000000) + 100000;
    const ttmRevenue = dealValue * 2;
    const ttmEbitda = ttmRevenue * 0.2;
    const targetMultiple = (Math.random() * 3 + 3).toFixed(1);
    
    return {
      name: this.generateCompanyName(),
      status: 'initial_contact',
      source: 'broker',
      deal_value: dealValue.toString(),
      ttm_revenue: ttmRevenue.toString(),
      ttm_ebitda: ttmEbitda.toString(),
      target_multiple: targetMultiple,
      asking_price: (ttmEbitda * targetMultiple).toString(),
      description: `Test deal created at ${new Date().toISOString()}`,
      ...overrides
    };
  }

  /**
   * Generate test contact data
   * @param {Object} overrides - Override default values
   * @returns {Object}
   */
  generateContactData(overrides = {}) {
    const name = this.generatePersonName();
    const address = this.generateAddress();
    
    return {
      first_name: name.firstName,
      last_name: name.lastName,
      email: this.generateEmail(name.firstName.toLowerCase()),
      phone_work: this.generatePhone(),
      title: 'Test Contact',
      department: 'Sales',
      primary_address_street: address.street,
      primary_address_city: address.city,
      primary_address_state: address.state,
      primary_address_postalcode: address.postalCode,
      primary_address_country: address.country,
      description: `Test contact created at ${new Date().toISOString()}`,
      ...overrides
    };
  }

  /**
   * Generate test account data
   * @param {Object} overrides - Override default values
   * @returns {Object}
   */
  generateAccountData(overrides = {}) {
    const address = this.generateAddress();
    
    return {
      name: this.generateCompanyName(),
      account_type: 'Customer',
      industry: 'Technology',
      annual_revenue: Math.floor(Math.random() * 100000000).toString(),
      phone_office: this.generatePhone(),
      billing_address_street: address.street,
      billing_address_city: address.city,
      billing_address_state: address.state,
      billing_address_postalcode: address.postalCode,
      billing_address_country: address.country,
      website: `https://www.${this.generateId('company')}.com`,
      description: `Test account created at ${new Date().toISOString()}`,
      ...overrides
    };
  }

  /**
   * Track created record for cleanup
   * @param {string} module - Module name
   * @param {string} id - Record ID
   */
  trackRecord(module, id) {
    this.createdRecords.push({
      module,
      id,
      timestamp: new Date().toISOString()
    });
  }

  /**
   * Load test data from JSON file
   * @param {string} filename - JSON file name
   * @returns {Promise<Object>}
   */
  async loadTestData(filename) {
    try {
      const filePath = path.join(this.dataDir, filename);
      const data = await fs.readFile(filePath, 'utf-8');
      return JSON.parse(data);
    } catch (error) {
      console.error(`Failed to load test data from ${filename}:`, error);
      return null;
    }
  }

  /**
   * Save test data to JSON file
   * @param {string} filename - JSON file name
   * @param {Object} data - Data to save
   * @returns {Promise<void>}
   */
  async saveTestData(filename, data) {
    try {
      await fs.mkdir(this.dataDir, { recursive: true });
      const filePath = path.join(this.dataDir, filename);
      await fs.writeFile(filePath, JSON.stringify(data, null, 2));
    } catch (error) {
      console.error(`Failed to save test data to ${filename}:`, error);
    }
  }

  /**
   * Generate bulk test data
   * @param {string} type - Data type (deal, contact, account)
   * @param {number} count - Number of records to generate
   * @returns {Array}
   */
  generateBulkData(type, count) {
    const data = [];
    const generators = {
      deal: () => this.generateDealData(),
      contact: () => this.generateContactData(),
      account: () => this.generateAccountData()
    };

    const generator = generators[type];
    if (!generator) {
      throw new Error(`Unknown data type: ${type}`);
    }

    for (let i = 0; i < count; i++) {
      data.push(generator());
    }

    return data;
  }

  /**
   * Cleanup created test data
   * @returns {Promise<void>}
   */
  async cleanup() {
    console.log(`Cleaning up ${this.createdRecords.length} test records...`);
    
    // In a real implementation, this would make API calls or database
    // queries to delete the created records
    for (const record of this.createdRecords) {
      try {
        // await deleteRecord(record.module, record.id);
        console.log(`Would delete ${record.module} record: ${record.id}`);
      } catch (error) {
        console.error(`Failed to cleanup ${record.module} record ${record.id}:`, error);
      }
    }

    this.createdRecords = [];
  }

  /**
   * Get test data statistics
   * @returns {Object}
   */
  getStats() {
    const stats = {
      totalRecords: this.createdRecords.length,
      byModule: {}
    };

    for (const record of this.createdRecords) {
      stats.byModule[record.module] = (stats.byModule[record.module] || 0) + 1;
    }

    return stats;
  }

  /**
   * Generate test data for specific scenario
   * @param {string} scenario - Scenario name
   * @returns {Object}
   */
  generateScenarioData(scenario) {
    const scenarios = {
      'deal-with-contacts': () => ({
        deal: this.generateDealData(),
        contacts: this.generateBulkData('contact', 3),
        account: this.generateAccountData()
      }),
      
      'deal-pipeline': () => ({
        deals: [
          this.generateDealData({ status: 'sourcing' }),
          this.generateDealData({ status: 'initial_contact' }),
          this.generateDealData({ status: 'nda_signed' }),
          this.generateDealData({ status: 'due_diligence' }),
          this.generateDealData({ status: 'closed_won' })
        ]
      }),
      
      'at-risk-deals': () => ({
        deals: [
          this.generateDealData({ 
            status: 'due_diligence',
            last_activity_date: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString()
          }),
          this.generateDealData({ 
            status: 'loi_submitted',
            last_activity_date: new Date(Date.now() - 45 * 24 * 60 * 60 * 1000).toISOString()
          })
        ]
      })
    };

    const generator = scenarios[scenario];
    if (!generator) {
      throw new Error(`Unknown scenario: ${scenario}`);
    }

    return generator();
  }
}

module.exports = TestDataManager;