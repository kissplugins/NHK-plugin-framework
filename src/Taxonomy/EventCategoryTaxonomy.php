<?php
/**
 * Event Category Taxonomy
 * 
 * Demonstrates taxonomy implementation using NHK Framework Abstract_Taxonomy.
 * Shows proper use of WordPress register_taxonomy() function through framework.
 * 
 * @package NHK\EventManager\Taxonomy
 * @since 1.0.0
 */

namespace NHK\EventManager\Taxonomy;

use NHK\Framework\Abstracts\Abstract_Taxonomy;

/**
 * Event Category Taxonomy Class
 * 
 * Demonstrates:
 * - Extending framework Abstract_Taxonomy
 * - WordPress taxonomy registration
 * - Hierarchical taxonomy structure
 * - Proper use of WordPress functions
 */
class EventCategoryTaxonomy extends Abstract_Taxonomy {
    
    /**
     * Get the taxonomy slug
     * 
     * @return string
     */
    protected function get_slug(): string {
        return 'nhk_event_category';
    }
    
    /**
     * Get the post types this taxonomy applies to
     * 
     * @return array
     */
    protected function get_post_types(): array {
        return ['nhk_event'];
    }
    
    /**
     * Get the taxonomy arguments
     * 
     * Configures the taxonomy using WordPress register_taxonomy() arguments.
     * 
     * @return array
     */
    protected function get_args(): array {
        return [
            'hierarchical'      => true,  // Like categories
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud'     => true,
            'show_in_rest'      => true,
            'rest_base'         => 'event-categories',
            'query_var'         => true,
            'rewrite'           => [
                'slug' => 'event-category',
                'with_front' => false,
            ],
        ];
    }
    
    /**
     * Get the taxonomy labels
     * 
     * Uses WordPress translation functions for internationalization.
     * 
     * @return array
     */
    protected function get_labels(): array {
        return [
            'name'                       => _x('Event Categories', 'Taxonomy General Name', 'nhk-event-manager'),
            'singular_name'              => _x('Event Category', 'Taxonomy Singular Name', 'nhk-event-manager'),
            'menu_name'                  => __('Categories', 'nhk-event-manager'),
            'all_items'                  => __('All Categories', 'nhk-event-manager'),
            'parent_item'                => __('Parent Category', 'nhk-event-manager'),
            'parent_item_colon'          => __('Parent Category:', 'nhk-event-manager'),
            'new_item_name'              => __('New Category Name', 'nhk-event-manager'),
            'add_new_item'               => __('Add New Category', 'nhk-event-manager'),
            'edit_item'                  => __('Edit Category', 'nhk-event-manager'),
            'update_item'                => __('Update Category', 'nhk-event-manager'),
            'view_item'                  => __('View Category', 'nhk-event-manager'),
            'separate_items_with_commas' => __('Separate categories with commas', 'nhk-event-manager'),
            'add_or_remove_items'        => __('Add or remove categories', 'nhk-event-manager'),
            'choose_from_most_used'      => __('Choose from the most used', 'nhk-event-manager'),
            'popular_items'              => __('Popular Categories', 'nhk-event-manager'),
            'search_items'               => __('Search Categories', 'nhk-event-manager'),
            'not_found'                  => __('Not Found', 'nhk-event-manager'),
            'no_terms'                   => __('No categories', 'nhk-event-manager'),
            'items_list'                 => __('Categories list', 'nhk-event-manager'),
            'items_list_navigation'      => __('Categories list navigation', 'nhk-event-manager'),
        ];
    }
    
