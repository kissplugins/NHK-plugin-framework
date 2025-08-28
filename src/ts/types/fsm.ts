export enum PluginState {
  UNKNOWN = 'unknown',
  CHECKING = 'checking',
  AVAILABLE = 'available',
  NOT_PLUGIN = 'not_plugin',
  INSTALLING = 'installing',
  INSTALLED_INACTIVE = 'installed_inactive',
  INSTALLED_ACTIVE = 'installed_active',
  ERROR = 'error',
}

export const isInstalled = (s: PluginState) =>
  s === PluginState.INSTALLED_ACTIVE || s === PluginState.INSTALLED_INACTIVE;

export const isPluginByState = (s: PluginState) =>
  s === PluginState.AVAILABLE || isInstalled(s);

