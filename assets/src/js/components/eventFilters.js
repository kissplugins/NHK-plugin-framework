/**
 * Event Filters Alpine.js Component
 * 
 * Provides advanced filtering interface for event lists.
 */

document.addEventListener('alpine:init', () => {
    Alpine.data('eventFilters', (config = {}) => ({
        // Filter state
        filters: {
            search: '',
            category: '',
            venue: '',
            dateFrom: '',
            dateTo: '',
            status: 'published',
            priceMin: '',
            priceMax: '',
            capacity: '',
            organizer: ''
        },
        
        // Available options
        categories: [],
        venues: [],
        organizers: [],
        
        // Configuration
        config: {
            showAdvanced: false,
            autoApply: true,
            debounceMs: 300,
            ...config
        },
        
        // UI state
        showAdvancedFilters: false,
        isLoading: false,
        
        init() {
            console.log('🔍 Initializing Event Filters component');
            
            // Load filter options
            this.loadFilterOptions();
            
            // Set up auto-apply with debouncing
            if (this.config.autoApply) {
                this.setupAutoApply();
            }
            
            // Load saved filters from localStorage
            this.loadSavedFilters();
        },
        
        async loadFilterOptions() {
            this.isLoading = true;
            
            try {
                const api = window.nhkEventApi;
                
                // Load categories
                const categoriesResponse = await api.getEventCategories();
                this.categories = categoriesResponse.data || categoriesResponse;
                
                // Load venues
                const venuesResponse = await api.getEventVenues();
                this.venues = venuesResponse.data || venuesResponse;
                
                // Load organizers (from events)
                const eventsResponse = await api.getEvents({ per_page: 100 });
                const events = eventsResponse.events || eventsResponse.data || eventsResponse;
                
                // Extract unique organizers
                const organizerSet = new Set();
                events.forEach(event => {
                    if (event.organizer_name) {
                        organizerSet.add(event.organizer_name);
                    }
                });
                this.organizers = Array.from(organizerSet).sort();
                
            } catch (error) {
                console.error('Error loading filter options:', error);
                window.showNotification('Failed to load filter options', 'error');
            } finally {
                this.isLoading = false;
            }
        },
        
        setupAutoApply() {
            // Watch for filter changes and auto-apply
            this.$watch('filters', 
                Alpine.debounce(() => {
                    this.applyFilters();
                }, this.config.debounceMs),
                { deep: true }
            );
        },
        
        loadSavedFilters() {
            try {
                const saved = localStorage.getItem('nhk_event_filters');
                if (saved) {
                    const savedFilters = JSON.parse(saved);
                    this.filters = { ...this.filters, ...savedFilters };
                }
            } catch (error) {
                console.error('Error loading saved filters:', error);
            }
        },
        
        saveFilters() {
            try {
                localStorage.setItem('nhk_event_filters', JSON.stringify(this.filters));
            } catch (error) {
                console.error('Error saving filters:', error);
            }
        },
        
        // Filter application
        applyFilters() {
            // Save current filters
            this.saveFilters();
            
            // Emit event for parent components to listen to
            this.$dispatch('filters-changed', {
                filters: { ...this.filters },
                hasActiveFilters: this.hasActiveFilters()
            });
        },
        
        clearFilters() {
            this.filters = {
                search: '',
                category: '',
                venue: '',
                dateFrom: '',
                dateTo: '',
                status: 'published',
                priceMin: '',
                priceMax: '',
                capacity: '',
                organizer: ''
            };
            
            this.applyFilters();
        },
        
        clearFilter(filterName) {
            this.filters[filterName] = '';
            this.applyFilters();
        },
        
        // Filter state helpers
        hasActiveFilters() {
            return Object.entries(this.filters).some(([key, value]) => {
                // Don't count status as active filter if it's the default
                if (key === 'status' && value === 'published') {
                    return false;
                }
                return value !== '' && value !== null && value !== undefined;
            });
        },
        
        getActiveFilterCount() {
            return Object.entries(this.filters).filter(([key, value]) => {
                if (key === 'status' && value === 'published') {
                    return false;
                }
                return value !== '' && value !== null && value !== undefined;
            }).length;
        },
        
        getActiveFilters() {
            const active = [];
            
            Object.entries(this.filters).forEach(([key, value]) => {
                if (value && !(key === 'status' && value === 'published')) {
                    active.push({
                        key,
                        value,
                        label: this.getFilterLabel(key, value)
                    });
                }
            });
            
            return active;
        },
        
        getFilterLabel(key, value) {
            const labels = {
                search: `Search: "${value}"`,
                category: `Category: ${this.getCategoryName(value)}`,
                venue: `Venue: ${this.getVenueName(value)}`,
                dateFrom: `From: ${this.formatDate(value)}`,
                dateTo: `To: ${this.formatDate(value)}`,
                status: `Status: ${value}`,
                priceMin: `Min Price: $${value}`,
                priceMax: `Max Price: $${value}`,
                capacity: `Min Capacity: ${value}`,
                organizer: `Organizer: ${value}`
            };
            
            return labels[key] || `${key}: ${value}`;
        },
        
        // Helper methods
        getCategoryName(categoryId) {
            const category = this.categories.find(c => c.id == categoryId);
            return category ? category.name : categoryId;
        },
        
        getVenueName(venueId) {
            const venue = this.venues.find(v => v.id == venueId);
            return venue ? venue.name : venueId;
        },
        
        formatDate(dateString) {
            if (!dateString) return '';
            return new Date(dateString).toLocaleDateString();
        },
        
        // Advanced filters
        toggleAdvancedFilters() {
            this.showAdvancedFilters = !this.showAdvancedFilters;
        },
        
        // Preset filters
        applyPreset(preset) {
            const presets = {
                upcoming: {
                    dateFrom: new Date().toISOString().split('T')[0],
                    status: 'published'
                },
                thisWeek: {
                    dateFrom: this.getStartOfWeek().toISOString().split('T')[0],
                    dateTo: this.getEndOfWeek().toISOString().split('T')[0],
                    status: 'published'
                },
                thisMonth: {
                    dateFrom: this.getStartOfMonth().toISOString().split('T')[0],
                    dateTo: this.getEndOfMonth().toISOString().split('T')[0],
                    status: 'published'
                },
                free: {
                    priceMax: '0',
                    status: 'published'
                },
                paid: {
                    priceMin: '0.01',
                    status: 'published'
                }
            };
            
            if (presets[preset]) {
                this.filters = { ...this.filters, ...presets[preset] };
                this.applyFilters();
            }
        },
        
        // Date helpers
        getStartOfWeek() {
            const now = new Date();
            const day = now.getDay();
            const diff = now.getDate() - day;
            return new Date(now.setDate(diff));
        },
        
        getEndOfWeek() {
            const start = this.getStartOfWeek();
            return new Date(start.getTime() + 6 * 24 * 60 * 60 * 1000);
        },
        
        getStartOfMonth() {
            const now = new Date();
            return new Date(now.getFullYear(), now.getMonth(), 1);
        },
        
        getEndOfMonth() {
            const now = new Date();
            return new Date(now.getFullYear(), now.getMonth() + 1, 0);
        },
        
        // Export/Import filters
        exportFilters() {
            const data = {
                filters: this.filters,
                timestamp: new Date().toISOString()
            };
            
            const blob = new Blob([JSON.stringify(data, null, 2)], {
                type: 'application/json'
            });
            
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `event-filters-${new Date().toISOString().split('T')[0]}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        },
        
        async importFilters(event) {
            const file = event.target.files[0];
            if (!file) return;
            
            try {
                const text = await file.text();
                const data = JSON.parse(text);
                
                if (data.filters) {
                    this.filters = { ...this.filters, ...data.filters };
                    this.applyFilters();
                    window.showNotification('Filters imported successfully', 'success');
                } else {
                    throw new Error('Invalid filter file format');
                }
                
            } catch (error) {
                console.error('Error importing filters:', error);
                window.showNotification('Failed to import filters', 'error');
            }
            
            // Reset file input
            event.target.value = '';
        },
        
        // URL integration
        updateUrlParams() {
            if (!window.history?.pushState) return;
            
            const url = new URL(window.location);
            const params = new URLSearchParams();
            
            Object.entries(this.filters).forEach(([key, value]) => {
                if (value && !(key === 'status' && value === 'published')) {
                    params.set(key, value);
                }
            });
            
            url.search = params.toString();
            window.history.pushState({}, '', url);
        },
        
        loadFromUrlParams() {
            const params = new URLSearchParams(window.location.search);
            
            Object.keys(this.filters).forEach(key => {
                if (params.has(key)) {
                    this.filters[key] = params.get(key);
                }
            });
        }
    }));
});

export default 'eventFilters';
