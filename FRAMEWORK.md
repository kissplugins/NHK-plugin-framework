# PRD: Neochrome Hypercart Kiss (NHK) WordPress Plugin Starter Framework

**Version**: 1.1

## 1. Overview

### 1.1. Problem Statement
Development of WordPress plugins across our three organizations (Neochrome, Hypercart, Kiss) is inconsistent. Each project starts from scratch, leading to duplicated effort, varied code quality, security oversights, and increased long-term maintenance costs.

### 1.2. Goal
To create a standardized, feature-rich, and robust WordPress plugin starter framework named "NHK Framework". This framework will serve as the foundation for all new plugin projects, ensuring adherence to modern development practices, improving code consistency, and accelerating the development lifecycle.

### 1.3. Target User
Internal WordPress/PHP developers at Neochrome, Hypercart, and Kiss.

---

## 2. Guiding Principles

- **Developer-Centric:** The framework must be easy to use, well-documented, and reduce boilerplate code.
- **OOP & Modern PHP:** All code must follow modern Object-Oriented principles and PSR-4 standards.
- **WordPress-First:** Leverage existing WordPress APIs and functions before creating custom solutions.
- **Secure by Default:** Security best practices (nonces, sanitization, escaping) must be integrated and easy to use.
- **Performant by Design:** The framework should encourage efficient coding practices, including caching and conditional asset loading.
- **Testable & Maintainable:** The architecture must be modular and include a foundation for automated testing.
- **Version Control:** Every iteration and change must include appropriate version bumping following semantic versioning.
- **DRY Principle:** Don't Repeat Yourself - use existing WordPress core functionality wherever possible.

---

## 3. Functional Requirements

### 3.1. WordPress Core Function Priority

#### 3.1.1. Core API Usage Guidelines
**CRITICAL:** Before implementing any custom functionality, developers MUST check for existing WordPress core solutions:

- **Database Operations:** Use `$wpdb`, `get_option()`, `update_option()`, `get_post_meta()`, `update_post_meta()` before custom queries
- **User Management:** Leverage `wp_get_current_user()`, `user_can()`, `get_userdata()`, `wp_create_user()`
- **HTTP Requests:** Use `wp_remote_get()`, `wp_remote_post()`, `wp_safe_remote_request()` instead of curl
- **File Operations:** Use `WP_Filesystem`, `wp_handle_upload()`, `wp_mkdir_p()` 
- **Caching:** Use WordPress Transients API (`set_transient()`, `get_transient()`) and Object Cache API
- **Validation/Sanitization:** Use WordPress functions like `sanitize_text_field()`, `esc_html()`, `wp_kses()`, `absint()`
- **URLs and Paths:** Use `home_url()`, `admin_url()`, `plugin_dir_url()`, `plugin_dir_path()`
- **Hooks System:** Always use `add_action()`, `add_filter()`, `do_action()`, `apply_filters()`
- **Localization:** Use `__()`, `_e()`, `_n()`, `wp_localize_script()`
- **Script/Style Loading:** Use `wp_enqueue_script()`, `wp_enqueue_style()` exclusively

#### 3.1.2. WordPress API Checklist
Before creating custom implementations, consult these WordPress APIs:
- Settings API for admin pages
- Customizer API for theme customization
- REST API for endpoints
- Rewrite API for custom URLs
- Metadata API for custom fields
- Options API for storing settings
- Transients API for temporary data
- Widgets API for widget functionality
- Shortcode API for content insertion
- Plugin API for extensibility

### 3.2. Core Architecture

#### 3.2.1. Foundation
- **PSR-4 Autoloader:** Implement a PSR-4 compliant autoloader using Composer. The primary namespace should be configurable but default to `NHK\{PluginName}`.
- **Directory Structure:** Use a logical directory structure:
  ```
  /src
    /Admin
    /Api
    /Core
    /Cpt
    /Database
    /Frontend
    /Helpers
    /Services
  /assets
    /css
    /js
    /images
  /tests
    /unit
    /integration
    /fixtures
  /build
  /languages
  ```
