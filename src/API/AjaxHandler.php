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
        add_action( 'wp_ajax_sbi_fetch_repository_list', [ $this, 'fetch_repository_list' ] );
        add_action( 'wp_ajax_sbi_process_repository', [ $this, 'process_repository' ] );
        add_action( 'wp_ajax_sbi_render_repository_row', [ $this, 'render_repository_row' ] );
        add_action( 'wp_ajax_sbi_refresh_repository', [ $this, 'refresh_repository' ] );
        
        // Plugin actions
        add_action( 'wp_ajax_sbi_install_plugin', [ $this, 'install_plugin' ] );
        add_action( 'wp_ajax_sbi_activate_plugin', [ $this, 'activate_plugin' ] );
        add_action( 'wp_ajax_sbi_deactivate_plugin', [ $this, 'deactivate_plugin' ] );
        
        // Batch actions
        add_action( 'wp_ajax_sbi_batch_install', [ $this, 'batch_install' ] );
        add_action( 'wp_ajax_sbi_batch_activate', [ $this, 'batch_activate' ] );
        add_action( 'wp_ajax_sbi_batch_deactivate', [ $this, 'batch_deactivate' ] );

        // Debug action (temporary)
        add_action( 'wp_ajax_sbi_debug_detection', [ $this, 'debug_detection' ] );

        // Test actions
        add_action( 'wp_ajax_sbi_test_repository', [ $this, 'test_repository' ] );

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
        $limit = (int) ( $_POST['limit'] ?? 0 ); // 0 = no limit

        if ( empty( $account_name ) ) {
            wp_send_json_error( [
                'message' => __( 'Account name is required.', 'kiss-smart-batch-installer' )
            ] );
        }

        $repositories = $this->github_service->fetch_repositories_for_account( $account_name, $force_refresh, $limit );
        
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
     * Fetch repository list without processing (for progressive loading).
     */
    public function fetch_repository_list(): void {
        $this->verify_nonce_and_capability();

        $account_name = sanitize_text_field( $_POST['organization'] ?? '' );
        $force_refresh = (bool) ( $_POST['force_refresh'] ?? false );
        $limit = (int) ( $_POST['limit'] ?? 0 ); // 0 = no limit

        // Debug logging
        error_log( sprintf( 'SBI AJAX: fetch_repository_list called for %s (limit: %d)', $account_name, $limit ) );

        if ( empty( $account_name ) ) {
            error_log( 'SBI AJAX: fetch_repository_list failed - account name is empty' );
            wp_send_json_error( [
                'message' => __( 'Account name is required.', 'kiss-smart-batch-installer' )
            ] );
        }

        $repositories = $this->github_service->fetch_repositories_for_account( $account_name, $force_refresh, $limit );

        if ( is_wp_error( $repositories ) ) {
            error_log( sprintf( 'SBI AJAX: fetch_repository_list failed for %s: %s', $account_name, $repositories->get_error_message() ) );
            wp_send_json_error( [
                'message' => $repositories->get_error_message()
            ] );
        }

        error_log( sprintf( 'SBI AJAX: fetch_repository_list success for %s - found %d repositories', $account_name, count( $repositories ) ) );

        // Return just the basic repository data without processing
        wp_send_json_success( [
            'repositories' => $repositories,
            'total' => count( $repositories ),
        ] );
    }

    /**
     * Process a single repository (plugin detection and state management).
     */
    public function process_repository(): void {
        $this->verify_nonce_and_capability();

        $repository = $_POST['repository'] ?? [];
        $repo_name = $repository['full_name'] ?? 'unknown';

        // Debug logging
        error_log( sprintf( 'SBI AJAX: process_repository called for %s', $repo_name ) );

        // Add a significant delay to prevent overwhelming GitHub API
        sleep( 1 ); // 1 second delay on server side

        if ( empty( $repository ) || ! is_array( $repository ) ) {
            error_log( 'SBI AJAX: process_repository failed - repository data is empty or invalid' );
            wp_send_json_error( [
                'message' => __( 'Repository data is required.', 'kiss-smart-batch-installer' )
            ] );
        }

        // Sanitize repository data
        $repo = [
            'id' => intval( $repository['id'] ?? 0 ),
            'name' => sanitize_text_field( $repository['name'] ?? '' ),
            'full_name' => sanitize_text_field( $repository['full_name'] ?? '' ),
            'description' => sanitize_textarea_field( $repository['description'] ?? '' ),
            'html_url' => esc_url_raw( $repository['html_url'] ?? '' ),
            'clone_url' => esc_url_raw( $repository['clone_url'] ?? '' ),
            'updated_at' => sanitize_text_field( $repository['updated_at'] ?? '' ),
            'language' => sanitize_text_field( $repository['language'] ?? '' ),
        ];

        if ( empty( $repo['full_name'] ) ) {
            error_log( 'SBI AJAX: process_repository failed - repository full_name is empty' );
            wp_send_json_error( [
                'message' => __( 'Repository full name is required.', 'kiss-smart-batch-installer' )
            ] );
        }

        error_log( sprintf( 'SBI AJAX: Starting plugin detection for %s', $repo['full_name'] ) );

        // Process repository with plugin detection
        try {
            $detection_result = $this->detection_service->detect_plugin( $repo );
            $is_plugin = ! is_wp_error( $detection_result ) && $detection_result['is_plugin'];

            // Determine the correct state based on detection result and installation status
            if ( is_wp_error( $detection_result ) ) {
                $state = \SBI\Enums\PluginState::ERROR;
                error_log( sprintf( 'SBI: Repository %s has error state: %s', $repo['full_name'], $detection_result->get_error_message() ) );
            } elseif ( ! $is_plugin ) {
                $state = \SBI\Enums\PluginState::NOT_PLUGIN;
                error_log( sprintf( 'SBI: Repository %s is not a WordPress plugin', $repo['full_name'] ) );
            } else {
                // It's a WordPress plugin, check if it's installed
                $plugin_slug = basename( $repo['full_name'] );

                // Look for the plugin file in the detection result first
                $detected_plugin_file = ! is_wp_error( $detection_result ) ? ($detection_result['plugin_file'] ?? '') : '';

                // Find installed plugin
                $installed_plugin_file = $this->find_installed_plugin( $plugin_slug );

                if ( ! empty( $installed_plugin_file ) ) {
                    // Plugin is installed
                    if ( is_plugin_active( $installed_plugin_file ) ) {
                        $state = \SBI\Enums\PluginState::INSTALLED_ACTIVE;
                        error_log( sprintf( 'SBI: Plugin %s is installed and active', $repo['full_name'] ) );
                    } else {
                        $state = \SBI\Enums\PluginState::INSTALLED_INACTIVE;
                        error_log( sprintf( 'SBI: Plugin %s is installed but inactive', $repo['full_name'] ) );
                    }
                    $plugin_file = $installed_plugin_file;
                } else {
                    // Plugin is not installed - mark as available for installation
                    $state = \SBI\Enums\PluginState::AVAILABLE;
                    $plugin_file = $detected_plugin_file; // Use the detected plugin file path
                    error_log( sprintf( 'SBI: Plugin %s is available for installation (detected file: %s)', $repo['full_name'], $plugin_file ) );
                }
            }

            $processed_repo = [
                'repository' => $repo,
                'is_plugin' => $is_plugin,
                'plugin_data' => ! is_wp_error( $detection_result ) ? $detection_result['plugin_data'] : [],
                'plugin_file' => $plugin_file ?? '',  // Make sure plugin_file is always set
                'state' => $state->value,
                'scan_method' => ! is_wp_error( $detection_result ) ? $detection_result['scan_method'] : '',
                'error' => is_wp_error( $detection_result ) ? $detection_result->get_error_message() : null,
            ];

            // Log successful processing for debugging
            error_log( sprintf( 'SBI: Successfully processed repository %s', $repo['full_name'] ) );

            wp_send_json_success( [
                'repository' => $processed_repo,
            ] );
        } catch ( Exception $e ) {
            // Log the error for debugging
            error_log( sprintf( 'SBI: Error processing repository %s: %s', $repo['full_name'], $e->getMessage() ) );

            wp_send_json_error( [
                'message' => sprintf(
                    __( 'Failed to process repository %s: %s', 'kiss-smart-batch-installer' ),
                    $repo['name'],
                    $e->getMessage()
                )
            ] );
        }
    }

    /**
     * Render a repository row HTML for progressive loading.
     */
    public function render_repository_row(): void {
        $this->verify_nonce_and_capability();

        $repository_data = $_POST['repository'] ?? [];
        $repo_name = $repository_data['repository']['full_name'] ?? 'unknown';

        // Debug logging
        error_log( sprintf( 'SBI AJAX: render_repository_row called for %s', $repo_name ) );

        if ( empty( $repository_data ) || ! is_array( $repository_data ) ) {
            error_log( 'SBI AJAX: render_repository_row failed - repository data is empty or invalid' );
            wp_send_json_error( [
                'message' => __( 'Repository data is required.', 'kiss-smart-batch-installer' )
            ] );
        }

        try {
            // Flatten the data structure to match what RepositoryListTable expects
            $repo_data = $repository_data['repository'] ?? [];
            $flattened_data = array_merge(
                $repo_data,
                [
                    'is_plugin' => $repository_data['is_plugin'] ?? false,
                    'plugin_data' => $repository_data['plugin_data'] ?? [],
                    'plugin_file' => $repository_data['plugin_file'] ?? '',
                    'installation_state' => \SBI\Enums\PluginState::from( $repository_data['state'] ?? 'unknown' ),
                    'full_name' => $repo_data['full_name'] ?? '',  // Ensure full_name is preserved
                    'name' => $repo_data['name'] ?? '',  // Ensure name is preserved
                ]
            );

            error_log( sprintf( 'SBI AJAX: Flattened data for %s: %s', $repo_name, json_encode( array_keys( $flattened_data ) ) ) );

            // Get the list table instance with proper dependencies
            $list_table = new \SBI\Admin\RepositoryListTable(
                $this->github_service,
                $this->detection_service,
                $this->state_manager
            );

            // Render the row HTML
            $row_html = $list_table->render_single_row( $flattened_data );

            error_log( sprintf( 'SBI AJAX: render_repository_row success for %s - HTML length: %d', $repo_name, strlen( $row_html ) ) );

            wp_send_json_success( [
                'row_html' => $row_html,
                'repository_id' => $repository_data['repository']['full_name'] ?? '',
            ] );
        } catch ( Exception $e ) {
            error_log( sprintf( 'SBI AJAX: render_repository_row failed for %s: %s', $repo_name, $e->getMessage() ) );
            wp_send_json_error( [
                'message' => sprintf( 'Failed to render row: %s', $e->getMessage() )
            ] );
        }
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
        // Increase memory limit for installation
        $original_memory_limit = ini_get( 'memory_limit' );
        if ( function_exists( 'ini_set' ) ) {
            ini_set( 'memory_limit', '512M' );
        }

        // Clean output buffer to prevent issues
        if ( ob_get_level() ) {
            ob_clean();
        }

        $debug_steps = [];
        $start_time = microtime( true );

        try {
            // Step 1: Security verification
            $debug_steps[] = [
                'step' => 'Security Verification',
                'status' => 'starting',
                'time' => round( ( microtime( true ) - $start_time ) * 1000, 2 )
            ];

            $this->send_progress_update( 'Security Verification', 'info', 'Verifying nonce and user permissions...' );
            $this->verify_nonce_and_capability();

            $debug_steps[] = [
                'step' => 'Security Verification',
                'status' => 'completed',
                'message' => 'Nonce and capability checks passed',
                'time' => round( ( microtime( true ) - $start_time ) * 1000, 2 )
            ];

            $this->send_progress_update( 'Security Verification', 'success', 'Security checks passed' );

            // Step 2: Parameter validation
            $debug_steps[] = [
                'step' => 'Parameter Validation',
                'status' => 'starting',
                'time' => round( ( microtime( true ) - $start_time ) * 1000, 2 )
            ];

            $this->send_progress_update( 'Parameter Validation', 'info', 'Validating installation parameters...' );

            $repo_name = sanitize_text_field( $_POST['repository'] ?? '' );
            $owner = sanitize_text_field( $_POST['owner'] ?? '' );
            $activate = (bool) ( $_POST['activate'] ?? false );

            error_log( sprintf( 'SBI INSTALL: Starting installation for %s/%s (activate: %s)',
                $owner, $repo_name, $activate ? 'yes' : 'no' ) );

            if ( empty( $repo_name ) ) {
                $debug_steps[] = [
                    'step' => 'Parameter Validation',
                    'status' => 'failed',
                    'error' => 'Repository name is required',
                    'time' => round( ( microtime( true ) - $start_time ) * 1000, 2 )
                ];

                $this->send_progress_update( 'Parameter Validation', 'error', 'Repository name is required' );

                wp_send_json_error( [
                    'message' => __( 'Repository name is required.', 'kiss-smart-batch-installer' ),
                    'debug_steps' => $debug_steps,
                    'progress_updates' => $this->progress_updates
                ] );
            }

            if ( empty( $owner ) ) {
                $debug_steps[] = [
                    'step' => 'Parameter Validation',
                    'status' => 'failed',
                    'error' => 'Repository owner is required',
                    'time' => round( ( microtime( true ) - $start_time ) * 1000, 2 )
                ];

                $this->send_progress_update( 'Parameter Validation', 'error', 'Repository owner is required' );

                wp_send_json_error( [
                    'message' => __( 'Repository owner is required.', 'kiss-smart-batch-installer' ),
                    'debug_steps' => $debug_steps,
                    'progress_updates' => $this->progress_updates
                ] );
            }

            $debug_steps[] = [
                'step' => 'Parameter Validation',
                'status' => 'completed',
                'message' => sprintf( 'Repository: %s/%s, Activate: %s', $owner, $repo_name, $activate ? 'yes' : 'no' ),
                'time' => round( ( microtime( true ) - $start_time ) * 1000, 2 )
            ];

            $this->send_progress_update( 'Parameter Validation', 'success', "Validated parameters for {$owner}/{$repo_name}" );

            // Step 3: Plugin installation
            $debug_steps[] = [
                'step' => 'Plugin Installation',
                'status' => 'starting',
                'message' => 'Calling installation service',
                'time' => round( ( microtime( true ) - $start_time ) * 1000, 2 )
            ];

            $this->send_progress_update( 'Plugin Installation', 'info', "Starting installation of {$owner}/{$repo_name}..." );

            error_log( sprintf( 'SBI INSTALL: Calling installation service for %s/%s', $owner, $repo_name ) );

            // Set progress callback for the installation service
            $this->installation_service->set_progress_callback( [ $this, 'send_progress_update' ] );

            // NOTE TO FUTURE CONTRIBUTORS AND LLMs:
            // The debug_steps/progress_updates and enhanced error mapping below are
            // critical for field debugging. Do NOT remove or simplify. If you must
            // refactor, preserve equivalent structured debug output.
            $result = $this->installation_service->install_and_activate( $owner, $repo_name, $activate );

            if ( is_wp_error( $result ) ) {
                $error_code = $result->get_error_code();
                $error_message = $result->get_error_message();

                // Enhanced error message for 404 errors
                if ( $error_code === 'github_api_error' && strpos( $error_message, '404' ) !== false ) {
                    $enhanced_message = sprintf(
                        'Repository %s/%s not found. This could mean: 1) Repository doesn\'t exist, 2) Repository is private, 3) Repository name is incorrect, or 4) GitHub API is temporarily unavailable.',
                        $owner,
                        $repo_name
                    );
                } else {
                    $enhanced_message = $error_message;
                }

                $debug_steps[] = [
                    'step' => 'Plugin Installation',
                    'status' => 'failed',
                    'error' => $enhanced_message,
                    'original_error' => $error_message,
                    'error_code' => $error_code,
                    'repository_url' => sprintf( 'https://github.com/%s/%s', $owner, $repo_name ),
                    'api_url' => sprintf( 'https://api.github.com/repos/%s/%s', $owner, $repo_name ),
                    'time' => round( ( microtime( true ) - $start_time ) * 1000, 2 )
                ];

                $this->send_progress_update( 'Plugin Installation', 'error', 'Installation failed: ' . $enhanced_message );

                error_log( sprintf( 'SBI INSTALL: Installation failed for %s/%s: %s (Code: %s)',
                    $owner, $repo_name, $error_message, $error_code ) );

                wp_send_json_error( [
                    'message' => $enhanced_message,
                    'repository' => $repo_name,
                    'debug_steps' => $debug_steps,
                    'progress_updates' => $this->progress_updates,
                    'troubleshooting' => [
                        'check_repository_exists' => sprintf( 'https://github.com/%s/%s', $owner, $repo_name ),
                        'verify_repository_public' => 'Make sure the repository is public',
                        'check_spelling' => 'Verify owner and repository names are correct'
                    ]
                ] );
            }

            $debug_steps[] = [
                'step' => 'Plugin Installation',
                'status' => 'completed',
                'message' => 'Installation completed successfully',
                'result_data' => $result,
                'time' => round( ( microtime( true ) - $start_time ) * 1000, 2 )
            ];

            $this->send_progress_update( 'Plugin Installation', 'success', "Successfully installed {$owner}/{$repo_name}" );

            error_log( sprintf( 'SBI INSTALL: Installation successful for %s/%s', $owner, $repo_name ) );

            // Step 4: Success response
            $total_time = round( ( microtime( true ) - $start_time ) * 1000, 2 );

            // Clean up memory before sending response
            if ( function_exists( 'gc_collect_cycles' ) ) {
                gc_collect_cycles();
            }

            // Restore original memory limit
            if ( function_exists( 'ini_set' ) && isset( $original_memory_limit ) ) {
                ini_set( 'memory_limit', $original_memory_limit );
            }

            wp_send_json_success( array_merge( $result, [
                'message' => sprintf(
                    __( 'Plugin %s installed successfully.', 'kiss-smart-batch-installer' ),
                    $repo_name
                ),
                'repository' => $repo_name,
                'debug_steps' => $debug_steps,
                'progress_updates' => $this->progress_updates,
                'total_time' => $total_time
            ] ) );

        } catch ( Exception $e ) {
            $debug_steps[] = [
                'step' => 'Exception Handler',
                'status' => 'failed',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'time' => round( ( microtime( true ) - $start_time ) * 1000, 2 )
            ];

            error_log( sprintf( 'SBI INSTALL: Exception during installation of %s/%s: %s',
                $owner ?? 'unknown', $repo_name ?? 'unknown', $e->getMessage() ) );

            // Clean up memory before sending error response
            if ( function_exists( 'gc_collect_cycles' ) ) {
                gc_collect_cycles();
            }

            // Restore original memory limit
            if ( function_exists( 'ini_set' ) && isset( $original_memory_limit ) ) {
                ini_set( 'memory_limit', $original_memory_limit );
            }

            wp_send_json_error( [
                'message' => sprintf( 'Installation failed: %s', $e->getMessage() ),
                'repository' => $repo_name ?? 'unknown',
                'debug_steps' => $debug_steps,
                'progress_updates' => $this->progress_updates
            ] );
        }
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
     * Debug plugin detection for specific repositories.
     */
    public function debug_detection(): void {
        $this->verify_nonce_and_capability();

        $repositories = [
            'kissplugins/KISS-Plugin-Quick-Search',
            'kissplugins/KISS-Projects-Tasks',
            'kissplugins/KISS-Smart-Batch-Installer',
        ];

        $results = $this->detection_service->debug_detection( $repositories );

        wp_send_json_success( [
            'message' => 'Debug detection completed',
            'results' => $results,
        ] );
    }

    /**
     * Progress updates storage.
     *
     * @var array
     */
    private array $progress_updates = [];

    /**
     * Send progress update to frontend debugger.
     */
    private function send_progress_update( string $step, string $status, string $message = '' ): void {
        // Only send progress updates if debug is enabled
        if ( ! get_option( 'sbi_debug_ajax', false ) ) {
            return;
        }

        // Store progress update for later inclusion in response
        $this->progress_updates[] = [
            'step' => $step,
            'status' => $status,
            'message' => $message,
            'timestamp' => microtime( true )
        ];

        // Also log to error log for server-side debugging
        error_log( sprintf( 'SBI PROGRESS: [%s] %s - %s', $status, $step, $message ) );
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

    /**
     * Find installed plugin file for a given slug.
     *
     * @param string $plugin_slug Plugin slug.
     * @return string Plugin file path or empty string if not found.
     */
    private function find_installed_plugin( string $plugin_slug ): string {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();

        foreach ( $all_plugins as $plugin_file => $plugin_data ) {
            $plugin_dir = dirname( $plugin_file );

            // Check if plugin directory matches the slug
            if ( $plugin_dir === $plugin_slug || $plugin_file === $plugin_slug . '.php' ) {
                return $plugin_file;
            }
        }

        return '';
    }

    /**
     * Test repository access for debugging.
     */
    public function test_repository(): void {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'sbi_test_repository' ) ) {
            wp_send_json_error( [ 'message' => 'Invalid nonce' ] );
        }

        $owner = sanitize_text_field( $_POST['owner'] ?? '' );
        $repo = sanitize_text_field( $_POST['repo'] ?? '' );

        if ( empty( $owner ) || empty( $repo ) ) {
            wp_send_json_error( [ 'message' => 'Owner and repository name are required' ] );
        }

        // Test repository access
        $repository_info = $this->github_service->get_repository_info( $owner, $repo );

        if ( is_wp_error( $repository_info ) ) {
            $error_data = $repository_info->get_error_data();
            $response_data = [
                'message' => $repository_info->get_error_message(),
                'troubleshooting' => [
                    'check_repository_exists' => sprintf( 'https://github.com/%s/%s', $owner, $repo ),
                    'verify_repository_public' => 'Make sure the repository is public',
                    'check_spelling' => 'Verify owner and repository names are correct'
                ]
            ];

            if ( is_array( $error_data ) ) {
                $response_data['debug_info'] = $error_data;
            }

            wp_send_json_error( $response_data );
        }

        // Success - return repository information
        wp_send_json_success( [
            'name' => $repository_info['name'] ?? $repo,
            'description' => $repository_info['description'] ?? '',
            'html_url' => $repository_info['html_url'] ?? sprintf( 'https://github.com/%s/%s', $owner, $repo ),
            'private' => $repository_info['private'] ?? false,
            'fork' => $repository_info['fork'] ?? false,
            'language' => $repository_info['language'] ?? 'Unknown',
            'stargazers_count' => $repository_info['stargazers_count'] ?? 0,
            'forks_count' => $repository_info['forks_count'] ?? 0
        ] );
    }
}
