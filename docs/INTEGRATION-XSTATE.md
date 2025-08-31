# XState Integration with NHK Framework

## 🔄 Overview

The NHK Framework leverages **XState** for sophisticated state management in complex UI components. XState provides predictable state machines and statecharts that eliminate common UI bugs and make complex application logic easier to understand, test, and maintain.

## ✅ What We Implemented

### 🎯 **State Machine Architecture**

XState serves as our primary state management solution for:
- **Complex UI Logic**: Multi-step forms, data loading, error handling
- **Business Rules**: Event status transitions, validation workflows
- **User Interactions**: Filtering, pagination, modal management
- **Async Operations**: API calls, file uploads, background tasks

### 🔧 **State Machines Built**

We implemented three core state machines that demonstrate different patterns:

#### **1. Event List Machine** (`eventListMachine.js`)
Manages the complete lifecycle of event list components:

```javascript
// State Flow
idle → loading → loaded → filtering
     ↓              ↓
   error ←----------┘

// Features
- Data loading with error handling
- Real-time filtering and sorting
- Pagination state management
- Layout switching (list/grid/table)
- Optimistic updates
```

#### **2. Event Form Machine** (`eventFormMachine.js`)
Handles complex form workflows with validation:

```javascript
// State Flow
idle → validating → submitting → success
     ↓              ↓              ↓
   error ←----------┘              ↓
     ↑                             ↓
     └─────────────────────────────┘

// Features
- Real-time field validation
- Auto-save functionality
- Dirty state tracking
- Multi-step form support
- Error recovery
```

#### **3. Event Status Machine** (`eventStatusMachine.js`)
Enforces business rules for event lifecycle:

```javascript
// State Flow
draft → published → completed
      ↓           ↓
   cancelled ←----┘

// Features
- Business rule enforcement
- Status transition validation
- History tracking
- Permission checking
- Audit trail
```

## 🚀 How to Use XState with NHK Framework

### **Step 1: Understanding State Machines**

State machines provide several key benefits:
- **Predictable Behavior**: Impossible states are impossible
- **Visual Documentation**: State diagrams serve as living documentation
- **Easy Testing**: Each state and transition can be tested independently
- **Debugging**: Clear state history and transition logs

### **Step 2: Creating a New State Machine**

```javascript
// assets/src/js/machines/yourMachine.js
import { createMachine, assign } from 'xstate';

export const yourMachine = createMachine({
    id: 'yourComponent',
    initial: 'idle',
    context: {
        data: null,
        error: null,
        isLoading: false
    },
    states: {
        idle: {
            on: {
                LOAD: 'loading'
            }
        },
        loading: {
            entry: assign({ isLoading: true }),
            invoke: {
                id: 'loadData',
                src: 'loadDataService',
                onDone: {
                    target: 'success',
                    actions: assign({
                        data: (context, event) => event.data,
                        isLoading: false
                    })
                },
                onError: {
                    target: 'error',
                    actions: assign({
                        error: (context, event) => event.data,
                        isLoading: false
                    })
                }
            }
        },
        success: {
            on: {
                RELOAD: 'loading'
            }
        },
        error: {
            on: {
                RETRY: 'loading'
            }
        }
    }
}, {
    services: {
        loadDataService: async (context, event) => {
            // Your async logic here
            const response = await fetch('/api/data');
            return response.json();
        }
    }
});
```

### **Step 3: Integrating with Alpine.js**

```javascript
// assets/src/js/components/yourComponent.js
import { yourMachine } from '../machines/yourMachine.js';

document.addEventListener('alpine:init', () => {
    Alpine.data('yourComponent', () => ({
        // State machine service
        service: null,
        
        // Reactive state
        state: null,
        data: null,
        isLoading: false,
        error: null,
        
        init() {
            // Create state machine service
            this.service = window.createStateMachine(yourMachine);
            
            // Subscribe to state changes
            this.service.subscribe((state) => {
                this.state = state;
                this.data = state.context.data;
                this.isLoading = state.context.isLoading;
                this.error = state.context.error;
            });
            
            // Start the service
            this.service.start();
        },
        
        // Actions
        loadData() {
            this.service.send('LOAD');
        },
        
        retry() {
            this.service.send('RETRY');
        }
    }));
});
```

### **Step 4: Using in HTML Templates**

