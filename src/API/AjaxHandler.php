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
     * State manager.
     *
     * @var StateManager
     */
    private StateManager $state_manager;

    /**
     * Constructor.
     *
     * @param GitHubService           $github_service    GitHub service.
     * @param PluginDetectionService  $detection_service Plugin detection service.
     * @param StateManager           $state_manager     State manager.
     */
    public function __construct( 
        GitHubService $github_service, 
        PluginDetectionService $detection_service, 
        StateManager $state_manager 
    ) {
        $this->github_service = $github_service;
        $this->detection_service = $detection_service;
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
        
        if ( empty( $repo_name ) ) {
            wp_send_json_error( [
                'message' => __( 'Repository name is required.', 'kiss-smart-batch-installer' )
            ] );
        }
        
        // TODO: Implement actual plugin installation
        // For now, simulate installation
        sleep( 2 ); // Simulate installation time
        
        wp_send_json_success( [
            'message' => sprintf( __( 'Plugin %s installed successfully.', 'kiss-smart-batch-installer' ), $repo_name ),
            'repository' => $repo_name,
        ] );
    }

    /**
     * Activate plugin.
     */
    public function activate_plugin(): void {
        $this->verify_nonce_and_capability();
        
        $repo_name = sanitize_text_field( $_POST['repository'] ?? '' );
        
        if ( empty( $repo_name ) ) {
            wp_send_json_error( [
                'message' => __( 'Repository name is required.', 'kiss-smart-batch-installer' )
            ] );
        }
        
        // TODO: Implement actual plugin activation
        // For now, simulate activation
        
        wp_send_json_success( [
            'message' => sprintf( __( 'Plugin %s activated successfully.', 'kiss-smart-batch-installer' ), $repo_name ),
            'repository' => $repo_name,
        ] );
    }

    /**
     * Deactivate plugin.
     */
    public function deactivate_plugin(): void {
        $this->verify_nonce_and_capability();
        
        $repo_name = sanitize_text_field( $_POST['repository'] ?? '' );
        
        if ( empty( $repo_name ) ) {
            wp_send_json_error( [
                'message' => __( 'Repository name is required.', 'kiss-smart-batch-installer' )
            ] );
        }
        
        // TODO: Implement actual plugin deactivation
        // For now, simulate deactivation
        
        wp_send_json_success( [
            'message' => sprintf( __( 'Plugin %s deactivated successfully.', 'kiss-smart-batch-installer' ), $repo_name ),
            'repository' => $repo_name,
        ] );
    }

    /**
     * Batch install plugins.
     */
    public function batch_install(): void {
        $this->verify_nonce_and_capability();
        
        $repositories = $_POST['repositories'] ?? [];
        
        if ( empty( $repositories ) || ! is_array( $repositories ) ) {
            wp_send_json_error( [
                'message' => __( 'No repositories selected.', 'kiss-smart-batch-installer' )
            ] );
        }
        
        $results = [];
        foreach ( $repositories as $repo_name ) {
            $repo_name = sanitize_text_field( $repo_name );
            
            // TODO: Implement actual batch installation
            // For now, simulate installation
            $results[] = [
                'repository' => $repo_name,
                'success' => true,
                'message' => sprintf( __( 'Plugin %s installed successfully.', 'kiss-smart-batch-installer' ), $repo_name ),
            ];
        }
        
        wp_send_json_success( [
            'results' => $results,
            'total' => count( $results ),
            'successful' => count( array_filter( $results, fn( $r ) => $r['success'] ) ),
        ] );
    }

    /**
     * Batch activate plugins.
     */
    public function batch_activate(): void {
        $this->verify_nonce_and_capability();
        
        $repositories = $_POST['repositories'] ?? [];
        
        if ( empty( $repositories ) || ! is_array( $repositories ) ) {
            wp_send_json_error( [
                'message' => __( 'No repositories selected.', 'kiss-smart-batch-installer' )
            ] );
        }
        
        $results = [];
        foreach ( $repositories as $repo_name ) {
            $repo_name = sanitize_text_field( $repo_name );
            
            // TODO: Implement actual batch activation
            // For now, simulate activation
            $results[] = [
                'repository' => $repo_name,
                'success' => true,
                'message' => sprintf( __( 'Plugin %s activated successfully.', 'kiss-smart-batch-installer' ), $repo_name ),
            ];
        }
        
        wp_send_json_success( [
            'results' => $results,
            'total' => count( $results ),
            'successful' => count( array_filter( $results, fn( $r ) => $r['success'] ) ),
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
