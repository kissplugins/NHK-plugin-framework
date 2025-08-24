# Architectural Guide for WordPress Plugins with External Data Dependencies

## Actionable Checklist: FSM & AJAX Error Handler Integration

This checklist outlines the pragmatic, phased integration of a Finite State Machine (FSM) and an advanced AJAX error handler into the NHK Framework.

-----

### Phase 1: Foundational AJAX Error Handling

**Goal:** Immediately improve debugging and user experience for all AJAX interactions.

  * **1. Integrate `ajax-error-handler.js`:**
      * Add the `ajax-error-handler.js` file to the `/assets/js/` directory.
      * Create a new `AjaxErrorHandler` class instance in a core admin JavaScript file.
      * Ensure it is enqueued and available in the admin area.
  * **2. Refactor Existing AJAX Calls:**
      * Update the AJAX calls in `src/Admin/SettingsPage.php` (for both the "Purge Event Cache" and "Run Health Checks" buttons) to use the new handler.
      * Replace the basic `success`/`error` callbacks with the `ajaxHandler.makeRequest()` wrapper.
      * Verify that the detailed error logging and user notifications are functioning correctly.
  * **3. Update Frontend Shortcode AJAX:**
      * Modify the `nhk-event-filter.js` script used by the `EventListShortcode` to leverage the new error handler for frontend event filtering, ensuring a consistent and robust experience for end-users.

-----

### Phase 2: FSM for Background Jobs

**Goal:** Formalize state management for a critical, self-contained component.

  * **1. Create a Generic FSM Service:**
      * Develop a basic `FiniteStateMachine` service/class within the framework. It should be able to define states, valid transitions, and trigger actions on state changes.
  * **2. Integrate FSM into `Abstract_Background_Job`:**
      * Inject the FSM service into `framework/Abstracts/Abstract_Background_Job.php`.
      * Define the states: `pending`, `scheduled`, `running`, `completed`, `failed`, `cancelled`.
      * Define the valid transitions (e.g., `scheduled` can go to `running` or `cancelled`, but not directly to `completed`).
      * Replace direct manipulation of the `$status` property with FSM transition methods (e.g., `$this->fsm->transitionTo('running')`). This will throw an error if an invalid state change is attempted.

-----

### Phase 3: FSM for Core Event Status

**Goal:** Expand the FSM pattern to manage the core data model, preventing inconsistencies.

  * **1. Define Event States:**
      * Identify the core lifecycle states for an event (e.g., `draft`, `scheduled`, `upcoming`, `ongoing`, `past`, `cancelled`).
  * **2. Implement Event State Manager Service:**
      * Create a new service, `EventStateManager`, that uses the FSM to manage event status.
      * This service will contain the logic for determining an event's current state based on its dates and a new, explicit `_event_status` meta field.
  * **3. Refactor `EventCPT.php`:**
      * In `src/CPT/EventCPT.php`, replace the dynamic status calculation in the `render_column` method with a call to the `EventStateManager` to get the current state from the meta field.
  * **4. Create a WP-Cron Job for State Updates:**
      * Implement a recurring daily WP-Cron job that uses the `EventStateManager` to automatically transition events from `upcoming` to `ongoing`, and from `ongoing` to `past`, ensuring data accuracy without manual intervention.

-----

## Analysis of FSM & AJAX Handler Integration

Integrating a **Finite State Machine (FSM)** and the provided **`ajax-error-handler.js`** is a significant and highly beneficial architectural enhancement for the NHK Framework. These additions directly align with the project's guiding principles of creating a robust, secure, and developer-centric foundation.

### Why a Finite State Machine is a Perfect Fit

The addition of an FSM is not just an improvement; it is the **realization of the architectural goals** already outlined in this document.

  * **Prevents Data Corruption:** The current codebase manages state implicitly. For example, `Abstract_Background_Job.php` tracks job statuses (`pending`, `running`, `completed`) via a simple string property. An FSM formalizes the rules for changing this state (e.g., a `completed` job cannot revert to `running`), which prevents bugs and ensures data integrity.
  * **Improves Maintainability & Clarity:** Centralizing state logic within an FSM makes the code easier to understand and debug. Instead of hunting for every place a status property might be changed, a developer can simply consult the FSM's transition rules. This is crucial for complex lifecycles like an event's journey from `scheduled` -\> `upcoming` -\> `ongoing` -\> `past`.
  * **Enhances Robustness:** An FSM makes the system more resilient. Invalid state transitions (e.g., trying to cancel a job that has already completed) would be explicitly forbidden and would throw an error, catching potential bugs early.

