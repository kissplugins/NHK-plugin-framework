<?php
/**
 * Plugin Installation Service for installing WordPress plugins from GitHub repositories.
 *
 * @package SBI\Services
 */

namespace SBI\Services;

use WP_Error;
use Plugin_Upgrader;
use WP_Upgrader_Skin;

/**
 * Handles WordPress plugin installation from GitHub repositories.
 */
class PluginInstallationService {
    
    /**
     * GitHub service for repository operations.
     *
     * @var GitHubService
     */
    private GitHubService $github_service;
    
    /**
     * Constructor.
     *
     * @param GitHubService $github_service GitHub service instance.
     */
    public function __construct( GitHubService $github_service ) {
        $this->github_service = $github_service;
    }
    
    /**
     * Install a plugin from a GitHub repository.
     *
     * @param string $owner Repository owner (user or organization).
     * @param string $repo Repository name.
     * @param string $branch Branch to install from (default: main).
     * @return array|WP_Error Installation result or error.
     */
    public function install_plugin( string $owner, string $repo, string $branch = 'main' ) {
        error_log( sprintf( 'SBI INSTALL SERVICE: Starting install_plugin for %s/%s (branch: %s)', $owner, $repo, $branch ) );

        if ( empty( $owner ) || empty( $repo ) ) {
            error_log( 'SBI INSTALL SERVICE: Invalid parameters - owner or repo empty' );
            return new WP_Error( 'invalid_params', __( 'Owner and repository name are required.', 'kiss-smart-batch-installer' ) );
        }

        // Check if user has permission to install plugins
        if ( ! current_user_can( 'install_plugins' ) ) {
            error_log( 'SBI INSTALL SERVICE: Insufficient permissions for current user' );
            return new WP_Error( 'insufficient_permissions', __( 'You do not have permission to install plugins.', 'kiss-smart-batch-installer' ) );
        }

        error_log( 'SBI INSTALL SERVICE: Permission check passed' );

        // Get repository information
        error_log( 'SBI INSTALL SERVICE: Getting repository information from GitHub' );
        $repo_info = $this->github_service->get_repository( $owner, $repo );
        if ( is_wp_error( $repo_info ) ) {
            error_log( sprintf( 'SBI INSTALL SERVICE: Failed to get repository info: %s', $repo_info->get_error_message() ) );
            return $repo_info;
        }

        error_log( 'SBI INSTALL SERVICE: Repository information retrieved successfully' );

        // Build download URL for the repository ZIP
        $download_url = sprintf( 'https://github.com/%s/%s/archive/refs/heads/%s.zip',
            urlencode( $owner ),
            urlencode( $repo ),
            urlencode( $branch )
        );

        error_log( sprintf( 'SBI INSTALL SERVICE: Download URL: %s', $download_url ) );
        
        // Include necessary WordPress files
        error_log( 'SBI INSTALL SERVICE: Including WordPress upgrader files' );
        if ( ! class_exists( 'Plugin_Upgrader' ) ) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }

        // Create a custom skin to capture output
        error_log( 'SBI INSTALL SERVICE: Creating upgrader skin' );
        $skin = new SBI_Plugin_Upgrader_Skin();

        // Create upgrader instance
        error_log( 'SBI INSTALL SERVICE: Creating Plugin_Upgrader instance' );
        $upgrader = new Plugin_Upgrader( $skin );

        // Install the plugin
        error_log( sprintf( 'SBI INSTALL SERVICE: Starting plugin installation from %s', $download_url ) );
        $result = $upgrader->install( $download_url );

        error_log( sprintf( 'SBI INSTALL SERVICE: Installation result: %s',
            is_wp_error( $result ) ? 'WP_Error: ' . $result->get_error_message() :
            ( $result ? 'Success' : 'Failed (false)' ) ) );

        if ( is_wp_error( $result ) ) {
            error_log( sprintf( 'SBI INSTALL SERVICE: Installation failed with WP_Error: %s', $result->get_error_message() ) );
            return $result;
        }

        if ( ! $result ) {
            error_log( 'SBI INSTALL SERVICE: Installation failed - upgrader returned false' );
            $messages = $skin->get_messages();
            error_log( sprintf( 'SBI INSTALL SERVICE: Upgrader messages: %s', implode( '; ', $messages ) ) );
            return new WP_Error( 'installation_failed', __( 'Plugin installation failed.', 'kiss-smart-batch-installer' ) );
        }

        // Get the installed plugin file
        error_log( 'SBI INSTALL SERVICE: Getting plugin file information' );
        $plugin_file = $upgrader->plugin_info();

        error_log( sprintf( 'SBI INSTALL SERVICE: Plugin file detected: %s', $plugin_file ?: 'none' ) );

        if ( ! $plugin_file ) {
            error_log( 'SBI INSTALL SERVICE: Plugin file could not be determined' );
            $messages = $skin->get_messages();
            error_log( sprintf( 'SBI INSTALL SERVICE: Upgrader messages: %s', implode( '; ', $messages ) ) );
            return new WP_Error( 'plugin_file_not_found', __( 'Plugin was installed but plugin file could not be determined.', 'kiss-smart-batch-installer' ) );
        }
        
        $messages = $skin->get_messages();
        error_log( sprintf( 'SBI INSTALL SERVICE: Installation completed successfully for %s/%s', $owner, $repo ) );
        error_log( sprintf( 'SBI INSTALL SERVICE: Plugin file: %s', $plugin_file ) );
        error_log( sprintf( 'SBI INSTALL SERVICE: Messages: %s', implode( '; ', $messages ) ) );

