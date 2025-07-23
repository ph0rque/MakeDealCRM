/**
 * Test Data Helper for Deals Module E2E Tests
 * Provides utilities for setting up and tearing down test data
 */

const mysql = require('mysql2/promise');

class DealsTestDataHelper {
  constructor() {
    this.connection = null;
    this.testDataPrefix = 'E2E_TEST_';
    this.createdRecordIds = {
      deals: [],
      accounts: [],
      contacts: [],
      users: []
    };
  }

  /**
   * Initialize database connection
   */
  async connect() {
    this.connection = await mysql.createConnection({
      host: process.env.DB_HOST || 'localhost',
      user: process.env.DB_USER || 'root',
      password: process.env.DB_PASSWORD || 'root',
      database: process.env.DB_NAME || 'suitecrm',
      port: process.env.DB_PORT || 3306
    });
  }

  /**
   * Close database connection
   */
  async disconnect() {
    if (this.connection) {
      await this.connection.end();
    }
  }

  /**
   * Create test account
   */
  async createTestAccount(accountData) {
    const id = this.generateId();
    const defaults = {
      id,
      name: `${this.testDataPrefix}Test Account`,
      account_type: 'Customer',
      industry: 'Technology',
      annual_revenue: '1000000',
      employees: '100',
      website: 'https://testaccount.com',
      phone_office: '555-123-4567',
      billing_address_street: '123 Test Street',
      billing_address_city: 'Test City',
      billing_address_state: 'CA',
      billing_address_postalcode: '12345',
      billing_address_country: 'USA',
      date_entered: new Date().toISOString().slice(0, 19).replace('T', ' '),
      date_modified: new Date().toISOString().slice(0, 19).replace('T', ' '),
      created_by: '1',
      modified_user_id: '1',
      deleted: 0
    };

    const data = { ...defaults, ...accountData };
    
    await this.connection.execute(
      `INSERT INTO accounts SET ?`,
      [data]
    );

    this.createdRecordIds.accounts.push(id);
    return id;
  }

  /**
   * Create test contact
   */
  async createTestContact(contactData) {
    const id = this.generateId();
    const defaults = {
      id,
      first_name: `${this.testDataPrefix}Test`,
      last_name: 'Contact',
      email1: 'test@example.com',
      phone_work: '555-987-6543',
      title: 'Test Manager',
      department: 'Testing',
      date_entered: new Date().toISOString().slice(0, 19).replace('T', ' '),
      date_modified: new Date().toISOString().slice(0, 19).replace('T', ' '),
      created_by: '1',
      modified_user_id: '1',
      deleted: 0
    };

    const data = { ...defaults, ...contactData };
    
    await this.connection.execute(
      `INSERT INTO contacts SET ?`,
      [data]
    );

    // Create email address record
    if (data.email1) {
      await this.createEmailAddress(id, data.email1, 'Contacts');
    }

    this.createdRecordIds.contacts.push(id);
    return id;
  }

  /**
   * Create test deal with relationships
   */
  async createTestDeal(dealData) {
    const id = this.generateId();
    const defaults = {
      id,
      name: `${this.testDataPrefix}Test Deal`,
      amount: 50000,
      sales_stage: 'Prospecting',
      probability: 10,
      date_closed: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10),
      deal_type: 'New Business',
      lead_source: 'Website',
      description: 'Test deal created by E2E test',
      date_entered: new Date().toISOString().slice(0, 19).replace('T', ' '),
      date_modified: new Date().toISOString().slice(0, 19).replace('T', ' '),
      created_by: '1',
      modified_user_id: '1',
      assigned_user_id: '1',
      deleted: 0
    };

    const data = { ...defaults, ...dealData };
    
    // Handle custom fields
    const customFields = {};
    if (data.email) {
      customFields.email_c = data.email;
      delete data.email;
    }
    if (data.phone) {
      customFields.phone_c = data.phone;
      delete data.phone;
    }
    if (data.competitor) {
      customFields.competitor_c = data.competitor;
      delete data.competitor;
    }

    // Insert main record
    await this.connection.execute(
      `INSERT INTO deals SET ?`,
      [data]
    );

    // Insert custom fields if any
    if (Object.keys(customFields).length > 0) {
      await this.connection.execute(
        `INSERT INTO deals_cstm SET id_c = ?, ?`,
        [id, customFields]
      );
    }

    // Create relationships
    if (data.account_id) {
      await this.createRelationship('deals_accounts', id, data.account_id);
    }

    if (data.contact_ids && Array.isArray(data.contact_ids)) {
      for (const contactId of data.contact_ids) {
        await this.createRelationship('deals_contacts', id, contactId);
      }
    }

