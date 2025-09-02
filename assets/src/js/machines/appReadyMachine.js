/**
 * App Readiness State Machine (XState v5)
 *
 * Responsibility: orchestrate readiness/capability flags only.
 * - Does NOT fetch data
 * - Does NOT render UI
 */

import { createMachine, assign } from 'xstate';

// Global feature flags (read by components)
window.nhkFeatures = window.nhkFeatures || {
  liveFilters: false,
  instantSearch: false,
  bulkActions: false
};

function setFeatures(flags) {
  window.nhkFeatures = { ...window.nhkFeatures, ...flags };
  document.dispatchEvent(new CustomEvent('nhk:features:updated', { detail: { ...window.nhkFeatures } }));
}

export const appReadyMachine = createMachine({
  id: 'appReady',
  initial: 'booting',
  states: {
    booting: {
      on: {
        DEPS_CHECKING: 'checking'
      }
    },
    checking: {
      on: {
        DEPS_READY: 'ready',
        DEPS_DEGRADED: 'degraded'
      }
    },
    ready: {
      entry: ['enableEnhancements'],
      on: {
        API_UNAVAILABLE: 'degraded'
      }
    },
    degraded: {
      entry: ['disableEnhancements'],
      on: {
        API_AVAILABLE: 'ready'
      }
    }
  }
}, {
  actions: {
    enableEnhancements: () => setFeatures({
      liveFilters: true,
      instantSearch: true,
      bulkActions: true
    }),
    disableEnhancements: () => setFeatures({
      liveFilters: false,
      instantSearch: false,
      bulkActions: false
    })
  }
});

export default appReadyMachine;

