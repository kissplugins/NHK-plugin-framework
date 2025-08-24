# Changelog

All notable changes to the KISS Smart Batch Installer will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.16] - 2025-08-24

### Added
- FSM Self Tests: validate allowed/blocked transitions and verify event log structure

### Changed
- Architecture doc: updated REVISED CHECKLIST to mark implemented FSM items

## [1.0.15] - 2025-08-24

### Added
- Lightweight validated state machine in StateManager: explicit transitions, allowed map, and transient-backed event log (capped)
- FSM integration points: Ajax install/activate/deactivate and refresh paths

### Fixed
- Robust state updates during install/activation/deactivation with transition logging

## [1.0.14] - 2025-08-24

### Fixed
- Always render Refresh button in Actions column even for non-plugin rows
- Self Test: force real plugin detection for error-handling subtest (restores original setting)

## [1.0.13] - 2025-08-24

### Fixed
- False negative where Installation State showed Not Plugin while Plugin Status showed WordPress Plugin; normalized to single source of truth
- Improved front-end AJAX failure diagnostics for Install action (HTTP code, response snippet)

### Added
- DO NOT REMOVE developer guard comments around critical debug logging and error reporting in install flow (PHP + JS)

### Developer Notes
- Kept verbose logging in PluginInstallationService and structured debug_steps in AjaxHandler; these aid field debugging and should be preserved

## [1.0.12] - 2025-08-24

### Fixed
- **CRITICAL**: Resolved Install button not appearing in repository table
- Fixed state determination logic in `AjaxHandler::process_repository()` method
- Enhanced plugin file handling to use detected plugin file when available
- Fixed repository data inconsistency between processing and rendering layers
- Corrected skip detection mode to return `is_plugin: true` instead of `false`

### Enhanced
- **Repository Processing**: Improved state determination with comprehensive debug logging
- **Button Rendering**: Enhanced `RepositoryListTable::column_actions()` with better data handling
- **Timeout Protection**: Reduced plugin detection timeout from 8s to 5s with response size limits
- **Error Recovery**: Added retry logic for GitHub API calls with smart rate limit handling
- **Data Consistency**: Fixed data structure flattening to preserve all required fields

### Added
- **NEW**: Comprehensive regression protection self-tests
- **NEW**: Plugin detection reliability tests with timeout validation
- **NEW**: GitHub API resilience tests with retry logic validation
- Added `find_installed_plugin()` helper method to RepositoryListTable
- Added `fetch_with_retry()` method to GitHubService for better error recovery
- Enhanced error logging with detailed failure messages and recovery guidance

### Technical Improvements
- **AjaxHandler**: Enhanced `process_repository()` with better plugin file detection
- **AjaxHandler**: Improved `render_repository_row()` data flattening consistency
- **PluginDetectionService**: Added timeout protection and response size limits (8KB)
- **PluginDetectionService**: Fixed skip detection mode to prevent button disappearance
- **GitHubService**: Implemented retry mechanism for temporary API failures
- **RepositoryListTable**: Improved owner/repo name extraction and button generation
- **Self-Tests**: Added 9 new tests covering critical regression points

### Developer Features
- Comprehensive debug logging for state transitions and button rendering
- Self-tests now include detailed error messages with specific recovery guidance
- Performance timing in tests to identify hanging and slow operations
- Error logging includes file names and method names for faster debugging

## [1.0.11] - 2025-08-24

### Known Issues
- **CRITICAL**: Install buttons not appearing in repository table despite successful repository processing
- Repository detection and plugin analysis working correctly (visible in debug logs)
- Repository data being processed and stored properly with correct plugin states
- Issue appears to be in the UI rendering layer - buttons not being generated in Actions column
- Debug logging added to `RepositoryListTable::column_actions()` method for investigation

### Investigation Status
- Repository fetching: ✅ Working (GitHub API and web scraping)
- Plugin detection: ✅ Working (correctly identifies WordPress plugins)
- State management: ✅ Working (AVAILABLE, INSTALLED_INACTIVE, INSTALLED_ACTIVE states)
- AJAX processing: ✅ Working (repositories processed successfully)
- UI table rendering: ❌ **BROKEN** (action buttons not appearing)

### Technical Details
- Added comprehensive debug logging to track button generation process
- Issue isolated to `column_actions()` method in `RepositoryListTable` class
- Repository data structure appears correct with proper `is_plugin` and `installation_state` values
- Next steps: Investigate why switch statement not matching plugin states for button generation

## [1.0.10] - 2025-08-23

