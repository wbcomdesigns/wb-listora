---
journey: settings-one-save-per-tab
plugin: wb-listora
priority: critical
roles: [admin]
covers: [settings, credits, gateways, needs, notifications, email-templates, features, card-10337174947]
prerequisites:
  - "WB Listora + Pro active; back up wb_listora_settings, wbcom_credits_gateway_settings_wb-listora, wb_listora_email_templates, wb_listora_pro_need_response_cost, wb_listora_features"
estimated_runtime_minutes: 10
---

# Every settings tab has one form and one Save, and it saves everything on the tab

Regression sentinel for `Settings_Page::render()` (one options.php form per tab, save notices from `get_settings_errors()`), Pro's credits sections on `wb_listora_settings_tab_content` (gateway + Needs inside the form, saved on `admin_init`), `Pro_Plugin::gateway_keys_look_valid()`, `Email_Templates_Page::save()` on `admin_init`, and the unsaved-changes guard in `assets/js/admin/settings-page.js`.

## Background

Card 10337174947 (owner decision 2026-09-25: one form and one save per settings tab). The Credits tab had separate forms for the gateways ("Save Gateway Settings") and Needs ("Save Needs settings"); Notifications had "Save Email Templates"; Features had "Save Features". Filling in Stripe and pressing the sticky "Save Changes" said "Settings saved." and stored nothing for Stripe.

## Steps

### 1. Credits: one Save stores every section
Settings > Credits. Change Listing submission cost, Stripe "Post-purchase redirect" and Needs "Credits per response". Press **Save Changes** once.
- **Expect**: one "Settings saved."; reload shows all three; `wp option get wbcom_credits_gateway_settings_wb-listora` has the redirect. The only other button on the tab is **+ Add Mapping** (a list action).

### 2. A malformed Stripe key is refused, loudly
Paste a secret key (`sk_test_...`) into "Publishable key" and change Needs cost too. Save.
- **Expect**: no "Settings saved"; an error "Stripe settings were not saved: check the fields marked below." and, under the field, "A Stripe publishable key starts with pk_live_ or pk_test_."; Stripe settings unchanged; the Needs change **is** saved. Secrets stay masked.

### 3. Notifications: toggle + template in one save
Untick an event and edit its template subject; Save once.
- **Expect**: both stored (`wb_listora_settings[notifications]`, `wb_listora_email_templates`); no "Save Email Templates" button.

### 4. Features
Toggle a feature; the tab's button reads **Save Changes**; it is stored.

### 5. Leaving with unsaved edits warns
Edit any field and navigate away -> the browser's "Leave site?" prompt. Saving clears it.

### 6. No tab regresses
Submit every tab's form unchanged (General, Maps, Submissions, Reviews, Credits, Pagination, SEO, Visibility, Notifications, Advanced, Features): each saves, no errors, stored values unchanged.

### 7. Restore
Put the backed-up options back.

## Fail diagnostics
- Stripe values lost on Save Changes -> the gateway fields are outside the options.php form again (rendered on `wb_listora_settings_tab_content_after_form`).
- "Settings saved" beside a refused Stripe key -> the notice is back to reading `?settings-updated` instead of `get_settings_errors()`.
