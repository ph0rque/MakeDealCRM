/**
 * Deal Checklist Manager
 * Handles due diligence checklist functionality for deals
 */

var DealChecklist = (function() {
    'use strict';
    
    var currentDealId = null;
    var checklistData = null;
    
    return {
        /**
         * Initialize checklist for a deal
         */
        init: function(dealId) {
            currentDealId = dealId;
            this.loadChecklist(dealId);
            this.attachEventHandlers();
        },
        
        /**
         * Load checklist data from server
         */
        loadChecklist: function(dealId) {
            var self = this;
            
            $.ajax({
                url: 'index.php?module=Deals&action=checklistApi',
                type: 'POST',
                data: {
                    action: 'getChecklist',
                    deal_id: dealId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        checklistData = response.data;
                        self.renderChecklist(response.data);
                    } else {
                        self.showError(response.message || 'Failed to load checklist');
                    }
                },
                error: function() {
                    self.showError('Network error loading checklist');
                }
            });
        },
        
        /**
         * Render checklist HTML
         */
        renderChecklist: function(data) {
            var html = '';
            
            if (!data || !data.sections || data.sections.length === 0) {
                html = '<div class="no-checklist">No checklist items available for this deal stage.</div>';
            } else {
                html = this.buildChecklistHTML(data);
            }
            
            $('#checklist-content').html(html);
            this.updateProgressBars();
        },
        
        /**
         * Build checklist HTML structure
         */
        buildChecklistHTML: function(data) {
            var html = '<div class="checklist-sections">';
            
            // Overall progress
            html += '<div class="overall-progress">';
            html += '<div class="progress-header">';
            html += '<span>Overall Progress</span>';
            html += '<span class="progress-text">' + data.overall_progress + '%</span>';
            html += '</div>';
            html += '<div class="progress">';
            html += '<div class="progress-bar" role="progressbar" style="width: ' + data.overall_progress + '%" aria-valuenow="' + data.overall_progress + '" aria-valuemin="0" aria-valuemax="100"></div>';
            html += '</div>';
            html += '</div>';
            
            // Sections
            data.sections.forEach(function(section, index) {
                html += '<div class="checklist-section" data-section-id="' + section.id + '">';
                html += '<div class="section-header" onclick="DealChecklist.toggleSection(' + index + ')">';
                html += '<span class="section-toggle">▼</span>';
                html += '<h4>' + section.name + '</h4>';
                html += '<span class="section-progress">' + section.progress + '%</span>';
                html += '</div>';
                html += '<div class="section-content" id="section-content-' + index + '">';
                
                // Section items
                if (section.items && section.items.length > 0) {
                    html += '<ul class="checklist-items">';
                    section.items.forEach(function(item) {
                        html += '<li class="checklist-item' + (item.completed ? ' completed' : '') + '" data-item-id="' + item.id + '">';
                        html += '<label>';
                        html += '<input type="checkbox" ' + (item.completed ? 'checked' : '') + ' onchange="DealChecklist.toggleItem(\'' + item.id + '\', this.checked)">';
                        html += '<span class="item-text">' + item.description + '</span>';
                        if (item.required) {
                            html += ' <span class="required-badge">Required</span>';
                        }
                        html += '</label>';
                        if (item.notes) {
                            html += '<div class="item-notes">' + item.notes + '</div>';
                        }
                        html += '</li>';
                    });
                    html += '</ul>';
                } else {
                    html += '<p class="no-items">No items in this section.</p>';
                }
                
                html += '</div>';
                html += '</div>';
            });
            
            html += '</div>';
            return html;
        },
        
        /**
         * Toggle section visibility
         */
        toggleSection: function(index) {
            var content = $('#section-content-' + index);
            var toggle = content.siblings('.section-header').find('.section-toggle');
            
            if (content.is(':visible')) {
                content.slideUp();
                toggle.text('▶');
            } else {
                content.slideDown();
                toggle.text('▼');
            }
        },
        
        /**
         * Toggle all sections
         */
        toggleAllSections: function() {
            var allVisible = $('.section-content:visible').length === $('.section-content').length;
            
            if (allVisible) {
                $('.section-content').slideUp();
                $('.section-toggle').text('▶');
            } else {
                $('.section-content').slideDown();
                $('.section-toggle').text('▼');
            }
        },
        
        /**
         * Toggle checklist item completion
         */
        toggleItem: function(itemId, checked) {
            var self = this;
            
            $.ajax({
                url: 'index.php?module=Deals&action=checklistApi',
                type: 'POST',
                data: {
                    action: 'toggleItem',
                    deal_id: currentDealId,
                    item_id: itemId,
                    completed: checked ? 1 : 0
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Update UI
                        var item = $('[data-item-id="' + itemId + '"]');
                        if (checked) {
                            item.addClass('completed');
                        } else {
                            item.removeClass('completed');
                        }
                        
                        // Refresh to update progress
                        self.refreshChecklist(currentDealId);
                    } else {
                        // Revert checkbox
                        $('[data-item-id="' + itemId + '"] input').prop('checked', !checked);
                        self.showError(response.message || 'Failed to update item');
                    }
                },
                error: function() {
                    // Revert checkbox
                    $('[data-item-id="' + itemId + '"] input').prop('checked', !checked);
                    self.showError('Network error updating item');
                }
            });
        },
        
        /**
         * Refresh checklist
         */
        refreshChecklist: function(dealId) {
            this.loadChecklist(dealId || currentDealId);
        },
        
        /**
         * Update progress bars with animation
         */
        updateProgressBars: function() {
            $('.progress-bar').each(function() {
                var $bar = $(this);
                var width = $bar.attr('aria-valuenow') + '%';
                $bar.css('width', '0%').animate({ width: width }, 1000);
            });
        },
        
        /**
         * Show error message
         */
        showError: function(message) {
            $('#checklist-content').html(
                '<div class="alert alert-danger">' +
                '<strong>Error:</strong> ' + message +
                '</div>'
            );
        },
        
        /**
         * Attach event handlers
         */
        attachEventHandlers: function() {
            // Add any global event handlers here
        }
    };
})();