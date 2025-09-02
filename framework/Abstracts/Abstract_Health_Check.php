<?php
/**
 * Abstract Health Check Class for NHK Framework
 * 
 * This class provides a base for creating health checks to monitor
 * system status and plugin functionality.
 * 
 * @package NHK\Framework\Abstracts
 * @since 1.0.0
 */

namespace NHK\Framework\Abstracts;

use NHK\Framework\Container\Container;

/**
 * Abstract class for Health Checks
 * 
 * Provides a structured way to create health checks with:
 * - Status monitoring
 * - Performance metrics
 * - Error detection
 * - Automated reporting
 */
abstract class Abstract_Health_Check {
    
    /**
     * Service container
     * 
     * @var Container
     */
    protected Container $container;
    
    /**
     * Check status constants
     */
    const STATUS_HEALTHY = 'healthy';
    const STATUS_WARNING = 'warning';
    const STATUS_CRITICAL = 'critical';
    const STATUS_UNKNOWN = 'unknown';
    
    /**
     * Constructor
     * 
     * @param Container $container Service container
     */
    public function __construct(Container $container) {
        $this->container = $container;
    }
    
    /**
     * Get the check name
     * 
     * @return string
     */
    abstract protected function get_check_name(): string;
    
    /**
     * Get the check description
     * 
     * @return string
     */
    abstract protected function get_check_description(): string;
    
    /**
     * Perform the health check
     * 
     * @return array Check result
     */
    abstract protected function perform_check(): array;
    
