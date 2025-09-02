<?php
/**
 * Event Query Builder (shared between REST and SSR)
 *
 * Builds consistent WP_Query args for listing events using the
 * NHK Event Manager conventions and meta keys.
 */

namespace NHK\EventManager\Services;

class EventQueryBuilder {
    /**
     * Build query args from request-like params.
     *
     * Supported params: page, per_page, search|s, category, venue,
     * date_from, date_to, orderby, order
     */
    public static function build_args(array $params = []): array {
        $page = isset($params['page']) ? max(1, (int) $params['page']) : 1;
        $per_page = isset($params['per_page']) ? max(1, (int) $params['per_page']) : 10;

        $args = [
            'post_type'      => 'nhk_event',
            'post_status'    => 'publish',
            'paged'          => $page,
            'posts_per_page' => $per_page,
        ];

        // Search
        $search = $params['search'] ?? $params['s'] ?? '';
        if (is_string($search) && $search !== '') {
            $args['s'] = sanitize_text_field($search);
        }

        // Taxonomies
        $tax_query = [];
        if (!empty($params['category'])) {
            $cat = is_array($params['category']) ? $params['category'] : [$params['category']];
            $tax_query[] = [
                'taxonomy' => 'nhk_event_category',
                'field'    => (self::is_all_numeric($cat) ? 'term_id' : 'slug'),
                'terms'    => $cat,
            ];
        }
        if (!empty($params['venue'])) {
            $ven = is_array($params['venue']) ? $params['venue'] : [$params['venue']];
            $tax_query[] = [
                'taxonomy' => 'nhk_event_venue',
                'field'    => (self::is_all_numeric($ven) ? 'term_id' : 'slug'),
                'terms'    => $ven,
            ];
        }
        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        // Date filters using plugin meta keys
        $meta_query = [];
        if (!empty($params['date_from'])) {
            $meta_query[] = [
                'key'     => '_nhk_event_start_date',
                'value'   => sanitize_text_field($params['date_from']),
                'compare' => '>=',
                'type'    => 'DATE',
            ];
        }
        if (!empty($params['date_to'])) {
            $meta_query[] = [
                'key'     => '_nhk_event_end_date',
                'value'   => sanitize_text_field($params['date_to']),
                'compare' => '<=',
                'type'    => 'DATE',
            ];
        }
        if (!empty($meta_query)) {
            if (count($meta_query) > 1) {
                $meta_query = array_merge(['relation' => 'AND'], $meta_query);
            }
            $args['meta_query'] = $meta_query;
        }

        // Sorting: default by start date ascending
        $orderby = $params['orderby'] ?? 'meta_value';
        $order = strtoupper($params['order'] ?? 'ASC');
        $order = in_array($order, ['ASC','DESC'], true) ? $order : 'ASC';
        $args['meta_key'] = '_nhk_event_start_date';
        $args['orderby'] = $orderby;
        $args['order'] = $order;

        return $args;
    }

    protected static function is_all_numeric(array $values): bool {
        foreach ($values as $v) {
            if (!is_numeric($v)) return false;
        }
        return !empty($values);
    }
}

