---
journey: paging-past-last-page
group: directory-search
actor: customer
mode: mixed
plugin: wb-listora
priority: high
roles: [guest, owner, other, admin]
covers: [pagination-clamp, directory-past-last-page, admin-table-past-last-page, dashboard-tab-clamp, big-site-2000-rows, paging-counts-from-count-star]
prerequisites:
  - "Site reachable at $SITE_URL with 2,000+ listing rows (seed QA-prefixed ones if fewer) and 100+ reviews"
  - "Per-page left at the default (20)"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, wp-admin default]
supersedes: []
site_profile: required
---

# An old or hand-edited page number shows the last real page, on the Directory, wp-admin tables and dashboard tabs

## Why
An old bookmark (`?listora_page=99`) after listings were removed rendered an empty grid with a nonsense count ("Showing 1,961-127 of 127"). Every paged list must clamp, not only the Directory.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Derive `ROWS`, `PUB`, `LAST`:
  ```bash
  wp --path=$WP_PATH eval 'global $wpdb; echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=\"listora_listing\" AND post_status<>\"auto-draft\"");'   # ROWS
  wp --path=$WP_PATH eval 'echo rest_do_request(new WP_REST_Request("GET","/listora/v1/search"))->get_data()["total"];'                                              # PUB
  ```
  `LAST = ceil(PUB / 20)`. `$RARE_WORD` matches only a few listings.
- Roles: guest, `{owner}` (owns many listings) , `{other}`, `{admin}`.

## Steps

### 1. Directory (guest, 1440)
- **Action**: open `$SITE_URL/listings/?listora_page=1`, `?listora_page=LAST`, `?listora_page=LAST+1`, `?listora_page=999999`.
- **Expect**: page 1 "Showing 1-20 of PUB listings"; LAST reads "Showing <20*(LAST-1)+1>-PUB of PUB listings"; both beyond-the-end URLs render the SAME as LAST (same count text, cards, and `a.listora-grid__page-num.is-active[aria-current="page"]` = LAST). Never "Showing N-M" with N > M, never a blank grid.
- **Positive control**: page 2 reads "Showing 21-40" in the same run.
- **Expect**: `listora_page=0`, `-3`, `abc` behave as page 1.
- **Pager**: "Previous page" enabled and "Next page" disabled on the last page; at most 7 numbered links with ellipses; every link keeps active filters.
- **On fail**: `blocks/listing-grid/render.php` (~72), `templates/blocks/listing-grid/pagination.php`.

### 2. Directory with a filter that leaves one page
- **Action**: `?listora_keyword=$RARE_WORD&listora_page=50`.
- **Expect**: page 1 of the filtered set; no pager when only one page ("Showing 1-k of k").

### 3. wp-admin tables (`{admin}`)
- **Action**: `$SITE_URL/wp-admin/admin.php?page=listora-reviews&status=all&paged=999` (and `listora-email-log`, `listora-claims`, `listora-leads`).
- **Expect**: the last page of rows with the count text still right ("<n> reviews") and a numbered pager; NOT the empty state; view tabs and rows agree.
- **expected-fail**: `Admin_Table` answers a past-the-end `paged` with the EMPTY STATE ("No reviews yet") although rows exist (`includes/admin/class-admin-table.php::request()` ~58 reads `paged` with no upper bound; `render()` ~544).
- **Positive control**: `...&status=all` (no paged) lists 20 rows and "<n> reviews".

### 4. Native Listings list (`{admin}`, ROWS rows)
- **Action**: `$SITE_URL/wp-admin/edit.php?post_type=listora_listing&paged=999`.
- **Expect**: WordPress redirects (302) to the last page (`paged=ceil(ROWS/20)`); the table shows "<ROWS> items" and the remainder rows; sort by title and filter by status keep working; bulk actions available.
- **Filter + sort at scale**: filter Status = Published, sort by Title; response < 3 s and no PHP notice in `debug.log`.

### 5. Dashboard tabs ({owner}, 1440)
- **Action**: none restated. The per-tab out-of-range clamp (`listings_page`, `leads_page`, `reviews_page`, `received_page`, `favorites_page`, `claims_page` = 99999 shows the LAST page, never "No listings yet") is step 4 of `dashboard-account/dashboard-tabs-paginate-every-row.md`; status filter and count-badge agreement are `dashboard-account/dashboard-my-listings-statuses-counts-filters.md`.
- **Expect**: both owners stay green when this journey passes; read the ACTIVE pane `#dash-panel-<tab>` (every pane is in the DOM).

### 6. Counts come from COUNT(*), not loading rows
- **Action**: Query Monitor / `SAVEQUERIES` on page 1 of the Directory and of the admin Listings list.
- **Expect**: one count query per list and `LIMIT 20` on the row query; no per-row listing meta query in the loop. Page generation stays flat from 100 to ROWS rows.

### 7. Lifecycle and N=0/1
- **Action**: trash all but 21 published listings in a QA copy (or filter to a keyword with 21 matches), then to exactly 20, then 1, then 0 matches.
- **Expect**: 21 -> two pages, last page "Showing 21-21 of 21"; 20 -> no pager; 1 -> "Showing 1-1 of 1"; 0 -> empty state, `?listora_page=5` still clamps to the empty state with HTTP 200. Not verified live.

### 8. 390px
- **Expect**: pager wraps (prev/next 40px, numbers 40px), no horizontal scroll, count text above the grid; wp-admin tables stack rows under 480px.

## Role ladder
| Rung | Expect |
|---|---|
| guest | steps 1-2, 7-8 (published listings only: PUB, never drafts) |
| `{owner}` | steps 1-2 identical; step 5 shows only their own rows |
| `{other}` | step 5 never shows another member's rows/leads |
| `{admin}` (control) | steps 3-4 |

## Pass criteria
1. Every paged list surface shows the last real page for an out-of-range page number.
2. Counts and pagers agree with the data on a 2,000+ row site; no unbounded query.
3. Invalid page numbers fall back to page 1.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| "Showing 1,961-127" | clamp removed in the grid render | `blocks/listing-grid/render.php` |
| Empty state under non-empty tabs | `Admin_Table` not clamping `paged` | `includes/admin/class-admin-table.php::request` |
| Dashboard tab empty at `page=999` | tab panel not re-querying after clamp | `Leads_Dashboard_Tab::render_panel`, `blocks/user-dashboard/render.php` |

## Not verified
Step 3 is expected to fail on this build; steps 6-8 partially. Steps 5 reviews/favorites outcome is recorded per run.

## Cleanup
Remove QA seeds; restore trashed listings.
