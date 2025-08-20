# PRD: Event Manager Plugin - NHK Framework Proof of Concept

**Version**: 1.0.0  
**Framework Version Required**: NHK Framework 1.0.0  
**Purpose**: Reference implementation and proof of concept for NHK Framework

---

## 1. Overview

### 1.1. Purpose
The Event Manager plugin serves as the canonical example of how to build a WordPress plugin using the NHK Framework. It demonstrates every major framework feature while solving a real-world need: managing and displaying events on a WordPress website.

### 1.2. Goals
1. **Demonstrate Framework Usage** - Show correct implementation of all NHK Framework components
2. **Provide Working Boilerplate** - Offer a fully functional starting point for developers
3. **Establish Best Practices** - Set patterns for framework-based plugin development
4. **Validate Framework Design** - Prove the framework works for real-world scenarios

### 1.3. Target Audience
- **Primary**: Developers learning the NHK Framework
- **Secondary**: End users who need event management functionality

---

## 2. Plugin Architecture

### 2.1. Directory Structure
```
nhk-event-manager/
├── src/
│   ├── Core/
│   │   ├── Plugin.php                 # Main plugin class extending NHK\Framework\Plugin
│   │   ├── Activator.php              # Activation hooks
│   │   ├── Deactivator.php            # Deactivation hooks
│   │   └── Uninstaller.php            # Uninstall cleanup
│   ├── CPT/
│   │   └── EventCPT.php               # Event post type definition
│   ├── Taxonomy/
│   │   ├── EventCategoryTaxonomy.php  # Event categories
│   │   └── EventVenueTaxonomy.php     # Event venues
│   ├── Admin/
│   │   ├── SettingsPage.php           # Plugin settings page
│   │   ├── EventMetaBoxes.php         # Custom meta boxes
│   │   └── EventColumns.php           # Admin column customization
│   ├── API/
│   │   ├── EventsEndpoint.php         # REST API for events
│   │   └── CalendarEndpoint.php       # iCal feed endpoint
│   ├── Frontend/
│   │   ├── EventListShortcode.php     # [nhk_events] shortcode
│   │   ├── EventCalendarShortcode.php # [nhk_event_calendar] shortcode
│   │   └── EventSingleShortcode.php   # [nhk_event_single id="x"] shortcode
│   ├── Services/
│   │   ├── EventQueryService.php      # Complex event queries
│   │   ├── EventCacheService.php      # Caching layer
│   │   └── EventReminderService.php   # Email reminders (background job)
│   ├── Database/
│   │   └── Migrations/
│   │       └── CreateEventMetaTable.php # Custom table for attendees
│   └── Helpers/
│       ├── EventDateHelper.php        # Date formatting and calculations
│       └── EventCapacityHelper.php    # Registration capacity logic
├── assets/
│   ├── css/
│   │   ├── admin-events.css           # Admin styles
│   │   ├── event-calendar.css         # Calendar widget styles
│   │   └── event-list.css             # Frontend event list styles
│   ├── js/
│   │   ├── admin-events.js            # Admin JavaScript
│   │   ├── event-calendar.js          # Calendar interactions
│   │   └── event-filter.js            # Frontend filtering
│   └── images/
│       └── calendar-icon.svg          # Default event icon
├── templates/                         # Override-able templates
│   ├── single-event.php
│   ├── archive-events.php
│   └── partials/
│       ├── event-card.php
│       └── event-calendar-day.php
├── languages/
│   └── nhk-event-manager.pot          # Translation template
├── tests/
│   ├── unit/
│   │   ├── EventCPTTest.php
│   │   └── EventQueryServiceTest.php
│   └── integration/
│       ├── EventAPITest.php
│       └── EventShortcodeTest.php
├── composer.json                       # Composer dependencies
├── package.json                        # NPM dependencies
├── webpack.config.js                   # Asset building
├── phpcs.xml                          # Coding standards
├── phpstan.neon                       # Static analysis
├── .gitignore
├── CHANGELOG.md                       # Version history
├── README.md                          # Documentation
├── Agents.md                          # Governance rules
└── nhk-event-manager.php              # Main plugin file
```

