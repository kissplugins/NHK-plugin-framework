<?php
/**
 * State manager handling plugin states for repositories.
 *
 * @package SBI\Services
 */

namespace SBI\Services;

use SBI\Enums\PluginState;

/**
 * Central arbiter for repository states.
 */
class StateManager {
    /**
     * Repository states cache.
     *
     * @var array<string, PluginState>
     */
    protected array $states = [];

    /**
     * Cache expiration time (5 minutes).
     */
    private const CACHE_EXPIRATION = 5 * MINUTE_IN_SECONDS;

    /**
     * PQS Integration service.
     *
     * @var PQSIntegration
     */
    protected PQSIntegration $pqs_integration;

    /**
     * Constructor.
     *
     * @param PQSIntegration $pqs_integration PQS integration service.
     */
    public function __construct( PQSIntegration $pqs_integration ) {
        $this->pqs_integration = $pqs_integration;
        $this->load_cached_states();
    }

    /**
     * Get repository state.
     *
     * @param string $repository Repository full name (owner/repo).
     * @param bool   $force_refresh Whether to force refresh the state.
     * @return PluginState Current state of the repository.
     */
    public function get_state( string $repository, bool $force_refresh = false ): PluginState {
        if ( $force_refresh || ! isset( $this->states[ $repository ] ) ) {
            $this->refresh_state( $repository );
        }

        return $this->states[ $repository ] ?? PluginState::UNKNOWN;
    }

    /**
     * Set repository state.
     *
     * @param string      $repository Repository full name.
     * @param PluginState $state      New state.
     */
    public function set_state( string $repository, PluginState $state ): void {
        $this->states[ $repository ] = $state;
        $this->save_cached_states();
    }

    /**
     * Refresh state for a specific repository.
     *
     * @param string $repository Repository full name.
     */
    public function refresh_state( string $repository ): void {
        $state = $this->determine_plugin_state( $repository );
        $this->set_state( $repository, $state );
    }

    /**
     * Batch refresh states for multiple repositories.
     *
     * @param array $repositories Array of repository names.
     */
    public function batch_refresh_states( array $repositories ): void {
        foreach ( $repositories as $repository ) {
            $this->refresh_state( $repository );
        }
    }

    /**
     * Get states for multiple repositories.
     *
     * @param array $repositories Array of repository names.
     * @param bool  $force_refresh Whether to force refresh all states.
     * @return array<string, PluginState> Array of states keyed by repository name.
     */
    public function get_batch_states( array $repositories, bool $force_refresh = false ): array {
        $states = [];

        foreach ( $repositories as $repository ) {
            $states[ $repository ] = $this->get_state( $repository, $force_refresh );
        }

        return $states;
    }

    /**
     * Determine the actual plugin state for a repository.
     *
     * @param string $repository Repository full name.
     * @return PluginState Determined state.
     */
    private function determine_plugin_state( string $repository ): PluginState {
        // Extract plugin slug from repository name
        $plugin_slug = $this->extract_plugin_slug( $repository );

        if ( empty( $plugin_slug ) ) {
            return PluginState::UNKNOWN;
        }

        // Check if plugin is installed and get its status
        $plugin_file = $this->find_plugin_file( $plugin_slug );

        if ( empty( $plugin_file ) ) {
            // Plugin not installed, check if it's a valid WordPress plugin
            return $this->is_wordpress_plugin( $repository ) ? PluginState::AVAILABLE : PluginState::NOT_PLUGIN;
        }

        // Plugin is installed, check if it's active
        if ( is_plugin_active( $plugin_file ) ) {
            return PluginState::INSTALLED_ACTIVE;
        }

        return PluginState::INSTALLED_INACTIVE;
    }

    /**
     * Extract plugin slug from repository name.
     *
     * @param string $repository Repository full name (owner/repo).
     * @return string Plugin slug.
     */
    private function extract_plugin_slug( string $repository ): string {
        $parts = explode( '/', $repository );
        return end( $parts );
    }

    /**
     * Find plugin file for a given slug.
     *
     * @param string $plugin_slug Plugin slug.
     * @return string Plugin file path or empty string if not found.
     */
    private function find_plugin_file( string $plugin_slug ): string {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();

        // Look for exact match first
        foreach ( $all_plugins as $plugin_file => $plugin_data ) {
            $plugin_dir = dirname( $plugin_file );
            if ( $plugin_dir === $plugin_slug || $plugin_file === $plugin_slug . '.php' ) {
                return $plugin_file;
            }
        }

        // Look for partial matches
        foreach ( $all_plugins as $plugin_file => $plugin_data ) {
            if ( strpos( $plugin_file, $plugin_slug ) === 0 ) {
                return $plugin_file;
            }
        }

        return '';
    }

    /**
     * Check if repository contains a WordPress plugin using PQS cache.
     *
     * @param string $repository Repository full name.
     * @return bool True if it's a WordPress plugin.
     */
    private function is_wordpress_plugin( string $repository ): bool {
        $pqs_cache = $this->pqs_integration->get_cache();
        $plugin_slug = $this->extract_plugin_slug( $repository );

        // Check PQS cache for plugin information
        if ( isset( $pqs_cache[ $plugin_slug ] ) ) {
            return true;
        }

        // Fallback: assume it might be a plugin (will be verified by PluginDetectionService)
        return false;
    }

    /**
     * Load cached states from WordPress transients.
     */
    private function load_cached_states(): void {
        $cached_states = get_transient( 'sbi_plugin_states' );

        if ( is_array( $cached_states ) ) {
            foreach ( $cached_states as $repository => $state_value ) {
                if ( is_string( $state_value ) ) {
                    $state = PluginState::tryFrom( $state_value );
                    if ( $state ) {
                        $this->states[ $repository ] = $state;
                    }
                }
            }
        }
    }

    /**
     * Save current states to WordPress transients.
     */
    private function save_cached_states(): void {
        $states_for_cache = [];

        foreach ( $this->states as $repository => $state ) {
            $states_for_cache[ $repository ] = $state->value;
        }

        set_transient( 'sbi_plugin_states', $states_for_cache, self::CACHE_EXPIRATION );
    }

    /**
     * Clear all cached states.
     */
    public function clear_cache(): void {
        $this->states = [];
        delete_transient( 'sbi_plugin_states' );
    }

    /**
     * Get statistics about current states.
     *
     * @return array Statistics array.
     */
    public function get_statistics(): array {
        $stats = [
            'total' => count( $this->states ),
            'available' => 0,
            'installed_active' => 0,
            'installed_inactive' => 0,
            'not_plugin' => 0,
            'unknown' => 0,
            'error' => 0,
        ];

        foreach ( $this->states as $state ) {
            switch ( $state ) {
                case PluginState::AVAILABLE:
                    $stats['available']++;
                    break;
                case PluginState::INSTALLED_ACTIVE:
                    $stats['installed_active']++;
                    break;
                case PluginState::INSTALLED_INACTIVE:
                    $stats['installed_inactive']++;
                    break;
                case PluginState::NOT_PLUGIN:
                    $stats['not_plugin']++;
                    break;
                case PluginState::UNKNOWN:
                    $stats['unknown']++;
                    break;
                case PluginState::ERROR:
                    $stats['error']++;
                    break;
            }
        }

        return $stats;
    }
}
