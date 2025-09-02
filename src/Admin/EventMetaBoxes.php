<?php
/**
 * Event Meta Boxes
 * 
 * Demonstrates meta box implementation using WordPress add_meta_box() function.
 * Shows proper security, sanitization, and data handling.
 * 
 * @package NHK\EventManager\Admin
 * @since 1.0.0
 */

namespace NHK\EventManager\Admin;

use NHK\Framework\Container\Container;

/**
 * Event Meta Boxes Class
 * 
 * Demonstrates:
 * - WordPress meta box registration
 * - Proper nonce usage for security
 * - Data sanitization and validation
 * - Meta field rendering and saving
 */
class EventMetaBoxes {
    
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
     * Initialize meta boxes
     * 
     * @return void
     */
    public function init(): void {
        add_action('add_meta_boxes', [$this, 'register']);
        add_action('save_post_nhk_event', [$this, 'save'], 10, 2);
    }
    
    /**
     * Register meta boxes
     * 
     * Uses WordPress add_meta_box() function to register meta boxes.
     * 
     * @return void
     */
    public function register(): void {
        // Event Details meta box
        add_meta_box(
            'nhk_event_details',
            __('Event Details', 'nhk-event-manager'),
            [$this, 'render_details_meta_box'],
            'nhk_event',
            'normal',
            'high'
        );
        
        // Event Location meta box
        add_meta_box(
            'nhk_event_location',
            __('Event Location', 'nhk-event-manager'),
            [$this, 'render_location_meta_box'],
            'nhk_event',
            'normal',
            'high'
        );
        
        // Event Registration meta box
        add_meta_box(
            'nhk_event_registration',
            __('Registration & Pricing', 'nhk-event-manager'),
            [$this, 'render_registration_meta_box'],
            'nhk_event',
            'side',
            'default'
        );
        
        // Event Organizer meta box
        add_meta_box(
            'nhk_event_organizer',
            __('Event Organizer', 'nhk-event-manager'),
            [$this, 'render_organizer_meta_box'],
            'nhk_event',
            'side',
            'default'
        );
    }
    
