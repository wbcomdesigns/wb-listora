---
journey: dashboard-tab-pagination
plugin: wb-listora
priority: high
roles: [member, admin, anonymous]
covers: [LST-F-06, dashboard, pagination, big-site-readiness]
prerequisites:
  - "Site reachable at http://wb-listora.local"
  - "Seed in this site: varundubey has 2,644 listings (133 pages); subscriber3 (user 5) has 25 favourites (2 pages); admin has 6 pages of reviews received. No member has more than 20 reviews WRITTEN: seed 21+ with the SQL below under the QA-J1- prefix, or mark step 3 'not run'"
  - "NEEDS SEEDED DATA: on a small dataset every pager correctly hides and this journey proves nothing"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Every dashboard tab must reach every row (LST-F-06 sentinel)

Each list tab on `/my-listings/` is server-rendered and paginated through `wb_listora_render_pagination()` (`includes/class-render-helpers.php:349`): listings, reviews written, reviews received, favourites, claims, leads, credits history. A page link reloads with `?tab={tab}&{arg}=N`. Works with JS off, survives the back button.

Verified via: curl + DOM parse as varundubey and subscriber3 on 2026-10-12 (the pane for Playwright was shared and busy). 390px not run.

## Setup

- `curl -c JAR -L "http://wb-listora.local/?autologin=varundubey"` (and `subscriber3`).
- Seed check: `SELECT (SELECT COUNT(*) FROM wp_posts WHERE post_author=1 AND post_type='listora_listing') listings, (SELECT COUNT(*) FROM wp_listora_favorites WHERE user_id=5) favourites;` expect 2644 and 25.

## Steps

### 1. Pagers render with real labels (2000+ rows)
- **Action**: as admin load `/my-listings/`; collect `nav.listora-pagination.listora-dashboard__pagination`.
- **Expect**: a nav with `aria-label="Listings pagination"` reading `Previous 1 2 3 … 133  Page 1 of 133 (2,644 items)  Next`; `aria-label="Reviews received pagination"` reading "Page 1 of 6". Labels in source: "Listings pagination", "Reviews written pagination", "Reviews received pagination", "Favorites pagination", "Claims pagination", "Leads pages", "Transaction history pagination".

### 2. Count and reachable rows agree
- **Action**: compare the Listings stat tile with the pager total.
- **Expect**: tile and "(2,644 items)" are the same figure; page 1 holds 20 `.listora-dashboard__listing-row`. A tile of 2,644 above 20 rows with no pager is the regression.

### 3. Next changes the slice; ends are disabled spans
- **Action**: `?tab=listings&listings_page=2`; then page 1.
- **Expect**: status "Page 2 of 133 (2,644 items)", Previous is an `<a rel="prev">`. Page 1: Previous is `<span aria-disabled="true">`. Never a link to nowhere.

### 4. Out-of-range clamps to the last populated page
- **Action**: `?tab=listings&listings_page=99999` (admin) and `?tab=favorites&favorites_page=99999` (subscriber3).
- **Expect**: "Page 133 of 133 (2,644 items)" with Next disabled and rows on screen; favourites "Page 2 of 2". An empty state is the regression (clamp built on `found_posts` returns 0 past the end).
- **Repeat** for `reviews_page`, `received_page`, `claims_page`.

### 5. Reviews pagers are independent
- **Action**: `?tab=reviews&received_page=3` as admin.
- **Expect**: received "Page 3 of 6" (previous links `?tab=reviews&received_page=2`) while written keeps its own arg `reviews_page`; paging one does not reset the other.

### 6. Page size filter
- **Action**: `add_filter( 'wb_listora_dashboard_per_page', fn() => 5 )` in a scratch mu-plugin.
- **Expect**: every tab paginates at 5; filter args are `( $per_page, $context, $user_id )`, context one of `listings`, `reviews_written`, `reviews_received`, `favorites` (leads tab: `leads`).

### 7. POSITIVE CONTROL for "no pager below two pages"
- **Action**: open the dashboard as `qa_vendor_01` (1 listing).
- **Expect**: no `nav.listora-pagination` for Listings. Compare step 1, where the same markup appears for 2,644 rows. (`total_pages < 2` returns early.)

### 8. Role ladder
- Owner of the data: steps 1-5. Another member (subscriber2): sees only their own rows (0 or 1 listing), never admin's. Logged out: `.listora-dashboard__login-prompt`, no pager. Editor (`qa_editor`): own rows only. Admin is the control with 2,644.

### 9. 390px
- **Expect**: pager nav visible, buttons >= 40px tall, `document.documentElement.scrollWidth <= 390`. Not run in this pass.

## Pass criteria

1. Listings and Reviews received pagers show "Page 1 of N" with N > 1 on a seeded member; Favorites on subscriber3.
2. Tile totals equal the pager total.
3. Next/Previous change the slice; disabled ends are `<span aria-disabled="true">`.
4. Out-of-range clamps to the last populated page.
5. Reviews written / received move independently.
6. `wb_listora_dashboard_per_page` changes the size.
7. No pager below two pages.

## Fail diagnostics

| Symptom | File |
|---|---|
| Tile says 2,644, list shows 20, no pager | `blocks/user-dashboard/render.php:142-262` (COUNT, clamp, LIMIT/OFFSET per tab) |
| Out-of-range shows empty state | `blocks/user-dashboard/render.php:259-262` clamp |
| Paging "written" resets "received" | `templates/blocks/user-dashboard/tab-reviews.php:84` (`reviews_page`) and `:211` (`received_page`) |
| Pager on a 3-row tab | `includes/class-render-helpers.php:358` `total_pages < 2` guard |
| Pager markup differs between tabs | each tab must call the helper: `tab-favorites.php:64`, `tab-listings.php:790`, `tab-claims.php:138`, `tab-leads.php:70` |

## Not verified

Reviews-written pager (no member has >20 written reviews on this site); 390px; filter step 6 was read from source, not executed.
