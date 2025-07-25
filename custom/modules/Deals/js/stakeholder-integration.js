/**
 * Stakeholder Integration Module
 * 
 * Handles stakeholder badges, quick actions, and communication tracking
 * in the Deal pipeline view
 */

var StakeholderIntegration = (function() {
    'use strict';
    
    var module = {
        stakeholderCache: {},
        communicationCache: {},
        
        /**
         * Initialize stakeholder integration
         */
        init: function() {
            console.log('Initializing Stakeholder Integration');
            
            // Load stakeholders for all visible deals
            this.loadAllStakeholders();
            
            // Set up event listeners
            this.bindEvents();
            
            // Refresh stakeholders when pipeline is refreshed
            $(document).on('pipeline:refreshed', this.loadAllStakeholders.bind(this));
        },
        
        /**
         * Load stakeholders for all visible deals
         */
        loadAllStakeholders: function() {
            var self = this;
            var dealCards = $('.deal-card');
            
            dealCards.each(function() {
                var dealId = $(this).data('deal-id');
                if (dealId) {
                    self.loadStakeholders(dealId);
                }
            });
        },
        
        /**
         * Load stakeholders for a specific deal
         */
        loadStakeholders: function(dealId) {
            var self = this;
            
            // Check cache first
            if (this.stakeholderCache[dealId]) {
                this.renderStakeholderBadges(dealId, this.stakeholderCache[dealId]);
                return;
            }
            
            $.ajax({
                url: 'index.php?module=Deals&action=stakeholders&deal_id=' + dealId,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        self.stakeholderCache[dealId] = response.stakeholders;
                        self.renderStakeholderBadges(dealId, response.stakeholders);
                    }
                },
                error: function() {
                    console.error('Failed to load stakeholders for deal: ' + dealId);
                }
            });
        },
        
        /**
         * Render stakeholder badges on deal card
         */
        renderStakeholderBadges: function(dealId, stakeholders) {
            var dealCard = $('.deal-card[data-deal-id="' + dealId + '"]');
            var existingBadges = dealCard.find('.stakeholder-badges');
            
            // Remove existing badges
            if (existingBadges.length) {
                existingBadges.remove();
            }
            
            if (stakeholders.length === 0) {
                return;
            }
            
            // Create badge container
            var badgeHtml = '<div class="stakeholder-badges">';
            
            // Group stakeholders by role
            var roleGroups = this.groupStakeholdersByRole(stakeholders);
            
            // Create role badges
            for (var role in roleGroups) {
                var count = roleGroups[role].length;
                var roleClass = this.getRoleClass(role);
                var roleIcon = this.getRoleIcon(role);
                
                badgeHtml += '<span class="stakeholder-badge ' + roleClass + '" ' +
                           'data-role="' + role + '" ' +
                           'title="' + role + ' (' + count + ')">' +
                           '<i class="glyphicon ' + roleIcon + '"></i> ' +
                           '<span class="badge-count">' + count + '</span>' +
                           '</span>';
            }
            
            // Add quick action button
            badgeHtml += '<button class="stakeholder-quick-action" ' +
                       'onclick="StakeholderIntegration.showQuickActions(\'' + dealId + '\', event)" ' +
                       'title="Manage stakeholders">' +
                       '<i class="glyphicon glyphicon-plus-sign"></i>' +
                       '</button>';
            
            badgeHtml += '</div>';
            
            // Insert after deal header
            dealCard.find('.deal-card-header').after(badgeHtml);
        },
        
        /**
         * Group stakeholders by role
         */
        groupStakeholdersByRole: function(stakeholders) {
            var groups = {};
            
            stakeholders.forEach(function(stakeholder) {
                var role = stakeholder.contact_role || 'Other';
                if (!groups[role]) {
                    groups[role] = [];
                }
                groups[role].push(stakeholder);
            });
            
            return groups;
        },
        
        /**
         * Get CSS class for role
         */
        getRoleClass: function(role) {
            var roleClasses = {
                'Decision Maker': 'role-decision-maker',
                'Executive Champion': 'role-champion',
                'Technical Evaluator': 'role-technical',
                'Business User': 'role-business',
                'Other': 'role-other'
            };
            
            return roleClasses[role] || 'role-other';
        },
        
        /**
         * Get icon for role
         */
        getRoleIcon: function(role) {
            var roleIcons = {
                'Decision Maker': 'glyphicon-star',
                'Executive Champion': 'glyphicon-thumbs-up',
                'Technical Evaluator': 'glyphicon-cog',
                'Business User': 'glyphicon-user',
                'Other': 'glyphicon-tag'
            };
            
            return roleIcons[role] || 'glyphicon-tag';
        },
        
        /**
         * Show quick actions menu
         */
        showQuickActions: function(dealId, event) {
            event.stopPropagation();
            
            // Close any existing menus
            $('.stakeholder-quick-menu').remove();
            
            var stakeholders = this.stakeholderCache[dealId] || [];
            var menuHtml = this.buildQuickActionsMenu(dealId, stakeholders);
            
            // Position menu near the button
            var button = $(event.target).closest('.stakeholder-quick-action');
            var offset = button.offset();
            
            $('body').append(menuHtml);
            
            var menu = $('.stakeholder-quick-menu');
            menu.css({
                top: offset.top + button.height() + 5,
                left: offset.left - menu.width() + button.width()
            });
            
            // Close menu on outside click
            setTimeout(function() {
                $(document).one('click', function() {
                    menu.remove();
                });
            }, 100);
        },
        
        /**
         * Build quick actions menu HTML
         */
        buildQuickActionsMenu: function(dealId, stakeholders) {
            var html = '<div class="stakeholder-quick-menu" data-deal-id="' + dealId + '">';
            
            // Header
            html += '<div class="menu-header">';
            html += '<h4>Stakeholders</h4>';
            html += '<button class="close-menu" onclick="$(\'.stakeholder-quick-menu\').remove()">&times;</button>';
            html += '</div>';
            
            // Actions
            html += '<div class="menu-actions">';
            html += '<button class="btn btn-sm btn-primary" onclick="StakeholderIntegration.openAddStakeholder(\'' + dealId + '\')">';
            html += '<i class="glyphicon glyphicon-plus"></i> Add Stakeholder</button>';
            html += '<button class="btn btn-sm btn-default" onclick="StakeholderIntegration.openManageStakeholders(\'' + dealId + '\')">';
            html += '<i class="glyphicon glyphicon-cog"></i> Manage All</button>';
            html += '<button class="btn btn-sm btn-info" onclick="StakeholderIntegration.viewCommunicationHistory(\'' + dealId + '\')">';
            html += '<i class="glyphicon glyphicon-envelope"></i> Communications</button>';
            html += '</div>';
            
            // Stakeholder list
            if (stakeholders.length > 0) {
                html += '<div class="menu-stakeholder-list">';
                html += '<h5>Current Stakeholders</h5>';
                
                stakeholders.forEach(function(stakeholder) {
                    html += '<div class="stakeholder-item">';
                    html += '<div class="stakeholder-info">';
                    html += '<strong>' + stakeholder.first_name + ' ' + stakeholder.last_name + '</strong>';
                    html += '<span class="stakeholder-role">' + stakeholder.contact_role + '</span>';
                    if (stakeholder.communication_count > 0) {
                        html += '<span class="comm-count" title="Communications">';
                        html += '<i class="glyphicon glyphicon-comment"></i> ' + stakeholder.communication_count;
                        html += '</span>';
                    }
                    html += '</div>';
                    html += '<div class="stakeholder-actions">';
                    html += '<a href="index.php?module=Contacts&action=DetailView&record=' + stakeholder.contact_id + '" target="_blank" class="btn btn-xs btn-default">';
                    html += '<i class="glyphicon glyphicon-eye-open"></i></a>';
                    html += '<button class="btn btn-xs btn-warning" onclick="StakeholderIntegration.editRole(\'' + stakeholder.relationship_id + '\', \'' + stakeholder.contact_role + '\')">';
                    html += '<i class="glyphicon glyphicon-pencil"></i></button>';
                    html += '</div>';
                    html += '</div>';
                });
                
                html += '</div>';
            } else {
                html += '<div class="menu-empty">No stakeholders assigned</div>';
            }
            
            html += '</div>';
            
            return html;
        },
        
        /**
         * Open add stakeholder dialog
         */
        openAddStakeholder: function(dealId) {
            var self = this;
            
            // Create modal
            var modalHtml = this.buildAddStakeholderModal(dealId);
            $('body').append(modalHtml);
            
            var modal = $('#addStakeholderModal');
            modal.modal('show');
            
            // Initialize contact search
            this.initContactSearch(modal);
            
            // Handle save
            modal.find('.save-stakeholder').on('click', function() {
                self.saveNewStakeholder(dealId, modal);
            });
            
            // Clean up on close
            modal.on('hidden.bs.modal', function() {
                modal.remove();
            });
        },
        
        /**
         * Build add stakeholder modal HTML
         */
        buildAddStakeholderModal: function(dealId) {
            var html = '<div class="modal fade" id="addStakeholderModal" tabindex="-1">';
            html += '<div class="modal-dialog">';
            html += '<div class="modal-content">';
            
            // Header
            html += '<div class="modal-header">';
            html += '<button type="button" class="close" data-dismiss="modal">&times;</button>';
            html += '<h4 class="modal-title">Add Stakeholder</h4>';
            html += '</div>';
            
            // Body
            html += '<div class="modal-body">';
            html += '<form>';
            
            // Contact search
            html += '<div class="form-group">';
            html += '<label>Contact</label>';
            html += '<div class="input-group">';
            html += '<input type="text" class="form-control" id="contactSearch" placeholder="Search for contact...">';
            html += '<input type="hidden" id="selectedContactId">';
            html += '<span class="input-group-btn">';
            html += '<button class="btn btn-default" type="button" onclick="StakeholderIntegration.searchContacts()">';
            html += '<i class="glyphicon glyphicon-search"></i></button>';
            html += '</span>';
            html += '</div>';
            html += '<div id="contactSearchResults"></div>';
            html += '</div>';
            
            // Role selection
            html += '<div class="form-group">';
            html += '<label>Role</label>';
            html += '<select class="form-control" id="contactRole">';
            html += '<option value="Decision Maker">Decision Maker</option>';
            html += '<option value="Executive Champion">Executive Champion</option>';
            html += '<option value="Technical Evaluator">Technical Evaluator</option>';
            html += '<option value="Business User">Business User</option>';
            html += '<option value="Other">Other</option>';
            html += '</select>';
            html += '</div>';
            
            html += '</form>';
            html += '</div>';
            
            // Footer
            html += '<div class="modal-footer">';
            html += '<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>';
            html += '<button type="button" class="btn btn-primary save-stakeholder">Add Stakeholder</button>';
            html += '</div>';
            
            html += '</div>';
            html += '</div>';
            html += '</div>';
            
            return html;
        },
        
        /**
         * Initialize contact search
         */
        initContactSearch: function(modal) {
            var self = this;
            var searchInput = modal.find('#contactSearch');
            var searchTimeout;
            
            searchInput.on('keyup', function() {
                clearTimeout(searchTimeout);
                var query = $(this).val();
                
                if (query.length >= 2) {
                    searchTimeout = setTimeout(function() {
                        self.searchContacts(query, modal);
                    }, 300);
                }
            });
        },
        
        /**
         * Search contacts
         */
        searchContacts: function(query, modal) {
            if (!query) {
                query = modal ? modal.find('#contactSearch').val() : $('#contactSearch').val();
            }
            
            var resultsContainer = modal ? modal.find('#contactSearchResults') : $('#contactSearchResults');
            
            $.ajax({
                url: 'index.php?module=Contacts&action=quicksearch',
                type: 'GET',
                data: {
                    query: query,
                    limit: 10
                },
                success: function(response) {
                    var html = '<div class="contact-search-results">';
                    
                    if (response.results && response.results.length > 0) {
                        response.results.forEach(function(contact) {
                            html += '<div class="contact-result" onclick="StakeholderIntegration.selectContact(\'' + 
                                  contact.id + '\', \'' + contact.name.replace(/'/g, "\\'") + '\')">';
                            html += '<strong>' + contact.name + '</strong>';
                            if (contact.account_name) {
                                html += ' - ' + contact.account_name;
                            }
                            html += '</div>';
                        });
                    } else {
                        html += '<div class="no-results">No contacts found</div>';
                    }
                    
                    html += '</div>';
                    resultsContainer.html(html);
                }
            });
        },
        
        /**
         * Select contact from search results
         */
        selectContact: function(contactId, contactName) {
            $('#selectedContactId').val(contactId);
            $('#contactSearch').val(contactName);
            $('#contactSearchResults').empty();
        },
        
        /**
         * Save new stakeholder
         */
        saveNewStakeholder: function(dealId, modal) {
            var self = this;
            var contactId = modal.find('#selectedContactId').val();
            var role = modal.find('#contactRole').val();
            
            if (!contactId) {
                alert('Please select a contact');
                return;
            }
            
            $.ajax({
                url: 'index.php?module=Deals&action=stakeholders',
                type: 'POST',
                data: {
                    deal_id: dealId,
                    contact_id: contactId,
                    contact_role: role
                },
                success: function(response) {
                    if (response.success) {
                        // Clear cache and reload
                        delete self.stakeholderCache[dealId];
                        self.loadStakeholders(dealId);
                        modal.modal('hide');
                        
                        // Show success message
                        PipelineView.showNotification('Stakeholder added successfully', 'success');
                    } else {
                        alert(response.error || 'Failed to add stakeholder');
                    }
                }
            });
        },
        
        /**
         * Open manage stakeholders dialog
         */
        openManageStakeholders: function(dealId) {
            // This would open a more comprehensive management interface
            // For now, redirect to deal detail view
            window.open('index.php?module=Opportunities&action=DetailView&record=' + dealId + '#stakeholders', '_blank');
        },
        
        /**
         * View communication history
         */
        viewCommunicationHistory: function(dealId) {
            var self = this;
            
            // Load communication history
            $.ajax({
                url: 'index.php?module=Deals&action=stakeholders&deal_id=' + dealId + '&action=communication',
                type: 'GET',
                success: function(response) {
                    if (response.success) {
                        self.showCommunicationModal(dealId, response.communications);
                    }
                }
            });
        },
        
        /**
         * Show communication history modal
         */
        showCommunicationModal: function(dealId, communications) {
            var modalHtml = this.buildCommunicationModal(dealId, communications);
            $('body').append(modalHtml);
            
            var modal = $('#communicationModal');
            modal.modal('show');
            
            modal.on('hidden.bs.modal', function() {
                modal.remove();
            });
        },
        
        /**
         * Build communication history modal
         */
        buildCommunicationModal: function(dealId, communications) {
            var html = '<div class="modal fade" id="communicationModal" tabindex="-1">';
            html += '<div class="modal-dialog modal-lg">';
            html += '<div class="modal-content">';
            
            // Header
            html += '<div class="modal-header">';
            html += '<button type="button" class="close" data-dismiss="modal">&times;</button>';
            html += '<h4 class="modal-title">Stakeholder Communications</h4>';
            html += '</div>';
            
            // Body
            html += '<div class="modal-body">';
            
            if (communications.length > 0) {
                html += '<div class="communication-timeline">';
                
                communications.forEach(function(comm) {
                    var icon = comm.type === 'email' ? 'envelope' : 
                             comm.type === 'call' ? 'phone' : 'calendar';
                    
                    html += '<div class="timeline-item">';
                    html += '<div class="timeline-icon ' + comm.type + '">';
                    html += '<i class="glyphicon glyphicon-' + icon + '"></i>';
                    html += '</div>';
                    html += '<div class="timeline-content">';
                    html += '<div class="timeline-header">';
                    html += '<strong>' + comm.subject + '</strong>';
                    html += '<span class="timeline-date">' + comm.date_start + '</span>';
                    html += '</div>';
                    html += '<div class="timeline-details">';
                    html += 'With: ' + comm.contact_name + ' (' + comm.contact_role + ')';
                    if (comm.assigned_user_name) {
                        html += ' | By: ' + comm.assigned_user_name;
                    }
                    html += '</div>';
                    html += '</div>';
                    html += '</div>';
                });
                
                html += '</div>';
            } else {
                html += '<p>No communications recorded</p>';
            }
            
            html += '</div>';
            
            // Footer
            html += '<div class="modal-footer">';
            html += '<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>';
            html += '</div>';
            
            html += '</div>';
            html += '</div>';
            html += '</div>';
            
            return html;
        },
        
        /**
         * Edit stakeholder role
         */
        editRole: function(relationshipId, currentRole) {
            var newRole = prompt('Select new role:', currentRole);
            
            if (newRole && newRole !== currentRole) {
                $.ajax({
                    url: 'index.php?module=Deals&action=stakeholders&relationship_id=' + relationshipId,
                    type: 'PUT',
                    data: {
                        contact_role: newRole
                    },
                    success: function(response) {
                        if (response.success) {
                            // Clear cache and reload all stakeholders
                            StakeholderIntegration.stakeholderCache = {};
                            StakeholderIntegration.loadAllStakeholders();
                            
                            PipelineView.showNotification('Role updated successfully', 'success');
                        }
                    }
                });
            }
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;
            
            // Handle stakeholder badge clicks
            $(document).on('click', '.stakeholder-badge', function(e) {
                e.stopPropagation();
                var dealId = $(this).closest('.deal-card').data('deal-id');
                var role = $(this).data('role');
                self.showRoleStakeholders(dealId, role);
            });
            
            // Handle deal card updates
            $(document).on('pipeline:dealMoved', function(e, data) {
                // Reload stakeholders for the moved deal
                self.loadStakeholders(data.dealId);
            });
        },
        
        /**
         * Show stakeholders for a specific role
         */
        showRoleStakeholders: function(dealId, role) {
            var stakeholders = this.stakeholderCache[dealId] || [];
            var roleStakeholders = stakeholders.filter(function(s) {
                return s.contact_role === role;
            });
            
            var html = '<div class="role-stakeholders-popup">';
            html += '<h5>' + role + 's</h5>';
            
            roleStakeholders.forEach(function(s) {
                html += '<div class="stakeholder-mini">';
                html += s.first_name + ' ' + s.last_name;
                if (s.title) {
                    html += ' - ' + s.title;
                }
                html += '</div>';
            });
            
            html += '</div>';
            
            // Show as tooltip
            var badge = $('.stakeholder-badge[data-role="' + role + '"]');
            badge.tooltip({
                title: html,
                html: true,
                trigger: 'manual',
                placement: 'bottom'
            }).tooltip('show');
            
            setTimeout(function() {
                badge.tooltip('hide');
            }, 3000);
        }
    };
    
    return module;
})();

// Initialize when document is ready
$(document).ready(function() {
    if ($('#pipeline-container').length > 0) {
        StakeholderIntegration.init();
    }
});