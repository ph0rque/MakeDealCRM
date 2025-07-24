/**
 * State Management Architecture for Pipeline System
 * 
 * Provides centralized state management with:
 * - Persistent state across page reloads
 * - Multi-user synchronization
 * - Undo/redo functionality
 * - State validation and error recovery
 * - Performance monitoring and debugging
 */

class PipelineStateManager {
    constructor(config = {}) {
        this.config = {
            autoSave: true,
            autoSaveInterval: 5000, // 5 seconds
            maxHistorySize: 50,
            syncInterval: 30000, // 30 seconds for multi-user sync
            enableDebug: false,
            storageKey: 'pipeline_state',
            websocketUrl: config.websocketUrl || null,
            userId: config.userId || 'anonymous',
            sessionId: config.sessionId || this.generateSessionId(),
            ...config
        };

        // State containers
        this.state = {
            deals: {},
            stages: {},
            filters: {
                focusOnly: false,
                compactView: false,
                searchQuery: '',
                assignedUser: null
            },
            ui: {
                selectedDeals: new Set(),
                dragState: null,
                notifications: [],
                loading: false
            },
            session: {
                userId: this.config.userId,
                sessionId: this.config.sessionId,
                lastSync: null,
                version: 1
            }
        };

        // History for undo/redo
        this.history = {
            past: [],
            future: [],
            current: null
        };

        // Event system
        this.listeners = new Map();
        this.middleware = [];

        // Sync state
        this.syncState = {
            isOnline: navigator.onLine,
            pendingChanges: [],
            conflictResolver: null,
            websocket: null
        };

        // Performance monitoring
        this.metrics = {
            operationTimes: {},
            stateSize: 0,
            syncLatency: [],
            errorCount: 0
        };

        this.initialize();
    }

    /**
     * Initialize the state manager
     */
    initialize() {
        this.debug('Initializing Pipeline State Manager');

        // Load persisted state
        this.loadPersistedState();

        // Setup auto-save
        if (this.config.autoSave) {
            this.setupAutoSave();
        }

        // Setup synchronization
        this.setupSync();

        // Setup event listeners
        this.setupEventListeners();

        // Initialize WebSocket if configured
        if (this.config.websocketUrl) {
            this.initializeWebSocket();
        }

        // Validate initial state
        this.validateState();

        this.debug('State Manager initialized', this.state);
    }

    /**
     * Get current state or specific state slice
     */
    getState(path = null) {
        if (!path) {
            return { ...this.state };
        }

        return this.getNestedValue(this.state, path);
    }

    /**
     * Update state with action
     */
    dispatch(action) {
        const startTime = performance.now();

        try {
            this.debug('Dispatching action:', action);

            // Validate action
            if (!this.validateAction(action)) {
                throw new Error(`Invalid action: ${JSON.stringify(action)}`);
            }

            // Save current state to history
            this.saveToHistory();

            // Apply middleware
            const processedAction = this.applyMiddleware(action);

            // Apply state changes
            const newState = this.reducer(this.state, processedAction);

            // Validate new state
            if (!this.validateState(newState)) {
                this.restoreFromHistory();
                throw new Error('State validation failed after action');
            }

            // Update state
            const oldState = { ...this.state };
            this.state = newState;

            // Track metrics
            const duration = performance.now() - startTime;
            this.trackOperation(action.type, duration);

            // Emit change event
            this.emit('stateChange', {
                action: processedAction,
                oldState,
                newState: this.state,
                timestamp: Date.now()
            });

            // Auto-save if enabled
            if (this.config.autoSave) {
                this.persistState();
            }

            // Add to sync queue if online
            if (this.syncState.isOnline && this.requiresSync(action)) {
                this.addToSyncQueue(action);
            }

            this.debug('Action dispatched successfully', { action: processedAction, newState: this.state });

            return this.state;

        } catch (error) {
            this.handleError('dispatch', error, { action });
            throw error;
        }
    }

