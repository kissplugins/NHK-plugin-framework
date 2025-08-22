# The Six Guiding Principles of WordPress Plugin Development per KISS Plugins

## 1. All Plugins → Plugin Listings Always Have Settings Link

Every plugin must provide an easily accessible settings link directly from the WordPress admin plugins page. This means implementing the `plugin_action_links` filter to add a "Settings" link next to "Activate" and "Deactivate" actions. Users should never have to hunt through WordPress menus to configure your plugin.

**Implementation:**
```php
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'add_settings_link');

function add_settings_link($links) {
    $settings_link = '<a href="admin.php?page=your-plugin-settings">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
```

## 2. All Plugins Should Use Progressive Loading

Load only what's needed, when it's needed. Implement lazy loading for heavy resources, use conditional loading for admin-only functionality, and leverage WordPress's built-in optimization features like script localization and dependency management. Your plugin should have minimal impact on page load times and overall site performance.

**Key Strategies:**
- Load admin scripts/styles only on admin pages that need them
- Use `wp_enqueue_script()` and `wp_enqueue_style()` with proper dependencies
- Implement database query optimization and caching where appropriate
- Consider using transients for expensive operations
- Load frontend assets conditionally based on shortcodes or blocks present

**Implement Light Caching for Data Tables:**
Never make users wait for the same data to reload repeatedly. Implement intelligent caching for data-heavy tables and listings using WordPress transients or object cache. Cache should be lightweight but effective - store processed data for reasonable periods (5-60 minutes depending on data sensitivity).

**Always Provide Cache Control:**
Include a "Force Reload" or "Refresh Cache" button on admin tables and data displays. Users should have control over when they want fresh data versus cached results. This is essential for debugging, real-time monitoring, or when users know data has changed.

```php
// Example: Cached data with refresh control
if (isset($_POST['force_refresh']) || false === ($cached_data = get_transient('plugin_table_data'))) {
    $fresh_data = expensive_database_operation();
    set_transient('plugin_table_data', $fresh_data, HOUR_IN_SECONDS);
    $table_data = $fresh_data;
} else {
    $table_data = $cached_data;
}
```

## 3. All Plugins Should Follow Established WordPress Patterns

Embrace the WordPress way of doing things. Use existing WordPress APIs, functions, and UI patterns instead of reinventing the wheel. This ensures consistency across the WordPress ecosystem, reduces learning curves for users, and keeps your code maintainable and future-proof.

**Don't Reinvent the Wheel - Especially UI/UX:**
Before building custom interfaces, check if WordPress core already provides what you need. Use WordPress's built-in UI components like `WP_List_Table` for data tables, Settings API for options pages, and WordPress admin CSS classes for styling. Users already know how these work - leverage that familiarity.

**WordPress Standards to Follow:**
- Use WordPress coding standards (WPCS)
- Leverage WordPress APIs: Settings API, Options API, Transients API, etc.
- Follow WordPress admin UI patterns and styling (`wp-admin` CSS classes, metaboxes, etc.)
- Use existing WordPress components: `WP_List_Table`, admin notices, modal dialogs
- Use WordPress hooks and filters appropriately
- Implement WordPress-style error handling and logging
- Keep it DRY (Don't Repeat Yourself) by using WordPress core functions

**UI/UX Components to Reuse:**
- Admin notices (`admin_notices` hook)
- WordPress buttons (`.button`, `.button-primary`, `.button-secondary`)
- WordPress form styling and metaboxes
- WordPress color schemes and typography
- WordPress modal/dialog patterns
- WordPress tabs and accordion interfaces

## 4. All Plugins Must Handle Deactivation and Uninstall Gracefully

Clean up after yourself completely. Register proper deactivation and uninstall hooks that remove custom database tables, delete options, clear scheduled events, and remove any files created. Users should be able to remove your plugin without leaving database bloat or orphaned data.

**Essential Cleanup:**
- Remove custom database tables
- Delete plugin options and transients
- Clear scheduled cron events
- Remove custom user roles and capabilities
- Delete uploaded files and directories
- Provide clear migration paths if users switch plugins

**Implementation:**
```php
// Deactivation hook
register_deactivation_hook(__FILE__, 'plugin_deactivation');

// Uninstall hook
register_uninstall_hook(__FILE__, 'plugin_uninstall');
// OR create uninstall.php file
```

## 5. All Plugins Must Implement Proper Security from Day One

Never trust user input - sanitize inputs, validate data, and escape outputs religiously. Security isn't something you bolt on later; it must be baked into your architecture from the first line of code. Follow the principle of least privilege and implement defense in depth.

**Security Essentials:**
- Use WordPress nonces for all form submissions
- Implement proper capability checks before allowing actions
- Sanitize all inputs using WordPress sanitization functions
- Validate data types and ranges
- Escape all outputs using appropriate WordPress functions
- Use prepared statements for database queries
- Implement rate limiting for sensitive operations
- Follow OWASP security guidelines

## 6. All Plugins Must Be Translation-Ready and Accessible

Build inclusively from the start. Wrap all user-facing strings in proper internationalization functions and follow WCAG accessibility guidelines. Your plugin should work for users regardless of their language, abilities, or how they interact with their WordPress site.

**Internationalization Requirements:**
- Use `__()`, `_e()`, `_n()`, and other i18n functions
- Provide a unique text domain
- Generate .pot files for translators
- Support RTL languages when applicable

**Accessibility Standards:**
- Use semantic HTML elements
- Implement proper ARIA labels and roles
- Ensure keyboard navigation support
- Maintain sufficient color contrast (4.5:1 minimum)
- Provide alternative text for images
- Test with screen readers
- Follow WordPress accessibility coding standards

---

## The Golden Rule

**Remember: You're contributing to an ecosystem used by millions.** Every plugin should enhance the WordPress experience while respecting the platform's principles, user expectations, and the broader community. When in doubt, ask yourself: "Does this make WordPress better for everyone?"
