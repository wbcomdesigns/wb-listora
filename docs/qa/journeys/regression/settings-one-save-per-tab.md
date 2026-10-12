---
journey: settings-one-save-per-tab
plugin: wb-listora
priority: critical
roles: [admin, editor]
covers: [settings, credits, gateways, needs, notifications, email-templates, features, card-10337174947]
prerequisites:
  - "WB Listora + Pro active; back up wb_listora_settings, the SDK gateway option (name comes from the Credits SDK for slug wb-listora; not saved on this site yet), wb_listora_email_templates, wb_listora_pro_need_response_cost, wb_listora_features"
estimated_runtime_minutes: 10
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark]
changed: true
---

# Every settings tab has one form and one Save, and it saves everything on the tab

Card 10337174947 (owner decision 2026-09-25: one form, one save per tab). Components: `Settings_Page::render()` (one `options.php` form per tab; save notices from `get_settings_errors()`, `includes/admin/class-settings-page.php:679`; sticky **Save Changes** `:765`), Pro's gateway + Needs sections rendered inside the form on `wb_listora_settings_tab_content` and saved on `admin_init` (`wb-listora-pro/includes/class-pro-plugin.php:691,747-756`), `Payment_Settings::gateway_keys_look_valid()` (`wb-listora-pro/includes/admin/class-payment-settings.php:404`), `Email_Templates_Page::save()` (`includes/admin/class-email-templates-page.php:544`) and the unsaved-changes guard (`assets/js/admin/settings-page.js:741`).

Verified via: curl of the rendered tabs as admin (Save Changes count, labels, `options.php` forms) and source reads. Saving was NOT performed (this site's live settings); the save/refusal steps are the contract to run with the backups taken.

## Steps

### 1. Credits: one Save stores every section
- **Action**: Listora > Settings > Credits. Change **Listing submission cost** (`#listora_default_listing_credit_cost`), the Stripe **Post-purchase redirect** (`gateway_stripe[success_url]`) and Needs marketplace **Credits per response**; press **Save Changes** once.
- **Expect**: one "Settings saved."; reload shows all three; the gateway option holds the redirect. The only other button on the tab is **+ Add Mapping** (a list action, observed). Observed on this build: the rendered settings page has no "Save Gateway Settings", "Save Needs settings", "Save Email Templates" or "Save Features" button; every tab form ends in **Save Changes**.

### 2. A malformed Stripe key is refused, loudly
- **Action**: paste a secret key (`sk_test_...`) into **Publishable key (active mode)**, change Needs cost too, Save.
- **Expect**: no "Settings saved"; error "Stripe settings were not saved: check the fields marked below." and under the field "A Stripe publishable key starts with pk_live_ or pk_test_."; Stripe settings unchanged; the Needs change IS saved; secrets stay masked.

### 3. Notifications: toggle + template in one save
- **Action**: untick an event, edit its template subject, Save once.
- **Expect**: both stored (`wb_listora_settings[notifications]`, `wb_listora_email_templates`); no separate "Save Email Templates" button.

### 4. Features
- **Action**: toggle a feature; the tab button reads **Save Changes**; stored in `wb_listora_features`.

### 5. Leaving with unsaved edits warns
- Edit a field, navigate away: the browser's "Leave site?" prompt; saving clears it.

### 6. No tab regresses
- Submit each tab unchanged: General, Maps, Submissions, Reviews, Credits, Notifications, Advanced, Features, Integrations, Import/Export, plus Pro's SEO, Visibility, White Label and License (observed tab slugs: advanced, credits, features, general, import-export, integrations, license, maps, notifications, reviews, seo, submissions, visibility, white-label). There is no standalone "Pagination" tab in this build; "Pagination style" is a field inside another tab. Each saves, no errors, stored values unchanged.

### 7. Role row
- qa_editor: `admin.php?page=listora-settings` -> "Sorry, you are not allowed to access this page."; admin is the only actor that can save (control: confirm a non-admin POST to `options.php` for `wb_listora_settings` is refused).

### 8. Restore
Put the backed-up options back.

## Fail diagnostics
- Stripe values lost on Save Changes: gateway fields outside the `options.php` form (they must render on `wb_listora_settings_tab_content`, `class-pro-plugin.php:691`).
- "Settings saved" beside a refused Stripe key: notice reading `?settings-updated` instead of `get_settings_errors()` (`class-settings-page.php:679`).
- Needs change lost on refusal: save order in `class-pro-plugin.php:747-756`.

## Not verified
Steps 1-6 saves (live settings not mutated); the SDK option name for gateway settings.