    /**
     * State reducer - handles all state mutations
     */
    reducer(state, action) {
        const newState = { ...state };

        switch (action.type) {
            case 'DEALS_LOADED':
                newState.deals = { ...action.payload.deals };
                newState.stages = { ...action.payload.stages };
                break;

            case 'DEAL_MOVED':
                const { dealId, fromStage, toStage, position } = action.payload;
                if (newState.deals[dealId]) {
                    newState.deals[dealId] = {
                        ...newState.deals[dealId],
                        stage: toStage,
                        stageEnteredDate: new Date().toISOString(),
                        position: position || 0
                    };
                }
                break;

            case 'DEAL_UPDATED':
                const { id, updates } = action.payload;
                if (newState.deals[id]) {
                    newState.deals[id] = {
                        ...newState.deals[id],
                        ...updates,
                        lastModified: new Date().toISOString()
                    };
                }
                break;

            case 'DEAL_FOCUS_TOGGLED':
                const { dealId: focusDealId, focused, focusOrder } = action.payload;
                if (newState.deals[focusDealId]) {
                    newState.deals[focusDealId] = {
                        ...newState.deals[focusDealId],
                        focused,
                        focusOrder: focused ? focusOrder : 0,
                        focusDate: focused ? new Date().toISOString() : null
                    };
                }
                break;

            case 'FILTER_UPDATED':
                newState.filters = {
                    ...newState.filters,
                    ...action.payload
                };
                break;

            case 'UI_STATE_UPDATED':
                newState.ui = {
                    ...newState.ui,
                    ...action.payload
                };
                break;

            case 'DEAL_SELECTED':
                const newSelected = new Set(newState.ui.selectedDeals);
                if (action.payload.selected) {
                    newSelected.add(action.payload.dealId);
                } else {
                    newSelected.delete(action.payload.dealId);
                }
                newState.ui.selectedDeals = newSelected;
                break;

            case 'DRAG_STARTED':
                newState.ui.dragState = {
                    dealId: action.payload.dealId,
                    sourceStage: action.payload.sourceStage,
                    startTime: Date.now()
                };
                break;

            case 'DRAG_ENDED':
                newState.ui.dragState = null;
                break;

            case 'NOTIFICATION_ADDED':
                newState.ui.notifications = [
                    ...newState.ui.notifications,
                    {
                        id: this.generateId(),
                        ...action.payload,
                        timestamp: Date.now()
                    }
                ];
                break;

            case 'NOTIFICATION_REMOVED':
                newState.ui.notifications = newState.ui.notifications.filter(
                    n => n.id !== action.payload.notificationId
                );
                break;

            case 'SYNC_COMPLETED':
                newState.session.lastSync = action.payload.timestamp;
                newState.session.version = action.payload.version;
                break;

            case 'RESET_STATE':
                return this.getInitialState();

            default:
                this.debug('Unknown action type:', action.type);
        }

        // Update session info
        newState.session.lastModified = Date.now();

        return newState;
    }

    /**
     * Action creators
     */
    actions = {
        loadDeals: (deals, stages) => ({
            type: 'DEALS_LOADED',
            payload: { deals, stages }
        }),

        moveDeal: (dealId, fromStage, toStage, position = null) => ({
            type: 'DEAL_MOVED',
            payload: { dealId, fromStage, toStage, position },
            meta: { requiresSync: true, optimistic: true }
        }),

        updateDeal: (id, updates) => ({
            type: 'DEAL_UPDATED',
            payload: { id, updates },
            meta: { requiresSync: true }
        }),

        toggleDealFocus: (dealId, focused, focusOrder = null) => ({
            type: 'DEAL_FOCUS_TOGGLED',
            payload: { dealId, focused, focusOrder },
            meta: { requiresSync: true }
        }),

        updateFilters: (filters) => ({
            type: 'FILTER_UPDATED',
            payload: filters
        }),

        updateUI: (uiUpdates) => ({
            type: 'UI_STATE_UPDATED',
            payload: uiUpdates
        }),

        selectDeal: (dealId, selected = true) => ({
            type: 'DEAL_SELECTED',
            payload: { dealId, selected }
        }),

        startDrag: (dealId, sourceStage) => ({
            type: 'DRAG_STARTED',
            payload: { dealId, sourceStage }
        }),

        endDrag: () => ({
            type: 'DRAG_ENDED'
        }),

        addNotification: (type, message, autoHide = true) => ({
            type: 'NOTIFICATION_ADDED',
            payload: { type, message, autoHide }
        }),

        removeNotification: (notificationId) => ({
            type: 'NOTIFICATION_REMOVED',
            payload: { notificationId }
        }),

        syncCompleted: (timestamp, version) => ({
            type: 'SYNC_COMPLETED',
            payload: { timestamp, version }
        }),

        resetState: () => ({
            type: 'RESET_STATE'
        })
    };

