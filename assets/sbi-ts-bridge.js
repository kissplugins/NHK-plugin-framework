// Module bridge to expose TS handlers on window for classic admin.js
// This script is enqueued as type="module" and dynamically imports the TS index

(async () => {
  try {
    // Prefer path relative to this bridge file to avoid global collisions
    let derivedUrl = '';
    try {
      derivedUrl = new URL('../dist/ts/index.js', import.meta.url).href;
    } catch (_) {}

    const localizedUrl = (window && window.sbiTs && window.sbiTs.indexUrl) || '';
    const indexUrl = derivedUrl || localizedUrl;

    if (!indexUrl) {
      if (window && window.sbiDebug && typeof window.sbiDebug.addEntry === 'function') {
        window.sbiDebug.addEntry('warning', 'TS Bridge Skipped', 'No indexUrl provided');
      }
      return;
    }

    // If both exist and differ, log a warning (helps detect collisions)
    if (derivedUrl && localizedUrl && derivedUrl !== localizedUrl && window && window.sbiDebug && typeof window.sbiDebug.addEntry === 'function') {
      window.sbiDebug.addEntry('warning', 'TS Bridge URL Mismatch', `derived=${derivedUrl}; localized=${localizedUrl}`);
    }

    const mod = await import(indexUrl);
    // Expose a stable global used by admin.js
    window.SBIts = {
      installPlugin: (win, owner, repository, activate = false) => mod.installPlugin(win, owner, repository, activate),
      activatePlugin: (win, repository, plugin_file) => mod.activatePlugin(win, repository, plugin_file),
      deactivatePlugin: (win, repository, plugin_file) => mod.deactivatePlugin(win, repository, plugin_file),
      refreshStatus: (win, repositories) => mod.refreshStatus(win, repositories),
      repositoryFSM: mod.repositoryFSM,
    };
  } catch (e) {
    // Swallow errors; admin.js will fallback gracefully
    const msg = (e && e.message) ? e.message : String(e);
    const details = (typeof location !== 'undefined' ? (' @ ' + location.href) : '');
    const attemptedUrl = (function(){
      try { return new URL('../dist/ts/index.js', import.meta.url).href; } catch(_) { return (window && window.sbiTs && window.sbiTs.indexUrl) || ''; }
    })();
    if (window && window.sbiDebug && typeof window.sbiDebug.addEntry === 'function') {
      window.sbiDebug.addEntry('error', 'TS Bridge Load Failed', `url=${attemptedUrl}; error=${msg}${details}`);
    }
  }
})();

