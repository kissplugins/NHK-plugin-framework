<?php
/**
 * Health Check Manager
 * 
 * Coordinates and manages all health checks for the Event Manager plugin.
 * Demonstrates centralized health monitoring and reporting.
 * 
 * @package NHK\EventManager\HealthChecks
 * @since 1.0.0
 */

namespace NHK\EventManager\HealthChecks;

use NHK\Framework\Container\Container;

/**
 * Health Check Manager Class
 * 
 * Demonstrates:
 * - Centralized health check coordination
 * - Automated health monitoring
 * - Report generation and caching
 * - WordPress integration
 */
class HealthCheckManager {
    
    /**
     * Service container
     * 
     * @var Container
     */
    protected Container $container;
    
    /**
     * Registered health checks
     * 
     * @var array
     */
    protected array $health_checks = [];
    
    /**
     * Constructor
     * 
     * @param Container $container Service container
     */
    public function __construct(Container $container) {
        $this->container = $container;
    }
    
    /**
     * Initialize the health check manager
     * 
     * @return void
     */
    public function init(): void {
        // Register default health checks
        $this->register_default_checks();
        
        // Setup WordPress hooks
        add_action('wp_ajax_nhk_run_health_checks', [$this, 'handle_ajax_health_check']);
        add_action('admin_init', [$this, 'maybe_run_scheduled_checks']);
        
        // Schedule daily health checks
        if (!wp_next_scheduled('nhk_daily_health_check')) {
            wp_schedule_event(time(), 'daily', 'nhk_daily_health_check');
        }
        
        add_action('nhk_daily_health_check', [$this, 'run_scheduled_health_checks']);
    }
    
    /**
     * Register default health checks
     * 
     * @return void
     */
    protected function register_default_checks(): void {
        $this->register_health_check('event_system', EventSystemHealthCheck::class);
        $this->register_health_check('performance', PerformanceHealthCheck::class);
    }
    
    /**
     * Register a health check
     * 
     * @param string $name Check name
     * @param string $class_name Check class name
     * @return void
     */
    public function register_health_check(string $name, string $class_name): void {
        $this->health_checks[$name] = $class_name;
    }
    
    /**
     * Run all health checks
     * 
     * @param array $check_names Specific checks to run (empty for all)
     * @return array Health check results
     */
    public function run_health_checks(array $check_names = []): array {
        $results = [];
        $overall_status = 'healthy';
        $start_time = microtime(true);
        
        // Determine which checks to run
        $checks_to_run = empty($check_names) ? array_keys($this->health_checks) : $check_names;
        
        foreach ($checks_to_run as $check_name) {
            if (!isset($this->health_checks[$check_name])) {
                continue;
            }
            
            try {
                $check_instance = $this->create_health_check_instance($check_name);
                $result = $check_instance->run();
                
                $results[$check_name] = $result;
                
                // Update overall status
                if ($result['status'] === 'critical') {
                    $overall_status = 'critical';
                } elseif ($result['status'] === 'warning' && $overall_status !== 'critical') {
                    $overall_status = 'warning';
                }
                
            } catch (\Exception $e) {
                $results[$check_name] = [
                    'check_name' => $check_name,
                    'status' => 'critical',
                    'message' => 'Health check failed: ' . $e->getMessage(),
                    'timestamp' => current_time('Y-m-d H:i:s'),
                    'execution_time_ms' => 0,
                    'details' => [],
                    'metrics' => [],
                ];
                
                $overall_status = 'critical';
            }
        }
        
        $total_time = microtime(true) - $start_time;
        
        $summary = [
            'overall_status' => $overall_status,
            'total_checks' => count($results),
            'healthy_checks' => count(array_filter($results, fn($r) => $r['status'] === 'healthy')),
            'warning_checks' => count(array_filter($results, fn($r) => $r['status'] === 'warning')),
            'critical_checks' => count(array_filter($results, fn($r) => $r['status'] === 'critical')),
            'total_execution_time_ms' => round($total_time * 1000, 2),
            'timestamp' => current_time('Y-m-d H:i:s'),
        ];
        
        $report = [
            'summary' => $summary,
            'checks' => $results,
        ];
        
        // Cache the results
        $this->cache_health_report($report);
        
        // Log critical issues
        if ($overall_status === 'critical') {
            $this->log_critical_issues($results);
        }
        
        return $report;
    }
    
    /**
     * Get cached health report
     * 
     * @return array|false Cached report or false if not found
     */
    public function get_cached_health_report() {
        return get_transient('nhk_event_health_report');
    }
    
