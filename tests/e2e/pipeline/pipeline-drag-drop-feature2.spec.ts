import { test, expect, Page } from '@playwright/test';

/**
 * Feature 2: Unified Deal & Portfolio Pipeline E2E Tests
 * Test Case 2.1: E2E Pipeline Stage Transition via Drag-and-Drop
 * 
 * This test verifies that a user can move a deal from one pipeline stage to another
 * using the drag-and-drop interface, and that the change persists correctly across
 * the system, including UI updates, database persistence, and audit logs.
 */

// Test configuration
const TEST_DEAL = {
  name: 'E2E Test Deal',
  initialStage: 'screening',
  targetStage: 'analysis_outreach',
  ttmRevenue: 1000000,
  ttmEbitda: 250000
};

// Helper functions
async function login(page: Page) {
  await page.goto('http://localhost:8080');
  
  // Handle potential redirect to login page
  if (page.url().includes('login')) {
    await page.fill('#user_name', 'admin');
    await page.fill('#username_password', 'admin');
    await page.click('#bigbutton');
    await page.waitForURL('**/index.php**', { timeout: 30000 });
  }
}

async function navigateToDealsModule(page: Page) {
  // Click on the Deals module from the navigation
  await page.click('text=Deals');
  await page.waitForTimeout(1000); // Wait for menu to load
}

async function navigateToPipelineView(page: Page) {
  // Navigate to pipeline view
  await page.goto('http://localhost:8080/index.php?module=Deals&action=pipeline');
  await page.waitForSelector('.pipeline-board', { timeout: 30000 });
}

async function createTestDeal(page: Page) {
  // Navigate to create deal form
  await page.goto('http://localhost:8080/index.php?module=Deals&action=EditView');
  
  // Fill in deal details
  await page.fill('#name', TEST_DEAL.name);
  await page.fill('#ttm_revenue_c', TEST_DEAL.ttmRevenue.toString());
  await page.fill('#ttm_ebitda_c', TEST_DEAL.ttmEbitda.toString());
  
  // Set initial stage to Screening
  await page.selectOption('#pipeline_stage_c', 'screening');
  
  // Save the deal
  await page.click('input[value="Save"]');
  await page.waitForURL('**/index.php?module=Deals&action=DetailView**', { timeout: 30000 });
}

async function findOrCreateTestDeal(page: Page) {
  // First, try to find existing test deal
  await navigateToDealsModule(page);
  await page.goto('http://localhost:8080/index.php?module=Deals&action=index');
  
  // Search for the test deal
  const searchBox = await page.locator('input[name="name_basic"]');
  if (await searchBox.isVisible()) {
    await searchBox.fill(TEST_DEAL.name);
    await page.click('input[value="Search"]');
    await page.waitForTimeout(2000);
  }
  
  // Check if deal exists
  const dealLink = page.locator(`a:has-text("${TEST_DEAL.name}")`).first();
  if (await dealLink.count() === 0) {
    // Create the deal if it doesn't exist
    await createTestDeal(page);
  } else {
    // Click on the deal to view details
    await dealLink.click();
    await page.waitForSelector('.detail-view', { timeout: 30000 });
    
    // Ensure it's in the correct initial stage
    const stageField = await page.locator('span[data-field="pipeline_stage_c"], div[data-field="pipeline_stage_c"]').textContent();
    if (!stageField?.toLowerCase().includes('screening')) {
      // Update the stage to Screening
      await page.click('a:has-text("Edit")');
      await page.waitForSelector('#pipeline_stage_c');
      await page.selectOption('#pipeline_stage_c', 'screening');
      await page.click('input[value="Save"]');
      await page.waitForURL('**/index.php?module=Deals&action=DetailView**', { timeout: 30000 });
    }
  }
}

async function getDealCard(page: Page, dealName: string) {
  return page.locator(`.deal-card:has-text("${dealName}")`).first();
}

async function getStageColumn(page: Page, stageId: string) {
  return page.locator(`.pipeline-stage[data-stage="${stageId}"] .stage-body, .droppable[data-stage="${stageId}"]`).first();
}

async function performDragAndDrop(page: Page, source: any, target: any) {
  // Get bounding boxes
  const sourceBox = await source.boundingBox();
  const targetBox = await target.boundingBox();
  
  if (!sourceBox || !targetBox) {
    throw new Error('Could not get bounding boxes for drag and drop');
  }
  
  // Calculate center points
  const sourceX = sourceBox.x + sourceBox.width / 2;
  const sourceY = sourceBox.y + sourceBox.height / 2;
  const targetX = targetBox.x + targetBox.width / 2;
  const targetY = targetBox.y + targetBox.height / 2;
  
  // Perform drag and drop with mouse events
  await page.mouse.move(sourceX, sourceY);
  await page.mouse.down();
  
  // Move in small steps to simulate realistic drag
  const steps = 10;
  for (let i = 1; i <= steps; i++) {
    const x = sourceX + (targetX - sourceX) * (i / steps);
    const y = sourceY + (targetY - sourceY) * (i / steps);
    await page.mouse.move(x, y);
    await page.waitForTimeout(50);
  }
  
  await page.mouse.up();
  await page.waitForTimeout(500); // Wait for animation
}