### 2.2. Main Plugin File Structure
```php
<?php
/**
 * Plugin Name: NHK Event Manager
 * Plugin URI: https://github.com/nhk/event-manager
 * Description: Event management system built on NHK Framework - Reference Implementation
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: NHK Framework Team
 * License: GPL v2 or later
 * Text Domain: nhk-event-manager
 * Domain Path: /languages
 */

namespace NHK\EventManager;

// Prevent direct access
defined('ABSPATH') || exit;

// Define plugin constants
define('NHK_EVENT_MANAGER_VERSION', '1.0.0');
define('NHK_EVENT_MANAGER_FILE', __FILE__);
define('NHK_EVENT_MANAGER_PATH', plugin_dir_path(__FILE__));
define('NHK_EVENT_MANAGER_URL', plugin_dir_url(__FILE__));

// Autoload dependencies
require_once NHK_EVENT_MANAGER_PATH . 'vendor/autoload.php';

// Initialize plugin
add_action('plugins_loaded', function() {
    $plugin = new Core\Plugin();
    $plugin->init();
});
```

---

## 3. Feature Implementation

### 3.1. Custom Post Type: Events

#### 3.1.1. Event CPT Definition
```php
namespace NHK\EventManager\CPT;

use NHK\Framework\Abstracts\Abstract_CPT;

class EventCPT extends Abstract_CPT {
    protected function get_slug(): string {
        return 'nhk_event';
    }
    
    protected function get_labels(): array {
        return [
            'name'               => __('Events', 'nhk-event-manager'),
            'singular_name'      => __('Event', 'nhk-event-manager'),
            'add_new'           => __('Add New Event', 'nhk-event-manager'),
            'add_new_item'      => __('Add New Event', 'nhk-event-manager'),
            'edit_item'         => __('Edit Event', 'nhk-event-manager'),
            'new_item'          => __('New Event', 'nhk-event-manager'),
            'view_item'         => __('View Event', 'nhk-event-manager'),
            'search_items'      => __('Search Events', 'nhk-event-manager'),
            'not_found'         => __('No events found', 'nhk-event-manager'),
            'not_found_in_trash' => __('No events found in trash', 'nhk-event-manager'),
        ];
    }
    
    protected function get_args(): array {
        return [
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'events'],
            'capability_type'   => 'post',
            'has_archive'       => true,
            'hierarchical'      => false,
            'menu_position'     => 25,
            'menu_icon'         => 'dashicons-calendar-alt',
            'supports'          => ['title', 'editor', 'thumbnail', 'excerpt'],
            'show_in_rest'      => true,
            'rest_base'         => 'events',
        ];
    }
    
    protected function register_meta_boxes(): void {
        $this->container->get(EventMetaBoxes::class)->register();
    }
    
    protected function register_columns(): array {
        return [
            'event_date' => __('Event Date', 'nhk-event-manager'),
            'venue'      => __('Venue', 'nhk-event-manager'),
            'capacity'   => __('Capacity', 'nhk-event-manager'),
            'status'     => __('Status', 'nhk-event-manager'),
        ];
    }
}
```

#### 3.1.2. Event Meta Fields
- `event_start_date` - DateTime picker (required)
- `event_end_date` - DateTime picker (required)
- `event_venue` - Text field for location
- `event_address` - Textarea for full address
- `event_capacity` - Number field for max attendees
- `event_registration_url` - URL field for external registration
- `event_cost` - Text field for pricing info
- `event_organizer` - Text field for organizer name
- `event_organizer_email` - Email field
- `event_is_recurring` - Checkbox for recurring events
- `event_recurrence_pattern` - Select (daily, weekly, monthly)

### 3.2. Taxonomies

#### 3.2.1. Event Categories
```php
namespace NHK\EventManager\Taxonomy;

use NHK\Framework\Abstracts\Abstract_Taxonomy;

class EventCategoryTaxonomy extends Abstract_Taxonomy {
    protected function get_slug(): string {
        return 'nhk_event_category';
    }
    
    protected function get_post_types(): array {
        return ['nhk_event'];
    }
    
    protected function get_args(): array {
        return [
            'hierarchical'      => true,
            'labels'           => $this->get_labels(),
            'show_ui'          => true,
            'show_admin_column' => true,
            'query_var'        => true,
            'rewrite'          => ['slug' => 'event-category'],
            'show_in_rest'     => true,
        ];
    }
}
```

