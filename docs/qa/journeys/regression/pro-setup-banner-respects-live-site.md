---
slug: pro-setup-banner-respects-live-site
priority: high
covers:
  - BC 10208509984
likely_files:
  - wb-listora-pro/includes/admin/class-setup-wizard.php
  - includes/class-template-helpers.php
---

# The Pro welcome banner leaves a working directory alone

`wb_listora_pro_setup_complete` records that the WIZARD was walked. Its
absence was read as "this site is not set up", which is a different claim —
owners configure by hand, restore backups, and clone to staging, and none of
those set it. So a directory with a hundred live listings was greeted with
"Finish the quick setup" on Dashboard, Reviews, Claims and Settings.

Three separate defects, all fixed: the completeness test, the dismissal, and
the dismiss affordance.

## Steps

1. On a site with published listings and configured submission + dashboard
   pages, delete the option: `wp option delete wb_listora_pro_setup_complete`.
2. Load **Listora → Reviews**.
   - **Expect:** no welcome banner. `wp option get wb_listora_pro_setup_complete`
     now returns 1 — the check self-healed and does not re-run every request.
   - **Fail if:** the "Welcome to Listora Pro" banner renders.
3. Fresh-install case — clear the option AND unset the submission page:
   - **Expect:** the banner DOES render on Dashboard / Plugins / the Listora
     top-level page. A site that genuinely needs guidance must still get it.
   - **Expect:** it does NOT render on Reviews, Claims, or Settings.
4. Click **Dismiss** on the banner, then reload.
   - **Expect:** gone permanently, for this user only.
   - **Fail if:** it returns after a week (it was a one-week transient), or a
     second admin also loses it (it was site-wide).
5. Confirm there is no native `is-dismissible` X on the banner.
   - **Fail if:** an X is present — WP's X hides client-side only, so clicking
     the obvious control and having the banner return is the nag itself.
