---
journey: dashboard-tab-feature-off
plugin: wb-listora
priority: normal
roles: [author]
covers: [dashboard-tab-fallback]
prerequisites:
  - "WB Listora Pro active; a member account with listings"
estimated_runtime_minutes: 5
---

# A dashboard link to a feature that is off opens Overview

Card 10393043370.

## Steps

### 1. Feature off
- **Action**: with Reverse Listings, Analytics and Advanced Search off, open the dashboard as a member with `?tab=needs`, `?tab=analytics`, `?tab=saved-searches`.
- **Expect**: Overview is shown with the note "There is no "<tab>" section in your dashboard. Showing Overview instead."; no empty panel.

### 2. Feature on
- **Action**: switch Reverse Listings and Advanced Search on; open `?tab=needs` and `?tab=saved-searches`.
- **Expect**: each opens its own panel; browser title "My Needs" / "Saved Searches"; no note. Restore the toggles.
- **On fail**: `blocks/user-dashboard/render.php` (known tabs = `wb_listora_get_dashboard_tab_labels()`), Pro `Pro_Plugin::register_dashboard_tab_labels()`.

## Pass criteria

1. Only tabs that exist can be opened by URL.
