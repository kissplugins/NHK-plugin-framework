# PROJECT-CLEANROOM.md
# KISS Smart Batch Installer - Complete Cleanroom Rewrite

## Executive Summary

Build a WordPress plugin from scratch that enables batch installation of WordPress plugins directly from GitHub repositories. The plugin will integrate with the existing Plugin Quick Search (PQS) cache for optimal performance and use WordPress's native "All Plugins" page UX patterns for immediate user familiarity.

## High-Level Implementation Checklist

### Phase 1: Foundation Architecture (Week 1)
- [ ] **PSR-4 Plugin Structure**: Set up modern autoloading with proper namespace
- [ ] **Dependency Injection Container**: Central service management
- [ ] **WordPress Integration Layer**: Admin pages, hooks, and core WordPress functionality
- [ ] **State Management System**: Central arbiter for all plugin states
- [ ] **PQS Cache Integration**: Read-only integration with existing PQS localStorage cache

### Phase 2: Core Functionality (Week 2)
- [ ] **GitHub Repository Service**: Scrape and cache GitHub org repositories
- [ ] **Plugin Detection Service**: Identify WordPress plugins in repositories
- [ ] **Installation Service**: Install plugins using WordPress core upgrader
- [ ] **WordPress List Table**: Native-style plugin listing interface
- [ ] **AJAX API**: Modern REST-like endpoints for frontend interactions

### Phase 3: User Interface (Week 3)
- [ ] **Modern Frontend**: Clean JavaScript with proper state management
- [ ] **Bulk Operations**: Multi-select installation with progress tracking
- [ ] **Error Handling**: Graceful error states and user feedback
- [ ] **Settings Integration**: Simple configuration interface
- [ ] **Performance Optimization**: Lazy loading and intelligent caching

---

## Product Requirements Document

### Problem Statement

WordPress developers and agencies need an efficient way to batch install multiple plugins from their GitHub organization repositories. Current solutions require manual plugin installation one-by-one, are slow to determine installation status, and don't integrate well with existing WordPress workflows.

### Target Users

- **Primary**: WordPress developers with GitHub organization containing multiple plugins
- **Secondary**: Agencies managing client sites with standardized plugin sets
- **Tertiary**: WordPress site builders who maintain their own plugin libraries

### Core Value Proposition

- **Speed**: Batch install multiple plugins in one operation
- **Familiarity**: Uses WordPress native "All Plugins" page patterns
- **Intelligence**: Automatically detects installed plugins via PQS integration
- **Simplicity**: Single column interface eliminates confusion

---

## User Experience Design

### UI Pattern: WordPress All Plugins Page Clone

```
┌─────────────────────────────────────────────────────────────────┐
│ GitHub Repository Plugins                              (15)     │
├─────────────────────────────────────────────────────────────────┤
│ □ Bulk Actions ▼ [Apply]    [□ Activate after install]          │
├─────────────────────────────────────────────────────────────────┤
│ □  Plugin Name                        │ Description     │ State │
├─────────────────────────────────────────────────────────────────┤
│ □  My-Awesome-Plugin                  │ An awesome      │ [Install] │
│    https://github.com/org/repo        │ WordPress       │       │
│    Version 1.2.0 | PHP | Updated 2h  │ plugin for...   │       │
├─────────────────────────────────────────────────────────────────┤
│ ⊗  Another-Plugin — Active            │ Another great   │ [Deactivate] │
│    https://github.com/org/repo        │ plugin that...  │ [Settings] │
│    Version 2.1.0 | PHP | Updated 1d  │                 │       │
├─────────────────────────────────────────────────────────────────┤
│ ⊗  Third-Plugin — Inactive            │ This plugin     │ [Activate] │
│    https://github.com/org/repo        │ provides...     │ [Settings] │
│    Version 1.0.3 | PHP | Updated 3d  │                 │       │
└─────────────────────────────────────────────────────────────────┘
```

