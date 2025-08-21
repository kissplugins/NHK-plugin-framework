<?php
/**
 * Plugin Name: NHK Event Manager
 * Plugin URI: https://github.com/nhkode/nhk-event-manager
 * Description: A comprehensive event management plugin demonstrating the NHK Framework. Features include custom post types, taxonomies, REST API, shortcodes, background jobs, health checks, and more.
 * Version: 1.0.0
 * Author: NHK Development Team
 * Author URI: https://nhkode.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: nhk-event-manager
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.4
 * Requires PHP: 8.0
 * Network: false
 *
 * @package NHK\EventManager
 * @since 1.0.0
 */

// Prevent direct access
defined('ABSPATH') || exit;

// Define plugin constants
define('NHK_EVENT_MANAGER_VERSION', '1.0.0');
define('NHK_EVENT_MANAGER_FILE', __FILE__);
define('NHK_EVENT_MANAGER_PATH', plugin_dir_path(__FILE__));
define('NHK_EVENT_MANAGER_URL', plugin_dir_url(__FILE__));
define('NHK_EVENT_MANAGER_BASENAME', plugin_basename(__FILE__));

/**
 * Check if the NHK Framework is available
 *
 * This plugin requires the NHK Framework to function properly.
 * The framework should be included in the plugin or available as a separate plugin.
 */
if (!class_exists('NHK\\Framework\\Container\\Container')) {
    // Try to load the framework from the plugin directory
    $framework_autoloader = NHK_EVENT_MANAGER_PATH . 'framework/autoload.php';

    if (file_exists($framework_autoloader)) {
        require_once $framework_autoloader;
    } else {
        // Framework not found, show admin notice
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('NHK Event Manager requires the NHK Framework to function properly. Please ensure the framework is installed.', 'nhk-event-manager');
            echo '</p></div>';
        });
        return;
    }
}

/**
 * Autoloader for plugin classes
 *
 * Demonstrates PSR-4 autoloading for plugin classes.
 */
