# Lessons Learned: NHK Framework Improvements from SBI Development

## Overview

During the development of the KISS Smart Batch Installer (SBI) based on the NHK plugin framework, we encountered and solved several critical issues that revealed opportunities for framework improvements. This document outlines actionable contributions that can be made back to the NHK framework to benefit all future plugins.

## Critical Issues Solved in SBI

### 1. **Install Button Regression** 
- **Problem**: UI buttons disappeared due to state management inconsistencies
- **Root Cause**: Data structure inconsistencies between processing and rendering layers
- **Solution**: Enhanced state validation and data consistency checks

### 2. **Plugin Detection Hanging**
- **Problem**: Plugin detection would hang indefinitely on network issues
- **Root Cause**: No timeout protection or response size limits
- **Solution**: Comprehensive timeout protection and retry logic

### 3. **GitHub API Reliability**
- **Problem**: API failures caused complete plugin breakdown
- **Root Cause**: No fallback mechanisms or error recovery
- **Solution**: Multi-method API access with intelligent fallbacks

### 4. **State Management Inconsistencies** *(NEW - v1.0.15+)*
- **Problem**: Plugin Status showed "WordPress Plugin" while Installation State showed "Not Plugin"
- **Root Cause**: Multiple sources of truth for state without validation or coordination
- **Solution**: Lightweight finite state machine with validated transitions and event logging

### 5. **Debug Information Loss During Refactoring** *(NEW - v1.0.15+)*
- **Problem**: Critical debug logging accidentally removed during code improvements
- **Root Cause**: No protection mechanism for essential debugging infrastructure
- **Solution**: "DO NOT REMOVE" guard comments and structured debug preservation

## Framework Improvement Opportunities

### 🎯 **Priority 1: Self-Testing Infrastructure**

**Current Gap**: No built-in regression protection for framework plugins
**SBI Solution**: Comprehensive self-test framework with 8 test categories

#### Actionable Checklist:
- [ ] Extract SBI's `SelfTestsPage.php` into abstract base class
- [ ] Create `NHK_SelfTest_Framework` abstract class
- [ ] Add standard test categories: Core Services, AJAX Handlers, UI Components, Data Integrity
- [ ] Include performance timing and detailed error reporting
- [ ] Add regression protection test templates
- [ ] Create framework documentation for implementing tests
- [ ] Add self-test page template to framework boilerplate

#### Implementation Files to Create:
```
framework/Abstracts/NHK_SelfTest_Framework.php
framework/Templates/self-test-page-template.php
framework/Documentation/self-testing-guide.md
```

### 🎯 **Priority 2: Enhanced AJAX Handler Pattern**

**Current Gap**: Basic AJAX handling without retry logic or timeout protection
**SBI Solution**: Advanced AJAX handlers with retry, timeout, and error recovery

#### Actionable Checklist:
- [ ] Extract retry logic from `GitHubService::fetch_with_retry()`
- [ ] Create `NHK_AJAX_Handler_Enhanced` base class
- [ ] Add timeout protection methods
- [ ] Include rate limiting detection and handling
- [ ] Add comprehensive error logging with context
- [ ] Create performance monitoring capabilities
- [ ] Add progressive data processing patterns
- [ ] Update framework AJAX documentation

#### Implementation Files to Create:
```
framework/Abstracts/NHK_AJAX_Handler_Enhanced.php
framework/Traits/NHK_Retry_Logic_Trait.php
framework/Traits/NHK_Timeout_Protection_Trait.php
```

### 🎯 **Priority 3: Finite State Machine & Event Logging** *(UPDATED - v1.0.15+)*

**Current Gap**: No standardized state management patterns with transition validation
**SBI Solution**: Lightweight FSM with validated transitions and transient-backed event logging

#### Actionable Checklist:
- [ ] Extract `StateManager::transition()` pattern into generic framework FSM
- [ ] Create `NHK_State_Machine` abstract base class with transition validation
- [ ] Add allowed transitions map configuration
- [ ] Include per-entity event logging with ring buffer (transient-backed)
- [ ] Add transition blocking and logging for invalid moves
- [ ] Create `get_events()` method for debugging and audit trails
- [ ] Add force parameter for system-initiated transitions (refresh, etc.)
- [ ] Include Self Tests for FSM validation and event log structure
- [ ] Document FSM patterns and transition design best practices

