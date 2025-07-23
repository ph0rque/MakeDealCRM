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

// Apply checklist template
function applyChecklistTemplate(dealId) {
    // Open popup to select template
    var url = 'index.php?module=ChecklistTemplates&action=Popup&deal_id=' + dealId;
    open_popup('ChecklistTemplates', 600, 400, '', true, false, {
        'call_back_function': 'set_checklist_template',
        'form_name': 'EditView',
        'field_to_name_array': {
            'id': 'template_id',
            'name': 'template_name'
        }
    });
}

// Callback for checklist template selection
function set_checklist_template(popup_reply_data) {
    var template_id = popup_reply_data.name_to_value_array.template_id;
    var deal_id = document.getElementById('record') ? document.getElementById('record').value : '';
    
    if (template_id && deal_id) {
        // Make AJAX call to apply template
        var url = 'index.php?module=Deals&action=ApplyChecklistTemplate&to_pdf=1';
        var data = 'template_id=' + template_id + '&deal_id=' + deal_id;
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                // Refresh the page to show new checklist
                window.location.reload();
            }
        };
        
        xhr.send(data);
    }
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