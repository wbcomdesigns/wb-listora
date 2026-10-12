---
journey: admin-listings-table-layout
plugin: wb-listora
priority: high
roles: [administrator, editor]
covers: [listing-columns, default-hidden-columns, admin-css-column-widths, pages-review-notice]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Free + Pro both active (Pro contributes the Moderator column)"
  - "WP-CLI access"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default]
changed: true
---

# The listings table stays readable, and the setup notice stays dismissed

Two admin-screen regressions reported together from a QA screenshot.

**Columns.** The listings table shipped with every column visible. WordPress renders it with `table-layout: fixed`, under which unsized columns get only the leftover; Title was unsized, collapsed to ~52px, and wrapped one character per line into a ribbon about 1000px tall.

**Notice.** The "WB Listora is set up. N pages are mapped" notice reappeared on every admin screen. It is `is-dismissible`, so core paints an X, but core's X is client-side only. Only the small "Dismiss" link wrote the user-meta flag, and every reactivation re-armed the 7-day transient.

## Current column inventory (verified live 2026-10-12 at `edit.php?post_type=listora_listing`)

Columns after the checkbox: `listora_thumb`, `title`, `listora_type`, `listora_location`, `listora_rating`, `listora_views`, `listora_featured`, `listora_renewals`, `listora_reports`, `listora_duplicate`, `author`, `taxonomy-listora_listing_type`, `taxonomy-listora_listing_cat`, `listora_moderator` (Pro), `date` = 15 plus the checkbox = 16. There is no Comments column (the listing type does not support comments).

Hidden by default (`Listing_Columns::default_hidden_columns()`, `includes/admin/class-listing-columns.php:200-235`): `listora_views` (Views lives in Analytics), `listora_renewals`, `listora_duplicate`, both taxonomy columns, `comments` if present, `listora_reports` unless any listing has an open report, plus Pro's `listora_moderator` through the `wb_listora_default_hidden_columns` filter. Visible by default: checkbox, thumb, title, type, location, rating, featured, author, date.

## Steps

### 1. Default column set
- **Action**: `wp user meta delete <admin id> manageedit-listora_listingcolumnshidden` (an empty saved array counts as a preference and suppresses the defaults), then load `$SITE_URL/wp-admin/edit.php?post_type=listora_listing` at 1440.
- **Expect**: visible `th`s are exactly checkbox, `column-listora_thumb`, `column-title`, `column-listora_type`, `column-listora_location`, `column-listora_rating`, `column-listora_featured`, `column-author`, `column-date`; the rest carry `.hidden` but are still listed under Screen Options (`input.hide-column-tog`). Title >= 240px; no row taller than ~80px.
- **POSITIVE CONTROL**: with an open visitor report on any listing, `listora_reports` is visible by default (`any_reported()`); with none it is hidden.
- **On fail**: `default_hidden_columns` filter not registered (`class-listing-columns.php:51`), or a saved preference overrides.

### 2. Check column is pinned
- **Expect**: the leading checkbox column is ~35px (`.post-type-listora_listing .wp-list-table .check-column`, `assets/css/admin.css`). Unsized it absorbs every leftover percent.

### 3. Worst case, every column on
- **Action**: `wp user meta update <admin id> manageedit-listora_listingcolumnshidden '[]' --format=json`, reload.
- **Expect**: 16 columns; no column narrower than 20px; no horizontal page scroll; rows under ~140px.

### 4. No leak into core list tables
- **Action**: load `$SITE_URL/wp-admin/edit.php` (core Posts).
- **Expect**: Title keeps its stock width (~400px at 1440). Widths are scoped to `.post-type-listora_listing`, never `.wp-list-table.posts`.

### 5. 390px
- **Expect**: core's stacked card layout; the page does not scroll sideways. Do not reintroduce a `min-width` + `overflow-x` rule.

### 6. Notice X persists
- **Action**:
  ```
  wp eval 'delete_user_meta( 1, "wb_listora_pages_review_dismissed" );
           set_transient( "wb_listora_pages_review_pending", "1", WEEK_IN_SECONDS );'
  ```
  Load any admin screen EXCEPT Listora > Settings and the Listora landing page (the notice is deliberately off there), as a user with `manage_options`, e.g. `wp-admin/edit.php?post_type=listora_listing`. Click the notice's X (`.notice-dismiss`), not the "Dismiss" link.
- **Expect before the click (POSITIVE CONTROL)**: `.notice.listora-notice.listora-pages-review-notice` is in the DOM, reads "WB Listora is set up. N pages are mapped - review or remap on Settings > General > Pages." with "Review pages" and "Dismiss" buttons.
- **Expect after the click**: transient `wb_listora_pages_review_pending` deleted AND user meta `wb_listora_pages_review_dismissed` set to `1`.
- **On fail**: `assets/js/admin/pages-review-notice.js` not enqueued: `includes/page-registry-helpers.php:203-216`, guards in `:162-190`.

### 7. Notice stays gone
- **Action**: reload.
- **Expect**: no `.listora-pages-review-notice` in the DOM.

### 8. Role ladder
- **Editor (no `manage_options`)**: never sees the notice even with the transient set (`wb_listora_should_show_pages_review_notice()` first guard), and the listings table shows the same default columns.
- **Administrator**: control for steps 1-7.

### 9. Large table
- **Action**: the list holds ~2,800 listings (2,814 items across statuses on the reference site): confirm WordPress pagination (20 rows per page), sort by Title and by Rating, and a `s=` search return without the layout breaking.

### 10. Cleanup
- `wp user meta delete <admin id> manageedit-listora_listingcolumnshidden` to return to the shipped defaults; re-arm or clear the transient as it was.

## Fail diagnostics
| Symptom | File |
|---|---|
| Wrong default columns | `includes/admin/class-listing-columns.php:200-235` |
| Title collapses | `assets/css/admin.css:646-715` (`.post-type-listora_listing .wp-list-table .column-*`) |
| Notice X does not persist | `assets/js/admin/pages-review-notice.js:20`, `includes/page-registry-helpers.php:203-285` |

Verified via: curl/DOM as administrator (live column list and Screen Options checkboxes), source for CSS and notice guards. Pixel widths, 390px behaviour and the X click were not measured (no Playwright).
