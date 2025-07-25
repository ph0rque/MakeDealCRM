const { chromium } = require('playwright');

async function testOurChanges() {
    console.log('🔍 Testing our specific changes...\n');
    
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    
    try {
        // Login
        await page.goto('http://localhost:8080');
        await page.fill('input[name="user_name"]', 'admin');
        await page.fill('input[name="username_password"]', 'admin');
        await page.click('input#bigbutton');
        await page.waitForURL('**/index.php**', { timeout: 10000 });
        console.log('✅ Logged in successfully');
        
        // Navigate to pipeline
        await page.goto('http://localhost:8080/index.php?module=Deals&action=Pipeline');
        await page.waitForTimeout(3000); // Wait for JS to load
        
        // Check if pipeline loads
        const pipelineContainer = await page.$('.pipeline-kanban-container');
        console.log(pipelineContainer ? '✅ Pipeline view loaded' : '❌ Pipeline view NOT loaded');
        
        // Create a test deal if none exist
        const dealCards = await page.$$('.deal-card');
        if (dealCards.length === 0) {
            console.log('📝 Creating test deal...');
            await page.goto('http://localhost:8080/index.php?module=Deals&action=EditView');
            await page.waitForTimeout(2000);
            
            const nameField = await page.$('input[name="name"]');
            if (nameField) {
                await page.fill('input[name="name"]', 'Test Deal for Navigation Check');
                const saveButton = await page.$('input[title="Save"], #SAVE');
                if (saveButton) {
                    await saveButton.click();
                    await page.waitForTimeout(3000);
                }
            }
            
            // Go back to pipeline
            await page.goto('http://localhost:8080/index.php?module=Deals&action=Pipeline');
            await page.waitForTimeout(3000);
        }
        
        // TEST 1: Check onclick handlers
        console.log('\n📋 TEST 1: Same Window Navigation');
        const viewButtonOnclick = await page.evaluate(() => {
            const viewButton = document.querySelector('.deal-card .view-deal');
            return viewButton ? viewButton.getAttribute('onclick') : null;
        });
        
        const editButtonOnclick = await page.evaluate(() => {
            const editButton = document.querySelector('.deal-card .edit-deal');
            return editButton ? editButton.getAttribute('onclick') : null;
        });
        
        if (viewButtonOnclick) {
            console.log('View button onclick:', viewButtonOnclick);
            console.log(viewButtonOnclick.includes('window.location.href') ? 
                '✅ View button uses window.location.href (same window)' : 
                '❌ View button still uses window.open (new window)');
        } else {
            console.log('⚠️ No view button found');
        }
        
        if (editButtonOnclick) {
            console.log('Edit button onclick:', editButtonOnclick);
            console.log(editButtonOnclick.includes('window.location.href') ? 
                '✅ Edit button uses window.location.href (same window)' : 
                '❌ Edit button still uses window.open (new window)');
        } else {
            console.log('⚠️ No edit button found');
        }
        
        // TEST 2: Check for alerts during drag/drop
        console.log('\n📋 TEST 2: Drag and Drop without Alerts');
        
        // Monitor console messages
        const consoleMessages = [];
        page.on('console', msg => {
            if (msg.text().includes('Success') || msg.text().includes('Loading')) {
                consoleMessages.push(msg.text());
            }
        });
        
        // Check if drag/drop functions have been modified
        const dragDropCode = await page.evaluate(() => {
            // Check if the showSuccessMessage calls are commented out
            const scriptTags = Array.from(document.querySelectorAll('script'));
            const pipelineScript = scriptTags.find(s => s.src && s.src.includes('PipelineKanbanView.js'));
            return pipelineScript ? pipelineScript.src : null;
        });
        
        if (dragDropCode) {
            console.log('✅ PipelineKanbanView.js is loaded');
            
            // Check the actual file content
            const response = await page.goto(dragDropCode);
            const content = await response.text();
            
            const hasCommentedAlerts = content.includes('// this.showSuccessMessage') || 
                                      content.includes('// this.showErrorMessage');
            
            console.log(hasCommentedAlerts ? 
                '✅ Alert messages are commented out in drag/drop' : 
                '❌ Alert messages are still active in drag/drop');
        }
        
        console.log('\n✅ All tests completed!');
        
    } catch (error) {
        console.error('❌ Test error:', error.message);
    } finally {
        await browser.close();
    }
}

testOurChanges();