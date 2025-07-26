/**
 * Data Relationship Manager
 * Manages complex data relationships between modules in E2E tests
 */

class DataRelationshipManager {
  constructor(connection, config = {}) {
    this.connection = connection;
    this.config = {
      enableCascadeDelete: config.enableCascadeDelete !== false,
      validateRelationships: config.validateRelationships !== false,
      trackOrphanRecords: config.trackOrphanRecords !== false,
      ...config
    };

    // Define module relationships
    this.relationships = new Map([
      // Deal relationships
      ['deals', {
        parents: [], // Deals don't have required parents
        children: ['documents', 'checklists', 'activities', 'tasks'],
        optional: ['accounts', 'contacts', 'users'],
        manyToMany: ['contacts'], // deals_contacts table
        oneToMany: ['documents', 'checklists', 'activities', 'tasks'],
        customFields: 'deals_cstm'
      }],
      
      // Account relationships  
      ['accounts', {
        parents: [],
        children: ['contacts', 'deals', 'documents'],
        optional: ['users'],
        oneToMany: ['contacts', 'deals'],
        customFields: 'accounts_cstm'
      }],
      
      // Contact relationships
      ['contacts', {
        parents: [], // Can exist without account
        children: ['activities', 'tasks'],
        optional: ['accounts'],
        manyToMany: ['deals'], // deals_contacts table
        oneToMany: ['activities', 'tasks'],
        customFields: 'contacts_cstm'
      }],
      
      // Document relationships
      ['documents', {
        parents: [], // Can exist independently
        children: [],
        optional: ['deals', 'accounts', 'contacts'],
        polymorphic: true, // Can relate to any module
        customFields: 'documents_cstm'
      }],
      
      // Checklist relationships
      ['checklists', {
        parents: ['deals'], // Usually belong to deals
        children: [],
        optional: ['users'],
        customFields: 'checklists_cstm'
      }],
      
      // Activity relationships
      ['activities', {
        parents: [], // Can exist independently
        children: [],
        optional: ['deals', 'accounts', 'contacts', 'users'],
        polymorphic: true,
        customFields: 'activities_cstm'
      }],
      
      // Task relationships
      ['tasks', {
        parents: [],
        children: [],
        optional: ['deals', 'accounts', 'contacts', 'users'],
        polymorphic: true,
        customFields: 'tasks_cstm'
      }]
    ]);

    // Track created relationships for cleanup
    this.createdRelationships = new Map();
    this.orphanRecords = new Set();
  }

  /**
   * Create record with all its relationships
   */
  async createRecordWithRelationships(module, data, options = {}) {
    const {
      createMissingParents = true,
      createOptionalRelationships = true,
      validateIntegrity = true
    } = options;

    try {
      // Extract relationship data
      const { recordData, relationshipData } = this.extractRelationshipData(module, data);
      
      // Create parent records if needed
      if (createMissingParents) {
        await this.createMissingParents(module, relationshipData);
      }

      // Create the main record
      const recordId = await this.createMainRecord(module, recordData);

      // Create relationships
      await this.createRelationships(module, recordId, relationshipData, {
        createOptional: createOptionalRelationships
      });

      // Validate integrity if requested
      if (validateIntegrity) {
        await this.validateRecordIntegrity(module, recordId);
      }

      return recordId;

    } catch (error) {
      throw new Error(`Failed to create ${module} with relationships: ${error.message}`);
    }
  }

  /**
   * Extract relationship data from record data
   */
  extractRelationshipData(module, data) {
    const moduleConfig = this.relationships.get(module);
    if (!moduleConfig) {
      throw new Error(`Unknown module: ${module}`);
    }

    const recordData = { ...data };
    const relationshipData = {};

    // Extract many-to-many relationships
    if (moduleConfig.manyToMany) {
      for (const relatedModule of moduleConfig.manyToMany) {
        const fieldName = `${relatedModule.slice(0, -1)}_ids`; // e.g., contact_ids
        if (recordData[fieldName]) {
          relationshipData[relatedModule] = recordData[fieldName];
          delete recordData[fieldName];
        }
      }
    }

    // Extract polymorphic relationships
    if (moduleConfig.polymorphic) {
      if (recordData.parent_type && recordData.parent_id) {
        relationshipData.polymorphic = {
          type: recordData.parent_type,
          id: recordData.parent_id
        };
      }
    }

    // Extract foreign key relationships
    for (const key of Object.keys(recordData)) {
      if (key.endsWith('_id') && key !== 'id') {
        const relatedModule = key.replace('_id', 's'); // Convert user_id to users
        if (recordData[key]) {
          relationshipData[relatedModule] = recordData[key];
        }
      }
    }

    return { recordData, relationshipData };
  }

