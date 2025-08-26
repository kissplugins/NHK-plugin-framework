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
     * GitHub web base URL for fallback.
     */
    private const WEB_BASE = 'https://github.com';

    /**
     * Cache expiration time (1 hour).
     */
    private const CACHE_EXPIRATION = HOUR_IN_SECONDS;

    /**
     * User agent for API requests.
     */
    private const USER_AGENT = 'KISS-Smart-Batch-Installer/1.0.0';

    /**
     * Get current GitHub configuration.
     *
     * @return array Configuration array with username and repositories.
     */
    public function get_configuration(): array {
        $organization = get_option( 'sbi_github_organization', '' );
        $repositories = [];

        // If organization is configured, fetch repositories
        if ( ! empty( $organization ) ) {
            $repos = $this->fetch_repositories_for_account( $organization );
            if ( ! is_wp_error( $repos ) && is_array( $repos ) ) {
                $repositories = array_column( $repos, 'full_name' );
            }
        }

        return [
            'username' => $organization, // For backward compatibility with tests
            'organization' => $organization,
            'repositories' => $repositories,
            'fetch_method' => get_option( 'sbi_fetch_method', 'web_only' ),
            'repository_limit' => get_option( 'sbi_repository_limit', 1 ),
            'skip_plugin_detection' => get_option( 'sbi_skip_plugin_detection', 0 ),
            'debug_ajax' => get_option( 'sbi_debug_ajax', 0 )
        ];
    }

    /**
     * Determine if a GitHub account is a user or organization
     *
     * @param string $account_name GitHub account name
     * @return string|WP_Error 'User' or 'Organization' on success, WP_Error on failure
     */
    private function get_account_type( string $account_name ) {
        // Try organization first
        $org_url = sprintf( '%s/orgs/%s', self::API_BASE, urlencode( $account_name ) );
        $args = [
            'timeout' => 10,
            'headers' => [
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'application/vnd.github.v3+json',
            ],
        ];

        $response = wp_remote_get( $org_url, $args );

        if ( ! is_wp_error( $response ) ) {
            $response_code = wp_remote_retrieve_response_code( $response );
            if ( 200 === $response_code ) {
                return 'Organization';
            } elseif ( 403 === $response_code ) {
                // Check if it's a rate limit issue
                $response_body = wp_remote_retrieve_body( $response );
                if ( strpos( $response_body, 'rate limit' ) !== false ) {
                    return new WP_Error(
                        'rate_limit_exceeded',
                        __( 'GitHub API rate limit exceeded. Please try again later or consider using a GitHub token for higher limits.', 'kiss-smart-batch-installer' )
                    );
                }
            }
        }

        // Try user
        $user_url = sprintf( '%s/users/%s', self::API_BASE, urlencode( $account_name ) );
        $response = wp_remote_get( $user_url, $args );

        if ( ! is_wp_error( $response ) ) {
            $response_code = wp_remote_retrieve_response_code( $response );
            if ( 200 === $response_code ) {
                return 'User';
            } elseif ( 403 === $response_code ) {
                // Check if it's a rate limit issue
                $response_body = wp_remote_retrieve_body( $response );
                if ( strpos( $response_body, 'rate limit' ) !== false ) {
                    return new WP_Error(
                        'rate_limit_exceeded',
                        __( 'GitHub API rate limit exceeded. Please try again later or consider using a GitHub token for higher limits.', 'kiss-smart-batch-installer' )
                    );
                }
            }
        }

        return new WP_Error(
            'account_not_found',
            sprintf( __( 'GitHub account "%s" not found or API limit exceeded.', 'kiss-smart-batch-installer' ), $account_name )
        );
    }

    /**
     * Fetch repositories from configured GitHub organization.
     * This is a convenience method for tests and internal use.
     *
     * @param bool $force_refresh Whether to bypass cache
     * @param int  $limit Maximum number of repositories to return (0 = no limit)
     * @return array|WP_Error Array of repositories or WP_Error on failure
     */
    public function fetch_repositories( bool $force_refresh = false, int $limit = 0 ) {
        $organization = get_option( 'sbi_github_organization', '' );

        if ( empty( $organization ) ) {
            return new WP_Error( 'no_organization', __( 'No GitHub organization configured.', 'kiss-smart-batch-installer' ) );
        }

        return $this->fetch_repositories_for_account( $organization, $force_refresh, $limit );
    }

    /**
     * Fetch repositories from GitHub account (user or organization)
     *
     * @param string $account_name GitHub account name
     * @param bool   $force_refresh Whether to bypass cache
     * @param int    $limit Maximum number of repositories to return (0 = no limit)
     * @return array|WP_Error Array of repositories or WP_Error on failure
     */
    public function fetch_repositories_for_account( string $account_name, bool $force_refresh = false, int $limit = 0 ) {
        if ( empty( $account_name ) ) {
            return new WP_Error( 'invalid_account', __( 'Account name cannot be empty.', 'kiss-smart-batch-installer' ) );
        }

        // v2 cache key to avoid old caches that stored limited results
        $cache_key = 'sbi_github_repos_v2_' . sanitize_key( $account_name );

        // Check cache first unless force refresh
        if ( ! $force_refresh ) {
            $cached_data = get_transient( $cache_key );
            if ( false !== $cached_data && is_array( $cached_data ) ) {
                // Always slice per-request; cache stores full list
                return ( $limit > 0 && count( $cached_data ) > $limit )
                    ? array_slice( $cached_data, 0, $limit )
                    : $cached_data;
            }
        }

        // Check user preference for fetch method
        $fetch_method = get_option( 'sbi_fetch_method', 'web_only' ); // Default to web-only due to API reliability issues

        // If user prefers web-only, skip API entirely
        if ( 'web_only' === $fetch_method ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KISS Smart Batch Installer: Using web-only method per user preference' );
            }

            $web_result = $this->fetch_repositories_via_web( $account_name );
            if ( ! is_wp_error( $web_result ) ) {
                $processed_repos = $this->process_repositories( $web_result );

                // Cache the full list, not the limited slice
                set_transient( $cache_key, $processed_repos, self::CACHE_EXPIRATION );

                // Return per-request limited view
                return ( $limit > 0 && count( $processed_repos ) > $limit )
                    ? array_slice( $processed_repos, 0, $limit )
                    : $processed_repos;
            }

            return $web_result; // Return the web error
        }

        // Try users endpoint first (more common and efficient)
        $base_url = sprintf( '%s/users/%s/repos', self::API_BASE, urlencode( $account_name ) );
        $query_params = [
            'type' => 'public',
            'sort' => 'updated',
            'per_page' => 50,
        ];
        $url = add_query_arg( $query_params, $base_url );

        $args = [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'application/vnd.github.v3+json',
            ],
        ];

        // Debug logging
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KISS Smart Batch Installer: Fetching from URL: ' . $url );
        }

        $response = $this->fetch_with_retry( $url, $args );

        if ( is_wp_error( $response ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KISS Smart Batch Installer: GitHub API error: ' . $response->get_error_message() );
            }
            return new WP_Error(
                'github_request_failed',
                sprintf( __( 'Failed to fetch repositories: %s', 'kiss-smart-batch-installer' ), $response->get_error_message() )
            );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KISS Smart Batch Installer: GitHub API response status: ' . $response_code );
        }

        // If user endpoint fails with 404, try organization endpoint
        if ( 404 === $response_code ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KISS Smart Batch Installer: User endpoint failed, trying organization endpoint' );
            }

            // Try organization endpoint
            $org_base_url = sprintf( '%s/orgs/%s/repos', self::API_BASE, urlencode( $account_name ) );
            $org_url = add_query_arg( $query_params, $org_base_url );

            $response = $this->fetch_with_retry( $org_url, $args );

            if ( is_wp_error( $response ) ) {
                return new WP_Error(
                    'github_request_failed',
                    sprintf( __( 'Failed to fetch repositories: %s', 'kiss-smart-batch-installer' ), $response->get_error_message() )
                );
            }

            $response_code = wp_remote_retrieve_response_code( $response );
        }

        if ( 200 !== $response_code ) {
            $response_body = wp_remote_retrieve_body( $response );
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KISS Smart Batch Installer: GitHub API error response: ' . $response_body );
            }

            // Handle rate limiting specifically - try web fallback if allowed
            if ( 403 === $response_code && strpos( $response_body, 'rate limit' ) !== false ) {
                // Only try web fallback if user allows it
                if ( 'api_only' !== $fetch_method ) {
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( 'KISS Smart Batch Installer: API rate limited, trying web fallback' );
                    }

                    $web_result = $this->fetch_repositories_via_web( $account_name );
                    if ( ! is_wp_error( $web_result ) ) {
                        // Process the web-scraped repositories
                        $processed_repos = $this->process_repositories( $web_result );

                        // Cache the full results
                        set_transient( $cache_key, $processed_repos, self::CACHE_EXPIRATION );

                        // Return per-request limited view
                        return ( $limit > 0 && count( $processed_repos ) > $limit )
                            ? array_slice( $processed_repos, 0, $limit )
                            : $processed_repos;
                    }

                    // If web fallback also fails, return the original rate limit error
                    return new WP_Error(
                        'rate_limit_exceeded',
                        __( 'GitHub API rate limit exceeded and web fallback failed. Please try again later.', 'kiss-smart-batch-installer' )
                    );
                } else {
                    // User prefers API-only, don't try web fallback
                    return new WP_Error(
                        'rate_limit_exceeded',
                        __( 'GitHub API rate limit exceeded. Please try again later or change fetch method to allow web fallback.', 'kiss-smart-batch-installer' )
                    );
                }
            }

            // Handle account not found - try web fallback if allowed
            if ( 404 === $response_code ) {
                // Only try web fallback if user allows it
                if ( 'api_only' !== $fetch_method ) {
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( 'KISS Smart Batch Installer: API returned 404, trying web fallback' );
                    }

                    $web_result = $this->fetch_repositories_via_web( $account_name );
                    if ( ! is_wp_error( $web_result ) ) {
                        // Process the web-scraped repositories
                        $processed_repos = $this->process_repositories( $web_result );

                        // Cache the full results
                        set_transient( $cache_key, $processed_repos, self::CACHE_EXPIRATION );

                        // Return per-request limited view
                        return ( $limit > 0 && count( $processed_repos ) > $limit )
                            ? array_slice( $processed_repos, 0, $limit )
                            : $processed_repos;
                    }
                }

                // If web fallback also fails or not allowed, return account not found
                return new WP_Error(
                    'account_not_found',
                    sprintf( __( 'GitHub account "%s" not found.', 'kiss-smart-batch-installer' ), $account_name )
                );
            }

            return new WP_Error(
                'github_api_error',
                sprintf( __( 'GitHub API returned error code: %d', 'kiss-smart-batch-installer' ), $response_code )
            );
        }

        $body = wp_remote_retrieve_body( $response );
        $repositories = json_decode( $body, true );

        if ( null === $repositories ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KISS Smart Batch Installer: Invalid JSON response: ' . substr( $body, 0, 500 ) );
            }
            return new WP_Error( 'invalid_json', __( 'Invalid JSON response from GitHub API.', 'kiss-smart-batch-installer' ) );
        }

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KISS Smart Batch Installer: Found ' . count( $repositories ) . ' repositories from GitHub API' );
        }

        // Process and filter repositories
        $processed_repos = $this->process_repositories( $repositories );

        // Cache the full results for 1 hour
        set_transient( $cache_key, $processed_repos, HOUR_IN_SECONDS );

        // Return per-request limited view
        return ( $limit > 0 && count( $processed_repos ) > $limit )
            ? array_slice( $processed_repos, 0, $limit )
            : $processed_repos;
    }
    /**
     * Get total number of public repositories for a GitHub account.
     * Tries organization endpoint first, then user endpoint. Caches for 10 minutes.
     *
     * @param string $account_name
     * @return int|WP_Error Total public repos or WP_Error on failure
     */
    public function get_total_public_repos( string $account_name ) {
        if ( empty( $account_name ) ) {
            return new WP_Error( 'invalid_account', __( 'Account name cannot be empty.', 'kiss-smart-batch-installer' ) );
        }

        $cache_key = 'sbi_github_total_repos_' . sanitize_key( $account_name );
        $cached = get_transient( $cache_key );
        if ( false !== $cached ) {
            return (int) $cached;
        }

        $args = [
            'timeout' => 10,
            'headers' => [
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'application/vnd.github.v3+json',
            ],
        ];

        // Try organization endpoint first
        $org_url = sprintf( '%s/orgs/%s', self::API_BASE, urlencode( $account_name ) );
        $response = wp_remote_get( $org_url, $args );
        if ( ! is_wp_error( $response ) ) {
            $code = wp_remote_retrieve_response_code( $response );
            if ( 200 === $code ) {
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( is_array( $body ) && isset( $body['public_repos'] ) ) {
                    set_transient( $cache_key, (int) $body['public_repos'], 10 * MINUTE_IN_SECONDS );
                    return (int) $body['public_repos'];
                }
            }
        }

        // Fall back to user endpoint
        $user_url = sprintf( '%s/users/%s', self::API_BASE, urlencode( $account_name ) );
        $response = wp_remote_get( $user_url, $args );
        if ( ! is_wp_error( $response ) ) {
            $code = wp_remote_retrieve_response_code( $response );
            if ( 200 === $code ) {
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( is_array( $body ) && isset( $body['public_repos'] ) ) {
                    set_transient( $cache_key, (int) $body['public_repos'], 10 * MINUTE_IN_SECONDS );
                    return (int) $body['public_repos'];
                }
            }
        }

        return new WP_Error( 'total_repos_unavailable', __( 'Unable to retrieve total repositories for account.', 'kiss-smart-batch-installer' ) );
    }



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
        $base_url = sprintf( '%s/orgs/%s/repos', self::API_BASE, urlencode( $organization ) );
        $query_params = [
            'type' => 'public',
            'sort' => 'updated',
            'per_page' => 50,
        ];
        $url = add_query_arg( $query_params, $base_url );

        $args = [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'application/vnd.github.v3+json',
            ],
        ];

        // Debug logging
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KISS Smart Batch Installer: Fetching from URL: ' . $url );
        }

        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KISS Smart Batch Installer: GitHub API error: ' . $response->get_error_message() );
            }
            return new WP_Error(
                'github_request_failed',
                sprintf( __( 'Failed to fetch repositories: %s', 'kiss-smart-batch-installer' ), $response->get_error_message() )
            );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KISS Smart Batch Installer: GitHub API response status: ' . $response_code );
        }

        if ( 200 !== $response_code ) {
            $response_body = wp_remote_retrieve_body( $response );
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KISS Smart Batch Installer: GitHub API error response: ' . $response_body );
            }
            return new WP_Error(
                'github_api_error',
                sprintf( __( 'GitHub API returned error code: %d', 'kiss-smart-batch-installer' ), $response_code )
            );
        }

        $body = wp_remote_retrieve_body( $response );
        $repositories = json_decode( $body, true );

        if ( null === $repositories ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KISS Smart Batch Installer: Invalid JSON response: ' . substr( $body, 0, 500 ) );
            }
            return new WP_Error( 'invalid_json', __( 'Invalid JSON response from GitHub API.', 'kiss-smart-batch-installer' ) );
        }

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KISS Smart Batch Installer: Found ' . count( $repositories ) . ' repositories from GitHub API' );
        }

        // Process and filter repositories
        $processed_repos = $this->process_repositories( $repositories );

        // Apply limit if specified
        if ( $limit > 0 && count( $processed_repos ) > $limit ) {
            $processed_repos = array_slice( $processed_repos, 0, $limit );
        }

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
        error_log( sprintf( 'SBI GITHUB SERVICE: Getting repository %s/%s', $owner, $repo ) );

        if ( empty( $owner ) || empty( $repo ) ) {
            error_log( 'SBI GITHUB SERVICE: Invalid parameters - owner or repo empty' );
            return new WP_Error( 'invalid_params', __( 'Owner and repository name are required.', 'kiss-smart-batch-installer' ) );
        }

        $cache_key = 'sbi_github_repo_' . sanitize_key( $owner . '_' . $repo );

        // Check cache first
        $cached_data = get_transient( $cache_key );
        if ( false !== $cached_data ) {
            error_log( sprintf( 'SBI GITHUB SERVICE: Using cached data for %s/%s', $owner, $repo ) );
            return $cached_data;
        }

        $url = sprintf( '%s/repos/%s/%s', self::API_BASE, urlencode( $owner ), urlencode( $repo ) );
        error_log( sprintf( 'SBI GITHUB SERVICE: Making API request to %s', $url ) );

        $args = [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'application/vnd.github.v3+json',
            ],
        ];

        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            error_log( sprintf( 'SBI GITHUB SERVICE: API request failed for %s/%s: %s', $owner, $repo, $response->get_error_message() ) );
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        error_log( sprintf( 'SBI GITHUB SERVICE: API response code: %d for %s/%s', $response_code, $owner, $repo ) );

        if ( 200 !== $response_code ) {
            $error_message = sprintf( 'Repository not found or API error: %d', $response_code );

            // Try to get more specific error from response body
            $decoded_body = json_decode( $response_body, true );
            if ( $decoded_body && isset( $decoded_body['message'] ) ) {
                $error_message .= ' - ' . $decoded_body['message'];
            }

            // Add specific handling for common error codes
            switch ( $response_code ) {
                case 404:
                    $error_message .= ' (Repository not found or private)';
                    break;
                case 403:
                    $error_message .= ' (Access forbidden - may be rate limited or private repository)';
                    break;
                case 401:
                    $error_message .= ' (Unauthorized - authentication required)';
                    break;
            }

            error_log( sprintf( 'SBI GITHUB SERVICE: API error for %s/%s - code: %d, message: %s', $owner, $repo, $response_code, $error_message ) );
            error_log( sprintf( 'SBI GITHUB SERVICE: Response body: %s', $response_body ) );

            return new WP_Error(
                'github_api_error',
                $error_message,
                [
                    'status_code' => $response_code,
                    'url' => $url,
                    'response_body' => $response_body,
                    'owner' => $owner,
                    'repo' => $repo
                ]
            );
        }

        $body = wp_remote_retrieve_body( $response );
        $repository = json_decode( $body, true );

        if ( null === $repository ) {
            error_log( sprintf( 'SBI GITHUB SERVICE: Invalid JSON response for %s/%s', $owner, $repo ) );
            return new WP_Error( 'invalid_json', __( 'Invalid JSON response from GitHub API.', 'kiss-smart-batch-installer' ) );
        }

        error_log( sprintf( 'SBI GITHUB SERVICE: Successfully retrieved repository data for %s/%s', $owner, $repo ) );

        $processed_repo = $this->process_repository( $repository );

        // Cache for shorter time for individual repos
        set_transient( $cache_key, $processed_repo, HOUR_IN_SECONDS / 2 );

        error_log( sprintf( 'SBI GITHUB SERVICE: Repository %s/%s processed and cached', $owner, $repo ) );

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

    /**
     * Fetch repositories using GitHub web interface (fallback method).
     *
     * @param string $account_name GitHub account name.
     * @return array|WP_Error Array of repositories or WP_Error on failure.
     */
    private function fetch_repositories_via_web( string $account_name ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KISS Smart Batch Installer: Fetching repositories via web scraping for ' . $account_name );
        }

        $all_repositories = [];
        $page = 1;
        $max_pages = 20; // Handle accounts with many repositories
        $consecutive_empty_pages = 0;
        $max_consecutive_empty = 3;

        while ( $page <= $max_pages && $consecutive_empty_pages < $max_consecutive_empty ) {
            // Build URL with pagination
            $url = sprintf( '%s/%s?tab=repositories&page=%d', self::WEB_BASE, urlencode( $account_name ), $page );

            $args = [
                'timeout' => 30, // Increased timeout
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo( 'version' ) . '; +' . get_bloginfo( 'url' ) . ')',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'DNT' => '1',
                    'Connection' => 'keep-alive',
                    'Upgrade-Insecure-Requests' => '1',
                    'Sec-Fetch-Dest' => 'document',
                    'Sec-Fetch-Mode' => 'navigate',
                    'Sec-Fetch-Site' => 'none',
                    'Cache-Control' => 'max-age=0',
                ],
                'sslverify' => true,
                'redirection' => 5,
            ];

            $response = wp_remote_get( $url, $args );

            if ( is_wp_error( $response ) ) {
                if ( $page === 1 ) {
                    return new WP_Error(
                        'web_request_failed',
                        sprintf( __( 'Failed to fetch repositories via web: %s', 'kiss-smart-batch-installer' ), $response->get_error_message() )
                    );
                } else {
                    // For subsequent pages, just break and return what we have
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( 'KISS Smart Batch Installer: Web request failed on page ' . $page . ', returning partial results' );
                    }
                    break;
                }
            }

            $response_code = wp_remote_retrieve_response_code( $response );
            if ( 200 !== $response_code ) {
                if ( 404 === $response_code ) {
                    if ( $page === 1 ) {
                        return new WP_Error(
                            'account_not_found',
                            sprintf( __( 'GitHub account "%s" not found.', 'kiss-smart-batch-installer' ), $account_name )
                        );
                    } else {
                        // No more pages
                        break;
                    }
                } elseif ( 429 === $response_code ) {
                    return new WP_Error(
                        'rate_limited',
                        __( 'Rate limited by GitHub. Please try again later.', 'kiss-smart-batch-installer' )
                    );
                } elseif ( $page === 1 ) {
                    return new WP_Error(
                        'web_fetch_error',
                        sprintf( __( 'GitHub web page returned error code: %d', 'kiss-smart-batch-installer' ), $response_code )
                    );
                } else {
                    // For subsequent pages, just break
                    break;
                }
            }

            $body = wp_remote_retrieve_body( $response );
            if ( empty( $body ) ) {
                if ( $page === 1 ) {
                    return new WP_Error(
                        'empty_response',
                        __( 'Empty response from GitHub.', 'kiss-smart-batch-installer' )
                    );
                } else {
                    break;
                }
            }

            // Parse repositories from HTML
            $page_repositories = $this->parse_repositories_from_html( $body, $account_name );

            if ( empty( $page_repositories ) ) {
                $consecutive_empty_pages++;
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'KISS Smart Batch Installer: No repositories found on page ' . $page . ' (consecutive empty: ' . $consecutive_empty_pages . ')' );
                }
            } else {
                $consecutive_empty_pages = 0; // Reset counter
                $all_repositories = array_merge( $all_repositories, $page_repositories );
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'KISS Smart Batch Installer: Found ' . count( $page_repositories ) . ' repositories on page ' . $page );
                }
            }

            $page++;

            // Add a respectful delay between requests
            if ( $page <= $max_pages && $consecutive_empty_pages < $max_consecutive_empty ) {
                usleep( 750000 ); // 0.75 seconds
            }
        }

        if ( empty( $all_repositories ) ) {
            return new WP_Error(
                'no_repositories_found',
                sprintf( __( 'No repositories found for account "%s".', 'kiss-smart-batch-installer' ), $account_name )
            );
        }

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KISS Smart Batch Installer: Total repositories found via web scraping: ' . count( $all_repositories ) );
        }

        return $all_repositories;
    }

    /**
     * Parse repository data from GitHub HTML page.
     *
     * @param string $html HTML content from GitHub page.
     * @param string $account_name GitHub account name.
     * @return array Array of repository data.
     */
    private function parse_repositories_from_html( string $html, string $account_name ): array {
        $repositories = [];

        // Use DOMDocument to parse HTML safely
        $dom = new \DOMDocument();

        // Suppress warnings for malformed HTML
        libxml_use_internal_errors( true );
        $dom->loadHTML( $html );
        libxml_clear_errors();

        $xpath = new \DOMXPath( $dom );

        $seen_repos = [];

        // Try multiple selectors to handle different GitHub layouts.
        // IMPORTANT: Aggregate results across ALL selectors and de-duplicate.
        $selectors = [
            // Current GitHub layout (2025) - repository list with h3 links
            '//h3/a[contains(@href, "/' . $account_name . '/") and not(contains(@href, "/issues")) and not(contains(@href, "/pulls")) and not(contains(@href, "/wiki")) and not(contains(@href, "/actions")) and not(contains(@href, "/security")) and not(contains(@href, "/settings"))]',
            // Modern GitHub layout - repository list items with data-testid
            '//div[@data-testid="results-list"]//h3/a[contains(@href, "/' . $account_name . '/")]',
            // Alternative layout - repository cards
            '//article//h3/a[contains(@href, "/' . $account_name . '/")]',
            // Fallback - any link that looks like a repository
            '//a[contains(@href, "/' . $account_name . '/") and not(contains(@href, "/issues")) and not(contains(@href, "/pulls")) and not(contains(@href, "/wiki")) and not(contains(@href, "/actions")) and not(contains(@href, "/security")) and not(contains(@href, "/settings"))]',
        ];

        $total_links_found = 0;

        foreach ( $selectors as $selector_index => $selector ) {
            $repo_links = $xpath->query( $selector );

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KISS Smart Batch Installer: Selector ' . ( $selector_index + 1 ) . ' found ' . $repo_links->length . ' links' );
            }

            if ( ! $repo_links || $repo_links->length === 0 ) {
                continue;
            }

            $total_links_found += $repo_links->length;

            foreach ( $repo_links as $link ) {
                $href = $link->getAttribute( 'href' );

                // Extract repository name from href like "/account/repo" or "/account/repo/"
                if ( preg_match( '#^/' . preg_quote( $account_name, '#' ) . '/([^/\?\#]+)/?$#', $href, $matches ) ) {
                    $repo_name = $matches[1];

                    // Skip if we've already seen this repo
                    if ( isset( $seen_repos[ $repo_name ] ) ) {
                        continue;
                    }

                    $seen_repos[ $repo_name ] = true;

                    // Get repository description from nearby elements
                    $description = '';
                    $language = '';
                    $updated_at = '';

                    // Try to find description in various ways
                    $parent = $link->parentNode;
                    $attempts = 0;
                    while ( $parent && $parent->nodeType === XML_ELEMENT_NODE && $attempts < 5 ) {
                        // Look for description paragraph
                        $desc_elements = $xpath->query( './/p[contains(@class, "description") or contains(@class, "repo-description")]', $parent );
                        if ( $desc_elements->length > 0 ) {
                            $description = trim( $desc_elements->item( 0 )->textContent );
                        }

                        // Look for language information
                        $lang_elements = $xpath->query( './/span[contains(@class, "language")]', $parent );
                        if ( $lang_elements->length > 0 ) {
                            $language = trim( $lang_elements->item( 0 )->textContent );
                        }

                        // Look for updated time
                        $time_elements = $xpath->query( './/relative-time', $parent );
                        if ( $time_elements->length > 0 ) {
                            $updated_at = $time_elements->item( 0 )->getAttribute( 'datetime' );
                        }

                        if ( $description || $language || $updated_at ) {
                            break; // Found some info, stop looking
                        }

                        $parent = $parent->parentNode;
                        $attempts++;
                    }

                    // Create repository data structure similar to API response
                    $repositories[] = [
                        'id' => crc32( $account_name . '/' . $repo_name ), // Generate a pseudo-ID
                        'name' => $repo_name,
                        'full_name' => $account_name . '/' . $repo_name,
                        'description' => $description ?: null,
                        'html_url' => self::WEB_BASE . '/' . $account_name . '/' . $repo_name,
                        'clone_url' => self::WEB_BASE . '/' . $account_name . '/' . $repo_name . '.git',
                        'default_branch' => 'main', // Default assumption
                        'updated_at' => $updated_at ?: null,
                        'language' => $language ?: null,
                        'size' => 0, // Not available via web scraping
                        'stargazers_count' => 0, // Not available via web scraping
                        'archived' => false, // Default assumption
                        'disabled' => false, // Default assumption
                        'private' => false, // We're only looking at public repos
                        'source' => 'web', // Mark as web-scraped
                    ];
                }
            }
        }

        if ( empty( $repositories ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KISS Smart Batch Installer: No repository links found across selectors' );
                // Log a sample of the HTML to help debug
                $sample_html = substr( $html, 0, 2000 );
                error_log( 'KISS Smart Batch Installer: HTML sample: ' . $sample_html );
            }
            return $repositories;
        }

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KISS Smart Batch Installer: Parsed ' . count( $repositories ) . ' repositories from HTML (total links scanned: ' . $total_links_found . ')' );
        }

        return $repositories;
    }

    /**
     * Fetch with retry logic for better error recovery.
     *
     * @param string $url URL to fetch.
     * @param array  $args Request arguments.
     * @param int    $max_retries Maximum number of retries.
     * @return array|WP_Error Response or WP_Error on failure.
     */
    private function fetch_with_retry( $url, $args, $max_retries = 2 ) {
        $attempts = 0;
        $last_error = null;

        while ( $attempts < $max_retries ) {
            $response = wp_remote_get( $url, $args );

            if ( ! is_wp_error( $response ) ) {
                $code = wp_remote_retrieve_response_code( $response );
                if ( $code === 200 ) {
                    return $response;
                }
                // If rate limited, don't retry
                if ( $code === 403 || $code === 429 ) {
                    return $response;
                }
            }

            $last_error = $response;
            $attempts++;

            if ( $attempts < $max_retries ) {
                sleep( 1 );  // Wait 1 second before retry
            }
        }

        return $last_error;
    }
}
