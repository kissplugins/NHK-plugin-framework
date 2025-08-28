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
     * Allowed transitions cache.
     *
     * @var array<string, array<string>>
     */
    private array $allowed_transitions = [];

    /**
     * Cache expiration time (5 minutes).
     */
    private const CACHE_EXPIRATION = 5 * 60; // 5 minutes in seconds

    /**
     * Event log transient TTL (1 day) and max entries per repo.
     */
    private const EVENT_LOG_TTL = 24 * 60 * 60; // 1 day in seconds
    private const EVENT_LOG_LIMIT = 30;

    /**
     * Allowed state transitions map.
     * NOTE: Keep conservative; refresh_state() uses force to avoid breaking flows.
     * UNKNOWN -> CHECKING/AVAILABLE/NOT_PLUGIN/ERROR/INSTALLED_INACTIVE/INSTALLED_ACTIVE
     * CHECKING -> AVAILABLE/NOT_PLUGIN/ERROR
     * AVAILABLE -> INSTALLED_INACTIVE/ERROR
     * INSTALLED_INACTIVE -> INSTALLED_ACTIVE/ERROR
     * INSTALLED_ACTIVE -> INSTALLED_INACTIVE/ERROR
     * NOT_PLUGIN -> CHECKING/AVAILABLE
     * ERROR -> CHECKING/AVAILABLE/NOT_PLUGIN
     */

    /**
     * Initialize allowed transitions.
     */
    private function init_transitions(): void {
        $this->allowed_transitions = [
            PluginState::UNKNOWN->value => [ PluginState::CHECKING->value, PluginState::AVAILABLE->value, PluginState::NOT_PLUGIN->value, PluginState::ERROR->value, PluginState::INSTALLED_INACTIVE->value, PluginState::INSTALLED_ACTIVE->value ],
            PluginState::CHECKING->value => [ PluginState::AVAILABLE->value, PluginState::NOT_PLUGIN->value, PluginState::ERROR->value ],
            PluginState::AVAILABLE->value => [ PluginState::INSTALLED_INACTIVE->value, PluginState::ERROR->value ],
            PluginState::INSTALLED_INACTIVE->value => [ PluginState::INSTALLED_ACTIVE->value, PluginState::ERROR->value ],
            PluginState::INSTALLED_ACTIVE->value => [ PluginState::INSTALLED_INACTIVE->value, PluginState::ERROR->value ],
            PluginState::NOT_PLUGIN->value => [ PluginState::CHECKING->value, PluginState::AVAILABLE->value ],
            PluginState::ERROR->value => [ PluginState::CHECKING->value, PluginState::AVAILABLE->value, PluginState::NOT_PLUGIN->value ],
        ];
    }

    /**
     * Transition to a new state with validation and event logging.
     *
     * @param string $repository owner/repo
     * @param PluginState $to_state target state
     * @param array $context optional context (e.g., source, message)
     * @param bool $force when true, bypass transition validation (used by refresh_state)
     */
    public function transition( string $repository, PluginState $to_state, array $context = [], bool $force = false ): void {
        $from_state = $this->states[$repository]->value ?? PluginState::UNKNOWN->value;
        $to_value = $to_state->value;

        // Initialize transition map on first use
        if (empty($this->allowed_transitions)) {
            $this->init_transitions();
        }

        if (! $force) {
            $allowed = $this->allowed_transitions[$from_state] ?? [];
            if (! in_array($to_value, $allowed, true)) {
                // Log and ignore invalid transition to keep system robust
                $this->log_event($repository, 'transition_blocked', [
                    'from' => $from_state,
                    'to' => $to_value,
                    'reason' => 'invalid_transition',
                    'context' => $context,
                ]);
                return;
            }
        }

        // Enhanced error state handling
        if ($to_state === PluginState::ERROR) {
            $this->handle_error_transition($repository, $context);
        } elseif ($from_state === PluginState::ERROR->value && $to_state !== PluginState::ERROR) {
            $this->handle_error_recovery($repository, $to_state, $context);
        }

        $this->set_state($repository, $to_state);
        $this->log_event($repository, 'transition', [
            'from' => $from_state,
            'to' => $to_value,
            'context' => $context,
        ]);
        // Broadcast hook (stub)
        $this->broadcast('state_changed', [
            'repository' => $repository,
            'from' => $from_state,
            'to' => $to_value,
            'context' => $context,
            'ts' => time(),
        ]);
    }

    /**
     * Handle transition to ERROR state with enhanced context tracking.
     */
    private function handle_error_transition(string $repository, array $context): void {
        $error_data = [
            'timestamp' => time(),
            'message' => $context['error'] ?? $context['message'] ?? 'Unknown error',
            'source' => $context['source'] ?? 'unknown',
            'recoverable' => $context['recoverable'] ?? true,
            'retry_count' => $this->get_retry_count($repository),
        ];

        // Store error context for recovery
        $this->store_error_context($repository, $error_data);

        // Log detailed error information
        $this->log_event($repository, 'error_occurred', $error_data);

        error_log(sprintf(
            'SBI FSM ERROR: %s - %s (source: %s, recoverable: %s)',
            $repository,
            $error_data['message'],
            $error_data['source'],
            $error_data['recoverable'] ? 'yes' : 'no'
        ));
    }

    /**
     * Handle recovery from ERROR state.
     */
    private function handle_error_recovery(string $repository, PluginState $to_state, array $context): void {
        $error_context = $this->get_error_context($repository);

        $recovery_data = [
            'timestamp' => time(),
            'recovered_to' => $to_state->value,
            'recovery_source' => $context['source'] ?? 'unknown',
            'previous_error' => $error_context['message'] ?? 'unknown',
            'retry_count' => $error_context['retry_count'] ?? 0,
        ];

        // Log successful recovery
        $this->log_event($repository, 'error_recovered', $recovery_data);

        // Clear error context on successful recovery
        $this->clear_error_context($repository);

        error_log(sprintf(
            'SBI FSM RECOVERY: %s recovered to %s (was: %s)',
            $repository,
            $to_state->value,
            $error_context['message'] ?? 'unknown error'
        ));
    }

    /**
     * Store error context for a repository.
     */
    private function store_error_context(string $repository, array $error_data): void {
        $key = 'sbi_error_context_' . md5($repository);
        set_transient($key, $error_data, self::EVENT_LOG_TTL);
    }

    /**
     * Get error context for a repository.
     */
    private function get_error_context(string $repository): array {
        $key = 'sbi_error_context_' . md5($repository);
        $context = get_transient($key);
        return is_array($context) ? $context : [];
    }

    /**
     * Clear error context for a repository.
     */
    private function clear_error_context(string $repository): void {
        $key = 'sbi_error_context_' . md5($repository);
        delete_transient($key);
    }

    /**
     * Get retry count for a repository.
     */
    private function get_retry_count(string $repository): int {
        $error_context = $this->get_error_context($repository);
        return $error_context['retry_count'] ?? 0;
    }

    /**
     * Increment retry count for a repository.
     */
    public function increment_retry_count(string $repository): int {
        $error_context = $this->get_error_context($repository);
        $retry_count = ($error_context['retry_count'] ?? 0) + 1;
        $error_context['retry_count'] = $retry_count;
        $error_context['last_retry_at'] = time();
        $this->store_error_context($repository, $error_context);
        return $retry_count;
    }

    /**
     * Append event to per-repo transient-backed ring buffer.
     */
    private function log_event(string $repository, string $event, array $data = []): void {
        $key = 'sbi_state_events_' . md5($repository);
        $events = \get_transient($key);
        if (!is_array($events)) { $events = []; }
        $events[] = [
            't' => time(),
            'event' => $event,
            'data' => $data,
        ];
        // Cap size
        if (count($events) > self::EVENT_LOG_LIMIT) {
            $events = array_slice($events, -self::EVENT_LOG_LIMIT);
        }
        \set_transient($key, $events, self::EVENT_LOG_TTL);
    }

    /**
     * Acquire a short-lived processing lock for a repository.
     * Returns true if acquired, false if already locked.
     */
    public function acquire_processing_lock(string $repository, int $ttl_seconds = 60): bool {
        $key = 'sbi_lock_' . md5($repository);
        // Attempt to add; add_option will fail if option exists. Use transients for TTL.
        if (false !== \get_transient($key)) {
            return false; // already locked
        }
        \set_transient($key, 1, $ttl_seconds);
        $this->log_event($repository, 'lock_acquired', [ 'ttl' => $ttl_seconds ]);
        return true;
    }

    /**
     * Release a processing lock for a repository.
     */
    public function release_processing_lock(string $repository): void {
        $key = 'sbi_lock_' . md5($repository);
        \delete_transient($key);
        $this->log_event($repository, 'lock_released');
    }



    /**
     * Read recent events for a repo (for Self Tests/UI).
     */
    public function get_events(string $repository, int $limit = 10): array {
        $key = 'sbi_state_events_' . md5($repository);
        $events = \get_transient($key);
        if (!is_array($events)) { return []; }
        return array_slice($events, -$limit);
    }

    /**
     * Broadcast a state-related event to listeners (stub + queue).
     * - Logs to per-repo event buffer
     * - Appends to a global broadcast ring buffer for SSE consumers
     */
    public function broadcast(string $event, array $payload = []): void {
        $repo = $payload['repository'] ?? 'unknown';
        $this->log_event($repo, $event, $payload);

        // Append to global broadcast queue (ring buffer)
        $last_id = (int) \get_option('sbi_broadcast_last_id', 0);
        $id = $last_id + 1;
        \update_option('sbi_broadcast_last_id', $id, false);

        $queue = \get_transient('sbi_broadcast_events');
        if (!is_array($queue)) { $queue = []; }
        $queue[] = [
            'id' => $id,
            'event' => $event,
            'payload' => $payload,
            'ts' => time(),
        ];
        // Cap at 100 events
        if (count($queue) > 100) {
            $queue = array_slice($queue, -100);
        }
        \set_transient('sbi_broadcast_events', $queue, self::EVENT_LOG_TTL);
    }

    /**
     * Get broadcast events with id greater than $last_id
     * for use by SSE endpoint.
     *
     * @return array<int, array{ id:int, event:string, payload:array, ts:int }>
     */
    public function get_broadcast_events_since(int $last_id): array {
        $queue = \get_transient('sbi_broadcast_events');
        if (!is_array($queue)) { return []; }
        return array_values(array_filter($queue, static function($e) use ($last_id) {
            return isset($e['id']) && (int)$e['id'] > $last_id;
        }));
    }

    /**
     * PQS Integration service.
     *
     * @var PQSIntegration
     */
    protected PQSIntegration $pqs_integration;
    protected PluginDetectionService $detection_service;

    /**
     * Constructor.
     *
     * @param PQSIntegration $pqs_integration PQS integration service.
     * @param PluginDetectionService $detection_service Plugin detection service.
     */
    public function __construct( PQSIntegration $pqs_integration, PluginDetectionService $detection_service ) {
        $this->pqs_integration = $pqs_integration;
        $this->detection_service = $detection_service;
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
        // Move through CHECKING to determined state; bypass transition validation for refresh
        $this->transition( $repository, PluginState::CHECKING, [ 'source' => 'refresh_state' ], true );
        // Consolidated detection + cache path
        $state = $this->determine_plugin_state( $repository );
        // If still unknown and not installed, try consolidated detect_plugin_state()
        if ( $state === PluginState::UNKNOWN ) {
            $state = $this->detect_plugin_state( $repository );
        }
        $this->transition( $repository, $state, [ 'source' => 'refresh_state' ], true );
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
            // Plugin not installed. Use detection service (scan up to 3 root PHP files) to decide.
            $repo = [ 'full_name' => $repository, 'name' => $plugin_slug ];
            $det = $this->detection_service->detect_plugin( $repo );

            if ( is_wp_error( $det ) ) {
                return PluginState::UNKNOWN; // conservatively unknown on error
            }

            if ( ! empty( $det['is_plugin'] ) ) {
                return PluginState::AVAILABLE;
            }

            // If detection explicitly concluded it's not a plugin
            if ( isset($det['is_plugin']) && $det['is_plugin'] === false ) {
                return PluginState::NOT_PLUGIN;
            }

            // For all other cases (scan failed, etc.), default to UNKNOWN
            return PluginState::UNKNOWN;
        }

        // Plugin is installed, check if it's active
        if ( is_plugin_active( $plugin_file ) ) {
            return PluginState::INSTALLED_ACTIVE;
        }

        return PluginState::INSTALLED_INACTIVE;
    }

    /**
     * Public helpers for installed/active/file queries via FSM + runtime.
     */
    public function getInstalledPluginFile(string $repository): string {
        $slug = $this->extract_plugin_slug($repository);
        return $this->find_plugin_file($slug);
    }

    public function isInstalled(string $repository): bool {
        $state = $this->get_state($repository);
        if (in_array($state, [PluginState::INSTALLED_ACTIVE, PluginState::INSTALLED_INACTIVE], true)) {
            return true;
        }
        // Fallback to runtime file discovery
        return $this->getInstalledPluginFile($repository) !== '';
    }

    public function isActive(string $repository): bool {
        $state = $this->get_state($repository);
        if ($state === PluginState::INSTALLED_ACTIVE) return true;
        if ($state === PluginState::INSTALLED_INACTIVE) return false;
        $file = $this->getInstalledPluginFile($repository);
        if ($file && function_exists('is_plugin_active')) {
            return is_plugin_active($file);
        }
        return false;
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
     * Normalize a slug or file/directory name for robust comparisons.
     */
    private function normalize_slug_str(string $s): string {
        $s = strtolower($s);
        // Remove non-alphanumeric to ignore separators like -, _, space
        return preg_replace('/[^a-z0-9]/', '', $s) ?? '';
    }

    /**
     * Find plugin file for a given slug.
     *
     * Robust to case, separator differences, and minor prefix/suffix variations.
     *
     * @param string $plugin_slug Plugin slug.
     * @return string Plugin file path or empty string if not found.
     */
    private function find_plugin_file( string $plugin_slug ): string {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $norm_slug = $this->normalize_slug_str($plugin_slug);

        foreach ( $all_plugins as $plugin_file => $plugin_data ) {
            $plugin_dir = dirname( $plugin_file );
            $base_file = basename( $plugin_file, '.php' );

            $norm_dir = $this->normalize_slug_str($plugin_dir);
            $norm_base = $this->normalize_slug_str($base_file);

            // Strong matches first
            if ( $norm_dir === $norm_slug || $norm_base === $norm_slug ) {
                return $plugin_file;
            }

            // Tolerate small variations (e.g., repo "KISS-Plugin-Quick-Search" vs dir "kisspluginquicksearch")
            if ( str_starts_with( $norm_dir, $norm_slug ) || str_starts_with( $norm_slug, $norm_dir ) ) {
                return $plugin_file;
            }
        }

        return '';
    }

    /**
     * Fast cache-based heuristic for plugin presence using PQS cache only.
     * @param string $repository owner/repo
     * @return bool
     */
    private function check_cache_state( string $repository ): bool {
        $pqs_cache = $this->pqs_integration->get_cache();
        $plugin_slug = $this->extract_plugin_slug( $repository );
        return isset( $pqs_cache[ $plugin_slug ] );
    }

    /**
     * Detect plugin state when not installed.
     * Combines PQS cache and header scan to return a conservative PluginState.
     * @param string $repository owner/repo
     * @return PluginState
     */
    private function detect_plugin_state( string $repository ): PluginState {
        // 1) PQS cache (fast path)
        if ( $this->check_cache_state( $repository ) ) {
            return PluginState::AVAILABLE;
        }
        // 2) Authoritative detection path via wrapped detection service
        $slug = $this->extract_plugin_slug( $repository );
        $repo = [ 'full_name' => $repository, 'name' => $slug ];
        $det = $this->detect_plugin_info( $repo );
        if ( is_wp_error( $det ) ) {
            return PluginState::UNKNOWN;
        }
        if ( ! empty( $det['is_plugin'] ) ) {
            return PluginState::AVAILABLE;
        }
        // Conservative default: UNKNOWN when headers not found
        return PluginState::UNKNOWN;
    }

    /**
     * Wrapper for plugin detection to centralize calls and logging.
     * Preserves existing detailed debug behavior inside PluginDetectionService.
     *
     * @param array $repository Minimal repo array with keys: full_name, name, description?
     * @param bool $force_refresh Bypass detection cache
     * @return array|\WP_Error
     */
    public function detect_plugin_info( array $repository, bool $force_refresh = false ) {
        try {
            $res = $this->detection_service->detect_plugin( $repository, $force_refresh );
            // Log a compact breadcrumb for diagnostics without spamming logs
            $this->log_event(
                $repository['full_name'] ?? 'unknown',
                'detect_plugin',
                [
                    'result' => is_wp_error($res) ? 'error' : (( $res['is_plugin'] ?? false ) ? 'is_plugin' : 'not_plugin'),
                    'scan_method' => is_wp_error($res) ? 'wp_error' : ($res['scan_method'] ?? ''),
                ]
            );
            return $res;
        } catch ( \Throwable $e ) {
            $this->log_event( $repository['full_name'] ?? 'unknown', 'detect_plugin_error', [ 'message' => $e->getMessage() ] );
            return new \WP_Error( 'detection_failed', $e->getMessage() );
        }
    }


    /**
     * Load cached states from WordPress transients.
     */
    private function load_cached_states(): void {
        $cached_states = \get_transient( 'sbi_plugin_states' );

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

        \set_transient( 'sbi_plugin_states', $states_for_cache, self::CACHE_EXPIRATION );
    }

    /**
     * Clear all cached states.
     */
    public function clear_cache(): void {
        $this->states = [];
        \delete_transient( 'sbi_plugin_states' );
    }

    /**
     * Clear cache for a specific repository.
     *
     * @param string $repository_full_name Repository full name (owner/repo).
     */
    public function clear_repository_cache( string $repository_full_name ): void {
        // Remove from memory cache
        unset( $this->states[ $repository_full_name ] );

        // Update persistent cache
        $cached_states = \get_transient( 'sbi_plugin_states' );
        if ( is_array( $cached_states ) && isset( $cached_states[ $repository_full_name ] ) ) {
            unset( $cached_states[ $repository_full_name ] );
            \set_transient( 'sbi_plugin_states', $cached_states, self::CACHE_EXPIRATION );
        }
    }

    /**
     * Get plugin file for a repository.
     *
     * @param string $repository_full_name Repository full name (owner/repo).
     * @return string|null Plugin file path or null if not found.
     */
    public function get_plugin_file( string $repository_full_name ): ?string {
        if ($this->isInstalled($repository_full_name)) {
            return $this->getInstalledPluginFile($repository_full_name);
        }
        return null;
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
