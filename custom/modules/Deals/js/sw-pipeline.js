/**
 * Service Worker for Pipeline Module Caching
 * 
 * Implements intelligent caching strategies for pipeline assets:
 * - Cache-first for static assets (CSS, JS, images)
 * - Network-first for dynamic data (AJAX requests)
 * - Stale-while-revalidate for moderate frequency updates
 */

const CACHE_NAME = 'pipeline-cache-v1';
const DATA_CACHE_NAME = 'pipeline-data-cache-v1';

// Assets to cache on install
const STATIC_CACHE_URLS = [
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
    'custom/modules/Deals/js/pipeline.js',
    'custom/modules/Deals/js/asset-loader.js'
];

// URLs that should use network-first strategy
const NETWORK_FIRST_URLS = [
    '/index.php?module=Deals&action=Pipeline',
    '/index.php?module=Deals&action=updatePipelineStage',
    '/index.php?module=Deals&action=toggleFocus',
    '/index.php?module=Deals&action=getPipelineDeals'
];

// URLs that should use stale-while-revalidate strategy  
const STALE_WHILE_REVALIDATE_URLS = [
    '/index.php?module=Deals&action=stakeholder_bulk'
];

/**
 * Install event - cache static assets
 */
self.addEventListener('install', (event) => {
    console.log('Pipeline Service Worker installing');
    
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('Pipeline Service Worker caching static assets');
                return cache.addAll(STATIC_CACHE_URLS.map(url => {
                    // Add version parameter to bypass browser cache during install
                    return url + '?sw_install=' + Date.now();
                }));
            })
            .then(() => {
                // Force activate immediately
                return self.skipWaiting();
            })
            .catch((error) => {
                console.error('Pipeline Service Worker install failed:', error);
            })
    );
});

/**
 * Activate event - cleanup old caches
 */
self.addEventListener('activate', (event) => {
    console.log('Pipeline Service Worker activating');
    
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    // Delete old caches
                    if (cacheName.startsWith('pipeline-') && cacheName !== CACHE_NAME && cacheName !== DATA_CACHE_NAME) {
                        console.log('Pipeline Service Worker deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => {
            // Take control of all clients immediately
            return self.clients.claim();
        })
    );
});

/**
 * Fetch event - implement caching strategies
 */
self.addEventListener('fetch', (event) => {
    const requestUrl = new URL(event.request.url);
    const requestPath = requestUrl.pathname + requestUrl.search;
    
    // Only handle GET requests
    if (event.request.method !== 'GET') {
        return;
    }
    
    // Only handle requests from the same origin
    if (requestUrl.origin !== location.origin) {
        return;
    }
    
    // Handle pipeline-specific requests
    if (requestPath.includes('/custom/modules/Deals/')) {
        event.respondWith(handleStaticAssetRequest(event.request));
        return;
    }
    
    // Handle API requests
    if (isNetworkFirstUrl(requestPath)) {
        event.respondWith(handleNetworkFirstRequest(event.request));
        return;
    }
    
    if (isStaleWhileRevalidateUrl(requestPath)) {
        event.respondWith(handleStaleWhileRevalidateRequest(event.request));
        return;
    }
});

/**
 * Handle static asset requests (cache-first strategy)
 */
