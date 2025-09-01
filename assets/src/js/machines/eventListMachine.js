/**
 * Event List State Machine
 * 
 * Manages the state of event lists including loading, filtering, pagination,
 * and error handling using XState.
 */

import { createMachine, assign, fromPromise } from 'xstate';

export const eventListMachine = createMachine({
    id: 'eventList',
    initial: 'idle',
    context: {
        events: [],
        filteredEvents: [],
        filters: {
            search: '',
            category: '',
            venue: '',
            dateFrom: '',
            dateTo: '',
            status: 'published'
        },
        pagination: {
            currentPage: 1,
            perPage: 10,
            totalPages: 1,
            totalItems: 0
        },
        layout: 'list', // 'list', 'grid', 'table', 'calendar'
        sortBy: 'start_date',
        sortOrder: 'asc',
        error: null,
        lastUpdated: null,
        // UI flags: FSM-first approach for Admin Demo
        showFiltersPanel: false,
        selectedIds: []
    },
    states: {
        idle: {
            on: {
                LOAD_EVENTS: 'loading',
                APPLY_FILTERS: 'filtering',
                CHANGE_LAYOUT: {
                    actions: assign({
                        layout: (context, event) => event.layout
                    })
                },
                TOGGLE_FILTERS: {
                    actions: assign({
                        showFiltersPanel: (ctx) => !ctx.showFiltersPanel
                    })
                },
                SELECT_EVENT: {
                    actions: assign({
                        selectedIds: (ctx, ev) => {
                            const id = ev.id;
                            const set = new Set(ctx.selectedIds);
                            if (set.has(id)) set.delete(id); else set.add(id);
                            return Array.from(set);
                        }
                    })
                },
                SELECT_ALL: {
                    actions: assign({
                        selectedIds: (ctx, ev) => Array.from(new Set([...(ctx.selectedIds||[]), ...ev.ids]))
                    })
                },
                CLEAR_SELECTION: {
                    actions: assign({ selectedIds: () => [] })
                },
                CHANGE_SORT: {
                    actions: assign({
                        sortBy: (context, event) => event.sortBy,
                        sortOrder: (context, event) => event.sortOrder || context.sortOrder
                    }),
                    target: 'filtering'
                }
            }
        },
        
        loading: {
            invoke: {
                id: 'loadEvents',
                src: 'loadEvents',
                input: (context) => ({ context }),
                onDone: {
                    target: 'loaded',
                    actions: assign({
                        events: (context, event) => event.data.events || [],
                        pagination: (context, event) => ({
                            ...context.pagination,
                            ...event.data.pagination
                        }),
                        lastUpdated: () => new Date().toISOString(),
                        error: null
                    })
                },
                onError: {
                    target: 'error',
                    actions: assign({
                        error: (context, event) => event.data
                    })
                }
            }
        },
        
        loaded: {
            entry: 'applyFiltersAndSort',
            on: {
                RELOAD_EVENTS: 'loading',
                APPLY_FILTERS: 'filtering',
                CHANGE_PAGE: {
                    actions: assign({
                        pagination: (context, event) => ({
                            ...context.pagination,
                            currentPage: event.page
                        })
                    }),
                    target: 'loading'
                },
                CHANGE_LAYOUT: {
                    actions: assign({
                        layout: (context, event) => event.layout
                    })
                },
                TOGGLE_FILTERS: {
                    actions: assign({
                        showFiltersPanel: (ctx) => !ctx.showFiltersPanel
                    })
                },
                SELECT_EVENT: {
                    actions: assign({
                        selectedIds: (ctx, ev) => {
                            const id = ev.id;
                            const set = new Set(ctx.selectedIds);
                            if (set.has(id)) set.delete(id); else set.add(id);
                            return Array.from(set);
                        }
                    })
                },
                SELECT_ALL: {
                    actions: assign({
                        selectedIds: (ctx, ev) => Array.from(new Set([...(ctx.selectedIds||[]), ...ev.ids]))
                    })
                },
                CLEAR_SELECTION: {
                    actions: assign({ selectedIds: () => [] })
                },
                CHANGE_SORT: {
                    actions: assign({
                        sortBy: (context, event) => event.sortBy,
                        sortOrder: (context, event) => event.sortOrder || context.sortOrder
                    }),
                    target: 'filtering'
                },
                UPDATE_EVENT: {
                    actions: assign({
                        events: (context, event) => {
                            return context.events.map(evt => 
                                evt.id === event.eventId 
                                    ? { ...evt, ...event.updates }
                                    : evt
                            );
                        }
                    }),
                    target: 'loaded'
                },
                DELETE_EVENT: {
                    actions: assign({
                        events: (context, event) => {
                            return context.events.filter(evt => evt.id !== event.eventId);
                        }
                    }),
                    target: 'loaded'
                }
            }
        },
        
        filtering: {
            entry: assign({
                filters: (context, event) => ({
                    ...context.filters,
                    ...event.filters
                })
            }),
            always: [
                {
                    target: 'loading',
                    guard: 'shouldReloadFromServer'
                },
                {
                    target: 'loaded',
                    actions: 'applyFiltersAndSort'
                }
            ]
        },
        
        error: {
            on: {
                RETRY: 'loading',
                CLEAR_ERROR: {
                    target: 'idle',
                    actions: assign({
                        error: null
                    })
                }
            }
        }
    }
}, {
    actions: {
        applyFiltersAndSort: assign({
            filteredEvents: (context) => {
                let filtered = [...context.events];
                
                // Apply search filter
                if (context.filters.search) {
                    const searchTerm = context.filters.search.toLowerCase();
                    filtered = filtered.filter(event => 
                        event.title.toLowerCase().includes(searchTerm) ||
                        event.content.toLowerCase().includes(searchTerm) ||
                        event.venue?.toLowerCase().includes(searchTerm)
                    );
                }
                
                // Apply category filter
                if (context.filters.category) {
                    filtered = filtered.filter(event => 
                        event.categories?.includes(context.filters.category)
                    );
                }
                
                // Apply venue filter
                if (context.filters.venue) {
                    filtered = filtered.filter(event => 
                        event.venue === context.filters.venue
                    );
                }
                
                // Apply date range filter
                if (context.filters.dateFrom) {
                    const fromDate = new Date(context.filters.dateFrom);
                    filtered = filtered.filter(event => 
                        new Date(event.start_date) >= fromDate
                    );
                }
                
                if (context.filters.dateTo) {
                    const toDate = new Date(context.filters.dateTo);
                    filtered = filtered.filter(event => 
                        new Date(event.start_date) <= toDate
                    );
                }
                
                // Apply status filter
                if (context.filters.status) {
                    filtered = filtered.filter(event => 
                        event.status === context.filters.status
                    );
                }
                
                // Apply sorting
                filtered.sort((a, b) => {
                    let aValue = a[context.sortBy];
                    let bValue = b[context.sortBy];
                    
                    // Handle date sorting
                    if (context.sortBy.includes('date')) {
                        aValue = new Date(aValue);
                        bValue = new Date(bValue);
                    }
                    
                    // Handle string sorting
                    if (typeof aValue === 'string') {
                        aValue = aValue.toLowerCase();
                        bValue = bValue.toLowerCase();
                    }
                    
                    if (context.sortOrder === 'desc') {
                        return bValue > aValue ? 1 : -1;
                    } else {
                        return aValue > bValue ? 1 : -1;
                    }
                });
                
                return filtered;
            }
        })
    },
    
    guards: {
        shouldReloadFromServer: (context, event) => {
            // Reload from server if certain filters change that require server-side processing
            return event.filters && (
                event.filters.status !== undefined ||
                event.filters.category !== undefined ||
                event.filters.venue !== undefined
            );
        }
    },
    
    actors: {
        loadEvents: fromPromise(async ({ input }) => {
            const context = input.context;
            const api = window.nhkEventApi;
            if (!api) {
                throw new Error('API client not available');
            }
            const params = {
                page: context.pagination.currentPage,
                per_page: context.pagination.perPage,
                orderby: context.sortBy,
                order: context.sortOrder,
                ...context.filters
            };
            Object.keys(params).forEach(key => {
                if (params[key] === '' || params[key] === null || params[key] === undefined) {
                    delete params[key];
                }
            });
            const response = await api.getEvents(params);
            return {
                events: response.events || response.data || response,
                pagination: {
                    currentPage: response.page || context.pagination.currentPage,
                    perPage: response.per_page || context.pagination.perPage,
                    totalPages: response.total_pages || Math.ceil((response.total || response.length) / context.pagination.perPage),
                    totalItems: response.total || response.length || 0
                }
            };
        })
    }
});

/**
 * Helper function to create an event list service
 */
export function createEventListService(initialContext = {}) {
    return window.createStateMachine(eventListMachine);
}

/**
 * Event list selectors for easy state access
 */
export const eventListSelectors = {
    isLoading: (state) => state.matches('loading'),
    isLoaded: (state) => state.matches('loaded'),
    isFiltering: (state) => state.matches('filtering'),
    hasError: (state) => state.matches('error'),
    getEvents: (state) => state.context.filteredEvents,
    getAllEvents: (state) => state.context.events,
    getFilters: (state) => state.context.filters,
    getPagination: (state) => state.context.pagination,
    getLayout: (state) => state.context.layout,
    getSort: (state) => ({ 
        sortBy: state.context.sortBy, 
        sortOrder: state.context.sortOrder 
    }),
    getError: (state) => state.context.error,
    getLastUpdated: (state) => state.context.lastUpdated,
    isFiltersOpen: (state) => !!state.context.showFiltersPanel,
    getSelectedIds: (state) => state.context.selectedIds || [],
    getSelectedCount: (state) => (state.context.selectedIds || []).length
};
