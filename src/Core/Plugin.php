<?php
/**
 * Main Plugin Class
 *
 * Coordinates all plugin components and demonstrates comprehensive WordPress plugin architecture.
 * Shows proper use of dependency injection, service registration, and component initialization.
 *
 * @package NHK\EventManager\Core
 * @since 1.0.0
 */

namespace NHK\EventManager\Core;

use NHK\Framework\Container\Container;
use NHK\EventManager\PostTypes\EventPostType;
use NHK\EventManager\Taxonomy\EventCategoryTaxonomy;
use NHK\EventManager\Taxonomy\EventVenueTaxonomy;
use NHK\EventManager\Admin\EventMetaBoxes;
use NHK\EventManager\Admin\SettingsPage;
use NHK\EventManager\API\EventsEndpoint;
use NHK\EventManager\Frontend\EventListShortcode;
use NHK\EventManager\Services\EventQueryService;
use NHK\EventManager\Services\EventCacheService;
use NHK\EventManager\Jobs\EventReminderJob;
use NHK\EventManager\Jobs\CacheCleanupJob;
use NHK\EventManager\HealthChecks\HealthCheckManager;
use NHK\EventManager\HealthChecks\EventSystemHealthCheck;
use NHK\EventManager\HealthChecks\PerformanceHealthCheck;

/**
 * Main Plugin Class
 *
 * Demonstrates:
 * - Dependency injection container usage
 * - Service registration and management
 * - Component initialization coordination
 * - WordPress hook integration
 * - Plugin lifecycle management
 */
class Plugin {

    /**
     * Service container
     *
     * @var Container
     */
    protected Container $container;

    /**
     * Plugin components
     *
     * @var array
     */
    protected array $components = [];

    /**
     * Constructor
     *
     * @param Container $container Service container
     */
    public function __construct(Container $container) {
        $this->container = $container;
    }
    
    /**
     * Register services in the container
     * 
     * Demonstrates service container usage by registering all plugin services.
     * Uses singleton pattern for services that should be shared.
     * 
     * @return void
     */
    protected function register_services(): void {
        // CPT and Taxonomies - Core content management
        $this->container->singleton(EventCPT::class);
        $this->container->singleton(EventCategoryTaxonomy::class);
        $this->container->singleton(EventVenueTaxonomy::class);
        
        // Admin components
        $this->container->singleton(SettingsPage::class);
        $this->container->singleton(EventMetaBoxes::class);
        
        // API endpoints
        $this->container->singleton(EventsEndpoint::class);
        
        // Frontend components
        $this->container->singleton(EventListShortcode::class);
        
        // Services - Business logic layer
        $this->container->singleton(EventQueryService::class);
        $this->container->singleton(EventCacheService::class);
    }
    
    /**
     * Boot registered services
     * 
     * Initializes all registered services in the correct order.
     * Services are resolved from the container with automatic dependency injection.
     * 
     * @return void
     */
    protected function boot_services(): void {
        // Boot CPT and Taxonomies first (they need to be available early)
        $this->container->get(EventCPT::class)->init();
        $this->container->get(EventCategoryTaxonomy::class)->init();
        $this->container->get(EventVenueTaxonomy::class)->init();
        
        // Boot admin components (only in admin)
        if (is_admin()) {
            $this->container->get(SettingsPage::class)->init();
            $this->container->get(EventMetaBoxes::class)->init();
        }
        
        // Boot API endpoints
        $this->container->get(EventsEndpoint::class)->init();
        
        // Boot frontend components (only on frontend)
        if (!is_admin()) {
            $this->container->get(EventListShortcode::class)->init();
        }
        
        // Services are available but don't need explicit initialization
        // They will be used by other components as needed
    }
    
    /**
     * Setup WordPress hooks
     * 
     * Registers WordPress hooks for plugin functionality.
     * Demonstrates proper hook usage following WordPress best practices.
     * 
     * @return void
     */
    protected function setup_hooks(): void {
        // Version management - check for updates
        add_action('admin_init', [$this, 'check_version']);
        
        // Asset loading
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Plugin action links
        add_filter('plugin_action_links_' . plugin_basename($this->plugin_file), [$this, 'add_action_links']);
        
        // Health checks
        add_action('wp_ajax_nhk_event_health_check', [$this, 'run_health_checks']);
        
        // Cache invalidation
        add_action('save_post_nhk_event', [$this, 'invalidate_event_cache']);
        add_action('delete_post', [$this, 'invalidate_event_cache']);
    }
    
    /**
     * Check plugin version and run updates if needed
     * 
     * Demonstrates version management using WordPress options API.
     * 
     * @return void
     */
    public function check_version(): void {
        $stored_version = get_option($this->get_option_prefix() . 'version', '0.0.0');
        
        if (version_compare($stored_version, $this->version, '<')) {
            $this->run_update_routines($stored_version);
            update_option($this->get_option_prefix() . 'version', $this->version);
        }
    }
    
