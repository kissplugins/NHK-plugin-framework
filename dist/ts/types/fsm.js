export var PluginState;
(function (PluginState) {
    PluginState["UNKNOWN"] = "unknown";
    PluginState["CHECKING"] = "checking";
    PluginState["AVAILABLE"] = "available";
    PluginState["NOT_PLUGIN"] = "not_plugin";
    PluginState["INSTALLING"] = "installing";
    PluginState["INSTALLED_INACTIVE"] = "installed_inactive";
    PluginState["INSTALLED_ACTIVE"] = "installed_active";
    PluginState["ERROR"] = "error";
})(PluginState || (PluginState = {}));
export const isInstalled = (s) => s === PluginState.INSTALLED_ACTIVE || s === PluginState.INSTALLED_INACTIVE;
export const isPluginByState = (s) => s === PluginState.AVAILABLE || isInstalled(s);
