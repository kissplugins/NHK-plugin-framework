<?php
/**
 * Plugin Detection Service for scanning WordPress plugin headers.
 *
 * @package SBI\Services
 */

namespace SBI\Services;

use WP_Error;

/**
 * Detects WordPress plugins in GitHub repositories.
 */
class PluginDetectionService {
    
    /**
     * Cache expiration time (24 hours).
     */
    private const CACHE_EXPIRATION = DAY_IN_SECONDS;
    
    /**
     * User agent for API requests.
     */
    private const USER_AGENT = 'KISS-Smart-Batch-Installer/1.0.0';
    
    /**
     * Required WordPress plugin headers.
     */
    private const REQUIRED_HEADERS = [ 'Plugin Name' ];
    
    /**
     * Optional WordPress plugin headers.
     */
    private const OPTIONAL_HEADERS = [
        'Plugin URI',
        'Description',
        'Version',
        'Author',
        'Author URI',
        'Text Domain',
        'Domain Path',
        'Requires at least',
        'Tested up to',
        'Requires PHP',
        'Network',
        'License',
        'License URI',
    ];
    
    /**
     * Detect if a repository contains a WordPress plugin.
     *
     * @param array $repository Repository data from GitHubService.
     * @param bool  $force_refresh Whether to bypass cache.
     * @return array|WP_Error Plugin detection result or WP_Error on failure.
     */
    public function detect_plugin( array $repository, bool $force_refresh = false ) {
        if ( empty( $repository['full_name'] ) ) {
            return new WP_Error( 'invalid_repository', __( 'Repository data is invalid.', 'kiss-smart-batch-installer' ) );
        }
        
        $cache_key = 'sbi_plugin_detection_' . sanitize_key( $repository['full_name'] );
        
        // Check cache first unless force refresh
        if ( ! $force_refresh ) {
            $cached_result = get_transient( $cache_key );
            if ( false !== $cached_result ) {
                return $cached_result;
            }
        }
        
        // Scan repository for WordPress plugin files
        $detection_result = $this->scan_repository_for_plugin( $repository );
        
        // Cache the result
        set_transient( $cache_key, $detection_result, self::CACHE_EXPIRATION );
        
        return $detection_result;
    }
    
    /**
     * Scan repository for WordPress plugin files.
     *
     * @param array $repository Repository data.
     * @return array Detection result.
     */
    private function scan_repository_for_plugin( array $repository ): array {
        $result = [
            'is_plugin' => false,
            'plugin_file' => '',
            'plugin_data' => [],
            'scan_method' => '',
            'error' => null,
        ];
        
        // Try to find main plugin file by common patterns
        $potential_files = $this->get_potential_plugin_files( $repository );
        
        foreach ( $potential_files as $file_path ) {
            $plugin_data = $this->scan_file_for_plugin_headers( $repository, $file_path );
            
            if ( ! is_wp_error( $plugin_data ) && ! empty( $plugin_data ) ) {
                $result['is_plugin'] = true;
                $result['plugin_file'] = $file_path;
                $result['plugin_data'] = $plugin_data;
                $result['scan_method'] = 'header_scan';
                break;
            }
        }
        
        return $result;
    }
    
    /**
     * Get potential plugin file paths to scan.
     *
     * @param array $repository Repository data.
     * @return array Array of file paths to check.
     */
    private function get_potential_plugin_files( array $repository ): array {
        $repo_name = $repository['name'] ?? '';
        $potential_files = [];
        
        // Common plugin file patterns
        if ( ! empty( $repo_name ) ) {
            $potential_files[] = $repo_name . '.php';
            $potential_files[] = strtolower( $repo_name ) . '.php';
            $potential_files[] = str_replace( '-', '_', $repo_name ) . '.php';
        }
        
        // Standard WordPress plugin file names
        $potential_files[] = 'plugin.php';
        $potential_files[] = 'index.php';
        $potential_files[] = 'main.php';
        
        // Remove duplicates and return
        return array_unique( $potential_files );
    }
    
    /**
     * Scan a specific file for WordPress plugin headers.
     *
     * @param array  $repository Repository data.
     * @param string $file_path  File path to scan.
     * @return array|WP_Error Plugin headers or WP_Error on failure.
     */
    private function scan_file_for_plugin_headers( array $repository, string $file_path ) {
        $file_content = $this->get_file_content( $repository, $file_path );
        
        if ( is_wp_error( $file_content ) ) {
            return $file_content;
        }
        
        return $this->parse_plugin_headers( $file_content );
    }
    
