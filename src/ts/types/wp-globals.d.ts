import type { ProgressUpdate } from './ajax';

export interface SbiAjax {
  ajaxurl: string;
  nonce: string;
  strings: Record<string, string>;
}

export interface SbiDebug {
  addEntry: (
    level: 'info' | 'success' | 'warning' | 'error',
    title: string,
    message: string
  ) => void;
}

declare global {
  interface Window {
    sbiAjax?: SbiAjax;
    sbiDebug?: SbiDebug;
  }
}

export {};

