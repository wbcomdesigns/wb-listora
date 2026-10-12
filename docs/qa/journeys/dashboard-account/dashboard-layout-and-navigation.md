---
journey: dashboard-layout-and-navigation
group: dashboard-account
actor: customer
mode: browser
plugin: wb-listora
priority: high
roles: [owner, other, editor, admin, guest]
covers: [user-dashboard-grid, listora-dashboard-css, page-shell-not-overriding, tab-navigation, tab-switch-drops-params, bc-10351013467]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "{owner} exists (subscriber); desktop viewport >= 1280 for the grid steps"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unbridged theme]
site_profile: required
supersedes:
  - ora:regression/dashboard-2-col-layout.md
  - ora:customer/11-dashboard-overview-tab.md (step 11 only)
fails_on:
  - b2b4a4ea
---

# The member dashboard is a 260px rail plus a main column, and switching tabs is clean

## Why
A flex-column page shell once collapsed the dashboard grid to one column; a tab switch once kept the previous tab's `action`/`id` and showed an add/edit form under the wrong tab. Every member sees a broken dashboard.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Dashboard URL: `wp eval 'echo wb_listora_get_dashboard_url();'` (the page in option `wb_listora_dashboard_page_id`; the registry default `/my-dashboard/` may 404 on a site that re-slugged it).
- Log in with `?autologin={owner}`. Viewports 1440x900, 820x1180, 390x844.

## Steps

### 1. Open the dashboard at desktop width
- **Action**: resize 1440x900, open the dashboard page as {owner}.
- **Expect**: renders, no console errors.
- **Read-back**: n/a (no write).

### 2. Computed grid layout
- **Action**:
  ```js
  const d = document.querySelector('.listora-dashboard');
  ({ display: getComputedStyle(d).display, cols: getComputedStyle(d).gridTemplateColumns,
     shellClass: d.className, pageDashboard: !!document.querySelector('.listora-page--dashboard') })
  ```
- **Expect**: `display === 'grid'`; `cols` starts with `260px ` then one fluid column; `shellClass` is `listora-dashboard listora-block wp-block-listora-user-dashboard`; `pageDashboard` is false.
- **Read-back**: the same read logged out (`.listora-dashboard--logged-out`, `display: block`) is the positive control that `grid` is a property of the member shell.

### 3. Sidebar and main both present
- **Action**: query `nav.listora-dashboard__sidebar` and `.listora-dashboard__main`.
- **Expect**: `nav.listora-dashboard__sidebar[role=tablist][aria-label="Dashboard navigation"]` and `.listora-dashboard__main` exist. Rail items are `a.listora-dashboard__nav-item[role=tab]` with ids `dash-tab-<id>` (`overview`, `listings`, `reviews`, `claims`, `leads`, `analytics`, `favorites`, `needs`, `my-responses`, `saved-searches`, `credits`, `profile`; only those the member's features allow).
- **Read-back**: item count equals the tabs allowed by `wb_listora_dashboard_tab_available()` (see `dashboard-tabs-follow-features`).

### 4. Tablet and phone collapse
- **Action**: resize 820x1180, then 390x844, reload.
- **Expect**: `gridTemplateColumns` is a single column at both (rail stacks above main, `@media (max-width: 900px)`; not 768, an upright iPad at 820 must collapse), `display === 'grid'`, `document.documentElement.scrollWidth <= innerWidth`, each rail item >= 40px tall.
- **Read-back**: repeat in dark mode and on an unbridged theme; the grid values are identical.

### 5. Tab click is in-page, with a real link behind it
- **Action**: click My Listings, Reviews, Favorites, Profile in the rail.
- **Expect**: `?tab=` updates (`listings`, `reviews`, `favorites`, `profile`; `replaceState`, no reload); the matching `#dash-panel-<tab>` loses `hidden` and the others get it; the clicked item gets `is-active` and `aria-selected="true"`; a Ctrl/Cmd click opens the real `?tab=` link in a new tab. The old `#hash` navigation does not exist. The document title follows the tab (owned by `dashboard-title-follows-tab`).
- **Read-back**: `location.search` equals `?tab=<clicked>`; reload keeps that tab.

### 6. Switching tabs drops the previous tab's `action`, `id` and `listings_status` (bc-10351013467)
- **Action**: open `?tab=listings&action=add`; click each nav item in turn (Overview, Reviews, Favorites, Profile, Credits, Claims); repeat from `?tab=listings&action=edit&id=<{owner}'s listing>`.
- **Expect**: after each switch `[...new URL(location).searchParams.keys()]` equals `['tab']`; the visible panel is that tab's body, never an add/edit form.
- **Read-back**: reload keeps the same tab and body. Negative control: `?tab=favorites&action=edit&id=1` typed by hand still renders Favorites, not a form.
- **fails_on**: b2b4a4ea (parent of 649ab2a0). Not verified live.

### 7. Keyboard
- **Action**: Tab into the rail, arrow/Enter through items.
- **Expect**: every rail item is keyboard reachable and shows a visible focus ring; Enter activates the tab.
- **Read-back**: `document.activeElement` is the activated item. Not verified live.

## Role ladder
| Rung | Expect |
|---|---|
| {owner} | steps 1-7 |
| {other}, {editor} | identical grid at 1440 and 390 |
| {admin} (control) | identical grid; sees their own tabs, not site totals |
| {guest} | `.listora-dashboard--logged-out`, `display: block`, no rail, login prompt |

## Pass criteria
1. 1440: grid, first column 260px, `.listora-page--dashboard` absent.
2. 820 and 390: single column, no horizontal scroll, items >= 40px.
3. Tab clicks update `?tab=` without reload and leave only `tab` in the query.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Sidebar collapses below main at desktop | canonical page shell applied to the wrapper | `blocks/user-dashboard/render.php:465` |
| `display !== 'grid'` | CSS missing or overridden | `blocks/user-dashboard/style.css:5-12`, theme CSS |
| Single column at 1440 | breakpoint rule widened | `blocks/user-dashboard/style.css:1474-1481` |
| Tab click reloads or hash updated | handler regressed | `src/blocks/user-dashboard/view.js:24-80` (`switchDashTab`) |
| Add/edit form under another tab | params not dropped on switch | `src/blocks/user-dashboard/view.js` (`switchDashTab`) |

## Not verified
Computed styles (step 2), 820/390 collapse (step 4), click behaviour (step 5) and step 6 were not run in a browser; markup was read from served HTML.

## Cleanup
None (read-only).
