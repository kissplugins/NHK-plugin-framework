// WordPress AJAX response envelopes
export type WpAjaxSuccess<T = any> = { success: true; data: T };
export type WpAjaxError<T = any> = { success: false; data: T };
export type WpAjaxResponse<T = any> = WpAjaxSuccess<T> | WpAjaxError;

// Common debug/progress structures surfaced by server
export interface ProgressUpdate {
  step: string;
  status: 'info' | 'success' | 'warning' | 'error';
  message?: string;
  timestamp?: number;
}

export interface InstallPluginRequest {
  action: 'sbi_install_plugin';
  repository: string; // repo slug only
  owner: string;
  activate: boolean;
  nonce: string;
}

export interface InstallPluginSuccessData {
  message: string;
  repository: string;
  total_time?: number;
  debug_steps?: unknown[];
  progress_updates?: ProgressUpdate[];
  // additional fields returned by server are allowed
  [k: string]: unknown;
}

export interface ActivatePluginRequest {
  action: 'sbi_activate_plugin';
  repository: string; // repo slug only
  plugin_file: string;
  nonce: string;
}

export interface DeactivatePluginRequest {
  action: 'sbi_deactivate_plugin';
  repository: string; // repo slug only
  plugin_file: string;
  nonce: string;
}

export interface RefreshStatusRequest {
  action: 'sbi_refresh_status';
  repositories: string[]; // full_name values
  nonce: string;
}

export interface RefreshStatusResultItem {
  repository: string; // full_name
  state: string; // server enum string
}

export interface RefreshStatusSuccessData {
  results: RefreshStatusResultItem[];
}

