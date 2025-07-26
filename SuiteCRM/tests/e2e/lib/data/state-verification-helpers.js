/**
 * Database State Verification Helpers
 * Comprehensive utilities for verifying database state during E2E tests
 */

class StateVerificationHelpers {
  constructor(connection, config = {}) {
    this.connection = connection;
    this.config = {
      enableDetailedReporting: config.enableDetailedReporting !== false,
      enablePerformanceMetrics: config.enablePerformanceMetrics !== false,
      maxRecordsToSample: config.maxRecordsToSample || 1000,
      verificationTimeout: config.verificationTimeout || 30000,
      ...config
    };

    this.verificationResults = [];
    this.performanceMetrics = {
      verificationTimes: {},
      queryCounts: {},
      slowQueries: []
    };
  }

  /**
   * Comprehensive database state verification
   */
  async verifyDatabaseState(expectedState, options = {}) {
    const startTime = Date.now();
    const {
      includeRelationships = true,
      includeCustomFields = true,
      includeIntegrityChecks = true,
      strictMode = false
    } = options;

    console.log('🔍 Starting comprehensive database state verification...');

    const verificationReport = {
      timestamp: new Date().toISOString(),
      overallStatus: 'PASSED',
      checks: [],
      errors: [],
      warnings: [],
      statistics: {}
    };

    try {
      // Phase 1: Basic record count verification
      console.log('Phase 1: Verifying record counts...');
      const recordCountResults = await this.verifyRecordCounts(expectedState);
      verificationReport.checks.push(recordCountResults);

      // Phase 2: Data integrity verification
      if (includeIntegrityChecks) {
        console.log('Phase 2: Verifying data integrity...');
        const integrityResults = await this.verifyDataIntegrity();
        verificationReport.checks.push(integrityResults);
      }

      // Phase 3: Relationship verification
      if (includeRelationships) {
        console.log('Phase 3: Verifying relationships...');
        const relationshipResults = await this.verifyRelationships();
        verificationReport.checks.push(relationshipResults);
      }

      // Phase 4: Custom fields verification
      if (includeCustomFields) {
        console.log('Phase 4: Verifying custom fields...');
        const customFieldResults = await this.verifyCustomFields();
        verificationReport.checks.push(customFieldResults);
      }

      // Phase 5: Business rule verification
      console.log('Phase 5: Verifying business rules...');
      const businessRuleResults = await this.verifyBusinessRules();
      verificationReport.checks.push(businessRuleResults);

      // Phase 6: Performance statistics
      if (this.config.enablePerformanceMetrics) {
        console.log('Phase 6: Collecting performance statistics...');
        verificationReport.statistics = await this.collectPerformanceStatistics();
      }

      // Compile final results
      const totalErrors = verificationReport.checks.reduce((sum, check) => sum + check.errors.length, 0);
      const totalWarnings = verificationReport.checks.reduce((sum, check) => sum + check.warnings.length, 0);

      verificationReport.overallStatus = totalErrors > 0 ? 'FAILED' : 
                                       totalWarnings > 0 ? 'PASSED_WITH_WARNINGS' : 'PASSED';

      const duration = Date.now() - startTime;
      verificationReport.duration = duration;

      console.log(`✅ Database state verification completed in ${duration}ms`);
      console.log(`Status: ${verificationReport.overallStatus} (${totalErrors} errors, ${totalWarnings} warnings)`);

      // Store results for reporting
      this.verificationResults.push(verificationReport);

      if (strictMode && totalErrors > 0) {
        throw new Error(`Database state verification failed: ${totalErrors} errors found`);
      }

      return verificationReport;

    } catch (error) {
      verificationReport.overallStatus = 'ERROR';
      verificationReport.errors.push({
        type: 'VERIFICATION_ERROR',
        message: error.message,
        timestamp: new Date().toISOString()
      });

      console.error('❌ Database state verification failed:', error);
      throw error;
    }
  }

