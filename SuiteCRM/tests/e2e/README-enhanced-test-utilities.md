# Enhanced Test Data Setup/Teardown Utilities

This document describes the comprehensive test data utilities built for SuiteCRM E2E testing. These utilities extend the existing `test-data-helper.js` with advanced features for performance testing, data relationships, isolation, and environment management.

## Architecture Overview

```
lib/
├── data/                           # Core data management utilities
│   ├── enhanced-test-data-manager.js      # Main enhanced data manager
│   ├── bulk-data-utilities.js             # High-performance bulk operations
│   ├── relationship-manager.js            # Data relationship management
│   ├── environment-seeder.js              # Environment seeding & cleanup
│   ├── state-verification-helpers.js      # Database state verification
│   └── test-isolation-manager.js          # Test data isolation
├── fixtures/                       # Playwright fixtures
│   └── enhanced-test-fixtures.js          # Enhanced test fixtures
└── config/                         # Configuration management
    └── test-environment-config.js         # Environment configuration
```

## Core Components

### 1. Enhanced Test Data Manager

**File:** `lib/data/enhanced-test-data-manager.js`

Main features:
- **Advanced data generation** with realistic relationships
- **Performance metrics** and operation tracking
- **Memory-efficient caching** for repeated operations
- **Test scenario creation** (complete-deal-lifecycle, pipeline-performance-test, etc.)
- **Data isolation** at test, suite, or global levels
- **Comprehensive cleanup** with verification

#### Usage Example:

```javascript
const manager = new EnhancedTestDataManager({
  testPrefix: 'MY_TEST_',
  isolationLevel: 'test',
  enableCaching: true,
  enableMetrics: true
});

await manager.initialize();

// Create deal with full validation and relationships
const deal = await manager.createTestData('deals', {
  name: 'Test Deal',
  amount: 100000,
  sales_stage: 'Negotiation'
}, {
  validateData: true,
  enableRelationships: true
});

// Create complete test scenario
const scenario = await manager.createTestScenario('complete-deal-lifecycle');

// Get performance metrics
const metrics = manager.getMetrics();

await manager.cleanup();
```

### 2. Bulk Data Utilities

**File:** `lib/data/bulk-data-utilities.js`

High-performance bulk data creation for performance testing:

- **Batch processing** with configurable batch sizes
- **Parallel execution** with concurrency control
- **Retry mechanisms** for failed operations
- **Progress tracking** and performance monitoring
- **Realistic data distribution** across pipeline stages

#### Usage Example:

```javascript
const bulkUtils = new BulkDataUtilities({
  maxBatchSize: 500,
  parallelBatches: 3,
  enableProgressBar: true
});

// Create large performance dataset
const dataset = await bulkUtils.createPerformanceDataset({
  accounts: 100,
  contactsPerAccount: 5,
  dealsPerAccount: 10,
  documentsPerDeal: 3,
  checklistsPerDeal: 8
});

// Get performance statistics
const stats = bulkUtils.getBulkStats();
console.log(`Created ${stats.totalRecordsCreated} records at ${stats.recordsPerSecond} records/sec`);
```

### 3. Data Relationship Manager

**File:** `lib/data/relationship-manager.js`

Manages complex relationships between modules:

- **Automatic relationship creation** (many-to-many, one-to-many, polymorphic)
- **Cascade delete operations** with dependency tracking
- **Relationship integrity verification**
- **Orphan record detection** and cleanup
- **Foreign key validation**

#### Usage Example:

```javascript
const relManager = new DataRelationshipManager(connection, {
  enableCascadeDelete: true,
  validateRelationships: true
});

// Create deal with all relationships
const dealId = await relManager.createRecordWithRelationships('deals', {
  name: 'Complex Deal',
  account_id: accountId,
  contact_ids: [contact1Id, contact2Id],
  assigned_user_id: userId
});

// Get integrity report
const report = await relManager.getRelationshipIntegrityReport();
console.log(`Found ${report.brokenForeignKeys.length} broken foreign keys`);
```

### 4. Environment Seeder

**File:** `lib/data/environment-seeder.js`

Comprehensive environment setup and cleanup:

- **Multiple seed profiles** (minimal, default, performance, stress, demo)
- **Phased seeding** with dependency management
- **Environment backups** before seeding
- **Verification** of seeded data integrity
- **Comprehensive cleanup** with multiple attempts

#### Usage Example:

