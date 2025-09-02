<?php
/**
 * Event Cache Service
 * 
 * Demonstrates caching implementation using WordPress transients and object cache.
 * Shows proper cache management and invalidation strategies.
 * 
 * @package NHK\EventManager\Services
 * @since 1.0.0
 */

namespace NHK\EventManager\Services;

use NHK\Framework\Container\Container;

/**
 * Event Cache Service Class
 * 
 * Demonstrates:
 * - WordPress transients API usage
 * - Object cache integration
 * - Cache invalidation strategies
 * - Performance optimization
 * - Cache key management
 */
class EventCacheService {
    
    /**
     * Service container
     * 
     * @var Container
     */
    protected Container $container;
    
    /**
     * Cache prefix
     * 
     * @var string
     */
    protected string $cache_prefix = 'nhk_event_';
    
    /**
     * Default cache duration in seconds
     * 
     * @var int
     */
    protected int $default_duration = 3600; // 1 hour
    
    /**
     * Constructor
     * 
     * @param Container $container Service container
     */
    public function __construct(Container $container) {
        $this->container = $container;
        
        // Get cache duration from settings
        $this->default_duration = get_option('nhk_event_cache_duration', 3600);
    }
    
    /**
     * Get cached data
     * 
     * Demonstrates WordPress transient usage with fallback to object cache.
     * 
     * @param string $key Cache key
     * @return mixed Cached data or false if not found
     */
    public function get(string $key) {
        $cache_key = $this->get_cache_key($key);
        
        // Try transient first (persistent cache)
        $data = get_transient($cache_key);
        
        if ($data === false) {
            // Try object cache (memory cache)
            $data = wp_cache_get($cache_key, 'nhk_events');
        }
        
        return $data;
    }
    
    /**
     * Set cached data
     * 
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @param int|null $duration Cache duration in seconds
     * @return bool Success status
     */
    public function set(string $key, $data, ?int $duration = null): bool {
        $cache_key = $this->get_cache_key($key);
        $duration = $duration ?? $this->default_duration;
        
        // Set transient (persistent cache)
        $transient_result = set_transient($cache_key, $data, $duration);
        
        // Set object cache (memory cache)
        $object_cache_result = wp_cache_set($cache_key, $data, 'nhk_events', $duration);
        
        return $transient_result && $object_cache_result;
    }
    
    /**
     * Delete cached data
     * 
     * @param string $key Cache key
     * @return bool Success status
     */
    public function delete(string $key): bool {
        $cache_key = $this->get_cache_key($key);
        
        // Delete transient
        $transient_result = delete_transient($cache_key);
        
        // Delete from object cache
        $object_cache_result = wp_cache_delete($cache_key, 'nhk_events');
        
        return $transient_result || $object_cache_result;
    }
    
