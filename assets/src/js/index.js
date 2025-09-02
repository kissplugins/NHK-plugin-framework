/**
 * NHK Event Manager - Main JavaScript Entry Point
 * 
 * This file initializes Alpine.js and XState integration for the NHK Event Manager plugin.
 * It provides modern, reactive UI components for event management.
 */

import Alpine from 'alpinejs';
import { interpret } from 'xstate';
import appReadyMachine from './machines/appReadyMachine.js';
import { runCapabilityChecks } from './loader/capabilityLoader.js';

// Import our custom components and machines
import { eventListMachine } from './machines/eventListMachine.js';
import { eventFormMachine } from './machines/eventFormMachine.js';
import { eventStatusMachine } from './machines/eventStatusMachine.js';
import ApiClient from './api/client.js';

// Import component definitions
import './components/eventList.js';
import './components/eventForm.js';
import './components/eventStatus.js';
import './components/eventFilters.js';
import './components/debugPanel.js';

// Initialize Alpine.js
window.Alpine = Alpine;

// Global API client instance
window.nhkEventApi = new ApiClient();

// Global state machines registry
window.nhkStateMachines = new Map();

/**
 * Initialize the NHK Event Manager frontend
 */
function initNHKEventManager() {
    console.log('🚀 Initializing NHK Event Manager frontend...');

    // Track initialization start time
    const initStartTime = performance.now();

    // Check if we have the necessary WordPress data
    if (typeof window.nhkEventManager === 'undefined') {
        console.warn('⚠️ NHK Event Manager data not found. Some features may not work.');
        // Still continue initialization for debugging
    }

    // Set up global error handling
    setupErrorHandling();

    // Initialize focus management for accessibility
    setupFocusManagement();

    // Initialize keyboard shortcuts
    setupKeyboardShortcuts();

    // Test module imports
    testModuleImports();

    // Start Alpine.js
    Alpine.start();

    // Initialize minimal readiness FSM and react to loader signals
    const appService = window.createStateMachine(appReadyMachine);
    appService.start();

    document.addEventListener('nhk:deps:checking', () => appService.send({ type: 'DEPS_CHECKING' }));
    document.addEventListener('nhk:deps:ready', () => appService.send({ type: 'DEPS_READY' }));
    document.addEventListener('nhk:deps:degraded', () => appService.send({ type: 'DEPS_DEGRADED' }));
    document.addEventListener('nhk:api:available', () => appService.send({ type: 'API_AVAILABLE' }));
    document.addEventListener('nhk:api:unavailable', () => appService.send({ type: 'API_UNAVAILABLE' }));

    // Kick off capability checks
    runCapabilityChecks();

    // Track initialization completion
    const initTime = performance.now() - initStartTime;
    console.log(`✅ NHK Event Manager frontend initialized successfully in ${initTime.toFixed(2)}ms`);

    // Dispatch initialization complete event
    document.dispatchEvent(new CustomEvent('nhk:initialized', {
        detail: { initTime, timestamp: new Date().toISOString() }
    }));
}

/**
 * Test module imports and availability
 */
function testModuleImports() {
    console.group('🔍 Testing Module Imports');

    // Test XState
    try {
        if (typeof interpret === 'function') {
            console.log('✅ XState interpret function available');
        } else {
            console.error('❌ XState interpret function not available');
        }
    } catch (error) {
        console.error('❌ XState import error:', error);
    }

    // Test state machines
    try {
        console.log('🔍 Testing state machine imports...');
        console.log('eventListMachine:', typeof eventListMachine);
        console.log('eventFormMachine:', typeof eventFormMachine);
        console.log('eventStatusMachine:', typeof eventStatusMachine);
    } catch (error) {
        console.error('❌ State machine import error:', error);
    }

    // Test API client
    try {
        console.log('ApiClient:', typeof ApiClient);
        console.log('window.nhkEventApi:', typeof window.nhkEventApi);
    } catch (error) {
        console.error('❌ API client error:', error);
    }

    // Test Alpine.js
    console.log('Alpine.js:', typeof Alpine);
    console.log('Alpine version:', Alpine.version || 'unknown');

    console.groupEnd();
}

/**
 * Set up global error handling
 */
