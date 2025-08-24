# REVISED CHECKLIST


## REVISED CHECKLIST: Phase 1 – Lightweight State Machine (defer Event Sourcing)

- [x] Decision: prioritize a lightweight validated state machine inside StateManager; defer Event Sourcing
- [/] Draft plan: transition map, validated transition() API, small per‑repo event log (transient), minimal wiring at install/activate/deactivate/refresh, Self Tests for transitions
- [x] Implement transition() in StateManager with allowed transition map and guards
- [ ] Centralize state changes to use transition() (replace ad‑hoc setters/derivations at key integration points)
- [x] Add compact transition event log (per repository, capped, transient‑backed) and expose a read method for Self Tests/UI
- [x] Wire transitions in PluginInstallationService (success/error) and AjaxHandler refresh paths
- [x] Add Self Tests for allowed/blocked transitions and event log shape
- [ ] Document states/flows in inline comments and Self Tests

Note: Event Sourcing, snapshots, and replay remain future considerations pending need.

[ ]Define the transition table (allowed from → to) for PluginState

[ ]Add StateManager methods that:

[ ]Validate transitions

[ ]Persist state

[ ]Emit a simple event/log entry per transition (date, source, context)

[ ]Update callers to use only these methods (and remove ad-hoc state derivations)

[ ]Add Self Tests to exercise the transitions and ensure UI actions (Install/Activate/Deactivate/Refresh) align with state

This staged approach keeps scope contained, fixes the real source of regressions, and leaves the door open to adopt event sourcing if/when the complexity justifies it.

Consider Event Sourcing later
If you need full audit trails, replay, or rollback capabilities beyond logs and Self Tests
Start with a minimal “event log” (append-only) before adopting full ES patterns

# DEPRECATED - CLAUE OPUS 4.1 ORIGINAL ASSESMENT

Based on my analysis of this WordPress plugin codebase for batch installing GitHub repositories, here are the top 3 architectural changes to improve stability, debuggability, and protect against regressions:

## 1. Implement a Robust State Machine Pattern with Event Sourcing

OVERALL STATUS: NOT STARTED

**Current Problem:** The plugin has complex state transitions (UNKNOWN → CHECKING → AVAILABLE → INSTALLED) that are scattered across multiple services, leading to the "Install button disappearance" regression documented in v1.0.11-1.0.12.

**Actionable TODOs:**
- Create a `StateMachine` class that enforces valid state transitions with explicit rules (e.g., only AVAILABLE state can transition to INSTALLING)
- Implement an `EventStore` that logs every state change with timestamp, trigger source, and context data
- Add state transition validation that throws exceptions for invalid transitions
- Create a `StateTransitionGuard` that validates data consistency before allowing state changes
- Build a visual state diagram generator from the event log for debugging
- Add rollback capability to revert to previous states when errors occur
- Implement state snapshot persistence every N transitions for recovery

## 2. Create a Layered Repository Pattern with Circuit Breaker

OVERALL STATUS: NOT STARTED

**Current Problem:** Direct coupling between UI components and external services (GitHub API, WordPress filesystem) causes cascading failures and makes debugging difficult. The timeout issues and API failures directly impact the UI.

**Actionable TODOs:**
- Implement a `RepositoryInterface` abstraction layer between services and external systems
- Create separate implementations: `GitHubApiRepository`, `WebScrapingRepository`, `CachedRepository`, `MockRepository`
- Add a `CircuitBreaker` wrapper that monitors failure rates and automatically switches to fallback repositories
- Implement a `RepositoryChain` that tries multiple data sources in sequence with configurable timeout per source
- Create a `HealthMonitor` that tracks success/failure metrics for each repository implementation
- Build retry logic with exponential backoff at the repository layer, not scattered throughout
- Add request/response logging middleware at the repository boundary
- Implement a `DryRunRepository` for testing that simulates operations without side effects

## 3. Establish Contract Testing with Observability Pipeline

OVERALL STATUS: NOT STARTED

**Current Problem:** The self-tests in `SelfTestsPage.php` are good but run after deployment. The regression with Install buttons shows that critical UI functionality can break without immediate detection.

**Actionable TODOs:**
- Create `Contract` interfaces for each service defining expected inputs/outputs
- Implement contract validation decorators that wrap services and validate data at runtime
- Build a `ContractRecorder` that captures real production data flows for replay testing
- Add pre-flight checks that run contract tests before any destructive operation
- Create synthetic transactions that continuously test critical paths (repository fetch → detect → install)
- Implement structured logging with correlation IDs that trace requests across all services
- Build a `DebugContext` collector that captures full execution context when contracts fail
- Add canary deployments that test new code against recorded contract data
- Create visual flow diagrams showing which contracts are satisfied/violated in real-time
- Implement feature flags that can disable problematic code paths without deployment

**Additional Cross-Cutting TODOs:**

OVERALL STATUS: NOT STARTED

- Add immutable value objects for critical data (Repository, PluginFile, InstallationResult)
- Implement the Specification pattern for complex business rules
- Create a command/query separation (CQRS) for read vs write operations
- Add compensating transactions for failed multi-step operations
- Build operation replay capability from event logs for debugging production issues

These changes would transform the brittle procedural code into a robust, observable, and self-healing system that fails gracefully and provides clear debugging paths.