### Fixed
- **SECURITY**: Enhanced HTTPS enforcement for plugin downloads from GitHub
- Added multiple layers of protection to prevent HTTP downgrade attacks
- Implemented GitHub API-based download URL resolution as primary method
- Added comprehensive HTTP request filtering to force HTTPS for all GitHub URLs
- Enhanced error logging and debugging for download URL issues

### Enhanced
- Improved plugin installation reliability with better URL handling
- Added fallback mechanisms for GitHub download URLs
- Better error reporting for download-related issues

## [1.0.9] - 2025-08-23

### Added
- **NEW**: "Debug AJAX" setting in admin interface for controlling debug panel
- Added persistent debug mode that can be enabled/disabled via settings
- Debug panel now only appears when explicitly enabled by user

### Enhanced
- Debug functionality is now optional and controlled by admin setting
- Improved performance when debug mode is disabled (no debug overhead)
- Better user experience with optional debugging features

### Developer Features
- Persistent debug setting stored in WordPress options
- Clean separation between production and debug modes
- Debug panel preserved for future troubleshooting needs

## [1.0.8] - 2025-08-23

### Added
- **NEW**: Comprehensive AJAX debugging panel with real-time logging
- Added detailed client-side debug logging for all AJAX calls and responses
- Added server-side debug logging for AJAX handlers
- Added visual debug panel with color-coded log entries and controls

### Enhanced
- Real-time AJAX request/response monitoring
- Detailed error reporting with timestamps and context
- Visual indicators for different log levels (info, success, warning, error)
- Debug panel controls (show/hide, clear log)

### Developer Features
- Complete AJAX call tracing from client to server
- HTTP response debugging with full request/response data
- Timeout and error condition monitoring
- Performance timing for slow operations

## [1.0.7] - 2025-08-23

### Added
- **NEW**: "Skip Plugin Detection" option for testing basic repository loading
- Added timeout protection and error handling to plugin detection service
- Added detailed error logging for debugging hanging issues

### Fixed
- **CRITICAL**: Fixed hanging issue during repository processing
- Reduced HTTP timeouts to prevent long waits (10s → 8s for file content, 30s → 10s for API)
- Added exception handling around plugin detection to prevent crashes
- Improved error recovery and fallback mechanisms

### Changed
- Enhanced plugin detection service with better timeout management
- Added performance logging for slow plugin detection operations
- Improved error messages and debugging information

## [1.0.6] - 2025-08-23

### Added
- **NEW**: Repository limit setting for progressive testing and deployment
- Added admin interface to control number of repositories processed (1-50)
- Added repository limit parameter to GitHub service and AJAX handlers

### Changed
- Modified repository processing to support limiting for testing purposes
- Enhanced progress messages to show current limit being applied
- Improved user experience with configurable repository limits

### Fixed
- Implemented progressive repository loading to prevent system overload
- Added safeguards to process repositories one at a time with limits

## [1.0.5] - 2025-08-23

### Fixed
- **CRITICAL**: Fixed regex pattern error in HTML parsing that prevented repository detection
- Fixed PHP constant expression error in main plugin file
- Updated XPath selectors to match current GitHub HTML structure (2025)
- Improved debugging output for HTML parsing process

### Changed
- Enhanced HTML parsing with updated selectors for current GitHub layout
- Added better error handling and debugging for web scraping failures

## [1.0.4] - 2025-08-22

### Changed
- **BREAKING**: Changed default fetch method from API to web-only due to GitHub API reliability issues
- Improved web scraping method with pagination support (up to 20 pages)
- Enhanced HTML parsing with multiple selectors to handle different GitHub layouts
- Increased timeouts and added better error handling for web requests
- Added more realistic browser headers for web scraping
- Improved rate limiting with 0.75-second delays between requests

### Fixed
- Resolved GitHub API rate limiting and 500 error issues
- Fixed pagination handling in web scraping
- Improved repository detection across different GitHub page layouts
- Better error handling for partial results when some pages fail

### Added
- Support for extracting language and updated_at information from web scraping
- Consecutive empty page detection to stop pagination early
- More robust error handling with fallback to partial results
- Enhanced debug logging for troubleshooting

## [1.0.3] - 2025-08-22

### Added
- Initial implementation of GitHub API with web scraping fallback
- Basic repository detection and installation functionality

## [1.0.2] - 2025-08-22

### Added
- Core plugin framework and structure

## [1.0.1] - 2025-08-22

### Added
- Initial plugin setup and configuration

## [1.0.0] - 2025-08-22

### Added
- Initial release of KISS Smart Batch Installer
- Basic GitHub repository scanning functionality
