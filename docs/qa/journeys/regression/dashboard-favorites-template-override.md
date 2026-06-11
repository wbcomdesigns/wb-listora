---
journey: dashboard-favorites-template-override
plugin: wb-listora
priority: normal
roles: [member]
covers: ["#9977212895", "favorites tab template override", "WooCommerce-style template contract"]
prerequisites:
  - "A logged-in user with at least 1 favorite"
estimated_runtime_minutes: 2
---

# Favorites dashboard tab loads from an overridable template

Card #9977212895: every dashboard tab (Listings, Reviews, Claims, Credits,
Profile) loaded a dedicated template via `wb_listora_get_template()` — except
Favorites, which was hardcoded inline in `blocks/user-dashboard/render.php`.
Theme overrides at `{theme}/wb-listora/blocks/user-dashboard/tab-favorites.php`
were silently ignored. (The inline loop also clobbered the block's
`$attributes` variable mid-render.)

Now Favorites renders via
`templates/blocks/user-dashboard/tab-favorites.php` with the same
`wb_listora_before_dashboard_favorites` / `wb_listora_after_dashboard_favorites`
hook pair every other tab has.

## Steps

### 1. Default template renders
- **Action**: visit the dashboard page (`/my-listings/?autologin=1`), open the
  Favorites tab.
- **Expect**: `#dash-panel-favorites` contains the favorites grid (cards) or
  the `.listora-dashboard__empty` state — content comes from
  `templates/blocks/user-dashboard/tab-favorites.php`.

### 2. Theme override is honored
- **Action**: copy a marker file to
  `{active-theme}/wb-listora/blocks/user-dashboard/tab-favorites.php`
  (e.g. echoing `<div id="qa-m3-override-marker">`), reload the dashboard.
- **Expect**: the marker renders in place of the default panel. Remove the
  override file afterwards; default panel returns.

### 3. Hooks fire
- **Action**: `add_action( 'wb_listora_before_dashboard_favorites', ... )` and
  the `after_` twin via a mu-plugin or eval.
- **Expect**: both receive `$view_data` containing `user_id` + `favorite_ids`.
