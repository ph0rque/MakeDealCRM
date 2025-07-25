/**
 * Stakeholder Bulk Manager
 * 
 * Handles bulk operations for stakeholder management across multiple deals
 */

var StakeholderBulkManager = (function() {
    'use strict';
    
    var module = {
        selectedDeals: [],
        selectedContacts: [],
        existingStakeholders: {},
        
        /**
         * Initialize the bulk manager
         */
        init: function(preselectedDeals) {
            this.selectedDeals = preselectedDeals || [];
            this.updateSelectedCount();
            this.bindEvents();
            this.initContactSearch();
            
            if (this.selectedDeals.length > 0) {
                this.loadExistingStakeholders();
            }
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;
            
            // Select all deals checkbox
            $('#selectAllDeals').on('change', function() {
                var checked = $(this).prop('checked');
                $('.deal-checkbox').prop('checked', checked);
                self.updateSelectedDeals();
            });
            
            // Individual deal checkboxes
            $(document).on('change', '.deal-checkbox', function() {
                self.updateSelectedDeals();
            });
            
            // Tab changes
            $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
                var target = $(e.target).attr('href');
                if (target === '#manage-roles' || target === '#remove-stakeholders') {
                    self.loadExistingStakeholders();
                }
            });
        },
        
        /**
         * Initialize contact search
         */
        initContactSearch: function() {
            var self = this;
            var searchTimeout;
            
            $('#contactSearchBulk').on('keyup', function() {
                clearTimeout(searchTimeout);
                var query = $(this).val();
                
                if (query.length >= 2) {
                    searchTimeout = setTimeout(function() {
                        self.searchContacts(query);
                    }, 300);
                } else {
                    $('#contactSearchResults').empty();
                }
            });
        },
        
        /**
         * Update selected deals array
         */
        updateSelectedDeals: function() {
            this.selectedDeals = [];
            var self = this;
            
            $('.deal-checkbox:checked').each(function() {
                self.selectedDeals.push($(this).val());
            });
            
            this.updateSelectedCount();
            
            // Clear existing stakeholder data when selection changes
            this.existingStakeholders = {};
        },
        
        /**
         * Update selected count display
         */
        updateSelectedCount: function() {
            $('#selectedCount').text(this.selectedDeals.length);
            
            // Update select all checkbox state
            var totalDeals = $('.deal-checkbox').length;
            var checkedDeals = $('.deal-checkbox:checked').length;
            
            $('#selectAllDeals').prop('checked', totalDeals > 0 && totalDeals === checkedDeals);
            $('#selectAllDeals').prop('indeterminate', checkedDeals > 0 && checkedDeals < totalDeals);
        },
        
        /**
         * Search for contacts
         */
        searchContacts: function(query) {
            var self = this;
            
            $.ajax({
                url: 'index.php?module=Contacts&action=quicksearch',
                type: 'GET',
                data: {
                    query: query,
                    limit: 20
                },
                success: function(response) {
                    self.displayContactSearchResults(response.results || []);
                }
            });
        },
        
        /**
         * Display contact search results
         */
        displayContactSearchResults: function(results) {
            var self = this;
            var html = '<div class="contact-search-results">';
            
            if (results.length > 0) {
                results.forEach(function(contact) {
                    var isSelected = self.selectedContacts.some(function(c) { return c.id === contact.id; });
                    
                    html += '<div class="contact-result' + (isSelected ? ' selected' : '') + '" data-contact-id="' + contact.id + '">';
                    html += '<span class="contact-name">' + contact.name + '</span>';
                    if (contact.title) {
                        html += ' <span class="contact-title">- ' + contact.title + '</span>';
                    }
                    if (contact.account_name) {
                        html += ' <span class="contact-account">(' + contact.account_name + ')</span>';
                    }
                    html += '<button class="btn btn-xs btn-' + (isSelected ? 'default' : 'primary') + ' pull-right" ';
                    html += 'onclick="StakeholderBulkManager.' + (isSelected ? 'removeContact' : 'addContact') + '(\'' + contact.id + '\', \'' + contact.name.replace(/'/g, "\\'") + '\')">';
                    html += isSelected ? 'Remove' : 'Add';
                    html += '</button>';
                    html += '</div>';
                });
            } else {
                html += '<div class="no-results">No contacts found</div>';
            }
            
            html += '</div>';
            $('#contactSearchResults').html(html);
        },
        
        /**
         * Add contact to selection
         */
        addContact: function(contactId, contactName) {
            if (!this.selectedContacts.some(function(c) { return c.id === contactId; })) {
                this.selectedContacts.push({
                    id: contactId,
                    name: contactName
                });
                this.updateSelectedContactsDisplay();
                
                // Update search results if visible
                $('.contact-result[data-contact-id="' + contactId + '"]').addClass('selected');
                $('.contact-result[data-contact-id="' + contactId + '"] button')
                    .removeClass('btn-primary').addClass('btn-default')
                    .text('Remove')
                    .attr('onclick', 'StakeholderBulkManager.removeContact(\'' + contactId + '\', \'' + contactName.replace(/'/g, "\\'") + '\')');
            }
        },
        
        /**
         * Remove contact from selection
         */
        removeContact: function(contactId) {
            this.selectedContacts = this.selectedContacts.filter(function(c) {
                return c.id !== contactId;
            });
            this.updateSelectedContactsDisplay();
            
            // Update search results if visible
            $('.contact-result[data-contact-id="' + contactId + '"]').removeClass('selected');
            $('.contact-result[data-contact-id="' + contactId + '"] button')
                .removeClass('btn-default').addClass('btn-primary')
                .text('Add');
        },
        
        /**
         * Update selected contacts display
         */
        updateSelectedContactsDisplay: function() {
            var html = '';
            
            this.selectedContacts.forEach(function(contact) {
                html += '<div class="selected-contact">';
                html += '<span>' + contact.name + '</span>';
                html += '<button class="btn btn-xs btn-danger" onclick="StakeholderBulkManager.removeContact(\'' + contact.id + '\')">';
                html += '<i class="glyphicon glyphicon-remove"></i></button>';
                html += '</div>';
            });
            
            if (html === '') {
                html = '<p class="text-muted">No contacts selected</p>';
            }
            
            $('#selectedContacts').html(html);
        },
        
        /**
         * Add stakeholders to selected deals
         */
        addStakeholdersToDeals: function() {
            if (this.selectedDeals.length === 0) {
                alert('Please select at least one deal');
                return;
            }
            
            if (this.selectedContacts.length === 0) {
                alert('Please select at least one contact');
                return;
            }
            
            var self = this;
            var role = $('#bulkRole').val();
            var operations = [];
            
            // Create operations for each deal-contact combination
            this.selectedDeals.forEach(function(dealId) {
                self.selectedContacts.forEach(function(contact) {
                    operations.push({
                        action: 'add',
                        deal_id: dealId,
                        contact_id: contact.id,
                        contact_role: role
                    });
                });
            });
            
            // Execute bulk operation
            this.executeBulkOperation(operations, 'Adding stakeholders...');
        },
        
        /**
         * Load existing stakeholders for selected deals
         */
        loadExistingStakeholders: function() {
            if (this.selectedDeals.length === 0) {
                $('#existingStakeholders').html('<p class="text-muted">Select deals to view stakeholders</p>');
                $('#removableStakeholders').html('<p class="text-muted">Select deals to view stakeholders</p>');
                return;
            }
            
            var self = this;
            var loadCount = 0;
            
            this.selectedDeals.forEach(function(dealId) {
                $.ajax({
                    url: 'index.php?module=Deals&action=stakeholders&deal_id=' + dealId,
                    type: 'GET',
                    success: function(response) {
                        if (response.success) {
                            self.existingStakeholders[dealId] = response.stakeholders;
                        }
                        loadCount++;
                        
                        if (loadCount === self.selectedDeals.length) {
                            self.displayExistingStakeholders();
                        }
                    }
                });
            });
        },
        
        /**
         * Display existing stakeholders
         */
        displayExistingStakeholders: function() {
            var self = this;
            var stakeholderMap = {};
            
            // Aggregate stakeholders across all selected deals
            for (var dealId in this.existingStakeholders) {
                this.existingStakeholders[dealId].forEach(function(stakeholder) {
                    var key = stakeholder.contact_id;
                    if (!stakeholderMap[key]) {
                        stakeholderMap[key] = {
                            contact_id: stakeholder.contact_id,
                            name: stakeholder.first_name + ' ' + stakeholder.last_name,
                            roles: {},
                            relationships: []
                        };
                    }
                    
                    if (!stakeholderMap[key].roles[stakeholder.contact_role]) {
                        stakeholderMap[key].roles[stakeholder.contact_role] = 0;
                    }
                    stakeholderMap[key].roles[stakeholder.contact_role]++;
                    
                    stakeholderMap[key].relationships.push({
                        deal_id: dealId,
                        relationship_id: stakeholder.relationship_id,
                        role: stakeholder.contact_role
                    });
                });
            }
            
            // Display for role management
            var roleHtml = '<table class="table table-striped">';
            roleHtml += '<thead><tr><th><input type="checkbox" id="selectAllStakeholders"></th>';
            roleHtml += '<th>Stakeholder</th><th>Current Roles</th><th>Deal Count</th></tr></thead>';
            roleHtml += '<tbody>';
            
            for (var contactId in stakeholderMap) {
                var stakeholder = stakeholderMap[contactId];
                var rolesList = Object.keys(stakeholder.roles).map(function(role) {
                    return role + ' (' + stakeholder.roles[role] + ')';
                }).join(', ');
                
                roleHtml += '<tr>';
                roleHtml += '<td><input type="checkbox" class="stakeholder-checkbox" value="' + contactId + '"></td>';
                roleHtml += '<td>' + stakeholder.name + '</td>';
                roleHtml += '<td>' + rolesList + '</td>';
                roleHtml += '<td>' + stakeholder.relationships.length + '</td>';
                roleHtml += '</tr>';
            }
            
            roleHtml += '</tbody></table>';
            
            $('#existingStakeholders').html(roleHtml);
            $('#removableStakeholders').html(roleHtml.replace('selectAllStakeholders', 'selectAllRemovable'));
            
            // Bind select all
            $('#selectAllStakeholders, #selectAllRemovable').on('change', function() {
                var checked = $(this).prop('checked');
                $(this).closest('table').find('.stakeholder-checkbox').prop('checked', checked);
            });
        },
        
        /**
         * Update selected stakeholder roles
         */
        updateSelectedRoles: function() {
            var newRole = $('#newRole').val();
            if (!newRole) {
                alert('Please select a new role');
                return;
            }
            
            var self = this;
            var operations = [];
            var selectedContacts = [];
            
            $('#existingStakeholders .stakeholder-checkbox:checked').each(function() {
                selectedContacts.push($(this).val());
            });
            
            if (selectedContacts.length === 0) {
                alert('Please select stakeholders to update');
                return;
            }
            
            // Create update operations
            selectedContacts.forEach(function(contactId) {
                if (self.existingStakeholders) {
                    for (var dealId in self.existingStakeholders) {
                        self.existingStakeholders[dealId].forEach(function(stakeholder) {
                            if (stakeholder.contact_id === contactId) {
                                operations.push({
                                    action: 'update',
                                    relationship_id: stakeholder.relationship_id,
                                    contact_role: newRole
                                });
                            }
                        });
                    }
                }
            });
            
            this.executeBulkOperation(operations, 'Updating roles...');
        },
        
        /**
         * Remove selected stakeholders
         */
        removeSelectedStakeholders: function() {
            var self = this;
            var operations = [];
            var selectedContacts = [];
            
            $('#removableStakeholders .stakeholder-checkbox:checked').each(function() {
                selectedContacts.push($(this).val());
            });
            
            if (selectedContacts.length === 0) {
                alert('Please select stakeholders to remove');
                return;
            }
            
            if (!confirm('Are you sure you want to remove ' + selectedContacts.length + ' stakeholder(s) from the selected deals?')) {
                return;
            }
            
            // Create remove operations
            selectedContacts.forEach(function(contactId) {
                if (self.existingStakeholders) {
                    for (var dealId in self.existingStakeholders) {
                        self.existingStakeholders[dealId].forEach(function(stakeholder) {
                            if (stakeholder.contact_id === contactId) {
                                operations.push({
                                    action: 'remove',
                                    relationship_id: stakeholder.relationship_id
                                });
                            }
                        });
                    }
                }
            });
            
            this.executeBulkOperation(operations, 'Removing stakeholders...');
        },
        
        /**
         * Execute bulk operation
         */
        executeBulkOperation: function(operations, message) {
            var self = this;
            
            $('#operationResults').html('<div class="alert alert-info">' + message + '</div>');
            
            $.ajax({
                url: 'index.php?module=Deals&action=stakeholders',
                type: 'PUT',
                data: {
                    operations: operations
                },
                success: function(response) {
                    if (response.success) {
                        var html = '<div class="alert alert-success">';
                        html += 'Operation completed successfully!<br>';
                        html += 'Total: ' + response.summary.total + ', ';
                        html += 'Success: ' + response.summary.success + ', ';
                        html += 'Errors: ' + response.summary.errors;
                        html += '</div>';
                        
                        // Show any errors
                        if (response.summary.errors > 0) {
                            html += '<div class="alert alert-warning">Errors:</div>';
                            html += '<ul>';
                            response.results.forEach(function(result, index) {
                                if (!result.success) {
                                    html += '<li>Operation ' + (index + 1) + ': ' + result.error + '</li>';
                                }
                            });
                            html += '</ul>';
                        }
                        
                        $('#operationResults').html(html);
                        
                        // Clear selections and reload
                        self.selectedContacts = [];
                        self.updateSelectedContactsDisplay();
                        $('#contactSearchBulk').val('');
                        $('#contactSearchResults').empty();
                        
                        // Reload existing stakeholders
                        self.loadExistingStakeholders();
                    } else {
                        $('#operationResults').html('<div class="alert alert-danger">Operation failed: ' + (response.error || 'Unknown error') + '</div>');
                    }
                }
            });
        },
        
        /**
         * Export stakeholders to CSV
         */
        exportStakeholders: function() {
            if (this.selectedDeals.length === 0) {
                alert('Please select at least one deal');
                return;
            }
            
            // Create CSV content
            var csv = 'Deal Name,Contact Name,Contact Email,Role,Account,Title\n';
            
            // TODO: Implement actual export with deal names and contact emails
            // For now, show a placeholder
            alert('Export functionality will be implemented with actual deal and contact data retrieval.');
        },
        
        /**
         * Import stakeholders from CSV
         */
        importStakeholders: function() {
            var file = document.getElementById('importFile').files[0];
            if (!file) {
                alert('Please select a CSV file');
                return;
            }
            
            var reader = new FileReader();
            reader.onload = function(e) {
                var contents = e.target.result;
                // TODO: Parse CSV and create import operations
                alert('Import functionality will parse CSV and create stakeholder relationships.');
            };
            reader.readAsText(file);
        },
        
        /**
         * Download CSV template
         */
        downloadTemplate: function() {
            var csv = 'Deal Name,Contact Email,Role\n';
            csv += 'Example Deal,john.doe@example.com,Decision Maker\n';
            csv += 'Another Deal,jane.smith@example.com,Technical Evaluator\n';
            
            var blob = new Blob([csv], { type: 'text/csv' });
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'stakeholder_import_template.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        },
        
        /**
         * Search deals
         */
        searchDeals: function() {
            var query = $('#dealSearch').val().toLowerCase();
            
            $('.deal-selection-table tbody tr').each(function() {
                var dealName = $(this).find('td:eq(1)').text().toLowerCase();
                var accountName = $(this).find('td:eq(2)').text().toLowerCase();
                
                if (dealName.includes(query) || accountName.includes(query)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        },
        
        /**
         * Select all visible deals
         */
        selectAllDeals: function() {
            $('.deal-selection-table tbody tr:visible .deal-checkbox').prop('checked', true);
            this.updateSelectedDeals();
        },
        
        /**
         * Clear deal selection
         */
        clearSelection: function() {
            $('.deal-checkbox').prop('checked', false);
            this.updateSelectedDeals();
        }
    };
    
    return module;
})();