#### 3.2.2. Event Venues (Non-hierarchical)
- Similar structure but `hierarchical => false`
- Used for venue tagging and filtering

### 3.3. Admin Interface

#### 3.3.1. Settings Page Structure
```php
namespace NHK\EventManager\Admin;

use NHK\Framework\Abstracts\Abstract_Settings_Page;

class SettingsPage extends Abstract_Settings_Page {
    protected function get_page_title(): string {
        return __('Event Manager Settings', 'nhk-event-manager');
    }
    
    protected function get_menu_slug(): string {
        return 'nhk-event-settings';
    }
    
    protected function get_tabs(): array {
        return [
            'general' => __('General', 'nhk-event-manager'),
            'display' => __('Display', 'nhk-event-manager'),
            'notifications' => __('Notifications', 'nhk-event-manager'),
            'advanced' => __('Advanced', 'nhk-event-manager'),
            'health' => __('Health Check', 'nhk-event-manager'),
        ];
    }
}
```

#### 3.3.2. Settings Fields
**General Tab:**
- Default event duration (hours)
- Default event capacity
- Time zone setting
- Date format preference
- Currency symbol

**Display Tab:**
- Events per page
- Show past events (yes/no)
- Default sort order (date, title, venue)
- Enable calendar view
- Theme selection (grid, list, calendar)

**Notifications Tab:**
- Enable reminder emails
- Reminder timing (1 day, 3 days, 1 week)
- Admin notification email
- Email template selection

**Advanced Tab:**
- Enable debugging (frontend/backend/file)
- Cache duration
- Purge cache button
- Export events (CSV)
- Import events (CSV)

**Health Check Tab:**
- Run system checks button
- Display check results
- Check history

### 3.4. REST API Implementation

#### 3.4.1. Events Endpoint
```php
namespace NHK\EventManager\API;

use NHK\Framework\Abstracts\Abstract_REST_Endpoint;

class EventsEndpoint extends Abstract_REST_Endpoint {
    protected function get_namespace(): string {
        return 'nhk-events/v1';
    }
    
    protected function get_routes(): array {
        return [
            '/events' => [
                'methods' => 'GET',
                'callback' => [$this, 'get_events'],
                'permission_callback' => '__return_true',
                'args' => [
                    'per_page' => [
                        'type' => 'integer',
                        'default' => 10,
                    ],
                    'category' => [
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'from_date' => [
                        'type' => 'string',
                        'format' => 'date',
                    ],
                    'to_date' => [
                        'type' => 'string',
                        'format' => 'date',
                    ],
                ],
            ],
            '/events/(?P<id>\d+)' => [
                'methods' => 'GET',
                'callback' => [$this, 'get_event'],
                'permission_callback' => '__return_true',
            ],
        ];
    }
}
```

#### 3.4.2. Response Format
```json
{
    "id": 123,
    "title": "Annual Conference 2025",
    "description": "Event description...",
    "start_date": "2025-09-15T09:00:00",
    "end_date": "2025-09-15T17:00:00",
    "venue": "Convention Center",
    "address": "123 Main St, City, State",
    "capacity": 500,
    "available_spots": 125,
    "categories": ["conference", "networking"],
    "registration_url": "https://example.com/register",
    "featured_image": "https://example.com/image.jpg"
}
```

### 3.5. Frontend Shortcodes

#### 3.5.1. Event List Shortcode
```php
// Usage: [nhk_events category="conference" limit="10" show_past="no"]
class EventListShortcode extends Abstract_Shortcode {
    protected function get_tag(): string {
        return 'nhk_events';
    }
    
    protected function get_defaults(): array {
        return [
            'category' => '',
            'limit' => 10,
            'show_past' => 'no',
            'layout' => 'grid', // grid, list
        ];
    }
    
    protected function render(array $atts): string {
        $events = $this->query_events($atts);
        return $this->load_template('event-list', ['events' => $events]);
    }
}
```

#### 3.5.2. Event Calendar Shortcode
```php
// Usage: [nhk_event_calendar month="2025-09" category="workshop"]
class EventCalendarShortcode extends Abstract_Shortcode {
    protected function get_tag(): string {
        return 'nhk_event_calendar';
    }
    
    // Renders interactive calendar with events
}
```