    /**
     * Flush all event-related caches
     * 
     * Demonstrates cache group invalidation.
     * 
     * @return bool Success status
     */
    public function flush_event_cache(): bool {
        global $wpdb;
        
        // Delete all event-related transients
        $transient_keys = $wpdb->get_col($wpdb->prepare("
            SELECT option_name 
            FROM {$wpdb->options} 
            WHERE option_name LIKE %s
        ", '_transient_' . $this->cache_prefix . '%'));
        
        foreach ($transient_keys as $transient_key) {
            $key = str_replace('_transient_', '', $transient_key);
            delete_transient($key);
        }
        
        // Flush object cache group
        wp_cache_flush_group('nhk_events');
        
        // Log cache flush
        if (get_option('nhk_event_enable_debug')) {
            error_log('[NHK Event Manager] Event cache flushed');
        }
        
        return true;
    }
    
    /**
     * Get cache statistics
     * 
     * @return array Cache statistics
     */
    public function get_cache_stats(): array {
        global $wpdb;
        
        // Count transients
        $transient_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->options} 
            WHERE option_name LIKE %s
        ", '_transient_' . $this->cache_prefix . '%'));
        
        // Get cache size (approximate)
        $cache_size = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(LENGTH(option_value)) 
            FROM {$wpdb->options} 
            WHERE option_name LIKE %s
        ", '_transient_' . $this->cache_prefix . '%'));
        
        return [
            'transient_count' => (int) $transient_count,
            'cache_size_bytes' => (int) $cache_size,
            'cache_size_mb' => round($cache_size / 1024 / 1024, 2),
            'default_duration' => $this->default_duration,
        ];
    }
    
    /**
     * Cache event data
     * 
     * Specialized method for caching individual event data.
     * 
     * @param int $event_id Event ID
     * @param array $data Event data
     * @param int|null $duration Cache duration
     * @return bool Success status
     */
    public function cache_event_data(int $event_id, array $data, ?int $duration = null): bool {
        $key = 'event_data_' . $event_id;
        return $this->set($key, $data, $duration);
    }
    
    /**
     * Get cached event data
     * 
     * @param int $event_id Event ID
     * @return array|false Event data or false if not cached
     */
    public function get_event_data(int $event_id) {
        $key = 'event_data_' . $event_id;
        return $this->get($key);
    }
    
    /**
     * Invalidate event data cache
     * 
     * @param int $event_id Event ID
     * @return bool Success status
     */
    public function invalidate_event_data(int $event_id): bool {
        $key = 'event_data_' . $event_id;
        return $this->delete($key);
    }
    
    /**
     * Cache event list
     * 
     * @param string $list_type List type identifier
     * @param array $events Event list
     * @param int|null $duration Cache duration
     * @return bool Success status
     */
    public function cache_event_list(string $list_type, array $events, ?int $duration = null): bool {
        $key = 'event_list_' . $list_type;
        return $this->set($key, $events, $duration);
    }
    
    /**
     * Get cached event list
     * 
     * @param string $list_type List type identifier
     * @return array|false Event list or false if not cached
     */
    public function get_event_list(string $list_type) {
        $key = 'event_list_' . $list_type;
        return $this->get($key);
    }
    
    /**
     * Cache query results
     * 
     * @param string $query_hash Query hash
     * @param array $results Query results
     * @param int|null $duration Cache duration
     * @return bool Success status
     */
    public function cache_query_results(string $query_hash, array $results, ?int $duration = null): bool {
        $key = 'query_' . $query_hash;
        return $this->set($key, $results, $duration);
    }
    
    /**
     * Get cached query results
     * 
     * @param string $query_hash Query hash
     * @return array|false Query results or false if not cached
     */
    public function get_query_results(string $query_hash) {
        $key = 'query_' . $query_hash;
        return $this->get($key);
    }
    
    /**
     * Generate cache key
     * 
     * @param string $key Base key
     * @return string Full cache key
     */
    protected function get_cache_key(string $key): string {
        return $this->cache_prefix . $key;
    }
    
    /**
     * Generate query hash
     * 
     * Creates a hash for query arguments to use as cache key.
     * 
     * @param array $args Query arguments
     * @return string Query hash
     */
    public function generate_query_hash(array $args): string {
        // Sort args to ensure consistent hash
        ksort($args);
        return md5(serialize($args));
    }
    
    /**
     * Warm up cache
     * 
     * Pre-populate cache with commonly accessed data.
     * 
     * @return bool Success status
     */
    public function warm_up_cache(): bool {
        try {
            // Cache upcoming events
            $upcoming_events = get_posts([
                'post_type' => 'nhk_event',
                'post_status' => 'publish',
                'posts_per_page' => 10,
                'meta_key' => 'event_start_date',
                'orderby' => 'meta_value',
                'order' => 'ASC',
                'meta_query' => [
                    [
                        'key' => 'event_start_date',
                        'value' => current_time('Y-m-d'),
                        'compare' => '>=',
                        'type' => 'DATE',
                    ],
                ],
            ]);
            
            $this->cache_event_list('upcoming', $upcoming_events, 3600);
            
            // Cache event categories
            $categories = get_terms([
                'taxonomy' => 'nhk_event_category',
                'hide_empty' => false,
            ]);
            
            $this->set('event_categories', $categories, 7200); // 2 hours
            
            // Cache event venues
            $venues = get_terms([
                'taxonomy' => 'nhk_event_venue',
                'hide_empty' => false,
            ]);
            
            $this->set('event_venues', $venues, 7200);
            
            return true;
        } catch (\Exception $e) {
            if (get_option('nhk_event_enable_debug')) {
                error_log('[NHK Event Manager] Cache warm-up failed: ' . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Schedule cache cleanup
     * 
     * Removes expired cache entries.
     * 
     * @return bool Success status
     */
    public function cleanup_expired_cache(): bool {
        global $wpdb;
        
        try {
            // Clean up expired transients
            $expired_transients = $wpdb->get_col($wpdb->prepare("
                SELECT REPLACE(option_name, '_transient_timeout_', '') as transient_name
                FROM {$wpdb->options} 
                WHERE option_name LIKE %s 
                AND option_value < %d
            ", '_transient_timeout_' . $this->cache_prefix . '%', time()));
            
            foreach ($expired_transients as $transient_name) {
                delete_transient($transient_name);
            }
            
            if (get_option('nhk_event_enable_debug')) {
                error_log(sprintf(
                    '[NHK Event Manager] Cleaned up %d expired cache entries',
                    count($expired_transients)
                ));
            }
            
            return true;
        } catch (\Exception $e) {
            if (get_option('nhk_event_enable_debug')) {
                error_log('[NHK Event Manager] Cache cleanup failed: ' . $e->getMessage());
            }
            return false;
        }
    }
}
