# WordPress Plugin Framework: Complete Implementation Guide

## Project Overview

This project is a modern WordPress starter plugin framework designed for building highly interactive and robust plugins. It uses a decoupled architecture, separating backend and frontend concerns cleanly with proper state management on both layers.

### Core Technologies & Roles

| Tool | Layer | Role & Responsibility |
| :--- | :--- | :--- |
| **PHP State Machine** | **Backend Logic** | Enforces business rules and manages the true state of data objects (e.g., post status, order status) on the server. |
| **XState** | **Frontend Logic** | Manages complex UI state and orchestrates user flows on the client-side (e.g., multi-step forms, loading/error states). |
| **Alpine.js** | **Frontend View** | Provides lightweight, declarative reactivity to bind the HTML view to the frontend state managed by XState. |
| **Tailwind CSS** | **Styling** | A utility-first CSS framework for rapidly building the user interface. |
| **Bun** | **Dev Environment** | A fast, all-in-one toolkit for installing dependencies, running scripts, and bundling all frontend assets (JS & CSS). |
| **Composer** | **PHP Dependencies** | Manages all PHP packages (like the state machine library) and handles PSR-4 autoloading for a clean backend structure. |
| **TypeScript** | **Type Safety** | Optional but recommended for better XState integration and frontend type safety. |

---

## Implementation Plan

### 1. Build Process (`package.json` & Asset Management)

This process connects the frontend development assets (source code) to the production-ready files that WordPress will load.

#### A. Enhanced `package.json` Configuration

```json
{
  "name": "wp-plugin-framework",
  "version": "1.0.0",
  "scripts": {
    "dev": "NODE_ENV=development concurrently \"bun run watch:css\" \"bun run watch:js\"",
    "build": "NODE_ENV=production bun run build:css && bun run build:js",
    "watch:css": "bunx tailwindcss -i ./src/css/main.css -o ./dist/main.css --watch",
    "watch:js": "bun build ./src/js/index.js --outdir=./dist --watch --sourcemap=inline",
    "build:css": "bunx tailwindcss -i ./src/css/main.css -o ./dist/main.css --minify",
    "build:js": "bun build ./src/js/index.js --outdir=./dist --format=iife --minify --external:wp-* --sourcemap=external",
    "test": "jest",
    "test:php": "vendor/bin/phpunit"
  },
  "devDependencies": {
    "tailwindcss": "^3.4.0",
    "concurrently": "^8.2.0",
    "jest": "^29.7.0",
    "@types/alpinejs": "^3.13.0",
    "typescript": "^5.3.0"
  },
  "dependencies": {
    "alpinejs": "^3.14.0",
    "xstate": "^5.11.0"
  }
}
```

**Key Improvements:**
- Added `concurrently` for parallel watch tasks
- Implemented environment-specific builds
- Added `--format=iife` to prevent global scope pollution
- Included `--external:wp-*` to exclude WordPress dependencies
- Added sourcemap generation for debugging
- Included testing scripts

#### B. Enhanced PHP Asset Enqueuing Class

**File Location:** `src/Core/Assets.php`

