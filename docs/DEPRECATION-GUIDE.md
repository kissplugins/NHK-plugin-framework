# FSM Migration & Deprecation Guide

## Overview

As of version 1.0.31, the KISS Smart Batch Installer has transitioned to a **Finite State Machine (FSM) architecture** for all plugin state management. This guide helps developers migrate from legacy patterns to the new FSM-first approach.

## 🚨 Deprecated Patterns

### ❌ Direct WordPress State Checks

**Deprecated:**
```php
// DON'T: Direct WordPress function calls
if (is_plugin_active($plugin_file)) {
    // Handle active plugin
}

$all_plugins = get_plugins();
foreach ($all_plugins as $file => $data) {
    // Manual plugin enumeration
}
```

**✅ Use Instead:**
```php
// DO: FSM-aware state management
if ($this->state_manager->isActive($repository)) {
    // Handle active plugin
}

$plugin_file = $this->state_manager->getInstalledPluginFile($repository);
$state = $this->state_manager->get_state($repository);
```

### ❌ Direct PluginDetectionService Usage

**Deprecated:**
```php
// DON'T: Direct service usage
$detection_result = $this->detection_service->detect_plugin($repo);
```

**✅ Use Instead:**
```php
// DO: StateManager wrapper with FSM integration
$detection_result = $this->state_manager->detect_plugin_info($repo);
```

### ❌ Manual State Tracking

**Deprecated:**
```php
// DON'T: Ad-hoc state variables
$isLoading = true;
$processingQueue = [];
$activeRequest = null;
```

**✅ Use Instead:**
```php
// DO: FSM state queries
$isLoading = $fsm->isInAnyState($repo, [PluginState::CHECKING, PluginState::INSTALLING]);
$state = $fsm->get($repo);
```

## 📋 Migration Checklist

### Backend (PHP)

- [ ] Replace `is_plugin_active()` calls with `StateManager::isActive()`
- [ ] Replace `get_plugins()` enumeration with `StateManager::getInstalledPluginFile()`
- [ ] Use `StateManager::detect_plugin_info()` instead of direct `PluginDetectionService`
- [ ] Ensure all state changes use `StateManager::transition()`
- [ ] Remove manual plugin file discovery logic

### Frontend (JavaScript/TypeScript)

- [ ] Replace generated DOM IDs with `data-repository` attributes
- [ ] Use `RepositoryFSM` for state management instead of ad-hoc variables
- [ ] Implement SSE listeners for real-time state updates
- [ ] Remove manual AJAX polling in favor of event-driven updates

## 🔧 StateManager API Reference

### Core Methods

```php
// State queries
$state = $state_manager->get_state($repository);
$is_active = $state_manager->isActive($repository);
$is_installed = $state_manager->isInstalled($repository);

// File resolution
$plugin_file = $state_manager->getInstalledPluginFile($repository);

// Detection (FSM-aware)
$detection_result = $state_manager->detect_plugin_info($repository);

// State transitions
$state_manager->transition($repository, PluginState::INSTALLING, [
    'source' => 'user_action',
    'context' => ['user_id' => get_current_user_id()]
]);
```

### Error Handling

```php
// Enhanced error transitions
$state_manager->transition($repository, PluginState::ERROR, [
    'error' => 'Installation failed',
    'source' => 'plugin_installer',
    'recoverable' => true
]);
```

## 🎯 Benefits of FSM Approach

### Reliability
- **Single Source of Truth**: All state in one place
- **Validated Transitions**: Prevents invalid state changes
- **Event Logging**: Complete audit trail of state changes

### Performance
- **Cached States**: Reduced WordPress function calls
- **Real-time Updates**: No polling required
- **Optimized Queries**: Batch state operations

### Maintainability
- **Centralized Logic**: State management in one service
- **Type Safety**: Enum-based states prevent typos
- **Clear Patterns**: Consistent API across codebase

## 📚 Further Reading

- [PROJECT-FSM.md](PROJECT-FSM.md) - Complete FSM implementation guide
- [AUDIT-CODEX-FSM.md](AUDIT-CODEX-FSM.md) - FSM architecture analysis
- [StateManager.php](../src/Services/StateManager.php) - Core FSM implementation
- [RepositoryFSM.ts](../src/ts/admin/repositoryFSM.ts) - Frontend FSM implementation

## 🚀 Next Steps

1. **Review Code**: Audit your code for deprecated patterns
2. **Update Calls**: Replace legacy methods with FSM equivalents  
3. **Test Thoroughly**: Validate state transitions work correctly
4. **Monitor Logs**: Watch for deprecation warnings in debug output

The FSM architecture provides a robust, maintainable foundation for plugin state management. Migrating to these patterns will improve reliability and make future development easier.