spl_autoload_register(function ($class) {
    $prefix = 'NHK\\EventManager\\';
    $base_dir = NHK_EVENT_MANAGER_PATH . 'src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Main plugin initialization
 *
 * Demonstrates proper WordPress plugin initialization with dependency injection.
 */
function nhk_event_manager_init() {
    try {
        // Create service container
        $container = new NHK\Framework\Container\Container();

        // Register framework services
        $container->singleton(NHK\Framework\Container\Container::class, function() use ($container) {
            return $container;
        });

        // Create and initialize the main plugin instance
        $plugin = $container->get(NHK\EventManager\Core\Plugin::class);
        $plugin->init();

        // Store container globally for access by other components
        $GLOBALS['nhk_event_manager_container'] = $container;

    } catch (Exception $e) {
        // Log initialization error
        if (WP_DEBUG_LOG) {
            error_log('[NHK Event Manager] Initialization failed: ' . $e->getMessage());
        }

        // Show admin notice
        add_action('admin_notices', function() use ($e) {
            echo '<div class="notice notice-error"><p>';
            echo esc_html(sprintf(
                __('NHK Event Manager failed to initialize: %s', 'nhk-event-manager'),
                $e->getMessage()
            ));
            echo '</p></div>';
        });
    }
}

/**
 * Plugin activation hook
 *
 * Demonstrates proper plugin activation with database setup and initial configuration.
 */
function nhk_event_manager_activate() {
    // Flush rewrite rules to ensure CPT URLs work
    flush_rewrite_rules();

    // Set default options
    $default_options = [
        'nhk_event_default_duration' => 2,
        'nhk_event_default_capacity' => 100,
        'nhk_event_currency_symbol' => '$',
        'nhk_event_date_format' => 'F j, Y',
        'nhk_event_time_format' => 'g:i A',
        'nhk_event_events_per_page' => 10,
        'nhk_event_show_past_events' => false,
        'nhk_event_default_sort' => 'date_asc',
        'nhk_event_enable_reminders' => false,
        'nhk_event_reminder_timing' => '1_day',
        'nhk_event_admin_email' => get_option('admin_email'),
        'nhk_event_enable_debug' => false,
        'nhk_event_debug_level' => 'error',
        'nhk_event_cache_duration' => 3600,
    ];

    foreach ($default_options as $option => $value) {
        if (get_option($option) === false) {
            add_option($option, $value);
        }
    }

    // Set plugin version
    update_option('nhk_event_manager_version', NHK_EVENT_MANAGER_VERSION);

    // Log activation
    if (WP_DEBUG_LOG) {
        error_log('[NHK Event Manager] Plugin activated successfully');
    }
}
/**
 * Plugin deactivation hook
 *
 * Demonstrates proper cleanup on plugin deactivation.
 */
function nhk_event_manager_deactivate() {
    // Clear scheduled events
    wp_clear_scheduled_hook('nhk_job_event_reminder');
    wp_clear_scheduled_hook('nhk_job_cache_cleanup');
    wp_clear_scheduled_hook('nhk_daily_health_check');

    // Flush rewrite rules
    flush_rewrite_rules();

    // Log deactivation
    if (WP_DEBUG_LOG) {
        error_log('[NHK Event Manager] Plugin deactivated');
    }
}

/**
 * Plugin uninstall cleanup
 *
 * Note: This function is called from uninstall.php, not here.
 * It's included for reference only.
 */
function nhk_event_manager_uninstall() {
    // This function is defined in uninstall.php
    // It handles complete plugin removal including data cleanup
}

/**
 * Load plugin textdomain for internationalization
 *
 * Demonstrates proper WordPress i18n implementation.
 */
function nhk_event_manager_load_textdomain() {
    load_plugin_textdomain(
        'nhk-event-manager',
        false,
        dirname(NHK_EVENT_MANAGER_BASENAME) . '/languages'
    );
}

/**
 * Add plugin action links
 *
 * Demonstrates adding custom links to the plugin list page.
 */
function nhk_event_manager_action_links($links) {
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        admin_url('admin.php?page=nhk-event-settings'),
        __('Settings', 'nhk-event-manager')
    );

    $docs_link = sprintf(
        '<a href="%s" target="_blank">%s</a>',
        'https://github.com/nhkode/nhk-event-manager/wiki',
        __('Documentation', 'nhk-event-manager')
    );

    array_unshift($links, $settings_link, $docs_link);

    return $links;
}

/**
 * Add plugin meta links
 *
 * Demonstrates adding custom meta links to the plugin list page.
 */
function nhk_event_manager_meta_links($links, $file) {
    if ($file === NHK_EVENT_MANAGER_BASENAME) {
        $links[] = sprintf(
            '<a href="%s" target="_blank">%s</a>',
            'https://github.com/nhkode/nhk-event-manager',
            __('GitHub', 'nhk-event-manager')
        );

        $links[] = sprintf(
            '<a href="%s" target="_blank">%s</a>',
            'https://github.com/nhkode/nhk-event-manager/issues',
            __('Support', 'nhk-event-manager')
        );
    }

    return $links;
}

/**
 * Check WordPress and PHP version requirements
 *
 * Demonstrates proper version checking for plugin compatibility.
 */
function nhk_event_manager_check_requirements() {
    global $wp_version;

    $min_wp_version = '6.0';
    $min_php_version = '8.0';

    $errors = [];

    // Check WordPress version
    if (version_compare($wp_version, $min_wp_version, '<')) {
        $errors[] = sprintf(
            __('NHK Event Manager requires WordPress %s or higher. You are running version %s.', 'nhk-event-manager'),
            $min_wp_version,
            $wp_version
        );
    }

    // Check PHP version
    if (version_compare(PHP_VERSION, $min_php_version, '<')) {
        $errors[] = sprintf(
            __('NHK Event Manager requires PHP %s or higher. You are running version %s.', 'nhk-event-manager'),
            $min_php_version,
            PHP_VERSION
        );
    }

    if (!empty($errors)) {
        add_action('admin_notices', function() use ($errors) {
            foreach ($errors as $error) {
                echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
            }
        });

        return false;
    }

    return true;
}

/**
 * Register WordPress hooks
 *
 * Demonstrates proper hook registration for WordPress plugins.
 */
function nhk_event_manager_register_hooks() {
    // Core plugin hooks
    register_activation_hook(__FILE__, 'nhk_event_manager_activate');
    register_deactivation_hook(__FILE__, 'nhk_event_manager_deactivate');

    // Plugin list page hooks
    add_filter('plugin_action_links_' . NHK_EVENT_MANAGER_BASENAME, 'nhk_event_manager_action_links');
    add_filter('plugin_row_meta', 'nhk_event_manager_meta_links', 10, 2);

    // Internationalization
    add_action('plugins_loaded', 'nhk_event_manager_load_textdomain');

    // Initialize plugin after WordPress is fully loaded
    add_action('plugins_loaded', 'nhk_event_manager_init', 10);
}

// Check requirements and register hooks
if (nhk_event_manager_check_requirements()) {
    nhk_event_manager_register_hooks();
}

/**
 * Helper function to get the plugin container
 *
 * Provides global access to the service container for other components.
 *
 * @return NHK\Framework\Container\Container|null
 */
function nhk_event_manager_container() {
    return $GLOBALS['nhk_event_manager_container'] ?? null;
}

/**
 * Helper function to get a service from the container
 *
 * @param string $service Service class name
 * @return mixed Service instance
 */
function nhk_event_manager_service(string $service) {
    $container = nhk_event_manager_container();
    return $container ? $container->get($service) : null;
}

/**
 * Debug helper function
 *
 * Logs debug messages if debugging is enabled.
 *
 * @param string $message Debug message
 * @param string $level Log level
 * @return void
 */
function nhk_event_manager_debug(string $message, string $level = 'info') {
    if (get_option('nhk_event_enable_debug', false) && WP_DEBUG_LOG) {
        error_log(sprintf('[NHK Event Manager] %s: %s', strtoupper($level), $message));
    }
}
