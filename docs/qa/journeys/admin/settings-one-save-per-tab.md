---
journey: settings-one-save-per-tab
plugin: wb-listora
priority: critical
roles: [admin, editor, guest]
covers: [settings, credits, gateways, needs, notifications, email-templates, features, card-10337174947]
prerequisites:
  - "Site reachable at $SITE_URL; WP-CLI against $WP_PATH"
  - "WB Listora + Pro active (Credits, Needs, SEO, Visibility, White Label, License tabs are Pro); Free-only runs the Free tabs"
  - "Back up wb_listora_settings, the Credits SDK gateway option for slug wb-listora, wb_listora_email_templates, wb_listora_pro_need_response_cost, wb_listora_features"
estimated_runtime_minutes: 12
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark]
site_profile: required
requires: [wb-listora-pro (Credits, Needs, Pro tabs)]
supersedes:
  - ora:regression/settings-one-save-per-tab.md
---

# Every settings tab has one form and one Save, and it saves everything on the tab

## Why
Owner decision 2026-09-25 (card 10337174947): one form, one save per tab. Components: `Settings_Page::render()` (one `options.php` form per tab; save notices from `get_settings_errors()`, `includes/admin/class-settings-page.php:679`; sticky **Save Changes** `:765`), Pro's gateway + Needs sections rendered inside the form on `wb_listora_settings_tab_content` and saved on `admin_init` (`wb-listora-pro/includes/class-pro-plugin.php:691,747-756`), `Payment_Settings::gateway_keys_look_valid()` (`wb-listora-pro/includes/admin/class-payment-settings.php:404`), `Email_Templates_Page::save()` (`includes/admin/class-email-templates-page.php:544`) and the unsaved-changes guard (`assets/js/admin/settings-page.js:741`). Per-tab independence, validation and reset are owned by `08-settings-merge`. A second Save button silently dropped half a tab.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Take the backups listed in prerequisites. Saving mutates live settings, so run on a scratch site or restore at step 9.

## Steps

### 1. Credits: one Save stores every section
- **Action**: Listora > Settings > Credits. Change **Listing submission cost** (`#listora_default_listing_credit_cost`), the Stripe **Post-purchase redirect** (`gateway_stripe[success_url]`) and Needs marketplace **Credits per response**; press **Save Changes** once.
- **Expect**: one "Settings saved."; reload shows all three; the only other button is **+ Add Mapping** (a list action). No "Save Gateway Settings", "Save Needs settings", "Save Email Templates" or "Save Features" button on any tab; every tab form ends in **Save Changes**.
- **Read-back**: `wp option get` of each backed-up option shows the new values; positive control: a different value fails the compare.

### 2. A malformed Stripe key is refused, loudly
- **Action**: paste a secret key (`sk_test_...`) into **Publishable key (active mode)**, change Needs cost too, Save.
- **Expect**: no "Settings saved"; error "Stripe settings were not saved: check the fields marked below." and under the field "A Stripe publishable key starts with pk_live_ or pk_test_."; Stripe settings unchanged; the Needs change IS saved; secrets stay masked.
- **Read-back**: gateway option unchanged, Needs option changed.

### 3. Notifications: toggle + template in one save
- **Action**: untick an event, edit its template subject, Save once.
- **Expect**: both stored; no separate "Save Email Templates" button.
- **Read-back**: `wb_listora_settings[notifications]` and `wb_listora_email_templates`.

### 4. Features
- **Action**: toggle a feature; the button reads **Save Changes**.
- **Expect**: stored in `wb_listora_features`.
- **Read-back**: `wp option get wb_listora_features --format=json`.

### 5. Leaving with unsaved edits warns
- **Action**: edit a field, navigate away; then save.
- **Expect**: the browser's "Leave site?" prompt; saving clears it.
- **Read-back**: `beforeunload` fires only when dirty.

### 6. No tab regresses
- **Action**: submit each tab unchanged: General, Maps, Submissions, Reviews, Credits, Notifications, Advanced, Features, Integrations, Import/Export, plus Pro's SEO, Visibility, White Label and License (list tab slugs from the live sidebar: advanced, credits, features, general, import-export, integrations, license, maps, notifications, reviews, seo, submissions, visibility, white-label). There is no standalone "Pagination" tab; "Pagination style" is a field inside another tab.
- **Expect**: each saves, no errors, stored values unchanged.
- **Read-back**: `wp option get wb_listora_settings --format=json` identical before/after (positive control: change one value and see the diff).

### 7. Boundary input and double submit
- **Action**: put markup, whitespace, an out-of-range number and a 10,000-char string into a text field; double-click Save; submit from two tabs.
- **Expect**: values sanitised or refused with a field error; one effective save; last write wins.
- **Read-back**: stored values match the sanitiser. Not verified live.

### 8. Role ladder and 390
- **Action**: as {editor} open `admin.php?page=listora-settings`; POST to `options.php` for `wb_listora_settings` as {editor}; view a tab at 390.
- **Expect**: "Sorry, you are not allowed to access this page."; the POST is refused; at 390 the sticky Save stays reachable, no horizontal scroll.
- **Read-back**: option unchanged.

### 9. Restore
- **Action**: put the backed-up options back.
- **Expect**: step 6 values return.
- **Read-back**: option compares.

## Role ladder
| Rung | Expect |
|---|---|
| {admin} | only actor that can save (steps 1-7) |
| {editor} | 403 on the page and on `options.php` (step 8) |
| {guest} | redirect to login |

## Pass criteria
1. Every tab has one form and one Save Changes; no auxiliary save buttons.
2. Credits, Needs, Notifications and Features values all store from one click.
3. A refused Stripe key blocks only its own section, with a visible error.

## Fail diagnostics
| Symptom | Where |
|---|---|
| Stripe values lost on Save Changes | gateway fields outside the `options.php` form (must render on `wb_listora_settings_tab_content`, `class-pro-plugin.php:691`) |
| "Settings saved" beside a refused Stripe key | notice reads `?settings-updated` instead of `get_settings_errors()` (`class-settings-page.php:679`) |
| Needs change lost on refusal | save order `class-pro-plugin.php:747-756` |

## Not verified
Steps 1-6 saves were not performed against the shared site (live settings); the SDK option name for gateway settings is unconfirmed; steps 7-8.

## Cleanup
Step 9.
