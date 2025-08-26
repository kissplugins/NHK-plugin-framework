<?php
/**
 * Plugin Name: NHK PoC
 * Description: NHK proof-of-concept WordPress plugin starter using an FSM-first architecture.
 * Version: 1.0.0
 * Author: NHK
 * Text Domain: nhk-poc
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define key plugin constants.
define( 'NHK_POC_VERSION', '1.0.0' );
define( 'NHK_POC_PATH', plugin_dir_path( __FILE__ ) );
define( 'NHK_POC_URL', plugin_dir_url( __FILE__ ) );

// Load Composer autoloader for PSR-4 classes.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use NHK\Poc\Core\Plugin;

// Boot the plugin using an FSM-centric Plugin class.
add_action( 'plugins_loaded', function () {
    Plugin::get_instance()->boot();
} );

// Add settings link on Plugins listing page.
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
    $url = admin_url( 'admin.php?page=nhk-poc-settings' );
    $links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'nhk-poc' ) . '</a>';
    return $links;
} );
