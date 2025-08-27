/*
 Minimal TypeScript scaffold for KISS Smart Batch Installer
 Phase 3: Add typed admin handlers; keep current JS intact
*/
export { PluginState, isInstalled, isPluginByState } from './types/fsm';
export { wpAjaxFetch } from './lib/ajaxClient';
export { mapResponseToError, mapExceptionToError } from './lib/errors';
export { installPlugin, activatePlugin, deactivatePlugin, refreshStatus } from './admin/handlers';
export { repositoryFSM, RepositoryFSM } from './admin/repositoryFSM';
// Smoke test to ensure bundling works when later integrated
export function tsScaffoldHello() {
    const hasAjax = typeof window !== 'undefined' && !!window.sbiAjax;
    return `TS scaffold ready. sbiAjax loaded: ${hasAjax}`;
}
