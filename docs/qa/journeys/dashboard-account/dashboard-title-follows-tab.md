---
journey: dashboard-title-follows-tab
group: dashboard-account
actor: customer
mode: mixed
plugin: wb-listora
priority: normal
roles: [owner, editor, admin, guest]
covers: [BC 10208510032, dashboard-title, bc-10351487141, tab-heading-once]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Pro active with Analytics and Advanced Search ON (for the Pro tab titles); Free-only runs steps 1, 2, 5, 6, 7 and 8 for the Free tabs"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unbridged theme]
site_profile: required
supersedes:
  - ora:regression/dashboard-title-follows-tab.md
fails_on:
  - dbc0105
likely_files:
  - includes/class-plugin.php
  - includes/class-template-helpers.php
  - templates/blocks/user-dashboard/nav.php
  - src/blocks/user-dashboard/view.js
  - ../wb-listora-pro/includes/class-pro-plugin.php
---

# The dashboard titles itself by its active tab, with one heading per panel

## Why
The dashboard is one page, so before the fix every tab announced itself as "My Listings" in the browser tab, history and to screen readers. Titles must be server-side because tabs navigate with `?tab=`; panels must not repeat the tab name as a second heading.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Dashboard URL from `wp eval 'echo wb_listora_get_dashboard_url();'`; log in as {owner} (`?autologin={owner}`).
- The unknown-tab and feature-off fallbacks (note text, Overview active) are owned by `dashboard-tabs-follow-features`; this journey asserts only the title.

## Steps

### 1. No `tab`
- **Action**: load `$DASH/`.
- **Expect**: `<title>` is the page's own title followed by ` – <site name>`.
- **Read-back**: `curl -s $DASH/ | grep -o '<title>[^<]*'`.

### 2. Fresh loads of each Free tab
- **Action**: GET `?tab=credits`, `profile`, `reviews`, `claims`, `favorites`, `listings`.
- **Expect**: titles "Credits", "Profile", "Reviews", "My Claims" (not "Claims"), "Favorites", "My Listings", each followed by ` – <site name>`.
- **Read-back**: label equals `wb_listora_get_dashboard_tab_labels()[tab]`.

### 3. Pro tabs
- **Action**: GET `?tab=saved-searches`, `analytics`, `needs`.
- **Expect**: "Saved Searches", "Analytics", "My Needs".
- **Read-back / positive control**: turn the feature off in `wb_listora_pro_features`; the same URL keeps the page title (fallback owned by `dashboard-tabs-follow-features`). Fail if a Pro-on tab says "My Listings": Pro must register through `wb_listora_dashboard_tab_labels` on the always-run path, not an admin-only init.

### 4. Unknown tab id
- **Action**: GET `?tab=nonsense`.
- **Expect**: the page's own title (no invented label).
- **Read-back**: title text equals step 1.

### 5. In-page click path (browser)
- **Action**: click through the sidebar without reloading.
- **Expect**: `document.title` updates and carries no count badge ("Credits", never "Credits 48.00"). The label is read from `data-listora-tab-label`.
- **Read-back**: `document.title` after each click equals the server `<title>` of the same `?tab=` URL.

### 6. Sidebar labels
- **Action**: count `[data-listora-tab-label]`.
- **Expect**: 12 with every Pro feature on (Overview, My Listings, Reviews, My Claims, Leads, Analytics, Favorites, My Needs, My Responses, Saved Searches, Credits, Profile); 8 with the Pro tabs off.
- **Read-back**: count equals the number of tabs for which `wb_listora_dashboard_tab_available()` is true.

### 7. Logged out
- **Action**: GET `?tab=needs` with no session.
- **Expect**: `.listora-dashboard__login-prompt`; the title still follows `?tab=` ("My Needs").
- **Read-back**: no panel markup in the HTML.

### 8. One heading per tab, not two (card 10351487141)
- **Action**: Pro on; as {owner} open each of `?tab=listings`, `reviews`, `favorites`, `needs`, `responses`, `saved-searches`, `credits`, `profile` at 1440 and 390.
- **Expect**: inside `#dash-panel-<tab>` exactly one visible h1/h2/h3 whose text equals the tab's nav label.
- **Read-back**: `[...document.querySelectorAll('#dash-panel-<tab> :is(h1,h2,h3)')].filter(h => h.textContent.trim() === <label>).length === 1`; negative control: a tab with no data (empty My Responses) still shows 1, not 0 and not 2.
- **fails_on**: dbc0105 (Pro parent of 399066a). Not verified live.

### 9. Dark and 390
- **Action**: repeat steps 2 and 8 at 390 and in dark mode.
- **Expect**: identical titles and heading counts; sidebar still lists every label.
- **Read-back**: same queries. Not verified live.

## Role ladder
| Rung | Expect |
|---|---|
| {owner} | steps 1-9 |
| {editor}, {admin} | same titles (admin is the control) |
| {guest} | login prompt; title follows `?tab=` (step 7) |

## Pass criteria
1. Every tab id has its label as the server `<title>` and, after an in-page click, as `document.title` with no badge.
2. Exactly one heading per panel equals the tab label.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Title ignores `?tab=` | filter not hooked | `includes/class-plugin.php:369`, `:1052` `filter_dashboard_document_title()` |
| Label map empty | helper | `includes/class-template-helpers.php:425` `wb_listora_get_dashboard_tab_labels()` |
| Pro tab title wrong | Pro label registration | `wb-listora-pro/includes/class-pro-plugin.php:926` |
| Count badge in JS title | label read from text instead of data attribute | `templates/blocks/user-dashboard/nav.php:52`, `src/blocks/user-dashboard/view.js:96` |

## Not verified
Step 5 (JS title update), step 8 and 390 / dark.

## Cleanup
Restore `wb_listora_pro_features` if toggled in step 3.
