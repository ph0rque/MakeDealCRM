const { chromium } = require('playwright');

async function quickCheck() {
    const browser = await chromium.launch({ headless: false });
    const page = await browser.newPage();
    
    try {
        console.log('Navigating to http://localhost:8080...');
        await page.goto('http://localhost:8080', { waitUntil: 'load', timeout: 10000 });
        
        console.log('Page loaded. URL:', page.url());
        console.log('Page title:', await page.title());
        
        // Check what's on the page
        const bodyText = await page.evaluate(() => document.body.innerText);
        console.log('Page contains:', bodyText.substring(0, 200) + '...');
        
        // Check for login form
        const userField = await page.$('input[name="user_name"]');
        const passField = await page.$('input[name="password"]');
        const passFieldAlt = await page.$('input[name="username_password"]');
        const passFieldType = await page.$('input[type="password"]');
        
        console.log('Username field found:', !!userField);
        console.log('Password field found:', !!passField);
        console.log('Password field alt found:', !!passFieldAlt);
        console.log('Password field by type found:', !!passFieldType);
        
        // Get all input fields for debugging
        const allInputs = await page.$$eval('input', inputs => 
            inputs.map(i => ({name: i.name, type: i.type, id: i.id}))
        );
        console.log('All input fields:', allInputs);
        
        // Try to login
        if (userField && passField) {
            await page.fill('input[name="user_name"]', 'admin');
            await page.fill('input[name="password"]', 'admin');
            
            const submitButton = await page.$('input[type="submit"], button[type="submit"], #bigbutton');
            console.log('Submit button found:', !!submitButton);
            
            if (submitButton) {
                await submitButton.click();
                await page.waitForTimeout(3000);
                console.log('After login URL:', page.url());
            }
        }
        
    } catch (error) {
        console.error('Error:', error.message);
    } finally {
        await page.waitForTimeout(5000); // Keep browser open for 5 seconds
        await browser.close();
    }
}

quickCheck();