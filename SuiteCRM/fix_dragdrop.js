// Quick fix for drag and drop functionality
// This script can be run in the browser console or added to the pipeline page

console.log('🔧 Fixing drag and drop functionality...');

// Step 1: Add missing data attributes to deal cards
jQuery('.deal-card').each(function(index) {
    var card = jQuery(this);
    
    // Add data-deal-id if missing (use a unique identifier)
    if (!card.attr('data-deal-id')) {
        // Try to extract ID from existing links or create a temporary one
        var dealLink = card.find('a[href*="record="]');
        var dealId = 'deal_' + (index + 1); // fallback ID
        
        if (dealLink.length > 0) {
            var href = dealLink.attr('href');
            var recordMatch = href.match(/record=([^&]+)/);
            if (recordMatch) {
                dealId = recordMatch[1];
            }
        }
        
        card.attr('data-deal-id', dealId);
    }
    
    // Add data-stage if missing (try to determine from parent container)
    if (!card.attr('data-stage')) {
        // Look for stage indicators in the parent container
        var stageContainer = card.closest('[class*="stage"]');
        var stageName = 'unknown';
        
        // Try to extract stage from container classes or text
        if (stageContainer.length > 0) {
            var classes = stageContainer.attr('class');
            // Look for common stage keywords
            if (classes.includes('sourcing')) stageName = 'sourcing';
            else if (classes.includes('screening')) stageName = 'screening';
            else if (classes.includes('analysis')) stageName = 'analysis_outreach';
            else if (classes.includes('diligence')) stageName = 'due_diligence';
            else if (classes.includes('valuation')) stageName = 'valuation_structuring';
            else if (classes.includes('loi') || classes.includes('negotiation')) stageName = 'loi_negotiation';
            else if (classes.includes('financing')) stageName = 'financing';
            else if (classes.includes('closing')) stageName = 'closing';
            else {
                // Try to get from heading text
                var heading = stageContainer.find('h3, h2, .stage-title').first();
                if (heading.length > 0) {
                    var headingText = heading.text().toLowerCase();
                    if (headingText.includes('sourcing')) stageName = 'sourcing';
                    else if (headingText.includes('screening')) stageName = 'screening';
                    else if (headingText.includes('analysis')) stageName = 'analysis_outreach';
                    else if (headingText.includes('diligence')) stageName = 'due_diligence';
                    else if (headingText.includes('valuation')) stageName = 'valuation_structuring';
                    else if (headingText.includes('loi') || headingText.includes('negotiation')) stageName = 'loi_negotiation';
                    else if (headingText.includes('financing')) stageName = 'financing';
                    else if (headingText.includes('closing')) stageName = 'closing';
                }
            }
        }
        
        card.attr('data-stage', stageName);
    }
    
    // Make it draggable
    this.draggable = true;
    card.css('cursor', 'move');
});

// Step 2: Add data-stage attributes to stage containers and make them droppable
jQuery('[class*="stage"]').each(function() {
    var stageContainer = jQuery(this);
    
    if (!stageContainer.attr('data-stage')) {
        // Try to determine stage from heading or class
        var stageName = 'unknown';
        var heading = stageContainer.find('h3, h2, .stage-title').first();
        
        if (heading.length > 0) {
            var headingText = heading.text().toLowerCase();
            if (headingText.includes('sourcing')) stageName = 'sourcing';
            else if (headingText.includes('screening')) stageName = 'screening';
            else if (headingText.includes('analysis')) stageName = 'analysis_outreach';
            else if (headingText.includes('diligence')) stageName = 'due_diligence';
            else if (headingText.includes('valuation')) stageName = 'valuation_structuring';
            else if (headingText.includes('loi') || headingText.includes('negotiation')) stageName = 'loi_negotiation';
            else if (headingText.includes('financing')) stageName = 'financing';
            else if (headingText.includes('closing')) stageName = 'closing';
        }
        
        stageContainer.attr('data-stage', stageName);
    }
});

// Step 3: Implement drag and drop functionality
jQuery('.deal-card').on('dragstart', function(e) {
    var card = jQuery(this);
    var dealId = card.attr('data-deal-id');
    var sourceStage = card.attr('data-stage');
    
    console.log('🔥 Drag started:', { dealId: dealId, sourceStage: sourceStage });
    
    e.originalEvent.dataTransfer.setData('text/deal-id', dealId);
    e.originalEvent.dataTransfer.setData('text/source-stage', sourceStage);
    
    card.addClass('dragging');
});

jQuery('.deal-card').on('dragend', function(e) {
    jQuery(this).removeClass('dragging');
});

// Make stage containers droppable
jQuery('[class*="stage"]').on('dragover', function(e) {
    e.preventDefault();
    jQuery(this).addClass('drag-over');
});

jQuery('[class*="stage"]').on('dragleave', function(e) {
    jQuery(this).removeClass('drag-over');
});

jQuery('[class*="stage"]').on('drop', function(e) {
    e.preventDefault();
    var dropZone = jQuery(this);
    dropZone.removeClass('drag-over');
    
    var dealId = e.originalEvent.dataTransfer.getData('text/deal-id');
    var sourceStage = e.originalEvent.dataTransfer.getData('text/source-stage');
    var targetStage = dropZone.attr('data-stage');
    
    console.log('💧 Drop detected:', { dealId: dealId, from: sourceStage, to: targetStage });
    
    if (sourceStage !== targetStage) {
        // Move the deal card
        var dealCard = jQuery('.deal-card[data-deal-id="' + dealId + '"]');
        var targetContainer = dropZone.find('[class*="deals"], .stage-content').first();
        
        // If no specific container found, append to the stage itself
        if (targetContainer.length === 0) {
            targetContainer = dropZone;
        }
        
        if (dealCard.length > 0) {
            dealCard.detach().appendTo(targetContainer);
            dealCard.attr('data-stage', targetStage);
            
            console.log('✅ Deal moved successfully!');
            
            // Show success notification
            var notification = jQuery('<div class="drag-drop-success">Deal moved to ' + targetStage + '!</div>');
            notification.css({
                position: 'fixed',
                top: '20px',
                right: '20px',
                background: '#4CAF50',
                color: 'white',
                padding: '10px 20px',
                borderRadius: '5px',
                zIndex: 9999,
                fontSize: '14px'
            });
            jQuery('body').append(notification);
            
            setTimeout(function() {
                notification.fadeOut(function() {
                    notification.remove();
                });
            }, 3000);
        }
    }
});

// Step 4: Add visual styling
var dragDropCSS = `
<style id="drag-drop-fix-styles">
.deal-card {
    cursor: move !important;
    transition: all 0.2s ease;
}
.deal-card.dragging {
    opacity: 0.5 !important;
    transform: rotate(5deg) !important;
}
.drag-over {
    background-color: #e8f5e8 !important;
    border: 2px dashed #4CAF50 !important;
    border-radius: 8px !important;
}
.drag-drop-success {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    font-weight: bold;
}
</style>`;

if (jQuery('#drag-drop-fix-styles').length === 0) {
    jQuery('head').append(dragDropCSS);
}

// Final report
var dealCards = jQuery('.deal-card').length;
var stages = jQuery('[class*="stage"][data-stage]').length;

console.log('🎉 Drag and Drop Fix Complete!');
console.log(`   📋 ${dealCards} deal cards made draggable`);
console.log(`   🎯 ${stages} drop zones configured`);
console.log('   ✨ Try dragging a deal card to a different stage!');

// Test drag and drop capability
if (dealCards > 0 && stages > 0) {
    console.log('✅ Drag and drop should now work!');
} else {
    console.log('❌ Still missing elements - check the page structure');
}