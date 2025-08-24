<?php
/**
 * Event List Shortcode
 * 
 * Demonstrates shortcode implementation using NHK Framework Abstract_Shortcode.
 * Shows proper use of WordPress shortcode API with template rendering.
 * 
 * @package NHK\EventManager\Frontend
 * @since 1.0.0
 */

namespace NHK\EventManager\Frontend;

use NHK\Framework\Abstracts\Abstract_Shortcode;
use NHK\EventManager\Services\EventQueryService;
use NHK\EventManager\Services\EventCacheService;

/**
 * Event List Shortcode Class
 * 
 * Demonstrates:
 * - Extending framework Abstract_Shortcode
 * - WordPress shortcode registration
 * - Template rendering system
 * - Service integration
 * - Caching implementation
 */
class EventListShortcode extends Abstract_Shortcode {
    
    /**
     * Event query service
     * 
     * @var EventQueryService
     */
    protected EventQueryService $query_service;
    
    /**
     * Cache service
     * 
     * @var EventCacheService
     */
    protected EventCacheService $cache_service;
    
    /**
     * Constructor
     * 
     * @param Container $container Service container
     * @param EventQueryService $query_service Event query service
     * @param EventCacheService $cache_service Cache service
     */
    public function __construct($container, EventQueryService $query_service, EventCacheService $cache_service) {
        parent::__construct($container);
        $this->query_service = $query_service;
        $this->cache_service = $cache_service;
    }
    
    /**
     * Get the shortcode tag
     * 
     * @return string
     */
    protected function get_shortcode_tag(): string {
        return 'nhk_events';
    }
    
    /**
     * Get default attributes
     * 
     * Demonstrates comprehensive shortcode attribute configuration.
     * 
     * @return array
     */
    protected function get_default_attributes(): array {
        return [
            'limit' => 10,
            'category' => '',
            'venue' => '',
            'orderby' => 'date',
            'order' => 'ASC',
            'show_past' => false,
            'show_title' => true,
            'show_date' => true,
            'show_excerpt' => true,
            'show_venue' => true,
            'show_image' => false,
            'image_size' => 'medium',
            'layout' => 'list',
            'css_class' => '',
            'date_format' => '',
            'time_format' => '',
            'no_events_text' => '',
            'title' => '',
            'show_filters' => false,
            'pagination' => false,
        ];
    }
    
    /**
     * Validate shortcode attributes
     * 
     * @param array $attributes Sanitized attributes
     * @return bool|string True if valid, error message if invalid
     */
    protected function validate_attributes(array $attributes) {
        // Validate limit
        if ($attributes['limit'] < 1 || $attributes['limit'] > 100) {
            return __('Limit must be between 1 and 100.', 'nhk-event-manager');
        }
        
        // Validate orderby
        $valid_orderby = ['date', 'title', 'modified', 'menu_order'];
        if (!in_array($attributes['orderby'], $valid_orderby)) {
            return sprintf(
                __('Invalid orderby value. Must be one of: %s', 'nhk-event-manager'),
                implode(', ', $valid_orderby)
            );
        }
        
        // Validate order
        if (!in_array(strtoupper($attributes['order']), ['ASC', 'DESC'])) {
            return __('Order must be ASC or DESC.', 'nhk-event-manager');
        }
        
        // Validate layout
        $valid_layouts = ['list', 'grid', 'card', 'table'];
        if (!in_array($attributes['layout'], $valid_layouts)) {
            return sprintf(
                __('Invalid layout. Must be one of: %s', 'nhk-event-manager'),
                implode(', ', $valid_layouts)
            );
        }
        
        return true;
    }
    
    /**
     * Render shortcode content
     * 
     * Demonstrates complex shortcode rendering with service integration.
     * 
     * @param array $attributes Shortcode attributes
     * @param string|null $content Shortcode content
     * @return string Rendered content
     */
    protected function render_content(array $attributes, ?string $content = null): string {
        // Enqueue necessary assets
        $this->enqueue_assets();
        
        // Build query arguments
        $query_args = $this->build_query_args($attributes);
        
        // Get events
        $events = $this->get_events($query_args, $attributes);
        
        // Prepare template variables
        $template_vars = [
            'events' => $events,
            'attributes' => $attributes,
            'total_events' => count($events),
            'has_events' => !empty($events),
            'no_events_text' => $this->get_no_events_text($attributes),
            'wrapper_class' => $this->get_wrapper_class($attributes),
        ];
        
        // Render template
        return $this->load_template('event-list', $template_vars);
    }
    
