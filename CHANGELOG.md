# Changelog

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

