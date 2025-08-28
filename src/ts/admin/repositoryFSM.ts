import { PluginState } from '../types/fsm';

export type RepoId = string; // owner/repo

type Listener = (repo: RepoId, state: PluginState) => void;

type StateMap = Map<RepoId, PluginState>;

export class RepositoryFSM {
  private states: StateMap = new Map();
  private listeners: Set<Listener> = new Set();
  private eventSource: EventSource | null = null;
  private sseEnabled = false;

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
    // Always enable debug logging in development (browser environment)
    if (true) {
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

  // SSE Integration Methods
  initSSE(windowObj: Window): void {
    const w = windowObj as any;
    if (!w.sbiAjax || this.eventSource) return;

    // Check if SSE is enabled
    this.sseEnabled = !!w.sbiAjax.sseEnabled;
    if (!this.sseEnabled) {
      this.debugLog('SSE disabled in configuration');
      return;
    }

    try {
      const sseUrl = w.sbiAjax.ajaxurl + '?action=sbi_state_stream';
      this.eventSource = new EventSource(sseUrl);

      this.eventSource.addEventListener('open', () => {
        this.debugLog('SSE connection opened');
      });

      this.eventSource.addEventListener('error', (e) => {
        this.debugLog('SSE connection error', 'error');
        // Auto-reconnect is handled by EventSource
      });

      this.eventSource.addEventListener('state_changed', (e) => {
        try {
          const payload = JSON.parse(e.data || '{}');
          const repo = payload.repository;
          const toState = payload.to;

          if (repo && toState) {
            this.debugLog(`SSE state update: ${repo} -> ${toState}`);
            // Convert string state to PluginState enum
            const state = this.stringToPluginState(toState);
            if (state) {
              this.set(repo, state);
              this.applyToRow(repo, state);
            }
          }
        } catch (err) {
          this.debugLog(`SSE event parsing error: ${err}`, 'error');
        }
      });

    } catch (err) {
      this.debugLog(`SSE initialization error: ${err}`, 'error');
    }
  }

  closeSSE(): void {
    if (this.eventSource) {
      this.eventSource.close();
      this.eventSource = null;
      this.debugLog('SSE connection closed');
    }
  }

  private stringToPluginState(stateStr: string): PluginState | null {
    const stateMap: Record<string, PluginState> = {
      'unknown': PluginState.UNKNOWN,
      'checking': PluginState.CHECKING,
      'available': PluginState.AVAILABLE,
      'not_plugin': PluginState.NOT_PLUGIN,
      'installed_inactive': PluginState.INSTALLED_INACTIVE,
      'installed_active': PluginState.INSTALLED_ACTIVE,
      'installing': PluginState.INSTALLING,
      'error': PluginState.ERROR,
    };
    return stateMap[stateStr] || null;
  }

  private debugLog(message: string, level: 'info' | 'error' = 'info'): void {
    try {
      const fullMessage = `[RepositoryFSM] ${message}`;
      if (typeof window !== 'undefined' && (window as any).sbiDebug) {
        (window as any).sbiDebug.addEntry(level, 'FSM SSE', fullMessage);
      } else {
        // eslint-disable-next-line no-console
        console.log(fullMessage);
      }
    } catch {}
  }

  // Helper method to check if a repository is in a specific state
  isInState(repo: RepoId, state: PluginState): boolean {
    return this.get(repo) === state;
  }

  // Helper method to check if a repository is in any of the given states
  isInAnyState(repo: RepoId, states: PluginState[]): boolean {
    const currentState = this.get(repo);
    return currentState ? states.includes(currentState) : false;
  }
}

export const repositoryFSM = new RepositoryFSM();

