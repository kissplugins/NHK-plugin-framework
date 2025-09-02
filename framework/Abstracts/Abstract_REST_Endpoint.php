<?php
/**
 * Abstract REST Endpoint Class for NHK Framework
 * 
 * This class provides a base for creating REST API endpoints using WordPress
 * REST API with additional framework features.
 * 
 * @package NHK\Framework\Abstracts
 * @since 1.0.0
 */

namespace NHK\Framework\Abstracts;

use NHK\Framework\Container\Container;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Abstract class for REST API Endpoints
 * 
 * Provides a structured way to create REST endpoints with:
 * - WordPress REST API integration
 * - Automatic route registration
 * - Permission handling
 * - Response formatting
 */
abstract class Abstract_REST_Endpoint {
    
    /**
     * Service container
     * 
     * @var Container
     */
    protected Container $container;
    
    /**
     * Constructor
     * 
     * @param Container $container Service container
     */
    public function __construct(Container $container) {
        $this->container = $container;
    }
    
    /**
     * Initialize the REST endpoint
     * 
     * @return void
     */
    public function init(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    /**
     * Register REST routes
     * 
     * Uses WordPress register_rest_route() function.
     * 
     * @return void
     */
    public function register_routes(): void {
        $routes = $this->get_routes();
        
        foreach ($routes as $route => $config) {
            register_rest_route(
                $this->get_namespace(),
                $route,
                [
                    'methods' => $config['methods'],
                    'callback' => $config['callback'],
                    'permission_callback' => $config['permission_callback'] ?? [$this, 'check_permissions'],
                    'args' => $config['args'] ?? [],
                ]
            );
        }
    }
    
    /**
     * Get the REST namespace
     * 
     * @return string
     */
    abstract protected function get_namespace(): string;
    
    /**
     * Get routes configuration
     * 
     * @return array
     */
    abstract protected function get_routes(): array;
    
    /**
     * Check permissions for REST requests
     * 
     * Default implementation allows public access.
     * Override in child classes for custom permission logic.
     * 
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error
     */
    public function check_permissions(WP_REST_Request $request) {
        return true;
    }
    
    /**
     * Create a success response
     * 
     * @param mixed $data Response data
     * @param int $status HTTP status code
     * @return WP_REST_Response
     */
    protected function success_response($data, int $status = 200): WP_REST_Response {
        return new WP_REST_Response($data, $status);
    }
    
    /**
     * Create an error response
     * 
     * @param string $code Error code
     * @param string $message Error message
     * @param int $status HTTP status code
     * @return WP_Error
     */
    protected function error_response(string $code, string $message, int $status = 400): WP_Error {
        return new WP_Error($code, $message, ['status' => $status]);
    }
    
    /**
     * Validate required parameters
     * 
     * @param WP_REST_Request $request Request object
     * @param array $required_params Required parameter names
     * @return bool|WP_Error
     */
    protected function validate_required_params(WP_REST_Request $request, array $required_params) {
        foreach ($required_params as $param) {
            if (!$request->has_param($param) || empty($request->get_param($param))) {
                return $this->error_response(
                    'missing_parameter',
                    sprintf(__('Missing required parameter: %s', 'nhk-framework'), $param),
                    400
                );
            }
        }
        
        return true;
    }
    
    /**
     * Sanitize request parameters
     * 
     * @param WP_REST_Request $request Request object
     * @param array $sanitize_map Parameter sanitization map
     * @return array Sanitized parameters
     */
    protected function sanitize_params(WP_REST_Request $request, array $sanitize_map): array {
        $sanitized = [];
        
        foreach ($sanitize_map as $param => $sanitize_function) {
            $value = $request->get_param($param);
            
            if ($value !== null) {
                if (is_callable($sanitize_function)) {
                    $sanitized[$param] = call_user_func($sanitize_function, $value);
                } else {
                    $sanitized[$param] = $value;
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Get pagination parameters from request
     * 
     * @param WP_REST_Request $request Request object
     * @return array Pagination parameters
     */
    protected function get_pagination_params(WP_REST_Request $request): array {
        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: 10;
        
        // Limit per_page to prevent abuse
        $per_page = min(max(1, absint($per_page)), 100);
        $page = max(1, absint($page));
        
        return [
            'page' => $page,
            'per_page' => $per_page,
            'offset' => ($page - 1) * $per_page,
        ];
    }
    
    /**
     * Add pagination headers to response
     * 
     * @param WP_REST_Response $response Response object
     * @param int $total_items Total number of items
     * @param int $per_page Items per page
     * @param int $current_page Current page
     * @return WP_REST_Response
     */
    protected function add_pagination_headers(WP_REST_Response $response, int $total_items, int $per_page, int $current_page): WP_REST_Response {
        $total_pages = ceil($total_items / $per_page);
        
        $response->header('X-WP-Total', $total_items);
        $response->header('X-WP-TotalPages', $total_pages);
        
        return $response;
    }
    
    /**
     * Check if user can edit posts
     * 
     * @param WP_REST_Request $request Request object
     * @return bool
     */
    protected function can_edit_posts(WP_REST_Request $request): bool {
        return current_user_can('edit_posts');
    }
    
    /**
     * Check if user can publish posts
     * 
     * @param WP_REST_Request $request Request object
     * @return bool
     */
    protected function can_publish_posts(WP_REST_Request $request): bool {
        return current_user_can('publish_posts');
    }
    
    /**
     * Check if user can delete posts
     * 
     * @param WP_REST_Request $request Request object
     * @return bool
     */
    protected function can_delete_posts(WP_REST_Request $request): bool {
        return current_user_can('delete_posts');
    }
    
    /**
     * Verify nonce from request
     * 
     * @param WP_REST_Request $request Request object
     * @param string $action Nonce action
     * @return bool
     */
    protected function verify_nonce(WP_REST_Request $request, string $action): bool {
        $nonce = $request->get_header('X-WP-Nonce') ?: $request->get_param('_wpnonce');
        
        if (!$nonce) {
            return false;
        }
        
        return wp_verify_nonce($nonce, $action);
    }
    
    /**
     * Log API request for debugging
     * 
     * @param WP_REST_Request $request Request object
     * @param string $endpoint Endpoint name
     * @return void
     */
    protected function log_request(WP_REST_Request $request, string $endpoint): void {
        if (!WP_DEBUG_LOG) {
            return;
        }
        
        $log_data = [
            'endpoint' => $endpoint,
            'method' => $request->get_method(),
            'params' => $request->get_params(),
            'user_id' => get_current_user_id(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ];
        
        error_log(sprintf(
            '[NHK Framework API] %s: %s',
            $endpoint,
            wp_json_encode($log_data)
        ));
    }
    
    /**
     * Format post data for API response
     * 
     * @param \WP_Post $post Post object
     * @param array $fields Fields to include
     * @return array Formatted post data
     */
    protected function format_post_data(\WP_Post $post, array $fields = []): array {
        $default_fields = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'status' => $post->post_status,
            'date' => $post->post_date,
            'modified' => $post->post_modified,
            'slug' => $post->post_name,
            'link' => get_permalink($post->ID),
        ];
        
        if (empty($fields)) {
            return $default_fields;
        }
        
        return array_intersect_key($default_fields, array_flip($fields));
    }
    
    /**
     * Format term data for API response
     * 
     * @param \WP_Term $term Term object
     * @param array $fields Fields to include
     * @return array Formatted term data
     */
    protected function format_term_data(\WP_Term $term, array $fields = []): array {
        $default_fields = [
            'id' => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
            'description' => $term->description,
            'count' => $term->count,
            'taxonomy' => $term->taxonomy,
            'link' => get_term_link($term),
        ];
        
        if (empty($fields)) {
            return $default_fields;
        }
        
        return array_intersect_key($default_fields, array_flip($fields));
    }
}