```php
<?php

namespace WpPluginFramework\Core;

class Assets {
    
    private string $namespace = 'my-plugin/v1';
    
    /**
     * Register hooks.
     */
    public function register(): void {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    /**
     * Enqueue scripts and styles for the frontend.
     */
    public function enqueue_frontend_assets(): void {
        // Only load on specific pages/conditions
        if (!$this->should_load_frontend_assets()) {
            return;
        }
        
        $plugin_url = plugin_dir_url(dirname(__FILE__, 2));
        $plugin_path = plugin_dir_path(dirname(__FILE__, 2));
        
        // Determine if we're in development or production mode
        $is_dev = defined('WP_DEBUG') && WP_DEBUG;
        $suffix = $is_dev ? '' : '.min';

        // Enqueue the main stylesheet
        wp_enqueue_style(
            'wp-plugin-framework-style',
            $plugin_url . 'dist/main.css',
            [],
            filemtime($plugin_path . 'dist/main.css')
        );

        // Enqueue the main JavaScript file
        wp_enqueue_script(
            'wp-plugin-framework-script',
            $plugin_url . 'dist/index.js',
            ['wp-api-fetch'], // Add WordPress API dependency
            filemtime($plugin_path . 'dist/index.js'),
            true
        );
        
        // Localize script with necessary data
        wp_localize_script(
            'wp-plugin-framework-script',
            'wpPluginFramework',
            [
                'apiUrl' => rest_url($this->namespace),
                'nonce' => wp_create_nonce('wp_rest'),
                'currentUser' => wp_get_current_user()->ID,
                'isDebug' => $is_dev,
                'i18n' => [
                    'errorGeneric' => __('An error occurred. Please try again.', 'wp-plugin-framework'),
                    'loading' => __('Loading...', 'wp-plugin-framework'),
                ]
            ]
        );
    }
    
    /**
     * Enqueue scripts and styles for the admin area.
     */
    public function enqueue_admin_assets(string $hook): void {
        // Only load on plugin-specific admin pages
        if (!$this->should_load_admin_assets($hook)) {
            return;
        }
        
        // Similar pattern as frontend assets
        // Add admin-specific scripts and styles here
    }
    
    /**
     * Determine if frontend assets should be loaded.
     */
    private function should_load_frontend_assets(): bool {
        // Add your conditional logic here
        // Examples:
        return is_singular() 
            || is_page_template('custom-template.php')
            || has_shortcode(get_post()->post_content ?? '', 'plugin_shortcode');
    }
    
    /**
     * Determine if admin assets should be loaded.
     */
    private function should_load_admin_assets(string $hook): bool {
        $plugin_pages = [
            'toplevel_page_wp-plugin-framework',
            'wp-plugin-framework_page_settings'
        ];
        
        return in_array($hook, $plugin_pages, true);
    }
}
```

### 2. API Endpoint Implementation with Security

#### A. Enhanced API Manager Class

**File Location:** `src/Api/ApiManager.php`

```php
<?php

namespace WpPluginFramework\Api;

use WpPluginFramework\Api\Endpoints\UpdatePostStatusEndpoint;
use WpPluginFramework\Core\Logger;

class ApiManager {

    protected string $namespace = 'my-plugin/v1';
    protected array $endpoints = [];
    
    /**
     * Register hooks.
     */
    public function register(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_filter('rest_authentication_errors', [$this, 'check_authentication']);
    }

    /**
     * Register all custom REST API routes.
     */
    public function register_routes(): void {
        // Initialize endpoints
        $this->endpoints = [
            new UpdatePostStatusEndpoint($this->namespace),
            // Add more endpoints here
        ];
        
        // Register each endpoint
        foreach ($this->endpoints as $endpoint) {
            try {
                $endpoint->register();
            } catch (\Exception $e) {
                Logger::error('Failed to register endpoint', [
                    'endpoint' => get_class($endpoint),
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    /**
     * Additional authentication check for REST API requests.
     */
    public function check_authentication($result) {
        // Skip if another authentication method has already failed
        if (!empty($result)) {
            return $result;
        }
        
        // Check if this is our namespace
        if (!$this->is_our_request()) {
            return $result;
        }
        
        // Verify user is logged in for our endpoints
        if (!is_user_logged_in()) {
            return new \WP_Error(
                'rest_not_logged_in',
                __('You must be logged in to access this endpoint.', 'wp-plugin-framework'),
                ['status' => 401]
            );
        }
        
        return $result;
    }
    
    /**
     * Check if current request is for our namespace.
     */
    private function is_our_request(): bool {
        $rest_route = $GLOBALS['wp']->query_vars['rest_route'] ?? '';
        return strpos($rest_route, '/' . $this->namespace) === 0;
    }
}
```

#### B. Enhanced Endpoint Class with State Machine Integration

**File Location:** `src/Api/Endpoints/UpdatePostStatusEndpoint.php`