- **Main Plugin Class:** A central `Plugin.php` class acts as the entry point, orchestrating component loading via a service container pattern.

#### 3.2.2. Service Container
- **Dependency Injection Container:** Implement a lightweight service container for managing dependencies.
- **Service Registration:** Provide methods for registering singletons, factories, and regular services.
- **Auto-wiring:** Support automatic dependency resolution where possible.

Example structure:
```php
class Plugin {
    private Container $container;
    
    public function __construct() {
        $this->container = new Container();
        $this->registerServices();
        $this->bootServices();
    }
    
    private function registerServices(): void {
        $this->container->singleton(Logger::class);
        $this->container->singleton(CPTManager::class);
        $this->container->singleton(AssetManager::class);
    }
}
```

### 3.3. Version Management System
- **Automatic Version Bumping:** Every code change must include a version bump in the plugin header and `composer.json`.
- **Version Constants:** Define version as a constant in the main plugin file: `define('NHK_PLUGIN_VERSION', '1.0.0');`
- **Migration System:** Track version changes and run migrations when plugin version increases.
- **Version Check Hook:** On `admin_init`, check if stored version differs from current version and trigger update routines if needed.
- **Changelog Generation:** Maintain a `CHANGELOG.md` file that must be updated with every version bump.

### 3.4. Debugging System
- **Logger Class:** Create a comprehensive `Logger` class that wraps WordPress debug functions when available.
- **Use WP_DEBUG:** Respect `WP_DEBUG`, `WP_DEBUG_LOG`, and `WP_DEBUG_DISPLAY` constants.
- **Admin Settings:** Provide toggles in WP Admin:
  - "Enable Frontend Debugging" - outputs to browser console
  - "Enable Backend Debugging" - displays as admin notices
  - "Enable File Logging" - writes to debug.log file
  - "Debug Level" - select between ERROR, WARNING, INFO, DEBUG
- **Frontend Logging:** Namespaced console output: `console.log('[NHK Plugin Name]:', data)`
- **Backend Logging:** Dismissible admin notices with severity levels (error, warning, info, success)
- **Structured Logging:** Support for context data and structured log messages
- **WordPress Integration:** Use `error_log()` when `WP_DEBUG_LOG` is enabled

### 3.5. Content Type Management

#### 3.5.1. CPT Manager
- **WordPress Functions First:** Use `register_post_type()`, `register_taxonomy()` directly
- **Automatic Registration:** CPT Manager automatically discovers and registers all CPT classes
- **Abstract CPT Class:** Base class with common functionality:
  ```php
  abstract class Abstract_CPT {
      abstract protected function get_slug(): string;
      abstract protected function get_labels(): array;
      abstract protected function get_args(): array;
      protected function get_capabilities(): array { /* default implementation */ }
      protected function register_meta_boxes(): void { /* uses add_meta_box() */ }
      protected function register_columns(): array { /* uses manage_posts_columns filter */ }
  }
  ```
- **Meta Box Integration:** Built-in support using WordPress `add_meta_box()` function
- **Admin Column Management:** Use `manage_{post_type}_posts_columns` and related filters

#### 3.5.2. Taxonomy Manager
- **Abstract Taxonomy Class:** Similar pattern to CPT management
- **Hierarchical Support:** Handle both hierarchical and non-hierarchical taxonomies
- **Term Meta Support:** Use WordPress `add_term_meta()`, `get_term_meta()` functions

### 3.6. Admin Experience

#### 3.6.1. Menu & Settings Management
- **WordPress Functions:** Use `add_menu_page()`, `add_submenu_page()`, `add_options_page()`
- **Settings API:** Wrapper should enhance, not replace, WordPress Settings API
- **Settings Link:** Automatically add "Settings" link on Plugins page using `plugin_action_links_` filter
- **Menu Generator:** Configuration-based menu registration that calls WordPress functions:
  ```php
  'menus' => [
      [
          'type' => 'menu', // calls add_menu_page()
          'page_title' => 'NHK Settings',
          'menu_title' => 'NHK',
          'capability' => 'manage_options',
          'slug' => 'nhk-settings',
          'icon' => 'dashicons-admin-generic',
          'position' => 80,
          'render' => [SettingsPage::class, 'render']
      ]
  ]
  ```
