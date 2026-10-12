---
journey: dashboard-tabs-follow-features
group: dashboard-account
actor: customer
mode: mixed
plugin: wb-listora
priority: high
roles: [owner, other, editor, admin, guest]
covers: [dashboard-tab-fallback, feature-toggle, dashboard-tabs, saved-searches-tab, analytics-tab, my-needs-tab, my-responses-tab, deep-link-off, unknown-tab-notice, bc-10393043370]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Steps 1-2 and 4-8 need WB Listora Pro active with Reverse Listings, Analytics and Advanced Search ON; step 3 and steps 9-10 are Free-only"
  - "{owner} has a listing, a saved search and a need or response so each Pro tab would have content"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unbridged theme]
site_profile: required
requires: [wb-listora-pro (Pro tab steps only)]
supersedes:
  - ora:regression/dashboard-tab-feature-off.md
  - pro:customer/2.0-dashboard-tabs-follow-features.md
  - ora:customer/11-dashboard-overview-tab.md (step 8, unknown tab)
  - ora:customer/15-dashboard-notifications-tab.md (step 1, ?tab=notifications)
---

# A dashboard tab opens only while its feature is on; anything else lands on Overview with a note

## Why
2.0.0 registers each tab only while its feature is switched on. A link to an off or unknown tab must never render an empty panel or a JS error, and switching one feature must not remove another feature's tab.

| Tab (nav id) | Label | Feature key |
|---|---|---|
| `#dash-tab-saved-searches` | Saved Searches | Pro `advanced_search` |
| `#dash-tab-analytics` | Analytics | Pro `analytics` |
| `#dash-tab-needs` | My Needs | Pro `reverse_listings` |
| `#dash-tab-my-responses` | My Responses | Pro `reverse_listings` |
| `#dash-tab-favorites` | Favorites | Free `favorites` |

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Save state first: `wp option get wb_listora_pro_features --format=json > $SCRATCH/features.json` and `wp option get wb_listora_features --format=json > $SCRATCH/free-features.json`.
- Logins: `?autologin={owner}`, `{other}`, `{editor}`, `{admin}`. Dashboard `$DASH` = `wp eval 'echo wb_listora_get_dashboard_url();'`; tabs are `?tab=<id>`. Toggles live at `$SITE_URL/wp-admin/admin.php?page=listora-settings&tab=features`.

## Steps

### 1. POSITIVE CONTROL: all features on
- **Action**: as {owner} open `$DASH/`, then `?tab=needs`, `?tab=analytics`, `?tab=saved-searches`.
- **Expect**: nav items `#dash-tab-saved-searches`, `#dash-tab-analytics`, `#dash-tab-needs`, `#dash-tab-my-responses`, each `role="tab"` with `aria-controls="dash-panel-<id>"`; panels exist with matching `aria-labelledby`; `<title>` reads "My Needs", "Analytics", "Saved Searches"; no `.listora-dashboard__notice`.
- **Read-back**: `curl` the page with the cookie and grep `dash-panel-` ids; they equal the nav ids.

### 2. One Pro feature off at a time (isolation)
- **Action**: for each in turn, switch OFF, reload `$DASH`, switch back ON:
  - `advanced_search` OFF: only Saved Searches disappears (also no "Save This Search" button on the Directory page).
  - `analytics` OFF: only Analytics disappears.
  - `reverse_listings` OFF: BOTH My Needs and My Responses disappear; Listings, Reviews, Favorites, Credits untouched.
- **Expect**: the missing tab has no nav element AND no `#dash-panel-*` in the HTML; sidebar has 8 tabs instead of 12 when all three are off.
- **Read-back**: grep the served HTML for the id; switching back ON restores the tab with its data (saved search listed, needs rows shown) without re-saving anything else.

### 3. Deep link to an off Pro tab
- **Action**: with all three off, open `?tab=needs`, `?tab=my-responses`, `?tab=analytics`, `?tab=saved-searches`.
- **Expect**: HTTP 200, no PHP notice, no JS error; `<title>` is the page's own title; `.listora-dashboard__notice[role="status"]` reads `There is no "<tab>" section in your dashboard. Showing Overview instead.`; active tab is `aria-controls="dash-panel-overview"`; no empty panel.
- **Read-back**: `wp-content/debug.log` delta has no notice.