#### Implementation Files to Create:
```
framework/Abstracts/NHK_State_Machine.php
framework/Traits/NHK_Event_Logging_Trait.php
framework/Traits/NHK_Transition_Validation_Trait.php
framework/Templates/fsm-self-tests-template.php
```

### 🎯 **Priority 4: Debug Preservation & Enhanced Diagnostics** *(NEW - v1.0.15+)*

**Current Gap**: Critical debug information lost during refactoring; insufficient AJAX error details
**SBI Solution**: Protected debug logging with guard comments and enhanced AJAX diagnostics

#### Actionable Checklist:
- [ ] Create `NHK_Debug_Guard` utility for protecting critical debug code
- [ ] Establish "DO NOT REMOVE" comment standards for essential logging
- [ ] Add AJAX fail handler enhancement patterns (HTTP codes, response snippets)
- [ ] Create debug preservation validation in framework Self Tests
- [ ] Include structured error context capture (URL, method, response size)
- [ ] Add debug mode detection and conditional verbose logging
- [ ] Document debug preservation best practices and standards
- [ ] Create linting rules to detect removal of protected debug code

#### Implementation Files to Create:
```
framework/Utilities/NHK_Debug_Guard.php
framework/Traits/NHK_Enhanced_AJAX_Diagnostics_Trait.php
framework/Documentation/debug-preservation-standards.md
```

## Secondary Improvements

### 🌐 **External API Integration Patterns**

#### Actionable Checklist:
- [ ] Extract multi-method API access pattern from `GitHubService`
- [ ] Create `NHK_External_API_Service` base class
- [ ] Add fallback mechanism templates (API → Web scraping → Cache)
- [ ] Include rate limiting respect patterns
- [ ] Add caching strategies with error caching
- [ ] Create progressive loading helpers
- [ ] Document API integration best practices

### 🎨 **Advanced Admin UI Components**

#### Actionable Checklist:
- [ ] Extract progressive loading table from `RepositoryListTable`
- [ ] Create `NHK_Progressive_List_Table` base class
- [ ] Add debug panel components
- [ ] Include status indicator helpers
- [ ] Add AJAX-powered admin interface patterns
- [ ] Create consistent error display components
- [ ] Document UI component usage

### 🔧 **Enhanced Error Handling & Debug Preservation** *(UPDATED - v1.0.15+)*

#### Actionable Checklist:
- [ ] Extract detailed error logging patterns from SBI
- [ ] Create `NHK_Error_Handler` utility class with HTTP code analysis
- [ ] Add context-aware logging methods with response snippet capture
- [ ] Include error recovery guidance in messages (403/404/SSL hints)
- [ ] Add performance timing utilities
- [ ] Create debug mode management with preservation guards
- [ ] Implement "DO NOT REMOVE" comment standards for critical debug code
- [ ] Add AJAX fail handler enhancement patterns (HTTP codes, response snippets)
- [ ] Document error handling and debug preservation best practices

## Implementation Strategy

### Phase 1: Foundation (Week 1-2)
- [ ] Create abstract base classes for top 3 priorities
- [ ] Extract and generalize SBI patterns
- [ ] Remove SBI-specific logic
- [ ] Add configuration hooks for customization

### Phase 2: Integration (Week 3-4)
- [ ] Add new base classes to NHK framework core
- [ ] Update framework autoloader
- [ ] Create migration documentation
- [ ] Update framework boilerplate templates

### Phase 3: Documentation (Week 5)
- [ ] Create comprehensive usage guides
- [ ] Add code examples for each pattern
- [ ] Document migration paths for existing plugins
- [ ] Create video tutorials for complex patterns

### Phase 4: Validation (Week 6-8)
- [ ] Retrofit SBI to use new framework patterns
- [ ] Test with other existing framework plugins
- [ ] Gather feedback from framework users
- [ ] Iterate based on real-world usage

### Phase 5: Release (Week 9-10)
- [ ] Finalize framework version with new features
- [ ] Create release notes with migration guide
- [ ] Update framework documentation site
- [ ] Announce improvements to framework community

## Expected Benefits