  /**
   * Verify record counts match expected state
   */
  async verifyRecordCounts(expectedState) {
    const startTime = Date.now();
    const result = {
      phase: 'Record Count Verification',
      status: 'PASSED',
      errors: [],
      warnings: [],
      details: {}
    };

    try {
      for (const [module, expectedCount] of Object.entries(expectedState)) {
        const actualCount = await this.getRecordCount(module);
        
        result.details[module] = {
          expected: expectedCount,
          actual: actualCount,
          difference: actualCount - expectedCount,
          status: actualCount === expectedCount ? 'MATCH' : 'MISMATCH'
        };

        if (actualCount !== expectedCount) {
          const error = {
            type: 'RECORD_COUNT_MISMATCH',
            module,
            expected: expectedCount,
            actual: actualCount,
            difference: actualCount - expectedCount,
            message: `${module}: expected ${expectedCount}, found ${actualCount} (difference: ${actualCount - expectedCount})`
          };

          if (Math.abs(actualCount - expectedCount) > expectedCount * 0.1) {
            result.errors.push(error);
            result.status = 'FAILED';
          } else {
            result.warnings.push(error);
            if (result.status !== 'FAILED') {
              result.status = 'PASSED_WITH_WARNINGS';
            }
          }
        }
      }

      this.recordPerformanceMetric('record_count_verification', Date.now() - startTime);
      return result;

    } catch (error) {
      result.status = 'ERROR';
      result.errors.push({
        type: 'RECORD_COUNT_ERROR',
        message: error.message
      });
      return result;
    }
  }

  /**
   * Verify data integrity (foreign keys, constraints, etc.)
   */
  async verifyDataIntegrity() {
    const startTime = Date.now();
    const result = {
      phase: 'Data Integrity Verification',
      status: 'PASSED',
      errors: [],
      warnings: [],
      details: {
        foreignKeyChecks: {},
        orphanRecords: {},
        duplicateChecks: {},
        nullConstraints: {}
      }
    };

    try {
      // Check foreign key integrity
      const foreignKeyResults = await this.checkForeignKeyIntegrity();
      result.details.foreignKeyChecks = foreignKeyResults;

      if (foreignKeyResults.brokenKeys.length > 0) {
        result.errors.push(...foreignKeyResults.brokenKeys.map(key => ({
          type: 'BROKEN_FOREIGN_KEY',
          table: key.table,
          field: key.field,
          recordId: key.recordId,
          referencedValue: key.referencedValue,
          message: `Broken foreign key: ${key.table}.${key.field} = ${key.referencedValue}`
        })));
        result.status = 'FAILED';
      }

      // Check for orphan records
      const orphanResults = await this.checkOrphanRecords();
      result.details.orphanRecords = orphanResults;

      if (orphanResults.orphans.length > 0) {
        result.warnings.push(...orphanResults.orphans.map(orphan => ({
          type: 'ORPHAN_RECORD',
          table: orphan.table,
          recordId: orphan.recordId,
          message: `Orphan record found: ${orphan.table}.${orphan.recordId}`
        })));
        if (result.status !== 'FAILED') {
          result.status = 'PASSED_WITH_WARNINGS';
        }
      }

      // Check for unexpected duplicates
      const duplicateResults = await this.checkUnexpectedDuplicates();
      result.details.duplicateChecks = duplicateResults;

      if (duplicateResults.duplicates.length > 0) {
        result.warnings.push(...duplicateResults.duplicates.map(dup => ({
          type: 'UNEXPECTED_DUPLICATE',
          table: dup.table,
          field: dup.field,
          value: dup.value,
          count: dup.count,
          message: `Unexpected duplicates: ${dup.table}.${dup.field} = "${dup.value}" (${dup.count} occurrences)`
        })));
        if (result.status !== 'FAILED') {
          result.status = 'PASSED_WITH_WARNINGS';
        }
      }

      // Check null constraints
      const nullConstraintResults = await this.checkNullConstraints();
      result.details.nullConstraints = nullConstraintResults;

      if (nullConstraintResults.violations.length > 0) {
        result.errors.push(...nullConstraintResults.violations.map(violation => ({
          type: 'NULL_CONSTRAINT_VIOLATION',
          table: violation.table,
          field: violation.field,
          recordId: violation.recordId,
          message: `Null constraint violation: ${violation.table}.${violation.field} is null for record ${violation.recordId}`
        })));
        result.status = 'FAILED';
      }

      this.recordPerformanceMetric('data_integrity_verification', Date.now() - startTime);
      return result;

    } catch (error) {
      result.status = 'ERROR';
      result.errors.push({
        type: 'INTEGRITY_CHECK_ERROR',
        message: error.message
      });
      return result;
    }
  }

