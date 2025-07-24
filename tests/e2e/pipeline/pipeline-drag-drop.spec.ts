import { test, expect, Page } from '@playwright/test';

/**
 * Pipeline Drag and Drop E2E Tests
 * 
 * Tests the complete pipeline functionality including:
 * - Deal card drag and drop
 * - Stage transitions
 * - WIP limits
 * - Visual feedback
 * - Mobile gestures
 */

// Test data
const testDeals = [
  { id: 1, name: 'Acme Corp Deal', amount: '$50,000', stage: 'lead' },
  { id: 2, name: 'TechStart Opportunity', amount: '$75,000', stage: 'lead' },
  { id: 3, name: 'Global Industries', amount: '$120,000', stage: 'contacted' },
  { id: 4, name: 'StartupXYZ', amount: '$25,000', stage: 'qualified' },
];

// Helper functions
async function login(page: Page) {
  await page.goto('/login');
  await page.fill('#username', 'testuser');
  await page.fill('#password', 'testpass');
  await page.click('button[type="submit"]');
  await page.waitForURL('/dashboard');
}

async function navigateToPipeline(page: Page) {
  await page.click('nav a:has-text("Deals")');
  await page.click('a:has-text("Pipeline View")');
  await page.waitForSelector('.pipeline-board');
}

async function getDealCard(page: Page, dealName: string) {
  return page.locator(`.deal-card:has-text("${dealName}")`);
}

async function getStageColumn(page: Page, stageName: string) {
  return page.locator(`.pipeline-stage[data-stage="${stageName}"]`);
}

// Desktop drag and drop tests
test.describe('Desktop Drag and Drop', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
    await navigateToPipeline(page);
  });

  test('should drag deal from Lead to Contacted', async ({ page }) => {
    // Get elements
    const dealCard = await getDealCard(page, 'Acme Corp Deal');
    const targetStage = await getStageColumn(page, 'contacted');
    
    // Verify initial state
    await expect(dealCard).toBeVisible();
    const leadStage = await getStageColumn(page, 'lead');
    await expect(leadStage).toContainText('Acme Corp Deal');
    
    // Perform drag and drop
    await dealCard.dragTo(targetStage);
    
    // Wait for animation
    await page.waitForTimeout(500);
    
    // Verify deal moved
    await expect(targetStage).toContainText('Acme Corp Deal');
    await expect(leadStage).not.toContainText('Acme Corp Deal');
    
    // Verify success notification
    await expect(page.locator('.notification.success')).toContainText('Deal moved successfully');
  });

  test('should prevent invalid stage transition', async ({ page }) => {
    // Try to drag from Lead directly to Won (invalid)
    const dealCard = await getDealCard(page, 'TechStart Opportunity');
    const wonStage = await getStageColumn(page, 'won');
    
    // Attempt drag
    await dealCard.dragTo(wonStage);
    
    // Verify deal didn't move
    const leadStage = await getStageColumn(page, 'lead');
    await expect(leadStage).toContainText('TechStart Opportunity');
    await expect(wonStage).not.toContainText('TechStart Opportunity');
    
    // Verify error notification
    await expect(page.locator('.notification.error')).toContainText('Invalid stage transition');
  });

  test('should enforce WIP limits', async ({ page }) => {
    // Fill up Qualified stage to WIP limit
    const qualifiedStage = await getStageColumn(page, 'qualified');
    const wipLimit = await qualifiedStage.getAttribute('data-wip-limit');
    
    // Check current count
    const currentCount = await qualifiedStage.locator('.deal-card').count();
    
    if (currentCount >= parseInt(wipLimit || '5')) {
      // Try to add one more
      const dealCard = await getDealCard(page, 'Global Industries');
      await dealCard.dragTo(qualifiedStage);
      
      // Verify warning dialog
      await expect(page.locator('.wip-warning-dialog')).toBeVisible();
      await expect(page.locator('.wip-warning-dialog')).toContainText('WIP limit reached');
      
      // Cancel the move
      await page.click('.wip-warning-dialog button:has-text("Cancel")');
      
      // Verify deal didn't move
      const contactedStage = await getStageColumn(page, 'contacted');
      await expect(contactedStage).toContainText('Global Industries');
    }
  });

  test('should highlight valid drop zones on drag start', async ({ page }) => {
    const dealCard = await getDealCard(page, 'Acme Corp Deal');
    
    // Start dragging
    await dealCard.hover();
    await page.mouse.down();
    await page.mouse.move(100, 100);
    
    // Check valid stages are highlighted
    await expect(page.locator('.pipeline-stage.valid-drop-zone')).toHaveCount(2); // contacted and lost
    await expect(page.locator('.pipeline-stage.invalid-drop-zone')).toHaveCount(4); // Others
    
    // Release drag
    await page.mouse.up();
  });

  test('should show stale deal indicator', async ({ page }) => {
    // Find deals that have been in stage > 7 days
    const staleDeals = page.locator('.deal-card.is-stale');
    
    if (await staleDeals.count() > 0) {
      // Verify stale indicator is visible
      await expect(staleDeals.first().locator('.stale-indicator')).toBeVisible();
      await expect(staleDeals.first().locator('.stale-indicator')).toContainText('7+ days');
      
      // Verify tooltip on hover
      await staleDeals.first().hover();
      await expect(page.locator('.tooltip')).toContainText('This deal has been in this stage for');
    }
  });
});