```html
<div x-data="yourComponent()">
    <!-- Loading State -->
    <div x-show="isLoading">
        <div class="loading-spinner"></div>
        <p>Loading data...</p>
    </div>
    
    <!-- Error State -->
    <div x-show="error" class="notification-error">
        <p x-text="error?.message || 'An error occurred'"></p>
        <button @click="retry()" class="btn-nhk-primary">
            Retry
        </button>
    </div>
    
    <!-- Success State -->
    <div x-show="data && !isLoading && !error">
        <template x-for="item in data" :key="item.id">
            <div x-text="item.name"></div>
        </template>
    </div>
    
    <!-- Actions -->
    <button @click="loadData()" class="btn-nhk-primary">
        Load Data
    </button>
</div>
```

## 🎯 **Advanced Patterns & Features**

### **1. Hierarchical States**

```javascript
states: {
    form: {
        initial: 'editing',
        states: {
            editing: {
                on: {
                    VALIDATE: 'validating'
                }
            },
            validating: {
                always: [
                    { target: 'valid', cond: 'isFormValid' },
                    { target: 'invalid' }
                ]
            },
            valid: {
                on: {
                    SUBMIT: '#form.submitting'
                }
            },
            invalid: {
                on: {
                    EDIT: 'editing'
                }
            }
        }
    },
    submitting: {
        // Parallel to form states
    }
}
```

### **2. Guards (Conditional Transitions)**

```javascript
guards: {
    isFormValid: (context, event) => {
        return Object.keys(context.errors).length === 0;
    },
    
    canEdit: (context, event) => {
        return context.user?.permissions?.includes('edit');
    },
    
    hasUnsavedChanges: (context, event) => {
        return context.isDirty;
    }
}
```

### **3. Actions (Side Effects)**

```javascript
actions: {
    updateField: assign({
        formData: (context, event) => ({
            ...context.formData,
            [event.field]: event.value
        }),
        isDirty: true
    }),
    
    logError: (context, event) => {
        console.error('State machine error:', event.data);
        if (window.nhkEventManager?.isDebug) {
            window.showNotification('Debug: ' + event.data.message, 'error');
        }
    },
    
    trackAnalytics: (context, event) => {
        if (window.gtag) {
            window.gtag('event', 'state_transition', {
                from: event.from,
                to: event.to,
                component: context.componentId
            });
        }
    }
}
```

### **4. Services (Async Operations)**

```javascript
services: {
    loadEvents: async (context, event) => {
        const api = window.nhkEventApi;
        const params = {
            page: context.pagination.currentPage,
            per_page: context.pagination.perPage,
            ...context.filters
        };
        
        return await api.getEvents(params);
    },
    
    saveEvent: async (context, event) => {
        const api = window.nhkEventApi;
        
        if (context.eventId) {
            return await api.updateEvent(context.eventId, context.formData);
        } else {
            return await api.createEvent(context.formData);
        }
    },
    
    uploadFile: async (context, event) => {
        const formData = new FormData();
        formData.append('file', event.file);
        formData.append('action', 'upload-attachment');
        
        const response = await fetch('/wp-admin/admin-ajax.php', {
            method: 'POST',
            body: formData
        });
        
        return response.json();
    }
}
```

## 🔍 **State Machine Patterns**

### **1. Loading Pattern**
```javascript
// Common pattern for async operations
idle → loading → success
     ↓         ↓
   error ←-----┘
```

### **2. CRUD Pattern**
```javascript
// Create, Read, Update, Delete operations
idle → creating → created
     → reading → loaded
     → updating → updated
     → deleting → deleted
```

### **3. Multi-Step Form Pattern**
```javascript
// Wizard-style forms
step1 → step2 → step3 → complete
  ↓       ↓       ↓
back ←--- back ←--┘
```

### **4. Modal Pattern**
```javascript
// Modal/dialog management
closed → opening → open → closing → closed
                   ↓
                 error
```

## 🛠 **Development Tools & Debugging**

### **1. XState Inspector**

Add to your development environment:

```javascript
// In development mode
if (window.nhkEventManager?.isDebug) {
    import('@xstate/inspect').then(({ inspect }) => {
        inspect({
            url: 'https://stately.ai/viz?inspect',
            iframe: false
        });
    });
}
```

### **2. State Logging**

```javascript
// Add to your Alpine.js components
this.service.subscribe((state) => {
    if (window.nhkEventManager?.isDebug) {
        console.log('🔄 State transition:', {
            from: state.history?.value,
            to: state.value,
            context: state.context,
            event: state.event
        });
    }
    
    this.updateFromState(state);
});
```

### **3. Visual State Charts**

Use the XState Visualizer to see your state machines:
- Visit: https://stately.ai/viz
- Paste your machine definition
- Get interactive state diagrams

## 📊 **Performance Considerations**

### **1. Memory Management**