    /**
     * Handle AJAX health check request
     * 
     * @return void
     */
    public function handle_ajax_health_check(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'nhk_event_health_check')) {
            wp_die(__('Security check failed', 'nhk-event-manager'));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'nhk-event-manager'));
        }
        
        $check_names = $_POST['checks'] ?? [];
        $results = $this->run_health_checks($check_names);
        
        wp_send_json_success($results);
    }
    
    /**
     * Run scheduled health checks
     * 
     * @return void
     */
    public function run_scheduled_health_checks(): void {
        $results = $this->run_health_checks();
        
        // Send notifications if there are critical issues
        if ($results['summary']['overall_status'] === 'critical') {
            $this->send_health_alert($results);
        }
        
        // Store historical data
        $this->store_health_history($results);
    }
    
    /**
     * Maybe run scheduled checks on admin init
     * 
     * @return void
     */
    public function maybe_run_scheduled_checks(): void {
        // Run checks if cache is expired and user is admin
        if (current_user_can('manage_options') && !$this->get_cached_health_report()) {
            $this->run_health_checks();
        }
    }
    
    /**
     * Create health check instance
     * 
     * @param string $check_name Check name
     * @return object Health check instance
     */
    protected function create_health_check_instance(string $check_name): object {
        $class_name = $this->health_checks[$check_name];
        
        // Use container to resolve dependencies
        return $this->container->get($class_name);
    }
    
    /**
     * Cache health report
     * 
     * @param array $report Health report
     * @return void
     */
    protected function cache_health_report(array $report): void {
        set_transient('nhk_event_health_report', $report, 3600); // 1 hour
    }
    
    /**
     * Log critical issues
     * 
     * @param array $results Health check results
     * @return void
     */
    protected function log_critical_issues(array $results): void {
        $critical_issues = array_filter($results, fn($r) => $r['status'] === 'critical');
        
        foreach ($critical_issues as $check_name => $result) {
            error_log(sprintf(
                '[NHK Event Manager] CRITICAL HEALTH ISSUE - %s: %s',
                $check_name,
                $result['message']
            ));
        }
    }
    
    /**
     * Send health alert email
     * 
     * @param array $results Health check results
     * @return void
     */
    protected function send_health_alert(array $results): void {
        $admin_email = get_option('nhk_event_admin_email', get_option('admin_email'));
        $site_name = get_bloginfo('name');
        
        $critical_issues = array_filter($results['checks'], fn($r) => $r['status'] === 'critical');
        
        $subject = sprintf(
            __('[%s] Critical Health Issues Detected', 'nhk-event-manager'),
            $site_name
        );
        
        $message = sprintf(
            __("Critical health issues have been detected in the Event Manager plugin:\n\n%s\n\nPlease check your admin dashboard for more details."),
            implode("\n", array_map(fn($r) => "- {$r['message']}", $critical_issues))
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Store health history
     * 
     * @param array $results Health check results
     * @return void
     */
    protected function store_health_history(array $results): void {
        $history = get_option('nhk_event_health_history', []);
        
        // Keep only last 30 days
        $cutoff_date = strtotime('-30 days');
        $history = array_filter($history, fn($entry) => strtotime($entry['timestamp']) > $cutoff_date);
        
        // Add new entry
        $history[] = [
            'timestamp' => $results['summary']['timestamp'],
            'overall_status' => $results['summary']['overall_status'],
            'total_checks' => $results['summary']['total_checks'],
            'critical_checks' => $results['summary']['critical_checks'],
            'warning_checks' => $results['summary']['warning_checks'],
        ];
        
        update_option('nhk_event_health_history', $history);
    }
    
    /**
     * Get health history
     * 
     * @param int $days Number of days to retrieve
     * @return array Health history
     */
    public function get_health_history(int $days = 7): array {
        $history = get_option('nhk_event_health_history', []);
        $cutoff_date = strtotime("-{$days} days");
        
        return array_filter($history, fn($entry) => strtotime($entry['timestamp']) > $cutoff_date);
    }
    
    /**
     * Get health status summary
     * 
     * @return array Status summary
     */
    public function get_health_status_summary(): array {
        $cached_report = $this->get_cached_health_report();
        
        if (!$cached_report) {
            return [
                'status' => 'unknown',
                'message' => __('Health checks not run yet', 'nhk-event-manager'),
                'last_check' => null,
            ];
        }
        
        $summary = $cached_report['summary'];
        
        return [
            'status' => $summary['overall_status'],
            'message' => $this->get_status_message($summary),
            'last_check' => $summary['timestamp'],
            'total_checks' => $summary['total_checks'],
            'issues' => $summary['critical_checks'] + $summary['warning_checks'],
        ];
    }
    
    /**
     * Get status message
     * 
     * @param array $summary Health summary
     * @return string Status message
     */
    protected function get_status_message(array $summary): string {
        switch ($summary['overall_status']) {
            case 'healthy':
                return __('All systems are functioning normally', 'nhk-event-manager');
            case 'warning':
                return sprintf(
                    __('%d warning(s) detected', 'nhk-event-manager'),
                    $summary['warning_checks']
                );
            case 'critical':
                return sprintf(
                    __('%d critical issue(s) require attention', 'nhk-event-manager'),
                    $summary['critical_checks']
                );
            default:
                return __('Health status unknown', 'nhk-event-manager');
        }
    }
}
