/*
 Minimal TypeScript scaffold for KISS Smart Batch Installer
 Phase 0: Do not replace existing assets/admin.js yet
*/
export var PluginState;
(function (PluginState) {
    PluginState["UNKNOWN"] = "unknown";
    PluginState["CHECKING"] = "checking";
    PluginState["AVAILABLE"] = "available";
    PluginState["NOT_PLUGIN"] = "not_plugin";
    PluginState["INSTALLED_INACTIVE"] = "installed_inactive";
    PluginState["INSTALLED_ACTIVE"] = "installed_active";
    PluginState["ERROR"] = "error";
})(PluginState || (PluginState = {}));
// Smoke test to ensure bundling works when later integrated
export function tsScaffoldHello() {
    const hasAjax = typeof window !== 'undefined' && !!window.sbiAjax;
    return `TS scaffold ready. sbiAjax loaded: ${hasAjax}`;
}