    /**
     * Enqueue frontend assets
     * 
     * Demonstrates conditional asset loading using WordPress enqueue system.
     * 
     * @return void
     */
    public function enqueue_frontend_assets(): void {
        // Only load on pages that might have events
        if (is_singular('nhk_event') || is_post_type_archive('nhk_event') || $this->has_event_shortcode()) {
            wp_enqueue_style(
                'nhk-event-frontend',
                $this->plugin_url . 'assets/css/event-list.css',
                [],
                $this->version
            );
            
            wp_enqueue_script(
                'nhk-event-frontend',
                $this->plugin_url . 'assets/js/event-filter.js',
                ['jquery'],
                $this->version,
                true
            );
            
            // Localize script with AJAX URL and nonce
            wp_localize_script('nhk-event-frontend', 'nhkEvents', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('nhk_event_filter'),
                'strings' => [
                    'loading' => __('Loading events...', 'nhk-event-manager'),
                    'noEvents' => __('No events found.', 'nhk-event-manager'),
                ]
            ]);
        }
    }
    
    /**
     * Enqueue admin assets
     * 
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueue_admin_assets(string $hook): void {
        // Only load on event-related admin pages
        if (in_array($hook, ['post.php', 'post-new.php', 'edit.php']) && 
            get_current_screen()->post_type === 'nhk_event') {
            
            wp_enqueue_style(
                'nhk-event-admin',
                $this->plugin_url . 'assets/css/admin-events.css',
                [],
                $this->version
            );
            
            wp_enqueue_script(
                'nhk-event-admin',
                $this->plugin_url . 'assets/js/admin-events.js',
                ['jquery', 'jquery-ui-datepicker'],
                $this->version,
                true
            );
        }
    }
    
    /**
     * Add plugin action links
     * 
     * Demonstrates WordPress plugin_action_links filter usage.
     * 
     * @param array $links Existing action links
     * @return array Modified action links
     */
    public function add_action_links(array $links): array {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=nhk-event-settings'),
            __('Settings', 'nhk-event-manager')
        );
        
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Run health checks via AJAX
     * 
     * Demonstrates AJAX handling and health check system.
     * 
     * @return void
     */
    public function run_health_checks(): void {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'nhk_event_health_check')) {
            wp_die(__('Security check failed', 'nhk-event-manager'));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'nhk-event-manager'));
        }
        
        $checks = $this->perform_health_checks();
        
        wp_send_json_success($checks);
    }
    
    /**
     * Invalidate event cache when events are modified
     * 
     * Demonstrates cache invalidation using WordPress hooks.
     * 
     * @param int $post_id Post ID
     * @return void
     */
    public function invalidate_event_cache(int $post_id): void {
        if (get_post_type($post_id) === 'nhk_event') {
            $cache_service = $this->container->get(EventCacheService::class);
            $cache_service->flush_event_cache();
        }
    }
    
    /**
     * Check if current page has event shortcodes
     * 
     * @return bool
     */
    protected function has_event_shortcode(): bool {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        return has_shortcode($post->post_content, 'nhk_events') ||
               has_shortcode($post->post_content, 'nhk_event_calendar') ||
               has_shortcode($post->post_content, 'nhk_event_single');
    }
    
    /**
     * Run update routines when version changes
     * 
     * @param string $old_version Previous version
     * @return void
     */
    protected function run_update_routines(string $old_version): void {
        // Run database migrations if needed
        $this->run_migrations();
        
        // Flush rewrite rules to ensure CPT URLs work
        flush_rewrite_rules();
        
        // Clear caches
        $this->container->get(EventCacheService::class)->flush_event_cache();
    }
    
    /**
     * Perform health checks
     * 
     * @return array
     */
    protected function perform_health_checks(): array {
        $checks = [];
        
        // Check if CPT is registered
        $checks['cpt_registered'] = [
            'status' => post_type_exists('nhk_event') ? 'healthy' : 'critical',
            'message' => post_type_exists('nhk_event') 
                ? __('Event post type is registered', 'nhk-event-manager')
                : __('Event post type is not registered', 'nhk-event-manager')
        ];
        
        // Check if taxonomies are registered
        $checks['taxonomies_registered'] = [
            'status' => (taxonomy_exists('nhk_event_category') && taxonomy_exists('nhk_event_venue')) ? 'healthy' : 'critical',
            'message' => (taxonomy_exists('nhk_event_category') && taxonomy_exists('nhk_event_venue'))
                ? __('Event taxonomies are registered', 'nhk-event-manager')
                : __('Event taxonomies are not registered', 'nhk-event-manager')
        ];
        
        // Check for upcoming events
        $upcoming_events = get_posts([
            'post_type' => 'nhk_event',
            'posts_per_page' => 1,
            'meta_query' => [[
                'key' => 'event_start_date',
                'value' => current_time('Y-m-d'),
                'compare' => '>=',
                'type' => 'DATE'
            ]]
        ]);
        
        $checks['upcoming_events'] = [
            'status' => !empty($upcoming_events) ? 'healthy' : 'warning',
            'message' => !empty($upcoming_events)
                ? __('Upcoming events found', 'nhk-event-manager')
                : __('No upcoming events', 'nhk-event-manager')
        ];
        
        return $checks;
    }
}
