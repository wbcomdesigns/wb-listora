---
journey: dashboard-title-follows-tab
plugin: wb-listora
priority: normal
roles: [member, anonymous, admin]
covers:
  - BC 10208510032
  - dashboard-title
likely_files:
  - includes/class-plugin.php
  - includes/class-template-helpers.php
  - templates/blocks/user-dashboard/nav.php
  - src/blocks/user-dashboard/view.js
  - ../wb-listora-pro/includes/class-pro-plugin.php
prerequisites:
  - "Pro active, features Analytics and Advanced Search on"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# The dashboard titles itself by its active tab

The member dashboard is one page ("My Listings"), so before the fix every tab announced itself as My Listings in the browser tab, history and to screen readers. The title must be server-side because tabs navigate (`?tab=...`).

Verified via: curl, `<title>` of each URL as admin, 2026-10-12. The in-page click path (step 5) was not run in a browser.

## Steps

1. Load `/my-listings/` with no `tab`.
   - **Expect**: `<title>My Listings – Listora</title>` (page's own title).
2. Fresh page loads (not in-page clicks) of `?tab=credits`, `profile`, `reviews`, `claims`, `favorites`, `listings`.
   - **Expect** titles: "Credits", "Profile", "Reviews", "My Claims" (not "Claims"), "Favorites", "My Listings", each followed by ` – <site name>`.
3. Pro: `?tab=saved-searches`, `?tab=analytics`, `?tab=needs`.
   - **Expect**: "Saved Searches", "Analytics", "My Needs". **POSITIVE CONTROL**: turn the feature off (`wb_listora_pro_features`) and the same URL keeps the page title and shows the "Showing Overview instead" note (see dashboard-tab-feature-off).
   - **Fail if** "My Listings": Pro must register through `wb_listora_dashboard_tab_labels` on the always-run path, not an admin-only init.
4. `?tab=nonsense`.
   - **Expect**: the page's own title; the notice `There is no "nonsense" section in your dashboard. Showing Overview instead.`; Overview active.
5. Click through the sidebar without reloading (browser).
   - **Expect**: `document.title` updates and carries no count badge: "Credits", never "Credits 48.00". The label is read from `data-listora-tab-label`.
6. Sidebar still renders every label: count of `[data-listora-tab-label]` is 12 with Pro features on (Overview, My Listings, Reviews, My Claims, Leads, Analytics, Favorites, My Needs, My Responses, Saved Searches, Credits, Profile).
7. Role ladder: member, editor and admin see the same titles; logged out sees `.listora-dashboard__login-prompt`; the title still follows `?tab=` (verified: "My Needs").

## Fail diagnostics

| Symptom | File |
|---|---|
| Title ignores `?tab=` | `includes/class-plugin.php:369` hook, `:1052` `filter_dashboard_document_title()` |
| Label map empty | `includes/class-template-helpers.php:425` `wb_listora_get_dashboard_tab_labels()` |
| Pro tab title wrong | `wb-listora-pro/includes/class-pro-plugin.php:926` |
| Count badge in JS title | `templates/blocks/user-dashboard/nav.php:52` `data-listora-tab-label`, `src/blocks/user-dashboard/view.js:96` |

## Not verified

Step 5 (JS title update) and 390px sidebar.
