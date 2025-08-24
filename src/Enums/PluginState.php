<?php
/**
 * Enumeration of plugin states.
 *
 * @package SBI\Enums
 */

namespace SBI\Enums;

enum PluginState: string {
    case UNKNOWN = 'unknown';            // Haven't checked yet
    case CHECKING = 'checking';          // Currently being analyzed
    case AVAILABLE = 'available';        // Is a WP plugin, can install
    case NOT_PLUGIN = 'not_plugin';      // Repository exists but not a WP plugin
    case INSTALLED_INACTIVE = 'installed_inactive'; // Installed but not active
    case INSTALLED_ACTIVE = 'installed_active';     // Installed and active
    case ERROR = 'error';                // Error occurred during processing
}