  /**
   * Verify relationships between modules
   */
  async verifyRelationships() {
    const startTime = Date.now();
    const result = {
      phase: 'Relationship Verification',
      status: 'PASSED',
      errors: [],
      warnings: [],
      details: {
        manyToManyChecks: {},
        parentChildChecks: {},
        polymorphicChecks: {}
      }
    };

    try {
      // Check many-to-many relationships
      const manyToManyResults = await this.checkManyToManyRelationships();
      result.details.manyToManyChecks = manyToManyResults;

      // Check parent-child relationships
      const parentChildResults = await this.checkParentChildRelationships();
      result.details.parentChildChecks = parentChildResults;

      // Check polymorphic relationships
      const polymorphicResults = await this.checkPolymorphicRelationships();
      result.details.polymorphicChecks = polymorphicResults;

      // Compile errors and warnings from all relationship checks
      const allResults = [manyToManyResults, parentChildResults, polymorphicResults];
      
      for (const checkResult of allResults) {
        if (checkResult.errors) {
          result.errors.push(...checkResult.errors);
        }
        if (checkResult.warnings) {
          result.warnings.push(...checkResult.warnings);
        }
      }

      if (result.errors.length > 0) {
        result.status = 'FAILED';
      } else if (result.warnings.length > 0) {
        result.status = 'PASSED_WITH_WARNINGS';
      }

      this.recordPerformanceMetric('relationship_verification', Date.now() - startTime);
      return result;

    } catch (error) {
      result.status = 'ERROR';
      result.errors.push({
        type: 'RELATIONSHIP_CHECK_ERROR',
        message: error.message
      });
      return result;
    }
  }

  /**
   * Verify custom fields integrity
   */
  async verifyCustomFields() {
    const startTime = Date.now();
    const result = {
      phase: 'Custom Fields Verification',
      status: 'PASSED',
      errors: [],
      warnings: [],
      details: {}
    };

    try {
      const customFieldTables = [
        { main: 'deals', custom: 'deals_cstm' },
        { main: 'accounts', custom: 'accounts_cstm' },
        { main: 'contacts', custom: 'contacts_cstm' },
        { main: 'documents', custom: 'documents_cstm' }
      ];

      for (const { main, custom } of customFieldTables) {
        try {
          const customFieldResult = await this.checkCustomFieldTable(main, custom);
          result.details[custom] = customFieldResult;

          if (customFieldResult.orphanedCustomFields.length > 0) {
            result.warnings.push({
              type: 'ORPHANED_CUSTOM_FIELDS',
              table: custom,
              count: customFieldResult.orphanedCustomFields.length,
              message: `${customFieldResult.orphanedCustomFields.length} orphaned custom field records in ${custom}`
            });
            if (result.status !== 'FAILED') {
              result.status = 'PASSED_WITH_WARNINGS';
            }
          }

          if (customFieldResult.missingCustomFields.length > 0) {
            result.warnings.push({
              type: 'MISSING_CUSTOM_FIELDS',
              table: custom,
              count: customFieldResult.missingCustomFields.length,
              message: `${customFieldResult.missingCustomFields.length} main records missing custom field records in ${custom}`
            });
            if (result.status !== 'FAILED') {
              result.status = 'PASSED_WITH_WARNINGS';
            }
          }

        } catch (error) {
          // Custom field table might not exist, which is acceptable
          if (!error.message.includes("doesn't exist")) {
            result.warnings.push({
              type: 'CUSTOM_FIELD_CHECK_ERROR',
              table: custom,
              message: `Error checking ${custom}: ${error.message}`
            });
          }
        }
      }

      this.recordPerformanceMetric('custom_fields_verification', Date.now() - startTime);
      return result;

    } catch (error) {
      result.status = 'ERROR';
      result.errors.push({
        type: 'CUSTOM_FIELDS_ERROR',
        message: error.message
      });
      return result;
    }
  }

