/**
 * State Debugger and Monitoring Dashboard
 * 
 * Provides debugging tools and real-time monitoring for the state management system
 */

class PipelineStateDebugger {
    constructor(stateManager) {
        this.stateManager = stateManager;
        this.isVisible = false;
        this.updateInterval = null;
        this.logBuffer = [];
        this.maxLogSize = 1000;
        
        this.init();
    }

    init() {
        this.createDebugPanel();
        this.setupEventListeners();
        this.startMonitoring();
    }

    /**
     * Create the debug panel UI
     */
    createDebugPanel() {
        // Remove existing panel if it exists
        jQuery('#state-debug-panel').remove();

        const panel = jQuery(`
            <div id="state-debug-panel" class="state-debug-panel" style="display: none;">
                <div class="debug-header">
                    <h4>Pipeline State Debugger</h4>
                    <div class="debug-controls">
                        <button class="btn btn-xs btn-success" id="debug-export">Export</button>
                        <button class="btn btn-xs btn-warning" id="debug-clear">Clear</button>
                        <button class="btn btn-xs btn-danger" id="debug-close">×</button>
                    </div>
                </div>
                
                <div class="debug-tabs">
                    <ul class="nav nav-tabs">
                        <li class="active"><a href="#debug-overview" data-toggle="tab">Overview</a></li>
                        <li><a href="#debug-state" data-toggle="tab">State</a></li>
                        <li><a href="#debug-history" data-toggle="tab">History</a></li>
                        <li><a href="#debug-metrics" data-toggle="tab">Metrics</a></li>
                        <li><a href="#debug-logs" data-toggle="tab">Logs</a></li>
                        <li><a href="#debug-sync" data-toggle="tab">Sync</a></li>
                    </ul>
                    
                    <div class="tab-content">
                        <div class="tab-pane active" id="debug-overview">
                            <div class="debug-overview"></div>
                        </div>
                        
                        <div class="tab-pane" id="debug-state">
                            <div class="debug-state"></div>
                        </div>
                        
                        <div class="tab-pane" id="debug-history">
                            <div class="debug-history"></div>
                        </div>
                        
                        <div class="tab-pane" id="debug-metrics">
                            <div class="debug-metrics"></div>
                        </div>
                        
                        <div class="tab-pane" id="debug-logs">
                            <div class="debug-logs"></div>
                        </div>
                        
                        <div class="tab-pane" id="debug-sync">
                            <div class="debug-sync"></div>
                        </div>
                    </div>
                </div>
            </div>
        `);

        // Append to body
        jQuery('body').append(panel);

        // Add CSS styles
        this.addDebugStyles();

        // Bind events
        this.bindPanelEvents();
    }

