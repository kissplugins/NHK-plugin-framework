<?php
namespace NHK\Poc\Admin;

use NHK\Poc\Core\FSM\StateMachine;

/**
 * Registers admin menus and pages.
 * Each page interacts with the shared FSM for predictable flows.
 */
class Menu {
    public static function init( StateMachine $sm ): void {
        add_action( 'admin_menu', function () use ( $sm ) {
            add_menu_page(
                'NHK PoC',
                'NHK PoC',
                'manage_options',
                'nhk-poc',
                [ self::class, 'render_dashboard' ],
                'dashicons-admin-generic'
            );

            add_submenu_page(
                'nhk-poc',
                'Dashboard',
                'Dashboard',
                'manage_options',
                'nhk-poc',
                [ self::class, 'render_dashboard' ]
            );

            add_submenu_page(
                'nhk-poc',
                'Settings',
                'Settings',
                'manage_options',
                'nhk-poc-settings',
                [ self::class, 'render_settings' ]
            );

            add_submenu_page(
                'nhk-poc',
                'Changelog - v' . NHK_POC_VERSION,
                'Changelog - v' . NHK_POC_VERSION,
                'manage_options',
                'nhk-poc-changelog',
                [ self::class, 'render_changelog' ]
            );
        } );

        // Enqueue shared FSM script on plugin pages and pass serialized machine state.
        add_action( 'admin_enqueue_scripts', function ( $hook ) use ( $sm ) {
            if ( false === strpos( $hook, 'nhk-poc' ) ) {
                return;
            }
            wp_enqueue_script( 'nhk-poc-fsm', NHK_POC_URL . 'assets/dist/fsm.js', [], NHK_POC_VERSION, true );
            wp_localize_script( 'nhk-poc-fsm', 'nhkPocState', $sm->serialize() );
        } );
    }

    public static function render_dashboard(): void {
        echo '<div class="wrap"><h1>NHK PoC Dashboard</h1><div id="nhk-poc-dashboard"></div></div>';
    }

    public static function render_settings(): void {
        echo '<div class="wrap"><h1>Settings</h1><p>FSM-driven settings page.</p></div>';
    }

    public static function render_changelog(): void {
        $file = NHK_POC_PATH . 'changelog.md';
        $content = file_exists( $file ) ? wp_kses_post( nl2br( file_get_contents( $file ) ) ) : __( 'No changelog available', 'nhk-poc' );
        echo '<div class="wrap"><h1>Changelog</h1><div class="nhk-poc-changelog">' . $content . '</div></div>';
    }
}
