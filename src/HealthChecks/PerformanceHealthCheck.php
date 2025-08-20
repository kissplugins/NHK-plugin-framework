<?php
/**
 * Performance Health Check
 * 
 * Demonstrates performance monitoring using NHK Framework Abstract_Health_Check.
 * Shows system performance metrics and optimization recommendations.
 * 
 * @package NHK\EventManager\HealthChecks
 * @since 1.0.0
 */

namespace NHK\EventManager\HealthChecks;

use NHK\Framework\Abstracts\Abstract_Health_Check;
use NHK\EventManager\Services\EventCacheService;

/**
 * Performance Health Check Class
 * 
 * Demonstrates:
 * - Performance metrics collection
 * - Memory usage monitoring
 * - Cache efficiency analysis
 * - Database performance checks
 */
class PerformanceHealthCheck extends Abstract_Health_Check {
    
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
     * Get the check name
     * 
     * @return string
     */
    protected function get_check_name(): string {
        return 'performance';
    }
    
    /**
     * Get the check description
     * 
     * @return string
     */
    protected function get_check_description(): string {
        return __('Monitors system performance and resource usage', 'nhk-event-manager');
    }
    
    /**
     * Perform the health check
     * 
     * @return array Check result
     */
    protected function perform_check(): array {
        $issues = [];
        $warnings = [];
        $metrics = [];
        $details = [];
        
        // Check memory usage
        $memory_check = $this->check_memory_usage();
        $metrics['memory'] = $memory_check['metrics'];
        
        if ($memory_check['status'] === self::STATUS_CRITICAL) {
            $issues[] = $memory_check['message'];
        } elseif ($memory_check['status'] === self::STATUS_WARNING) {
            $warnings[] = $memory_check['message'];
        }
        
        // Check database performance
        $db_check = $this->check_database_performance();
        $metrics['database'] = $db_check['metrics'];
        
        if ($db_check['status'] === self::STATUS_CRITICAL) {
            $issues[] = $db_check['message'];
        } elseif ($db_check['status'] === self::STATUS_WARNING) {
            $warnings[] = $db_check['message'];
        }
        
        // Check cache performance
        $cache_check = $this->check_cache_performance();
        $metrics['cache'] = $cache_check['metrics'];
        
        if ($cache_check['status'] === self::STATUS_WARNING) {
            $warnings[] = $cache_check['message'];
        }
        
        // Check disk space
        $disk_check = $this->check_disk_space();
        $metrics['disk'] = $disk_check['metrics'];
        
        if ($disk_check['status'] === self::STATUS_CRITICAL) {
            $issues[] = $disk_check['message'];
        } elseif ($disk_check['status'] === self::STATUS_WARNING) {
            $warnings[] = $disk_check['message'];
        }
        
        // Check plugin load time
        $load_time_check = $this->check_plugin_load_time();
        $metrics['load_time'] = $load_time_check['metrics'];
        
        if ($load_time_check['status'] === self::STATUS_WARNING) {
            $warnings[] = $load_time_check['message'];
        }
        
        // Determine overall status
        if (!empty($issues)) {
            return $this->critical(
                sprintf(__('Performance issues detected: %s', 'nhk-event-manager'), implode(', ', $issues)),
                $details,
                $metrics
            );
        } elseif (!empty($warnings)) {
            return $this->warning(
                sprintf(__('Performance warnings: %s', 'nhk-event-manager'), implode(', ', $warnings)),
                $details,
                $metrics
            );
        } else {
            return $this->healthy(
                __('System performance is optimal', 'nhk-event-manager'),
                $details,
                $metrics
            );
        }
    }
    
    /**
     * Check memory usage
     * 
     * @return array Memory usage check result
     */
    protected function check_memory_usage(): array {
        $memory = $this->get_memory_usage();
        
        $status = $this->check_threshold(
            $memory['usage_percentage'],
            80, // Warning at 80%
            95, // Critical at 95%
            true
        );
        
        $message = sprintf(
            __('Memory usage: %s of %s (%s%%)', 'nhk-event-manager'),
            $this->format_bytes($memory['current_usage']),
            $this->format_bytes($memory['limit']),
            $memory['usage_percentage']
        );
        
        return [
            'status' => $status,
            'message' => $message,
            'metrics' => [
                'current_usage_bytes' => $memory['current_usage'],
                'peak_usage_bytes' => $memory['peak_usage'],
                'limit_bytes' => $memory['limit'],
                'usage_percentage' => $memory['usage_percentage'],
                'current_usage_formatted' => $this->format_bytes($memory['current_usage']),
                'peak_usage_formatted' => $this->format_bytes($memory['peak_usage']),
                'limit_formatted' => $this->format_bytes($memory['limit']),
            ],
        ];
    }
    
