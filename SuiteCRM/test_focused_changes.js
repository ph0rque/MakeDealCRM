const { chromium } = require('playwright');

// Test configuration
const BASE_URL = 'http://localhost:8080';
const USERNAME = 'admin';
const PASSWORD = 'admin';

async function runFocusedTests() {
    console.log('🎯 Testing Same-Window Navigation and No-Alert Drag/Drop\n');
    
    const browser = await chromium.launch({ 
        headless: false, // Show browser for debugging
        timeout: 60000 
    });
    const context = await browser.newContext({
        viewport: { width: 1280, height: 720 }
    });
    const page = await context.newPage();
    
    let testsPassed = 0;
    let testsFailed = 0;
    
    function logTest(name, passed, details = '') {
        if (passed) {
            console.log(`✅ ${name}`);
            testsPassed++;
        } else {
            console.log(`❌ ${name}`);
            if (details) console.log(`   Details: ${details}`);
            testsFailed++;
        }
    }
    
    try {
        // 1. Login
        console.log('1️⃣ Logging in...');
        await page.goto(BASE_URL);
        await page.fill('input[name="user_name"]', USERNAME);
        await page.fill('input[name="username_password"]', PASSWORD);
        await page.click('input#bigbutton');
        await page.waitForURL('**/index.php**', { timeout: 10000 });
        logTest('Login successful', true);
        
        // 2. Navigate to Deals pipeline
        console.log('\n2️⃣ Navigating to pipeline view...');
        await page.goto(`${BASE_URL}/index.php?module=Deals&action=pipeline`);
        await page.waitForTimeout(5000); // Wait for JS to initialize
        
        // Check if pipeline container exists
        const pipelineExists = await page.$('#pipeline-container');
        logTest('Pipeline container renders', !!pipelineExists);
        
        // 3. Check if PipelineKanbanView.js is loaded
        console.log('\n3️⃣ Checking if our JavaScript changes are loaded...');
        const scriptLoaded = await page.evaluate(() => {
            return typeof PipelineKanbanView !== 'undefined';
        });
        logTest('PipelineKanbanView.js is loaded', scriptLoaded);
        
        // 4. Create a test deal if needed
        const dealCards = await page.$$('.deal-card');
        if (dealCards.length === 0) {
            console.log('\n4️⃣ No deals found, creating a test deal...');
            await page.goto(`${BASE_URL}/index.php?module=Opportunities&action=EditView`);
            await page.waitForTimeout(2000);
            
            // Fill in opportunity form
            await page.fill('input[name="name"]', 'Test Deal for Navigation');
            const accountField = await page.$('input[name="account_name"]');
            if (accountField) {
                await page.fill('input[name="account_name"]', 'Test Company');
            }
            await page.fill('input[name="amount"]', '1000000');
            
            // Save
            await page.click('input[title="Save"], #SAVE');
            await page.waitForTimeout(3000);
            
            // Go back to pipeline
            await page.goto(`${BASE_URL}/index.php?module=Deals&action=pipeline`);
            await page.waitForTimeout(5000);
        }
        
        // 5. Test same-window navigation
        console.log('\n5️⃣ Testing same-window navigation...');
        
        // Get the onclick handler from view button
        const viewButtonOnclick = await page.evaluate(() => {
            const btn = document.querySelector('.deal-card .view-deal');
            return btn ? btn.getAttribute('onclick') : null;
        });
        
        if (viewButtonOnclick) {
            logTest('View button found', true);
            logTest('View button uses window.location.href', 
                   viewButtonOnclick.includes('window.location.href'),
                   `onclick: ${viewButtonOnclick}`);
        } else {
            // Check if our JS file is actually being used
            const jsContent = await page.evaluate(async () => {
                try {
                    const scripts = Array.from(document.querySelectorAll('script'));
                    const pipelineScript = scripts.find(s => s.src && s.src.includes('PipelineKanbanView.js'));
                    if (pipelineScript) {
                        const response = await fetch(pipelineScript.src);
                        const text = await response.text();
                        return {
                            url: pipelineScript.src,
                            hasLocationHref: text.includes('window.location.href'),
                            hasWindowOpen: text.includes('window.open')
                        };
                    }
                } catch (e) {
                    return null;
                }
            });
            
            if (jsContent) {
                logTest('PipelineKanbanView.js contains window.location.href', jsContent.hasLocationHref);
                logTest('PipelineKanbanView.js removed window.open', !jsContent.hasWindowOpen);
            } else {
                logTest('Could not verify JavaScript changes', false, 'Script not accessible');
            }
        }
        
        // 6. Test drag/drop alerts are disabled
        console.log('\n6️⃣ Testing drag/drop without alerts...');
        
        const jsAlertCheck = await page.evaluate(async () => {
            try {
                const scripts = Array.from(document.querySelectorAll('script'));
                const pipelineScript = scripts.find(s => s.src && s.src.includes('PipelineKanbanView.js'));
                if (pipelineScript) {
                    const response = await fetch(pipelineScript.src);
                    const text = await response.text();
                    return {
                        hasCommentedAlerts: text.includes('// this.showSuccessMessage') || 
                                          text.includes('// this.showErrorMessage'),
                        alertsStillActive: text.includes('this.showSuccessMessage(') && 
                                         !text.includes('// this.showSuccessMessage(')
                    };
                }
            } catch (e) {
                return null;
            }
        });
        
        if (jsAlertCheck) {
            logTest('Alert messages are commented out', jsAlertCheck.hasCommentedAlerts);
            logTest('No active alert messages remain', !jsAlertCheck.alertsStillActive);
        }
        
        // 7. Summary
        console.log('\n📊 FOCUSED TEST SUMMARY');
        console.log('='.repeat(30));
        console.log(`✅ Passed: ${testsPassed}`);
        console.log(`❌ Failed: ${testsFailed}`);
        console.log(`Total: ${testsPassed + testsFailed}`);
        
        if (testsFailed === 0) {
            console.log('\n🎉 All focused tests passed! The changes are working correctly.');
        } else {
            console.log('\n⚠️ Some tests failed. Check the details above.');
        }
        
        // Keep browser open for 10 seconds to see results
        await page.waitForTimeout(10000);
        
    } catch (error) {
        console.error('Fatal error:', error);
    } finally {
        await browser.close();
    }
}

runFocusedTests();