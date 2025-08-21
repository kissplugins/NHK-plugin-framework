<?php
/**
 * PQS cache integration service.
 *
 * @package SBI\Services
 */

namespace SBI\Services;

/**
 * Handles read-only access to PQS cache.
 */
class PQSIntegration {
    /**
     * Retrieve PQS cache from WordPress transient store.
     *
     * @return array
     */
    public function get_cache(): array {
        $cache = get_transient('pqs_cache');
        return is_array($cache) ? $cache : [];
    }
}
