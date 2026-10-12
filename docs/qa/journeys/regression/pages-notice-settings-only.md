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
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
changed: true
---

# The unlinked-pages notice appears in exactly one place

Both notices match ONE exact screen id, `listora_page_listora-settings` (Free
`Menu_Prompt::SETTINGS_SCREEN_ID`, `includes/admin/class-menu-prompt.php:33`; Pro
`Feature_Pages_Notice::SETTINGS_SCREEN_ID`, `wb-listora-pro/includes/admin/class-feature-pages-notice.php:31`).
Previously a `listora` substring matched every listing and taxonomy screen.

Role ladder: administrator only (the notice needs `edit_theme_options`; Pro's `manage_listora_settings`). Control: a
user without the cap sees no notice anywhere (system/admin-chrome journey, no end-user surface).

## Steps

### 1. Content screens are quiet
- **Action**: with an unlinked Listora page present, open each: `edit.php?post_type=listora_listing`,
  `post.php?post=<id>&action=edit`, `edit-tags.php?taxonomy=listora_listing_cat&post_type=listora_listing`, `..._location`,
  `..._feature`, `listora_service_cat`.
- **Expect**: none contains "These pages exist but nothing on your site links to them." (Free) or
  "Some Pro features have nowhere to send people yet." (Pro). Checked on this site: 0 occurrences on all six.

### 2. The Listora landing page is quiet
- **Expect**: `admin.php?page=listora` has neither notice (0 occurrences checked).

### 3. Settings shows it (positive control)
- **Action**: open `admin.php?page=listora-settings`.
- **Expect**: the Free notice renders with its Add to menu / Create action and a **No thanks** link. Checked: 1 occurrence.
  Pro's notice renders here only while a Pro feature page is missing and not dismissed (on this site all four exist, so 0).
  To get the Pro positive control, delete Post a Need's page on a scratch site first.
- **This is the step that catches an over-tight fix.**

### 4. Free and Pro agree
- **Verify**: `Menu_Prompt::SETTINGS_SCREEN_ID === Feature_Pages_Notice::SETTINGS_SCREEN_ID === 'listora_page_listora-settings'`.

### 5. Dismissal is per user
- **Action**: press **No thanks** (Free: user meta `wb_listora_menu_prompt_dismissed`; Pro: `wb_listora_pro_pages_notice_dismissed`), reload.
- **Expect**: stays dismissed for that user; another administrator still sees it.

## Automated coverage
Free `tests/integration/MenuPromptScreenScopeTest.php`, Pro `tests/integration/FeaturePagesNoticeScopeTest.php`.

## Not verified
Step 5 (dismiss click) and the Pro positive control were not exercised on the shared site.
