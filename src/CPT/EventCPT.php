<?php
/**
 * Event Custom Post Type
 * 
 * Demonstrates CPT implementation using NHK Framework Abstract_CPT.
 * Shows proper use of WordPress register_post_type() function through framework.
 * 
 * @package NHK\EventManager\CPT
 * @since 1.0.0
 */

namespace NHK\EventManager\CPT;

use NHK\Framework\Abstracts\Abstract_CPT;
use NHK\EventManager\Admin\EventMetaBoxes;

/**
 * Event Custom Post Type Class
 * 
 * Demonstrates:
 * - Extending framework Abstract_CPT
 * - WordPress CPT registration
 * - Meta box integration
 * - Admin column customization
 * - Proper use of WordPress functions
 */
class EventCPT extends Abstract_CPT {
    
    /**
     * Get the CPT slug
     * 
     * @return string
     */
    protected function get_slug(): string {
        return 'nhk_event';
    }
    
    /**
     * Get the CPT labels
     * 
     * Uses WordPress translation functions for internationalization.
     * 
     * @return array
     */
    protected function get_labels(): array {
        return [
            'name'                  => _x('Events', 'Post type general name', 'nhk-event-manager'),
            'singular_name'         => _x('Event', 'Post type singular name', 'nhk-event-manager'),
            'menu_name'             => _x('Events', 'Admin Menu text', 'nhk-event-manager'),
            'name_admin_bar'        => _x('Event', 'Add New on Toolbar', 'nhk-event-manager'),
            'add_new'               => __('Add New', 'nhk-event-manager'),
            'add_new_item'          => __('Add New Event', 'nhk-event-manager'),
            'new_item'              => __('New Event', 'nhk-event-manager'),
            'edit_item'             => __('Edit Event', 'nhk-event-manager'),
            'view_item'             => __('View Event', 'nhk-event-manager'),
            'all_items'             => __('All Events', 'nhk-event-manager'),
            'search_items'          => __('Search Events', 'nhk-event-manager'),
            'parent_item_colon'     => __('Parent Events:', 'nhk-event-manager'),
            'not_found'             => __('No events found.', 'nhk-event-manager'),
            'not_found_in_trash'    => __('No events found in Trash.', 'nhk-event-manager'),
            'featured_image'        => _x('Event Featured Image', 'Overrides the "Featured Image" phrase', 'nhk-event-manager'),
            'set_featured_image'    => _x('Set event image', 'Overrides the "Set featured image" phrase', 'nhk-event-manager'),
            'remove_featured_image' => _x('Remove event image', 'Overrides the "Remove featured image" phrase', 'nhk-event-manager'),
            'use_featured_image'    => _x('Use as event image', 'Overrides the "Use as featured image" phrase', 'nhk-event-manager'),
            'archives'              => _x('Event archives', 'The post type archive label', 'nhk-event-manager'),
            'insert_into_item'      => _x('Insert into event', 'Overrides the "Insert into post" phrase', 'nhk-event-manager'),
            'uploaded_to_this_item' => _x('Uploaded to this event', 'Overrides the "Uploaded to this post" phrase', 'nhk-event-manager'),
            'filter_items_list'     => _x('Filter events list', 'Screen reader text for the filter links', 'nhk-event-manager'),
            'items_list_navigation' => _x('Events list navigation', 'Screen reader text for the pagination', 'nhk-event-manager'),
            'items_list'            => _x('Events list', 'Screen reader text for the items list', 'nhk-event-manager'),
        ];
    }
    