### Single Source of Truth: State Model

```php
enum PluginState: string {
    case UNKNOWN = 'unknown';              // Haven't checked yet
    case CHECKING = 'checking';            // Currently being analyzed
    case AVAILABLE = 'available';          // Is a WP plugin, can install
    case NOT_PLUGIN = 'not_plugin';        // Repository exists but not a WP plugin
    case INSTALLED_INACTIVE = 'installed_inactive';  // Installed but not active
    case INSTALLED_ACTIVE = 'installed_active';      // Installed and active
    case ERROR = 'error';                  // Error occurred during processing
}
```

### State Transitions and Actions

```
UNKNOWN → [Check] → CHECKING → {AVAILABLE|NOT_PLUGIN|ERROR}
AVAILABLE → [Install] → INSTALLED_INACTIVE → [Activate] → INSTALLED_ACTIVE
INSTALLED_INACTIVE → [Activate] → INSTALLED_ACTIVE
INSTALLED_ACTIVE → [Deactivate] → INSTALLED_INACTIVE
INSTALLED_ACTIVE → [Settings] → (external page)
ERROR → [Retry] → CHECKING
```

---

## Technical Architecture

### PSR-4 Directory Structure

```
github-batch-installer/
├── github-batch-installer.php           # WordPress plugin header
├── composer.json                        # PSR-4 autoloading
├── src/
│   ├── Plugin.php                       # Main plugin bootstrap
│   ├── Container.php                    # Dependency injection
│   ├── Core/
│   │   ├── Models/
│   │   │   ├── Repository.php           # GitHub repository model
│   │   │   ├── Plugin.php              # Plugin state model
│   │   │   └── InstallationResult.php  # Installation outcome
│   │   ├── Services/
│   │   │   ├── StateManager.php        # Central state arbiter
│   │   │   ├── GitHubService.php       # Repository fetching & caching
│   │   │   ├── PluginDetectionService.php # WordPress plugin detection
│   │   │   ├── InstallationService.php # Plugin installation
│   │   │   ├── PQSIntegration.php     # Plugin Quick Search integration
│   │   │   └── CacheService.php       # Caching layer
│   │   └── Contracts/
│   │       ├── StateManagerInterface.php
│   │       ├── CacheInterface.php
│   │       └── PluginDetectorInterface.php
│   ├── Admin/
│   │   ├── Controllers/
│   │   │   ├── PluginListController.php # Main plugin list page
│   │   │   └── SettingsController.php   # Configuration page
│   │   ├── Views/
│   │   │   └── PluginListTable.php     # WordPress WP_List_Table
│   │   └── Assets/
│   │       ├── AdminAssets.php         # Script/style registration
│   │       └── AjaxHandler.php         # AJAX endpoint router
│   └── Integration/
│       └── WordPress.php               # WordPress hooks & integration
├── assets/
│   ├── js/
│   │   └── admin.js                    # Modern ES6+ JavaScript
│   └── css/
│       └── admin.css                   # WordPress-native styling
└── templates/
    └── admin/
        └── plugin-list.php             # Main admin template
```

### Central State Management System

The **StateManager** serves as the single source of truth for all plugin states, preventing synchronization issues between different data sources.

```php
interface StateManagerInterface 
{
    public function getPluginState(string $repositoryName): PluginState;
    public function updatePluginState(string $repositoryName, PluginState $state, array $metadata = []): void;
    public function getPluginMetadata(string $repositoryName): array;
    public function invalidatePlugin(string $repositoryName): void;
    public function bulkUpdateStates(array $updates): void;
    public function registerStateChangeListener(callable $listener): void;
}
```

### Data Flow Architecture

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   GitHub API    │───▶│   GitHubService  │───▶│  StateManager   │
└─────────────────┘    └──────────────────┘    └─────────────────┘
                                                         │