    /**
     * Render event details meta box
     * 
     * Demonstrates proper form field rendering with nonce security.
     * 
     * @param \WP_Post $post Current post object
     * @return void
     */
    public function render_details_meta_box(\WP_Post $post): void {
        // Add nonce for security
        wp_nonce_field('nhk_event_meta', 'nhk_event_meta_nonce');
        
        // Get current values
        $start_date = get_post_meta($post->ID, 'event_start_date', true);
        $start_time = get_post_meta($post->ID, 'event_start_time', true);
        $end_date = get_post_meta($post->ID, 'event_end_date', true);
        $end_time = get_post_meta($post->ID, 'event_end_time', true);
        $all_day = get_post_meta($post->ID, 'event_all_day', true);
        $is_recurring = get_post_meta($post->ID, 'event_is_recurring', true);
        $recurrence_pattern = get_post_meta($post->ID, 'event_recurrence_pattern', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="event_start_date"><?php esc_html_e('Start Date', 'nhk-event-manager'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="date" 
                           id="event_start_date" 
                           name="event_start_date" 
                           value="<?php echo esc_attr($start_date); ?>" 
                           required />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="event_start_time"><?php esc_html_e('Start Time', 'nhk-event-manager'); ?></label>
                </th>
                <td>
                    <input type="time" 
                           id="event_start_time" 
                           name="event_start_time" 
                           value="<?php echo esc_attr($start_time); ?>" />
                    <label>
                        <input type="checkbox" 
                               id="event_all_day" 
                               name="event_all_day" 
                               value="1" 
                               <?php checked($all_day, '1'); ?> />
                        <?php esc_html_e('All Day Event', 'nhk-event-manager'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="event_end_date"><?php esc_html_e('End Date', 'nhk-event-manager'); ?></label>
                </th>
                <td>
                    <input type="date" 
                           id="event_end_date" 
                           name="event_end_date" 
                           value="<?php echo esc_attr($end_date); ?>" />
                    <p class="description"><?php esc_html_e('Leave blank if same as start date.', 'nhk-event-manager'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="event_end_time"><?php esc_html_e('End Time', 'nhk-event-manager'); ?></label>
                </th>
                <td>
                    <input type="time" 
                           id="event_end_time" 
                           name="event_end_time" 
                           value="<?php echo esc_attr($end_time); ?>" />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <?php esc_html_e('Recurring Event', 'nhk-event-manager'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               id="event_is_recurring" 
                               name="event_is_recurring" 
                               value="1" 
                               <?php checked($is_recurring, '1'); ?> />
                        <?php esc_html_e('This is a recurring event', 'nhk-event-manager'); ?>
                    </label>
                    
                    <div id="recurrence_options" style="margin-top: 10px; <?php echo $is_recurring ? '' : 'display: none;'; ?>">
                        <select name="event_recurrence_pattern" id="event_recurrence_pattern">
                            <option value=""><?php esc_html_e('Select pattern', 'nhk-event-manager'); ?></option>
                            <option value="daily" <?php selected($recurrence_pattern, 'daily'); ?>><?php esc_html_e('Daily', 'nhk-event-manager'); ?></option>
                            <option value="weekly" <?php selected($recurrence_pattern, 'weekly'); ?>><?php esc_html_e('Weekly', 'nhk-event-manager'); ?></option>
                            <option value="monthly" <?php selected($recurrence_pattern, 'monthly'); ?>><?php esc_html_e('Monthly', 'nhk-event-manager'); ?></option>
                            <option value="yearly" <?php selected($recurrence_pattern, 'yearly'); ?>><?php esc_html_e('Yearly', 'nhk-event-manager'); ?></option>
                        </select>
                    </div>
                </td>
            </tr>
        </table>
        
        <script>
        jQuery(document).ready(function($) {
            $('#event_is_recurring').change(function() {
                if ($(this).is(':checked')) {
                    $('#recurrence_options').show();
                } else {
                    $('#recurrence_options').hide();
                }
            });
            
            $('#event_all_day').change(function() {
                if ($(this).is(':checked')) {
                    $('#event_start_time, #event_end_time').prop('disabled', true);
                } else {
                    $('#event_start_time, #event_end_time').prop('disabled', false);
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render event location meta box
     * 
     * @param \WP_Post $post Current post object
     * @return void
     */
    public function render_location_meta_box(\WP_Post $post): void {
        $venue = get_post_meta($post->ID, 'event_venue', true);
        $address = get_post_meta($post->ID, 'event_address', true);
        $city = get_post_meta($post->ID, 'event_city', true);
        $state = get_post_meta($post->ID, 'event_state', true);
        $country = get_post_meta($post->ID, 'event_country', true);
        $postal_code = get_post_meta($post->ID, 'event_postal_code', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="event_venue"><?php esc_html_e('Venue Name', 'nhk-event-manager'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="event_venue" 
                           name="event_venue" 
                           value="<?php echo esc_attr($venue); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="event_address"><?php esc_html_e('Street Address', 'nhk-event-manager'); ?></label>
                </th>
                <td>
                    <textarea id="event_address" 
                              name="event_address" 
                              rows="3" 
                              class="large-text"><?php echo esc_textarea($address); ?></textarea>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="event_city"><?php esc_html_e('City', 'nhk-event-manager'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="event_city" 
                           name="event_city" 
                           value="<?php echo esc_attr($city); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="event_state"><?php esc_html_e('State/Province', 'nhk-event-manager'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="event_state" 
                           name="event_state" 
                           value="<?php echo esc_attr($state); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="event_postal_code"><?php esc_html_e('Postal Code', 'nhk-event-manager'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="event_postal_code" 
                           name="event_postal_code" 
                           value="<?php echo esc_attr($postal_code); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="event_country"><?php esc_html_e('Country', 'nhk-event-manager'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="event_country" 
                           name="event_country" 
                           value="<?php echo esc_attr($country); ?>" 
                           class="regular-text" />
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render registration meta box
     * 
     * @param \WP_Post $post Current post object
     * @return void
     */
    public function render_registration_meta_box(\WP_Post $post): void {
        $capacity = get_post_meta($post->ID, 'event_capacity', true);
        $registration_url = get_post_meta($post->ID, 'event_registration_url', true);
        $cost = get_post_meta($post->ID, 'event_cost', true);
        $registration_required = get_post_meta($post->ID, 'event_registration_required', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="event_capacity"><?php esc_html_e('Capacity', 'nhk-event-manager'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="event_capacity" 
                           name="event_capacity" 
                           value="<?php echo esc_attr($capacity); ?>" 
                           min="1" 
                           class="small-text" />
                    <p class="description"><?php esc_html_e('Maximum number of attendees. Leave blank for unlimited.', 'nhk-event-manager'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="event_cost"><?php esc_html_e('Cost', 'nhk-event-manager'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="event_cost" 
                           name="event_cost" 
                           value="<?php echo esc_attr($cost); ?>" 
                           class="regular-text" 
                           placeholder="<?php esc_attr_e('e.g., Free, $25, $10-$50', 'nhk-event-manager'); ?>" />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <?php esc_html_e('Registration', 'nhk-event-manager'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               id="event_registration_required" 
                               name="event_registration_required" 
                               value="1" 
                               <?php checked($registration_required, '1'); ?> />
                        <?php esc_html_e('Registration required', 'nhk-event-manager'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="event_registration_url"><?php esc_html_e('Registration URL', 'nhk-event-manager'); ?></label>
                </th>
                <td>
                    <input type="url" 
                           id="event_registration_url" 
                           name="event_registration_url" 
                           value="<?php echo esc_attr($registration_url); ?>" 
                           class="regular-text" 
                           placeholder="https://" />
                    <p class="description"><?php esc_html_e('External registration page URL.', 'nhk-event-manager'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render organizer meta box
     * 
     * @param \WP_Post $post Current post object
     * @return void
     */
    public function render_organizer_meta_box(\WP_Post $post): void {
        $organizer = get_post_meta($post->ID, 'event_organizer', true);
        $organizer_email = get_post_meta($post->ID, 'event_organizer_email', true);
        $organizer_phone = get_post_meta($post->ID, 'event_organizer_phone', true);
        $organizer_website = get_post_meta($post->ID, 'event_organizer_website', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="event_organizer"><?php esc_html_e('Organizer Name', 'nhk-event-manager'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="event_organizer" 
                           name="event_organizer" 
                           value="<?php echo esc_attr($organizer); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="event_organizer_email"><?php esc_html_e('Email', 'nhk-event-manager'); ?></label>
                </th>
                <td>
                    <input type="email" 
                           id="event_organizer_email" 
                           name="event_organizer_email" 
                           value="<?php echo esc_attr($organizer_email); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="event_organizer_phone"><?php esc_html_e('Phone', 'nhk-event-manager'); ?></label>
                </th>
                <td>
                    <input type="tel" 
                           id="event_organizer_phone" 
                           name="event_organizer_phone" 
                           value="<?php echo esc_attr($organizer_phone); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="event_organizer_website"><?php esc_html_e('Website', 'nhk-event-manager'); ?></label>
                </th>
                <td>
                    <input type="url" 
                           id="event_organizer_website" 
                           name="event_organizer_website" 
                           value="<?php echo esc_attr($organizer_website); ?>" 
                           class="regular-text" 
                           placeholder="https://" />
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Save meta box data
     * 
     * Demonstrates proper meta data saving with security checks.
     * Uses WordPress nonce verification, capability checks, and sanitization.
     * 
     * @param int $post_id Post ID
     * @param \WP_Post $post Post object
     * @return void
     */
    public function save(int $post_id, \WP_Post $post): void {
        // Skip autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check user capabilities
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nhk_event_meta_nonce'] ?? '', 'nhk_event_meta')) {
            return;
        }
        
        // Define meta fields with their sanitization functions
        $meta_fields = [
            // Date/Time fields
            'event_start_date' => 'sanitize_text_field',
            'event_start_time' => 'sanitize_text_field',
            'event_end_date' => 'sanitize_text_field',
            'event_end_time' => 'sanitize_text_field',
            'event_all_day' => 'absint',
            'event_is_recurring' => 'absint',
            'event_recurrence_pattern' => 'sanitize_text_field',
            
            // Location fields
            'event_venue' => 'sanitize_text_field',
            'event_address' => 'sanitize_textarea_field',
            'event_city' => 'sanitize_text_field',
            'event_state' => 'sanitize_text_field',
            'event_country' => 'sanitize_text_field',
            'event_postal_code' => 'sanitize_text_field',
            
            // Registration fields
            'event_capacity' => 'absint',
            'event_registration_url' => 'esc_url_raw',
            'event_cost' => 'sanitize_text_field',
            'event_registration_required' => 'absint',
            
            // Organizer fields
            'event_organizer' => 'sanitize_text_field',
            'event_organizer_email' => 'sanitize_email',
            'event_organizer_phone' => 'sanitize_text_field',
            'event_organizer_website' => 'esc_url_raw',
        ];
        
        // Save each field with proper sanitization
        foreach ($meta_fields as $field => $sanitize_function) {
            if (isset($_POST[$field])) {
                $value = call_user_func($sanitize_function, $_POST[$field]);
                
                // Handle special cases
                if (in_array($field, ['event_all_day', 'event_is_recurring', 'event_registration_required'])) {
                    $value = $value ? '1' : '';
                }
                
                // Save or delete meta
                if ($value !== '') {
                    update_post_meta($post_id, $field, $value);
                } else {
                    delete_post_meta($post_id, $field);
                }
            }
        }
        
        // Validate date logic
        $this->validate_event_dates($post_id);
    }
    
    /**
     * Validate event dates
     * 
     * Ensures end date is not before start date.
     * 
     * @param int $post_id Post ID
     * @return void
     */
    protected function validate_event_dates(int $post_id): void {
        $start_date = get_post_meta($post_id, 'event_start_date', true);
        $end_date = get_post_meta($post_id, 'event_end_date', true);
        
        if ($start_date && $end_date) {
            if (strtotime($end_date) < strtotime($start_date)) {
                // End date is before start date, remove it
                delete_post_meta($post_id, 'event_end_date');
                delete_post_meta($post_id, 'event_end_time');
                
                // Add admin notice
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-warning is-dismissible">';
                    echo '<p>' . esc_html__('Event end date cannot be before start date. End date has been cleared.', 'nhk-event-manager') . '</p>';
                    echo '</div>';
                });
            }
        }
    }
}
