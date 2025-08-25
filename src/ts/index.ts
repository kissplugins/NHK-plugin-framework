/*
 Minimal TypeScript scaffold for KISS Smart Batch Installer
 Phase 1: Add shared types and keep current JS intact
*/

export { PluginState, isInstalled, isPluginByState } from './types/fsm';
export type { WpAjaxResponse, WpAjaxSuccess, WpAjaxError, ProgressUpdate } from './types/ajax';
export type { SbiAjax, SbiDebug } from './types/wp-globals';

// Smoke test to ensure bundling works when later integrated
export function tsScaffoldHello(): string {
  const hasAjax = typeof window !== 'undefined' && !!window.sbiAjax;
  return `TS scaffold ready. sbiAjax loaded: ${hasAjax}`;
}

