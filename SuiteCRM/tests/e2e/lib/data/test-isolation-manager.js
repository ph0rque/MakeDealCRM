/**
 * Test Data Isolation Manager
 * Provides comprehensive test data isolation between test runs
 */

const crypto = require('crypto');

class TestIsolationManager {
  constructor(connection, config = {}) {
    this.connection = connection;
    this.config = {
      isolationLevel: config.isolationLevel || 'test', // 'test', 'suite', 'worker', 'global'
      enableNamespacing: config.enableNamespacing !== false,
      enableTransactions: config.enableTransactions !== false,
      enableSnapshots: config.enableSnapshots !== false,
      snapshotPath: config.snapshotPath || '/tmp/test-snapshots',
      maxConcurrentTests: config.maxConcurrentTests || 10,
      isolationTimeout: config.isolationTimeout || 30000,
      ...config
    };

    // Isolation tracking
    this.isolationContexts = new Map(); // contextId -> context data
    this.activeTransactions = new Map(); // contextId -> transaction
    this.namespaceMapping = new Map(); // contextId -> namespace prefix
    this.dataSnapshots = new Map(); // contextId -> snapshot data
    
    // Resource locks for concurrent access control
    this.resourceLocks = new Map(); // resource -> Set<contextId>
    this.lockQueue = new Map(); // resource -> Queue<{contextId, resolve, reject}>
    
    // Cleanup tracking
    this.cleanupQueue = [];
    this.orphanedContexts = new Set();
  }

  /**
   * Initialize isolation context for a test
   */
  async initializeIsolationContext(testInfo) {
    const contextId = this.generateContextId(testInfo);
    const namespace = this.generateNamespace(contextId);
    
    const context = {
      id: contextId,
      testInfo,
      namespace,
      isolationLevel: this.config.isolationLevel,
      startTime: Date.now(),
      createdResources: new Map(), // resourceType -> Set<resourceId>
      activeLocks: new Set(),
      transactionId: null,
      snapshotId: null,
      status: 'initializing'
    };

    this.isolationContexts.set(contextId, context);
    this.namespaceMapping.set(contextId, namespace);

    try {
      // Initialize based on isolation level
      switch (this.config.isolationLevel) {
        case 'transaction':
          await this.initializeTransactionIsolation(contextId);
          break;
        case 'snapshot':
          await this.initializeSnapshotIsolation(contextId);
          break;
        case 'namespace':
          await this.initializeNamespaceIsolation(contextId);
          break;
        case 'test':
        default:
          await this.initializeTestIsolation(contextId);
          break;
      }

      context.status = 'active';
      console.log(`🔒 Isolation context initialized: ${contextId} (${this.config.isolationLevel})`);
      
      return contextId;

    } catch (error) {
      context.status = 'failed';
      this.orphanedContexts.add(contextId);
      throw new Error(`Failed to initialize isolation context: ${error.message}`);
    }
  }

  /**
   * Create isolated test data within a context
   */
  async createIsolatedData(contextId, dataType, data, options = {}) {
    const context = this.isolationContexts.get(contextId);
    if (!context) {
      throw new Error(`Isolation context not found: ${contextId}`);
    }

    try {
      // Apply isolation to the data
      const isolatedData = await this.applyIsolation(contextId, dataType, data);
      
      // Acquire resource locks if needed
      const resourceKey = this.getResourceKey(dataType, isolatedData);
      await this.acquireResourceLock(contextId, resourceKey);

      // Create the data based on isolation level
      let result;
      switch (this.config.isolationLevel) {
        case 'transaction':
          result = await this.createDataInTransaction(contextId, dataType, isolatedData);
          break;
        case 'snapshot':
          result = await this.createDataWithSnapshot(contextId, dataType, isolatedData);
          break;
        default:
          result = await this.createDataWithNamespace(contextId, dataType, isolatedData);
          break;
      }

      // Track created resource
      this.trackCreatedResource(contextId, dataType, result.id);

      return result;

    } catch (error) {
      throw new Error(`Failed to create isolated data: ${error.message}`);
    }
  }

