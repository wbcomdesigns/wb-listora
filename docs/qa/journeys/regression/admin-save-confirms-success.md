---
journey: admin-save-confirms-success
plugin: wb-listora
priority: normal
roles: [administrator]
covers:
  - BC 10167580523
likely_files:
  - assets/js/admin/type-editor.js
  - assets/js/admin/settings-page.js
  - includes/admin/class-type-editor.php
  - includes/admin/class-settings-page.php
prerequisites:
  - "Site reachable at $SITE_URL; administrator"
  - "Before step 4 export the settings (Settings > Advanced > Export, or GET /listora/v1/settings/export) so the reset can be undone"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [wp-admin default]
changed: true
---

# A save that redirects still reports that it saved

Both admin writes toasted on success and then destroyed the toast: the new-type save redirects to the edit screen, and Reset reloads the page. A toast cannot survive either, so from the owner's side the write was silent, worst of all after a reset, which is destructive and irreversible. The confirmation now travels through the navigation as a URL flag and is rendered server-side on arrival.

2.0.0 note: Reset is per tab. The button is "Reset this tab" (`button[data-listora-action="reset-defaults"]`, one per settings tab), it asks for confirmation, and only that tab's keys go back to defaults (`DELETE /listora/v1/settings` with the tab's keys). The old site-wide "Reset Settings" button and the sentence "All settings were reset to their defaults." no longer exist.

## Steps

### 1. New type: confirmation survives the redirect
- **Action**: as administrator, Listora > Listing Types > Add New (`page=listora-listing-types&action=new`), fill a name, click "Save Type" (`#listora-save-type`).
- **Expect**: the browser lands on `...&edit=<slug>&listora_saved=type` AND `.notice.listora-notice.notice-success` reads "Listing type saved.". Flag verified server-side 2026-10-12 by loading that URL.
- **Fail if**: the edit screen loads with no confirmation of any kind.
- **Cleanup**: delete the QA-J1- type afterwards.

### 2. Edit an existing type (no redirect)
- **Action**: open an existing type, change a harmless label, "Save Type".
- **Expect**: a "Type saved successfully." toast appears and stays; the Save button text returns to "Save Type" (`assets/js/admin/type-editor.js:818`).

### 3. Failure is reported too (POSITIVE CONTROL for the error branch)
- **Action**: save a type with the REST write refused (e.g. empty name; the exact refusal was not exercised).
- **Expect**: an error toast carrying the server message, not "saved".

### 4. Reset a settings tab
- **Action**: Listora > Settings > Advanced (`page=listora-settings&tab=advanced`), click "Reset this tab"; the confirmation dialog title is "Reset <tab label>?" with "Everything on this tab goes back to its default. Your other tabs keep their settings. This cannot be undone." and a danger "Reset this tab" button; confirm.
- **Expect**: the page reloads at `...&listora_reset=1` and `.notice.listora-notice.notice-success` reads "This tab was reset to its defaults. Other tabs kept their settings." (flag verified live). Another tab's values (e.g. General) are unchanged: this is the assertion the notice makes.
- **Fail if**: the page reloads with defaults restored but says nothing, or the notice is shown while another tab also changed.
- **Cancel path**: choosing Cancel in the dialog changes nothing and shows no notice.

### 5. Role ladder
- **Administrator (control)**: steps 1-4.
- **Editor / subscriber**: cannot open Listing Types or Settings (403), so no save or reset is reachable; `DELETE /listora/v1/settings` and the types write routes refuse them.
- **Logged out**: 401 on both REST writes.

## Restore
Re-import the exported settings (Settings > Advanced > Import) or re-enter the tab's values; delete any QA-J1- type.

## Fail diagnostics
| Symptom | File |
|---|---|
| New type saves silently | `assets/js/admin/type-editor.js:820-828` (flag in redirect), `includes/admin/class-type-editor.php:158-168` (`render_saved_notice()`) |
| Reset silent | `assets/js/admin/settings-page.js:360-368`, `includes/admin/class-settings-page.php:702-709` |
| Reset button missing on a tab | `includes/admin/class-settings-page.php:761` |

Verified via: curl/DOM for both flags and 10 per-tab reset buttons on the Settings page; JS and PHP read in source. The dialog, the reload and the toasts were not click-tested (no Playwright), and nothing was reset on the live site.
