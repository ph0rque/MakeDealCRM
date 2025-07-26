import { Page, Locator } from '@playwright/test';

/**
 * Helper utilities for drag-and-drop operations in pipeline tests
 * These helpers are designed to work with the specific implementation
 * in custom/modules/Deals/js/pipeline.js
 */

export interface DragDropOptions {
  steps?: number;
  delayBetweenSteps?: number;
  finalDelay?: number;
}

/**
 * Perform a realistic drag-and-drop operation that works with the pipeline implementation
 */
export async function performRealisticDragDrop(
  page: Page,
  source: Locator,
  target: Locator,
  options: DragDropOptions = {}
) {
  const {
    steps = 10,
    delayBetweenSteps = 50,
    finalDelay = 500
  } = options;

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
  
  // Move to source and start drag
  await page.mouse.move(sourceX, sourceY);
  await page.mouse.down();
  
  // Wait a bit to ensure drag is registered
  await page.waitForTimeout(100);
  
  // Move in steps to simulate realistic drag
  for (let i = 1; i <= steps; i++) {
    const x = sourceX + (targetX - sourceX) * (i / steps);
    const y = sourceY + (targetY - sourceY) * (i / steps);
    await page.mouse.move(x, y);
    if (delayBetweenSteps > 0) {
      await page.waitForTimeout(delayBetweenSteps);
    }
  }
  
  // Drop
  await page.mouse.up();
  
  // Wait for animation/processing
  if (finalDelay > 0) {
    await page.waitForTimeout(finalDelay);
  }
}

/**
 * Alternative drag-drop using HTML5 drag events
 */
export async function performHTML5DragDrop(
  page: Page,
  sourceSelector: string,
  targetSelector: string
) {
  await page.evaluate(({ sourceSelector, targetSelector }) => {
    const source = document.querySelector(sourceSelector);
    const target = document.querySelector(targetSelector);
    
    if (!source || !target) {
      throw new Error('Source or target element not found');
    }
    
    // Create drag start event
    const dragStartEvent = new DragEvent('dragstart', {
      bubbles: true,
      cancelable: true,
      dataTransfer: new DataTransfer()
    });
    
    // Set data on the drag event
    dragStartEvent.dataTransfer!.effectAllowed = 'move';
    dragStartEvent.dataTransfer!.setData('text/html', source.innerHTML);
    
    // Dispatch drag start
    source.dispatchEvent(dragStartEvent);
    
    // Create and dispatch drag over event on target
    const dragOverEvent = new DragEvent('dragover', {
      bubbles: true,
      cancelable: true,
      dataTransfer: dragStartEvent.dataTransfer
    });
    target.dispatchEvent(dragOverEvent);
    
    // Create and dispatch drop event
    const dropEvent = new DragEvent('drop', {
      bubbles: true,
      cancelable: true,
      dataTransfer: dragStartEvent.dataTransfer
    });
    target.dispatchEvent(dropEvent);
    
    // Dispatch drag end event
    const dragEndEvent = new DragEvent('dragend', {
      bubbles: true,
      cancelable: true,
      dataTransfer: dragStartEvent.dataTransfer
    });
    source.dispatchEvent(dragEndEvent);
  }, { sourceSelector, targetSelector });
}

/**
 * Wait for AJAX operations to complete
 */
export async function waitForAjaxComplete(page: Page, timeout: number = 5000) {
  await page.waitForFunction(
    () => {
      // Check jQuery active requests
      if (typeof jQuery !== 'undefined' && jQuery.active) {
        return jQuery.active === 0;
      }
      // Check native fetch
      return !document.querySelector('.pipeline-loading, #pipeline-loading');
    },
    { timeout }
  );
}

/**
 * Check if drag operation was successful by verifying notifications
 */
export async function verifyDragSuccess(page: Page) {
  // Wait for any of the possible success indicators
  const successSelectors = [
    '.notification.success',
    '.alert.alert-success',
    '[role="alert"]:has-text("success")',
    '[role="alert"]:has-text("moved")'
  ];
  
  for (const selector of successSelectors) {
    const element = page.locator(selector).first();
    if (await element.isVisible({ timeout: 2000 })) {
      return true;
    }
  }
  
  return false;
}

/**
 * Get the current stage of a deal
 */
export async function getDealStage(page: Page, dealName: string): Promise<string | null> {
  const stages = ['screening', 'analysis_outreach', 'due_diligence', 'closing'];
  
  for (const stage of stages) {
    const stageContainer = page.locator(`.pipeline-stage[data-stage="${stage}"]`);
    if (await stageContainer.locator(`:has-text("${dealName}")`).count() > 0) {
      return stage;
    }
  }
  
  return null;
}

/**
 * Mobile touch-based drag and drop
 */
export async function performTouchDragDrop(
  page: Page,
  source: Locator,
  target: Locator,
  holdDuration: number = 1000
) {
  const sourceBox = await source.boundingBox();
  const targetBox = await target.boundingBox();
  
  if (!sourceBox || !targetBox) {
    throw new Error('Could not get bounding boxes for touch drag and drop');
  }
  
  const sourceX = sourceBox.x + sourceBox.width / 2;
  const sourceY = sourceBox.y + sourceBox.height / 2;
  const targetX = targetBox.x + targetBox.width / 2;
  const targetY = targetBox.y + targetBox.height / 2;
  
  // Long press to activate drag mode
  await page.touchscreen.tap(sourceX, sourceY, { delay: holdDuration });
  
  // Wait for drag mode to activate
  await page.waitForTimeout(200);
  
  // Drag to target
  await page.touchscreen.tap(targetX, targetY);
  
  // Wait for operation to complete
  await page.waitForTimeout(500);
}

/**
 * Verify WIP limit warnings
 */
export async function checkWIPLimitWarning(page: Page): Promise<boolean> {
  const warningSelectors = [
    '.wip-warning-dialog',
    '.wip-limit-exceeded',
    '.alert:has-text("WIP limit")',
    '.notification.error:has-text("WIP")'
  ];
  
  for (const selector of warningSelectors) {
    if (await page.locator(selector).isVisible({ timeout: 1000 })) {
      return true;
    }
  }
  
  return false;
}

/**
 * Get stage deal count
 */
export async function getStageDealCount(page: Page, stageId: string): Promise<number> {
  const countElement = page.locator(`.pipeline-stage[data-stage="${stageId}"] .deal-count`).first();
  const countText = await countElement.textContent();
  return parseInt(countText || '0');
}

/**
 * Wait for stage counts to update
 */
export async function waitForStageCountUpdate(
  page: Page,
  stageId: string,
  expectedCount: number,
  timeout: number = 5000
) {
  await page.waitForFunction(
    ({ stageId, expectedCount }) => {
      const countElement = document.querySelector(
        `.pipeline-stage[data-stage="${stageId}"] .deal-count`
      );
      if (!countElement) return false;
      const count = parseInt(countElement.textContent || '0');
      return count === expectedCount;
    },
    { stageId, expectedCount },
    { timeout }
  );
}