    /**
     * Run the health check
     * 
     * @return array Complete check result
     */
    public function run(): array {
        $start_time = microtime(true);
        
        try {
            $result = $this->perform_check();
            
            // Ensure required fields are present
            $result = array_merge([
                'status' => self::STATUS_UNKNOWN,
                'message' => '',
                'details' => [],
                'metrics' => [],
            ], $result);
            
        } catch (\Exception $e) {
            $result = [
                'status' => self::STATUS_CRITICAL,
                'message' => 'Health check failed: ' . $e->getMessage(),
                'details' => [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
                'metrics' => [],
            ];
        }
        
        $end_time = microtime(true);
        $execution_time = round(($end_time - $start_time) * 1000, 2); // milliseconds
        
        return array_merge($result, [
            'check_name' => $this->get_check_name(),
            'description' => $this->get_check_description(),
            'timestamp' => current_time('Y-m-d H:i:s'),
            'execution_time_ms' => $execution_time,
        ]);
    }
    
    /**
     * Create a healthy status result
     * 
     * @param string $message Status message
     * @param array $details Additional details
     * @param array $metrics Performance metrics
     * @return array Result array
     */
    protected function healthy(string $message, array $details = [], array $metrics = []): array {
        return [
            'status' => self::STATUS_HEALTHY,
            'message' => $message,
            'details' => $details,
            'metrics' => $metrics,
        ];
    }
    
    /**
     * Create a warning status result
     * 
     * @param string $message Warning message
     * @param array $details Additional details
     * @param array $metrics Performance metrics
     * @return array Result array
     */
    protected function warning(string $message, array $details = [], array $metrics = []): array {
        return [
            'status' => self::STATUS_WARNING,
            'message' => $message,
            'details' => $details,
            'metrics' => $metrics,
        ];
    }
    
    /**
     * Create a critical status result
     * 
     * @param string $message Error message
     * @param array $details Additional details
     * @param array $metrics Performance metrics
     * @return array Result array
     */
    protected function critical(string $message, array $details = [], array $metrics = []): array {
        return [
            'status' => self::STATUS_CRITICAL,
            'message' => $message,
            'details' => $details,
            'metrics' => $metrics,
        ];
    }
    
    /**
     * Check if a value is within acceptable range
     * 
     * @param float $value Value to check
     * @param float $warning_threshold Warning threshold
     * @param float $critical_threshold Critical threshold
     * @param bool $higher_is_worse Whether higher values are worse
     * @return string Status
     */
    protected function check_threshold(float $value, float $warning_threshold, float $critical_threshold, bool $higher_is_worse = true): string {
        if ($higher_is_worse) {
            if ($value >= $critical_threshold) {
                return self::STATUS_CRITICAL;
            } elseif ($value >= $warning_threshold) {
                return self::STATUS_WARNING;
            } else {
                return self::STATUS_HEALTHY;
            }
        } else {
            if ($value <= $critical_threshold) {
                return self::STATUS_CRITICAL;
            } elseif ($value <= $warning_threshold) {
                return self::STATUS_WARNING;
            } else {
                return self::STATUS_HEALTHY;
            }
        }
    }
    
    /**
     * Format bytes to human readable format
     * 
     * @param int $bytes Bytes
     * @param int $precision Decimal precision
     * @return string Formatted size
     */
    protected function format_bytes(int $bytes, int $precision = 2): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Check database connectivity
     * 
     * @return bool Database is accessible
     */
    protected function check_database(): bool {
        global $wpdb;
        
        try {
            $result = $wpdb->get_var("SELECT 1");
            return $result === '1';
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Get WordPress memory usage
     * 
     * @return array Memory usage information
     */
    protected function get_memory_usage(): array {
        return [
            'current_usage' => memory_get_usage(true),
            'peak_usage' => memory_get_peak_usage(true),
            'limit' => $this->get_memory_limit(),
            'usage_percentage' => round((memory_get_usage(true) / $this->get_memory_limit()) * 100, 2),
        ];
    }
    
    /**
     * Get PHP memory limit in bytes
     * 
     * @return int Memory limit in bytes
     */
    protected function get_memory_limit(): int {
        $limit = ini_get('memory_limit');
        
        if ($limit === '-1') {
            return PHP_INT_MAX;
        }
        
        $unit = strtolower(substr($limit, -1));
        $value = (int) substr($limit, 0, -1);
        
        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return (int) $limit;
        }
    }
    
    /**
     * Check if a WordPress function exists
     * 
     * @param string $function Function name
     * @return bool Function exists
     */
    protected function wp_function_exists(string $function): bool {
        return function_exists($function);
    }
    
    /**
     * Check if a WordPress constant is defined
     * 
     * @param string $constant Constant name
     * @return bool Constant is defined
     */
    protected function wp_constant_defined(string $constant): bool {
        return defined($constant);
    }
    
    /**
     * Get WordPress version
     * 
     * @return string WordPress version
     */
    protected function get_wp_version(): string {
        global $wp_version;
        return $wp_version;
    }
    
    /**
     * Get PHP version
     * 
     * @return string PHP version
     */
    protected function get_php_version(): string {
        return PHP_VERSION;
    }
    
    /**
     * Check if a plugin is active
     * 
     * @param string $plugin Plugin file
     * @return bool Plugin is active
     */
    protected function is_plugin_active(string $plugin): bool {
        return is_plugin_active($plugin);
    }
    
    /**
     * Get file permissions
     * 
     * @param string $file File path
     * @return string|false File permissions or false if file doesn't exist
     */
    protected function get_file_permissions(string $file) {
        if (!file_exists($file)) {
            return false;
        }
        
        return substr(sprintf('%o', fileperms($file)), -4);
    }
    
    /**
     * Check if directory is writable
     * 
     * @param string $directory Directory path
     * @return bool Directory is writable
     */
    protected function is_directory_writable(string $directory): bool {
        return is_dir($directory) && is_writable($directory);
    }
    
    /**
     * Get disk space information
     * 
     * @param string $directory Directory to check
     * @return array|false Disk space info or false on failure
     */
    protected function get_disk_space(string $directory) {
        if (!is_dir($directory)) {
            return false;
        }
        
        $free_bytes = disk_free_space($directory);
        $total_bytes = disk_total_space($directory);
        
        if ($free_bytes === false || $total_bytes === false) {
            return false;
        }
        
        return [
            'free_bytes' => $free_bytes,
            'total_bytes' => $total_bytes,
            'used_bytes' => $total_bytes - $free_bytes,
            'usage_percentage' => round((($total_bytes - $free_bytes) / $total_bytes) * 100, 2),
        ];
    }
}
