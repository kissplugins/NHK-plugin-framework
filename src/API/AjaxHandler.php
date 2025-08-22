<?php
/**
 * AJAX API handler for frontend interactions.
 *
 * @package SBI\API
 */

namespace SBI\API;

use WP_Error;
use SBI\Services\GitHubService;
use SBI\Services\PluginDetectionService;
use SBI\Services\PluginInstallationService;
use SBI\Services\StateManager;

/**
 * AJAX handler class.
 */
class AjaxHandler {

    /**
     * GitHub service.
     *
     * @var GitHubService
     */
    private GitHubService $github_service;

    /**
     * Plugin detection service.
     *
     * @var PluginDetectionService
     */
    private PluginDetectionService $detection_service;

    /**
     * Plugin installation service.
     *
     * @var PluginInstallationService
     */
    private PluginInstallationService $installation_service;

    /**
     * State manager.
     *
     * @var StateManager
     */
    private StateManager $state_manager;

    /**
     * Constructor.
     *
     * @param GitHubService              $github_service       GitHub service.
     * @param PluginDetectionService     $detection_service    Plugin detection service.
     * @param PluginInstallationService  $installation_service Plugin installation service.
     * @param StateManager              $state_manager        State manager.
     */
    public function __construct(
        GitHubService $github_service,
        PluginDetectionService $detection_service,
        PluginInstallationService $installation_service,
        StateManager $state_manager
    ) {
        $this->github_service = $github_service;
        $this->detection_service = $detection_service;
        $this->installation_service = $installation_service;
        $this->state_manager = $state_manager;
    }

    /**
     * Register AJAX hooks.
     */
    public function register_hooks(): void {
        // Repository actions
        add_action( 'wp_ajax_sbi_fetch_repositories', [ $this, 'fetch_repositories' ] );
        add_action( 'wp_ajax_sbi_refresh_repository', [ $this, 'refresh_repository' ] );
        
        // Plugin actions
        add_action( 'wp_ajax_sbi_install_plugin', [ $this, 'install_plugin' ] );
        add_action( 'wp_ajax_sbi_activate_plugin', [ $this, 'activate_plugin' ] );
        add_action( 'wp_ajax_sbi_deactivate_plugin', [ $this, 'deactivate_plugin' ] );
        
        // Batch actions
        add_action( 'wp_ajax_sbi_batch_install', [ $this, 'batch_install' ] );
        add_action( 'wp_ajax_sbi_batch_activate', [ $this, 'batch_activate' ] );
        add_action( 'wp_ajax_sbi_batch_deactivate', [ $this, 'batch_deactivate' ] );
        
        // Status actions
        add_action( 'wp_ajax_sbi_refresh_status', [ $this, 'refresh_status' ] );
        add_action( 'wp_ajax_sbi_get_installation_progress', [ $this, 'get_installation_progress' ] );
    }

    /**
     * Fetch repositories for GitHub account (organization or user).
     */
    public function fetch_repositories(): void {
        $this->verify_nonce_and_capability();

        $account_name = sanitize_text_field( $_POST['organization'] ?? '' );
        $force_refresh = (bool) ( $_POST['force_refresh'] ?? false );

        if ( empty( $account_name ) ) {
            wp_send_json_error( [
                'message' => __( 'Account name is required.', 'kiss-smart-batch-installer' )
            ] );
        }

        $repositories = $this->github_service->fetch_repositories( $account_name, $force_refresh );
        
        if ( is_wp_error( $repositories ) ) {
            wp_send_json_error( [
                'message' => $repositories->get_error_message()
            ] );
        }
        
        // Process repositories with plugin detection
        $processed_repos = [];
        foreach ( $repositories as $repo ) {
            $detection_result = $this->detection_service->detect_plugin( $repo );
            $state = $this->state_manager->get_state( $repo['full_name'] );
            
            $processed_repos[] = [
                'repository' => $repo,
                'is_plugin' => ! is_wp_error( $detection_result ) && $detection_result['is_plugin'],
                'plugin_data' => ! is_wp_error( $detection_result ) ? $detection_result['plugin_data'] : [],
                'state' => $state->value,
            ];
        }
        
        wp_send_json_success( [
            'repositories' => $processed_repos,
            'total' => count( $processed_repos ),
        ] );
    }

    /**
     * Refresh single repository status.
     */
    public function refresh_repository(): void {
        $this->verify_nonce_and_capability();
        
        $repo_name = sanitize_text_field( $_POST['repository'] ?? '' );
        
        if ( empty( $repo_name ) ) {
            wp_send_json_error( [
                'message' => __( 'Repository name is required.', 'kiss-smart-batch-installer' )
            ] );
        }
        
        // Refresh state
        $this->state_manager->refresh_state( $repo_name );
        $new_state = $this->state_manager->get_state( $repo_name );
        
        wp_send_json_success( [
            'repository' => $repo_name,
            'state' => $new_state->value,
        ] );
    }

