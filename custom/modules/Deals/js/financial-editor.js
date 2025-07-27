/**
 * Financial Data Editor
 * Handles inline editing and deletion of financial metrics
 */

var FinancialEditor = (function() {
    'use strict';
    
    var currentDealId = null;
    var originalValues = {};
    var editingFields = {};
    
    return {
        /**
         * Initialize the editor
         */
        init: function(dealId) {
            currentDealId = dealId;
            this.attachEventHandlers();
            this.makeFieldsEditable();
        },
        
        /**
         * Make financial fields editable
         */
        makeFieldsEditable: function() {
            // Key Metrics
            this.addEditButton('annual-revenue', 'Annual Revenue');
            this.addEditButton('ebitda', 'EBITDA');
            this.addEditButton('sde', 'SDE');
            this.addEditButton('gross-margin', 'Gross Margin');
            this.addEditButton('operating-margin', 'Operating Margin');
            
            // Capital Stack
            this.addEditButton('senior-debt', 'Senior Debt');
            this.addEditButton('seller-note', 'Seller Note');
            this.addEditButton('equity-required', 'Equity Required');
            
            // Market Comparables
            this.addEditButton('industry-multiple', 'Industry Multiple');
            this.addEditButton('target-multiple', 'Target Multiple');
        },
        
        /**
         * Add edit button to a field
         */
        addEditButton: function(fieldId, fieldLabel) {
            var field = $('#' + fieldId);
            if (field.length === 0) return;
            
            var editBtn = $('<button class="btn btn-xs btn-edit" title="Edit ' + fieldLabel + '"><i class="fa fa-pencil"></i></button>');
            var deleteBtn = $('<button class="btn btn-xs btn-delete" title="Clear ' + fieldLabel + '"><i class="fa fa-trash"></i></button>');
            
            var btnGroup = $('<div class="edit-btn-group"></div>');
            btnGroup.append(editBtn).append(deleteBtn);
            
            field.parent().css('position', 'relative').append(btnGroup);
            
            // Attach click handlers
            editBtn.on('click', function() {
                FinancialEditor.startEdit(fieldId, fieldLabel);
            });
            
            deleteBtn.on('click', function() {
                FinancialEditor.deleteValue(fieldId, fieldLabel);
            });
        },
        
        /**
         * Start editing a field
         */
        startEdit: function(fieldId, fieldLabel) {
            var field = $('#' + fieldId);
            var currentValue = field.text().replace(/[$,%]/g, '').trim();
            
            // Store original value
            originalValues[fieldId] = field.text();
            editingFields[fieldId] = true;
            
            // Create input field
            var input = $('<input type="text" class="form-control form-control-sm edit-input" />');
            input.val(currentValue);
            
            // Create save/cancel buttons
            var saveBtn = $('<button class="btn btn-sm btn-success"><i class="fa fa-check"></i></button>');
            var cancelBtn = $('<button class="btn btn-sm btn-danger"><i class="fa fa-times"></i></button>');
            
            var editControls = $('<div class="edit-controls"></div>');
            editControls.append(input).append(saveBtn).append(cancelBtn);
            
            // Replace field content
            field.html(editControls);
            input.focus().select();
            
            // Handle save
            saveBtn.on('click', function() {
                FinancialEditor.saveEdit(fieldId, input.val(), fieldLabel);
            });
            
            // Handle cancel
            cancelBtn.on('click', function() {
                FinancialEditor.cancelEdit(fieldId);
            });
            
            // Handle enter key
            input.on('keypress', function(e) {
                if (e.which === 13) {
                    FinancialEditor.saveEdit(fieldId, input.val(), fieldLabel);
                } else if (e.which === 27) {
                    FinancialEditor.cancelEdit(fieldId);
                }
            });
        },
        
        /**
         * Save edited value
         */
        saveEdit: function(fieldId, newValue, fieldLabel) {
            var self = this;
            
            // Validate input
            if (!this.validateInput(fieldId, newValue)) {
                return;
            }
            
            // Prepare update data
            var updateData = {};
            var fieldMapping = this.getFieldMapping(fieldId);
            
            if (!fieldMapping) {
                this.showError('Unknown field: ' + fieldId);
                this.cancelEdit(fieldId);
                return;
            }
            
            updateData[fieldMapping.group] = {};
            updateData[fieldMapping.group][fieldMapping.field] = parseFloat(newValue) || 0;
            
            // Show loading
            $('#' + fieldId).html('<i class="fa fa-spinner fa-spin"></i>');
            
            // Send update request
            $.ajax({
                url: 'index.php?module=Deals&action=updateFinancialData',
                type: 'POST',
                data: {
                    deal_id: currentDealId,
                    update_data: JSON.stringify(updateData)
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Update UI with new value
                        var formattedValue = self.formatValue(fieldId, newValue);
                        $('#' + fieldId).text(formattedValue);
                        delete editingFields[fieldId];
                        
                        // Refresh calculated metrics
                        if (response.updated_metrics) {
                            self.updateCalculatedMetrics(response.updated_metrics);
                        }
                        
                        self.showSuccess(fieldLabel + ' updated successfully');
                    } else {
                        self.showError(response.message || 'Failed to update ' + fieldLabel);
                        self.cancelEdit(fieldId);
                    }
                },
                error: function() {
                    self.showError('Network error updating ' + fieldLabel);
                    self.cancelEdit(fieldId);
                }
            });
        },
        
        /**
         * Cancel editing
         */
        cancelEdit: function(fieldId) {
            $('#' + fieldId).text(originalValues[fieldId]);
            delete editingFields[fieldId];
            delete originalValues[fieldId];
        },
        
        /**
         * Delete a value
         */
        deleteValue: function(fieldId, fieldLabel) {
            var self = this;
            
            if (!confirm('Are you sure you want to clear ' + fieldLabel + '?')) {
                return;
            }
            
            // Prepare update data
            var updateData = {};
            var fieldMapping = this.getFieldMapping(fieldId);
            
            if (!fieldMapping) {
                this.showError('Unknown field: ' + fieldId);
                return;
            }
            
            updateData[fieldMapping.group] = {};
            updateData[fieldMapping.group][fieldMapping.field] = null;
            
            // Show loading
            $('#' + fieldId).html('<i class="fa fa-spinner fa-spin"></i>');
            
            // Send delete request
            $.ajax({
                url: 'index.php?module=Deals&action=updateFinancialData',
                type: 'POST',
                data: {
                    deal_id: currentDealId,
                    update_data: JSON.stringify(updateData),
                    action: 'delete'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Update UI
                        $('#' + fieldId).text('-');
                        
                        // Refresh calculated metrics
                        if (response.updated_metrics) {
                            self.updateCalculatedMetrics(response.updated_metrics);
                        }
                        
                        self.showSuccess(fieldLabel + ' cleared successfully');
                    } else {
                        self.showError(response.message || 'Failed to clear ' + fieldLabel);
                        location.reload(); // Refresh to restore original value
                    }
                },
                error: function() {
                    self.showError('Network error clearing ' + fieldLabel);
                    location.reload();
                }
            });
        },
        
        /**
         * Get field mapping
         */
        getFieldMapping: function(fieldId) {
            var mappings = {
                'annual-revenue': { group: 'revenue_metrics', field: 'annual_revenue_c' },
                'ebitda': { group: 'calculated', field: 'ebitda' },
                'sde': { group: 'calculated', field: 'sde' },
                'gross-margin': { group: 'calculated', field: 'gross_margin' },
                'operating-margin': { group: 'calculated', field: 'operating_margin' },
                'senior-debt': { group: 'capital_stack', field: 'senior_debt_amount_c' },
                'seller-note': { group: 'capital_stack', field: 'seller_note_amount_c' },
                'equity-required': { group: 'calculated', field: 'equity_required' },
                'industry-multiple': { group: 'valuation_settings', field: 'industry_multiple_c' },
                'target-multiple': { group: 'valuation_settings', field: 'target_multiple_c' }
            };
            
            return mappings[fieldId] || null;
        },
        
        /**
         * Validate input
         */
        validateInput: function(fieldId, value) {
            // Check if numeric
            if (value !== '' && isNaN(parseFloat(value))) {
                this.showError('Please enter a valid number');
                return false;
            }
            
            // Field-specific validation
            if (fieldId.includes('margin') || fieldId.includes('rate')) {
                var numValue = parseFloat(value);
                if (numValue < 0 || numValue > 100) {
                    this.showError('Please enter a value between 0 and 100');
                    return false;
                }
            }
            
            return true;
        },
        
        /**
         * Format value for display
         */
        formatValue: function(fieldId, value) {
            var numValue = parseFloat(value);
            
            if (fieldId.includes('margin') || fieldId.includes('rate')) {
                return numValue.toFixed(1) + '%';
            } else if (fieldId.includes('multiple')) {
                return numValue.toFixed(2) + 'x';
            } else {
                return '$' + numValue.toLocaleString();
            }
        },
        
        /**
         * Update calculated metrics
         */
        updateCalculatedMetrics: function(metrics) {
            // Update each metric in the UI
            for (var key in metrics) {
                var element = $('#' + key.replace(/_/g, '-'));
                if (element.length) {
                    element.text(this.formatValue(key, metrics[key]));
                }
            }
        },
        
        /**
         * Show success message
         */
        showSuccess: function(message) {
            var alert = $('<div class="alert alert-success alert-dismissible fade show financial-alert">' +
                         '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
                         '<strong>Success!</strong> ' + message +
                         '</div>');
            
            $('#financial-dashboard').prepend(alert);
            setTimeout(function() {
                alert.fadeOut();
            }, 3000);
        },
        
        /**
         * Show error message
         */
        showError: function(message) {
            var alert = $('<div class="alert alert-danger alert-dismissible fade show financial-alert">' +
                         '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
                         '<strong>Error!</strong> ' + message +
                         '</div>');
            
            $('#financial-dashboard').prepend(alert);
            setTimeout(function() {
                alert.fadeOut();
            }, 5000);
        },
        
        /**
         * Attach event handlers
         */
        attachEventHandlers: function() {
            // Add keyboard shortcuts
            $(document).on('keydown', function(e) {
                if (e.ctrlKey && e.key === 'e') {
                    // Ctrl+E to enter edit mode
                    $('.btn-edit:visible:first').click();
                    e.preventDefault();
                }
            });
        }
    };
})();