### For Framework Users:
- **Instant Quality Assurance**: Built-in regression protection for all plugins
- **Improved Reliability**: Robust error handling and recovery patterns
- **Faster Development**: Pre-built patterns for common complex tasks
- **Better User Experience**: Consistent, modern admin interfaces
- **Reduced Debugging Time**: Comprehensive logging and error reporting

### For Framework Maintainers:
- **Reduced Support Burden**: Self-diagnosing plugins with detailed error messages
- **Higher Plugin Quality**: Built-in testing encourages better development practices
- **Competitive Advantage**: More robust framework attracts more developers
- **Community Contributions**: Easier for users to contribute improvements

## Success Metrics

- [ ] **Adoption Rate**: 80% of new framework plugins use enhanced patterns within 6 months
- [ ] **Bug Reduction**: 50% reduction in support tickets related to common issues
- [ ] **Development Speed**: 30% faster plugin development with new patterns
- [ ] **User Satisfaction**: Improved admin interface consistency across framework plugins
- [ ] **Framework Growth**: Increased framework adoption due to improved capabilities

## Next Steps

1. **Immediate (This Week)**:
   - [ ] Review this document with framework team
   - [ ] Prioritize which improvements to implement first
   - [ ] Assign team members to each improvement area

2. **Short Term (Next Month)**:
   - [ ] Begin Phase 1 implementation
   - [ ] Set up development branch for framework improvements
   - [ ] Create project timeline and milestones

3. **Long Term (Next Quarter)**:
   - [ ] Complete all phases of implementation
   - [ ] Release enhanced framework version
   - [ ] Begin migration of existing plugins

## Code Examples & Templates

### Self-Testing Framework Template
```php
// framework/Abstracts/NHK_SelfTest_Framework.php
abstract class NHK_SelfTest_Framework {
    protected $test_results = [];

    abstract protected function get_test_categories();
    abstract protected function test_core_functionality();

    protected function run_test($name, $callback) {
        $start_time = microtime(true);
        try {
            $result = $callback();
            $end_time = microtime(true);
            return [
                'name' => $name,
                'passed' => true,
                'message' => $result,
                'time' => round(($end_time - $start_time) * 1000, 2),
                'error' => null
            ];
        } catch (Exception $e) {
            $end_time = microtime(true);
            error_log("Plugin Test Failed - {$name}: " . $e->getMessage());
            return [
                'name' => $name,
                'passed' => false,
                'message' => 'Test failed',
                'time' => round(($end_time - $start_time) * 1000, 2),
                'error' => $e->getMessage()
            ];
        }
    }
}
```

### Enhanced AJAX Handler Template
```php
// framework/Abstracts/NHK_AJAX_Handler_Enhanced.php
abstract class NHK_AJAX_Handler_Enhanced extends NHK_AJAX_Handler {

    protected function fetch_with_retry($url, $args, $max_retries = 2) {
        $attempts = 0;
        $last_error = null;

        while ($attempts < $max_retries) {
            $response = wp_remote_get($url, $args);

            if (!is_wp_error($response)) {
                $code = wp_remote_retrieve_response_code($response);
                if ($code === 200) return $response;
                if ($code === 403 || $code === 429) return $response; // Don't retry rate limits
            }

            $last_error = $response;
            $attempts++;

            if ($attempts < $max_retries) {
                sleep(1); // Wait before retry
            }
        }

        return $last_error;
    }

    protected function handle_timeout_protection($callback, $timeout_ms = 5000) {
        $start_time = microtime(true);

        try {
            $result = $callback();
            $end_time = microtime(true);
            $duration = ($end_time - $start_time) * 1000;

            if ($duration > $timeout_ms) {
                error_log("Operation exceeded timeout: {$duration}ms > {$timeout_ms}ms");
            }

            return $result;
        } catch (Exception $e) {
            $end_time = microtime(true);
            $duration = ($end_time - $start_time) * 1000;
            error_log("Operation failed after {$duration}ms: " . $e->getMessage());
            throw $e;
        }
    }
}
```