  /**
   * Cleanup isolation context
   */
  async cleanupIsolationContext(contextId, options = {}) {
    const {
      forceCleanup = false,
      verifyCleanup = true,
      timeout = this.config.isolationTimeout
    } = options;

    const context = this.isolationContexts.get(contextId);
    if (!context) {
      console.warn(`Isolation context not found for cleanup: ${contextId}`);
      return;
    }

    context.status = 'cleaning';
    const cleanupStartTime = Date.now();

    try {
      console.log(`🧹 Cleaning up isolation context: ${contextId}`);

      // Set cleanup timeout
      const cleanupPromise = this.performContextCleanup(contextId, forceCleanup);
      const timeoutPromise = new Promise((_, reject) => 
        setTimeout(() => reject(new Error('Cleanup timeout')), timeout)
      );

      await Promise.race([cleanupPromise, timeoutPromise]);

      // Verify cleanup if requested
      if (verifyCleanup && !forceCleanup) {
        await this.verifyContextCleanup(contextId);
      }

      // Release all locks
      await this.releaseAllLocks(contextId);

      // Mark context as cleaned
      context.status = 'cleaned';
      context.cleanupTime = Date.now() - cleanupStartTime;

      console.log(`✅ Isolation context cleaned: ${contextId} (${context.cleanupTime}ms)`);

    } catch (error) {
      context.status = 'cleanup_failed';
      this.orphanedContexts.add(contextId);
      
      console.error(`❌ Failed to cleanup isolation context ${contextId}: ${error.message}`);
      
      if (forceCleanup) {
        await this.forceCleanupContext(contextId);
      } else {
        throw error;
      }
    } finally {
      // Remove from active contexts
      this.isolationContexts.delete(contextId);
      this.namespaceMapping.delete(contextId);
    }
  }

  /**
   * Initialize transaction-based isolation
   */
  async initializeTransactionIsolation(contextId) {
    if (!this.config.enableTransactions) {
      throw new Error('Transaction isolation is disabled');
    }

    try {
      await this.connection.beginTransaction();
      const transactionId = `txn_${contextId}`;
      
      this.activeTransactions.set(contextId, transactionId);
      
      const context = this.isolationContexts.get(contextId);
      context.transactionId = transactionId;
      
      console.log(`🔄 Transaction isolation initialized: ${transactionId}`);
      
    } catch (error) {
      throw new Error(`Failed to initialize transaction isolation: ${error.message}`);
    }
  }

  /**
   * Initialize snapshot-based isolation
   */
  async initializeSnapshotIsolation(contextId) {
    if (!this.config.enableSnapshots) {
      throw new Error('Snapshot isolation is disabled');
    }

    try {
      const snapshotId = `snap_${contextId}`;
      const snapshot = await this.createDatabaseSnapshot(snapshotId);
      
      this.dataSnapshots.set(contextId, snapshot);
      
      const context = this.isolationContexts.get(contextId);
      context.snapshotId = snapshotId;
      
      console.log(`📸 Snapshot isolation initialized: ${snapshotId}`);
      
    } catch (error) {
      throw new Error(`Failed to initialize snapshot isolation: ${error.message}`);
    }
  }

  /**
   * Initialize namespace-based isolation
   */
  async initializeNamespaceIsolation(contextId) {
    const context = this.isolationContexts.get(contextId);
    
    // Namespace is already generated and stored
    console.log(`🏷️ Namespace isolation initialized: ${context.namespace}`);
  }

  /**
   * Initialize test-level isolation (default)
   */
  async initializeTestIsolation(contextId) {
    // Combination of namespace and resource tracking
    await this.initializeNamespaceIsolation(contextId);
    
    const context = this.isolationContexts.get(contextId);
    console.log(`🧪 Test isolation initialized: ${contextId}`);
  }

