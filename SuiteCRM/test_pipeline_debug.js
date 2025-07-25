const { chromium } = require('playwright');

async function debugPipeline() {
    const browser = await chromium.launch({ headless: false });
    const page = await browser.newPage();
    
    // Capture console messages and errors
    page.on('console', msg => {
        console.log(`Browser console [${msg.type()}]:`, msg.text());
    });
    
    page.on('pageerror', error => {
        console.error('Page error:', error.message);
    });
    
    try {
        // Login
        await page.goto('http://localhost:8080');
        await page.fill('input[name="user_name"]', 'admin');
        await page.fill('input[name="username_password"]', 'admin');
        await page.click('input#bigbutton');
        await page.waitForURL('**/index.php**', { timeout: 10000 });
        console.log('✅ Logged in');
        
        // Navigate to pipeline
        console.log('\nNavigating to pipeline...');
        await page.goto('http://localhost:8080/index.php?module=mdeal_Deals&action=Pipeline');
        await page.waitForTimeout(5000); // Wait for JS to load
        
        // Check what's loaded
        const pageContent = await page.evaluate(() => {
            const container = document.getElementById('pipeline-container');
            const stages = document.querySelectorAll('.pipeline-stage-column');
            const deals = document.querySelectorAll('.deal-card');
            const scripts = Array.from(document.querySelectorAll('script')).map(s => s.src || 'inline');
            
            return {
                containerExists: !!container,
                containerHTML: container ? container.innerHTML.substring(0, 200) : null,
                stageCount: stages.length,
                dealCount: deals.length,
                pipelineStages: window.pipelineStages,
                pipelineDeals: window.pipelineDeals,
                pipelineView: !!window.pipelineView,
                scripts: scripts.filter(s => s.includes('Pipeline'))
            };
        });
        
        console.log('\nPage analysis:');
        console.log('- Container exists:', pageContent.containerExists);
        console.log('- Container HTML:', pageContent.containerHTML);
        console.log('- Stage count:', pageContent.stageCount);
        console.log('- Deal count:', pageContent.dealCount);
        console.log('- Pipeline stages data:', !!pageContent.pipelineStages);
        console.log('- Pipeline deals data:', !!pageContent.pipelineDeals);
        console.log('- Pipeline view initialized:', pageContent.pipelineView);
        console.log('- Pipeline scripts loaded:', pageContent.scripts);
        
        // Keep browser open for manual inspection
        console.log('\nKeeping browser open for 30 seconds...');
        await page.waitForTimeout(30000);
        
    } catch (error) {
        console.error('Error:', error.message);
    } finally {
        await browser.close();
    }
}

debugPipeline();