    /**
     * Check database performance
     * 
     * @return array Database performance check result
     */
    protected function check_database_performance(): array {
        global $wpdb;
        
        $start_time = microtime(true);
        
        // Test query performance
        $test_queries = [
            'simple_select' => "SELECT 1",
            'event_count' => "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'nhk_event'",
            'meta_query' => "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE 'event_%'",
        ];
        
        $query_times = [];
        $total_time = 0;
        
        foreach ($test_queries as $name => $query) {
            $query_start = microtime(true);
            $wpdb->get_var($query);
            $query_time = microtime(true) - $query_start;
            
            $query_times[$name] = round($query_time * 1000, 2); // milliseconds
            $total_time += $query_time;
        }
        
        $avg_query_time = ($total_time / count($test_queries)) * 1000; // milliseconds
        
        // Check query performance
        $status = $this->check_threshold(
            $avg_query_time,
            100, // Warning at 100ms
            500, // Critical at 500ms
            true
        );
        
        $message = sprintf(
            __('Average database query time: %sms', 'nhk-event-manager'),
            round($avg_query_time, 2)
        );
        
        return [
            'status' => $status,
            'message' => $message,
            'metrics' => [
                'average_query_time_ms' => round($avg_query_time, 2),
                'total_test_time_ms' => round($total_time * 1000, 2),
                'query_times' => $query_times,
            ],
        ];
    }
    
    /**
     * Check cache performance
     * 
     * @return array Cache performance check result
     */
    protected function check_cache_performance(): array {
        $cache_stats = $this->cache_service->get_cache_stats();
        
        $issues = [];
        
        // Check cache size
        if ($cache_stats['cache_size_mb'] > 100) {
            $issues[] = sprintf(
                __('Cache size is large: %sMB', 'nhk-event-manager'),
                $cache_stats['cache_size_mb']
            );
        }
        
        // Check number of cache entries
        if ($cache_stats['transient_count'] > 1000) {
            $issues[] = sprintf(
                __('High number of cache entries: %d', 'nhk-event-manager'),
                $cache_stats['transient_count']
            );
        }
        
        $status = empty($issues) ? self::STATUS_HEALTHY : self::STATUS_WARNING;
        $message = empty($issues) 
            ? sprintf(__('Cache: %d entries, %sMB', 'nhk-event-manager'), $cache_stats['transient_count'], $cache_stats['cache_size_mb'])
            : implode(', ', $issues);
        
        return [
            'status' => $status,
            'message' => $message,
            'metrics' => $cache_stats,
        ];
    }
    
    /**
     * Check disk space
     * 
     * @return array Disk space check result
     */
    protected function check_disk_space(): array {
        $disk_info = $this->get_disk_space(ABSPATH);
        
        if ($disk_info === false) {
            return [
                'status' => self::STATUS_UNKNOWN,
                'message' => __('Unable to check disk space', 'nhk-event-manager'),
                'metrics' => [],
            ];
        }
        
        $status = $this->check_threshold(
            $disk_info['usage_percentage'],
            80, // Warning at 80%
            95, // Critical at 95%
            true
        );
        
        $message = sprintf(
            __('Disk usage: %s of %s (%s%%)', 'nhk-event-manager'),
            $this->format_bytes($disk_info['used_bytes']),
            $this->format_bytes($disk_info['total_bytes']),
            $disk_info['usage_percentage']
        );
        
        return [
            'status' => $status,
            'message' => $message,
            'metrics' => [
                'free_bytes' => $disk_info['free_bytes'],
                'total_bytes' => $disk_info['total_bytes'],
                'used_bytes' => $disk_info['used_bytes'],
                'usage_percentage' => $disk_info['usage_percentage'],
                'free_formatted' => $this->format_bytes($disk_info['free_bytes']),
                'total_formatted' => $this->format_bytes($disk_info['total_bytes']),
                'used_formatted' => $this->format_bytes($disk_info['used_bytes']),
            ],
        ];
    }
    
    /**
     * Check plugin load time
     * 
     * @return array Load time check result
     */
    protected function check_plugin_load_time(): array {
        // Simulate plugin load time check
        $start_time = microtime(true);
        
        // Perform some typical plugin operations
        post_type_exists('nhk_event');
        taxonomy_exists('nhk_event_category');
        get_option('nhk_event_cache_duration');
        
        $load_time = (microtime(true) - $start_time) * 1000; // milliseconds
        
        $status = $this->check_threshold(
            $load_time,
            50, // Warning at 50ms
            200, // Critical at 200ms
            true
        );
        
        $message = sprintf(
            __('Plugin operations time: %sms', 'nhk-event-manager'),
            round($load_time, 2)
        );
        
        return [
            'status' => $status,
            'message' => $message,
            'metrics' => [
                'load_time_ms' => round($load_time, 2),
            ],
        ];
    }
}