    /**
     * Get the CPT arguments
     * 
     * Configures the post type using WordPress register_post_type() arguments.
     * 
     * @return array
     */
    protected function get_args(): array {
        return [
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'events'],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 25,
            'menu_icon'          => 'dashicons-calendar-alt',
            'supports'           => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
            'show_in_rest'       => true,
            'rest_base'          => 'events',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
        ];
    }
    
    /**
     * Register meta boxes for this CPT
     * 
     * Demonstrates service container usage to get EventMetaBoxes service.
     * Uses WordPress add_meta_box() function through the framework.
     * 
     * @return void
     */
    protected function register_meta_boxes(): void {
        // Get the EventMetaBoxes service from container
        $meta_boxes = $this->container->get(EventMetaBoxes::class);
        $meta_boxes->register();
    }
    
    /**
     * Register custom admin columns
     * 
     * Demonstrates admin column customization using WordPress filters.
     * 
     * @return array
     */
    protected function register_columns(): array {
        return [
            'event_date'     => __('Event Date', 'nhk-event-manager'),
            'event_venue'    => __('Venue', 'nhk-event-manager'),
            'event_capacity' => __('Capacity', 'nhk-event-manager'),
            'event_status'   => __('Status', 'nhk-event-manager'),
        ];
    }
    
    /**
     * Render custom admin column content
     * 
     * Demonstrates proper use of WordPress get_post_meta() function.
     * Shows data sanitization and escaping for security.
     * 
     * @param string $column Column name
     * @param int $post_id Post ID
     * @return void
     */
    protected function render_column(string $column, int $post_id): void {
        switch ($column) {
            case 'event_date':
                $start_date = $this->get_meta($post_id, 'event_start_date');
                $end_date = $this->get_meta($post_id, 'event_end_date');
                
                if ($start_date) {
                    $start_formatted = date_i18n(get_option('date_format'), strtotime($start_date));
                    echo esc_html($start_formatted);
                    
                    if ($end_date && $end_date !== $start_date) {
                        $end_formatted = date_i18n(get_option('date_format'), strtotime($end_date));
                        echo '<br><small>' . esc_html(sprintf(__('to %s', 'nhk-event-manager'), $end_formatted)) . '</small>';
                    }
                } else {
                    echo '<span class="na">' . esc_html__('Not set', 'nhk-event-manager') . '</span>';
                }
                break;
                
            case 'event_venue':
                $venue = $this->get_meta($post_id, 'event_venue');
                if ($venue) {
                    echo esc_html($venue);
                } else {
                    echo '<span class="na">' . esc_html__('Not set', 'nhk-event-manager') . '</span>';
                }
                break;
                
            case 'event_capacity':
                $capacity = $this->get_meta($post_id, 'event_capacity');
                if ($capacity) {
                    echo esc_html(number_format_i18n($capacity));
                } else {
                    echo '<span class="na">' . esc_html__('Unlimited', 'nhk-event-manager') . '</span>';
                }
                break;
                
            case 'event_status':
                $start_date = $this->get_meta($post_id, 'event_start_date');
                if ($start_date) {
                    $now = current_time('timestamp');
                    $event_time = strtotime($start_date);
                    
                    if ($event_time > $now) {
                        echo '<span class="event-status upcoming">' . esc_html__('Upcoming', 'nhk-event-manager') . '</span>';
                    } elseif ($event_time < $now) {
                        $end_date = $this->get_meta($post_id, 'event_end_date');
                        $end_time = $end_date ? strtotime($end_date) : $event_time;
                        
                        if ($end_time > $now) {
                            echo '<span class="event-status ongoing">' . esc_html__('Ongoing', 'nhk-event-manager') . '</span>';
                        } else {
                            echo '<span class="event-status past">' . esc_html__('Past', 'nhk-event-manager') . '</span>';
                        }
                    } else {
                        echo '<span class="event-status today">' . esc_html__('Today', 'nhk-event-manager') . '</span>';
                    }
                } else {
                    echo '<span class="na">' . esc_html__('Unknown', 'nhk-event-manager') . '</span>';
                }
                break;
        }
    }
    
    /**
     * Additional initialization
     * 
     * Demonstrates adding custom WordPress hooks for CPT-specific functionality.
     * 
     * @return void
     */
    public function init(): void {
        // Call parent initialization
        parent::init();
        
        // Add custom hooks for event-specific functionality
        add_action('save_post_nhk_event', [$this, 'save_event_meta'], 10, 2);
        add_filter('the_content', [$this, 'add_event_details_to_content']);
        add_action('pre_get_posts', [$this, 'modify_event_queries']);
    }
    
    /**
     * Save event meta data
     * 
     * Demonstrates proper meta data saving with security checks.
     * Uses WordPress nonce verification and capability checks.
     * 
     * @param int $post_id Post ID
     * @param \WP_Post $post Post object
     * @return void
     */
    public function save_event_meta(int $post_id, \WP_Post $post): void {
        // Skip autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check user capabilities
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Verify nonce (this would be set by the meta box)
        if (!wp_verify_nonce($_POST['nhk_event_meta_nonce'] ?? '', 'nhk_event_meta')) {
            return;
        }
        
        // Save meta fields (sanitization handled by meta box class)
        $meta_fields = [
            'event_start_date',
            'event_end_date',
            'event_venue',
            'event_address',
            'event_capacity',
            'event_registration_url',
            'event_cost',
            'event_organizer',
            'event_organizer_email',
        ];
        
        foreach ($meta_fields as $field) {
            if (isset($_POST[$field])) {
                $value = sanitize_text_field($_POST[$field]);
                $this->update_meta($post_id, $field, $value);
            }
        }
    }
    
    /**
     * Add event details to content on single event pages
     * 
     * Demonstrates content filtering using WordPress the_content filter.
     * 
     * @param string $content Post content
     * @return string Modified content
     */
    public function add_event_details_to_content(string $content): string {
        if (is_singular('nhk_event') && in_the_loop() && is_main_query()) {
            $event_details = $this->get_event_details_html(get_the_ID());
            $content = $event_details . $content;
        }
        
        return $content;
    }
    
    /**
     * Modify event queries
     * 
     * Demonstrates query modification using WordPress pre_get_posts action.
     * 
     * @param \WP_Query $query Query object
     * @return void
     */
    public function modify_event_queries(\WP_Query $query): void {
        if (!is_admin() && $query->is_main_query()) {
            if (is_post_type_archive('nhk_event')) {
                // Order events by start date
                $query->set('meta_key', 'event_start_date');
                $query->set('orderby', 'meta_value');
                $query->set('order', 'ASC');
                
                // Only show upcoming events by default
                $query->set('meta_query', [[
                    'key' => 'event_start_date',
                    'value' => current_time('Y-m-d'),
                    'compare' => '>=',
                    'type' => 'DATE'
                ]]);
            }
        }
    }
    
    /**
     * Get event details HTML
     * 
     * @param int $post_id Post ID
     * @return string HTML content
     */
    protected function get_event_details_html(int $post_id): string {
        $start_date = $this->get_meta($post_id, 'event_start_date');
        $end_date = $this->get_meta($post_id, 'event_end_date');
        $venue = $this->get_meta($post_id, 'event_venue');
        $address = $this->get_meta($post_id, 'event_address');
        $cost = $this->get_meta($post_id, 'event_cost');
        
        ob_start();
        ?>
        <div class="event-details">
            <?php if ($start_date): ?>
                <div class="event-date">
                    <strong><?php esc_html_e('Date:', 'nhk-event-manager'); ?></strong>
                    <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($start_date))); ?>
                    <?php if ($end_date && $end_date !== $start_date): ?>
                        - <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($end_date))); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($venue): ?>
                <div class="event-venue">
                    <strong><?php esc_html_e('Venue:', 'nhk-event-manager'); ?></strong>
                    <?php echo esc_html($venue); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($address): ?>
                <div class="event-address">
                    <strong><?php esc_html_e('Address:', 'nhk-event-manager'); ?></strong>
                    <?php echo esc_html($address); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($cost): ?>
                <div class="event-cost">
                    <strong><?php esc_html_e('Cost:', 'nhk-event-manager'); ?></strong>
                    <?php echo esc_html($cost); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
