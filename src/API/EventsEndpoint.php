<?php
/**
 * Events REST API Endpoint
 * 
 * Demonstrates REST API implementation using NHK Framework Abstract_REST_Endpoint.
 * Shows proper use of WordPress REST API with custom endpoints.
 * 
 * @package NHK\EventManager\API
 * @since 1.0.0
 */

namespace NHK\EventManager\API;

use NHK\Framework\Abstracts\Abstract_REST_Endpoint;
use NHK\EventManager\Services\EventQueryService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Events REST API Endpoint Class
 * 
 * Demonstrates:
 * - Extending framework Abstract_REST_Endpoint
 * - WordPress REST API usage
 * - CRUD operations via REST
 * - Proper authentication and permissions
 * - Data validation and sanitization
 */
class EventsEndpoint extends Abstract_REST_Endpoint {
    
    /**
     * Event query service
     * 
     * @var EventQueryService
     */
    protected EventQueryService $query_service;
    
    /**
     * Constructor
     * 
     * @param Container $container Service container
     * @param EventQueryService $query_service Event query service
     */
    public function __construct($container, EventQueryService $query_service) {
        parent::__construct($container);
        $this->query_service = $query_service;
    }
    
    /**
     * Get the REST namespace
     * 
     * @return string
     */
    protected function get_namespace(): string {
        return 'nhk-events/v1';
    }
    
    /**
     * Get routes configuration
     * 
     * Demonstrates multiple REST routes with different methods and permissions.
     * 
     * @return array
     */
    protected function get_routes(): array {
        return [
            '/events' => [
                'methods' => ['GET', 'POST'],
                'callback' => [$this, 'handle_events_collection'],
                'permission_callback' => [$this, 'check_collection_permissions'],
                'args' => $this->get_collection_args(),
            ],
            '/events/(?P<id>\d+)' => [
                'methods' => ['GET', 'PUT', 'DELETE'],
                'callback' => [$this, 'handle_single_event'],
                'permission_callback' => [$this, 'check_single_permissions'],
                'args' => $this->get_single_args(),
            ],
            '/events/upcoming' => [
                'methods' => 'GET',
                'callback' => [$this, 'get_upcoming_events'],
                'permission_callback' => '__return_true',
                'args' => $this->get_upcoming_args(),
            ],
            '/events/search' => [
                'methods' => 'GET',
                'callback' => [$this, 'search_events'],
                'permission_callback' => '__return_true',
                'args' => $this->get_search_args(),
            ],
        ];
    }
    
    /**
     * Handle events collection requests (GET /events, POST /events)
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function handle_events_collection(WP_REST_Request $request) {
        $this->log_request($request, 'events_collection');
        
        $method = $request->get_method();
        
        switch ($method) {
            case 'GET':
                return $this->get_events($request);
            case 'POST':
                return $this->create_event($request);
            default:
                return $this->error_response('invalid_method', 'Method not allowed', 405);
        }
    }
    
    /**
     * Handle single event requests (GET /events/{id}, PUT /events/{id}, DELETE /events/{id})
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function handle_single_event(WP_REST_Request $request) {
        $this->log_request($request, 'single_event');
        
        $method = $request->get_method();
        $event_id = $request->get_param('id');
        
        // Check if event exists
        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'nhk_event') {
            return $this->error_response('event_not_found', 'Event not found', 404);
        }
        
        switch ($method) {
            case 'GET':
                return $this->get_single_event($request, $event);
            case 'PUT':
                return $this->update_event($request, $event);
            case 'DELETE':
                return $this->delete_event($request, $event);
            default:
                return $this->error_response('invalid_method', 'Method not allowed', 405);
        }
    }
    
    /**
     * Get events list
     * 
     * Demonstrates query parameter handling and pagination.
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function get_events(WP_REST_Request $request): WP_REST_Response {
        $pagination = $this->get_pagination_params($request);
        
        // Build query arguments
        $args = [
            'post_type' => 'nhk_event',
            'post_status' => 'publish',
            'posts_per_page' => $pagination['per_page'],
            'offset' => $pagination['offset'],
            'meta_key' => 'event_start_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
        ];
        
        // Add filters
        $this->apply_filters($request, $args);
        
        // Execute query
        $query = new \WP_Query($args);
        $events = [];
        
        foreach ($query->posts as $post) {
            $events[] = $this->format_event_data($post);
        }
        
        $response = $this->success_response($events);
        $this->add_pagination_headers($response, $query->found_posts, $pagination['per_page'], $pagination['page']);
        
        return $response;
    }
    
    /**
     * Get single event
     * 
     * @param WP_REST_Request $request Request object
     * @param \WP_Post $event Event post object
     * @return WP_REST_Response
     */
    public function get_single_event(WP_REST_Request $request, \WP_Post $event): WP_REST_Response {
        $event_data = $this->format_event_data($event, true);
        return $this->success_response($event_data);
    }
    
