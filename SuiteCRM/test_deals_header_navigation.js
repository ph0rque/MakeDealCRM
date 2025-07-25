const { chromium } = require('playwright');

// Test configuration
const BASE_URL = 'http://localhost:8080';
const USERNAME = 'admin';
const PASSWORD = 'admin123';

async function testDealsHeaderNavigation() {
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

    try {
        console.log('🚀 Testing Deals Header Navigation\n');

        // Step 1: Login
        console.log('📋 Step 1: Login to SuiteCRM');
        await page.goto(BASE_URL);
        
        // Check if already logged in
        const isLoggedIn = await page.$('#toolbar, .navbar, nav');
        if (!isLoggedIn) {
            await page.fill('input[name="user_name"]', USERNAME);
            await page.fill('input[name="username_password"]', PASSWORD);
            await page.click('input[type="submit"]');
            await page.waitForSelector('#toolbar, .navbar, nav, #content', { timeout: 15000 });
        }
        console.log('✅ Login successful');

        // Step 2: Check for DEALS in navigation
        console.log('\n📋 Step 2: Looking for DEALS in navigation');
        
        // Take a screenshot to see the current page
        await page.screenshot({ path: 'debug-navigation.png' });
        console.log('📸 Screenshot saved as debug-navigation.png');

        // Try multiple ways to find the DEALS link
        const dealSearchSelectors = [
            'a:has-text("DEALS")',
            'a:has-text("Deals")',
            'a[href*="module=Deals"]',
            'text=DEALS',
            'text=Deals',
            '.navbar a:has-text("Deals")',
            '#toolbar a:has-text("Deals")',
            '.tab a:has-text("Deals")',
            'li:has-text("Deals") a'
        ];

        let dealsLink = null;
        for (const selector of dealSearchSelectors) {
            try {
                dealsLink = await page.$(selector);
                if (dealsLink) {
                    console.log(`✅ Found DEALS link with selector: ${selector}`);
                    break;
                }
            } catch (e) {
                // Continue to next selector
            }
        }

        if (!dealsLink) {
            console.log('❌ DEALS link not found in navigation');
            console.log('📋 Available navigation items:');
            
            // List all navigation links for debugging
            const navLinks = await page.$$eval('a', links => 
                links.map(link => ({
                    text: link.textContent.trim(),
                    href: link.href
                })).filter(link => link.text && link.text.length > 0)
            );
            
            navLinks.slice(0, 20).forEach(link => {
                console.log(`  - "${link.text}" -> ${link.href}`);
            });
            
            // Try direct navigation instead
            console.log('\n📋 Step 3: Testing direct navigation to pipeline');
            await page.goto(`${BASE_URL}/index.php?module=Deals&action=pipeline`);
            await page.waitForLoadState('networkidle');
            
            const currentUrl = page.url();
            console.log(`Current URL: ${currentUrl}`);
            
            // Check if pipeline content is visible
            const pipelineStages = await page.$$('.pipeline-stage, .kanban-column, .kanban-stage');
            console.log(`Pipeline stages found: ${pipelineStages.length}`);
            
            if (pipelineStages.length > 0) {
                console.log('✅ Direct navigation to pipeline works');
                
                // Check stage names
                const stageNames = await page.$$eval('.pipeline-stage h3, .kanban-column h3, .pipeline-stage .stage-name', 
                    elements => elements.map(el => el.textContent.trim())
                );
                console.log('Pipeline stages:', stageNames);
            } else {
                console.log('❌ Pipeline view not loading properly');
            }
            
        } else {
            // Step 3: Click on DEALS and verify pipeline loads
            console.log('\n📋 Step 3: Clicking DEALS and checking for pipeline');
            
            // Check if element is visible first
            const isVisible = await dealsLink.isVisible();
            console.log(`DEALS link is visible: ${isVisible}`);
            
            if (!isVisible) {
                // Try to scroll to element or wait for it to be visible
                await dealsLink.scrollIntoViewIfNeeded();
                await page.waitForTimeout(1000);
            }
            
            // Try force click if normal click fails
            try {
                await dealsLink.click();
            } catch (e) {
                console.log('Normal click failed, trying force click...');
                await dealsLink.click({ force: true });
            }
            
            await page.waitForLoadState('networkidle');
            
            const currentUrl = page.url();
            console.log(`Navigated to: ${currentUrl}`);
            
            // Check if we're on the pipeline view
            const isOnPipeline = currentUrl.includes('action=pipeline') || currentUrl.includes('module=Deals');
            console.log(`Is on pipeline: ${isOnPipeline}`);
            
            // Check if pipeline content is visible
            const pipelineStages = await page.$$('.pipeline-stage, .kanban-column');
            console.log(`Pipeline stages visible: ${pipelineStages.length > 0}`);
            
            if (isOnPipeline && pipelineStages.length > 0) {
                console.log('✅ DEALS header link successfully navigates to pipeline view');
            } else {
                console.log('❌ DEALS header link does not properly navigate to pipeline view');
            }
        }

        // Take final screenshot
        await page.screenshot({ path: 'debug-final.png' });
        console.log('📸 Final screenshot saved as debug-final.png');

    } catch (error) {
        console.error('❌ Test failed:', error);
        await page.screenshot({ path: 'debug-error.png' });
    } finally {
        await browser.close();
    }
}

// Run the test
testDealsHeaderNavigation().catch(console.error);