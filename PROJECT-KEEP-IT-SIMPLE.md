# NHK Event Manager - Keep It Simple Checklist

This document serves as a practical checklist for the NHK Event Manager plugin to ensure we follow simple, WordPress-native patterns and avoid over-engineering.

## Current Project Context
- **Primary Goal**: Event management plugin that works reliably for WordPress users
- **Current Users**: Small to medium WordPress sites (10-1000 events)
- **Core Features**: Event listing, filtering, basic admin management
- **Technology Stack**: WordPress core + Alpine.js + XState (minimal) + Tailwind CSS


## Agreed Conventions (Project-Specific)
- Server-side rendering is the default for [nhk_events]; JavaScript is progressive enhancement only
- Opt-in attribute for enhancements: `ajax="true"`
- Minimal FSM responsibility: readiness/capabilities only; no data fetching or rendering
- Loader emits capability signals: `nhk:deps:checking`, `nhk:deps:ready`, `nhk:deps:degraded`, `nhk:api:available`, `nhk:api:unavailable`
- FSM states: `booting` → `checking` → `ready | degraded`
- Feature flags set by FSM: `window.nhkFeatures = { liveFilters, instantSearch, bulkActions }`

### Loader/FSM Contract (for implementers)
- Loader → dispatches DOM events listed above
- FSM → converts those into capability flags only
- Components → read `window.nhkFeatures` (and the shortcode `ajax` flag) to decide if live behavior should run

## Decision Checklist

### Before Adding Any New Feature/Dependency
- [ ] **WordPress Native First**: Can this be done with WP core features (shortcodes, templates, WP_Query, REST API)?
- [ ] **Evidence of Need**: Do we have actual user feedback or measurable pain points requiring this?
- [ ] **Dependency Audit**: Does this new dependency pay rent in real value vs. maintenance burden?
- [ ] **Graceful Degradation**: Will the feature still work with JavaScript disabled?
- [ ] **Simple First**: Have we tried the simplest possible solution that could work?

### FSM Usage Guidelines
**Use FSM for:**
- [ ] Application readiness states (boot → deps_loaded → ready/degraded)
- [ ] Complex multi-step admin workflows (import/export, bulk operations)
- [ ] State transitions that have business rules or validation

**Don't use FSM for:**
- [ ] Simple data fetching (use WordPress patterns: WP_Query, REST endpoints)
- [ ] Basic UI interactions (use Alpine.js reactive data)
- [ ] Rendering lists or forms (use WordPress templates/shortcodes)

### Rendering Strategy
**Server-Side First:**
- [ ] Initial event lists render via WP_Query in shortcodes/templates
- [ ] Pagination works with standard GET parameters
- [ ] Filtering works with query parameters (fallback)

**Progressive Enhancement:**
- [ ] JavaScript enhances the experience when available
- [ ] AJAX filtering/pagination when FSM reports "ready"
- [ ] Graceful fallback to full-page reloads when degraded

### Code Organization Principles
- [ ] **Single Responsibility**: Each class/component has one clear purpose
- [ ] **WordPress Conventions**: Follow WP coding standards and patterns
- [ ] **Minimal Dependencies**: Each dependency must justify its existence
- [ ] **Clear Separation**: FSM ≠ Data Layer ≠ Rendering Layer

## Current Architecture Decisions

### What We Keep Simple
1. **Event Listing**: Server-rendered shortcodes with progressive enhancement
2. **REST API**: Standard WordPress REST endpoints for AJAX enhancement
3. **Asset Loading**: WordPress wp_enqueue_scripts with proper dependencies
4. **Caching**: WordPress transients and object cache
5. **Admin Interface**: Standard WordPress admin pages and meta boxes

### What We Use FSM For
1. **Application Readiness**: Boot sequence and dependency validation
2. **Feature Flags**: Enable/disable enhancements based on capability
3. **Admin Workflows**: Multi-step processes like data import/export

### What We Avoid
1. **Custom Dependency Loaders**: Use wp_enqueue_script instead
2. **Deep Component Hierarchies**: Keep components flat and independent
3. **Premature Abstractions**: Build concrete first, abstract later
4. **Fighting WordPress**: Work with WP patterns, not against them

## Refactoring Guidelines

### Phase 1: Simplify Current Implementation
- [x] Move event listing to server-side rendering in shortcodes
  - Notes: [nhk_events] now SSRs via WP_Query with GET pagination/filters. JS is optional.
- [x] Reduce FSM to app readiness only (boot → ready/degraded)
  - Notes: Introduced appReadyMachine for readiness flags only; no data fetching/rendering.
- [x] Ensure all features work without JavaScript
  - Notes: Public pages render lists and navigate via GET; enhancements are opt-in via ajax="true".
- [ ] Extract shared query logic between REST and PHP rendering
  - Notes: Planned for Phase 3 to prevent drift between SSR and REST responses.

### Phase 2: Progressive Enhancement
- [x] Add JavaScript enhancements when FSM reports ready
  - Notes: Capability loader emits nhk:* signals; FSM sets window.nhkFeatures. Components can consult flags.
- [x] Implement graceful fallbacks for all AJAX features
  - Notes: SSR remains the source of truth; without ajax="true" or when degraded, components do not enhance.
- [x] Add feature flags for optional enhancements
  - Notes: window.nhkFeatures = { liveFilters, instantSearch, bulkActions } set by FSM; components read-only.

### Phase 3: Optimize and Clean
- [ ] Remove unused dependencies and code
- [ ] Audit and simplify state machines
- [ ] Document the simplified architecture

## Success Metrics
- [ ] **Reliability**: Features work consistently across different WP environments
- [ ] **Performance**: Fast initial page loads (server-rendered)
- [ ] **Maintainability**: New developers can understand and modify code quickly
- [ ] **Compatibility**: Works with common WordPress themes and plugins
- [ ] **Accessibility**: Functional without JavaScript, enhanced with it

## Red Flags to Watch For
- [ ] Adding dependencies without clear justification
- [ ] Building custom solutions for problems WordPress already solves
- [ ] FSM growing beyond readiness orchestration
- [ ] Features that only work with JavaScript enabled
- [ ] Complex dependency chains (A needs B needs C needs D)

## Review Questions for Each PR
1. Does this follow WordPress conventions?
2. Will it work without JavaScript?
3. Is the FSM staying focused on readiness/orchestration?
4. Are we adding complexity or removing it?
5. Can a new developer understand this in 5 minutes?

---

**Remember**: The most sophisticated solution is often the simplest one that could possibly work.
