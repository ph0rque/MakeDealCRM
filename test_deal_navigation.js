const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: false });
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
        
        // Look for deal cards and check their onclick handlers
        const dealCards = await page.$$('.deal-card .view-deal');
        if (dealCards.length > 0) {
            // Get the onclick attribute of the first deal card
            const onclickValue = await dealCards[0].getAttribute('onclick');
            console.log('Deal card onclick:', onclickValue);
            
            // Check if it uses window.location.href (same window) or window.open (new window)
            if (onclickValue.includes('window.location.href')) {
                console.log('✅ Deal cards open in same window');
            } else if (onclickValue.includes('window.open')) {
                console.log('❌ Deal cards still open in new window');
            }
        } else {
            console.log('No deal cards found - creating a test deal...');
            
            // Try to create a deal via the API or navigate to create form
            await page.goto('http://localhost:8080/index.php?module=mdeal_Deals&action=EditView');
            await page.waitForTimeout(2000);
            
            // Fill in basic deal info
            const nameField = await page.$('input[name="name"]');
            if (nameField) {
                await nameField.fill('Test Deal for Navigation');
                await page.click('input[title="Save"]');
                await page.waitForTimeout(3000);
                
                // Go back to pipeline
                await page.goto('http://localhost:8080/index.php?module=mdeal_Deals&action=Pipeline');
                await page.waitForSelector('.pipeline-kanban-container', { timeout: 10000 });
                
                // Check again
                const dealCardsAfter = await page.$$('.deal-card .view-deal');
                if (dealCardsAfter.length > 0) {
                    const onclickValue = await dealCardsAfter[0].getAttribute('onclick');
                    console.log('Deal card onclick after creating:', onclickValue);
                    
                    if (onclickValue.includes('window.location.href')) {
                        console.log('✅ Deal cards open in same window');
                    } else if (onclickValue.includes('window.open')) {
                        console.log('❌ Deal cards still open in new window');
                    }
                }
            }
        }
        
    } catch (error) {
        console.error('Error during test:', error.message);
    } finally {
        await browser.close();
    }
})();