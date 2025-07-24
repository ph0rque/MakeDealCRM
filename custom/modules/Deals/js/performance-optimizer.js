/**
 * Performance Optimizer for Pipeline with Large Datasets
 * 
 * Implements:
 * - Lazy loading and virtualization
 * - Intelligent caching
 * - Database query optimization
 * - Pagination and infinite scroll
 * - Web workers for heavy calculations
 */

class PipelinePerformanceOptimizer {
    constructor(config = {}) {
        this.config = {
            // Virtualization settings
            virtualScrollEnabled: true,
            itemHeight: 120, // Average deal card height
            bufferSize: 5, // Number of items to render outside viewport
            
            // Lazy loading settings
            lazyLoadThreshold: 100, // Load more when within 100px of bottom
            batchSize: 20, // Items to load per batch
            maxConcurrentRequests: 3,
            
            // Caching settings
            cacheEnabled: true,
            cacheExpiration: 300000, // 5 minutes in milliseconds
            maxCacheSize: 1000, // Maximum cached items
            
            // Performance monitoring
            performanceMonitoring: true,
            debugMode: false,
            
            ...config
        };
        
        this.cache = new Map();
        this.loadingStates = new Map();
        this.visibleItems = new Map();
        this.observers = new Map();
        this.webWorker = null;
        this.metrics = {
            cacheHits: 0,
            cacheMisses: 0,
            loadTimes: [],
            renderTimes: []
        };
        
        this.init();
    }
    
    /**
     * Initialize performance optimizer
     */
    init() {
        this.setupWebWorker();
        this.setupIntersectionObserver();
        this.setupPerformanceMonitoring();
        
        if (this.config.debugMode) {
            this.enableDebugMode();
        }
    }
    
    /**
     * Setup web worker for heavy calculations
     */
    setupWebWorker() {
        if (typeof Worker !== 'undefined') {
            const workerScript = this.createWorkerScript();
            const blob = new Blob([workerScript], { type: 'application/javascript' });
            this.webWorker = new Worker(URL.createObjectURL(blob));
            
            this.webWorker.onmessage = (e) => {
                this.handleWorkerMessage(e.data);
            };
        }
    }
    
    /**
     * Create web worker script
     */
    createWorkerScript() {
        return `
            // Web Worker for heavy pipeline calculations
            self.onmessage = function(e) {
                const { type, data, taskId } = e.data;
                let result;
                
                switch (type) {
                    case 'calculateTimeInStage':
                        result = calculateTimeInStage(data);
                        break;
                    case 'sortDeals':
                        result = sortDeals(data);
                        break;
                    case 'filterDeals':
                        result = filterDeals(data);
                        break;
                    case 'aggregateMetrics':
                        result = aggregateMetrics(data);
                        break;
                    default:
                        result = { error: 'Unknown task type' };
                }
                
                self.postMessage({ taskId, result });
            };
            
            function calculateTimeInStage(deals) {
                return deals.map(deal => {
                    const stageDate = new Date(deal.stage_entered_date_c || deal.date_modified);
                    const now = new Date();
                    const daysDiff = Math.floor((now - stageDate) / (1000 * 60 * 60 * 24));
                    
                    return {
                        ...deal,
                        days_in_stage: daysDiff,
                        stage_color_class: daysDiff > 30 ? 'stage-red' : 
                                         daysDiff > 14 ? 'stage-orange' : 'stage-normal'
                    };
                });
            }
            
            function sortDeals(data) {
                const { deals, sortBy, sortOrder } = data;
                
                return deals.sort((a, b) => {
                    let aVal = a[sortBy];
                    let bVal = b[sortBy];
                    
                    // Handle focus ordering
                    if (sortBy === 'focus_order') {
                        aVal = a.focus_flag_c ? (a.focus_order_c || 0) : 999999;
                        bVal = b.focus_flag_c ? (b.focus_order_c || 0) : 999999;
                    }
                    
                    if (typeof aVal === 'string') {
                        aVal = aVal.toLowerCase();
                        bVal = bVal.toLowerCase();
                    }
                    
                    if (sortOrder === 'desc') {
                        return aVal < bVal ? 1 : aVal > bVal ? -1 : 0;
                    } else {
                        return aVal > bVal ? 1 : aVal < bVal ? -1 : 0;
                    }
                });
            }
            
            function filterDeals(data) {
                const { deals, filters } = data;
                
                return deals.filter(deal => {
                    for (const [field, value] of Object.entries(filters)) {
                        if (field === 'focus_only' && value && !deal.focus_flag_c) {
                            return false;
                        }
                        if (field === 'stage' && value && deal.pipeline_stage_c !== value) {
                            return false;
                        }
                        if (field === 'assigned_user' && value && deal.assigned_user_id !== value) {
                            return false;
                        }
                        if (field === 'amount_min' && value && (deal.amount || 0) < value) {
                            return false;
                        }
                        if (field === 'amount_max' && value && (deal.amount || 0) > value) {
                            return false;
                        }
                    }
                    return true;
                });
            }
            
            function aggregateMetrics(deals) {
                const metrics = {
                    total_deals: deals.length,
                    total_value: 0,
                    avg_value: 0,
                    stages: {},
                    users: {}
                };
                
                deals.forEach(deal => {
                    const amount = parseFloat(deal.amount) || 0;
                    metrics.total_value += amount;
                    
                    // Stage metrics
                    const stage = deal.pipeline_stage_c || 'unknown';
                    if (!metrics.stages[stage]) {
                        metrics.stages[stage] = { count: 0, value: 0 };
                    }
                    metrics.stages[stage].count++;
                    metrics.stages[stage].value += amount;
                    
                    // User metrics
                    const user = deal.assigned_user_id || 'unassigned';
                    if (!metrics.users[user]) {
                        metrics.users[user] = { count: 0, value: 0 };
                    }
                    metrics.users[user].count++;
                    metrics.users[user].value += amount;
                });
                
                metrics.avg_value = metrics.total_deals > 0 ? 
                    metrics.total_value / metrics.total_deals : 0;
                
                return metrics;
            }
        `;
    }
    