  /**
   * Apply isolation transformations to data
   */
  async applyIsolation(contextId, dataType, data) {
    const context = this.isolationContexts.get(contextId);
    const isolatedData = { ...data };

    // Apply namespace prefix to identifiable fields
    if (this.config.enableNamespacing) {
      const nameFields = this.getNameFields(dataType);
      
      for (const field of nameFields) {
        if (isolatedData[field] && !isolatedData[field].startsWith(context.namespace)) {
          isolatedData[field] = `${context.namespace}_${isolatedData[field]}`;
        }
      }
      
      // Add namespace to ID if not present
      if (isolatedData.id && !isolatedData.id.startsWith(context.namespace)) {
        isolatedData.id = `${context.namespace}_${isolatedData.id}`;
      } else if (!isolatedData.id) {
        isolatedData.id = this.generateIsolatedId(contextId, dataType);
      }
    }

    // Add isolation metadata
    isolatedData._isolation = {
      contextId,
      namespace: context.namespace,
      timestamp: Date.now()
    };

    return isolatedData;
  }

  /**
   * Create data within a transaction
   */
  async createDataInTransaction(contextId, dataType, data) {
    const transactionId = this.activeTransactions.get(contextId);
    if (!transactionId) {
      throw new Error(`No active transaction for context: ${contextId}`);
    }

    // Create data using the existing transaction
    const result = await this.insertData(dataType, data);
    
    return result;
  }

  /**
   * Create data with snapshot support
   */
  async createDataWithSnapshot(contextId, dataType, data) {
    const snapshot = this.dataSnapshots.get(contextId);
    if (!snapshot) {
      throw new Error(`No snapshot available for context: ${contextId}`);
    }

    // Create data and track for snapshot restoration
    const result = await this.insertData(dataType, data);
    
    // Add to snapshot tracking
    snapshot.createdData.push({
      dataType,
      id: result.id,
      timestamp: Date.now()
    });

    return result;
  }

  /**
   * Create data with namespace isolation
   */
  async createDataWithNamespace(contextId, dataType, data) {
    // Data already has namespace applied
    return await this.insertData(dataType, data);
  }

  /**
   * Perform context cleanup based on isolation level
   */
  async performContextCleanup(contextId, forceCleanup) {
    const context = this.isolationContexts.get(contextId);
    
    switch (this.config.isolationLevel) {
      case 'transaction':
        await this.cleanupTransactionIsolation(contextId, forceCleanup);
        break;
      case 'snapshot':
        await this.cleanupSnapshotIsolation(contextId, forceCleanup);
        break;
      default:
        await this.cleanupNamespaceIsolation(contextId, forceCleanup);
        break;
    }

    // Clean up created resources
    await this.cleanupCreatedResources(contextId);
  }

  /**
   * Cleanup transaction isolation
   */
  async cleanupTransactionIsolation(contextId, forceCleanup) {
    const transactionId = this.activeTransactions.get(contextId);
    if (!transactionId) {
      return;
    }

    try {
      if (forceCleanup) {
        await this.connection.rollback();
        console.log(`🔄 Transaction rolled back: ${transactionId}`);
      } else {
        await this.connection.rollback(); // Always rollback for test isolation
        console.log(`🔄 Transaction rolled back: ${transactionId}`);
      }
    } catch (error) {
      console.warn(`Failed to rollback transaction ${transactionId}: ${error.message}`);
    } finally {
      this.activeTransactions.delete(contextId);
    }
  }

  /**
   * Cleanup snapshot isolation
   */
  async cleanupSnapshotIsolation(contextId, forceCleanup) {
    const snapshot = this.dataSnapshots.get(contextId);
    if (!snapshot) {
      return;
    }

    try {
      if (forceCleanup) {
        // Force delete all created data
        for (const item of snapshot.createdData) {
          await this.forceDeleteData(item.dataType, item.id);
        }
      } else {
        // Restore from snapshot
        await this.restoreFromSnapshot(snapshot);
      }
      
      // Clean up snapshot files
      await this.deleteSnapshot(snapshot.id);
      
    } catch (error) {
      console.warn(`Failed to cleanup snapshot ${snapshot.id}: ${error.message}`);
    } finally {
      this.dataSnapshots.delete(contextId);
    }
  }

