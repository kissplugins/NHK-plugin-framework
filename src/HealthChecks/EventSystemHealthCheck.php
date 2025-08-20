<?php
/**
 * Event System Health Check
 * 
 * Demonstrates health check implementation using NHK Framework Abstract_Health_Check.
 * Shows comprehensive system monitoring for event management functionality.
 * 
 * @package NHK\EventManager\HealthChecks
 * @since 1.0.0
 */

namespace NHK\EventManager\HealthChecks;

use NHK\Framework\Abstracts\Abstract_Health_Check;

/**
 * Event System Health Check Class
 * 
 * Demonstrates:
 * - System status monitoring
 * - Performance metrics collection
 * - Error detection and reporting
 * - WordPress integration health
 */
class EventSystemHealthCheck extends Abstract_Health_Check {
    
    /**
     * Get the check name
     * 
     * @return string
     */
    protected function get_check_name(): string {
        return 'event_system';
    }
    
    /**
     * Get the check description
     * 
     * @return string
     */
    protected function get_check_description(): string {
        return __('Checks the overall health of the Event Manager system', 'nhk-event-manager');
    }
    
    /**
     * Perform the health check
     * 
     * @return array Check result
     */
    protected function perform_check(): array {
        $issues = [];
        $metrics = [];
        $details = [];
        
        // Check if CPT is registered
        if (!post_type_exists('nhk_event')) {
            $issues[] = __('Event post type is not registered', 'nhk-event-manager');
        } else {
            $details['cpt_registered'] = true;
        }
        
        // Check if taxonomies are registered
        $taxonomies = ['nhk_event_category', 'nhk_event_venue'];
        $missing_taxonomies = [];
        
        foreach ($taxonomies as $taxonomy) {
            if (!taxonomy_exists($taxonomy)) {
                $missing_taxonomies[] = $taxonomy;
            }
        }
        
        if (!empty($missing_taxonomies)) {
            $issues[] = sprintf(
                __('Missing taxonomies: %s', 'nhk-event-manager'),
                implode(', ', $missing_taxonomies)
            );
        } else {
            $details['taxonomies_registered'] = true;
        }
        
        // Check database tables
        $db_check = $this->check_database_health();
        if (!$db_check['healthy']) {
            $issues[] = $db_check['message'];
        } else {
            $metrics['database'] = $db_check['metrics'];
        }
        
        // Check event data integrity
        $data_check = $this->check_event_data_integrity();
        $metrics['data_integrity'] = $data_check['metrics'];
        
        if (!empty($data_check['issues'])) {
            $issues = array_merge($issues, $data_check['issues']);
        }
        
        // Check WordPress requirements
        $wp_check = $this->check_wordpress_requirements();
        if (!$wp_check['healthy']) {
            $issues[] = $wp_check['message'];
        } else {
            $details['wordpress_requirements'] = $wp_check['details'];
        }
        
        // Check file permissions
        $permissions_check = $this->check_file_permissions();
        if (!$permissions_check['healthy']) {
            $issues[] = $permissions_check['message'];
        } else {
            $details['file_permissions'] = $permissions_check['details'];
        }
        
        // Determine overall status
        if (empty($issues)) {
            return $this->healthy(
                __('Event system is functioning properly', 'nhk-event-manager'),
                $details,
                $metrics
            );
        } elseif (count($issues) <= 2) {
            return $this->warning(
                sprintf(__('Event system has minor issues: %s', 'nhk-event-manager'), implode(', ', $issues)),
                $details,
                $metrics
            );
        } else {
            return $this->critical(
                sprintf(__('Event system has critical issues: %s', 'nhk-event-manager'), implode(', ', $issues)),
                $details,
                $metrics
            );
        }
    }
    
    /**
     * Check database health
     * 
     * @return array Database health status
     */
    protected function check_database_health(): array {
        global $wpdb;
        
        $start_time = microtime(true);
        
        if (!$this->check_database()) {
            return [
                'healthy' => false,
                'message' => __('Database connection failed', 'nhk-event-manager'),
                'metrics' => [],
            ];
        }
        
        $query_time = microtime(true) - $start_time;
        
        // Check for event posts
        $event_count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} 
            WHERE post_type = 'nhk_event'
        ");
        
