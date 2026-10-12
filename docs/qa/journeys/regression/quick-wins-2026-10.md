---
journey: quick-wins-2026-10
plugin: wb-listora
priority: high
roles: [anonymous, admin]
covers: [categories-hide-empty, demo-dates-spread, wizard-quiet-and-honest]
prerequisites:
  - "Site reachable at $SITE_URL with demo content imported"
estimated_runtime_minutes: 10
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Usability quick wins: empty categories, demo dates, quiet wizard

Found by the 2026-10-10 frontend UX audit. Role ladder: anonymous reads the categories block; admin runs the wizard and import
(scratch site). Control for "hide empty": the filter turns the empties back on.

## Steps

### 1. Empty categories (guest, 1440 and 390)
- **Action**: open a page with `<!-- wp:listora/listing-categories /-->` (this site: the home page `$SITE_URL/`), no saved hideEmpty.
- **Expect** (verified): no tile reads "0 listings"; the toggle reads "View all 41 categories" (`.listora-categories__toggle-more`, with
  "Show fewer" `__toggle-less`), the count equals the non-empty terms (41 of 120), groups (`.listora-categories__group-title`) are per
  listing type and none consists only of empty categories; each unpinned tile names its type (`.listora-categories__type`).
- **Phone (390)**: two columns, no horizontal scroll, "View all" spaced from the next block.
- **Positive control** (verified in-process): `add_filter( 'wb_listora_categories_hide_empty', '__return_false' )` -> the toggle reads
  "View all 120 categories" and 79 "0 listings" tiles appear. Remove the filter afterwards.

### 2. Demo dates (admin, scratch site; NOT RUN)
- **Action**: `wp listora demo seed --pack=place --skip-images` (or Settings > Re-run Demo Import).
- **Expect**: publish dates spread across the last 60 days; the New badge (Pro, 7-day rule) on a minority of cards;
  `$SITE_URL/listings/?listora_sort=newest` differs from the default order; no review `created_at` earlier than its listing's `post_date_gmt`.

### 3. Wizard (admin, 1440; NOT RUN live)
- **Action**: open `admin.php?page=listora-setup&rerun=1&step=type`, then `step=demo`, then `step=done`.
- **Expect**: 0 visible admin notices on each step (`quiet_wizard_notices()` strips `admin_notices`/`all_admin_notices`, only the demo-import
  error notice is re-added); **Skip setup** link (`.listora-wizard__skip a`) on type and demo, NOT on done; the demo step's
  **All packs** card reads "100+ listings across all 10 listing types, with photos, reviews and services." and the **Events** pack
  exists ("8 upcoming concerts, conferences and workshops, dated from the day you import"). Old text "recommended for QA" / "all 9
  types" / "Events has no demo pack" is gone.

## Pass criteria
1. No empty category tile by default; the filter restores them. 2. Demo listings carry spread dates and reviews never predate their
listing. 3. The wizard shows no foreign notices and no Skip on Done.

## Fail diagnostics
`blocks/listing-categories/render.php:31` (filter) and `:34-45` (query), `demo/class-demo-seeder.php` (`seed_listing`, `seed_review`),
`includes/admin/class-setup-wizard.php:65` / `:76` (`quiet_wizard_notices`), `:487` (skip link, hidden on done), demo packs from `:860`.

## Not verified
Steps 2 and 3 were not run: a demo import mutates the shared 2,800-listing site and `rerun=1` opens a per-user wizard session.
