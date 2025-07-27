/**
 * JavaScript functions for Deals Module
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 */

// Calculate proposed valuation based on EBITDA and multiple
function calculateProposedValuation() {
    var ebitda = document.getElementById('ttm_ebitda_c') ? parseFloat(document.getElementById('ttm_ebitda_c').value.replace(/[^0-9.-]+/g, '')) : 0;
    var multiple = document.getElementById('target_multiple_c') ? parseFloat(document.getElementById('target_multiple_c').value) : 0;
    
    if (ebitda && multiple) {
        var valuation = ebitda * multiple;
        if (document.getElementById('proposed_valuation_c')) {
            document.getElementById('proposed_valuation_c').value = formatCurrency(valuation);
        }
    }
}

// Calculate total capital stack
function calculateTotalCapital() {
    var equity = document.getElementById('equity_c') ? parseFloat(document.getElementById('equity_c').value.replace(/[^0-9.-]+/g, '')) : 0;
    var senior_debt = document.getElementById('senior_debt_c') ? parseFloat(document.getElementById('senior_debt_c').value.replace(/[^0-9.-]+/g, '')) : 0;
    var seller_note = document.getElementById('seller_note_c') ? parseFloat(document.getElementById('seller_note_c').value.replace(/[^0-9.-]+/g, '')) : 0;
    
    var total = equity + senior_debt + seller_note;
    
    if (document.getElementById('total_capital_display')) {
        document.getElementById('total_capital_display').innerHTML = formatCurrency(total);
    }
}

// Format currency display
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount);
}

// Check for duplicate deals
function checkDuplicateDeals() {
    var dealName = document.getElementById('name') ? document.getElementById('name').value : '';
    
    if (dealName.length > 3) {
        // Make AJAX call to check for duplicates
        var url = 'index.php?module=Deals&action=CheckDuplicates&to_pdf=1';
        var data = 'deal_name=' + encodeURIComponent(dealName);
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                var response = JSON.parse(xhr.responseText);
                if (response.duplicates && response.duplicates.length > 0) {
                    showDuplicateWarning(response.duplicates);
                }
            }
        };
        
        xhr.send(data);
    }
}

// Show duplicate warning
function showDuplicateWarning(duplicates) {
    var message = 'Possible duplicate deals found:\n\n';
    for (var i = 0; i < duplicates.length; i++) {
        message += '- ' + duplicates[i].name + '\n';
    }
    message += '\nContinue anyway?';
    
    if (!confirm(message)) {
        // Optionally prevent form submission
        return false;
    }
    return true;
}

// Show checklist template selection popup
function showChecklistTemplatePopup(dealId) {
    // Create modal overlay
    var overlay = document.createElement('div');
    overlay.id = 'checklist-template-overlay';
    overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9998;';
    
    // Create modal container
    var modal = document.createElement('div');
    modal.id = 'checklist-template-modal';
    modal.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:white;padding:20px;border-radius:5px;z-index:9999;width:600px;max-height:80vh;overflow-y:auto;';
    
    modal.innerHTML = '<h2>Select Checklist Template</h2><div id="template-list">Loading templates...</div>';
    
    document.body.appendChild(overlay);
    document.body.appendChild(modal);
    
    // Load templates via AJAX
    loadChecklistTemplates(dealId);
    
    // Close on overlay click
    overlay.onclick = function() {
        closeChecklistTemplatePopup();
    };
}

// Load checklist templates
function loadChecklistTemplates(dealId) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'index.php?module=ChecklistTemplates&action=GetTemplateList&to_pdf=1', true);
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            var response = JSON.parse(xhr.responseText);
            displayTemplateList(response.templates, dealId);
        }
    };
    
    xhr.send();
}

