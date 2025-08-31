<?php
/**
 * Debug Panel Template
 * 
 * Provides an on-screen debugging interface for development.
 * Only shown when WP_DEBUG is enabled.
 */

// Only show in debug mode
if (!defined('WP_DEBUG') || !WP_DEBUG) {
    return;
}
?>

<div x-data="debugPanel" x-show="isVisible" x-cloak class="nhk-debug-panel fixed top-4 right-4 w-96 bg-white border border-gray-300 rounded-lg shadow-xl z-[9999] font-mono text-xs">
    <!-- Header -->
    <div class="bg-gray-800 text-white px-3 py-2 rounded-t-lg flex items-center justify-between cursor-move" 
         @mousedown="isDragging = true" 
         @mousemove="if(isDragging) { /* handle drag */ }"
         @mouseup="isDragging = false">
        <div class="flex items-center space-x-2">
            <span class="text-green-400">●</span>
            <span class="font-semibold">NHK Debug Panel</span>
        </div>
        <div class="flex items-center space-x-2">
            <button @click="exportDebugData()" class="text-gray-300 hover:text-white" title="Export Debug Data">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z"/>
                </svg>
            </button>
            <button @click="toggle()" class="text-gray-300 hover:text-white" title="Close">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200">
        <nav class="flex space-x-1 px-2 py-1">
            <button @click="switchTab('modules')" 
                    :class="activeTab === 'modules' ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:text-gray-700'"
                    class="px-2 py-1 rounded text-xs font-medium">
                Modules
            </button>
            <button @click="switchTab('machines')" 
                    :class="activeTab === 'machines' ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:text-gray-700'"
                    class="px-2 py-1 rounded text-xs font-medium">
                FSM
            </button>
            <button @click="switchTab('api')" 
                    :class="activeTab === 'api' ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:text-gray-700'"
                    class="px-2 py-1 rounded text-xs font-medium">
                API
            </button>
            <button @click="switchTab('errors')" 
                    :class="activeTab === 'errors' ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:text-gray-700'"
                    class="px-2 py-1 rounded text-xs font-medium">
                Errors <span x-show="errors.length > 0" class="bg-red-500 text-white rounded-full px-1 ml-1" x-text="errors.length"></span>
            </button>
            <button @click="switchTab('performance')" 
                    :class="activeTab === 'performance' ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:text-gray-700'"
                    class="px-2 py-1 rounded text-xs font-medium">
                Perf
            </button>
        </nav>
    </div>

    <!-- Content -->
    <div class="p-3 max-h-96 overflow-y-auto">
        
        <!-- Modules Tab -->
        <div x-show="activeTab === 'modules'" class="space-y-2">
            <div class="flex justify-between items-center">
                <h3 class="font-semibold text-gray-700">Module Status</h3>
                <span class="text-gray-500" x-text="`${modules.size} modules`"></span>
            </div>
            <template x-for="[name, module] in modules" :key="name">
                <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                    <div class="flex items-center space-x-2">
                        <span :class="module.status === 'loaded' ? 'text-green-500' : 'text-red-500'">
                            <span x-show="module.status === 'loaded'">✓</span>
                            <span x-show="module.status === 'failed'">✗</span>
                            <span x-show="module.status === 'pending'">⏳</span>
                        </span>
                        <span class="font-medium" x-text="module.name"></span>
                    </div>
                    <div class="text-right">
                        <div class="text-gray-500" x-text="module.status"></div>
                        <div class="text-gray-400" x-show="module.size > 0" x-text="formatBytes(module.size)"></div>
                    </div>
                </div>
            </template>
        </div>

        <!-- State Machines Tab -->
        <div x-show="activeTab === 'machines'" class="space-y-2">
            <div class="flex justify-between items-center">
                <h3 class="font-semibold text-gray-700">State Machines</h3>
                <div class="flex items-center space-x-2">
                    <span class="text-gray-500" x-text="`${machines.size} machines`"></span>
                    <button @click="clear('machines')" class="text-red-500 hover:text-red-700 text-xs">Clear</button>
                </div>
            </div>
            <template x-for="[id, machine] in machines" :key="id">
                <div class="p-2 bg-gray-50 rounded">
                    <div class="flex justify-between items-center mb-1">
                        <span class="font-medium" x-text="id"></span>
                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs" x-text="machine.currentState"></span>
                    </div>
                    <div class="text-gray-600 text-xs">
                        <div>Events: <span x-text="machine.events.length"></span></div>
                        <div>Context keys: <span x-text="Object.keys(machine.context).length"></span></div>
                    </div>
                </div>
            </template>
            <div x-show="machines.size === 0" class="text-gray-500 text-center py-4">
                No state machines found
            </div>
        </div>

        <!-- API Tab -->
        <div x-show="activeTab === 'api'" class="space-y-2">
            <div class="flex justify-between items-center">
                <h3 class="font-semibold text-gray-700">API Calls</h3>
                <div class="flex items-center space-x-2">
                    <span class="text-gray-500" x-text="`${apiCalls.length} calls`"></span>
                    <button @click="clear('api')" class="text-red-500 hover:text-red-700 text-xs">Clear</button>
                </div>
            </div>
            <template x-for="call in apiCalls" :key="call.id">
                <div class="p-2 bg-gray-50 rounded">
                    <div class="flex justify-between items-center mb-1">
                        <div class="flex items-center space-x-2">
                            <span class="font-medium" x-text="call.method"></span>
                            <span class="text-gray-600 truncate" x-text="call.url"></span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span :class="call.status === 'success' ? 'text-green-600' : call.status === 'error' ? 'text-red-600' : 'text-yellow-600'" 
                                  x-text="call.status"></span>
                            <span class="text-gray-500" x-text="formatDuration(call.duration)"></span>
                        </div>
                    </div>
                </div>
            </template>
            <div x-show="apiCalls.length === 0" class="text-gray-500 text-center py-4">
                No API calls yet
            </div>
        </div>

        <!-- Errors Tab -->
        <div x-show="activeTab === 'errors'" class="space-y-2">
            <div class="flex justify-between items-center">
                <h3 class="font-semibold text-gray-700">JavaScript Errors</h3>
                <div class="flex items-center space-x-2">
                    <span class="text-gray-500" x-text="`${errors.length} errors`"></span>
                    <button @click="clear('errors')" class="text-red-500 hover:text-red-700 text-xs">Clear</button>
                </div>
            </div>
            <template x-for="error in errors" :key="error.timestamp">
                <div class="p-2 bg-red-50 border border-red-200 rounded">
                    <div class="font-medium text-red-800" x-text="error.message"></div>
                    <div class="text-red-600 text-xs mt-1" x-text="error.error"></div>
                    <div class="text-gray-500 text-xs mt-1" x-text="new Date(error.timestamp).toLocaleTimeString()"></div>
                </div>
            </template>
            <div x-show="errors.length === 0" class="text-green-600 text-center py-4">
                No errors detected ✓
            </div>
        </div>

        <!-- Performance Tab -->
        <div x-show="activeTab === 'performance'" class="space-y-2">
            <h3 class="font-semibold text-gray-700">Performance Metrics</h3>
            <div class="space-y-2">
                <div class="flex justify-between p-2 bg-gray-50 rounded">
                    <span>Load Time</span>
                    <span x-text="formatDuration(performance.loadTime)"></span>
                </div>
                <div class="flex justify-between p-2 bg-gray-50 rounded" x-show="performance.memoryUsage > 0">
                    <span>Memory Usage</span>
                    <span x-text="`${performance.memoryUsage} MB`"></span>
                </div>
                <div class="flex justify-between p-2 bg-gray-50 rounded">
                    <span>User Agent</span>
                    <span class="text-xs text-gray-600 truncate" x-text="navigator.userAgent.split(' ')[0]"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="bg-gray-50 px-3 py-2 rounded-b-lg border-t border-gray-200 text-xs text-gray-500">
        Press <kbd class="bg-gray-200 px-1 rounded">Ctrl+Shift+D</kbd> to toggle
    </div>
