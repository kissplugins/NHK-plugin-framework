/**
 * NHK Event Manager API Client
 * 
 * Provides a clean interface for interacting with the WordPress REST API
 * endpoints for the NHK Event Manager plugin.
 */

class ApiClient {
    constructor() {
        // Debug WordPress data availability
        console.log('🔧 Initializing API Client...');
        console.log('WordPress data:', window.nhkEventManager);

        this.baseUrl = window.nhkEventManager?.apiUrl || '/wp-json/nhk-events/v1';
        this.nonce = window.nhkEventManager?.nonce || '';
        this.isDebug = window.nhkEventManager?.isDebug || false;

        console.log('API Config:', {
            baseUrl: this.baseUrl,
            hasNonce: !!this.nonce,
            isDebug: this.isDebug
        });

        // Request interceptors
        this.requestInterceptors = [];
        this.responseInterceptors = [];

        // Rate limiting
        this.requestQueue = [];
        this.isProcessingQueue = false;
        this.maxConcurrentRequests = 5;
        this.activeRequests = 0;
    }
    
    /**
     * Add a request interceptor
     */
    addRequestInterceptor(interceptor) {
        this.requestInterceptors.push(interceptor);
    }
    
    /**
     * Add a response interceptor
     */
    addResponseInterceptor(interceptor) {
        this.responseInterceptors.push(interceptor);
    }
    
    /**
     * Make an authenticated API request
     */
    async request(endpoint, options = {}) {
        return new Promise((resolve, reject) => {
            this.requestQueue.push({ endpoint, options, resolve, reject });
            this.processQueue();
        });
    }
    
    /**
     * Process the request queue
     */
    async processQueue() {
        if (this.isProcessingQueue || this.activeRequests >= this.maxConcurrentRequests) {
            return;
        }
        
        if (this.requestQueue.length === 0) {
            return;
        }
        
        this.isProcessingQueue = true;
        
        while (this.requestQueue.length > 0 && this.activeRequests < this.maxConcurrentRequests) {
            const { endpoint, options, resolve, reject } = this.requestQueue.shift();
            this.activeRequests++;
            
            this.executeRequest(endpoint, options)
                .then(resolve)
                .catch(reject)
                .finally(() => {
                    this.activeRequests--;
                    this.processQueue();
                });
        }
        
        this.isProcessingQueue = false;
    }
    
