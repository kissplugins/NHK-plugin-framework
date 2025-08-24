# KISS Smart Batch Installer - Web Scraping System

## Overview

The KISS Smart Batch Installer uses a robust web scraping system to fetch GitHub repository data without relying on the GitHub API. This approach bypasses rate limits and provides more reliable access to public repository information.

## Architecture

### Core Components

1. **GitHubService** (`src/Services/GitHubService.php`)
   - Main service class handling repository fetching
   - Contains both API and web scraping methods
   - Manages caching and error handling

2. **Web Scraping Method** (`fetch_repositories_via_web()`)
   - Primary method for fetching repository data
   - Handles pagination and progressive loading
   - Implements robust error handling

3. **HTML Parser** (`parse_repositories_from_html()`)
   - Parses GitHub HTML pages to extract repository data
   - Uses multiple selectors for different page layouts
   - Extracts metadata like description, language, and update time

## How Web Scraping Works

### 1. Request Flow

```
User Request → GitHubService → Web Scraping → HTML Parsing → Repository Data
```

### 2. Pagination System

The scraper handles accounts with many repositories through pagination:

- **Page Range**: Processes up to 20 pages
- **URL Pattern**: `https://github.com/{account}?tab=repositories&page={n}`
- **Empty Page Detection**: Stops after 3 consecutive empty pages
- **Rate Limiting**: 0.75-second delay between requests

### 3. Request Headers

The system uses realistic browser headers to avoid detection:

```php
'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo( 'version' ) . '; +' . get_bloginfo( 'url' ) . ')',
'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
'Accept-Language' => 'en-US,en;q=0.9',
'DNT' => '1',
'Connection' => 'keep-alive',
```

## HTML Parsing Strategy

### Multiple Selector Approach

The parser uses multiple CSS selectors to handle different GitHub layouts:

1. **Modern Layout**: `//div[@data-testid="results-list"]//h3/a[contains(@href, "/{account}/")]`
2. **Alternative Layout**: `//article//h3/a[contains(@href, "/{account}/")]`
3. **Fallback**: Generic repository link detection

### Data Extraction

For each repository, the parser extracts:

- **Name**: From the repository link
- **Description**: From nearby `<p>` elements with description classes
- **Language**: From `<span>` elements with language classes
- **Updated Time**: From `<relative-time>` elements
- **URL**: Constructed from account and repository name

### Repository Data Structure

```php
[
    'id' => crc32( $account_name . '/' . $repo_name ),
    'name' => $repo_name,
    'full_name' => $account_name . '/' . $repo_name,
    'description' => $description ?: null,
    'html_url' => 'https://github.com/' . $account_name . '/' . $repo_name,
    'clone_url' => 'https://github.com/' . $account_name . '/' . $repo_name . '.git',
    'default_branch' => 'main',
    'updated_at' => $updated_at ?: null,
    'language' => $language ?: null,
    'source' => 'web',
    // ... other fields with defaults
]
```

## Error Handling

### Progressive Degradation

- **First Page Errors**: Return error to user
- **Subsequent Page Errors**: Return partial results
- **Empty Pages**: Continue until consecutive limit reached
- **Network Timeouts**: Graceful fallback with partial data

### Error Types

1. **Network Errors**: Connection failures, timeouts
2. **HTTP Errors**: 404 (account not found), 429 (rate limited)
3. **Parsing Errors**: Malformed HTML, missing elements
4. **Empty Results**: No repositories found

## Configuration Options

### Fetch Methods

Users can choose from three methods in the admin interface:

1. **Web Scraping Only** (Recommended)
   - Uses only web scraping
   - No rate limits
   - Most reliable

2. **GitHub API with Web Fallback**
   - Tries API first, falls back to web scraping
   - May hit rate limits

3. **GitHub API Only** (Not Recommended)
   - API only, no fallback
   - Subject to rate limits and reliability issues

### Caching

- **Cache Duration**: 1 hour (3600 seconds)
- **Cache Key**: Based on account name and fetch method
- **Force Refresh**: Available via admin interface

## Performance Considerations

### Request Timing

- **Timeout**: 30 seconds per request
- **Delay**: 0.75 seconds between pages
- **Max Pages**: 20 pages maximum
- **Concurrent Requests**: Sequential (one at a time)

### Memory Usage

- **DOM Parsing**: Uses PHP's DOMDocument for safe HTML parsing
- **Progressive Loading**: Processes one page at a time
- **Duplicate Detection**: Prevents duplicate repositories