- **Settings API Wrapper:** Use `register_setting()`, `add_settings_section()`, `add_settings_field()`
- **Tab System:** Built-in tabbed interface for settings pages

#### 3.6.2. Admin Notices Manager
- **WordPress Hooks:** Use `admin_notices` action for displaying notices
- **Persistent Notices:** Store using WordPress options or transients
- **User-specific Notices:** Use `get_user_meta()` and `update_user_meta()`
- **Dismissible Notices:** AJAX-powered using `wp_ajax_` hooks

### 3.7. Automated Updates
- **GitHub Integration:** Integrate `YahnisElsts/plugin-update-checker` via Composer
- **Configuration:** Simple initialization in main plugin class:
  ```php
  $this->updater = Updater::init(
      'https://github.com/org/plugin-name',
      __FILE__,
      'plugin-slug'
  );
  ```
- **Release Management:** Support for pre-release versions and stable channels
- **Update Notices:** Custom update messages and changelogs

### 3.8. Health Check System

#### 3.8.1. UI Integration
- **Status Page:** Dedicated "Health Check" tab in plugin settings
- **Visual Indicators:** Green/yellow/red status indicators for each check
- **Run Button:** "Run System Checks" button with loading state
- **AJAX Execution:** Use WordPress AJAX API (`wp_ajax_` actions)
- **Check History:** Store using WordPress options API

#### 3.8.2. Required Checks
- **Core Functionality Check:**
  - Verify CPTs are registered using `post_type_exists()`
  - Check database tables exist using `$wpdb->get_var()`
  - Verify required WordPress capabilities using `current_user_can()`
  - Confirm PHP extensions are loaded
  
- **Frontend Output Check:**
  - Fetch page with shortcode using `wp_remote_get()`
  - Verify expected HTML output
  - Check asset loading (CSS/JS)
  - Test AJAX endpoints

- **Performance Check:**
  - Database query performance
  - Autoload options size
  - Cache availability using `wp_cache_get()`
  - Memory usage

#### 3.8.3. Custom Check Registration
- **Extensible System:** Allow developers to register custom health checks:
  ```php
  HealthCheck::register('custom_check', [
      'label' => 'Custom System Check',
      'callback' => [CustomChecker::class, 'run'],
      'severity' => 'critical' // critical, warning, info
  ]);
  ```

### 3.9. Database Management

#### 3.9.1. Migration System
- **Use $wpdb:** All database operations must use WordPress `$wpdb` global
- **dbDelta Function:** Use `dbDelta()` for table creation and updates
- **Migration Classes:** Each migration extends `Abstract_Migration`:
  ```php
  class CreateCustomTable extends Abstract_Migration {
      public function up(): void { 
          global $wpdb;
          $charset_collate = $wpdb->get_charset_collate();
          // Use dbDelta() for table creation
      }
      public function down(): void { /* drop table using $wpdb */ }
      public function version(): string { return '1.0.0'; }
  }
  ```
- **Migration Runner:** Automatically run pending migrations on plugin activation/update
- **Rollback Support:** Ability to rollback migrations in development

#### 3.9.2. Database Abstraction
- **Query Builder:** Wrapper around `$wpdb` with fluent interface - NOT a replacement
- **Schema Builder:** Use `dbDelta()` for table operations
- **Model Classes:** Optional ActiveRecord-style model classes that use `$wpdb`

### 3.10. REST API Support

#### 3.10.1. API Scaffolding
- **WordPress REST API:** Use `register_rest_route()` for all endpoints
- **Abstract Endpoint Class:**
  ```php
  abstract class Abstract_Endpoint {
      abstract protected function get_namespace(): string;
      abstract protected function get_route(): string;
      abstract protected function get_methods(): array;
      abstract protected function handle_request(WP_REST_Request $request);
      protected function permission_check(WP_REST_Request $request): bool {
          // Use current_user_can() for capability checks
      }
  }
  ```