### Finite State Machine Template *(NEW - v1.0.15+)*
```php
// framework/Abstracts/NHK_State_Machine.php
abstract class NHK_State_Machine {

    protected array $allowed_transitions = [];
    private const EVENT_LOG_TTL = DAY_IN_SECONDS;
    private const EVENT_LOG_LIMIT = 30;

    abstract protected function get_state_enum_class();
    abstract protected function init_transitions(): void;

    /**
     * Transition to a new state with validation and event logging.
     */
    public function transition(string $entity_id, $to_state, array $context = [], bool $force = false): void {
        $from_state_value = $this->get_current_state_value($entity_id);
        $to_value = $to_state->value;

        // Initialize transition map on first use
        if (empty($this->allowed_transitions)) {
            $this->init_transitions();
        }

        if (!$force) {
            $allowed = $this->allowed_transitions[$from_state_value] ?? [];
            if (!in_array($to_value, $allowed, true)) {
                // Log and ignore invalid transition to keep system robust
                $this->log_event($entity_id, 'transition_blocked', [
                    'from' => $from_state_value,
                    'to' => $to_value,
                    'reason' => 'invalid_transition',
                    'context' => $context,
                ]);
                return;
            }
        }

        $this->set_state($entity_id, $to_state);
        $this->log_event($entity_id, 'transition', [
            'from' => $from_state_value,
            'to' => $to_value,
            'context' => $context,
        ]);
    }

    /**
     * Append event to per-entity transient-backed ring buffer.
     */
    private function log_event(string $entity_id, string $event, array $data = []): void {
        $key = $this->get_event_log_key($entity_id);
        $events = get_transient($key);
        if (!is_array($events)) { $events = []; }

        $events[] = [
            't' => time(),
            'event' => $event,
            'data' => $data,
        ];

        // Cap size
        if (count($events) > self::EVENT_LOG_LIMIT) {
            $events = array_slice($events, -self::EVENT_LOG_LIMIT);
        }

        set_transient($key, $events, self::EVENT_LOG_TTL);
    }

    /**
     * Read recent events for an entity (for Self Tests/UI).
     */
    public function get_events(string $entity_id, int $limit = 10): array {
        $key = $this->get_event_log_key($entity_id);
        $events = get_transient($key);
        if (!is_array($events)) { return []; }
        return array_slice($events, -$limit);
    }

    abstract protected function get_current_state_value(string $entity_id): string;
    abstract protected function set_state(string $entity_id, $state): void;
    abstract protected function get_event_log_key(string $entity_id): string;
}
```

## Migration Guide for Existing Plugins

### Step 1: Update Plugin Structure
- [ ] Add `use` statements for new framework classes
- [ ] Extend enhanced base classes instead of basic ones
- [ ] Update constructor calls to include new dependencies

### Step 2: Implement Self-Tests
- [ ] Create `tests/` directory in plugin
- [ ] Extend `NHK_SelfTest_Framework`
- [ ] Implement plugin-specific test methods
- [ ] Add self-test admin page

### Step 3: Enhance AJAX Handlers
- [ ] Replace `wp_remote_get` calls with `fetch_with_retry`
- [ ] Add timeout protection to long-running operations
- [ ] Implement comprehensive error logging

### Step 4: Implement Finite State Machine *(NEW - v1.0.15+)*
- [ ] Create plugin-specific state enums
- [ ] Extend `NHK_State_Machine`
- [ ] Define allowed transitions map in `init_transitions()`
- [ ] Replace direct state setters with `transition()` calls
- [ ] Add Self Tests for FSM validation and event logging
- [ ] Implement debug preservation guards for critical logging

## Framework Version Compatibility

### Current Framework (v1.x)
- Basic AJAX handling
- Simple admin page templates
- Manual error handling
- No built-in testing

### Enhanced Framework (v2.x) *(UPDATED - v1.0.15+)*
- Advanced AJAX with retry logic and timeout protection
- Self-testing infrastructure with FSM validation
- Finite state machine with validated transitions and event logging
- Comprehensive error handling with debug preservation
- Progressive UI components with always-available refresh
- Enhanced AJAX diagnostics with HTTP codes and response snippets

### Migration Path
1. **Backward Compatible**: New features are opt-in
2. **Gradual Adoption**: Plugins can migrate feature by feature
3. **Documentation**: Clear guides for each enhancement
4. **Support**: Framework team provides migration assistance

---

**Document Version**: 1.1
**Created**: 2025-08-24
**Updated**: 2025-08-24 (Added FSM and debug preservation lessons)
**Based on**: SBI v1.0.16 development experience including architectural refactoring
**Target Framework**: NHK Plugin Framework v2.0+
**Review Status**: Ready for framework team review
