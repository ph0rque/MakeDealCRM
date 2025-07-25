const { chromium } = require('playwright');

async function testDealNavigation() {
    console.log('Testing deal card navigation...');
    
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    
    try {
        // Login
        await page.goto('http://localhost:8080');
        await page.fill('input[name="user_name"]', 'admin');
        await page.fill('input[name="password"]', 'admin');
        await page.click('input[type="submit"]');
        
        // Navigate to pipeline
        await page.goto('http://localhost:8080/index.php?module=mdeal_Deals&action=Pipeline');
        
        // Wait for pipeline to load
        await page.waitForSelector('.pipeline-kanban-container', { timeout: 10000 });
        
        // Check the HTML content to see the actual onclick handlers
        const dealCardHTML = await page.evaluate(() => {
            const dealCard = document.querySelector('.deal-card .view-deal');
            return dealCard ? dealCard.outerHTML : null;
        });
        
        if (dealCardHTML) {
            console.log('Deal card HTML:', dealCardHTML);
            
            if (dealCardHTML.includes('window.location.href')) {
                console.log('✅ SUCCESS: Deal cards now open in the SAME window');
            } else if (dealCardHTML.includes('window.open')) {
                console.log('❌ FAILED: Deal cards still open in a NEW window');
            }
        } else {
            console.log('No deal cards found in pipeline view');
        }
        
    } catch (error) {
        console.error('Test error:', error.message);
    } finally {
        await browser.close();
    }
}

testDealNavigation();