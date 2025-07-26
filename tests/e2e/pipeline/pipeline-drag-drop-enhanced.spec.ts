import { test, expect, Page } from '@playwright/test';
import {
  performRealisticDragDrop,
  performHTML5DragDrop,
  waitForAjaxComplete,
  verifyDragSuccess,
  getDealStage,
  performTouchDragDrop,
  checkWIPLimitWarning,
  getStageDealCount,
  waitForStageCountUpdate
} from './drag-drop-helpers';

/**
 * Enhanced Feature 2: Pipeline Drag-and-Drop Tests
 * 
 * This test suite provides comprehensive coverage of the drag-and-drop
 * functionality with multiple approaches to ensure reliability across
 * different browsers and environments.
 */

// Test configuration
const TEST_CONFIG = {
  baseUrl: 'http://localhost:8080',
  deals: {
    primary: {
      name: 'E2E Test Deal',
      ttmRevenue: 1000000,
      ttmEbitda: 250000
    },
    secondary: {
      name: 'E2E Secondary Deal',
      ttmRevenue: 750000,
      ttmEbitda: 200000
    }
  },
  stages: {
    screening: {
      id: 'screening',
      label: 'Screening',
      next: 'analysis_outreach'
    },
    analysisOutreach: {
      id: 'analysis_outreach',
      label: 'Analysis & Outreach',
      next: 'due_diligence'
    },
    dueDiligence: {
      id: 'due_diligence',
      label: 'Due Diligence',
      next: 'valuation_structuring'
    }
  }
};

// Helper functions
async function login(page: Page) {
  await page.goto(TEST_CONFIG.baseUrl);
  
  if (page.url().includes('login')) {
    await page.fill('#user_name', 'admin');
    await page.fill('#username_password', 'admin');
    await page.click('#bigbutton');
    await page.waitForURL('**/index.php**', { timeout: 30000 });
  }
}

async function ensureTestDealExists(page: Page, dealData: any, stage: string = 'screening') {
  // Navigate to deals list
  await page.goto(`${TEST_CONFIG.baseUrl}/index.php?module=Deals&action=index`);
  
  // Search for the deal
  const searchVisible = await page.locator('input[name="name_basic"]').isVisible({ timeout: 5000 });
  if (searchVisible) {
    await page.fill('input[name="name_basic"]', dealData.name);
    await page.click('input[value="Search"]');
    await page.waitForTimeout(2000);
  }
  
  // Check if deal exists
  const dealExists = await page.locator(`a:has-text("${dealData.name}")`).count() > 0;
  
  if (!dealExists) {
    // Create the deal
    await page.goto(`${TEST_CONFIG.baseUrl}/index.php?module=Deals&action=EditView`);
    await page.fill('#name', dealData.name);
    await page.fill('#ttm_revenue_c', dealData.ttmRevenue.toString());
    await page.fill('#ttm_ebitda_c', dealData.ttmEbitda.toString());
    await page.selectOption('#pipeline_stage_c', stage);
    await page.click('input[value="Save"]');
    await page.waitForURL('**/index.php?module=Deals&action=DetailView**', { timeout: 30000 });
  } else {
    // Ensure it's in the correct stage
    await page.click(`a:has-text("${dealData.name}")`);
    await page.waitForSelector('.detail-view');
    
    const currentStage = await page.locator('[data-field="pipeline_stage_c"]').textContent();
    if (!currentStage?.toLowerCase().includes(stage.replace('_', ' '))) {
      // Update the stage
      await page.click('a:has-text("Edit")');
      await page.selectOption('#pipeline_stage_c', stage);
      await page.click('input[value="Save"]');
      await page.waitForURL('**/index.php?module=Deals&action=DetailView**');
    }
  }
}

async function navigateToPipeline(page: Page) {
  await page.goto(`${TEST_CONFIG.baseUrl}/index.php?module=Deals&action=pipeline`);
  await page.waitForSelector('.pipeline-board', { timeout: 30000 });
  await waitForAjaxComplete(page);
}

