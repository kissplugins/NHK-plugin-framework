<?php
/**
 * Event Reminder Background Job
 * 
 * Demonstrates background job implementation using NHK Framework Abstract_Background_Job.
 * Shows proper use of WordPress cron system for automated tasks.
 * 
 * @package NHK\EventManager\Jobs
 * @since 1.0.0
 */

namespace NHK\EventManager\Jobs;

use NHK\Framework\Abstracts\Abstract_Background_Job;
use NHK\EventManager\Services\EventQueryService;

/**
 * Event Reminder Job Class
 * 
 * Demonstrates:
 * - Extending framework Abstract_Background_Job
 * - WordPress cron integration
 * - Email sending functionality
 * - Progress tracking
 * - Error handling
 */
class EventReminderJob extends Abstract_Background_Job {
    
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
     * Get the job name
     * 
     * @return string
     */
    protected function get_job_name(): string {
        return 'event_reminder';
    }
    
    /**
     * Execute the job
     * 
     * Demonstrates complex background job execution with progress tracking.
     * 
     * @param array $args Job arguments
     * @return bool Success status
     */
    protected function execute(array $args = []): bool {
        // Check if reminders are enabled
        if (!get_option('nhk_event_enable_reminders', false)) {
            $this->log_message('Event reminders are disabled');
            return true;
        }
        
        $reminder_timing = get_option('nhk_event_reminder_timing', '1_day');
        $reminder_hours = $this->get_reminder_hours($reminder_timing);
        
        // Calculate reminder date
        $reminder_date = date('Y-m-d H:i:s', strtotime("+{$reminder_hours} hours"));
        
        // Get events that need reminders
        $events = $this->get_events_needing_reminders($reminder_date);
        
        if (empty($events)) {
            $this->log_message('No events need reminders at this time');
            return true;
        }
        
        $total_events = count($events);
        $processed = 0;
        $sent = 0;
        $errors = 0;
        
        $this->log_message("Processing reminders for {$total_events} events");
        
        foreach ($events as $event) {
            try {
                $this->update_progress(
                    intval(($processed / $total_events) * 100),
                    "Processing event: {$event->post_title}"
                );
                
                $reminder_sent = $this->send_event_reminder($event);
                
                if ($reminder_sent) {
                    $sent++;
                    $this->mark_reminder_sent($event->ID);
                } else {
                    $errors++;
                }
                
                $processed++;
                
                // Small delay to prevent overwhelming the mail server
                usleep(100000); // 0.1 seconds
                
            } catch (\Exception $e) {
                $this->handle_error("Error processing event {$event->ID}: " . $e->getMessage());
                $errors++;
                $processed++;
            }
        }
        
        $this->log_message("Reminder job completed: {$sent} sent, {$errors} errors");
        
        return $errors === 0;
    }
    