  /**
   * Verify business rules and constraints
   */
  async verifyBusinessRules() {
    const startTime = Date.now();
    const result = {
      phase: 'Business Rules Verification',
      status: 'PASSED',
      errors: [],
      warnings: [],
      details: {}
    };

    try {
      // Check deal-specific business rules
      const dealRules = await this.checkDealBusinessRules();
      result.details.dealRules = dealRules;

      // Check account-specific business rules
      const accountRules = await this.checkAccountBusinessRules();
      result.details.accountRules = accountRules;

      // Check contact-specific business rules
      const contactRules = await this.checkContactBusinessRules();
      result.details.contactRules = contactRules;

      // Compile results
      const allRules = [dealRules, accountRules, contactRules];
      
      for (const ruleResult of allRules) {
        if (ruleResult.violations) {
          result.errors.push(...ruleResult.violations.map(violation => ({
            type: 'BUSINESS_RULE_VIOLATION',
            rule: violation.rule,
            table: violation.table,
            recordId: violation.recordId,
            message: violation.message
          })));
        }
      }

      if (result.errors.length > 0) {
        result.status = 'FAILED';
      }

      this.recordPerformanceMetric('business_rules_verification', Date.now() - startTime);
      return result;

    } catch (error) {
      result.status = 'ERROR';
      result.errors.push({
        type: 'BUSINESS_RULES_ERROR',
        message: error.message
      });
      return result;
    }
  }

  /**
   * Get record count for a module
   */
  async getRecordCount(module, conditions = {}) {
    const {
      includeDeleted = false,
      testDataOnly = true,
      customWhere = ''
    } = conditions;

    let whereClause = includeDeleted ? '' : 'WHERE deleted = 0';
    
    if (testDataOnly) {
      const testFilter = includeDeleted ? 
        'WHERE (name LIKE ? OR first_name LIKE ? OR document_name LIKE ?)' :
        'AND (name LIKE ? OR first_name LIKE ? OR document_name LIKE ?)';
      
      whereClause += whereClause ? ` ${testFilter}` : testFilter;
    }

    if (customWhere) {
      whereClause += whereClause ? ` AND ${customWhere}` : `WHERE ${customWhere}`;
    }

    const testPrefix = 'E2E_TEST_%';
    const params = testDataOnly ? [testPrefix, testPrefix, testPrefix] : [];

    const [rows] = await this.connection.execute(
      `SELECT COUNT(*) as count FROM ${module} ${whereClause}`,
      params
    );

    return rows[0].count;
  }

  /**
   * Check foreign key integrity
   */
  async checkForeignKeyIntegrity() {
    const foreignKeyMappings = [
      { table: 'deals', field: 'account_id', referencedTable: 'accounts' },
      { table: 'deals', field: 'assigned_user_id', referencedTable: 'users' },
      { table: 'contacts', field: 'account_id', referencedTable: 'accounts' },
      { table: 'contacts', field: 'assigned_user_id', referencedTable: 'users' },
      { table: 'documents', field: 'created_by', referencedTable: 'users' },
      { table: 'checklists', field: 'deal_id', referencedTable: 'deals' }
    ];

    const brokenKeys = [];

    for (const mapping of foreignKeyMappings) {
      try {
        const [rows] = await this.connection.execute(`
          SELECT m.id, m.${mapping.field}
          FROM ${mapping.table} m
          LEFT JOIN ${mapping.referencedTable} r ON m.${mapping.field} = r.id
          WHERE m.deleted = 0 
          AND m.${mapping.field} IS NOT NULL 
          AND (r.id IS NULL OR r.deleted = 1)
          LIMIT 100
        `);

        for (const row of rows) {
          brokenKeys.push({
            table: mapping.table,
            field: mapping.field,
            recordId: row.id,
            referencedValue: row[mapping.field],
            referencedTable: mapping.referencedTable
          });
        }
      } catch (error) {
        console.warn(`Failed to check foreign key ${mapping.table}.${mapping.field}: ${error.message}`);
      }
    }

    return { brokenKeys, totalChecked: foreignKeyMappings.length };
  }

