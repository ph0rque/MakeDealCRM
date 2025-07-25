/**
 * Optimized Asset Loader for Pipeline Module
 * 
 * Implements intelligent asset loading strategies:
 * - Critical CSS inlining
 * - JavaScript chunking and lazy loading
 * - Resource hints for better performance
 * - Service worker integration for caching
 */

class PipelineAssetLoader {
    constructor(options = {}) {
        this.config = {
            // Performance thresholds
            criticalThreshold: 1500, // ms for critical resources
            lazyThreshold: 100, // px from viewport for lazy loading
            
            // Asset versioning
            enableVersioning: true,
            versionKey: 'deals_asset_v',
            
            // Caching configuration
            enableServiceWorker: true,
            cacheFirst: ['css', 'fonts', 'images'],
            networkFirst: ['js', 'html'],
            
            // Loading strategy
            loadStrategy: 'progressive', // 'progressive', 'aggressive', 'conservative'
            
            // Debug mode
            debug: false,
            
            ...options
        };
        
        this.loadedAssets = new Set();
        this.criticalAssets = new Map();
        this.performanceEntries = [];
        
        this.init();
    }
    
    /**
     * Initialize the asset loader
     */
    init() {
        // Check if we're in a modern browser
        this.isModernBrowser = this.checkModernBrowser();
        
        // Set up performance monitoring
        this.setupPerformanceMonitoring();
        
        // Initialize service worker if supported
        if (this.config.enableServiceWorker && 'serviceWorker' in navigator) {
            this.initServiceWorker();
        }
        
        // Load critical assets immediately
        this.loadCriticalAssets();
        
        // Set up lazy loading for non-critical assets
        this.setupLazyLoading();
        
        if (this.config.debug) {
            this.enableDebugMode();
        }
    }
    
    /**
     * Check if browser supports modern features
     */
    checkModernBrowser() {
        return (
            'fetch' in window &&
            'Promise' in window &&
            'IntersectionObserver' in window &&
            'addEventListener' in window
        );
    }
    
    /**
     * Setup performance monitoring
     */
    setupPerformanceMonitoring() {
        if (typeof PerformanceObserver !== 'undefined') {
            // Monitor resource loading
            const resourceObserver = new PerformanceObserver((list) => {
                for (const entry of list.getEntries()) {
                    this.performanceEntries.push(entry);
                    this.analyzeResourcePerformance(entry);
                }
            });
            
            try {
                resourceObserver.observe({ entryTypes: ['resource', 'navigation'] });
            } catch (e) {
                console.warn('Performance monitoring not fully supported');
            }
        }
    }
    
    /**
     * Analyze resource performance and optimize loading
     */
    analyzeResourcePerformance(entry) {
        // Identify slow loading resources
        if (entry.duration > this.config.criticalThreshold) {
            console.warn(`Slow resource detected: ${entry.name} (${Math.round(entry.duration)}ms)`);
            
            // Mark for preloading in future sessions
            this.markForPreloading(entry.name);
        }
        
        // Track asset loading patterns
        if (entry.name.includes('/custom/modules/Deals/')) {
            this.optimizeAssetLoading(entry);
        }
    }
    
    /**
     * Mark resource for preloading in future sessions
     */
    markForPreloading(resourceUrl) {
        try {
            const preloadList = JSON.parse(localStorage.getItem('pipeline_preload') || '[]');
            if (!preloadList.includes(resourceUrl)) {
                preloadList.push(resourceUrl);
                localStorage.setItem('pipeline_preload', JSON.stringify(preloadList.slice(-20))); // Keep last 20
            }
        } catch (e) {
            // Ignore localStorage errors
        }
    }
    
    /**
     * Optimize asset loading based on performance data
     */
    optimizeAssetLoading(entry) {
        // Implement adaptive loading based on connection speed
        if (navigator.connection) {
            const connection = navigator.connection;
            const effectiveType = connection.effectiveType;
            
            // Adjust loading strategy based on connection
            if (effectiveType === 'slow-2g' || effectiveType === '2g') {
                this.config.loadStrategy = 'conservative';
            } else if (effectiveType === '4g') {
                this.config.loadStrategy = 'aggressive';
            }
        }
    }
    
