<?php
/**
 * Event Query Service
 * 
 * Demonstrates service layer implementation for complex event queries.
 * Shows proper use of WordPress query functions and caching.
 * 
 * @package NHK\EventManager\Services
 * @since 1.0.0
 */

namespace NHK\EventManager\Services;

use NHK\Framework\Container\Container;

/**
 * Event Query Service Class
 * 
 * Demonstrates:
 * - Service layer pattern
 * - Complex WordPress queries
 * - Query optimization
 * - Caching integration
 * - Business logic separation
 */
class EventQueryService {
    
    /**
     * Service container
     * 
     * @var Container
     */
    protected Container $container;
    
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
    public function __construct(Container $container, EventCacheService $cache_service) {
        $this->container = $container;
        $this->cache_service = $cache_service;
    }
    
    /**
     * Get upcoming events
     * 
     * Demonstrates date-based queries with meta_query.
     * 
     * @param int $limit Number of events to return
     * @param array $args Additional query arguments
     * @return array Event posts
     */
    public function get_upcoming_events(int $limit = 10, array $args = []): array {
        $cache_key = 'upcoming_events_' . md5(serialize([$limit, $args]));
        
        // Try to get from cache first
        $cached = $this->cache_service->get($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        $default_args = [
            'post_type' => 'nhk_event',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
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
        ];
        
        $query_args = array_merge($default_args, $args);
        $query = new \WP_Query($query_args);
        
        // Cache the results
        $this->cache_service->set($cache_key, $query->posts, 3600); // 1 hour
        
        return $query->posts;
    }
    
    /**
     * Get events by date range
     * 
     * @param string $start_date Start date (Y-m-d format)
     * @param string $end_date End date (Y-m-d format)
     * @param array $args Additional query arguments
     * @return array Event posts
     */
    public function get_events_by_date_range(string $start_date, string $end_date, array $args = []): array {
        $cache_key = 'events_date_range_' . md5($start_date . $end_date . serialize($args));
        
        $cached = $this->cache_service->get($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        $default_args = [
            'post_type' => 'nhk_event',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_key' => 'event_start_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'event_start_date',
                    'value' => $start_date,
                    'compare' => '>=',
                    'type' => 'DATE',
                ],
                [
                    'key' => 'event_start_date',
                    'value' => $end_date,
                    'compare' => '<=',
                    'type' => 'DATE',
                ],
            ],
        ];
        
        $query_args = array_merge($default_args, $args);
        $query = new \WP_Query($query_args);
        
        $this->cache_service->set($cache_key, $query->posts, 1800); // 30 minutes
        
        return $query->posts;
    }
    