    /**
     * Add CSS styles for the debug panel
     */
    addDebugStyles() {
        if (jQuery('#state-debug-styles').length === 0) {
            const styles = `
                <style id="state-debug-styles">
                .state-debug-panel {
                    position: fixed;
                    top: 10px;
                    right: 10px;
                    width: 600px;
                    height: 500px;
                    background: #fff;
                    border: 1px solid #ddd;
                    border-radius: 5px;
                    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
                    z-index: 9999;
                    font-family: monospace;
                    font-size: 12px;
                }

                .debug-header {
                    padding: 10px;
                    background: #f5f5f5;
                    border-bottom: 1px solid #ddd;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    border-radius: 5px 5px 0 0;
                }

                .debug-header h4 {
                    margin: 0;
                    font-size: 14px;
                }

                .debug-controls {
                    display: flex;
                    gap: 5px;
                }

                .debug-tabs {
                    height: calc(100% - 50px);
                    display: flex;
                    flex-direction: column;
                }

                .debug-tabs .nav-tabs {
                    flex-shrink: 0;
                    margin: 0;
                    padding: 0 10px;
                    background: #f9f9f9;
                }

                .debug-tabs .nav-tabs li a {
                    padding: 5px 10px;
                    font-size: 11px;
                }

                .debug-tabs .tab-content {
                    flex: 1;
                    overflow: hidden;
                    padding: 10px;
                }

                .debug-tabs .tab-pane {
                    height: 100%;
                    overflow-y: auto;
                }

                .debug-section {
                    margin-bottom: 15px;
                    padding: 10px;
                    background: #f9f9f9;
                    border-radius: 3px;
                }

                .debug-section h5 {
                    margin: 0 0 10px 0;
                    font-size: 12px;
                    color: #333;
                }

                .debug-item {
                    margin-bottom: 5px;
                    padding: 2px 0;
                    border-bottom: 1px solid #eee;
                }

                .debug-item:last-child {
                    border-bottom: none;
                }

                .debug-label {
                    font-weight: bold;
                    color: #666;
                    min-width: 120px;
                    display: inline-block;
                }

                .debug-value {
                    color: #333;
                }

                .debug-json {
                    background: #f0f0f0;
                    border: 1px solid #ddd;
                    border-radius: 3px;
                    padding: 5px;
                    font-family: 'Courier New', monospace;
                    font-size: 10px;
                    white-space: pre-wrap;
                    max-height: 200px;
                    overflow-y: auto;
                }

                .debug-log-entry {
                    padding: 2px 5px;
                    margin-bottom: 2px;
                    border-radius: 2px;
                    font-size: 10px;
                }

                .debug-log-entry.error {
                    background: #ffebee;
                    border-left: 3px solid #f44336;
                }

                .debug-log-entry.warning {
                    background: #fff3e0;
                    border-left: 3px solid #ff9800;
                }

                .debug-log-entry.info {
                    background: #e3f2fd;
                    border-left: 3px solid #2196f3;
                }

                .debug-log-entry.success {
                    background: #e8f5e8;
                    border-left: 3px solid #4caf50;
                }

                .debug-metric {
                    display: flex;
                    justify-content: space-between;
                    padding: 2px 0;
                }

                .debug-metric-name {
                    font-weight: bold;
                }

                .debug-metric-value {
                    color: #666;
                }

                .debug-button {
                    margin: 2px;
                    padding: 2px 8px;
                    font-size: 10px;
                }

                .state-debug-toggle {
                    position: fixed;
                    top: 10px;
                    right: 10px;
                    z-index: 10000;
                    background: #333;
                    color: #fff;
                    border: none;
                    border-radius: 15px;
                    width: 30px;
                    height: 30px;
                    font-size: 12px;
                    cursor: pointer;
                }

                .state-debug-toggle:hover {
                    background: #555;
                }
                </style>
            `;
            jQuery('head').append(styles);
        }
    }

    /**
     * Bind events for the debug panel
     */
    bindPanelEvents() {
        const self = this;

        // Close button
        jQuery('#debug-close').on('click', function() {
            self.hide();
        });

        // Export button
        jQuery('#debug-export').on('click', function() {
            self.exportDebugData();
        });

        // Clear button
        jQuery('#debug-clear').on('click', function() {
            self.clearLogs();
        });

        // Tab switching
        jQuery('.debug-tabs a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
            const target = jQuery(e.target).attr('href');
            self.updateTabContent(target);
        });