### 3.6. Background Jobs

#### 3.6.1. Event Reminder Job
```php
namespace NHK\EventManager\Services;

use NHK\Framework\Abstracts\Abstract_Job;

class EventReminderJob extends Abstract_Job {
    public function handle(array $args): void {
        $event_id = $args['event_id'];
        $days_before = $args['days_before'];
        
        // Get event details
        $event = get_post($event_id);
        
        // Get registered attendees (from custom table)
        $attendees = $this->get_attendees($event_id);
        
        // Send reminder emails
        foreach ($attendees as $attendee) {
            wp_mail(
                $attendee->email,
                sprintf(__('Reminder: %s is coming up', 'nhk-event-manager'), $event->post_title),
                $this->get_reminder_template($event, $days_before)
            );
        }
        
        // Log completion
        $this->logger->info('Event reminders sent', [
            'event_id' => $event_id,
            'recipient_count' => count($attendees)
        ]);
    }
}
```

### 3.7. Health Checks

#### 3.7.1. Event System Health Checks
```php
// Register health checks in Plugin::init()
HealthCheck::register('event_cpt', [
    'label' => __('Event Post Type', 'nhk-event-manager'),
    'callback' => function() {
        $exists = post_type_exists('nhk_event');
        return [
            'status' => $exists ? 'healthy' : 'critical',
            'message' => $exists 
                ? __('Event post type is registered', 'nhk-event-manager')
                : __('Event post type is not registered', 'nhk-event-manager')
        ];
    },
    'severity' => 'critical'
]);

HealthCheck::register('upcoming_events', [
    'label' => __('Upcoming Events', 'nhk-event-manager'),
    'callback' => function() {
        $upcoming = get_posts([
            'post_type' => 'nhk_event',
            'meta_query' => [[
                'key' => 'event_start_date',
                'value' => current_time('Y-m-d'),
                'compare' => '>=',
                'type' => 'DATE'
            ]],
            'posts_per_page' => 1
        ]);
        
        return [
            'status' => !empty($upcoming) ? 'healthy' : 'warning',
            'message' => !empty($upcoming)
                ? __('Upcoming events found', 'nhk-event-manager')
                : __('No upcoming events', 'nhk-event-manager')
        ];
    },
    'severity' => 'warning'
]);

HealthCheck::register('event_cache', [
    'label' => __('Event Cache', 'nhk-event-manager'),
    'callback' => function() {
        $cache_works = Cache::set('health_check_test', 'value', 60);
        $retrieved = Cache::get('health_check_test');
        
        return [
            'status' => ($retrieved === 'value') ? 'healthy' : 'warning',
            'message' => ($retrieved === 'value')
                ? __('Cache is working', 'nhk-event-manager')
                : __('Cache may not be working properly', 'nhk-event-manager')
        ];
    },
    'severity' => 'warning'
]);
```

### 3.8. Database Migration

#### 3.8.1. Attendees Table Migration
```php
namespace NHK\EventManager\Database\Migrations;

use NHK\Framework\Abstracts\Abstract_Migration;

class CreateEventAttendeesTable extends Abstract_Migration {
    public function up(): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'nhk_event_attendees';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            email varchar(100) NOT NULL,
            name varchar(255) NOT NULL,
            registration_date datetime DEFAULT CURRENT_TIMESTAMP,
            status varchar(20) DEFAULT 'confirmed',
            PRIMARY KEY (id),
            KEY event_id (event_id),
            KEY user_id (user_id),
            KEY email (email)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function down(): void {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}nhk_event_attendees");
    }
    
    public function version(): string {
        return '1.0.0';
    }
}
```

### 3.9. Caching Implementation

#### 3.9.1. Event Query Caching
```php
namespace NHK\EventManager\Services;

use NHK\Framework\Services\Cache;

class EventCacheService {
    public function get_upcoming_events(int $limit = 10): array {
        return Cache::remember('upcoming_events_' . $limit, function() use ($limit) {
            return get_posts([
                'post_type' => 'nhk_event',
                'posts_per_page' => $limit,
                'meta_key' => 'event_start_date',
                'orderby' => 'meta_value',
                'order' => 'ASC',
                'meta_query' => [[
                    'key' => 'event_start_date',
                    'value' => current_time('Y-m-d'),
                    'compare' => '>=',
                    'type' => 'DATE'
                ]]
            ]);
        }, HOUR_IN_SECONDS);
    }
    
    public function flush_event_cache(): void {
        Cache::flush_group('events');
    }
}
```

