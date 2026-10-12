---
journey: privacy-policy-url-draft-state
plugin: wb-listora
priority: high
roles: [administrator]
covers: [settings, legal, app-config, card-10313405198]
prerequisites:
  - "A privacy policy page mapped in WP Settings > Privacy ({page-privacy}) and a page to use as terms ({page-terms}); both usable as draft and published"
  - "Back up the privacy page option and `wb_listora_settings[legal_terms_page_id]`"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default]
site_profile: required
supersedes: []
fails_on: []
---

# Legal rows tell a draft page apart from no page

## Why
Core's `get_privacy_policy_url()` is empty for a non-published page, so Settings printed "Not set" right after WordPress' own Create flow saved a draft; the owner then thinks nothing is mapped and the app gets no privacy link (App Store rejection risk).

Listora > Settings > General, section **Mobile app: legal links** (`includes/admin/class-advanced-settings-tab.php:80`). `wb_listora_get_page_publish_status()` (`includes/class-template-helpers.php:781`) returns `published` / `unpublished` / `none`. PHPUnit: `tests/unit/PagePublishStatusTest.php`. `GET /listora/v1/settings/app-config` `legal.*` returns an empty URL for a draft (no public address).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Settings page: `admin.php?page=listora-settings&tab=general`. Only a user with `manage_listora_settings` reaches it.

## Steps

### 1. Draft privacy page
- **Action**: set {page-privacy} to Draft; open the General tab; read `#legal_privacy_policy_url` (read-only).
- **Expect**: bold "Page selected but not published." then `"<title>" is not public yet, so the app has no privacy policy to link to.` and a **Publish it** link to the page editor. NOT "Not set."
- **Read-back**: `app-config` `legal.privacy_policy_url` is `""`.

### 2. No privacy page
- **Action**: WP Settings > Privacy: choose none.
- **Expect**: bold "Not set." then "The app will have no privacy policy - set one under Settings -> Privacy to avoid App Store rejection."

### 3. Published (positive control)
- **Action**: publish {page-privacy}.
- **Expect**: the URL fills the field and the line reads "Pulled from Settings -> Privacy. Change it there."; `app-config` returns the URL.

### 4. Draft terms page
- **Action**: pick {page-terms} in `#legal_terms_page_id`, save, set that page to Draft, reload.
- **Expect**: the dropdown still shows it selected (drafts listed: `post_status` publish/draft/pending/private); description "Page selected but not published." and "Members and the app get no terms link until it is public. **Publish it**"; saving Settings keeps the mapping (does not reset it to 0).
- **Read-back**: control: a published terms page shows no warning; `wb_listora_settings[legal_terms_page_id]` unchanged after save.

### 5. Other non-published states
- **Action**: set the page to Pending, Private and Trash; and map a page that was deleted.
- **Expect**: Pending/Private read "Page selected but not published."; a trashed or deleted page reads "Not set." (no stale id kept); no PHP notice. Not verified live (added in consolidation).

### 6. Role ladder
| Rung | Expect |
|---|---|
| {admin} | steps 1-5 |
| {editor} | Settings answers "Sorry, you are not allowed to access this page." |
| {guest} | login redirect |

### 7. Mobile (390)
- **Expect**: no horizontal page scroll; the dropdown and Publish it link are tappable (>= 40px).

## Pass criteria
1. A draft is never reported as "Not set".
2. Saving Settings never drops a selected draft terms page.

## Fail diagnostics
`includes/admin/class-advanced-settings-tab.php:99` (privacy draft line), `:109` (Not set), `:121` (Pulled from), `:152` / `:160` (terms), `includes/class-template-helpers.php:781`.

## Not verified
- Step 1 verified by DOM on a site whose privacy page was a draft; steps 2-5 were not toggled (shared site's mapping left untouched).

## Cleanup
Restore the privacy page option and terms mapping and the pages' statuses.
