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

// Include WordPress upgrader and plugin management classes
if ( ! class_exists( 'WP_Upgrader' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
}
if ( ! class_exists( 'Plugin_Upgrader' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
}
if ( ! function_exists( 'activate_plugin' ) ) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

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
     * Progress callback function.
     *
     * @var callable|null
     */
    private $progress_callback;
    
    /**
     * Constructor.
     *
     * @param GitHubService $github_service GitHub service instance.
     */
    public function __construct( GitHubService $github_service ) {
        $this->github_service = $github_service;
    }

    /**
     * Set progress callback function.
     *
     * @param callable $callback Progress callback function.
     */
    public function set_progress_callback( callable $callback ): void {
        $this->progress_callback = $callback;
    }

    /**
     * Send progress update if callback is set.
     *
     * @param string $step Current step name.
     * @param string $status Status (info, success, error).
     * @param string $message Progress message.
     */
    private function send_progress( string $step, string $status, string $message ): void {
        if ( $this->progress_callback ) {
            call_user_func( $this->progress_callback, $step, $status, $message );
        }
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
        $this->send_progress( 'Repository Verification', 'info', 'Checking repository on GitHub...' );
        error_log( 'SBI INSTALL SERVICE: Getting repository information from GitHub' );
        $repo_info = $this->github_service->get_repository( $owner, $repo );
        if ( is_wp_error( $repo_info ) ) {
            error_log( sprintf( 'SBI INSTALL SERVICE: Failed to get repository info: %s', $repo_info->get_error_message() ) );
            $this->send_progress( 'Repository Verification', 'error', 'Repository not found or inaccessible' );
            return $repo_info;
        }

        $this->send_progress( 'Repository Verification', 'success', 'Repository found and accessible' );
        error_log( 'SBI INSTALL SERVICE: Repository information retrieved successfully' );

        // Try to get the download URL from GitHub API first
        $this->send_progress( 'Download Preparation', 'info', 'Preparing download URL...' );
        $api_download_url = $this->get_download_url_from_api( $owner, $repo, $branch );

        if ( ! is_wp_error( $api_download_url ) ) {
            $download_url = $api_download_url;
            $this->send_progress( 'Download Preparation', 'success', 'Using GitHub API download URL' );
            error_log( sprintf( 'SBI INSTALL SERVICE: Using API download URL: %s', $download_url ) );
        } else {
            // Fallback to direct GitHub archive URL - Force HTTPS
            $download_url = sprintf( 'https://github.com/%s/%s/archive/refs/heads/%s.zip',
                urlencode( $owner ),
                urlencode( $repo ),
                urlencode( $branch )
            );
            $this->send_progress( 'Download Preparation', 'info', 'Using fallback download URL' );
            error_log( sprintf( 'SBI INSTALL SERVICE: Using fallback download URL: %s', $download_url ) );
        }

        // Verify the URL is HTTPS
        if ( strpos( $download_url, 'https://' ) !== 0 ) {
            error_log( 'SBI INSTALL SERVICE: ERROR - Download URL is not HTTPS: ' . $download_url );
            return new WP_Error( 'invalid_url', __( 'Download URL must use HTTPS.', 'kiss-smart-batch-installer' ) );
        }

        // Create a custom skin to capture output
        error_log( 'SBI INSTALL SERVICE: Creating upgrader skin' );
        $skin = new SBI_Plugin_Upgrader_Skin();

        // Create upgrader instance
        error_log( 'SBI INSTALL SERVICE: Creating Plugin_Upgrader instance' );
        $upgrader = new Plugin_Upgrader( $skin );

        // Install the plugin
        $this->send_progress( 'Plugin Download', 'info', 'Downloading plugin from GitHub...' );
        error_log( sprintf( 'SBI INSTALL SERVICE: Starting plugin installation from %s', $download_url ) );

        // Add filter to monitor and force HTTPS for all requests
        $https_filter = function( $args, $url ) use ( $download_url ) {
            // Force HTTPS for any GitHub-related URLs
            if ( strpos( $url, 'github.com' ) !== false || strpos( $url, 'githubusercontent.com' ) !== false ) {
                error_log( sprintf( 'SBI INSTALL SERVICE: HTTP request for GitHub URL: %s', $url ) );

                // Convert HTTP to HTTPS if needed
                if ( strpos( $url, 'http://' ) === 0 ) {
                    $url = str_replace( 'http://', 'https://', $url );
                    error_log( sprintf( 'SBI INSTALL SERVICE: Converted to HTTPS: %s', $url ) );
                }

                // Force HTTPS settings
                $args['sslverify'] = true;
                $args['timeout'] = 30;
                $args['redirection'] = 5;
            }
            return $args;
        };

        add_filter( 'http_request_args', $https_filter, 10, 2 );

        // Add filter to monitor responses
        $response_filter = function( $response, $args, $url ) use ( $download_url ) {
            if ( strpos( $url, 'github.com' ) !== false || strpos( $url, 'githubusercontent.com' ) !== false ) {
                $response_code = wp_remote_retrieve_response_code( $response );
                error_log( sprintf( 'SBI INSTALL SERVICE: HTTP response for %s: Code %d', $url, $response_code ) );
            }
            return $response;
        };

        add_filter( 'http_response', $response_filter, 10, 3 );

        add_filter( 'http_response', function( $response, $args, $url ) use ( $download_url ) {
            if ( $url === $download_url ) {
                $response_code = wp_remote_retrieve_response_code( $response );
                $headers = wp_remote_retrieve_headers( $response );
                error_log( sprintf( 'SBI INSTALL SERVICE: HTTP response for %s: Code %d, Headers: %s',
                    $url, $response_code, json_encode( $headers ) ) );
            }
            return $response;
        }, 10, 3 );

        $result = $upgrader->install( $download_url );

        // Remove filters after installation
        remove_filter( 'http_request_args', $https_filter, 10 );
        remove_filter( 'http_response', $response_filter, 10 );

        error_log( sprintf( 'SBI INSTALL SERVICE: Installation result: %s',
            is_wp_error( $result ) ? 'WP_Error: ' . $result->get_error_message() :
            ( $result ? 'Success' : 'Failed (false)' ) ) );

        if ( is_wp_error( $result ) ) {
            $this->send_progress( 'Plugin Installation', 'error', 'Installation failed: ' . $result->get_error_message() );
            error_log( sprintf( 'SBI INSTALL SERVICE: Installation failed with WP_Error: %s', $result->get_error_message() ) );
            return $result;
        }

        if ( ! $result ) {
            $messages = $skin->get_messages();
            $this->send_progress( 'Plugin Installation', 'error', 'Installation failed - see debug log for details' );
            error_log( 'SBI INSTALL SERVICE: Installation failed - upgrader returned false' );
            error_log( sprintf( 'SBI INSTALL SERVICE: Upgrader messages: %s', implode( '; ', $messages ) ) );
            return new WP_Error( 'installation_failed', __( 'Plugin installation failed.', 'kiss-smart-batch-installer' ) );
        }

        $this->send_progress( 'Plugin Installation', 'success', 'Plugin files downloaded and extracted successfully' );

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
            $this->send_progress( 'Plugin Activation', 'info', 'Activating plugin...' );
            $activate_result = $this->activate_plugin( $install_result['plugin_file'] );

            if ( is_wp_error( $activate_result ) ) {
                $this->send_progress( 'Plugin Activation', 'error', 'Activation failed: ' . $activate_result->get_error_message() );
                $result['activation_error'] = $activate_result->get_error_message();
                $result['activated'] = false;
            } else {
                $this->send_progress( 'Plugin Activation', 'success', 'Plugin activated successfully' );
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

    /**
     * Get download URL from GitHub API.
     *
     * @param string $owner Repository owner.
     * @param string $repo Repository name.
     * @param string $branch Branch name.
     * @return string|WP_Error Download URL or error.
     */
    private function get_download_url_from_api( string $owner, string $repo, string $branch ) {
        $api_url = sprintf( 'https://api.github.com/repos/%s/%s/zipball/%s',
            urlencode( $owner ),
            urlencode( $repo ),
            urlencode( $branch )
        );

        error_log( sprintf( 'SBI INSTALL SERVICE: Checking API download URL: %s', $api_url ) );

        // Make a HEAD request to get the redirect URL
        $response = wp_remote_head( $api_url, [
            'timeout' => 15,
            'redirection' => 0, // Don't follow redirects
            'headers' => [
                'User-Agent' => 'KISS-Smart-Batch-Installer/1.0.0',
                'Accept' => 'application/vnd.github.v3+json',
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            error_log( sprintf( 'SBI INSTALL SERVICE: API request failed: %s', $response->get_error_message() ) );
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );

        // GitHub API returns 302 with Location header for download
        if ( $response_code === 302 ) {
            $headers = wp_remote_retrieve_headers( $response );
            $location = $headers['location'] ?? '';

            if ( ! empty( $location ) && strpos( $location, 'https://' ) === 0 ) {
                error_log( sprintf( 'SBI INSTALL SERVICE: Got API redirect to: %s', $location ) );
                return $location;
            }
        }

        error_log( sprintf( 'SBI INSTALL SERVICE: API request returned code %d, falling back to direct URL', $response_code ) );
        return new WP_Error( 'api_download_failed', 'Could not get download URL from API' );
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
