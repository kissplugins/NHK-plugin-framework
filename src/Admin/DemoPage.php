<?php
/**
 * Demo Admin Page
 * 
 * Provides an admin page to showcase the modern frontend integration.
 * 
 * @package NHK\EventManager\Admin
 * @since 1.0.0
 */

namespace NHK\EventManager\Admin;

use NHK\Framework\Container\Container;

/**
 * Demo Admin Page Class
 * 
 * Demonstrates admin page creation with modern frontend integration.
 */
class DemoPage {
    
    /**
     * Service container
     * 
     * @var Container
     */
    protected Container $container;
    
    /**
     * Constructor
     * 
     * @param Container $container Service container
     */
    public function __construct(Container $container) {
        $this->container = $container;
    }
    
    /**
     * Initialize the admin page
     * 
     * @return void
     */
    public function init(): void {
        \add_action('admin_menu', [$this, 'add_admin_menu']);
        \add_action('admin_notices', [$this, 'show_demo_notice']);
    }
    
    /**
     * Add admin menu
     *
     * @return void
     */
    public function add_admin_menu(): void {
        // Add submenu pages under the Events menu (created by Event CPT)
        \add_submenu_page(
            'edit.php?post_type=nhk_event',
            __('Event Manager Demo', 'nhk-event-manager'),
            __('Event Manager', 'nhk-event-manager'),
            'manage_options',
            'nhk-event-manager',
            [$this, 'render_demo_page']
        );

        \add_submenu_page(
            'edit.php?post_type=nhk_event',
            __('Frontend Demo', 'nhk-event-manager'),
            __('Frontend Demo', 'nhk-event-manager'),
            'manage_options',
            'nhk-event-demo',
            [$this, 'render_demo_page']
        );
    }

