(function(window){
    class AjaxErrorHandler {
        async makeRequest(url, options = {}) {
            try {
                const response = await fetch(url, options);
                if (!response.ok) {
                    const text = await response.text();
                    console.error('AJAX error', response.status, text);
                    throw new Error(response.statusText || 'Request failed');
                }
                return await response.json();
            } catch (error) {
                console.error('AJAX request failed', error);
                throw error;
            }
        }
    }

    window.AjaxErrorHandler = AjaxErrorHandler;
})(window);
