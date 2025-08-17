# PRD: Neochrome Hypercart Kiss (NHK) WordPress Plugin Starter Framework

**Version**: 1.0

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
- **Secure by Default:** Security best practices (nonces, sanitization, escaping) must be integrated and easy to use.
- **Performant by Design:** The framework should encourage efficient coding practices, including caching and conditional asset loading.
- **Testable & Maintainable:** The architecture must be modular and include a foundation for automated testing.
- **Version Control:** Every iteration and change must include appropriate version bumping following semantic versioning.

---

## 3. Functional Requirements

### 3.1. Core Architecture

#### 3.1.1. Foundation
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

#### 3.1.2. Service Container
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

### 3.2. Version Management System
- **Automatic Version Bumping:** Every code change must include a version bump in the plugin header and `composer.json`.
- **Version Constants:** Define version as a constant in the main plugin file: `define('NHK_PLUGIN_VERSION', '1.0.0');`
- **Migration System:** Track version changes and run migrations when plugin version increases.
- **Version Check Hook:** On `admin_init`, check if stored version differs from current version and trigger update routines if needed.
- **Changelog Generation:** Maintain a `CHANGELOG.md` file that must be updated with every version bump.

### 3.3. Debugging System
- **Logger Class:** Create a comprehensive `Logger` class with multiple output targets.
- **Admin Settings:** Provide toggles in WP Admin:
  - "Enable Frontend Debugging" - outputs to browser console
  - "Enable Backend Debugging" - displays as admin notices
  - "Enable File Logging" - writes to debug.log file
  - "Debug Level" - select between ERROR, WARNING, INFO, DEBUG
- **Frontend Logging:** Namespaced console output: `console.log('[NHK Plugin Name]:', data)`
- **Backend Logging:** Dismissible admin notices with severity levels (error, warning, info, success)
- **Structured Logging:** Support for context data and structured log messages

### 3.4. Content Type Management

#### 3.4.1. CPT Manager
- **Automatic Registration:** CPT Manager automatically discovers and registers all CPT classes
- **Abstract CPT Class:** Base class with common functionality:
  ```php
  abstract class Abstract_CPT {
      abstract protected function get_slug(): string;
      abstract protected function get_labels(): array;
      abstract protected function get_args(): array;
      protected function get_capabilities(): array { /* default implementation */ }
      protected function register_meta_boxes(): void { /* override if needed */ }
      protected function register_columns(): array { /* override if needed */ }
  }
  ```
- **Meta Box Integration:** Built-in support for registering meta boxes
- **Admin Column Management:** Easy customization of admin list columns

#### 3.4.2. Taxonomy Manager
- **Abstract Taxonomy Class:** Similar pattern to CPT management
- **Hierarchical Support:** Handle both hierarchical and non-hierarchical taxonomies
- **Term Meta Support:** Built-in methods for handling term metadata

### 3.5. Admin Experience

