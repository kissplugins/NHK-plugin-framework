<?php
/**
 * Enhanced Asset Manager for NHK Event Manager
 * 
 * Manages modern frontend assets including Tailwind CSS, Alpine.js, and XState
 * with proper WordPress integration and performance optimization.
 * 
 * @package NHK\EventManager\Core
 * @since 1.0.0
 */

namespace NHK\EventManager\Core;

use NHK\Framework\Container\Container;

/**
 * Asset Manager Class
 * 
 * Demonstrates:
 * - Modern asset management with build process integration
 * - Conditional loading based on context
 * - Cache busting and versioning
 * - WordPress best practices for asset enqueuing
 */
class AssetManager {
    
    /**
     * Service container
     * 
     * @var Container
     */
    protected Container $container;
    
    /**
     * Plugin version for cache busting
     * 
     * @var string
     */
    protected string $version;
    
    /**
     * Asset base URL
     * 
     * @var string
     */
    protected string $asset_url;
    
    /**
     * Asset base path
     * 
     * @var string
     */
    protected string $asset_path;
    
    /**
     * Whether we're in development mode
     * 
     * @var bool
     */
    protected bool $is_dev_mode;
    
    /**
     * Constructor
     * 
     * @param Container $container Service container
     */
    public function __construct(Container $container) {
        $this->container = $container;
        $this->version = NHK_EVENT_MANAGER_VERSION;
        $this->asset_url = NHK_EVENT_MANAGER_URL . 'assets/';
        $this->asset_path = NHK_EVENT_MANAGER_PATH . 'assets/';
        $this->is_dev_mode = defined('WP_DEBUG') && WP_DEBUG;
    }
    
    /**
     * Initialize asset management
     * 
     * @return void
     */
    public function init(): void {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_head', [$this, 'add_preload_hints'], 1);
        add_action('wp_footer', [$this, 'add_critical_inline_scripts'], 1);
        add_action('wp_footer', [$this, 'add_debug_panel'], 999);
        add_action('admin_footer', [$this, 'add_debug_panel'], 999);
    }
    