- **Automatic Registration:** Discover and register all endpoint classes
- **Built-in Validation:** Use WordPress REST API schema validation
- **Response Formatting:** Use `WP_REST_Response` for responses

#### 3.10.2. Authentication
- **Nonce Verification:** Use `wp_verify_nonce()` for cookie auth
- **Application Passwords:** Support WordPress application password authentication
- **Custom Auth:** Extensible authentication system using WordPress filters

### 3.11. Background Processing

#### 3.11.1. Job Queue System
- **WordPress Cron First:** Consider `wp_schedule_event()` before external libraries
- **Action Scheduler Integration:** Wrapper around Action Scheduler library when needed
- **Job Classes:** Abstract job class for background tasks:
  ```php
  abstract class Abstract_Job {
      abstract public function handle(array $args): void;
      public function failed(Exception $e): void { /* log failure */ }
  }
  ```
- **Scheduling Interface:** Simple API for scheduling jobs:
  ```php
  JobQueue::dispatch(SendEmailJob::class, ['user_id' => 123])
      ->delay(300) // 5 minutes
      ->onQueue('emails');
  ```

### 3.12. Asset Management

#### 3.12.1. Asset Loading
- **WordPress Enqueue System:** Always use `wp_enqueue_script()` and `wp_enqueue_style()`
- **Conditional Loading:** Load assets only where needed:
  ```php
  AssetManager::register('admin-script')
      ->src('admin.js')
      ->deps(['jquery']) // Use WordPress script handles
      ->version(filemtime())
      ->in_footer(true)
      ->condition(function() {
          return is_admin() && get_current_screen()->id === 'nhk-settings';
      });
  ```
- **Automatic Versioning:** Use file modification time or git hash for cache busting
- **Dependency Management:** Use WordPress script dependencies

#### 3.12.2. Build Pipeline
- **Webpack Configuration:** Standardized webpack config for modern JS/CSS
- **NPM Scripts:** Common build, watch, and production scripts
- **Asset Manifest:** Generate manifest file for enqueued assets
- **WordPress Scripts:** Consider using `@wordpress/scripts` package

### 3.13. Security Helpers

#### 3.13.1. Security Class
- **WordPress Functions First:** Wrapper should use WordPress security functions:
- **Nonce Management:**
  ```php
  Security::createNonce('action_name'); // Uses wp_create_nonce()
  Security::verifyNonce($_POST['nonce'], 'action_name'); // Uses wp_verify_nonce()
  ```
- **Input Sanitization:**
  ```php
  Security::sanitize($_POST['email'], 'email'); // Uses sanitize_email()
  Security::sanitize($_POST['content'], 'textarea'); // Uses sanitize_textarea_field()
  ```
- **Output Escaping:**
  ```php
  Security::escape($data, 'html'); // Uses esc_html()
  Security::escape($url, 'url'); // Uses esc_url()
  ```

#### 3.13.2. CSRF Protection
- **WordPress Nonces:** Use WordPress nonce system as primary CSRF protection
- **Token Manager:** Advanced CSRF token system for AJAX operations
- **Automatic Injection:** Auto-inject tokens into forms
- **Rate Limiting:** Built-in rate limiting for sensitive operations

#### 3.13.3. Input Validation
- **WordPress Validation:** Use `sanitize_*` and `wp_kses` functions first
- **Validation Rules:**
  ```php
  Validator::make($data, [
      'email' => 'required|email', // Uses is_email()
      'age' => 'required|integer|min:18',
      'website' => 'url|nullable' // Uses esc_url_raw()
  ]);
  ```
- **Custom Rules:** Extensible validation rule system
- **Error Messages:** Customizable error messages

### 3.14. Capability Management
- **WordPress Capabilities:** Use `add_cap()`, `remove_cap()`, `current_user_can()`
- **Custom Capabilities:** System for defining and checking custom capabilities:
  ```php
  Capabilities::define('edit_nhk_settings', ['administrator', 'editor']);
  Capabilities::can('edit_nhk_settings'); // Uses current_user_can()
  ```
- **Role Management:** Use `add_role()`, `remove_role()`, `get_role()`