---

## 4. Asset Management

### 4.1. JavaScript Files

#### 4.1.1. Admin JavaScript (admin-events.js)
- Date picker initialization
- Recurring event UI logic
- Venue autocomplete
- Form validation

#### 4.1.2. Frontend Calendar (event-calendar.js)
- Calendar rendering
- Event tooltips
- Month navigation
- Category filtering

#### 4.1.3. Event Filter (event-filter.js)
- AJAX-powered filtering
- Category selection
- Date range picker
- Live search

### 4.2. CSS Structure

#### 4.2.1. Admin Styles (admin-events.css)
```css
/* Meta box styling */
.nhk-event-meta-box { }
.nhk-event-date-field { }
.nhk-event-capacity-indicator { }
```

#### 4.2.2. Frontend Styles (event-list.css)
```css
/* Event grid layout */
.nhk-events-grid { }
.nhk-event-card { }
.nhk-event-date-badge { }
```

---

## 5. Testing Implementation

### 5.1. Unit Tests

#### 5.1.1. CPT Registration Test
```php
class EventCPTTest extends WP_UnitTestCase {
    public function test_event_cpt_registered() {
        $this->assertTrue(post_type_exists('nhk_event'));
    }
    
    public function test_event_cpt_supports() {
        $supports = get_all_post_type_supports('nhk_event');
        $this->assertArrayHasKey('title', $supports);
        $this->assertArrayHasKey('editor', $supports);
        $this->assertArrayHasKey('thumbnail', $supports);
    }
}
```

### 5.2. Integration Tests

#### 5.2.1. REST API Test
```php
class EventAPITest extends WP_Test_REST_TestCase {
    public function test_get_events_endpoint() {
        $request = new WP_REST_Request('GET', '/nhk-events/v1/events');
        $response = rest_do_request($request);
        
        $this->assertEquals(200, $response->get_status());
        $this->assertIsArray($response->get_data());
    }
}
```

---

## 6. Version Management

### 6.1. Initial Version (1.0.0)
- Core event management functionality
- Basic shortcodes
- Admin interface
- REST API

### 6.2. Version Bumping Examples
```php
// Every change requires version bump
// Before: Version 1.0.0
// Fix typo in admin notice
// After: Version 1.0.1

// Before: Version 1.0.1  
// Add new filter hook
// After: Version 1.0.2

// Before: Version 1.0.2
// Add calendar view feature
// After: Version 1.1.0

// Before: Version 1.1.0
// Change CPT slug (breaking change)
// After: Version 2.0.0
```

---

## 7. Documentation Requirements

### 7.1. README.md Structure
1. **Installation** - Composer and manual installation
2. **Quick Start** - Basic usage examples
3. **Shortcodes** - All available shortcodes with parameters
4. **Hooks** - Available actions and filters
5. **REST API** - Endpoint documentation
6. **Templates** - How to override templates
7. **FAQ** - Common questions
8. **Changelog** - Link to CHANGELOG.md

### 7.2. Inline Documentation
- Every public method must have PHPDoc
- Complex logic must have inline comments
- WordPress functions used must be noted

### 7.3. Developer Guide
- How to extend the plugin
- Available filters and actions
- Template hierarchy
- Database schema

---

## 8. Demonstration Features Checklist

### 8.1. Framework Features Demonstrated
- ✅ **Service Container** - All services registered and injected
- ✅ **CPT Management** - Event post type with full implementation
- ✅ **Taxonomy Management** - Categories and venues
- ✅ **Settings API Wrapper** - Multi-tab settings page
- ✅ **REST API** - Full CRUD endpoints
- ✅ **Shortcodes** - Multiple shortcodes with different layouts
- ✅ **Background Jobs** - Reminder emails via Action Scheduler
- ✅ **Database Migrations** - Custom attendees table
- ✅ **Caching Layer** - Query caching with cache groups
- ✅ **Asset Management** - Conditional loading, versioning
- ✅ **Health Checks** - Multiple system checks
- ✅ **Logging** - Debug logging throughout
- ✅ **Security** - Nonces, sanitization, escaping
- ✅ **Internationalization** - Fully translatable
- ✅ **Version Management** - Semantic versioning
- ✅ **Testing** - Unit and integration tests
- ✅ **Admin Notices** - Dismissible notices system
- ✅ **Debugging System** - Frontend and backend debugging