  /**
   * Cleanup namespace isolation
   */
  async cleanupNamespaceIsolation(contextId, forceCleanup) {
    const context = this.isolationContexts.get(contextId);
    if (!context) {
      return;
    }

    try {
      // Delete all data with this namespace
      const tables = ['deals', 'accounts', 'contacts', 'documents', 'checklists'];
      
      for (const table of tables) {
        const nameField = this.getMainNameField(table);
        const query = forceCleanup ? 
          `DELETE FROM ${table} WHERE ${nameField} LIKE ?` :
          `UPDATE ${table} SET deleted = 1 WHERE ${nameField} LIKE ?`;
        
        const [result] = await this.connection.execute(query, [`${context.namespace}_%`]);
        
        if (result.affectedRows > 0) {
          console.log(`🧹 Cleaned ${result.affectedRows} records from ${table}`);
        }
      }
      
    } catch (error) {
      console.warn(`Failed to cleanup namespace ${context.namespace}: ${error.message}`);
    }
  }

  /**
   * Acquire resource lock for concurrent access control
   */
  async acquireResourceLock(contextId, resourceKey, timeout = 10000) {
    return new Promise((resolve, reject) => {
      const existingLocks = this.resourceLocks.get(resourceKey) || new Set();
      
      // Check if resource is already locked by this context
      if (existingLocks.has(contextId)) {
        resolve(); // Already have the lock
        return;
      }

      // Check if resource is locked by another context
      if (existingLocks.size > 0) {
        // Add to queue
        if (!this.lockQueue.has(resourceKey)) {
          this.lockQueue.set(resourceKey, []);
        }
        
        const timeoutId = setTimeout(() => {
          reject(new Error(`Lock timeout for resource: ${resourceKey}`));
        }, timeout);
        
        this.lockQueue.get(resourceKey).push({
          contextId,
          resolve: () => {
            clearTimeout(timeoutId);
            resolve();
          },
          reject: (error) => {
            clearTimeout(timeoutId);
            reject(error);
          }
        });
        
        return;
      }

      // Acquire lock immediately
      existingLocks.add(contextId);
      this.resourceLocks.set(resourceKey, existingLocks);
      
      const context = this.isolationContexts.get(contextId);
      if (context) {
        context.activeLocks.add(resourceKey);
      }
      
      resolve();
    });
  }

  /**
   * Release resource lock
   */
  async releaseResourceLock(contextId, resourceKey) {
    const existingLocks = this.resourceLocks.get(resourceKey);
    if (!existingLocks || !existingLocks.has(contextId)) {
      return; // No lock to release
    }

    existingLocks.delete(contextId);
    
    if (existingLocks.size === 0) {
      this.resourceLocks.delete(resourceKey);
      
      // Process lock queue
      const queue = this.lockQueue.get(resourceKey);
      if (queue && queue.length > 0) {
        const next = queue.shift();
        
        // Grant lock to next in queue
        const newLocks = new Set([next.contextId]);
        this.resourceLocks.set(resourceKey, newLocks);
        
        const nextContext = this.isolationContexts.get(next.contextId);
        if (nextContext) {
          nextContext.activeLocks.add(resourceKey);
        }
        
        next.resolve();
      }
      
      if (queue && queue.length === 0) {
        this.lockQueue.delete(resourceKey);
      }
    }

    // Remove from context's active locks
    const context = this.isolationContexts.get(contextId);
    if (context) {
      context.activeLocks.delete(resourceKey);
    }
  }

  /**
   * Release all locks for a context
   */
  async releaseAllLocks(contextId) {
    const context = this.isolationContexts.get(contextId);
    if (!context || context.activeLocks.size === 0) {
      return;
    }

    const locks = Array.from(context.activeLocks);
    for (const resourceKey of locks) {
      await this.releaseResourceLock(contextId, resourceKey);
    }
  }

  /**
   * Verify context cleanup completion
   */
  async verifyContextCleanup(contextId) {
    const context = this.isolationContexts.get(contextId);
    if (!context) {
      return;
    }

    // Check for remaining data with this namespace
    const tables = ['deals', 'accounts', 'contacts', 'documents'];
    let remainingRecords = 0;

    for (const table of tables) {
      const nameField = this.getMainNameField(table);
      const [rows] = await this.connection.execute(
        `SELECT COUNT(*) as count FROM ${table} WHERE deleted = 0 AND ${nameField} LIKE ?`,
        [`${context.namespace}_%`]
      );
      
      remainingRecords += rows[0].count;
    }

    if (remainingRecords > 0) {
      throw new Error(`Cleanup verification failed: ${remainingRecords} records still exist for context ${contextId}`);
    }

    // Check for active locks
    if (context.activeLocks.size > 0) {
      throw new Error(`Cleanup verification failed: ${context.activeLocks.size} locks still active for context ${contextId}`);
    }
  }