```php
<?php

namespace WpPluginFramework\Api\Endpoints;

use WpPluginFramework\Core\Logger;
use WpPluginFramework\StateMachine\PostStateMachine;

class UpdatePostStatusEndpoint {

    protected string $namespace;
    protected string $route = '/posts/(?P<id>\d+)/status';
    
    // Rate limiting
    private const RATE_LIMIT_WINDOW = 60; // seconds
    private const RATE_LIMIT_MAX_REQUESTS = 10;

    public function __construct(string $namespace) {
        $this->namespace = $namespace;
    }

    /**
     * Registers the route with WordPress.
     */
    public function register(): void {
        register_rest_route($this->namespace, $this->route, [
            'methods'  => \WP_REST_Server::EDITABLE,
            'callback' => [$this, 'handle_request'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => $this->get_endpoint_args(),
        ]);
    }
    
    /**
     * Define and validate endpoint arguments.
     */
    private function get_endpoint_args(): array {
        return [
            'id' => [
                'required' => true,
                'type' => 'integer',
                'validate_callback' => function($value) {
                    return is_numeric($value) && $value > 0;
                },
                'sanitize_callback' => 'absint',
            ],
            'new_status' => [
                'required' => true,
                'type' => 'string',
                'validate_callback' => function($value) {
                    $allowed_statuses = ['draft', 'pending', 'publish', 'private', 'trash'];
                    return in_array($value, $allowed_statuses, true);
                },
                'sanitize_callback' => 'sanitize_key',
            ],
            'reason' => [
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
            ],
        ];
    }

    /**
     * Permission check for the endpoint.
     */
    public function check_permissions(\WP_REST_Request $request): bool {
        // Verify nonce
        $nonce = $request->get_header('X-WP-Nonce');
        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            return false;
        }
        
        // Check rate limiting
        if (!$this->check_rate_limit($request)) {
            return false;
        }
        
        // Check user capabilities
        $post_id = (int) $request->get_param('id');
        return current_user_can('edit_post', $post_id);
    }
    
    /**
     * Check rate limiting for the current user.
     */
    private function check_rate_limit(\WP_REST_Request $request): bool {
        $user_id = get_current_user_id();
        $transient_key = 'api_rate_limit_' . $user_id . '_' . $request->get_route();
        
        $requests = get_transient($transient_key) ?: 0;
        
        if ($requests >= self::RATE_LIMIT_MAX_REQUESTS) {
            Logger::warning('Rate limit exceeded', [
                'user_id' => $user_id,
                'endpoint' => $request->get_route()
            ]);
            return false;
        }
        
        set_transient($transient_key, $requests + 1, self::RATE_LIMIT_WINDOW);
        return true;
    }

    /**
     * The main handler for the API request.
     */
    public function handle_request(\WP_REST_Request $request): \WP_REST_Response {
        $post_id = (int) $request->get_param('id');
        $new_status = $request->get_param('new_status');
        $reason = $request->get_param('reason') ?: '';
        
        try {
            // Get the post
            $post = get_post($post_id);
            if (!$post) {
                return new \WP_REST_Response(
                    ['message' => __('Post not found.', 'wp-plugin-framework')],
                    404
                );
            }
            
            // Initialize state machine
            $state_machine = new PostStateMachine($post_id);
            $current_state = get_post_meta($post_id, '_state_machine_state', true) ?: 'draft';
            $state_machine->setState($current_state);
            
            // Check if transition is valid
            if (!$state_machine->can($new_status)) {
                return new \WP_REST_Response([
                    'message' => __('Invalid state transition.', 'wp-plugin-framework'),
                    'current_state' => $current_state,
                    'requested_state' => $new_status,
                    'allowed_transitions' => $state_machine->getAllowedTransitions()
                ], 400);
            }
            
            // Apply the transition
            $state_machine->apply($new_status);
            
            // Update post meta
            update_post_meta($post_id, '_state_machine_state', $new_status);
            update_post_meta($post_id, '_state_machine_history', [
                'from' => $current_state,
                'to' => $new_status,
                'reason' => $reason,
                'user_id' => get_current_user_id(),
                'timestamp' => current_time('mysql')
            ]);
            
            // Update actual post status if needed
            if (in_array($new_status, ['draft', 'pending', 'publish', 'private', 'trash'])) {
                wp_update_post([
                    'ID' => $post_id,
                    'post_status' => $new_status
                ]);
            }
            
            // Trigger WordPress hooks
            do_action('wp_plugin_framework_state_changed', $post_id, $current_state, $new_status, $reason);
            
            // Log the successful transition
            Logger::info('Post status updated', [
                'post_id' => $post_id,
                'from' => $current_state,
                'to' => $new_status,
                'user_id' => get_current_user_id()
            ]);
            
            return new \WP_REST_Response([
                'success' => true,
                'message' => __('Status updated successfully.', 'wp-plugin-framework'),
                'data' => [
                    'post_id' => $post_id,
                    'previous_state' => $current_state,
                    'new_state' => $new_status,
                    'timestamp' => current_time('c')
                ]
            ], 200);
            
        } catch (\Exception $e) {
            Logger::error('Failed to update post status', [
                'post_id' => $post_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return new \WP_REST_Response([
                'message' => __('An error occurred while updating the status.', 'wp-plugin-framework'),
                'error' => defined('WP_DEBUG') && WP_DEBUG ? $e->getMessage() : null
            ], 500);
        }
    }
}
```

