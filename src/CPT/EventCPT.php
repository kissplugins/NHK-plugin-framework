<?php
/**
 * Event Custom Post Type
 * 
 * Defines the Event custom post type with all necessary fields and meta boxes.
 * 
 * @package NHK\EventManager\CPT
 * @since 1.0.0
 */

namespace NHK\EventManager\CPT;

use NHK\Framework\Container\Container;

/**
 * Event CPT Class
 * 
 * Handles registration and management of the Event custom post type.
 */
class EventCPT {
    
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
     * @return void
     */
    public function init(): void {
        \add_action('init', [$this, 'register_post_type']);
        \add_action('init', [$this, 'register_taxonomies']);
        \add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        \add_action('save_post', [$this, 'save_meta_boxes']);
    }
    
    /**
     * Register the Event post type
     * 
     * @return void
     */
    public function register_post_type(): void {
        $labels = [
            'name' => \_x('Events', 'Post type general name', 'nhk-event-manager'),
            'singular_name' => \_x('Event', 'Post type singular name', 'nhk-event-manager'),
            'menu_name' => \_x('Events', 'Admin Menu text', 'nhk-event-manager'),
            'name_admin_bar' => \_x('Event', 'Add New on Toolbar', 'nhk-event-manager'),
            'add_new' => \__('Add New', 'nhk-event-manager'),
            'add_new_item' => \__('Add New Event', 'nhk-event-manager'),
            'new_item' => \__('New Event', 'nhk-event-manager'),
            'edit_item' => \__('Edit Event', 'nhk-event-manager'),
            'view_item' => \__('View Event', 'nhk-event-manager'),
            'all_items' => \__('All Events', 'nhk-event-manager'),
            'search_items' => \__('Search Events', 'nhk-event-manager'),
            'parent_item_colon' => \__('Parent Events:', 'nhk-event-manager'),
            'not_found' => \__('No events found.', 'nhk-event-manager'),
            'not_found_in_trash' => \__('No events found in Trash.', 'nhk-event-manager'),
            'featured_image' => \_x('Event Image', 'Overrides the "Featured Image" phrase', 'nhk-event-manager'),
            'set_featured_image' => \_x('Set event image', 'Overrides the "Set featured image" phrase', 'nhk-event-manager'),
            'remove_featured_image' => \_x('Remove event image', 'Overrides the "Remove featured image" phrase', 'nhk-event-manager'),
            'use_featured_image' => \_x('Use as event image', 'Overrides the "Use as featured image" phrase', 'nhk-event-manager'),
            'archives' => \_x('Event archives', 'The post type archive label', 'nhk-event-manager'),
            'insert_into_item' => \_x('Insert into event', 'Overrides the "Insert into post" phrase', 'nhk-event-manager'),
            'uploaded_to_this_item' => \_x('Uploaded to this event', 'Overrides the "Uploaded to this post" phrase', 'nhk-event-manager'),
            'filter_items_list' => \_x('Filter events list', 'Screen reader text for the filter links', 'nhk-event-manager'),
            'items_list_navigation' => \_x('Events list navigation', 'Screen reader text for the pagination', 'nhk-event-manager'),
            'items_list' => \_x('Events list', 'Screen reader text for the items list', 'nhk-event-manager'),
        ];
        
        $args = [
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'events'],
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 25,
            'menu_icon' => 'dashicons-calendar-alt',
            'show_in_rest' => true,
            'rest_base' => 'events',
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
            'taxonomies' => ['nhk_event_category', 'nhk_event_venue'],
        ];
        