    /**
     * Get events that need reminders
     * 
     * @param string $reminder_date Reminder date threshold
     * @return array Events needing reminders
     */
    protected function get_events_needing_reminders(string $reminder_date): array {
        global $wpdb;
        
        // Query events that:
        // 1. Start within the reminder window
        // 2. Haven't had reminders sent yet
        // 3. Are published
        $query = $wpdb->prepare("
            SELECT p.* 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = 'event_start_date'
            LEFT JOIN {$wpdb->postmeta} pm_reminder ON p.ID = pm_reminder.post_id AND pm_reminder.meta_key = 'event_reminder_sent'
            WHERE p.post_type = 'nhk_event'
            AND p.post_status = 'publish'
            AND pm_start.meta_value <= %s
            AND pm_start.meta_value >= %s
            AND (pm_reminder.meta_value IS NULL OR pm_reminder.meta_value = '')
        ", $reminder_date, current_time('Y-m-d H:i:s'));
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Send reminder for a specific event
     * 
     * @param \WP_Post $event Event post object
     * @return bool Success status
     */
    protected function send_event_reminder(\WP_Post $event): bool {
        // Get event details
        $start_date = get_post_meta($event->ID, 'event_start_date', true);
        $start_time = get_post_meta($event->ID, 'event_start_time', true);
        $venue = get_post_meta($event->ID, 'event_venue', true);
        $organizer_email = get_post_meta($event->ID, 'event_organizer_email', true);
        
        // Get admin email as fallback
        $admin_email = get_option('nhk_event_admin_email', get_option('admin_email'));
        
        // Prepare email content
        $subject = sprintf(
            __('Reminder: %s is coming up', 'nhk-event-manager'),
            $event->post_title
        );
        
        $message = $this->build_reminder_email($event, [
            'start_date' => $start_date,
            'start_time' => $start_time,
            'venue' => $venue,
        ]);
        
        // Set email headers
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . $admin_email . '>',
        ];
        
        // Send to organizer if email is available
        $recipients = [];
        if ($organizer_email) {
            $recipients[] = $organizer_email;
        }
        
        // Send to admin
        $recipients[] = $admin_email;
        
        // Remove duplicates
        $recipients = array_unique($recipients);
        
        $success = true;
        foreach ($recipients as $recipient) {
            $sent = wp_mail($recipient, $subject, $message, $headers);
            if (!$sent) {
                $success = false;
                $this->log_message("Failed to send reminder to {$recipient} for event {$event->ID}", 'error');
            } else {
                $this->log_message("Reminder sent to {$recipient} for event {$event->ID}");
            }
        }
        
        return $success;
    }
    
    /**
     * Build reminder email content
     * 
     * @param \WP_Post $event Event post object
     * @param array $event_data Event data
     * @return string Email content
     */
    protected function build_reminder_email(\WP_Post $event, array $event_data): string {
        $event_url = get_permalink($event->ID);
        $site_name = get_bloginfo('name');
        
        $formatted_date = '';
        if ($event_data['start_date']) {
            $formatted_date = date_i18n(get_option('date_format'), strtotime($event_data['start_date']));
            
            if ($event_data['start_time']) {
                $formatted_date .= ' ' . date_i18n(get_option('time_format'), strtotime($event_data['start_time']));
            }
        }
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php echo esc_html($event->post_title); ?></title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #f8f9fa; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .event-details { background: #f8f9fa; padding: 15px; margin: 20px 0; }
                .button { display: inline-block; background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php esc_html_e('Event Reminder', 'nhk-event-manager'); ?></h1>
                </div>
                
                <div class="content">
                    <h2><?php echo esc_html($event->post_title); ?></h2>
                    
                    <p><?php esc_html_e('This is a reminder that the following event is coming up:', 'nhk-event-manager'); ?></p>
                    
                    <div class="event-details">
                        <?php if ($formatted_date): ?>
                            <p><strong><?php esc_html_e('Date & Time:', 'nhk-event-manager'); ?></strong> <?php echo esc_html($formatted_date); ?></p>
                        <?php endif; ?>
                        
                        <?php if ($event_data['venue']): ?>
                            <p><strong><?php esc_html_e('Venue:', 'nhk-event-manager'); ?></strong> <?php echo esc_html($event_data['venue']); ?></p>
                        <?php endif; ?>
                        
                        <?php if ($event->post_excerpt): ?>
                            <p><strong><?php esc_html_e('Description:', 'nhk-event-manager'); ?></strong></p>
                            <p><?php echo wp_kses_post($event->post_excerpt); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <p style="text-align: center;">
                        <a href="<?php echo esc_url($event_url); ?>" class="button">
                            <?php esc_html_e('View Event Details', 'nhk-event-manager'); ?>
                        </a>
                    </p>
                </div>
                
                <div class="footer">
                    <p><?php printf(esc_html__('This reminder was sent by %s', 'nhk-event-manager'), esc_html($site_name)); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Mark reminder as sent for an event
     * 
     * @param int $event_id Event ID
     * @return void
     */
    protected function mark_reminder_sent(int $event_id): void {
        update_post_meta($event_id, 'event_reminder_sent', current_time('Y-m-d H:i:s'));
    }
    
    /**
     * Get reminder hours from timing setting
     * 
     * @param string $timing Timing setting
     * @return int Hours
     */
    protected function get_reminder_hours(string $timing): int {
        switch ($timing) {
            case '1_hour':
                return 1;
            case '1_day':
                return 24;
            case '3_days':
                return 72;
            case '1_week':
                return 168;
            default:
                return 24;
        }
    }
    
    /**
     * Schedule daily reminder check
     * 
     * @return bool Success status
     */
    public function schedule_daily_check(): bool {
        return $this->schedule_recurring('daily', [], strtotime('tomorrow 9:00 AM'));
    }
    
    /**
     * Get job statistics
     * 
     * @return array Statistics
     */
    public function get_statistics(): array {
        global $wpdb;
        
        // Count events with reminders sent
        $reminders_sent = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = 'event_reminder_sent'
            AND p.post_type = 'nhk_event'
            AND pm.meta_value != ''
        ");
        
        // Count upcoming events without reminders
        $pending_reminders = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = 'event_start_date'
            LEFT JOIN {$wpdb->postmeta} pm_reminder ON p.ID = pm_reminder.post_id AND pm_reminder.meta_key = 'event_reminder_sent'
            WHERE p.post_type = 'nhk_event'
            AND p.post_status = 'publish'
            AND pm_start.meta_value >= %s
            AND (pm_reminder.meta_value IS NULL OR pm_reminder.meta_value = '')
        ", current_time('Y-m-d')));
        
        return [
            'reminders_sent' => (int) $reminders_sent,
            'pending_reminders' => (int) $pending_reminders,
            'is_enabled' => get_option('nhk_event_enable_reminders', false),
            'reminder_timing' => get_option('nhk_event_reminder_timing', '1_day'),
        ];
    }
}
