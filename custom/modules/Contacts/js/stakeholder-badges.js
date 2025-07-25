/**
 * Stakeholder Badges JavaScript
 * Handles badge generation, updates, and interactions
 */

(function($) {
    'use strict';

    // Stakeholder badge manager
    window.StakeholderBadges = {
        
        // Calculate freshness status based on last contact and frequency
        calculateFreshness: function(lastContact, frequency) {
            if (!lastContact || !frequency) {
                return 'inactive';
            }

            const now = new Date();
            const lastContactDate = new Date(lastContact);
            const daysSinceContact = Math.floor((now - lastContactDate) / (1000 * 60 * 60 * 24));
            
            // Calculate thresholds based on frequency
            const warningThreshold = frequency * 0.8;
            const overdueThreshold = frequency;

            if (daysSinceContact <= warningThreshold) {
                return 'fresh';
            } else if (daysSinceContact <= overdueThreshold) {
                return 'warning';
            } else {
                return 'overdue';
            }
        },

        // Generate badge HTML
        generateBadge: function(status, options) {
            options = $.extend({
                showIcon: true,
                showTooltip: true,
                compact: false,
                lastContact: null,
                nextDue: null
            }, options);

            const statusConfig = {
                fresh: {
                    text: 'Fresh',
                    icon: 'fa-check-circle',
                    tooltip: 'Recently contacted'
                },
                warning: {
                    text: 'Due Soon',
                    icon: 'fa-exclamation-triangle',
                    tooltip: 'Contact needed soon'
                },
                overdue: {
                    text: 'Overdue',
                    icon: 'fa-times-circle',
                    tooltip: 'Contact overdue!'
                },
                inactive: {
                    text: 'Inactive',
                    icon: 'fa-minus-circle',
                    tooltip: 'No tracking set'
                }
            };

            const config = statusConfig[status] || statusConfig.inactive;
            const classes = ['stakeholder-badge', status];
            if (options.compact) classes.push('compact');

            let html = '<span class="' + classes.join(' ') + '">';
            
            if (options.showIcon) {
                html += '<span class="stakeholder-badge-icon">';
                html += '<i class="fa ' + config.icon + '"></i> ';
                html += config.text;
                html += '</span>';
            } else {
                html += config.text;
            }

            if (options.showTooltip) {
                let tooltipText = config.tooltip;
                if (options.lastContact) {
                    tooltipText += ' (Last: ' + this.formatDate(options.lastContact) + ')';
                }
                if (options.nextDue) {
                    tooltipText += ' (Due: ' + this.formatDate(options.nextDue) + ')';
                }
                html += '<span class="stakeholder-badge-tooltip">' + tooltipText + '</span>';
            }

            html += '</span>';
            return html;
        },

        // Generate role badge
        generateRoleBadge: function(role) {
            const roleClasses = {
                'Decision Maker': 'decision-maker',
                'Influencer': 'influencer',
                'Champion': 'champion',
                'Blocker': 'blocker'
            };

            const roleClass = roleClasses[role] || '';
            return '<span class="stakeholder-role ' + roleClass + '">' + role + '</span>';
        },

        // Format date for display
        formatDate: function(dateStr) {
            const date = new Date(dateStr);
            const now = new Date();
            const daysDiff = Math.floor((now - date) / (1000 * 60 * 60 * 24));

            if (daysDiff === 0) return 'Today';
            if (daysDiff === 1) return 'Yesterday';
            if (daysDiff < 7) return daysDiff + ' days ago';
            if (daysDiff < 30) return Math.floor(daysDiff / 7) + ' weeks ago';
            
            return date.toLocaleDateString();
        },

        // Update all badges on the page
        updateAllBadges: function() {
            $('.stakeholder-badge-container').each(function() {
                const $container = $(this);
                const lastContact = $container.data('last-contact');
                const frequency = parseInt($container.data('frequency')) || 0;
                const status = StakeholderBadges.calculateFreshness(lastContact, frequency);
                
                const badge = StakeholderBadges.generateBadge(status, {
                    lastContact: lastContact,
                    nextDue: StakeholderBadges.calculateNextDue(lastContact, frequency)
                });
                
                $container.html(badge);
            });
        },

        // Calculate next due date
        calculateNextDue: function(lastContact, frequency) {
            if (!lastContact || !frequency) return null;
            
            const lastDate = new Date(lastContact);
            const nextDue = new Date(lastDate);
            nextDue.setDate(nextDue.getDate() + frequency);
            
            return nextDue.toISOString();
        },

        // Initialize badge system
        init: function() {
            // Update badges on page load
            this.updateAllBadges();

            // Set up periodic updates
            setInterval(function() {
                StakeholderBadges.updateAllBadges();
            }, 60000); // Update every minute

            // Handle dynamic content
            $(document).on('DOMNodeInserted', function(e) {
                if ($(e.target).find('.stakeholder-badge-container').length) {
                    StakeholderBadges.updateAllBadges();
                }
            });
        }
    };

    // Quick action handler for badges
    $(document).on('click', '.stakeholder-badge.overdue, .stakeholder-badge.warning', function(e) {
        e.stopPropagation();
        const $badge = $(this);
        const contactId = $badge.closest('[data-contact-id]').data('contact-id');
        
        if (contactId) {
            // Show quick contact options
            StakeholderQuickActions.showContactOptions(contactId, $badge);
        }
    });

    // Initialize on document ready
    $(document).ready(function() {
        StakeholderBadges.init();
    });

})(jQuery);