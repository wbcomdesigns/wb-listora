---
journey: quick-wins-2026-10
plugin: wb-listora
priority: high
roles: [anonymous, admin]
covers: [categories-hide-empty, demo-dates-spread, wizard-quiet-and-honest]
prerequisites:
  - "Site reachable at $SITE_URL with demo content imported"
estimated_runtime_minutes: 10
---

# Usability quick wins: empty categories, demo dates, quiet wizard

Found by the 2026-10-10 frontend UX audit on the rebuilt model site.

## Steps

### 1. Empty categories (guest, 1440)
- **Action**: open a page with `<!-- wp:listora/listing-categories /-->` (no saved hideEmpty); open "View all".
- **Expect**: no tile reads "0 listings"; the "View all N categories" count matches the tiles; no type group made only of empty categories.
- **Action**: `add_filter( 'wb_listora_categories_hide_empty', '__return_false' );` in a mu-plugin; reload.
- **Expect**: empty categories show again. Remove the mu-plugin.

### 2. Demo dates (admin, scratch site)
- **Action**: run a demo import (`wp listora demo seed --pack=place --skip-images`).
- **Expect**: listing publish dates spread across the last 60 days; New badge (Pro, 7-day rule) on a minority of cards; `/listings/?listora_sort=newest` differs from Featured order; no review `created_at` earlier than its listing's `post_date_gmt`.

### 3. Wizard (admin, 1440)
- **Action**: open `admin.php?page=listora-setup&rerun=1&step=type`, then `step=demo`, then `step=done`.
- **Expect**: 0 visible admin notices on each step; "Skip setup" on type and demo, not on done; the All packs card does not say "recommended for QA" or "all 9 types" and says Events has no demo pack.
- **On fail**: `blocks/listing-categories/render.php`, `demo/class-demo-seeder.php` (`seed_listing`, `seed_review`), `includes/admin/class-setup-wizard.php` (`quiet_wizard_notices`, done-step skip link, demo pack copy).

## Pass criteria

ALL of the following hold:
1. No empty category tile by default; filter restores them.
2. Demo listings carry spread dates and reviews never predate their listing.
3. The wizard shows no foreign notices and no Skip on Done.
