---
journey: empty-state-server-rendered
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [listing-grid-empty-state, iapi-show-empty-state-getter, server-render-hydration]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "An archive URL or filter combination known to return zero results (e.g. /business/ if no Business listings exist)"
estimated_runtime_minutes: 2
---

# 0-result archive empty state sentinel

The canonical `.listora-card--empty` empty state must be VISIBLE when results count = 0, both on first server-paint AND after a filter changes the count to 0. Today's bug: empty state was server-rendered, then immediately hidden by IAPI hydration because `state.showEmptyState` getter only returned true when `hasSearched=true`.

## Setup

- Site: `$SITE_URL`
- 0-result fixture: pick a category slug with zero published listings, e.g. delete Business listings or use a brand-new category with no posts.

## Steps

### 1. Navigate to a known 0-result archive
- **Action**: `playwright_navigate $SITE_URL/business/` (or whichever category has 0 listings)
- **Expect**:
  - DOM has `.listora-grid__empty.listora-card--empty` (visible)
  - Computed style: `display !== 'none'`, no `is-hidden` class

### 2. Verify visibility from JavaScript
- **Action**: `browser_evaluate "const el = document.querySelector('.listora-grid__empty'); JSON.stringify({ display: getComputedStyle(el).display, classes: el.className });"`
- **Expect**: `display !== 'none'` AND classes do NOT include `is-hidden`

### 3. Verify the IAPI getter returns true
- **Action**: `browser_evaluate "window.wp.interactivity.state['listora/directory'].showEmptyState"` (or whichever store namespace is canonical)
- **Expect**: `true`. Pre-fix: this returned `false` because the getter required `hasSearched=true`.
- **On fail**: regression of today's `showEmptyState` getter rewrite. Must return true on `state.totalResults === 0` regardless of `hasSearched`.

### 4. Verify empty card content
- **Action**: check DOM for icon + heading + CTA inside the empty card
- **Expect**: heading text "No listings found", CTA button "Clear All Filters"

### 5. Click "Clear All Filters" CTA
- **Action**: click the CTA
- **Expect**: URL strips category param, navigates to full listings list (which presumably has results), empty state disappears

### 6. Drain a fresh search to 0 (live filter)
- **Action**: navigate to `/listings/`, then type `xyzzy12345` in keyword
- **Expect**: result count drops to 0, empty card appears (in this case from filter, not server). `state.showEmptyState === true`.

## Pass criteria

1. Server-paint of 0-result archive shows visible empty state
2. IAPI hydration does NOT hide it
3. Empty card has icon + heading + Clear All Filters CTA
4. Live-filter to 0 also surfaces empty state

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Empty state hidden on server-paint | `data-wp-class--is-hidden` evaluating wrong | `templates/blocks/listing-grid/grid.php` binding |
| `state.showEmptyState` returns false on 0 results | regression — getter requires hasSearched | `src/interactivity/store.js` — getter must check `state.totalResults === 0` |
| `wb_interactivity_state` doesn't include hasSearched | server state init | `blocks/listing-grid/render.php` `wp_interactivity_state` call |
