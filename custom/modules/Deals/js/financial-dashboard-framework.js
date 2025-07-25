/**
 * Financial Dashboard Widget Framework
 * Provides extensible framework for dashboard components with standardized interfaces
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 */

(function(global) {
    'use strict';

    // Namespace for financial dashboard
    global.FinancialDashboard = global.FinancialDashboard || {};

    /**
     * Base Widget Class
     * All dashboard widgets should extend this class
     */
    class DashboardWidget {
        constructor(config) {
            this.id = config.id || this.generateId();
            this.element = config.element;
            this.data = config.data || {};
            this.options = config.options || {};
            this.state = 'uninitialized';
            this.eventHandlers = {};
            this.updateQueue = [];
            this.isUpdating = false;
        }

        generateId() {
            return 'widget-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        }

        // Lifecycle methods
        initialize() {
            this.state = 'initializing';
            this.setupEventListeners();
            this.render();
            this.state = 'initialized';
            this.emit('initialized', { widget: this });
        }

        render() {
            // To be implemented by subclasses
            throw new Error('render() method must be implemented by widget subclass');
        }

        update(data) {
            this.updateQueue.push(data);
            if (!this.isUpdating) {
                this.processUpdateQueue();
            }
        }

        async processUpdateQueue() {
            this.isUpdating = true;
            while (this.updateQueue.length > 0) {
                const data = this.updateQueue.shift();
                await this.performUpdate(data);
            }
            this.isUpdating = false;
        }

        async performUpdate(data) {
            this.state = 'updating';
            this.emit('beforeUpdate', { widget: this, data: data });
            
            // Merge new data
            this.data = Object.assign({}, this.data, data);
            
            // Re-render with new data
            await this.render();
            
            this.state = 'updated';
            this.emit('afterUpdate', { widget: this, data: this.data });
        }

        dispose() {
            this.state = 'disposing';
            this.emit('beforeDispose', { widget: this });
            
            // Clean up event listeners
            this.removeEventListeners();
            
            // Clear element
            if (this.element) {
                this.element.innerHTML = '';
            }
            
            // Clear data
            this.data = null;
            this.updateQueue = [];
            
            this.state = 'disposed';
            this.emit('disposed', { widget: this });
        }

        // Event handling
        on(event, handler) {
            if (!this.eventHandlers[event]) {
                this.eventHandlers[event] = [];
            }
            this.eventHandlers[event].push(handler);
        }

        off(event, handler) {
            if (this.eventHandlers[event]) {
                this.eventHandlers[event] = this.eventHandlers[event].filter(h => h !== handler);
            }
        }

        emit(event, data) {
            if (this.eventHandlers[event]) {
                this.eventHandlers[event].forEach(handler => {
                    try {
                        handler(data);
                    } catch (error) {
                        console.error('Error in event handler for ' + event + ':', error);
                    }
                });
            }
        }

        setupEventListeners() {
            // To be implemented by subclasses
        }

        removeEventListeners() {
            // To be implemented by subclasses
        }

        // Utility methods
        formatCurrency(value) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(value);
        }

        formatPercent(value) {
            return new Intl.NumberFormat('en-US', {
                style: 'percent',
                minimumFractionDigits: 1,
                maximumFractionDigits: 1
            }).format(value / 100);
        }

        formatNumber(value, decimals = 0) {
            return new Intl.NumberFormat('en-US', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            }).format(value);
        }
    }

    /**
     * Widget Registry
     * Manages all dashboard widgets
     */
    class WidgetRegistry {
        constructor() {
            this.widgets = new Map();
            this.widgetTypes = new Map();
        }

        registerType(type, WidgetClass) {
            if (!WidgetClass.prototype instanceof DashboardWidget) {
                throw new Error('Widget class must extend DashboardWidget');
            }
            this.widgetTypes.set(type, WidgetClass);
        }

        createWidget(type, config) {
            if (!this.widgetTypes.has(type)) {
                throw new Error('Unknown widget type: ' + type);
            }
            
            const WidgetClass = this.widgetTypes.get(type);
            const widget = new WidgetClass(config);
            
            this.widgets.set(widget.id, widget);
            return widget;
        }

        getWidget(id) {
            return this.widgets.get(id);
        }

        getAllWidgets() {
            return Array.from(this.widgets.values());
        }

        removeWidget(id) {
            const widget = this.widgets.get(id);
            if (widget) {
                widget.dispose();
                this.widgets.delete(id);
            }
        }

        clear() {
            this.widgets.forEach(widget => widget.dispose());
            this.widgets.clear();
        }
    }

    /**
     * Data Binding Interface
     * Standardizes data binding for financial metrics
     */
    class DataBinding {
        constructor(source, target, transformer) {
            this.source = source;
            this.target = target;
            this.transformer = transformer || (data => data);
            this.active = false;
        }

        bind() {
            if (this.active) return;
            
            this.active = true;
            this.source.on('dataChanged', (data) => {
                const transformed = this.transformer(data);
                this.target.update(transformed);
            });
        }

        unbind() {
            this.active = false;
            // Note: In production, we'd need to store and remove specific listeners
        }
    }

    /**
     * Event Bus
     * Facilitates inter-widget communication
     */
    class EventBus {
        constructor() {
            this.events = {};
        }

        subscribe(event, callback) {
            if (!this.events[event]) {
                this.events[event] = [];
            }
            this.events[event].push(callback);
        }

        unsubscribe(event, callback) {
            if (this.events[event]) {
                this.events[event] = this.events[event].filter(cb => cb !== callback);
            }
        }

        publish(event, data) {
            if (this.events[event]) {
                this.events[event].forEach(callback => {
                    try {
                        callback(data);
                    } catch (error) {
                        console.error('Error in event bus callback:', error);
                    }
                });
            }
        }
    }

    /**
     * Dashboard Manager
     * Main controller for the financial dashboard
     */
    class DashboardManager {
        constructor() {
            this.registry = new WidgetRegistry();
            this.eventBus = new EventBus();
            this.dataBindings = [];
            this.config = {};
        }

        initialize(config) {
            this.config = config;
            this.setupEventHandlers();
            this.loadWidgets();
        }

        setupEventHandlers() {
            // Handle global dashboard events
            this.eventBus.subscribe('widget:requestUpdate', (data) => {
                this.updateWidget(data.widgetId, data.updates);
            });

            this.eventBus.subscribe('dashboard:refresh', () => {
                this.refreshAllWidgets();
            });
        }

        loadWidgets() {
            // Load widget configuration from server or config
            const widgetConfigs = this.config.widgets || [];
            
            widgetConfigs.forEach(config => {
                try {
                    const widget = this.registry.createWidget(config.type, config);
                    widget.initialize();
                } catch (error) {
                    console.error('Failed to load widget:', error);
                }
            });
        }

        updateWidget(widgetId, data) {
            const widget = this.registry.getWidget(widgetId);
            if (widget) {
                widget.update(data);
            }
        }

        refreshAllWidgets() {
            this.registry.getAllWidgets().forEach(widget => {
                // Trigger data refresh for each widget
                this.fetchWidgetData(widget.id).then(data => {
                    widget.update(data);
                });
            });
        }

        async fetchWidgetData(widgetId) {
            // Fetch fresh data from server
            // This would be implemented based on specific widget needs
            return {};
        }

        createDataBinding(sourceWidget, targetWidget, transformer) {
            const binding = new DataBinding(sourceWidget, targetWidget, transformer);
            binding.bind();
            this.dataBindings.push(binding);
            return binding;
        }

        dispose() {
            // Clean up all widgets
            this.registry.clear();
            
            // Unbind all data bindings
            this.dataBindings.forEach(binding => binding.unbind());
            this.dataBindings = [];
            
            // Clear event bus
            this.eventBus.events = {};
        }
    }

    // Export to global namespace
    global.FinancialDashboard.DashboardWidget = DashboardWidget;
    global.FinancialDashboard.WidgetRegistry = WidgetRegistry;
    global.FinancialDashboard.DataBinding = DataBinding;
    global.FinancialDashboard.EventBus = EventBus;
    global.FinancialDashboard.DashboardManager = DashboardManager;

    // Create singleton instance
    global.FinancialDashboard.manager = new DashboardManager();

})(window);