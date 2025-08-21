<?php
/**
 * Main Plugin Bootstrap for GitHub Batch Installer.
 *
 * @package SBI
 */

namespace SBI;

use NHK\Framework\Core\Plugin as BasePlugin;
use SBI\Services\StateManager;
use SBI\Services\PQSIntegration;

/**
 * Plugin class coordinating all components.
 */
class Plugin extends BasePlugin {
    /**
     * Setup core plugin properties.
     */
    protected function setup_properties(): void {
        $this->version      = GBI_VERSION;
        $this->text_domain  = 'github-batch-installer';
        $this->plugin_file  = GBI_FILE;
        $this->plugin_path  = plugin_dir_path($this->plugin_file);
        $this->plugin_url   = plugin_dir_url($this->plugin_file);
    }

    /**
     * Register services with the container.
     */
    protected function register_services(): void {
        $this->container->singleton(StateManager::class);
        $this->container->singleton(PQSIntegration::class);
    }

    /**
     * Setup WordPress hooks.
     */
    protected function setup_hooks(): void {
        add_action('admin_menu', [ $this, 'register_admin_page' ]);
    }

    /**
     * Register admin menu page.
     */
    public function register_admin_page(): void {
        add_menu_page(
            __( 'GitHub Batch Installer', 'github-batch-installer' ),
            __( 'GitHub Batch Installer', 'github-batch-installer' ),
            'manage_options',
            'github-batch-installer',
            [ $this, 'render_admin_page' ],
            'dashicons-admin-plugins'
        );
    }

    /**
     * Render admin page output.
     */
    public function render_admin_page(): void {
        echo '<div class="wrap"><h1>' . esc_html__( 'GitHub Batch Installer', 'github-batch-installer' ) . '</h1></div>';
    }
}