    this.createdRecordIds.deals.push(id);
    return id;
  }

  /**
   * Create duplicate deals for testing
   */
  async createDuplicateScenarios() {
    // Create base account
    const accountId = await this.createTestAccount({
      name: `${this.testDataPrefix}Acme Corporation`
    });

    // Create base contact
    const contactId = await this.createTestContact({
      first_name: 'John',
      last_name: 'Doe',
      email1: 'john.doe@acmecorp.com',
      account_id: accountId
    });

    // Scenario 1: Exact duplicate (same name, email, phone)
    const deal1 = await this.createTestDeal({
      name: `${this.testDataPrefix}Enterprise Software License`,
      amount: 150000,
      sales_stage: 'Negotiation',
      account_id: accountId,
      contact_ids: [contactId],
      email: 'sales@acmecorp.com',
      phone: '555-123-4567'
    });

    // Scenario 2: Partial duplicate (same name, different details)
    const deal2 = await this.createTestDeal({
      name: `${this.testDataPrefix}Enterprise Software License`,
      amount: 175000,
      sales_stage: 'Proposal',
      account_id: accountId,
      email: 'contact@acmecorp.com',
      phone: '555-123-4568'
    });

    // Scenario 3: Similar name
    const deal3 = await this.createTestDeal({
      name: `${this.testDataPrefix}Enterprise Software`,
      amount: 125000,
      sales_stage: 'Qualification',
      account_id: accountId
    });

    // Scenario 4: Same email/phone, different name
    const deal4 = await this.createTestDeal({
      name: `${this.testDataPrefix}Cloud Services Contract`,
      amount: 80000,
      sales_stage: 'Prospecting',
      email: 'sales@acmecorp.com',
      phone: '555-123-4567'
    });

    return {
      accountId,
      contactId,
      dealIds: [deal1, deal2, deal3, deal4]
    };
  }

  /**
   * Clean up all test data
   */
  async cleanup() {
    console.log('Cleaning up test data...');

    // Delete deals
    if (this.createdRecordIds.deals.length > 0) {
      await this.connection.execute(
        `UPDATE deals SET deleted = 1 WHERE id IN (?)`,
        [this.createdRecordIds.deals]
      );
      await this.connection.execute(
        `DELETE FROM deals_cstm WHERE id_c IN (?)`,
        [this.createdRecordIds.deals]
      );
    }

    // Delete accounts
    if (this.createdRecordIds.accounts.length > 0) {
      await this.connection.execute(
        `UPDATE accounts SET deleted = 1 WHERE id IN (?)`,
        [this.createdRecordIds.accounts]
      );
    }

    // Delete contacts
    if (this.createdRecordIds.contacts.length > 0) {
      await this.connection.execute(
        `UPDATE contacts SET deleted = 1 WHERE id IN (?)`,
        [this.createdRecordIds.contacts]
      );
    }

    // Clean up relationships
    await this.cleanupRelationships();

    // Clean up by prefix as backup
    await this.cleanupByPrefix();

    console.log('Test data cleanup completed');
  }

  /**
   * Clean up using prefix (backup method)
   */
  async cleanupByPrefix() {
    // Clean deals
    await this.connection.execute(
      `UPDATE deals SET deleted = 1 WHERE name LIKE ?`,
      [`${this.testDataPrefix}%`]
    );

    // Clean accounts
    await this.connection.execute(
      `UPDATE accounts SET deleted = 1 WHERE name LIKE ?`,
      [`${this.testDataPrefix}%`]
    );

    // Clean contacts
    await this.connection.execute(
      `UPDATE contacts SET deleted = 1 WHERE first_name LIKE ?`,
      [`${this.testDataPrefix}%`]
    );
  }

  /**
   * Helper methods
   */
  
  generateId() {
    return `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
  }

  async createRelationship(table, leftId, rightId) {
    const id = this.generateId();
    await this.connection.execute(
      `INSERT INTO ${table} SET id = ?, deal_id = ?, ${table.includes('account') ? 'account_id' : 'contact_id'} = ?, date_modified = NOW(), deleted = 0`,
      [id, leftId, rightId]
    );
  }

  async createEmailAddress(beanId, email, module) {
    const emailId = this.generateId();
    const addrId = this.generateId();
    
    // Insert email address
    await this.connection.execute(
      `INSERT INTO email_addresses SET id = ?, email_address = ?, email_address_caps = ?, date_created = NOW(), date_modified = NOW(), deleted = 0`,
      [emailId, email, email.toUpperCase()]
    );

    // Link to bean
    await this.connection.execute(
      `INSERT INTO email_addr_bean_rel SET id = ?, email_address_id = ?, bean_id = ?, bean_module = ?, primary_address = 1, date_created = NOW(), date_modified = NOW(), deleted = 0`,
      [addrId, emailId, beanId, module]
    );
  }

  async cleanupRelationships() {
    if (this.createdRecordIds.deals.length > 0) {
      await this.connection.execute(
        `DELETE FROM deals_accounts WHERE deal_id IN (?)`,
        [this.createdRecordIds.deals]
      );
      await this.connection.execute(
        `DELETE FROM deals_contacts WHERE deal_id IN (?)`,
        [this.createdRecordIds.deals]
      );
    }
  }

  /**
   * Wait for data to be available in the application
   */
  async waitForDataSync() {
    // Give the application time to process the data
    await new Promise(resolve => setTimeout(resolve, 1000));
  }
}

// Playwright test fixtures
module.exports = {
  DealsTestDataHelper,
  
  // Playwright fixture for easy use in tests
  testDataHelper: async ({}, use) => {
    const helper = new DealsTestDataHelper();
    await helper.connect();
    
    // Use the helper in tests
    await use(helper);
    
    // Cleanup after tests
    await helper.cleanup();
    await helper.disconnect();
  }
};

// Export for direct use
module.exports.createTestFixtures = async () => {
  const helper = new DealsTestDataHelper();
  await helper.connect();
  const fixtures = await helper.createDuplicateScenarios();
  await helper.waitForDataSync();
  return { helper, fixtures };
};

module.exports.cleanupTestFixtures = async (helper) => {
  if (helper) {
    await helper.cleanup();
    await helper.disconnect();
  }
};