### 8.2. WordPress Core Functions Used
- ✅ `register_post_type()` for CPT
- ✅ `register_taxonomy()` for taxonomies
- ✅ `add_meta_box()` for meta boxes
- ✅ `wp_enqueue_script()` for assets
- ✅ `register_rest_route()` for API
- ✅ `add_shortcode()` for shortcodes
- ✅ `wp_schedule_event()` considered before Action Scheduler
- ✅ `dbDelta()` for database tables
- ✅ `wp_mail()` for notifications
- ✅ `get_option()` / `update_option()` for settings
- ✅ `set_transient()` / `get_transient()` for caching
- ✅ `wp_nonce_field()` / `wp_verify_nonce()` for security
- ✅ `sanitize_text_field()` and other sanitization
- ✅ `esc_html()` and other escaping functions
- ✅ `__()` / `_e()` for translations

---

## 9. Development Workflow

### 9.1. Initial Setup
```bash
# Clone the framework
composer create-project nhk/framework nhk-event-manager

# Install dependencies
composer install
npm install

# Build assets
npm run build

# Activate plugin in WordPress
wp plugin activate nhk-event-manager
```

### 9.2. Development Commands
```bash
# Watch for changes
npm run watch

# Run tests
composer test

# Check coding standards
composer phpcs

# Static analysis
composer phpstan

# Build for production
npm run production
```

### 9.3. Release Process
1. Update version in main plugin file
2. Update version in `composer.json`
3. Update `CHANGELOG.md`
4. Run all tests
5. Build production assets
6. Tag release in git
7. GitHub release triggers update notification

---

## 10. Performance Considerations

### 10.1. Query Optimization
- Events queries cached for 1 hour
- Use meta_query sparingly
- Pagination on all event lists
- Lazy load event details

### 10.2. Asset Optimization
- Minified CSS/JS in production
- Assets only loaded on relevant pages
- Images lazy loaded
- SVG icons instead of icon fonts

### 10.3. Database Optimization
- Indexed columns for attendees table
- Efficient meta queries
- Batch operations for bulk actions

---

## 11. Security Implementation

### 11.1. Data Validation
- All inputs sanitized on save
- Type checking for all parameters
- Capability checks for all actions
- Nonce verification on all forms

### 11.2. Output Escaping
- All dynamic content escaped
- Template variables escaped
- Attributes properly escaped
- JavaScript data localized safely

### 11.3. SQL Security
- Prepared statements for all queries
- Table prefix used consistently
- No direct SQL input from users

---

## 12. Success Metrics

### 12.1. Code Quality Metrics
- **Test Coverage**: Minimum 80%
- **PHPCS**: Zero errors
- **PHPStan**: Level 6 passing
- **Performance**: Page load < 2 seconds

### 12.2. Framework Validation
- All framework features successfully demonstrated
- No custom implementations where WordPress functions exist
- Clean separation of concerns
- Proper dependency injection throughout

### 12.3. Developer Experience
- New developer can understand structure in < 30 minutes
- Can add new feature following patterns in < 2 hours
- Clear documentation for all extension points

---

## 13. Future Enhancements (Post-MVP)

1. **Ticketing System** - Sell tickets with payment gateway
2. **Check-in System** - QR codes for event check-in
3. **Recurring Events** - Complex recurrence patterns
4. **Virtual Events** - Zoom/Teams integration
5. **Event Speakers** - Speaker profiles and management
6. **Event Sponsors** - Sponsor levels and display
7. **Email Templates** - Customizable email designs
8. **Event Duplication** - Clone events quickly
9. **Bulk Actions** - Bulk edit/delete events
10. **Analytics Dashboard** - Event performance metrics

---

## Instructions for LLM Implementation

When implementing this Event Manager plugin using the NHK Framework:

