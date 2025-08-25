export function mapResponseToError(response, requestId, url, method) {
    const headers = {};
    response.headers.forEach((v, k) => (headers[k] = v));
    return {
        requestId,
        timestamp: new Date().toISOString(),
        url,
        method,
        status: response.status,
        statusText: response.statusText,
        headers,
    };
}
export function mapExceptionToError(e, requestId, url, method) {
    const message = e instanceof Error ? e.message : String(e);
    return {
        requestId,
        timestamp: new Date().toISOString(),
        url,
        method,
        serverError: {
            message,
        },
    };
}
