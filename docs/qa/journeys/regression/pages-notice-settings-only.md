---
journey: pages-notice-settings-only
plugin: wb-listora
priority: normal
roles: [administrator]
covers: [admin-notices, menu-prompt, pro-feature-pages-notice]
prerequisites:
  - "At least one Listora page exists but sits in no nav menu"
  - "Run once with Pro active and once without"
estimated_runtime_minutes: 4
covers_card: 10322574958
---

# The unlinked-pages notice appears in exactly one place

Regression sentinel for the notice screen scope.

## Background

Both guards tested for the substring `listora` in the screen id. That matches the Listora admin pages — and also `edit-listora_listing`, `listora_listing`, `edit-listora_listing_cat`, `edit-listora_listing_location`, `edit-listora_listing_feature` and `edit-listora_service_cat`. An owner working through their categories met the same "nothing links to these pages" notice on page after page, and on a Pro install it stacked with Pro's near-identical feature-pages notice.

Both now match one exact screen id: `listora_page_listora-settings`. That is where the Pages table and the Add to menu / Create actions live, so it is the one screen where the notice is something the owner can act on rather than an interruption.

## Steps

### 1. Content screens are quiet
- **Action**: with an unlinked Listora page present, open in turn: Listings list, a single listing editor, and each listing taxonomy screen (Categories, Locations, Features, Service Categories).
- **Expect**: **no** pages notice on any of them. Free's and, with Pro active, Pro's.

### 2. The Listora landing page is quiet
- **Expect**: no pages notice on `admin.php?page=listora` — the onboarding notice and setup wizard own that screen. This was already true and must stay true.

### 3. Settings still shows it
- **Action**: open Listora → Settings.
- **Expect**: the notice renders, with its Add to menu / Create action. **This is the step that catches an over-tight fix** — narrowing the guard until the notice never appears would "fix" the duplication by deleting the feature.

### 4. Free and Pro agree
- **Action**: with Pro active, confirm both notices appear on Settings and nowhere else.
- **Verify**: `Menu_Prompt::SETTINGS_SCREEN_ID === Feature_Pages_Notice::SETTINGS_SCREEN_ID`. They were duplicating each other; having narrowed both, they must not drift apart and reintroduce half the bug.

### 5. Dismissal still works
- **Action**: dismiss the notice on Settings, reload.
- **Expect**: it stays dismissed (per-user meta), unchanged by this fix.

## Automated coverage

Free `tests/integration/MenuPromptScreenScopeTest.php` (9 tests) and Pro `tests/integration/FeaturePagesNoticeScopeTest.php` (8 tests) walk every wrongly-matched screen id, and assert the two constants are equal.