        // Make panel draggable
        this.makePanelDraggable();
    }

    /**
     * Make the debug panel draggable
     */
    makePanelDraggable() {
        const panel = jQuery('#state-debug-panel');
        const header = panel.find('.debug-header');
        
        header.css('cursor', 'move');
        
        let isDragging = false;
        let startX, startY, startLeft, startTop;

        header.on('mousedown', function(e) {
            isDragging = true;
            startX = e.clientX;
            startY = e.clientY;
            startLeft = panel.offset().left;
            startTop = panel.offset().top;
            
            jQuery(document).on('mousemove.debug-panel', function(e) {
                if (isDragging) {
                    const newLeft = startLeft + (e.clientX - startX);
                    const newTop = startTop + (e.clientY - startY);
                    
                    panel.css({
                        left: Math.max(0, Math.min(newLeft, window.innerWidth - panel.width())),
                        top: Math.max(0, Math.min(newTop, window.innerHeight - panel.height()))
                    });
                }
            });
        });

        jQuery(document).on('mouseup.debug-panel', function() {
            isDragging = false;
            jQuery(document).off('mousemove.debug-panel');
        });
    }

    /**
     * Setup event listeners for state changes
     */
    setupEventListeners() {
        const self = this;

        // Listen to state manager events
        this.stateManager.on('stateChange', function(event) {
            self.logEvent('State Change', event, 'info');
            if (self.isVisible) {
                self.updateOverview();
                self.updateTabContent('#debug-state');
                self.updateTabContent('#debug-history');
            }
        });

        this.stateManager.on('error', function(error) {
            self.logEvent('Error', error, 'error');
        });

        // Keyboard shortcut to toggle (Ctrl+Shift+D)
        jQuery(document).on('keydown', function(e) {
            if (e.ctrlKey && e.shiftKey && e.key === 'D') {
                e.preventDefault();
                self.toggle();
            }
        });
    }

    /**
     * Start monitoring
     */
    startMonitoring() {
        const self = this;
        
        this.updateInterval = setInterval(function() {
            if (self.isVisible) {
                self.updateMetrics();
                self.updateSyncStatus();
            }
        }, 1000);
    }

    /**
     * Show the debug panel
     */
    show() {
        this.isVisible = true;
        jQuery('#state-debug-panel').show();
        this.updateAllTabs();
        this.logEvent('Debug Panel', 'Opened', 'info');
    }

    /**
     * Hide the debug panel
     */
    hide() {
        this.isVisible = false;
        jQuery('#state-debug-panel').hide();
        this.logEvent('Debug Panel', 'Closed', 'info');
    }

    /**
     * Toggle debug panel visibility
     */
    toggle() {
        if (this.isVisible) {
            this.hide();
        } else {
            this.show();
        }
    }

    /**
     * Update all tabs content
     */
    updateAllTabs() {
        this.updateOverview();
        this.updateTabContent('#debug-state');
        this.updateTabContent('#debug-history');
        this.updateTabContent('#debug-metrics');
        this.updateTabContent('#debug-logs');
        this.updateTabContent('#debug-sync');
    }

    /**
     * Update overview tab
     */
    updateOverview() {
        const state = this.stateManager.getState();
        const metrics = this.stateManager.getMetrics();
        
        const overview = jQuery('.debug-overview');
        overview.html(`
            <div class="debug-section">
                <h5>System Status</h5>
                <div class="debug-item">
                    <span class="debug-label">State Version:</span>
                    <span class="debug-value">${state.session.version}</span>
                </div>
                <div class="debug-item">
                    <span class="debug-label">Session ID:</span>
                    <span class="debug-value">${state.session.sessionId}</span>
                </div>
                <div class="debug-item">
                    <span class="debug-label">Online Status:</span>
                    <span class="debug-value ${navigator.onLine ? 'text-success' : 'text-danger'}">
                        ${navigator.onLine ? 'Online' : 'Offline'}
                    </span>
                </div>
                <div class="debug-item">
                    <span class="debug-label">Last Sync:</span>
                    <span class="debug-value">${state.session.lastSync ? new Date(state.session.lastSync).toLocaleTimeString() : 'Never'}</span>
                </div>
            </div>

            <div class="debug-section">
                <h5>State Summary</h5>
                <div class="debug-item">
                    <span class="debug-label">Total Deals:</span>
                    <span class="debug-value">${Object.keys(state.deals).length}</span>
                </div>
                <div class="debug-item">
                    <span class="debug-label">Focused Deals:</span>
                    <span class="debug-value">${Object.values(state.deals).filter(d => d.focused).length}</span>
                </div>
                <div class="debug-item">
                    <span class="debug-label">Selected Deals:</span>
                    <span class="debug-value">${state.ui.selectedDeals.size}</span>
                </div>
                <div class="debug-item">
                    <span class="debug-label">History Size:</span>
                    <span class="debug-value">${metrics.historySize}</span>
                </div>
            </div>

            <div class="debug-section">
                <h5>Quick Actions</h5>
                <button class="btn btn-xs btn-info debug-button" onclick="StateDebugger.exportState()">Export State</button>
                <button class="btn btn-xs btn-warning debug-button" onclick="StateDebugger.validateState()">Validate State</button>
                <button class="btn btn-xs btn-success debug-button" onclick="StateDebugger.forcSync()">Force Sync</button>
                <button class="btn btn-xs btn-danger debug-button" onclick="StateDebugger.resetState()">Reset State</button>
            </div>
        `);
    }

    /**
     * Update specific tab content
     */
    updateTabContent(tabId) {
        switch (tabId) {
            case '#debug-state':
                this.updateStateTab();
                break;
            case '#debug-history':
                this.updateHistoryTab();
                break;
            case '#debug-metrics':
                this.updateMetricsTab();
                break;
            case '#debug-logs':
                this.updateLogsTab();
                break;
            case '#debug-sync':
                this.updateSyncTab();
                break;
        }
    }

    /**
     * Update state tab
     */
    updateStateTab() {
        const state = this.stateManager.getState();
        const stateTab = jQuery('.debug-state');
        
        stateTab.html(`
            <div class="debug-section">
                <h5>Current State</h5>
                <div class="debug-json">${JSON.stringify(state, null, 2)}</div>
            </div>
        `);
    }

    /**
     * Update history tab
     */
    updateHistoryTab() {
        const history = this.stateManager.history;
        const historyTab = jQuery('.debug-history');
        
        let historyHtml = `
            <div class="debug-section">
                <h5>Undo History (${history.past.length} items)</h5>
                <div class="debug-item">
                    <span class="debug-label">Can Undo:</span>
                    <span class="debug-value">${this.stateManager.canUndo() ? 'Yes' : 'No'}</span>
                </div>
                <div class="debug-item">
                    <span class="debug-label">Can Redo:</span>
                    <span class="debug-value">${this.stateManager.canRedo() ? 'Yes' : 'No'}</span>
                </div>
            </div>

            <div class="debug-section">
                <h5>Recent Actions</h5>
        `;

        // Show last 10 actions from log
        const recentActions = this.logBuffer
            .filter(entry => entry.type === 'State Change')
            .slice(-10)
            .reverse();

        recentActions.forEach(action => {
            historyHtml += `
                <div class="debug-item">
                    <span class="debug-label">${new Date(action.timestamp).toLocaleTimeString()}:</span>
                    <span class="debug-value">${action.data.action.type}</span>
                </div>
            `;
        });

        historyHtml += '</div>';
        historyTab.html(historyHtml);
    }

    /**
     * Update metrics tab
     */
    updateMetricsTab() {
        const metrics = this.stateManager.getMetrics();
        const metricsTab = jQuery('.debug-metrics');
        
        let metricsHtml = `
            <div class="debug-section">
                <h5>Performance Metrics</h5>
                <div class="debug-metric">
                    <span class="debug-metric-name">State Size:</span>
                    <span class="debug-metric-value">${this.formatBytes(metrics.stateSize)}</span>
                </div>
                <div class="debug-metric">
                    <span class="debug-metric-name">History Size:</span>
                    <span class="debug-metric-value">${metrics.historySize} items</span>
                </div>
                <div class="debug-metric">
                    <span class="debug-metric-name">Error Count:</span>
                    <span class="debug-metric-value">${metrics.errorCount}</span>
                </div>
                <div class="debug-metric">
                    <span class="debug-metric-name">Avg Sync Latency:</span>
                    <span class="debug-metric-value">${metrics.avgSyncLatency.toFixed(2)}ms</span>
                </div>
            </div>

            <div class="debug-section">
                <h5>Operation Times</h5>
        `;

        Object.entries(metrics.operationTimes).forEach(([operation, times]) => {
            const avg = times.reduce((a, b) => a + b, 0) / times.length;
            metricsHtml += `
                <div class="debug-metric">
                    <span class="debug-metric-name">${operation}:</span>
                    <span class="debug-metric-value">${avg.toFixed(2)}ms (${times.length} ops)</span>
                </div>
            `;
        });

        metricsHtml += '</div>';
        metricsTab.html(metricsHtml);
    }

    /**
     * Update logs tab
     */
    updateLogsTab() {
        const logsTab = jQuery('.debug-logs');
        
        let logsHtml = `
            <div class="debug-section">
                <h5>Event Logs (${this.logBuffer.length}/${this.maxLogSize})</h5>
        `;

        // Show recent logs (last 50)
        this.logBuffer.slice(-50).reverse().forEach(entry => {
            logsHtml += `
                <div class="debug-log-entry ${entry.level}">
                    <strong>${new Date(entry.timestamp).toLocaleTimeString()}</strong> 
                    [${entry.type}] ${typeof entry.data === 'string' ? entry.data : JSON.stringify(entry.data)}
                </div>
            `;
        });

        logsHtml += '</div>';
        logsTab.html(logsHtml);
    }

    /**
     * Update sync tab
     */
    updateSyncTab() {
        const syncState = this.stateManager.syncState;
        const syncTab = jQuery('.debug-sync');
        
        syncTab.html(`
            <div class="debug-section">
                <h5>Sync Status</h5>
                <div class="debug-item">
                    <span class="debug-label">Online:</span>
                    <span class="debug-value ${syncState.isOnline ? 'text-success' : 'text-danger'}">
                        ${syncState.isOnline ? 'Yes' : 'No'}
                    </span>
                </div>
                <div class="debug-item">
                    <span class="debug-label">Pending Changes:</span>
                    <span class="debug-value">${syncState.pendingChanges.length}</span>
                </div>
                <div class="debug-item">
                    <span class="debug-label">WebSocket:</span>
                    <span class="debug-value">${syncState.websocket ? syncState.websocket.readyState : 'Not connected'}</span>
                </div>
            </div>

            <div class="debug-section">
                <h5>Pending Changes</h5>
                <div class="debug-json">${JSON.stringify(syncState.pendingChanges, null, 2)}</div>
            </div>
        `);
    }

    /**
     * Log an event
     */
    logEvent(type, data, level = 'info') {
        const entry = {
            timestamp: Date.now(),
            type,
            data,
            level
        };

        this.logBuffer.push(entry);

        // Limit buffer size
        if (this.logBuffer.length > this.maxLogSize) {
            this.logBuffer.shift();
        }

        // Update logs tab if visible
        if (this.isVisible && jQuery('#debug-logs').hasClass('active')) {
            this.updateLogsTab();
        }
    }

    /**
     * Clear logs
     */
    clearLogs() {
        this.logBuffer = [];
        this.updateLogsTab();
        this.logEvent('Debug Panel', 'Logs cleared', 'info');
    }

    /**
     * Export debug data
     */
    exportDebugData() {
        const debugData = {
            timestamp: new Date().toISOString(),
            state: this.stateManager.getState(),
            metrics: this.stateManager.getMetrics(),
            history: {
                pastLength: this.stateManager.history.past.length,
                futureLength: this.stateManager.history.future.length
            },
            logs: this.logBuffer,
            syncState: this.stateManager.syncState
        };

        const blob = new Blob([JSON.stringify(debugData, null, 2)], {
            type: 'application/json'
        });

        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `pipeline-debug-${Date.now()}.json`;
        a.click();
        URL.revokeObjectURL(url);

        this.logEvent('Debug Panel', 'Debug data exported', 'success');
    }

    /**
     * Format bytes to human readable
     */
    formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    /**
     * Update metrics (called periodically)
     */
    updateMetrics() {
        if (jQuery('#debug-metrics').hasClass('active')) {
            this.updateMetricsTab();
        }
    }

    /**
     * Update sync status (called periodically)
     */
    updateSyncStatus() {
        if (jQuery('#debug-sync').hasClass('active')) {
            this.updateSyncTab();
        }
        
        // Update overview if it's visible
        if (jQuery('#debug-overview').hasClass('active')) {
            this.updateOverview();
        }
    }

    /**
     * Destroy the debugger
     */
    destroy() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
        }
        
        jQuery('#state-debug-panel').remove();
        jQuery('#state-debug-styles').remove();
        jQuery(document).off('.debug-panel');
        
        this.logEvent('Debug Panel', 'Destroyed', 'info');
    }
}

