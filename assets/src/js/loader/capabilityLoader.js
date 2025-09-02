/**
 * Capability Loader
 *
 * Detects dependencies and REST availability, then emits DOM events
 * the appReady FSM listens to.
 */

export function runCapabilityChecks() {
  try {
    document.dispatchEvent(new CustomEvent('nhk:deps:checking'));

    const deps = {
      alpine: typeof window.Alpine !== 'undefined',
      xstate: typeof window.createStateMachine === 'function',
      apiUrl: !!(window.nhkEventManager && window.nhkEventManager.apiUrl)
    };

    const allDeps = deps.alpine && deps.xstate && deps.apiUrl;
    if (allDeps) {
      document.dispatchEvent(new CustomEvent('nhk:deps:ready', { detail: deps }));
    } else {
      document.dispatchEvent(new CustomEvent('nhk:deps:degraded', { detail: deps }));
    }

    // Quick REST ping (lightweight)
    const base = (window.nhkEventManager && window.nhkEventManager.apiUrl) || '/?rest_route=/nhk-events/v1';
    const url = base + '/health';

    fetch(url, { credentials: 'same-origin' })
      .then(res => {
        if (res.ok) {
          document.dispatchEvent(new CustomEvent('nhk:api:available'));
        } else {
          document.dispatchEvent(new CustomEvent('nhk:api:unavailable', { detail: { status: res.status } }));
        }
      })
      .catch(() => {
        document.dispatchEvent(new CustomEvent('nhk:api:unavailable'));
      });
  } catch (e) {
    console.warn('Capability check failed', e);
    document.dispatchEvent(new CustomEvent('nhk:deps:degraded'));
  }
}

