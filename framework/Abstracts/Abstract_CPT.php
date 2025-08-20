<?php
/**
 * Abstract Custom Post Type Class for NHK Framework
 * 
 * This class provides a base for creating custom post types using WordPress
 * register_post_type() function with additional framework features.
 * 
 * @package NHK\Framework\Abstracts
 * @since 1.0.0
 */

namespace NHK\Framework\Abstracts;

use NHK\Framework\Container\Container;

/**
 * Abstract class for Custom Post Types
 * 
 * Provides a structured way to create CPTs with:
 * - WordPress register_post_type() integration
 * - Meta box registration
 * - Admin column customization
 * - Capability management
 */
abstract class Abstract_CPT {
    
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
     * Initialize the CPT
     * 
     * Registers the post type and sets up hooks.
     * 
     * @return void
     */
    public function init(): void {
        add_action('init', [$this, 'register']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_filter("manage_{$this->get_slug()}_posts_columns", [$this, 'add_admin_columns']);
        add_action("manage_{$this->get_slug()}_posts_custom_column", [$this, 'render_admin_columns'], 10, 2);
    }
    
    /**
     * Register the custom post type
     * 
     * Uses WordPress register_post_type() function.
     * 
     * @return void
     */
    public function register(): void {
        $args = array_merge($this->get_default_args(), $this->get_args());
        $args['labels'] = $this->get_labels();
        $args['capabilities'] = $this->get_capabilities();
        
        register_post_type($this->get_slug(), $args);
    }
    
    /**
     * Add meta boxes for this CPT
     * 
     * @return void
     */
    public function add_meta_boxes(): void {
        $this->register_meta_boxes();
    }
    
    /**
     * Add custom admin columns
     * 
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_admin_columns(array $columns): array {
        $custom_columns = $this->register_columns();
        
        if (empty($custom_columns)) {
            return $columns;
        }
        
        // Insert custom columns before the date column
        $date_column = $columns['date'] ?? null;
        unset($columns['date']);
        
        $columns = array_merge($columns, $custom_columns);
        
        if ($date_column) {
            $columns['date'] = $date_column;
        }
        
        return $columns;
    }
    
    /**
     * Render custom admin columns
     * 
     * @param string $column Column name
     * @param int $post_id Post ID
     * @return void
     */
    public function render_admin_columns(string $column, int $post_id): void {
        $this->render_column($column, $post_id);
    }
    
    /**
     * Get the CPT slug
     * 
     * @return string
     */
    abstract protected function get_slug(): string;
    
    /**
     * Get the CPT labels
     * 
     * @return array
     */
    abstract protected function get_labels(): array;
    
    /**
     * Get the CPT arguments
     * 
     * @return array
     */
    abstract protected function get_args(): array;
    
    /**
     * Get default CPT arguments
     * 
     * @return array
     */
    protected function get_default_args(): array {
        return [
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => null,
            'supports' => ['title', 'editor'],
            'show_in_rest' => true,
        ];
    }
    
    /**
     * Get CPT capabilities
     * 
     * @return array
     */
    protected function get_capabilities(): array {
        return [
            'edit_post' => 'edit_posts',
            'read_post' => 'read_posts',
            'delete_post' => 'delete_posts',
            'edit_posts' => 'edit_posts',
            'edit_others_posts' => 'edit_others_posts',
            'publish_posts' => 'publish_posts',
            'read_private_posts' => 'read_private_posts',
        ];
    }
    
    /**
     * Register meta boxes for this CPT
     * 
     * Override in child classes to add meta boxes using add_meta_box().
     * 
     * @return void
     */
    protected function register_meta_boxes(): void {
        // Override in child classes
    }
    
    /**
     * Register custom admin columns
     * 
     * Override in child classes to return array of custom columns.
     * 
     * @return array
     */
    protected function register_columns(): array {
        return [];
    }
    
    /**
     * Render custom admin column content
     * 
     * Override in child classes to render column content.
     * 
     * @param string $column Column name
     * @param int $post_id Post ID
     * @return void
     */
    protected function render_column(string $column, int $post_id): void {
        // Override in child classes
    }
    
    /**
     * Get post meta value
     * 
     * Wrapper around WordPress get_post_meta() function.
     * 
     * @param int $post_id Post ID
     * @param string $key Meta key
     * @param bool $single Return single value
     * @return mixed
     */
    protected function get_meta(int $post_id, string $key, bool $single = true) {
        return get_post_meta($post_id, $key, $single);
    }
    
    /**
     * Update post meta value
     * 
     * Wrapper around WordPress update_post_meta() function.
     * 
     * @param int $post_id Post ID
     * @param string $key Meta key
     * @param mixed $value Meta value
     * @return bool|int
     */
    protected function update_meta(int $post_id, string $key, $value) {
        return update_post_meta($post_id, $key, $value);
    }
    
    /**
     * Delete post meta value
     * 
     * Wrapper around WordPress delete_post_meta() function.
     * 
     * @param int $post_id Post ID
     * @param string $key Meta key
     * @param mixed $value Meta value (optional)
     * @return bool
     */
    protected function delete_meta(int $post_id, string $key, $value = '') {
        return delete_post_meta($post_id, $key, $value);
    }
}