    /**
     * Build query arguments from shortcode attributes
     * 
     * @param array $attributes Shortcode attributes
     * @return array Query arguments
     */
    protected function build_query_args(array $attributes): array {
        $args = [
            'post_type' => 'nhk_event',
            'post_status' => 'publish',
            'posts_per_page' => $attributes['limit'],
            'meta_key' => 'event_start_date',
            'orderby' => 'meta_value',
            'order' => strtoupper($attributes['order']),
        ];
        
        // Date filter
        if (!$attributes['show_past']) {
            $args['meta_query'] = [
                [
                    'key' => 'event_start_date',
                    'value' => current_time('Y-m-d'),
                    'compare' => '>=',
                    'type' => 'DATE',
                ],
            ];
        }
        
        // Category filter
        if (!empty($attributes['category'])) {
            $args['tax_query'] = $args['tax_query'] ?? [];
            $args['tax_query'][] = [
                'taxonomy' => 'nhk_event_category',
                'field' => 'slug',
                'terms' => explode(',', $attributes['category']),
            ];
        }
        
        // Venue filter
        if (!empty($attributes['venue'])) {
            $args['tax_query'] = $args['tax_query'] ?? [];
            $args['tax_query'][] = [
                'taxonomy' => 'nhk_event_venue',
                'field' => 'slug',
                'terms' => explode(',', $attributes['venue']),
            ];
        }
        
        // Custom orderby
        if ($attributes['orderby'] !== 'date') {
            unset($args['meta_key']);
            $args['orderby'] = $attributes['orderby'];
        }
        
        return $args;
    }
    
    /**
     * Get events using query service
     * 
     * @param array $query_args Query arguments
     * @param array $attributes Shortcode attributes
     * @return array Events
     */
    protected function get_events(array $query_args, array $attributes): array {
        // Generate cache key
        $cache_key = $this->cache_service->generate_query_hash($query_args);
        
        // Try to get from cache
        $events = $this->cache_service->get_query_results($cache_key);
        
        if ($events === false) {
            // Execute query
            $query = new \WP_Query($query_args);
            $events = $query->posts;
            
            // Cache results
            $this->cache_service->cache_query_results($cache_key, $events, 1800); // 30 minutes
        }
        
        return $events;
    }
    
    /**
     * Get no events text
     * 
     * @param array $attributes Shortcode attributes
     * @return string No events text
     */
    protected function get_no_events_text(array $attributes): string {
        if (!empty($attributes['no_events_text'])) {
            return $attributes['no_events_text'];
        }
        
        return __('No events found.', 'nhk-event-manager');
    }
    
    /**
     * Get wrapper CSS class
     * 
     * @param array $attributes Shortcode attributes
     * @return string CSS class
     */
    protected function get_wrapper_class(array $attributes): string {
        $classes = ['nhk-events-list', 'layout-' . $attributes['layout']];
        
        if (!empty($attributes['css_class'])) {
            $classes[] = $attributes['css_class'];
        }
        
        if ($attributes['show_filters']) {
            $classes[] = 'has-filters';
        }
        
        return implode(' ', $classes);
    }
    
    /**
     * Check if caching is enabled
     * 
     * @return bool
     */
    protected function is_cache_enabled(): bool {
        return true; // Enable caching for this shortcode
    }
    
    /**
     * Enqueue shortcode assets
     * 
     * @return void
     */
    protected function enqueue_assets(): void {
        // Enqueue CSS
        wp_enqueue_style(
            'nhk-event-list',
            NHK_EVENT_MANAGER_URL . 'assets/css/event-list.css',
            [],
            NHK_EVENT_MANAGER_VERSION
        );
        
        // Enqueue JavaScript if filters are enabled
        if (isset($_GET['show_filters']) || $this->has_filters_in_content()) {
            wp_enqueue_script(
                'nhk-ajax-handler',
                NHK_EVENT_MANAGER_URL . 'assets/js/nhk-ajax-handler.js',
                [],
                NHK_EVENT_MANAGER_VERSION,
                true
            );

            wp_enqueue_script(
                'nhk-event-filter',
                NHK_EVENT_MANAGER_URL . 'assets/js/event-filter.js',
                ['jquery', 'nhk-ajax-handler'],
                NHK_EVENT_MANAGER_VERSION,
                true
            );
            
            // Localize script
            wp_localize_script('nhk-event-filter', 'nhkEventFilter', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('nhk_event_filter'),
                'strings' => [
                    'loading' => __('Loading...', 'nhk-event-manager'),
                    'noEvents' => __('No events found.', 'nhk-event-manager'),
                    'error' => __('Error loading events.', 'nhk-event-manager'),
                ],
            ]);
        }
    }
    
    /**
     * Get plugin template path
     * 
     * @return string Template path
     */
    protected function get_plugin_template_path(): string {
        return NHK_EVENT_MANAGER_PATH . 'templates/';
    }
    
    /**
     * Check if filters are present in content
     * 
     * @return bool
     */
    protected function has_filters_in_content(): bool {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        return strpos($post->post_content, 'show_filters="true"') !== false ||
               strpos($post->post_content, "show_filters='true'") !== false;
    }
    
    /**
     * Get shortcode help text
     * 
     * @return string Help text
     */
    public function get_help_text(): string {
        return __('Display a list of events with various filtering and display options.', 'nhk-event-manager');
    }
    
    /**
     * Get shortcode examples
     * 
     * @return array Examples
     */
    public function get_examples(): array {
        return [
            '[nhk_events]' => __('Display 10 upcoming events', 'nhk-event-manager'),
            '[nhk_events limit="5" layout="grid"]' => __('Display 5 events in grid layout', 'nhk-event-manager'),
            '[nhk_events category="workshop" show_past="true"]' => __('Display workshop events including past ones', 'nhk-event-manager'),
            '[nhk_events venue="conference-center" orderby="title"]' => __('Display events at conference center ordered by title', 'nhk-event-manager'),
            '[nhk_events show_filters="true" pagination="true"]' => __('Display events with filters and pagination', 'nhk-event-manager'),
        ];
    }
}
