const { chromium } = require('playwright');

const BASE_URL = 'http://localhost:8080';
const USERNAME = 'admin';
const PASSWORD = 'admin';

async function testDragDropFix() {
    const browser = await chromium.launch({ 
        headless: false,
        slowMo: 500 
    });
    const context = await browser.newContext();
    const page = await context.newPage();
    
    try {
        console.log('🚀 Testing Drag and Drop Fix');
        console.log('=============================\n');
        
        // 1. Login
        console.log('1️⃣ Logging in...');
        await page.goto(BASE_URL);
        await page.fill('input[name="user_name"]', USERNAME);
        await page.fill('input[name="username_password"]', PASSWORD);
        await page.click('input[title="Log In"]');
        await page.waitForLoadState('networkidle');
        console.log('✅ Login successful\n');
        
        // 2. Navigate to pipeline
        console.log('2️⃣ Navigating to pipeline view...');
        await page.goto(`${BASE_URL}/index.php?module=Deals&action=pipeline`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(3000); // Wait for JS to initialize
        console.log('✅ Pipeline loaded\n');
        
        // 3. Check if pipeline is visible
        const pipelineVisible = await page.isVisible('.pipeline-board');
        console.log(`3️⃣ Pipeline board visible: ${pipelineVisible ? '✅' : '❌'}\n`);
        
        // 4. Check for deal cards
        const dealCards = await page.$$('.deal-card');
        console.log(`4️⃣ Found ${dealCards.length} deal cards\n`);
        
        if (dealCards.length === 0) {
            console.log('❌ No deals found to test drag and drop');
            return;
        }
        
        // 5. Open browser console to see logs
        page.on('console', msg => {
            if (msg.type() === 'log' || msg.type() === 'error') {
                console.log(`🖥️ Console ${msg.type()}: ${msg.text()}`);
            }
        });
        
        // 6. Get stages info
        const stages = await page.$$eval('.pipeline-stage', elements => 
            elements.map(el => ({
                stage: el.getAttribute('data-stage'),
                dealCount: el.querySelectorAll('.deal-card').length
            }))
        );
        console.log('5️⃣ Pipeline stages:');
        stages.forEach(s => console.log(`   - ${s.stage}: ${s.dealCount} deals`));
        console.log('');
        
        // 7. Find a deal to drag
        const sourceStage = stages.find(s => s.dealCount > 0);
        const targetStage = stages.find(s => s.stage !== sourceStage.stage);
        
        if (!sourceStage || !targetStage) {
            console.log('❌ Cannot find suitable stages for drag test');
            return;
        }
        
        console.log(`6️⃣ Testing drag from "${sourceStage.stage}" to "${targetStage.stage}"\n`);
        
        // 8. Perform drag and drop
        const dealCard = await page.$(`.pipeline-stage[data-stage="${sourceStage.stage}"] .deal-card:first-child`);
        const dropZone = await page.$(`.pipeline-stage[data-stage="${targetStage.stage}"] .stage-body`);
        
        if (!dealCard || !dropZone) {
            console.log('❌ Could not find elements for drag and drop');
            return;
        }
        
        // Get deal info
        const dealId = await dealCard.getAttribute('data-deal-id');
        const dealName = await dealCard.$eval('.deal-name', el => el.textContent.trim());
        console.log(`7️⃣ Dragging deal: "${dealName}" (ID: ${dealId})\n`);
        
        // Inject monitoring code
        await page.evaluate(() => {
            console.log('Injecting AJAX monitor...');
            const originalAjax = jQuery.ajax;
            jQuery.ajax = function(options) {
                console.log('AJAX Request:', {
                    url: options.url,
                    type: options.type,
                    data: options.data
                });
                
                const originalSuccess = options.success;
                const originalError = options.error;
                
                options.success = function(response) {
                    console.log('AJAX Success:', response);
                    if (originalSuccess) originalSuccess.apply(this, arguments);
                };
                
                options.error = function(xhr, status, error) {
                    console.log('AJAX Error:', {status, error, responseText: xhr.responseText});
                    if (originalError) originalError.apply(this, arguments);
                };
                
                return originalAjax.call(this, options);
            };
        });
        
        // Perform the drag
        console.log('8️⃣ Performing drag and drop...');
        await dealCard.dragTo(dropZone);
        console.log('✅ Drag and drop action completed\n');
        
        // Wait for AJAX
        console.log('9️⃣ Waiting for AJAX response...');
        await page.waitForTimeout(3000);
        
        // Check if card moved
        const movedCard = await page.$(`.pipeline-stage[data-stage="${targetStage.stage}"] .deal-card[data-deal-id="${dealId}"]`);
        const cardMoved = movedCard !== null;
        
        console.log(`\n🎯 RESULT: Card ${cardMoved ? 'successfully moved' : 'did NOT move'} to target stage\n`);
        
        // Check final state
        const finalStages = await page.$$eval('.pipeline-stage', elements => 
            elements.map(el => ({
                stage: el.getAttribute('data-stage'),
                dealCount: el.querySelectorAll('.deal-card').length
            }))
        );
        console.log('📊 Final pipeline state:');
        finalStages.forEach(s => console.log(`   - ${s.stage}: ${s.dealCount} deals`));
        
        // Leave browser open for manual inspection
        console.log('\n⏸️ Browser left open for inspection. Close it manually when done.');
        
    } catch (error) {
        console.error('❌ Error during test:', error);
    }
}

// Run the test
testDragDropFix();