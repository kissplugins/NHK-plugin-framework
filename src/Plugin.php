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
use SBI\Services\GitHubService;
use SBI\Services\PluginDetectionService;
use SBI\Services\PluginInstallationService;
use SBI\Admin\RepositoryManager;
use SBI\Admin\SelfTestsPage;
use SBI\API\AjaxHandler;
use Exception;

/**
 * Plugin class coordinating all components.
 */
class Plugin extends BasePlugin {
    /**
     * Setup core plugin properties.
     */
    protected function setup_properties(): void {
        $this->version      = GBI_VERSION;
        $this->text_domain  = 'kiss-smart-batch-installer';
        $this->plugin_file  = GBI_FILE;
        $this->plugin_path  = \plugin_dir_path($this->plugin_file);
        $this->plugin_url   = \plugin_dir_url($this->plugin_file);
    }

    /**
     * Register services with the container.
     */
    protected function register_services(): void {
        try {
            // Register core services
            $this->container->singleton(PQSIntegration::class);
            $this->container->singleton(GitHubService::class);
            $this->container->singleton(PluginDetectionService::class);

            // Register PluginInstallationService with GitHubService + StateManager dependency
            $this->container->singleton(PluginInstallationService::class, function($container) {
                return new PluginInstallationService(
                    $container->get(GitHubService::class),
                    $container->get(StateManager::class)
                );
            });

            // Register StateManager with PQSIntegration + Detection dependency (FSM SSoT)
            $this->container->singleton(StateManager::class, function($container) {
                return new StateManager(
                    $container->get(PQSIntegration::class),
                    $container->get(PluginDetectionService::class)
                );
            });

            // Register admin services
            $this->container->singleton(RepositoryManager::class, function($container) {
                return new RepositoryManager(
                    $container->get(GitHubService::class),
                    $container->get(PluginDetectionService::class),
                    $container->get(StateManager::class)
                );
            });

            // Register Self Tests page
            $this->container->singleton(SelfTestsPage::class, function($container) {
                return new SelfTestsPage(
                    $container->get(GitHubService::class),
                    $container->get(PluginDetectionService::class),
                    $container->get(StateManager::class),
                    $container->get(AjaxHandler::class)
                );
            });

            // Register AJAX handler with all dependencies
            $this->container->singleton(AjaxHandler::class, function($container) {
                return new AjaxHandler(
                    $container->get(GitHubService::class),
                    $container->get(PluginDetectionService::class),
                    $container->get(PluginInstallationService::class),
                    $container->get(StateManager::class)
                );
            });
        } catch ( Exception $e ) {
            if ( WP_DEBUG_LOG ) {
                error_log( '[SBI] Service registration failed: ' . $e->getMessage() );
            }
        }
    }