┌─────────────────┐    ┌──────────────────┐              │
│ PQS localStorage│───▶│  PQSIntegration  │─────────────▶│
└─────────────────┘    └──────────────────┘              │
                                                         │
┌─────────────────┐    ┌──────────────────┐              │
│WordPress Plugins│───▶│InstallationService│────────────▶│
└─────────────────┘    └──────────────────┘              │
                                                         ▼
                       ┌──────────────────┐    ┌─────────────────┐
                       │   Admin UI       │◀───│ PluginListTable │
                       └──────────────────┘    └─────────────────┘
```

---

## Detailed Functional Requirements

### FR-1: Repository Management

**Requirement**: Fetch and display GitHub organization repositories

**Acceptance Criteria**:
- [ ] User can configure GitHub organization name in settings
- [ ] System fetches top 50 most recently updated repositories 
- [ ] Repository data is cached for 1 hour to minimize API calls
- [ ] Manual refresh option bypasses cache
- [ ] Handle GitHub API rate limiting gracefully

**Implementation Notes**:
- Use WordPress HTTP API for GitHub requests
- Implement exponential backoff for rate limiting
- Cache using WordPress transients for consistency

### FR-2: Plugin Detection

**Requirement**: Automatically identify which repositories contain WordPress plugins

**Acceptance Criteria**:
- [ ] Scan repository root for PHP files with WordPress plugin headers
- [ ] Support both `main` and `master` branch conventions
- [ ] Extract plugin metadata (name, version, description)
- [ ] Cache detection results for 24 hours
- [ ] Handle repositories that aren't WordPress plugins gracefully

**Implementation Notes**:
- Check for `Plugin Name:` header in PHP comment blocks
- Validate PHP opening tags before processing
- Implement fallback scanning for common plugin file names

### FR-3: Installation Status Detection

**Requirement**: Determine current installation status of repository plugins

**Priority Order**:
1. **PQS Cache** (fastest): Read from Plugin Quick Search localStorage cache
2. **WordPress Registry**: Use `get_plugins()` for authoritative data
3. **Filesystem Scan**: Direct plugin directory inspection as fallback

**Acceptance Criteria**:
- [ ] Integrate with PQS cache without requiring PQS to be active
- [ ] Detect active vs inactive plugin states
- [ ] Identify plugin settings pages when available
- [ ] Handle plugin slug variations (dashes vs underscores)
- [ ] Update states when plugins are installed/activated externally

### FR-4: Batch Installation

**Requirement**: Install multiple plugins simultaneously with progress tracking

**Acceptance Criteria**:
- [ ] Select multiple repositories via checkboxes
- [ ] Install plugins sequentially to avoid conflicts
- [ ] Show real-time progress for each plugin
- [ ] Option to activate plugins after installation
- [ ] Handle installation failures gracefully
- [ ] Update UI states after successful installations

**Implementation Notes**:
- Use WordPress `Plugin_Upgrader` for installations
- Implement queue system for sequential processing
- Provide detailed error messages for failures

### FR-5: WordPress Native UI

**Requirement**: Match WordPress "All Plugins" page design patterns

**Acceptance Criteria**:
- [ ] Use `WP_List_Table` for consistent styling
- [ ] Single column combining status and actions
- [ ] Bulk actions dropdown matching WordPress conventions
- [ ] Pagination for large repository lists
- [ ] Search/filter functionality
- [ ] Responsive design for mobile devices

### FR-6: Error Handling & Recovery

**Requirement**: Graceful handling of all error conditions

**Acceptance Criteria**:
- [ ] Network timeouts and connectivity issues
- [ ] GitHub API rate limiting
- [ ] Plugin installation failures
- [ ] Invalid repository structures
- [ ] WordPress permission errors
- [ ] Filesystem write permission issues

**Error Recovery**:
- [ ] Retry mechanisms for transient failures
- [ ] Clear error messages with suggested actions
- [ ] Fallback detection methods when primary sources fail

---

## Integration Requirements

### PQS Cache Integration

**Requirement**: Read-only integration with Plugin Quick Search cache

**Technical Approach**:
```javascript
// Client-side PQS cache reading
function readPQSCache() {
    try {
        const cache = localStorage.getItem('pqs_plugin_cache');
        const plugins = JSON.parse(cache || '[]');
        return plugins.reduce((acc, plugin) => {
            const variants = [
                plugin.slug,
                plugin.name.toLowerCase().replace(/\s+/g, '-'),
                plugin.name.toLowerCase().replace(/[^a-z0-9]/g, '-')
            ];
            
            variants.forEach(variant => {
                acc[variant] = {
                    isActive: plugin.isActive,
                    pluginFile: plugin.pluginFile,
                    settingsUrl: plugin.settingsUrl,
                    metadata: {
                        name: plugin.name,
                        version: plugin.version,
                        description: plugin.description
                    }
                };
            });
            
            return acc;
        }, {});
    } catch (error) {
        console.warn('Failed to read PQS cache:', error);
        return {};
    }
}
```

**Cache Synchronization**:
- Listen for PQS cache rebuild events
- Invalidate local state when PQS cache changes
- Fallback to WordPress plugin registry when PQS unavailable

### WordPress Core Integration

**Hook Integration Points**:
- `activated_plugin` / `deactivated_plugin`: Update states when plugins change
- `upgrader_process_complete`: Refresh states after plugin updates
- `admin_notices`: Display operation results and error messages

---

## Performance Requirements

### Response Time Targets

- **Initial page load**: < 2 seconds
- **Repository refresh**: < 3 seconds  
- **Plugin state check**: < 500ms
- **Single plugin install**: < 10 seconds
- **Bulk install (5 plugins)**: < 30 seconds

### Caching Strategy

**Multi-layer Caching**:
1. **Repository List**: 1 hour (WordPress transient)
2. **Plugin Detection**: 24 hours (WordPress transient) 
3. **Installation Status**: 5 minutes (in-memory + transient)
4. **PQS Integration**: Real-time (localStorage read-through)

### Memory Management

- Lazy load plugin states (check on-demand)
- Limit concurrent installation operations
- Clear caches when memory usage exceeds thresholds

---

## Security Requirements

### Input Validation

- [ ] Sanitize all GitHub organization names
- [ ] Validate repository names against allowed patterns
- [ ] Escape all output for XSS prevention
- [ ] Verify WordPress nonces on all AJAX requests

### Permission Checks

- [ ] `install_plugins` capability for installation operations
- [ ] `activate_plugins` capability for activation operations  
- [ ] `manage_options` capability for settings changes
- [ ] Network admin permissions for multisite installations

### Installation Security

- [ ] Use WordPress core `Plugin_Upgrader` exclusively
- [ ] Validate plugin archives before extraction
- [ ] Implement source verification for GitHub downloads
- [ ] Prevent path traversal attacks during installation

---

## API Design

### AJAX Endpoints

**Plugin State Management**:
```php
POST /wp-admin/admin-ajax.php
action: gbi_check_plugin_status
data: {
    repository_name: string,
    force_refresh?: boolean
}
response: {
    success: boolean,
    data: {
        repository_name: string,
        state: PluginState,
        metadata: object,
        actions: string[] // Available action buttons
    }
}
```

**Plugin Installation**:
```php
POST /wp-admin/admin-ajax.php  
action: gbi_install_plugin
data: {
    repository_name: string,
    activate_after_install?: boolean
}
response: {
    success: boolean,
    data: {
        repository_name: string,
        new_state: PluginState,
        plugin_file?: string,
        installation_log: string[]
    }
}
```

**Bulk Operations**:
```php
POST /wp-admin/admin-ajax.php
action: gbi_bulk_install
data: {
    repository_names: string[],
    activate_after_install?: boolean
}
response: {
    success: boolean,
    data: {
        results: Array<{
            repository_name: string,
            success: boolean,
            new_state?: PluginState,
            error_message?: string
        }>,
        summary: {
            total: number,
            successful: number,
            failed: number
        }
    }
}
```

### WordPress Hooks & Filters

**Extensibility Points**:
```php
// Filter repository list before display
apply_filters('gbi_repositories', $repositories, $github_org);