## Debugging

### Debug Logging

When `WP_DEBUG` is enabled, the system logs:

- Request URLs and timing
- Response codes and errors
- Repository counts per page
- Parsing results and selector usage

### Common Issues

1. **No Repositories Found**
   - Check if account exists
   - Verify account has public repositories
   - Check debug logs for parsing issues

2. **Partial Results**
   - Network timeouts on later pages
   - GitHub layout changes
   - Rate limiting (rare with web scraping)

3. **Parsing Failures**
   - GitHub HTML structure changes
   - JavaScript-rendered content
   - Anti-scraping measures

## Maintenance

### Updating Selectors

If GitHub changes their HTML structure:

1. Inspect the new repository page layout
2. Update selectors in `parse_repositories_from_html()`
3. Test with various account types
4. Add new selectors to the array (don't remove old ones immediately)

### Monitoring

- Monitor error logs for parsing failures
- Check success rates across different account types
- Watch for changes in GitHub's HTML structure
- Test with accounts of varying sizes

## Security Considerations

### Rate Limiting

- Respectful delays between requests
- Progressive loading to avoid overwhelming GitHub
- Graceful handling of rate limit responses

### User Agent

- Identifies as WordPress with site URL
- Includes version information
- Follows web scraping best practices

### Data Handling

- Only processes public repository data
- No authentication or private data access
- Respects robots.txt guidelines

## Recent Challenges & Lessons Learned (v1.0.11-1.0.12)

### Critical Regression: Install Button Disappearance

**Problem**: After implementing web scraping, Install buttons stopped appearing in the repository table despite successful data fetching.

**Root Causes Identified**:
1. **State Management Inconsistency**: Plugin detection was returning `is_plugin: false` when skip detection was enabled
2. **Data Structure Flattening Issues**: Repository data was losing required fields during processing
3. **Button Rendering Logic**: Early return in `column_actions()` when `is_plugin` was false

**Impact**: Complete breakdown of core functionality - users couldn't install any plugins

### Recovery Strategy Implemented

#### 1. **Enhanced Debug Logging**
```php
// Added comprehensive logging throughout the pipeline
error_log( sprintf( 'SBI: Repository %s - is_plugin: %s, state: %s',
    $repo_name,
    $is_plugin ? 'true' : 'false',
    $state->value
) );
```

#### 2. **Data Consistency Validation**
```php
// Improved data flattening in render_repository_row()
$flattened_data = array_merge(
    $repo_data,
    [
        'is_plugin' => $repository_data['is_plugin'] ?? false,
        'plugin_data' => $repository_data['plugin_data'] ?? [],
        'plugin_file' => $repository_data['plugin_file'] ?? '',
        'installation_state' => \SBI\Enums\PluginState::from( $repository_data['state'] ?? 'unknown' ),
        'full_name' => $repo_data['full_name'] ?? '',  // Ensure preservation
        'name' => $repo_data['name'] ?? '',  // Ensure preservation
    ]
);
```

#### 3. **Skip Detection Mode Fix**
```php
// Fixed skip detection to return appropriate state
if ( $skip_detection ) {
    return [
        'is_plugin' => true,  // Changed from false to true
        'plugin_file' => $repository['name'] . '.php',
        'plugin_data' => [
            'Plugin Name' => $repository['name'],
            'Description' => $repository['description'] ?? '',
        ],
        'scan_method' => 'skipped_for_testing',
        'error' => null,
    ];
}
```

### Regression Prevention Measures

#### 1. **Comprehensive Self-Tests Added**
- **Repository Processing & Button Rendering Tests**: Validates complete flow from data processing to UI rendering
- **Plugin Detection Reliability Tests**: Ensures timeout protection and skip mode work correctly
- **GitHub API Resilience Tests**: Validates retry logic and fallback mechanisms

#### 2. **Enhanced Error Reporting**
```php
// Self-tests now include detailed recovery guidance
if ( ! $button_found ) {
    throw new \Exception( sprintf(
        'REGRESSION DETECTED: Expected "%s" button not found for state %s. This indicates the Install button fix has regressed. HTML output: %s. Check RepositoryListTable::column_actions() method and ensure is_plugin field is properly set.',
        $expected_button,
        $state->value,
        $actions_html
    ) );
}
```

#### 3. **Timeout Protection Improvements**
```php
// Reduced timeouts and added response size limits
$args = [
    'timeout' => 5,  // Reduced from 8 seconds
    'blocking' => true,
    'stream' => false,
    'filename' => null,
    'limit_response_size' => 8192,  // Only need first 8KB for headers
    'headers' => [
        'User-Agent' => self::USER_AGENT,
    ],
];
```

#### 4. **Retry Logic for API Calls**
```php
// Added intelligent retry mechanism
private function fetch_with_retry( $url, $args, $max_retries = 2 ) {
    $attempts = 0;
    $last_error = null;

    while ( $attempts < $max_retries ) {
        $response = wp_remote_get( $url, $args );

        if ( ! is_wp_error( $response ) ) {
            $code = wp_remote_retrieve_response_code( $response );
            if ( $code === 200 ) return $response;
            // Don't retry rate limits
            if ( $code === 403 || $code === 429 ) return $response;
        }

        $last_error = $response;
        $attempts++;

        if ( $attempts < $max_retries ) {
            sleep( 1 );  // Wait before retry
        }
    }

    return $last_error;
}
```

## Recovery Playbook for Future Regressions

### 🚨 **Emergency Response Checklist**

#### **Step 1: Immediate Assessment (5 minutes)**
- [ ] Check if Install buttons are visible in repository table
- [ ] Verify repository data is being fetched (check debug logs)
- [ ] Test with "Skip Plugin Detection" both enabled and disabled
- [ ] Check browser console for JavaScript errors

#### **Step 2: Quick Diagnosis (10 minutes)**
- [ ] Run Self-Tests from admin page (`/wp-admin/admin.php?page=sbi-self-tests`)
- [ ] Check "Regression Protection Tests" results
- [ ] Review error logs for state management issues
- [ ] Verify `is_plugin` field values in debug output

#### **Step 3: Common Fix Patterns (15 minutes)**

**If buttons missing but data loads:**
```php
// Check RepositoryListTable::column_actions() method
// Verify early return condition:
if ( ! $item['is_plugin'] ) {
    return '<span style="color: #999;">No actions available</span>';
}
```

**If plugin detection failing:**
```php
// Check PluginDetectionService skip detection mode
// Ensure it returns is_plugin: true when skipping
```

**If data structure inconsistent:**
```php
// Check AjaxHandler::render_repository_row() flattening
// Ensure all required fields are preserved
```

#### **Step 4: Systematic Recovery (30 minutes)**
- [ ] Enable comprehensive debug logging
- [ ] Test with single repository using repository test tool
- [ ] Verify state transitions: UNKNOWN → CHECKING → AVAILABLE
- [ ] Check data consistency at each processing stage
- [ ] Validate button rendering logic with mock data

### 🔧 **Debug Tools & Commands**

#### **Enable Debug Mode**
```php
// Add to wp-config.php for detailed logging
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

#### **Test Single Repository**
```php
// Use repository test tool in admin interface
// Test with known working repository: kissplugins/KISS-Smart-Batch-Installer
```

#### **Check State Management**
```php
// Verify state enum values
$state = \SBI\Enums\PluginState::AVAILABLE;
error_log("State value: " . $state->value); // Should be 'available'
```

#### **Validate Data Structure**
```php
// Check flattened data structure
error_log("Flattened data keys: " . json_encode(array_keys($flattened_data)));
// Should include: full_name, name, is_plugin, installation_state
```

### 📊 **Monitoring & Prevention**

#### **Regular Health Checks**
- [ ] Run self-tests weekly
- [ ] Monitor error logs for state management issues
- [ ] Test with different repository types (plugins vs non-plugins)
- [ ] Verify timeout protection is working (operations complete < 5s)

#### **Code Review Checklist**
- [ ] Any changes to state management must include self-tests
- [ ] Data structure changes require consistency validation
- [ ] UI rendering changes need button visibility tests
- [ ] Timeout modifications require performance validation

#### **Release Validation**
- [ ] All self-tests must pass before release
- [ ] Manual testing with real repositories
- [ ] Verify both API and web scraping methods work
- [ ] Test skip detection mode functionality

## Future Improvements

### Potential Enhancements

1. **JavaScript Rendering**: Handle JS-rendered content
2. **Advanced Parsing**: Extract more metadata (stars, forks, etc.)
3. **Caching Optimization**: Smarter cache invalidation
4. **Parallel Processing**: Concurrent page fetching with rate limiting
5. **Fallback Strategies**: Multiple data sources for reliability
6. **Automated Regression Testing**: CI/CD integration with self-tests
7. **Performance Monitoring**: Real-time performance metrics and alerting
