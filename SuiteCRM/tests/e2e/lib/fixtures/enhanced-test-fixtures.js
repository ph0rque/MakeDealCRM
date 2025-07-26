/**
 * Enhanced Test Fixtures
 * Advanced fixture management for consistent test states
 * Extends the existing test-fixtures.js with comprehensive capabilities
 */

const { test: base } = require('@playwright/test');
const EnhancedTestDataManager = require('../data/enhanced-test-data-manager');
const BulkDataUtilities = require('../data/bulk-data-utilities');
const DataRelationshipManager = require('../data/relationship-manager');
const EnvironmentSeeder = require('../data/environment-seeder');
const StateVerificationHelpers = require('../data/state-verification-helpers');
const TestIsolationManager = require('../data/test-isolation-manager');
const fs = require('fs').promises;
const path = require('path');

/**
 * Enhanced test fixture with comprehensive data management
 */
exports.test = base.extend({
  // Enhanced data manager fixture
  enhancedDataManager: async ({ }, use, testInfo) => {
    const config = {
      testPrefix: 'E2E_ENHANCED_',
      isolationLevel: process.env.TEST_ISOLATION || 'test',
      enableCaching: true,
      enableMetrics: true
    };

    const manager = new EnhancedTestDataManager(config);
    await manager.initialize({ 
      suite: testInfo.project.name,
      title: testInfo.title,
      file: testInfo.file
    });

    await use(manager);
    
    // Cleanup and collect metrics
    const metrics = manager.getMetrics();
    await testInfo.attach('data-manager-metrics', {
      body: JSON.stringify(metrics, null, 2),
      contentType: 'application/json'
    });

    await manager.cleanup();
    await manager.disconnect();
  },

  // Bulk data utilities fixture
  bulkDataUtils: async ({ enhancedDataManager }, use, testInfo) => {
    const bulkUtils = new BulkDataUtilities({
      testPrefix: 'E2E_BULK_',
      maxBatchSize: 200,
      parallelBatches: 2,
      enableProgressBar: !process.env.CI
    });

    await bulkUtils.initialize();
    await use(bulkUtils);

    // Attach bulk operation statistics
    const stats = bulkUtils.getBulkStats();
    await testInfo.attach('bulk-operation-stats', {
      body: JSON.stringify(stats, null, 2),
      contentType: 'application/json'
    });

    await bulkUtils.cleanup();
    await bulkUtils.disconnect();
  },

  // Relationship manager fixture
  relationshipManager: async ({ enhancedDataManager }, use, testInfo) => {
    const relManager = new DataRelationshipManager(
      enhancedDataManager.connection,
      {
        enableCascadeDelete: true,
        validateRelationships: true,
        trackOrphanRecords: true
      }
    );

    await use(relManager);

    // Generate integrity report
    const integrityReport = await relManager.getRelationshipIntegrityReport();
    if (integrityReport.orphanRecords.length > 0 || integrityReport.brokenForeignKeys.length > 0) {
      await testInfo.attach('relationship-integrity-issues', {
        body: JSON.stringify(integrityReport, null, 2),
        contentType: 'application/json'
      });
    }

    await relManager.cleanupAllRelationships();
  },

  // Environment seeder fixture
  environmentSeeder: async ({ }, use, testInfo) => {
    const seeder = new EnvironmentSeeder({
      environmentName: process.env.NODE_ENV || 'test',
      enableBackups: process.env.ENABLE_BACKUPS === 'true',
      enableSeeding: process.env.ENABLE_SEEDING !== 'false'
    });

    await seeder.initialize();
    await use(seeder);

    await seeder.disconnect();
  },

  // State verification fixture
  stateVerifier: async ({ enhancedDataManager }, use, testInfo) => {
    const verifier = new StateVerificationHelpers(
      enhancedDataManager.connection,
      {
        enableDetailedReporting: true,
        enablePerformanceMetrics: true
      }
    );

    await use(verifier);

    // Attach verification report
    const report = verifier.generateVerificationReport();
    await testInfo.attach('state-verification-report', {
      body: JSON.stringify(report, null, 2),
      contentType: 'application/json'
    });
  },

  // Test isolation fixture
  testIsolation: async ({ enhancedDataManager }, use, testInfo) => {
    const isolationManager = new TestIsolationManager(
      enhancedDataManager.connection,
      {
        isolationLevel: process.env.TEST_ISOLATION || 'test',
        enableNamespacing: true,
        enableTransactions: process.env.DB_TRANSACTIONS === 'true'
      }
    );

    const contextId = await isolationManager.initializeIsolationContext(testInfo);
    
    await use({
      contextId,
      createIsolatedData: (dataType, data, options) => 
        isolationManager.createIsolatedData(contextId, dataType, data, options),
      getContextInfo: () => isolationManager.getContextInfo(contextId)
    });

    await isolationManager.cleanupIsolationContext(contextId);
  },

  // Pre-seeded environment fixture
  seededEnvironment: async ({ environmentSeeder }, use, testInfo) => {
    const profile = testInfo.tags?.includes('@performance') ? 'performance' :
                   testInfo.tags?.includes('@stress') ? 'stress' :
                   testInfo.tags?.includes('@demo') ? 'demo' : 'default';

    const seedReport = await environmentSeeder.seedEnvironment({ 
      profile,
      skipIfExists: true 
    });

    await use(seedReport);

    // Cleanup if this test created the seed
    if (!seedReport.skipped) {
      await environmentSeeder.cleanupEnvironment({ verifyCleanup: true });
    }
  },

  // Performance testing fixture
  performanceTesting: async ({ bulkDataUtils }, use, testInfo) => {
    const performanceData = {
      startTime: Date.now(),
      operations: [],
      metrics: {
        memory: [],
        timing: {}
      }
    };

    // Memory monitoring
    const memoryInterval = setInterval(() => {
      performanceData.metrics.memory.push({
        timestamp: Date.now(),
        ...process.memoryUsage()
      });
    }, 1000);

    const performanceUtils = {
      // Benchmark a function
      benchmark: async (name, operation, iterations = 1) => {
        const result = await bulkDataUtils.benchmarkOperation(name, operation, iterations);
        performanceData.operations.push(result);
        return result;
      },

      // Record timing
      recordTiming: (operation, duration) => {
        if (!performanceData.metrics.timing[operation]) {
          performanceData.metrics.timing[operation] = [];
        }
        performanceData.metrics.timing[operation].push(duration);
      },

      // Get current memory usage
      getMemoryUsage: () => process.memoryUsage(),

      // Create performance dataset
      createPerformanceDataset: (config) => bulkDataUtils.createPerformanceDataset(config)
    };

    await use(performanceUtils);

    clearInterval(memoryInterval);
    performanceData.endTime = Date.now();
    performanceData.totalDuration = performanceData.endTime - performanceData.startTime;

    // Attach performance report
    await testInfo.attach('performance-report', {
      body: JSON.stringify(performanceData, null, 2),
      contentType: 'application/json'
    });
  },

  // Deal-specific fixture
  dealFixture: async ({ enhancedDataManager, relationshipManager }, use) => {
    const dealUtils = {
      // Create deal with full relationships
      createDealWithRelationships: async (dealData = {}) => {
        // Create account first
        const account = await enhancedDataManager.createTestData('accounts', 
          enhancedDataManager.generateAccountData()
        );

        // Create contacts
        const contacts = [];
        for (let i = 0; i < 2; i++) {
          const contact = await enhancedDataManager.createTestData('contacts',
            enhancedDataManager.generateContactData({ account_id: account.id })
          );
          contacts.push(contact);
        }

        // Create deal with relationships
        const deal = await relationshipManager.createRecordWithRelationships('deals', {
          ...enhancedDataManager.generateDealData(),
          account_id: account.id,
          contact_ids: contacts.map(c => c.id),
          ...dealData
        });

        return { deal, account, contacts };
      },

      // Create pipeline scenario
      createPipelineScenario: async (dealsPerStage = 3) => {
        const stages = ['Prospecting', 'Qualification', 'Needs Analysis', 'Value Proposition', 'Negotiation', 'Closed Won'];
        const pipeline = {};

        for (const stage of stages) {
          pipeline[stage] = [];
          for (let i = 0; i < dealsPerStage; i++) {
            const { deal } = await dealUtils.createDealWithRelationships({
              sales_stage: stage,
              name: `${stage} Deal ${i + 1}`
            });
            pipeline[stage].push(deal);
          }
        }

        return pipeline;
      },

      // Create duplicate scenarios
      createDuplicateScenario: async () => {
        const account = await enhancedDataManager.createTestData('accounts',
          enhancedDataManager.generateAccountData({ name: 'Duplicate Test Corp' })
        );

        // Exact duplicate
        const deal1 = await enhancedDataManager.createTestData('deals',
          enhancedDataManager.generateDealData({
            name: 'Duplicate Deal Test',
            account_id: account.id,
            amount: 100000
          })
        );

        // Near duplicate
        const deal2 = await enhancedDataManager.createTestData('deals',
          enhancedDataManager.generateDealData({
            name: 'Duplicate Deal Testing',
            account_id: account.id,
            amount: 110000
          })
        );

        return { account, exactDuplicate: deal1, nearDuplicate: deal2 };
      }
    };

    await use(dealUtils);
  },

  // Contact-specific fixture
  contactFixture: async ({ enhancedDataManager }, use) => {
    const contactUtils = {
      // Create contact with account
      createContactWithAccount: async (contactData = {}, accountData = {}) => {
        const account = await enhancedDataManager.createTestData('accounts',
          enhancedDataManager.generateAccountData(accountData)
        );

        const contact = await enhancedDataManager.createTestData('contacts',
          enhancedDataManager.generateContactData({
            account_id: account.id,
            ...contactData
          })
        );

        return { contact, account };
      },

      // Create contact hierarchy
      createContactHierarchy: async (levels = 2, contactsPerLevel = 3) => {
        const hierarchy = {};
        
        // Create top-level account
        const rootAccount = await enhancedDataManager.createTestData('accounts',
          enhancedDataManager.generateAccountData({ name: 'Root Corporation' })
        );

        hierarchy.root = { account: rootAccount, contacts: [] };

        // Create contacts for root
        for (let i = 0; i < contactsPerLevel; i++) {
          const contact = await enhancedDataManager.createTestData('contacts',
            enhancedDataManager.generateContactData({
              account_id: rootAccount.id,
              title: i === 0 ? 'CEO' : `Executive ${i}`
            })
          );
          hierarchy.root.contacts.push(contact);
        }

        // Create sub-levels
        for (let level = 1; level < levels; level++) {
          hierarchy[`level${level}`] = [];
          
          for (let group = 0; group < 2; group++) {
            const subAccount = await enhancedDataManager.createTestData('accounts',
              enhancedDataManager.generateAccountData({
                name: `Sub Corp Level ${level} Group ${group}`,
                parent_id: rootAccount.id
              })
            );

            const subContacts = [];
            for (let i = 0; i < contactsPerLevel; i++) {
              const contact = await enhancedDataManager.createTestData('contacts',
                enhancedDataManager.generateContactData({
                  account_id: subAccount.id,
                  title: `Manager ${i}`
                })
              );
              subContacts.push(contact);
            }

            hierarchy[`level${level}`].push({ account: subAccount, contacts: subContacts });
          }
        }

        return hierarchy;
      }
    };

    await use(contactUtils);
  },

  // Document management fixture
  documentFixture: async ({ enhancedDataManager }, use) => {
    const documentUtils = {
      // Create document with deal relationship
      createDocumentWithDeal: async (documentData = {}, dealData = {}) => {
        const deal = await enhancedDataManager.createTestData('deals',
          enhancedDataManager.generateDealData(dealData)
        );

        const document = await enhancedDataManager.createTestData('documents',
          enhancedDataManager.generateDocumentData({
            deal_id: deal.id,
            ...documentData
          })
        );

        return { document, deal };
      },

      // Create document library
      createDocumentLibrary: async (categories = ['Legal', 'Financial', 'Technical']) => {
        const library = {};

        for (const category of categories) {
          library[category] = [];
          
          for (let i = 0; i < 5; i++) {
            const document = await enhancedDataManager.createTestData('documents',
              enhancedDataManager.generateDocumentData({
                document_name: `${category} Document ${i + 1}`,
                category_id: category.toLowerCase(),
                status_id: i % 2 === 0 ? 'Active' : 'Draft'
              })
            );
            library[category].push(document);
          }
        }

        return library;
      }
    };

    await use(documentUtils);
  },

  // API testing fixture
  apiTestingFixture: async ({ request, enhancedDataManager }, use) => {
    const apiUtils = {
      // Authenticated API client
      createAuthenticatedClient: async () => {
        // Login and get session
        const loginResponse = await request.post('/api/auth/login', {
          data: {
            username: process.env.ADMIN_USERNAME || 'admin',
            password: process.env.ADMIN_PASSWORD || 'admin123'
          }
        });

        const session = await loginResponse.json();
        
        return {
          get: (url, options = {}) => request.get(url, {
            ...options,
            headers: {
              'Authorization': `Bearer ${session.token}`,
              ...options.headers
            }
          }),
          post: (url, options = {}) => request.post(url, {
            ...options,
            headers: {
              'Authorization': `Bearer ${session.token}`,
              'Content-Type': 'application/json',
              ...options.headers
            }
          }),
          put: (url, options = {}) => request.put(url, {
            ...options,
            headers: {
              'Authorization': `Bearer ${session.token}`,
              'Content-Type': 'application/json',
              ...options.headers
            }
          }),
          delete: (url, options = {}) => request.delete(url, {
            ...options,
            headers: {
              'Authorization': `Bearer ${session.token}`,
              ...options.headers
            }
          })
        };
      },

      // Test data via API
      createDataViaAPI: async (module, data) => {
        const client = await apiUtils.createAuthenticatedClient();
        const response = await client.post(`/api/${module}`, { data });
        return await response.json();
      }
    };

    await use(apiUtils);
  },

  // Visual regression fixture
  visualRegressionFixture: async ({ page }, use, testInfo) => {
    const visualUtils = {
      // Take baseline screenshot
      takeBaseline: async (name, options = {}) => {
        const screenshotPath = path.join(
          testInfo.outputDir,
          `${name}-baseline.png`
        );
        
        await page.screenshot({
          path: screenshotPath,
          fullPage: true,
          ...options
        });

        return screenshotPath;
      },

      // Compare screenshots
      compareScreenshot: async (name, options = {}) => {
        const screenshotPath = path.join(
          testInfo.outputDir,
          `${name}-current.png`
        );

        await page.screenshot({
          path: screenshotPath,
          fullPage: true,
          ...options
        });

        // In a real implementation, you would compare with baseline
        // For now, just attach the screenshot
        await testInfo.attach(`${name}-screenshot`, {
          path: screenshotPath,
          contentType: 'image/png'
        });

        return screenshotPath;
      },

      // Take element screenshot
      takeElementScreenshot: async (selector, name, options = {}) => {
        const element = page.locator(selector);
        const screenshotPath = path.join(
          testInfo.outputDir,
          `${name}-element.png`
        );

        await element.screenshot({
          path: screenshotPath,
          ...options
        });

        await testInfo.attach(`${name}-element-screenshot`, {
          path: screenshotPath,
          contentType: 'image/png'
        });

        return screenshotPath;
      }
    };

    await use(visualUtils);
  },

  // Mobile testing fixture
  mobileTestingFixture: async ({ browser }, use, testInfo) => {
    const mobileContext = await browser.newContext({
      ...require('@playwright/test').devices['iPhone 12'],
      locale: 'en-US',
      timezoneId: 'America/New_York'
    });

    const mobilePage = await mobileContext.newPage();

    const mobileUtils = {
      page: mobilePage,
      context: mobileContext,
      
      // Simulate mobile gestures
      swipe: async (direction, distance = 100) => {
        const viewport = mobilePage.viewportSize();
        const startX = viewport.width / 2;
        const startY = viewport.height / 2;
        
        let endX = startX;
        let endY = startY;
        
        switch (direction) {
          case 'left':
            endX = startX - distance;
            break;
          case 'right':
            endX = startX + distance;
            break;
          case 'up':
            endY = startY - distance;
            break;
          case 'down':
            endY = startY + distance;
            break;
        }

        await mobilePage.touchscreen.tap(startX, startY);
        await mobilePage.mouse.move(startX, startY);
        await mobilePage.mouse.down();
        await mobilePage.mouse.move(endX, endY, { steps: 10 });
        await mobilePage.mouse.up();
      },

      // Test orientation changes
      rotateDevice: async (orientation = 'landscape') => {
        const viewport = mobilePage.viewportSize();
        await mobilePage.setViewportSize({
          width: orientation === 'landscape' ? viewport.height : viewport.width,
          height: orientation === 'landscape' ? viewport.width : viewport.height
        });
      }
    };

    await use(mobileUtils);

    await mobileContext.close();
  },

  // Accessibility testing fixture
  accessibilityFixture: async ({ page }, use) => {
    const a11yUtils = {
      // Check basic accessibility
      checkA11y: async (options = {}) => {
        // This would integrate with axe-core or similar tool
        // For now, we'll do basic checks
        const results = {
          violations: [],
          passes: [],
          warnings: []
        };

        // Check for alt attributes on images
        const images = await page.locator('img').all();
        for (const img of images) {
          const alt = await img.getAttribute('alt');
          if (!alt) {
            results.violations.push({
              rule: 'alt-text',
              element: await img.innerHTML(),
              message: 'Image missing alt attribute'
            });
          }
        }

        // Check for form labels
        const inputs = await page.locator('input[type="text"], input[type="email"], textarea').all();
        for (const input of inputs) {
          const id = await input.getAttribute('id');
          const ariaLabel = await input.getAttribute('aria-label');
          
          if (id) {
            const label = await page.locator(`label[for="${id}"]`).count();
            if (label === 0 && !ariaLabel) {
              results.violations.push({
                rule: 'form-labels',
                element: await input.innerHTML(),
                message: 'Form input missing label'
              });
            }
          }
        }

        return results;
      },

      // Test keyboard navigation
      testKeyboardNavigation: async () => {
        // Test tab navigation
        const focusableElements = await page.locator(
          'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        ).all();

        const navigationResults = [];
        
        for (let i = 0; i < Math.min(focusableElements.length, 10); i++) {
          await page.keyboard.press('Tab');
          const focusedElement = await page.evaluate(() => document.activeElement.tagName);
          navigationResults.push({
            step: i + 1,
            focusedElement
          });
        }

        return navigationResults;
      }
    };

    await use(a11yUtils);
  }
});

// Re-export expect
exports.expect = base.expect;

// Enhanced test hooks with comprehensive setup/teardown
exports.test.beforeEach(async ({ page, testIsolation }, testInfo) => {
  // Clear browser state
  await page.context().clearCookies();
  await page.evaluate(() => {
    localStorage.clear();
    sessionStorage.clear();
  });

  // Set test context headers
  await page.setExtraHTTPHeaders({
    'X-Test-Context': testIsolation?.contextId || 'unknown',
    'X-Test-Name': testInfo.title,
    'X-Test-File': testInfo.file
  });

  console.log(`🧪 Test started: ${testInfo.title} (Context: ${testIsolation?.contextId || 'N/A'})`);
});

exports.test.afterEach(async ({ page, stateVerifier }, testInfo) => {
  // Capture final page state on failure
  if (testInfo.status !== 'passed') {
    try {
      // Take final screenshot
      const failureScreenshot = path.join(testInfo.outputDir, 'failure-final-state.png');
      await page.screenshot({ 
        path: failureScreenshot, 
        fullPage: true 
      });

      // Capture console logs
      const logs = await page.evaluate(() => {
        return window.__testLogs || [];
      });

      if (logs.length > 0) {
        await testInfo.attach('console-logs', {
          body: JSON.stringify(logs, null, 2),
          contentType: 'application/json'
        });
      }

      // Capture network requests if available
      const requests = await page.evaluate(() => {
        return window.__testRequests || [];
      });

      if (requests.length > 0) {
        await testInfo.attach('network-requests', {
          body: JSON.stringify(requests, null, 2),
          contentType: 'application/json'
        });
      }

    } catch (error) {
      console.warn('Failed to capture failure state:', error.message);
    }
  }

  // Log test completion
  const duration = Date.now() - (testInfo.startTime || Date.now());
  console.log(`${testInfo.status === 'passed' ? '✅' : '❌'} Test completed: ${testInfo.title} (${duration}ms)`);
});

// Global test setup
exports.test.beforeAll(async () => {
  console.log('🚀 Enhanced test suite starting...');
  
  // Global setup can be added here
  // e.g., database migrations, service startup, etc.
});

exports.test.afterAll(async () => {
  console.log('🏁 Enhanced test suite completed');
  
  // Global cleanup can be added here
  // e.g., service shutdown, cleanup orphaned data, etc.
});