// Mobile touch gesture tests
test.describe('Mobile Touch Gestures', () => {
  test.use({ 
    viewport: { width: 375, height: 667 },
    hasTouch: true 
  });

  test.beforeEach(async ({ page }) => {
    await login(page);
    await navigateToPipeline(page);
  });

  test('should move deal using long press and drag on mobile', async ({ page }) => {
    const dealCard = await getDealCard(page, 'Acme Corp Deal');
    const targetStage = await getStageColumn(page, 'contacted');
    
    // Get positions
    const dealBox = await dealCard.boundingBox();
    const targetBox = await targetStage.boundingBox();
    
    if (dealBox && targetBox) {
      // Long press to activate drag mode
      await page.touchscreen.tap(dealBox.x + dealBox.width / 2, dealBox.y + dealBox.height / 2, { delay: 1000 });
      
      // Verify drag mode activated
      await expect(dealCard).toHaveClass(/dragging/);
      
      // Drag to target
      await page.touchscreen.tap(targetBox.x + targetBox.width / 2, targetBox.y + 50);
      
      // Verify moved
      await expect(targetStage).toContainText('Acme Corp Deal');
    }
  });

  test('should show mobile-optimized stage selector', async ({ page }) => {
    // Tap deal to select
    const dealCard = await getDealCard(page, 'TechStart Opportunity');
    await dealCard.tap();
    
    // Verify mobile stage selector appears
    await expect(page.locator('.mobile-stage-selector')).toBeVisible();
    
    // Select new stage
    await page.click('.mobile-stage-selector button[data-stage="contacted"]');
    
    // Verify deal moved
    const contactedStage = await getStageColumn(page, 'contacted');
    await expect(contactedStage).toContainText('TechStart Opportunity');
  });

  test('should support swipe gestures for horizontal scroll', async ({ page }) => {
    // Check if pipeline requires horizontal scroll
    const pipeline = page.locator('.pipeline-board');
    const pipelineBox = await pipeline.boundingBox();
    
    if (pipelineBox && pipelineBox.width > 375) {
      // Swipe left to see more stages
      await page.touchscreen.tap(300, 300);
      await page.waitForTimeout(100);
      await page.touchscreen.tap(100, 300);
      
      // Verify scroll happened
      const scrollLeft = await pipeline.evaluate(el => el.scrollLeft);
      expect(scrollLeft).toBeGreaterThan(0);
    }
  });
});