```javascript
destroy() {
    // Always clean up state machine services
    if (this.service) {
        this.service.stop();
        this.service = null;
    }
}
```

### **2. Selective Updates**

```javascript
// Only update what changed
updateFromState(state) {
    const newData = eventListSelectors.getEvents(state);
    if (JSON.stringify(newData) !== JSON.stringify(this.events)) {
        this.events = newData;
    }
}
```

### **3. Debounced Actions**

```javascript
// Debounce frequent state updates
searchEvents: Alpine.debounce(function(searchTerm) {
    this.service.send('SEARCH', { query: searchTerm });
}, 300)
```

## 🧪 **Testing State Machines**

### **1. Unit Testing States**

```javascript
import { interpret } from 'xstate';
import { eventListMachine } from '../machines/eventListMachine.js';

describe('Event List Machine', () => {
    it('should start in idle state', () => {
        const service = interpret(eventListMachine);
        service.start();
        
        expect(service.state.value).toBe('idle');
        
        service.stop();
    });
    
    it('should transition to loading when LOAD_EVENTS is sent', () => {
        const service = interpret(eventListMachine);
        service.start();
        
        service.send('LOAD_EVENTS');
        
        expect(service.state.value).toBe('loading');
        
        service.stop();
    });
});
```

### **2. Integration Testing**

```javascript
// Test complete workflows
it('should load events successfully', async () => {
    const mockApi = {
        getEvents: jest.fn().mockResolvedValue({
            events: [{ id: 1, title: 'Test Event' }],
            total: 1
        })
    };
    
    window.nhkEventApi = mockApi;
    
    const service = interpret(eventListMachine);
    service.start();
    
    service.send('LOAD_EVENTS');
    
    // Wait for async operation
    await new Promise(resolve => {
        service.onTransition(state => {
            if (state.matches('loaded')) {
                expect(state.context.events).toHaveLength(1);
                resolve();
            }
        });
    });
    
    service.stop();
});
```

## 🎉 **Benefits Achieved**

### **1. Predictable UI Behavior**
- ✅ **Impossible states eliminated**: No more loading + error simultaneously
- ✅ **Clear state transitions**: Every UI change is intentional and documented
- ✅ **Consistent behavior**: Same inputs always produce same outputs

### **2. Enhanced Developer Experience**
- ✅ **Visual documentation**: State diagrams serve as living documentation
- ✅ **Easy debugging**: Clear state history and transition logs
- ✅ **Testable logic**: Each state and transition can be tested independently

### **3. Maintainable Code**
- ✅ **Separation of concerns**: UI logic separated from business logic
- ✅ **Reusable patterns**: State machines can be composed and extended
- ✅ **Self-documenting**: State machine definitions explain the intended behavior

### **4. WordPress Integration**
- ✅ **Progressive enhancement**: Works with or without JavaScript
- ✅ **Server-side compatibility**: State can be hydrated from PHP
- ✅ **Performance optimized**: Minimal overhead with maximum benefit

## 🔄 **Real-World Examples**

### **Event List Component**
- **12 states** managing loading, filtering, pagination, and error handling
- **Handles complex interactions** like simultaneous filtering and sorting
- **Optimistic updates** for better user experience
- **Graceful error recovery** with retry mechanisms

### **Event Form Component**
- **Auto-save functionality** with dirty state tracking
- **Multi-step validation** with field-level and form-level rules
- **File upload handling** with progress tracking
- **Unsaved changes warnings** before navigation

### **Event Status Component**
- **Business rule enforcement** for status transitions
- **Permission-based actions** with role checking
- **Audit trail** for status change history
- **Bulk operations** with progress tracking

## 🚀 **Next Steps**

1. **Explore the existing machines** in `assets/src/js/machines/`
2. **Create your own state machine** following the patterns shown
3. **Use the XState Visualizer** to design complex workflows
4. **Add state machines** to your Alpine.js components
5. **Test your state logic** with the provided testing patterns

XState integration with the NHK Framework demonstrates how sophisticated state management can make WordPress plugins more reliable, maintainable, and user-friendly while maintaining the simplicity that makes WordPress accessible to developers of all skill levels.

---

## 💡 **Quick Start Examples**

### **Simple Toggle Machine**
```javascript
// Perfect for modals, dropdowns, toggles
const toggleMachine = createMachine({
    id: 'toggle',
    initial: 'closed',
    states: {
        closed: {
            on: { TOGGLE: 'open' }
        },
        open: {
            on: { TOGGLE: 'closed' }
        }
    }
});
```

