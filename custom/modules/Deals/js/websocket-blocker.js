/**
 * WebSocket Blocker
 * Prevents all WebSocket connections in SuiteCRM environment
 * Since this is a PHP-based application, WebSockets are not supported
 */

(function() {
    'use strict';
    
    console.log('WebSocket blocker initializing...');
    
    // Store the original WebSocket constructor
    const OriginalWebSocket = window.WebSocket;
    
    // Override the WebSocket constructor
    window.WebSocket = function(url, protocols) {
        console.warn('WebSocket connection blocked:', url);
        console.warn('WebSocket is not supported in this SuiteCRM installation. Real-time features are disabled.');
        
        // Create a mock WebSocket object that does nothing
        const mockWebSocket = {
            url: url,
            readyState: 0, // CONNECTING
            bufferedAmount: 0,
            extensions: '',
            protocol: '',
            binaryType: 'blob',
            
            // Mock methods that do nothing
            send: function() {
                console.warn('WebSocket send blocked - WebSocket not available');
            },
            close: function() {
                this.readyState = 3; // CLOSED
                if (this.onclose) {
                    setTimeout(() => {
                        this.onclose({ type: 'close', target: this });
                    }, 0);
                }
            },
            
            // Event handlers
            onopen: null,
            onclose: null,
            onerror: null,
            onmessage: null,
            
            // Mock addEventListener
            addEventListener: function(type, listener) {
                // Do nothing
            },
            removeEventListener: function(type, listener) {
                // Do nothing
            }
        };
        
        // Immediately trigger error and close events
        setTimeout(() => {
            mockWebSocket.readyState = 3; // CLOSED
            if (mockWebSocket.onerror) {
                mockWebSocket.onerror({
                    type: 'error',
                    target: mockWebSocket,
                    message: 'WebSocket is not supported in this environment'
                });
            }
            if (mockWebSocket.onclose) {
                mockWebSocket.onclose({
                    type: 'close',
                    target: mockWebSocket,
                    code: 1006,
                    reason: 'WebSocket is not supported'
                });
            }
        }, 100);
        
        return mockWebSocket;
    };
    
    // Copy static properties
    Object.setPrototypeOf(window.WebSocket, OriginalWebSocket);
    window.WebSocket.CONNECTING = 0;
    window.WebSocket.OPEN = 1;
    window.WebSocket.CLOSING = 2;
    window.WebSocket.CLOSED = 3;
    
    console.log('WebSocket blocker active - all WebSocket connections will be prevented');
    
})();