#### 3.5.1. Menu & Settings Management
- **Settings Link:** Automatically add "Settings" link on Plugins page using `plugin_action_links_` filter
- **Menu Generator:** Configuration-based menu registration:
  ```php
  'menus' => [
      [
          'type' => 'menu', // or 'submenu'
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
- **Settings API Wrapper:** Simplified interface for WordPress Settings API
- **Tab System:** Built-in tabbed interface for settings pages

#### 3.5.2. Admin Notices Manager
- **Persistent Notices:** Store notices that survive redirects
- **User-specific Notices:** Show notices to specific users or roles
- **Dismissible Notices:** AJAX-powered dismissible notices with memory

### 3.6. Automated Updates
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

### 3.7. Health Check System

#### 3.7.1. UI Integration
- **Status Page:** Dedicated "Health Check" tab in plugin settings
- **Visual Indicators:** Green/yellow/red status indicators for each check
- **Run Button:** "Run System Checks" button with loading state
- **AJAX Execution:** Non-blocking check execution with progress updates
- **Check History:** Store last 10 check results with timestamps

#### 3.7.2. Required Checks
- **Core Functionality Check:**
  - Verify CPTs are registered using `post_type_exists()`
  - Check database tables exist
  - Verify required WordPress capabilities
  - Confirm PHP extensions are loaded
  
- **Frontend Output Check:**
  - Fetch page with shortcode using `wp_remote_get()`
  - Verify expected HTML output
  - Check asset loading (CSS/JS)
  - Test AJAX endpoints

- **Performance Check:**
  - Database query performance
  - Autoload options size
  - Cache availability
  - Memory usage

#### 3.7.3. Custom Check Registration
- **Extensible System:** Allow developers to register custom health checks:
  ```php
  HealthCheck::register('custom_check', [
      'label' => 'Custom System Check',
      'callback' => [CustomChecker::class, 'run'],
      'severity' => 'critical' // critical, warning, info
  ]);
  ```

### 3.8. Database Management

#### 3.8.1. Migration System
- **Migration Classes:** Each migration extends `Abstract_Migration`:
  ```php
  class CreateCustomTable extends Abstract_Migration {
      public function up(): void { /* create table */ }
      public function down(): void { /* drop table */ }
      public function version(): string { return '1.0.0'; }
  }
  ```
- **Migration Runner:** Automatically run pending migrations on plugin activation/update
- **Rollback Support:** Ability to rollback migrations in development

#### 3.8.2. Database Abstraction
- **Query Builder:** Wrapper around `$wpdb` with fluent interface
- **Schema Builder:** Programmatic table creation and modification
- **Model Classes:** Optional ActiveRecord-style model classes

### 3.9. REST API Support

#### 3.9.1. API Scaffolding
- **Abstract Endpoint Class:**
  ```php
  abstract class Abstract_Endpoint {
      abstract protected function get_namespace(): string;
      abstract protected function get_route(): string;
      abstract protected function get_methods(): array;
      abstract protected function handle_request(WP_REST_Request $request);
      protected function permission_check(WP_REST_Request $request): bool;
  }
  ```
- **Automatic Registration:** Discover and register all endpoint classes
- **Built-in Validation:** Schema validation for request parameters
- **Response Formatting:** Consistent response structure

#### 3.9.2. Authentication
- **Nonce Verification:** Built-in nonce checking for cookie auth
- **Application Passwords:** Support for application password authentication
- **Custom Auth:** Extensible authentication system

### 3.10. Background Processing

#### 3.10.1. Job Queue System
- **Action Scheduler Integration:** Wrapper around Action Scheduler library
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

### 3.11. Asset Management

#### 3.11.1. Asset Loading
- **Conditional Loading:** Load assets only where needed:
  ```php
  AssetManager::register('admin-script')
      ->src('admin.js')
      ->deps(['jquery'])
      ->version(filemtime())
      ->in_footer(true)
      ->condition(function() {
          return is_admin() && get_current_screen()->id === 'nhk-settings';
      });
  ```
- **Automatic Versioning:** Use file modification time or git hash for cache busting
- **Dependency Management:** Handle script and style dependencies

#### 3.11.2. Build Pipeline
- **Webpack Configuration:** Standardized webpack config for modern JS/CSS
- **NPM Scripts:** Common build, watch, and production scripts
- **Asset Manifest:** Generate manifest file for enqueued assets

### 3.12. Security Helpers

#### 3.12.1. Security Class
- **Nonce Management:**
  ```php
  Security::createNonce('action_name');
  Security::verifyNonce($_POST['nonce'], 'action_name');
  ```
- **Input Sanitization:**
  ```php
  Security::sanitize($_POST['email'], 'email');
  Security::sanitize($_POST['content'], 'textarea');
  ```
- **Output Escaping:**
  ```php
  Security::escape($data, 'html');
  Security::escape($url, 'url');
  ```

#### 3.12.2. CSRF Protection
- **Token Manager:** Advanced CSRF token system for AJAX operations
- **Automatic Injection:** Auto-inject tokens into forms
- **Rate Limiting:** Built-in rate limiting for sensitive operations

#### 3.12.3. Input Validation
- **Validation Rules:**
  ```php
  Validator::make($data, [
      'email' => 'required|email',
      'age' => 'required|integer|min:18',
      'website' => 'url|nullable'
  ]);
  ```
- **Custom Rules:** Extensible validation rule system
- **Error Messages:** Customizable error messages

### 3.13. Capability Management
- **Custom Capabilities:** System for defining and checking custom capabilities:
  ```php
  Capabilities::define('edit_nhk_settings', ['administrator', 'editor']);
  Capabilities::can('edit_nhk_settings'); // current user check
  ```
- **Role Management:** Helper methods for role creation and modification

### 3.14. Caching Layer

#### 3.14.1. Cache Wrapper
- **Unified Interface:** Wrapper for WordPress transients and object cache:
  ```php
  Cache::remember('expensive_query', function() {
      return expensive_database_query();
  }, 3600); // 1 hour
  ```
- **Cache Tags:** Support for cache tagging and bulk invalidation
- **Driver Support:** Support for Redis, Memcached when available

### 3.15. Internationalization
- **Text Domain Management:** Automatic text domain loading
- **Translation Helpers:** Simplified translation functions
- **JavaScript Translations:** Support for wp_localize_script patterns

### 3.16. Testing Infrastructure

#### 3.16.1. Unit Testing
- **Base Test Case:** Extended PHPUnit test case with WordPress mocks
- **Factory Classes:** Test data factories for common objects
- **Assertion Helpers:** Custom assertions for WordPress-specific checks

#### 3.16.2. Integration Testing
- **WordPress Test Suite:** Integration with WordPress test framework
- **Database Transactions:** Automatic transaction rollback after tests
- **HTTP Mocking:** Mock external API calls

#### 3.16.3. Testing Environment
- **Docker Configuration:** Docker compose setup for testing
- **CI/CD Templates:** GitHub Actions/GitLab CI configurations
- **Coverage Reports:** Code coverage reporting setup

### 3.17. Governance File
Create `Agents.md` in repository root with these rules:
```markdown
# Development Governance Rules

1. **No Unnecessary Refactoring:** No refactoring that is not absolutely necessary to implement a required feature or fix a bug.