  /**
   * Check for orphan records
   */
  async checkOrphanRecords() {
    const orphans = [];

    // Check for contacts without accounts (if account is required)
    try {
      const [contactOrphans] = await this.connection.execute(`
        SELECT c.id
        FROM contacts c
        LEFT JOIN accounts a ON c.account_id = a.id
        WHERE c.deleted = 0 
        AND c.account_id IS NOT NULL 
        AND (a.id IS NULL OR a.deleted = 1)
        LIMIT 50
      `);

      for (const orphan of contactOrphans) {
        orphans.push({
          table: 'contacts',
          recordId: orphan.id,
          reason: 'Account not found or deleted'
        });
      }
    } catch (error) {
      console.warn(`Failed to check contact orphans: ${error.message}`);
    }

    // Check for documents without parent records
    try {
      const [documentOrphans] = await this.connection.execute(`
        SELECT d.id, d.parent_type, d.parent_id
        FROM documents d
        WHERE d.deleted = 0 
        AND d.parent_type IS NOT NULL 
        AND d.parent_id IS NOT NULL
        LIMIT 50
      `);

      for (const doc of documentOrphans) {
        // Check if parent exists (simplified check)
        const parentTable = doc.parent_type.toLowerCase();
        try {
          const [parentCheck] = await this.connection.execute(
            `SELECT id FROM ${parentTable} WHERE id = ? AND deleted = 0`,
            [doc.parent_id]
          );

          if (parentCheck.length === 0) {
            orphans.push({
              table: 'documents',
              recordId: doc.id,
              reason: `Parent ${doc.parent_type}(${doc.parent_id}) not found`
            });
          }
        } catch (error) {
          // Parent table might not exist or be accessible
        }
      }
    } catch (error) {
      console.warn(`Failed to check document orphans: ${error.message}`);
    }

    return { orphans, totalChecked: 2 };
  }

  /**
   * Check for unexpected duplicates
   */
  async checkUnexpectedDuplicates() {
    const duplicates = [];

    // Check for duplicate deal names within the same account
    try {
      const [dealDuplicates] = await this.connection.execute(`
        SELECT name, account_id, COUNT(*) as count
        FROM deals 
        WHERE deleted = 0 
        AND name LIKE 'E2E_TEST_%'
        GROUP BY name, account_id 
        HAVING COUNT(*) > 1
        LIMIT 50
      `);

      for (const dup of dealDuplicates) {
        duplicates.push({
          table: 'deals',
          field: 'name',
          value: dup.name,
          count: dup.count,
          additionalInfo: `account_id: ${dup.account_id}`
        });
      }
    } catch (error) {
      console.warn(`Failed to check deal duplicates: ${error.message}`);
    }

    // Check for duplicate email addresses in contacts
    try {
      const [emailDuplicates] = await this.connection.execute(`
        SELECT email1, COUNT(*) as count
        FROM contacts 
        WHERE deleted = 0 
        AND email1 IS NOT NULL 
        AND email1 LIKE '%@example.com'
        GROUP BY email1 
        HAVING COUNT(*) > 1
        LIMIT 50
      `);

      for (const dup of emailDuplicates) {
        duplicates.push({
          table: 'contacts',
          field: 'email1',
          value: dup.email1,
          count: dup.count
        });
      }
    } catch (error) {
      console.warn(`Failed to check email duplicates: ${error.message}`);
    }

    return { duplicates, totalChecked: 2 };
  }

  /**
   * Check null constraints for required fields
   */
  async checkNullConstraints() {
    const violations = [];

    const requiredFields = [
      { table: 'deals', field: 'name' },
      { table: 'accounts', field: 'name' },
      { table: 'contacts', field: 'last_name' },
      { table: 'users', field: 'user_name' }
    ];

    for (const constraint of requiredFields) {
      try {
        const [rows] = await this.connection.execute(`
          SELECT id
          FROM ${constraint.table} 
          WHERE deleted = 0 
          AND (${constraint.field} IS NULL OR ${constraint.field} = '')
          LIMIT 50
        `);

        for (const row of rows) {
          violations.push({
            table: constraint.table,
            field: constraint.field,
            recordId: row.id
          });
        }
      } catch (error) {
        console.warn(`Failed to check null constraint ${constraint.table}.${constraint.field}: ${error.message}`);
      }
    }

    return { violations, totalChecked: requiredFields.length };
  }

  /**
   * Check many-to-many relationships
   */
  async checkManyToManyRelationships() {
    const results = { errors: [], warnings: [] };

    // Check deals_contacts relationships
    try {
      const [orphanedDealsContacts] = await this.connection.execute(`
        SELECT dc.id, dc.deal_id, dc.contact_id
        FROM deals_contacts dc
        LEFT JOIN deals d ON dc.deal_id = d.id
        LEFT JOIN contacts c ON dc.contact_id = c.id
        WHERE dc.deleted = 0 
        AND ((d.id IS NULL OR d.deleted = 1) OR (c.id IS NULL OR c.deleted = 1))
        LIMIT 50
      `);

      for (const orphan of orphanedDealsContacts) {
        results.errors.push({
          type: 'ORPHANED_RELATIONSHIP',
          table: 'deals_contacts',
          relationshipId: orphan.id,
          dealId: orphan.deal_id,
          contactId: orphan.contact_id,
          message: `Orphaned deals_contacts relationship: ${orphan.id}`
        });
      }
    } catch (error) {
      console.warn(`Failed to check deals_contacts relationships: ${error.message}`);
    }

    return results;
  }

