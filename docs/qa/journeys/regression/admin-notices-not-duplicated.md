---
journey: admin-notices-not-duplicated
plugin: wb-listora
priority: normal
roles: [administrator]
covers: [admin-header, admin-notices]
prerequisites:
  - "Site reachable at $SITE_URL"
estimated_runtime_minutes: 5
covers_card: 10332070181
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [wp-admin default]
changed: true
---

# Admin notices show once on Listora list screens

Sentinel for the second 1.8.0 QA bounce. Core's `common.js` moves every notice after the FIRST `.wp-header-end` it finds; a Listora header that printed its own marker on a core list table (which already prints one) made it copy each notice after both.

## Background (2.0.0 shape)

There is no auto-injected title bar any more. Plugin screens print their header through `wb_listora_render_admin_header()` (`includes/class-render-helpers.php:218-250`), which ends with exactly one `<hr class="wp-header-end">`; core list tables (Listings, Categories, Plans) keep core's marker and Listora prints none there; the listing-type editor prints its own once (`includes/admin/class-type-editor.php:469`).

## Steps

### 1. Listings list
- **Action**: as administrator open `$SITE_URL/wp-admin/edit.php?post_type=listora_listing&updated=1`.
- **Expect**: exactly one `hr.wp-header-end` in the DOM (count verified 2026-10-12: 1) and ONE notice "1 post updated." (`#message.notice.updated`). Check the rendered DOM after `common.js` ran: `document.querySelectorAll('#message').length === 1` and no second copy after the table header.

### 2. Categories
- **Expect**: exactly one marker on `edit-tags.php?taxonomy=listora_listing_cat&post_type=listora_listing` (verified: 1).

### 3. Plugin pages keep theirs
- **Expect**: Listing Types (`page=listora-listing-types`), Settings (`page=listora-settings`) and Reviews (`page=listora-reviews`) each have exactly one marker (verified: 1 each) and their notices render below the page header (`.listora-page-header`), once.

### 4. POSITIVE CONTROL: a notice does move
- **Action**: on Reviews, trigger a moderation action (or load `page=listora-reviews&listora_notice=approve&n=1`).
- **Expect**: one "1 review approved..." notice under the header, `display` not `none`. Proves the duplicate guard has not simply suppressed notices.

### 5. Role ladder
Admin-only chrome. Control: administrator (above). Editor sees the same single marker on Reviews and Claims (`page=listora-reviews`, `page=listora-claims`); subscriber and logged-out cannot reach these screens (403 / login redirect).

### 6. Large list
The Listings list holds ~2,800 rows; paginated pages (`paged=2`) and a search (`s=`) keep one marker and one notice.

## Fail diagnostics
| Symptom | File |
|---|---|
| Two markers on a core list table | a Listora header printing `wp-header-end` there: `includes/class-render-helpers.php:250` must stay plugin-page only |
| Notice appears twice | same cause; common.js copies after each marker |

Verified via: curl/DOM marker counts as administrator on six screens. The client-side move done by `common.js` (the actual duplication) needs a browser and was not run in this pass.