    /**
     * Execute a single request
     */
    async executeRequest(endpoint, options = {}) {
        const url = `${this.baseUrl}${endpoint}`;
        
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': this.nonce
            },
            credentials: 'same-origin'
        };
        
        let mergedOptions = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...(options.headers || {})
            }
        };
        
        // Apply request interceptors
        for (const interceptor of this.requestInterceptors) {
            mergedOptions = await interceptor(mergedOptions);
        }
        
        if (this.isDebug) {
            console.log('🌐 API Request:', { url, options: mergedOptions });
        }
        
        try {
            const response = await fetch(url, mergedOptions);
            
            // Apply response interceptors
            let processedResponse = response;
            for (const interceptor of this.responseInterceptors) {
                processedResponse = await interceptor(processedResponse);
            }
            
            if (!processedResponse.ok) {
                const errorData = await this.parseErrorResponse(processedResponse);
                throw new ApiError(errorData.message || 'API request failed', processedResponse.status, errorData);
            }
            
            const data = await processedResponse.json();
            
            if (this.isDebug) {
                console.log('✅ API Response:', data);
            }
            
            return data;
            
        } catch (error) {
            if (this.isDebug) {
                console.error('❌ API Error:', error);
            }
            
            if (error instanceof ApiError) {
                throw error;
            }
            
            throw new ApiError('Network error occurred', 0, { originalError: error });
        }
    }
    
    /**
     * Parse error response
     */
    async parseErrorResponse(response) {
        try {
            return await response.json();
        } catch {
            return {
                message: `HTTP ${response.status}: ${response.statusText}`,
                code: response.status
            };
        }
    }
    
    /**
     * GET request helper
     */
    async get(endpoint, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `${this.baseUrl}${endpoint}?${queryString}` : `${this.baseUrl}${endpoint}`;

        return this.request(url, {
            method: 'GET'
        });
    }
    
    /**
     * POST request helper
     */
    async post(endpoint, data = {}) {
        return this.request(`${this.baseUrl}${endpoint}`, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }
    
    /**
     * PUT request helper
     */
    async put(endpoint, data = {}) {
        return this.request(`${this.baseUrl}${endpoint}`, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }
    
    /**
     * PATCH request helper
     */
    async patch(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'PATCH',
            body: JSON.stringify(data)
        });
    }
    
    /**
     * DELETE request helper
     */
    async delete(endpoint) {
        return this.request(`${this.baseUrl}${endpoint}`, {
            method: 'DELETE'
        });
    }
    
    // Event-specific API methods
    
    /**
     * Get events with optional filtering
     */
    async getEvents(params = {}) {
        return this.get('/events', params);
    }
    
    /**
     * Get a single event by ID
     */
    async getEvent(eventId) {
        return this.get(`/events/${eventId}`);
    }
    
    /**
     * Create a new event
     */
    async createEvent(eventData) {
        return this.post('/events', eventData);
    }
    
    /**
     * Update an existing event
     */
    async updateEvent(eventId, eventData) {
        return this.put(`/events/${eventId}`, eventData);
    }
    
    /**
     * Delete an event
     */
    async deleteEvent(eventId) {
        return this.delete(`/events/${eventId}`);
    }
    
    /**
     * Get upcoming events
     */
    async getUpcomingEvents(limit = 10) {
        return this.get('/events/upcoming', { limit });
    }
    
    /**
     * Search events
     */
    async searchEvents(query, params = {}) {
        return this.get('/events/search', { q: query, ...params });
    }
    
    /**
     * Update event status
     */
    async updateEventStatus(eventId, newStatus, reason = '') {
        return this.patch(`/events/${eventId}/status`, {
            new_status: newStatus,
            reason: reason
        });
    }
    
    /**
     * Get event categories
     */
    async getEventCategories() {
        return this.get('/categories');
    }
    
    /**
     * Get event venues
     */
    async getEventVenues() {
        return this.get('/venues');
    }
    
    /**
     * Register for an event
     */
    async registerForEvent(eventId, registrationData) {
        return this.post(`/events/${eventId}/register`, registrationData);
    }
    
    /**
     * Cancel event registration
     */
    async cancelEventRegistration(eventId, registrationId) {
        return this.delete(`/events/${eventId}/registrations/${registrationId}`);
    }
    
    /**
     * Get event registrations (admin only)
     */
    async getEventRegistrations(eventId) {
        return this.get(`/events/${eventId}/registrations`);
    }
    
    /**
     * Export events
     */
    async exportEvents(format = 'json', params = {}) {
        return this.get('/events/export', { format, ...params });
    }
    
    /**
     * Import events
     */
    async importEvents(importData) {
        return this.post('/events/import', importData);
    }
    
    /**
     * Get plugin health status
     */
    async getHealthStatus() {
        return this.get('/health');
    }
    
    /**
     * Clear plugin cache
     */
    async clearCache() {
        return this.post('/cache/clear');
    }
}

/**
 * Custom API Error class
 */
class ApiError extends Error {
    constructor(message, status = 0, data = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.data = data;
    }
    
    /**
     * Check if error is due to authentication
     */
    isAuthError() {
        return this.status === 401 || this.status === 403;
    }
    
    /**
     * Check if error is due to validation
     */
    isValidationError() {
        return this.status === 400 || this.status === 422;
    }
    
    /**
     * Check if error is due to not found
     */
    isNotFoundError() {
        return this.status === 404;
    }
    
    /**
     * Check if error is due to server issues
     */
    isServerError() {
        return this.status >= 500;
    }
    
    /**
     * Check if error is due to network issues
     */
    isNetworkError() {
        return this.status === 0;
    }
}

export default ApiClient;
export { ApiError };
