<?php
/**
 * Abstract Background Job Class for NHK Framework
 * 
 * This class provides a base for creating background jobs using WordPress
 * cron system with additional framework features.
 * 
 * @package NHK\Framework\Abstracts
 * @since 1.0.0
 */

namespace NHK\Framework\Abstracts;

use NHK\Framework\Container\Container;

/**
 * Abstract class for Background Jobs
 * 
 * Provides a structured way to create background jobs with:
 * - WordPress cron integration
 * - Job scheduling and management
 * - Error handling and logging
 * - Progress tracking
 */
abstract class Abstract_Background_Job {
    
    /**
     * Service container
     * 
     * @var Container
     */
    protected Container $container;
    
    /**
     * Job identifier
     * 
     * @var string
     */
    protected string $job_id;
    
    /**
     * Job status
     * 
     * @var string
     */
    protected string $status = 'pending';
    
    /**
     * Job progress (0-100)
     * 
     * @var int
     */
    protected int $progress = 0;
    
    /**
     * Job start time
     * 
     * @var int
     */
    protected int $start_time;
    
    /**
     * Job end time
     * 
     * @var int
     */
    protected int $end_time;
    
    /**
     * Job errors
     * 
     * @var array
     */
    protected array $errors = [];
    
    /**
     * Constructor
     * 
     * @param Container $container Service container
     */
    public function __construct(Container $container) {
        $this->container = $container;
        $this->job_id = $this->generate_job_id();
    }
    
    /**
     * Initialize the background job
     * 
     * @return void
     */
    public function init(): void {
        add_action($this->get_hook_name(), [$this, 'run_job']);
        add_action('wp_ajax_' . $this->get_ajax_action(), [$this, 'handle_ajax_request']);
        add_action('wp_ajax_nopriv_' . $this->get_ajax_action(), [$this, 'handle_ajax_request']);
    }
    
    /**
     * Get the job name
     * 
     * @return string
     */
    abstract protected function get_job_name(): string;
    
    /**
     * Execute the job
     * 
     * @param array $args Job arguments
     * @return bool Success status
     */
    abstract protected function execute(array $args = []): bool;
    
    /**
     * Schedule the job
     * 
     * @param array $args Job arguments
     * @param int|null $timestamp When to run (null for immediate)
     * @return bool Success status
     */
    public function schedule(array $args = [], ?int $timestamp = null): bool {
        $timestamp = $timestamp ?? time();
        
        // Check if job is already scheduled
        if (wp_next_scheduled($this->get_hook_name(), [$args])) {
            return false;
        }
        
        // Schedule the job
        $result = wp_schedule_single_event($timestamp, $this->get_hook_name(), [$args]);
        
        if ($result) {
            $this->log_message('Job scheduled: ' . $this->get_job_name());
            $this->save_job_data([
                'status' => 'scheduled',
                'scheduled_time' => $timestamp,
                'args' => $args,
            ]);
        }
        
        return $result;
    }
    
    /**
     * Schedule recurring job
     * 
     * @param string $recurrence Recurrence interval
     * @param array $args Job arguments
     * @param int|null $timestamp When to start
     * @return bool Success status
     */
    public function schedule_recurring(string $recurrence, array $args = [], ?int $timestamp = null): bool {
        $timestamp = $timestamp ?? time();
        
        // Check if job is already scheduled
        if (wp_next_scheduled($this->get_hook_name(), [$args])) {
            return false;
        }
        
        // Schedule the recurring job
        $result = wp_schedule_event($timestamp, $recurrence, $this->get_hook_name(), [$args]);
        
        if ($result) {
            $this->log_message('Recurring job scheduled: ' . $this->get_job_name());
            $this->save_job_data([
                'status' => 'scheduled',
                'recurrence' => $recurrence,
                'scheduled_time' => $timestamp,
                'args' => $args,
            ]);
        }
        
        return $result;
    }
    
    /**
     * Cancel scheduled job
     * 
     * @param array $args Job arguments
     * @return bool Success status
     */
    public function cancel(array $args = []): bool {
        $timestamp = wp_next_scheduled($this->get_hook_name(), [$args]);
        
        if ($timestamp) {
            $result = wp_unschedule_event($timestamp, $this->get_hook_name(), [$args]);
            
            if ($result) {
                $this->log_message('Job cancelled: ' . $this->get_job_name());
                $this->save_job_data([
                    'status' => 'cancelled',
                    'cancelled_time' => time(),
                ]);
            }
            
            return $result;
        }
        
        return false;
    }
    
