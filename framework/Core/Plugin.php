<?php
/**
 * Base Plugin Class for NHK Framework
 * 
 * This class provides the foundation for all NHK Framework-based plugins.
 * It implements the service container pattern and provides common functionality.
 * 
 * @package NHK\Framework\Core
 * @since 1.0.0
 */

namespace NHK\Framework\Core;

use NHK\Framework\Container\Container;

/**
 * Abstract Plugin class that all NHK Framework plugins should extend
 * 
 * This class provides:
 * - Service container for dependency injection
 * - Plugin lifecycle management (activation, deactivation, uninstall)
 * - Service registration and bootstrapping
 * - Version management
 * - WordPress hooks integration
 */
abstract class Plugin {
    
    /**
     * Service container instance
     * 
     * @var Container
     */
    protected Container $container;
    
    /**
     * Plugin version
     * 
     * @var string
     */
    protected string $version;
    
    /**
     * Plugin text domain
     * 
     * @var string
     */
    protected string $text_domain;
    
    /**
     * Plugin file path
     * 
     * @var string
     */
    protected string $plugin_file;
    
    /**
     * Plugin directory path
     * 
     * @var string
     */
    protected string $plugin_path;
    
    /**
     * Plugin URL
     * 
     * @var string
     */
    protected string $plugin_url;
    
    /**
     * Constructor
     * 
     * Initializes the service container and sets up basic plugin properties.
     */
    public function __construct() {
        $this->container = new Container();
        $this->setup_properties();
    }
    
    /**
     * Initialize the plugin
     * 
     * This method should be called from the main plugin file to start the plugin.
     * It registers services, boots them, and sets up WordPress hooks.
     * 
     * @return void
     */
    public function init(): void {
        $this->register_services();
        $this->boot_services();
        $this->setup_hooks();
        $this->load_textdomain();
    }
    
    /**
     * Plugin activation hook
     * 
     * Called when the plugin is activated. Override in child classes to add
     * custom activation logic.
     * 
     * @return void
     */
    public function activate(): void {
        // Run database migrations
        $this->run_migrations();
        
        // Flush rewrite rules to ensure CPTs work
        flush_rewrite_rules();
        
        // Set activation flag
        update_option($this->get_option_prefix() . 'activated', true);
    }
    
    /**
     * Plugin deactivation hook
     * 
     * Called when the plugin is deactivated. Override in child classes to add
     * custom deactivation logic.
     * 
     * @return void
     */
    public function deactivate(): void {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Remove activation flag
        delete_option($this->get_option_prefix() . 'activated');
    }
    
    /**
     * Plugin uninstall hook
     * 
     * Called when the plugin is uninstalled. Override in child classes to add
     * custom cleanup logic.
     * 
     * @return void
     */
    public function uninstall(): void {
        // Clean up options
        $this->cleanup_options();
        
        // Clean up database tables
        $this->cleanup_database();
    }
    
    /**
     * Get the service container
     * 
     * @return Container
     */
    public function get_container(): Container {
        return $this->container;
    }
    
    /**
     * Get plugin version
     * 
     * @return string
     */
    public function get_version(): string {
        return $this->version;
    }
    
    /**
     * Get plugin text domain
     * 
     * @return string
     */
    public function get_text_domain(): string {
        return $this->text_domain;
    }
    
    /**
     * Get plugin file path
     * 
     * @return string
     */
    public function get_plugin_file(): string {
        return $this->plugin_file;
    }
    
    /**
     * Get plugin directory path
     * 
     * @return string
     */
    public function get_plugin_path(): string {
        return $this->plugin_path;
    }
    
    /**
     * Get plugin URL
     * 
     * @return string
     */
    public function get_plugin_url(): string {
        return $this->plugin_url;
    }
    
    /**
     * Setup plugin properties
     * 
     * Child classes should override this method to set their specific properties.
     * 
     * @return void
     */
    protected function setup_properties(): void {
        // Default values - should be overridden by child classes
        $this->version = '1.0.0';
        $this->text_domain = 'nhk-framework';
        $this->plugin_file = __FILE__;
        $this->plugin_path = plugin_dir_path($this->plugin_file);
        $this->plugin_url = plugin_dir_url($this->plugin_file);
    }
    
    /**
     * Register services in the container
     * 
     * Child classes should override this method to register their services.
     * 
     * @return void
     */
    protected function register_services(): void {
        // Override in child classes
    }
    
    /**
     * Boot registered services
     * 
     * Child classes should override this method to boot their services.
     * 
     * @return void
     */
    protected function boot_services(): void {
        // Override in child classes
    }
    
    /**
     * Setup WordPress hooks
     * 
     * Child classes should override this method to add their hooks.
     * 
     * @return void
     */
    protected function setup_hooks(): void {
        // Override in child classes
    }
    
    /**
     * Load plugin textdomain for translations
     * 
     * Uses WordPress load_plugin_textdomain() function.
     * 
     * @return void
     */
    protected function load_textdomain(): void {
        load_plugin_textdomain(
            $this->text_domain,
            false,
            dirname(plugin_basename($this->plugin_file)) . '/languages'
        );
    }
    
    /**
     * Run database migrations
     * 
     * @return void
     */
    protected function run_migrations(): void {
        // Override in child classes if migrations are needed
    }
    
    /**
     * Get option prefix for this plugin
     * 
     * @return string
     */
    protected function get_option_prefix(): string {
        return str_replace('-', '_', $this->text_domain) . '_';
    }
    
    /**
     * Clean up plugin options
     * 
     * @return void
     */
    protected function cleanup_options(): void {
        // Override in child classes
    }
    
    /**
     * Clean up database tables
     * 
     * @return void
     */
    protected function cleanup_database(): void {
        // Override in child classes
    }
}
