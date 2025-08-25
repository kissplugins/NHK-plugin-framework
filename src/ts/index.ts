/*
 Minimal TypeScript scaffold for KISS Smart Batch Installer
 Phase 0: Do not replace existing assets/admin.js yet
*/

export enum PluginState {
  UNKNOWN = 'unknown',
  CHECKING = 'checking',
  AVAILABLE = 'available',
  NOT_PLUGIN = 'not_plugin',
  INSTALLED_INACTIVE = 'installed_inactive',
  INSTALLED_ACTIVE = 'installed_active',
  ERROR = 'error',
}

export interface SbiAjax {
  ajaxurl: string;
  nonce: string;
  strings: Record<string, string>;
}

declare global {
  interface Window {
    sbiDebug?: {
      addEntry: (level: 'info' | 'success' | 'warning' | 'error', title: string, message: string) => void;
    };
    sbiAjax?: SbiAjax;
  }
}

// Smoke test to ensure bundling works when later integrated
export function tsScaffoldHello(): string {
  const hasAjax = typeof window !== 'undefined' && !!window.sbiAjax;
  return `TS scaffold ready. sbiAjax loaded: ${hasAjax}`;
}