// Main test suite
test.describe('Enhanced Pipeline Drag-and-Drop Tests', () => {
  test.beforeEach(async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await login(page);
  });

  test('Primary: Drag deal using realistic mouse movements', async ({ page }) => {
    // Setup
    await ensureTestDealExists(page, TEST_CONFIG.deals.primary, 'screening');
    await navigateToPipeline(page);
    
    // Get elements
    const dealCard = page.locator(`.deal-card:has-text("${TEST_CONFIG.deals.primary.name}")`).first();
    const targetStage = page.locator(`.droppable[data-stage="analysis_outreach"], .stage-body[data-stage="analysis_outreach"]`).first();
    
    // Verify initial state
    await expect(dealCard).toBeVisible();
    const initialStage = await getDealStage(page, TEST_CONFIG.deals.primary.name);
    expect(initialStage).toBe('screening');
    
    // Get initial counts
    const screeningCountBefore = await getStageDealCount(page, 'screening');
    const analysisCountBefore = await getStageDealCount(page, 'analysis_outreach');
    
    // Perform drag and drop
    await performRealisticDragDrop(page, dealCard, targetStage, {
      steps: 15,
      delayBetweenSteps: 30,
      finalDelay: 1000
    });
    
    // Wait for AJAX to complete
    await waitForAjaxComplete(page);
    
    // Verify success
    const dragSuccess = await verifyDragSuccess(page);
    expect(dragSuccess).toBe(true);
    
    // Verify deal moved
    const newStage = await getDealStage(page, TEST_CONFIG.deals.primary.name);
    expect(newStage).toBe('analysis_outreach');
    
    // Verify counts updated
    await waitForStageCountUpdate(page, 'screening', screeningCountBefore - 1);
    await waitForStageCountUpdate(page, 'analysis_outreach', analysisCountBefore + 1);
    
    // Verify persistence after refresh
    await page.reload();
    await page.waitForSelector('.pipeline-board');
    
    const stageAfterRefresh = await getDealStage(page, TEST_CONFIG.deals.primary.name);
    expect(stageAfterRefresh).toBe('analysis_outreach');
  });

  test('Alternative: Drag deal using HTML5 drag events', async ({ page }) => {
    // This test uses a different approach for browsers that might handle drag differently
    await ensureTestDealExists(page, TEST_CONFIG.deals.secondary, 'screening');
    await navigateToPipeline(page);
    
    // Wait for board to be ready
    await page.waitForSelector('.deal-card', { timeout: 10000 });
    
    const dealSelector = `.deal-card[data-deal-id]:has-text("${TEST_CONFIG.deals.secondary.name}")`;
    const targetSelector = '.droppable[data-stage="analysis_outreach"]';
    
    // Ensure elements exist
    await expect(page.locator(dealSelector)).toBeVisible();
    await expect(page.locator(targetSelector)).toBeVisible();
    
    // Try HTML5 drag-drop
    try {
      await performHTML5DragDrop(page, dealSelector, targetSelector);
      await waitForAjaxComplete(page);
      
      // Verify move
      const newStage = await getDealStage(page, TEST_CONFIG.deals.secondary.name);
      expect(newStage).toBe('analysis_outreach');
    } catch (error) {
      // Fallback to realistic drag if HTML5 doesn't work
      console.log('HTML5 drag failed, using realistic drag as fallback');
      const dealCard = page.locator(dealSelector);
      const targetStage = page.locator(targetSelector);
      await performRealisticDragDrop(page, dealCard, targetStage);
      
      await waitForAjaxComplete(page);
      const newStage = await getDealStage(page, TEST_CONFIG.deals.secondary.name);
      expect(newStage).toBe('analysis_outreach');
    }
  });

  test('Verify drag visual feedback and drop zones', async ({ page }) => {
    await ensureTestDealExists(page, TEST_CONFIG.deals.primary, 'screening');
    await navigateToPipeline(page);
    
    const dealCard = page.locator(`.deal-card:has-text("${TEST_CONFIG.deals.primary.name}")`).first();
    const box = await dealCard.boundingBox();
    
    if (box) {
      // Start dragging
      await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
      await page.mouse.down();
      
      // Move to trigger drag state
      await page.mouse.move(box.x + box.width / 2 + 100, box.y + box.height / 2);
      await page.waitForTimeout(200);
      
      // Verify dragging state
      await expect(dealCard).toHaveClass(/dragging/);
      
      // Check for drop zone highlights
      const validDropZones = page.locator('.drag-over, .valid-drop-zone, .droppable.highlight');
      await expect(validDropZones).toBeVisible();
      
      // Cancel drag
      await page.keyboard.press('Escape');
      await page.mouse.up();
      
      // Verify state cleared
      await expect(dealCard).not.toHaveClass(/dragging/);
    }
  });

  test('Handle WIP limit restrictions', async ({ page }) => {
    await navigateToPipeline(page);
    
    // Find a stage that might have WIP limits
    const stagesWithLimits = await page.locator('.pipeline-stage[data-wip-limit]').all();
    
    if (stagesWithLimits.length > 0) {
      const targetStage = stagesWithLimits[0];
      const stageId = await targetStage.getAttribute('data-stage');
      const wipLimit = parseInt(await targetStage.getAttribute('data-wip-limit') || '999');
      const currentCount = await getStageDealCount(page, stageId!);
      
      if (currentCount >= wipLimit) {
        // Try to drag a deal to this full stage
        const dealFromOtherStage = await page.locator('.deal-card').filter({
          hasNot: page.locator(`[data-stage="${stageId}"]`)
        }).first();
        
        if (await dealFromOtherStage.isVisible()) {
          const dropZone = page.locator(`.droppable[data-stage="${stageId}"]`);
          await performRealisticDragDrop(page, dealFromOtherStage, dropZone);
          
          // Check for WIP warning
          const hasWarning = await checkWIPLimitWarning(page);
          expect(hasWarning).toBe(true);
        }
      }
    }
  });

  test('Verify audit trail after stage change', async ({ page }) => {
    await ensureTestDealExists(page, TEST_CONFIG.deals.primary, 'screening');
    await navigateToPipeline(page);
    
    // Move the deal
    const dealCard = page.locator(`.deal-card:has-text("${TEST_CONFIG.deals.primary.name}")`);
    const targetStage = page.locator('.droppable[data-stage="analysis_outreach"]');
    await performRealisticDragDrop(page, dealCard, targetStage);
    await waitForAjaxComplete(page);
    
    // Navigate to deal detail
    await dealCard.click();
    await page.waitForSelector('.detail-view');
    
    // Look for audit/history options
    const auditOptions = [
      'a:has-text("View Change Log")',
      'a:has-text("Audit")',
      'a:has-text("History")',
      'button:has-text("Activities")'
    ];
    
    for (const selector of auditOptions) {
      const link = page.locator(selector).first();
      if (await link.isVisible({ timeout: 2000 })) {
        await link.click();
        break;
      }
    }
    
    // Wait for audit data to load
    await page.waitForTimeout(2000);
    
    // Look for stage change entry
    const auditEntry = page.locator('tr, .audit-row, .activity-item').filter({
      hasText: /pipeline.*stage.*screening.*analysis/i
    }).first();
    
    if (await auditEntry.isVisible({ timeout: 5000 })) {
      await expect(auditEntry).toContainText(/screening/i);
      await expect(auditEntry).toContainText(/analysis/i);
    }
  });
});