    /**
     * Create new event
     * 
     * Demonstrates POST request handling with validation.
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function create_event(WP_REST_Request $request) {
        // Validate required fields
        $validation = $this->validate_required_params($request, ['title', 'start_date']);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Sanitize input data
        $sanitized = $this->sanitize_params($request, [
            'title' => 'sanitize_text_field',
            'content' => 'wp_kses_post',
            'excerpt' => 'sanitize_textarea_field',
            'start_date' => 'sanitize_text_field',
            'end_date' => 'sanitize_text_field',
            'venue' => 'sanitize_text_field',
            'capacity' => 'absint',
        ]);
        
        // Create post
        $post_data = [
            'post_type' => 'nhk_event',
            'post_title' => $sanitized['title'],
            'post_content' => $sanitized['content'] ?? '',
            'post_excerpt' => $sanitized['excerpt'] ?? '',
            'post_status' => 'publish',
            'meta_input' => [
                'event_start_date' => $sanitized['start_date'],
                'event_end_date' => $sanitized['end_date'] ?? '',
                'event_venue' => $sanitized['venue'] ?? '',
                'event_capacity' => $sanitized['capacity'] ?? '',
            ],
        ];
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            return $this->error_response('create_failed', 'Failed to create event', 500);
        }
        
        $event = get_post($post_id);
        $event_data = $this->format_event_data($event, true);
        
        return $this->success_response($event_data, 201);
    }
    
    /**
     * Update existing event
     * 
     * @param WP_REST_Request $request Request object
     * @param \WP_Post $event Event post object
     * @return WP_REST_Response|WP_Error
     */
    public function update_event(WP_REST_Request $request, \WP_Post $event) {
        // Sanitize input data
        $sanitized = $this->sanitize_params($request, [
            'title' => 'sanitize_text_field',
            'content' => 'wp_kses_post',
            'excerpt' => 'sanitize_textarea_field',
            'start_date' => 'sanitize_text_field',
            'end_date' => 'sanitize_text_field',
            'venue' => 'sanitize_text_field',
            'capacity' => 'absint',
        ]);
        
        // Update post
        $post_data = [
            'ID' => $event->ID,
        ];
        
        if (isset($sanitized['title'])) {
            $post_data['post_title'] = $sanitized['title'];
        }
        
        if (isset($sanitized['content'])) {
            $post_data['post_content'] = $sanitized['content'];
        }
        
        if (isset($sanitized['excerpt'])) {
            $post_data['post_excerpt'] = $sanitized['excerpt'];
        }
        
        $result = wp_update_post($post_data);
        
        if (is_wp_error($result)) {
            return $this->error_response('update_failed', 'Failed to update event', 500);
        }
        
        // Update meta fields
        $meta_fields = ['start_date', 'end_date', 'venue', 'capacity'];
        foreach ($meta_fields as $field) {
            if (isset($sanitized[$field])) {
                update_post_meta($event->ID, 'event_' . $field, $sanitized[$field]);
            }
        }
        
        $updated_event = get_post($event->ID);
        $event_data = $this->format_event_data($updated_event, true);
        
        return $this->success_response($event_data);
    }
    
    /**
     * Delete event
     * 
     * @param WP_REST_Request $request Request object
     * @param \WP_Post $event Event post object
     * @return WP_REST_Response|WP_Error
     */
    public function delete_event(WP_REST_Request $request, \WP_Post $event) {
        $force = $request->get_param('force');
        
        if ($force) {
            $result = wp_delete_post($event->ID, true);
        } else {
            $result = wp_trash_post($event->ID);
        }
        
        if (!$result) {
            return $this->error_response('delete_failed', 'Failed to delete event', 500);
        }
        
        return $this->success_response([
            'deleted' => true,
            'id' => $event->ID,
        ]);
    }
    