### **API Request Machine**
```javascript
// Standard pattern for any API call
const apiMachine = createMachine({
    id: 'api',
    initial: 'idle',
    context: { data: null, error: null },
    states: {
        idle: {
            on: { FETCH: 'loading' }
        },
        loading: {
            invoke: {
                src: 'fetchData',
                onDone: { target: 'success', actions: 'setData' },
                onError: { target: 'failure', actions: 'setError' }
            }
        },
        success: {
            on: { REFETCH: 'loading' }
        },
        failure: {
            on: { RETRY: 'loading' }
        }
    }
});
```

### **Form Validation Machine**
```javascript
// Handles complex form workflows
const formMachine = createMachine({
    id: 'form',
    initial: 'editing',
    context: {
        values: {},
        errors: {},
        isDirty: false
    },
    states: {
        editing: {
            on: {
                CHANGE: { actions: 'updateField' },
                SUBMIT: 'validating'
            }
        },
        validating: {
            always: [
                { target: 'submitting', cond: 'isValid' },
                { target: 'editing', actions: 'showErrors' }
            ]
        },
        submitting: {
            invoke: {
                src: 'submitForm',
                onDone: 'success',
                onError: { target: 'editing', actions: 'setSubmitError' }
            }
        },
        success: {
            type: 'final'
        }
    }
});
```

## 🎨 **WordPress-Specific Patterns**

### **1. WordPress AJAX Integration**
```javascript
services: {
    wpAjaxCall: async (context, event) => {
        const formData = new FormData();
        formData.append('action', event.action);
        formData.append('nonce', window.nhkEventManager.nonce);
        formData.append('data', JSON.stringify(event.data));

        const response = await fetch(window.ajaxurl, {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.data?.message || 'AJAX request failed');
        }

        return result.data;
    }
}
```

### **2. WordPress REST API Integration**
```javascript
services: {
    wpRestCall: async (context, event) => {
        const response = await fetch(`${window.wpApiSettings.root}wp/v2/${event.endpoint}`, {
            method: event.method || 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': window.wpApiSettings.nonce
            },
            body: event.data ? JSON.stringify(event.data) : undefined
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        return response.json();
    }
}
```

### **3. WordPress Media Upload**
```javascript
const mediaUploadMachine = createMachine({
    id: 'mediaUpload',
    initial: 'idle',
    context: {
        file: null,
        uploadProgress: 0,
        mediaId: null,
        error: null
    },
    states: {
        idle: {
            on: {
                SELECT_FILE: {
                    target: 'selected',
                    actions: 'setFile'
                }
            }
        },
        selected: {
            on: {
                UPLOAD: 'uploading',
                CANCEL: 'idle'
            }
        },
        uploading: {
            invoke: {
                src: 'uploadToWordPress',
                onDone: {
                    target: 'uploaded',
                    actions: 'setMediaId'
                },
                onError: {
                    target: 'error',
                    actions: 'setError'
                }
            }
        },
        uploaded: {
            on: {
                RESET: 'idle'
            }
        },
        error: {
            on: {
                RETRY: 'uploading',
                RESET: 'idle'
            }
        }
    }
});
```

## 🔧 **Integration Helpers**

### **Global State Machine Creator**
```javascript
// assets/src/js/index.js
window.createStateMachine = (machine, options = {}) => {
    const { interpret } = window.XState;

    const service = interpret(machine.withContext({
        ...machine.context,
        ...options.context
    }));

    // Add global error handling
    service.onTransition((state, event) => {
        if (state.matches('error') && window.nhkEventManager?.isDebug) {
            console.error('State machine error:', {
                machine: machine.id,
                state: state.value,
                context: state.context,
                event
            });
        }
    });

    return service;
};
```

### **State Machine Selectors**
```javascript
// Reusable selectors for common state queries
export const createSelectors = (machine) => ({
    isLoading: (state) => state.matches('loading'),
    isError: (state) => state.matches('error'),
    isSuccess: (state) => state.matches('success'),
    getData: (state) => state.context.data,
    getError: (state) => state.context.error,
    canTransition: (state, event) => state.can(event)
});
```

**Resources:**
- [XState Documentation](https://xstate.js.org/docs/)
- [XState Visualizer](https://stately.ai/viz)
- [State Machine Patterns](https://xstate.js.org/docs/guides/introduction-to-state-machines-and-statecharts/)
- [NHK Framework Examples](../assets/src/js/machines/)
- [WordPress REST API](https://developer.wordpress.org/rest-api/)
- [WordPress AJAX](https://codex.wordpress.org/AJAX_in_Plugins)
