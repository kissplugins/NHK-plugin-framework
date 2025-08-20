# NHK Event Manager

A fully functional WordPress event management plugin built on the NHK Framework. While serving as a comprehensive reference implementation and proof of concept for the framework's capabilities, this plugin is designed to be production-ready and suitable for real-world use.

## 🎯 Dual Purpose

### 🚀 **Production-Ready Plugin**
- Complete event management system for WordPress sites
- Professional-grade features suitable for businesses, organizations, and communities
- Robust architecture with proper error handling, security, and performance optimization
- Extensible design allowing for custom modifications and integrations

### 📚 **Framework Showcase & Learning Resource**
- Comprehensive demonstration of NHK Framework capabilities
- Reference implementation showcasing modern WordPress development practices
- Educational resource for developers learning advanced plugin architecture
- Proof of concept for enterprise-grade WordPress development patterns

## ✨ Key Features

### Event Management
- **Custom Post Type**: Full-featured event creation and management
- **Rich Metadata**: Date/time, venue, capacity, pricing, organizer information
- **Taxonomies**: Categorization by event type and venue
- **Media Support**: Featured images and gallery support

### Admin Experience
- **Intuitive Interface**: Clean, user-friendly admin panels
- **Settings Management**: Comprehensive configuration options
- **Bulk Operations**: Efficient management of multiple events
- **Health Monitoring**: Built-in system health checks and performance metrics

### Frontend Display
- **Flexible Shortcodes**: Multiple display options (list, grid, table, calendar)
- **Template System**: Theme-compatible with override support
- **Responsive Design**: Mobile-friendly layouts
- **Filtering & Search**: Advanced event discovery features

### Developer Features
- **REST API**: Complete CRUD operations with proper authentication
- **Background Jobs**: Automated email reminders and maintenance tasks
- **Caching System**: Multi-layer performance optimization
- **Extensibility**: Hook system for custom modifications

## 🛠️ Installation & Setup

### Requirements
- WordPress 6.0 or higher
- PHP 8.0 or higher
- NHK Framework (included)

### Quick Start
1. **Download** the plugin from the releases page
2. **Upload** to your WordPress `/wp-content/plugins/` directory
3. **Activate** the plugin through the WordPress admin
4. **Configure** settings at `Events > Settings`
5. **Create** your first event!

### Manual Installation
```bash
# Clone the repository
git clone https://github.com/nhkode/nhk-event-manager.git

# Move to WordPress plugins directory
mv nhk-event-manager /path/to/wordpress/wp-content/plugins/

# Activate via WordPress admin or WP-CLI
wp plugin activate nhk-event-manager
```

## 🎯 Quick Usage Guide

### Creating Events
1. Navigate to `Events > Add New` in your WordPress admin
2. Fill in event details (title, description, date, venue, etc.)
3. Set categories and venues as needed
4. Publish your event

### Displaying Events
Use shortcodes to display events on any page or post:

```php
// Basic event list
[nhk_events]

// Upcoming events only
[nhk_events limit="5" show_past="false"]

// Events by category
[nhk_events category="workshop,conference"]

// Grid layout with images
[nhk_events layout="grid" show_image="true"]

// With filtering interface
[nhk_events show_filters="true" pagination="true"]
```

### REST API Access
Access events programmatically via the REST API:

```javascript
// Get upcoming events
GET /wp-json/nhk-events/v1/events/upcoming

// Create new event (authenticated)
POST /wp-json/nhk-events/v1/events
{
  "title": "WordPress Meetup",
  "start_date": "2024-03-15",
  "venue": "Tech Hub"
}
```

## 🏗️ Architecture & Framework Integration

### Clean Architecture
```
├── framework/              # NHK Framework (Abstract classes & utilities)
├── src/                   # Plugin implementation
│   ├── Core/             # Plugin initialization & coordination
│   ├── PostTypes/        # Custom Post Type definitions
│   ├── Taxonomy/         # Custom Taxonomy definitions
│   ├── Admin/            # Admin interface components
│   ├── API/              # REST API endpoints
│   ├── Frontend/         # Public-facing components
│   ├── Services/         # Business logic layer
│   ├── Jobs/             # Background processing
│   └── HealthChecks/     # System monitoring
├── templates/            # Template files
└── assets/              # CSS, JS, and other assets
```

### Framework Benefits Demonstrated
- **Dependency Injection**: Automatic service resolution and management
- **Abstract Base Classes**: Reusable patterns for CPTs, taxonomies, and more
- **Service Layer**: Clean separation of business logic
- **Background Processing**: Automated tasks with monitoring
- **Health Monitoring**: Built-in system diagnostics

## ⚙️ Configuration Options

### General Settings
- **Default Event Duration**: Set standard event length
- **Currency Symbol**: Configure pricing display
- **Date/Time Formats**: Customize display formats
- **Capacity Defaults**: Set standard event capacity

### Display Settings
- **Events Per Page**: Control archive pagination
- **Show Past Events**: Include/exclude past events
- **Default Sort Order**: Configure event ordering
- **Layout Options**: Choose default display styles

### Notifications
- **Email Reminders**: Automated attendee notifications
- **Admin Alerts**: System health and critical issues
- **Reminder Timing**: Configure when reminders are sent

### Advanced Options
- **Debug Mode**: Enable detailed logging
- **Cache Duration**: Performance optimization settings
- **API Access**: REST API configuration
- **Health Checks**: System monitoring intervals

