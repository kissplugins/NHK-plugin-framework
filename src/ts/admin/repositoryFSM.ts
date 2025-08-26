import { PluginState } from '../types/fsm';

export type RepoId = string; // owner/repo

type Listener = (repo: RepoId, state: PluginState) => void;

type StateMap = Map<RepoId, PluginState>;

export class RepositoryFSM {
  private states: StateMap = new Map();
  private listeners: Set<Listener> = new Set();

  onChange(listener: Listener): () => void {
    this.listeners.add(listener);
    return () => this.listeners.delete(listener);
  }

  get(repo: RepoId): PluginState | undefined {
    return this.states.get(repo);
  }

  set(repo: RepoId, state: PluginState): void {
    const prev = this.states.get(repo);
    this.states.set(repo, state);
    if (process.env.NODE_ENV !== 'production') {
      try {
        // Preserve/improve debug output
        const msg = `[RepositoryFSM] ${repo}: ${prev ?? '∅'} -> ${state}`;
        if (typeof window !== 'undefined' && (window as any).sbiDebug) {
          (window as any).sbiDebug.addEntry('info', 'FSM Transition', msg);
        } else {
          // eslint-disable-next-line no-console
          console.log(msg);
        }
      } catch {}
    }
    this.listeners.forEach((fn) => fn(repo, state));
  }

  // Apply state to DOM row: minimal façade that can evolve later
  applyToRow(repo: RepoId, state: PluginState): void {
    const rowId = 'repo-' + repo.replace(/[^a-zA-Z0-9_-]/g, '-');
    const row = document.getElementById(rowId);
    if (!row) return;

    // Minimal UX tweaks; full rendering is still server-driven via row_html
    // Here we can disable/enable action buttons based on state
    const isInstalled = state === PluginState.INSTALLED_ACTIVE || state === PluginState.INSTALLED_INACTIVE;

    const installBtn = row.querySelector('.sbi-install-plugin') as HTMLButtonElement | null;
    const activateBtn = row.querySelector('.sbi-activate-plugin') as HTMLButtonElement | null;
    const deactivateBtn = row.querySelector('.sbi-deactivate-plugin') as HTMLButtonElement | null;

    if (installBtn) installBtn.disabled = isInstalled; // hide/disable install if installed
    if (activateBtn) activateBtn.disabled = state !== PluginState.INSTALLED_INACTIVE;
    if (deactivateBtn) deactivateBtn.disabled = state !== PluginState.INSTALLED_ACTIVE;
  }
}

export const repositoryFSM = new RepositoryFSM();

