<?php
/**
 * GitHub Repository Service for fetching public repositories.
 *
 * @package SBI\Services
 */

namespace SBI\Services;

use WP_Error;

/**
 * Handles GitHub API interactions for public repositories.
 */
class GitHubService {
    
    /**
     * GitHub API base URL.
     */
    private const API_BASE = 'https://api.github.com';
    
    /**
     * Cache expiration time (1 hour).
     */
    private const CACHE_EXPIRATION = HOUR_IN_SECONDS;
    
    /**
     * User agent for API requests.
     */
    private const USER_AGENT = 'KISS-Smart-Batch-Installer/1.0.0';
    
    /**
     * Fetch repositories for a GitHub organization.
     *
     * @param string $organization GitHub organization name.
     * @param bool   $force_refresh Whether to bypass cache.
     * @return array|WP_Error Array of repositories or WP_Error on failure.
     */
    public function get_organization_repositories( string $organization, bool $force_refresh = false ) {
        if ( empty( $organization ) ) {
            return new WP_Error( 'invalid_organization', __( 'Organization name cannot be empty.', 'kiss-smart-batch-installer' ) );
        }
        
        $cache_key = 'sbi_github_repos_' . sanitize_key( $organization );
        
        // Check cache first unless force refresh
        if ( ! $force_refresh ) {
            $cached_data = get_transient( $cache_key );
            if ( false !== $cached_data ) {
                return $cached_data;
            }
        }
        
        // Fetch from GitHub API
        $url = sprintf( '%s/orgs/%s/repos', self::API_BASE, urlencode( $organization ) );
        $args = [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'application/vnd.github.v3+json',
            ],
            'body' => [
                'type' => 'public',
                'sort' => 'updated',
                'per_page' => 50,
            ],
        ];
        
        $response = wp_remote_get( add_query_arg( $args['body'], $url ), $args );
        
        if ( is_wp_error( $response ) ) {
            return new WP_Error( 
                'github_request_failed', 
                sprintf( __( 'Failed to fetch repositories: %s', 'kiss-smart-batch-installer' ), $response->get_error_message() )
            );
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $response_code ) {
            return new WP_Error( 
                'github_api_error', 
                sprintf( __( 'GitHub API returned error code: %d', 'kiss-smart-batch-installer' ), $response_code )
            );
        }
        
        $body = wp_remote_retrieve_body( $response );
        $repositories = json_decode( $body, true );
        
        if ( null === $repositories ) {
            return new WP_Error( 'invalid_json', __( 'Invalid JSON response from GitHub API.', 'kiss-smart-batch-installer' ) );
        }
        
        // Process and filter repositories
        $processed_repos = $this->process_repositories( $repositories );
        
        // Cache the results
        set_transient( $cache_key, $processed_repos, self::CACHE_EXPIRATION );
        
        return $processed_repos;
    }
    
    /**
     * Get a specific repository.
     *
     * @param string $owner Repository owner.
     * @param string $repo  Repository name.
     * @return array|WP_Error Repository data or WP_Error on failure.
     */
    public function get_repository( string $owner, string $repo ) {
        if ( empty( $owner ) || empty( $repo ) ) {
            return new WP_Error( 'invalid_params', __( 'Owner and repository name are required.', 'kiss-smart-batch-installer' ) );
        }
        
        $cache_key = 'sbi_github_repo_' . sanitize_key( $owner . '_' . $repo );
        
        // Check cache first
        $cached_data = get_transient( $cache_key );
        if ( false !== $cached_data ) {
            return $cached_data;
        }
        
        $url = sprintf( '%s/repos/%s/%s', self::API_BASE, urlencode( $owner ), urlencode( $repo ) );
        $args = [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'application/vnd.github.v3+json',
            ],
        ];
        
        $response = wp_remote_get( $url, $args );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $response_code ) {
            return new WP_Error( 
                'github_api_error', 
                sprintf( __( 'Repository not found or API error: %d', 'kiss-smart-batch-installer' ), $response_code )
            );
        }
        
        $body = wp_remote_retrieve_body( $response );
        $repository = json_decode( $body, true );
        
        if ( null === $repository ) {
            return new WP_Error( 'invalid_json', __( 'Invalid JSON response from GitHub API.', 'kiss-smart-batch-installer' ) );
        }
        
        $processed_repo = $this->process_repository( $repository );
        
        // Cache for shorter time for individual repos
        set_transient( $cache_key, $processed_repo, HOUR_IN_SECONDS / 2 );
        
        return $processed_repo;
    }
    
    /**
     * Get GitHub API rate limit status.
     *
     * @return array|WP_Error Rate limit data or WP_Error on failure.
     */
    public function get_rate_limit() {
        $url = self::API_BASE . '/rate_limit';
        $args = [
            'timeout' => 10,
            'headers' => [
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'application/vnd.github.v3+json',
            ],
        ];
        
        $response = wp_remote_get( $url, $args );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $rate_limit = json_decode( $body, true );
        
        return $rate_limit ?: new WP_Error( 'invalid_json', __( 'Invalid rate limit response.', 'kiss-smart-batch-installer' ) );
    }
    
    /**
     * Process array of repositories from GitHub API.
     *
     * @param array $repositories Raw repository data from GitHub.
     * @return array Processed repository data.
     */
    private function process_repositories( array $repositories ): array {
        $processed = [];
        
        foreach ( $repositories as $repo ) {
            $processed[] = $this->process_repository( $repo );
        }
        
        return $processed;
    }
    
    /**
     * Process a single repository from GitHub API.
     *
     * @param array $repo Raw repository data from GitHub.
     * @return array Processed repository data.
     */
    private function process_repository( array $repo ): array {
        return [
            'id' => $repo['id'] ?? 0,
            'name' => $repo['name'] ?? '',
            'full_name' => $repo['full_name'] ?? '',
            'description' => $repo['description'] ?? '',
            'html_url' => $repo['html_url'] ?? '',
            'clone_url' => $repo['clone_url'] ?? '',
            'default_branch' => $repo['default_branch'] ?? 'main',
            'updated_at' => $repo['updated_at'] ?? '',
            'language' => $repo['language'] ?? '',
            'size' => $repo['size'] ?? 0,
            'stargazers_count' => $repo['stargazers_count'] ?? 0,
            'archived' => $repo['archived'] ?? false,
            'disabled' => $repo['disabled'] ?? false,
            'private' => $repo['private'] ?? false,
        ];
    }
    
    /**
     * Clear cached repository data.
     *
     * @param string $organization Organization name (optional).
     * @return bool True on success.
     */
    public function clear_cache( string $organization = '' ): bool {
        if ( ! empty( $organization ) ) {
            $cache_key = 'sbi_github_repos_' . sanitize_key( $organization );
            return delete_transient( $cache_key );
        }
        
        // Clear all GitHub-related transients
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sbi_github_%'" );
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_sbi_github_%'" );
        
        return true;
    }
}