  /**
   * Create missing parent records
   */
  async createMissingParents(module, relationshipData) {
    const moduleConfig = this.relationships.get(module);
    
    for (const parentModule of moduleConfig.parents || []) {
      const parentId = relationshipData[parentModule];
      if (parentId && !(await this.recordExists(parentModule, parentId))) {
        console.log(`Creating missing parent ${parentModule} record: ${parentId}`);
        
        // Generate minimal parent data
        const parentData = this.generateMinimalRecordData(parentModule, parentId);
        await this.createMainRecord(parentModule, parentData);
      }
    }
  }

  /**
   * Create the main record
   */
  async createMainRecord(module, data) {
    const columns = Object.keys(data);
    const placeholders = columns.map(() => '?').join(',');
    const values = Object.values(data);

    const query = `INSERT INTO ${module} (${columns.join(',')}) VALUES (${placeholders})`;
    const [result] = await this.connection.execute(query, values);

    // Track created record
    this.trackCreatedRecord(module, data.id);

    return data.id;
  }

  /**
   * Create all relationships for a record
   */
  async createRelationships(module, recordId, relationshipData, options = {}) {
    const { createOptional = true } = options;
    const moduleConfig = this.relationships.get(module);

    // Create many-to-many relationships
    if (moduleConfig.manyToMany) {
      for (const relatedModule of moduleConfig.manyToMany) {
        const relatedIds = relationshipData[relatedModule];
        if (relatedIds && Array.isArray(relatedIds)) {
          await this.createManyToManyRelationships(module, recordId, relatedModule, relatedIds);
        }
      }
    }

    // Create polymorphic relationships
    if (relationshipData.polymorphic) {
      await this.createPolymorphicRelationship(module, recordId, relationshipData.polymorphic);
    }

    // Create optional relationships
    if (createOptional && moduleConfig.optional) {
      for (const optionalModule of moduleConfig.optional) {
        const relatedId = relationshipData[optionalModule];
        if (relatedId) {
          await this.createOptionalRelationship(module, recordId, optionalModule, relatedId);
        }
      }
    }
  }

  /**
   * Create many-to-many relationships
   */
  async createManyToManyRelationships(module, recordId, relatedModule, relatedIds) {
    const relationshipTable = this.getRelationshipTableName(module, relatedModule);
    const moduleField = `${module.slice(0, -1)}_id`; // deals -> deal_id
    const relatedField = `${relatedModule.slice(0, -1)}_id`; // contacts -> contact_id

    for (const relatedId of relatedIds) {
      const relationshipId = this.generateRelationshipId();
      
      const relationshipData = {
        id: relationshipId,
        [moduleField]: recordId,
        [relatedField]: relatedId,
        date_modified: new Date().toISOString().slice(0, 19).replace('T', ' '),
        deleted: 0
      };

      const query = `INSERT INTO ${relationshipTable} SET ?`;
      await this.connection.execute(query, [relationshipData]);

      // Track created relationship
      this.trackCreatedRelationship(relationshipTable, relationshipId);
    }
  }

  /**
   * Create polymorphic relationship
   */
  async createPolymorphicRelationship(module, recordId, polymorphicData) {
    // Update the record with polymorphic data
    const query = `UPDATE ${module} SET parent_type = ?, parent_id = ? WHERE id = ?`;
    await this.connection.execute(query, [polymorphicData.type, polymorphicData.id, recordId]);
  }

  /**
   * Create optional relationship
   */
  async createOptionalRelationship(module, recordId, optionalModule, relatedId) {
    // This might involve updating foreign keys or creating junction table records
    // Implementation depends on the specific relationship type
    
    if (await this.recordExists(optionalModule, relatedId)) {
      console.log(`Creating optional relationship: ${module}(${recordId}) -> ${optionalModule}(${relatedId})`);
      // Relationship is already established via foreign key in the main record
    } else if (this.config.trackOrphanRecords) {
      this.orphanRecords.add(`${module}:${recordId} -> ${optionalModule}:${relatedId}`);
    }
  }

  /**
   * Validate record integrity
   */
  async validateRecordIntegrity(module, recordId) {
    const moduleConfig = this.relationships.get(module);
    
    // Check required parent relationships
    for (const parentModule of moduleConfig.parents || []) {
      const hasParent = await this.hasRequiredParent(module, recordId, parentModule);
      if (!hasParent) {
        throw new Error(`${module} record ${recordId} missing required parent in ${parentModule}`);
      }
    }

    // Check foreign key constraints
    await this.validateForeignKeys(module, recordId);

    return true;
  }

  /**
   * Check if record has required parent
   */
  async hasRequiredParent(module, recordId, parentModule) {
    const parentField = `${parentModule.slice(0, -1)}_id`;
    const [rows] = await this.connection.execute(
      `SELECT ${parentField} FROM ${module} WHERE id = ? AND deleted = 0`,
      [recordId]
    );

    if (rows.length === 0) return false;
    
    const parentId = rows[0][parentField];
    if (!parentId) return false;

    return await this.recordExists(parentModule, parentId);
  }