    /**
     * Get upcoming events
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function get_upcoming_events(WP_REST_Request $request): WP_REST_Response {
        $limit = min(absint($request->get_param('limit') ?: 10), 50);
        
        $events = $this->query_service->get_upcoming_events($limit);
        $formatted_events = [];
        
        foreach ($events as $event) {
            $formatted_events[] = $this->format_event_data($event);
        }
        
        return $this->success_response($formatted_events);
    }
    
    /**
     * Search events
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function search_events(WP_REST_Request $request): WP_REST_Response {
        $search_term = sanitize_text_field($request->get_param('q'));
        
        if (empty($search_term)) {
            return $this->error_response('missing_search_term', 'Search term is required', 400);
        }
        
        $args = [
            'post_type' => 'nhk_event',
            'post_status' => 'publish',
            's' => $search_term,
            'posts_per_page' => 20,
        ];
        
        $query = new \WP_Query($args);
        $events = [];
        
        foreach ($query->posts as $post) {
            $events[] = $this->format_event_data($post);
        }
        
        return $this->success_response($events);
    }

    /**
     * Check permissions for collection requests
     *
     * @param WP_REST_Request $request Request object
     * @return bool
     */
    public function check_collection_permissions(WP_REST_Request $request): bool {
        $method = $request->get_method();

        switch ($method) {
            case 'GET':
                return true; // Public access for reading
            case 'POST':
                return $this->can_publish_posts($request);
            default:
                return false;
        }
    }

    /**
     * Check permissions for single event requests
     *
     * @param WP_REST_Request $request Request object
     * @return bool
     */
    public function check_single_permissions(WP_REST_Request $request): bool {
        $method = $request->get_method();

        switch ($method) {
            case 'GET':
                return true; // Public access for reading
            case 'PUT':
                return $this->can_edit_posts($request);
            case 'DELETE':
                return $this->can_delete_posts($request);
            default:
                return false;
        }
    }

