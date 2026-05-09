---
journey: dashboard-2-col-layout
plugin: wb-listora
priority: high
roles: [subscriber]
covers: [user-dashboard-grid, listora-dashboard-css, page-shell-not-overriding]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "tester user (subscriber) exists"
  - "Viewport ≥1280px during walk"
estimated_runtime_minutes: 2
---

# Dashboard 2-column layout regression sentinel

The dashboard at desktop must render as a 2-column grid (260px sidebar + main). Today's regression: applying `.listora-page--dashboard` shell with `flex-direction: column` collapsed `.listora-dashboard`'s grid to single column. Fix: reverted the canonical shell on dashboard until inner sections migrate.

## Setup

- Site: `$SITE_URL`
- User: `tester` (subscriber)

## Steps

### 1. Open dashboard at desktop viewport
- **Action**: `playwright_resize 1280 800`, then `playwright_navigate $SITE_URL/dashboard/?autologin=tester`
- **Expect**: page renders, no console errors

### 2. Verify computed grid layout
- **Action**: `browser_evaluate "JSON.stringify({ display: getComputedStyle(document.querySelector('.listora-dashboard')).display, gridTemplateColumns: getComputedStyle(document.querySelector('.listora-dashboard')).gridTemplateColumns })"`
- **Expect**:
  - `display === 'grid'`
  - `gridTemplateColumns` starts with `260px ` (sidebar width)
- **On fail**: regression of today's dashboard fix. Check `blocks/user-dashboard/render.php` — must NOT carry `.listora-page--dashboard` class until inner sections migrate. The outer wrapper should keep `.listora-dashboard` only.

### 3. Verify sidebar + main present in DOM
- **Action**: `browser_evaluate "[document.querySelector('.listora-dashboard__sidebar') !== null, document.querySelector('.listora-dashboard__main') !== null]"`
- **Expect**: `[true, true]`

### 4. Verify mobile breakpoint
- **Action**: `playwright_resize 390 844`, refresh
- **Expect**: `display === 'grid'` still, but `gridTemplateColumns` collapses to single column (sidebar stacks above main). No horizontal scrollbar.

### 5. Verify tab navigation works
- **Action**: click each sidebar tab (My Listings → Reviews → Favorites → Profile)
- **Expect**: URL hash updates, main content updates without page reload

## Pass criteria

1. Desktop: `.listora-dashboard` is a CSS grid with `260px` first column
2. Mobile: layout stacks (single column)
3. Sidebar + main both render
4. Tab nav functions

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Sidebar collapses below main at desktop | `.listora-page--dashboard` flex-direction:column applied to outer | `blocks/user-dashboard/render.php` — remove canonical shell class |
| `display !== 'grid'` | `.listora-dashboard` CSS missing or overridden | `blocks/user-dashboard/style.css`, theme override |
| No tabs render | dashboard data REST returns empty | `Dashboard_Controller::get_data` |
