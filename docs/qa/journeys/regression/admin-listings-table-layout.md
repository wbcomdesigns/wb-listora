---
journey: admin-listings-table-layout
plugin: wb-listora
priority: high
roles: [admin]
covers: [listing-columns, default-hidden-columns, admin-css-column-widths, pages-review-notice]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Free + Pro both active (Pro contributes the Moderator column)"
  - "WP-CLI access"
estimated_runtime_minutes: 5
---

# The listings table must stay readable, and the setup notice must stay dismissed

Two admin-screen regressions reported together from a QA screenshot.

**Columns.** The listings table shipped 17 columns all visible — 9 from Free,
Moderator from Pro, and cb/title/author/date/2 taxonomies/comments from core.
WordPress renders it with `table-layout: fixed`, under which unsized columns
get only the leftover; Title was unsized, collapsed to ~52px, and wrapped one
character per line into a ribbon roughly 1000px tall.

**Notice.** "WB Listora is set up. N pages are mapped" reappeared on every
admin screen. The notice is `is-dismissible`, so core paints an X — but core's
X is client-side only and persisted nothing. Only the small "Dismiss" link
wrote the user-meta flag, and every plugin reactivation re-armed the 7-day
transient.

## Steps

### 1. Default column set
- **Action**: `wp user meta delete 1 manageedit-listora_listingcolumnshidden`
  (an empty saved array counts as a preference and suppresses the defaults),
  then load `$SITE_URL/wp-admin/edit.php?post_type=listora_listing` at 1440px.
- **Expect**: 11 visible columns. Renewals, Reports, Duplicate confirmed,
  Listing Types, Categories and Comments are OFF but still listed in Screen
  Options. Title ≥ 240px; no row taller than ~80px.
- **On fail**: `Listing_Columns::default_hidden_columns()` is not registered on
  `default_hidden_columns`, or a saved user preference is overriding it.

### 2. Check column is pinned
- **Expect**: the leading checkbox column is ~35px.
- **On fail**: its width rule was dropped — unsized it absorbs every leftover
  percent and swells to roughly a third of the table.

### 3. Worst case — every column on
- **Action**: `wp user meta update 1 manageedit-listora_listingcolumnshidden ''
  --format=json` (empty array = show all), reload.
- **Expect**: 17 columns; ZERO columns narrower than 20px (the taxonomy columns
  previously collapsed to 0 and spilled a category list ~700px down the row);
  no horizontal page scroll; rows under ~140px.

### 4. No leak into core list tables
- **Action**: load `$SITE_URL/wp-admin/edit.php` (core Posts).
- **Expect**: the Title column keeps its stock width (~400px at 1440px). The
  widths must be scoped to `.post-type-listora_listing` — an earlier revision
  used `.wp-list-table.posts`, which matches EVERY post-type list table.

### 5. 390px
- **Expect**: core's stacked card layout; the page must not scroll sideways.
  Do not reintroduce a `min-width` + `overflow-x` rule here — the table's
  ancestors don't clip, so it scrolls the whole admin instead of the table.

### 6. Notice X persists
- **Action**:
  ```
  wp eval 'delete_user_meta( 1, "wb_listora_pages_review_dismissed" );
           set_transient( "wb_listora_pages_review_pending", "1", WEEK_IN_SECONDS );'
  ```
  Load any admin screen, click the notice's **X** (not the "Dismiss" link).
- **Expect**: `wb_listora_pages_review_pending` transient deleted AND user meta
  `wb_listora_pages_review_dismissed` set to `1`.
- **On fail**: `assets/js/admin/pages-review-notice.js` isn't enqueued — check
  `wb_listora_enqueue_pages_review_notice_script()` and that it shares the
  guards in `wb_listora_should_show_pages_review_notice()`.

### 7. Notice stays gone
- **Action**: reload.
- **Expect**: no `.listora-pages-review-notice` in the DOM.

### 8. Cleanup
- **Action**: `wp user meta delete 1 manageedit-listora_listingcolumnshidden`
  to return to the shipped defaults.