  /**
   * Validate foreign key constraints
   */
  async validateForeignKeys(module, recordId) {
    const [rows] = await this.connection.execute(
      `SELECT * FROM ${module} WHERE id = ? AND deleted = 0`,
      [recordId]
    );

    if (rows.length === 0) {
      throw new Error(`Record ${recordId} not found in ${module}`);
    }

    const record = rows[0];
    
    // Check all foreign key fields
    for (const [field, value] of Object.entries(record)) {
      if (field.endsWith('_id') && field !== 'id' && value) {
        const relatedModule = this.getForeignKeyModule(field);
        if (relatedModule && !(await this.recordExists(relatedModule, value))) {
          throw new Error(`Invalid foreign key: ${module}.${field} = ${value} (${relatedModule} record not found)`);
        }
      }
    }
  }

  /**
   * Delete record with cascade options
   */
  async deleteRecordWithRelationships(module, recordId, options = {}) {
    const {
      cascadeDelete = this.config.enableCascadeDelete,
      deleteOrphans = true,
      validateBeforeDelete = true
    } = options;

    if (validateBeforeDelete) {
      await this.validateRecordExists(module, recordId);
    }

    // Delete child relationships first
    if (cascadeDelete) {
      await this.cascadeDeleteChildren(module, recordId);
    }

    // Delete many-to-many relationships
    await this.deleteManyToManyRelationships(module, recordId);

    // Soft delete the main record
    await this.connection.execute(
      `UPDATE ${module} SET deleted = 1, date_modified = NOW() WHERE id = ?`,
      [recordId]
    );

    // Delete custom fields
    const moduleConfig = this.relationships.get(module);
    if (moduleConfig && moduleConfig.customFields) {
      await this.connection.execute(
        `DELETE FROM ${moduleConfig.customFields} WHERE id_c = ?`,
        [recordId]
      );
    }

    console.log(`Deleted ${module} record ${recordId} with relationships`);
  }

  /**
   * Cascade delete child records
   */
  async cascadeDeleteChildren(module, recordId) {
    const moduleConfig = this.relationships.get(module);
    
    if (moduleConfig.children) {
      for (const childModule of moduleConfig.children) {
        const childIds = await this.getChildRecords(module, recordId, childModule);
        
        for (const childId of childIds) {
          await this.deleteRecordWithRelationships(childModule, childId, {
            cascadeDelete: true,
            validateBeforeDelete: false
          });
        }
      }
    }
  }

  /**
   * Get child records for a parent
   */
  async getChildRecords(parentModule, parentId, childModule) {
    const parentField = `${parentModule.slice(0, -1)}_id`;
    
    const [rows] = await this.connection.execute(
      `SELECT id FROM ${childModule} WHERE ${parentField} = ? AND deleted = 0`,
      [parentId]
    );

    return rows.map(row => row.id);
  }

  /**
   * Delete many-to-many relationships
   */
  async deleteManyToManyRelationships(module, recordId) {
    const moduleConfig = this.relationships.get(module);
    
    if (moduleConfig.manyToMany) {
      for (const relatedModule of moduleConfig.manyToMany) {
        const relationshipTable = this.getRelationshipTableName(module, relatedModule);
        const moduleField = `${module.slice(0, -1)}_id`;
        
        await this.connection.execute(
          `DELETE FROM ${relationshipTable} WHERE ${moduleField} = ?`,
          [recordId]
        );
      }
    }
  }

  /**
   * Get relationship integrity report
   */
  async getRelationshipIntegrityReport() {
    const report = {
      orphanRecords: Array.from(this.orphanRecords),
      brokenForeignKeys: [],
      missingRelationships: [],
      circularReferences: []
    };

    // Check for broken foreign keys
    for (const [module, config] of this.relationships) {
      const brokenKeys = await this.findBrokenForeignKeys(module);
      if (brokenKeys.length > 0) {
        report.brokenForeignKeys.push({ module, brokenKeys });
      }
    }

    // Check for missing required relationships
    for (const [module, config] of this.relationships) {
      if (config.parents && config.parents.length > 0) {
        const missingParents = await this.findMissingRequiredParents(module, config.parents);
        if (missingParents.length > 0) {
          report.missingRelationships.push({ module, missingParents });
        }
      }
    }

    return report;
  }

