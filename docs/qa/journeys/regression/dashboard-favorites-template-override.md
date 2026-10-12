---
journey: dashboard-favorites-template-override
plugin: wb-listora
priority: normal
roles: [member]
covers: ["#9977212895", "favorites tab template override", "WooCommerce-style template contract"]
prerequisites:
  - "A logged-in user with at least 1 favorite"
estimated_runtime_minutes: 2
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Favorites dashboard tab loads from an overridable template

Every dashboard tab (Listings, Reviews, Claims, Credits, Leads, Profile) loads a dedicated template via `wb_listora_get_template()`, and so does Favorites: `templates/blocks/user-dashboard/tab-favorites.php`, called from `blocks/user-dashboard/render.php:1081`. A theme override at `{theme}/wb-listora/blocks/user-dashboard/tab-favorites.php` must win, and the template fires the `wb_listora_before_dashboard_favorites` / `wb_listora_after_dashboard_favorites` hook pair like every other tab (the block's `$attributes` must not be clobbered mid-render).

Roles: the member (actor); a second member sees only their favourites; logged out gets the login prompt; admin is the control.

## Setup

- A logged-in member with at least 1 favourite (QA site: `qa_vendor_01` has 1) and another with none (empty state).
- Dashboard `$SITE_URL/my-listings/?tab=favorites` (`wb_listora_get_dashboard_url()`; QA site `/my-listings/`).
- Remove any override afterwards.

## Steps

### 1. Default template renders
- **Action**: as the member open the Favorites tab (`#dash-tab-favorites`, label "Favorites").
- **Expect**: `#dash-panel-favorites` (`role=tabpanel`, `aria-labelledby="dash-tab-favorites"`) contains `.listora-dashboard__favorites-grid.listora-grid` with the favourite card(s). For a member with none it shows the shared empty state (`.listora-empty`): title "No saved listings", "Save listings you like by clicking the heart icon.", and a **Browse the directory** button to the Directory. Content comes from `templates/blocks/user-dashboard/tab-favorites.php`.

### 2. Theme override is honoured
- **Action**: copy a marker file to `{active-theme}/wb-listora/blocks/user-dashboard/tab-favorites.php` containing `<div id="qa-j1-override-marker">override</div>`; reload the tab.
- **Expect**: the marker renders in place of the default panel and the default grid is absent. Remove the file; the default panel returns.

### 3. Hooks fire
- **Action**: via an mu-plugin or `wp eval`, `add_action( 'wb_listora_before_dashboard_favorites', function ( $d ) { error_log( 'fav-before ' . json_encode( array_keys( $d ) ) ); } );` and the `after_` twin.
- **Expect**: both fire once per render and receive `$view_data` containing `user_id` and `favorite_ids`.

### 4. Positive control and role ladder
- **Control**: step 2's marker proves the override path is live; with the file absent step 1 passes, so the pair detects a hard-coded inline panel (the original defect: the override was silently ignored).
- **Another member**: their tab lists only their own favourites. **Logged out**: login prompt, no panel.
- **Add and remove** a favourite from the Directory (heart), reload: the Favorites tab and the badge follow (verified via REST add/remove: badge 1 -> 2 -> 1).
- **Big site**: favourites paginate on the shared pagination nav (`favorites_page`, `wb_listora_render_pagination()`); a member with 20+ favourites shows "Page 1 of N".

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Override ignored | panel inline again | `blocks/user-dashboard/render.php:1081` (`wb_listora_get_template( 'blocks/user-dashboard/tab-favorites.php' ...`) |
| Hooks missing | removed from template | `templates/blocks/user-dashboard/tab-favorites.php:20`, `:78` |
| `$view_data` lacks keys | view data array changed | `blocks/user-dashboard/render.php:1074-1076` |

## Not verified

The template call, hooks and view-data keys were read in source; the badge round-trip (REST favourite add/remove as `qa_vendor_01`) ran live. The theme override file (step 2), the hook listener (step 3) and the browser rendering of the empty state were NOT run.