        // Check for orphaned meta
        $orphaned_meta = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} pm
            LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.ID IS NULL
            AND pm.meta_key LIKE 'event_%'
        ");
        
        return [
            'healthy' => true,
            'message' => __('Database is accessible', 'nhk-event-manager'),
            'metrics' => [
                'connection_time_ms' => round($query_time * 1000, 2),
                'event_count' => (int) $event_count,
                'orphaned_meta' => (int) $orphaned_meta,
            ],
        ];
    }
    
    /**
     * Check event data integrity
     * 
     * @return array Data integrity status
     */
    protected function check_event_data_integrity(): array {
        global $wpdb;
        
        $issues = [];
        $metrics = [];
        
        // Check for events without start dates
        $events_without_dates = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'event_start_date'
            WHERE p.post_type = 'nhk_event'
            AND p.post_status = 'publish'
            AND (pm.meta_value IS NULL OR pm.meta_value = '')
        ");
        
        if ($events_without_dates > 0) {
            $issues[] = sprintf(
                __('%d events are missing start dates', 'nhk-event-manager'),
                $events_without_dates
            );
        }
        
        $metrics['events_without_dates'] = (int) $events_without_dates;
        
        // Check for events with invalid dates
        $invalid_dates = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_type = 'nhk_event'
            AND pm.meta_key = 'event_start_date'
            AND pm.meta_value != ''
            AND STR_TO_DATE(pm.meta_value, '%Y-%m-%d') IS NULL
        ");
        
        if ($invalid_dates > 0) {
            $issues[] = sprintf(
                __('%d events have invalid date formats', 'nhk-event-manager'),
                $invalid_dates
            );
        }
        
        $metrics['invalid_dates'] = (int) $invalid_dates;
        
        // Check for events with end dates before start dates
        $invalid_date_ranges = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} pm_start
            INNER JOIN {$wpdb->postmeta} pm_end ON pm_start.post_id = pm_end.post_id
            INNER JOIN {$wpdb->posts} p ON pm_start.post_id = p.ID
            WHERE p.post_type = 'nhk_event'
            AND pm_start.meta_key = 'event_start_date'
            AND pm_end.meta_key = 'event_end_date'
            AND pm_start.meta_value > pm_end.meta_value
            AND pm_end.meta_value != ''
        ");
        
        if ($invalid_date_ranges > 0) {
            $issues[] = sprintf(
                __('%d events have end dates before start dates', 'nhk-event-manager'),
                $invalid_date_ranges
            );
        }
        
        $metrics['invalid_date_ranges'] = (int) $invalid_date_ranges;
        
        return [
            'issues' => $issues,
            'metrics' => $metrics,
        ];
    }
    
    /**
     * Check WordPress requirements
     * 
     * @return array WordPress requirements status
     */
    protected function check_wordpress_requirements(): array {
        $issues = [];
        $details = [];
        
        // Check WordPress version
        $wp_version = $this->get_wp_version();
        $min_wp_version = '6.0';
        
        if (version_compare($wp_version, $min_wp_version, '<')) {
            $issues[] = sprintf(
                __('WordPress version %s is below minimum required version %s', 'nhk-event-manager'),
                $wp_version,
                $min_wp_version
            );
        }
        
        $details['wordpress_version'] = $wp_version;
        
        // Check PHP version
        $php_version = $this->get_php_version();
        $min_php_version = '8.0';
        
        if (version_compare($php_version, $min_php_version, '<')) {
            $issues[] = sprintf(
                __('PHP version %s is below minimum required version %s', 'nhk-event-manager'),
                $php_version,
                $min_php_version
            );
        }
        
        $details['php_version'] = $php_version;
        
        // Check required WordPress functions
        $required_functions = [
            'register_post_type',
            'register_taxonomy',
            'add_meta_box',
            'wp_enqueue_script',
            'wp_enqueue_style',
        ];
        
        $missing_functions = [];
        foreach ($required_functions as $function) {
            if (!$this->wp_function_exists($function)) {
                $missing_functions[] = $function;
            }
        }
        
        if (!empty($missing_functions)) {
            $issues[] = sprintf(
                __('Missing required WordPress functions: %s', 'nhk-event-manager'),
                implode(', ', $missing_functions)
            );
        }
        
        $details['required_functions'] = count($required_functions) - count($missing_functions);
        
        return [
            'healthy' => empty($issues),
            'message' => empty($issues) 
                ? __('WordPress requirements are met', 'nhk-event-manager')
                : implode(', ', $issues),
            'details' => $details,
        ];
    }
    
    /**
     * Check file permissions
     * 
     * @return array File permissions status
     */
    protected function check_file_permissions(): array {
        $issues = [];
        $details = [];
        
        // Check plugin directory permissions
        $plugin_dir = NHK_EVENT_MANAGER_PATH;
        $uploads_dir = wp_upload_dir()['basedir'];
        
        $directories_to_check = [
            'plugin_directory' => $plugin_dir,
            'uploads_directory' => $uploads_dir,
            'wp_content' => WP_CONTENT_DIR,
        ];
        
        foreach ($directories_to_check as $name => $directory) {
            $permissions = $this->get_file_permissions($directory);
            $is_writable = $this->is_directory_writable($directory);
            
            $details[$name] = [
                'path' => $directory,
                'permissions' => $permissions,
                'writable' => $is_writable,
            ];
            
            if ($name === 'uploads_directory' && !$is_writable) {
                $issues[] = sprintf(
                    __('Uploads directory is not writable: %s', 'nhk-event-manager'),
                    $directory
                );
            }
        }
        
        return [
            'healthy' => empty($issues),
            'message' => empty($issues) 
                ? __('File permissions are correct', 'nhk-event-manager')
                : implode(', ', $issues),
            'details' => $details,
        ];
    }
}
