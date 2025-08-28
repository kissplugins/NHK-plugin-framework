import { PluginState } from '../types/fsm';
export class RepositoryFSM {
    constructor() {
        this.states = new Map();
        this.listeners = new Set();
        this.eventSource = null;
        this.sseEnabled = false;
        this.errorContexts = new Map();
        this.maxRetries = 3;
        this.retryDelayMs = 5000;
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
        // Use data-repository attribute for more resilient DOM targeting
        const row = document.querySelector(`[data-repository="${repo}"]`);
        if (!row) {
            this.debugLog(`Row not found for repository: ${repo}`, 'error');
            return;
        }
        // Enhanced UX with error handling and recovery options
        const isInstalled = state === PluginState.INSTALLED_ACTIVE || state === PluginState.INSTALLED_INACTIVE;
        const isError = state === PluginState.ERROR;
        const installBtn = row.querySelector('.sbi-install-plugin');
        const activateBtn = row.querySelector('.sbi-activate-plugin');
        const deactivateBtn = row.querySelector('.sbi-deactivate-plugin');
        // Standard button state management
        if (installBtn)
            installBtn.disabled = isInstalled || isError;
        if (activateBtn)
            activateBtn.disabled = state !== PluginState.INSTALLED_INACTIVE;
        if (deactivateBtn)
            deactivateBtn.disabled = state !== PluginState.INSTALLED_ACTIVE;
        // Enhanced error state handling
        if (isError) {
            this.handleErrorState(row, repo);
        }
        else {
            this.clearErrorDisplay(row);
        }
    }
    // SSE Integration Methods
    initSSE(windowObj) {
        const w = windowObj;
        if (!w.sbiAjax || this.eventSource)
            return;
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
                }
                catch (err) {
                    this.debugLog(`SSE event parsing error: ${err}`, 'error');
                }
            });
        }
        catch (err) {
            this.debugLog(`SSE initialization error: ${err}`, 'error');
        }
    }
    closeSSE() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
            this.debugLog('SSE connection closed');
        }
    }
    stringToPluginState(stateStr) {
        const stateMap = {
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
    debugLog(message, level = 'info') {
        try {
            const fullMessage = `[RepositoryFSM] ${message}`;
            if (typeof window !== 'undefined' && window.sbiDebug) {
                window.sbiDebug.addEntry(level, 'FSM SSE', fullMessage);
            }
            else {
                // eslint-disable-next-line no-console
                console.log(fullMessage);
            }
        }
        catch { }
    }
    // Helper method to check if a repository is in a specific state
    isInState(repo, state) {
        return this.get(repo) === state;
    }
    // Enhanced Error Handling Methods
    setError(repo, message, source, recoverable = true) {
        const errorContext = {
            timestamp: Date.now(),
            message,
            source,
            retryCount: 0,
            recoverable,
        };
        this.errorContexts.set(repo, errorContext);
        this.set(repo, PluginState.ERROR);
        this.debugLog(`Error set for ${repo}: ${message} (source: ${source}, recoverable: ${recoverable})`, 'error');
    }
    getErrorContext(repo) {
        return this.errorContexts.get(repo) || null;
    }
    canRetry(repo) {
        const errorContext = this.errorContexts.get(repo);
        if (!errorContext || !errorContext.recoverable)
            return false;
        return errorContext.retryCount < this.maxRetries;
    }
    async retryRepository(repo) {
        const errorContext = this.errorContexts.get(repo);
        if (!errorContext || !this.canRetry(repo)) {
            this.debugLog(`Cannot retry ${repo}: ${!errorContext ? 'no error context' : 'max retries exceeded'}`, 'error');
            return false;
        }
        // Update retry context
        errorContext.retryCount++;
        errorContext.lastRetryAt = Date.now();
        this.errorContexts.set(repo, errorContext);
        this.debugLog(`Retrying ${repo} (attempt ${errorContext.retryCount}/${this.maxRetries})`);
        try {
            // Transition back to CHECKING state to restart the process
            this.set(repo, PluginState.CHECKING);
            // Trigger a refresh via the backend
            if (typeof window !== 'undefined' && window.sbiAjax) {
                const response = await fetch(window.sbiAjax.ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'sbi_refresh_repository',
                        repository: repo,
                        nonce: window.sbiAjax.nonce,
                    }),
                });
                if (response.ok) {
                    this.debugLog(`Retry initiated for ${repo}`);
                    return true;
                }
                else {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
            }
        }
        catch (error) {
            this.debugLog(`Retry failed for ${repo}: ${error}`, 'error');
            this.setError(repo, `Retry failed: ${error}`, 'retry_mechanism', errorContext.recoverable);
        }
        return false;
    }
    clearError(repo) {
        this.errorContexts.delete(repo);
        this.debugLog(`Error context cleared for ${repo}`);
    }
    getErrorStatistics() {
        let total = 0;
        let recoverable = 0;
        let maxRetriesReached = 0;
        for (const [repo, context] of this.errorContexts) {
            total++;
            if (context.recoverable)
                recoverable++;
            if (context.retryCount >= this.maxRetries)
                maxRetriesReached++;
        }
        return { total, recoverable, maxRetriesReached };
    }
    // Helper method to check if a repository is in any of the given states
    isInAnyState(repo, states) {
        const currentState = this.get(repo);
        return currentState ? states.includes(currentState) : false;
    }
    // Error Display Management
    handleErrorState(row, repo) {
        const errorContext = this.getErrorContext(repo);
        if (!errorContext)
            return;
        // Find or create error display container
        let errorContainer = row.querySelector('.sbi-error-display');
        if (!errorContainer) {
            errorContainer = document.createElement('div');
            errorContainer.className = 'sbi-error-display';
            errorContainer.style.cssText = 'background: #ffeaea; border: 1px solid #d63638; padding: 8px; margin: 4px 0; border-radius: 3px; font-size: 12px;';
            // Insert after the first cell
            const firstCell = row.querySelector('td');
            if (firstCell) {
                firstCell.appendChild(errorContainer);
            }
        }
        // Build error message with context
        const timeAgo = this.formatTimeAgo(errorContext.timestamp);
        let errorHtml = `<strong>Error:</strong> ${errorContext.message}<br>`;
        errorHtml += `<small>Source: ${errorContext.source} • ${timeAgo}</small>`;
        if (errorContext.retryCount > 0) {
            errorHtml += `<br><small>Retries: ${errorContext.retryCount}/${this.maxRetries}</small>`;
        }
        // Add retry button if recoverable
        if (errorContext.recoverable && this.canRetry(repo)) {
            errorHtml += `<br><button class="button button-small sbi-retry-btn" data-repo="${repo}" style="margin-top: 4px;">Retry</button>`;
        }
        else if (!errorContext.recoverable) {
            errorHtml += `<br><small style="color: #d63638;">Non-recoverable error</small>`;
        }
        else {
            errorHtml += `<br><small style="color: #d63638;">Max retries reached</small>`;
        }
        errorContainer.innerHTML = errorHtml;
        // Attach retry handler
        const retryBtn = errorContainer.querySelector('.sbi-retry-btn');
        if (retryBtn) {
            retryBtn.onclick = () => this.handleRetryClick(repo, retryBtn);
        }
    }
    clearErrorDisplay(row) {
        const errorContainer = row.querySelector('.sbi-error-display');
        if (errorContainer) {
            errorContainer.remove();
        }
    }
    async handleRetryClick(repo, button) {
        button.disabled = true;
        button.textContent = 'Retrying...';
        const success = await this.retryRepository(repo);
        if (!success) {
            button.disabled = false;
            button.textContent = 'Retry Failed';
            setTimeout(() => {
                if (this.canRetry(repo)) {
                    button.textContent = 'Retry';
                    button.disabled = false;
                }
            }, 3000);
        }
        // If successful, the error display will be cleared by state change
    }
    formatTimeAgo(timestamp) {
        const seconds = Math.floor((Date.now() - timestamp) / 1000);
        if (seconds < 60)
            return `${seconds}s ago`;
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60)
            return `${minutes}m ago`;
        const hours = Math.floor(minutes / 60);
        return `${hours}h ago`;
    }
}
export const repositoryFSM = new RepositoryFSM();
