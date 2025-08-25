import { wpAjaxFetch } from '../lib/ajaxClient';
function requireAjax(windowObj) {
    const w = windowObj;
    if (!w.sbiAjax)
        throw new Error('sbiAjax not found on window');
    return w.sbiAjax;
}
// Utility to satisfy Record<string, unknown> requirement when calling fetch
function asPayload(obj) {
    return obj;
}
export async function installPlugin(windowObj, owner, repository, activate = false) {
    const sbiAjax = requireAjax(windowObj);
    const payload = {
        action: 'sbi_install_plugin',
        owner,
        repository,
        activate,
        nonce: sbiAjax.nonce,
    };
    return wpAjaxFetch(windowObj, asPayload(payload));
}
export async function activatePlugin(windowObj, repository, plugin_file) {
    const sbiAjax = requireAjax(windowObj);
    const payload = {
        action: 'sbi_activate_plugin',
        repository,
        plugin_file,
        nonce: sbiAjax.nonce,
    };
    return wpAjaxFetch(windowObj, asPayload(payload));
}
export async function deactivatePlugin(windowObj, repository, plugin_file) {
    const sbiAjax = requireAjax(windowObj);
    const payload = {
        action: 'sbi_deactivate_plugin',
        repository,
        plugin_file,
        nonce: sbiAjax.nonce,
    };
    return wpAjaxFetch(windowObj, asPayload(payload));
}
export async function refreshStatus(windowObj, repositories) {
    const sbiAjax = requireAjax(windowObj);
    const payload = {
        action: 'sbi_refresh_status',
        repositories,
        nonce: sbiAjax.nonce,
    };
    return wpAjaxFetch(windowObj, asPayload(payload));
}