// Display template list
function displayTemplateList(templates, dealId) {
    var listDiv = document.getElementById('template-list');
    
    if (!templates || templates.length === 0) {
        listDiv.innerHTML = '<p>No templates available. <a href="index.php?module=ChecklistTemplates&action=EditView">Create a template</a></p>';
        return;
    }
    
    var html = '<div style="max-height:400px;overflow-y:auto;">';
    
    // Group templates by category
    var categories = {};
    templates.forEach(function(template) {
        var cat = template.category || 'general';
        if (!categories[cat]) categories[cat] = [];
        categories[cat].push(template);
    });
    
    // Display templates by category
    for (var category in categories) {
        html += '<h3 style="margin-top:10px;">' + getCategoryLabel(category) + '</h3>';
        html += '<div style="margin-left:20px;">';
        
        categories[category].forEach(function(template) {
            html += '<div style="border:1px solid #ddd;padding:10px;margin-bottom:10px;cursor:pointer;" ';
            html += 'onmouseover="this.style.backgroundColor=\'#f0f0f0\'" ';
            html += 'onmouseout="this.style.backgroundColor=\'white\'" ';
            html += 'onclick="applyChecklistTemplate(\'' + dealId + '\', \'' + template.id + '\')">';
            html += '<strong>' + template.name + '</strong>';
            html += '<span style="float:right;color:#666;">' + template.item_count + ' items</span>';
            if (template.description) {
                html += '<br><small style="color:#666;">' + template.description + '</small>';
            }
            html += '</div>';
        });
        
        html += '</div>';
    }
    
    html += '</div>';
    html += '<div style="margin-top:20px;text-align:right;">';
    html += '<button onclick="closeChecklistTemplatePopup()">Cancel</button>';
    html += '</div>';
    
    listDiv.innerHTML = html;
}

// Get category label
function getCategoryLabel(category) {
    var labels = {
        'general': 'General',
        'due_diligence': 'Due Diligence',
        'financial': 'Financial Due Diligence',
        'legal': 'Legal Due Diligence',
        'operational': 'Operational Review',
        'compliance': 'Compliance',
        'quick_screen': 'Quick Screen'
    };
    return labels[category] || category;
}

// Close checklist template popup
function closeChecklistTemplatePopup() {
    var overlay = document.getElementById('checklist-template-overlay');
    var modal = document.getElementById('checklist-template-modal');
    if (overlay) overlay.remove();
    if (modal) modal.remove();
}

// Apply checklist template
function applyChecklistTemplate(dealId, templateId) {
    // Show loading
    var modal = document.getElementById('checklist-template-modal');
    if (modal) {
        modal.innerHTML = '<h2>Applying Template...</h2><p>Please wait while the checklist is created.</p>';
    }
    
    // Make AJAX call to apply template
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'index.php?module=Deals&action=ApplyChecklistTemplate&to_pdf=1', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                var response = JSON.parse(xhr.responseText);
                if (response.success) {
                    // Show success message
                    if (modal) {
                        modal.innerHTML = '<h2>Success!</h2><p>Checklist has been created successfully.</p>';
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    }
                } else {
                    alert('Error: ' + (response.message || 'Failed to apply template'));
                    closeChecklistTemplatePopup();
                }
            } else {
                alert('Error applying template. Please try again.');
                closeChecklistTemplatePopup();
            }
        }
    };
    
    var data = 'template_id=' + encodeURIComponent(templateId) + '&deal_id=' + encodeURIComponent(dealId);
    xhr.send(data);
}

// Initialize on page load
YAHOO.util.Event.onDOMReady(function() {
    // Add event listeners for financial calculations
    if (document.getElementById('ttm_ebitda_c')) {
        document.getElementById('ttm_ebitda_c').addEventListener('blur', calculateProposedValuation);
    }
    if (document.getElementById('target_multiple_c')) {
        document.getElementById('target_multiple_c').addEventListener('blur', calculateProposedValuation);
    }
    
    // Add event listeners for capital stack calculations
    var capitalFields = ['equity_c', 'senior_debt_c', 'seller_note_c'];
    for (var i = 0; i < capitalFields.length; i++) {
        if (document.getElementById(capitalFields[i])) {
            document.getElementById(capitalFields[i]).addEventListener('blur', calculateTotalCapital);
        }
    }
    
    // Add event listener for duplicate checking
    if (document.getElementById('name')) {
        document.getElementById('name').addEventListener('blur', checkDuplicateDeals);
    }
    
    // Calculate initial values
    calculateProposedValuation();
    calculateTotalCapital();
});