<?php
/**
 * Abstract Settings Page Class for NHK Framework
 * 
 * This class provides a base for creating admin settings pages using WordPress
 * Settings API with additional framework features.
 * 
 * @package NHK\Framework\Abstracts
 * @since 1.0.0
 */

namespace NHK\Framework\Abstracts;

use NHK\Framework\Container\Container;

/**
 * Abstract class for Settings Pages
 * 
 * Provides a structured way to create settings pages with:
 * - WordPress Settings API integration
 * - Tabbed interface support
 * - Automatic form handling
 * - Security and validation
 */
abstract class Abstract_Settings_Page {
    
    /**
     * Service container
     * 
     * @var Container
     */
    protected Container $container;
    
    /**
     * Page hook suffix
     * 
     * @var string
     */
    protected string $page_hook;
    
    /**
     * Constructor
     * 
     * @param Container $container Service container
     */
    public function __construct(Container $container) {
        $this->container = $container;
    }
    
    /**
     * Initialize the settings page
     * 
     * @return void
     */
    public function init(): void {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }
    
    /**
     * Add menu page
     * 
     * Uses WordPress add_options_page() or add_menu_page() function.
     * 
     * @return void
     */
    public function add_menu_page(): void {
        $this->page_hook = add_options_page(
            $this->get_page_title(),
            $this->get_menu_title(),
            'manage_options',
            $this->get_menu_slug(),
            [$this, 'render_page']
        );
    }
    
    /**
     * Register settings
     * 
     * Uses WordPress Settings API functions.
     * 
     * @return void
     */
    public function register_settings(): void {
        $this->register_setting_sections();
        $this->register_setting_fields();
    }
    
