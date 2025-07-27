/**
 * Simple Navigation Test to verify "Create Deals" appears instead of "Create Opportunities"
 */

const playwright = require('@playwright/test');

(async () => {
    try {
        const browser = await playwright.chromium.launch({ headless: false });
        const context = await browser.newContext();
        const page = await context.newPage();

        console.log('Navigating to SuiteCRM...');
        await page.goto('http://localhost:8080/');

        // Wait for login form
        await page.waitForSelector('input[name="user_name"]', { timeout: 10000 });

        // Login with admin credentials
        await page.fill('input[name="user_name"]', 'admin');
        await page.fill('input[name="user_password"]', 'admin');
        await page.click('input[type="submit"]');

        // Wait for main page to load
        await page.waitForSelector('.moduleTab', { timeout: 10000 });

        console.log('Checking navigation for "Create Deals"...');

        // Look for Deals tab
        const dealsTab = await page.locator('text="Deals"');
        if (await dealsTab.count() > 0) {
            console.log('✅ "Deals" tab found in navigation');
            
            // Click on Deals tab
            await dealsTab.first().click();
            await page.waitForTimeout(2000);

            // Look for "Create Deals" link
            const createDealsLink = await page.locator('text="Create Deals"');
            if (await createDealsLink.count() > 0) {
                console.log('✅ "Create Deals" link found - NAVIGATION FIX SUCCESSFUL!');
            } else {
                console.log('❌ "Create Deals" link not found');
                
                // Check if "Create Opportunities" still exists
                const createOpportunities = await page.locator('text="Create Opportunities"');
                if (await createOpportunities.count() > 0) {
                    console.log('❌ "Create Opportunities" still exists - fix incomplete');
                }
            }
        } else {
            console.log('❌ "Deals" tab not found in navigation');
        }

        // Take a screenshot
        await page.screenshot({ path: 'navigation_test_result.png', fullPage: true });
        console.log('Screenshot saved as navigation_test_result.png');

        await browser.close();
    } catch (error) {
        console.error('Test failed:', error);
    }
})();