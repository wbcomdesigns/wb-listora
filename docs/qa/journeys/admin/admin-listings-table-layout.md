---
journey: admin-listings-table-layout
plugin: wb-listora
priority: high
roles: [admin, editor]
covers: [listing-columns, default-hidden-columns, admin-css-column-widths]
prerequisites:
  - "Site reachable at $SITE_URL; WP-CLI against $WP_PATH"
  - "Free + Pro both active (Pro contributes the Moderator column); run Free-only for the Free columns"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default]
site_profile: required
supersedes:
  - ora:regression/admin-listings-table-layout.md (steps 1-5, 8-10)
---

# The listings table stays readable by default

## Why
The listings table once shipped with every column visible. WordPress renders it with `table-layout: fixed`, under which unsized columns get only the leftover; Title was unsized, collapsed to ~52px and wrapped one character per line into a ribbon about 1000px tall. The setup-notice dismissal that was reported with it is owned by `pages-review-notice-dismiss-persists`.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- `$LIST = $SITE_URL/wp-admin/edit.php?post_type=listora_listing`, as {admin} (`?autologin={admin}`). `ADMIN_ID` = {admin}'s user id.
- Current column inventory after the checkbox: `listora_thumb`, `title`, `listora_type`, `listora_location`, `listora_rating`, `listora_views`, `listora_featured`, `listora_renewals`, `listora_reports`, `listora_duplicate`, `author`, `taxonomy-listora_listing_type`, `taxonomy-listora_listing_cat`, `listora_moderator` (Pro), `date` = 15 plus the checkbox = 16 (no Comments column).
- Hidden by default (`Listing_Columns::default_hidden_columns()`, `includes/admin/class-listing-columns.php:200-235`): `listora_views` (Views lives in Analytics), `listora_renewals`, `listora_duplicate`, both taxonomy columns, `comments` if present, `listora_reports` unless any listing has an open report, plus Pro's `listora_moderator` through `wb_listora_default_hidden_columns`. Visible: checkbox, thumb, title, type, location, rating, featured, author, date.

## Steps

### 1. Default column set
- **Action**: `wp user meta delete ADMIN_ID manageedit-listora_listingcolumnshidden` (an empty saved array counts as a preference and suppresses the defaults), then load `$LIST` at 1440.
- **Expect**: visible `th`s are exactly checkbox, `column-listora_thumb`, `column-title`, `column-listora_type`, `column-listora_location`, `column-listora_rating`, `column-listora_featured`, `column-author`, `column-date`; the rest carry `.hidden` but stay listed under Screen Options (`input.hide-column-tog`). Title >= 240px; no row taller than ~80px.
- **Read-back**: positive control: with an open visitor report on any listing `listora_reports` is visible by default (`any_reported()`); with none it is hidden.

### 2. The check column is pinned
- **Action**: measure `.post-type-listora_listing .wp-list-table .check-column`.
- **Expect**: about 35px (`assets/css/admin.css`); unsized it absorbs every leftover percent.
- **Read-back**: `getBoundingClientRect().width`.

### 3. Worst case, every column on
- **Action**: `wp user meta update ADMIN_ID manageedit-listora_listingcolumnshidden '[]' --format=json`, reload.
- **Expect**: 16 columns; none narrower than 20px; no horizontal page scroll; rows under ~140px.
- **Read-back**: `[...document.querySelectorAll('.wp-list-table thead th')].map(th => th.offsetWidth)`.

### 4. No leak into core list tables
- **Action**: load `$SITE_URL/wp-admin/edit.php` (core Posts).
- **Expect**: Title keeps its stock width (~400px at 1440). Widths are scoped to `.post-type-listora_listing`, never `.wp-list-table.posts`.
- **Read-back**: Title `th` width compared with the same screen before the plugin's CSS (positive control: Listora's own table differs).

### 5. 390px
- **Action**: open `$LIST` at 390x844.
- **Expect**: core's stacked card layout; no sideways scroll. Do not reintroduce a `min-width` + `overflow-x` rule.
- **Read-back**: `scrollWidth <= innerWidth`.

### 6. Large table
- **Action**: on a site with 2,000+ listings (seed with the fixtures script if fewer): confirm WordPress pagination (20 per page), sort by Title and by Rating, and a `s=` search.
- **Expect**: each returns with the layout intact; totals match.
- **Read-back**: the "N items" count equals `wp post list --post_type=listora_listing --post_status=all --format=count`.

### 7. Role ladder and screen options
- **Action**: as {editor} load `$LIST`; save a column preference as {admin} and reload as {editor}.
- **Expect**: {editor} sees the same default columns (preferences are per user); {admin} is the control.
- **Read-back**: per-user meta key differs per user.

### 8. Restore
- **Action**: `wp user meta delete ADMIN_ID manageedit-listora_listingcolumnshidden`.
- **Expect**: back to the shipped defaults (step 1 passes again).
- **Read-back**: step 1 query.

## Role ladder
| Rung | Expect |
|---|---|
| {admin} | steps 1-8 |
| {editor} | same default columns |
| {guest} | `edit.php` redirects to login |

## Pass criteria
1. Default visible set is exactly the nine columns above; the rest are toggleable under Screen Options.
2. Title >= 240px; with all 16 columns on none is narrower than 20px and the page does not scroll sideways.
3. Core Posts is unaffected; 390px uses the stacked layout.

## Fail diagnostics
| Symptom | File |
|---|---|
| Wrong default columns | `includes/admin/class-listing-columns.php:200-235` (`default_hidden_columns` registered at `:51`) |
| Title collapses | `assets/css/admin.css:646-715` (`.post-type-listora_listing .wp-list-table .column-*`) |

## Not verified
Pixel widths and 390px behaviour were not measured (no Playwright at the time); column list and Screen Options checkboxes were read from the live DOM as admin.

## Cleanup
Step 8.
