<?php
/**
 * Abstract Shortcode Class for NHK Framework
 * 
 * This class provides a base for creating WordPress shortcodes with
 * additional framework features and proper structure.
 * 
 * @package NHK\Framework\Abstracts
 * @since 1.0.0
 */

namespace NHK\Framework\Abstracts;

use NHK\Framework\Container\Container;

/**
 * Abstract class for Shortcodes
 * 
 * Provides a structured way to create shortcodes with:
 * - WordPress add_shortcode() integration
 * - Attribute validation and sanitization
 * - Template rendering
 * - Caching support
 */
abstract class Abstract_Shortcode {
    
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
     * Initialize the shortcode
     * 
     * @return void
     */
    public function init(): void {
        add_shortcode($this->get_shortcode_tag(), [$this, 'render_shortcode']);
    }
    
    /**
     * Get the shortcode tag
     * 
     * @return string
     */
    abstract protected function get_shortcode_tag(): string;
    
    /**
     * Get default attributes
     * 
     * @return array
     */
    abstract protected function get_default_attributes(): array;
    
    /**
     * Render shortcode content
     * 
     * @param array $attributes Shortcode attributes
     * @param string|null $content Shortcode content
     * @return string Rendered content
     */
    abstract protected function render_content(array $attributes, ?string $content = null): string;
    
    /**
     * Render shortcode
     * 
     * WordPress shortcode callback function.
     * 
     * @param array|string $atts Shortcode attributes
     * @param string|null $content Shortcode content
     * @return string Rendered shortcode
     */
    public function render_shortcode($atts = [], ?string $content = null): string {
        // Parse and sanitize attributes
        $attributes = $this->parse_attributes($atts);
        
        // Validate attributes
        $validation_result = $this->validate_attributes($attributes);
        if (is_string($validation_result)) {
            return $this->render_error($validation_result);
        }
        
        // Check cache if enabled
        $cache_key = $this->get_cache_key($attributes, $content);
        if ($cache_key && $this->is_cache_enabled()) {
            $cached_content = $this->get_cached_content($cache_key);
            if ($cached_content !== false) {
                return $cached_content;
            }
        }
        
        // Render content
        $rendered_content = $this->render_content($attributes, $content);
        
        // Cache the result if enabled
        if ($cache_key && $this->is_cache_enabled()) {
            $this->cache_content($cache_key, $rendered_content);
        }
        
        return $rendered_content;
    }
    
    /**
     * Parse and sanitize shortcode attributes
     * 
     * @param array|string $atts Raw attributes
     * @return array Parsed attributes
     */
    protected function parse_attributes($atts): array {
        $defaults = $this->get_default_attributes();
        $attributes = shortcode_atts($defaults, $atts, $this->get_shortcode_tag());
        
        // Sanitize attributes
        return $this->sanitize_attributes($attributes);
    }
    
