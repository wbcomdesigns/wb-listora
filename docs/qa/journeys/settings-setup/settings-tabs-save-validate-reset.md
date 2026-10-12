---
journey: settings-tabs-save-validate-reset
group: settings-setup
actor: admin
mode: browser
plugin: wb-listora
priority: high
roles: [administrator, editor, anonymous]
covers: [settings-page, settings-tabs-merge, settings-reset-this-tab, near-me-radius-setting, distance-unit, expiry-note, bounded-numbers, get-setting-helper, 2.0.0-settings, settings-docs-links, store-docs-anchor-format, "#9919933465", page-ids-from-registry, removed-settings-gone]
prerequisites:
  - "Site reachable at $SITE_URL; {admin} session"
  - "Snapshot: wp option get wb_listora_settings --format=json > $SCRATCH/settings-baseline.json (restore at the end)"
estimated_runtime_minutes: 10
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default light]
supersedes:
  - ora:admin/08-settings-merge.md
  - ora:regression/settings-docs-links-live.md
fails_on: unknown (no regression steps carry a pre-fix sha)
site_profile: required
---

# Settings tabs save independently, validate their numbers, reset one tab at a time and link to the live docs

## Why
All values live in one `wb_listora_settings` option. If saving one tab rewrites another, an out-of-range number reaches the front end, a reset wipes more than its tab, or a Documentation button 404s, the owner loses configuration or trust. Settings (`admin.php?page=listora-settings&tab=<id>`) has grouped tabs: Directory (General, Features, Submissions, Reviews, Maps), Monetization (Credits), Communication (Notifications), Advanced (Integrations, Import / Export, Migration, Advanced).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Docs links: `Settings_Page::get_docs_url()` emits `https://store.wbcomdesigns.com/listora/docs/#<slug>-ls` (general -> general-settings, maps -> map-settings, submissions -> submission-settings, reviews -> reviews-settings, credits -> credits-and-plans, notifications -> notifications-settings, advanced -> advanced-settings; Pro pagination -> infinite-scroll, seo -> seo-pages, visibility -> coming-soon, white-label -> white-label); override filter `wb_listora_docs_url`.

## Steps

### 1. General tab content
- **Action**: `$SITE_URL/wp-admin/admin.php?page=listora-settings&tab=general` as {admin}.
- **Expect**: header "Settings" ("How your directory works, grouped by task."), no hub tab row, sidebar groups above; General holds Basics (Listings per page, Listing URL slug, Currency, **Distance unit** radios Kilometers (km) / Miles (mi), **Near me radius** number), Pages, and **Listing Lifecycle** (Listing expiration checkbox + "Off by default ..." help, Default expiration, Renewal window, Renewal duration, Renewal cost).

### 2. Save one tab, then another: nothing leaks
- **Action**: General: Listings per page `12`, Near me radius `10`, Distance unit Miles; Save Changes. Reviews tab: Minimum length `30`; Save. Submissions: change one value; Save.
- **Expect**: after each save "Settings saved." (`listora-notice`, below the header); General still shows 12 / 10 / Miles.
- **Read-back**: `wp option get wb_listora_settings --format=json | jq '{per_page,near_me_radius,distance_unit,reviews}'` shows all three changes. Positive control: change the Reviews value to a different number and re-read to show the read-back can fail.

### 3. Numbers are bounded server-side
- **Action**: Near me radius `0` then `9999`; Listings per page empty; Save each. Also negative, whitespace-only, markup (`<b>`), emoji and a non-number.
- **Expect**: stored values clamp/default (`near_me_radius` between 1 and 500, empty falls back to its default), never 0, 9999 or markup.

### 4. Expiry note reads the paid add-on
- **Expect**: with Pro (Pricing Plans) active the expiration help is followed by "Expiration is on while Monetization is active, so paid listings run for their plan's duration and can be renewed."; with Pro off no note. (Expiry behaviour itself: `plans-expiry-renewal/expiry-off-on-free-directory.md`.)