// Filter plugin detection logic
apply_filters('gbi_plugin_detection_result', $result, $repository_name);

// Action after successful installation
do_action('gbi_plugin_installed', $repository_name, $plugin_file);

// Filter installation options
apply_filters('gbi_installation_options', $options, $repository_name);
```

---

## Testing Strategy

### Unit Testing

**Core Services**:
- [ ] StateManager state transitions
- [ ] GitHubService repository parsing
- [ ] PluginDetectionService header parsing
- [ ] PQSIntegration cache reading
- [ ] CacheService operations

### Integration Testing

**WordPress Integration**:
- [ ] Plugin installation via upgrader
- [ ] WordPress plugin registry interaction  
- [ ] Admin page rendering
- [ ] AJAX endpoint responses
- [ ] Permission checking

### End-to-End Testing

**User Workflows**:
- [ ] Configure GitHub organization
- [ ] View repository list
- [ ] Check plugin status
- [ ] Install single plugin
- [ ] Bulk install multiple plugins
- [ ] Handle error conditions

### Performance Testing

**Load Testing**:
- [ ] Large repository lists (100+ repos)
- [ ] Concurrent installation operations
- [ ] Cache performance under load
- [ ] Memory usage patterns

---

## Deployment & Maintenance

### Release Process

1. **Development**: Feature branches with PSR-4 compliance
2. **Testing**: Automated test suite + manual QA
3. **Staging**: Test on multiple WordPress versions  
4. **Production**: WordPress.org plugin directory submission

### Monitoring & Metrics

**Error Tracking**:
- Installation failure rates
- GitHub API error rates  
- Cache hit/miss ratios
- Performance metrics

**User Analytics**:
- Feature usage patterns
- Repository detection accuracy
- Installation success rates

### Backup & Recovery

**Data Protection**:
- WordPress database backups before bulk operations
- Plugin installation rollback procedures
- Cache invalidation and rebuild processes

---

## Migration from Existing Plugin

### Migration Strategy

**For Existing Users**:
1. **Preserve Settings**: Import GitHub organization configuration
2. **Cache Migration**: Convert existing transients to new format
3. **Gradual Rollout**: A/B testing with opt-in beta program
4. **Documentation**: Migration guide and feature comparison

### Compatibility Matrix

**WordPress Versions**: 5.0+ (latest 3 major versions)
**PHP Versions**: 7.4+ (following WordPress requirements)
**Multisite**: Full support with network admin integration
**PQS Integration**: Optional but recommended for optimal performance

---

## Success Metrics

### User Experience Metrics

- **Task Completion Rate**: >95% for plugin installation workflows
- **User Satisfaction**: >4.5/5 in plugin directory reviews
- **Time to Value**: <5 minutes from installation to first bulk install
- **Error Recovery**: <10% of users abandon after encountering errors

### Technical Metrics

- **Performance**: All response time targets met consistently
- **Reliability**: >99.5% uptime for core functionality
- **Compatibility**: Works across target WordPress/PHP versions
- **Security**: Zero critical vulnerabilities in security audits

### Business Metrics

- **Adoption**: 1000+ active installations within 6 months
- **Engagement**: Average user installs 5+ plugins per session
- **Retention**: 80% of users return within 30 days
- **Community**: 10+ GitHub issues/PRs from community contributors

This cleanroom rewrite will deliver a modern, maintainable WordPress plugin that provides exceptional user experience while integrating seamlessly with existing WordPress workflows and the PQS ecosystem.