    /**
     * Get events by category
     * 
     * Demonstrates taxonomy queries.
     * 
     * @param string|array $categories Category slug(s)
     * @param array $args Additional query arguments
     * @return array Event posts
     */
    public function get_events_by_category($categories, array $args = []): array {
        $cache_key = 'events_category_' . md5(serialize($categories) . serialize($args));
        
        $cached = $this->cache_service->get($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        $default_args = [
            'post_type' => 'nhk_event',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_key' => 'event_start_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'tax_query' => [
                [
                    'taxonomy' => 'nhk_event_category',
                    'field' => 'slug',
                    'terms' => $categories,
                ],
            ],
        ];
        
        $query_args = array_merge($default_args, $args);
        $query = new \WP_Query($query_args);
        
        $this->cache_service->set($cache_key, $query->posts, 1800);
        
        return $query->posts;
    }
    
    /**
     * Get events by venue
     * 
     * @param string|array $venues Venue slug(s)
     * @param array $args Additional query arguments
     * @return array Event posts
     */
    public function get_events_by_venue($venues, array $args = []): array {
        $cache_key = 'events_venue_' . md5(serialize($venues) . serialize($args));
        
        $cached = $this->cache_service->get($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        $default_args = [
            'post_type' => 'nhk_event',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_key' => 'event_start_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'tax_query' => [
                [
                    'taxonomy' => 'nhk_event_venue',
                    'field' => 'slug',
                    'terms' => $venues,
                ],
            ],
        ];
        
        $query_args = array_merge($default_args, $args);
        $query = new \WP_Query($query_args);
        
        $this->cache_service->set($cache_key, $query->posts, 1800);
        
        return $query->posts;
    }
    
    /**
     * Search events
     * 
     * Demonstrates search functionality with meta field inclusion.
     * 
     * @param string $search_term Search term
     * @param array $args Additional query arguments
     * @return array Event posts
     */
    public function search_events(string $search_term, array $args = []): array {
        $cache_key = 'events_search_' . md5($search_term . serialize($args));
        
        $cached = $this->cache_service->get($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        $default_args = [
            'post_type' => 'nhk_event',
            'post_status' => 'publish',
            'posts_per_page' => 50,
            's' => $search_term,
            'meta_key' => 'event_start_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
        ];
        
        $query_args = array_merge($default_args, $args);
        
        // Add meta search using posts_where filter
        add_filter('posts_where', [$this, 'extend_search_to_meta'], 10, 2);
        
        $query = new \WP_Query($query_args);
        
        // Remove the filter
        remove_filter('posts_where', [$this, 'extend_search_to_meta'], 10);
        
        $this->cache_service->set($cache_key, $query->posts, 900); // 15 minutes
        
        return $query->posts;
    }
    
    /**
     * Get events calendar data
     * 
     * Returns events organized by date for calendar display.
     * 
     * @param string $month Month in Y-m format
     * @return array Calendar data
     */
    public function get_calendar_events(string $month): array {
        $cache_key = 'events_calendar_' . $month;
        
        $cached = $this->cache_service->get($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        $start_date = $month . '-01';
        $end_date = date('Y-m-t', strtotime($start_date));
        
        $events = $this->get_events_by_date_range($start_date, $end_date);
        
        // Organize events by date
        $calendar_data = [];
        foreach ($events as $event) {
            $event_date = get_post_meta($event->ID, 'event_start_date', true);
            if (!isset($calendar_data[$event_date])) {
                $calendar_data[$event_date] = [];
            }
            $calendar_data[$event_date][] = $event;
        }
        
        $this->cache_service->set($cache_key, $calendar_data, 3600);
        
        return $calendar_data;
    }
    
    /**
     * Get event statistics
     * 
     * @return array Statistics data
     */
    public function get_event_statistics(): array {
        $cache_key = 'event_statistics';
        
        $cached = $this->cache_service->get($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        global $wpdb;
        
        // Total events
        $total_events = wp_count_posts('nhk_event')->publish;
        
        // Upcoming events
        $upcoming_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(p.ID) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'nhk_event'
            AND p.post_status = 'publish'
            AND pm.meta_key = 'event_start_date'
            AND pm.meta_value >= %s
        ", current_time('Y-m-d')));
        
        // Past events
        $past_count = $total_events - $upcoming_count;
        
        // Events this month
        $this_month = current_time('Y-m');
        $month_start = $this_month . '-01';
        $month_end = date('Y-m-t', strtotime($month_start));
        
        $this_month_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(p.ID) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'nhk_event'
            AND p.post_status = 'publish'
            AND pm.meta_key = 'event_start_date'
            AND pm.meta_value BETWEEN %s AND %s
        ", $month_start, $month_end));
        
        $statistics = [
            'total_events' => (int) $total_events,
            'upcoming_events' => (int) $upcoming_count,
            'past_events' => (int) $past_count,
            'events_this_month' => (int) $this_month_count,
        ];
        
        $this->cache_service->set($cache_key, $statistics, 1800);
        
        return $statistics;
    }
    
    /**
     * Extend search to include meta fields
     * 
     * WordPress filter callback to search in meta fields.
     * 
     * @param string $where WHERE clause
     * @param \WP_Query $query Query object
     * @return string Modified WHERE clause
     */
    public function extend_search_to_meta(string $where, \WP_Query $query): string {
        global $wpdb;
        
        if (!$query->is_search() || $query->get('post_type') !== 'nhk_event') {
            return $where;
        }
        
        $search_term = $query->get('s');
        if (empty($search_term)) {
            return $where;
        }
        
        // Add meta field search
        $meta_where = $wpdb->prepare("
            OR EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm 
                WHERE pm.post_id = {$wpdb->posts}.ID 
                AND pm.meta_key IN ('event_venue', 'event_organizer', 'event_address')
                AND pm.meta_value LIKE %s
            )
        ", '%' . $wpdb->esc_like($search_term) . '%');
        
        $where = preg_replace('/\)\s*$/', ') ' . $meta_where . ' )', $where);
        
        return $where;
    }
}