```javascript
const seeder = new EnvironmentSeeder({
  environmentName: 'test',
  enableBackups: true
});

await seeder.initialize();

// Seed with performance profile
const report = await seeder.seedEnvironment({
  profile: 'performance',
  skipIfExists: true
});

// Cleanup environment
await seeder.cleanupEnvironment({
  verifyCleanup: true,
  maxAttempts: 3
});
```

#### Seed Profiles:

- **minimal**: 87 total records (5 accounts, 15 contacts, 10 deals, etc.)
- **default**: 565 total records (25 accounts, 100 contacts, 75 deals, etc.)
- **performance**: 2,100 total records (100 accounts, 500 contacts, 400 deals, etc.)
- **stress**: 15,000 total records (500 accounts, 2,500 contacts, 2,000 deals, etc.)
- **demo**: 1,050 total records with realistic scenarios

### 5. State Verification Helpers

**File:** `lib/data/state-verification-helpers.js`

Comprehensive database state verification:

- **Record count verification** with tolerance checking
- **Data integrity checks** (foreign keys, constraints, nulls)
- **Relationship verification** (many-to-many, parent-child, polymorphic)
- **Custom field integrity** checking
- **Business rule validation**
- **Performance metrics** collection

#### Usage Example:

```javascript
const verifier = new StateVerificationHelpers(connection, {
  enableDetailedReporting: true,
  enablePerformanceMetrics: true
});

// Verify expected database state
const result = await verifier.verifyDatabaseState({
  deals: 10,
  accounts: 5,
  contacts: 15
}, {
  includeRelationships: true,
  includeIntegrityChecks: true,
  strictMode: false
});

console.log(`Verification: ${result.overallStatus}`);
console.log(`Errors: ${result.errors.length}, Warnings: ${result.warnings.length}`);
```

### 6. Test Isolation Manager

**File:** `lib/data/test-isolation-manager.js`

Advanced test data isolation:

- **Multiple isolation levels** (test, suite, worker, global)
- **Namespace-based isolation** with automatic prefixing
- **Transaction-based isolation** (when supported)
- **Snapshot-based isolation** for complex scenarios
- **Resource locking** for concurrent test execution
- **Emergency cleanup** for orphaned contexts

#### Usage Example:

```javascript
const isolationManager = new TestIsolationManager(connection, {
  isolationLevel: 'test',
  enableNamespacing: true
});

// Initialize isolation context
const contextId = await isolationManager.initializeIsolationContext(testInfo);

// Create isolated data
const isolatedDeal = await isolationManager.createIsolatedData(contextId, 'deals', {
  name: 'Test Deal',
  amount: 50000
});

// Cleanup context
await isolationManager.cleanupIsolationContext(contextId);
```

## Enhanced Test Fixtures

**File:** `lib/fixtures/enhanced-test-fixtures.js`

Playwright fixtures that integrate all utilities:

### Available Fixtures:

- **enhancedDataManager**: Pre-configured enhanced data manager
- **bulkDataUtils**: Bulk data utilities for performance testing
- **relationshipManager**: Relationship management utilities
- **environmentSeeder**: Environment seeding capabilities
- **stateVerifier**: Database state verification
- **testIsolation**: Test data isolation
- **seededEnvironment**: Pre-seeded test environment
- **performanceTesting**: Performance benchmarking utilities
- **dealFixture**: Deal-specific test utilities
- **contactFixture**: Contact-specific test utilities
- **documentFixture**: Document management utilities
- **apiTestingFixture**: API testing utilities
- **visualRegressionFixture**: Visual regression testing
- **mobileTestingFixture**: Mobile device testing
- **accessibilityFixture**: Accessibility testing utilities

### Usage in Tests:

```javascript
const { test, expect } = require('../lib/fixtures/enhanced-test-fixtures');

test('Deal creation with relationships', async ({ 
  dealFixture, 
  relationshipManager,
  stateVerifier 
}) => {
  // Create deal with full relationships
  const { deal, account, contacts } = await dealFixture.createDealWithRelationships({
    name: 'Test Deal',
    amount: 100000
  });

  // Verify relationships
  const integrityReport = await relationshipManager.getRelationshipIntegrityReport();
  expect(integrityReport.brokenForeignKeys.length).toBe(0);

  // Verify database state
  const verificationResult = await stateVerifier.verifyDatabaseState({
    deals: 1,
    accounts: 1,
    contacts: 2
  });
  expect(verificationResult.overallStatus).toBe('PASSED');
});
```

## Configuration System

**File:** `lib/config/test-environment-config.js`

Environment-specific configuration management:

