# A Practical Application: Building a Resilient Plugin Framework

This document reframes the concepts from the original PROJECT-BALANCE.md to align with the Phased Architecture & Delivery Guide. Instead of presenting a final, complex framework, it walks through how to build it phase-by-phase, ensuring that a simple, working version is always the foundation.

This approach demonstrates that you can achieve sophistication without sacrificing stability.

## Phase 1: The Resilient Foundation (Proof of Concept)

**Goal:** Ship a core feature that works 100% of the time, even if all JavaScript fails. This aligns with the principle of starting with the simplest thing that could possibly work.

We begin with Progressive Enhancement. The server renders the initial HTML, and JavaScript will only enhance it if and when it becomes available.

### 1. Server-Side Rendering (The "Always Works" Layer)

This PHP code is our baseline. It fetches data and renders HTML. This is our "minimal vertical slice" for the user-facing feature.

```php
// PHP side - always works, no JS required
class EventManagerRenderer {
    public function render_event_list($atts = []) {
        $events = $this->get_events($atts);
        
        // Server-rendered HTML is our reliable fallback
        $html = '<div class="nhk-event-list" data-nhk-events="' . esc_attr(json_encode($atts)) . '">';
        
        foreach ($events as $event) {
            $html .= $this->render_event_card($event);
        }
        
        $html .= '</div>';
        return $html;
    }
}
```

### 2. Core Client-Side Logic (The Simplest JS Layer)

This is the most basic version of our client-side manager. It has a single job: fetch data. It has no external dependencies and represents the simplest client-side state.

```javascript
// Core layer - always works, no external library dependencies
class EventManagerCore {
    constructor(element) {
        this.element = element;
        this.events = [];
        this.state = 'idle'; // Simple state: idle, loading, loaded, error
    }
    
    async loadEvents() {
        this.state = 'loading';
        try {
            // In a real scenario, we'd get the endpoint from the element's data attributes
            const response = await fetch('/wp-json/nhk-events/v1/events');
            this.events = await response.json();
            this.state = 'loaded';
            this.render();
        } catch (error) {
            this.state = 'error';
            console.error("Failed to load events:", error);
            // The server-rendered HTML remains, so the user sees no failure.
        }
    }

    render() {
        // Basic render logic to update the list, if needed.
        // For now, it might do nothing and just rely on the server render.
    }
}
```

**Exit Criteria for Phase 1:** The event list displays correctly on the front-end, rendered by PHP. The core JavaScript class can be instantiated and can fetch data without errors, but it doesn't add any major new functionality yet.

## Phase 2: Adding Guardrails (Architecture & Reliability)

**Goal:** Build a robust initialization system to manage our JavaScript, ensuring it loads reliably and fails gracefully. This is where we add architectural rigor and debugging tools, aligning with "Make it right."

### 1. A Robust Initialization System

We create a main framework class responsible for the entire boot sequence. It includes timeouts and error handling from day one, directly applying the "design for recovery" philosophy.

```javascript
// Main framework initialization
class NHKFramework {
    constructor() {
        this.modules = new Map();
        this.initialized = false;
        this.initPromise = null; // Prevent race conditions
    }

    async init() {
        if (this.initialized) return;
        if (this.initPromise) return this.initPromise;

        this.initPromise = this._initialize();
        await this.initPromise;
        this.initialized = true;
        return this;
    }

    async _initialize() {
        // Phase 1: Initialize core modules that have no dependencies
        document.querySelectorAll('.nhk-event-list').forEach(el => {
            this.modules.set('events', new EventManagerCore(el));
        });

        // Phase 2: Load external dependencies with timeouts
        await this.loadDependenciesWithTimeout();

        console.log('NHK Framework Core Initialized');
    }

    async loadDependenciesWithTimeout() {
        const timeout = (ms, promise) => new Promise((resolve, reject) => {
            const timer = setTimeout(() => {
                reject(new Error('Promise timed out'));
            }, ms);
            promise.then(
                value => { clearTimeout(timer); resolve(value); },
                error => { clearTimeout(timer); reject(error); }
            );
        });

        const dependencyPromises = [
            // We'll add actual dependencies in the next phase
            // For now, this structure is our guardrail.
        ];

        // Process all dependencies but don't let a single failure stop everything.
        await Promise.allSettled(dependencyPromises.map(p => timeout(5000, p).catch(err => {
            console.warn('A dependency failed to load:', err.message);
            return null; // Gracefully fail
        })));
    }
}

// Global initialization with an error boundary
window.addEventListener('DOMContentLoaded', () => {
    window.NHKFramework = new NHKFramework();
    window.NHKFramework.init().catch(console.error);
});
```

### 2. A Testing and Debugging Strategy

With the architecture in place, we add tests and debug tools to maintain it.

