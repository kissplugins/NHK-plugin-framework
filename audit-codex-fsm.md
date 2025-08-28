# FSM Audit

## 1. Current overall project analysis state
The project is migrating toward a finite‑state‑machine (FSM) architecture guided by a multi‑phase checklist. Phase 1 focuses on introducing a JavaScript FSM and removing ad‑hoc UI flags, while later phases centralize backend state management, add processing locks, and plan for event‑driven updates【F:PROJECT-FSM.md†L9-L70】

## 2. Current FSM implementation status
* **State definitions.** Plugin states are enumerated from `UNKNOWN` through `ERROR`, covering availability and installation states【F:src/Enums/PluginState.php†L10-L17】
* **Backend manager.** `StateManager` validates transitions, logs events, and exposes processing locks and a broadcast stub【F:src/Services/StateManager.php†L41-L156】【F:src/Services/StateManager.php†L170-L197】
* **Frontend mirror.** A lightweight TypeScript `RepositoryFSM` stores per‑repository state and updates the DOM; it logs transitions for debugging【F:src/ts/admin/repositoryFSM.ts†L9-L57】
* **SSE hook.** The admin script listens for `state_changed` events and pushes them through the frontend FSM for immediate UI updates【F:assets/admin.js†L100-L117】

## 3. Possible fallacies/errors/issues with current FSM
* **TypeScript build failure.** Running the TypeScript build fails because `@types/node` is missing, leaving `process.env` unresolved【49de99†L1-L6】
* **Broadcasting incomplete.** Although `StateManager` has a `broadcast` stub, there is no listener registration or backend endpoint yet, leaving real‑time updates partially implemented【F:src/Services/StateManager.php†L170-L197】【F:PROJECT-FSM.md†L72-L89】
* **Residual direct WordPress checks.** Documentation notes remaining direct calls such as `is_plugin_active()` that bypass the FSM, indicating inconsistent state handling in some services【F:PROJECT-FSM.md†L48-L63】

## 4. Gaps towards "bug free" / easy to maintain FSM
* Missing build dependencies block TypeScript compilation and hinder frontend FSM reliability.
* Backend broadcasting lacks listeners and an SSE/long‑polling endpoint, so the frontend relies on partial updates.
* Some services still query WordPress state directly, reducing the FSM’s role as single source of truth.
* Limited test coverage means transitions and event logging are not continuously validated.

## 5. Recommended next steps
### Phase 1 – Quick wins
1. Add `@types/node` (or remove `process` usage) to restore TypeScript builds.
2. Finish replacing direct WordPress checks with `StateManager` helpers.
3. Implement a `notifyBackend` method in `RepositoryFSM` and remove legacy UI flags.

### Phase 2 – Structural improvements
1. Expose a proper event listener/queue system in `StateManager` and create an SSE or long‑polling endpoint.
2. Refactor `PluginInstallationService` so all state changes pass through `transition()`.
3. Introduce automated tests validating allowed/blocked transitions and broadcast queues.

### Phase 3 – Refinement and maintenance
1. Replace initial AJAX sync with real‑time event stream consumption in the frontend.
2. Document and annotate deprecated non‑FSM state methods, ensuring a single state source.
3. Expand test suite and continuous integration to prevent regression in FSM behavior.
