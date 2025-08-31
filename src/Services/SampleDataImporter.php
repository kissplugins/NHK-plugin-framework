<?php
/**
 * Sample Data Importer Service
 * 
 * Handles importing sample event data from JSON file to demonstrate
 * the Event Manager functionality with realistic data.
 * 
 * @package NHK\EventManager\Services
 * @since 1.0.0
 */

namespace NHK\EventManager\Services;

use NHK\Framework\Container\Container;

/**
 * Sample Data Importer Class
 * 
 * Demonstrates data import functionality and populates the Event Manager
 * with realistic sample data for demonstration purposes.
 */
class SampleDataImporter {
    
    /**
     * Service container
     * 
     * @var Container
     */
    protected Container $container;
    
    /**
     * Sample data file path
     * 
     * @var string
     */
    protected string $sample_data_file;
    
    /**
     * Constructor
     * 
     * @param Container $container Service container
     */
    public function __construct(Container $container) {
        $this->container = $container;
        $this->sample_data_file = NHK_EVENT_MANAGER_PATH . 'nhk-events-data-import.json';
    }
    
    /**
     * Import sample data from JSON file
     * 
     * @return array Import results with success/error information
     */
    public function import_sample_data(): array {
        $results = [
            'success' => false,
            'imported' => 0,
            'skipped' => 0,
            'errors' => [],
            'events' => []
        ];
        
        try {
            // Check if file exists
            if (!\file_exists($this->sample_data_file)) {
                $results['errors'][] = 'Sample data file not found: nhk-events-data-import.json';
                return $results;
            }
            
            // Read and decode JSON
            $json_content = \file_get_contents($this->sample_data_file);
            $data = \json_decode($json_content, true);
            
            if (\json_last_error() !== JSON_ERROR_NONE) {
                $results['errors'][] = 'Invalid JSON format: ' . \json_last_error_msg();
                return $results;
            }
            
            if (!isset($data['events']) || !\is_array($data['events'])) {
                $results['errors'][] = 'No events array found in JSON data';
                return $results;
            }
            
            // Import each event
            foreach ($data['events'] as $event_data) {
                $import_result = $this->import_single_event($event_data);
                
                if ($import_result['success']) {
                    $results['imported']++;
                    $results['events'][] = $import_result['event'];
                } else {
                    $results['skipped']++;
                    $results['errors'] = \array_merge($results['errors'], $import_result['errors']);
                }
            }
            
            $results['success'] = $results['imported'] > 0;
            
        } catch (\Exception $e) {
            $results['errors'][] = 'Import failed: ' . $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Import a single event
     * 
     * @param array $event_data Raw event data from JSON
     * @return array Import result for this event
     */
    protected function import_single_event(array $event_data): array {
        $result = [
            'success' => false,
            'event' => null,
            'errors' => []
        ];
        
        try {
            // Validate required fields
            if (empty($event_data['title'])) {
                $result['errors'][] = 'Event title is required';
                return $result;
            }
            
            if (empty($event_data['date'])) {
                $result['errors'][] = 'Event date is required';
                return $result;
            }
            
            // Check if event already exists (by title and date)
            $existing_event = $this->find_existing_event($event_data['title'], $event_data['date']);
            if ($existing_event) {
                $result['errors'][] = 'Event already exists: ' . $event_data['title'];
                return $result;
            }
            
            // Transform data to our CPT structure
            $transformed_data = $this->transform_event_data($event_data);
            
            // Create WordPress post
            $post_id = \wp_insert_post([
                'post_title' => $transformed_data['title'],
                'post_content' => $transformed_data['content'],
                'post_status' => 'publish',
                'post_type' => 'nhk_event',
                'post_author' => \get_current_user_id() ?: 1,
                'meta_input' => [
                    '_nhk_event_start_date' => $transformed_data['start_date'],
                    '_nhk_event_end_date' => $transformed_data['end_date'],
                    '_nhk_event_start_time' => $transformed_data['start_time'],
                    '_nhk_event_end_time' => $transformed_data['end_time'],
                    '_nhk_event_venue' => $transformed_data['venue'],
                    '_nhk_event_capacity' => $transformed_data['capacity'],
                    '_nhk_event_organizer_name' => $transformed_data['organizer_name'],
                    '_nhk_event_organizer_email' => $transformed_data['organizer_email'],
                    '_nhk_event_price' => $transformed_data['price'],
                    '_nhk_event_registration_url' => $transformed_data['registration_url'],
                ]
            ]);
            
            if (\is_wp_error($post_id)) {
                $result['errors'][] = 'Failed to create event: ' . $post_id->get_error_message();
                return $result;
            }
            
            // Set event categories
            if (!empty($transformed_data['categories'])) {
                \wp_set_object_terms($post_id, $transformed_data['categories'], 'nhk_event_category');
            }
            
            $result['success'] = true;
            $result['event'] = [
                'id' => $post_id,
                'title' => $transformed_data['title'],
                'date' => $transformed_data['start_date'],
                'instructor' => $transformed_data['organizer_name']
            ];
            
        } catch (\Exception $e) {
            $result['errors'][] = 'Error importing event: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Transform raw event data to our CPT structure
     * 
     * @param array $event_data Raw event data
     * @return array Transformed data ready for WordPress
     */
    protected function transform_event_data(array $event_data): array {
        // Calculate end time (2 hours after start time)
        $start_time = $this->normalize_time($event_data['start_time'] ?? '10:00 AM');
        $end_time = $this->calculate_end_time($start_time, 2); // 2 hours duration
        
        return [
            'title' => \sanitize_text_field($event_data['title']),
            'content' => \wp_kses_post($event_data['subject'] ?? ''),
            'start_date' => \sanitize_text_field($event_data['date']),
            'end_date' => \sanitize_text_field($event_data['date']), // Same day event
            'start_time' => $start_time,
            'end_time' => $end_time,
            'venue' => 'Online Training Platform', // Default venue
            'capacity' => 25, // Default capacity for workshops
            'organizer_name' => \sanitize_text_field($event_data['instructor'] ?? 'NHK Training Team'),
            'organizer_email' => 'training@nhkode.com', // Default email
            'price' => '0.00', // Free workshops
            'registration_url' => '', // No external registration
            'categories' => ['WordPress Training'], // Default category
        ];
    }
    
    /**
     * Normalize time format to 24-hour format
     * 
     * @param string $time_string Time in various formats
     * @return string Time in HH:MM format
     */
    protected function normalize_time(string $time_string): string {
        try {
            $time = \DateTime::createFromFormat('g:i A', $time_string);
            if ($time === false) {
                // Try alternative format
                $time = \DateTime::createFromFormat('H:i', $time_string);
            }
            
            if ($time === false) {
                return '10:00'; // Default fallback
            }
            
            return $time->format('H:i');
        } catch (\Exception $e) {
            return '10:00'; // Default fallback
        }
    }
    
    /**
     * Calculate end time by adding hours to start time
     * 
     * @param string $start_time Start time in HH:MM format
     * @param int $duration_hours Duration in hours
     * @return string End time in HH:MM format
     */
    protected function calculate_end_time(string $start_time, int $duration_hours): string {
        try {
            $time = \DateTime::createFromFormat('H:i', $start_time);
            if ($time === false) {
                return '12:00'; // Default fallback
            }
            
            $time->add(new \DateInterval('PT' . $duration_hours . 'H'));
            return $time->format('H:i');
        } catch (\Exception $e) {
            return '12:00'; // Default fallback
        }
    }
    
    /**
     * Find existing event by title and date
     * 
     * @param string $title Event title
     * @param string $date Event date
     * @return \WP_Post|null Existing post or null
     */
    protected function find_existing_event(string $title, string $date): ?\WP_Post {
        $posts = \get_posts([
            'post_type' => 'nhk_event',
            'post_status' => 'any',
            'title' => $title,
            'meta_query' => [
                [
                    'key' => '_nhk_event_start_date',
                    'value' => $date,
                    'compare' => '='
                ]
            ],
            'numberposts' => 1
        ]);
        
        return !empty($posts) ? $posts[0] : null;
    }
    
    /**
     * Check if sample data has already been imported
     * 
     * @return bool True if sample data exists
     */
    public function has_sample_data(): bool {
        $events = \get_posts([
            'post_type' => 'nhk_event',
            'post_status' => 'publish',
            'numberposts' => 1,
            'meta_query' => [
                [
                    'key' => '_nhk_event_organizer_email',
                    'value' => 'training@nhkode.com',
                    'compare' => '='
                ]
            ]
        ]);
        
        return !empty($events);
    }
    
    /**
     * Clear all sample data
     * 
     * @return array Results of the clear operation
     */
    public function clear_sample_data(): array {
        $results = [
            'success' => false,
            'deleted' => 0,
            'errors' => []
        ];
        
        try {
            $events = \get_posts([
                'post_type' => 'nhk_event',
                'post_status' => 'any',
                'numberposts' => -1,
                'meta_query' => [
                    [
                        'key' => '_nhk_event_organizer_email',
                        'value' => 'training@nhkode.com',
                        'compare' => '='
                    ]
                ]
            ]);
            
            foreach ($events as $event) {
                if (\wp_delete_post($event->ID, true)) {
                    $results['deleted']++;
                } else {
                    $results['errors'][] = 'Failed to delete event: ' . $event->post_title;
                }
            }
            
            $results['success'] = true;
            
        } catch (\Exception $e) {
            $results['errors'][] = 'Error clearing sample data: ' . $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Get import statistics
     * 
     * @return array Statistics about imported data
     */
    public function get_import_stats(): array {
        $events = \get_posts([
            'post_type' => 'nhk_event',
            'post_status' => 'any',
            'numberposts' => -1,
            'meta_query' => [
                [
                    'key' => '_nhk_event_organizer_email',
                    'value' => 'training@nhkode.com',
                    'compare' => '='
                ]
            ]
        ]);
        
        return [
            'total_events' => \count($events),
            'published_events' => \count(\array_filter($events, function($event) {
                return $event->post_status === 'publish';
            })),
            'has_sample_data' => !empty($events),
            'sample_data_file_exists' => \file_exists($this->sample_data_file)
        ];
    }
}