### 3.15. Caching Layer

#### 3.15.1. Cache Wrapper
- **WordPress Cache API First:** Use Object Cache and Transients API
- **Unified Interface:** Wrapper for WordPress transients and object cache:
  ```php
  Cache::remember('expensive_query', function() {
      return expensive_database_query();
  }, 3600); // Uses set_transient() or wp_cache_set()
  ```
- **Cache Tags:** Support for cache tagging and bulk invalidation
- **Driver Support:** Detect and use persistent object cache when available

### 3.16. Internationalization
- **WordPress i18n:** Use WordPress localization functions exclusively
- **Text Domain Management:** Use `load_plugin_textdomain()`
- **Translation Helpers:** Wrapper for `__()`, `_e()`, `_n()`, `_x()`, etc.
- **JavaScript Translations:** Use `wp_set_script_translations()`

### 3.17. Testing Infrastructure

#### 3.17.1. Unit Testing
- **Base Test Case:** Extended PHPUnit test case with WordPress mocks
- **Factory Classes:** Test data factories for common objects
- **Assertion Helpers:** Custom assertions for WordPress-specific checks

#### 3.17.2. Integration Testing
- **WordPress Test Suite:** Integration with WordPress test framework
- **Database Transactions:** Automatic transaction rollback after tests
- **HTTP Mocking:** Mock external API calls

#### 3.17.3. Testing Environment
- **Docker Configuration:** Docker compose setup for testing
- **CI/CD Templates:** GitHub Actions/GitLab CI configurations
- **Coverage Reports:** Code coverage reporting setup

### 3.18. Governance File
Create `Agents.md` in repository root with these rules:
```markdown
# Development Governance Rules

1. **WordPress Core First:** Always check for existing WordPress functions, hooks, and APIs before implementing custom solutions. This includes but is not limited to: database operations, user management, HTTP requests, caching, validation, sanitization, and localization.

2. **No Unnecessary Refactoring:** No refactoring that is not absolutely necessary to implement a required feature or fix a bug.

3. **Data Stability:** Do not change existing data structures, models, CPT slugs, or taxonomy slugs unless it is a primary, explicitly requested feature of a project. Data stability is paramount.

4. **Version Bumping:** Every code change, no matter how small, must include a version bump following semantic versioning (MAJOR.MINOR.PATCH).

5. **Backward Compatibility:** Breaking changes require a major version bump and must be documented in UPGRADE.md.

6. **Testing Required:** New features must include corresponding tests. Bug fixes must include regression tests.

7. **Documentation Updates:** Any public API change must be reflected in documentation before merging.

8. **DRY Compliance:** Don't Repeat Yourself - if WordPress core provides a function, use it. Custom implementations are only acceptable when core functionality is genuinely insufficient.
```

---

## 4. Non-Functional Requirements

### 4.1. Performance
- **Autoload Optimization:** Optimize Composer autoloader for production
- **Lazy Loading:** Components should load only when needed
- **Database Queries:** No more than 5 additional queries per page load
- **Memory Usage:** Framework overhead should not exceed 2MB
- **WordPress Optimization:** Use WordPress built-in caching and optimization features

### 4.2. Security
- **OWASP Compliance:** Follow OWASP WordPress Security Guidelines
- **WordPress Security:** Follow WordPress Security Best Practices
- **Automated Security Scanning:** Include security scanning in CI/CD
- **Secure Defaults:** All features must be secure by default
- **Regular Updates:** Security patches must be released within 48 hours of discovery

### 4.3. Compatibility
- **WordPress Version:** Compatible with WordPress 6.0+
- **PHP Version:** PHP 8.0+ required, PHP 8.3 compatible
- **Database:** MySQL 5.7+ / MariaDB 10.3+
- **Browser Support:** Modern browsers (last 2 versions)

### 4.4. Documentation
- **PHPDoc Comments:** All classes, methods, and properties must have PHPDoc comments
- **WordPress Function References:** Document which WordPress functions are being wrapped or extended
- **README.md:** Comprehensive getting started guide
- **Wiki:** Detailed documentation for each component
- **Example Plugin:** Full-featured example plugin demonstrating all framework features
- **API Documentation:** Auto-generated API docs from PHPDoc
- **Migration Guide:** Guide for converting existing plugins to use framework