    /**
     * Enqueue frontend assets
     * 
     * @return void
     */
    public function enqueue_frontend_assets(): void {
        if (!$this->should_load_frontend_assets()) {
            return;
        }
        
        // Enqueue main stylesheet
        $this->enqueue_style(
            'nhk-event-manager-frontend',
            $this->get_asset_url('dist/main.css'),
            [],
            $this->get_asset_version('dist/main.css')
        );
        
        // Enqueue main JavaScript
        $this->enqueue_script(
            'nhk-event-manager-frontend',
            $this->get_asset_url('dist/index.js'),
            ['wp-api-fetch'],
            $this->get_asset_version('dist/index.js'),
            true
        );
        
        // Localize script with necessary data
        wp_localize_script('nhk-event-manager-frontend', 'nhkEventManager', [
            // Use a REST route format that works regardless of pretty permalinks
            'apiUrl' => add_query_arg('rest_route', '/nhk-events/v1', home_url('/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'isUserLoggedIn' => is_user_logged_in(),
            'locale' => get_locale(),
            'isDebug' => true, // Enable debug mode temporarily to diagnose FSM issue
            'strings' => $this->get_localized_strings(),
            'config' => $this->get_frontend_config(),
        ]);

        // Add inline styles for critical CSS if needed
        if ($this->should_inline_critical_css()) {
            $this->add_critical_css();
        }
    }
    
    /**
     * Enqueue admin assets
     * 
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueue_admin_assets(string $hook): void {
        if (!$this->should_load_admin_assets($hook)) {
            return;
        }
        
        // Enqueue admin stylesheet
        $this->enqueue_style(
            'nhk-event-manager-admin',
            $this->get_asset_url('dist/main.css'),
            ['wp-admin', 'wp-color-picker'],
            $this->get_asset_version('dist/main.css')
        );

        // Enqueue admin JavaScript
        $this->enqueue_script(
            'nhk-event-manager-admin',
            $this->get_asset_url('dist/index.js'),
            ['jquery', 'wp-color-picker', 'wp-api-fetch'],
            $this->get_asset_version('dist/index.js'),
            true
        );
        
        // Localize admin script
        wp_localize_script('nhk-event-manager-admin', 'nhkEventManagerAdmin', [
            // Use a REST route format that works regardless of pretty permalinks
            'apiUrl' => add_query_arg('rest_route', '/nhk-events/v1', home_url('/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'isUserLoggedIn' => is_user_logged_in(),
            'adminUrl' => admin_url('admin-ajax.php'),
            'isDebug' => $this->is_dev_mode,
            'strings' => $this->get_admin_localized_strings(),
            'config' => $this->get_admin_config(),
        ]);
    }
    
    /**
     * Add preload hints for critical assets
     * 
     * @return void
     */
    public function add_preload_hints(): void {
        if (!$this->should_load_frontend_assets()) {
            return;
        }
        
        // Preload critical CSS
        echo sprintf(
            '<link rel="preload" href="%s" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">',
            esc_url($this->get_asset_url('dist/main.css'))
        );
        
        // Preload critical JavaScript
        echo sprintf(
            '<link rel="preload" href="%s" as="script">',
            esc_url($this->get_asset_url('dist/index.js'))
        );
        
        // DNS prefetch for external resources
        echo '<link rel="dns-prefetch" href="//fonts.googleapis.com">';
    }
    
    /**
     * Add critical inline scripts
     * 
     * @return void
     */
    public function add_critical_inline_scripts(): void {
        if (!$this->should_load_frontend_assets()) {
            return;
        }
        
        // Add focus-visible polyfill for older browsers
        echo '<script>!function(){try{document.querySelector(":focus-visible")}catch(e){var t=function(){document.body.classList.add("js-focus-visible")},n=function(){document.body.classList.remove("js-focus-visible")};document.addEventListener("keydown",t),document.addEventListener("mousedown",n)}}();</script>';
    }
    
    /**
     * Enhanced style enqueuing with integrity and crossorigin
     * 
     * @param string $handle Style handle
     * @param string $src Style URL
     * @param array $deps Dependencies
     * @param string $ver Version
     * @param string $media Media type
     * @return void
     */
    protected function enqueue_style(string $handle, string $src, array $deps = [], string $ver = '', string $media = 'all'): void {
        wp_enqueue_style($handle, $src, $deps, $ver, $media);
        
        // Add integrity and crossorigin for external resources
        if ($this->is_external_url($src)) {
            add_filter('style_loader_tag', function($html, $handle_filter) use ($handle) {
                if ($handle === $handle_filter) {
                    $html = str_replace('<link ', '<link crossorigin="anonymous" ', $html);
                }
                return $html;
            }, 10, 2);
        }
    }
    
    /**
     * Enhanced script enqueuing with module support
     * 
     * @param string $handle Script handle
     * @param string $src Script URL
     * @param array $deps Dependencies
     * @param string $ver Version
     * @param bool $in_footer Load in footer
     * @return void
     */
    protected function enqueue_script(string $handle, string $src, array $deps = [], string $ver = '', bool $in_footer = false): void {
        wp_enqueue_script($handle, $src, $deps, $ver, $in_footer);
        
        // Add module type for modern browsers if needed
        if ($this->should_use_modules()) {
            add_filter('script_loader_tag', function($tag, $handle_filter) use ($handle) {
                if ($handle === $handle_filter && strpos($tag, 'type=') === false) {
                    $tag = str_replace('<script ', '<script type="module" ', $tag);
                }
                return $tag;
            }, 10, 2);
        }
    }
    
    /**
     * Get asset URL with proper cache busting
     * 
     * @param string $asset Asset path relative to assets directory
     * @return string Full asset URL
     */
    protected function get_asset_url(string $asset): string {
        return $this->asset_url . $asset;
    }
    
    /**
     * Get asset version for cache busting
     * 
     * @param string $asset Asset path relative to assets directory
     * @return string Asset version
     */
    protected function get_asset_version(string $asset): string {
        if ($this->is_dev_mode) {
            // Use file modification time in development
            $file_path = $this->asset_path . $asset;
            if (file_exists($file_path)) {
                return (string) filemtime($file_path);
            }
        }
        
        return $this->version;
    }
    
    /**
     * Check if frontend assets should be loaded
     * 
     * @return bool
     */
    protected function should_load_frontend_assets(): bool {
        global $post;
        
        // Load on event-related pages
        if (is_singular('nhk_event') || is_post_type_archive('nhk_event')) {
            return true;
        }
        
        // Load on pages with event shortcodes
        if ($post && (has_shortcode($post->post_content, 'nhk_events')
            || has_shortcode($post->post_content, 'nhk_event_demo')
            || has_shortcode($post->post_content, 'nhk_events_simple'))) {
            return true;
        }
        
        // Load on pages with event blocks (Gutenberg)
        if ($post && has_block('nhk-events/event-list', $post)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if admin assets should be loaded
     * 
     * @param string $hook Current admin page hook
     * @return bool
     */
    protected function should_load_admin_assets(string $hook): bool {
        // Event-related admin pages
        $event_pages = [
            'post.php',
            'post-new.php',
            'edit.php',
            'edit-tags.php',
            'term.php',
        ];
        
        if (in_array($hook, $event_pages) && isset($_GET['post_type']) && $_GET['post_type'] === 'nhk_event') {
            return true;
        }
        
        // Settings and health check pages
        if (strpos($hook, 'nhk-event') !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get localized strings for frontend
     * 
     * @return array
     */
    protected function get_localized_strings(): array {
        return [
            'loading' => __('Loading...', 'nhk-event-manager'),
            'error' => __('An error occurred', 'nhk-event-manager'),
            'noEvents' => __('No events found', 'nhk-event-manager'),
            'loadMore' => __('Load More', 'nhk-event-manager'),
            'showFilters' => __('Show Filters', 'nhk-event-manager'),
            'hideFilters' => __('Hide Filters', 'nhk-event-manager'),
            'clearFilters' => __('Clear Filters', 'nhk-event-manager'),
            'search' => __('Search events...', 'nhk-event-manager'),
            'category' => __('Category', 'nhk-event-manager'),
            'venue' => __('Venue', 'nhk-event-manager'),
            'dateFrom' => __('From Date', 'nhk-event-manager'),
            'dateTo' => __('To Date', 'nhk-event-manager'),
            'register' => __('Register', 'nhk-event-manager'),
            'viewDetails' => __('View Details', 'nhk-event-manager'),
        ];
    }
    
    /**
     * Get admin localized strings
     * 
     * @return array
     */
    protected function get_admin_localized_strings(): array {
        return [
            'confirmDelete' => __('Are you sure you want to delete this?', 'nhk-event-manager'),
            'saved' => __('Settings saved', 'nhk-event-manager'),
            'error' => __('An error occurred', 'nhk-event-manager'),
            'bulkDelete' => __('Delete Selected', 'nhk-event-manager'),
            'selectAll' => __('Select All', 'nhk-event-manager'),
            'clearSelection' => __('Clear Selection', 'nhk-event-manager'),
        ];
    }
    
    /**
     * Get frontend configuration
     * 
     * @return array
     */
    protected function get_frontend_config(): array {
        return [
            'dateFormat' => get_option('nhk_event_date_format', 'F j, Y'),
            'timeFormat' => get_option('nhk_event_time_format', 'g:i A'),
            'eventsPerPage' => get_option('nhk_event_events_per_page', 10),
            'showPastEvents' => get_option('nhk_event_show_past_events', false),
            'defaultSort' => get_option('nhk_event_default_sort', 'date_asc'),
            'enableReminders' => get_option('nhk_event_enable_reminders', false),
        ];
    }
    
    /**
     * Get admin configuration
     * 
     * @return array
     */
    protected function get_admin_config(): array {
        return [
            'enableDebug' => get_option('nhk_event_enable_debug', false),
            'cacheDuration' => get_option('nhk_event_cache_duration', 3600),
        ];
    }
    
    /**
     * Check if critical CSS should be inlined
     * 
     * @return bool
     */
    protected function should_inline_critical_css(): bool {
        return !$this->is_dev_mode && $this->should_load_frontend_assets();
    }
    
    /**
     * Add critical CSS inline
     * 
     * @return void
     */
    protected function add_critical_css(): void {
        $critical_css = $this->get_critical_css();
        if ($critical_css) {
            wp_add_inline_style('nhk-event-manager-frontend', $critical_css);
        }
    }
    
    /**
     * Get critical CSS content
     * 
     * @return string
     */
    protected function get_critical_css(): string {
        $critical_file = $this->asset_path . 'dist/critical.css';
        if (file_exists($critical_file)) {
            return file_get_contents($critical_file);
        }
        
        return '';
    }
    
    /**
     * Check if URL is external
     * 
     * @param string $url URL to check
     * @return bool
     */
    protected function is_external_url(string $url): bool {
        return strpos($url, 'http') === 0 && strpos($url, home_url()) !== 0;
    }
    
    /**
     * Check if ES modules should be used
     *
     * @return bool
     */
    protected function should_use_modules(): bool {
        return $this->is_dev_mode && !is_admin();
    }

    /**
     * Add debug panel to footer
     *
     * @return void
     */
    public function add_debug_panel(): void {
        // Only show debug panel if WP_DEBUG is enabled
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        // Only show on pages where our assets are loaded
        if (!$this->should_show_debug_panel()) {
            return;
        }

        $template_path = NHK_EVENT_MANAGER_PATH . 'templates/debug-panel.php';
        if (file_exists($template_path)) {
            include $template_path;
        }
    }

    /**
     * Check if debug panel should be shown
     *
     * @return bool
     */
    protected function should_show_debug_panel(): bool {
        // Show on admin pages where our assets are loaded
        if (is_admin()) {
            $hook = $_GET['page'] ?? '';
            return strpos($hook, 'nhk-event') !== false;
        }

        // Show on frontend pages with our shortcodes or post types
        if (is_singular('nhk_event')) {
            return true;
        }

        // Check for shortcodes in content
        global $post;
        if ($post && has_shortcode($post->post_content, 'nhk_events')) {
            return true;
        }

        return false;
    }
}
