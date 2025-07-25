/**
 * Test script to verify drag and drop functionality in the pipeline view
 * Run this in the browser console when on the pipeline view
 */

console.log("Testing Pipeline Drag and Drop...");

// Check if PipelineView is loaded
if (typeof PipelineView !== 'undefined') {
    console.log("✓ PipelineView is loaded");
    
    // Check configuration
    console.log("Configuration:", PipelineView.config);
    
    // Check for draggable elements
    var draggables = document.querySelectorAll('.draggable');
    console.log("✓ Found " + draggables.length + " draggable elements");
    
    // Check for droppable zones
    var droppables = document.querySelectorAll('.droppable');
    console.log("✓ Found " + droppables.length + " droppable zones");
    
    // Test drag event listeners
    if (draggables.length > 0) {
        var testCard = draggables[0];
        console.log("Testing drag events on:", testCard);
        
        // Check if draggable attribute is set
        console.log("Draggable attribute:", testCard.getAttribute('draggable'));
        
        // Simulate drag start
        var dragStartEvent = new DragEvent('dragstart', {
            dataTransfer: new DataTransfer(),
            bubbles: true
        });
        
        testCard.dispatchEvent(dragStartEvent);
        console.log("✓ Drag start event dispatched");
        
        // Simulate drag end
        var dragEndEvent = new DragEvent('dragend', {
            bubbles: true
        });
        
        testCard.dispatchEvent(dragEndEvent);
        console.log("✓ Drag end event dispatched");
    }
    
    // Check AJAX URLs
    console.log("Update URL:", PipelineView.config.updateUrl);
    console.log("Refresh URL:", PipelineView.config.refreshUrl);
    
    // Test manual drag handler attachment
    console.log("\nManually attaching drag handlers to verify...");
    
    document.querySelectorAll('.draggable').forEach(function(element) {
        element.addEventListener('dragstart', function(e) {
            console.log("Manual dragstart fired for:", e.target);
        });
    });
    
    document.querySelectorAll('.droppable').forEach(function(element) {
        element.addEventListener('dragover', function(e) {
            e.preventDefault();
            console.log("Manual dragover fired for:", e.target);
        });
        
        element.addEventListener('drop', function(e) {
            e.preventDefault();
            console.log("Manual drop fired for:", e.target);
        });
    });
    
    console.log("✓ Manual handlers attached. Try dragging a card now.");
    
} else {
    console.error("✗ PipelineView is not loaded!");
    
    // Check if jQuery is loaded
    if (typeof jQuery !== 'undefined') {
        console.log("✓ jQuery is loaded");
    } else {
        console.error("✗ jQuery is not loaded");
    }
    
    // Check for pipeline container
    var container = document.getElementById('pipeline-container');
    if (container) {
        console.log("✓ Pipeline container found");
    } else {
        console.error("✗ Pipeline container not found");
    }
}

// Additional diagnostics
console.log("\nAdditional diagnostics:");

// Check CSS classes
var dealCards = document.querySelectorAll('.deal-card');
console.log("Deal cards found:", dealCards.length);

// Check stage bodies
var stageBodies = document.querySelectorAll('.stage-body');
console.log("Stage bodies found:", stageBodies.length);

// Check if drag-drop CSS is loaded
var styles = Array.from(document.styleSheets);
var hasPipelineStyles = styles.some(sheet => {
    try {
        return sheet.href && sheet.href.includes('deals.css');
    } catch(e) {
        return false;
    }
});
console.log("Pipeline CSS loaded:", hasPipelineStyles);

console.log("\nDrag and drop test complete. Check the console output above.");