    /**
     * Run the job
     * 
     * WordPress cron callback function.
     * 
     * @param array $args Job arguments
     * @return void
     */
    public function run_job(array $args = []): void {
        $this->start_time = time();
        $this->status = 'running';
        $this->progress = 0;
        $this->errors = [];
        
        $this->log_message('Job started: ' . $this->get_job_name());
        $this->save_job_data([
            'status' => 'running',
            'start_time' => $this->start_time,
            'progress' => 0,
        ]);
        
        try {
            $success = $this->execute($args);
            
            $this->end_time = time();
            $this->status = $success ? 'completed' : 'failed';
            $this->progress = $success ? 100 : $this->progress;
            
            $this->log_message(sprintf(
                'Job %s: %s (Duration: %d seconds)',
                $success ? 'completed' : 'failed',
                $this->get_job_name(),
                $this->end_time - $this->start_time
            ));
            
            $this->save_job_data([
                'status' => $this->status,
                'end_time' => $this->end_time,
                'progress' => $this->progress,
                'duration' => $this->end_time - $this->start_time,
                'errors' => $this->errors,
            ]);
            
        } catch (\Exception $e) {
            $this->handle_error($e->getMessage());
        }
    }
    
    /**
     * Handle AJAX requests for job status
     * 
     * @return void
     */
    public function handle_ajax_request(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', $this->get_ajax_action())) {
            wp_die(__('Security check failed', 'nhk-framework'));
        }
        
        $action = $_POST['job_action'] ?? 'status';
        
        switch ($action) {
            case 'status':
                $this->send_status_response();
                break;
            case 'cancel':
                $this->handle_cancel_request();
                break;
            default:
                wp_send_json_error(['message' => 'Invalid action']);
        }
    }
    
    /**
     * Update job progress
     * 
     * @param int $progress Progress percentage (0-100)
     * @param string $message Optional progress message
     * @return void
     */
    protected function update_progress(int $progress, string $message = ''): void {
        $this->progress = max(0, min(100, $progress));
        
        $this->save_job_data([
            'progress' => $this->progress,
            'progress_message' => $message,
            'updated_time' => time(),
        ]);
        
        if ($message) {
            $this->log_message($message);
        }
    }
    
    /**
     * Handle job error
     * 
     * @param string $error_message Error message
     * @return void
     */
    protected function handle_error(string $error_message): void {
        $this->errors[] = $error_message;
        $this->status = 'failed';
        $this->end_time = time();
        
        $this->log_message('Job error: ' . $error_message, 'error');
        
        $this->save_job_data([
            'status' => 'failed',
            'end_time' => $this->end_time,
            'errors' => $this->errors,
        ]);
    }
    
    /**
     * Get job status
     * 
     * @return array Job status data
     */
    public function get_status(): array {
        $job_data = $this->get_job_data();
        
        return [
            'job_id' => $this->job_id,
            'job_name' => $this->get_job_name(),
            'status' => $job_data['status'] ?? 'unknown',
            'progress' => $job_data['progress'] ?? 0,
            'start_time' => $job_data['start_time'] ?? null,
            'end_time' => $job_data['end_time'] ?? null,
            'duration' => $job_data['duration'] ?? null,
            'errors' => $job_data['errors'] ?? [],
            'progress_message' => $job_data['progress_message'] ?? '',
        ];
    }
    
    /**
     * Generate unique job ID
     * 
     * @return string Job ID
     */
    protected function generate_job_id(): string {
        return $this->get_job_name() . '_' . uniqid();
    }
    
    /**
     * Get WordPress hook name
     * 
     * @return string Hook name
     */
    protected function get_hook_name(): string {
        return 'nhk_job_' . $this->get_job_name();
    }
    
    /**
     * Get AJAX action name
     * 
     * @return string AJAX action name
     */
    protected function get_ajax_action(): string {
        return 'nhk_job_' . $this->get_job_name() . '_ajax';
    }
    
    /**
     * Save job data
     * 
     * @param array $data Job data
     * @return void
     */
    protected function save_job_data(array $data): void {
        $existing_data = $this->get_job_data();
        $updated_data = array_merge($existing_data, $data);
        
        update_option($this->get_job_option_key(), $updated_data);
    }
    
    /**
     * Get job data
     * 
     * @return array Job data
     */
    protected function get_job_data(): array {
        return get_option($this->get_job_option_key(), []);
    }
    
    /**
     * Get job option key
     * 
     * @return string Option key
     */
    protected function get_job_option_key(): string {
        return 'nhk_job_' . $this->job_id;
    }
    
    /**
     * Send status response via AJAX
     * 
     * @return void
     */
    protected function send_status_response(): void {
        wp_send_json_success($this->get_status());
    }
    
    /**
     * Handle cancel request via AJAX
     * 
     * @return void
     */
    protected function handle_cancel_request(): void {
        $args = $_POST['args'] ?? [];
        $success = $this->cancel($args);
        
        if ($success) {
            wp_send_json_success(['message' => 'Job cancelled successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to cancel job']);
        }
    }
    
    /**
     * Log message
     * 
     * @param string $message Log message
     * @param string $level Log level
     * @return void
     */
    protected function log_message(string $message, string $level = 'info'): void {
        if (WP_DEBUG_LOG) {
            error_log(sprintf('[NHK Framework Job] %s: %s', strtoupper($level), $message));
        }
    }
}