    /**
     * Additional initialization
     * 
     * Demonstrates adding custom WordPress hooks for taxonomy-specific functionality.
     * 
     * @return void
     */
    public function init(): void {
        // Call parent initialization
        parent::init();
        
        // Add custom hooks for category-specific functionality
        add_action('created_nhk_event_category', [$this, 'category_created'], 10, 2);
        add_action('edited_nhk_event_category', [$this, 'category_updated'], 10, 2);
        add_action('delete_nhk_event_category', [$this, 'category_deleted'], 10, 4);
        
        // Add meta fields to category edit form
        add_action('nhk_event_category_add_form_fields', [$this, 'add_category_meta_fields']);
        add_action('nhk_event_category_edit_form_fields', [$this, 'edit_category_meta_fields']);
        
        // Save category meta
        add_action('created_nhk_event_category', [$this, 'save_category_meta']);
        add_action('edited_nhk_event_category', [$this, 'save_category_meta']);
    }
    
    /**
     * Handle category creation
     * 
     * Demonstrates WordPress taxonomy action hooks.
     * 
     * @param int $term_id Term ID
     * @param int $tt_id Term taxonomy ID
     * @return void
     */
    public function category_created(int $term_id, int $tt_id): void {
        // Log category creation
        if (function_exists('error_log') && WP_DEBUG_LOG) {
            error_log(sprintf(
                '[NHK Event Manager] Event category created: ID %d',
                $term_id
            ));
        }
        
        // Clear related caches
        $this->clear_category_caches();
    }
    
    /**
     * Handle category update
     * 
     * @param int $term_id Term ID
     * @param int $tt_id Term taxonomy ID
     * @return void
     */
    public function category_updated(int $term_id, int $tt_id): void {
        // Log category update
        if (function_exists('error_log') && WP_DEBUG_LOG) {
            error_log(sprintf(
                '[NHK Event Manager] Event category updated: ID %d',
                $term_id
            ));
        }
        
        // Clear related caches
        $this->clear_category_caches();
    }
    
    /**
     * Handle category deletion
     * 
     * @param int $term_id Term ID
     * @param int $tt_id Term taxonomy ID
     * @param mixed $deleted_term Deleted term object
     * @param array $object_ids Object IDs
     * @return void
     */
    public function category_deleted(int $term_id, int $tt_id, $deleted_term, array $object_ids): void {
        // Log category deletion
        if (function_exists('error_log') && WP_DEBUG_LOG) {
            error_log(sprintf(
                '[NHK Event Manager] Event category deleted: ID %d, affected %d events',
                $term_id,
                count($object_ids)
            ));
        }
        
        // Clear related caches
        $this->clear_category_caches();
    }
    
