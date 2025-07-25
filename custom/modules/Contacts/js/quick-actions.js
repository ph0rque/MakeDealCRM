/**
 * Quick Actions JavaScript
 * Handles floating buttons, quick menus, and rapid contact actions
 */

(function($) {
    'use strict';

    window.StakeholderQuickActions = {
        
        // Initialize quick actions
        init: function() {
            this.createFloatingButton();
            this.bindEvents();
            this.initializeTooltips();
        },

        // Create floating action button
        createFloatingButton: function() {
            const fabHtml = `
                <button class="quick-access-fab" id="quickAccessFab">
                    <i class="fa fa-bolt"></i>
                </button>
                <div class="quick-access-menu" id="quickAccessMenu">
                    <a href="#" class="quick-access-item" data-action="bulk-email">
                        <i class="fa fa-envelope"></i>
                        <span>Send Bulk Email</span>
                    </a>
                    <a href="#" class="quick-access-item" data-action="overdue-contacts">
                        <i class="fa fa-exclamation-triangle"></i>
                        <span>View Overdue Contacts</span>
                    </a>
                    <a href="#" class="quick-access-item" data-action="schedule-followups">
                        <i class="fa fa-calendar"></i>
                        <span>Schedule Follow-ups</span>
                    </a>
                    <a href="#" class="quick-access-item" data-action="export-stakeholders">
                        <i class="fa fa-download"></i>
                        <span>Export Stakeholders</span>
                    </a>
                </div>
            `;
            
            $('body').append(fabHtml);
        },

        // Bind event handlers
        bindEvents: function() {
            // Floating button toggle
            $('#quickAccessFab').on('click', function() {
                $('#quickAccessMenu').toggleClass('active');
            });

            // Close menu when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#quickAccessFab, #quickAccessMenu').length) {
                    $('#quickAccessMenu').removeClass('active');
                }
            });

            // Quick menu actions
            $('.quick-access-item').on('click', function(e) {
                e.preventDefault();
                const action = $(this).data('action');
                StakeholderQuickActions.handleQuickAction(action);
            });

            // Row quick actions
            $(document).on('click', '.quick-action-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const $btn = $(this);
                const action = $btn.data('action');
                const contactId = $btn.closest('[data-contact-id]').data('contact-id');
                StakeholderQuickActions.handleRowAction(action, contactId);
            });
        },

        // Handle quick menu actions
        handleQuickAction: function(action) {
            switch(action) {
                case 'bulk-email':
                    this.openBulkEmailDialog();
                    break;
                case 'overdue-contacts':
                    this.showOverdueContacts();
                    break;
                case 'schedule-followups':
                    this.openScheduleDialog();
                    break;
                case 'export-stakeholders':
                    this.exportStakeholders();
                    break;
            }
            $('#quickAccessMenu').removeClass('active');
        },

        // Handle row-level quick actions
        handleRowAction: function(action, contactId) {
            switch(action) {
                case 'quick-email':
                    this.sendQuickEmail(contactId);
                    break;
                case 'quick-call':
                    this.logQuickCall(contactId);
                    break;
                case 'quick-note':
                    this.addQuickNote(contactId);
                    break;
                case 'schedule-followup':
                    this.scheduleFollowup(contactId);
                    break;
            }
        },

        // Show contact options menu
        showContactOptions: function(contactId, $element) {
            const menuHtml = `
                <div class="contact-quick-menu" id="contact-menu-${contactId}">
                    <div class="contact-quick-menu-item" data-action="email-now">
                        <i class="fa fa-envelope"></i> Email Now
                    </div>
                    <div class="contact-quick-menu-item" data-action="schedule-call">
                        <i class="fa fa-phone"></i> Schedule Call
                    </div>
                    <div class="contact-quick-menu-item" data-action="update-status">
                        <i class="fa fa-refresh"></i> Update Status
                    </div>
                </div>
            `;

            // Remove any existing menus
            $('.contact-quick-menu').remove();

            // Position and show menu
            const $menu = $(menuHtml);
            $('body').append($menu);
            
            const offset = $element.offset();
            $menu.css({
                top: offset.top + $element.outerHeight() + 5,
                left: offset.left
            }).addClass('active');

            // Handle menu item clicks
            $menu.find('.contact-quick-menu-item').on('click', function() {
                const action = $(this).data('action');
                StakeholderQuickActions.executeContactAction(contactId, action);
                $menu.remove();
            });

            // Close on outside click
            setTimeout(function() {
                $(document).one('click', function() {
                    $menu.remove();
                });
            }, 100);
        },

        // Execute contact-specific action
        executeContactAction: function(contactId, action) {
            switch(action) {
                case 'email-now':
                    this.openEmailComposer(contactId);
                    break;
                case 'schedule-call':
                    this.openCallScheduler(contactId);
                    break;
                case 'update-status':
                    this.updateContactStatus(contactId);
                    break;
            }
        },

        // Open email composer with templates
        openEmailComposer: function(contactId) {
            $.ajax({
                url: 'index.php?module=Contacts&action=GetEmailTemplates',
                data: { contact_id: contactId },
                success: function(templates) {
                    const dialog = new EmailComposerDialog(contactId, templates);
                    dialog.show();
                }
            });
        },

        // Send quick email
        sendQuickEmail: function(contactId) {
            // Show template selector
            const templates = this.getEmailTemplates();
            const $selector = this.createTemplateSelector(templates);
            
            $selector.on('template-selected', function(e, template) {
                StakeholderQuickActions.sendEmailWithTemplate(contactId, template);
            });
        },

        // Create template selector UI
        createTemplateSelector: function(templates) {
            const selectorHtml = `
                <div class="template-selector-overlay">
                    <div class="template-selector">
                        <h3>Select Email Template</h3>
                        <div class="template-list">
                            ${templates.map(t => `
                                <div class="template-item" data-template-id="${t.id}">
                                    <h4>${t.name}</h4>
                                    <p>${t.description}</p>
                                </div>
                            `).join('')}
                        </div>
                        <div class="template-actions">
                            <button class="btn btn-cancel">Cancel</button>
                        </div>
                    </div>
                </div>
            `;

            const $selector = $(selectorHtml);
            $('body').append($selector);

            // Handle template selection
            $selector.find('.template-item').on('click', function() {
                const templateId = $(this).data('template-id');
                const template = templates.find(t => t.id === templateId);
                $selector.trigger('template-selected', [template]);
                $selector.remove();
            });

            // Handle cancel
            $selector.find('.btn-cancel').on('click', function() {
                $selector.remove();
            });

            return $selector;
        },

        // Get available email templates
        getEmailTemplates: function() {
            // This would typically fetch from server
            return [
                { id: 1, name: 'Check-in Email', description: 'Regular stakeholder check-in' },
                { id: 2, name: 'Meeting Request', description: 'Request for stakeholder meeting' },
                { id: 3, name: 'Project Update', description: 'Update on project progress' },
                { id: 4, name: 'Thank You', description: 'Thank you for recent interaction' }
            ];
        },

        // Initialize tooltips
        initializeTooltips: function() {
            // Initialize all tooltips
            $('.stakeholder-tooltip').each(function() {
                const $element = $(this);
                const contactId = $element.data('contact-id');
                
                // Load contact history on hover
                $element.on('mouseenter', function() {
                    if (!$element.data('history-loaded')) {
                        StakeholderQuickActions.loadContactHistory(contactId, $element);
                    }
                });
            });
        },

        // Load contact history for tooltip
        loadContactHistory: function(contactId, $element) {
            $.ajax({
                url: 'index.php?module=Contacts&action=GetRecentHistory',
                data: { contact_id: contactId, limit: 3 },
                success: function(data) {
                    const historyHtml = StakeholderQuickActions.renderContactHistory(data);
                    $element.find('.contact-history-preview').html(historyHtml);
                    $element.data('history-loaded', true);
                }
            });
        },

        // Render contact history HTML
        renderContactHistory: function(history) {
            return history.map(item => `
                <div class="contact-history-item">
                    <div class="contact-history-icon ${item.type}">
                        <i class="fa fa-${this.getHistoryIcon(item.type)}"></i>
                    </div>
                    <div class="contact-history-details">
                        <div class="contact-history-type">${item.subject}</div>
                        <div class="contact-history-date">${this.formatRelativeDate(item.date)}</div>
                    </div>
                </div>
            `).join('');
        },

        // Get icon for history type
        getHistoryIcon: function(type) {
            const icons = {
                'email': 'envelope',
                'call': 'phone',
                'meeting': 'calendar',
                'note': 'sticky-note'
            };
            return icons[type] || 'info-circle';
        },

        // Format relative date
        formatRelativeDate: function(dateStr) {
            const date = new Date(dateStr);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);

            if (diffMins < 60) return diffMins + ' mins ago';
            if (diffHours < 24) return diffHours + ' hours ago';
            if (diffDays < 7) return diffDays + ' days ago';
            
            return date.toLocaleDateString();
        },

        // Show overdue contacts
        showOverdueContacts: function() {
            window.location.href = 'index.php?module=Contacts&action=index&filter=overdue_stakeholders';
        },

        // Open bulk email dialog
        openBulkEmailDialog: function() {
            // This would open a dialog for bulk email operations
            alert('Bulk email feature - to be implemented');
        },

        // Export stakeholders
        exportStakeholders: function() {
            window.location.href = 'index.php?module=Contacts&action=ExportStakeholders';
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        StakeholderQuickActions.init();
    });

})(jQuery);