## 🔧 Customization & Extension

### Theme Integration
The plugin respects your theme's styling and provides template override capabilities:

```php
// Override templates in your theme
/wp-content/themes/your-theme/nhk-events/
├── event-list.php          # Event list display
├── single-event.php        # Single event template
└── archive-event.php       # Event archive template
```

### Custom Hooks & Filters
Extend functionality with WordPress hooks:

```php
// Modify event query
add_filter('nhk_event_query_args', function($args) {
    // Customize query parameters
    return $args;
});

// Add custom event fields
add_action('nhk_event_meta_fields', function($event_id) {
    // Add your custom fields
});

// Customize email templates
add_filter('nhk_event_reminder_template', function($template, $event) {
    // Modify email content
    return $template;
}, 10, 2);
```

### REST API Extensions
Build custom integrations using the REST API:

```php
// Register custom endpoint
add_action('rest_api_init', function() {
    register_rest_route('nhk-events/v1', '/custom-endpoint', [
        'methods' => 'GET',
        'callback' => 'your_custom_function',
        'permission_callback' => '__return_true'
    ]);
});
```

## 🧪 Testing & Quality Assurance

### Built-in Health Checks
Monitor your event system with comprehensive health monitoring:
- **System Health**: Database connectivity, file permissions, WordPress requirements
- **Performance Metrics**: Memory usage, query performance, cache efficiency
- **Data Integrity**: Event validation, orphaned data detection
- **Automated Alerts**: Email notifications for critical issues

### Manual Testing
```bash
# Run health checks via WP-CLI (if available)
wp nhk-event health-check

# Test REST API endpoints
curl -X GET "https://yoursite.com/wp-json/nhk-events/v1/events"

# Validate shortcode output
# Add [nhk_events] to any page and verify display
```

## 🚀 Performance & Scalability

### Caching Strategy
- **Multi-layer Caching**: Transients, object cache, and page cache integration
- **Smart Invalidation**: Automatic cache clearing on content updates
- **Background Cleanup**: Scheduled maintenance of cache entries

### Database Optimization
- **Efficient Queries**: Optimized database queries with proper indexing
- **Lazy Loading**: Load data only when needed
- **Bulk Operations**: Efficient handling of multiple events

### Asset Management
- **Conditional Loading**: Assets loaded only when needed
- **Minification**: Optimized CSS and JavaScript
- **CDN Ready**: Compatible with content delivery networks

## 🔒 Security Features

### Data Protection
- **Input Sanitization**: All user input properly sanitized
- **Output Escaping**: XSS prevention on all output
- **Nonce Verification**: CSRF protection on all forms
- **Capability Checks**: Proper permission validation

### API Security
- **Authentication**: WordPress REST API authentication
- **Rate Limiting**: Built-in request throttling
- **Input Validation**: Comprehensive request validation
- **Error Handling**: Secure error messages

## 📊 Analytics & Insights

### Event Metrics
- **Attendance Tracking**: Monitor event popularity
- **Performance Analytics**: System performance metrics
- **Usage Statistics**: Plugin usage insights
- **Health History**: Historical system health data

### Reporting
- **Admin Dashboard**: Quick overview of event statistics
- **Health Reports**: Detailed system health information
- **Performance Reports**: Cache efficiency and query performance
- **Export Capabilities**: Data export for external analysis

## 🌐 Internationalization

### Multi-language Support
- **Translation Ready**: All strings properly internationalized
- **RTL Support**: Right-to-left language compatibility
- **Date Localization**: Locale-aware date formatting
- **Currency Formatting**: Regional currency display

### Available Languages
- English (default)
- Translation files ready for community contributions

## 🤝 Community & Support

### Getting Help
- **Documentation**: Comprehensive inline documentation
- **Code Examples**: Extensive usage examples throughout
- **Health Checks**: Built-in diagnostic tools
- **GitHub Issues**: Community support and bug reports

### Contributing
We welcome contributions that enhance both the plugin's functionality and its value as a learning resource:

1. **Bug Reports**: Help us identify and fix issues
2. **Feature Requests**: Suggest improvements for real-world usage
3. **Code Examples**: Add more demonstration patterns
4. **Documentation**: Improve explanations and examples
5. **Translations**: Help make the plugin accessible globally

### Development Setup
```bash
# Clone the repository
git clone https://github.com/nhkode/nhk-event-manager.git
cd nhk-event-manager

# Install development dependencies (if any)
composer install --dev

# Set up local development environment
# (Follow WordPress development best practices)
```

## 📈 Roadmap & Future Development

See [PROJECT.md](PROJECT.md) for detailed development roadmap and planned features.

## 📄 License & Legal

**License**: GPL v2 or later - Same as WordPress

This plugin is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 2 of the License, or any later version.

## 🔗 Resources & Links

- **Plugin Repository**: [GitHub](https://github.com/nhkode/nhk-event-manager)
- **NHK Framework**: [Documentation](https://github.com/nhkode/nhk-framework)
- **WordPress Development**: [Official Guide](https://developer.wordpress.org/plugins/)
- **Modern PHP**: [Best Practices](https://phptherightway.com/)
- **WordPress Coding Standards**: [Guidelines](https://developer.wordpress.org/coding-standards/)

---

**Ready to get started?** Install the plugin and create your first event in minutes, or dive into the code to explore modern WordPress development patterns!