1. **Start with the framework** - Ensure NHK Framework is properly installed and loaded
2. **Follow PSR-4 structure** - Maintain the namespace and directory structure exactly
3. **Use framework abstracts** - Extend all framework abstract classes properly
4. **WordPress functions first** - Always use WordPress core functions before creating custom solutions
5. **Register everything** - Use the service container to register all services
6. **Version from the start** - Begin with version 1.0.0 and bump with every change
7. **Test as you build** - Write tests alongside implementation
8. **Document everything** - PHPDoc blocks for all public methods
9. **Security by default** - Sanitize inputs, escape outputs, verify nonces
10. **Cache strategically** - Cache expensive queries but respect cache invalidation

### Implementation Order:
1. **Phase 1**: Core structure and CPT registration
2. **Phase 2**: Taxonomies and meta fields
3. **Phase 3**: Admin interface and settings
4. **Phase 4**: REST API endpoints
5. **Phase 5**: Frontend shortcodes
6. **Phase 6**: Background jobs and caching
7. **Phase 7**: Health checks and debugging
8. **Phase 8**: Testing and documentation

### Key Patterns to Follow:

#### Service Registration Pattern
```php
// In Plugin.php
protected function registerServices(): void {
    // CPT and Taxonomies
    $this->container->singleton(EventCPT::class);
    $this->container->singleton(EventCategoryTaxonomy::class);
    
    // Admin
    $this->container->singleton(SettingsPage::class);
    $this->container->singleton(EventMetaBoxes::class);
    
    // Frontend
    $this->container->singleton(EventListShortcode::class);
    
    // Services
    $this->container->singleton(EventQueryService::class);
    $this->container->singleton(EventCacheService::class);
    
    // API
    $this->container->singleton(EventsEndpoint::class);
}

protected function bootServices(): void {
    // Boot each service
    $this->container->get(EventCPT::class)->init();
    $this->container->get(EventCategoryTaxonomy::class)->init();
    // ... etc
}
```

#### Meta Box Pattern
```php
class EventMetaBoxes {
    use NHK\Framework\Traits\Hooker;
    
    public function init(): void {
        $this->add_action('add_meta_boxes', 'register_meta_boxes');
        $this->add_action('save_post_nhk_event', 'save_meta_boxes');
    }
    
    public function register_meta_boxes(): void {
        add_meta_box(
            'nhk_event_details',
            __('Event Details', 'nhk-event-manager'),
            [$this, 'render_details_meta_box'],
            'nhk_event',
            'normal',
            'high'
        );
    }
    
    public function save_meta_boxes(int $post_id): void {
        // Verify nonce
        if (!Security::verifyNonce($_POST['event_nonce'] ?? '', 'save_event')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Sanitize and save
        $start_date = Security::sanitize($_POST['event_start_date'] ?? '', 'text');
        update_post_meta($post_id, 'event_start_date', $start_date);
    }
}
```

#### Shortcode Pattern
```php
class EventListShortcode extends Abstract_Shortcode {
    private EventQueryService $query_service;
    private EventCacheService $cache_service;
    
    public function __construct(
        EventQueryService $query_service,
        EventCacheService $cache_service
    ) {
        $this->query_service = $query_service;
        $this->cache_service = $cache_service;
    }
    
    protected function render(array $atts): string {
        // Use cache
        $cache_key = 'event_list_' . md5(serialize($atts));
        
        return $this->cache_service->remember($cache_key, function() use ($atts) {
            $events = $this->query_service->get_events($atts);
            
            ob_start();
            $this->load_template('event-list', [
                'events' => $events,
                'atts' => $atts
            ]);
            return ob_get_clean();
        }, 30 * MINUTE_IN_SECONDS);
    }
}
```