    /**
     * Enqueue scripts and styles
     * 
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueue_scripts(string $hook): void {
        if ($hook !== $this->page_hook) {
            return;
        }
        
        // Enqueue WordPress color picker if needed
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        // Enqueue custom admin styles
        wp_enqueue_style('nhk-admin-settings', $this->get_admin_css_url(), [], '1.0.0');
        wp_enqueue_script('nhk-admin-settings', $this->get_admin_js_url(), ['jquery', 'wp-color-picker'], '1.0.0', true);
    }
    
    /**
     * Render the settings page
     * 
     * @return void
     */
    public function render_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'nhk-framework'));
        }
        
        $active_tab = $_GET['tab'] ?? 'general';
        $tabs = $this->get_tabs();
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($this->get_page_title()); ?></h1>
            
            <?php if (count($tabs) > 1): ?>
                <nav class="nav-tab-wrapper">
                    <?php foreach ($tabs as $tab_key => $tab_label): ?>
                        <a href="?page=<?php echo esc_attr($this->get_menu_slug()); ?>&tab=<?php echo esc_attr($tab_key); ?>" 
                           class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                            <?php echo esc_html($tab_label); ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields($this->get_option_group($active_tab));
                do_settings_sections($this->get_menu_slug() . '_' . $active_tab);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Get the page title
     * 
     * @return string
     */
    abstract protected function get_page_title(): string;
    
    /**
     * Get the menu title
     * 
     * @return string
     */
    protected function get_menu_title(): string {
        return $this->get_page_title();
    }
    
    /**
     * Get the menu slug
     * 
     * @return string
     */
    abstract protected function get_menu_slug(): string;
    
    /**
     * Get tabs configuration
     * 
     * @return array
     */
    protected function get_tabs(): array {
        return [
            'general' => __('General', 'nhk-framework'),
        ];
    }
    
    /**
     * Register setting sections
     * 
     * Override in child classes to register sections.
     * 
     * @return void
     */
    protected function register_setting_sections(): void {
        // Override in child classes
    }
    
    /**
     * Register setting fields
     * 
     * Override in child classes to register fields.
     * 
     * @return void
     */
    protected function register_setting_fields(): void {
        // Override in child classes
    }
    
    /**
     * Get option group for a tab
     * 
     * @param string $tab Tab key
     * @return string
     */
    protected function get_option_group(string $tab): string {
        return $this->get_menu_slug() . '_' . $tab;
    }
    
    /**
     * Get admin CSS URL
     * 
     * @return string
     */
    protected function get_admin_css_url(): string {
        return '';
    }
    
    /**
     * Get admin JS URL
     * 
     * @return string
     */
    protected function get_admin_js_url(): string {
        return '';
    }
    
    /**
     * Render a text field
     * 
     * @param array $args Field arguments
     * @return void
     */
    protected function render_text_field(array $args): void {
        $option_name = $args['option_name'];
        $field_name = $args['field_name'];
        $value = get_option($option_name, $args['default'] ?? '');
        $placeholder = $args['placeholder'] ?? '';
        $class = $args['class'] ?? 'regular-text';
        
        printf(
            '<input type="text" id="%s" name="%s" value="%s" placeholder="%s" class="%s" />',
            esc_attr($field_name),
            esc_attr($option_name),
            esc_attr($value),
            esc_attr($placeholder),
            esc_attr($class)
        );
        
        if (!empty($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
    
    /**
     * Render a textarea field
     * 
     * @param array $args Field arguments
     * @return void
     */
    protected function render_textarea_field(array $args): void {
        $option_name = $args['option_name'];
        $field_name = $args['field_name'];
        $value = get_option($option_name, $args['default'] ?? '');
        $rows = $args['rows'] ?? 5;
        $class = $args['class'] ?? 'large-text';
        
        printf(
            '<textarea id="%s" name="%s" rows="%d" class="%s">%s</textarea>',
            esc_attr($field_name),
            esc_attr($option_name),
            absint($rows),
            esc_attr($class),
            esc_textarea($value)
        );
        
        if (!empty($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
    
    /**
     * Render a checkbox field
     * 
     * @param array $args Field arguments
     * @return void
     */
    protected function render_checkbox_field(array $args): void {
        $option_name = $args['option_name'];
        $field_name = $args['field_name'];
        $value = get_option($option_name, $args['default'] ?? '');
        $label = $args['label'] ?? '';
        
        printf(
            '<label><input type="checkbox" id="%s" name="%s" value="1" %s /> %s</label>',
            esc_attr($field_name),
            esc_attr($option_name),
            checked($value, '1', false),
            esc_html($label)
        );
        
        if (!empty($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
    
    /**
     * Render a select field
     * 
     * @param array $args Field arguments
     * @return void
     */
    protected function render_select_field(array $args): void {
        $option_name = $args['option_name'];
        $field_name = $args['field_name'];
        $value = get_option($option_name, $args['default'] ?? '');
        $options = $args['options'] ?? [];
        $class = $args['class'] ?? '';
        
        printf('<select id="%s" name="%s" class="%s">', esc_attr($field_name), esc_attr($option_name), esc_attr($class));
        
        foreach ($options as $option_value => $option_label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($option_value),
                selected($value, $option_value, false),
                esc_html($option_label)
            );
        }
        
        echo '</select>';
        
        if (!empty($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
    
    /**
     * Render a number field
     * 
     * @param array $args Field arguments
     * @return void
     */
    protected function render_number_field(array $args): void {
        $option_name = $args['option_name'];
        $field_name = $args['field_name'];
        $value = get_option($option_name, $args['default'] ?? '');
        $min = $args['min'] ?? '';
        $max = $args['max'] ?? '';
        $step = $args['step'] ?? '';
        $class = $args['class'] ?? 'small-text';
        
        printf(
            '<input type="number" id="%s" name="%s" value="%s" min="%s" max="%s" step="%s" class="%s" />',
            esc_attr($field_name),
            esc_attr($option_name),
            esc_attr($value),
            esc_attr($min),
            esc_attr($max),
            esc_attr($step),
            esc_attr($class)
        );
        
        if (!empty($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
}