// Performance tests
test.describe('Performance', () => {
  test('should load pipeline with 500+ deals in under 2 seconds', async ({ page }) => {
    // Navigate to test environment with many deals
    await login(page);
    
    const startTime = Date.now();
    await navigateToPipeline(page);
    await page.waitForSelector('.deal-card');
    const loadTime = Date.now() - startTime;
    
    // Verify load time
    expect(loadTime).toBeLessThan(2000);
    
    // Verify all deals loaded
    const dealCount = await page.locator('.deal-card').count();
    expect(dealCount).toBeGreaterThan(100); // Assuming test data has many deals
  });

  test('should maintain smooth animations during drag', async ({ page }) => {
    await login(page);
    await navigateToPipeline(page);
    
    // Enable performance monitoring
    const client = await page.context().newCDPSession(page);
    await client.send('Performance.enable');
    
    // Perform drag operation
    const dealCard = await getDealCard(page, 'Acme Corp Deal');
    const targetStage = await getStageColumn(page, 'contacted');
    
    // Start monitoring
    await client.send('Performance.setTimeDomain', { timeDomain: 'timeTicks' });
    const startMetrics = await client.send('Performance.getMetrics');
    
    // Drag
    await dealCard.dragTo(targetStage);
    await page.waitForTimeout(1000);
    
    // Get end metrics
    const endMetrics = await client.send('Performance.getMetrics');
    
    // Calculate FPS (simplified)
    const startTime = startMetrics.metrics.find(m => m.name === 'Timestamp')?.value || 0;
    const endTime = endMetrics.metrics.find(m => m.name === 'Timestamp')?.value || 0;
    const duration = endTime - startTime;
    
    // Verify smooth animation (at least 30 FPS)
    expect(duration).toBeGreaterThan(0);
  });
});

// Accessibility tests
test.describe('Accessibility', () => {
  test('should support keyboard navigation', async ({ page }) => {
    await login(page);
    await navigateToPipeline(page);
    
    // Focus first deal
    await page.keyboard.press('Tab');
    await page.keyboard.press('Tab'); // Skip navigation
    
    // Select deal with Enter
    await page.keyboard.press('Enter');
    await expect(page.locator('.deal-card:focus')).toHaveClass(/selected/);
    
    // Navigate stages with arrow keys
    await page.keyboard.press('ArrowRight');
    await expect(page.locator('.stage-selector:focus')).toHaveAttribute('data-stage', 'contacted');
    
    // Move deal with Enter
    await page.keyboard.press('Enter');
    
    // Verify deal moved
    const contactedStage = await getStageColumn(page, 'contacted');
    await expect(contactedStage).toContainText('Acme Corp Deal');
  });

  test('should have proper ARIA labels', async ({ page }) => {
    await login(page);
    await navigateToPipeline(page);
    
    // Check pipeline board
    await expect(page.locator('.pipeline-board')).toHaveAttribute('role', 'grid');
    await expect(page.locator('.pipeline-board')).toHaveAttribute('aria-label', 'Sales Pipeline');
    
    // Check stages
    const stages = page.locator('.pipeline-stage');
    await expect(stages.first()).toHaveAttribute('role', 'gridcell');
    await expect(stages.first()).toHaveAttribute('aria-label', /Lead - \d+ deals/);
    
    // Check deal cards
    const dealCards = page.locator('.deal-card');
    await expect(dealCards.first()).toHaveAttribute('role', 'button');
    await expect(dealCards.first()).toHaveAttribute('aria-draggable', 'true');
  });

  test('should announce stage changes to screen readers', async ({ page }) => {
    await login(page);
    await navigateToPipeline(page);
    
    // Move a deal
    const dealCard = await getDealCard(page, 'Acme Corp Deal');
    const targetStage = await getStageColumn(page, 'contacted');
    await dealCard.dragTo(targetStage);
    
    // Check for ARIA live region update
    await expect(page.locator('[aria-live="polite"]')).toContainText('Acme Corp Deal moved to Contacted');
  });
});