    /**
     * Setup WordPress hooks.
     */
    protected function setup_hooks(): void {
        add_action('admin_menu', [ $this, 'register_admin_page' ]);
        add_filter('plugin_action_links_' . plugin_basename( GBI_FILE ), [ $this, 'add_settings_link' ]);

        // Register AJAX hooks
        add_action('init', [ $this, 'register_ajax_hooks' ]);

        // Register admin assets
        add_action('admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ]);
    }

    /**
     * Register admin menu page.
     */
    public function register_admin_page(): void {
        // Add main submenu under Plugins menu
        add_submenu_page(
            'plugins.php',
            __( 'KISS Smart Batch Installer', 'kiss-smart-batch-installer' ),
            __( 'KISS Batch Installer', 'kiss-smart-batch-installer' ),
            'install_plugins',
            'kiss-smart-batch-installer',
            [ $this, 'render_admin_page' ]
        );

        // Add Self Tests submenu
        add_submenu_page(
            'plugins.php',
            __( 'KISS Smart Batch Installer - Self Tests', 'kiss-smart-batch-installer' ),
            __( 'SBI Self Tests', 'kiss-smart-batch-installer' ),
            'install_plugins',
            'sbi-self-tests',
            [ $this, 'render_self_tests_page' ]
        );
    }

    /**
     * Add settings link to plugin actions.
     */
    public function add_settings_link( array $links ): array {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'plugins.php?page=kiss-smart-batch-installer' ),
            __( 'Settings', 'kiss-smart-batch-installer' )
        );
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Register AJAX hooks.
     */
    public function register_ajax_hooks(): void {
        try {
            $ajax_handler = $this->container->get( AjaxHandler::class );
            $ajax_handler->register_hooks();
        } catch ( Exception $e ) {
            if ( WP_DEBUG_LOG ) {
                error_log( '[SBI] AJAX handler registration failed: ' . $e->getMessage() );
            }
        }
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_assets( string $hook ): void {
        // Only load on SBI admin pages
        if ( ! in_array( $hook, [ 'plugins_page_kiss-smart-batch-installer', 'plugins_page_sbi-self-tests' ], true ) ) {
            return;
        }

        // Register and enqueue admin CSS
        wp_register_style(
            'sbi-admin',
            $this->plugin_url . 'assets/admin.css',
            [],
            $this->version
        );
        wp_enqueue_style( 'sbi-admin' );

        // Register and enqueue admin JavaScript
        wp_register_script(
            'sbi-admin',
            $this->plugin_url . 'assets/admin.js',
            [ 'jquery' ],
            $this->version,
            true
        );
        wp_enqueue_script( 'sbi-admin' );

        // Localize script with AJAX data
        wp_localize_script( 'sbi-admin', 'sbiAjax', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'sbi_ajax_nonce' ),
            'sseEnabled' => (bool) get_option( 'sbi_sse_diagnostics', false ),
            'strings' => [
                'loading' => __( 'Loading...', 'kiss-smart-batch-installer' ),
                'error' => __( 'An error occurred', 'kiss-smart-batch-installer' ),
                'success' => __( 'Success', 'kiss-smart-batch-installer' ),
            ]
        ] );

