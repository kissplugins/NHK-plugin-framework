// Module bridge to expose TS handlers on window for classic admin.js
// This script is enqueued as type="module" and dynamically imports the TS index

(async () => {
  try {
    const indexUrl = (window && window.sbiTs && window.sbiTs.indexUrl) || '';
    if (!indexUrl) return;
    const mod = await import(indexUrl);
    // Expose a stable global used by admin.js
    window.SBIts = {
      installPlugin: (win, owner, repository, activate = false) => mod.installPlugin(win, owner, repository, activate),
      activatePlugin: (win, repository, plugin_file) => mod.activatePlugin(win, repository, plugin_file),
      deactivatePlugin: (win, repository, plugin_file) => mod.deactivatePlugin(win, repository, plugin_file),
      refreshStatus: (win, repositories) => mod.refreshStatus(win, repositories),
    };
  } catch (e) {
    // Swallow errors; admin.js will fallback gracefully
    if (window && window.sbiDebug && typeof window.sbiDebug.addEntry === 'function') {
      window.sbiDebug.addEntry('error', 'TS Bridge Load Failed', String(e));
    }
  }
})();

