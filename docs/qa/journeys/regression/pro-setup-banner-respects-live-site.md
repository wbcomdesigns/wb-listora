---
journey: pro-setup-banner-respects-live-site
plugin: wb-listora
priority: high
roles: [administrator, other-administrator]
covers:
  - BC 10208509984
likely_files:
  - wb-listora-pro/includes/admin/class-setup-wizard.php
  - includes/class-template-helpers.php
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
changed: true
---

# The Pro welcome banner leaves a working directory alone

`wb_listora_pro_setup_complete` records that the WIZARD was walked; its absence used to read as "not set up", greeting a directory
with a hundred live listings. Now `Setup_Wizard::is_complete()` (`wb-listora-pro/includes/admin/class-setup-wizard.php:129`) asks Free's
`wb_listora_directory_is_operational()` (`includes/class-template-helpers.php:906`) and persists the answer.

Banner copy (Pro `maybe_render_notice()`, `:152`): "**Welcome to WB Listora Pro** - Finish the quick setup to unlock licensing, credit packs,
plans, and Google Maps." Buttons **Start Setup** and **Dismiss** (a link; user meta `_listora_pro_wizard_dismissed`, no `is-dismissible` X). It
shows ONLY on screens `dashboard`, `plugins`, `toplevel_page_listora`, and only once Free's own setup is complete. Class
`.listora-pro-wizard-notice`. With White Label on the product name is the brand name.

Role ladder: administrator with `manage_listora_settings` sees it; another administrator is independent (dismissal is per user);
editors/members never see it.

## Steps

### 1. A live site self-heals (verified)
- **Action**: on a site with published listings and a submission + dashboard page, `wp option delete wb_listora_pro_setup_complete`, then
  load `admin.php?page=listora-reviews`, `admin.php?page=listora`, `index.php`, `plugins.php`.
- **Expect**: no `.listora-pro-wizard-notice` on any (checked); `wp option get wb_listora_pro_setup_complete` is `1` again (self-healed once).

### 2. Positive control: a site that really needs guidance
- **Setup (scratch site only)**: clear the option AND unmap the submission page so `wb_listora_directory_is_operational()` is false.
- **Expect**: the banner renders on Dashboard, Plugins and the Listora landing page, and does NOT render on Reviews, Claims or Settings.

### 3. Dismiss is permanent and per user
- **Action**: click **Dismiss** then reload; log in as a second administrator.
- **Expect**: gone for the first admin permanently (not a one-week transient); the second admin still sees it.

### 4. No native X
- **Expect**: the banner has no `is-dismissible` class / X button (the X hides client-side only and the banner returns).

## Fail diagnostics
`wb-listora-pro/includes/admin/class-setup-wizard.php:129` (completeness), `:152` (screen allowlist, dismissal), `:205` (dismiss handler);
`includes/class-template-helpers.php:906`.

## Not verified
Steps 2-4 need a scratch site (they require an unmapped submission page and a second admin); only step 1 ran on the shared site.
