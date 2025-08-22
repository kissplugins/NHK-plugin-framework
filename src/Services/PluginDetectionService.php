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

        // First, try to get all PHP files from the repository root
        $root_php_files = $this->get_root_php_files( $repository );
        if ( ! empty( $root_php_files ) ) {
            $potential_files = array_merge( $potential_files, $root_php_files );
        }

        // Common plugin file patterns based on repository name
        if ( ! empty( $repo_name ) ) {
            $potential_files[] = $repo_name . '.php';
            $potential_files[] = strtolower( $repo_name ) . '.php';
            $potential_files[] = str_replace( '-', '_', $repo_name ) . '.php';
            $potential_files[] = str_replace( '_', '-', $repo_name ) . '.php';

            // Handle KISS plugin naming patterns
            if ( strpos( $repo_name, 'KISS-' ) === 0 ) {
                $kiss_name = substr( $repo_name, 5 ); // Remove 'KISS-' prefix
                $potential_files[] = 'KISS-' . strtolower( $kiss_name ) . '.php';
                $potential_files[] = strtolower( $kiss_name ) . '.php';
                $potential_files[] = str_replace( '-', '_', strtolower( $kiss_name ) ) . '.php';
            }

            // Handle common WordPress plugin naming patterns
            $clean_name = strtolower( str_replace( [ 'KISS-', 'WP-', 'WordPress-' ], '', $repo_name ) );
            $potential_files[] = $clean_name . '.php';
            $potential_files[] = str_replace( '-', '_', $clean_name ) . '.php';
        }

        // Standard WordPress plugin file names
        $potential_files[] = 'plugin.php';
        $potential_files[] = 'index.php';
        $potential_files[] = 'main.php';
        $potential_files[] = 'init.php';

        // Remove duplicates and return
        return array_unique( $potential_files );
    }

    /**
     * Get all PHP files from the repository root directory.
     *
     * @param array $repository Repository data.
     * @return array Array of PHP file names.
     */
    private function get_root_php_files( array $repository ): array {
        $php_files = [];

        // Get repository contents from GitHub API
        $contents_url = sprintf(
            'https://api.github.com/repos/%s/contents',
            $repository['full_name'] ?? ''
        );

        $response = wp_remote_get( $contents_url, [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            return $php_files;
        }

        $body = wp_remote_retrieve_body( $response );
        $contents = json_decode( $body, true );

        if ( ! is_array( $contents ) ) {
            return $php_files;
        }

        // Filter for PHP files in root directory
        foreach ( $contents as $item ) {
            if ( isset( $item['type'] ) && $item['type'] === 'file' &&
                 isset( $item['name'] ) && substr( $item['name'], -4 ) === '.php' ) {
                $php_files[] = $item['name'];
            }
        }

        // Sort by likelihood of being the main plugin file
        usort( $php_files, [ $this, 'sort_php_files_by_likelihood' ] );

        return $php_files;
    }

    /**
     * Sort PHP files by likelihood of being the main plugin file.
     *
     * @param string $a First file name.
     * @param string $b Second file name.
     * @return int Sort comparison result.
     */
    private function sort_php_files_by_likelihood( string $a, string $b ): int {
        // Priority order for main plugin files
        $priority_patterns = [
            '/^[^\/]*\.php$/',           // Single word files (highest priority)
            '/plugin\.php$/',            // plugin.php
            '/main\.php$/',              // main.php
            '/init\.php$/',              // init.php
            '/index\.php$/',             // index.php (lowest priority for main files)
        ];

        // Files to deprioritize
        $low_priority_patterns = [
            '/functions\.php$/',         // functions.php
            '/config\.php$/',           // config.php
            '/settings\.php$/',         // settings.php
            '/admin\.php$/',            // admin.php
            '/helper/',                 // helper files
            '/util/',                   // utility files
            '/class-/',                 // class files
        ];

        $a_priority = $this->get_file_priority( $a, $priority_patterns, $low_priority_patterns );
        $b_priority = $this->get_file_priority( $b, $priority_patterns, $low_priority_patterns );

        return $a_priority - $b_priority;
    }

    /**
     * Get priority score for a file.
     *
     * @param string $filename File name.
     * @param array  $priority_patterns High priority patterns.
     * @param array  $low_priority_patterns Low priority patterns.
     * @return int Priority score (lower is higher priority).
     */
    private function get_file_priority( string $filename, array $priority_patterns, array $low_priority_patterns ): int {
        // Check for low priority patterns first
        foreach ( $low_priority_patterns as $pattern ) {
            if ( preg_match( $pattern, $filename ) ) {
                return 1000; // Very low priority
            }
        }

        // Check for high priority patterns
        foreach ( $priority_patterns as $index => $pattern ) {
            if ( preg_match( $pattern, $filename ) ) {
                return $index; // Higher priority (lower number)
            }
        }

        return 500; // Medium priority
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

    /**
     * Debug method to test detection on specific repositories.
     *
     * @param array $repository_names Array of repository full names to test.
     * @return array Debug results.
     */
    public function debug_detection( array $repository_names ): array {
        $results = [];

        foreach ( $repository_names as $repo_name ) {
            // Clear cache first
            $this->clear_cache( $repo_name );

            // Create a mock repository array for detection
            $repository = [
                'full_name' => $repo_name,
                'name' => basename( $repo_name ),
            ];

            // Get potential files
            $potential_files = $this->get_potential_plugin_files( $repository );

            // Run detection with force refresh
            $detection_result = $this->detect_plugin( $repository, true );

            $results[ $repo_name ] = [
                'repository' => $repository,
                'potential_files' => $potential_files,
                'detection_result' => $detection_result,
            ];
        }

        return $results;
    }
}