        // Enqueue TS bridge (module) to expose typed handlers to classic admin.js
        $ts_index_url = $this->plugin_url . 'dist/ts/index.js';
        wp_register_script(
            'sbi-ts-bridge',
            $this->plugin_url . 'assets/sbi-ts-bridge.js',
            [],
            $this->version,
            true
        );
        // Mark as ES module
        if ( function_exists( 'wp_script_add_data' ) ) {
            wp_script_add_data( 'sbi-ts-bridge', 'type', 'module' );
        }
        // Pass module index URL to the bridge; bridge will no-op if URL not found
        wp_localize_script( 'sbi-ts-bridge', 'sbiTs', [
            'indexUrl' => $ts_index_url,
        ] );
        wp_enqueue_script( 'sbi-ts-bridge' );

    }

    /**
     * Render admin page output.
     */
    public function render_admin_page(): void {
        // Check if we should show the old welcome page or new repository manager
        $show_welcome = isset( $_GET['welcome'] ) && $_GET['welcome'] === '1';

        if ( $show_welcome ) {
            $this->render_welcome_page();
        } else {
            $this->render_repository_manager();
        }
    }

    /**
     * Render Self Tests page.
     */
    public function render_self_tests_page(): void {
        try {
            $self_tests_page = $this->container->get( SelfTestsPage::class );
            $self_tests_page->render();
        } catch ( Exception $e ) {
            echo '<div class="notice notice-error"><p>';
            echo esc_html( sprintf( __( 'Failed to load Self Tests page: %s', 'kiss-smart-batch-installer' ), $e->getMessage() ) );
            echo '</p></div>';
        }
    }

    /**
     * Render the repository manager (main interface).
     */
    private function render_repository_manager(): void {
        try {
            $repository_manager = $this->container->get( RepositoryManager::class );
            $repository_manager->render();
        } catch ( Exception $e ) {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'KISS Smart Batch Installer', 'kiss-smart-batch-installer' ); ?></h1>
                <div class="notice notice-error">
                    <p><?php printf( esc_html__( 'Error loading repository manager: %s', 'kiss-smart-batch-installer' ), esc_html( $e->getMessage() ) ); ?></p>
                    <p><a href="<?php echo esc_url( add_query_arg( 'welcome', '1' ) ); ?>" class="button"><?php esc_html_e( 'View Welcome Page', 'kiss-smart-batch-installer' ); ?></a></p>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Render welcome page.
     */
    private function render_welcome_page(): void {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'KISS Smart Batch Installer', 'kiss-smart-batch-installer' ); ?></h1>

            <div style="margin-bottom: 20px;">
                <a href="<?php echo esc_url( remove_query_arg( 'welcome' ) ); ?>" class="button button-primary">
                    <?php esc_html_e( '← Back to Repository Manager', 'kiss-smart-batch-installer' ); ?>
                </a>
            </div>

            <div class="notice notice-info">
                <p>
                    <?php esc_html_e( 'Welcome to KISS Smart Batch Installer! This plugin allows you to batch install WordPress plugins directly from GitHub repositories.', 'kiss-smart-batch-installer' ); ?>
                </p>
            </div>

            <div class="card">
                <h2><?php esc_html_e( 'Getting Started', 'kiss-smart-batch-installer' ); ?></h2>
                <p><?php esc_html_e( 'The main interface is now available! Use the Repository Manager to configure GitHub organizations and manage plugin installations.', 'kiss-smart-batch-installer' ); ?></p>

                <h3><?php esc_html_e( 'Features', 'kiss-smart-batch-installer' ); ?></h3>
                <ul>
                    <li><?php esc_html_e( '🚀 Batch install multiple plugins from GitHub repositories', 'kiss-smart-batch-installer' ); ?></li>
                    <li><?php esc_html_e( '🔍 Smart plugin detection with WordPress plugin header scanning', 'kiss-smart-batch-installer' ); ?></li>
                    <li><?php esc_html_e( '⚡ PQS (Plugin Quick Search) integration for performance', 'kiss-smart-batch-installer' ); ?></li>
                    <li><?php esc_html_e( '📋 Familiar WordPress "All Plugins" page interface', 'kiss-smart-batch-installer' ); ?></li>
                    <li><?php esc_html_e( '🎯 Automatic detection of installed and active plugins', 'kiss-smart-batch-installer' ); ?></li>
                </ul>

                <h3><?php esc_html_e( 'Core Services Status', 'kiss-smart-batch-installer' ); ?></h3>
                <ul>
                    <li>✅ <?php esc_html_e( 'GitHub Repository Service - Ready for public repository fetching', 'kiss-smart-batch-installer' ); ?></li>
                    <li>✅ <?php esc_html_e( 'Plugin Detection Service - Ready for WordPress plugin header scanning', 'kiss-smart-batch-installer' ); ?></li>
                    <li>✅ <?php esc_html_e( 'State Manager - Ready for plugin installation status tracking', 'kiss-smart-batch-installer' ); ?></li>
                    <li>✅ <?php esc_html_e( 'PQS Integration - Ready for cache integration', 'kiss-smart-batch-installer' ); ?></li>
                    <li>✅ <?php esc_html_e( 'WordPress List Table - Ready for repository display', 'kiss-smart-batch-installer' ); ?></li>
                    <li>✅ <?php esc_html_e( 'AJAX API - Ready for frontend interactions', 'kiss-smart-batch-installer' ); ?></li>
                </ul>
            </div>

            <div class="card">
                <h2><?php esc_html_e( 'System Information', 'kiss-smart-batch-installer' ); ?></h2>
                <table class="widefat">
                    <tbody>
                        <tr>
                            <td><strong><?php esc_html_e( 'Plugin Version', 'kiss-smart-batch-installer' ); ?></strong></td>
                            <td><?php echo esc_html( GBI_VERSION ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'NHK Framework', 'kiss-smart-batch-installer' ); ?></strong></td>
                            <td><?php echo class_exists( 'NHK\\Framework\\Container\\Container' ) ? '✅ ' . esc_html__( 'Loaded', 'kiss-smart-batch-installer' ) : '❌ ' . esc_html__( 'Not Found', 'kiss-smart-batch-installer' ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'GitHub Service', 'kiss-smart-batch-installer' ); ?></strong></td>
                            <td><?php echo class_exists( 'SBI\\Services\\GitHubService' ) ? '✅ ' . esc_html__( 'Ready', 'kiss-smart-batch-installer' ) : '❌ ' . esc_html__( 'Not Available', 'kiss-smart-batch-installer' ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Plugin Detection Service', 'kiss-smart-batch-installer' ); ?></strong></td>
                            <td><?php echo class_exists( 'SBI\\Services\\PluginDetectionService' ) ? '✅ ' . esc_html__( 'Ready', 'kiss-smart-batch-installer' ) : '❌ ' . esc_html__( 'Not Available', 'kiss-smart-batch-installer' ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'State Manager', 'kiss-smart-batch-installer' ); ?></strong></td>
                            <td><?php echo class_exists( 'SBI\\Services\\StateManager' ) ? '✅ ' . esc_html__( 'Ready', 'kiss-smart-batch-installer' ) : '❌ ' . esc_html__( 'Not Available', 'kiss-smart-batch-installer' ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'WordPress Version', 'kiss-smart-batch-installer' ); ?></strong></td>
                            <td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'PHP Version', 'kiss-smart-batch-installer' ); ?></strong></td>
                            <td><?php echo esc_html( PHP_VERSION ); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <?php if ( isset( $_GET['test_services'] ) && current_user_can( 'install_plugins' ) ): ?>
            <div class="card">
                <h2><?php esc_html_e( 'Service Test Results', 'kiss-smart-batch-installer' ); ?></h2>
                <?php $this->render_service_tests(); ?>
            </div>
            <?php else: ?>
            <div class="card">
                <h2><?php esc_html_e( 'Service Testing', 'kiss-smart-batch-installer' ); ?></h2>
                <p><?php esc_html_e( 'Test the core services to verify they are working correctly.', 'kiss-smart-batch-installer' ); ?></p>
                <a href="<?php echo esc_url( add_query_arg( 'test_services', '1' ) ); ?>" class="button button-secondary">
                    <?php esc_html_e( 'Run Service Tests', 'kiss-smart-batch-installer' ); ?>
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render service test results.
     */
    private function render_service_tests(): void {
        // Check if user has permission
        if ( ! current_user_can( 'install_plugins' ) ) {
            echo '<p class="notice notice-error">' . esc_html__( 'Insufficient permissions.', 'kiss-smart-batch-installer' ) . '</p>';
            return;
        }

        $container = sbi_container();
        if ( ! $container ) {
            echo '<p class="notice notice-error">' . esc_html__( 'Container not available.', 'kiss-smart-batch-installer' ) . '</p>';
            return;
        }

        echo '<div class="test-results">';

        // Test GitHub Service
        try {
            if ( ! class_exists( 'SBI\\Services\\GitHubService' ) ) {
                echo '<p>❌ <strong>' . esc_html__( 'GitHub Service', 'kiss-smart-batch-installer' ) . '</strong>: ' . esc_html__( 'Class not found', 'kiss-smart-batch-installer' ) . '</p>';
            } else {
                $github_service = $container->get( GitHubService::class );
                echo '<p>✅ <strong>' . esc_html__( 'GitHub Service', 'kiss-smart-batch-installer' ) . '</strong>: ' . esc_html__( 'Service loaded successfully', 'kiss-smart-batch-installer' ) . '</p>';

                // Test rate limit (optional, might fail due to network)
                $rate_limit = $github_service->get_rate_limit();
                if ( ! is_wp_error( $rate_limit ) && isset( $rate_limit['rate']['remaining'] ) ) {
                    echo '<p>ℹ️ <strong>' . esc_html__( 'GitHub API', 'kiss-smart-batch-installer' ) . '</strong>: ' .
                         sprintf( esc_html__( 'Rate limit: %s remaining', 'kiss-smart-batch-installer' ), esc_html( $rate_limit['rate']['remaining'] ) ) . '</p>';
                }
            }
        } catch ( Exception $e ) {
            echo '<p>❌ <strong>' . esc_html__( 'GitHub Service', 'kiss-smart-batch-installer' ) . '</strong>: ' . esc_html( $e->getMessage() ) . '</p>';
        }

        // Test Plugin Detection Service
        try {
            if ( class_exists( 'SBI\\Services\\PluginDetectionService' ) ) {
                $detection_service = $container->get( PluginDetectionService::class );
                echo '<p>✅ <strong>' . esc_html__( 'Plugin Detection Service', 'kiss-smart-batch-installer' ) . '</strong>: ' . esc_html__( 'Service loaded successfully', 'kiss-smart-batch-installer' ) . '</p>';
            } else {
                echo '<p>❌ <strong>' . esc_html__( 'Plugin Detection Service', 'kiss-smart-batch-installer' ) . '</strong>: ' . esc_html__( 'Class not found', 'kiss-smart-batch-installer' ) . '</p>';
            }
        } catch ( Exception $e ) {
            echo '<p>❌ <strong>' . esc_html__( 'Plugin Detection Service', 'kiss-smart-batch-installer' ) . '</strong>: ' . esc_html( $e->getMessage() ) . '</p>';
        }

        // Test State Manager
        try {
            if ( class_exists( 'SBI\\Services\\StateManager' ) ) {
                $state_manager = $container->get( StateManager::class );
                echo '<p>✅ <strong>' . esc_html__( 'State Manager', 'kiss-smart-batch-installer' ) . '</strong>: ' . esc_html__( 'Service loaded successfully', 'kiss-smart-batch-installer' ) . '</p>';
            } else {
                echo '<p>❌ <strong>' . esc_html__( 'State Manager', 'kiss-smart-batch-installer' ) . '</strong>: ' . esc_html__( 'Class not found', 'kiss-smart-batch-installer' ) . '</p>';
            }
        } catch ( Exception $e ) {
            echo '<p>❌ <strong>' . esc_html__( 'State Manager', 'kiss-smart-batch-installer' ) . '</strong>: ' . esc_html( $e->getMessage() ) . '</p>';
        }

        // Test PQS Integration
        try {
            if ( class_exists( 'SBI\\Services\\PQSIntegration' ) ) {
                $pqs_integration = $container->get( PQSIntegration::class );
                echo '<p>✅ <strong>' . esc_html__( 'PQS Integration', 'kiss-smart-batch-installer' ) . '</strong>: ' . esc_html__( 'Service loaded successfully', 'kiss-smart-batch-installer' ) . '</p>';
            } else {
                echo '<p>❌ <strong>' . esc_html__( 'PQS Integration', 'kiss-smart-batch-installer' ) . '</strong>: ' . esc_html__( 'Class not found', 'kiss-smart-batch-installer' ) . '</p>';
            }
        } catch ( Exception $e ) {
            echo '<p>❌ <strong>' . esc_html__( 'PQS Integration', 'kiss-smart-batch-installer' ) . '</strong>: ' . esc_html( $e->getMessage() ) . '</p>';
        }

        echo '</div>';
        echo '<p><a href="' . esc_url( remove_query_arg( 'test_services' ) ) . '" class="button">' . esc_html__( 'Back', 'kiss-smart-batch-installer' ) . '</a></p>';
    }
}
