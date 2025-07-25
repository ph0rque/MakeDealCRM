const { chromium } = require('playwright');

const BASE_URL = 'http://localhost:8080';
const USERNAME = 'admin';
const PASSWORD = 'admin123';

async function testDragDrop() {
    const browser = await chromium.launch({ 
        headless: false,
        slowMo: 500 
    });
    const page = await browser.newPage();
    
    // Monitor console logs
    page.on('console', msg => {
        if (msg.type() === 'log' || msg.type() === 'error') {
            console.log(`Browser ${msg.type()}: ${msg.text()}`);
        }
    });
    
    try {
        console.log('🚀 Testing Drag and Drop with Fixed View\n');
        
        // Login
        console.log('1️⃣ Logging in...');
        await page.goto(BASE_URL);
        await page.fill('input[name="user_name"]', USERNAME);
        await page.fill('input[name="username_password"]', PASSWORD);
        await page.click('input[type="submit"]');
        await page.waitForSelector('#toolbar, .navbar');
        console.log('✅ Logged in\n');
        
        // Navigate to pipeline
        console.log('2️⃣ Navigating to pipeline...');
        await page.goto(`${BASE_URL}/index.php?module=Deals&action=pipeline`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(3000); // Wait for JS to load
        console.log('✅ Pipeline loaded\n');
        
        // Check if PipelineView is available
        const jsCheck = await page.evaluate(() => {
            return {
                jQuery: typeof jQuery !== 'undefined',
                PipelineView: typeof PipelineView !== 'undefined',
                pipelineJS: Array.from(document.querySelectorAll('script')).some(s => s.src.includes('pipeline.js'))
            };
        });
        console.log('3️⃣ JavaScript check:', jsCheck);
        
        // Check for deal cards
        const dealCards = await page.$$('.deal-card');
        console.log(`\n4️⃣ Found ${dealCards.length} deal cards`);
        
        if (dealCards.length > 0) {
            // Get first deal info
            const dealInfo = await dealCards[0].evaluate(el => ({
                id: el.getAttribute('data-deal-id'),
                stage: el.getAttribute('data-stage'),
                name: el.querySelector('.deal-name')?.textContent.trim()
            }));
            console.log(`\n5️⃣ Testing with deal: "${dealInfo.name}" (Stage: ${dealInfo.stage})`);
            
            // Find a different stage to drop to
            const stages = await page.$$eval('.pipeline-stage', elements => 
                elements.map(el => el.getAttribute('data-stage'))
            );
            const targetStage = stages.find(s => s !== dealInfo.stage);
            
            if (targetStage) {
                console.log(`\n6️⃣ Dragging to stage: ${targetStage}`);
                
                // Perform drag and drop
                const dropZone = await page.$(`.pipeline-stage[data-stage="${targetStage}"] .stage-body`);
                await dealCards[0].dragTo(dropZone);
                
                console.log('✅ Drag and drop performed');
                
                // Wait for any AJAX calls
                await page.waitForTimeout(2000);
                
                // Check if card moved
                const movedCard = await page.$(`.pipeline-stage[data-stage="${targetStage}"] .deal-card[data-deal-id="${dealInfo.id}"]`);
                if (movedCard) {
                    console.log('✅ Deal successfully moved!');
                } else {
                    console.log('❌ Deal did not move');
                }
            }
        }
        
        console.log('\n✨ Test complete. Browser left open for inspection.');
        
    } catch (error) {
        console.error('❌ Error:', error);
    }
}

testDragDrop();