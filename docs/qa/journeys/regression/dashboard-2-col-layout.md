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
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Dashboard 2-column layout regression sentinel

At desktop the member dashboard renders as a CSS grid: a 260px navigation rail (`.listora-dashboard__sidebar`) and the main column (`.listora-dashboard__main`). Applying a flex-column page shell (`.listora-page--dashboard`) once collapsed the grid to one column; the dashboard wrapper keeps only `.listora-dashboard` (see the note at `blocks/user-dashboard/render.php:465`). Below 900px (not 768: an upright iPad at 820 kept the rail and left ~500px for content) the grid becomes a single column.

Roles: any logged-in member sees the same layout (subscriber is the actor); logged out sees the login prompt in `.listora-dashboard--logged-out` (`display: block`, no rail); admin is the control.

## Setup

- Site `$SITE_URL`. The dashboard page is the page in option `wb_listora_dashboard_page_id`: `wp eval 'echo wb_listora_get_dashboard_url();'` (QA site: `/my-listings/`; `/my-dashboard/` is the registry default and 404s here, and `/dashboard/` is an unrelated page).
- A subscriber-level member (QA site: `qa_vendor_01`, or `tester`). Viewports: 1440, 820 and 390.

## Steps

### 1. Open the dashboard at desktop width
- **Action**: resize 1440x900, log in as the member (`?autologin=<login>`), open the dashboard page.
- **Expect**: renders, no console errors.

### 2. Computed grid layout
- **Action**: evaluate
  ```js
  const d = document.querySelector('.listora-dashboard');
  ({ display: getComputedStyle(d).display, cols: getComputedStyle(d).gridTemplateColumns,
     shellClass: d.className, pageDashboard: !!document.querySelector('.listora-page--dashboard') })
  ```
- **Expect**: `display === 'grid'`; `cols` starts with `260px ` (then one fluid column); `shellClass` is `listora-dashboard listora-block wp-block-listora-user-dashboard`; `pageDashboard` false (verified in the served HTML).
- **On fail**: `.listora-page--dashboard` re-applied (see `blocks/user-dashboard/render.php:465`), or the CSS at `blocks/user-dashboard/style.css:5-12` overridden by the theme.

### 3. Sidebar and main both present
- **Expect**: `nav.listora-dashboard__sidebar[role=tablist][aria-label="Dashboard navigation"]` and `.listora-dashboard__main` both exist. The rail's items are `a.listora-dashboard__nav-item[role=tab]` with ids `dash-tab-overview`, `dash-tab-listings`, `dash-tab-reviews`, `dash-tab-claims`, `dash-tab-leads`, `dash-tab-analytics`, `dash-tab-favorites`, `dash-tab-needs`, `dash-tab-my-responses`, `dash-tab-saved-searches`, `dash-tab-credits`, `dash-tab-profile` (only those the member's features allow).

### 4. Mobile and tablet breakpoints
- **Action**: resize 820x1180 then 390x844, reload.
- **Expect**: at 820 and 390 `gridTemplateColumns` is a single column (the rail stacks above main, `@media (max-width: 900px)`), `display === 'grid'` still, no horizontal page scroll (`document.documentElement.scrollWidth <= innerWidth`), each rail item >= 40px tall.

### 5. Tab navigation works
- **Action**: click My Listings -> Reviews -> Favorites -> Profile in the rail.
- **Expect**: the address bar `?tab=` updates (`listings`, `reviews`, `favorites`, `profile`; via `replaceState`, no reload), the matching `#dash-panel-<tab>` loses `hidden` and the others get it, the clicked item gets `is-active` + `aria-selected="true"`, and the document title follows the tab. A modified click (Ctrl/Cmd) opens the real `?tab=` link in a new tab. The old `#hash` navigation no longer exists.

### 6. Positive control and role ladder
- **Control**: the logged-out view (`.listora-dashboard--logged-out`, `display: block`, a login prompt) proves the grid is a property of the member shell: step 2's `grid` would be `block` there.
- **Another member / editor / admin**: identical grid at 1440; admin additionally sees the same tabs for their own account.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Sidebar collapses below main at desktop | canonical page shell applied to the outer wrapper | `blocks/user-dashboard/render.php:465` |
| `display !== 'grid'` | CSS missing or overridden | `blocks/user-dashboard/style.css:5-12`, theme CSS |
| Single column at 1440 | breakpoint rule widened | `blocks/user-dashboard/style.css:1474-1481` |
| Tab click reloads / hash updated | handler regressed | `src/blocks/user-dashboard/view.js:24-80` (`switchDashTab`) |

## Not verified

The wrapper class list, the rail markup (roles, ids, `href="?tab=..."`) and the dashboard URL were read from the served HTML as `qa_vendor_01`, and the CSS values from source. Computed styles (step 2), the 820 / 390 collapse (step 4) and the click behaviour (step 5) were NOT run in a browser.
