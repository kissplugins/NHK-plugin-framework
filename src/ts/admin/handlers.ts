import type {
  InstallPluginSuccessData,
  InstallPluginRequest,
  ActivatePluginRequest,
  DeactivatePluginRequest,
  RefreshStatusRequest,
  RefreshStatusSuccessData,
  WpAjaxResponse,
} from '../types/ajax';
import type { SbiAjax } from '../types/wp-globals';
import { wpAjaxFetch } from '../lib/ajaxClient';

function requireAjax(windowObj: Window): SbiAjax {
  const w = windowObj as any as { sbiAjax?: SbiAjax };
  if (!w.sbiAjax) throw new Error('sbiAjax not found on window');
  return w.sbiAjax;
}

// Utility to satisfy Record<string, unknown> requirement when calling fetch
function asPayload(obj: unknown): Record<string, unknown> {
  return obj as Record<string, unknown>;
}

export async function installPlugin(
  windowObj: Window,
  owner: string,
  repository: string,
  activate: boolean = false
): Promise<WpAjaxResponse<InstallPluginSuccessData>> {
  const sbiAjax = requireAjax(windowObj);
  const payload: InstallPluginRequest = {
    action: 'sbi_install_plugin',
    owner,
    repository,
    activate,
    nonce: sbiAjax.nonce,
  };
  return wpAjaxFetch(windowObj, asPayload(payload));
}

export async function activatePlugin(
  windowObj: Window,
  repository: string,
  plugin_file: string
): Promise<WpAjaxResponse<{ message?: string }>> {
  const sbiAjax = requireAjax(windowObj);
  const payload: ActivatePluginRequest = {
    action: 'sbi_activate_plugin',
    repository,
    plugin_file,
    nonce: sbiAjax.nonce,
  };
  return wpAjaxFetch(windowObj, asPayload(payload));
}

export async function deactivatePlugin(
  windowObj: Window,
  repository: string,
  plugin_file: string
): Promise<WpAjaxResponse<{ message?: string }>> {
  const sbiAjax = requireAjax(windowObj);
  const payload: DeactivatePluginRequest = {
    action: 'sbi_deactivate_plugin',
    repository,
    plugin_file,
    nonce: sbiAjax.nonce,
  };
  return wpAjaxFetch(windowObj, asPayload(payload));
}

export async function refreshStatus(
  windowObj: Window,
  repositories: string[]
): Promise<WpAjaxResponse<RefreshStatusSuccessData>> {
  const sbiAjax = requireAjax(windowObj);
  const payload: RefreshStatusRequest = {
    action: 'sbi_refresh_status',
    repositories,
    nonce: sbiAjax.nonce,
  };
  return wpAjaxFetch(windowObj, asPayload(payload));
}