### Why the AJAX Error Handler is a Major Upgrade

The provided `ajax-error-handler.js` elevates the framework's frontend capabilities from basic to professional-grade, directly impacting both developer efficiency and end-user experience.

  * **Superior Debugging:** The handler's ability to parse detailed 500-level errors, track request lifecycles, and provide actionable suggestions in the console is a massive improvement. This aligns perfectly with the framework's goal of being developer-centric and complements the structured `WP_Error` responses sent by `EventsEndpoint.php`.
  * **Improved User Experience:** The existing AJAX calls (e.g., in `SettingsPage.php`) have minimal error feedback. This handler provides a structured, user-friendly notification system, ensuring that users are never left guessing when an operation fails. This is critical for both admin actions and frontend features like event filtering.
  * **Increased Reliability:** With built-in timeout handling and clear distinctions between network, server, and parsing errors, the handler makes all AJAX interactions more reliable and predictable.

## Overview

This guide provides battle-tested architectural patterns for WordPress plugins that integrate with third-party APIs, web scraping, or external data sources. These patterns emerged from real-world production issues including API rate limits, timeout failures, and UI state inconsistencies.

## 1\. State Management Architecture

### The Problem Space

WordPress plugins managing external data face unique challenges:

  - Multiple asynchronous operations with interdependent states
  - UI components that must reflect accurate data status
  - Recovery from partial failures without data corruption
  - Debugging production issues without reproducible steps

### Implementation Pattern: Event-Sourced State Machine

**Core Components:**

```
StateManager (Orchestrator)
├── StateMachine (Rules Engine)
├── EventStore (Audit Log)
├── StateSnapshot (Recovery Points)
└── TransitionValidator (Data Integrity)
```

**Actionable Implementation Steps:**

  - Define explicit state enums for each entity type (e.g., PENDING, FETCHING, PROCESSING, READY, ERROR)
  - Create a state transition matrix defining legal transitions
  - Log every state change as an immutable event with context
  - Implement state snapshots at configurable intervals
  - Add transition hooks for side effects (cache clearing, UI updates)
  - Build replay capability from event history
  - Create WP-CLI commands for state inspection and manual transitions
  - Store state in WordPress transients with proper expiration
  - Implement state reconciliation on plugin activation/upgrade

**WordPress-Specific Considerations:**

  - Use `wp_schedule_single_event()` for delayed state transitions
  - Leverage `shutdown` hook for state persistence
  - Integrate with WordPress admin notices for state error reporting

## 2\. External Data Integration Layer

### The Problem Space

Direct API calls from WordPress plugins create:

  - Cascading failures affecting user experience
  - Difficult debugging of third-party issues
  - No fallback when external services fail
  - Inconsistent error handling across the codebase

### Implementation Pattern: Repository Pattern with Circuit Breaker

**Core Components:**

```
DataRepository (Interface)
├── PrimarySource (API/Scraping)
├── FallbackSource (Cache/Alternative)
├── CircuitBreaker (Failure Protection)
├── RateLimiter (Compliance)
└── ResponseValidator (Data Quality)
```

**Actionable Implementation Steps:**

  - Create abstract `ExternalDataSource` interface with standard methods
  - Implement multiple data source adapters (API, GraphQL, REST, Scraping, RSS, etc.)
  - Add circuit breaker that tracks failure rates per source
  - Implement automatic fallback chain with configurable priorities
  - Create rate limiting with bucket algorithms respecting API limits
  - Add response validation against JSON schemas or contracts
  - Implement request/response caching with WordPress transients
  - Build mock data sources for development and testing
  - Add health checks that probe each data source
  - Create admin UI for monitoring source health and switching
  - Log all external requests with timing and response codes
  - Implement webhook receivers for push-based updates

**WordPress-Specific Considerations:**

  - Use `wp_remote_request()` with proper timeout and SSL verification
  - Leverage `pre_http_request` filter for request mocking
  - Store credentials in `wp_options` with encryption
  - Use `wp_cron` for periodic data refreshes

## 3\. Quality Assurance Through Contract Testing

### The Problem Space

WordPress plugins often break due to:

  - Unexpected data format changes from external sources
  - UI components receiving malformed data
  - Untested edge cases in production
  - No early warning system for degradation

### Implementation Pattern: Runtime Contract Validation with Observability

**Core Components:**

```
ContractSystem (Validation Layer)
├── DataContracts (Schema Definitions)
├── RuntimeValidator (Live Checking)
├── ContractRecorder (Learning Mode)
├── SyntheticMonitor (Continuous Testing)
└── ObservabilityPipeline (Debugging)
```