### 4.5. Code Quality
- **Coding Standards:** WordPress Coding Standards enforced via PHPCS
- **Static Analysis:** PHPStan level 6 minimum
- **Code Coverage:** Minimum 80% test coverage for framework code
- **Continuous Integration:** All PRs must pass CI checks
- **WordPress Best Practices:** Follow WordPress Plugin Handbook guidelines

---

## 5. Technical Stack

- **Language:** PHP 8.0+
- **Environment:** WordPress 6.0+
- **Dependency Management:** Composer
- **Required Libraries:**
  - `YahnisElsts/plugin-update-checker` - Automated updates
  - `woocommerce/action-scheduler` - Background processing (when WP Cron insufficient)
  - `symfony/dependency-injection` - Service container (optional, or custom implementation)
- **Development Tools:**
  - PHPUnit 9+ - Unit testing
  - Cypress/Playwright - E2E testing
  - PHPCS with WordPress standards
  - PHPStan for static analysis
  - Webpack 5 for asset building
  - @wordpress/scripts - WordPress build tools
- **Optional Libraries:**
  - `monolog/monolog` - Advanced logging (when WordPress logging insufficient)
  - `vlucas/phpdotenv` - Environment configuration
  - `illuminate/validation` - Advanced validation (extends WordPress validation)

---

## 6. Implementation Phases

### Phase 1: Core Foundation (Week 1-2)
- Service container implementation
- Autoloading setup
- Basic plugin structure
- Version management system
- Logging system (using WordPress functions)

### Phase 2: Content Management (Week 3-4)
- CPT Manager and Abstract classes
- Taxonomy Manager
- Meta box integration (using WordPress functions)
- Admin columns

### Phase 3: Admin & Settings (Week 5-6)
- Menu generation system (wrapping WordPress functions)
- Settings API wrapper
- Admin notices manager
- Health check system

### Phase 4: Advanced Features (Week 7-8)
- REST API scaffolding (using WordPress REST API)
- Database migration system (using dbDelta)
- Background job queue
- Asset management (enhancing wp_enqueue system)

### Phase 5: Security & Performance (Week 9-10)
- Security helpers implementation (wrapping WordPress functions)
- Caching layer (using WordPress cache APIs)
- Performance optimizations
- Input validation system

### Phase 6: Testing & Documentation (Week 11-12)
- Testing infrastructure setup
- Example plugin creation
- Documentation writing
- CI/CD pipeline setup

---

## 7. Acceptance Criteria (Definition of Done)

### 7.1. Functional Criteria
- ✅ A new plugin can be created from the framework in under 5 minutes
- ✅ All functional requirements are implemented and working
- ✅ Health checks run successfully and report accurate status
- ✅ Automated updates work from GitHub releases
- ✅ Version bumping system is operational
- ✅ All debugging modes function correctly
- ✅ CPT and Taxonomy registration works without manual hookup
- ✅ Database migrations run automatically on version updates
- ✅ All wrappers properly use underlying WordPress functions

### 7.2. Quality Criteria
- ✅ 80% or higher test coverage on framework code
- ✅ All code passes PHPCS with WordPress standards
- ✅ PHPStan analysis passes at level 6 or higher
- ✅ No critical or high security vulnerabilities in dependency scan
- ✅ No reimplementation of existing WordPress functionality

### 7.3. Documentation Criteria
- ✅ All public methods have complete PHPDoc comments
- ✅ README.md includes quick start guide
- ✅ Example plugin demonstrates all major features
- ✅ Migration guide for existing plugins is complete
- ✅ API documentation is auto-generated and accessible
- ✅ `Agents.md` governance file exists with specified rules
- ✅ `CHANGELOG.md` is present and follows Keep a Changelog format
- ✅ WordPress function usage is documented in code comments

### 7.4. Performance Criteria
- ✅ Framework adds no more than 2MB memory overhead
- ✅ Page load time increase is less than 100ms
- ✅ No more than 5 additional database queries per page
- ✅ Leverages WordPress caching mechanisms

