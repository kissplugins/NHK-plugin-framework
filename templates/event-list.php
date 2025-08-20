<?php
/**
 * Event List Template
 * 
 * Template for displaying event lists via shortcode.
 * This file demonstrates proper WordPress template structure.
 * 
 * @package NHK\EventManager
 * @since 1.0.0
 * 
 * Available variables:
 * @var array $events Array of event post objects
 * @var array $attributes Shortcode attributes
 * @var int $total_events Total number of events
 * @var bool $has_events Whether events were found
 * @var string $no_events_text Text to show when no events found
 * @var string $wrapper_class CSS wrapper class
 */

// Prevent direct access
defined('ABSPATH') || exit;

?>

<div class="<?php echo esc_attr($wrapper_class); ?>">
    
    <?php if (!empty($attributes['title'])): ?>
        <h2 class="nhk-events-title"><?php echo esc_html($attributes['title']); ?></h2>
    <?php endif; ?>
    
    <?php if ($attributes['show_filters']): ?>
        <div class="nhk-events-filters">
            <form class="nhk-event-filter-form" method="get">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="event-category-filter"><?php esc_html_e('Category:', 'nhk-event-manager'); ?></label>
                        <select id="event-category-filter" name="event_category">
                            <option value=""><?php esc_html_e('All Categories', 'nhk-event-manager'); ?></option>
                            <?php
                            $categories = get_terms([
                                'taxonomy' => 'nhk_event_category',
                                'hide_empty' => true,
                            ]);
                            foreach ($categories as $category):
                            ?>
                                <option value="<?php echo esc_attr($category->slug); ?>">
                                    <?php echo esc_html($category->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="event-venue-filter"><?php esc_html_e('Venue:', 'nhk-event-manager'); ?></label>
                        <select id="event-venue-filter" name="event_venue">
                            <option value=""><?php esc_html_e('All Venues', 'nhk-event-manager'); ?></option>
                            <?php
                            $venues = get_terms([
                                'taxonomy' => 'nhk_event_venue',
                                'hide_empty' => true,
                            ]);
                            foreach ($venues as $venue):
                            ?>
                                <option value="<?php echo esc_attr($venue->slug); ?>">
                                    <?php echo esc_html($venue->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="event-date-filter"><?php esc_html_e('Date:', 'nhk-event-manager'); ?></label>
                        <input type="date" id="event-date-filter" name="event_date" />
                    </div>
                    
                    <div class="filter-group">
                        <button type="submit" class="filter-button">
                            <?php esc_html_e('Filter Events', 'nhk-event-manager'); ?>
                        </button>
                        <button type="button" class="clear-filters-button">
                            <?php esc_html_e('Clear', 'nhk-event-manager'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    <?php endif; ?>
    
    <?php if ($has_events): ?>
        
        <div class="nhk-events-container">
            
            <?php if ($attributes['layout'] === 'table'): ?>
                
                <table class="nhk-events-table">
                    <thead>
                        <tr>
                            <?php if ($attributes['show_title']): ?>
                                <th class="event-title"><?php esc_html_e('Event', 'nhk-event-manager'); ?></th>
                            <?php endif; ?>
                            <?php if ($attributes['show_date']): ?>
                                <th class="event-date"><?php esc_html_e('Date', 'nhk-event-manager'); ?></th>
                            <?php endif; ?>
                            <?php if ($attributes['show_venue']): ?>
                                <th class="event-venue"><?php esc_html_e('Venue', 'nhk-event-manager'); ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                            <tr class="event-row">
                                <?php if ($attributes['show_title']): ?>
                                    <td class="event-title">
                                        <a href="<?php echo esc_url(get_permalink($event->ID)); ?>">
                                            <?php echo esc_html($event->post_title); ?>
                                        </a>
                                    </td>
                                <?php endif; ?>
                                <?php if ($attributes['show_date']): ?>
                                    <td class="event-date">
                                        <?php echo esc_html($this->format_event_date($event, $attributes)); ?>
                                    </td>
                                <?php endif; ?>
                                <?php if ($attributes['show_venue']): ?>
                                    <td class="event-venue">
                                        <?php echo esc_html(get_post_meta($event->ID, 'event_venue', true)); ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
            <?php else: ?>
                
                <div class="nhk-events-<?php echo esc_attr($attributes['layout']); ?>">
                    <?php foreach ($events as $event): ?>
                        
                        <article class="nhk-event-item">
                            
                            <?php if ($attributes['show_image'] && has_post_thumbnail($event->ID)): ?>
                                <div class="event-image">
                                    <a href="<?php echo esc_url(get_permalink($event->ID)); ?>">
                                        <?php echo get_the_post_thumbnail($event->ID, $attributes['image_size']); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <div class="event-content">
                                
                                <?php if ($attributes['show_title']): ?>
                                    <h3 class="event-title">
                                        <a href="<?php echo esc_url(get_permalink($event->ID)); ?>">
                                            <?php echo esc_html($event->post_title); ?>
                                        </a>
                                    </h3>
                                <?php endif; ?>
                                
                                <?php if ($attributes['show_date']): ?>
                                    <div class="event-date">
                                        <time datetime="<?php echo esc_attr(get_post_meta($event->ID, 'event_start_date', true)); ?>">
                                            <?php echo esc_html($this->format_event_date($event, $attributes)); ?>
                                        </time>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($attributes['show_venue']): ?>
                                    <?php $venue = get_post_meta($event->ID, 'event_venue', true); ?>
                                    <?php if ($venue): ?>
                                        <div class="event-venue">
                                            <span class="venue-label"><?php esc_html_e('Venue:', 'nhk-event-manager'); ?></span>
                                            <span class="venue-name"><?php echo esc_html($venue); ?></span>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php if ($attributes['show_excerpt']): ?>
                                    <div class="event-excerpt">
                                        <?php
                                        if ($event->post_excerpt) {
                                            echo wp_kses_post($event->post_excerpt);
                                        } else {
                                            echo wp_kses_post(wp_trim_words($event->post_content, 20));
                                        }
                                        ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="event-meta">
                                    <?php
                                    // Display event categories
                                    $categories = get_the_terms($event->ID, 'nhk_event_category');
                                    if ($categories && !is_wp_error($categories)):
                                    ?>
                                        <div class="event-categories">
                                            <?php foreach ($categories as $category): ?>
                                                <span class="event-category"><?php echo esc_html($category->name); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="event-actions">
                                        <a href="<?php echo esc_url(get_permalink($event->ID)); ?>" class="event-link">
                                            <?php esc_html_e('View Details', 'nhk-event-manager'); ?>
                                        </a>
                                        
                                        <?php
                                        $registration_url = get_post_meta($event->ID, 'event_registration_url', true);
                                        if ($registration_url):
                                        ?>
                                            <a href="<?php echo esc_url($registration_url); ?>" class="event-register" target="_blank" rel="noopener">
                                                <?php esc_html_e('Register', 'nhk-event-manager'); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                            </div>
                            
                        </article>
                        
                    <?php endforeach; ?>
                </div>
                
            <?php endif; ?>
            
        </div>
        
        <?php if ($attributes['pagination']): ?>
            <div class="nhk-events-pagination">
                <!-- Pagination would be implemented here -->
                <p class="events-count">
                    <?php
                    printf(
                        esc_html__('Showing %d events', 'nhk-event-manager'),
                        $total_events
                    );
                    ?>
                </p>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        
        <div class="nhk-events-empty">
            <p class="no-events-message"><?php echo esc_html($no_events_text); ?></p>
        </div>
        
    <?php endif; ?>
    
</div>

<?php
/**
 * Helper function to format event date
 * This would typically be in the shortcode class, but included here for template completeness
 */
if (!function_exists('format_event_date')) {
    function format_event_date($event, $attributes) {
        $start_date = get_post_meta($event->ID, 'event_start_date', true);
        $end_date = get_post_meta($event->ID, 'event_end_date', true);
        $start_time = get_post_meta($event->ID, 'event_start_time', true);
        $end_time = get_post_meta($event->ID, 'event_end_time', true);
        $all_day = get_post_meta($event->ID, 'event_all_day', true);
        
        if (!$start_date) {
            return '';
        }
        
        $date_format = !empty($attributes['date_format']) ? $attributes['date_format'] : get_option('date_format');
        $time_format = !empty($attributes['time_format']) ? $attributes['time_format'] : get_option('time_format');
        
        $formatted_date = date_i18n($date_format, strtotime($start_date));
        
        if ($end_date && $end_date !== $start_date) {
            $formatted_date .= ' - ' . date_i18n($date_format, strtotime($end_date));
        }
        
        if (!$all_day && $start_time) {
            $formatted_date .= ' ' . date_i18n($time_format, strtotime($start_time));
            
            if ($end_time && $end_time !== $start_time) {
                $formatted_date .= ' - ' . date_i18n($time_format, strtotime($end_time));
            }
        }
        
        return $formatted_date;
    }
}
?>
