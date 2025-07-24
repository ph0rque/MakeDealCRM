/**
 * WIP Limit Manager
 * Enforces Work-In-Progress limits for the Deal Pipeline
 * Provides validation, visual indicators, and enforcement logic
 */

var WIPLimitManager = {
    // Configuration
    config: {
        enableStrictMode: true, // Prevent drops if limit exceeded
        showWarnings: true,     // Show warnings near limit
        warningThreshold: 0.8,  // Show warning at 80% of limit
        autoAdjustPositions: true, // Auto-adjust positions after drops
        enableOverrides: true,  // Allow admin overrides
        animationDuration: 300  // UI animation duration
    },
    
    // Default WIP limits per stage
    defaultLimits: {
        'sourcing': 20,
        'screening': 15,
        'analysis_outreach': 10,
        'due_diligence': 8,
        'valuation_structuring': 6,
        'loi_negotiation': 5,
        'financing': 5,
        'closing': 5,
        'closed_owned_90_day': 10,
        'closed_owned_stable': null,
        'unavailable': null
    },
    
    // Current limits (can be modified by admin)
    currentLimits: {},
    
    // Stage count cache
    stageCounts: {},
    
    // Overflow queue for stages over limit
    overflowQueue: {},
    
    /**
     * Initialize WIP Limit Manager
     */
    init: function(config) {
        this.config = jQuery.extend(this.config, config);
        this.currentLimits = jQuery.extend({}, this.defaultLimits);
        
        // Load custom limits from server or local storage
        this.loadCustomLimits();
        
        // Initialize stage counts
        this.updateStageCounts();
        
        // Create visual indicators
        this.createCapacityIndicators();
        
        // Set up event handlers
        this.initEventHandlers();
        
        console.log('WIP Limit Manager initialized', this.currentLimits);
    },
    
    /**
     * Load custom WIP limits from server or storage
     */
    loadCustomLimits: function() {
        var stored = localStorage.getItem('pipeline_wip_limits');
        if (stored) {
            try {
                var customLimits = JSON.parse(stored);
                this.currentLimits = jQuery.extend(this.currentLimits, customLimits);
            } catch (e) {
                console.warn('Failed to load custom WIP limits:', e);
            }
        }
        
        // TODO: Load from server-side configuration
        this.loadServerLimits();
    },
    
    /**
     * Load limits from server configuration
     */
    loadServerLimits: function() {
        jQuery.ajax({
            url: 'index.php?module=Deals&action=getWIPLimits',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.limits) {
                    WIPLimitManager.currentLimits = jQuery.extend(
                        WIPLimitManager.currentLimits, 
                        response.limits
                    );
                    WIPLimitManager.updateCapacityIndicators();
                }
            },
            error: function() {
                console.log('Using default WIP limits - server limits not available');
            }
        });
    },
    
    /**
     * Save custom limits to server and storage
     */
    saveCustomLimits: function() {
        // Save to localStorage
        localStorage.setItem('pipeline_wip_limits', JSON.stringify(this.currentLimits));
        
        // Save to server
        jQuery.ajax({
            url: 'index.php?module=Deals&action=saveWIPLimits',
            type: 'POST',
            data: {
                limits: JSON.stringify(this.currentLimits)
            },
            success: function(response) {
                if (response.success) {
                    WIPLimitManager.showNotification('WIP limits saved successfully', 'success');
                }
            },
            error: function() {
                WIPLimitManager.showNotification('Failed to save WIP limits to server', 'warning');
            }
        });
    },
    
    /**
     * Set up event handlers
     */
    initEventHandlers: function() {
        var self = this;
        
        // Listen for drag operations
        jQuery(document).on('dragover', '.droppable', function(e) {
            var stage = jQuery(this).data('stage');
            self.handleDragOver(stage, this);
        });
        
        // Listen for successful drops
        jQuery(document).on('dealMoved', function(e, data) {
            self.handleDealMoved(data);
        });
        
        // Admin interface triggers
        jQuery(document).on('click', '.wip-limit-edit', function(e) {
            e.preventDefault();
            var stage = jQuery(this).data('stage');
            self.showLimitEditor(stage);
        });
        
        // Capacity indicator clicks
        jQuery(document).on('click', '.capacity-indicator', function(e) {
            var stage = jQuery(this).closest('.pipeline-stage').data('stage');
            self.showStageDetails(stage);
        });
    },
    
    /**
     * Update stage counts cache
     */
    updateStageCounts: function() {
        var self = this;
        
        jQuery('.pipeline-stage').each(function() {
            var stage = jQuery(this).data('stage');
            var count = jQuery(this).find('.deal-card:visible').length;
            self.stageCounts[stage] = count;
        });
    },
    
    /**
     * Create visual capacity indicators
     */
    createCapacityIndicators: function() {
        var self = this;
        
        jQuery('.pipeline-stage').each(function() {
            var $stage = jQuery(this);
            var stage = $stage.data('stage');
            var $header = $stage.find('.stage-header');
            
            // Remove existing indicator
            $header.find('.capacity-indicator').remove();
            
            // Create new indicator if limit exists
            var limit = self.currentLimits[stage];
            if (limit) {
                var count = self.stageCounts[stage] || 0;
                var indicator = self.createCapacityIndicatorElement(stage, count, limit);
                $header.append(indicator);
            }
        });
    },
    
    /**
     * Create individual capacity indicator element
     */
    createCapacityIndicatorElement: function(stage, count, limit) {
        var percentage = Math.min((count / limit) * 100, 100);
        var status = this.getCapacityStatus(count, limit);
        
        var $indicator = jQuery('<div class="capacity-indicator" data-stage="' + stage + '">')
            .html(`
                <div class="capacity-bar">
                    <div class="capacity-fill capacity-${status}" style="width: ${percentage}%"></div>
                </div>
                <div class="capacity-text">
                    <span class="capacity-current">${count}</span>
                    <span class="capacity-divider">/</span>
                    <span class="capacity-limit">${limit}</span>
                </div>
                <div class="capacity-status">
                    ${this.getCapacityStatusIcon(status)}
                </div>
            `);
        
        return $indicator;
    },
    
    /**
     * Get capacity status for a stage
     */
    getCapacityStatus: function(count, limit) {
        if (count >= limit) return 'over';
        if (count >= limit * this.config.warningThreshold) return 'warning';
        return 'normal';
    },
    
    /**
     * Get status icon for capacity
     */
    getCapacityStatusIcon: function(status) {
        var icons = {
            'normal': '<i class="glyphicon glyphicon-ok text-success"></i>',
            'warning': '<i class="glyphicon glyphicon-warning-sign text-warning"></i>',
            'over': '<i class="glyphicon glyphicon-ban-circle text-danger"></i>'
        };
        return icons[status] || icons.normal;
    },
    
    /**
     * Update capacity indicators
     */
    updateCapacityIndicators: function() {
        this.updateStageCounts();
        this.createCapacityIndicators();
    },
    
    /**
     * Validate if a deal can be dropped in a stage
     */
    validateDrop: function(targetStage, sourceStage, dealId) {
        var limit = this.currentLimits[targetStage];
        
        // No limit set - allow drop
        if (!limit) {
            return { allowed: true, reason: null };
        }
        
        var currentCount = this.stageCounts[targetStage] || 0;
        
        // If moving within same stage, don't count as increase
        if (targetStage === sourceStage) {
            return { allowed: true, reason: null };
        }
        
        // Check if adding would exceed limit
        if (currentCount >= limit) {
            return {
                allowed: false,
                reason: 'WIP_LIMIT_EXCEEDED',
                message: `Stage "${this.getStageDisplayName(targetStage)}" is at capacity (${currentCount}/${limit})`,
                canOverride: this.config.enableOverrides
            };
        }
        
        // Check warning threshold
        if (currentCount >= limit * this.config.warningThreshold) {
            return {
                allowed: true,
                warning: true,
                reason: 'NEAR_LIMIT',
                message: `Stage "${this.getStageDisplayName(targetStage)}" is nearing capacity (${currentCount + 1}/${limit})`
            };
        }
        
        return { allowed: true, reason: null };
    },
    
    /**
     * Handle drag over event
     */
    handleDragOver: function(stage, element) {
        var $element = jQuery(element);
        var sourceStage = PipelineView.draggedData?.sourceStage;
        var dealId = PipelineView.draggedData?.dealId;
        
        if (!stage || !sourceStage) return;
        
        var validation = this.validateDrop(stage, sourceStage, dealId);
        
        // Remove previous states
        $element.removeClass('wip-normal wip-warning wip-over-limit');
        
        if (!validation.allowed) {
            $element.addClass('wip-over-limit');
            this.showDropFeedback(element, validation.message, 'error');
        } else if (validation.warning) {
            $element.addClass('wip-warning');
            this.showDropFeedback(element, validation.message, 'warning');
        } else {
            $element.addClass('wip-normal');
        }
    },
    
    /**
     * Handle successful deal move
     */
    handleDealMoved: function(data) {
        // Update counts
        this.updateCapacityIndicators();
        
        // Log WIP limit event
        this.logWIPEvent('deal_moved', {
            dealId: data.dealId,
            fromStage: data.oldStage,
            toStage: data.newStage,
            newCount: this.stageCounts[data.newStage]
        });
        
        // Check for any limit violations
        this.checkLimitViolations();
    },
    
    /**
     * Show drop feedback tooltip
     */
    showDropFeedback: function(element, message, type) {
        var $element = jQuery(element);
        
        // Remove existing feedback
        $element.find('.wip-feedback').remove();
        
        if (message) {
            var $feedback = jQuery(`
                <div class="wip-feedback wip-feedback-${type}">
                    <div class="wip-feedback-arrow"></div>
                    <div class="wip-feedback-content">${message}</div>
                </div>
            `);
            
            $element.append($feedback);
            
            // Auto-remove after delay
            setTimeout(function() {
                $feedback.fadeOut(200, function() {
                    $feedback.remove();
                });
            }, 3000);
        }
    },
    
    /**
     * Check for limit violations across all stages
     */
    checkLimitViolations: function() {
        var violations = [];
        var self = this;
        
        Object.keys(this.currentLimits).forEach(function(stage) {
            var limit = self.currentLimits[stage];
            var count = self.stageCounts[stage] || 0;
            
            if (limit && count > limit) {
                violations.push({
                    stage: stage,
                    count: count,
                    limit: limit,
                    excess: count - limit
                });
            }
        });
        
        if (violations.length > 0) {
            this.handleLimitViolations(violations);
        }
    },
    
    /**
     * Handle limit violations
     */
    handleLimitViolations: function(violations) {
        var self = this;
        
        violations.forEach(function(violation) {
            self.logWIPEvent('limit_violation', violation);
            
            // Show notification
            self.showNotification(
                `WIP limit exceeded in ${self.getStageDisplayName(violation.stage)}: ${violation.count}/${violation.limit}`,
                'warning'
            );
            
            // Suggest actions
            self.suggestViolationResolution(violation);
        });
    },
    
    /**
     * Suggest actions for resolving violations
     */
    suggestViolationResolution: function(violation) {
        var actions = [
            `Move ${violation.excess} deal(s) to another stage`,
            `Increase the WIP limit for ${this.getStageDisplayName(violation.stage)}`,
            'Review the deals in this stage for potential issues'
        ];
        
        // Could show a modal with suggestions
        console.log('WIP Violation Resolution Suggestions:', actions);
    },
    
    /**
     * Show limit configuration editor
     */
    showLimitEditor: function(stage) {
        var self = this;
        var currentLimit = this.currentLimits[stage];
        var stageDisplayName = this.getStageDisplayName(stage);
        
        var modal = `
            <div class="modal fade" id="wipLimitEditor" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h4 class="modal-title">Edit WIP Limit - ${stageDisplayName}</h4>
                        </div>
                        <div class="modal-body">
                            <form id="wipLimitForm">
                                <div class="form-group">
                                    <label for="wipLimitValue">WIP Limit:</label>
                                    <input type="number" class="form-control" id="wipLimitValue" 
                                           value="${currentLimit || ''}" min="0" max="100">
                                    <small class="help-block">
                                        Set to 0 or leave empty for no limit. Current deals in stage: ${this.stageCounts[stage] || 0}
                                    </small>
                                </div>
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" id="wipStrictMode" ${this.config.enableStrictMode ? 'checked' : ''}>
                                        Strict enforcement (prevent drops when limit exceeded)
                                    </label>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="saveWipLimit">Save</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal
        jQuery('#wipLimitEditor').remove();
        
        // Add and show modal
        jQuery('body').append(modal);
        jQuery('#wipLimitEditor').modal('show');
        
        // Handle save
        jQuery('#saveWipLimit').click(function() {
            var newLimit = parseInt(jQuery('#wipLimitValue').val()) || null;
            var strictMode = jQuery('#wipStrictMode').is(':checked');
            
            self.currentLimits[stage] = newLimit;
            self.config.enableStrictMode = strictMode;
            
            // Save and update
            self.saveCustomLimits();
            self.updateCapacityIndicators();
            
            jQuery('#wipLimitEditor').modal('hide');
            self.showNotification('WIP limit updated successfully', 'success');
        });
    },
    
    /**
     * Show stage capacity details
     */
    showStageDetails: function(stage) {
        var limit = this.currentLimits[stage];
        var count = this.stageCounts[stage] || 0;
        var status = this.getCapacityStatus(count, limit);
        var stageDisplayName = this.getStageDisplayName(stage);
        
        var details = `
            <div class="stage-details-popup">
                <h5>${stageDisplayName} Capacity</h5>
                <div class="capacity-details">
                    <div class="capacity-metric">
                        <span class="metric-label">Current Deals:</span>
                        <span class="metric-value">${count}</span>
                    </div>
                    <div class="capacity-metric">
                        <span class="metric-label">WIP Limit:</span>
                        <span class="metric-value">${limit || 'No limit'}</span>
                    </div>
                    <div class="capacity-metric">
                        <span class="metric-label">Status:</span>
                        <span class="metric-value capacity-${status}">${status.toUpperCase()}</span>
                    </div>
                    ${limit ? `
                    <div class="capacity-metric">
                        <span class="metric-label">Utilization:</span>
                        <span class="metric-value">${Math.round((count/limit)*100)}%</span>
                    </div>
                    ` : ''}
                </div>
                <div class="stage-actions">
                    <button class="btn btn-sm btn-default wip-limit-edit" data-stage="${stage}">
                        Edit Limit
                    </button>
                </div>
            </div>
        `;
        
        // Show as tooltip or modal based on screen size
        if (window.innerWidth < 768) {
            this.showNotification(details, 'info');
        } else {
            // Show as positioned tooltip
            this.showTooltip(jQuery(`.capacity-indicator[data-stage="${stage}"]`), details);
        }
    },
    
    /**
     * Show positioned tooltip
     */
    showTooltip: function($element, content) {
        // Remove existing tooltips
        jQuery('.wip-tooltip').remove();
        
        var $tooltip = jQuery(`<div class="wip-tooltip">${content}</div>`);
        jQuery('body').append($tooltip);
        
        // Position tooltip
        var offset = $element.offset();
        $tooltip.css({
            top: offset.top - $tooltip.outerHeight() - 10,
            left: offset.left + ($element.outerWidth() / 2) - ($tooltip.outerWidth() / 2)
        });
        
        // Auto-remove after delay
        setTimeout(function() {
            $tooltip.fadeOut(200, function() {
                $tooltip.remove();
            });
        }, 5000);
        
        // Remove on click outside
        jQuery(document).one('click', function() {
            $tooltip.remove();
        });
    },
    
    /**
     * Get display name for stage
     */
    getStageDisplayName: function(stage) {
        var stageNames = {
            'sourcing': 'Sourcing',
            'screening': 'Screening', 
            'analysis_outreach': 'Analysis & Outreach',
            'due_diligence': 'Due Diligence',
            'valuation_structuring': 'Valuation & Structuring',
            'loi_negotiation': 'LOI / Negotiation',
            'financing': 'Financing',
            'closing': 'Closing',
            'closed_owned_90_day': 'Closed/Owned – 90-Day Plan',
            'closed_owned_stable': 'Closed/Owned – Stable Operations',
            'unavailable': 'Unavailable'
        };
        return stageNames[stage] || stage;
    },
    
    /**
     * Log WIP-related events
     */
    logWIPEvent: function(eventType, data) {
        var logEntry = {
            timestamp: Date.now(),
            event: eventType,
            data: data,
            userId: PipelineView.config.currentUserId
        };
        
        // Store in localStorage for analytics
        var logs = JSON.parse(localStorage.getItem('pipeline_wip_logs') || '[]');
        logs.push(logEntry);
        
        // Keep only last 100 entries
        if (logs.length > 100) {
            logs = logs.slice(-100);
        }
        
        localStorage.setItem('pipeline_wip_logs', JSON.stringify(logs));
        
        // Send to server for reporting
        jQuery.ajax({
            url: 'index.php?module=Deals&action=logWIPEvent',
            type: 'POST',
            data: {
                event_type: eventType,
                event_data: JSON.stringify(data)
            }
        });
    },
    
    /**
     * Generate WIP limit violation report
     */
    generateViolationReport: function(days = 7) {
        var logs = JSON.parse(localStorage.getItem('pipeline_wip_logs') || '[]');
        var cutoff = Date.now() - (days * 24 * 60 * 60 * 1000);
        
        var violations = logs.filter(function(log) {
            return log.timestamp > cutoff && log.event === 'limit_violation';
        });
        
        var report = {
            period: days + ' days',
            totalViolations: violations.length,
            violationsByStage: {},
            frequentViolators: {}
        };
        
        violations.forEach(function(violation) {
            var stage = violation.data.stage;
            if (!report.violationsByStage[stage]) {
                report.violationsByStage[stage] = 0;
            }
            report.violationsByStage[stage]++;
        });
        
        return report;
    },
    
    /**
     * Show notification using existing pipeline notification system
     */
    showNotification: function(message, type) {
        if (typeof PipelineView !== 'undefined' && PipelineView.showNotification) {
            PipelineView.showNotification(message, type);
        } else {
            console.log('WIP Notification:', type, message);
        }
    },
    
    /**
     * Export WIP configuration and logs
     */
    exportWIPData: function() {
        var data = {
            limits: this.currentLimits,
            config: this.config,
            logs: JSON.parse(localStorage.getItem('pipeline_wip_logs') || '[]'),
            report: this.generateViolationReport(30),
            exportDate: new Date().toISOString()
        };
        
        return data;
    },
    
    /**
     * Reset WIP limits to defaults
     */
    resetToDefaults: function() {
        if (confirm('Are you sure you want to reset all WIP limits to defaults? This cannot be undone.')) {
            this.currentLimits = jQuery.extend({}, this.defaultLimits);
            this.saveCustomLimits();
            this.updateCapacityIndicators();
            this.showNotification('WIP limits reset to defaults', 'success');
        }
    }
};

// Auto-initialize when PipelineView is ready
jQuery(document).ready(function() {
    if (typeof PipelineView !== 'undefined') {
        // Initialize after a short delay to ensure PipelineView is fully loaded
        setTimeout(function() {
            WIPLimitManager.init();
        }, 100);
    }
});