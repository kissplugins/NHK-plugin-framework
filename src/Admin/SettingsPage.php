<?php
/**
 * Event Manager Settings Page
 * 
 * Demonstrates settings page implementation using NHK Framework Abstract_Settings_Page.
 * Shows proper use of WordPress Settings API with tabbed interface.
 * 
 * @package NHK\EventManager\Admin
 * @since 1.0.0
 */

namespace NHK\EventManager\Admin;

use NHK\Framework\Abstracts\Abstract_Settings_Page;

/**
 * Event Manager Settings Page Class
 * 
 * Demonstrates:
 * - Extending framework Abstract_Settings_Page
 * - WordPress Settings API usage
 * - Multi-tab settings interface
 * - Proper sanitization and validation
 * - Health check integration
 */
class SettingsPage extends Abstract_Settings_Page {
    
    /**
     * Get the page title
     * 
     * @return string
     */
    protected function get_page_title(): string {
        return __('Event Manager Settings', 'nhk-event-manager');
    }
    
    /**
     * Get the menu slug
     * 
     * @return string
     */
    protected function get_menu_slug(): string {
        return 'nhk-event-settings';
    }
    
    /**
     * Get tabs configuration
     * 
     * Demonstrates multi-tab settings interface.
     * 
     * @return array
     */
    protected function get_tabs(): array {
        return [
            'general' => __('General', 'nhk-event-manager'),
            'display' => __('Display', 'nhk-event-manager'),
            'notifications' => __('Notifications', 'nhk-event-manager'),
            'advanced' => __('Advanced', 'nhk-event-manager'),
            'health' => __('Health Check', 'nhk-event-manager'),
        ];
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_scripts(string $hook): void {
        if ($hook !== $this->page_hook) {
            return;
        }

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        wp_enqueue_script(
            'nhk-ajax-handler',
            NHK_EVENT_MANAGER_URL . 'assets/js/nhk-ajax-handler.js',
            [],
            NHK_EVENT_MANAGER_VERSION,
            true
        );

        wp_enqueue_script(
            'nhk-admin-settings',
            $this->get_admin_js_url(),
            ['jquery', 'wp-color-picker', 'nhk-ajax-handler'],
            NHK_EVENT_MANAGER_VERSION,
            true
        );
    }

    /**
     * Get admin JavaScript URL.
     *
     * @return string
     */
    protected function get_admin_js_url(): string {
        return NHK_EVENT_MANAGER_URL . 'assets/js/admin.js';
    }
    
    /**
     * Register setting sections
     * 
     * Uses WordPress add_settings_section() function.
     * 
     * @return void
     */
    protected function register_setting_sections(): void {
        // General tab sections
        add_settings_section(
            'nhk_event_general_defaults',
            __('Default Settings', 'nhk-event-manager'),
            [$this, 'render_general_defaults_section'],
            $this->get_menu_slug() . '_general'
        );
        
        add_settings_section(
            'nhk_event_general_timezone',
            __('Date & Time Settings', 'nhk-event-manager'),
            [$this, 'render_timezone_section'],
            $this->get_menu_slug() . '_general'
        );
        
        // Display tab sections
        add_settings_section(
            'nhk_event_display_archive',
            __('Archive Display', 'nhk-event-manager'),
            [$this, 'render_archive_section'],
            $this->get_menu_slug() . '_display'
        );
        
        add_settings_section(
            'nhk_event_display_single',
            __('Single Event Display', 'nhk-event-manager'),
            [$this, 'render_single_section'],
            $this->get_menu_slug() . '_display'
        );
        
        // Notifications tab sections
        add_settings_section(
            'nhk_event_notifications_email',
            __('Email Notifications', 'nhk-event-manager'),
            [$this, 'render_email_section'],
            $this->get_menu_slug() . '_notifications'
        );
        
        // Advanced tab sections
        add_settings_section(
            'nhk_event_advanced_debug',
            __('Debug Settings', 'nhk-event-manager'),
            [$this, 'render_debug_section'],
            $this->get_menu_slug() . '_advanced'
        );
        
        add_settings_section(
            'nhk_event_advanced_cache',
            __('Cache Settings', 'nhk-event-manager'),
            [$this, 'render_cache_section'],
            $this->get_menu_slug() . '_advanced'
        );
        
        // Health tab sections
        add_settings_section(
            'nhk_event_health_checks',
            __('System Health', 'nhk-event-manager'),
            [$this, 'render_health_section'],
            $this->get_menu_slug() . '_health'
        );
    }
    
    /**
     * Register setting fields
     * 
     * Uses WordPress add_settings_field() and register_setting() functions.
     * 
     * @return void
     */
    protected function register_setting_fields(): void {
        $this->register_general_fields();
        $this->register_display_fields();
        $this->register_notification_fields();
        $this->register_advanced_fields();
    }
    
    /**
     * Register general settings fields
     * 
     * @return void
     */
    protected function register_general_fields(): void {
        // Default event duration
        register_setting('nhk-event-settings_general', 'nhk_event_default_duration', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 2
        ]);
        
        add_settings_field(
            'nhk_event_default_duration',
            __('Default Event Duration', 'nhk-event-manager'),
            [$this, 'render_number_field'],
            $this->get_menu_slug() . '_general',
            'nhk_event_general_defaults',
            [
                'option_name' => 'nhk_event_default_duration',
                'field_name' => 'nhk_event_default_duration',
                'default' => 2,
                'min' => 1,
                'max' => 24,
                'description' => __('Default duration in hours for new events.', 'nhk-event-manager')
            ]
        );
        
        // Default event capacity
        register_setting('nhk-event-settings_general', 'nhk_event_default_capacity', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 100
        ]);
        
