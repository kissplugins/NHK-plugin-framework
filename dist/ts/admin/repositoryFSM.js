import { PluginState } from '../types/fsm';
export class RepositoryFSM {
    constructor() {
        this.states = new Map();
        this.listeners = new Set();
    }
    onChange(listener) {
        this.listeners.add(listener);
        return () => this.listeners.delete(listener);
    }
    get(repo) {
        return this.states.get(repo);
    }
    set(repo, state) {
        const prev = this.states.get(repo);
        this.states.set(repo, state);
        // Always enable debug logging in development (browser environment)
        if (true) {
            try {
                // Preserve/improve debug output
                const msg = `[RepositoryFSM] ${repo}: ${prev ?? '∅'} -> ${state}`;
                if (typeof window !== 'undefined' && window.sbiDebug) {
                    window.sbiDebug.addEntry('info', 'FSM Transition', msg);
                }
                else {
                    // eslint-disable-next-line no-console
                    console.log(msg);
                }
            }
            catch { }
        }
        this.listeners.forEach((fn) => fn(repo, state));
    }
    // Apply state to DOM row: minimal façade that can evolve later
    applyToRow(repo, state) {
        const rowId = 'repo-' + repo.replace(/[^a-zA-Z0-9_-]/g, '-');
        const row = document.getElementById(rowId);
        if (!row)
            return;
        // Minimal UX tweaks; full rendering is still server-driven via row_html
        // Here we can disable/enable action buttons based on state
        const isInstalled = state === PluginState.INSTALLED_ACTIVE || state === PluginState.INSTALLED_INACTIVE;
        const installBtn = row.querySelector('.sbi-install-plugin');
        const activateBtn = row.querySelector('.sbi-activate-plugin');
        const deactivateBtn = row.querySelector('.sbi-deactivate-plugin');
        if (installBtn)
            installBtn.disabled = isInstalled; // hide/disable install if installed
        if (activateBtn)
            activateBtn.disabled = state !== PluginState.INSTALLED_INACTIVE;
        if (deactivateBtn)
            deactivateBtn.disabled = state !== PluginState.INSTALLED_ACTIVE;
    }
}
export const repositoryFSM = new RepositoryFSM();
