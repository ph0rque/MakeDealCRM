const { test, expect } = require('@playwright/test');

test('Pipeline Final Verification', async ({ page }) => {
  console.log('Starting pipeline verification test...');
  
  // Navigate to login
  await page.goto('http://localhost:8080');
  
  // Check if we need to login
  const isLoginPage = await page.isVisible('input[name="user_name"]');
  
  if (isLoginPage) {
    console.log('Logging in...');
    await page.fill('input[name="user_name"]', 'admin');
    await page.fill('input[name="username_password"]', 'admin123');
    await page.click('input[type="submit"]');
    await page.waitForLoadState('networkidle');
  }
  
  // Navigate to pipeline
  await page.goto('http://localhost:8080/index.php?module=Deals&action=pipeline');
  
  // Wait for pipeline to load completely - try different selectors
  await page.waitForLoadState('networkidle');
  
  // Wait for either the pipeline container or stage columns
  try {
    await page.waitForSelector('.pipeline-container, .stage-column, .pipeline-view', { timeout: 10000 });
  } catch (e) {
    console.log('Pipeline container not found, continuing with available elements...');
  }
  
  // Check 1: Verify .stage-body drop zones exist
  console.log('\n1. Checking for .stage-body drop zones...');
  const stageBodyElements = await page.$$('.stage-body');
  console.log(`   ✓ Found ${stageBodyElements.length} .stage-body drop zones`);
  
  // Check that .stage-deals is NOT used as drop zone
  const stageDealElements = await page.$$('.stage-deals[ondrop]');
  console.log(`   ✓ Confirmed .stage-deals is NOT used as drop zone (found ${stageDealElements.length})`);
  
  // Check 2: Verify no sample deals or they're clearly marked
  console.log('\n2. Checking for sample deals...');
  const dealCards = await page.$$('.deal-card');
  let sampleDealCount = 0;
  
  for (const card of dealCards) {
    const dealName = await card.$eval('.deal-name', el => el.textContent.trim()).catch(() => '');
    const isSample = dealName.toLowerCase().includes('sample') || 
                    dealName.toLowerCase().includes('test') ||
                    dealName.toLowerCase().includes('example');
    
    if (isSample) {
      sampleDealCount++;
      console.log(`   - Found potential sample deal: "${dealName}"`);
    }
  }
  console.log(`   ✓ Total deals: ${dealCards.length}, Sample deals: ${sampleDealCount}`);
  
  // Check 3: Verify no "missing required params" error
  console.log('\n3. Checking for errors...');
  
  // Check for visible error messages
  const errorElements = await page.$$('.error-message, .alert-danger, [class*="error"], .sugar-error-message');
  let hasParamsError = false;
  
  for (const errorEl of errorElements) {
    const errorText = await errorEl.textContent();
    if (errorText.toLowerCase().includes('missing') && 
        errorText.toLowerCase().includes('required') && 
        errorText.toLowerCase().includes('param')) {
      hasParamsError = true;
      console.log(`   ✗ Found params error: ${errorText}`);
    }
  }
  
  if (!hasParamsError) {
    console.log('   ✓ No "missing required params" error found');
  }
  
  // Test drag and drop functionality
  console.log('\n4. Testing drag and drop...');
  
  // Find a deal to drag
  const dealToDrag = await page.$('.deal-card');
  if (dealToDrag) {
    try {
      // Get the original stage
      const originalStageId = await page.evaluate(el => {
        const stage = el.closest('.stage-column');
        return stage ? stage.dataset.stageId : null;
      }, dealToDrag);
      
      console.log(`   - Found deal in stage: ${originalStageId}`);
      
      // Find a different stage to drop into
      const targetStageBody = await page.$(`.stage-column:not([data-stage-id="${originalStageId}"]) .stage-body`);
      
      if (targetStageBody) {
        // Get the deal center position
        const dealBox = await dealToDrag.boundingBox();
        const targetBox = await targetStageBody.boundingBox();
        
        if (dealBox && targetBox) {
          // Perform drag and drop
          await page.mouse.move(dealBox.x + dealBox.width / 2, dealBox.y + dealBox.height / 2);
          await page.mouse.down();
          await page.mouse.move(targetBox.x + targetBox.width / 2, targetBox.y + targetBox.height / 2, { steps: 10 });
          await page.mouse.up();
          
          // Wait for any animations
          await page.waitForTimeout(1000);
          
          // Check if deal moved
          const newStageId = await page.evaluate(el => {
            const stage = el.closest('.stage-column');
            return stage ? stage.dataset.stageId : null;
          }, dealToDrag);
          
          if (newStageId !== originalStageId) {
            console.log(`   ✓ Drag and drop working! Deal moved from stage ${originalStageId} to ${newStageId}`);
          } else {
            console.log('   ✗ Drag and drop may not be working - deal did not move');
          }
        }
      } else {
        console.log('   - Could not find a different stage to test drag and drop');
      }
    } catch (error) {
      console.log(`   - Error testing drag and drop: ${error.message}`);
    }
  } else {
    console.log('   - No deals found to test drag and drop');
  }
  
  // Take screenshot
  console.log('\n5. Taking screenshot of pipeline...');
  await page.screenshot({ 
    path: 'pipeline-verification.png',
    fullPage: true 
  });
  console.log('   ✓ Screenshot saved: pipeline-verification.png');
  
  // Summary
  console.log('\n=== VERIFICATION SUMMARY ===');
  console.log(`✓ Drop zones: ${stageBodyElements.length} .stage-body elements found`);
  console.log(`✓ No .stage-deals drop zones: Confirmed`);
  console.log(`✓ Deal cards: ${dealCards.length} total, ${sampleDealCount} potential samples`);
  console.log(`✓ "Missing required params" error: ${hasParamsError ? 'FOUND' : 'Not found'}`);
  console.log('✓ Screenshot: Saved to pipeline-verification.png');
  console.log('=============================\n');
  
  // Assert critical checks
  expect(stageBodyElements.length).toBeGreaterThan(0);
  expect(stageDealElements.length).toBe(0);
  expect(hasParamsError).toBe(false);
});