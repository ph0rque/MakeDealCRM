const { chromium } = require('playwright');

// Test configuration
const BASE_URL = 'http://localhost:8080';
const USERNAME = 'admin';
const PASSWORD = 'admin123';

async function testDragDropFunctionality() {
    const browser = await chromium.launch({ 
        headless: false,  // Run in visible mode for debugging
        timeout: 60000 
    });
    const context = await browser.newContext({
        viewport: { width: 1280, height: 720 }
    });
    const page = await context.newPage();

    // Enable console log monitoring
    page.on('console', msg => {
        console.log(`Browser console: ${msg.text()}`);
    });

    // Monitor JavaScript errors
    page.on('pageerror', error => {
        console.error(`Page error: ${error.message}`);
    });

    try {
        console.log('🚀 Testing Drag and Drop Functionality\n');

        // Step 1: Login
        console.log('📋 Step 1: Login to SuiteCRM');
        await page.goto(BASE_URL);
        
        const isLoggedIn = await page.$('#toolbar, .navbar, nav');
        if (!isLoggedIn) {
            await page.fill('input[name="user_name"]', USERNAME);
            await page.fill('input[name="username_password"]', PASSWORD);
            await page.click('input[type="submit"]');
            await page.waitForSelector('#toolbar, .navbar, nav, #content', { timeout: 15000 });
        }
        console.log('✅ Login successful');

        // Step 2: Navigate to pipeline
        console.log('\n📋 Step 2: Navigate to pipeline');
        await page.goto(`${BASE_URL}/index.php?module=Deals&action=pipeline`);
        await page.waitForLoadState('networkidle');
        
        // Check for any 404 or loading errors
        const scriptTags = await page.$$eval('script[src*="pipeline.js"]', scripts => 
            scripts.map(script => script.src)
        );
        console.log('Pipeline.js script tags found:', scriptTags);
        
        console.log('✅ Pipeline loaded');

        // Step 3: Check if JavaScript files are loaded
        console.log('\n📋 Step 3: Checking JavaScript dependencies');
        
        // Check if jQuery is available
        const jqueryAvailable = await page.evaluate(() => {
            return typeof jQuery !== 'undefined';
        });
        console.log(`jQuery available: ${jqueryAvailable}`);

        // Check if PipelineView is available
        const pipelineViewAvailable = await page.evaluate(() => {
            return typeof PipelineView !== 'undefined';
        });
        console.log(`PipelineView available: ${pipelineViewAvailable}`);

        // Check the actual content of the page
        const dealCards = await page.$$('.deal-card');
        console.log(`Deal cards found: ${dealCards.length}`);
        
        const pipelineStages = await page.$$('.pipeline-stage');
        console.log(`Pipeline stages found: ${pipelineStages.length}`);
        
        // Check if draggable elements exist
        const draggableElements = await page.$$('.draggable');
        console.log(`Draggable elements found: ${draggableElements.length}`);

        // Check if droppable elements exist
        const droppableElements = await page.$$('.droppable');
        console.log(`Droppable elements found: ${droppableElements.length}`);
        
        // Check for our debug console messages
        const debugMessages = await page.evaluate(() => {
            // Look for any console messages we added
            return window.console && window.console.log ? 'Console available' : 'No console';
        });
        
        // Get the actual HTML structure to understand what's being rendered
        const pageTitle = await page.title();
        console.log(`Page title: ${pageTitle}`);
        
        // Check what elements are actually on the page
        const allElementsWithStage = await page.$$('[class*="stage"]');
        console.log(`Elements with 'stage' in class: ${allElementsWithStage.length}`);
        
        const allElementsWithPipeline = await page.$$('[class*="pipeline"]');
        console.log(`Elements with 'pipeline' in class: ${allElementsWithPipeline.length}`);

        // Step 4: Test drag and drop manually
        if (dealCards.length > 0) {
            console.log('\n📋 Step 4: Testing drag and drop');
            
            // Get the first deal card
            const firstDeal = dealCards[0];
            const dealId = await firstDeal.getAttribute('data-deal-id');
            const currentStage = await firstDeal.getAttribute('data-stage');
            console.log(`Testing with deal ID: ${dealId}, current stage: ${currentStage}`);

            // Get a different drop target (different stage)
            const stageElements = await page.$$('[class*="stage"]');
            let targetStage = null;
            for (const stageElement of stageElements) {
                const stageKey = await stageElement.getAttribute('data-stage');
                if (stageKey && stageKey !== currentStage) {
                    targetStage = stageElement;
                    break;
                }
            }

            if (targetStage) {
                const targetStageKey = await targetStage.getAttribute('data-stage');
                console.log(`Target stage: ${targetStageKey}`);

                // Attempt to drag and drop
                try {
                    await firstDeal.dragTo(targetStage);
                    console.log('✅ Drag and drop operation completed');
                    
                    // Wait a moment for any updates
                    await page.waitForTimeout(2000);
                    
                    // Check if the deal moved
                    const dealAfterDrop = await page.$(`[data-deal-id="${dealId}"]`);
                    if (dealAfterDrop) {
                        const newStage = await dealAfterDrop.getAttribute('data-stage');
                        console.log(`Deal is now in stage: ${newStage}`);
                        
                        if (newStage === targetStageKey) {
                            console.log('✅ Drag and drop successful - deal moved to new stage!');
                        } else {
                            console.log('❌ Drag and drop failed - deal did not move');
                        }
                    }
                } catch (dragError) {
                    console.error('❌ Drag and drop failed:', dragError.message);
                }
            } else {
                console.log('❌ No valid target stage found for testing');
            }
        } else {
            console.log('❌ No draggable or droppable elements found');
        }

        // Step 5: Check if there are any JavaScript errors in the browser console
        console.log('\n📋 Step 5: Checking for JavaScript initialization');
        await page.evaluate(() => {
            console.log('Browser environment check:');
            console.log('- jQuery version:', jQuery ? jQuery.fn.jquery : 'Not available');
            console.log('- PipelineView object:', typeof PipelineView);
            
            // Check if our template JavaScript ran
            console.log('- Looking for our debug messages...');
            
            // Try to manually initialize drag and drop if template didn't load
            console.log('- Manually checking deal cards:', jQuery('.deal-card').length);
            
            if (jQuery('.deal-card').length > 0) {
                console.log('- Deal cards found, attempting manual drag/drop setup...');
                
                // Enable draggable on deal cards
                jQuery('.deal-card').each(function() {
                    this.draggable = true;
                    console.log('- Made deal card draggable:', jQuery(this).data('deal-id'));
                });
                
                console.log('- Drag and drop manually initialized!');
            }
            
            if (typeof PipelineView !== 'undefined') {
                console.log('- PipelineView config:', PipelineView.config);
                console.log('- Draggable elements:', jQuery('.draggable').length);
                console.log('- Droppable elements:', jQuery('.droppable').length);
            }
        });

        // Take screenshots for debugging
        await page.screenshot({ path: 'debug-drag-drop.png' });
        console.log('📸 Screenshot saved as debug-drag-drop.png');

    } catch (error) {
        console.error('❌ Test failed:', error);
        await page.screenshot({ path: 'debug-drag-drop-error.png' });
    } finally {
        await browser.close();
    }
}

// Run the test
testDragDropFunctionality().catch(console.error);