#### Health Check Pattern
```php
// In Plugin.php init()
$this->registerHealthChecks();

private function registerHealthChecks(): void {
    // Critical checks
    HealthCheck::register('event_system_critical', [
        'label' => __('Critical Event Systems', 'nhk-event-manager'),
        'callback' => [$this, 'checkCriticalSystems'],
        'severity' => 'critical'
    ]);
    
    // Warning level checks
    HealthCheck::register('event_content', [
        'label' => __('Event Content', 'nhk-event-manager'),
        'callback' => [$this, 'checkEventContent'],
        'severity' => 'warning'
    ]);
}

public function checkCriticalSystems(): array {
    $checks = [];
    
    // Check CPT
    $checks['cpt'] = post_type_exists('nhk_event');
    
    // Check database table
    global $wpdb;
    $table = $wpdb->prefix . 'nhk_event_attendees';
    $checks['db_table'] = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
    
    // Check REST API
    $routes = rest_get_server()->get_routes();
    $checks['rest_api'] = isset($routes['/nhk-events/v1/events']);
    
    $all_pass = !in_array(false, $checks, true);
    
    return [
        'status' => $all_pass ? 'healthy' : 'critical',
        'message' => $all_pass 
            ? __('All critical systems operational', 'nhk-event-manager')
            : __('Critical system failure detected', 'nhk-event-manager'),
        'details' => $checks
    ];
}
```

### Common Pitfalls to Avoid:

1. **Don't bypass the framework** - Use framework abstracts, don't directly call WordPress functions when framework provides a wrapper
2. **Don't forget version bumping** - Every single change needs a version increment
3. **Don't skip sanitization** - Every input must be sanitized, every output escaped
4. **Don't ignore caching** - Cache expensive operations but ensure proper invalidation
5. **Don't hardcode strings** - Everything must be translatable
6. **Don't forget hooks** - Provide action/filter hooks for extensibility
7. **Don't skip tests** - Write tests for critical functionality

### Validation Checklist:

Before considering the implementation complete, verify:

- [ ] All framework abstract classes are properly extended
- [ ] Service container is used for all dependency injection
- [ ] All WordPress core functions are used (not reimplemented)
- [ ] Version management is working (auto-updates from GitHub)
- [ ] Health checks pass successfully
- [ ] Debugging can be enabled/disabled from settings
- [ ] All data is properly sanitized and escaped
- [ ] Caching is implemented for expensive queries
- [ ] All strings are translatable
- [ ] PHPDoc comments on all public methods
- [ ] Tests written and passing
- [ ] PHPCS and PHPStan pass without errors
- [ ] Assets are properly enqueued and versioned
- [ ] Database migrations run on activation
- [ ] Uninstall cleanup is implemented

---

## 14. Code Generation Instructions

### For LLMs generating the actual code:

When you receive this PRD along with the NHK Framework PRD, generate the Event Manager plugin following this structure:

1. **Generate the main plugin file first** (`nhk-event-manager.php`)
2. **Create the Plugin class** that extends `NHK\Framework\Core\Plugin`
3. **Implement each component** in the order specified in the Implementation Order
4. **Include inline comments** explaining framework usage
5. **Add TODO comments** where framework features are demonstrated
6. **Generate working examples** not just stubs
7. **Include error handling** and logging throughout
8. **Follow WordPress Coding Standards** exactly

### Expected Output Structure:

```
nhk-event-manager/
├── nhk-event-manager.php          # Full implementation
├── composer.json                   # With NHK Framework dependency
├── src/
│   ├── Core/
│   │   └── Plugin.php             # Full implementation with service registration
│   ├── CPT/
│   │   └── EventCPT.php          # Complete with meta boxes and columns
│   ├── Taxonomy/
│   │   ├── EventCategoryTaxonomy.php
│   │   └── EventVenueTaxonomy.php
│   ├── Admin/
│   │   ├── SettingsPage.php      # Multi-tab settings
│   │   └── EventMetaBoxes.php    # All meta fields
│   ├── API/
│   │   └── EventsEndpoint.php    # Full REST implementation
│   ├── Frontend/
│   │   └── EventListShortcode.php # Complete shortcode
│   └── Services/
│       ├── EventQueryService.php  # Query logic
│       └── EventCacheService.php  # Caching implementation
├── assets/
│   ├── css/
│   │   └── event-list.css        # Basic styles
│   └── js/
│       └── event-filter.js       # Basic filtering
├── templates/
│   └── event-list.php            # Basic template
├── README.md                      # Complete documentation
├── CHANGELOG.md                   # Starting at 1.0.0
└── Agents.md                      # Governance rules

```

### Remember:
This is a **reference implementation** - it should be production-ready code that developers can learn from and build upon. Every line should demonstrate best practices for using the NHK Framework.