// Main test suite
test.describe('Feature 2: Pipeline Drag-and-Drop', () => {
  test.beforeEach(async ({ page }) => {
    // Set viewport for better drag-drop handling
    await page.setViewportSize({ width: 1440, height: 900 });
    
    // Login and setup
    await login(page);
    await findOrCreateTestDeal(page);
  });

  test('Test Case 2.1: E2E Pipeline Stage Transition via Drag-and-Drop', async ({ page }) => {
    // Step 1: Navigate to the Deals module to open the pipeline view
    await navigateToPipelineView(page);
    
    // Step 2: Locate the "E2E Test Deal" card in the "Screening" column
    const screeningStage = await getStageColumn(page, 'screening');
    await expect(screeningStage).toBeVisible();
    
    const dealCard = await getDealCard(page, TEST_DEAL.name);
    await expect(dealCard).toBeVisible();
    
    // Verify initial state - deal is in Screening stage
    const screeningStageContainer = page.locator('.pipeline-stage[data-stage="screening"]');
    await expect(screeningStageContainer).toContainText(TEST_DEAL.name);
    
    // Step 3 & 4: Drag the deal card from "Screening" to "Analysis & Outreach"
    const analysisStage = await getStageColumn(page, 'analysis_outreach');
    await expect(analysisStage).toBeVisible();
    
    // Perform the drag and drop
    await performDragAndDrop(page, dealCard, analysisStage);
    
    // Step 5: Verify the UI updates immediately
    await page.waitForTimeout(1000); // Wait for DOM update
    
    // Check that deal is now in Analysis & Outreach stage
    const analysisStageContainer = page.locator('.pipeline-stage[data-stage="analysis_outreach"]');
    await expect(analysisStageContainer).toContainText(TEST_DEAL.name);
    
    // Verify it's no longer in Screening
    await expect(screeningStageContainer).not.toContainText(TEST_DEAL.name);
    
    // Check for success notification
    const notification = page.locator('.notification.success, .alert-success, [role="alert"]').first();
    if (await notification.isVisible()) {
      await expect(notification).toContainText(/moved|success/i);
    }
    
    // Step 6 & 7: Refresh the browser page and verify persistence
    await page.reload();
    await page.waitForSelector('.pipeline-board', { timeout: 30000 });
    
    // Verify the deal remains in Analysis & Outreach after refresh
    const analysisStageAfterRefresh = page.locator('.pipeline-stage[data-stage="analysis_outreach"]');
    await expect(analysisStageAfterRefresh).toContainText(TEST_DEAL.name);
    
    // Step 8 & 9: Click on the deal card to navigate to its detail view
    const dealCardAfterRefresh = await getDealCard(page, TEST_DEAL.name);
    await dealCardAfterRefresh.click();
    await page.waitForSelector('.detail-view', { timeout: 30000 });
    
    // Verify the "Stage" field shows "Analysis & Outreach"
    const stageField = page.locator('span[data-field="pipeline_stage_c"], div[data-field="pipeline_stage_c"], td:has-text("Pipeline Stage") + td').first();
    await expect(stageField).toContainText(/Analysis.*Outreach/i);
    
    // Step 10: Navigate to the deal's "View Change Log" and verify audit entry
    // Look for audit/history link
    const auditLink = page.locator('a:has-text("View Change Log"), a:has-text("Audit"), a:has-text("History")').first();
    if (await auditLink.isVisible()) {
      await auditLink.click();
      await page.waitForTimeout(2000);
      
      // Look for the stage change entry in the audit log
      const auditEntries = page.locator('.list-view-data tr, .audit-table tr, table tr').filter({
        hasText: /pipeline.*stage|stage.*change/i
      });
      
      if (await auditEntries.count() > 0) {
        // Verify the audit log contains the stage change
        const latestEntry = auditEntries.first();
        await expect(latestEntry).toContainText(/screening/i);
        await expect(latestEntry).toContainText(/analysis.*outreach/i);
      }
    }
  });

  test('should show visual feedback during drag operation', async ({ page }) => {
    await navigateToPipelineView(page);
    
    const dealCard = await getDealCard(page, TEST_DEAL.name);
    const analysisStage = await getStageColumn(page, 'analysis_outreach');
    
    // Start dragging
    const box = await dealCard.boundingBox();
    if (box) {
      await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
      await page.mouse.down();
      
      // Move slightly to trigger drag state
      await page.mouse.move(box.x + box.width / 2 + 50, box.y + box.height / 2);
      
      // Check for dragging class
      await expect(dealCard).toHaveClass(/dragging/);
      
      // Check for drop zone highlight
      const dropZones = page.locator('.drag-over, .valid-drop-zone');
      await expect(dropZones).toHaveCount(1, { timeout: 5000 });
      
      // Complete the drag
      await page.mouse.up();
    }
  });

  test('should handle invalid stage transitions gracefully', async ({ page }) => {
    await navigateToPipelineView(page);
    
    // First, ensure the deal is in Screening stage
    const dealCard = await getDealCard(page, TEST_DEAL.name);
    
    // Try to drag directly to a late stage (e.g., Closing) which might be invalid
    const closingStage = await getStageColumn(page, 'closing');
    
    if (await closingStage.isVisible()) {
      await performDragAndDrop(page, dealCard, closingStage);
      
      // Check for error notification or validation message
      const errorNotification = page.locator('.notification.error, .alert-error, .alert-danger').first();
      if (await errorNotification.isVisible({ timeout: 2000 })) {
        await expect(errorNotification).toContainText(/invalid|not allowed|cannot/i);
      }
      
      // Verify deal remains in original stage
      const screeningStage = page.locator('.pipeline-stage[data-stage="screening"]');
      await expect(screeningStage).toContainText(TEST_DEAL.name);
    }
  });

  test('should update stage counts after moving deals', async ({ page }) => {
    await navigateToPipelineView(page);
    
    // Get initial counts
    const screeningCount = page.locator('.pipeline-stage[data-stage="screening"] .deal-count').first();
    const analysisCount = page.locator('.pipeline-stage[data-stage="analysis_outreach"] .deal-count').first();
    
    const initialScreeningCount = await screeningCount.textContent() || '0';
    const initialAnalysisCount = await analysisCount.textContent() || '0';
    
    // Move the deal
    const dealCard = await getDealCard(page, TEST_DEAL.name);
    const analysisStage = await getStageColumn(page, 'analysis_outreach');
    await performDragAndDrop(page, dealCard, analysisStage);
    
    await page.waitForTimeout(1000);
    
    // Verify counts updated
    const newScreeningCount = await screeningCount.textContent() || '0';
    const newAnalysisCount = await analysisCount.textContent() || '0';
    
    expect(parseInt(newScreeningCount)).toBe(parseInt(initialScreeningCount) - 1);
    expect(parseInt(newAnalysisCount)).toBe(parseInt(initialAnalysisCount) + 1);
  });
});