### 4. Free feature off: Overview, no note
- **Action**: set Free feature `favorites` off (`wb_listora_features`), open `?tab=favorites` as {other}.
- **Expect**: Overview active, Favorites absent from the sidebar, `<title>` is the page's own. NO "Showing Overview instead" note (the id is still registered; only `wb_listora_dashboard_tab_available()` says no). Restore the toggle.
- **Read-back**: `wp eval 'var_dump( wb_listora_dashboard_tab_available( "favorites" ) );'` is false while off.

### 5. Unknown tab id
- **Action**: open `?tab=nope` and `?tab=notifications` (there is no Notifications tab; only a REST feed, see `dashboard-notifications-feed`).
- **Expect**: Overview opens with the same `There is no "<tab>" section in your dashboard. Showing Overview instead.` note; no `#dash-tab-notifications`.
- **Read-back / positive control**: `?tab=profile` opens Profile with no note, so the note is specific to unavailable ids.

### 6. REST follows the same switch
- **Action**: with `reverse_listings` OFF, `GET /wp-json/listora/v1/dashboard/needs` and `/dashboard/responses` as {owner}.
- **Expect**: expected-fail: feature-off routes should answer 404/403 but today only the pages are gated (owner decision pending, see report question). Until decided, assert only that rows are the caller's own, never another member's; with the feature ON the same.
- **Read-back**: response ids all have `author` = {owner}.

### 7. Admin screens follow the same switch
- **Action**: as {admin} with `reverse_listings` OFF, open Moderation > Needs.
- **Expect**: the dependent menu item is absent; restored when ON.
- **Read-back**: Not verified live.

### 8. Phone
- **Action**: 390px with all four on, then two off.
- **Expect**: nav scrolls or wraps with no horizontal page scroll, each tab target >= 40px; with two off the rest reflow with no gap or dead tab. Note layout at 390 and dark mode.
- **Read-back**: `scrollWidth <= innerWidth`. Not verified live.

### 9. Restore
- **Action**: write `$SCRATCH/features.json` and `free-features.json` back.
- **Expect**: step 1 titles return.
- **Read-back**: `wp option get wb_listora_pro_features --format=json` equals the saved file.

## Role ladder
| Rung | Expect |
|---|---|
| {owner} | steps 1-5 |
| {other}, {editor} | same Overview fallback in step 3; never see {owner}'s needs or searches |
| {guest} | `.listora-dashboard__login-prompt` ("Please log in to view your dashboard." with a Log In button) for any `?tab=`; REST 401 |
| {admin} | control for step 1 and step 7 |

## Pass criteria
1. Only tabs whose feature is on can be opened by `?tab=`; both Needs tabs share one switch.
2. Switching one feature never removes another feature's tab.
3. Off and unknown ids land on Overview quietly; Pro-off and unknown ids show the note, Free-feature-off does not.
4. With the features back on the tabs open under their own titles.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Off tab still opens an empty panel | `$known_tabs` / availability gate | `blocks/user-dashboard/render.php:61`, `:84` |
| Needs tabs remain after OFF | not gated on `reverse_listings` | `wb-listora-pro/includes/class-pro-plugin.php:936`, `includes/class-feature-manager.php:74` |
| Analytics tab remains | gate | `wb-listora-pro/includes/features/class-analytics.php:54` |
| Saved Searches tab remains | `advanced_search` gate | `wb-listora-pro/includes/features/class-advanced-search.php:569` |
| Deep link errors / label map | registration | `wb-listora-pro/includes/class-pro-plugin.php:926` |
| Free-feature-off tab silently Overview | by design, helper | `includes/class-template-helpers.php:400` |
| Unknown tab renders blank | notice branch | `blocks/user-dashboard/render.php:646` |

## Not verified
390px layout and dark mode of the note; step 7; REST decision in step 6. The original journeys flipped features by option on a shared site without a browser pane.

## Cleanup
Step 9 restores both feature options.