async function handleStaticAssetRequest(request) {
    try {
        // Try cache first
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // If not in cache, fetch from network
        const networkResponse = await fetch(request);
        
        // Cache successful responses
        if (networkResponse.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
        
    } catch (error) {
        console.error('Pipeline Service Worker static asset error:', error);
        
        // Return cached version if available
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return offline fallback
        return new Response('Offline - Asset not available', {
            status: 503,
            statusText: 'Service Unavailable'
        });
    }
}

/**
 * Handle network-first requests (dynamic data)
 */
async function handleNetworkFirstRequest(request) {
    try {
        // Try network first
        const networkResponse = await fetch(request);
        
        // Cache successful responses
        if (networkResponse.ok) {
            const cache = await caches.open(DATA_CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
        
    } catch (error) {
        console.error('Pipeline Service Worker network-first error:', error);
        
        // Fallback to cache
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return offline fallback
        return new Response(JSON.stringify({
            error: 'Offline',
            message: 'Network unavailable and no cached data'
        }), {
            status: 503,
            statusText: 'Service Unavailable',
            headers: {
                'Content-Type': 'application/json'
            }
        });
    }
}

/**
 * Handle stale-while-revalidate requests
 */
async function handleStaleWhileRevalidateRequest(request) {
    const cache = await caches.open(DATA_CACHE_NAME);
    
    // Get cached response
    const cachedResponse = await cache.match(request);
    
    // Start fetch in background
    const fetchPromise = fetch(request).then((networkResponse) => {
        if (networkResponse.ok) {
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    }).catch((error) => {
        console.error('Pipeline Service Worker stale-while-revalidate background fetch error:', error);
    });
    
    // Return cached response immediately, or wait for network if no cache
    return cachedResponse || fetchPromise;
}

/**
 * Check if URL should use network-first strategy
 */
function isNetworkFirstUrl(url) {
    return NETWORK_FIRST_URLS.some(pattern => url.includes(pattern));
}

/**
 * Check if URL should use stale-while-revalidate strategy
 */
function isStaleWhileRevalidateUrl(url) {
    return STALE_WHILE_REVALIDATE_URLS.some(pattern => url.includes(pattern));
}

/**
 * Handle messages from main thread
 */
self.addEventListener('message', (event) => {
    const { command, version } = event.data;
    
    switch (command) {
        case 'CLEAR_CACHE':
            handleClearCache(version);
            break;
            
        case 'UPDATE_CACHE':
            handleUpdateCache();
            break;
            
        case 'GET_CACHE_STATUS':
            handleGetCacheStatus(event);
            break;
            
        default:
            console.warn('Pipeline Service Worker unknown command:', command);
    }
});

/**
 * Clear cache (called when assets are updated)
 */
async function handleClearCache(version) {
    try {
        console.log('Pipeline Service Worker clearing cache for version:', version);
        
        const cacheNames = await caches.keys();
        const deletePromises = cacheNames
            .filter(name => name.startsWith('pipeline-'))
            .map(name => caches.delete(name));
            
        await Promise.all(deletePromises);
        
        console.log('Pipeline Service Worker cache cleared');
        
        // Notify all clients to reload
        const clients = await self.clients.matchAll();
        clients.forEach(client => {
            client.postMessage({
                type: 'CACHE_CLEARED',
                version: version
            });
        });
        
    } catch (error) {
        console.error('Pipeline Service Worker clear cache error:', error);
    }
}

/**
 * Update cache with new assets
 */
async function handleUpdateCache() {
    try {
        console.log('Pipeline Service Worker updating cache');
        
        const cache = await caches.open(CACHE_NAME);
        await cache.addAll(STATIC_CACHE_URLS);
        
        console.log('Pipeline Service Worker cache updated');
        
    } catch (error) {
        console.error('Pipeline Service Worker update cache error:', error);
    }
}

/**
 * Get cache status
 */
async function handleGetCacheStatus(event) {
    try {
        const cache = await caches.open(CACHE_NAME);
        const dataCache = await caches.open(DATA_CACHE_NAME);
        
        const staticKeys = await cache.keys();
        const dataKeys = await dataCache.keys();
        
        const status = {
            staticCacheSize: staticKeys.length,
            dataCacheSize: dataKeys.length,
            cacheNames: [CACHE_NAME, DATA_CACHE_NAME]
        };
        
        event.ports[0].postMessage(status);
        
    } catch (error) {
        console.error('Pipeline Service Worker get cache status error:', error);
        event.ports[0].postMessage({ error: error.message });
    }
}

/**
 * Background sync for offline actions
 */
self.addEventListener('sync', (event) => {
    if (event.tag === 'pipeline-sync') {
        event.waitUntil(handleBackgroundSync());
    }
});

/**
 * Handle background sync
 */
async function handleBackgroundSync() {
    try {
        console.log('Pipeline Service Worker background sync triggered');
        
        // Get offline actions from IndexedDB or cache
        const offlineActions = await getOfflineActions();
        
        for (const action of offlineActions) {
            try {
                await fetch(action.url, action.options);
                await removeOfflineAction(action.id);
                console.log('Pipeline Service Worker synced offline action:', action.id);
            } catch (error) {
                console.error('Pipeline Service Worker sync failed for action:', action.id, error);
            }
        }
        
    } catch (error) {
        console.error('Pipeline Service Worker background sync error:', error);
    }
}

/**
 * Get offline actions (placeholder - implement with IndexedDB)
 */
async function getOfflineActions() {
    // This would typically use IndexedDB to store offline actions
    // For now, return empty array
    return [];
}

/**
 * Remove offline action (placeholder - implement with IndexedDB)
 */
async function removeOfflineAction(actionId) {
    // This would typically remove the action from IndexedDB
    console.log('Remove offline action:', actionId);
}

/**
 * Push event for notifications
 */
self.addEventListener('push', (event) => {
    console.log('Pipeline Service Worker push received');
    
    let data = {};
    if (event.data) {
        try {
            data = event.data.json();
        } catch (error) {
            data = { title: 'Pipeline Update', body: event.data.text() };
        }
    }
    
    const options = {
        title: data.title || 'Pipeline Update',
        body: data.body || 'The pipeline has been updated',
        icon: 'custom/modules/Deals/icons/icon_Deals_32.png',
        badge: 'custom/modules/Deals/icons/icon_Deals_32.png',
        data: data,
        actions: [
            {
                action: 'view',
                title: 'View Pipeline'
            },
            {
                action: 'dismiss',
                title: 'Dismiss'
            }
        ]
    };
    
    event.waitUntil(
        self.registration.showNotification(options.title, options)
    );
});

/**
 * Notification click handler
 */
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    
    if (event.action === 'view') {
        event.waitUntil(
            clients.openWindow('index.php?module=Deals&action=Pipeline')
        );
    }
});

console.log('Pipeline Service Worker loaded');