2. **Data Stability:** Do not change existing data structures, models, CPT slugs, or taxonomy slugs unless it is a primary, explicitly requested feature of a project. Data stability is paramount.

3. **Version Bumping:** Every code change, no matter how small, must include a version bump following semantic versioning (MAJOR.MINOR.PATCH).

4. **Backward Compatibility:** Breaking changes require a major version bump and must be documented in UPGRADE.md.

5. **Testing Required:** New features must include corresponding tests. Bug fixes must include regression tests.

6. **Documentation Updates:** Any public API change must be reflected in documentation before merging.
```

---

## 4. Non-Functional Requirements

### 4.1. Performance
- **Autoload Optimization:** Optimize Composer autoloader for production
- **Lazy Loading:** Components should load only when needed
- **Database Queries:** No more than 5 additional queries per page load
- **Memory Usage:** Framework overhead should not exceed 2MB

### 4.2. Security
- **OWASP Compliance:** Follow OWASP WordPress Security Guidelines
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

---

## 5. Technical Stack

- **Language:** PHP 8.0+
- **Environment:** WordPress 6.0+
- **Dependency Management:** Composer
- **Required Libraries:**
  - `YahnisElsts/plugin-update-checker` - Automated updates
  - `woocommerce/action-scheduler` - Background processing
  - `symfony/dependency-injection` - Service container (optional, or custom implementation)
- **Development Tools:**
  - PHPUnit 9+ - Unit testing
  - Cypress/Playwright - E2E testing
  - PHPCS with WordPress standards
  - PHPStan for static analysis
  - Webpack 5 for asset building
- **Optional Libraries:**
  - `monolog/monolog` - Advanced logging
  - `vlucas/phpdotenv` - Environment configuration
  - `illuminate/validation` - Advanced validation

---

## 6. Implementation Phases

### Phase 1: Core Foundation (Week 1-2)
- Service container implementation
- Autoloading setup
- Basic plugin structure
- Version management system
- Logging system

### Phase 2: Content Management (Week 3-4)
- CPT Manager and Abstract classes
- Taxonomy Manager
- Meta box integration
- Admin columns

### Phase 3: Admin & Settings (Week 5-6)
- Menu generation system
- Settings API wrapper
- Admin notices manager
- Health check system

### Phase 4: Advanced Features (Week 7-8)
- REST API scaffolding
- Database migration system
- Background job queue
- Asset management

### Phase 5: Security & Performance (Week 9-10)
- Security helpers implementation
- Caching layer
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

### 7.2. Quality Criteria
- ✅ 80% or higher test coverage on framework code
- ✅ All code passes PHPCS with WordPress standards
- ✅ PHPStan analysis passes at level 6 or higher
- ✅ No critical or high security vulnerabilities in dependency scan

### 7.3. Documentation Criteria
- ✅ All public methods have complete PHPDoc comments
- ✅ README.md includes quick start guide
- ✅ Example plugin demonstrates all major features
- ✅ Migration guide for existing plugins is complete
- ✅ API documentation is auto-generated and accessible
- ✅ `Agents.md` governance file exists with specified rules
- ✅ `CHANGELOG.md` is present and follows Keep a Changelog format

### 7.4. Performance Criteria
- ✅ Framework adds no more than 2MB memory overhead
- ✅ Page load time increase is less than 100ms
- ✅ No more than 5 additional database queries per page

---

## 8. Example Usage

### Creating a New Plugin
```bash
composer create-project nhk/framework my-plugin
cd my-plugin
npm install
npm run build
```

### Registering a CPT
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
        ];
    }
    
    protected function get_args(): array {
        return [
            'public' => true,
            'has_archive' => true,
            'supports' => ['title', 'editor', 'thumbnail'],
        ];
    }
}
```

### Adding a REST Endpoint
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

---

## 10. Future Enhancements (Post-MVP)

- Gutenberg block scaffolding system
- WooCommerce integration helpers
- Multisite specific features
- GraphQL endpoint support
- Plugin marketplace integration
- Visual settings page builder
- Automated code generation CLI tool
- Plugin analytics and telemetry system
- A/B testing framework
- Feature flag system

---

## Instructions for LLM Implementation

When implementing this framework, follow these priorities:

1. **Start with the core architecture** - Get the plugin base class, service container, and autoloading working first
2. **Implement version management early** - This needs to be in place before other features
3. **Focus on developer experience** - Make sure each component is easy to use with minimal configuration
4. **Write tests as you go** - Don't leave testing until the end
5. **Document everything** - Every public method needs PHPDoc comments
6. **Follow WordPress conventions** - Even when using modern PHP, respect WordPress coding patterns where they make sense
7. **Make it extensible** - Developers should be able to override or extend any component
8. **Keep performance in mind** - Lazy load components, use efficient queries, implement caching
9. **Security first** - Every input must be sanitized, every output must be escaped
10. **Version bump everything** - Remember that every single code change requires a version bump

The framework should be production-ready, well-tested, and a joy to use for WordPress developers. Make it something developers will choose over starting from scratch every time.
