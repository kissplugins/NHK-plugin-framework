// Enhanced AJAX error handler with detailed 500 error breakdown
class AjaxErrorHandler {
    constructor(options = {}) {
        // Configure logging and error display preferences
        this.debug = options.debug || true;
        this.showUserNotification = options.showUserNotification || true;
        this.logToConsole = options.logToConsole || true;
        this.customErrorHandler = options.customErrorHandler || null;
        
        // Track request lifecycle for better debugging
        this.requestStages = {
            PREPARING: 'Preparing request',
            SENDING: 'Sending request',
            WAITING: 'Waiting for response',
            RECEIVING: 'Receiving response',
            PROCESSING: 'Processing response',
            ERROR: 'Error occurred'
        };
    }

    // Main AJAX request wrapper with comprehensive error handling
    async makeRequest(url, options = {}) {
        // Create a unique request ID for tracking
        const requestId = `req_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
        let currentStage = this.requestStages.PREPARING;
        
        try {
            // Log request initiation
            this.logStage(requestId, currentStage, { url, options });
            
            // Set up default options with timeout
            const fetchOptions = {
                method: options.method || 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Request-ID': requestId, // Add request ID to headers for server-side tracking
                    ...options.headers
                },
                ...options
            };
            
            // Add request body if provided
            if (options.body) {
                fetchOptions.body = typeof options.body === 'string' 
                    ? options.body 
                    : JSON.stringify(options.body);
            }
            
            // Create abort controller for timeout handling
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), options.timeout || 30000);
            fetchOptions.signal = controller.signal;
            
            // Update stage to sending
            currentStage = this.requestStages.SENDING;
            this.logStage(requestId, currentStage);
            
            // Make the actual request
            const response = await fetch(url, fetchOptions);
            clearTimeout(timeoutId);
            
            // Update stage to receiving
            currentStage = this.requestStages.RECEIVING;
            this.logStage(requestId, currentStage, { status: response.status });
            
            // Check if response is a 500 error
            if (response.status === 500) {
                // Extract as much information as possible from the 500 error
                await this.handle500Error(response, requestId, url, fetchOptions);
                throw new Error('Server error occurred');
            }
            
            // Handle other HTTP errors
            if (!response.ok) {
                await this.handleHTTPError(response, requestId);
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Process successful response
            currentStage = this.requestStages.PROCESSING;
            this.logStage(requestId, currentStage);
            
            const data = await response.json();
            return data;
            
        } catch (error) {
            // Handle various error types with detailed information
            currentStage = this.requestStages.ERROR;
            this.handleCatchError(error, requestId, currentStage, url);
            throw error;
        }
    }
    
    // Specialized handler for 500 errors with detailed breakdown
    async handle500Error(response, requestId, url, options) {
        const errorDetails = {
            requestId,
            timestamp: new Date().toISOString(),
            url,
            method: options.method || 'GET',
            status: 500,
            statusText: response.statusText || 'Internal Server Error',
            headers: {}
        };
        
        // Collect all response headers for debugging
        response.headers.forEach((value, key) => {
            errorDetails.headers[key] = value;
        });
        
        // Try to extract error details from response body
        try {
            const contentType = response.headers.get('content-type');
            
            if (contentType && contentType.includes('application/json')) {
                // Parse JSON error response
                const errorBody = await response.json();
                errorDetails.serverError = errorBody;
                
                // Common error fields from various frameworks
                errorDetails.message = errorBody.message || errorBody.error || errorBody.detail || 'No error message provided';
                errorDetails.code = errorBody.code || errorBody.error_code || 'UNKNOWN';
                errorDetails.stackTrace = errorBody.stack || errorBody.stackTrace || null;
                errorDetails.debugInfo = errorBody.debug || errorBody.debugInfo || null;
                
            } else if (contentType && contentType.includes('text/html')) {
                // Handle HTML error pages (common in development)
                const htmlText = await response.text();
                
                // Try to extract error message from HTML
                const titleMatch = htmlText.match(/<title>(.*?)<\/title>/i);
                const h1Match = htmlText.match(/<h1[^>]*>(.*?)<\/h1>/i);
                
                errorDetails.message = titleMatch ? titleMatch[1] : (h1Match ? h1Match[1] : 'HTML error page returned');
                errorDetails.htmlResponse = this.debug ? htmlText.substring(0, 500) + '...' : 'HTML response hidden';
                
            } else {
                // Handle plain text or other responses
                const textResponse = await response.text();
                errorDetails.message = textResponse.substring(0, 200) || 'Empty response body';
                errorDetails.rawResponse = textResponse;
            }
        } catch (parseError) {
            // If we can't parse the response body
            errorDetails.message = 'Failed to parse error response';
            errorDetails.parseError = parseError.message;
        }
        
        // Log comprehensive error information
        if (this.logToConsole) {
            console.group(`🔴 500 Server Error - ${requestId}`);
            console.error('Error Summary:', {
                url: errorDetails.url,
                method: errorDetails.method,
                message: errorDetails.message,
                code: errorDetails.code
            });
            console.error('Full Error Details:', errorDetails);
            
            // Provide debugging suggestions
            console.info('🔍 Debugging Suggestions:');
            this.provideSuggestions(errorDetails);
            
            console.groupEnd();
        }
        
        // Show user-friendly notification
        if (this.showUserNotification) {
            this.showErrorNotification(errorDetails);
        }
        
        // Call custom error handler if provided
        if (this.customErrorHandler) {
            this.customErrorHandler(errorDetails);
        }
        
        return errorDetails;
    }
    
    // Handle other HTTP errors (4xx, etc.)
    async handleHTTPError(response, requestId) {
        const errorInfo = {
            requestId,
            status: response.status,
            statusText: response.statusText,
            url: response.url
        };
        
        try {
            const errorBody = await response.json();
            errorInfo.details = errorBody;
        } catch {
            errorInfo.details = await response.text();
        }
        
        if (this.logToConsole) {
            console.error(`HTTP Error ${response.status}:`, errorInfo);
        }
        
        return errorInfo;
    }
    
    // Handle JavaScript/Network errors
    handleCatchError(error, requestId, stage, url) {
        const errorInfo = {
            requestId,
            stage,
            url,
            type: error.name,
            message: error.message
        };
        
        // Identify specific error types
        if (error.name === 'AbortError') {
            errorInfo.category = 'TIMEOUT';
            errorInfo.suggestion = 'Request timed out. Check network connection or increase timeout duration.';
        } else if (error.message.includes('Failed to fetch')) {
            errorInfo.category = 'NETWORK';
            errorInfo.suggestion = 'Network error. Check if the server is reachable and CORS is configured correctly.';
        } else if (error instanceof SyntaxError) {
            errorInfo.category = 'PARSE_ERROR';
            errorInfo.suggestion = 'Failed to parse response. Server may be returning invalid JSON.';
        }
        
        if (this.logToConsole) {
            console.error('Request failed at stage:', stage, errorInfo);
        }
        
        return errorInfo;
    }
    
    // Provide debugging suggestions based on error details
    provideSuggestions(errorDetails) {
        const suggestions = [];
        
        // Check for common patterns
        if (errorDetails.message?.toLowerCase().includes('database')) {
            suggestions.push('• Check database connection and query logs');
            suggestions.push('• Verify database credentials and permissions');
        }
        
        if (errorDetails.message?.toLowerCase().includes('null') || errorDetails.message?.toLowerCase().includes('undefined')) {
            suggestions.push('• Check for null reference errors in server code');
            suggestions.push('• Validate request parameters');
        }
        
        if (errorDetails.stackTrace) {
            suggestions.push('• Stack trace available - check the specific line numbers');
            suggestions.push('• Look for the root cause in the deepest stack frame');
        }
        
        if (errorDetails.headers['x-powered-by']) {
            suggestions.push(`• Server framework: ${errorDetails.headers['x-powered-by']}`);
        }
        
        // General suggestions
        suggestions.push('• Check server logs for more details');
        suggestions.push('• Verify API endpoint URL and method');
        suggestions.push('• Test with minimal request payload');
        suggestions.push('• Check server resource limits (memory, CPU)');
        
        suggestions.forEach(s => console.info(s));
    }
    
    // Show user-friendly error notification
    showErrorNotification(errorDetails) {
        // Create a simple notification (customize based on your UI framework)
        const notification = `
            Server Error Occurred:
            ${errorDetails.message}
            Request ID: ${errorDetails.requestId}
            Please try again or contact support if the problem persists.
        `;
        
        // You can replace this with your preferred notification method
        // (toast, modal, etc.)
        console.warn('User Notification:', notification);
        
        // Example: If you have a notification library
        // toast.error(notification);
    }
    
    // Log request stages for debugging
    logStage(requestId, stage, details = {}) {
        if (this.debug && this.logToConsole) {
            console.log(`[${requestId}] Stage: ${stage}`, details);
        }
    }
}

// ============================================
// USAGE EXAMPLES
// ============================================

// Initialize the error handler
const ajaxHandler = new AjaxErrorHandler({
    debug: true,
    showUserNotification: true,
    logToConsole: true,
    customErrorHandler: (errorDetails) => {
        // Your custom error handling logic
        // e.g., send to error tracking service
        console.log('Custom handler:', errorDetails);
    }
});

// Example 1: Simple GET request
async function fetchData() {
    try {
        const data = await ajaxHandler.makeRequest('/api/users');
        console.log('Success:', data);
    } catch (error) {
        // Error already handled and logged by the handler
        console.log('Request failed');
    }
}

// Example 2: POST request with timeout
async function createUser(userData) {
    try {
        const result = await ajaxHandler.makeRequest('/api/users', {
            method: 'POST',
            body: userData,
            timeout: 10000, // 10 second timeout
            headers: {
                'Authorization': 'Bearer token123'
            }
        });
        console.log('User created:', result);
    } catch (error) {
        // Error details available in console
    }
}

// Example 3: Using with fetch directly (without wrapper)
fetch('/api/data')
    .then(response => {
        if (response.status === 500) {
            // Use the handler just for error processing
            return ajaxHandler.handle500Error(
                response, 
                'manual_request_123',
                '/api/data',
                { method: 'GET' }
            );
        }
        return response.json();
    })
    .then(data => console.log(data))
    .catch(error => console.error('Fetch failed:', error));

// Example 4: Retry mechanism for 500 errors
async function fetchWithRetry(url, maxRetries = 3) {
    for (let i = 0; i < maxRetries; i++) {
        try {
            const data = await ajaxHandler.makeRequest(url);
            return data; // Success, return the data
        } catch (error) {
            console.log(`Attempt ${i + 1} failed`);
            
            if (i === maxRetries - 1) {
                throw error; // Final attempt failed
            }
            
            // Wait before retrying (exponential backoff)
            await new Promise(resolve => setTimeout(resolve, Math.pow(2, i) * 1000));
        }
    }
}
