<?php
/**
 * Plugin Name: KISS Smart Batch Installer - NHK Codex Edition
 * Plugin URI: https://github.com/sbi/github-batch-installer
 * Description: Batch install WordPress plugins from GitHub repositories.
 * Version: 1.0.0
 * Author: SBI Development Team
 * Author URI: https://sbi.local
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: github-batch-installer
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * @package SBI
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

// Plugin constants
const GBI_VERSION  = '1.0.0';
const GBI_FILE     = __FILE__;
const GBI_PATH     = __DIR__ . '/';
const GBI_URL      = plugin_dir_url( __FILE__ );
const GBI_BASENAME = plugin_basename( __FILE__ );

// Load NHK Framework if available
if ( ! class_exists( 'NHK\\Framework\\Container\\Container' ) ) {
    $framework_autoloader = GBI_PATH . 'framework/autoload.php';
    if ( file_exists( $framework_autoloader ) ) {
        require_once $framework_autoloader;
    } else {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'GitHub Batch Installer requires the NHK Framework to function properly.', 'github-batch-installer' );
            echo '</p></div>';
        } );
        return;
    }
}

// PSR-4 autoloader for plugin classes
spl_autoload_register( function ( $class ) {
    $prefix   = 'SBI\\';
    $base_dir = GBI_PATH . 'src/';

    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, $len );
    $file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

    if ( file_exists( $file ) ) {
        require $file;
    }
} );

/**
 * Initialize the plugin.
 */
function sbi_init() {
    try {
        $container = new SBI\Container();
        $container->singleton( SBI\Container::class, function () use ( $container ) {
            return $container;
        } );

        $plugin = $container->get( SBI\Plugin::class );
        $plugin->init();

        $GLOBALS['sbi_container'] = $container;
    } catch ( Exception $e ) {
        if ( WP_DEBUG_LOG ) {
            error_log( '[SBI] Initialization failed: ' . $e->getMessage() );
        }
        add_action( 'admin_notices', function () use ( $e ) {
            echo '<div class="notice notice-error"><p>';
            echo esc_html( sprintf( __( 'GitHub Batch Installer failed to initialize: %s', 'github-batch-installer' ), $e->getMessage() ) );
            echo '</p></div>';
        } );
    }
}

/** Activation hook */
function sbi_activate() {
    $plugin = sbi_container()->get( SBI\Plugin::class );
    $plugin->activate();
}

/** Deactivation hook */
function sbi_deactivate() {
    $plugin = sbi_container()->get( SBI\Plugin::class );
    $plugin->deactivate();
}

/**
 * Register hooks.
 */
function sbi_register_hooks() {
    register_activation_hook( __FILE__, 'sbi_activate' );
    register_deactivation_hook( __FILE__, 'sbi_deactivate' );
    add_action( 'plugins_loaded', 'sbi_init', 10 );
}

sbi_register_hooks();

/** Container helper */
function sbi_container() {
    return $GLOBALS['sbi_container'] ?? null;
}

/** Service helper */
function sbi_service( string $service ) {
    $container = sbi_container();
    return $container ? $container->get( $service ) : null;
}