```javascript
// Integration test that verifies the core stack
describe('NHK Framework Initialization', () => {
    it('should work without any external dependencies', async () => {
        // Set up mock DOM element
        document.body.innerHTML = `<div class="nhk-event-list"></div>`;
        const framework = new NHKFramework();
        await framework.init();
        expect(framework.modules.has('events')).toBe(true);
        expect(framework.initialized).toBe(true);
    });
});

// A simple debug helper for the browser console
window.NHKDebug = {
    diagnose() {
        console.log({
            FrameworkInitialized: window.NHKFramework?.initialized,
            ModulesLoaded: window.NHKFramework ? Array.from(window.NHKFramework.modules.keys()) : 'N/A',
            Alpine: typeof window.Alpine,
            XState: typeof window.XState,
        });
    }
};
```

**Exit Criteria for Phase 2:** The framework reliably initializes the core EventManagerCore module. We have a test suite that proves the basic functionality works, and a debug helper to diagnose issues in production.

## Phase 3: Hardening & Enhancement (Adding Value)

**Goal:** Enhance the user experience by adding an external library (Alpine.js) for interactivity. This is done on top of our stable foundation. This is a "reversible decision" - if Alpine fails to load, the core functionality remains.

```javascript
// Enhancement layer - adds Alpine if available
class EventManagerAlpine extends EventManagerCore {
    enhance() {
        // Don't enhance if Alpine isn't present
        if (!window.Alpine) {
            console.log('Alpine not found. Sticking with core functionality.');
            return;
        }

        // Use Alpine to add interactivity to the server-rendered HTML
        Alpine.data('eventManager', () => ({
            state: this.state,
            events: this.events,
            
            init() {
                this.loadEvents().then(() => {
                    this.state = 'loaded';
                    this.events = this.events; // Trigger Alpine reactivity
                });
            },
            
            // Add interactive methods here, like filtering or sorting
            filterByCategory(category) {
                // ...
            }
        }));
        
        this.element.setAttribute('x-data', 'eventManager');
        Alpine.initTree(this.element);
    }
}

// We modify the NHKFramework._initialize() method:
// ... inside _initialize()
const eventManager = new EventManagerAlpine(el);
this.modules.set('events', eventManager);

// After loading dependencies, enhance the modules
eventManager.enhance(); 
// ...
```

**Exit Criteria for Phase 3:** The event list is now interactive. If Alpine.js fails to load or initialize, the system gracefully degrades to the Phase 1 static list with no errors.

## Phase 4: Scaling with Advanced Tooling

**Goal:** Tackle a new, highly complex requirement (e.g., a multi-step booking process) by introducing a more powerful tool (XState). This complexity is now justified by a specific, advanced need.

### 1. Advanced Layer for Complex State

We extend our class again, only adding the XState machine when the library is present and the module requires it.

```javascript
// Advanced layer - adds XState for a specific, complex workflow
class EventManagerAdvanced extends EventManagerAlpine {
    addStateMachine() {
        if (!window.XState) return;

        const bookingMachine = createMachine({ /* ... state machine config ... */ });
        this.bookingService = interpret(bookingMachine).start();
        
        // Connect the machine to the Alpine component for complex UI state
        this.bookingService.onTransition(state => {
            // Update Alpine data with the new state
        });
    }
}
```

### 2. Dependency Injection for Scalability

As we add more modules with different dependencies, a simple loader is no longer enough. We introduce a more formal Dependency Injection system to manage this scaled complexity.

```javascript
// This loader is introduced when the number of dependencies makes the simple
// loader in NHKFramework hard to maintain.
class DependencyLoader {
    constructor() {
        this.dependencies = new Map();
    }
    
    require(name, loader, fallback = null) {
        this.dependencies.set(name, { loader, fallback, instance: null });
        return this;
    }

    async resolveAll() {
        for (const [name, dep] of this.dependencies) {
            try {
                dep.instance = await dep.loader();
            } catch (error) {
                console.warn(`Failed to load ${name}, using fallback`, error);
                dep.instance = dep.fallback ? await dep.fallback() : null;
            }
        }
    }

    get(name) {
        return this.dependencies.get(name)?.instance;
    }
}
```

**Exit Criteria for Phase 4:** The plugin now supports both simple interactive lists and advanced, state-managed workflows. The dependency loader makes it possible to add future modules without refactoring the core initialization chain.

## Key Takeaways

This phased approach ensures that:

**A Working Product Exists at Every Stage:** The core functionality delivered in Phase 1 is never broken.

**Complexity is Justified:** We didn't add Alpine or XState because they are "modern," but because we had specific UX and state management problems to solve in Phases 3 and 4.

**The System is Resilient:** By building on a simple core and adding layers with graceful degradation, the application is robust against network failures, script blockers, and dependency conflicts.

**The Architecture Serves the Need:** The architecture evolves from a simple class to a more sophisticated framework as the project's requirements grow, not before.