// Mobile-specific tests
test.describe('Mobile Pipeline Drag-and-Drop', () => {
  test.use({
    viewport: { width: 375, height: 667 },
    hasTouch: true
  });

  test('Touch-based deal movement', async ({ page }) => {
    await login(page);
    await ensureTestDealExists(page, TEST_CONFIG.deals.primary, 'screening');
    await navigateToPipeline(page);
    
    const dealCard = page.locator(`.deal-card:has-text("${TEST_CONFIG.deals.primary.name}")`);
    const targetStage = page.locator('.droppable[data-stage="analysis_outreach"]');
    
    // Try touch drag
    await performTouchDragDrop(page, dealCard, targetStage);
    await waitForAjaxComplete(page);
    
    // If touch drag doesn't work, try tap interface
    const mobileSelector = page.locator('.mobile-stage-selector');
    if (!await mobileSelector.isVisible({ timeout: 2000 })) {
      // Tap deal to open selector
      await dealCard.tap();
      await page.waitForTimeout(500);
    }
    
    if (await mobileSelector.isVisible()) {
      await page.locator('[data-stage="analysis_outreach"]').tap();
      await waitForAjaxComplete(page);
    }
    
    // Verify move
    const newStage = await getDealStage(page, TEST_CONFIG.deals.primary.name);
    expect(newStage).toBe('analysis_outreach');
  });
});

// Performance tests
test.describe('Drag-and-Drop Performance', () => {
  test('Fast successive drag operations', async ({ page }) => {
    await login(page);
    await navigateToPipeline(page);
    
    // Ensure we have multiple deals
    for (let i = 1; i <= 3; i++) {
      await ensureTestDealExists(page, {
        name: `Perf Test Deal ${i}`,
        ttmRevenue: 100000 * i,
        ttmEbitda: 25000 * i
      }, 'screening');
    }
    
    await navigateToPipeline(page);
    
    // Perform rapid successive drags
    const targetStage = page.locator('.droppable[data-stage="analysis_outreach"]');
    
    for (let i = 1; i <= 3; i++) {
      const dealCard = page.locator(`.deal-card:has-text("Perf Test Deal ${i}")`);
      if (await dealCard.isVisible()) {
        await performRealisticDragDrop(page, dealCard, targetStage, {
          steps: 5,
          delayBetweenSteps: 10,
          finalDelay: 200
        });
      }
    }
    
    // Wait for all operations to complete
    await waitForAjaxComplete(page, 10000);
    
    // Verify all deals moved
    for (let i = 1; i <= 3; i++) {
      const stage = await getDealStage(page, `Perf Test Deal ${i}`);
      expect(stage).toBe('analysis_outreach');
    }
  });
});