    /**
     * Get file content from GitHub repository.
     *
     * @param array  $repository Repository data.
     * @param string $file_path  File path.
     * @return string|WP_Error File content or WP_Error on failure.
     */
    private function get_file_content( array $repository, string $file_path ) {
        $owner_repo = $repository['full_name'] ?? '';
        $branch = $repository['default_branch'] ?? 'main';
        
        if ( empty( $owner_repo ) ) {
            return new WP_Error( 'invalid_repository', __( 'Repository name is missing.', 'kiss-smart-batch-installer' ) );
        }
        
        // GitHub raw content URL
        $url = sprintf( 
            'https://raw.githubusercontent.com/%s/%s/%s',
            $owner_repo,
            $branch,
            $file_path
        );
        
        $args = [
            'timeout' => 10,
            'headers' => [
                'User-Agent' => self::USER_AGENT,
            ],
        ];
        
        $response = wp_remote_get( $url, $args );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $response_code ) {
            return new WP_Error( 
                'file_not_found', 
                sprintf( __( 'File not found: %s (HTTP %d)', 'kiss-smart-batch-installer' ), $file_path, $response_code )
            );
        }
        
        return wp_remote_retrieve_body( $response );
    }
    
    /**
     * Parse WordPress plugin headers from file content.
     *
     * @param string $file_content File content to parse.
     * @return array Plugin headers.
     */
    private function parse_plugin_headers( string $file_content ): array {
        $headers = [];
        
        // Only scan the first 8 kilobytes for performance
        $file_content = substr( $file_content, 0, 8192 );
        
        // Check if file starts with PHP opening tag
        if ( ! preg_match( '/^\s*<\?php/i', $file_content ) ) {
            return $headers;
        }
        
        // Extract plugin headers using WordPress-style parsing
        $all_headers = array_merge( self::REQUIRED_HEADERS, self::OPTIONAL_HEADERS );
        
        foreach ( $all_headers as $header ) {
            $pattern = '/^[ \t\/*#@]*' . preg_quote( $header, '/' ) . ':(.*)$/mi';
            if ( preg_match( $pattern, $file_content, $matches ) ) {
                $headers[ $header ] = $this->clean_header_value( $matches[1] );
            }
        }
        
        // Only return headers if we found at least the required ones
        foreach ( self::REQUIRED_HEADERS as $required_header ) {
            if ( empty( $headers[ $required_header ] ) ) {
                return [];
            }
        }
        
        return $headers;
    }
    
    /**
     * Clean and sanitize header value.
     *
     * @param string $value Raw header value.
     * @return string Cleaned header value.
     */
    private function clean_header_value( string $value ): string {
        // Remove comment markers and whitespace
        $value = trim( $value );
        $value = preg_replace( '/\s*(?:\*\/|\?>).*/', '', $value );
        $value = preg_replace( '/^[\s\/*#@]*/', '', $value );
        $value = trim( $value );
        
        return $value;
    }
    
    /**
     * Batch detect plugins for multiple repositories.
     *
     * @param array $repositories Array of repository data.
     * @param bool  $force_refresh Whether to bypass cache.
     * @return array Array of detection results keyed by repository name.
     */
    public function batch_detect_plugins( array $repositories, bool $force_refresh = false ): array {
        $results = [];
        
        foreach ( $repositories as $repository ) {
            if ( empty( $repository['full_name'] ) ) {
                continue;
            }
            
            $detection_result = $this->detect_plugin( $repository, $force_refresh );
            $results[ $repository['full_name'] ] = $detection_result;
        }
        
        return $results;
    }
    
    /**
     * Clear plugin detection cache.
     *
     * @param string $repository_name Repository name (optional).
     * @return bool True on success.
     */
    public function clear_cache( string $repository_name = '' ): bool {
        if ( ! empty( $repository_name ) ) {
            $cache_key = 'sbi_plugin_detection_' . sanitize_key( $repository_name );
            return delete_transient( $cache_key );
        }
        
        // Clear all plugin detection transients
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sbi_plugin_detection_%'" );
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_sbi_plugin_detection_%'" );
        
        return true;
    }

    /**
     * Clear cache for a specific repository.
     *
     * @param string $repository_full_name Repository full name (owner/repo).
     * @return bool True on success.
     */
    public function clear_repository_cache( string $repository_full_name ): bool {
        return $this->clear_cache( $repository_full_name );
    }
}