---

## 8. Example Usage

### Creating a New Plugin
```bash
composer create-project nhk/framework my-plugin
cd my-plugin
npm install
npm run build
```

### Registering a CPT (Using WordPress Functions)
```php
namespace MyPlugin\Cpt;

class ProductCPT extends Abstract_CPT {
    protected function get_slug(): string {
        return 'nhk_product';
    }
    
    protected function get_labels(): array {
        return [
            'name' => 'Products',
            'singular_name' => 'Product',
            // Labels that WordPress expects
        ];
    }
    
    protected function get_args(): array {
        return [
            'public' => true,
            'has_archive' => true,
            'supports' => ['title', 'editor', 'thumbnail'],
            // Args for register_post_type()
        ];
    }
}
```

### Adding a REST Endpoint (Using WordPress REST API)
```php
namespace MyPlugin\Api;

class ProductEndpoint extends Abstract_Endpoint {
    protected function get_namespace(): string {
        return 'my-plugin/v1';
    }
    
    protected function get_route(): string {
        return '/products';
    }
    
    protected function get_methods(): array {
        return ['GET', 'POST'];
    }
    
    protected function handle_request(WP_REST_Request $request) {
        // Uses WordPress REST API request object
        if ($request->get_method() === 'GET') {
            return $this->get_products();
        }
        return $this->create_product($request->get_json_params());
    }
}
```

---

## 9. Success Metrics

- **Development Speed:** 50% reduction in plugin development time
- **Code Consistency:** 90% code similarity across projects using framework
- **Bug Reduction:** 40% fewer bugs in production
- **Maintenance Time:** 60% reduction in maintenance hours per plugin
- **Developer Satisfaction:** 8/10 or higher developer satisfaction score
- **WordPress Compliance:** 100% of wrapped functions use WordPress core

---

## 10. Future Enhancements (Post-MVP)

- Gutenberg block scaffolding system (using @wordpress/blocks)
- WooCommerce integration helpers
- Multisite specific features (using WordPress multisite functions)
- GraphQL endpoint support
- Plugin marketplace integration
- Visual settings page builder
- Automated code generation CLI tool
- Plugin analytics and telemetry system
- A/B testing framework
- Feature flag system

---

## 11. WordPress Core Function Reference

### Critical WordPress Functions to Use
Before implementing ANY custom solution, check these WordPress resources:

#### Database
- `$wpdb` - Global database object
- `get_option()`, `update_option()`, `delete_option()`
- `get_post_meta()`, `update_post_meta()`, `delete_post_meta()`
- `get_user_meta()`, `update_user_meta()`, `delete_user_meta()`
- `get_term_meta()`, `update_term_meta()`, `delete_term_meta()`
- `dbDelta()` - For database schema updates

#### HTTP & Remote Requests
- `wp_remote_get()`, `wp_remote_post()`, `wp_remote_request()`
- `wp_safe_remote_get()`, `wp_safe_remote_post()`
- `wp_remote_retrieve_body()`, `wp_remote_retrieve_response_code()`

#### Security & Validation
- `wp_create_nonce()`, `wp_verify_nonce()`, `check_admin_referer()`
- `sanitize_text_field()`, `sanitize_email()`, `sanitize_url()`
- `esc_html()`, `esc_attr()`, `esc_url()`, `esc_js()`
- `wp_kses()`, `wp_kses_post()`, `wp_strip_all_tags()`
- `is_email()`, `is_serialized()`, `absint()`

#### Users & Capabilities
- `current_user_can()`, `user_can()`
- `wp_get_current_user()`, `get_userdata()`, `get_user_by()`
- `is_user_logged_in()`, `wp_create_user()`, `wp_update_user()`

#### Content & Hooks
- `add_action()`, `do_action()`, `remove_action()`
- `add_filter()`, `apply_filters()`, `remove_filter()`
- `register_post_type()`, `register_taxonomy()`
- `add_shortcode()`, `do_shortcode()`

