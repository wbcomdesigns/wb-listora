---
journey: admin-settings-tabs-merge
plugin: wb-listora
priority: high
roles: [administrator, qa_editor]
covers: [settings-page, settings-tabs-merge, settings-reset-this-tab, near-me-radius-setting, distance-unit, expiry-note, bounded-numbers, get-setting-helper, 2.0.0-settings]
prerequisites:
  - "Site reachable at $SITE_URL; admin ?autologin=1"
  - "Snapshot: wp option get wb_listora_settings --format=json > /tmp/j20-settings-baseline.json (restore it at the end)"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default light]
---

# Settings tabs save independently, validate their numbers, and reset one tab at a time

Settings (`admin.php?page=listora-settings&tab=<id>`) is one screen with a left sidebar of grouped tabs: Directory (General `general`, Features `features`, Submissions `submissions`, Reviews `reviews`, Maps `maps`), Monetization (Credits `credits`, when Pro/credits present), Communication (Notifications `notifications`), Advanced (Integrations `integrations`, Import / Export `import-export`, Migration `migration`, Advanced `advanced`). All values live in the one `wb_listora_settings` option, but each tab saves only its own keys (Save Changes), and "Reset this tab" resets only that tab.

## Steps

### 1. General tab: what 2.0.0 shows (administrator, 1440)
- **Action**: `$SITE_URL/wp-admin/admin.php?page=listora-settings&tab=general&autologin=1`.
- **Expect**: shared header "Settings" ("How your directory works, grouped by task."), no hub tab row (single tab hub), the sidebar groups above; General holds: Basics (Listings per page, Listing URL slug, Currency "(Symbol shown alongside prices ...)", **Distance unit** radios "Kilometers (km)" / "Miles (mi)" ("Used for the distance shown on listing cards and search results."), **Near me radius** number "How far "Near me" searches at first, in the distance unit above. Visitors can change it next to the search bar."), Pages, and **Listing Lifecycle** (Listing expiration checkbox + the "Off by default ..." help, Default expiration, Renewal window, Renewal duration, Renewal cost).
- **On fail**: `Settings_Page::render_general_tab` (`includes/admin/class-settings-page.php` ~840-990).

### 2. Save one tab, then another, and check nothing leaked
- **Action**: General: Listings per page `12`, Near me radius `10`, Distance unit Miles; Save Changes. Then open Reviews tab: Minimum length `30`; Save Changes. Then Submissions: change one value; Save.
- **Expect**: after each save the notice "Settings saved." (class `listora-notice`, below the header); returning to General still shows 12 / 10 / Miles (the Reviews and Submissions saves did not zero or revert General booleans and numbers).
- **DB**: `wp option get wb_listora_settings --format=json | jq '{per_page,near_me_radius,distance_unit,reviews}'` shows all three changes.
- **On fail**: `Settings_Page::sanitize` (~150) must start from the stored option and overlay only submitted keys.

### 3. Numbers are bounded server-side
- **Action**: General: Near me radius `0` then `9999`; Listings per page empty; Save each.
- **Expect**: stored values are clamped/defaulted (`near_me_radius` between 1 and 500; an empty field falls back to its default), never 0 or 9999, even though the form posted them.
- **On fail**: `Settings_Page::bounded_int_ranges` (~124).

### 4. Expiry note (reads the paid add-on)
- **Expect**: with Pro (Pricing Plans) active the Listing expiration help is followed by "Expiration is on while Monetization is active, so paid listings run for their plan's duration and can be renewed." With Pro off no note. See `admin/2.0-expiry-off-on-free-directory.md`.

### 5. Reset this tab
- **Action**: Reviews tab > "Reset this tab" (`[data-listora-action=reset-defaults]`) > confirm in the `listoraConfirm` dialog (never a native `confirm()`).
- **Expect**: redirected back with the notice "This tab was reset to its defaults. Other tabs kept their settings."; Reviews values are defaults (Minimum length 20), General's 12 / 10 / Miles are untouched.
- **Combo (Pro)**: Pro's listener re-seeds its own defaults on `wb_listora_after_reset_settings`; Pro options for THAT tab are reset too, others untouched.
- **Under the hood**: `DELETE /listora/v1/settings` with the tab's `keys`; **On fail**: `Settings_Controller::reset_settings` (~738-801), `wb_listora_reset_option_keys` filter.

### 6. Helper reads the saved value
- **Action**: `wp eval 'echo wb_listora_get_setting("per_page"), " ", wb_listora_get_setting("near_me_radius"), " ", wb_listora_get_setting("distance_unit");'`
- **Expect**: `12 10 mi` (after step 2). Directory page 1 now shows 12 cards; card distances read "mi away".

### 7. Removed settings do not reappear (2.0.0 clean-up)
- **Expect**: the saved option has no `default_type`, `max_upload_size`, `facet_cache_ttl`, `enable_guest_submission`, `guest_email_verification`, or `reviews.require_login` keys after the update; and no field for them on any tab.

### 8. Restore
`wp option update wb_listora_settings "$(cat /tmp/j20-settings-baseline.json)" --format=json`.

### 9. Role ladder
| Rung | Action | Expect |
|---|---|---|
| administrator | steps 1-8 | pass |
| qa_editor (no `manage_listora_settings`) | settings URL | "Sorry, you are not allowed to access this page." |
| qa_editor | `PUT /listora/v1/settings` or `DELETE /listora/v1/settings` | 403 |
| anonymous | `GET /listora/v1/settings` | 401 (a logged-in non-admin gets 403) |

### 10. 390px
- **Expect**: the sidebar collapses above the form (or into a select), fields stack, "Save Changes" visible without horizontal scroll.

## Pass criteria

1. Every tab renders; saving one never changes another's values.
2. Out-of-range numbers are clamped on the server.
3. "Reset this tab" resets that tab only and says so.
4. Saved values are what `wb_listora_get_setting()` and the front end use.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Saving one tab clears another | `sanitize()` rebuilding from defaults | `class-settings-page.php::sanitize` |
| 0 stored for radius | bounds table missing the key | `bounded_int_ranges()` |
| Reset wipes every tab | reset keys not scoped to the tab | `class-settings-controller.php` reset route |