### 3. State Machine Implementation

**File Location:** `src/StateMachine/PostStateMachine.php`

```php
<?php

namespace WpPluginFramework\StateMachine;

class PostStateMachine {
    
    private int $post_id;
    private string $current_state;
    private array $transitions;
    
    public function __construct(int $post_id) {
        $this->post_id = $post_id;
        $this->initializeTransitions();
    }
    
    /**
     * Initialize allowed state transitions.
     */
    private function initializeTransitions(): void {
        $this->transitions = [
            'draft' => ['pending', 'publish', 'trash'],
            'pending' => ['draft', 'publish', 'trash'],
            'publish' => ['draft', 'pending', 'private', 'trash'],
            'private' => ['draft', 'publish', 'trash'],
            'trash' => ['draft', 'pending', 'publish', 'private']
        ];
        
        // Allow filtering of transitions
        $this->transitions = apply_filters(
            'wp_plugin_framework_state_transitions',
            $this->transitions,
            $this->post_id
        );
    }
    
    /**
     * Set the current state.
     */
    public function setState(string $state): void {
        $this->current_state = $state;
    }
    
    /**
     * Check if a transition is allowed.
     */
    public function can(string $target_state): bool {
        if (!isset($this->transitions[$this->current_state])) {
            return false;
        }
        
        return in_array($target_state, $this->transitions[$this->current_state], true);
    }
    
    /**
     * Apply a state transition.
     */
    public function apply(string $target_state): void {
        if (!$this->can($target_state)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Cannot transition from %s to %s',
                    $this->current_state,
                    $target_state
                )
            );
        }
        
        // Trigger pre-transition hook
        do_action(
            'wp_plugin_framework_pre_state_transition',
            $this->post_id,
            $this->current_state,
            $target_state
        );
        
        $this->current_state = $target_state;
        
        // Trigger post-transition hook
        do_action(
            'wp_plugin_framework_post_state_transition',
            $this->post_id,
            $this->current_state,
            $target_state
        );
    }
    
    /**
     * Get allowed transitions from current state.
     */
    public function getAllowedTransitions(): array {
        return $this->transitions[$this->current_state] ?? [];
    }
}
```

### 4. Frontend Integration

**File Location:** `src/js/api/client.js`

```javascript
/**
 * API Client for WordPress REST API integration
 */
class ApiClient {
    constructor() {
        this.baseUrl = window.wpPluginFramework?.apiUrl || '/wp-json/my-plugin/v1';
        this.nonce = window.wpPluginFramework?.nonce || '';
    }
    
    /**
     * Make an authenticated API request
     */
    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}${endpoint}`;
        
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': this.nonce
            }
        };
        
        const mergedOptions = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...(options.headers || {})
            }
        };
        
        try {
            const response = await fetch(url, mergedOptions);
            
            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'API request failed');
            }
            
            return await response.json();
        } catch (error) {
            console.error('API request error:', error);
            throw error;
        }
    }
    
    /**
     * Update post status
     */
    async updatePostStatus(postId, newStatus, reason = '') {
        return this.request(`/posts/${postId}/status`, {
            method: 'PATCH',
            body: JSON.stringify({
                new_status: newStatus,
                reason: reason
            })
        });
    }
}

export default ApiClient;
```

**File Location:** `src/js/machines/postStatusMachine.js`

```javascript
import { createMachine, assign } from 'xstate';
import ApiClient from '../api/client';

const apiClient = new ApiClient();

/**
 * XState machine for managing post status UI state
 */