        return [
            'success' => true,
            'plugin_file' => $plugin_file,
            'plugin_name' => $repo,
            'download_url' => $download_url,
            'messages' => $messages,
        ];
    }
    
    /**
     * Activate a plugin.
     *
     * @param string $plugin_file Plugin file path.
     * @return array|WP_Error Activation result or error.
     */
    public function activate_plugin( string $plugin_file ) {
        if ( empty( $plugin_file ) ) {
            return new WP_Error( 'invalid_plugin_file', __( 'Plugin file is required.', 'kiss-smart-batch-installer' ) );
        }
        
        // Check if user has permission to activate plugins
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return new WP_Error( 'insufficient_permissions', __( 'You do not have permission to activate plugins.', 'kiss-smart-batch-installer' ) );
        }
        
        // Check if plugin is already active
        if ( is_plugin_active( $plugin_file ) ) {
            return new WP_Error( 'already_active', __( 'Plugin is already active.', 'kiss-smart-batch-installer' ) );
        }
        
        // Activate the plugin
        $result = activate_plugin( $plugin_file );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        return [
            'success' => true,
            'plugin_file' => $plugin_file,
            'message' => __( 'Plugin activated successfully.', 'kiss-smart-batch-installer' ),
        ];
    }
    
    /**
     * Deactivate a plugin.
     *
     * @param string $plugin_file Plugin file path.
     * @return array|WP_Error Deactivation result or error.
     */
    public function deactivate_plugin( string $plugin_file ) {
        if ( empty( $plugin_file ) ) {
            return new WP_Error( 'invalid_plugin_file', __( 'Plugin file is required.', 'kiss-smart-batch-installer' ) );
        }
        
        // Check if user has permission to deactivate plugins
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return new WP_Error( 'insufficient_permissions', __( 'You do not have permission to deactivate plugins.', 'kiss-smart-batch-installer' ) );
        }
        
        // Check if plugin is active
        if ( ! is_plugin_active( $plugin_file ) ) {
            return new WP_Error( 'not_active', __( 'Plugin is not active.', 'kiss-smart-batch-installer' ) );
        }
        
        // Deactivate the plugin
        deactivate_plugins( $plugin_file );
        
        return [
            'success' => true,
            'plugin_file' => $plugin_file,
            'message' => __( 'Plugin deactivated successfully.', 'kiss-smart-batch-installer' ),
        ];
    }
    
    /**
     * Install and optionally activate a plugin from a repository.
     *
     * @param string $owner Repository owner.
     * @param string $repo Repository name.
     * @param bool   $activate Whether to activate after installation.
     * @param string $branch Branch to install from.
     * @return array|WP_Error Combined installation and activation result.
     */
    public function install_and_activate( string $owner, string $repo, bool $activate = false, string $branch = 'main' ) {
        // Install the plugin
        $install_result = $this->install_plugin( $owner, $repo, $branch );
        
        if ( is_wp_error( $install_result ) ) {
            return $install_result;
        }
        
        $result = $install_result;
        
        // Activate if requested
        if ( $activate && isset( $install_result['plugin_file'] ) ) {
            $activate_result = $this->activate_plugin( $install_result['plugin_file'] );
            
            if ( is_wp_error( $activate_result ) ) {
                $result['activation_error'] = $activate_result->get_error_message();
                $result['activated'] = false;
            } else {
                $result['activated'] = true;
                $result['activation_message'] = $activate_result['message'];
            }
        } else {
            $result['activated'] = false;
        }
        
        return $result;
    }
    
    /**
     * Batch install multiple plugins.
     *
     * @param array $repositories Array of repository data with owner/repo.
     * @param bool  $activate Whether to activate plugins after installation.
     * @return array Array of installation results.
     */
    public function batch_install( array $repositories, bool $activate = false ) {
        $results = [];
        
        foreach ( $repositories as $repo_data ) {
            if ( ! isset( $repo_data['owner'] ) || ! isset( $repo_data['repo'] ) ) {
                $results[] = [
                    'repository' => $repo_data['repo'] ?? 'unknown',
                    'success' => false,
                    'error' => __( 'Invalid repository data.', 'kiss-smart-batch-installer' ),
                ];
                continue;
            }
            
            $result = $this->install_and_activate( 
                $repo_data['owner'], 
                $repo_data['repo'], 
                $activate,
                $repo_data['branch'] ?? 'main'
            );
            
            if ( is_wp_error( $result ) ) {
                $results[] = [
                    'repository' => $repo_data['repo'],
                    'success' => false,
                    'error' => $result->get_error_message(),
                ];
            } else {
                $results[] = array_merge( $result, [
                    'repository' => $repo_data['repo'],
                ] );
            }
        }
        
        return $results;
    }
}

/**
 * Custom upgrader skin to capture installation messages.
 */
class SBI_Plugin_Upgrader_Skin extends WP_Upgrader_Skin {
    
    /**
     * Messages captured during installation.
     *
     * @var array
     */
    private array $messages = [];
    
    /**
     * Capture feedback messages.
     *
     * @param string $string Message to capture.
     * @param mixed  ...$args Additional arguments.
     */
    public function feedback( $string, ...$args ) {
        if ( isset( $this->upgrader->strings[ $string ] ) ) {
            $string = $this->upgrader->strings[ $string ];
        }
        
        if ( strpos( $string, '%' ) !== false ) {
            if ( $args ) {
                $string = vsprintf( $string, $args );
            }
        }
        
        $this->messages[] = $string;
    }
    
    /**
     * Get captured messages.
     *
     * @return array Array of messages.
     */
    public function get_messages() {
        return $this->messages;
    }
}