    /**
     * Apply filters to query arguments
     *
     * @param WP_REST_Request $request Request object
     * @param array &$args Query arguments (passed by reference)
     * @return void
     */
    protected function apply_filters(WP_REST_Request $request, array &$args): void {
        // Date range filter
        $start_date = $request->get_param('start_date');
        $end_date = $request->get_param('end_date');

        if ($start_date || $end_date) {
            $meta_query = $args['meta_query'] ?? [];

            if ($start_date) {
                $meta_query[] = [
                    'key' => 'event_start_date',
                    'value' => sanitize_text_field($start_date),
                    'compare' => '>=',
                    'type' => 'DATE',
                ];
            }

            if ($end_date) {
                $meta_query[] = [
                    'key' => 'event_start_date',
                    'value' => sanitize_text_field($end_date),
                    'compare' => '<=',
                    'type' => 'DATE',
                ];
            }

            $args['meta_query'] = $meta_query;
        }

        // Category filter
        $category = $request->get_param('category');
        if ($category) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'nhk_event_category',
                    'field' => 'slug',
                    'terms' => sanitize_text_field($category),
                ],
            ];
        }

        // Venue filter
        $venue = $request->get_param('venue');
        if ($venue) {
            $args['tax_query'] = $args['tax_query'] ?? [];
            $args['tax_query'][] = [
                'taxonomy' => 'nhk_event_venue',
                'field' => 'slug',
                'terms' => sanitize_text_field($venue),
            ];
        }

        // Sort order
        $orderby = $request->get_param('orderby');
        $order = $request->get_param('order');

        if ($orderby) {
            switch ($orderby) {
                case 'date':
                    $args['meta_key'] = 'event_start_date';
                    $args['orderby'] = 'meta_value';
                    break;
                case 'title':
                    $args['orderby'] = 'title';
                    break;
                case 'modified':
                    $args['orderby'] = 'modified';
                    break;
            }
        }

        if ($order && in_array(strtoupper($order), ['ASC', 'DESC'])) {
            $args['order'] = strtoupper($order);
        }
    }

    /**
     * Format event data for API response
     *
     * @param \WP_Post $event Event post object
     * @param bool $detailed Include detailed information
     * @return array Formatted event data
     */
    protected function format_event_data(\WP_Post $event, bool $detailed = false): array {
        $data = $this->format_post_data($event);

        // Add event-specific fields
        $data['start_date'] = get_post_meta($event->ID, 'event_start_date', true);
        $data['end_date'] = get_post_meta($event->ID, 'event_end_date', true);
        $data['venue'] = get_post_meta($event->ID, 'event_venue', true);
        $data['capacity'] = get_post_meta($event->ID, 'event_capacity', true);

        // Add taxonomies
        $data['categories'] = $this->get_event_terms($event->ID, 'nhk_event_category');
        $data['venues'] = $this->get_event_terms($event->ID, 'nhk_event_venue');

        if ($detailed) {
            // Add detailed information
            $data['address'] = get_post_meta($event->ID, 'event_address', true);
            $data['cost'] = get_post_meta($event->ID, 'event_cost', true);
            $data['registration_url'] = get_post_meta($event->ID, 'event_registration_url', true);
            $data['organizer'] = get_post_meta($event->ID, 'event_organizer', true);
            $data['organizer_email'] = get_post_meta($event->ID, 'event_organizer_email', true);

            // Add featured image
            if (has_post_thumbnail($event->ID)) {
                $data['featured_image'] = wp_get_attachment_image_src(get_post_thumbnail_id($event->ID), 'large');
            }
        }

        return $data;
    }

    /**
     * Get event terms for a taxonomy
     *
     * @param int $event_id Event ID
     * @param string $taxonomy Taxonomy name
     * @return array Terms data
     */
    protected function get_event_terms(int $event_id, string $taxonomy): array {
        $terms = get_the_terms($event_id, $taxonomy);

        if (!$terms || is_wp_error($terms)) {
            return [];
        }

        $formatted_terms = [];
        foreach ($terms as $term) {
            $formatted_terms[] = $this->format_term_data($term);
        }

        return $formatted_terms;
    }

    /**
     * Get collection arguments
     *
     * @return array
     */
    protected function get_collection_args(): array {
        return [
            'page' => [
                'description' => 'Page number',
                'type' => 'integer',
                'minimum' => 1,
                'default' => 1,
            ],
            'per_page' => [
                'description' => 'Items per page',
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 100,
                'default' => 10,
            ],
            'start_date' => [
                'description' => 'Filter events starting from this date',
                'type' => 'string',
                'format' => 'date',
            ],
            'end_date' => [
                'description' => 'Filter events ending before this date',
                'type' => 'string',
                'format' => 'date',
            ],
            'category' => [
                'description' => 'Filter by category slug',
                'type' => 'string',
            ],
            'venue' => [
                'description' => 'Filter by venue slug',
                'type' => 'string',
            ],
            'orderby' => [
                'description' => 'Sort by field',
                'type' => 'string',
                'enum' => ['date', 'title', 'modified'],
                'default' => 'date',
            ],
            'order' => [
                'description' => 'Sort order',
                'type' => 'string',
                'enum' => ['asc', 'desc'],
                'default' => 'asc',
            ],
        ];
    }

    /**
     * Get single event arguments
     *
     * @return array
     */
    protected function get_single_args(): array {
        return [
            'id' => [
                'description' => 'Event ID',
                'type' => 'integer',
                'required' => true,
            ],
            'force' => [
                'description' => 'Force delete (bypass trash)',
                'type' => 'boolean',
                'default' => false,
            ],
        ];
    }

    /**
     * Get upcoming events arguments
     *
     * @return array
     */
    protected function get_upcoming_args(): array {
        return [
            'limit' => [
                'description' => 'Number of events to return',
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 50,
                'default' => 10,
            ],
        ];
    }

    /**
     * Get search arguments
     *
     * @return array
     */
    protected function get_search_args(): array {
        return [
            'q' => [
                'description' => 'Search term',
                'type' => 'string',
                'required' => true,
            ],
        ];
    }
}