export const postStatusMachine = createMachine({
    id: 'postStatus',
    initial: 'idle',
    context: {
        postId: null,
        currentStatus: null,
        error: null,
        loading: false
    },
    states: {
        idle: {
            on: {
                CHANGE_STATUS: {
                    target: 'updating',
                    actions: assign({
                        loading: true,
                        error: null
                    })
                }
            }
        },
        updating: {
            invoke: {
                src: async (context, event) => {
                    return await apiClient.updatePostStatus(
                        context.postId,
                        event.newStatus,
                        event.reason
                    );
                },
                onDone: {
                    target: 'success',
                    actions: assign({
                        currentStatus: (context, event) => event.data.data.new_state,
                        loading: false
                    })
                },
                onError: {
                    target: 'error',
                    actions: assign({
                        error: (context, event) => event.data.message,
                        loading: false
                    })
                }
            }
        },
        success: {
            after: {
                2000: 'idle'
            }
        },
        error: {
            on: {
                RETRY: 'updating',
                DISMISS: 'idle'
            }
        }
    }
});
```

**File Location:** `src/js/index.js`

```javascript
import Alpine from 'alpinejs';
import { interpret } from 'xstate';
import { postStatusMachine } from './machines/postStatusMachine';

// Initialize Alpine.js
window.Alpine = Alpine;

// Create Alpine component with XState integration
Alpine.data('postStatusManager', (postId, initialStatus) => ({
    postId,
    currentStatus: initialStatus,
    loading: false,
    error: null,
    machine: null,
    
    init() {
        // Initialize XState machine
        this.machine = interpret(
            postStatusMachine.withContext({
                postId: this.postId,
                currentStatus: this.currentStatus
            })
        );
        
        // Subscribe to state changes
        this.machine.subscribe((state) => {
            this.currentStatus = state.context.currentStatus;
            this.loading = state.context.loading;
            this.error = state.context.error;
        });
        
        this.machine.start();
    },
    
    changeStatus(newStatus, reason = '') {
        this.machine.send({
            type: 'CHANGE_STATUS',
            newStatus,
            reason
        });
    },
    
    retry() {
        this.machine.send('RETRY');
    },
    
    dismissError() {
        this.machine.send('DISMISS');
    }
}));

// Start Alpine
Alpine.start();
```

### 5. Logging Service

**File Location:** `src/Core/Logger.php`

```php
<?php

namespace WpPluginFramework\Core;

class Logger {
    
    private const LOG_PREFIX = '[WP Plugin Framework]';
    
    /**
     * Log an error message.
     */
    public static function error(string $message, array $context = []): void {
        self::log('error', $message, $context);
    }
    
    /**
     * Log a warning message.
     */
    public static function warning(string $message, array $context = []): void {
        self::log('warning', $message, $context);
    }
    
    /**
     * Log an info message.
     */
    public static function info(string $message, array $context = []): void {
        self::log('info', $message, $context);
    }
    
