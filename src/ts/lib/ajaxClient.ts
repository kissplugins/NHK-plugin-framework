import type { WpAjaxResponse } from '../types/ajax';
import type { SbiAjax } from '../types/wp-globals';
import { mapExceptionToError, mapResponseToError } from './errors';

export interface AjaxOptions {
  method?: 'GET' | 'POST';
  timeoutMs?: number; // default 30000
  headers?: Record<string, string>;
}

function timeout(signal: AbortSignal | undefined, ms: number): AbortController | undefined {
  if (typeof AbortController === 'undefined') return undefined;
  const ctrl = new AbortController();
  const id = setTimeout(() => ctrl.abort(), ms);
  // When caller passes an external signal we could chain, but keep minimal
  void signal; // not used for now
  // Clear will be responsibility of caller lifecycle; for small calls, process ends anyway
  return ctrl;
}

function formEncode(body: Record<string, unknown>): string {
  return Object.entries(body)
    .map(([k, v]) => encodeURIComponent(k) + '=' + encodeURIComponent(String(v)))
    .join('&');
}

export async function wpAjaxFetch<TData = any>(
  windowObj: Window,
  payload: Record<string, unknown>,
  opts: AjaxOptions = {}
): Promise<WpAjaxResponse<TData>> {
  const w = windowObj as any as { sbiAjax?: SbiAjax; sbiDebug?: any };
  if (!w.sbiAjax) throw new Error('sbiAjax not found on window');

  const url = w.sbiAjax.ajaxurl;
  const method = opts.method || 'POST';
  const timeoutMs = opts.timeoutMs ?? 30000;
  const headers: Record<string, string> = {
    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
    ...(opts.headers || {}),
  };

  const controller = timeout(undefined, timeoutMs);

  try {
    const resp = await fetch(url, {
      method,
      headers,
      body: method === 'POST' ? formEncode(payload) : undefined,
      signal: controller?.signal,
      credentials: 'same-origin',
    });

    if (!resp.ok) {
      const err = mapResponseToError(resp, crypto.randomUUID?.() || Date.now().toString(), url, method);
      w.sbiDebug?.addEntry('error', 'AJAX Error', JSON.stringify(err));
      return { success: false, data: err as any };
    }

    const data = (await resp.json()) as WpAjaxResponse<TData>;
    return data;
  } catch (e) {
    const err = mapExceptionToError(e, crypto.randomUUID?.() || Date.now().toString(), url, method);
    w.sbiDebug?.addEntry('error', 'AJAX Exception', JSON.stringify(err));
    return { success: false, data: err as any };
  }
}