  /**
   * Find broken foreign keys
   */
  async findBrokenForeignKeys(module) {
    const brokenKeys = [];
    const [records] = await this.connection.execute(
      `SELECT * FROM ${module} WHERE deleted = 0 LIMIT 1000`
    );

    for (const record of records) {
      for (const [field, value] of Object.entries(record)) {
        if (field.endsWith('_id') && field !== 'id' && value) {
          const relatedModule = this.getForeignKeyModule(field);
          if (relatedModule && !(await this.recordExists(relatedModule, value))) {
            brokenKeys.push({
              recordId: record.id,
              field,
              value,
              relatedModule
            });
          }
        }
      }
    }

    return brokenKeys;
  }

  /**
   * Find missing required parents
   */
  async findMissingRequiredParents(module, requiredParents) {
    const missing = [];

    for (const parentModule of requiredParents) {
      const parentField = `${parentModule.slice(0, -1)}_id`;
      const [orphans] = await this.connection.execute(`
        SELECT m.id, m.${parentField}
        FROM ${module} m
        LEFT JOIN ${parentModule} p ON m.${parentField} = p.id
        WHERE m.deleted = 0 
        AND (m.${parentField} IS NULL OR p.id IS NULL OR p.deleted = 1)
        LIMIT 100
      `);

      if (orphans.length > 0) {
        missing.push({
          parentModule,
          orphanCount: orphans.length,
          examples: orphans.slice(0, 5)
        });
      }
    }

    return missing;
  }

  // Utility methods

  recordExists(module, recordId) {
    return this.connection.execute(
      `SELECT 1 FROM ${module} WHERE id = ? AND deleted = 0`,
      [recordId]
    ).then(([rows]) => rows.length > 0);
  }

  validateRecordExists(module, recordId) {
    return this.recordExists(module, recordId).then(exists => {
      if (!exists) {
        throw new Error(`Record ${recordId} not found in ${module}`);
      }
    });
  }

  generateRelationshipId() {
    return `rel_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  }

  getRelationshipTableName(module1, module2) {
    // Sort alphabetically to ensure consistent table names
    const modules = [module1, module2].sort();
    return `${modules[0]}_${modules[1]}`;
  }

  getForeignKeyModule(fieldName) {
    const moduleMap = {
      'account_id': 'accounts',
      'contact_id': 'contacts',
      'deal_id': 'deals',
      'user_id': 'users',
      'assigned_user_id': 'users',
      'created_by': 'users',
      'modified_user_id': 'users',
      'document_id': 'documents',
      'task_id': 'tasks',
      'activity_id': 'activities'
    };
    
    return moduleMap[fieldName];
  }

  generateMinimalRecordData(module, id) {
    const baseData = {
      id,
      date_entered: new Date().toISOString().slice(0, 19).replace('T', ' '),
      date_modified: new Date().toISOString().slice(0, 19).replace('T', ' '),
      created_by: '1',
      modified_user_id: '1',
      deleted: 0
    };

    // Add module-specific required fields
    switch (module) {
      case 'accounts':
        return { ...baseData, name: `Auto-generated Account ${id}` };
      case 'contacts':
        return { ...baseData, first_name: 'Auto', last_name: `Generated ${id}` };
      case 'deals':
        return { ...baseData, name: `Auto-generated Deal ${id}`, amount: 0 };
      case 'users':
        return { ...baseData, user_name: `auto_user_${id}`, first_name: 'Auto', last_name: 'User' };
      default:
        return { ...baseData, name: `Auto-generated ${module} ${id}` };
    }
  }

  trackCreatedRecord(module, recordId) {
    if (!this.createdRelationships.has(module)) {
      this.createdRelationships.set(module, new Set());
    }
    this.createdRelationships.get(module).add(recordId);
  }

  trackCreatedRelationship(table, relationshipId) {
    if (!this.createdRelationships.has(table)) {
      this.createdRelationships.set(table, new Set());
    }
    this.createdRelationships.get(table).add(relationshipId);
  }

  /**
   * Cleanup all tracked relationships
   */
  async cleanupAllRelationships() {
    console.log('Cleaning up all tracked relationships...');
    
    for (const [table, ids] of this.createdRelationships) {
      if (ids.size > 0) {
        try {
          if (table.includes('_')) {
            // Relationship table
            await this.connection.execute(
              `DELETE FROM ${table} WHERE id IN (${Array.from(ids).map(() => '?').join(',')})`,
              Array.from(ids)
            );
          } else {
            // Regular table - soft delete
            await this.connection.execute(
              `UPDATE ${table} SET deleted = 1 WHERE id IN (${Array.from(ids).map(() => '?').join(',')})`,
              Array.from(ids)
            );
          }
          console.log(`Cleaned up ${ids.size} relationships in ${table}`);
        } catch (error) {
          console.error(`Failed to cleanup relationships in ${table}:`, error.message);
        }
      }
    }

    this.createdRelationships.clear();
    this.orphanRecords.clear();
  }
}

module.exports = DataRelationshipManager;