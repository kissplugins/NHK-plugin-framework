<?php
/**
 * Abstract Taxonomy Class for NHK Framework
 * 
 * This class provides a base for creating custom taxonomies using WordPress
 * register_taxonomy() function with additional framework features.
 * 
 * @package NHK\Framework\Abstracts
 * @since 1.0.0
 */

namespace NHK\Framework\Abstracts;

use NHK\Framework\Container\Container;

/**
 * Abstract class for Custom Taxonomies
 * 
 * Provides a structured way to create taxonomies with:
 * - WordPress register_taxonomy() integration
 * - Term meta support
 * - Admin customization
 */
abstract class Abstract_Taxonomy {
    
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
     * Initialize the taxonomy
     * 
     * Registers the taxonomy and sets up hooks.
     * 
     * @return void
     */
    public function init(): void {
        add_action('init', [$this, 'register']);
    }
    
    /**
     * Register the custom taxonomy
     * 
     * Uses WordPress register_taxonomy() function.
     * 
     * @return void
     */
    public function register(): void {
        $args = array_merge($this->get_default_args(), $this->get_args());
        $args['labels'] = $this->get_labels();
        
        register_taxonomy($this->get_slug(), $this->get_post_types(), $args);
    }
    
    /**
     * Get the taxonomy slug
     * 
     * @return string
     */
    abstract protected function get_slug(): string;
    
    /**
     * Get the post types this taxonomy applies to
     * 
     * @return array
     */
    abstract protected function get_post_types(): array;
    
    /**
     * Get the taxonomy arguments
     * 
     * @return array
     */
    abstract protected function get_args(): array;
    
    /**
     * Get the taxonomy labels
     * 
     * @return array
     */
    protected function get_labels(): array {
        $singular = $this->get_singular_label();
        $plural = $this->get_plural_label();
        
        return [
            'name' => $plural,
            'singular_name' => $singular,
            'search_items' => sprintf(__('Search %s', 'nhk-framework'), $plural),
            'all_items' => sprintf(__('All %s', 'nhk-framework'), $plural),
            'parent_item' => sprintf(__('Parent %s', 'nhk-framework'), $singular),
            'parent_item_colon' => sprintf(__('Parent %s:', 'nhk-framework'), $singular),
            'edit_item' => sprintf(__('Edit %s', 'nhk-framework'), $singular),
            'update_item' => sprintf(__('Update %s', 'nhk-framework'), $singular),
            'add_new_item' => sprintf(__('Add New %s', 'nhk-framework'), $singular),
            'new_item_name' => sprintf(__('New %s Name', 'nhk-framework'), $singular),
            'menu_name' => $plural,
        ];
    }
    
    /**
     * Get default taxonomy arguments
     * 
     * @return array
     */
    protected function get_default_args(): array {
        return [
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud' => true,
            'show_in_rest' => true,
        ];
    }
    
    /**
     * Get singular label for the taxonomy
     * 
     * Override in child classes to provide custom label.
     * 
     * @return string
     */
    protected function get_singular_label(): string {
        return ucfirst(str_replace(['_', '-'], ' ', $this->get_slug()));
    }
    
    /**
     * Get plural label for the taxonomy
     * 
     * Override in child classes to provide custom label.
     * 
     * @return string
     */
    protected function get_plural_label(): string {
        return $this->get_singular_label() . 's';
    }
    
    /**
     * Get term meta value
     * 
     * Wrapper around WordPress get_term_meta() function.
     * 
     * @param int $term_id Term ID
     * @param string $key Meta key
     * @param bool $single Return single value
     * @return mixed
     */
    protected function get_term_meta(int $term_id, string $key, bool $single = true) {
        return get_term_meta($term_id, $key, $single);
    }
    
    /**
     * Update term meta value
     * 
     * Wrapper around WordPress update_term_meta() function.
     * 
     * @param int $term_id Term ID
     * @param string $key Meta key
     * @param mixed $value Meta value
     * @return bool|int
     */
    protected function update_term_meta(int $term_id, string $key, $value) {
        return update_term_meta($term_id, $key, $value);
    }
    
    /**
     * Delete term meta value
     * 
     * Wrapper around WordPress delete_term_meta() function.
     * 
     * @param int $term_id Term ID
     * @param string $key Meta key
     * @param mixed $value Meta value (optional)
     * @return bool
     */
    protected function delete_term_meta(int $term_id, string $key, $value = '') {
        return delete_term_meta($term_id, $key, $value);
    }
    
    /**
     * Get terms for this taxonomy
     * 
     * Wrapper around WordPress get_terms() function.
     * 
     * @param array $args Query arguments
     * @return array|\WP_Error
     */
    protected function get_terms(array $args = []) {
        $args['taxonomy'] = $this->get_slug();
        return get_terms($args);
    }
    
    /**
     * Get term by field
     * 
     * Wrapper around WordPress get_term_by() function.
     * 
     * @param string $field Field to search by
     * @param string|int $value Value to search for
     * @return \WP_Term|false
     */
    protected function get_term_by(string $field, $value) {
        return get_term_by($field, $value, $this->get_slug());
    }
}
