<?php
/**
 * Cache Cleanup Background Job
 * 
 * Demonstrates background job for cache maintenance using NHK Framework.
 * Shows automated cache cleanup and optimization tasks.
 * 
 * @package NHK\EventManager\Jobs
 * @since 1.0.0
 */

namespace NHK\EventManager\Jobs;

use NHK\Framework\Abstracts\Abstract_Background_Job;
use NHK\EventManager\Services\EventCacheService;

/**
 * Cache Cleanup Job Class
 * 
 * Demonstrates:
 * - Cache maintenance automation
 * - Performance optimization
 * - System cleanup tasks
 * - Resource management
 */
class CacheCleanupJob extends Abstract_Background_Job {
    
    /**
     * Cache service
     * 
     * @var EventCacheService
     */
    protected EventCacheService $cache_service;
    
    /**
     * Constructor
     * 
     * @param Container $container Service container
     * @param EventCacheService $cache_service Cache service
     */
    public function __construct($container, EventCacheService $cache_service) {
        parent::__construct($container);
        $this->cache_service = $cache_service;
    }
    
    /**
     * Get the job name
     * 
     * @return string
     */
    protected function get_job_name(): string {
        return 'cache_cleanup';
    }
    
    /**
     * Execute the job
     * 
     * Performs comprehensive cache cleanup and optimization.
     * 
     * @param array $args Job arguments
     * @return bool Success status
     */
    protected function execute(array $args = []): bool {
        $this->log_message('Starting cache cleanup job');
        
        $cleanup_tasks = [
            'expired_transients' => 'Clean expired transients',
            'orphaned_cache' => 'Remove orphaned cache entries',
            'optimize_cache' => 'Optimize cache structure',
            'warm_cache' => 'Warm up frequently accessed cache',
            'cleanup_logs' => 'Clean old log entries',
        ];
        
        $total_tasks = count($cleanup_tasks);
        $completed_tasks = 0;
        $errors = 0;
        
        foreach ($cleanup_tasks as $task => $description) {
            try {
                $this->update_progress(
                    intval(($completed_tasks / $total_tasks) * 100),
                    $description
                );
                
                $success = $this->execute_cleanup_task($task, $args);
                
                if (!$success) {
                    $errors++;
                    $this->log_message("Task failed: {$task}", 'warning');
                }
                
                $completed_tasks++;
                
            } catch (\Exception $e) {
                $this->handle_error("Error in task {$task}: " . $e->getMessage());
                $errors++;
                $completed_tasks++;
            }
        }
        
        $this->log_message("Cache cleanup completed: {$completed_tasks} tasks, {$errors} errors");
        
        return $errors === 0;
    }
    
    /**
     * Execute specific cleanup task
     * 
     * @param string $task Task name
     * @param array $args Job arguments
     * @return bool Success status
     */
    protected function execute_cleanup_task(string $task, array $args): bool {
        switch ($task) {
            case 'expired_transients':
                return $this->cleanup_expired_transients();
                
            case 'orphaned_cache':
                return $this->cleanup_orphaned_cache();
                
            case 'optimize_cache':
                return $this->optimize_cache_structure();
                
            case 'warm_cache':
                return $this->warm_cache();
                
            case 'cleanup_logs':
                return $this->cleanup_old_logs();
                
            default:
                return false;
        }
    }
    