  /**
   * Force cleanup for orphaned contexts
   */
  async forceCleanupOrphanedContexts() {
    console.log(`🧹 Force cleaning ${this.orphanedContexts.size} orphaned contexts...`);
    
    const cleanupPromises = Array.from(this.orphanedContexts).map(async (contextId) => {
      try {
        await this.forceCleanupContext(contextId);
        this.orphanedContexts.delete(contextId);
      } catch (error) {
        console.error(`Failed to force cleanup context ${contextId}: ${error.message}`);
      }
    });

    await Promise.allSettled(cleanupPromises);
  }

  /**
   * Force cleanup a specific context
   */
  async forceCleanupContext(contextId) {
    const context = this.isolationContexts.get(contextId);
    if (!context) {
      // Try cleanup by namespace if context exists in mapping
      const namespace = this.namespaceMapping.get(contextId);
      if (namespace) {
        await this.forceCleanupByNamespace(namespace);
      }
      return;
    }

    try {
      // Force cleanup based on isolation level
      await this.performContextCleanup(contextId, true);
      
      // Force release all locks
      await this.releaseAllLocks(contextId);
      
      console.log(`🔥 Force cleanup completed for context: ${contextId}`);
      
    } catch (error) {
      console.error(`Force cleanup failed for context ${contextId}: ${error.message}`);
    }
  }

  /**
   * Force cleanup by namespace
   */
  async forceCleanupByNamespace(namespace) {
    const tables = ['deals', 'accounts', 'contacts', 'documents', 'checklists'];
    
    for (const table of tables) {
      try {
        const nameField = this.getMainNameField(table);
        await this.connection.execute(
          `DELETE FROM ${table} WHERE ${nameField} LIKE ?`,
          [`${namespace}_%`]
        );
      } catch (error) {
        console.warn(`Failed to force cleanup ${table} for namespace ${namespace}: ${error.message}`);
      }
    }
  }

  // Utility methods

  generateContextId(testInfo) {
    const hash = crypto.createHash('md5')
      .update(`${testInfo.title}_${testInfo.file}_${Date.now()}_${Math.random()}`)
      .digest('hex')
      .substring(0, 8);
    
    return `ctx_${hash}`;
  }

  generateNamespace(contextId) {
    return `ISO_${contextId}`;
  }

  generateIsolatedId(contextId, dataType) {
    const context = this.isolationContexts.get(contextId);
    return `${context.namespace}_${dataType}_${Date.now()}_${Math.random().toString(36).substr(2, 6)}`;
  }

  getNameFields(dataType) {
    const nameFieldMap = {
      'deals': ['name'],
      'accounts': ['name'],
      'contacts': ['first_name', 'last_name'],
      'documents': ['document_name'],
      'checklists': ['name'],
      'users': ['user_name', 'first_name', 'last_name']
    };
    
    return nameFieldMap[dataType] || ['name'];
  }

  getMainNameField(table) {
    const mainFieldMap = {
      'deals': 'name',
      'accounts': 'name',
      'contacts': 'first_name',
      'documents': 'document_name',
      'checklists': 'name',
      'users': 'user_name'
    };
    
    return mainFieldMap[table] || 'name';
  }

  getResourceKey(dataType, data) {
    return `${dataType}:${data.name || data.first_name || data.document_name || data.id}`;
  }

  trackCreatedResource(contextId, dataType, resourceId) {
    const context = this.isolationContexts.get(contextId);
    if (!context) return;

    if (!context.createdResources.has(dataType)) {
      context.createdResources.set(dataType, new Set());
    }
    
    context.createdResources.get(dataType).add(resourceId);
  }