    /**
     * Undo/Redo functionality
     */
    undo() {
        if (!this.canUndo()) {
            this.debug('Cannot undo - no history available');
            return false;
        }

        const previousState = this.history.past.pop();
        this.history.future.unshift(this.state);

        this.state = previousState;
        this.emit('stateChange', {
            action: { type: 'UNDO' },
            oldState: this.history.future[0],
            newState: this.state,
            timestamp: Date.now()
        });

        this.debug('Undo completed', { newState: this.state });
        return true;
    }

    redo() {
        if (!this.canRedo()) {
            this.debug('Cannot redo - no future state available');
            return false;
        }

        const nextState = this.history.future.shift();
        this.history.past.push(this.state);

        this.state = nextState;
        this.emit('stateChange', {
            action: { type: 'REDO' },
            oldState: this.history.past[this.history.past.length - 1],
            newState: this.state,
            timestamp: Date.now()
        });

        this.debug('Redo completed', { newState: this.state });
        return true;
    }

    canUndo() {
        return this.history.past.length > 0;
    }

    canRedo() {
        return this.history.future.length > 0;
    }

    /**
     * State persistence
     */
    persistState() {
        try {
            const stateToSave = {
                ...this.state,
                ui: {
                    ...this.state.ui,
                    selectedDeals: Array.from(this.state.ui.selectedDeals) // Convert Set to Array
                }
            };

            const serialized = JSON.stringify({
                state: stateToSave,
                timestamp: Date.now(),
                version: this.state.session.version
            });

            localStorage.setItem(this.config.storageKey, serialized);
            
            // Also save to IndexedDB for larger state
            this.saveToIndexedDB(stateToSave);

            this.debug('State persisted successfully');

        } catch (error) {
            this.handleError('persistState', error);
        }
    }

    loadPersistedState() {
        try {
            const saved = localStorage.getItem(this.config.storageKey);
            if (!saved) {
                this.debug('No persisted state found');
                return;
            }

            const { state, timestamp, version } = JSON.parse(saved);
            
            // Convert selectedDeals array back to Set
            if (state.ui && Array.isArray(state.ui.selectedDeals)) {
                state.ui.selectedDeals = new Set(state.ui.selectedDeals);
            }

            // Validate loaded state
            if (this.validateState(state)) {
                this.state = {
                    ...this.getInitialState(),
                    ...state,
                    session: {
                        ...state.session,
                        sessionId: this.config.sessionId // Always use current session ID
                    }
                };
                
                this.debug('State loaded successfully', { timestamp, version });
            } else {
                this.debug('Loaded state failed validation, using initial state');
            }

        } catch (error) {
            this.handleError('loadPersistedState', error);
        }
    }

    /**
     * Multi-user synchronization
     */
    setupSync() {
        if (!this.syncState.isOnline) {
            this.debug('Offline - sync disabled');
            return;
        }

        // Periodic sync
        setInterval(() => {
            this.syncWithServer();
        }, this.config.syncInterval);

        // Online/offline handlers
        window.addEventListener('online', () => {
            this.syncState.isOnline = true;
            this.debug('Connection restored - syncing pending changes');
            this.syncPendingChanges();
        });

        window.addEventListener('offline', () => {
            this.syncState.isOnline = false;
            this.debug('Connection lost - enabling offline mode');
        });
    }

    async syncWithServer() {
        if (!this.syncState.isOnline || this.syncState.pendingChanges.length === 0) {
            return;
        }

        try {
            const syncStart = performance.now();

            const response = await fetch('/api/pipeline/sync', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Session-ID': this.config.sessionId
                },
                body: JSON.stringify({
                    changes: this.syncState.pendingChanges,
                    currentVersion: this.state.session.version,
                    userId: this.config.userId
                })
            });

            if (!response.ok) {
                throw new Error(`Sync failed: ${response.statusText}`);
            }

            const result = await response.json();
            
            if (result.conflicts && result.conflicts.length > 0) {
                this.handleSyncConflicts(result.conflicts);
            }

            // Apply server changes
            if (result.changes && result.changes.length > 0) {
                this.applyServerChanges(result.changes);
            }

            // Clear synced changes
            this.syncState.pendingChanges = [];

            // Update sync info
            this.dispatch(this.actions.syncCompleted(Date.now(), result.version));

            // Track sync performance
            const syncTime = performance.now() - syncStart;
            this.metrics.syncLatency.push(syncTime);
            if (this.metrics.syncLatency.length > 10) {
                this.metrics.syncLatency.shift();
            }