    /**
     * Clean up expired transients
     * 
     * @return bool Success status
     */
    protected function cleanup_expired_transients(): bool {
        try {
            $result = $this->cache_service->cleanup_expired_cache();
            $this->log_message('Expired transients cleaned up');
            return $result;
        } catch (\Exception $e) {
            $this->log_message('Failed to clean expired transients: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Clean up orphaned cache entries
     * 
     * @return bool Success status
     */
    protected function cleanup_orphaned_cache(): bool {
        global $wpdb;
        
        try {
            // Find cache entries for deleted events
            $orphaned_cache = $wpdb->get_col("
                SELECT option_name 
                FROM {$wpdb->options} 
                WHERE option_name LIKE '_transient_nhk_event_event_data_%'
                AND SUBSTRING(option_name, 35) NOT IN (
                    SELECT ID FROM {$wpdb->posts} WHERE post_type = 'nhk_event'
                )
            ");
            
            $cleaned = 0;
            foreach ($orphaned_cache as $option_name) {
                $transient_name = str_replace('_transient_', '', $option_name);
                if (delete_transient($transient_name)) {
                    $cleaned++;
                }
            }
            
            $this->log_message("Cleaned {$cleaned} orphaned cache entries");
            return true;
            
        } catch (\Exception $e) {
            $this->log_message('Failed to clean orphaned cache: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Optimize cache structure
     * 
     * @return bool Success status
     */
    protected function optimize_cache_structure(): bool {
        try {
            global $wpdb;
            
            // Optimize options table
            $wpdb->query("OPTIMIZE TABLE {$wpdb->options}");
            
            // Clean up autoload options
            $large_autoload = $wpdb->get_results("
                SELECT option_name, LENGTH(option_value) as size
                FROM {$wpdb->options} 
                WHERE autoload = 'yes' 
                AND option_name LIKE 'nhk_event_%'
                AND LENGTH(option_value) > 1000
                ORDER BY size DESC
                LIMIT 10
            ");
            
            foreach ($large_autoload as $option) {
                // Convert large autoload options to non-autoload
                $wpdb->update(
                    $wpdb->options,
                    ['autoload' => 'no'],
                    ['option_name' => $option->option_name]
                );
            }
            
            $this->log_message('Cache structure optimized');
            return true;
            
        } catch (\Exception $e) {
            $this->log_message('Failed to optimize cache structure: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Warm up cache with frequently accessed data
     * 
     * @return bool Success status
     */
    protected function warm_cache(): bool {
        try {
            $result = $this->cache_service->warm_up_cache();
            $this->log_message('Cache warmed up successfully');
            return $result;
        } catch (\Exception $e) {
            $this->log_message('Failed to warm cache: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Clean up old log entries
     * 
     * @return bool Success status
     */
    protected function cleanup_old_logs(): bool {
        try {
            global $wpdb;
            
            // Clean up old job data (older than 30 days)
            $old_jobs = $wpdb->get_col($wpdb->prepare("
                SELECT option_name 
                FROM {$wpdb->options} 
                WHERE option_name LIKE 'nhk_job_%'
                AND option_value LIKE '%%end_time%%'
                AND CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(option_value, '\"end_time\";i:', -1), ';', 1) AS UNSIGNED) < %d
            ", strtotime('-30 days')));
            
            $cleaned = 0;
            foreach ($old_jobs as $option_name) {
                if (delete_option($option_name)) {
                    $cleaned++;
                }
            }
            
            // Clean up old debug logs if they exist
            $log_file = WP_CONTENT_DIR . '/debug.log';
            if (file_exists($log_file) && filesize($log_file) > 10 * 1024 * 1024) { // 10MB
                // Truncate large log files
                $handle = fopen($log_file, 'r+');
                if ($handle) {
                    fseek($handle, -1024 * 1024); // Keep last 1MB
                    $content = fread($handle, 1024 * 1024);
                    ftruncate($handle, 0);
                    fseek($handle, 0);
                    fwrite($handle, $content);
                    fclose($handle);
                }
            }
            
            $this->log_message("Cleaned {$cleaned} old log entries");
            return true;
            
        } catch (\Exception $e) {
            $this->log_message('Failed to clean old logs: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Schedule weekly cache cleanup
     * 
     * @return bool Success status
     */
    public function schedule_weekly_cleanup(): bool {
        return $this->schedule_recurring('weekly', [], strtotime('next sunday 2:00 AM'));
    }
    
    /**
     * Get cache statistics before and after cleanup
     * 
     * @return array Statistics
     */
    public function get_cleanup_statistics(): array {
        global $wpdb;
        
        // Count transients
        $transient_count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_nhk_event_%'
        ");
        
        // Get cache size
        $cache_size = $wpdb->get_var("
            SELECT SUM(LENGTH(option_value)) 
            FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_nhk_event_%'
        ");
        
        // Count expired transients
        $expired_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->options} o1
            INNER JOIN {$wpdb->options} o2 ON o1.option_name = CONCAT('_transient_timeout_', SUBSTRING(o2.option_name, 12))
            WHERE o2.option_name LIKE '_transient_nhk_event_%'
            AND CAST(o1.option_value AS UNSIGNED) < %d
        ", time()));
        
        // Count job records
        $job_count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->options} 
            WHERE option_name LIKE 'nhk_job_%'
        ");
        
        return [
            'transient_count' => (int) $transient_count,
            'cache_size_bytes' => (int) $cache_size,
            'cache_size_mb' => round($cache_size / 1024 / 1024, 2),
            'expired_count' => (int) $expired_count,
            'job_records' => (int) $job_count,
            'last_cleanup' => get_option('nhk_event_last_cache_cleanup', 'Never'),
        ];
    }
    
    /**
     * Force immediate cache cleanup
     * 
     * @return array Cleanup results
     */
    public function force_cleanup(): array {
        $before_stats = $this->get_cleanup_statistics();
        
        $success = $this->execute([]);
        
        $after_stats = $this->get_cleanup_statistics();
        
        // Update last cleanup time
        update_option('nhk_event_last_cache_cleanup', current_time('Y-m-d H:i:s'));
        
        return [
            'success' => $success,
            'before' => $before_stats,
            'after' => $after_stats,
            'cleaned_transients' => $before_stats['transient_count'] - $after_stats['transient_count'],
            'freed_space_mb' => $before_stats['cache_size_mb'] - $after_stats['cache_size_mb'],
        ];
    }
}