  async insertData(dataType, data) {
    const table = dataType;
    const columns = Object.keys(data).filter(key => !key.startsWith('_'));
    const values = columns.map(col => data[col]);
    const placeholders = columns.map(() => '?').join(',');
    
    const query = `INSERT INTO ${table} (${columns.join(',')}) VALUES (${placeholders})`;
    await this.connection.execute(query, values);
    
    return { id: data.id, ...data };
  }

  async forceDeleteData(dataType, id) {
    try {
      await this.connection.execute(`DELETE FROM ${dataType} WHERE id = ?`, [id]);
    } catch (error) {
      console.warn(`Failed to force delete ${dataType} ${id}: ${error.message}`);
    }
  }

  async cleanupCreatedResources(contextId) {
    const context = this.isolationContexts.get(contextId);
    if (!context || context.createdResources.size === 0) {
      return;
    }

    for (const [dataType, resourceIds] of context.createdResources) {
      if (resourceIds.size > 0) {
        try {
          const ids = Array.from(resourceIds);
          const placeholders = ids.map(() => '?').join(',');
          
          await this.connection.execute(
            `UPDATE ${dataType} SET deleted = 1 WHERE id IN (${placeholders})`,
            ids
          );
          
          console.log(`🧹 Cleaned ${ids.length} ${dataType} resources`);
        } catch (error) {
          console.warn(`Failed to cleanup ${dataType} resources: ${error.message}`);
        }
      }
    }

    context.createdResources.clear();
  }

  // Snapshot management (simplified implementation)
  async createDatabaseSnapshot(snapshotId) {
    // This would typically create a database snapshot or backup
    // For now, we'll track created data for later cleanup
    return {
      id: snapshotId,
      timestamp: Date.now(),
      createdData: []
    };
  }

  async restoreFromSnapshot(snapshot) {
    // This would restore database state from snapshot
    // For now, we'll just clean up the tracked created data
    for (const item of snapshot.createdData) {
      await this.forceDeleteData(item.dataType, item.id);
    }
  }

  async deleteSnapshot(snapshotId) {
    // Clean up snapshot files/data
    console.log(`📸 Snapshot deleted: ${snapshotId}`);
  }

  /**
   * Get isolation statistics
   */
  getIsolationStatistics() {
    return {
      activeContexts: this.isolationContexts.size,
      orphanedContexts: this.orphanedContexts.size,
      activeTransactions: this.activeTransactions.size,
      activeSnapshots: this.dataSnapshots.size,
      activeLocks: this.resourceLocks.size,
      queuedLocks: this.lockQueue.size,
      isolationLevel: this.config.isolationLevel
    };
  }

  /**
   * Get context information
   */
  getContextInfo(contextId) {
    const context = this.isolationContexts.get(contextId);
    if (!context) {
      return null;
    }

    return {
      id: context.id,
      namespace: context.namespace,
      status: context.status,
      isolationLevel: context.isolationLevel,
      startTime: context.startTime,
      duration: Date.now() - context.startTime,
      createdResourceCounts: Object.fromEntries(
        Array.from(context.createdResources.entries()).map(([type, ids]) => [type, ids.size])
      ),
      activeLocks: Array.from(context.activeLocks)
    };
  }

  /**
   * List all active contexts
   */
  listActiveContexts() {
    return Array.from(this.isolationContexts.keys()).map(contextId => this.getContextInfo(contextId));
  }

  /**
   * Emergency cleanup all contexts
   */
  async emergencyCleanup() {
    console.log('🚨 Emergency cleanup initiated...');
    
    const cleanupPromises = [];
    
    // Clean up all active contexts
    for (const contextId of this.isolationContexts.keys()) {
      cleanupPromises.push(this.forceCleanupContext(contextId));
    }
    
    // Clean up orphaned contexts
    cleanupPromises.push(this.forceCleanupOrphanedContexts());
    
    await Promise.allSettled(cleanupPromises);
    
    // Clear all tracking
    this.isolationContexts.clear();
    this.activeTransactions.clear();
    this.namespaceMapping.clear();
    this.dataSnapshots.clear();
    this.resourceLocks.clear();
    this.lockQueue.clear();
    this.orphanedContexts.clear();
    
    console.log('🚨 Emergency cleanup completed');
  }
}

module.exports = TestIsolationManager;