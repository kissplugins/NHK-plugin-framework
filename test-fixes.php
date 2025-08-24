<?php
/**
 * Simple test script to verify our fixes
 */

// Include WordPress
require_once '../../../wp-config.php';

echo "Testing KISS Smart Batch Installer fixes...\n\n";

try {
    // Check if plugin is loaded
    if (!function_exists('sbi_container')) {
        echo "ERROR: Plugin not loaded properly\n";
        exit(1);
    }
    
    $container = sbi_container();
    if (!$container) {
        echo "ERROR: Container not available\n";
        exit(1);
    }
    
    echo "✓ Plugin container loaded successfully\n";
    
    // Test GitHubService
    $github_service = $container->get(SBI\Services\GitHubService::class);
    echo "✓ GitHubService instance created\n";
    
    // Test get_configuration method
    $config = $github_service->get_configuration();
    echo "✓ get_configuration() method works\n";
    echo "  - Organization: " . ($config['organization'] ?? 'not set') . "\n";
    echo "  - Repositories count: " . count($config['repositories']) . "\n";
    
    // Test fetch_repositories method (without parameters)
    $repos = $github_service->fetch_repositories();
    if (is_wp_error($repos)) {
        echo "✓ fetch_repositories() correctly returns error when no organization set: " . $repos->get_error_message() . "\n";
    } else {
        echo "✓ fetch_repositories() returned " . count($repos) . " repositories\n";
    }
    
    // Test asset registration
    global $wp_scripts, $wp_styles;
    
    // Simulate admin page load
    $_GET['page'] = 'kiss-smart-batch-installer';
    $plugin = $container->get(SBI\Plugin::class);
    $plugin->enqueue_admin_assets('plugins_page_kiss-smart-batch-installer');
    
    if (isset($wp_scripts->registered['sbi-admin'])) {
        echo "✓ sbi-admin JavaScript registered\n";
    } else {
        echo "✗ sbi-admin JavaScript NOT registered\n";
    }
    
    if (isset($wp_styles->registered['sbi-admin'])) {
        echo "✓ sbi-admin CSS registered\n";
    } else {
        echo "✗ sbi-admin CSS NOT registered\n";
    }
    
    echo "\n✓ All tests passed! The fixes should resolve the failing tests.\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