    /**
     * Show demo notice on events list page
     *
     * @return void
     */
    public function show_demo_notice(): void {
        $screen = \get_current_screen();

        // Only show on events list page
        if (!$screen || $screen->post_type !== 'nhk_event' || $screen->base !== 'edit') {
            return;
        }

        // Check if there are real events
        $real_events_count = \wp_count_posts('nhk_event');
        $has_real_events = ($real_events_count->publish ?? 0) > 0;

        // Only show notice if there are real events (hide demo notice)
        if ($has_real_events) {
            return;
        }

        // Show demo notice only when no real events exist
        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <strong><?php \_e('Demo Mode Active', 'nhk-event-manager'); ?></strong>
                <?php \_e('This is a demonstration of the event list component. Import sample data or create real events to see the full functionality.', 'nhk-event-manager'); ?>
                <a href="<?php echo \admin_url('edit.php?post_type=nhk_event&page=nhk-event-manager'); ?>" class="button button-primary" style="margin-left: 10px;">
                    <?php \_e('Manage Sample Data', 'nhk-event-manager'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Render the demo page
     * 
     * @return void
     */
    public function render_demo_page(): void {
        // Handle NHK Debug helper toggle
        if (isset($_POST['nhk_action']) && $_POST['nhk_action'] === 'toggle_nhkdebug' && current_user_can('manage_options')) {
            check_admin_referer('nhk_toggle_nhkdebug');
            $enabled = isset($_POST['nhk_event_enable_nhkdebug']) ? (bool) $_POST['nhk_event_enable_nhkdebug'] : false;
            update_option('nhk_event_enable_nhkdebug', $enabled);
            echo '<div class="notice notice-success is-dismissible"><p>NHK Debug helper ' . ($enabled ? 'enabled' : 'disabled') . '.</p></div>';
        }
        ?>
        <div class="wrap">
            <h1><?php echo \esc_html(\get_admin_page_title()); ?></h1>
            
            <div class="notice notice-info">
                <p>
                    <strong><?php \_e('Welcome to the NHK Event Manager Demo!', 'nhk-event-manager'); ?></strong>
                    <?php \_e('This page demonstrates the modern frontend integration with Alpine.js, XState, and Tailwind CSS.', 'nhk-event-manager'); ?>
                </p>
            </div>

            <div id="nhk-api-health" style="display:flex;align-items:center;gap:8px;margin:8px 0;padding:6px 10px;border-radius:4px;background:#f7f7f7;border:1px solid #ddd;">
                <span style="font-weight:600;">API</span>
                <span class="nhk-health-dot" style="width:10px;height:10px;border-radius:50%;background:#ffbf00;display:inline-block"></span>
                <span class="nhk-health-text" style="color:#555;">Checking…</span>
            </div>
            
            <div class="nhk-demo-container" style="background: white; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h2><?php \_e('Modern Frontend Demo', 'nhk-event-manager'); ?></h2>
                <p><?php \_e('The demo below shows the event list component with modern JavaScript integration:', 'nhk-event-manager'); ?></p>
                
                <!-- Demo shortcode -->
                <?php echo \do_shortcode('[nhk_event_demo layout="list" per_page="3" show_filters="true"]'); ?>
            </div>
            
            <div style="margin-top: 20px;">
                <h2><?php \_e('Technical Features Demonstrated', 'nhk-event-manager'); ?></h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 15px;">
                    <div style="background: white; padding: 15px; border: 1px solid #ccd0d4;">
                        <h3>🎨 Tailwind CSS</h3>
                        <p>Utility-first CSS framework for rapid UI development with consistent design system.</p>
                    </div>
                    <div style="background: white; padding: 15px; border: 1px solid #ccd0d4;">
                        <h3>⚡ Alpine.js</h3>
                        <p>Lightweight reactive framework for building interactive components with minimal JavaScript.</p>
                    </div>
                    <div style="background: white; padding: 15px; border: 1px solid #ccd0d4;">
                        <h3>🔄 XState</h3>
                        <p>State machines for managing complex UI logic and application state transitions.</p>
                    </div>
                    <div style="background: white; padding: 15px; border: 1px solid #ccd0d4;">
                        <h3>🚀 Modern Build Process</h3>
                        <p>Bun for fast builds, TypeScript support, and optimized asset delivery.</p>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 20px; background: #f0f6fc; border: 1px solid #0969da; padding: 15px;">
                <h3><?php \_e('Usage Instructions', 'nhk-event-manager'); ?></h3>
                <ol>
                    <li><?php \_e('Use the shortcode <code>[nhk_event_demo]</code> to display the demo on any page or post', 'nhk-event-manager'); ?></li>
                    <li><?php \_e('Use <code>[nhk_events]</code> for the production event list component', 'nhk-event-manager'); ?></li>
                    <li><?php \_e('Customize with attributes like <code>layout="grid"</code>, <code>per_page="10"</code>, etc.', 'nhk-event-manager'); ?></li>
                    <li><?php \_e('All components are responsive and accessible by default', 'nhk-event-manager'); ?></li>
                </ol>
            </div>
            
            <div style="margin-top: 20px;">
                <h3><?php \_e('System Status', 'nhk-event-manager'); ?></h3>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php \_e('Component', 'nhk-event-manager'); ?></th>
                            <th><?php \_e('Status', 'nhk-event-manager'); ?></th>
                            <th><?php \_e('Details', 'nhk-event-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php \_e('CSS Assets', 'nhk-event-manager'); ?></td>
                            <td>
                                <?php if (\file_exists(NHK_EVENT_MANAGER_PATH . 'assets/dist/main.css')): ?>
                                    <span style="color: green;">✅ <?php \_e('Built', 'nhk-event-manager'); ?></span>
                                <?php else: ?>
                                    <span style="color: red;">❌ <?php \_e('Missing', 'nhk-event-manager'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php \_e('Tailwind CSS compiled and ready', 'nhk-event-manager'); ?></td>
                        </tr>
                        <tr>
                            <td><?php \_e('JavaScript Assets', 'nhk-event-manager'); ?></td>
                            <td>
                                <?php if (\file_exists(NHK_EVENT_MANAGER_PATH . 'assets/dist/index.js')): ?>
                                    <span style="color: green;">✅ <?php \_e('Built', 'nhk-event-manager'); ?></span>
                                <?php else: ?>
                                    <span style="color: red;">❌ <?php \_e('Missing', 'nhk-event-manager'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php \_e('Alpine.js and XState components ready', 'nhk-event-manager'); ?></td>
                        </tr>
                        <tr>
                            <td><?php \_e('PHP Version', 'nhk-event-manager'); ?></td>
                            <td>
                                <?php if (\version_compare(PHP_VERSION, '8.0', '>=')): ?>
                                    <span style="color: green;">✅ <?php echo PHP_VERSION; ?></span>
                                <?php else: ?>
                                    <span style="color: orange;">⚠️ <?php echo PHP_VERSION; ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php \_e('Modern PHP features available', 'nhk-event-manager'); ?></td>
                        </tr>
                        <tr>
                            <td><?php \_e('WordPress Version', 'nhk-event-manager'); ?></td>
                            <td>
                                <?php if (\version_compare(\get_bloginfo('version'), '6.0', '>=')): ?>
                                    <span style="color: green;">✅ <?php echo \get_bloginfo('version'); ?></span>
                                <?php else: ?>
                                    <span style="color: orange;">⚠️ <?php echo \get_bloginfo('version'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php \_e('Modern WordPress features available', 'nhk-event-manager'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Sample Data Import Section -->
            <div style="margin-top: 20px; background: #e7f3ff; border: 1px solid #0073aa; padding: 15px;">
                <h3><?php \_e('Sample Data Management', 'nhk-event-manager'); ?></h3>
                <p><?php \_e('Manage the WordPress training events sample data for demonstration purposes.', 'nhk-event-manager'); ?></p>

                <div style="margin: 15px 0;">
                    <button id="import-sample-data" class="button button-primary" style="margin-right: 10px;">
                        <?php \_e('Import Sample Data - Sept. 2025', 'nhk-event-manager'); ?>
                    </button>
                    <button id="clear-sample-data" class="button button-secondary">
                        <?php \_e('Clear Sample Data', 'nhk-event-manager'); ?>
                    </button>
                </div>

                <p style="font-size: 12px; color: #666; margin-top: 10px;">
                    <strong><?php \_e('Source:', 'nhk-event-manager'); ?></strong>
                    nhk-events-data-import.json in repo
                </p>

                <div id="import-results" style="margin-top: 15px; display: none;"></div>

                <?php
                // Get current sample data stats
                $container = nhk_event_manager_container();
                if ($container) {
                    $sample_importer = $container->get(\NHK\EventManager\Services\SampleDataImporter::class);
                    $stats = $sample_importer->get_import_stats();
                    ?>
                    <div style="margin-top: 15px; padding: 10px; background: #f9f9f9; border-left: 4px solid #0073aa;">
                        <strong><?php \_e('Current Status:', 'nhk-event-manager'); ?></strong><br>
                        <?php \_e('Sample Events:', 'nhk-event-manager'); ?> <?php echo \esc_html($stats['total_events']); ?><br>
                        <?php \_e('Published Events:', 'nhk-event-manager'); ?> <?php echo \esc_html($stats['published_events']); ?><br>
                        <?php \_e('Data File Available:', 'nhk-event-manager'); ?> <?php echo $stats['sample_data_file_exists'] ? '✅' : '❌'; ?>
                    </div>
                    <?php
                }
                ?>
            </div>

            <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7;">
                <h3><?php \_e('Development Notes', 'nhk-event-manager'); ?></h3>
                <ul>
                    <li><?php \_e('This plugin demonstrates modern WordPress development patterns', 'nhk-event-manager'); ?></li>
                    <li><?php \_e('All code follows WordPress coding standards and best practices', 'nhk-event-manager'); ?></li>
                    <li><?php \_e('The architecture is designed for scalability and maintainability', 'nhk-event-manager'); ?></li>
                    <li><?php \_e('Components are built with accessibility and performance in mind', 'nhk-event-manager'); ?></li>
                    <li><?php \_e('Sample data is automatically imported on first plugin activation', 'nhk-event-manager'); ?></li>
                    <li><?php \_e('You can modify nhk-events-data-import.json to customize the sample data', 'nhk-event-manager'); ?></li>
                </ul>

                <hr style="margin: 12px 0;">
                <h4 style="margin-top:8px;">NHK Debug Helper</h4>
                <p>Enable a light diagnostic helper (`window.NHKDebug`) and optional autorun after page load.</p>
                <form method="post">
                    <?php \wp_nonce_field('nhk_toggle_nhkdebug'); ?>
                    <input type="hidden" name="nhk_action" value="toggle_nhkdebug" />
                    <?php $dbg_enabled = (bool) \get_option('nhk_event_enable_nhkdebug', false); ?>
                    <label>
                        <input type="checkbox" name="nhk_event_enable_nhkdebug" value="1" <?php echo $dbg_enabled ? 'checked' : ''; ?>>
                        Enable NHK Debug helper autorun (can also `NHKDebug.run()` in console)
                    </label>
                    <p class="submit" style="margin-top:8px;">
                        <button type="submit" class="button button-primary">Save</button>
                        <span style="margin-left:8px;color:#666;">Console: <code>NHKDebug.run()</code> or <code>NHKDebug.auto(true)</code></span>
                    </p>
                </form>
            </div>
        </div>
        
        <script>
            // Add some admin-specific JavaScript
            document.addEventListener('DOMContentLoaded', function() {
                console.log('🎉 NHK Event Manager Admin Demo loaded!');

                // Quick REST route health badge
                (function() {
                    const el = document.getElementById('nhk-api-health');
                    if (!el) return;
                    const dot = el.querySelector('.nhk-health-dot');
                    const text = el.querySelector('.nhk-health-text');
                    const base = (window.nhkEventManagerAdmin && window.nhkEventManagerAdmin.apiUrl) || '/wp-json/nhk-events/v1';
                    const url = base.replace(/\/$/, '') + '/health';
                    fetch(url, { credentials: 'same-origin' })
                        .then(r => r.json().catch(() => ({})))
                        .then(data => {
                            if (data && (data.status === 'healthy' || data.success)) {
                                dot.style.background = '#22c55e';
                                text.textContent = 'Healthy';
                            } else {
                                dot.style.background = '#ef4444';
                                text.textContent = 'Unhealthy';
                            }
                        })
                        .catch(err => {
                            console.warn('Health check error', err);
                            dot.style.background = '#ef4444';
                            text.textContent = 'Unavailable';
                        });
                })();

                // Sample data import functionality
                const importButton = document.getElementById('import-sample-data');
                const clearButton = document.getElementById('clear-sample-data');
                const resultsDiv = document.getElementById('import-results');

                function showResults(message, isError = false) {
                    resultsDiv.style.display = 'block';
                    resultsDiv.innerHTML = `
                        <div style="padding: 10px; border-radius: 4px; background: ${isError ? '#ffebee' : '#e8f5e8'};
                                    border: 1px solid ${isError ? '#f44336' : '#4caf50'}; color: ${isError ? '#c62828' : '#2e7d32'};">
                            ${message}
                        </div>
                    `;

                    // Auto-hide after 5 seconds
                    setTimeout(() => {
                        resultsDiv.style.display = 'none';
                    }, 5000);
                }

                function performImportAction(action) {
                    const button = action === 'clear' ? clearButton : importButton;
                    const originalText = button.textContent;

                    button.disabled = true;
                    button.textContent = action === 'clear' ? 'Clearing...' : 'Importing...';

                    const formData = new FormData();
                    formData.append('action', 'nhk_import_sample_data');
                    formData.append('import_action', action);
                    formData.append('nonce', '<?php echo \wp_create_nonce('wp_rest'); ?>');

                    fetch('<?php echo \admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showResults('✅ ' + data.data.message, false);
                            // Reload page after successful import/clear to update stats
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        } else {
                            showResults('❌ ' + (data.data?.message || 'Operation failed'), true);
                        }
                    })
                    .catch(error => {
                        console.error('Import error:', error);
                        showResults('❌ Network error occurred', true);
                    })
                    .finally(() => {
                        button.disabled = false;
                        button.textContent = originalText;
                    });
                }

                if (importButton) {
                    importButton.addEventListener('click', () => performImportAction('import'));
                }

                if (clearButton) {
                    clearButton.addEventListener('click', () => {
                        if (confirm('Are you sure you want to clear all sample data? This cannot be undone.')) {
                            performImportAction('clear');
                        }
                    });
                }

                // Show a welcome message
                if (typeof window.showNotification === 'function') {
                    setTimeout(() => {
                        window.showNotification(
                            'Welcome to the NHK Event Manager admin demo!',
                            'info',
                            5000
                        );
                    }, 1000);
                }
            });
        </script>
        <?php
    }
}