    /**
     * Sanitize shortcode attributes
     * 
     * Override in child classes for custom sanitization.
     * 
     * @param array $attributes Raw attributes
     * @return array Sanitized attributes
     */
    protected function sanitize_attributes(array $attributes): array {
        $sanitized = [];
        
        foreach ($attributes as $key => $value) {
            switch ($key) {
                case 'id':
                case 'limit':
                case 'count':
                case 'per_page':
                    $sanitized[$key] = absint($value);
                    break;
                    
                case 'class':
                case 'css_class':
                    $sanitized[$key] = sanitize_html_class($value);
                    break;
                    
                case 'orderby':
                case 'order':
                case 'type':
                case 'format':
                    $sanitized[$key] = sanitize_key($value);
                    break;
                    
                case 'title':
                case 'heading':
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
                    
                case 'show_title':
                case 'show_excerpt':
                case 'show_date':
                case 'show_image':
                    $sanitized[$key] = $this->sanitize_boolean($value);
                    break;
                    
                default:
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Validate shortcode attributes
     * 
     * Override in child classes for custom validation.
     * 
     * @param array $attributes Sanitized attributes
     * @return bool|string True if valid, error message if invalid
     */
    protected function validate_attributes(array $attributes) {
        return true;
    }
    
    /**
     * Sanitize boolean value
     * 
     * @param mixed $value Input value
     * @return bool Boolean value
     */
    protected function sanitize_boolean($value): bool {
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['1', 'true', 'yes', 'on'], true);
        }
        
        return (bool) $value;
    }
    
    /**
     * Render error message
     * 
     * @param string $message Error message
     * @return string Rendered error
     */
    protected function render_error(string $message): string {
        if (!current_user_can('edit_posts')) {
            return ''; // Don't show errors to non-editors
        }
        
        return sprintf(
            '<div class="nhk-shortcode-error" style="background: #ffebe8; border: 1px solid #c00; padding: 10px; margin: 10px 0; color: #c00;">
                <strong>%s:</strong> %s
            </div>',
            esc_html__('Shortcode Error', 'nhk-framework'),
            esc_html($message)
        );
    }
    
    /**
     * Get cache key for shortcode
     * 
     * @param array $attributes Shortcode attributes
     * @param string|null $content Shortcode content
     * @return string|null Cache key or null if caching disabled
     */
    protected function get_cache_key(array $attributes, ?string $content = null): ?string {
        if (!$this->is_cache_enabled()) {
            return null;
        }
        
        $cache_data = [
            'tag' => $this->get_shortcode_tag(),
            'attributes' => $attributes,
            'content' => $content,
        ];
        
        return 'shortcode_' . md5(serialize($cache_data));
    }
    
    /**
     * Check if caching is enabled
     * 
     * @return bool
     */
    protected function is_cache_enabled(): bool {
        return false; // Override in child classes to enable caching
    }
    
    /**
     * Get cached content
     * 
     * @param string $cache_key Cache key
     * @return string|false Cached content or false if not found
     */
    protected function get_cached_content(string $cache_key) {
        return get_transient($cache_key);
    }
    
    /**
     * Cache content
     * 
     * @param string $cache_key Cache key
     * @param string $content Content to cache
     * @param int $duration Cache duration in seconds
     * @return bool Success status
     */
    protected function cache_content(string $cache_key, string $content, int $duration = 3600): bool {
        return set_transient($cache_key, $content, $duration);
    }
    
    /**
     * Load template file
     * 
     * @param string $template_name Template name
     * @param array $variables Variables to pass to template
     * @return string Rendered template
     */
    protected function load_template(string $template_name, array $variables = []): string {
        // Extract variables for use in template
        extract($variables, EXTR_SKIP);
        
        // Start output buffering
        ob_start();
        
        // Look for template in theme first, then plugin
        $template_paths = [
            get_stylesheet_directory() . '/nhk-events/' . $template_name . '.php',
            get_template_directory() . '/nhk-events/' . $template_name . '.php',
            $this->get_plugin_template_path() . $template_name . '.php',
        ];
        
        $template_found = false;
        foreach ($template_paths as $template_path) {
            if (file_exists($template_path)) {
                include $template_path;
                $template_found = true;
                break;
            }
        }
        
        if (!$template_found) {
            echo $this->render_error(sprintf(
                __('Template not found: %s', 'nhk-framework'),
                $template_name
            ));
        }
        
        return ob_get_clean();
    }
    
    /**
     * Get plugin template path
     * 
     * Override in child classes to provide correct path.
     * 
     * @return string Template path
     */
    protected function get_plugin_template_path(): string {
        return '';
    }
    
    /**
     * Enqueue shortcode assets
     * 
     * Override in child classes to enqueue specific assets.
     * 
     * @return void
     */
    protected function enqueue_assets(): void {
        // Override in child classes
    }
    
    /**
     * Get shortcode help text
     * 
     * Override in child classes to provide help documentation.
     * 
     * @return string Help text
     */
    public function get_help_text(): string {
        return sprintf(
            __('Usage: [%s]', 'nhk-framework'),
            $this->get_shortcode_tag()
        );
    }
    
    /**
     * Get shortcode examples
     * 
     * Override in child classes to provide usage examples.
     * 
     * @return array Examples
     */
    public function get_examples(): array {
        return [
            sprintf('[%s]', $this->get_shortcode_tag()),
        ];
    }
}