    /**
     * Log a debug message.
     */
    public static function debug(string $message, array $context = []): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            self::log('debug', $message, $context);
        }
    }
    
    /**
     * Core logging method.
     */
    private static function log(string $level, string $message, array $context = []): void {
        if (!self::should_log($level)) {
            return;
        }
        
        $formatted_message = sprintf(
            '%s [%s] %s | Context: %s',
            self::LOG_PREFIX,
            strtoupper($level),
            $message,
            json_encode($context)
        );
        
        // Use WordPress debug log
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log($formatted_message);
        }
        
        // Store in custom log table if needed
        self::store_in_database($level, $message, $context);
        
        // Trigger action for external logging services
        do_action('wp_plugin_framework_log', $level, $message, $context);
    }
    
    /**
     * Determine if message should be logged based on level.
     */
    private static function should_log(string $level): bool {
        $min_level = defined('WP_PLUGIN_FRAMEWORK_LOG_LEVEL') 
            ? WP_PLUGIN_FRAMEWORK_LOG_LEVEL 
            : 'warning';
        
        $levels = [
            'debug' => 0,
            'info' => 1,
            'warning' => 2,
            'error' => 3
        ];
        
        return $levels[$level] >= $levels[$min_level];
    }
    
    /**
     * Store log entry in database for persistent logging.
     */
    private static function store_in_database(string $level, string $message, array $context): void {
        global $wpdb;
        
        // Only store warnings and errors in database
        if (!in_array($level, ['warning', 'error'], true)) {
            return;
        }
        
        $table_name = $wpdb->prefix . 'plugin_framework_logs';
        
        $wpdb->insert(
            $table_name,
            [
                'level' => $level,
                'message' => $message,
                'context' => json_encode($context),
                'user_id' => get_current_user_id(),
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%d', '%s']
        );
    }
}
```

### 6. Plugin Bootstrap File

**File Location:** `plugin-main.php`

```php
<?php
/**
 * Plugin Name: WP Plugin Framework
 * Plugin URI: https://example.com/
 * Description: A modern WordPress plugin framework with state management
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com/
 * License: GPL v2 or later
 * Text Domain: wp-plugin-framework
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_PLUGIN_FRAMEWORK_VERSION', '1.0.0');
define('WP_PLUGIN_FRAMEWORK_PATH', plugin_dir_path(__FILE__));
define('WP_PLUGIN_FRAMEWORK_URL', plugin_dir_url(__FILE__));
define('WP_PLUGIN_FRAMEWORK_BASENAME', plugin_basename(__FILE__));

// Compatibility check
if (version_compare(PHP_VERSION, '8.0', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('WP Plugin Framework requires PHP 8.0 or higher.', 'wp-plugin-framework');
        echo '</p></div>';
    });
    return;
}

// Autoload Composer dependencies
if (file_exists(WP_PLUGIN_FRAMEWORK_PATH . 'vendor/autoload.php')) {
    require_once WP_PLUGIN_FRAMEWORK_PATH . 'vendor/autoload.php';
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    // Load text domain
    load_plugin_textdomain(
        'wp-plugin-framework',
        false,
        dirname(WP_PLUGIN_FRAMEWORK_BASENAME) . '/languages'
    );
    
    // Initialize core components
    $assets = new \WpPluginFramework\Core\Assets();
    $assets->register();
    
    $api = new \WpPluginFramework\Api\ApiManager();
    $api->register();
    
    // Initialize other components as needed
    do_action('wp_plugin_framework_init');
});

// Activation hook
register_activation_hook(__FILE__, function() {
    // Create database tables
    \WpPluginFramework\Core\Installer::activate();
    
    // Set default options
    add_option('wp_plugin_framework_version', WP_PLUGIN_FRAMEWORK_VERSION);
    
    // Flush rewrite rules
    flush_rewrite_rules();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Clean up temporary data
    \WpPluginFramework\Core\Installer::deactivate();
    
    // Flush rewrite rules
    flush_rewrite_rules();
});
```

### 7. Testing Configuration

**File Location:** `phpunit.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit
    bootstrap="tests/bootstrap.php"
    colors="true"
    verbose="true">
    <testsuites>
        <testsuite name="Plugin Test Suite">
            <directory>tests/</directory>
        </testsuite>
    </testsuites>
    <coverage processUncoveredFiles="true">
        <include>
            <directory suffix=".php">src</directory>
        </include>
    </coverage>
</phpunit>
```

**File Location:** `jest.config.js`

```javascript
module.exports = {
    testEnvironment: 'jsdom',
    roots: ['<rootDir>/tests/js'],
    testMatch: ['**/__tests__/**/*.js', '**/?(*.)+(spec|test).js'],
    transform: {
        '^.+\\.js: 'babel-jest',
    },
    moduleNameMapper: {
        '^@/(.*): '<rootDir>/src/js/$1',
    },
    setupFilesAfterEnv: ['<rootDir>/tests/js/setup.js'],
    collectCoverageFrom: [
        'src/js/**/*.js',
        '!src/js/index.js',
    ],
};
```

### 8. Project Structure

```
wp-plugin-framework/
├── dist/                      # Compiled assets (git-ignored)
│   ├── index.js
│   ├── index.js.map
│   └── main.css
├── languages/                 # Translation files
├── src/
│   ├── Api/
│   │   ├── ApiManager.php
│   │   └── Endpoints/
│   │       └── UpdatePostStatusEndpoint.php
│   ├── Core/
│   │   ├── Assets.php
│   │   ├── Installer.php
│   │   └── Logger.php
│   ├── StateMachine/
│   │   └── PostStateMachine.php
│   ├── css/
│   │   └── main.css
│   └── js/
│       ├── api/
│       │   └── client.js
│       ├── machines/
│       │   └── postStatusMachine.js
│       └── index.js
├── tests/
│   ├── php/
│   │   └── test-post-status.php
│   └── js/
│       └── postStatus.test.js
├── vendor/                    # Composer dependencies (git-ignored)
├── .gitignore
├── composer.json
├── jest.config.js
├── package.json
├── phpunit.xml
├── plugin-main.php
├── README.md
└── tailwind.config.js
```

### 9. Additional Configuration Files

**File Location:** `composer.json`

```json
{
    "name": "yourname/wp-plugin-framework",
    "description": "A modern WordPress plugin framework",
    "type": "wordpress-plugin",
    "license": "GPL-2.0-or-later",
    "minimum-stability": "stable",
    "require": {
        "php": ">=8.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.5",
        "wp-coding-standards/wpcs": "^3.0",
        "dealerdirect/phpcodesniffer-composer-installer": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "WpPluginFramework\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "WpPluginFramework\\Tests\\": "tests/php/"
        }
    },
    "scripts": {
        "test": "phpunit",
        "phpcs": "phpcs --standard=WordPress src/",
        "phpcbf": "phpcbf --standard=WordPress src/"
    }
}
```

**File Location:** `tailwind.config.js`

```javascript
module.exports = {
    content: [
        './src/**/*.{html,js,php}',
        './templates/**/*.php'
    ],
    theme: {
        extend: {
            colors: {
                'plugin-primary': '#4F46E5',
                'plugin-secondary': '#7C3AED',
            }
        }
    },
    plugins: [
        require('@tailwindcss/forms'),
        require('@tailwindcss/typography')
    ]
}
```

**File Location:** `.gitignore`

```
# Dependencies
/vendor/
/node_modules/