  /**
   * Check parent-child relationships
   */
  async checkParentChildRelationships() {
    const results = { errors: [], warnings: [] };

    // Check documents -> deals relationship
    try {
      const [orphanedDocuments] = await this.connection.execute(`
        SELECT d.id, d.deal_id
        FROM documents d
        LEFT JOIN deals dl ON d.deal_id = dl.id
        WHERE d.deleted = 0 
        AND d.deal_id IS NOT NULL 
        AND (dl.id IS NULL OR dl.deleted = 1)
        LIMIT 50
      `);

      for (const orphan of orphanedDocuments) {
        results.errors.push({
          type: 'ORPHANED_CHILD',
          table: 'documents',
          recordId: orphan.id,
          parentId: orphan.deal_id,
          parentTable: 'deals',
          message: `Document ${orphan.id} references non-existent deal ${orphan.deal_id}`
        });
      }
    } catch (error) {
      console.warn(`Failed to check document-deal relationships: ${error.message}`);
    }

    return results;
  }

  /**
   * Check polymorphic relationships
   */
  async checkPolymorphicRelationships() {
    const results = { errors: [], warnings: [] };

    // Check activities/tasks with parent_type and parent_id
    const polymorphicTables = ['activities', 'tasks'];

    for (const table of polymorphicTables) {
      try {
        const [orphanedPolymorphic] = await this.connection.execute(`
          SELECT id, parent_type, parent_id
          FROM ${table}
          WHERE deleted = 0 
          AND parent_type IS NOT NULL 
          AND parent_id IS NOT NULL
          LIMIT 50
        `);

        for (const record of orphanedPolymorphic) {
          // Simplified check - in reality, you'd check against the actual parent table
          const parentTable = record.parent_type.toLowerCase();
          
          try {
            const [parentExists] = await this.connection.execute(
              `SELECT id FROM ${parentTable} WHERE id = ? AND deleted = 0`,
              [record.parent_id]
            );

            if (parentExists.length === 0) {
              results.errors.push({
                type: 'BROKEN_POLYMORPHIC_RELATIONSHIP',
                table,
                recordId: record.id,
                parentType: record.parent_type,
                parentId: record.parent_id,
                message: `${table} ${record.id} references non-existent ${record.parent_type} ${record.parent_id}`
              });
            }
          } catch (error) {
            // Parent table might not exist or be accessible
          }
        }
      } catch (error) {
        console.warn(`Failed to check polymorphic relationships for ${table}: ${error.message}`);
      }
    }

    return results;
  }

  /**
   * Check custom field table integrity
   */
  async checkCustomFieldTable(mainTable, customTable) {
    const result = {
      orphanedCustomFields: [],
      missingCustomFields: [],
      totalMainRecords: 0,
      totalCustomRecords: 0
    };

    try {
      // Get counts
      const [mainCount] = await this.connection.execute(
        `SELECT COUNT(*) as count FROM ${mainTable} WHERE deleted = 0 AND name LIKE 'E2E_TEST_%'`
      );
      result.totalMainRecords = mainCount[0].count;

      const [customCount] = await this.connection.execute(
        `SELECT COUNT(*) as count FROM ${customTable} WHERE id_c LIKE 'E2E_TEST_%'`
      );
      result.totalCustomRecords = customCount[0].count;

      // Check for orphaned custom field records
      const [orphaned] = await this.connection.execute(`
        SELECT c.id_c
        FROM ${customTable} c
        LEFT JOIN ${mainTable} m ON c.id_c = m.id
        WHERE c.id_c LIKE 'E2E_TEST_%'
        AND (m.id IS NULL OR m.deleted = 1)
        LIMIT 50
      `);

      result.orphanedCustomFields = orphaned.map(row => row.id_c);

      // Check for main records missing custom field records
      const [missing] = await this.connection.execute(`
        SELECT m.id
        FROM ${mainTable} m
        LEFT JOIN ${customTable} c ON m.id = c.id_c
        WHERE m.deleted = 0 
        AND m.name LIKE 'E2E_TEST_%'
        AND c.id_c IS NULL
        LIMIT 50
      `);

      result.missingCustomFields = missing.map(row => row.id);

    } catch (error) {
      throw new Error(`Failed to check custom field table ${customTable}: ${error.message}`);
    }

    return result;
  }