### 5. Reset this tab resets only that tab
- **Action**: Reviews tab > "Reset this tab" (`[data-listora-action=reset-defaults]`) > confirm in the `listoraConfirm` dialog (never a native `confirm()`).
- **Expect**: Reviews values are defaults (Minimum length 20); General's 12 / 10 / Miles untouched. Notice wording and cancel path: `settings-setup/save-and-reset-confirm-success.md` step 4.
- **Combo (Pro)**: Pro's listener re-seeds its own defaults on `wb_listora_after_reset_settings` for THAT tab only.
- **Under the hood**: `DELETE /listora/v1/settings` with the tab's `keys`; filter `wb_listora_reset_option_keys`.

### 6. The helper and the front end read the saved value
- **Action**: `wp eval 'echo wb_listora_get_setting("per_page"), " ", wb_listora_get_setting("near_me_radius"), " ", wb_listora_get_setting("distance_unit");'`
- **Expect**: `12 10 mi` (after step 2); directory page 1 shows 12 cards; distances read "mi away".

### 7. Removed settings do not reappear
- **Expect**: the saved option has no `default_type`, `max_upload_size`, `facet_cache_ttl`, `enable_guest_submission`, `guest_email_verification` or `reviews.require_login` key after the update, and no field for them on any tab.

### 8. Documentation buttons deep-link into the live docs
- **Action**: collect `document.querySelectorAll('.listora-docs-link')` hrefs on the rendered Settings page (all tabs are present).
- **Expect**: anchor form `https://store.wbcomdesigns.com/listora/docs/#<slug>-ls` per the Setup mapping (no `/docs/<section>/` path form), each `<a class="listora-docs-link" target="_blank" rel="noopener noreferrer">`; no link on tabs not in the map (Features, Integrations, Import/Export in this build).
- **Anchors exist**: in a browser open the docs page and confirm each `<slug>-ls` resolves to an element id (HTTP 200). Not verified (the store answered 403 to curl).
- **Filter override (positive control)**: `add_filter( 'wb_listora_docs_url', fn() => 'https://example.com/x' );` in a temporary mu-plugin: every `.listora-docs-link` points at the override; remove it.

### 9. Switching a mapped page updates links everywhere
- **Action**: Settings > Pages: switch the Dashboard page to another page; reload a member page.
- **Expect**: "Buy credits" links and the `listora-has-dashboard` body class follow the new page (`Page_Registry::get_id()` is the one source). Restore the mapping afterwards. Not verified live.

### 10. Restore
`wp option update wb_listora_settings "$(cat $SCRATCH/settings-baseline.json)" --format=json`.

### 11. 390px
- **Expect**: the sidebar collapses above the form (or into a select), fields stack, "Save Changes" is visible without horizontal scroll.

## Role ladder

| Rung | Action | Expect |
|---|---|---|
| {admin} | steps 1-11 | pass |
| {editor} (no `manage_listora_settings`) | settings URL | "Sorry, you are not allowed to access this page." (so no docs buttons exposed) |
| {editor} | `PUT` / `DELETE /listora/v1/settings` | 403 |
| {guest} | `GET /listora/v1/settings` | 401 (a logged-in non-admin gets 403) |

## Pass criteria
1. Every tab renders; saving one never changes another's values.
2. Out-of-range numbers are clamped on the server.
3. "Reset this tab" resets that tab only.
4. Saved values are what `wb_listora_get_setting()` and the front end use.
5. Every docs button uses the live anchor form; the filter overrides it.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Saving one tab clears another | `sanitize()` rebuilding from defaults | `includes/admin/class-settings-page.php` `sanitize` (must overlay submitted keys on the stored option) |
| 0 stored for radius | bounds table missing the key | `bounded_int_ranges()` |
| Reset wipes every tab | keys not scoped | `includes/rest/class-settings-controller.php` `reset_settings`, `wb_listora_reset_option_keys` |
| Docs link 404 | slug/anchor form | `Settings_Page::get_docs_url()` |
| Page mapping ignored | link reads an option, not the registry | `includes/core/class-page-registry.php` |

## Not verified
Steps 8 (live anchors), 9, 11 at 390 and the Pro combo half of step 5.

## Cleanup
Step 10; remove the docs-filter mu-plugin; restore the page mapping.
