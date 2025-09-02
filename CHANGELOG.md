# Changelog

## [1.0.8] - 2025-09-02

## [1.0.9] - 2025-09-02
### Added
- Minimal readiness FSM (appReadyMachine) and capability loader emitting nhk:* signals. FSM sets window.nhkFeatures flags and never handles data fetching/rendering.

### Changed
- index.js wires loader signals to FSM and kicks off checks during init. Components can now consult window.nhkFeatures for enhancements.

### Notes
- Rebuild assets: `bun run build:js`.

### Added
- PROJECT-KEEP-IT-SIMPLE.md: project-specific checklist that prioritizes WordPress-native patterns and progressive enhancement.

### Changed
- [nhk_events] shortcode now server-renders the initial event list (WP_Query) with standard pagination and filters; JS progressively enhances when ready.
- Kept existing REST endpoints and Alpine components; enhancement is optional and gated by readiness.

### Notes
- No asset rebuild required for this change.


## [1.0.7] - 2025-09-01
### Added
- NHKDebug helper available on pages where plugin assets load. Use from the browser console with `NHKDebug.run()` to print dependency presence, script order, DOM binding state, and REST health. Also supports `NHKDebug.auto(true)` which stores a localStorage flag to auto-run after load.
- Admin toggle to enable NHKDebug autorun globally: Events → Event Manager → Development Notes → “NHK Debug Helper”. When enabled, the helper auto-runs a diagnosis ~2s after load.

### Changed
- Inject NHKDebug helper inline after plugin scripts (`nhk-event-manager-frontend` and `nhk-event-manager-admin`). Helper is lightweight and safe when unused.

### Notes
- Console quick start: `NHKDebug.run()` to run once, `NHKDebug.auto(true)` to persist auto-run in localStorage.

## [1.0.6] - 2025-09-01
### Fixed
- REST base now uses the canonical `rest_url('nhk-events/v1')` everywhere (admin + frontend). This avoids environments where the legacy `?rest_route=/...` query form is not recognized or is intercepted by server config, which previously produced `rest_no_route` 404s for `[nhk_events_simple]` and the admin demo.
- `/health` endpoint `permission_callback` corrected from the capability string `"manage_options"` (invalid as a callable) to a valid callback. We made the endpoint public (`__return_true`) because it returns non-sensitive diagnostics and is used by the new badge.
- XState v5 event shape fix: `onDone` handlers now read `event.output` (not `event.data`), and `onError` reads `event.error`. This resolves the Event Manager UI being stuck in “loading” with a `TypeError: ... Z.data` in DevTools. Source and built assets updated.

### Added
- Admin “API” health badge on the Event Manager page that pings `/wp-json/nhk-events/v1/health` and indicates Healthy/Unavailable with a colored dot. Helps quickly confirm route wiring without opening DevTools.
- Diagnostic step buttons on the demo panel to manually run: Step 1 `/health`, Step 2 init API client, Step 3 fetch events, Step 4 invoke FSM load. Outputs a log panel for quick triage.

### Technical Notes (for implementers)
- WordPress `register_rest_route` expects `permission_callback` to be a callable. Passing a capability string causes a fatal when the route is hit via HTTP (the REST server tries to `call_user_func('manage_options')`). Use `function(){ return current_user_can('manage_options'); }` or `__return_true` for public endpoints.
- Prefer `rest_url('namespace/v#')` for client bases; it maps to `/wp-json/...` and works consistently with and without pretty permalinks, while `?rest_route=` depends on rewrite rules and some hosts block or rewrite it.
- In XState v5, invoke completion events provide `event.output`. If you migrated from v4 examples using `event.data`, update selectors and reducers accordingly or adapt actor output to v5.

All notable changes to this project will be documented in this file.



## [1.0.5] - 2025-09-01
### Fixed
- Prevent 403 `rest_cookie_invalid_nonce` by only sending `X-WP-Nonce` when the user is logged in. Public GET requests are now anonymous as intended.
- Localized `isUserLoggedIn` to the frontend and admin to drive the conditional header.
- Simple demo shortcode now conditionally adds the nonce header.

### Notes
- Rebuild assets after update: `bun run build:js`.

## [1.0.4] - 2025-09-01
### Added
- Diagnostic shortcode [nhk_events_simple] that loads events directly via the REST API (bypassing the XState FSM). Useful to confirm API/asset wiring in both admin and frontend.

### Changed
- Bumped plugin version to 1.0.4.

### Notes
- Use the admin Event Manager page or the shortcode to compare the simple loader vs. the FSM-driven component.

## [1.0.3] - 2025-09-01
### Fixed
- Frontend REST API base is now localized using a route that works with and without pretty permalinks (`/?rest_route=/nhk-events/v1`).
- ApiClient now retries requests using the `rest_route` fallback automatically when a 404 is encountered on the pretty REST path.

### Notes
- Rebuild assets after update: `bun run build:js`.

## [1.0.2] - 2025-09-01
### Fixed
- eventListMachine updated to use XState v5 actors (fromPromise) and invoke input; resolves “Actor type 'loadEvents' not found” and stuck-in-loading state.

### Notes
- Rebuild frontend assets to pick up the change: `bun run build` (or `bun run build:js`). WordPress enqueues assets/dist/index.js.


## [1.0.1] - 2025-09-01
### Fixed
- Admin “Frontend Demo” Event List now uses XState v5-compatible object events, resolving initialization errors and eliminating a false-positive “Unable to load events” notice.
- API client requests are correctly prefixed with the localized REST base (`nhkEventManager.apiUrl`), preventing 404s when loading events.

### Changed
- Internal: Updated global state machine cleanup to use `subscribe({ complete })` for XState v5 compatibility.

## [1.0.0] - 2025-08-31
- Initial release.
