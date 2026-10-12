---
journey: save-and-reset-confirm-success
plugin: wb-listora
priority: normal
roles: [administrator, editor, anonymous]
covers: [BC-10167580523, type-editor-save-confirmation, settings-reset-confirmation]
prerequisites:
  - "Site reachable at $SITE_URL; {admin} session"
  - "Before step 4 export the settings (Settings > Advanced > Export, or GET /listora/v1/settings/export) so the reset can be undone"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default]
supersedes:
  - ora:regression/admin-save-confirms-success.md
fails_on: unknown (card BC-10167580523, pre-fix sha not recorded)
site_profile: required
---

# A save that redirects still tells the owner it saved

## Why
Both admin writes toasted on success and then destroyed the toast: the new-type save redirects to the edit screen and Reset reloads the page. From the owner's side the write was silent, worst after a destructive reset. The confirmation now travels as a URL flag and is rendered server-side on arrival.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Reset is per tab: button "Reset this tab" (`button[data-listora-action="reset-defaults"]`, one per settings tab), confirmation, then `DELETE /listora/v1/settings` with the tab's keys. The old site-wide Reset no longer exists.

## Steps

### 1. New type: confirmation survives the redirect
- **Action**: Listing Types > Add New (`page=listora-listing-types&action=new`), fill a name, "Save Type" (`#listora-save-type`).
- **Expect**: lands on `...&edit=<slug>&listora_saved=type` and `.notice.listora-notice.notice-success` reads "Listing type saved.". Fail if the edit screen loads with no confirmation.

### 2. Edit an existing type (no redirect)
- **Action**: change a harmless label, "Save Type".
- **Expect**: a "Type saved successfully." toast appears and stays; the button text returns to "Save Type".

### 3. Failure is reported too
- **Action**: save a type with the REST write refused (empty name).
- **Expect**: an error toast carrying the server message, not "saved".

### 4. Reset a settings tab
- **Action**: Settings > Advanced > "Reset this tab"; dialog title "Reset <tab label>?" with "Everything on this tab goes back to its default. Your other tabs keep their settings. This cannot be undone." and a danger "Reset this tab" button; confirm.
- **Expect**: reloads at `...&listora_reset=1` with `.notice.listora-notice.notice-success` "This tab was reset to its defaults. Other tabs kept their settings."; another tab's values (General) unchanged.
- **Read-back**: `wp option get wb_listora_settings --format=json` differs from the export only in the reset tab's keys. Fail if the page reloads with defaults and says nothing, or the notice shows while another tab also changed.
- **Cancel path**: Cancel changes nothing and shows no notice.

### 5. Notice is computed-visible at 390 (added)
- **Expect**: the step 1 and 4 notices have `getComputedStyle(el).display !== 'none'` and fit the viewport with no horizontal scroll. Not verified live.

## Role ladder

| Rung | Expect |
|---|---|
| {admin} | steps 1-5 |
| {editor} | Listing Types and Settings answer 403; `DELETE /listora/v1/settings` and the type write routes refuse |
| {guest} | 401 on both REST writes |

## Pass criteria
Every redirecting write ends with a visible server-rendered confirmation; failure shows an error; Cancel is silent and inert.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| New type saves silently | flag missing in redirect | `assets/js/admin/type-editor.js`, `includes/admin/class-type-editor.php` (`render_saved_notice()`) |
| Reset silent | flag/notice | `assets/js/admin/settings-page.js`, `includes/admin/class-settings-page.php` |
| Reset button missing on a tab | tab render | `includes/admin/class-settings-page.php` |

## Not verified
The dialog, reload and toasts were not click-tested (flags and ten per-tab buttons verified by curl/DOM); failure branch exact refusal not exercised.

## Cleanup
Re-import the exported settings (Settings > Advanced > Import) or re-enter the tab's values; delete any QA type.