#### Caching
- `wp_cache_get()`, `wp_cache_set()`, `wp_cache_delete()`
- `set_transient()`, `get_transient()`, `delete_transient()`

#### Files & Media
- `WP_Filesystem()` - File system abstraction
- `wp_handle_upload()`, `wp_handle_sideload()`
- `wp_mkdir_p()`, `wp_upload_dir()`

#### URLs & Paths
- `home_url()`, `site_url()`, `admin_url()`
- `plugin_dir_url()`, `plugin_dir_path()`
- `get_template_directory_uri()`, `get_stylesheet_directory_uri()`

---

## Instructions for LLM Implementation

When implementing this framework, follow these priorities:

1. **Check WordPress first** - Before writing any custom function, verify WordPress doesn't already provide it
2. **Start with the core architecture** - Get the plugin base class, service container, and autoloading working first
3. **Implement version management early** - This needs to be in place before other features
4. **Focus on developer experience** - Make sure each component is easy to use with minimal configuration
5. **Write tests as you go** - Don't leave testing until the end
6. **Document everything** - Every public method needs PHPDoc comments, including which WP functions are used
7. **Follow WordPress conventions** - Even when using modern PHP, respect WordPress coding patterns where they make sense
8. **Make it extensible** - Developers should be able to override or extend any component
9. **Keep performance in mind** - Lazy load components, use efficient queries, implement caching
10. **Security first** - Every input must be sanitized, every output must be escaped
11. **Version bump everything** - Remember that every single code change requires a version bump
12. **Use WordPress hooks** - Always provide hooks for extensibility using do_action() and apply_filters()
13. **Leverage WordPress globals** - Use $wpdb, $wp_query, $wp_rewrite when appropriate

The framework should be production-ready, well-tested, and a joy to use for WordPress developers. Make it something developers will choose over starting from scratch every time.

---

## 12. DRY Principle Reinforcement

### Core Philosophy: Don't Repeat Yourself - Don't Repeat WordPress

The NHK Framework is built on the fundamental principle that **we should never reimplement what WordPress already provides**. This means:

#### Before You Code, Ask Yourself:
1. **Does WordPress have a function for this?** Check the WordPress Code Reference first.
2. **Is there a WordPress hook for this?** Look for existing actions and filters.
3. **Does WordPress Settings API handle this?** Don't build custom settings storage.
4. **Can WordPress handle this natively?** Use core functionality before adding dependencies.
5. **Is this in the WordPress way?** Follow platform conventions and patterns.

#### Why This Matters:
- **Reliability:** WordPress core functions are battle-tested across millions of sites
- **Security:** Core functions include security measures you might overlook
- **Performance:** WordPress functions are optimized and cached appropriately
- **Compatibility:** Using core functions ensures forward compatibility
- **Maintenance:** Less custom code means fewer bugs and easier updates
- **Learning Curve:** Developers already know WordPress functions

#### The Framework's Role:
The NHK Framework should **enhance and organize** WordPress functionality, not replace it. Every wrapper or abstraction should:
- Add value through better organization or developer experience
- Maintain full compatibility with the underlying WordPress function
- Document which WordPress functions are being used
- Allow direct access to WordPress functions when needed
- Never hide or restrict WordPress capabilities

#### Examples of Good DRY Practice:

❌ **Wrong:** Creating a custom database class from scratch
✅ **Right:** Creating a fluent wrapper around $wpdb that adds convenience methods

❌ **Wrong:** Building a custom user authentication system  
✅ **Right:** Using wp_authenticate() and extending with additional checks if needed

❌ **Wrong:** Writing custom sanitization functions
✅ **Right:** Using WordPress sanitize_* functions and creating typed wrappers

❌ **Wrong:** Implementing a custom caching mechanism
✅ **Right:** Using WordPress Transients API and Object Cache API

#### Remember:
**Every line of code you don't write is a line you don't have to maintain.** The best code is often the code that leverages what already exists. WordPress has been solving these problems for over 20 years - use that knowledge and experience to your advantage.

**The NHK Framework succeeds not by what it adds, but by how well it organizes and leverages what WordPress already provides.**
