/**
 * Event List Alpine.js Component
 * 
 * Provides reactive event list functionality with filtering, sorting,
 * and pagination using Alpine.js and XState.
 */

import { createEventListService, eventListSelectors } from '../machines/eventListMachine.js';

document.addEventListener('alpine:init', () => {
    Alpine.data('eventList', (config = {}) => ({
        // State machine service
        service: null,
        
        // Reactive state
        state: null,
        events: [],
        isLoading: false,
        hasError: false,
        error: null,
        
        // Configuration
        config: {
            layout: 'list',
            perPage: 10,
            showFilters: true,
            showPagination: true,
            showSort: true,
            allowedLayouts: ['list', 'grid', 'table'],
            ...config
        },
        
        // Filters
        filters: {
            search: '',
            category: '',
            venue: '',
            dateFrom: '',
            dateTo: '',
            status: 'published'
        },
        
        // UI state
        showFiltersPanel: false,
        selectedEvents: new Set(),
        
        init() {
            console.log('🎬 Initializing Event List component');

            try {
                // Check if required dependencies are available
                if (typeof createEventListService !== 'function') {
                    console.error('❌ createEventListService not available');
                    this.hasError = true;
                    this.error = 'State machine service not available';
                    return;
                }

                // Create state machine service
                console.log('🔧 Creating event list service...');
                this.service = createEventListService({
                    layout: this.config.layout,
                    pagination: {
                        currentPage: 1,
                        perPage: this.config.perPage,
                        totalPages: 1,
                        totalItems: 0
                    }
                });

                if (!this.service) {
                    console.error('❌ Failed to create event list service');
                    this.hasError = true;
                    this.error = 'Failed to create state machine service';
                    return;
                }

                console.log('✅ Event list service created successfully');

                // Subscribe to state changes
                this.service.subscribe((state) => {
                    console.log('🔄 State changed:', state.value);
                    this.updateFromState(state);
                });

                // Start the service
                this.service.start();
                console.log('🚀 Event list service started');

                // Load initial events
                this.loadEvents();

                // Set up keyboard shortcuts
                this.setupKeyboardShortcuts();

            } catch (error) {
                console.error('❌ Error initializing Event List component:', error);
                this.hasError = true;
                this.error = error.message || 'Initialization failed';
            }
        },
        
        destroy() {
            if (this.service) {
                this.service.stop();
            }
        },
        
        updateFromState(state) {
            this.state = state;
            this.events = eventListSelectors.getEvents(state);
            this.isLoading = eventListSelectors.isLoading(state);
            this.hasError = eventListSelectors.hasError(state);
            this.error = eventListSelectors.getError(state);
            
            // Update filters from state
            const stateFilters = eventListSelectors.getFilters(state);
            this.filters = { ...this.filters, ...stateFilters };
        },
        
        setupKeyboardShortcuts() {
            document.addEventListener('nhk:search', () => {
                this.$refs.searchInput?.focus();
            });
            
            document.addEventListener('nhk:escape', () => {
                this.showFiltersPanel = false;
                this.selectedEvents.clear();
            });
        },
        
        // Event loading
        loadEvents() {
            this.service.send('LOAD_EVENTS');
        },
        
        reloadEvents() {
            this.service.send('RELOAD_EVENTS');
        },
        
        // Filtering
        applyFilters() {
            this.service.send('APPLY_FILTERS', { filters: this.filters });
        },
        
        clearFilters() {
            this.filters = {
                search: '',
                category: '',
                venue: '',
                dateFrom: '',
                dateTo: '',
                status: 'published'
            };
            this.applyFilters();
        },
        
        toggleFiltersPanel() {
            this.showFiltersPanel = !this.showFiltersPanel;
        },
        
        // Search with debouncing
        searchEvents: window.debounce ? window.debounce(function(searchTerm) {
            this.filters.search = searchTerm;
            this.applyFilters();
        }, 300) : function(searchTerm) {
            this.filters.search = searchTerm;
            this.applyFilters();
        },
        
        // Layout management
        changeLayout(layout) {
            if (this.config.allowedLayouts.includes(layout)) {
                this.service.send('CHANGE_LAYOUT', { layout });
            }
        },
        
        getCurrentLayout() {
            return this.state ? eventListSelectors.getLayout(this.state) : this.config.layout;
        },
        
        // Sorting
        changeSort(sortBy, sortOrder = null) {
            // Toggle sort order if same field
            const currentSort = this.state ? eventListSelectors.getSort(this.state) : {};
            if (currentSort.sortBy === sortBy && !sortOrder) {
                sortOrder = currentSort.sortOrder === 'asc' ? 'desc' : 'asc';
            } else if (!sortOrder) {
                sortOrder = 'asc';
            }
            
            this.service.send('CHANGE_SORT', { sortBy, sortOrder });
        },
        
        getSortIcon(field) {
            if (!this.state) return '';
            
            const sort = eventListSelectors.getSort(this.state);
            if (sort.sortBy !== field) return '↕️';
            
            return sort.sortOrder === 'asc' ? '↑' : '↓';
        },
        
        // Pagination
        changePage(page) {
            this.service.send('CHANGE_PAGE', { page });
        },
        
        getPagination() {
            return this.state ? eventListSelectors.getPagination(this.state) : this.config.pagination;
        },
        
        // Event selection
        toggleEventSelection(eventId) {
            if (this.selectedEvents.has(eventId)) {
                this.selectedEvents.delete(eventId);
            } else {
                this.selectedEvents.add(eventId);
            }
        },
        
        selectAllEvents() {
            this.events.forEach(event => {
                this.selectedEvents.add(event.id);
            });
        },
        
        clearSelection() {
            this.selectedEvents.clear();
        },
        
        isEventSelected(eventId) {
            return this.selectedEvents.has(eventId);
        },
        
        getSelectedCount() {
            return this.selectedEvents.size;
        },
        
        // Bulk actions
        async bulkDeleteEvents() {
            if (this.selectedEvents.size === 0) return;
            
            if (!confirm(`Are you sure you want to delete ${this.selectedEvents.size} event(s)?`)) {
                return;
            }
            
            try {
                const api = window.nhkEventApi;
                const promises = Array.from(this.selectedEvents).map(eventId => 
                    api.deleteEvent(eventId)
                );
                
                await Promise.all(promises);
                
                // Remove deleted events from state
                this.selectedEvents.forEach(eventId => {
                    this.service.send('DELETE_EVENT', { eventId });
                });
                
                this.clearSelection();
                window.showNotification('Events deleted successfully', 'success');
                
            } catch (error) {
                console.error('Error deleting events:', error);
                window.showNotification('Error deleting events', 'error');
            }
        },
        
        async bulkUpdateStatus(newStatus) {
            if (this.selectedEvents.size === 0) return;
            
            try {
                const api = window.nhkEventApi;
                const promises = Array.from(this.selectedEvents).map(eventId => 
                    api.updateEventStatus(eventId, newStatus)
                );
                
                await Promise.all(promises);
                
                // Update events in state
                this.selectedEvents.forEach(eventId => {
                    this.service.send('UPDATE_EVENT', { 
                        eventId, 
                        updates: { status: newStatus } 
                    });
                });
                
                this.clearSelection();
                window.showNotification(`Events updated to ${newStatus}`, 'success');
                
            } catch (error) {
                console.error('Error updating events:', error);
                window.showNotification('Error updating events', 'error');
            }
        },
        
        // Error handling
        retryLoad() {
            this.service.send('RETRY');
        },
        
        clearError() {
            this.service.send('CLEAR_ERROR');
        },
        
        // Utility methods
        formatDate(dateString) {
            return window.formatEventDate(dateString);
        },
        
        formatTime(timeString) {
            return window.formatEventTime(timeString);
        },
        
        getEventUrl(event) {
            return `${window.location.origin}/?post_type=nhk_event&p=${event.id}`;
        },
        
        getEditUrl(event) {
            return `${window.location.origin}/wp-admin/post.php?post=${event.id}&action=edit`;
        },
        
        // Export functionality
        async exportEvents(format = 'json') {
            try {
                const api = window.nhkEventApi;
                const data = await api.exportEvents(format, this.filters);
                
                // Create download link
                const blob = new Blob([JSON.stringify(data, null, 2)], { 
                    type: 'application/json' 
                });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `events-export-${new Date().toISOString().split('T')[0]}.${format}`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                
                window.showNotification('Events exported successfully', 'success');
                
            } catch (error) {
                console.error('Error exporting events:', error);
                window.showNotification('Error exporting events', 'error');
            }
        }
    }));
});

// Export for potential external use
export default 'eventList';