        \register_post_type('nhk_event', $args);
    }
    
    /**
     * Register event taxonomies
     * 
     * @return void
     */
    public function register_taxonomies(): void {
        // Event Categories
        $category_labels = [
            'name' => \_x('Event Categories', 'taxonomy general name', 'nhk-event-manager'),
            'singular_name' => \_x('Event Category', 'taxonomy singular name', 'nhk-event-manager'),
            'search_items' => \__('Search Event Categories', 'nhk-event-manager'),
            'all_items' => \__('All Event Categories', 'nhk-event-manager'),
            'parent_item' => \__('Parent Event Category', 'nhk-event-manager'),
            'parent_item_colon' => \__('Parent Event Category:', 'nhk-event-manager'),
            'edit_item' => \__('Edit Event Category', 'nhk-event-manager'),
            'update_item' => \__('Update Event Category', 'nhk-event-manager'),
            'add_new_item' => \__('Add New Event Category', 'nhk-event-manager'),
            'new_item_name' => \__('New Event Category Name', 'nhk-event-manager'),
            'menu_name' => \__('Categories', 'nhk-event-manager'),
        ];
        
        \register_taxonomy('nhk_event_category', ['nhk_event'], [
            'hierarchical' => true,
            'labels' => $category_labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'event-category'],
            'show_in_rest' => true,
        ]);
        
        // Event Venues
        $venue_labels = [
            'name' => \_x('Event Venues', 'taxonomy general name', 'nhk-event-manager'),
            'singular_name' => \_x('Event Venue', 'taxonomy singular name', 'nhk-event-manager'),
            'search_items' => \__('Search Event Venues', 'nhk-event-manager'),
            'all_items' => \__('All Event Venues', 'nhk-event-manager'),
            'edit_item' => \__('Edit Event Venue', 'nhk-event-manager'),
            'update_item' => \__('Update Event Venue', 'nhk-event-manager'),
            'add_new_item' => \__('Add New Event Venue', 'nhk-event-manager'),
            'new_item_name' => \__('New Event Venue Name', 'nhk-event-manager'),
            'menu_name' => \__('Venues', 'nhk-event-manager'),
        ];
        
        \register_taxonomy('nhk_event_venue', ['nhk_event'], [
            'hierarchical' => false,
            'labels' => $venue_labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'event-venue'],
            'show_in_rest' => true,
        ]);
    }
    
    /**
     * Add meta boxes for event data
     * 
     * @return void
     */
    public function add_meta_boxes(): void {
        \add_meta_box(
            'nhk_event_details',
            \__('Event Details', 'nhk-event-manager'),
            [$this, 'render_event_details_meta_box'],
            'nhk_event',
            'normal',
            'high'
        );
        
        \add_meta_box(
            'nhk_event_organizer',
            \__('Event Organizer', 'nhk-event-manager'),
            [$this, 'render_event_organizer_meta_box'],
            'nhk_event',
            'side',
            'default'
        );
    }
    
    /**
     * Render event details meta box
     * 
     * @param \WP_Post $post Current post object
     * @return void
     */
    public function render_event_details_meta_box(\WP_Post $post): void {
        \wp_nonce_field('nhk_event_meta_box', 'nhk_event_meta_box_nonce');
        
        $start_date = \get_post_meta($post->ID, '_nhk_event_start_date', true);
        $end_date = \get_post_meta($post->ID, '_nhk_event_end_date', true);
        $start_time = \get_post_meta($post->ID, '_nhk_event_start_time', true);
        $end_time = \get_post_meta($post->ID, '_nhk_event_end_time', true);
        $venue = \get_post_meta($post->ID, '_nhk_event_venue', true);
        $capacity = \get_post_meta($post->ID, '_nhk_event_capacity', true);
        $price = \get_post_meta($post->ID, '_nhk_event_price', true);
        $registration_url = \get_post_meta($post->ID, '_nhk_event_registration_url', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="nhk_event_start_date"><?php \_e('Start Date', 'nhk-event-manager'); ?></label>
                </th>
                <td>
                    <input type="date" id="nhk_event_start_date" name="nhk_event_start_date" 
                           value="<?php echo \esc_attr($start_date); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="nhk_event_end_date"><?php \_e('End Date', 'nhk-event-manager'); ?></label>
                </th>
                <td>
                    <input type="date" id="nhk_event_end_date" name="nhk_event_end_date" 
                           value="<?php echo \esc_attr($end_date); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="nhk_event_start_time"><?php \_e('Start Time', 'nhk-event-manager'); ?></label>
                </th>
                <td>
                    <input type="time" id="nhk_event_start_time" name="nhk_event_start_time" 
                           value="<?php echo \esc_attr($start_time); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="nhk_event_end_time"><?php \_e('End Time', 'nhk-event-manager'); ?></label>
                </th>
                <td>
                    <input type="time" id="nhk_event_end_time" name="nhk_event_end_time" 
                           value="<?php echo \esc_attr($end_time); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="nhk_event_venue"><?php \_e('Venue', 'nhk-event-manager'); ?></label>
                </th>
                <td>
                    <input type="text" id="nhk_event_venue" name="nhk_event_venue" 
                           value="<?php echo \esc_attr($venue); ?>" class="regular-text" 
                           placeholder="<?php \_e('Event venue or location', 'nhk-event-manager'); ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="nhk_event_capacity"><?php \_e('Capacity', 'nhk-event-manager'); ?></label>
                </th>
                <td>
                    <input type="number" id="nhk_event_capacity" name="nhk_event_capacity" 
                           value="<?php echo \esc_attr($capacity); ?>" class="small-text" min="1" 
                           placeholder="<?php \_e('Maximum attendees', 'nhk-event-manager'); ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="nhk_event_price"><?php \_e('Price', 'nhk-event-manager'); ?></label>
                </th>
                <td>
                    <input type="text" id="nhk_event_price" name="nhk_event_price" 
                           value="<?php echo \esc_attr($price); ?>" class="regular-text" 
                           placeholder="<?php \_e('0.00 for free events', 'nhk-event-manager'); ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="nhk_event_registration_url"><?php \_e('Registration URL', 'nhk-event-manager'); ?></label>
                </th>
                <td>
                    <input type="url" id="nhk_event_registration_url" name="nhk_event_registration_url" 
                           value="<?php echo \esc_attr($registration_url); ?>" class="regular-text" 
                           placeholder="<?php \_e('External registration link (optional)', 'nhk-event-manager'); ?>" />
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render event organizer meta box
     * 
     * @param \WP_Post $post Current post object
     * @return void
     */
    public function render_event_organizer_meta_box(\WP_Post $post): void {
        $organizer_name = \get_post_meta($post->ID, '_nhk_event_organizer_name', true);
        $organizer_email = \get_post_meta($post->ID, '_nhk_event_organizer_email', true);
        
        ?>
        <p>
            <label for="nhk_event_organizer_name"><?php \_e('Organizer Name', 'nhk-event-manager'); ?></label><br>
            <input type="text" id="nhk_event_organizer_name" name="nhk_event_organizer_name" 
                   value="<?php echo \esc_attr($organizer_name); ?>" class="widefat" />
        </p>
        <p>
            <label for="nhk_event_organizer_email"><?php \_e('Organizer Email', 'nhk-event-manager'); ?></label><br>
            <input type="email" id="nhk_event_organizer_email" name="nhk_event_organizer_email" 
                   value="<?php echo \esc_attr($organizer_email); ?>" class="widefat" />
        </p>
        <?php
    }
    
    /**
     * Save meta box data
     * 
     * @param int $post_id Post ID
     * @return void
     */
    public function save_meta_boxes(int $post_id): void {
        // Verify nonce
        if (!\wp_verify_nonce($_POST['nhk_event_meta_box_nonce'] ?? '', 'nhk_event_meta_box')) {
            return;
        }
        
        // Check if user has permission to edit
        if (!\current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Don't save on autosave
        if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Only save for our post type
        if (\get_post_type($post_id) !== 'nhk_event') {
            return;
        }
        
        // Save meta fields
        $meta_fields = [
            '_nhk_event_start_date' => 'nhk_event_start_date',
            '_nhk_event_end_date' => 'nhk_event_end_date',
            '_nhk_event_start_time' => 'nhk_event_start_time',
            '_nhk_event_end_time' => 'nhk_event_end_time',
            '_nhk_event_venue' => 'nhk_event_venue',
            '_nhk_event_capacity' => 'nhk_event_capacity',
            '_nhk_event_price' => 'nhk_event_price',
            '_nhk_event_registration_url' => 'nhk_event_registration_url',
            '_nhk_event_organizer_name' => 'nhk_event_organizer_name',
            '_nhk_event_organizer_email' => 'nhk_event_organizer_email',
        ];
        
        foreach ($meta_fields as $meta_key => $form_field) {
            if (isset($_POST[$form_field])) {
                \update_post_meta($post_id, $meta_key, \sanitize_text_field($_POST[$form_field]));
            }
        }
    }
}
