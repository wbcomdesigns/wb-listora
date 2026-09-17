---
journey: privacy-policy-url-draft-state
plugin: wb-listora
priority: high
roles: [administrator]
covers: [settings, legal, app-config, card-10313405198]
prerequisites:
  - "A privacy policy page mapped in Settings > Privacy, and a page to use as terms"
estimated_runtime_minutes: 4
---

# Legal rows tell a draft page apart from no page

Settings > General > Legal & App Store read core's `get_privacy_policy_url()`, which is empty for any
non-published page, and printed "Not set" - right after WordPress' own Create flow saved the page as
a draft (card 10313405198). The terms dropdown listed only published pages, so a selected draft
showed "Select your terms page" and saving Settings reset the mapping to 0.

`wb_listora_get_page_publish_status()` returns published / unpublished / none. PHPUnit:
`tests/unit/PagePublishStatusTest.php`. The app (`/settings/app-config` `legal.*`) still gets an
empty URL for a draft - it has no public address.

## Steps

### 1. Draft privacy page
- **Action**: set the mapped privacy page to Draft; open Listora Settings > General
- **Expect**: "Page selected but not published." with the page title and a **Publish it** link to its editor - not "Not set"

### 2. No privacy page
- **Action**: Settings > Privacy - select none
- **Expect**: "Not set. The app will have no privacy policy…"

### 3. Published
- **Expect**: the URL fills the field; "Pulled from Settings > Privacy."

### 4. Draft terms page
- **Action**: select a terms page, save, set that page to Draft, reload
- **Expect**: the dropdown still shows it selected; "Page selected but not published." with **Publish it**; saving Settings keeps the mapping

## Pass criteria

1. A draft is never reported as "Not set".
2. Saving Settings never drops a selected draft terms page.