**Actionable Implementation Steps:**

  - Define contracts as PHP interfaces or JSON schemas
  - Create decorator classes that wrap services with validation
  - Implement "learning mode" that generates contracts from real data
  - Add runtime validation that can be toggled via settings
  - Build synthetic transactions testing critical paths
  - Create correlation IDs that follow requests across components
  - Implement structured logging with contextual data capture
  - Add WordPress admin dashboard widgets showing system health
  - Create WP-CLI commands for contract validation
  - Build regression test suite from recorded production data
  - Implement canary releases with gradual rollout
  - Add feature flags for disabling problematic code paths
  - Create debug mode that captures full execution traces
  - Build admin tools for replaying failed operations

**WordPress-Specific Considerations:**

  - Use WordPress debug constants (`WP_DEBUG`, `WP_DEBUG_LOG`)
  - Integrate with Query Monitor plugin for development
  - Leverage `doing_it_wrong()` for contract violations
  - Use WordPress hooks for observation points

## 4\. WordPress-Specific Best Practices

### Database and Caching Strategy

  - Use WordPress Transients API for temporary data
  - Implement custom tables only when necessary
  - Leverage object cache when available
  - Add cache warming strategies for better UX

### Error Handling and Recovery

  - Use `WP_Error` consistently for error propagation
  - Implement admin notices for user-facing errors
  - Add recovery mechanisms via `wp_cron`
  - Create rollback procedures for failed operations

### Security Considerations

  - Validate capabilities before external requests
  - Sanitize all data from external sources
  - Use nonces for state-changing operations
  - Implement rate limiting per user

### Performance Optimization

  - Use `wp_defer_term_counting()` for bulk operations
  - Implement pagination for large datasets
  - Add progress indicators for long operations
  - Use AJAX for non-blocking operations

## 5\. Debugging and Maintenance Tools

### Essential Debugging Infrastructure

**Actionable Components:**

  - Debug panel (similar to Query Monitor) showing:

      - External API calls with timing
      - State transitions with timestamps
      - Cache hit/miss ratios
      - Failed operations with stack traces

  - WP-CLI commands for:

      - Manual data refresh
      - State inspection and modification
      - Contract validation
      - Cache management
      - Failed operation replay

  - Admin tools providing:

      - Visual state diagrams
      - API health dashboard
      - Operation history browser
      - Manual retry interface

### Monitoring and Alerting

  - Log aggregation compatible format
  - WordPress admin email alerts for critical failures
  - Metrics collection for external service reliability
  - Performance degradation detection

## 6\. Testing Strategy

### Test Pyramid for External Data Plugins

1.  **Unit Tests** (Fast, Isolated)

      - Mock external dependencies
      - Test state transitions
      - Validate data transformations

2.  **Integration Tests** (With WordPress)

      - Test with WordPress loaded
      - Verify hook integrations
      - Check database operations

3.  **Contract Tests** (External Boundaries)

      - Validate API response formats
      - Test error scenarios
      - Verify rate limit handling

4.  **End-to-End Tests** (Full Flow)

      - Test complete user workflows
      - Verify UI state management
      - Check error recovery

## Implementation Priority Matrix

| Priority | Component | Impact | Effort |
|----------|-----------|---------|---------|
| HIGH | State Machine | Prevents data corruption | Medium |
| HIGH | Circuit Breaker | Prevents cascade failures | Low |
| HIGH | Contract Validation | Early error detection | Medium |
| MEDIUM | Event Sourcing | Debugging capability | Medium |
| MEDIUM | Repository Pattern | Code maintainability | High |
| LOW | Synthetic Monitoring | Proactive detection | High |

## Common Pitfalls to Avoid

1.  **Direct API calls in render methods** - Always use cached/async data
2.  **Synchronous external requests** - Use wp\_cron or AJAX
3.  **Missing timeout handling** - Set aggressive timeouts with fallbacks
4.  **No rate limit respect** - Implement bucket algorithms
5.  **Tight coupling to API structure** - Use adapter patterns
6.  **No degradation strategy** - Design for partial functionality
7.  **Insufficient logging** - Log boundaries, not implementation
8.  **No replay capability** - Store enough context to retry

## Conclusion

This architecture provides resilience, observability, and maintainability for WordPress plugins that depend on external data. The patterns are modular - implement based on your plugin's complexity and critical failure points. Start with State Management and Circuit Breaker patterns for immediate stability improvements, then layer in Contract Testing and Observability as the plugin matures.