</div>

<!-- Debug Panel Toggle Button (always visible in debug mode) -->
<div x-data="debugPanelToggle" x-show="shouldShow" x-cloak>
    <button @click="togglePanel()"
            class="fixed bottom-4 right-4 bg-gray-800 text-white p-2 rounded-full shadow-lg z-[9998] hover:bg-gray-700"
            title="Open Debug Panel (Ctrl+Shift+D)">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
        </svg>
    </button>
</div>

<script>
// Debug panel toggle component
Alpine.data('debugPanelToggle', () => ({
    shouldShow: <?php echo (defined('WP_DEBUG') && WP_DEBUG) ? 'true' : 'false'; ?>,

    togglePanel() {
        // Find the debug panel and toggle it
        const debugPanel = document.querySelector('[x-data*="debugPanel"]');
        if (debugPanel && debugPanel._x_dataStack) {
            const panelData = debugPanel._x_dataStack[0];
            if (panelData && typeof panelData.toggle === 'function') {
                panelData.toggle();
            }
        }

        // Fallback: dispatch custom event
        document.dispatchEvent(new CustomEvent('toggle-debug'));
    }
}));
</script>

<style>
.nhk-debug-panel {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
}

.nhk-debug-panel kbd {
    font-family: inherit;
    font-size: 0.75rem;
}

.nhk-debug-panel [x-cloak] {
    display: none !important;
}
</style>
