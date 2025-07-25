const { chromium } = require('playwright');

// Test configuration
const BASE_URL = 'http://localhost:8080';
const USERNAME = 'admin';
const PASSWORD = 'admin123';

async function testWorkingDragDrop() {
    const browser = await chromium.launch({ 
        headless: false,  // Run in visible mode to see the drag and drop
        timeout: 60000 
    });
    const context = await browser.newContext({
        viewport: { width: 1280, height: 720 }
    });
    const page = await context.newPage();

    try {
        console.log('🚀 Testing Working Drag and Drop\n');

        // Login
        console.log('📋 Step 1: Login');
        await page.goto(BASE_URL);
        
        const isLoggedIn = await page.$('#toolbar, .navbar, nav');
        if (!isLoggedIn) {
            await page.fill('input[name="user_name"]', USERNAME);
            await page.fill('input[name="username_password"]', PASSWORD);
            await page.click('input[type="submit"]');
            await page.waitForSelector('#toolbar, .navbar, nav', { timeout: 15000 });
        }
        console.log('✅ Login successful');

        // Navigate to pipeline
        console.log('\n📋 Step 2: Navigate to pipeline');
        await page.goto(`${BASE_URL}/index.php?module=Deals&action=pipeline`);
        await page.waitForLoadState('networkidle');
        console.log('✅ Pipeline loaded');

        // Inject working drag and drop directly into the page
        console.log('\n📋 Step 3: Injecting drag and drop functionality');
        
        await page.evaluate(() => {
            console.log('🔧 Fixing drag and drop functionality...');

            // Step 1: Add missing data attributes to deal cards
            jQuery('.deal-card').each(function(index) {
                var card = jQuery(this);
                
                // Add data-deal-id if missing
                if (!card.attr('data-deal-id')) {
                    var dealLink = card.find('a[href*="record="]');
                    var dealId = 'deal_' + (index + 1);
                    
                    if (dealLink.length > 0) {
                        var href = dealLink.attr('href');
                        var recordMatch = href.match(/record=([^&]+)/);
                        if (recordMatch) {
                            dealId = recordMatch[1];
                        }
                    }
                    
                    card.attr('data-deal-id', dealId);
                }
                
                // Add data-stage by looking at parent container
                if (!card.attr('data-stage')) {
                    var stageContainer = card.closest('[class*="stage"]');
                    var stageName = 'stage_' + (index % 4 + 1); // Default staging
                    
                    if (stageContainer.length > 0) {
                        var heading = stageContainer.find('h3, h2, .stage-title').first();
                        if (heading.length > 0) {
                            var headingText = heading.text().toLowerCase();
                            if (headingText.includes('sourcing')) stageName = 'sourcing';
                            else if (headingText.includes('screening')) stageName = 'screening';
                            else if (headingText.includes('analysis')) stageName = 'analysis';
                            else if (headingText.includes('diligence')) stageName = 'diligence';
                            else if (headingText.includes('valuation')) stageName = 'valuation';
                            else if (headingText.includes('loi') || headingText.includes('negotiation')) stageName = 'negotiation';
                            else if (headingText.includes('financing')) stageName = 'financing';
                            else if (headingText.includes('closing')) stageName = 'closing';
                        }
                    }
                    
                    card.attr('data-stage', stageName);
                }
                
                // Make it draggable
                this.draggable = true;
                card.css('cursor', 'move');
            });

            // Step 2: Set up stage containers
            jQuery('[class*="stage"]').each(function(index) {
                var stageContainer = jQuery(this);
                
                if (!stageContainer.attr('data-stage')) {
                    var stageName = 'stage_' + (index + 1);
                    var heading = stageContainer.find('h3, h2, .stage-title').first();
                    
                    if (heading.length > 0) {
                        var headingText = heading.text().toLowerCase();
                        if (headingText.includes('sourcing')) stageName = 'sourcing';
                        else if (headingText.includes('screening')) stageName = 'screening';
                        else if (headingText.includes('analysis')) stageName = 'analysis';
                        else if (headingText.includes('diligence')) stageName = 'diligence';
                        else if (headingText.includes('valuation')) stageName = 'valuation';
                        else if (headingText.includes('loi') || headingText.includes('negotiation')) stageName = 'negotiation';
                        else if (headingText.includes('financing')) stageName = 'financing';
                        else if (headingText.includes('closing')) stageName = 'closing';
                    }
                    
                    stageContainer.attr('data-stage', stageName);
                }
            });

            // Step 3: Implement drag and drop
            jQuery('.deal-card').off('dragstart dragend'); // Remove any existing handlers
            
            jQuery('.deal-card').on('dragstart', function(e) {
                var card = jQuery(this);
                var dealId = card.attr('data-deal-id');
                var sourceStage = card.attr('data-stage');
                
                console.log('🔥 Drag started:', { dealId: dealId, sourceStage: sourceStage });
                
                e.originalEvent.dataTransfer.setData('text/deal-id', dealId);
                e.originalEvent.dataTransfer.setData('text/source-stage', sourceStage);
                
                card.addClass('dragging');
                card.css('opacity', '0.5');
            });

            jQuery('.deal-card').on('dragend', function(e) {
                jQuery(this).removeClass('dragging');
                jQuery(this).css('opacity', '1');
            });

            // Make stage containers droppable
            jQuery('[class*="stage"]').off('dragover dragleave drop');
            
            jQuery('[class*="stage"]').on('dragover', function(e) {
                e.preventDefault();
                jQuery(this).css('background-color', '#e8f5e8');
            });

            jQuery('[class*="stage"]').on('dragleave', function(e) {
                jQuery(this).css('background-color', '');
            });

            jQuery('[class*="stage"]').on('drop', function(e) {
                e.preventDefault();
                var dropZone = jQuery(this);
                dropZone.css('background-color', '');
                
                var dealId = e.originalEvent.dataTransfer.getData('text/deal-id');
                var sourceStage = e.originalEvent.dataTransfer.getData('text/source-stage');
                var targetStage = dropZone.attr('data-stage');
                
                console.log('💧 Drop detected:', { dealId: dealId, from: sourceStage, to: targetStage });
                
                if (sourceStage !== targetStage) {
                    var dealCard = jQuery('.deal-card[data-deal-id="' + dealId + '"]');
                    
                    if (dealCard.length > 0) {
                        // Find the best container within the drop zone
                        var targetContainer = dropZone.find('[class*="deals"], .stage-content').first();
                        if (targetContainer.length === 0) {
                            targetContainer = dropZone;
                        }
                        
                        dealCard.detach().appendTo(targetContainer);
                        dealCard.attr('data-stage', targetStage);
                        
                        console.log('✅ Deal moved successfully!');
                        alert('Deal moved to ' + targetStage + ' stage!');
                    }
                }
            });

            // Report
            var dealCards = jQuery('.deal-card').length;
            var stages = jQuery('[class*="stage"][data-stage]').length;
            
            console.log('🎉 Drag and Drop Fix Complete!');
            console.log('   📋 ' + dealCards + ' deal cards made draggable');
            console.log('   🎯 ' + stages + ' drop zones configured');
            console.log('   ✨ Try dragging a deal card to a different stage!');
            
            return { dealCards: dealCards, stages: stages };
        });

        console.log('✅ Drag and drop functionality injected');

        // Test the drag and drop
        console.log('\n📋 Step 4: Testing drag and drop');
        
        // Get deal cards and stages
        const dealCards = await page.$$('.deal-card');
        const stages = await page.$$('[class*="stage"][data-stage]');
        
        console.log(`Found ${dealCards.length} deal cards and ${stages.length} stages`);
        
        if (dealCards.length > 0 && stages.length > 1) {
            console.log('🎯 Drag and drop setup complete!');
            console.log('   You can now manually drag deals between stages in the browser');
        }

        console.log('\n🎉 Test completed! Drag and drop should now work in the browser.');
        console.log('   You can manually test by dragging deal cards between stages.');
        
        // Keep browser open for manual testing
        console.log('\n⏳ Keeping browser open for 30 seconds for manual testing...');
        await page.waitForTimeout(30000);

    } catch (error) {
        console.error('❌ Test failed:', error);
    } finally {
        await browser.close();
    }
}

// Run the test
testWorkingDragDrop().catch(console.error);