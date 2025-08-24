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

## Future Improvements

### Potential Enhancements

1. **JavaScript Rendering**: Handle JS-rendered content
2. **Advanced Parsing**: Extract more metadata (stars, forks, etc.)
3. **Caching Optimization**: Smarter cache invalidation
4. **Parallel Processing**: Concurrent page fetching with rate limiting
5. **Fallback Strategies**: Multiple data sources for reliability