            this.debug('Sync completed successfully', { syncTime, version: result.version });

        } catch (error) {
            this.handleError('syncWithServer', error);
            
            // Retry logic for failed syncs
            setTimeout(() => {
                if (this.syncState.isOnline) {
                    this.syncWithServer();
                }
            }, 5000);
        }
    }

    /**
     * WebSocket for real-time updates
     */
    initializeWebSocket() {
        try {
            this.syncState.websocket = new WebSocket(this.config.websocketUrl);

            this.syncState.websocket.onopen = () => {
                this.debug('WebSocket connected');
                this.syncState.websocket.send(JSON.stringify({
                    type: 'subscribe',
                    sessionId: this.config.sessionId,
                    userId: this.config.userId
                }));
            };

            this.syncState.websocket.onmessage = (event) => {
                try {
                    const message = JSON.parse(event.data);
                    this.handleWebSocketMessage(message);
                } catch (error) {
                    this.handleError('websocketMessage', error);
                }
            };

            this.syncState.websocket.onclose = () => {
                this.debug('WebSocket disconnected - attempting reconnect');
                setTimeout(() => {
                    this.initializeWebSocket();
                }, 5000);
            };

        } catch (error) {
            this.handleError('initializeWebSocket', error);
        }
    }

    /**
     * State validation
     */
    validateState(state = this.state) {
        try {
            // Basic structure validation
            const requiredKeys = ['deals', 'stages', 'filters', 'ui', 'session'];
            for (const key of requiredKeys) {
                if (!(key in state)) {
                    this.debug(`Validation failed: missing key ${key}`);
                    return false;
                }
            }

            // Validate deals
            if (typeof state.deals !== 'object') {
                this.debug('Validation failed: deals must be an object');
                return false;
            }

            // Validate UI state
            if (!(state.ui.selectedDeals instanceof Set)) {
                this.debug('Validation failed: selectedDeals must be a Set');
                return false;
            }

            // Validate session
            if (!state.session.userId || !state.session.sessionId) {
                this.debug('Validation failed: session missing userId or sessionId');
                return false;
            }

            return true;

        } catch (error) {
            this.handleError('validateState', error);
            return false;
        }
    }

    /**
     * Error handling and recovery
     */
    handleError(operation, error, context = {}) {
        this.metrics.errorCount++;
        
        const errorInfo = {
            operation,
            error: error.message,
            stack: error.stack,
            context,
            timestamp: Date.now(),
            sessionId: this.config.sessionId
        };

        console.error(`StateManager Error in ${operation}:`, errorInfo);

        // Emit error event
        this.emit('error', errorInfo);

        // Attempt recovery based on error type
        this.attemptRecovery(operation, error);
    }

    attemptRecovery(operation, error) {
        switch (operation) {
            case 'dispatch':
                this.debug('Attempting state recovery after dispatch error');
                this.restoreFromHistory();
                break;

            case 'persistState':
                this.debug('Attempting to clear corrupted localStorage');
                try {
                    localStorage.removeItem(this.config.storageKey);
                } catch (e) {
                    this.debug('Failed to clear localStorage');
                }
                break;

            case 'syncWithServer':
                this.debug('Sync failed - enabling offline mode temporarily');
                // Handled by retry logic in syncWithServer
                break;

            default:
                this.debug(`No recovery strategy for operation: ${operation}`);
        }
    }

    /**
     * Performance monitoring and debugging
     */
    getMetrics() {
        return {
            ...this.metrics,
            stateSize: JSON.stringify(this.state).length,
            historySize: this.history.past.length + this.history.future.length,
            avgSyncLatency: this.metrics.syncLatency.reduce((a, b) => a + b, 0) / this.metrics.syncLatency.length || 0,
            uptime: Date.now() - (this.initTime || Date.now())
        };
    }

    enableDebugMode() {
        this.config.enableDebug = true;
        this.debug('Debug mode enabled');
    }

    disableDebugMode() {
        this.config.enableDebug = false;
    }

    debug(message, data = null) {
        if (this.config.enableDebug) {
            console.log(`[StateManager] ${message}`, data);
        }
    }

    /**
     * Event system
     */
    on(event, callback) {
        if (!this.listeners.has(event)) {
            this.listeners.set(event, new Set());
        }
        this.listeners.get(event).add(callback);
    }

    off(event, callback) {
        if (this.listeners.has(event)) {
            this.listeners.get(event).delete(callback);
        }
    }

    emit(event, data) {
        if (this.listeners.has(event)) {
            this.listeners.get(event).forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    this.handleError('eventCallback', error, { event, data });
                }
            });
        }
    }

    /**
     * Utility methods
     */
    generateId() {
        return 'id_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
    }

    generateSessionId() {
        return 'session_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
    }

    getNestedValue(obj, path) {
        return path.split('.').reduce((current, key) => current && current[key], obj);
    }

    getInitialState() {
        return {
            deals: {},
            stages: {},
            filters: {
                focusOnly: false,
                compactView: false,
                searchQuery: '',
                assignedUser: null
            },
            ui: {
                selectedDeals: new Set(),
                dragState: null,
                notifications: [],
                loading: false
            },
            session: {
                userId: this.config.userId,
                sessionId: this.config.sessionId,
                lastSync: null,
                version: 1,
                lastModified: Date.now()
            }
        };
    }

    // Additional helper methods for history, validation, sync, etc.
    saveToHistory() {
        // Deep clone current state
        const stateClone = JSON.parse(JSON.stringify({
            ...this.state,
            ui: {
                ...this.state.ui,
                selectedDeals: Array.from(this.state.ui.selectedDeals)
            }
        }));

        // Convert selectedDeals back to Set
        stateClone.ui.selectedDeals = new Set(stateClone.ui.selectedDeals);

        this.history.past.push(stateClone);
        
        // Limit history size
        if (this.history.past.length > this.config.maxHistorySize) {
            this.history.past.shift();
        }

        // Clear future history
        this.history.future = [];
    }

    restoreFromHistory() {
        if (this.history.past.length > 0) {
            this.state = this.history.past.pop();
            this.debug('State restored from history');
        }
    }

    validateAction(action) {
        return action && typeof action.type === 'string';
    }

    applyMiddleware(action) {
        return this.middleware.reduce((acc, middleware) => {
            return middleware(acc, this.state);
        }, action);
    }

    requiresSync(action) {
        return action.meta && action.meta.requiresSync;
    }

    addToSyncQueue(action) {
        this.syncState.pendingChanges.push({
            action,
            timestamp: Date.now(),
            sessionId: this.config.sessionId
        });
    }

    trackOperation(operationType, duration) {
        if (!this.metrics.operationTimes[operationType]) {
            this.metrics.operationTimes[operationType] = [];
        }
        this.metrics.operationTimes[operationType].push(duration);
        
        // Keep only last 100 measurements
        if (this.metrics.operationTimes[operationType].length > 100) {
            this.metrics.operationTimes[operationType].shift();
        }
    }

    setupAutoSave() {
        setInterval(() => {
            this.persistState();
        }, this.config.autoSaveInterval);
    }

    setupEventListeners() {
        // Handle page unload
        window.addEventListener('beforeunload', () => {
            this.persistState();
        });

        // Handle visibility change
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.persistState();
            }
        });
    }

    async saveToIndexedDB(state) {
        // Implementation for IndexedDB storage for larger states
        try {
            const request = indexedDB.open('PipelineState', 1);
            
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                if (!db.objectStoreNames.contains('states')) {
                    db.createObjectStore('states', { keyPath: 'id' });
                }
            };

            request.onsuccess = (event) => {
                const db = event.target.result;
                const transaction = db.transaction(['states'], 'readwrite');
                const store = transaction.objectStore('states');
                
                store.put({
                    id: 'current',
                    state,
                    timestamp: Date.now()
                });
            };

        } catch (error) {
            this.debug('IndexedDB not available or error:', error);
        }
    }

    handleSyncConflicts(conflicts) {
        // Implement conflict resolution strategy
        this.debug('Handling sync conflicts:', conflicts);
        
        conflicts.forEach(conflict => {
            // For now, server wins - could implement more sophisticated resolution
            this.dispatch({
                type: 'RESOLVE_CONFLICT',
                payload: conflict.serverState
            });
        });
    }

    applyServerChanges(changes) {
        changes.forEach(change => {
            this.dispatch(change.action);
        });
    }

    handleWebSocketMessage(message) {
        switch (message.type) {
            case 'state_update':
                this.dispatch(message.action);
                break;
                
            case 'conflict':
                this.handleSyncConflicts([message.conflict]);
                break;
                
            default:
                this.debug('Unknown WebSocket message type:', message.type);
        }
    }

    syncPendingChanges() {
        if (this.syncState.pendingChanges.length > 0) {
            this.syncWithServer();
        }
    }

    // Public API methods
    destroy() {
        // Cleanup
        if (this.syncState.websocket) {
            this.syncState.websocket.close();
        }
        
        this.listeners.clear();
        this.persistState();
        this.debug('StateManager destroyed');
    }
}

// Export for use in other modules
window.PipelineStateManager = PipelineStateManager;