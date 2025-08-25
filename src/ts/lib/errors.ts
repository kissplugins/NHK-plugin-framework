export interface AjaxErrorDetails {
  requestId: string;
  timestamp: string;
  url: string;
  method: 'GET' | 'POST' | 'PUT' | 'DELETE';
  status?: number;
  statusText?: string;
  headers?: Record<string, string>;
  serverError?: {
    message: string;
    code?: string;
    stackTrace?: string;
    debugInfo?: Record<string, unknown>;
  };
}

export function mapResponseToError(
  response: Response,
  requestId: string,
  url: string,
  method: 'GET' | 'POST' | 'PUT' | 'DELETE'
): AjaxErrorDetails {
  const headers: Record<string, string> = {};
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

export function mapExceptionToError(
  e: unknown,
  requestId: string,
  url: string,
  method: 'GET' | 'POST' | 'PUT' | 'DELETE'
): AjaxErrorDetails {
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

