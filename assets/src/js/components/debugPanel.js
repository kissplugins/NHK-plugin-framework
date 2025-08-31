/**
 * Debug Panel Component
 * 
 * Provides an on-screen debugging interface for monitoring:
 * - Module loading status
 * - XState machine states
 * - API calls and responses
 * - JavaScript errors
 * - Performance metrics
 */

import Alpine from 'alpinejs';

// Debug panel data and methods
Alpine.data('debugPanel', () => ({
    // Panel state
    isVisible: false,
    activeTab: 'modules',
    position: { x: 20, y: 20 },
    isDragging: false,
    
    // Debug data
    modules: new Map(),
    machines: new Map(),
    apiCalls: [],
    errors: [],
    performance: {
        loadTime: 0,
        memoryUsage: 0,
        renderTime: 0
    },
    
    // Initialize debug panel
    init() {
        console.log('🔧 Initializing Debug Panel...');

        this.setupModuleTracking();
        this.setupMachineTracking();
        this.setupApiTracking();
        this.setupErrorTracking();
        this.setupPerformanceTracking();
        this.setupKeyboardShortcuts();
        this.addDebugToggleButton();

        // Auto-show in debug mode
        if (window.nhkEventManager?.isDebug || window.location.search.includes('debug=1')) {
            this.isVisible = true;
            console.log('🔧 Debug Panel auto-shown (debug mode detected)');
        }

        // Listen for toggle events
        document.addEventListener('toggle-debug', () => {
            this.toggle();
        });

        // Global console commands are set up outside the component

        console.log('🔧 Debug Panel initialized successfully');
        console.log('💡 Use showDebugPanel() in console to open debug panel');

        // Test basic functionality
        this.runInitialTests();
    },
    
    // Module tracking
    setupModuleTracking() {
        const originalImport = window.import || (() => {});
        const self = this;
        
        // Track module loading
        this.trackModule('Alpine.js', Alpine ? 'loaded' : 'failed', Alpine);
        this.trackModule('XState', window.XState ? 'loaded' : 'failed', window.XState);
        this.trackModule('API Client', window.nhkEventApi ? 'loaded' : 'failed', window.nhkEventApi);
        
        // Track state machines
        if (window.nhkStateMachines) {
            this.trackModule('State Machines Registry', 'loaded', window.nhkStateMachines);
        }
        
        // Check for specific imports
        this.checkImports();
    },
    
    async checkImports() {
        const imports = [
            { name: 'eventListMachine', path: './machines/eventListMachine.js' },
            { name: 'eventFormMachine', path: './machines/eventFormMachine.js' },
            { name: 'eventStatusMachine', path: './machines/eventStatusMachine.js' },
            { name: 'ApiClient', path: './api/client.js' }
        ];
        
        for (const imp of imports) {
            try {
                const module = await import(imp.path);
                this.trackModule(imp.name, 'loaded', module);
            } catch (error) {
                this.trackModule(imp.name, 'failed', error);
                this.logError(`Failed to import ${imp.name}`, error);
            }
        }
    },
    
    trackModule(name, status, data = null) {
        this.modules.set(name, {
            name,
            status,
            data,
            timestamp: new Date().toISOString(),
            size: data ? this.getObjectSize(data) : 0
        });
    },
    
    // State machine tracking
    setupMachineTracking() {
        if (window.nhkStateMachines) {
            // Monitor machine registry changes
            const originalSet = window.nhkStateMachines.set.bind(window.nhkStateMachines);
            const self = this;
            
            window.nhkStateMachines.set = function(key, service) {
                const result = originalSet(key, service);
                self.trackMachine(key, service);
                return result;
            };
        }
    },
    
    trackMachine(id, service) {
        this.machines.set(id, {
            id,
            service,
            currentState: service.state?.value || 'unknown',
            context: service.state?.context || {},
            timestamp: new Date().toISOString(),
            events: []
        });
        
        // Subscribe to state changes
        service.subscribe((state) => {
            const machine = this.machines.get(id);
            if (machine) {
                machine.currentState = state.value;
                machine.context = state.context;
                machine.events.push({
                    type: 'state_change',
                    from: machine.currentState,
                    to: state.value,
                    timestamp: new Date().toISOString()
                });
                
                // Keep only last 10 events
                if (machine.events.length > 10) {
                    machine.events = machine.events.slice(-10);
                }
            }
        });
    },
    
    // API call tracking
    setupApiTracking() {
        if (window.nhkEventApi) {
            const api = window.nhkEventApi;
            const self = this;
            
            // Wrap API methods
            ['get', 'post', 'put', 'delete'].forEach(method => {
                if (api[method]) {
                    const original = api[method].bind(api);
                    api[method] = async function(...args) {
                        const startTime = performance.now();
                        const callId = `${method}_${Date.now()}`;
                        
                        self.trackApiCall(callId, method, args[0], 'pending');
                        
                        try {
                            const result = await original(...args);
                            const duration = performance.now() - startTime;
                            self.updateApiCall(callId, 'success', result, duration);
                            return result;
                        } catch (error) {
                            const duration = performance.now() - startTime;
                            self.updateApiCall(callId, 'error', error, duration);
                            throw error;
                        }
                    };
                }
            });
        }
    },
    
    trackApiCall(id, method, url, status, response = null, duration = 0) {
        this.apiCalls.unshift({
            id,
            method: method.toUpperCase(),
            url,
            status,
            response,
            duration,
            timestamp: new Date().toISOString()
        });
        
        // Keep only last 20 calls
        if (this.apiCalls.length > 20) {
            this.apiCalls = this.apiCalls.slice(0, 20);
        }
    },
    
    updateApiCall(id, status, response, duration) {
        const call = this.apiCalls.find(c => c.id === id);
        if (call) {
            call.status = status;
            call.response = response;
            call.duration = Math.round(duration);
        }
    },
    
    // Error tracking
    setupErrorTracking() {
        const self = this;
        
        window.addEventListener('error', (event) => {
            self.logError('JavaScript Error', event.error, {
                filename: event.filename,
                lineno: event.lineno,
                colno: event.colno
            });
        });
        
        window.addEventListener('unhandledrejection', (event) => {
            self.logError('Unhandled Promise Rejection', event.reason);
        });
    },
    
    logError(message, error, details = {}) {
        this.errors.unshift({
            message,
            error: error?.toString() || 'Unknown error',
            stack: error?.stack || '',
            details,
            timestamp: new Date().toISOString()
        });
        
        // Keep only last 10 errors
        if (this.errors.length > 10) {
            this.errors = this.errors.slice(0, 10);
        }
    },
    
    // Performance tracking
    setupPerformanceTracking() {
        this.performance.loadTime = performance.now();
        
        if (performance.memory) {
            this.performance.memoryUsage = Math.round(performance.memory.usedJSHeapSize / 1024 / 1024);
        }
        
        // Update performance metrics periodically
        setInterval(() => {
            if (performance.memory) {
                this.performance.memoryUsage = Math.round(performance.memory.usedJSHeapSize / 1024 / 1024);
            }
        }, 5000);
    },

    // Add visible debug toggle button
    addDebugToggleButton() {
        // Create floating debug button
        const button = document.createElement('button');
        button.innerHTML = '🐛';
        button.title = 'Toggle Debug Panel (Cmd+Shift+I)';
        button.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            background: #1e293b;
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            font-size: 20px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            transition: all 0.2s ease;
        `;

        button.addEventListener('click', () => this.toggle());
        button.addEventListener('mouseenter', () => {
            button.style.transform = 'scale(1.1)';
            button.style.background = '#334155';
        });
        button.addEventListener('mouseleave', () => {
            button.style.transform = 'scale(1)';
            button.style.background = '#1e293b';
        });

        document.body.appendChild(button);
    },

    // Keyboard shortcuts
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (event) => {
            // Cmd + Shift + I to toggle debug panel (Mac-friendly)
            if (event.metaKey && event.shiftKey && event.key === 'I') {
                event.preventDefault();
                this.toggle();
            }
            // Also support Ctrl + Shift + D for non-Mac
            if (event.ctrlKey && event.shiftKey && event.key === 'D') {
                event.preventDefault();
                this.toggle();
            }
        });
    },
    
    // Panel controls
    toggle() {
        this.isVisible = !this.isVisible;
    },
    
    switchTab(tab) {
        this.activeTab = tab;
    },
    
    clear(type) {
        switch (type) {
            case 'api':
                this.apiCalls = [];
                break;
            case 'errors':
                this.errors = [];
                break;
            case 'machines':
                this.machines.clear();
                break;
        }
    },
    
    // Utility methods
    getObjectSize(obj) {
        try {
            return JSON.stringify(obj).length;
        } catch {
            return 0;
        }
    },
    
    formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    },
    
    formatDuration(ms) {
        if (ms < 1000) return `${Math.round(ms)}ms`;
        return `${(ms / 1000).toFixed(2)}s`;
    },
    
    exportDebugData() {
        const data = {
            modules: Array.from(this.modules.entries()),
            machines: Array.from(this.machines.entries()),
            apiCalls: this.apiCalls,
            errors: this.errors,
            performance: this.performance,
            timestamp: new Date().toISOString(),
            userAgent: navigator.userAgent,
            url: window.location.href
        };

        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `nhk-debug-${Date.now()}.json`;
        a.click();
        URL.revokeObjectURL(url);
    },

    // Run initial tests
    runInitialTests() {
        console.group('🧪 Running Initial Debug Tests');

        // Test 1: Check if Alpine.js is available
        if (typeof Alpine !== 'undefined') {
            console.log('✅ Alpine.js is available');
            this.trackModule('Alpine.js', 'loaded', Alpine);
        } else {
            console.error('❌ Alpine.js not found');
            this.trackModule('Alpine.js', 'failed', 'Not found');
        }

        // Test 2: Check if XState is available
        try {
            const { createMachine } = window.XState || {};
            if (createMachine) {
                console.log('✅ XState is available');
                this.trackModule('XState', 'loaded', window.XState);
            } else {
                console.error('❌ XState not found');
                this.trackModule('XState', 'failed', 'Not found');
            }
        } catch (error) {
            console.error('❌ XState error:', error);
            this.trackModule('XState', 'failed', error);
        }

        // Test 3: Check WordPress data
        if (window.nhkEventManager) {
            console.log('✅ WordPress data available');
            this.trackModule('WordPress Data', 'loaded', window.nhkEventManager);
        } else {
            console.warn('⚠️ WordPress data not found');
            this.trackModule('WordPress Data', 'failed', 'Not found');
        }

        console.groupEnd();
    }
}));

// Set up global debug panel functions immediately (not waiting for component init)
window.showDebugPanel = () => {
    // Try to find the debug panel component and show it
    const debugElement = document.querySelector('[x-data*="debugPanel"]');
    if (debugElement && debugElement._x_dataStack) {
        const component = debugElement._x_dataStack[0];
        if (component && typeof component.show === 'function') {
            component.isVisible = true;
            console.log('🔧 Debug Panel opened via console command');
        } else {
            console.log('🔧 Debug Panel component not found, dispatching event');
            document.dispatchEvent(new CustomEvent('toggle-debug'));
        }
    } else {
        console.log('🔧 Debug Panel element not found, dispatching event');
        document.dispatchEvent(new CustomEvent('toggle-debug'));
    }
};

window.hideDebugPanel = () => {
    const debugElement = document.querySelector('[x-data*="debugPanel"]');
    if (debugElement && debugElement._x_dataStack) {
        const component = debugElement._x_dataStack[0];
        if (component) {
            component.isVisible = false;
            console.log('🔧 Debug Panel closed via console command');
        }
    }
};

window.toggleDebugPanel = () => {
    const debugElement = document.querySelector('[x-data*="debugPanel"]');
    if (debugElement && debugElement._x_dataStack) {
        const component = debugElement._x_dataStack[0];
        if (component && typeof component.toggle === 'function') {
            component.toggle();
        }
    } else {
        document.dispatchEvent(new CustomEvent('toggle-debug'));
    }
};

console.log('💡 Global debug functions available: showDebugPanel(), hideDebugPanel(), toggleDebugPanel()');