    /**
     * Add meta fields to category add form
     * 
     * Demonstrates term meta field addition using WordPress hooks.
     * 
     * @return void
     */
    public function add_category_meta_fields(): void {
        wp_nonce_field('nhk_event_category_meta', 'nhk_event_category_meta_nonce');
        ?>
        <div class="form-field">
            <label for="category_color"><?php esc_html_e('Category Color', 'nhk-event-manager'); ?></label>
            <input type="color" name="category_color" id="category_color" value="#3498db" />
            <p><?php esc_html_e('Choose a color to represent this category in calendar views.', 'nhk-event-manager'); ?></p>
        </div>
        
        <div class="form-field">
            <label for="category_icon"><?php esc_html_e('Category Icon', 'nhk-event-manager'); ?></label>
            <input type="text" name="category_icon" id="category_icon" value="" placeholder="dashicons-calendar-alt" />
            <p><?php esc_html_e('Dashicon class name for this category (optional).', 'nhk-event-manager'); ?></p>
        </div>
        
        <div class="form-field">
            <label for="category_description_extended"><?php esc_html_e('Extended Description', 'nhk-event-manager'); ?></label>
            <textarea name="category_description_extended" id="category_description_extended" rows="5" cols="50"></textarea>
            <p><?php esc_html_e('Additional description for this category (used in category archives).', 'nhk-event-manager'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Add meta fields to category edit form
     * 
     * @param \WP_Term $term Current term object
     * @return void
     */
    public function edit_category_meta_fields(\WP_Term $term): void {
        $color = $this->get_term_meta($term->term_id, 'category_color') ?: '#3498db';
        $icon = $this->get_term_meta($term->term_id, 'category_icon') ?: '';
        $extended_desc = $this->get_term_meta($term->term_id, 'category_description_extended') ?: '';
        
        wp_nonce_field('nhk_event_category_meta', 'nhk_event_category_meta_nonce');
        ?>
        <tr class="form-field">
            <th scope="row">
                <label for="category_color"><?php esc_html_e('Category Color', 'nhk-event-manager'); ?></label>
            </th>
            <td>
                <input type="color" name="category_color" id="category_color" value="<?php echo esc_attr($color); ?>" />
                <p class="description"><?php esc_html_e('Choose a color to represent this category in calendar views.', 'nhk-event-manager'); ?></p>
            </td>
        </tr>
        
        <tr class="form-field">
            <th scope="row">
                <label for="category_icon"><?php esc_html_e('Category Icon', 'nhk-event-manager'); ?></label>
            </th>
            <td>
                <input type="text" name="category_icon" id="category_icon" value="<?php echo esc_attr($icon); ?>" placeholder="dashicons-calendar-alt" />
                <p class="description"><?php esc_html_e('Dashicon class name for this category (optional).', 'nhk-event-manager'); ?></p>
            </td>
        </tr>
        
        <tr class="form-field">
            <th scope="row">
                <label for="category_description_extended"><?php esc_html_e('Extended Description', 'nhk-event-manager'); ?></label>
            </th>
            <td>
                <textarea name="category_description_extended" id="category_description_extended" rows="5" cols="50"><?php echo esc_textarea($extended_desc); ?></textarea>
                <p class="description"><?php esc_html_e('Additional description for this category (used in category archives).', 'nhk-event-manager'); ?></p>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Save category meta data
     * 
     * Demonstrates proper meta data saving with security checks.
     * Uses WordPress nonce verification and sanitization.
     * 
     * @param int $term_id Term ID
     * @return void
     */
    public function save_category_meta(int $term_id): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nhk_event_category_meta_nonce'] ?? '', 'nhk_event_category_meta')) {
            return;
        }
        
        // Check user capabilities
        if (!current_user_can('manage_categories')) {
            return;
        }
        
        // Save category color
        if (isset($_POST['category_color'])) {
            $color = sanitize_hex_color($_POST['category_color']);
            if ($color) {
                $this->update_term_meta($term_id, 'category_color', $color);
            }
        }
        
        // Save category icon
        if (isset($_POST['category_icon'])) {
            $icon = sanitize_text_field($_POST['category_icon']);
            $this->update_term_meta($term_id, 'category_icon', $icon);
        }
        
        // Save extended description
        if (isset($_POST['category_description_extended'])) {
            $extended_desc = wp_kses_post($_POST['category_description_extended']);
            $this->update_term_meta($term_id, 'category_description_extended', $extended_desc);
        }
    }
    
    /**
     * Clear category-related caches
     * 
     * Demonstrates cache invalidation using WordPress transients.
     * 
     * @return void
     */
    protected function clear_category_caches(): void {
        // Clear category list cache
        delete_transient('nhk_event_categories_list');
        
        // Clear category counts cache
        delete_transient('nhk_event_categories_counts');
        
        // Clear WordPress object cache for this taxonomy
        wp_cache_delete('all_ids', 'nhk_event_category');
        wp_cache_delete('get', 'nhk_event_category');
    }
    
    /**
     * Get category color
     * 
     * @param int $term_id Term ID
     * @return string Color hex code
     */
    public function get_category_color(int $term_id): string {
        return $this->get_term_meta($term_id, 'category_color') ?: '#3498db';
    }
    
    /**
     * Get category icon
     * 
     * @param int $term_id Term ID
     * @return string Icon class name
     */
    public function get_category_icon(int $term_id): string {
        return $this->get_term_meta($term_id, 'category_icon') ?: 'dashicons-calendar-alt';
    }
}
