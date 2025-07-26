const { test, expect } = require('@playwright/test');

test.describe('Deal Detail View Access', () => {
    test.beforeEach(async ({ page }) => {
        // Navigate to login page
        await page.goto('http://localhost:8080');
        
        // Check which login form is present
        const oldLoginForm = await page.locator('#user_name').count();
        const newLoginForm = await page.locator('input[name="username"]').count();
        
        if (oldLoginForm > 0) {
            // Old login form
            await page.fill('#user_name', 'admin');
            await page.fill('#username_password', 'admin123');
            await page.click('#bigbutton');
        } else if (newLoginForm > 0) {
            // New login form
            await page.fill('input[name="username"]', 'admin');
            await page.fill('input[name="password"]', 'admin123');
            await page.click('button:has-text("LOG IN")');
        }
        
        // Wait for dashboard
        await page.waitForSelector('.navbar', { timeout: 10000 });
    });

    test('should open deal details in same window with proper access', async ({ page }) => {
        // Navigate to Deals Pipeline
        await page.goto('http://localhost:8080/index.php?module=Deals&action=pipeline');
        await page.waitForSelector('.pipeline-board', { timeout: 10000 });
        
        // Get the first deal card
        const dealCard = await page.locator('.deal-card').first();
        await expect(dealCard).toBeVisible();
        
        // Get the deal link
        const dealLink = await dealCard.locator('.deal-name a').first();
        await expect(dealLink).toBeVisible();
        
        // Get the href to verify it uses Deals module
        const href = await dealLink.getAttribute('href');
        console.log('Deal link href:', href);
        expect(href).toContain('module=Deals');
        expect(href).toContain('action=DetailView');
        
        // Verify no target="_blank" attribute
        const target = await dealLink.getAttribute('target');
        expect(target).toBeNull();
        
        // Get the current page count to ensure no new window opens
        const pageCount = page.context().pages().length;
        
        // Click the deal link
        await dealLink.click();
        
        // Wait for navigation
        await page.waitForLoadState('networkidle');
        
        // Verify no new window was opened
        expect(page.context().pages().length).toBe(pageCount);
        
        // Verify we're on the detail view page
        await expect(page).toHaveURL(/module=Deals.*action=DetailView/);
        
        // Verify we have access (no redirect to home)
        const currentUrl = page.url();
        expect(currentUrl).not.toContain('module=Home');
        
        // Verify detail view elements are visible - Financial & Valuation Hub is unique to deal detail view
        await page.waitForSelector('text=Financial & Valuation Hub', { timeout: 10000 });
        
        // Verify no access denied message
        const bodyText = await page.locator('body').textContent();
        expect(bodyText).not.toContain('You do not have access');
        expect(bodyText).not.toContain('Redirect to Home');
        
        console.log('✅ Deal detail view opened successfully in same window with proper access');
    });

    test('should navigate between pipeline and detail view seamlessly', async ({ page }) => {
        // Navigate to Deals Pipeline
        await page.goto('http://localhost:8080/index.php?module=Deals&action=pipeline');
        await page.waitForSelector('.pipeline-board', { timeout: 10000 });
        
        // Click on a deal
        const dealLink = await page.locator('.deal-card .deal-name a').first();
        const dealName = await dealLink.textContent();
        await dealLink.click();
        
        // Verify we're on detail view
        await page.waitForLoadState('networkidle');
        await expect(page).toHaveURL(/module=Deals.*action=DetailView/);
        
        // Navigate back to pipeline using browser back
        await page.goBack();
        
        // Verify we're back on pipeline view
        await expect(page).toHaveURL(/module=Deals.*action=(pipeline|Pipeline)/);
        await expect(page.locator('.pipeline-board')).toBeVisible();
        
        console.log('✅ Navigation between pipeline and detail view works correctly');
    });
});