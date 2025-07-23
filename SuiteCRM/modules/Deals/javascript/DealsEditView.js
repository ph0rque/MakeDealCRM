/**
 * Deals Edit View JavaScript
 * Handles AJAX duplicate checking and form interactions
 */

var DealsEditView = {
    
    duplicateCheckFields: ['name', 'account_name', 'amount', 'email1'],
    duplicateCheckTimer: null,
    lastCheckedValues: {},
    
    init: function() {
        // Bind events to form fields for duplicate checking
        this.bindDuplicateCheck();
        
        // Override form submission to check for duplicates
        this.overrideFormSubmit();
        
        // Initialize field monitoring
        this.initFieldMonitoring();
    },
    
    bindDuplicateCheck: function() {
        var self = this;
        
        // Bind to specific fields that trigger duplicate check
        $.each(this.duplicateCheckFields, function(index, fieldName) {
            var field = $('#' + fieldName);
            if (field.length > 0) {
                field.on('blur', function() {
                    self.performDuplicateCheck();
                });
            }
        });
        
        // Also check on account selection
        $('#account_name').on('blur', function() {
            self.performDuplicateCheck();
        });
    },
    
    performDuplicateCheck: function() {
        var self = this;
        
        // Clear any existing timer
        if (this.duplicateCheckTimer) {
            clearTimeout(this.duplicateCheckTimer);
        }
        
        // Set a small delay to avoid too many requests
        this.duplicateCheckTimer = setTimeout(function() {
            self.executeDuplicateCheck();
        }, 500);
    },
    
    executeDuplicateCheck: function() {
        var self = this;
        var checkData = {};
        var hasData = false;
        
        // Collect field values
        $.each(this.duplicateCheckFields, function(index, fieldName) {
            var value = $('#' + fieldName).val();
            if (value && value.trim() !== '') {
                checkData[fieldName] = value.trim();
                hasData = true;
            }
        });
        
        // Only check if we have data and it's different from last check
        if (!hasData || JSON.stringify(checkData) === JSON.stringify(this.lastCheckedValues)) {
            return;
        }
        
        this.lastCheckedValues = checkData;
        
        // Show loading indicator
        this.showDuplicateLoading();
        
        // Perform AJAX check
        $.ajax({
            url: 'index.php?module=Deals&action=CheckDuplicates&to_pdf=1',
            type: 'POST',
            data: {
                check_data: JSON.stringify(checkData),
                record_id: $('[name="record"]').val() || ''
            },
            dataType: 'json',
            success: function(response) {
                self.handleDuplicateResponse(response);
            },
            error: function() {
                self.hideDuplicateCheck();
            }
        });
    },
    
    handleDuplicateResponse: function(response) {
        if (response && response.duplicates && response.duplicates.length > 0) {
            this.showDuplicates(response.duplicates);
        } else {
            this.hideDuplicateCheck();
        }
    },
    
    showDuplicates: function(duplicates) {
        var html = '<div class="duplicate-warning">';
        html += '<p class="warning-text">Potential duplicate deals found:</p>';
        html += '<table class="duplicate-table">';
        html += '<thead><tr>';
        html += '<th>Deal Name</th>';
        html += '<th>Account</th>';
        html += '<th>Amount</th>';
        html += '<th>Stage</th>';
        html += '<th>Assigned To</th>';
        html += '<th>Actions</th>';
        html += '</tr></thead><tbody>';
        
        $.each(duplicates, function(index, deal) {
            html += '<tr>';
            html += '<td><a href="index.php?module=Deals&action=DetailView&record=' + deal.id + '" target="_blank">' + deal.name + '</a></td>';
            html += '<td>' + (deal.account_name || '-') + '</td>';
            html += '<td>' + (deal.amount_formatted || '-') + '</td>';
            html += '<td>' + (deal.sales_stage || '-') + '</td>';
            html += '<td>' + (deal.assigned_user_name || '-') + '</td>';
            html += '<td>';
            html += '<button type="button" class="button btn-sm" onclick="DealsEditView.mergeWithDuplicate(\'' + deal.id + '\')">Merge</button> ';
            html += '<button type="button" class="button btn-sm" onclick="DealsEditView.viewDuplicate(\'' + deal.id + '\')">View</button>';
            html += '</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        html += '<div class="duplicate-score">Confidence: ' + (duplicates[0].score || 'High') + '</div>';
        html += '</div>';
        
        $('#duplicatesList').html(html);
        $('#duplicateCheckResults').fadeIn();
        
        // Scroll to duplicate check results
        $('html, body').animate({
            scrollTop: $('#duplicateCheckResults').offset().top - 100
        }, 500);
    },
    
    hideDuplicateCheck: function() {
        $('#duplicateCheckResults').fadeOut();
    },
    
    showDuplicateLoading: function() {
        $('#duplicatesList').html('<div class="loading-indicator">Checking for duplicates...</div>');
        $('#duplicateCheckResults').show();
    },
    
    overrideFormSubmit: function() {
        var self = this;
        var originalSubmit = check_form;
        
        window.check_form = function(formname) {
            if ($('#duplicateCheckResults').is(':visible') && !self.allowDuplicateSubmit) {
                // Show confirmation dialog
                if (confirm('Potential duplicates found. Are you sure you want to continue?')) {
                    self.allowDuplicateSubmit = true;
                    return originalSubmit(formname);
                }
                return false;
            }
            return originalSubmit(formname);
        };
    },
    
    initFieldMonitoring: function() {
        // Monitor probability field to auto-calculate weighted amount
        $('#probability').on('change', function() {
            DealsEditView.calculateWeightedAmount();
        });
        
        $('#amount').on('change', function() {
            DealsEditView.calculateWeightedAmount();
        });
    },
    
    calculateWeightedAmount: function() {
        var amount = parseFloat($('#amount').val()) || 0;
        var probability = parseFloat($('#probability').val()) || 0;
        var weighted = amount * (probability / 100);
        
        // Update weighted amount display
        if ($('#weighted_amount_display').length === 0) {
            $('#amount').after('<span id="weighted_amount_display" class="weighted-amount"></span>');
        }
        
        $('#weighted_amount_display').text(' (Weighted: $' + weighted.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') + ')');
    },
    
    mergeWithDuplicate: function(duplicateId) {
        if (confirm('Are you sure you want to merge with this deal? This action cannot be undone.')) {
            window.location.href = 'index.php?module=MergeRecords&action=Step1&record=' + duplicateId + '&merge_module=Deals';
        }
    },
    
    viewDuplicate: function(duplicateId) {
        window.open('index.php?module=Deals&action=DetailView&record=' + duplicateId, '_blank');
    },
    
    continueWithDuplicate: function() {
        this.allowDuplicateSubmit = true;
        this.hideDuplicateCheck();
    }
};

// Bind button events
$(document).ready(function() {
    $('#continueWithDuplicate').on('click', function() {
        DealsEditView.continueWithDuplicate();
    });
    
    $('#cancelDuplicate').on('click', function() {
        DealsEditView.hideDuplicateCheck();
    });
});