        add_settings_field(
            'nhk_event_default_capacity',
            __('Default Event Capacity', 'nhk-event-manager'),
            [$this, 'render_number_field'],
            $this->get_menu_slug() . '_general',
            'nhk_event_general_defaults',
            [
                'option_name' => 'nhk_event_default_capacity',
                'field_name' => 'nhk_event_default_capacity',
                'default' => 100,
                'min' => 1,
                'description' => __('Default capacity for new events (leave blank for unlimited).', 'nhk-event-manager')
            ]
        );
        
        // Currency symbol
        register_setting('nhk-event-settings_general', 'nhk_event_currency_symbol', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '$'
        ]);
        
        add_settings_field(
            'nhk_event_currency_symbol',
            __('Currency Symbol', 'nhk-event-manager'),
            [$this, 'render_text_field'],
            $this->get_menu_slug() . '_general',
            'nhk_event_general_defaults',
            [
                'option_name' => 'nhk_event_currency_symbol',
                'field_name' => 'nhk_event_currency_symbol',
                'default' => '$',
                'class' => 'small-text',
                'description' => __('Currency symbol to display with event costs.', 'nhk-event-manager')
            ]
        );
        
        // Date format
        register_setting('nhk-event-settings_general', 'nhk_event_date_format', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'F j, Y'
        ]);
        
        add_settings_field(
            'nhk_event_date_format',
            __('Date Format', 'nhk-event-manager'),
            [$this, 'render_select_field'],
            $this->get_menu_slug() . '_general',
            'nhk_event_general_timezone',
            [
                'option_name' => 'nhk_event_date_format',
                'field_name' => 'nhk_event_date_format',
                'default' => 'F j, Y',
                'options' => [
                    'F j, Y' => date('F j, Y'),
                    'Y-m-d' => date('Y-m-d'),
                    'm/d/Y' => date('m/d/Y'),
                    'd/m/Y' => date('d/m/Y'),
                    'j F Y' => date('j F Y'),
                ],
                'description' => __('Date format for displaying event dates.', 'nhk-event-manager')
            ]
        );
        
        // Time format
        register_setting('nhk-event-settings_general', 'nhk_event_time_format', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'g:i A'
        ]);
        
        add_settings_field(
            'nhk_event_time_format',
            __('Time Format', 'nhk-event-manager'),
            [$this, 'render_select_field'],
            $this->get_menu_slug() . '_general',
            'nhk_event_general_timezone',
            [
                'option_name' => 'nhk_event_time_format',
                'field_name' => 'nhk_event_time_format',
                'default' => 'g:i A',
                'options' => [
                    'g:i A' => date('g:i A'),
                    'H:i' => date('H:i'),
                    'g:i a' => date('g:i a'),
                ],
                'description' => __('Time format for displaying event times.', 'nhk-event-manager')
            ]
        );
    }
    
    /**
     * Register display settings fields
     * 
     * @return void
     */
    protected function register_display_fields(): void {
        // Events per page
        register_setting('nhk-event-settings_display', 'nhk_event_events_per_page', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 10
        ]);
        
        add_settings_field(
            'nhk_event_events_per_page',
            __('Events Per Page', 'nhk-event-manager'),
            [$this, 'render_number_field'],
            $this->get_menu_slug() . '_display',
            'nhk_event_display_archive',
            [
                'option_name' => 'nhk_event_events_per_page',
                'field_name' => 'nhk_event_events_per_page',
                'default' => 10,
                'min' => 1,
                'max' => 100,
                'description' => __('Number of events to display per page in archives.', 'nhk-event-manager')
            ]
        );
        
        // Show past events
        register_setting('nhk-event-settings_display', 'nhk_event_show_past_events', [
            'type' => 'boolean',
            'sanitize_callback' => [$this, 'sanitize_checkbox'],
            'default' => false
        ]);
        
        add_settings_field(
            'nhk_event_show_past_events',
            __('Show Past Events', 'nhk-event-manager'),
            [$this, 'render_checkbox_field'],
            $this->get_menu_slug() . '_display',
            'nhk_event_display_archive',
            [
                'option_name' => 'nhk_event_show_past_events',
                'field_name' => 'nhk_event_show_past_events',
                'label' => __('Include past events in archive listings', 'nhk-event-manager'),
                'description' => __('When enabled, past events will be shown in event archives.', 'nhk-event-manager')
            ]
        );
        
        // Default sort order
        register_setting('nhk-event-settings_display', 'nhk_event_default_sort', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'date_asc'
        ]);
        
        add_settings_field(
            'nhk_event_default_sort',
            __('Default Sort Order', 'nhk-event-manager'),
            [$this, 'render_select_field'],
            $this->get_menu_slug() . '_display',
            'nhk_event_display_archive',
            [
                'option_name' => 'nhk_event_default_sort',
                'field_name' => 'nhk_event_default_sort',
                'default' => 'date_asc',
                'options' => [
                    'date_asc' => __('Date (Earliest First)', 'nhk-event-manager'),
                    'date_desc' => __('Date (Latest First)', 'nhk-event-manager'),
                    'title_asc' => __('Title (A-Z)', 'nhk-event-manager'),
                    'title_desc' => __('Title (Z-A)', 'nhk-event-manager'),
                ],
                'description' => __('Default sorting for event listings.', 'nhk-event-manager')
            ]
        );
    }

    /**
     * Register notification settings fields
     *
     * @return void
     */
    protected function register_notification_fields(): void {
        // Enable reminder emails
        register_setting('nhk-event-settings_notifications', 'nhk_event_enable_reminders', [
            'type' => 'boolean',
            'sanitize_callback' => [$this, 'sanitize_checkbox'],
            'default' => false
        ]);

        add_settings_field(
            'nhk_event_enable_reminders',
            __('Email Reminders', 'nhk-event-manager'),
            [$this, 'render_checkbox_field'],
            $this->get_menu_slug() . '_notifications',
            'nhk_event_notifications_email',
            [
                'option_name' => 'nhk_event_enable_reminders',
                'field_name' => 'nhk_event_enable_reminders',
                'label' => __('Enable email reminders for events', 'nhk-event-manager'),
                'description' => __('Send reminder emails to registered attendees.', 'nhk-event-manager')
            ]
        );

        // Reminder timing
        register_setting('nhk-event-settings_notifications', 'nhk_event_reminder_timing', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '1_day'
        ]);

        add_settings_field(
            'nhk_event_reminder_timing',
            __('Reminder Timing', 'nhk-event-manager'),
            [$this, 'render_select_field'],
            $this->get_menu_slug() . '_notifications',
            'nhk_event_notifications_email',
            [
                'option_name' => 'nhk_event_reminder_timing',
                'field_name' => 'nhk_event_reminder_timing',
                'default' => '1_day',
                'options' => [
                    '1_hour' => __('1 Hour Before', 'nhk-event-manager'),
                    '1_day' => __('1 Day Before', 'nhk-event-manager'),
                    '3_days' => __('3 Days Before', 'nhk-event-manager'),
                    '1_week' => __('1 Week Before', 'nhk-event-manager'),
                ],
                'description' => __('When to send reminder emails before events.', 'nhk-event-manager')
            ]
        );

        // Admin notification email
        register_setting('nhk-event-settings_notifications', 'nhk_event_admin_email', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_email',
            'default' => get_option('admin_email')
        ]);

        add_settings_field(
            'nhk_event_admin_email',
            __('Admin Email', 'nhk-event-manager'),
            [$this, 'render_text_field'],
            $this->get_menu_slug() . '_notifications',
            'nhk_event_notifications_email',
            [
                'option_name' => 'nhk_event_admin_email',
                'field_name' => 'nhk_event_admin_email',
                'default' => get_option('admin_email'),
                'class' => 'regular-text',
                'description' => __('Email address for admin notifications.', 'nhk-event-manager')
            ]
        );
    }

    /**
     * Register advanced settings fields
     *
     * @return void
     */
    protected function register_advanced_fields(): void {
        // Enable debugging
        register_setting('nhk-event-settings_advanced', 'nhk_event_enable_debug', [
            'type' => 'boolean',
            'sanitize_callback' => [$this, 'sanitize_checkbox'],
            'default' => false
        ]);

        add_settings_field(
            'nhk_event_enable_debug',
            __('Debug Mode', 'nhk-event-manager'),
            [$this, 'render_checkbox_field'],
            $this->get_menu_slug() . '_advanced',
            'nhk_event_advanced_debug',
            [
                'option_name' => 'nhk_event_enable_debug',
                'field_name' => 'nhk_event_enable_debug',
                'label' => __('Enable debug logging', 'nhk-event-manager'),
                'description' => __('Log debug information for troubleshooting.', 'nhk-event-manager')
            ]
        );

        // Debug level
        register_setting('nhk-event-settings_advanced', 'nhk_event_debug_level', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'error'
        ]);

        add_settings_field(
            'nhk_event_debug_level',
            __('Debug Level', 'nhk-event-manager'),
            [$this, 'render_select_field'],
            $this->get_menu_slug() . '_advanced',
            'nhk_event_advanced_debug',
            [
                'option_name' => 'nhk_event_debug_level',
                'field_name' => 'nhk_event_debug_level',
                'default' => 'error',
                'options' => [
                    'error' => __('Error', 'nhk-event-manager'),
                    'warning' => __('Warning', 'nhk-event-manager'),
                    'info' => __('Info', 'nhk-event-manager'),
                    'debug' => __('Debug', 'nhk-event-manager'),
                ],
                'description' => __('Minimum level for debug logging.', 'nhk-event-manager')
            ]
        );

        // Cache duration
        register_setting('nhk-event-settings_advanced', 'nhk_event_cache_duration', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 3600
        ]);

        add_settings_field(
            'nhk_event_cache_duration',
            __('Cache Duration', 'nhk-event-manager'),
            [$this, 'render_number_field'],
            $this->get_menu_slug() . '_advanced',
            'nhk_event_advanced_cache',
            [
                'option_name' => 'nhk_event_cache_duration',
                'field_name' => 'nhk_event_cache_duration',
                'default' => 3600,
                'min' => 300,
                'max' => 86400,
                'description' => __('Cache duration in seconds (300-86400).', 'nhk-event-manager')
            ]
        );
    }

    /**
     * Sanitize checkbox value
     *
     * @param mixed $value Input value
     * @return bool
     */
    public function sanitize_checkbox($value): bool {
        return !empty($value);
    }

    /**
     * Render section descriptions
     */

    public function render_general_defaults_section(): void {
        echo '<p>' . esc_html__('Configure default values for new events.', 'nhk-event-manager') . '</p>';
    }

    public function render_timezone_section(): void {
        echo '<p>' . esc_html__('Configure date and time display formats.', 'nhk-event-manager') . '</p>';
    }

    public function render_archive_section(): void {
        echo '<p>' . esc_html__('Configure how events are displayed in archive pages.', 'nhk-event-manager') . '</p>';
    }

    public function render_single_section(): void {
        echo '<p>' . esc_html__('Configure single event page display.', 'nhk-event-manager') . '</p>';
    }

    public function render_email_section(): void {
        echo '<p>' . esc_html__('Configure email notifications and reminders.', 'nhk-event-manager') . '</p>';
    }

    public function render_debug_section(): void {
        echo '<p>' . esc_html__('Configure debugging and logging options.', 'nhk-event-manager') . '</p>';
    }

    public function render_cache_section(): void {
        echo '<p>' . esc_html__('Configure caching settings for better performance.', 'nhk-event-manager') . '</p>';

        // Add cache purge button
        echo '<p>';
        echo '<button type="button" id="purge-event-cache" class="button button-secondary">';
        echo esc_html__('Purge Event Cache', 'nhk-event-manager');
        echo '</button>';
        echo ' <span id="cache-purge-result"></span>';
        echo '</p>';

        // Add JavaScript for cache purge
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#purge-event-cache').click(function() {
                var button = $(this);
                var result = $('#cache-purge-result');

                button.prop('disabled', true).text('<?php echo esc_js(__('Purging...', 'nhk-event-manager')); ?>');

                var params = new URLSearchParams({
                    action: 'nhk_purge_event_cache',
                    nonce: '<?php echo wp_create_nonce('nhk_purge_cache'); ?>'
                });

                nhkAjaxHandler.makeRequest(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: params.toString()
                }).then(function(response) {
                    if (response.success) {
                        result.html('<span style="color: green;"><?php echo esc_js(__('Cache purged successfully!', 'nhk-event-manager')); ?></span>');
                    } else {
                        result.html('<span style="color: red;"><?php echo esc_js(__('Failed to purge cache.', 'nhk-event-manager')); ?></span>');
                    }
                }).catch(function() {
                    result.html('<span style="color: red;"><?php echo esc_js(__('Error purging cache.', 'nhk-event-manager')); ?></span>');
                }).finally(function() {
                    button.prop('disabled', false).text('<?php echo esc_js(__('Purge Event Cache', 'nhk-event-manager')); ?>');
                    setTimeout(function() {
                        result.html('');
                    }, 3000);
                });
            });
        });
        </script>
        <?php
    }

    public function render_health_section(): void {
        echo '<p>' . esc_html__('Check the health of your Event Manager installation.', 'nhk-event-manager') . '</p>';

        // Add health check button
        echo '<p>';
        echo '<button type="button" id="run-health-checks" class="button button-primary">';
        echo esc_html__('Run Health Checks', 'nhk-event-manager');
        echo '</button>';
        echo '</p>';

        echo '<div id="health-check-results" style="margin-top: 20px;"></div>';

        // Add JavaScript for health checks
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#run-health-checks').click(function() {
                var button = $(this);
                var results = $('#health-check-results');

                button.prop('disabled', true).text('<?php echo esc_js(__('Running Checks...', 'nhk-event-manager')); ?>');
                results.html('<p><?php echo esc_js(__('Running health checks...', 'nhk-event-manager')); ?></p>');

                var params = new URLSearchParams({
                    action: 'nhk_event_health_check',
                    nonce: '<?php echo wp_create_nonce('nhk_event_health_check'); ?>'
                });

                nhkAjaxHandler.makeRequest(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: params.toString()
                }).then(function(response) {
                    if (response.success) {
                        var html = '<div class="health-check-results">';
                        $.each(response.data, function(check, result) {
                            var statusClass = result.status === 'healthy' ? 'success' :
                                            result.status === 'warning' ? 'warning' : 'error';
                            html += '<div class="notice notice-' + statusClass + ' inline">';
                            html += '<p><strong>' + check.replace(/_/g, ' ') + ':</strong> ' + result.message + '</p>';
                            html += '</div>';
                        });
                        html += '</div>';
                        results.html(html);
                    } else {
                        results.html('<div class="notice notice-error inline"><p><?php echo esc_js(__('Failed to run health checks.', 'nhk-event-manager')); ?></p></div>');
                    }
                }).catch(function() {
                    results.html('<div class="notice notice-error inline"><p><?php echo esc_js(__('Error running health checks.', 'nhk-event-manager')); ?></p></div>');
                }).finally(function() {
                    button.prop('disabled', false).text('<?php echo esc_js(__('Run Health Checks', 'nhk-event-manager')); ?>');
                });

            });
        });
        </script>
        <?php
    }
}
