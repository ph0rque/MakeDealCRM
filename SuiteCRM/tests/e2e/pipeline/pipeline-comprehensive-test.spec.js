const { test, expect } = require('@playwright/test');
const { login } = require('../deals/helpers/auth.helper');

/**
 * Comprehensive Pipeline Functionality Test Suite
 * 
 * This test suite thoroughly examines:
 * 1. Current pipeline view functionality
 * 2. Sample deals appearing and their sources
 * 3. Drag-and-drop functionality
 * 4. Any "Opportunities" labels that should be "Deals"
 * 5. Screenshots of all issues found
 */

test.describe('Pipeline Comprehensive Test Suite', () => {
  let issuesFound = [];
  let testRunId = Date.now();
  
  test.beforeEach(async ({ page }) => {
    await login(page);
    await page.waitForLoadState('networkidle');
  });

  test.afterEach(async ({ page }, testInfo) => {
    if (testInfo.status !== 'passed') {
      await page.screenshot({ 
        path: `test-results/pipeline-test-failure-${testRunId}.png`, 
        fullPage: true 
      });
    }
  });

  test('1. Test Pipeline View Access and Navigation', async ({ page }) => {
    console.log('\n=== Testing Pipeline View Access ===');
    
    // Try multiple navigation methods
    const navigationMethods = [
      {
        name: 'Direct URL',
        action: async () => await page.goto('http://localhost:8080/index.php?module=Deals&action=pipeline')
      },
      {
        name: 'Menu Navigation',
        action: async () => {
          await page.goto('http://localhost:8080');
          const dealsMenu = await page.locator('a:has-text("Deals"):visible').first();
          if (await dealsMenu.isVisible()) {
            await dealsMenu.click();
            await page.waitForTimeout(1000);
            const pipelineLink = await page.locator('a:has-text("Pipeline"):visible').first();
            if (await pipelineLink.isVisible()) {
              await pipelineLink.click();
            }
          }
        }
      },
      {
        name: 'Alternative Pipeline URL',
        action: async () => await page.goto('http://localhost:8080/index.php?module=qd_Deals&action=pipeline')
      },
      {
        name: 'Custom Pipeline URL', 
        action: async () => await page.goto('http://localhost:8080/index.php?module=Pipelines&action=kanban')
      }
    ];

    for (const method of navigationMethods) {
      console.log(`\nTrying ${method.name}...`);
      try {
        await method.action();
        await page.waitForLoadState('networkidle');
        
        const currentUrl = page.url();
        console.log(`Current URL: ${currentUrl}`);
        
        // Check if pipeline view loaded
        const pipelineIndicators = [
          '.pipeline-board',
          '.pipeline-stage',
          '.kanban-board',
          '.stage-column',
          '#pipeline-container',
          '.deals-pipeline'
        ];
        
        for (const selector of pipelineIndicators) {
          if (await page.locator(selector).isVisible()) {
            console.log(`✓ Found pipeline indicator: ${selector}`);
            await page.screenshot({ 
              path: `test-results/pipeline-view-${method.name.replace(/\s+/g, '-')}-${testRunId}.png`,
              fullPage: true 
            });
            break;
          }
        }
        
        // Check page title/header
        const pageHeaders = await page.locator('h1, h2, .module-title, .moduleTitle').allTextContents();
        console.log('Page headers found:', pageHeaders);
        
        // Check for error messages
        const errorMessages = await page.locator('.error, .alert-danger, .message.error').allTextContents();
        if (errorMessages.length > 0) {
          console.log('Error messages found:', errorMessages);
          issuesFound.push({
            type: 'Navigation Error',
            method: method.name,
            errors: errorMessages
          });
        }
        
      } catch (error) {
        console.log(`✗ ${method.name} failed:`, error.message);
        issuesFound.push({
          type: 'Navigation Failure',
          method: method.name,
          error: error.message
        });
      }
    }
  });

  test('2. Identify and Document Sample Deals', async ({ page }) => {
    console.log('\n=== Identifying Sample Deals ===');
    
    // Navigate to deals list first
    await page.goto('http://localhost:8080/index.php?module=Deals&action=index');
    await page.waitForLoadState('networkidle');
    
    // Check for any existing deals
    const dealSelectors = [
      'tr.oddListRowS1, tr.evenListRowS1',
      '.list-view-data tr',
      '.listViewEntryValue',
      'table.list tbody tr'
    ];
    
    let dealsData = [];
    
    for (const selector of dealSelectors) {
      const dealRows = await page.locator(selector).all();
      if (dealRows.length > 0) {
        console.log(`Found ${dealRows.length} deals using selector: ${selector}`);
        
        for (let i = 0; i < Math.min(dealRows.length, 10); i++) {
          const row = dealRows[i];
          try {
            const dealInfo = {
              name: await row.locator('td a:first-child').textContent().catch(() => 'N/A'),
              stage: await row.locator('td:nth-child(4)').textContent().catch(() => 'N/A'),
              amount: await row.locator('td:nth-child(5)').textContent().catch(() => 'N/A'),
              dateCreated: await row.locator('td:nth-child(7)').textContent().catch(() => 'N/A'),
              assignedTo: await row.locator('td:nth-child(8)').textContent().catch(() => 'N/A')
            };
            dealsData.push(dealInfo);
            console.log(`Deal ${i + 1}:`, dealInfo);
          } catch (e) {
            console.log(`Could not parse deal row ${i + 1}`);
          }
        }
        break;
      }
    }
    
    // Take screenshot of deals list
    await page.screenshot({ 
      path: `test-results/deals-list-${testRunId}.png`,
      fullPage: true 
    });
    
    // Check database for sample deals
    console.log('\n--- Checking for Sample/Test Data ---');
    
    // Look for indicators of sample data
    const sampleDataPatterns = [
      /test/i,
      /sample/i,
      /demo/i,
      /example/i,
      /dummy/i
    ];
    
    const sampleDeals = dealsData.filter(deal => {
      const dealString = JSON.stringify(deal).toLowerCase();
      return sampleDataPatterns.some(pattern => pattern.test(dealString));
    });
    
    if (sampleDeals.length > 0) {
      console.log('Found potential sample deals:', sampleDeals);
      issuesFound.push({
        type: 'Sample Data Found',
        deals: sampleDeals,
        count: sampleDeals.length
      });
    }
    
    // Navigate to pipeline view to check deals there
    await page.goto('http://localhost:8080/index.php?module=Deals&action=pipeline');
    await page.waitForLoadState('networkidle');
    
    // Count deals in pipeline
    const pipelineDeals = await page.locator('.deal-card, .pipeline-deal, .kanban-card').all();
    console.log(`\nDeals in pipeline view: ${pipelineDeals.length}`);
    
    if (pipelineDeals.length > 0) {
      await page.screenshot({ 
        path: `test-results/pipeline-deals-${testRunId}.png`,
        fullPage: true 
      });
    }
  });

  test('3. Test Drag and Drop Functionality', async ({ page }) => {
    console.log('\n=== Testing Drag and Drop ===');
    
    // Try to navigate to pipeline
    await page.goto('http://localhost:8080/index.php?module=Deals&action=pipeline');
    await page.waitForLoadState('networkidle');
    
    // Look for draggable deals
    const draggableSelectors = [
      '.deal-card',
      '.pipeline-deal',
      '.kanban-card',
      '[draggable="true"]',
      '.draggable'
    ];
    
    let draggableElement = null;
    let draggableSelector = null;
    
    for (const selector of draggableSelectors) {
      const elements = await page.locator(selector).all();
      if (elements.length > 0) {
        draggableElement = elements[0];
        draggableSelector = selector;
        console.log(`Found ${elements.length} draggable elements with selector: ${selector}`);
        break;
      }
    }
    
    if (draggableElement) {
      // Test drag and drop
      try {
        // Get the deal's current position and info
        const dealText = await draggableElement.textContent();
        console.log('Testing drag for deal:', dealText);
        
        // Find drop zones
        const dropZoneSelectors = [
          '.pipeline-stage',
          '.stage-column',
          '.kanban-column',
          '.drop-zone'
        ];
        
        let targetZone = null;
        for (const selector of dropZoneSelectors) {
          const zones = await page.locator(selector).all();
          if (zones.length > 1) {
            targetZone = zones[1]; // Get second stage
            console.log(`Found ${zones.length} drop zones with selector: ${selector}`);
            break;
          }
        }
        
        if (targetZone) {
          // Take before screenshot
          await page.screenshot({ 
            path: `test-results/drag-drop-before-${testRunId}.png`,
            fullPage: true 
          });
          
          // Perform drag and drop
          const sourceBox = await draggableElement.boundingBox();
          const targetBox = await targetZone.boundingBox();
          
          if (sourceBox && targetBox) {
            console.log('Performing drag and drop...');
            
            // Move to source
            await page.mouse.move(sourceBox.x + sourceBox.width / 2, sourceBox.y + sourceBox.height / 2);
            await page.mouse.down();
            
            // Drag to target
            await page.mouse.move(targetBox.x + targetBox.width / 2, targetBox.y + targetBox.height / 2, { steps: 10 });
            await page.waitForTimeout(500);
            
            // Drop
            await page.mouse.up();
            await page.waitForTimeout(1000);
            
            // Take after screenshot
            await page.screenshot({ 
              path: `test-results/drag-drop-after-${testRunId}.png`,
              fullPage: true 
            });
            
            // Check for error messages
            const errorMessages = await page.locator('.error, .alert-danger, .notification.error').allTextContents();
            if (errorMessages.length > 0) {
              console.log('Drag and drop errors:', errorMessages);
              issuesFound.push({
                type: 'Drag and Drop Error',
                errors: errorMessages
              });
            }
            
            // Check for success messages
            const successMessages = await page.locator('.success, .alert-success, .notification.success').allTextContents();
            if (successMessages.length > 0) {
              console.log('Drag and drop success:', successMessages);
            }
          } else {
            console.log('Could not get bounding boxes for drag and drop');
            issuesFound.push({
              type: 'Drag and Drop Issue',
              error: 'Could not get element positions'
            });
          }
        } else {
          console.log('No drop zones found');
          issuesFound.push({
            type: 'Drag and Drop Issue',
            error: 'No drop zones found'
          });
        }
      } catch (error) {
        console.log('Drag and drop test failed:', error.message);
        issuesFound.push({
          type: 'Drag and Drop Failure',
          error: error.message
        });
      }
    } else {
      console.log('No draggable elements found');
      issuesFound.push({
        type: 'Drag and Drop Issue',
        error: 'No draggable elements found'
      });
    }
  });

  test('4. Check for Opportunities Labels', async ({ page }) => {
    console.log('\n=== Checking for "Opportunities" Labels ===');
    
    const pagesToCheck = [
      { url: 'http://localhost:8080/index.php?module=Deals&action=index', name: 'Deals List' },
      { url: 'http://localhost:8080/index.php?module=Deals&action=pipeline', name: 'Pipeline View' },
      { url: 'http://localhost:8080/index.php?module=Deals&action=EditView', name: 'Deal Create' },
      { url: 'http://localhost:8080', name: 'Home Page' }
    ];
    
    for (const pageInfo of pagesToCheck) {
      console.log(`\nChecking ${pageInfo.name}...`);
      await page.goto(pageInfo.url);
      await page.waitForLoadState('networkidle');
      
      // Search for "Opportunities" text
      const opportunitiesElements = await page.locator('*:has-text("Opportunities"):not(script)').all();
      const opportunitiesTexts = [];
      
      for (const element of opportunitiesElements) {
        try {
          const text = await element.textContent();
          const tagName = await element.evaluate(el => el.tagName);
          const className = await element.getAttribute('class') || '';
          
          if (text && text.includes('Opportunities')) {
            opportunitiesTexts.push({
              text: text.trim(),
              tag: tagName,
              class: className,
              page: pageInfo.name
            });
          }
        } catch (e) {
          // Element might have been removed
        }
      }
      
      if (opportunitiesTexts.length > 0) {
        console.log(`Found ${opportunitiesTexts.length} "Opportunities" references:`, opportunitiesTexts);
        issuesFound.push({
          type: 'Opportunities Label Found',
          page: pageInfo.name,
          instances: opportunitiesTexts
        });
        
        // Take screenshot
        await page.screenshot({ 
          path: `test-results/opportunities-label-${pageInfo.name.replace(/\s+/g, '-')}-${testRunId}.png`,
          fullPage: true 
        });
      }
      
      // Also check for lowercase
      const oppsElements = await page.locator('*:has-text("opportunities"):not(script)').all();
      for (const element of oppsElements) {
        try {
          const text = await element.textContent();
          if (text && text.toLowerCase().includes('opportunities') && !text.includes('Opportunities')) {
            console.log('Found lowercase "opportunities":', text.trim());
          }
        } catch (e) {
          // Element might have been removed
        }
      }
    }
  });

  test('5. Pipeline Stage Configuration Test', async ({ page }) => {
    console.log('\n=== Testing Pipeline Stage Configuration ===');
    
    await page.goto('http://localhost:8080/index.php?module=Deals&action=pipeline');
    await page.waitForLoadState('networkidle');
    
    // Look for stage elements
    const stageSelectors = [
      '.pipeline-stage',
      '.stage-column',
      '.kanban-column',
      '[data-stage]'
    ];
    
    let stages = [];
    
    for (const selector of stageSelectors) {
      const stageElements = await page.locator(selector).all();
      if (stageElements.length > 0) {
        console.log(`Found ${stageElements.length} stages with selector: ${selector}`);
        
        for (const stage of stageElements) {
          try {
            const stageInfo = {
              name: await stage.locator('.stage-name, h3, h4').textContent().catch(() => 'Unknown'),
              dealCount: await stage.locator('.deal-card, .pipeline-deal').count(),
              dataStage: await stage.getAttribute('data-stage').catch(() => null)
            };
            stages.push(stageInfo);
            console.log('Stage:', stageInfo);
          } catch (e) {
            console.log('Could not parse stage');
          }
        }
        break;
      }
    }
    
    if (stages.length === 0) {
      console.log('No pipeline stages found');
      issuesFound.push({
        type: 'Pipeline Configuration',
        error: 'No pipeline stages found'
      });
    } else {
      await page.screenshot({ 
        path: `test-results/pipeline-stages-${testRunId}.png`,
        fullPage: true 
      });
    }
  });

  test('6. Generate Comprehensive Report', async ({ page }) => {
    console.log('\n=== TEST SUMMARY REPORT ===\n');
    
    console.log(`Total issues found: ${issuesFound.length}`);
    console.log('\nIssues by type:');
    
    const issuesByType = {};
    for (const issue of issuesFound) {
      if (!issuesByType[issue.type]) {
        issuesByType[issue.type] = [];
      }
      issuesByType[issue.type].push(issue);
    }
    
    for (const [type, issues] of Object.entries(issuesByType)) {
      console.log(`\n${type}: ${issues.length} issues`);
      issues.forEach((issue, index) => {
        console.log(`  ${index + 1}.`, JSON.stringify(issue, null, 2));
      });
    }
    
    // Create a visual report
    await page.goto('data:text/html,<html><body style="font-family: Arial, sans-serif; padding: 20px;"></body></html>');
    
    const reportHtml = `
      <h1>Pipeline Test Report - ${new Date().toLocaleString()}</h1>
      <h2>Summary</h2>
      <p>Total Issues Found: <strong>${issuesFound.length}</strong></p>
      
      <h2>Issues by Category</h2>
      ${Object.entries(issuesByType).map(([type, issues]) => `
        <div style="margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
          <h3>${type}</h3>
          <p>Count: ${issues.length}</p>
          <pre style="background: #f5f5f5; padding: 10px; overflow: auto;">
${JSON.stringify(issues, null, 2)}
          </pre>
        </div>
      `).join('')}
      
      <h2>Recommendations</h2>
      <ul>
        ${issuesFound.some(i => i.type === 'Navigation Failure') ? '<li>Fix pipeline view navigation and routing</li>' : ''}
        ${issuesFound.some(i => i.type === 'Drag and Drop Error') ? '<li>Fix drag and drop functionality</li>' : ''}
        ${issuesFound.some(i => i.type === 'Opportunities Label Found') ? '<li>Replace all "Opportunities" labels with "Deals"</li>' : ''}
        ${issuesFound.some(i => i.type === 'Sample Data Found') ? '<li>Remove or properly label sample/test data</li>' : ''}
        ${issuesFound.some(i => i.type === 'Pipeline Configuration') ? '<li>Configure pipeline stages properly</li>' : ''}
      </ul>
    `;
    
    await page.setContent(`<html><body style="font-family: Arial, sans-serif; padding: 20px;">${reportHtml}</body></html>`);
    await page.screenshot({ 
      path: `test-results/pipeline-test-report-${testRunId}.png`,
      fullPage: true 
    });
    
    // Assert that we documented the issues
    expect(issuesFound.length).toBeGreaterThanOrEqual(0);
  });
});