    /**
     * Install plugin from repository.
     */
    public function install_plugin(): void {
        $this->verify_nonce_and_capability();

        $repo_name = sanitize_text_field( $_POST['repository'] ?? '' );
        $owner = sanitize_text_field( $_POST['owner'] ?? '' );
        $activate = (bool) ( $_POST['activate'] ?? false );

        if ( empty( $repo_name ) ) {
            wp_send_json_error( [
                'message' => __( 'Repository name is required.', 'kiss-smart-batch-installer' )
            ] );
        }

        if ( empty( $owner ) ) {
            wp_send_json_error( [
                'message' => __( 'Repository owner is required.', 'kiss-smart-batch-installer' )
            ] );
        }

        // Install the plugin
        $result = $this->installation_service->install_and_activate( $owner, $repo_name, $activate );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [
                'message' => $result->get_error_message(),
                'repository' => $repo_name,
            ] );
        }

        wp_send_json_success( array_merge( $result, [
            'message' => sprintf(
                __( 'Plugin %s installed successfully.', 'kiss-smart-batch-installer' ),
                $repo_name
            ),
            'repository' => $repo_name,
        ] ) );
    }

    /**
     * Activate plugin.
     */
    public function activate_plugin(): void {
        $this->verify_nonce_and_capability();

        $plugin_file = sanitize_text_field( $_POST['plugin_file'] ?? '' );
        $repo_name = sanitize_text_field( $_POST['repository'] ?? '' );

        if ( empty( $plugin_file ) ) {
            wp_send_json_error( [
                'message' => __( 'Plugin file is required.', 'kiss-smart-batch-installer' )
            ] );
        }

        // Activate the plugin
        $result = $this->installation_service->activate_plugin( $plugin_file );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [
                'message' => $result->get_error_message(),
                'repository' => $repo_name,
            ] );
        }

        wp_send_json_success( array_merge( $result, [
            'repository' => $repo_name,
        ] ) );
    }

    /**
     * Deactivate plugin.
     */
    public function deactivate_plugin(): void {
        $this->verify_nonce_and_capability();

        $plugin_file = sanitize_text_field( $_POST['plugin_file'] ?? '' );
        $repo_name = sanitize_text_field( $_POST['repository'] ?? '' );

        if ( empty( $plugin_file ) ) {
            wp_send_json_error( [
                'message' => __( 'Plugin file is required.', 'kiss-smart-batch-installer' )
            ] );
        }

        // Deactivate the plugin
        $result = $this->installation_service->deactivate_plugin( $plugin_file );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [
                'message' => $result->get_error_message(),
                'repository' => $repo_name,
            ] );
        }

        wp_send_json_success( array_merge( $result, [
            'repository' => $repo_name,
        ] ) );
    }

    /**
     * Batch install plugins.
     */
    public function batch_install(): void {
        $this->verify_nonce_and_capability();
        
        $repositories = $_POST['repositories'] ?? [];
        $activate = (bool) ( $_POST['activate'] ?? false );

        if ( empty( $repositories ) || ! is_array( $repositories ) ) {
            wp_send_json_error( [
                'message' => __( 'No repositories selected.', 'kiss-smart-batch-installer' )
            ] );
        }

        // Validate and sanitize repository data
        $repo_data = [];
        foreach ( $repositories as $repo ) {
            if ( ! is_array( $repo ) || empty( $repo['owner'] ) || empty( $repo['repo'] ) ) {
                continue;
            }

            $repo_data[] = [
                'owner' => sanitize_text_field( $repo['owner'] ),
                'repo' => sanitize_text_field( $repo['repo'] ),
                'branch' => sanitize_text_field( $repo['branch'] ?? 'main' ),
            ];
        }

        if ( empty( $repo_data ) ) {
            wp_send_json_error( [
                'message' => __( 'No valid repositories provided.', 'kiss-smart-batch-installer' )
            ] );
        }

        // Perform batch installation
        $results = $this->installation_service->batch_install( $repo_data, $activate );

        // Count successful installations
        $success_count = count( array_filter( $results, function( $result ) {
            return $result['success'] ?? false;
        } ) );

        wp_send_json_success( [
            'message' => sprintf(
                __( 'Successfully processed %d of %d plugins.', 'kiss-smart-batch-installer' ),
                $success_count,
                count( $results )
            ),
            'results' => $results,
            'success_count' => $success_count,
            'total_count' => count( $results ),
        ] );
    }

    /**
     * Batch activate plugins.
     */
    public function batch_activate(): void {
        $this->verify_nonce_and_capability();
        
        $plugin_files = $_POST['plugin_files'] ?? [];

        if ( empty( $plugin_files ) || ! is_array( $plugin_files ) ) {
            wp_send_json_error( [
                'message' => __( 'No plugin files provided.', 'kiss-smart-batch-installer' )
            ] );
        }

        $results = [];
        foreach ( $plugin_files as $plugin_data ) {
            if ( ! is_array( $plugin_data ) || empty( $plugin_data['plugin_file'] ) ) {
                continue;
            }

            $plugin_file = sanitize_text_field( $plugin_data['plugin_file'] );
            $repo_name = sanitize_text_field( $plugin_data['repository'] ?? '' );

            $result = $this->installation_service->activate_plugin( $plugin_file );

            if ( is_wp_error( $result ) ) {
                $results[] = [
                    'repository' => $repo_name,
                    'plugin_file' => $plugin_file,
                    'success' => false,
                    'error' => $result->get_error_message(),
                ];
            } else {
                $results[] = array_merge( $result, [
                    'repository' => $repo_name,
                    'success' => true,
                ] );
            }
        }
        
        wp_send_json_success( [
            'results' => $results,
            'total' => count( $results ),
            'successful' => count( array_filter( $results, fn( $r ) => $r['success'] ) ),
        ] );
    }

    /**
     * Batch deactivate plugins.
     */
    public function batch_deactivate(): void {
        $this->verify_nonce_and_capability();

        $plugin_files = $_POST['plugin_files'] ?? [];

        if ( empty( $plugin_files ) || ! is_array( $plugin_files ) ) {
            wp_send_json_error( [
                'message' => __( 'No plugin files provided.', 'kiss-smart-batch-installer' )
            ] );
        }

        $results = [];
        foreach ( $plugin_files as $plugin_data ) {
            if ( ! is_array( $plugin_data ) || empty( $plugin_data['plugin_file'] ) ) {
                continue;
            }

            $plugin_file = sanitize_text_field( $plugin_data['plugin_file'] );
            $repo_name = sanitize_text_field( $plugin_data['repository'] ?? '' );

            $result = $this->installation_service->deactivate_plugin( $plugin_file );

            if ( is_wp_error( $result ) ) {
                $results[] = [
                    'repository' => $repo_name,
                    'plugin_file' => $plugin_file,
                    'success' => false,
                    'error' => $result->get_error_message(),
                ];
            } else {
                $results[] = array_merge( $result, [
                    'repository' => $repo_name,
                    'success' => true,
                ] );
            }
        }

        // Count successful deactivations
        $success_count = count( array_filter( $results, function( $result ) {
            return $result['success'] ?? false;
        } ) );

        wp_send_json_success( [
            'message' => sprintf(
                __( 'Successfully processed %d of %d plugins.', 'kiss-smart-batch-installer' ),
                $success_count,
                count( $results )
            ),
            'results' => $results,
            'success_count' => $success_count,
            'total_count' => count( $results ),
        ] );
    }

    /**
     * Refresh status for multiple repositories.
     */
    public function refresh_status(): void {
        $this->verify_nonce_and_capability();
        
        $repositories = $_POST['repositories'] ?? [];
        
        if ( empty( $repositories ) || ! is_array( $repositories ) ) {
            wp_send_json_error( [
                'message' => __( 'No repositories specified.', 'kiss-smart-batch-installer' )
            ] );
        }
        
        $results = [];
        foreach ( $repositories as $repo_name ) {
            $repo_name = sanitize_text_field( $repo_name );
            $this->state_manager->refresh_state( $repo_name );
            $new_state = $this->state_manager->get_state( $repo_name );
            
            $results[] = [
                'repository' => $repo_name,
                'state' => $new_state->value,
            ];
        }
        
        wp_send_json_success( [
            'results' => $results,
        ] );
    }

    /**
     * Get installation progress.
     */
    public function get_installation_progress(): void {
        $this->verify_nonce_and_capability();
        
        // TODO: Implement actual progress tracking
        // For now, return mock progress data
        
        wp_send_json_success( [
            'progress' => 75,
            'current_step' => __( 'Installing plugin dependencies...', 'kiss-smart-batch-installer' ),
            'completed' => 3,
            'total' => 4,
        ] );
    }

    /**
     * Verify nonce and user capability.
     */
    private function verify_nonce_and_capability(): void {
        if ( ! check_ajax_referer( 'sbi_ajax_nonce', 'nonce', false ) ) {
            wp_send_json_error( [
                'message' => __( 'Security check failed.', 'kiss-smart-batch-installer' )
            ] );
        }
        
        if ( ! current_user_can( 'install_plugins' ) ) {
            wp_send_json_error( [
                'message' => __( 'Insufficient permissions.', 'kiss-smart-batch-installer' )
            ] );
        }
    }
}