### Supported Environments:

- **local**: Development environment with debugging features
- **test**: Standard test environment
- **ci**: CI/CD environment with optimized settings
- **staging**: Staging environment testing
- **production**: Production environment (limited testing)

### Configuration Features:

- **Environment-specific overrides**
- **Secret management** (passwords, tokens)
- **Feature flags** (visual regression, accessibility, performance)
- **Database configuration**
- **Browser settings**
- **Test execution parameters**

### Usage:

```javascript
const config = require('../lib/config/test-environment-config');

// Get database configuration
const dbConfig = config.getDatabaseConfig();

// Get Playwright configuration
const playwrightConfig = config.getPlaywrightConfig();

// Check feature flags
if (config.isFeatureEnabled('performance')) {
  // Run performance tests
}

// Environment-specific settings
const isCI = config.get('ci.enabled');
const baseUrl = config.get('application.baseUrl');
```

## Performance Features

### Memory Management

- **Automatic memory monitoring** with usage tracking
- **Cache management** with automatic cleanup
- **Memory-efficient batch processing**
- **Garbage collection optimization**

### Performance Monitoring

- **Operation timing** with detailed metrics
- **Throughput measurement** (records/second)
- **Performance threshold monitoring**
- **Slow query detection**

### Benchmarking

```javascript
test('Performance benchmark', async ({ performanceTesting, bulkDataUtils }) => {
  const benchmark = await performanceTesting.benchmark(
    'bulk-deal-creation',
    async () => {
      return await bulkDataUtils.createBulkDeals(100);
    },
    5 // iterations
  );

  expect(benchmark.summary.performance.avg).toBeLessThan(10000); // < 10 seconds
  expect(benchmark.summary.successfulIterations).toBe(5);
});
```

## Test Data Scenarios

### Pre-built Scenarios:

1. **complete-deal-lifecycle**: Full deal with account, contacts, documents, checklists
2. **pipeline-performance-test**: Deals distributed across all pipeline stages
3. **duplicate-detection-test**: Exact and near-duplicate deals for testing
4. **relationship-stress-test**: Complex relationship network for testing
5. **at-risk-deals**: Deals with overdue activities for dashboard testing

### Custom Scenarios:

```javascript
// Create custom scenario
const customScenario = await enhancedDataManager.createTestScenario('custom-scenario', {
  accounts: 5,
  dealsPerAccount: 3,
  includeDocuments: true
});
```

## Best Practices

### 1. Test Isolation

- Use appropriate isolation level for your tests
- Always clean up test data after execution
- Use namespacing to avoid conflicts

### 2. Performance Testing

- Use bulk utilities for large datasets
- Monitor memory usage during tests
- Set appropriate performance thresholds

### 3. Data Relationships

- Validate relationships after creation
- Use cascade delete for cleanup
- Check integrity regularly

### 4. Environment Management

- Use appropriate seed profiles
- Verify seeded data before tests
- Clean up environments between runs

### 5. State Verification

- Verify expected state after operations
- Check for data integrity issues
- Monitor performance metrics

## Troubleshooting

### Common Issues:

1. **Memory Issues**: Increase batch sizes, enable cleanup verification
2. **Performance Problems**: Check slow query logs, optimize batch sizes
3. **Relationship Errors**: Verify foreign keys, check cascade settings
4. **Isolation Failures**: Check namespace conflicts, verify cleanup
5. **Seeding Problems**: Check database permissions, verify profiles

### Debug Tools:

- **Performance metrics**: Available in all utilities
- **Integrity reports**: Generated by relationship manager
- **Verification reports**: Detailed state verification
- **Cleanup verification**: Ensures complete cleanup

## Examples

See `examples/comprehensive-test-example.spec.js` for complete usage examples of all utilities.

## Migration from Existing Tests

To migrate from the existing `test-data-helper.js`:

1. Replace imports:
   ```javascript
   // Old
   const { DealsTestDataHelper } = require('./test-data-helper');
   
   // New
   const { test, expect } = require('../lib/fixtures/enhanced-test-fixtures');
   ```

2. Use fixtures in tests:
   ```javascript
   test('My test', async ({ enhancedDataManager, relationshipManager }) => {
     // Enhanced functionality available
   });
   ```

3. Update cleanup:
   ```javascript
   // Old
   await helper.cleanup();
   
   // New - automatic via fixtures
   // Cleanup handled automatically after each test
   ```

The enhanced utilities are fully backward compatible and provide significant performance and functionality improvements over the existing system.