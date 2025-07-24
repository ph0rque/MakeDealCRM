/**
 * Progress Indicators JavaScript
 * 
 * Manages progress bar updates, tooltips, and real-time progress tracking
 * for deal cards in the pipeline view
 */

var ProgressIndicators = {
    config: {
        tooltipDelay: 500,
        updateInterval: 30000, // 30 seconds
        animationDuration: 300,
        enableRealTimeUpdates: true,
        enableTooltips: true
    },
    
    activeTooltips: {},
    updateTimer: null,
    
    /**
     * Initialize progress indicators
     */
    init: function(options) {
        this.config = jQuery.extend(this.config, options);
        
        // Initialize tooltips
        if (this.config.enableTooltips) {
            this.initTooltips();
        }
        
        // Initialize real-time updates
        if (this.config.enableRealTimeUpdates) {
            this.initRealTimeUpdates();
        }
        
        // Initialize event handlers
        this.initEventHandlers();
        
        // Load initial progress data
        this.loadProgressData();
    },
    
    /**
     * Initialize tooltip functionality
     */
    initTooltips: function() {
        var self = this;
        
        // Progress bar tooltips
        jQuery(document).on('mouseenter', '.deal-progress-container', function(e) {
            var dealId = jQuery(this).closest('.deal-card').data('deal-id');
            self.showProgressTooltip(this, dealId);
        });
        
        jQuery(document).on('mouseleave', '.deal-progress-container', function(e) {
            var tooltipId = jQuery(this).data('tooltip-id');
            if (tooltipId) {
                self.hideTooltip(tooltipId);
            }
        });
        
        // Status badge tooltips
        jQuery(document).on('mouseenter', '.deal-status-badge', function(e) {
            var badge = jQuery(this);
            var status = badge.text();
            var description = self.getStatusDescription(status);
            self.showSimpleTooltip(this, description);
        });
        
        jQuery(document).on('mouseleave', '.deal-status-badge', function(e) {
            var tooltipId = jQuery(this).data('tooltip-id');
            if (tooltipId) {
                self.hideTooltip(tooltipId);
            }
        });
    },
    
    /**
     * Initialize real-time progress updates
     */
    initRealTimeUpdates: function() {
        var self = this;
        
        // Set up periodic updates
        this.updateTimer = setInterval(function() {
            self.updateAllProgressIndicators();
        }, this.config.updateInterval);
        
        // Listen for WebSocket updates if available
        if (window.WebSocket && window.location.protocol === 'https:') {
            this.initWebSocketUpdates();
        }
    },
    
    /**
     * Initialize WebSocket for real-time updates
     */
    initWebSocketUpdates: function() {
        var self = this;
        var wsUrl = 'wss://' + window.location.host + '/ws/progress-updates';
        
        try {
            this.websocket = new WebSocket(wsUrl);
            
            this.websocket.onopen = function() {
                console.log('Progress updates WebSocket connected');
            };
            
            this.websocket.onmessage = function(event) {
                try {
                    var data = JSON.parse(event.data);
                    if (data.type === 'progress_update') {
                        self.updateProgressIndicator(data.dealId, data.progress);
                    }
                } catch (e) {
                    console.warn('Invalid WebSocket message:', event.data);
                }
            };
            
            this.websocket.onclose = function() {
                console.log('Progress updates WebSocket closed');
                // Fallback to polling
                self.initRealTimeUpdates();
            };
            
            this.websocket.onerror = function(error) {
                console.warn('WebSocket error:', error);
            };
        } catch (e) {
            console.warn('WebSocket not supported, using polling');
        }
    },
    
    /**
     * Initialize event handlers
     */
    initEventHandlers: function() {
        var self = this;
        
        // Handle card moves to update progress context
        jQuery(document).on('cardMoved', function(e, data) {
            self.updateProgressContext(data.dealId, data.newStage);
        });
        
        // Handle focus toggle to update priority indicators
        jQuery(document).on('focusToggled', function(e, data) {
            self.updatePriorityIndicator(data.dealId, data.focused);
        });
        
        // Handle compact view toggle
        jQuery(document).on('compactViewToggled', function(e, compact) {
            self.handleCompactViewChange(compact);
        });
    },
    
    /**
     * Load initial progress data for all deals
     */
    loadProgressData: function() {
        var self = this;
        var dealIds = [];
        
        // Collect all deal IDs on the current board
        jQuery('.deal-card').each(function() {
            var dealId = jQuery(this).data('deal-id');
            if (dealId) {
                dealIds.push(dealId);
            }
        });
        
        if (dealIds.length === 0) {
            return;
        }
        
        // For now, generate mock data until backend API is ready
        dealIds.forEach(function(dealId) {
            var mockProgress = self.generateMockProgressData(dealId);
            self.renderProgressIndicators(dealId, mockProgress);
        });
        
        // TODO: Replace with actual API call when backend is ready
        /*
        jQuery.ajax({
            url: 'index.php?module=Deals&action=getProgressData',
            type: 'POST',
            data: { deal_ids: dealIds },
            success: function(response) {
                if (response.success && response.data) {
                    Object.keys(response.data).forEach(function(dealId) {
                        self.renderProgressIndicators(dealId, response.data[dealId]);
                    });
                }
            },
            error: function() {
                console.warn('Failed to load progress data, using mock data');
                dealIds.forEach(function(dealId) {
                    var mockProgress = self.generateMockProgressData(dealId);
                    self.renderProgressIndicators(dealId, mockProgress);
                });
            }
        });
        */
    },
    
    /**
     * Generate mock progress data for testing
     */
    generateMockProgressData: function(dealId) {
        // Generate realistic mock data based on deal ID
        var seed = parseInt(dealId) || Math.floor(Math.random() * 1000);
        var completedTasks = Math.floor((seed % 10) + 2);
        var totalTasks = Math.floor(completedTasks + (seed % 5) + 1);
        var percentage = Math.floor((completedTasks / totalTasks) * 100);
        
        var statuses = ['on-track', 'due-today', 'overdue', 'completed'];
        var priorities = ['low', 'medium', 'high', 'critical'];
        
        return {
            percentage: percentage,
            completedTasks: completedTasks,
            totalTasks: totalTasks,
            status: statuses[seed % statuses.length],
            priority: priorities[seed % priorities.length],
            checklist: this.generateMockChecklist(seed, completedTasks, totalTasks),
            lastUpdated: new Date(),
            overdueTasks: Math.floor(seed % 3),
            dueTodayTasks: Math.floor(seed % 2)
        };
    },
    
    /**
     * Generate mock checklist data
     */
    generateMockChecklist: function(seed, completed, total) {
        var taskNames = [
            'Market Analysis Complete',
            'Financial Review',
            'Legal Documentation',
            'Due Diligence Report',
            'Valuation Model',
            'Risk Assessment',
            'Stakeholder Approval',
            'Contract Negotiation',
            'Final Documentation',
            'Closing Preparation'
        ];
        
        var checklist = [];
        var statuses = ['completed', 'pending', 'overdue'];
        
        for (var i = 0; i < total; i++) {
            var status = i < completed ? 'completed' : statuses[(seed + i) % 2 + 1];
            checklist.push({
                id: i + 1,
                name: taskNames[(seed + i) % taskNames.length],
                status: status,
                dueDate: new Date(Date.now() + (i - completed) * 24 * 60 * 60 * 1000),
                priority: i < 3 ? 'high' : 'medium'
            });
        }
        
        return checklist;
    },
    
    /**
     * Render progress indicators for a deal card
     */
    renderProgressIndicators: function(dealId, progressData) {
        var card = jQuery('.deal-card[data-deal-id="' + dealId + '"]');
        if (card.length === 0) {
            return;
        }
        
        // Remove existing progress indicators
        card.find('.deal-progress-container').remove();
        card.find('.deal-priority-indicator').remove();
        
        // Create progress container
        var progressHtml = this.buildProgressHtml(progressData);
        card.find('.deal-card-body').after(progressHtml);
        
        // Add priority indicator
        var priorityHtml = this.buildPriorityIndicatorHtml(progressData.priority);
        card.prepend(priorityHtml);
        
        // Animate progress bar
        this.animateProgressBar(card, progressData.percentage);
        
        // Store progress data for tooltips
        card.data('progress-data', progressData);
    },
    
    /**
     * Build progress HTML structure
     */
    buildProgressHtml: function(progressData) {
        var progressClass = this.getProgressClass(progressData.percentage);
        var statusBadges = this.buildStatusBadges(progressData);
        
        return `
            <div class="deal-progress-container" tabindex="0" role="progressbar" 
                 aria-valuenow="${progressData.percentage}" aria-valuemin="0" aria-valuemax="100"
                 aria-label="Checklist completion progress">
                <div class="deal-progress-bar">
                    <div class="deal-progress-fill ${progressClass}" 
                         style="width: 0%"></div>
                </div>
                <div class="deal-progress-stats">
                    <span class="deal-progress-percentage">${progressData.percentage}%</span>
                    <span class="deal-progress-count">
                        <span class="glyphicon glyphicon-check"></span>
                        ${progressData.completedTasks}/${progressData.totalTasks}
                    </span>
                </div>
                ${statusBadges}
                <div class="deal-checklist-preview">
                    <span class="deal-checklist-icon glyphicon glyphicon-list"></span>
                    <span class="deal-checklist-summary">
                        ${this.getChecklistSummary(progressData)}
                    </span>
                </div>
            </div>
        `;
    },
    
    /**
     * Build status badges HTML
     */
    buildStatusBadges: function(progressData) {
        var badges = [];
        
        if (progressData.overdueTasks > 0) {
            badges.push(`<span class="deal-status-badge overdue" title="${progressData.overdueTasks} overdue tasks">
                Overdue (${progressData.overdueTasks})
            </span>`);
        }
        
        if (progressData.dueTodayTasks > 0) {
            badges.push(`<span class="deal-status-badge due-today" title="${progressData.dueTodayTasks} tasks due today">
                Due Today (${progressData.dueTodayTasks})
            </span>`);
        }
        
        if (progressData.percentage === 100) {
            badges.push('<span class="deal-status-badge completed">Complete</span>');
        } else if (progressData.status === 'on-track') {
            badges.push('<span class="deal-status-badge on-track">On Track</span>');
        } else if (progressData.status === 'blocked') {
            badges.push('<span class="deal-status-badge blocked">Blocked</span>');
        }
        
        if (badges.length === 0) {
            return '';
        }
        
        return '<div class="deal-status-badges">' + badges.join('') + '</div>';
    },
    
    /**
     * Build priority indicator HTML
     */
    buildPriorityIndicatorHtml: function(priority) {
        return `<div class="deal-priority-indicator ${priority}" title="Priority: ${priority}"></div>`;
    },
    
    /**
     * Get progress CSS class based on percentage
     */
    getProgressClass: function(percentage) {
        if (percentage >= 80) return 'high-progress';
        if (percentage >= 40) return 'medium-progress';
        return 'low-progress';
    },
    
    /**
     * Get checklist summary text
     */
    getChecklistSummary: function(progressData) {
        var pending = progressData.totalTasks - progressData.completedTasks;
        if (pending === 0) {
            return 'All tasks completed';
        } else if (pending === 1) {
            return '1 task remaining';
        } else {
            return `${pending} tasks remaining`;
        }
    },
    
    /**
     * Animate progress bar fill
     */
    animateProgressBar: function(card, percentage) {
        var progressBar = card.find('.deal-progress-fill');
        
        setTimeout(function() {
            progressBar.css('width', percentage + '%');
        }, 100);
    },
    
    /**
     * Show progress tooltip with detailed breakdown
     */
    showProgressTooltip: function(element, dealId) {
        var card = jQuery(element).closest('.deal-card');
        var progressData = card.data('progress-data');
        
        if (!progressData) {
            return;
        }
        
        var tooltipId = 'progress-tooltip-' + dealId;
        var tooltip = this.createProgressTooltip(tooltipId, progressData);
        
        // Position tooltip
        var rect = element.getBoundingClientRect();
        tooltip.css({
            top: rect.top - tooltip.outerHeight() - 10,
            left: rect.left + (rect.width / 2) - (tooltip.outerWidth() / 2)
        });
        
        // Show tooltip
        tooltip.addClass('show');
        jQuery(element).data('tooltip-id', tooltipId);
        this.activeTooltips[tooltipId] = tooltip;
    },
    
    /**
     * Create detailed progress tooltip
     */
    createProgressTooltip: function(tooltipId, progressData) {
        var tooltip = jQuery(`
            <div id="${tooltipId}" class="deal-progress-tooltip tooltip-checklist-breakdown">
                <div style="font-weight: bold; margin-bottom: 6px; border-bottom: 1px solid rgba(255,255,255,0.2); padding-bottom: 4px;">
                    Checklist Progress: ${progressData.percentage}%
                </div>
                <div class="tooltip-checklist-items">
                    ${this.buildTooltipChecklistItems(progressData.checklist)}
                </div>
                <div style="margin-top: 6px; font-size: 9px; opacity: 0.8;">
                    Last updated: ${this.formatDateTime(progressData.lastUpdated)}
                </div>
            </div>
        `);
        
        jQuery('body').append(tooltip);
        return tooltip;
    },
    
    /**
     * Build checklist items for tooltip
     */
    buildTooltipChecklistItems: function(checklist) {
        return checklist.map(function(item) {
            return `
                <div class="tooltip-checklist-item">
                    <span class="tooltip-item-name">${item.name}</span>
                    <span class="tooltip-item-status ${item.status}">${item.status}</span>
                </div>
            `;
        }).join('');
    },
    
    /**
     * Show simple tooltip
     */
    showSimpleTooltip: function(element, text) {
        var tooltipId = 'simple-tooltip-' + Date.now();
        var tooltip = jQuery(`
            <div id="${tooltipId}" class="deal-progress-tooltip">
                ${text}
            </div>
        `);
        
        jQuery('body').append(tooltip);
        
        // Position tooltip
        var rect = element.getBoundingClientRect();
        tooltip.css({
            top: rect.top - tooltip.outerHeight() - 10,
            left: rect.left + (rect.width / 2) - (tooltip.outerWidth() / 2)
        });
        
        // Show tooltip
        setTimeout(function() {
            tooltip.addClass('show');
        }, 50);
        
        jQuery(element).data('tooltip-id', tooltipId);
        this.activeTooltips[tooltipId] = tooltip;
    },
    
    /**
     * Hide tooltip
     */
    hideTooltip: function(tooltipId) {
        var tooltip = this.activeTooltips[tooltipId];
        if (tooltip) {
            tooltip.removeClass('show');
            setTimeout(function() {
                tooltip.remove();
            }, 200);
            delete this.activeTooltips[tooltipId];
        }
    },
    
    /**
     * Get status description for tooltip
     */
    getStatusDescription: function(status) {
        var descriptions = {
            'overdue': 'Tasks that have passed their due date',
            'due-today': 'Tasks that are due today',
            'on-track': 'All tasks are progressing on schedule',
            'completed': 'All checklist items have been completed',
            'blocked': 'Progress is blocked waiting for external dependencies'
        };
        
        return descriptions[status.toLowerCase()] || 'Status information';
    },
    
    /**
     * Update all progress indicators
     */
    updateAllProgressIndicators: function() {
        var self = this;
        
        jQuery('.deal-card').each(function() {
            var dealId = jQuery(this).data('deal-id');
            if (dealId) {
                // For now, generate new mock data
                // TODO: Replace with API call to get actual progress updates
                var mockProgress = self.generateMockProgressData(dealId);
                self.updateProgressIndicator(dealId, mockProgress);
            }
        });
    },
    
    /**
     * Update specific progress indicator
     */
    updateProgressIndicator: function(dealId, progressData) {
        var card = jQuery('.deal-card[data-deal-id="' + dealId + '"]');
        if (card.length === 0) {
            return;
        }
        
        // Update progress bar
        var progressBar = card.find('.deal-progress-fill');
        var progressClass = this.getProgressClass(progressData.percentage);
        progressBar.removeClass('low-progress medium-progress high-progress')
                  .addClass(progressClass)
                  .css('width', progressData.percentage + '%');
        
        // Update percentage text
        card.find('.deal-progress-percentage').text(progressData.percentage + '%');
        
        // Update task count
        card.find('.deal-progress-count').html(`
            <span class="glyphicon glyphicon-check"></span>
            ${progressData.completedTasks}/${progressData.totalTasks}
        `);
        
        // Update status badges
        var statusBadges = this.buildStatusBadges(progressData);
        card.find('.deal-status-badges').replaceWith(statusBadges);
        
        // Update checklist summary
        card.find('.deal-checklist-summary').text(this.getChecklistSummary(progressData));
        
        // Update priority indicator
        var priorityIndicator = card.find('.deal-priority-indicator');
        priorityIndicator.removeClass('low medium high critical')
                        .addClass(progressData.priority);
        
        // Update stored data
        card.data('progress-data', progressData);
    },
    
    /**
     * Update progress context when card moves between stages
     */
    updateProgressContext: function(dealId, newStage) {
        // This would typically trigger a backend update to recalculate
        // progress based on stage-specific checklists
        console.log('Updating progress context for deal', dealId, 'in stage', newStage);
        
        // For now, just refresh the progress data
        var mockProgress = this.generateMockProgressData(dealId);
        this.updateProgressIndicator(dealId, mockProgress);
    },
    
    /**
     * Update priority indicator when focus changes
     */
    updatePriorityIndicator: function(dealId, focused) {
        var card = jQuery('.deal-card[data-deal-id="' + dealId + '"]');
        var priorityIndicator = card.find('.deal-priority-indicator');
        
        if (focused) {
            priorityIndicator.addClass('critical');
        } else {
            priorityIndicator.removeClass('critical');
        }
    },
    
    /**
     * Handle compact view changes
     */
    handleCompactViewChange: function(compact) {
        if (compact) {
            // Hide detailed elements in compact view
            jQuery('.deal-status-badges, .deal-checklist-preview').fadeOut(150);
        } else {
            // Show detailed elements in normal view
            jQuery('.deal-status-badges, .deal-checklist-preview').fadeIn(150);
        }
    },
    
    /**
     * Format date/time for tooltips
     */
    formatDateTime: function(date) {
        return new Date(date).toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    },
    
    /**
     * Cleanup - remove event listeners and timers
     */
    destroy: function() {
        if (this.updateTimer) {
            clearInterval(this.updateTimer);
        }
        
        if (this.websocket) {
            this.websocket.close();
        }
        
        // Remove all tooltips
        Object.keys(this.activeTooltips).forEach(function(tooltipId) {
            this.hideTooltip(tooltipId);
        }, this);
        
        // Remove event listeners
        jQuery(document).off('.progress-indicators');
    }
};

// Initialize when document is ready
jQuery(document).ready(function() {
    if (typeof PipelineView !== 'undefined') {
        // Initialize after pipeline view is ready
        setTimeout(function() {
            ProgressIndicators.init();
        }, 500);
    }
});