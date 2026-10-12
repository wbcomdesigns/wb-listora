---
journey: privacy-policy-url-draft-state
plugin: wb-listora
priority: high
roles: [administrator]
covers: [settings, legal, app-config, card-10313405198]
prerequisites:
  - "A privacy policy page mapped in WP Settings > Privacy, and a page to use as terms"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
changed: true
---

# Legal rows tell a draft page apart from no page

Listora > Settings > General, section **Mobile app: legal links** (`includes/admin/class-advanced-settings-tab.php:80`), reads core's
`get_privacy_policy_url()`, which is empty for a non-published page, and used to print "Not set" right after WordPress' own Create
flow saved a draft (card 10313405198). `wb_listora_get_page_publish_status()`
(`includes/class-template-helpers.php:781`) returns `published` / `unpublished` / `none`. PHPUnit:
`tests/unit/PagePublishStatusTest.php`. The app (`GET /listora/v1/settings/app-config`, `legal.*`) still gets an empty URL for a draft
(no public address): `legal.privacy_policy_url` is `""` here.

Role ladder: administrator only (needs `manage_listora_settings`); control: the same row for a published page shows the URL.

## Steps

### 1. Draft privacy page (verified on this site: page 3 "Privacy Policy" is a draft)
- **Action**: `admin.php?page=listora-settings&tab=general`; the field `#legal_privacy_policy_url` is read-only.
- **Expect**: bold "Page selected but not published." then `"Privacy Policy" is not public yet, so the app has no privacy policy to link to.`
  and a **Publish it** link to the page editor. NOT "Not set."

### 2. No privacy page
- **Action**: WP Settings > Privacy: choose none.
- **Expect**: bold "Not set." then "The app will have no privacy policy - set one under **Settings -> Privacy** to avoid App Store rejection."

### 3. Published (positive control)
- **Expect**: the URL fills the field and the line reads "Pulled from Settings -> Privacy. Change it there."

### 4. Draft terms page
- **Action**: pick a terms page in `#legal_terms_page_id`, save, set that page to Draft, reload.
- **Expect**: the dropdown still shows it selected (drafts are listed: `post_status` publish/draft/pending/private); a description reads
  "Page selected but not published." and "Members and the app get no terms link until it is public. **Publish it**"; saving
  Settings keeps the mapping (does not reset it to 0).
- **Control**: terms page 6432 "Terms of Service" is published here and shows no warning.

### 5. Mobile (390)
- **Expect**: no horizontal page scroll, the dropdown and Publish it link are tappable.

## Pass criteria
1. A draft is never reported as "Not set". 2. Saving Settings never drops a selected draft terms page.

## Fail diagnostics
`includes/admin/class-advanced-settings-tab.php:99` (privacy draft line), `:109` (Not set), `:121` (Pulled from), `:152` / `:160` (terms),
`includes/class-template-helpers.php:781`.

## Not verified
Steps 2-4 were not toggled (the shared site's privacy page and terms mapping were left untouched); step 1 verified by DOM.
