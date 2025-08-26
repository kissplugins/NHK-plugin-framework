# KISS Smart Batch Installer - FSM Implementation Analysis

***

## 🎯 Unified Implementation Plan & Refactoring Checklist

This is a living, canonical checklist that drives the FSM-first implementation. Follow this order to transition to a fully FSM-centric architecture.

### **Phase 1: Implement Frontend State Mirror & Decouple UI**
*Goal: Establish the FSM pattern on the frontend and remove ad-hoc state flags to fix progressive loading bugs and UI drift.*

* [ ] **Task: Create JavaScript FSM Class**
    * Implement the `RepositoryStateMachine` class in JavaScript/TypeScript, mirroring the backend FSM's states and transitions.
    * This class will be the **single source of truth** for UI state.
    * Debug/console output MUST be preserved or improved; wire detailed transition logs at dev level.

* [ ] **Task: Remove Ad-Hoc JavaScript State Variables 🚫**
    * **File**: `src/Admin/RepositoryManager.php` (JavaScript section)
    * **Action**: Delete `isLoading`, `processingQueue`, and `activeRequest` variables entirely.
    * **Replace with**: Calls to the new `repositoryFSM` (e.g., `repositoryFSM.isInState('repo', 'checking')`).
    * Maintain current debug panel/detail logs; add any new FSM-specific breadcrumbs.

* [ ] **Task: Refactor UI Updates to be FSM-Driven**
    * Modify all JavaScript functions that show/hide spinners, disable buttons, or update status text.
    * **Action**: All UI changes must be triggered by state transitions within the `updateUI` method of the `RepositoryStateMachine`.
    * Ensure existing debug logs remain; add transition and guard evaluation logs.

* [ ] **Task: Sync Frontend FSM with Backend**
    * Implement the `notifyBackend` method in the JavaScript FSM.
    * **Action**: Ensure every frontend state transition sends an AJAX call to a new endpoint (`sbi_update_state`) to keep the backend FSM synchronized.

### **Phase 2: Centralize Backend State Management**
*Goal: Make the PHP `StateManager` the undisputed source of truth by absorbing disparate state logic and removing legacy paths.*

* [ ] **Task: Implement Processing State Lock Mechanism 🔐**
    * Add the `acquireProcessingLock` and `releaseProcessingLock` methods to `StateManager.php`.
    * **Action**: Wrap all core processing logic in `AjaxHandler.php` with these lock functions to prevent race conditions.
    * Preserve and extend debug output: log lock acquisition, contention, and release events.

* [ ] **Task: Merge Redundant Services into StateManager 🗑️**
    * **Action**: Merge all logic from `PluginDetectionService.php` into a private `detectPluginState()` method within `StateManager`. The method should return a `PluginState` enum, not a boolean.
    * **Action**: Merge all logic from `PQSIntegration.php` into a private `checkCacheState()` method within `StateManager`.
    * Maintain existing debug logs and enrich with detection/cache decision breadcrumbs.

* [ ] **Task: Deprecate and Replace Direct State Checks 🔄**
    * **Action**: Search the entire codebase for WordPress functions like `is_plugin_active()`.
    * **Replace with**: New `StateManager` methods (e.g., `$this->state_manager->isActive($repo)`). These new methods **must** query the FSM's state, not call the WordPress functions directly.

* [ ] **Task: Eliminate Parallel State Tracking ⚡**
    * **File**: `src/Admin/RepositoryListTable.php`
        * **Action**: Remove any methods that determine state; the class should only read state from `StateManager`.
    * **File**: `src/API/AjaxHandler.php`
        * **Action**: Remove any code that sets state directly. All state changes **must** now use `$this->state_manager->transition()`.

* [ ] **Task: Refactor Installation Service 🔧**
    * **File**: `src/Services/PluginInstallationService.php`
    * **Action**: Refactor the `install_plugin` method to be stateless. It should no longer return success/failure arrays. Instead, it must call `transition()` to move the repository into `INSTALLING`, `INSTALLED_INACTIVE`, or `ERROR` states.

### **Phase 2.5: Near-Term, High-Impact FSM Hardening**
*Goal: Quick wins that reinforce SSoT without large refactors (recommended to do next).*

* [x] Add a minimal processing lock in `StateManager` and apply it in `AjaxHandler` install/activate/deactivate paths.
* [x] Introduce a lightweight frontend RepositoryFSM façade (TS) to apply state→UI mapping for row updates returned by `sbi_refresh_repository`.
* [ ] Add `broadcast()` stub and document the SSE endpoint contract; no UI listener yet.

### **Phase 3: Implement State Broadcasting & Finalize Integration**
*Goal: Complete the event-driven architecture so the system is reactive and robust.*

* [ ] **Task: Implement State Change Broadcasting in PHP 📡**
    * Add the `addListener` and `broadcast` methods to `StateManager.php`.
    * **Action**: Modify the `transition()` method to broadcast a `state_changed` event every time a state change occurs.
    * Include structured transition logs for the debug panel.

* [ ] **Task: Connect Frontend to Backend Events**
    * **Action**: Implement a Server-Sent Events (SSE) or long-polling endpoint in PHP that hooks into the broadcast system.
    * **Action**: In JavaScript, use an `EventSource` to listen for `state_changed` events from the server.
    * Log event stream connection status and received events to the debug panel/console.

* [ ] **Task: Finalize UI Reactivity**
    * **Action**: Remove the initial AJAX-based state sync from Phase 1 in favor of the new event stream. The frontend FSM should now react to events pushed from the server.
    * **Action**: Ensure all UI components update correctly based on the server-pushed events, creating a real-time experience.
    * Preserve and enhance debug output around UI reactivity changes.

* [ ] **Task: Final Code Review and Cleanup**
    * Add `@deprecated` tags to all old, non-FSM state methods.
    * Log all transitions for easier debugging.
    * Verify that all success criteria have been met.

***

### **Guiding Principles & Success Criteria**

> **CRITICAL INSTRUCTION**: The refactoring is complete only when the `StateManager` is the single, undisputed source of state truth.

* **Success Criteria**
    * ✅ **Zero Direct State Checks**: No code directly checks plugin status without going through the FSM.
    * ✅ **Single State Source**: Only `StateManager` manages and reports the current state.
    * ✅ **All Changes Are Transitions**: Every single state change uses the `transition()` method.
    * ✅ **Frontend-Backend Sync**: The JavaScript FSM perfectly mirrors the PHP FSM via the event stream.
    * ✅ **No Duplicate Logic**: State determination logic exists in exactly one place: `StateManager`.

* **Anti-Patterns to Avoid 🚨**
    * ❌ Do not add "helper" methods that bypass the FSM.
    * ❌ Do not create "convenience" functions that check state directly.
    * ❌ Do not keep "backup" state variables "just in case."
    * ❌ Do not allow any component to determine state independently.