    /**
     * Initialize service worker for caching
     */
    async initServiceWorker() {
        try {
            // Register service worker if not already registered
            const registration = await navigator.serviceWorker.register(
                'custom/modules/Deals/js/sw-pipeline.js',
                { scope: '/custom/modules/Deals/' }
            );
            
            console.log('Pipeline service worker registered:', registration.scope);
            
            // Listen for service worker updates
            registration.addEventListener('updatefound', () => {
                const newWorker = registration.installing;
                newWorker.addEventListener('statechange', () => {
                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                        // New version available, prompt user to refresh
                        this.notifyServiceWorkerUpdate();
                    }
                });
            });
            
        } catch (error) {
            console.warn('Service worker registration failed:', error);
        }
    }
    
    /**
     * Notify user of service worker update
     */
    notifyServiceWorkerUpdate() {
        // Use SuiteCRM notification system if available
        if (typeof SUGAR !== 'undefined' && SUGAR.App && SUGAR.App.alert) {
            SUGAR.App.alert.show('sw-update', {
                level: 'info',
                messages: 'A new version of the pipeline is available. Please refresh the page.',
                autoClose: false
            });
        }
    }
    
    /**
     * Load critical assets immediately
     */
    loadCriticalAssets() {
        const criticalAssets = [
            'custom/modules/Deals/css/theme-integration.css',
            'custom/modules/Deals/css/pipeline.css',
            'custom/modules/Deals/js/pipeline.js'
        ];
        
        // Add resource hints for critical assets
        this.addResourceHints(criticalAssets);
        
        // Load critical CSS inline if small enough
        this.inlineCriticalCSS();
        
        // Preload critical JavaScript
        criticalAssets.forEach(asset => {
            if (asset.endsWith('.js')) {
                this.preloadScript(asset, true);
            }
        });
    }
    
    /**
     * Add resource hints for better performance
     */
    addResourceHints(assets) {
        const head = document.head;
        
        assets.forEach(asset => {
            // Add preload hint
            const preloadLink = document.createElement('link');
            preloadLink.rel = 'preload';
            preloadLink.href = asset + this.getVersionQuery();
            preloadLink.as = asset.endsWith('.css') ? 'style' : 'script';
            head.appendChild(preloadLink);
            
            // Add prefetch for non-critical assets
            if (!this.isCriticalAsset(asset)) {
                const prefetchLink = document.createElement('link');
                prefetchLink.rel = 'prefetch';
                prefetchLink.href = asset + this.getVersionQuery();
                head.appendChild(prefetchLink);
            }
        });
    }
    
    /**
     * Check if asset is critical
     */
    isCriticalAsset(asset) {
        const criticalPatterns = [
            'theme-integration.css',
            'pipeline.css',
            'pipeline.js'
        ];
        
        return criticalPatterns.some(pattern => asset.includes(pattern));
    }
    
    /**
     * Inline critical CSS for faster rendering
     */
    async inlineCriticalCSS() {
        const criticalCSS = 'custom/modules/Deals/css/theme-integration.css';
        
        try {
            const response = await fetch(criticalCSS + this.getVersionQuery());
            if (response.ok) {
                const css = await response.text();
                
                // Only inline if CSS is small enough (< 10KB)
                if (css.length < 10240) {
                    const style = document.createElement('style');
                    style.textContent = css;
                    style.setAttribute('data-critical', 'true');
                    document.head.appendChild(style);
                    
                    this.loadedAssets.add(criticalCSS);
                    return true;
                }
            }
        } catch (error) {
            console.warn('Failed to inline critical CSS:', error);
        }
        
        return false;
    }
    
    /**
     * Preload script with priority
     */
    preloadScript(src, critical = false) {
        return new Promise((resolve, reject) => {
            if (this.loadedAssets.has(src)) {
                resolve();
                return;
            }
            
            const script = document.createElement('script');
            script.src = src + this.getVersionQuery();
            script.async = !critical;
            script.defer = !critical;
            
            script.onload = () => {
                this.loadedAssets.add(src);
                resolve();
            };
            
            script.onerror = () => {
                reject(new Error(`Failed to load script: ${src}`));
            };
            
            document.head.appendChild(script);
        });
    }
    
    /**
     * Setup lazy loading for non-critical assets
     */
    setupLazyLoading() {
        if (!this.isModernBrowser) {
            // Fallback for older browsers
            this.loadAllAssetsImmediately();
            return;
        }
        
        // Use Intersection Observer for lazy loading
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.loadLazyAsset(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, {
            rootMargin: `${this.config.lazyThreshold}px`
        });
        
        // Observe elements that need lazy loading
        document.querySelectorAll('[data-lazy-src]').forEach(el => {
            observer.observe(el);
        });
    }
    
    /**
     * Load lazy asset
     */
    async loadLazyAsset(element) {
        const src = element.dataset.lazySrc;
        if (!src || this.loadedAssets.has(src)) return;
        
        try {
            if (src.endsWith('.css')) {
                await this.loadStylesheet(src);
            } else if (src.endsWith('.js')) {
                await this.preloadScript(src);
            }
            
            element.removeAttribute('data-lazy-src');
        } catch (error) {
            console.warn(`Failed to load lazy asset: ${src}`, error);
        }
    }
    
    /**
     * Load stylesheet dynamically
     */
    loadStylesheet(href) {
        return new Promise((resolve, reject) => {
            if (this.loadedAssets.has(href)) {
                resolve();
                return;
            }
            
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href + this.getVersionQuery();
            
            link.onload = () => {
                this.loadedAssets.add(href);
                resolve();
            };
            
            link.onerror = () => {
                reject(new Error(`Failed to load stylesheet: ${href}`));
            };
            
            document.head.appendChild(link);
        });
    }
    
    /**
     * Fallback for older browsers - load all assets immediately
     */
    loadAllAssetsImmediately() {
        const allAssets = [
            'custom/modules/Deals/css/theme-integration.css',
            'custom/modules/Deals/css/pipeline.css',
            'custom/modules/Deals/css/pipeline-focus.css',
            'custom/modules/Deals/css/wip-limits.css',
            'custom/modules/Deals/css/progress-indicators.css',
            'custom/modules/Deals/css/stakeholder-badges.css',
            'custom/modules/Deals/js/performance-optimizer.js',
            'custom/modules/Deals/js/state-manager.js',
            'custom/modules/Deals/js/pipeline-state-integration.js',
            'custom/modules/Deals/js/wip-limit-manager.js',
            'custom/modules/Deals/js/progress-indicators.js',
            'custom/modules/Deals/js/stakeholder-integration.js',
            'custom/modules/Deals/js/pipeline.js'
        ];
        
        allAssets.forEach(async (asset) => {
            try {
                if (asset.endsWith('.css')) {
                    await this.loadStylesheet(asset);
                } else if (asset.endsWith('.js')) {
                    await this.preloadScript(asset);
                }
            } catch (error) {
                console.warn(`Failed to load asset: ${asset}`, error);
            }
        });
    }
    
    /**
     * Get version query string for cache busting
     */
    getVersionQuery() {
        if (!this.config.enableVersioning) return '';
        
        const version = localStorage.getItem(this.config.versionKey) || Date.now();
        return `?v=${version}`;
    }
    
    /**
     * Update asset version (call this when assets are updated)
     */
    updateVersion() {
        const newVersion = Date.now();
        localStorage.setItem(this.config.versionKey, newVersion);
        
        // Clear old cached assets if service worker is available
        if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({
                command: 'CLEAR_CACHE',
                version: newVersion
            });
        }
    }
    
    /**
     * Enable debug mode
     */
    enableDebugMode() {
        console.log('Pipeline Asset Loader - Debug Mode Enabled');
        
        // Create debug panel
        this.createDebugPanel();
        
        // Log performance metrics
        setInterval(() => {
            console.log('Asset Loading Metrics:', {
                loadedAssets: this.loadedAssets.size,
                performanceEntries: this.performanceEntries.length,
                loadStrategy: this.config.loadStrategy,
                isModernBrowser: this.isModernBrowser
            });
        }, 10000);
    }
    
    /**
     * Create debug panel
     */
    createDebugPanel() {
        const panel = document.createElement('div');
        panel.id = 'asset-loader-debug';
        panel.style.cssText = `
            position: fixed;
            top: 50px;
            right: 10px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 10px;
            font-family: monospace;
            font-size: 12px;
            z-index: 9998;
            border-radius: 4px;
            min-width: 250px;
            max-height: 300px;
            overflow-y: auto;
        `;
        
        document.body.appendChild(panel);
        
        // Update panel every second
        setInterval(() => {
            const avgLoadTime = this.performanceEntries.length > 0 
                ? this.performanceEntries.reduce((sum, entry) => sum + entry.duration, 0) / this.performanceEntries.length
                : 0;
                
            panel.innerHTML = `
                <div><strong>Asset Loader Debug</strong></div>
                <div>Loaded Assets: ${this.loadedAssets.size}</div>
                <div>Performance Entries: ${this.performanceEntries.length}</div>
                <div>Load Strategy: ${this.config.loadStrategy}</div>
                <div>Modern Browser: ${this.isModernBrowser}</div>
                <div>Avg Load Time: ${Math.round(avgLoadTime)}ms</div>
                <div>Service Worker: ${navigator.serviceWorker ? 'Available' : 'Not Available'}</div>
                <hr>
                <div style="font-size: 10px;">
                    ${Array.from(this.loadedAssets).map(asset => 
                        `<div>✓ ${asset.split('/').pop()}</div>`
                    ).join('')}
                </div>
            `;
        }, 1000);
    }
    
    /**
     * Get performance metrics
     */
    getMetrics() {
        return {
            loadedAssets: Array.from(this.loadedAssets),
            performanceEntries: this.performanceEntries,
            config: this.config,
            averageLoadTime: this.performanceEntries.length > 0 
                ? this.performanceEntries.reduce((sum, entry) => sum + entry.duration, 0) / this.performanceEntries.length
                : 0
        };
    }
    
    /**
     * Cleanup and destroy the loader
     */
    destroy() {
        // Remove debug panel
        const debugPanel = document.getElementById('asset-loader-debug');
        if (debugPanel) {
            debugPanel.remove();
        }
        
        // Clear references
        this.loadedAssets.clear();
        this.criticalAssets.clear();
        this.performanceEntries.length = 0;
    }
}

// Global instance
window.PipelineAssetLoader = null;

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.PipelineAssetLoader = new PipelineAssetLoader({
        debug: window.location.search.includes('asset_debug=1')
    });
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PipelineAssetLoader;
}