<?php
/**
 * Event Venue Taxonomy
 * 
 * Demonstrates non-hierarchical taxonomy implementation using NHK Framework.
 * Shows proper use of WordPress register_taxonomy() function for tag-like taxonomy.
 * 
 * @package NHK\EventManager\Taxonomy
 * @since 1.0.0
 */

namespace NHK\EventManager\Taxonomy;

use NHK\Framework\Abstracts\Abstract_Taxonomy;

/**
 * Event Venue Taxonomy Class
 * 
 * Demonstrates:
 * - Non-hierarchical taxonomy (like tags)
 * - Venue-specific meta fields
 * - Location data handling
 * - Proper WordPress function usage
 */
class EventVenueTaxonomy extends Abstract_Taxonomy {
    
    /**
     * Get the taxonomy slug
     * 
     * @return string
     */
    protected function get_slug(): string {
        return 'nhk_event_venue';
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
     * Configures as non-hierarchical taxonomy (like tags).
     * 
     * @return array
     */
    protected function get_args(): array {
        return [
            'hierarchical'      => false,  // Like tags
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud'     => true,
            'show_in_rest'      => true,
            'rest_base'         => 'event-venues',
            'query_var'         => true,
            'rewrite'           => [
                'slug' => 'event-venue',
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
            'name'                       => _x('Event Venues', 'Taxonomy General Name', 'nhk-event-manager'),
            'singular_name'              => _x('Event Venue', 'Taxonomy Singular Name', 'nhk-event-manager'),
            'menu_name'                  => __('Venues', 'nhk-event-manager'),
            'all_items'                  => __('All Venues', 'nhk-event-manager'),
            'new_item_name'              => __('New Venue Name', 'nhk-event-manager'),
            'add_new_item'               => __('Add New Venue', 'nhk-event-manager'),
            'edit_item'                  => __('Edit Venue', 'nhk-event-manager'),
            'update_item'                => __('Update Venue', 'nhk-event-manager'),
            'view_item'                  => __('View Venue', 'nhk-event-manager'),
            'separate_items_with_commas' => __('Separate venues with commas', 'nhk-event-manager'),
            'add_or_remove_items'        => __('Add or remove venues', 'nhk-event-manager'),
            'choose_from_most_used'      => __('Choose from the most used', 'nhk-event-manager'),
            'popular_items'              => __('Popular Venues', 'nhk-event-manager'),
            'search_items'               => __('Search Venues', 'nhk-event-manager'),
            'not_found'                  => __('Not Found', 'nhk-event-manager'),
            'no_terms'                   => __('No venues', 'nhk-event-manager'),
            'items_list'                 => __('Venues list', 'nhk-event-manager'),
            'items_list_navigation'      => __('Venues list navigation', 'nhk-event-manager'),
        ];
    }
    
    /**
     * Additional initialization
     * 
     * Demonstrates adding custom WordPress hooks for venue-specific functionality.
     * 
     * @return void
     */
    public function init(): void {
        // Call parent initialization
        parent::init();
        
        // Add venue meta fields
        add_action('nhk_event_venue_add_form_fields', [$this, 'add_venue_meta_fields']);
        add_action('nhk_event_venue_edit_form_fields', [$this, 'edit_venue_meta_fields']);
        
        // Save venue meta
        add_action('created_nhk_event_venue', [$this, 'save_venue_meta']);
        add_action('edited_nhk_event_venue', [$this, 'save_venue_meta']);
        
        // Add venue columns to admin list
        add_filter('manage_edit-nhk_event_venue_columns', [$this, 'add_venue_columns']);
        add_filter('manage_nhk_event_venue_custom_column', [$this, 'render_venue_columns'], 10, 3);
    }
    
    /**
     * Add meta fields to venue add form
     * 
     * Demonstrates venue-specific meta fields for location data.
     * 
     * @return void
     */
    public function add_venue_meta_fields(): void {
        wp_nonce_field('nhk_event_venue_meta', 'nhk_event_venue_meta_nonce');
        ?>
        <div class="form-field">
            <label for="venue_address"><?php esc_html_e('Address', 'nhk-event-manager'); ?></label>
            <textarea name="venue_address" id="venue_address" rows="3" cols="50"></textarea>
            <p><?php esc_html_e('Full address of the venue.', 'nhk-event-manager'); ?></p>
        </div>
        
        <div class="form-field">
            <label for="venue_city"><?php esc_html_e('City', 'nhk-event-manager'); ?></label>
            <input type="text" name="venue_city" id="venue_city" value="" />
        </div>
        
        <div class="form-field">
            <label for="venue_state"><?php esc_html_e('State/Province', 'nhk-event-manager'); ?></label>
            <input type="text" name="venue_state" id="venue_state" value="" />
        </div>
        
        <div class="form-field">
            <label for="venue_country"><?php esc_html_e('Country', 'nhk-event-manager'); ?></label>
            <input type="text" name="venue_country" id="venue_country" value="" />
        </div>
        
        <div class="form-field">
            <label for="venue_postal_code"><?php esc_html_e('Postal Code', 'nhk-event-manager'); ?></label>
            <input type="text" name="venue_postal_code" id="venue_postal_code" value="" />
        </div>
        
        <div class="form-field">
            <label for="venue_phone"><?php esc_html_e('Phone', 'nhk-event-manager'); ?></label>
            <input type="tel" name="venue_phone" id="venue_phone" value="" />
        </div>
        
        <div class="form-field">
            <label for="venue_website"><?php esc_html_e('Website', 'nhk-event-manager'); ?></label>
            <input type="url" name="venue_website" id="venue_website" value="" placeholder="https://" />
        </div>
        
        <div class="form-field">
            <label for="venue_capacity"><?php esc_html_e('Capacity', 'nhk-event-manager'); ?></label>
            <input type="number" name="venue_capacity" id="venue_capacity" value="" min="1" />
            <p><?php esc_html_e('Maximum capacity of the venue (optional).', 'nhk-event-manager'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Add meta fields to venue edit form
     * 
     * @param \WP_Term $term Current term object
     * @return void
     */
    public function edit_venue_meta_fields(\WP_Term $term): void {
        $address = $this->get_term_meta($term->term_id, 'venue_address') ?: '';
        $city = $this->get_term_meta($term->term_id, 'venue_city') ?: '';
        $state = $this->get_term_meta($term->term_id, 'venue_state') ?: '';
        $country = $this->get_term_meta($term->term_id, 'venue_country') ?: '';
        $postal_code = $this->get_term_meta($term->term_id, 'venue_postal_code') ?: '';
        $phone = $this->get_term_meta($term->term_id, 'venue_phone') ?: '';
        $website = $this->get_term_meta($term->term_id, 'venue_website') ?: '';
        $capacity = $this->get_term_meta($term->term_id, 'venue_capacity') ?: '';
        
        wp_nonce_field('nhk_event_venue_meta', 'nhk_event_venue_meta_nonce');
        ?>
        <tr class="form-field">
            <th scope="row">
                <label for="venue_address"><?php esc_html_e('Address', 'nhk-event-manager'); ?></label>
            </th>
            <td>
                <textarea name="venue_address" id="venue_address" rows="3" cols="50"><?php echo esc_textarea($address); ?></textarea>
                <p class="description"><?php esc_html_e('Full address of the venue.', 'nhk-event-manager'); ?></p>
            </td>
        </tr>
        
        <tr class="form-field">
            <th scope="row">
                <label for="venue_city"><?php esc_html_e('City', 'nhk-event-manager'); ?></label>
            </th>
            <td>
                <input type="text" name="venue_city" id="venue_city" value="<?php echo esc_attr($city); ?>" />
            </td>
        </tr>
        
        <tr class="form-field">
            <th scope="row">
                <label for="venue_state"><?php esc_html_e('State/Province', 'nhk-event-manager'); ?></label>
            </th>
            <td>
                <input type="text" name="venue_state" id="venue_state" value="<?php echo esc_attr($state); ?>" />
            </td>
        </tr>
        
        <tr class="form-field">
            <th scope="row">
                <label for="venue_country"><?php esc_html_e('Country', 'nhk-event-manager'); ?></label>
            </th>
            <td>
                <input type="text" name="venue_country" id="venue_country" value="<?php echo esc_attr($country); ?>" />
            </td>
        </tr>
        
        <tr class="form-field">
            <th scope="row">
                <label for="venue_postal_code"><?php esc_html_e('Postal Code', 'nhk-event-manager'); ?></label>
            </th>
            <td>
                <input type="text" name="venue_postal_code" id="venue_postal_code" value="<?php echo esc_attr($postal_code); ?>" />
            </td>
        </tr>
        
        <tr class="form-field">
            <th scope="row">
                <label for="venue_phone"><?php esc_html_e('Phone', 'nhk-event-manager'); ?></label>
            </th>
            <td>
                <input type="tel" name="venue_phone" id="venue_phone" value="<?php echo esc_attr($phone); ?>" />
            </td>
        </tr>
        
        <tr class="form-field">
            <th scope="row">
                <label for="venue_website"><?php esc_html_e('Website', 'nhk-event-manager'); ?></label>
            </th>
            <td>
                <input type="url" name="venue_website" id="venue_website" value="<?php echo esc_attr($website); ?>" placeholder="https://" />
            </td>
        </tr>
        
        <tr class="form-field">
            <th scope="row">
                <label for="venue_capacity"><?php esc_html_e('Capacity', 'nhk-event-manager'); ?></label>
            </th>
            <td>
                <input type="number" name="venue_capacity" id="venue_capacity" value="<?php echo esc_attr($capacity); ?>" min="1" />
                <p class="description"><?php esc_html_e('Maximum capacity of the venue (optional).', 'nhk-event-manager'); ?></p>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Save venue meta data
     * 
     * Demonstrates proper meta data saving with security checks and sanitization.
     * 
     * @param int $term_id Term ID
     * @return void
     */
    public function save_venue_meta(int $term_id): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nhk_event_venue_meta_nonce'] ?? '', 'nhk_event_venue_meta')) {
            return;
        }
        
        // Check user capabilities
        if (!current_user_can('manage_categories')) {
            return;
        }
        
        // Save venue meta fields with proper sanitization
        $meta_fields = [
            'venue_address' => 'sanitize_textarea_field',
            'venue_city' => 'sanitize_text_field',
            'venue_state' => 'sanitize_text_field',
            'venue_country' => 'sanitize_text_field',
            'venue_postal_code' => 'sanitize_text_field',
            'venue_phone' => 'sanitize_text_field',
            'venue_website' => 'esc_url_raw',
            'venue_capacity' => 'absint',
        ];
        
        foreach ($meta_fields as $field => $sanitize_function) {
            if (isset($_POST[$field])) {
                $value = call_user_func($sanitize_function, $_POST[$field]);
                if ($value || $field === 'venue_capacity') {
                    $this->update_term_meta($term_id, $field, $value);
                }
            }
        }
    }
    
    /**
     * Add custom columns to venue admin list
     * 
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_venue_columns(array $columns): array {
        // Insert new columns before posts count
        $posts_column = $columns['posts'] ?? null;
        unset($columns['posts']);
        
        $columns['venue_city'] = __('City', 'nhk-event-manager');
        $columns['venue_capacity'] = __('Capacity', 'nhk-event-manager');
        
        if ($posts_column) {
            $columns['posts'] = $posts_column;
        }
        
        return $columns;
    }
    
    /**
     * Render custom venue columns
     * 
     * @param string $content Column content
     * @param string $column_name Column name
     * @param int $term_id Term ID
     * @return string Modified content
     */
    public function render_venue_columns(string $content, string $column_name, int $term_id): string {
        switch ($column_name) {
            case 'venue_city':
                $city = $this->get_term_meta($term_id, 'venue_city');
                $state = $this->get_term_meta($term_id, 'venue_state');
                
                if ($city) {
                    $content = esc_html($city);
                    if ($state) {
                        $content .= ', ' . esc_html($state);
                    }
                } else {
                    $content = '<span class="na">' . esc_html__('Not set', 'nhk-event-manager') . '</span>';
                }
                break;
                
            case 'venue_capacity':
                $capacity = $this->get_term_meta($term_id, 'venue_capacity');
                if ($capacity) {
                    $content = esc_html(number_format_i18n($capacity));
                } else {
                    $content = '<span class="na">' . esc_html__('Not set', 'nhk-event-manager') . '</span>';
                }
                break;
        }
        
        return $content;
    }
    
    /**
     * Get venue full address
     * 
     * @param int $term_id Term ID
     * @return string Formatted address
     */
    public function get_venue_full_address(int $term_id): string {
        $address_parts = [];
        
        $address = $this->get_term_meta($term_id, 'venue_address');
        if ($address) {
            $address_parts[] = $address;
        }
        
        $city = $this->get_term_meta($term_id, 'venue_city');
        $state = $this->get_term_meta($term_id, 'venue_state');
        $postal_code = $this->get_term_meta($term_id, 'venue_postal_code');
        
        $city_line = [];
        if ($city) $city_line[] = $city;
        if ($state) $city_line[] = $state;
        if ($postal_code) $city_line[] = $postal_code;
        
        if (!empty($city_line)) {
            $address_parts[] = implode(', ', $city_line);
        }
        
        $country = $this->get_term_meta($term_id, 'venue_country');
        if ($country) {
            $address_parts[] = $country;
        }
        
        return implode("\n", $address_parts);
    }
}