// Global helper functions for quick actions
window.StateDebugger = {
    exportState: function() {
        if (window.StateIntegration) {
            window.StateIntegration.exportState();
        }
    },

    validateState: function() {
        if (window.StateIntegration) {
            const stateManager = window.StateIntegration.getStateManager();
            const isValid = stateManager.validateState();
            alert(`State validation: ${isValid ? 'PASSED' : 'FAILED'}`);
        }
    },

    forcSync: function() {
        if (window.StateIntegration) {
            const stateManager = window.StateIntegration.getStateManager();
            stateManager.syncWithServer();
        }
    },

    resetState: function() {
        if (confirm('This will reset all state data. Are you sure?')) {
            if (window.StateIntegration) {
                window.StateIntegration.resetState();
            }
        }
    }
};

// Auto-initialize debugger when state integration is ready
jQuery(document).ready(function() {
    // Add debug toggle button
    const toggleButton = jQuery('<button class="state-debug-toggle" title="Toggle State Debugger (Ctrl+Shift+D)">🔧</button>');
    jQuery('body').append(toggleButton);

    // Wait for state integration to be ready
    const initDebugger = function() {
        if (window.StateIntegration && window.StateIntegration.getStateManager()) {
            const stateManager = window.StateIntegration.getStateManager();
            window.pipelineDebugger = new PipelineStateDebugger(stateManager);
            
            // Bind toggle button
            toggleButton.on('click', function() {
                window.pipelineDebugger.toggle();
            });
            
            console.log('Pipeline State Debugger initialized. Press Ctrl+Shift+D to toggle.');
        } else {
            setTimeout(initDebugger, 1000);
        }
    };

    setTimeout(initDebugger, 1000);
});