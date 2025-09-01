Plan to make Admin “Frontend Demo” FSM‑first and remove the false positive
No code changes made yet. Here’s a small, staged plan to convert the Admin “Frontend Demo” to a single‑source‑of‑truth (FSM‑centric) view and eliminate the error banner you’re seeing.

Findings
The console error in your screenshot comes from sending string events to XState v5. v5 requires event objects: service.send({ type: 'LOAD_EVENTS' }) not service.send('LOAD_EVENTS'). This alone can cause initialization to throw and bubble up a scary “Unable to load events” even when the backend is working.
A few UI states are managed outside the FSM (e.g., showFiltersPanel, selectedEvents, some keyboard behavior), which makes it easy for the UI to drift out of sync with the machine.
Proposed, minimal, staged changes
Stage 1 — Unblock and remove false positive

Convert all service.send calls in the Admin “Frontend Demo” Event List Alpine component to single-object events compatible with XState v5:
this.service.send({ type: 'LOAD_EVENTS' })
this.service.send({ type: 'RELOAD_EVENTS' })
this.service.send({ type: 'APPLY_FILTERS', filters: this.filters })
this.service.send({ type: 'CHANGE_LAYOUT', layout })
this.service.send({ type: 'CHANGE_SORT', sortBy, sortOrder })
this.service.send({ type: 'CHANGE_PAGE', page })
Rationale: Stops the v5 error and ensures the machine runs, so the top error notice won’t display spuriously.
Stage 2 — Move UI flags into FSM (FSM-driven Admin Demo UI)

eventListMachine context additions:
showFiltersPanel: false
selectedIds: [] (or Set internally, but events carry arrays to keep it serializable)
Add FSM events/actions:
TOGGLE_FILTERS
SELECT_EVENT { id }
SELECT_ALL { ids }
CLEAR_SELECTION
Add selectors:
isFiltersOpen(state)
getSelectedIds(state)
getSelectedCount(state)
Update Alpine bindings to:
Use state.matches('loading'|'error'|'loaded') for conditionals
Read selection and filters panel visibility from selectors, not local Alpine data
Map keyboard shortcuts to machine events (e.g., send({ type: 'ESCAPE' }) → closes panel via action)
Stage 3 — FSM-driven demo notice/empty-state messaging

Add isDemoMode to the machine context (or a small parent page FSM if you prefer).
Determine isDemoMode from localized config on entry, and show notice only when state.matches('loaded') and context.isDemoMode === true.
Also split loaded into sub-states: loaded.empty vs loaded.hasData to render empty/demo states solely from the FSM.
Stage 4 — Housekeeping

Bump plugin version (suggest 1.0.1) and update the changelog.
Rebuild assets; quick smoke test in the Admin Demo page.
Files we’d touch (upcoming; not edited yet)
assets/src/js/components/eventList.js
assets/src/js/machines/eventListMachine.js
assets/src/js/index.js (only if we need small parent FSM wiring; otherwise unchanged)
nhk-event-manager.php (version bump)
CHANGELOG.md (add entry; if it doesn’t exist, I’ll create it with your approval)
Safety/Scope
No refactors beyond the immediate FSM-first changes for the Admin “Frontend Demo”.
We will reuse the existing machine and selectors where possible (DRY).
We’ll keep labels/strings intact.
We’ll leverage the existing FSM rather than introducing parallel state.