  /**
   * Check deal-specific business rules
   */
  async checkDealBusinessRules() {
    const violations = [];

    try {
      // Rule: Deal amount should be positive
      const [negativeAmounts] = await this.connection.execute(`
        SELECT id, name, amount
        FROM deals 
        WHERE deleted = 0 
        AND name LIKE 'E2E_TEST_%'
        AND (amount < 0 OR amount IS NULL)
        LIMIT 50
      `);

      for (const deal of negativeAmounts) {
        violations.push({
          rule: 'POSITIVE_AMOUNT',
          table: 'deals',
          recordId: deal.id,
          message: `Deal ${deal.name} has invalid amount: ${deal.amount}`
        });
      }

      // Rule: Probability should be between 0 and 100
      const [invalidProbability] = await this.connection.execute(`
        SELECT id, name, probability
        FROM deals 
        WHERE deleted = 0 
        AND name LIKE 'E2E_TEST_%'
        AND (probability < 0 OR probability > 100)
        LIMIT 50
      `);

      for (const deal of invalidProbability) {
        violations.push({
          rule: 'VALID_PROBABILITY',
          table: 'deals',
          recordId: deal.id,
          message: `Deal ${deal.name} has invalid probability: ${deal.probability}`
        });
      }

      // Rule: Closed Won deals should have 100% probability
      const [closedWonInconsistent] = await this.connection.execute(`
        SELECT id, name, sales_stage, probability
        FROM deals 
        WHERE deleted = 0 
        AND name LIKE 'E2E_TEST_%'
        AND sales_stage = 'Closed Won'
        AND probability != 100
        LIMIT 50
      `);

      for (const deal of closedWonInconsistent) {
        violations.push({
          rule: 'CLOSED_WON_PROBABILITY',
          table: 'deals',
          recordId: deal.id,
          message: `Closed Won deal ${deal.name} should have 100% probability, has ${deal.probability}%`
        });
      }

    } catch (error) {
      console.warn(`Failed to check deal business rules: ${error.message}`);
    }

    return { violations };
  }

  /**
   * Check account-specific business rules
   */
  async checkAccountBusinessRules() {
    const violations = [];

    try {
      // Rule: Account should have a valid industry
      const validIndustries = ['Technology', 'Healthcare', 'Manufacturing', 'Financial Services', 'Retail', 'Other'];
      
      const [invalidIndustry] = await this.connection.execute(`
        SELECT id, name, industry
        FROM accounts 
        WHERE deleted = 0 
        AND name LIKE 'E2E_TEST_%'
        AND (industry IS NULL OR industry NOT IN (${validIndustries.map(() => '?').join(',')}))
        LIMIT 50
      `, validIndustries);

      for (const account of invalidIndustry) {
        violations.push({
          rule: 'VALID_INDUSTRY',
          table: 'accounts',
          recordId: account.id,
          message: `Account ${account.name} has invalid industry: ${account.industry}`
        });
      }

    } catch (error) {
      console.warn(`Failed to check account business rules: ${error.message}`);
    }

    return { violations };
  }

  /**
   * Check contact-specific business rules
   */
  async checkContactBusinessRules() {
    const violations = [];

    try {
      // Rule: Contact should have valid email format
      const [invalidEmails] = await this.connection.execute(`
        SELECT id, first_name, last_name, email1
        FROM contacts 
        WHERE deleted = 0 
        AND (first_name LIKE 'E2E_TEST_%' OR first_name LIKE 'TestFirst%')
        AND email1 IS NOT NULL 
        AND email1 NOT REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$'
        LIMIT 50
      `);

      for (const contact of invalidEmails) {
        violations.push({
          rule: 'VALID_EMAIL_FORMAT',
          table: 'contacts',
          recordId: contact.id,
          message: `Contact ${contact.first_name} ${contact.last_name} has invalid email: ${contact.email1}`
        });
      }

    } catch (error) {
      console.warn(`Failed to check contact business rules: ${error.message}`);
    }

    return { violations };
  }