// Mobile drag-and-drop tests
test.describe('Mobile Pipeline Interactions', () => {
  test.use({ 
    viewport: { width: 375, height: 667 },
    hasTouch: true 
  });

  test('should support touch-based stage selection on mobile', async ({ page }) => {
    await login(page);
    await navigateToPipelineView(page);
    
    // Find the test deal
    const dealCard = await getDealCard(page, TEST_DEAL.name);
    
    // Tap to select the deal
    await dealCard.tap();
    await page.waitForTimeout(500);
    
    // Check for mobile stage selector
    const mobileSelector = page.locator('.mobile-stage-selector, .stage-selector-mobile');
    if (await mobileSelector.isVisible({ timeout: 2000 })) {
      // Select new stage
      const analysisButton = page.locator('button[data-stage="analysis_outreach"], option[value="analysis_outreach"]').first();
      await analysisButton.click();
      
      // Verify deal moved
      await page.waitForTimeout(1000);
      const analysisStage = page.locator('.pipeline-stage[data-stage="analysis_outreach"]');
      await expect(analysisStage).toContainText(TEST_DEAL.name);
    } else {
      // Fallback: Try long press for mobile drag
      const box = await dealCard.boundingBox();
      const targetBox = await (await getStageColumn(page, 'analysis_outreach')).boundingBox();
      
      if (box && targetBox) {
        // Long press to activate drag
        await page.touchscreen.tap(box.x + box.width / 2, box.y + box.height / 2, { delay: 1000 });
        
        // Drag to target
        await page.touchscreen.tap(targetBox.x + targetBox.width / 2, targetBox.y + 50);
        
        // Verify moved
        await page.waitForTimeout(1000);
        const analysisStage = page.locator('.pipeline-stage[data-stage="analysis_outreach"]');
        await expect(analysisStage).toContainText(TEST_DEAL.name);
      }
    }
  });
});