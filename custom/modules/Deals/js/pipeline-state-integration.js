/**
 * Pipeline State Integration
 * 
 * Integrates the state management system with the existing pipeline functionality
 * Provides backwards compatibility while adding state management features
 */

(function() {
    'use strict';

    // Initialize state manager when document is ready
    let stateManager = null;
    let originalPipelineView = null;

    // State integration configuration
    const StateIntegration = {
        config: {
            enableUndo: true,
            enableSync: true,
            enableDebug: false,
            optimisticUpdates: true,
            conflictResolution: 'server_wins' // 'server_wins', 'client_wins', 'merge'
        },

        /**
         * Initialize state management integration
         */
        init: function(pipelineConfig) {
            console.log('Initializing Pipeline State Integration');

            // Initialize state manager
            stateManager = new PipelineStateManager({
                userId: pipelineConfig.currentUserId,
                enableDebug: this.config.enableDebug,
                websocketUrl: this.getWebSocketUrl(),
                autoSave: true,
                syncInterval: 30000
            });

            // Store reference to original PipelineView
            originalPipelineView = window.PipelineView;

            // Enhance PipelineView with state management
            this.enhancePipelineView();

            // Setup event listeners
            this.setupEventListeners();

            // Load initial state
            this.loadInitialState();

            // Setup keyboard shortcuts
            this.setupKeyboardShortcuts();

            console.log('Pipeline State Integration initialized successfully');
        },

        /**
         * Enhance existing PipelineView with state management
         */
        enhancePipelineView: function() {
            const self = this;

            // Enhance moveCard method
            const originalMoveCard = originalPipelineView.moveCard;
            originalPipelineView.moveCard = function(card, dropZone, targetStage) {
                const dealId = this.draggedData.dealId;
                const sourceStage = this.draggedData.sourceStage;

                // Dispatch state change
                stateManager.dispatch(stateManager.actions.moveDeal(
                    dealId, 
                    sourceStage, 
                    targetStage
                ));

                // If optimistic updates enabled, update UI immediately
                if (self.config.optimisticUpdates) {
                    // Call original method for immediate UI update
                    originalMoveCard.call(this, card, dropZone, targetStage);
                } else {
                    // Wait for server confirmation
                    self.moveCardWithServerConfirmation(card, dropZone, targetStage);
                }
            };

            // Enhance toggleFocus method
            const originalToggleFocus = originalPipelineView.toggleFocus;
            originalPipelineView.toggleFocus = function(dealId, focusState) {
                // Dispatch state change
                stateManager.dispatch(stateManager.actions.toggleDealFocus(
                    dealId, 
                    focusState
                ));

                // Call original method
                originalToggleFocus.call(this, dealId, focusState);
            };

            // Enhance updateStageCounts method
            const originalUpdateStageCounts = originalPipelineView.updateStageCounts;
            originalPipelineView.updateStageCounts = function() {
                // Call original method
                originalUpdateStageCounts.call(this);

                // Update state
                const stageData = self.getCurrentStageData();
                stateManager.dispatch({
                    type: 'STAGE_COUNTS_UPDATED',
                    payload: stageData
                });
            };

            // Add new methods to PipelineView
            originalPipelineView.undo = function() {
                return self.undo();
            };

            originalPipelineView.redo = function() {
                return self.redo();
            };

            originalPipelineView.getStateMetrics = function() {
                return stateManager.getMetrics();
            };

            originalPipelineView.enableDebugMode = function() {
                stateManager.enableDebugMode();
                self.config.enableDebug = true;
            };

            originalPipelineView.disableDebugMode = function() {
                stateManager.disableDebugMode();
                self.config.enableDebug = false;
            };

            originalPipelineView.exportState = function() {
                return self.exportState();
            };

            originalPipelineView.importState = function(stateData) {
                return self.importState(stateData);
            };
        },

        /**
         * Setup event listeners for state changes
         */
        setupEventListeners: function() {
            const self = this;

            // Listen to state changes
            stateManager.on('stateChange', function(event) {
                self.handleStateChange(event);
            });

            // Listen to errors
            stateManager.on('error', function(error) {
                self.handleStateError(error);
            });

            // Handle online/offline events
            window.addEventListener('online', function() {
                self.handleOnline();
            });

            window.addEventListener('offline', function() {
                self.handleOffline();
            });

            // Handle page visibility changes
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    stateManager.persistState();
                }
            });

            // Custom events for external integration
            document.addEventListener('pipeline:refresh', function() {
                self.refreshFromServer();
            });

            document.addEventListener('pipeline:reset', function() {
                self.resetState();
            });
        },

        /**
         * Load initial state from server and localStorage
         */
        loadInitialState: function() {
            const self = this;

            // Load deals data
            const dealsData = this.extractCurrentDealsData();
            const stagesData = this.extractCurrentStagesData();

            // Initialize state with current data
            stateManager.dispatch(stateManager.actions.loadDeals(dealsData, stagesData));

            // Load user preferences
            this.loadUserPreferences();

            console.log('Initial state loaded', { deals: Object.keys(dealsData).length });
        },

        /**
         * Extract current deals data from DOM
         */
        extractCurrentDealsData: function() {
            const deals = {};

            jQuery('.deal-card').each(function() {
                const card = jQuery(this);
                const dealId = card.data('deal-id');
                
                if (dealId) {
                    deals[dealId] = {
                        id: dealId,
                        name: card.find('.deal-name').text().trim(),
                        amount: card.find('.deal-amount').text().trim(),
                        stage: card.data('stage'),
                        focused: card.hasClass('focused-deal'),
                        focusOrder: parseInt(card.data('focus-order')) || 0,
                        assignedUser: card.data('assigned-user'),
                        lastModified: new Date().toISOString(),
                        position: card.index()
                    };
                }
            });

            return deals;
        },

        /**
         * Extract current stages data from DOM
         */
        extractCurrentStagesData: function() {
            const stages = {};

            jQuery('.pipeline-stage').each(function() {
                const stage = jQuery(this);
                const stageKey = stage.data('stage');
                
                if (stageKey) {
                    stages[stageKey] = {
                        id: stageKey,
                        name: stage.find('.stage-header h3').text().trim(),
                        count: parseInt(stage.find('.deal-count').text()) || 0,
                        wipLimit: parseInt(stage.find('.droppable').data('wip-limit')) || null,
                        order: stage.index()
                    };
                }
            });

            return stages;
        },

        /**
         * Handle state changes and update UI accordingly
         */
        handleStateChange: function(event) {
            const { action, oldState, newState } = event;

            switch (action.type) {
                case 'DEAL_MOVED':
                    this.updateDealInUI(action.payload);
                    break;

                case 'DEAL_FOCUS_TOGGLED':
                    this.updateDealFocusInUI(action.payload);
                    break;

                case 'FILTER_UPDATED':
                    this.updateFiltersInUI(newState.filters);
                    break;

                case 'UNDO':
                case 'REDO':
                    this.refreshUIFromState(newState);
                    break;
            }

            // Update state indicator
            this.updateStateIndicator(newState);

            // Emit custom event for external listeners
            document.dispatchEvent(new CustomEvent('pipeline:stateChanged', {
                detail: { action, oldState, newState }
            }));
        },

        /**
         * Update deal position in UI based on state
         */
        updateDealInUI: function(payload) {
            const { dealId, toStage, position } = payload;
            const card = jQuery(`.deal-card[data-deal-id="${dealId}"]`);
            const targetStage = jQuery(`.droppable[data-stage="${toStage}"]`);

            if (card.length && targetStage.length) {
                // Remove from current position
                card.detach();

                // Insert at new position
                if (position && position > 0) {
                    const targetCard = targetStage.find('.deal-card').eq(position - 1);
                    if (targetCard.length) {
                        targetCard.after(card);
                    } else {
                        targetStage.append(card);
                    }
                } else {
                    targetStage.prepend(card);
                }

                // Update card data
                card.attr('data-stage', toStage);

                // Update stage counts
                originalPipelineView.updateStageCounts();
            }
        },

        /**
         * Update deal focus in UI
         */
        updateDealFocusInUI: function(payload) {
            const { dealId, focused } = payload;
            const card = jQuery(`.deal-card[data-deal-id="${dealId}"]`);

            if (card.length) {
                if (focused) {
                    card.addClass('focused-deal');
                    card.attr('data-focused', 'true');
                } else {
                    card.removeClass('focused-deal');
                    card.attr('data-focused', 'false');
                }

                // Reorder cards in stage
                const stageBody = card.closest('.stage-body');
                originalPipelineView.reorderCardsInStage(stageBody);
            }
        },

        /**
         * Update filters in UI
         */
        updateFiltersInUI: function(filters) {
            // Update compact view
            if (filters.compactView !== originalPipelineView.config.compactView) {
                originalPipelineView.config.compactView = filters.compactView;
                originalPipelineView.setCompactView(filters.compactView);
            }

            // Update focus filter
            if (filters.focusOnly !== originalPipelineView.config.focusFilterActive) {
                originalPipelineView.config.focusFilterActive = filters.focusOnly;
                originalPipelineView.applyFocusFilter(filters.focusOnly);
            }
        },

        /**
         * Refresh UI from current state
         */
        refreshUIFromState: function(state) {
            // This would be used for undo/redo operations
            // For now, we'll refresh the entire board
            originalPipelineView.refreshBoard();
        },

        /**
         * Update state indicator in UI
         */
        updateStateIndicator: function(state) {
            let indicator = jQuery('#state-indicator');
            
            if (indicator.length === 0) {
                indicator = jQuery('<div id="state-indicator" class="state-indicator"></div>');
                jQuery('.pipeline-actions').prepend(indicator);
            }

            const canUndo = stateManager.canUndo();
            const canRedo = stateManager.canRedo();
            const isOnline = navigator.onLine;

            indicator.html(`
                <div class="state-controls">
                    <button class="btn btn-sm ${canUndo ? '' : 'disabled'}" id="undo-btn" title="Undo (Ctrl+Z)">
                        <i class="glyphicon glyphicon-arrow-left"></i>
                    </button>
                    <button class="btn btn-sm ${canRedo ? '' : 'disabled'}" id="redo-btn" title="Redo (Ctrl+Y)">
                        <i class="glyphicon glyphicon-arrow-right"></i>
                    </button>
                    <span class="state-info">
                        <i class="glyphicon glyphicon-${isOnline ? 'ok' : 'remove'} ${isOnline ? 'text-success' : 'text-danger'}"></i>
                        v${state.session.version}
                    </span>
                </div>
            `);

            // Bind events
            indicator.find('#undo-btn').off('click').on('click', () => this.undo());
            indicator.find('#redo-btn').off('click').on('click', () => this.redo());
        },

        /**
         * Setup keyboard shortcuts
         */
        setupKeyboardShortcuts: function() {
            const self = this;

            jQuery(document).on('keydown', function(e) {
                // Ctrl+Z for undo
                if (e.ctrlKey && e.key === 'z' && !e.shiftKey) {
                    e.preventDefault();
                    self.undo();
                }

                // Ctrl+Y or Ctrl+Shift+Z for redo
                if ((e.ctrlKey && e.key === 'y') || (e.ctrlKey && e.shiftKey && e.key === 'z')) {
                    e.preventDefault();
                    self.redo();
                }

                // Ctrl+S for manual save
                if (e.ctrlKey && e.key === 's') {
                    e.preventDefault();
                    stateManager.persistState();
                    self.showNotification('State saved manually', 'success');
                }
            });
        },

        /**
         * Undo last action
         */
        undo: function() {
            if (stateManager.undo()) {
                this.showNotification('Action undone', 'info');
                return true;
            } else {
                this.showNotification('Nothing to undo', 'warning');
                return false;
            }
        },

        /**
         * Redo last undone action
         */
        redo: function() {
            if (stateManager.redo()) {
                this.showNotification('Action redone', 'info');
                return true;
            } else {
                this.showNotification('Nothing to redo', 'warning');
                return false;
            }
        },

        /**
         * Handle online event
         */
        handleOnline: function() {
            this.showNotification('Connection restored - syncing changes', 'success');
            stateManager.syncState.isOnline = true;
        },

        /**
         * Handle offline event
         */
        handleOffline: function() {
            this.showNotification('Connection lost - working offline', 'warning');
            stateManager.syncState.isOnline = false;
        },

        /**
         * Handle state errors
         */
        handleStateError: function(error) {
            console.error('State Manager Error:', error);
            
            let message = 'A state management error occurred';
            
            switch (error.operation) {
                case 'dispatch':
                    message = 'Failed to update state';
                    break;
                case 'persistState':
                    message = 'Failed to save state';
                    break;
                case 'syncWithServer':
                    message = 'Failed to sync with server';
                    break;
            }

            this.showNotification(message, 'error');
        },

        /**
         * Move card with server confirmation (non-optimistic)
         */
        moveCardWithServerConfirmation: function(card, dropZone, targetStage) {
            const self = this;
            
            // Show loading state
            originalPipelineView.showLoading();

            // Send to server first
            jQuery.ajax({
                url: originalPipelineView.config.updateUrl,
                type: 'POST',
                data: {
                    deal_id: originalPipelineView.draggedData.dealId,
                    new_stage: targetStage,
                    old_stage: originalPipelineView.draggedData.sourceStage
                },
                success: function(response) {
                    originalPipelineView.hideLoading();
                    
                    if (response.success) {
                        // Update UI only after server confirmation
                        originalPipelineView.moveCard(card, dropZone, targetStage);
                        self.showNotification('Deal moved successfully', 'success');
                    } else {
                        self.showNotification(response.message || 'Failed to move deal', 'error');
                    }
                },
                error: function() {
                    originalPipelineView.hideLoading();
                    self.showNotification('Network error. Please try again.', 'error');
                }
            });
        },

        /**
         * Load user preferences from state
         */
        loadUserPreferences: function() {
            const filters = stateManager.getState('filters');
            
            if (filters) {
                // Apply compact view preference
                if (filters.compactView) {
                    originalPipelineView.setCompactView(true);
                }

                // Apply focus filter preference
                if (filters.focusOnly) {
                    originalPipelineView.applyFocusFilter(true);
                }
            }
        },

        /**
         * Get current stage data
         */
        getCurrentStageData: function() {
            const stageData = {};

            jQuery('.pipeline-stage').each(function() {
                const stage = jQuery(this);
                const stageKey = stage.data('stage');
                const count = parseInt(stage.find('.deal-count').text()) || 0;

                stageData[stageKey] = { count };
            });

            return stageData;
        },

        /**
         * Refresh data from server
         */
        refreshFromServer: function() {
            originalPipelineView.refreshBoard();
        },

        /**
         * Reset state to initial
         */
        resetState: function() {
            if (confirm('Are you sure you want to reset the state? This will clear all local changes.')) {
                stateManager.dispatch(stateManager.actions.resetState());
                this.showNotification('State reset successfully', 'info');
            }
        },

        /**
         * Export current state
         */
        exportState: function() {
            const state = stateManager.getState();
            const exportData = {
                state,
                timestamp: new Date().toISOString(),
                version: state.session.version
            };

            const blob = new Blob([JSON.stringify(exportData, null, 2)], {
                type: 'application/json'
            });

            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `pipeline-state-${Date.now()}.json`;
            a.click();
            URL.revokeObjectURL(url);

            this.showNotification('State exported successfully', 'success');
        },

        /**
         * Import state from file
         */
        importState: function(stateData) {
            try {
                if (typeof stateData === 'string') {
                    stateData = JSON.parse(stateData);
                }

                if (stateData.state) {
                    // Validate imported state
                    if (stateManager.validateState(stateData.state)) {
                        stateManager.state = stateData.state;
                        this.refreshUIFromState(stateData.state);
                        this.showNotification('State imported successfully', 'success');
                        return true;
                    } else {
                        throw new Error('Invalid state data');
                    }
                } else {
                    throw new Error('No state data found');
                }
            } catch (error) {
                this.showNotification('Failed to import state: ' + error.message, 'error');
                return false;
            }
        },

        /**
         * Get WebSocket URL
         */
        getWebSocketUrl: function() {
            // WebSocket disabled - no WebSocket server available in this SuiteCRM installation
            // Return null to prevent connection attempts
            return null;
        },

        /**
         * Show notification
         */
        showNotification: function(message, type = 'info') {
            if (originalPipelineView && originalPipelineView.showNotification) {
                originalPipelineView.showNotification(message, type);
            } else {
                // Fallback notification
                console.log(`[${type.toUpperCase()}] ${message}`);
            }
        },

        /**
         * Get state manager instance (for debugging)
         */
        getStateManager: function() {
            return stateManager;
        }
    };

    // Make StateIntegration globally available
    window.StateIntegration = StateIntegration;

    // Auto-initialize when PipelineView is ready
    jQuery(document).ready(function() {
        if (window.PipelineView && window.PipelineView.config) {
            // Add a small delay to ensure PipelineView is fully initialized
            setTimeout(function() {
                StateIntegration.init(window.PipelineView.config);
            }, 500);
        }
    });

})();