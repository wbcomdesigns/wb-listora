---
journey: dashboard-tab-feature-off
plugin: wb-listora
priority: normal
roles: [author, subscriber, anonymous, admin]
covers: [dashboard-tab-fallback, feature-toggle, dashboard-tabs]
prerequisites:
  - "WB Listora Pro active; a member account with listings (qa_vendor_01) and subscriber3"
  - "Pro features Reverse Listings, Analytics and Advanced Search currently ON (read wb_listora_pro_features first so you can restore it)"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# A dashboard link to a feature that is off opens Overview

Card 10393043370. Verified via: curl against the live site as admin and subscriber3 plus `wp eval` feature flips (no browser pane was available; see Not verified).

Dashboard page: `/my-listings/` (page id 7). Tab ids and labels come from `wb_listora_get_dashboard_tab_labels()`; Pro adds `saved-searches` ("Saved Searches"), `analytics` ("Analytics"), `needs` ("My Needs"), `my-responses` ("My Responses") only while their feature is on.

## Setup

- Save the option: `wp option get wb_listora_pro_features --format=json > /tmp-or-scratch/features.json`.
- Logins: `?autologin=varundubey` (admin), `?autologin=subscriber3` (member), `?autologin=qa_vendor_01` (owner).

## Steps

### 1. POSITIVE CONTROL: features on
- **Action**: as a member open `/my-listings/?tab=needs`, `?tab=analytics`, `?tab=saved-searches`.
- **Expect**: each `<title>` reads "My Needs", "Analytics", "Saved Searches" (followed by the site name); the sidebar (`role="tablist"`) holds a tab with `data-listora-tab-label` of the same text; no `.listora-dashboard__notice`.

### 2. Pro tabs off
- **Action**: set `reverse_listings`, `analytics`, `advanced_search` to false in `wb_listora_pro_features` (Listora > Settings > Features), reload each URL from step 1 plus `?tab=my-responses`.
- **Expect**: `<title>` is the page's own title ("My Listings"); `.listora-dashboard__notice[role="status"]` reads `There is no "<tab>" section in your dashboard. Showing Overview instead.`; the active tab is `aria-controls="dash-panel-overview"`; sidebar has 8 tabs instead of 12 (no `data-listora-tab-label` "My Needs"/"My Responses"/"Saved Searches"/"Analytics"); no empty panel.

### 3. Free feature off (no note)
- **Action**: set Free feature `favorites` off (`wb_listora_features`), open `?tab=favorites` as subscriber3.
- **Expect**: Overview is the active tab, Favorites is absent from the sidebar, `<title>` is "My Listings". No "Showing Overview instead" note appears here (the tab id is still registered; only `wb_listora_dashboard_tab_available()` says no). Restore the toggle.

### 4. Role ladder
- Logged out: `/my-listings/?tab=needs` shows `.listora-dashboard__login-prompt` ("Please log in to view your dashboard." with a Log In button), never a panel. Owner (qa_vendor_01), another member (subscriber3), editor (`qa_editor`) all get the same Overview fallback in step 2. Admin is only the control for step 1.

### 5. Restore
- Write the saved option back; re-run step 1 and see the three titles again.

## Pass criteria

1. Only tabs whose feature is on can be opened by `?tab=`.
2. Off tabs land on Overview with no empty panel; unknown or Pro-off ids show the note.
3. With the features back on, the three tabs open under their own titles.

## Fail diagnostics

| Symptom | Where |
|---|---|
| Off tab still opens an empty panel | `blocks/user-dashboard/render.php:61` (`$known_tabs`), `render.php:84` (`$show_favorites && wb_listora_dashboard_tab_available()`) |
| Pro tab still in sidebar when off | `wb-listora-pro/includes/class-pro-plugin.php:926` `register_dashboard_tab_labels()` |
| Title says "My Listings" for an on tab | `includes/class-plugin.php:1052` `filter_dashboard_document_title()` |
| Free-feature-off tab silently Overview | `includes/class-template-helpers.php:400` `wb_listora_dashboard_tab_available()` |

## Not verified

390px layout of the note; theme dark mode. The shared Playwright pane was in use by another run.