    /**
     * Handle web worker messages
     */
    handleWorkerMessage(data) {
        const { taskId, result } = data;
        const callback = this.workerCallbacks?.get(taskId);
        
        if (callback) {
            callback(result);
            this.workerCallbacks.delete(taskId);
        }
    }
    
    /**
     * Execute task in web worker
     */
    executeInWorker(type, data) {
        return new Promise((resolve, reject) => {
            if (!this.webWorker) {
                reject(new Error('Web worker not available'));
                return;
            }
            
            const taskId = `task_${Date.now()}_${Math.random()}`;
            
            if (!this.workerCallbacks) {
                this.workerCallbacks = new Map();
            }
            
            this.workerCallbacks.set(taskId, (result) => {
                if (result.error) {
                    reject(new Error(result.error));
                } else {
                    resolve(result);
                }
            });
            
            this.webWorker.postMessage({ type, data, taskId });
        });
    }
    
    /**
     * Setup intersection observer for lazy loading
     */
    setupIntersectionObserver() {
        if (typeof IntersectionObserver !== 'undefined') {
            this.intersectionObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.handleIntersection(entry.target);
                    }
                });
            }, {
                rootMargin: `${this.config.lazyLoadThreshold}px`
            });
        }
    }
    
    /**
     * Handle intersection for lazy loading
     */
    handleIntersection(element) {
        const stage = element.dataset.stage;
        if (stage && !this.loadingStates.get(stage)) {
            this.loadMoreDeals(stage);
        }
    }
    
    /**
     * Setup performance monitoring
     */
    setupPerformanceMonitoring() {
        if (!this.config.performanceMonitoring) return;
        
        // Monitor long tasks
        if ('PerformanceObserver' in window) {
            try {
                const observer = new PerformanceObserver((list) => {
                    for (const entry of list.getEntries()) {
                        if (entry.duration > 50) { // Long task threshold
                            console.warn('Long task detected:', {
                                name: entry.name,
                                duration: entry.duration,
                                startTime: entry.startTime
                            });
                        }
                    }
                });
                observer.observe({ entryTypes: ['longtask'] });
            } catch (e) {
                // PerformanceObserver not supported
            }
        }
        
        // Monitor memory usage
        this.monitorMemoryUsage();
    }
    
    /**
     * Monitor memory usage
     */
    monitorMemoryUsage() {
        if ('memory' in performance) {
            setInterval(() => {
                const memory = performance.memory;
                if (memory.usedJSHeapSize > memory.jsHeapSizeLimit * 0.9) {
                    console.warn('High memory usage detected, clearing cache');
                    this.clearCache();
                }
            }, 30000); // Check every 30 seconds
        }
    }
    
    /**
     * Enable debug mode
     */
    enableDebugMode() {
        console.log('Pipeline Performance Optimizer - Debug Mode Enabled');
        
        // Add performance overlay
        this.createDebugOverlay();
        
        // Log cache statistics
        setInterval(() => {
            console.log('Cache Statistics:', {
                size: this.cache.size,
                hits: this.metrics.cacheHits,
                misses: this.metrics.cacheMisses,
                hitRate: this.metrics.cacheHits / (this.metrics.cacheHits + this.metrics.cacheMisses) * 100
            });
        }, 10000);
    }
    
    /**
     * Create debug overlay
     */
    createDebugOverlay() {
        const overlay = document.createElement('div');
        overlay.id = 'pipeline-debug-overlay';
        overlay.style.cssText = `
            position: fixed;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 10px;
            font-family: monospace;
            font-size: 12px;
            z-index: 9999;
            border-radius: 4px;
            min-width: 200px;
        `;
        
        document.body.appendChild(overlay);
        
        // Update overlay every second
        setInterval(() => {
            const memory = performance.memory || {};
            overlay.innerHTML = `
                <div><strong>Pipeline Performance</strong></div>
                <div>Cache Size: ${this.cache.size}</div>
                <div>Cache Hits: ${this.metrics.cacheHits}</div>
                <div>Cache Misses: ${this.metrics.cacheMisses}</div>
                <div>Memory: ${Math.round((memory.usedJSHeapSize || 0) / 1048576)}MB</div>
                <div>Avg Load Time: ${this.getAverageLoadTime()}ms</div>
                <div>Avg Render Time: ${this.getAverageRenderTime()}ms</div>
            `;
        }, 1000);
    }
    
    /**
     * Load deals with optimization
     */
    async loadDeals(stage, options = {}) {
        const startTime = performance.now();
        
        const {
            offset = 0,
            limit = this.config.batchSize,
            useCache = this.config.cacheEnabled,
            sortBy = 'focus_order',
            sortOrder = 'asc',
            filters = {}
        } = options;
        
        const cacheKey = this.generateCacheKey(stage, offset, limit, sortBy, sortOrder, filters);
        
        // Check cache first
        if (useCache && this.cache.has(cacheKey)) {
            const cached = this.cache.get(cacheKey);
            if (Date.now() - cached.timestamp < this.config.cacheExpiration) {
                this.metrics.cacheHits++;
                return cached.data;
            } else {
                this.cache.delete(cacheKey);
            }
        }
        
        this.metrics.cacheMisses++;
        
        try {
            // Set loading state
            this.loadingStates.set(stage, true);
            
            // Build optimized query parameters
            const params = new URLSearchParams({
                module: 'Deals',
                action: 'getPipelineDeals',
                stage: stage,
                offset: offset,
                limit: limit,
                sort_by: sortBy,
                sort_order: sortOrder,
                ...filters
            });
            
            const response = await fetch(`index.php?${params}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            // Process deals in web worker if available
            let processedDeals = data.records;
            if (this.webWorker && processedDeals.length > 10) {
                try {
                    processedDeals = await this.executeInWorker('calculateTimeInStage', processedDeals);
                } catch (e) {
                    console.warn('Web worker processing failed, falling back to main thread:', e);
                    processedDeals = this.calculateTimeInStageMainThread(processedDeals);
                }
            } else {
                processedDeals = this.calculateTimeInStageMainThread(processedDeals);
            }
            
            const result = {
                ...data,
                records: processedDeals
            };
            
            // Cache the result
            if (useCache) {
                this.cacheResult(cacheKey, result);
            }
            
            // Record metrics
            const loadTime = performance.now() - startTime;
            this.metrics.loadTimes.push(loadTime);
            if (this.metrics.loadTimes.length > 100) {
                this.metrics.loadTimes.shift();
            }
            
            return result;
            
        } catch (error) {
            console.error('Failed to load deals:', error);
            throw error;
        } finally {
            this.loadingStates.set(stage, false);
        }
    }
    
    /**
     * Calculate time in stage on main thread (fallback)
     */
    calculateTimeInStageMainThread(deals) {
        return deals.map(deal => {
            const stageDate = new Date(deal.stage_entered_date_c || deal.date_modified);
            const now = new Date();
            const daysDiff = Math.floor((now - stageDate) / (1000 * 60 * 60 * 24));
            
            return {
                ...deal,
                days_in_stage: daysDiff,
                stage_color_class: daysDiff > 30 ? 'stage-red' : 
                                 daysDiff > 14 ? 'stage-orange' : 'stage-normal'
            };
        });
    }
    
    /**
     * Load more deals for infinite scroll
     */
    async loadMoreDeals(stage) {
        const currentDeals = this.getStageDeals(stage);
        const offset = currentDeals.length;
        
        try {
            const result = await this.loadDeals(stage, { offset });
            
            if (result.records.length > 0) {
                this.appendDealsToStage(stage, result.records);
            }
            
            return result;
        } catch (error) {
            console.error('Failed to load more deals:', error);
            return null;
        }
    }
    
    /**
     * Render deals with virtual scrolling
     */
    renderDealsWithVirtualScrolling(stage, deals, container) {
        if (!this.config.virtualScrollEnabled || deals.length < 50) {
            return this.renderDealsNormal(stage, deals, container);
        }
        
        const startTime = performance.now();
        
        const containerHeight = container.clientHeight;
        const scrollTop = container.scrollTop;
        const itemHeight = this.config.itemHeight;
        
        // Calculate visible range
        const startIndex = Math.max(0, Math.floor(scrollTop / itemHeight) - this.config.bufferSize);
        const endIndex = Math.min(deals.length, 
            Math.ceil((scrollTop + containerHeight) / itemHeight) + this.config.bufferSize);
        
        // Create virtual scroller structure
        const totalHeight = deals.length * itemHeight;
        const visibleDeals = deals.slice(startIndex, endIndex);
        
        // Build HTML
        let html = `
            <div class="virtual-scroller" style="height: ${totalHeight}px; position: relative;">
                <div class="virtual-content" style="transform: translateY(${startIndex * itemHeight}px);">
        `;
        
        visibleDeals.forEach((deal, index) => {
            html += this.renderDealCard(deal, startIndex + index);
        });
        
        html += `
                </div>
            </div>
        `;
        
        container.innerHTML = html;
        
        // Setup scroll listener for virtual scrolling
        this.setupVirtualScrollListener(container, stage, deals);
        
        // Record render time
        const renderTime = performance.now() - startTime;
        this.metrics.renderTimes.push(renderTime);
        if (this.metrics.renderTimes.length > 100) {
            this.metrics.renderTimes.shift();
        }
    }
    
    /**
     * Render deals normally
     */
    renderDealsNormal(stage, deals, container) {
        const startTime = performance.now();
        
        let html = '';
        deals.forEach((deal, index) => {
            html += this.renderDealCard(deal, index);
        });
        
        container.innerHTML = html;
        
        // Record render time
        const renderTime = performance.now() - startTime;
        this.metrics.renderTimes.push(renderTime);
        if (this.metrics.renderTimes.length > 100) {
            this.metrics.renderTimes.shift();
        }
    }
    
    /**
     * Render individual deal card
     */
    renderDealCard(deal, index) {
        const focusClass = deal.focus_flag_c ? 'focused-deal' : '';
        const stageClass = deal.stage_color_class || 'stage-normal';
        
        return `
            <div class="deal-card draggable ${stageClass} ${focusClass}" 
                 data-deal-id="${deal.id}" 
                 data-stage="${deal.pipeline_stage_c}"
                 data-focused="${deal.focus_flag_c ? 'true' : 'false'}"
                 data-focus-order="${deal.focus_order_c || 0}"
                 data-index="${index}"
                 draggable="true">
                
                <div class="deal-card-header">
                    <h4 class="deal-name">
                        <a href="index.php?module=Opportunities&action=DetailView&record=${deal.id}" 
                           target="_blank" 
                           onclick="event.stopPropagation();">
                            ${this.truncateText(deal.name, 50)}
                        </a>
                    </h4>
                    <div class="deal-card-actions">
                        <button class="focus-toggle-btn ${deal.focus_flag_c ? 'active' : ''}" 
                                onclick="PipelineView.toggleFocus('${deal.id}', ${!deal.focus_flag_c}); event.stopPropagation();" 
                                title="${deal.focus_flag_c ? 'Remove focus' : 'Mark as focused'}">
                            <span class="glyphicon glyphicon-star${deal.focus_flag_c ? '' : '-empty'}"></span>
                        </button>
                        <div class="deal-days-indicator" title="Days in current stage">
                            <span class="glyphicon glyphicon-time"></span> ${deal.days_in_stage}d
                        </div>
                    </div>
                </div>
                
                <div class="deal-card-body">
                    ${deal.account_name ? `
                        <div class="deal-account">
                            <span class="glyphicon glyphicon-briefcase"></span> ${this.truncateText(deal.account_name, 30)}
                        </div>
                    ` : ''}
                    
                    ${deal.amount ? `
                        <div class="deal-amount">
                            <span class="glyphicon glyphicon-usd"></span> ${this.formatNumber(deal.amount)}
                        </div>
                    ` : ''}
                    
                    ${deal.assigned_user_name ? `
                        <div class="deal-assigned">
                            <span class="glyphicon glyphicon-user"></span> ${deal.assigned_user_name}
                        </div>
                    ` : ''}
                    
                    ${deal.expected_close_date_c ? `
                        <div class="deal-close-date">
                            <span class="glyphicon glyphicon-calendar"></span> ${deal.expected_close_date_c}
                        </div>
                    ` : ''}
                </div>
                
                ${deal.probability ? `
                    <div class="deal-probability">
                        <div class="probability-bar" style="width: ${deal.probability}%"></div>
                        <span class="probability-text">${deal.probability}%</span>
                    </div>
                ` : ''}
            </div>
        `;
    }
    
    /**
     * Setup virtual scroll listener
     */
    setupVirtualScrollListener(container, stage, deals) {
        let ticking = false;
        
        const updateVirtualScroll = () => {
            if (!ticking) {
                requestAnimationFrame(() => {
                    this.renderDealsWithVirtualScrolling(stage, deals, container);
                    ticking = false;
                });
                ticking = true;
            }
        };
        
        container.addEventListener('scroll', updateVirtualScroll, { passive: true });
    }
    
    /**
     * Generate cache key
     */
    generateCacheKey(stage, offset, limit, sortBy, sortOrder, filters) {
        const filterKey = Object.keys(filters).sort().map(key => `${key}:${filters[key]}`).join(',');
        return `${stage}:${offset}:${limit}:${sortBy}:${sortOrder}:${filterKey}`;
    }
    
    /**
     * Cache result
     */
    cacheResult(key, data) {
        // Enforce cache size limit
        if (this.cache.size >= this.config.maxCacheSize) {
            const firstKey = this.cache.keys().next().value;
            this.cache.delete(firstKey);
        }
        
        this.cache.set(key, {
            data: data,
            timestamp: Date.now()
        });
    }
    
    /**
     * Clear cache
     */
    clearCache() {
        this.cache.clear();
        this.metrics.cacheHits = 0;
        this.metrics.cacheMisses = 0;
    }
    
    /**
     * Get stage deals from DOM
     */
    getStageDeals(stage) {
        const stageElement = document.querySelector(`[data-stage="${stage}"] .stage-body`);
        if (!stageElement) return [];
        
        return Array.from(stageElement.querySelectorAll('.deal-card')).map(card => ({
            id: card.dataset.dealId,
            stage: card.dataset.stage
        }));
    }
    
    /**
     * Append deals to stage
     */
    appendDealsToStage(stage, deals) {
        const stageBody = document.querySelector(`[data-stage="${stage}"] .stage-body`);
        if (!stageBody) return;
        
        const fragment = document.createDocumentFragment();
        deals.forEach((deal, index) => {
            const cardElement = document.createElement('div');
            cardElement.outerHTML = this.renderDealCard(deal, this.getStageDeals(stage).length + index);
            fragment.appendChild(cardElement);
        });
        
        stageBody.appendChild(fragment);
    }
    
    /**
     * Utility: Truncate text
     */
    truncateText(text, maxLength) {
        if (!text || text.length <= maxLength) return text || '';
        return text.substring(0, maxLength) + '...';
    }
    
    /**
     * Utility: Format number
     */
    formatNumber(num) {
        if (!num) return '0';
        return new Intl.NumberFormat().format(num);
    }
    
    /**
     * Get average load time
     */
    getAverageLoadTime() {
        if (this.metrics.loadTimes.length === 0) return 0;
        const sum = this.metrics.loadTimes.reduce((a, b) => a + b, 0);
        return Math.round(sum / this.metrics.loadTimes.length);
    }
    
    /**
     * Get average render time
     */
    getAverageRenderTime() {
        if (this.metrics.renderTimes.length === 0) return 0;
        const sum = this.metrics.renderTimes.reduce((a, b) => a + b, 0);
        return Math.round(sum / this.metrics.renderTimes.length);
    }
    
    /**
     * Destroy optimizer and cleanup resources
     */
    destroy() {
        if (this.webWorker) {
            this.webWorker.terminate();
            this.webWorker = null;
        }
        
        if (this.intersectionObserver) {
            this.intersectionObserver.disconnect();
        }
        
        this.cache.clear();
        this.loadingStates.clear();
        this.visibleItems.clear();
        this.observers.clear();
        
        // Remove debug overlay
        const overlay = document.getElementById('pipeline-debug-overlay');
        if (overlay) {
            overlay.remove();
        }
    }
}

// Global instance
window.PipelineOptimizer = null;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.PipelineOptimizer = new PipelinePerformanceOptimizer({
        debugMode: window.location.search.includes('debug=1')
    });
});