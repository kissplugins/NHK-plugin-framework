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
use NHK\EventManager\Frontend\DemoShortcode;
use NHK\EventManager\Admin\DemoPage;
use NHK\EventManager\CPT\EventCPT;
use NHK\EventManager\Services\SampleDataImporter;

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
     * Initialize the plugin
     * 
     * Demonstrates comprehensive plugin initialization with proper component coordination.
     * 
     * @return void
     */
    public function init(): void {
        // Register services first
        $this->register_services();
        
        // Initialize core components
        $this->init_core_components();
        
        // Initialize admin components
        $this->init_admin_components();
        
        // Initialize frontend components
        $this->init_frontend_components();
        
        // Initialize API components
        $this->init_api_components();
        
        // Setup WordPress hooks
        $this->setup_hooks();
        
        // Log successful initialization
        nhk_event_manager_debug('Plugin initialized successfully');
    }
    
    /**
     * Register services in the container
     *
     * Demonstrates service registration with dependency injection.
     *
     * @return void
     */
    protected function register_services(): void {
        // Register asset manager
        $this->container->singleton(AssetManager::class, function($container) {
            return new AssetManager($container);
        });

        // Register demo shortcode
        $this->container->singleton(DemoShortcode::class, function($container) {
            return new DemoShortcode($container);
        });

        // Register demo admin page
        $this->container->singleton(DemoPage::class, function($container) {
            return new DemoPage($container);
        });

        // Register Event CPT
        $this->container->singleton(EventCPT::class, function($container) {
            return new EventCPT($container);
        });

        // Register Sample Data Importer
        $this->container->singleton(SampleDataImporter::class, function($container) {
            return new SampleDataImporter($container);
        });

        // Register other services as they're created
        // This is where we'll add EventQueryService, EventCacheService, etc.
    }
    
    /**
     * Initialize core components
     *
     * @return void
     */
    protected function init_core_components(): void {
        // Initialize asset manager
        $asset_manager = $this->container->get(AssetManager::class);
        $asset_manager->init();
        $this->components['asset_manager'] = $asset_manager;

        // Initialize Event CPT
        $event_cpt = $this->container->get(EventCPT::class);
        $event_cpt->init();
        $this->components['event_cpt'] = $event_cpt;

        // Initialize Sample Data Importer
        $sample_importer = $this->container->get(SampleDataImporter::class);
        $this->components['sample_importer'] = $sample_importer;

        // Auto-import sample data if none exists
        $this->maybe_import_sample_data();

        // Initialize other core components as they're created
    }
    
    /**
     * Initialize admin components
     * 
     * @return void
     */
    protected function init_admin_components(): void {
        if (!\function_exists('is_admin') || !\is_admin()) {
            return;
        }

        // Initialize demo admin page
        $demo_page = $this->container->get(DemoPage::class);
        $demo_page->init();
        $this->components['demo_page'] = $demo_page;

        // Initialize other admin components as they're created
    }
    
    /**
     * Initialize frontend components
     *
     * @return void
     */
    protected function init_frontend_components(): void {
        // Initialize demo shortcode
        $demo_shortcode = $this->container->get(DemoShortcode::class);
        $demo_shortcode->init();
        $this->components['demo_shortcode'] = $demo_shortcode;

        // Initialize other frontend components as they're created
    }
    
    /**
     * Initialize API components
     * 
     * @return void
     */
    protected function init_api_components(): void {
        // Initialize API components as they're created
    }
    
    /**
     * Setup WordPress hooks
     * 
     * @return void
     */
    protected function setup_hooks(): void {
        // Plugin lifecycle hooks
        add_action('init', [$this, 'load_textdomain']);
        
        // AJAX handlers for modern frontend
        add_action('wp_ajax_nhk_purge_event_cache', [$this, 'handle_cache_purge']);
        add_action('wp_ajax_nhk_event_health_check', [$this, 'handle_health_check']);
        add_action('wp_ajax_nhk_import_sample_data', [$this, 'handle_sample_data_import']);
        
        // REST API initialization
        add_action('rest_api_init', [$this, 'init_rest_api']);
        
        // Custom hooks for extensibility
        do_action('nhk_event_manager_loaded', $this);
    }
    
    /**
     * Initialize REST API endpoints
     * 
     * @return void
     */
    public function init_rest_api(): void {
        // Register basic REST API endpoints
        register_rest_route('nhk-events/v1', '/events', [
            'methods' => 'GET',
            'callback' => [$this, 'get_events_endpoint'],
            'permission_callback' => '__return_true'
        ]);
        
        register_rest_route('nhk-events/v1', '/events/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_event_endpoint'],
            'permission_callback' => '__return_true'
        ]);
        
        register_rest_route('nhk-events/v1', '/health', [
            'methods' => 'GET',
            'callback' => [$this, 'get_health_endpoint'],
            'permission_callback' => 'manage_options'
        ]);

        register_rest_route('nhk-events/v1', '/test', [
            'methods' => 'GET',
            'callback' => [$this, 'get_test_endpoint'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    /**
     * REST API endpoint: Get events
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function get_events_endpoint($request) {
        try {
            // Basic implementation - will be enhanced with proper service layer
            $args = [
                'post_type' => 'nhk_event',
                'post_status' => 'publish',
                'posts_per_page' => $request->get_param('per_page') ?: 10,
                'paged' => $request->get_param('page') ?: 1,
            ];

            // Add search parameter if provided
            if ($search = $request->get_param('search')) {
                $args['s'] = sanitize_text_field($search);
            }

            // Add category filter if provided
            if ($category = $request->get_param('category')) {
                $args['tax_query'] = [
                    [
                        'taxonomy' => 'nhk_event_category',
                        'field'    => 'slug',
                        'terms'    => sanitize_text_field($category),
                    ],
                ];
            }

            // Add venue filter if provided
            if ($venue = $request->get_param('venue')) {
                $args['tax_query'] = [
                    [
                        'taxonomy' => 'nhk_event_venue',
                        'field'    => 'slug',
                        'terms'    => sanitize_text_field($venue),
                    ],
                ];
            }

            // Add ordering
            if ($orderby = $request->get_param('orderby')) {
                $args['orderby'] = sanitize_text_field($orderby);
                $args['order'] = $request->get_param('order') ?: 'ASC';
            }

            $query = new \WP_Query($args);

            $events = [];
            foreach ($query->posts as $post) {
                // Get event meta data
                $start_date = get_post_meta($post->ID, '_nhk_event_start_date', true);
                $end_date = get_post_meta($post->ID, '_nhk_event_end_date', true);
                $start_time = get_post_meta($post->ID, '_nhk_event_start_time', true);
                $end_time = get_post_meta($post->ID, '_nhk_event_end_time', true);
                $venue = get_post_meta($post->ID, '_nhk_event_venue', true);
                $organizer = get_post_meta($post->ID, '_nhk_event_organizer', true);

                // Get categories and venues
                $categories = wp_get_post_terms($post->ID, 'nhk_event_category', ['fields' => 'names']);
                $venues = wp_get_post_terms($post->ID, 'nhk_event_venue', ['fields' => 'names']);

                $events[] = [
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'content' => $post->post_content,
                    'excerpt' => $post->post_excerpt,
                    'status' => $post->post_status,
                    'date' => $post->post_date,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'venue' => $venue,
                    'organizer' => $organizer,
                    'categories' => $categories,
                    'venues' => $venues,
                    'url' => get_permalink($post->ID),
                    'edit_url' => get_edit_post_link($post->ID),
                ];
            }

            return rest_ensure_response([
                'events' => $events,
                'total' => $query->found_posts,
                'pages' => $query->max_num_pages,
                'page' => $args['paged'],
                'per_page' => $args['posts_per_page'],
            ]);

        } catch (\Exception $e) {
            return new \WP_Error('api_error', 'Failed to fetch events: ' . $e->getMessage(), ['status' => 500]);
        }
    }
    
    /**
     * REST API endpoint: Get single event
     * 
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function get_event_endpoint($request) {
        $event_id = $request->get_param('id');
        $post = get_post($event_id);
        
        if (!$post || $post->post_type !== 'nhk_event') {
            return new \WP_Error('event_not_found', 'Event not found', ['status' => 404]);
        }
        
        return rest_ensure_response([
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'status' => $post->post_status,
            'date' => $post->post_date,
            'url' => get_permalink($post->ID),
        ]);
    }
    
    /**
     * REST API endpoint: Get health status
     * 
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function get_health_endpoint($request) {
        return rest_ensure_response([
            'status' => 'healthy',
            'version' => NHK_EVENT_MANAGER_VERSION,
            'timestamp' => current_time('mysql'),
            'checks' => [
                'database' => 'ok',
                'cache' => 'ok',
                'assets' => file_exists(NHK_EVENT_MANAGER_PATH . 'assets/dist/main.css') ? 'ok' : 'warning'
            ]
        ]);
    }

    /**
     * REST API endpoint: Test endpoint for debugging
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function get_test_endpoint($request) {
        return rest_ensure_response([
            'message' => 'API is working!',
            'timestamp' => current_time('mysql'),
            'request_params' => $request->get_params(),
            'debug_info' => [
                'wp_debug' => defined('WP_DEBUG') && WP_DEBUG,
                'rest_url' => rest_url('nhk-events/v1'),
                'events_registered' => post_type_exists('nhk_event'),
                'sample_events' => wp_count_posts('nhk_event'),
            ]
        ]);
    }

    /**
     * Handle cache purge AJAX request
     *
     * @return void
     */
    public function handle_cache_purge(): void {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wp_rest')) {
            wp_die(__('Security check failed', 'nhk-event-manager'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'nhk-event-manager'));
        }
        
        // Basic cache clearing - will be enhanced with proper cache service
        wp_cache_flush();
        
        wp_send_json_success(['message' => __('Cache purged successfully', 'nhk-event-manager')]);
    }
    
    /**
     * Handle health check AJAX request
     * 
     * @return void
     */
    public function handle_health_check(): void {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wp_rest')) {
            wp_die(__('Security check failed', 'nhk-event-manager'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'nhk-event-manager'));
        }
        
        $health_data = [
            'status' => 'healthy',
            'checks' => [
                'php_version' => version_compare(PHP_VERSION, '8.0', '>=') ? 'ok' : 'error',
                'wp_version' => version_compare(get_bloginfo('version'), '6.0', '>=') ? 'ok' : 'error',
                'assets_built' => file_exists(NHK_EVENT_MANAGER_PATH . 'assets/dist/main.css') ? 'ok' : 'warning',
                'js_built' => file_exists(NHK_EVENT_MANAGER_PATH . 'assets/dist/index.js') ? 'ok' : 'warning',
            ]
        ];
        
        wp_send_json_success($health_data);
    }
    
    /**
     * Load plugin textdomain
     * 
     * @return void
     */
    public function load_textdomain(): void {
        load_plugin_textdomain(
            'nhk-event-manager',
            false,
            dirname(NHK_EVENT_MANAGER_BASENAME) . '/languages'
        );
    }
    
    /**
     * Get plugin version
     * 
     * @return string
     */
    public function get_version(): string {
        return NHK_EVENT_MANAGER_VERSION;
    }
    
    /**
     * Get container instance
     * 
     * @return Container
     */
    public function get_container(): Container {
        return $this->container;
    }
    
    /**
     * Get component instance
     *
     * @param string $component_name Component name
     * @return mixed|null Component instance or null if not found
     */
    public function get_component(string $component_name) {
        return $this->components[$component_name] ?? null;
    }

    /**
     * Maybe import sample data on first run
     *
     * @return void
     */
    protected function maybe_import_sample_data(): void {
        // Only run this once after plugin activation
        if (\get_option('nhk_event_manager_sample_data_imported', false)) {
            return;
        }

        $sample_importer = $this->components['sample_importer'] ?? null;
        if (!$sample_importer) {
            return;
        }

        // Check if we already have sample data
        if ($sample_importer->has_sample_data()) {
            \update_option('nhk_event_manager_sample_data_imported', true);
            return;
        }

        // Import sample data
        $results = $sample_importer->import_sample_data();

        if ($results['success']) {
            \update_option('nhk_event_manager_sample_data_imported', true);
            nhk_event_manager_debug(
                "Auto-imported {$results['imported']} sample events on plugin initialization"
            );
        } else {
            nhk_event_manager_debug(
                'Failed to auto-import sample data: ' . \implode(', ', $results['errors']),
                'error'
            );
        }
    }

    /**
     * Handle manual sample data import AJAX request
     *
     * @return void
     */
    public function handle_sample_data_import(): void {
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'wp_rest')) {
            \wp_die(\__('Security check failed', 'nhk-event-manager'));
        }

        if (!\current_user_can('manage_options')) {
            \wp_die(\__('Insufficient permissions', 'nhk-event-manager'));
        }

        $sample_importer = $this->components['sample_importer'] ?? null;
        if (!$sample_importer) {
            \wp_send_json_error(['message' => \__('Sample data importer not available', 'nhk-event-manager')]);
            return;
        }

        $action = $_POST['import_action'] ?? 'import';

        if ($action === 'clear') {
            $results = $sample_importer->clear_sample_data();
            if ($results['success']) {
                \delete_option('nhk_event_manager_sample_data_imported');
                \wp_send_json_success([
                    'message' => \sprintf(
                        \__('Successfully deleted %d sample events', 'nhk-event-manager'),
                        $results['deleted']
                    ),
                    'results' => $results
                ]);
            } else {
                \wp_send_json_error([
                    'message' => \__('Failed to clear sample data', 'nhk-event-manager'),
                    'errors' => $results['errors']
                ]);
            }
        } else {
            $results = $sample_importer->import_sample_data();
            if ($results['success']) {
                \update_option('nhk_event_manager_sample_data_imported', true);
                \wp_send_json_success([
                    'message' => \sprintf(
                        \__('Successfully imported %d events (%d skipped)', 'nhk-event-manager'),
                        $results['imported'],
                        $results['skipped']
                    ),
                    'results' => $results
                ]);
            } else {
                \wp_send_json_error([
                    'message' => \__('Failed to import sample data', 'nhk-event-manager'),
                    'errors' => $results['errors']
                ]);
            }
        }
    }
}