# Build artifacts
/dist/

# Development files
.DS_Store
Thumbs.db
*.log
*.sql
*.sqlite

# IDE files
.idea/
.vscode/
*.sublime-project
*.sublime-workspace

# Environment files
.env
.env.local

# Testing
/coverage/
.phpunit.result.cache

# WordPress
wp-config.php
```

### 10. Example HTML Template with Alpine.js Integration

**File Location:** `templates/post-status-manager.php`

```php
<?php
/**
 * Template for post status manager UI
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

$post_id = get_the_ID();
$current_status = get_post_meta($post_id, '_state_machine_state', true) ?: 'draft';
?>

<div 
    x-data="postStatusManager(<?php echo esc_attr($post_id); ?>, '<?php echo esc_attr($current_status); ?>')"
    class="bg-white rounded-lg shadow-md p-6 max-w-2xl mx-auto"
>
    <!-- Status Display -->
    <div class="mb-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">
            <?php esc_html_e('Post Status Manager', 'wp-plugin-framework'); ?>
        </h3>
        
        <div class="flex items-center space-x-2">
            <span class="text-sm text-gray-600">
                <?php esc_html_e('Current Status:', 'wp-plugin-framework'); ?>
            </span>
            <span 
                class="px-3 py-1 text-sm font-medium rounded-full"
                :class="{
                    'bg-gray-100 text-gray-800': currentStatus === 'draft',
                    'bg-yellow-100 text-yellow-800': currentStatus === 'pending',
                    'bg-green-100 text-green-800': currentStatus === 'publish',
                    'bg-purple-100 text-purple-800': currentStatus === 'private',
                    'bg-red-100 text-red-800': currentStatus === 'trash'
                }"
                x-text="currentStatus"
            ></span>
        </div>
    </div>

    <!-- Status Change Buttons -->
    <div class="space-y-4">
        <div class="flex flex-wrap gap-2">
            <button
                @click="changeStatus('draft')"
                :disabled="loading || currentStatus === 'draft'"
                class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
                <?php esc_html_e('Set to Draft', 'wp-plugin-framework'); ?>
            </button>
            
            <button
                @click="changeStatus('pending')"
                :disabled="loading || currentStatus === 'pending'"
                class="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
                <?php esc_html_e('Set to Pending', 'wp-plugin-framework'); ?>
            </button>
            
            <button
                @click="changeStatus('publish')"
                :disabled="loading || currentStatus === 'publish'"
                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
                <?php esc_html_e('Publish', 'wp-plugin-framework'); ?>
            </button>
            
            <button
                @click="changeStatus('private')"
                :disabled="loading || currentStatus === 'private'"
                class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
                <?php esc_html_e('Make Private', 'wp-plugin-framework'); ?>
            </button>
            
            <button
                @click="changeStatus('trash')"
                :disabled="loading || currentStatus === 'trash'"
                class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
                <?php esc_html_e('Move to Trash', 'wp-plugin-framework'); ?>
            </button>
        </div>
    </div>

    <!-- Loading State -->
    <div 
        x-show="loading" 
        x-transition
        class="mt-4 flex items-center justify-center py-4"
    >
        <svg class="animate-spin h-6 w-6 text-plugin-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span class="ml-2 text-gray-600">
            <?php esc_html_e('Updating status...', 'wp-plugin-framework'); ?>
        </span>
    </div>

    <!-- Error State -->
    <div 
        x-show="error" 
        x-transition
        class="mt-4 bg-red-50 border border-red-200 rounded-md p-4"
    >
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-red-800" x-text="error"></p>
                <div class="mt-2 flex space-x-2">
                    <button 
                        @click="retry()"
                        class="text-sm text-red-600 hover:text-red-500 underline"
                    >
                        <?php esc_html_e('Retry', 'wp-plugin-framework'); ?>
                    </button>
                    <button 
                        @click="dismissError()"
                        class="text-sm text-gray-600 hover:text-gray-500 underline"
                    >
                        <?php esc_html_e('Dismiss', 'wp-plugin-framework'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
```

### 11. Database Installer

**File Location:** `src/Core/Installer.php`

```php
<?php

namespace WpPluginFramework\Core;

class Installer {
    
    /**
     * Run installation routines.
     */
    public static function activate(): void {
        self::create_tables();
        self::set_default_options();
        self::schedule_events();
    }
    
    /**
     * Run deactivation routines.
     */
    public static function deactivate(): void {
        self::clear_scheduled_events();
    }
    
    /**
     * Create custom database tables.
     */
    private static function create_tables(): void {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'plugin_framework_logs';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            level varchar(20) NOT NULL,
            message text NOT NULL,
            context longtext,
            user_id bigint(20) unsigned DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY level (level),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Set default plugin options.
     */
    private static function set_default_options(): void {
        $defaults = [
            'wp_plugin_framework_settings' => [
                'enable_logging' => true,
                'log_level' => 'warning',
                'api_rate_limit' => 10,
                'enable_state_machine' => true
            ]
        ];
        
        foreach ($defaults as $option_name => $option_value) {
            if (get_option($option_name) === false) {
                add_option($option_name, $option_value);
            }
        }
    }
    
    /**
     * Schedule recurring events.
     */
    private static function schedule_events(): void {
        if (!wp_next_scheduled('wp_plugin_framework_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'wp_plugin_framework_cleanup_logs');
        }
    }
    
    /**
     * Clear scheduled events.
     */
    private static function clear_scheduled_events(): void {
        wp_clear_scheduled_hook('wp_plugin_framework_cleanup_logs');
    }
}
```

## Summary

This enhanced implementation guide provides a production-ready WordPress plugin framework with:

### ✅ Complete Features
- **Modern build process** with Bun, proper bundling, and environment-specific builds
- **Secure API endpoints** with nonce verification, rate limiting, and comprehensive validation
- **State machine integration** on both frontend (XState) and backend (PHP)
- **Robust error handling** and logging system
- **Frontend framework integration** with Alpine.js and XState
- **Testing infrastructure** for both PHP and JavaScript
- **Database management** for persistent data storage
- **Asset optimization** with conditional loading and cache busting

### 🔒 Security Enhancements
- CSRF protection via nonces
- Rate limiting to prevent abuse
- Input sanitization and validation
- Permission checks at multiple levels
- Error message sanitization for production

### 🚀 Performance Optimizations
- Conditional asset loading
- Minification and bundling
- Cache busting with file timestamps
- Lazy loading capabilities
- Efficient state management

### 🛠️ Developer Experience
- PSR-4 autoloading
- Modern JavaScript tooling
- Comprehensive logging
- Testing infrastructure
- Clear project structure
- TypeScript support ready

This framework is now ready for building complex, interactive WordPress plugins with confidence in security, performance, and maintainability.