  /**
   * Collect performance statistics
   */
  async collectPerformanceStatistics() {
    const stats = {
      tableStatistics: {},
      indexUsage: {},
      queryPerformance: this.performanceMetrics,
      memoryUsage: process.memoryUsage()
    };

    try {
      // Collect table statistics
      const tables = ['deals', 'accounts', 'contacts', 'documents'];
      
      for (const table of tables) {
        const [tableStats] = await this.connection.execute(`
          SELECT 
            COUNT(*) as total_rows,
            COUNT(CASE WHEN deleted = 0 THEN 1 END) as active_rows,
            COUNT(CASE WHEN deleted = 1 THEN 1 END) as deleted_rows
          FROM ${table}
        `);

        stats.tableStatistics[table] = tableStats[0];
      }

    } catch (error) {
      console.warn(`Failed to collect performance statistics: ${error.message}`);
    }

    return stats;
  }

  /**
   * Record performance metric
   */
  recordPerformanceMetric(operation, duration) {
    if (!this.config.enablePerformanceMetrics) return;

    if (!this.performanceMetrics.verificationTimes[operation]) {
      this.performanceMetrics.verificationTimes[operation] = [];
    }

    this.performanceMetrics.verificationTimes[operation].push(duration);

    if (duration > 5000) { // 5 seconds threshold for slow queries
      this.performanceMetrics.slowQueries.push({
        operation,
        duration,
        timestamp: new Date().toISOString()
      });
    }

    // Increment query counts
    this.performanceMetrics.queryCounts[operation] = 
      (this.performanceMetrics.queryCounts[operation] || 0) + 1;
  }

  /**
   * Get verification history
   */
  getVerificationHistory() {
    return this.verificationResults;
  }

  /**
   * Get performance summary
   */
  getPerformanceSummary() {
    const summary = {
      totalVerifications: this.verificationResults.length,
      averageDuration: 0,
      slowQueries: this.performanceMetrics.slowQueries,
      operationStats: {}
    };

    if (this.verificationResults.length > 0) {
      const totalDuration = this.verificationResults.reduce((sum, result) => sum + result.duration, 0);
      summary.averageDuration = Math.round(totalDuration / this.verificationResults.length);
    }

    // Calculate operation statistics
    for (const [operation, times] of Object.entries(this.performanceMetrics.verificationTimes)) {
      const avgTime = times.reduce((sum, time) => sum + time, 0) / times.length;
      summary.operationStats[operation] = {
        count: times.length,
        averageTime: Math.round(avgTime),
        minTime: Math.min(...times),
        maxTime: Math.max(...times)
      };
    }

    return summary;
  }

  /**
   * Generate comprehensive verification report
   */
  generateVerificationReport() {
    const report = {
      summary: {
        totalVerifications: this.verificationResults.length,
        lastVerification: this.verificationResults[this.verificationResults.length - 1],
        performanceSummary: this.getPerformanceSummary()
      },
      verificationHistory: this.verificationResults,
      recommendations: this.generateRecommendations()
    };

    return report;
  }

  /**
   * Generate recommendations based on verification results
   */
  generateRecommendations() {
    const recommendations = [];

    // Analyze verification results for patterns
    const recentResults = this.verificationResults.slice(-5);
    
    // Check for recurring errors
    const errorPatterns = {};
    for (const result of recentResults) {
      for (const check of result.checks) {
        for (const error of check.errors) {
          const key = `${error.type}_${error.table || error.module}`;
          errorPatterns[key] = (errorPatterns[key] || 0) + 1;
        }
      }
    }

    for (const [pattern, count] of Object.entries(errorPatterns)) {
      if (count >= 3) {
        recommendations.push({
          type: 'RECURRING_ERROR',
          pattern,
          count,
          message: `Recurring error pattern detected: ${pattern} (${count} occurrences in recent verifications)`,
          priority: 'HIGH'
        });
      }
    }

    // Check for performance issues
    if (this.performanceMetrics.slowQueries.length > 0) {
      recommendations.push({
        type: 'PERFORMANCE',
        message: `${this.performanceMetrics.slowQueries.length} slow verification queries detected`,
        priority: 'MEDIUM',
        details: this.performanceMetrics.slowQueries.slice(-3)
      });
    }

    return recommendations;
  }
}

module.exports = StateVerificationHelpers;