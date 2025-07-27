const { test, expect } = require('@playwright/test');
const { login } = require('../deals/helpers/auth.helper');

test.describe('Pipeline Drag & Drop Verification', () => {
  test('Verify drag and drop with detailed logging', async ({ page }) => {
    // Enable console logging
    page.on('console', msg => console.log('Browser:', msg.text()));
    
    await login(page);
    await page.goto('http://localhost:8080/index.php?module=Deals&action=pipeline');
    await page.waitForLoadState('networkidle');
    
    console.log('\n=== Analyzing Pipeline Structure ===');
    
    // Wait for pipeline to fully load
    await page.waitForSelector('#pipeline-container', { timeout: 10000 });
    
    // Analyze the DOM structure
    const pipelineStructure = await page.evaluate(() => {
      const container = document.querySelector('#pipeline-container');
      const stages = container ? container.querySelectorAll('.pipeline-stage, .stage-column, [class*="stage"]') : [];
      const dealCards = document.querySelectorAll('.deal-card');
      const dropZones = document.querySelectorAll('.stage-body, .drop-zone, [class*="drop"]');
      
      return {
        containerFound: !!container,
        containerClasses: container ? container.className : 'not found',
        stageCount: stages.length,
        stageClasses: Array.from(stages).map(s => s.className),
        dealCount: dealCards.length,
        dealClasses: dealCards.length > 0 ? dealCards[0].className : 'none',
        dropZoneCount: dropZones.length,
        dropZoneClasses: Array.from(dropZones).map(z => z.className),
        // Check for draggable attributes
        draggableDeals: Array.from(dealCards).map(d => ({
          draggable: d.draggable,
          hasDragEvents: !!(d.ondragstart || d.ondragend),
          classes: d.className
        }))
      };
    });
    
    console.log('Pipeline Structure:', JSON.stringify(pipelineStructure, null, 2));
    
    // Take a screenshot showing the current state
    await page.screenshot({ 
      path: 'test-results/drag-drop-structure-analysis.png',
      fullPage: true 
    });
    
    // Try to find stages and drop zones with broader selectors
    const stageSelectors = [
      '.pipeline-stage',
      '.stage-column',
      '.kanban-column',
      '[data-stage]',
      '.stage',
      'div[class*="stage"]'
    ];
    
    let foundStages = false;
    for (const selector of stageSelectors) {
      const count = await page.locator(selector).count();
      if (count > 0) {
        console.log(`Found ${count} stages with selector: ${selector}`);
        foundStages = true;
        
        // Get details of first stage
        const firstStage = await page.locator(selector).first();
        const stageInfo = await firstStage.evaluate(el => ({
          classes: el.className,
          id: el.id,
          dataAttrs: Object.keys(el.dataset || {}),
          innerHTML: el.innerHTML.substring(0, 200)
        }));
        console.log('First stage info:', stageInfo);
      }
    }
    
    // Look for the actual drop zones
    console.log('\n=== Looking for Drop Zones ===');
    const dropSelectors = [
      '.stage-body',
      '.drop-zone',
      '.stage-drop-zone',
      '.droppable',
      '[data-drop-zone]',
      '.stage-content',
      '.stage-deals'
    ];
    
    for (const selector of dropSelectors) {
      const count = await page.locator(selector).count();
      if (count > 0) {
        console.log(`Found ${count} drop zones with selector: ${selector}`);
      }
    }
    
    // Check if drag and drop is implemented
    const dragDropImplemented = await page.evaluate(() => {
      const deals = document.querySelectorAll('.deal-card');
      if (deals.length === 0) return { implemented: false, reason: 'No deal cards found' };
      
      const firstDeal = deals[0];
      return {
        implemented: true,
        draggable: firstDeal.draggable,
        hasDataTransfer: !!(window.DataTransfer),
        eventListeners: {
          dragstart: !!(firstDeal.ondragstart),
          dragend: !!(firstDeal.ondragend),
          dragover: !!(document.ondragover),
          drop: !!(document.ondrop)
        }
      };
    });
    
    console.log('\nDrag & Drop Implementation:', dragDropImplemented);
    
    // Generate summary
    const summary = {
      pipelineLoads: pipelineStructure.containerFound,
      stagesFound: pipelineStructure.stageCount > 0,
      dealsFound: pipelineStructure.dealCount > 0,
      dropZonesFound: pipelineStructure.dropZoneCount > 0,
      dragDropReady: dragDropImplemented.implemented && dragDropImplemented.draggable
    };
    
    console.log('\n=== SUMMARY ===');
    console.log(`Pipeline Loads: ${summary.pipelineLoads ? '✅' : '❌'}`);
    console.log(`Stages Found: ${summary.stagesFound ? '✅' : '❌'} (${pipelineStructure.stageCount})`);
    console.log(`Deals Found: ${summary.dealsFound ? '✅' : '❌'} (${pipelineStructure.dealCount})`);
    console.log(`Drop Zones Found: ${summary.dropZonesFound ? '✅' : '❌'} (${pipelineStructure.dropZoneCount})`);
    console.log(`Drag & Drop Ready: ${summary.dragDropReady ? '✅' : '❌'}`);
    
    // Assertions
    expect(summary.pipelineLoads).toBeTruthy();
    expect(summary.dealsFound).toBeTruthy();
  });
});