function setupErrorHandling() {
    window.addEventListener('error', (event) => {
        console.error('🚨 JavaScript Error:', event.error);

        // Dispatch error event for debug panel
        document.dispatchEvent(new CustomEvent('nhk:error', {
            detail: {
                type: 'javascript',
                message: event.message,
                error: event.error,
                filename: event.filename,
                lineno: event.lineno,
                colno: event.colno
            }
        }));

        // Send error to WordPress if debug mode is enabled
        if (window.nhkEventManager?.isDebug) {
            console.log('Debug mode: Error logged locally');
        }
    });

    window.addEventListener('unhandledrejection', (event) => {
        console.error('🚨 Unhandled Promise Rejection:', event.reason);

        // Dispatch error event for debug panel
        document.dispatchEvent(new CustomEvent('nhk:error', {
            detail: {
                type: 'promise',
                message: 'Unhandled Promise Rejection',
                error: event.reason
            }
        }));

        // Prevent the default browser behavior
        event.preventDefault();
    });
}

/**
 * Set up focus management for accessibility
 */
function setupFocusManagement() {
    // Add focus-visible polyfill behavior
    document.addEventListener('keydown', () => {
        document.body.classList.add('js-focus-visible');
    });
    
    document.addEventListener('mousedown', () => {
        document.body.classList.remove('js-focus-visible');
    });
    
    // Skip links for accessibility
    const skipLink = document.createElement('a');
    skipLink.href = '#main-content';
    skipLink.textContent = 'Skip to main content';
    skipLink.className = 'sr-only focus:not-sr-only focus:absolute focus:top-0 focus:left-0 bg-nhk-primary-600 text-white p-2 z-50';
    document.body.insertBefore(skipLink, document.body.firstChild);
}

/**
 * Set up keyboard shortcuts
 */
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', (event) => {
        // Escape key to close modals
        if (event.key === 'Escape') {
            // Dispatch custom event that components can listen to
            document.dispatchEvent(new CustomEvent('nhk:escape'));
        }
        
        // Ctrl/Cmd + K for search (if search component exists)
        if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
            event.preventDefault();
            document.dispatchEvent(new CustomEvent('nhk:search'));
        }
    });
}

/**
 * Utility function to create and manage state machines
 */
window.createStateMachine = function(machineConfig, context = {}) {
    // Create an actor from the machine (XState v5 compatible)
    const service = interpret(machineConfig);

    // Store in global registry for debugging
    const id = `machine_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
    window.nhkStateMachines.set(id, service);

    // In XState v5 there is no onStop; use subscribe complete to detect stop and cleanup
    const subscription = service.subscribe({
        complete: () => {
            window.nhkStateMachines.delete(id);
        }
    });

    // Expose a tiny cleanup helper in case we ever need to unsubscribe manually
    service.__nhkCleanup = () => subscription.unsubscribe?.();

    return service;
};

/**
 * Utility function to format dates consistently
 */
window.formatEventDate = function(dateString, options = {}) {
    if (!dateString) return '';
    
    const defaultOptions = {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        ...options
    };
    
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString(
            window.nhkEventManager?.locale || 'en-US',
            defaultOptions
        );
    } catch (error) {
        console.error('Error formatting date:', error);
        return dateString;
    }
};

/**
 * Utility function to format event time
 */
window.formatEventTime = function(timeString, options = {}) {
    if (!timeString) return '';
    
    const defaultOptions = {
        hour: 'numeric',
        minute: '2-digit',
        ...options
    };
    
    try {
        const time = new Date(`2000-01-01T${timeString}`);
        return time.toLocaleTimeString(
            window.nhkEventManager?.locale || 'en-US',
            defaultOptions
        );
    } catch (error) {
        console.error('Error formatting time:', error);
        return timeString;
    }
};

/**
 * Utility function to debounce function calls
 */
window.debounce = function(func, wait, immediate) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            timeout = null;
            if (!immediate) func(...args);
        };
        const callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        if (callNow) func(...args);
    };
};

/**
 * Utility function to throttle function calls
 */
window.throttle = function(func, limit) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
};

/**
 * Utility function to show notifications
 */
window.showNotification = function(message, type = 'info', duration = 5000) {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type} fixed top-4 right-4 z-50 max-w-sm animate-slide-down`;
    notification.innerHTML = `
        <div class="flex items-start">
            <div class="flex-1">
                <p class="text-sm font-medium">${message}</p>
            </div>
            <button type="button" class="ml-3 flex-shrink-0" onclick="this.parentElement.parentElement.remove()">
                <span class="sr-only">Close</span>
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after duration
    if (duration > 0) {
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, duration);
    }
    
    return notification;
};

/**
 * Initialize when DOM is ready
 */
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNHKEventManager);
} else {
    initNHKEventManager();
}

// Export for potential external use
export {
    eventListMachine,
    eventFormMachine,
    eventStatusMachine,
    ApiClient
};
