---
journey: dashboard-tabs-paginate-every-row
plugin: wb-listora
priority: high
roles: [owner, other, editor, guest, admin]
covers: [LST-F-06, user-dashboard, claims-tab, dashboard-claims-rest, pagination, big-site-readiness, count-star]
prerequisites:
  - "NEEDS SEEDED DATA: on a small dataset every pager correctly hides and this journey proves nothing"
  - "{owner} with 21+ listings, {other} with 21+ favourites, a member with 21+ reviews received and one with 21+ reviews WRITTEN, and $CLAIMANT with MORE THAN 20 `listora_claims` rows (seed under a QA- prefix)"
  - "The dashboard page: `wp eval 'echo wb_listora_get_dashboard_url();'` = $DASH"
estimated_runtime_minutes: 12
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes:
  - ora:regression/dashboard-tab-pagination.md
  - ora:regression/claims-tab-pagination.md
fails_on: fcbf0d1
site_profile: required
---

# Every dashboard list tab reaches every row, with Previous / Page X of Y / Next

## Why
Each list tab on $DASH is server-rendered and paginated through `wb_listora_render_pagination()` (`includes/class-render-helpers.php:349`): listings, reviews written, reviews received, favourites, claims, leads, credits history. The Claims tab once hard-capped at `LIMIT 20` with no navigation, so claim 21 was unreachable; a page link reloads with `?tab={tab}&{arg}=N` (works with JS off, survives Back).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Login via `curl -c JAR -L "$SITE_URL/?autologin=$LOGIN"`. Seed a claimant: `wp eval` inserting 25 rows into `${PREFIX}listora_claims` (`listing_id, user_id, status, proof_text, created_at`, `proof_text` "QA- claim N").
- Capture `TOTAL` per tab (`SELECT COUNT(*) ...` e.g. `listora_claims WHERE user_id=<id>`), `PAGES = ceil(TOTAL / 20)`.

## Steps

### 1. Pagers render with real labels (2000+ rows)
- **Action**: as {owner} load $DASH; collect `nav.listora-pagination.listora-dashboard__pagination`.
- **Expect**: `aria-label="Listings pagination"` reading `Previous 1 2 3 ... N  Page 1 of N (TOTAL items)  Next`; `aria-label="Reviews received pagination"` "Page 1 of ..". Labels in source: "Listings pagination", "Reviews written pagination", "Reviews received pagination", "Favorites pagination", "Claims pagination", "Leads pages", "Transaction history pagination".

### 2. Count and reachable rows agree
- **Action**: compare the Listings stat tile with the pager total.
- **Expect**: tile and "(TOTAL items)" are the same figure; page 1 holds 20 `.listora-dashboard__listing-row`. A tile of TOTAL above 20 rows with no pager is the regression.

### 3. Next changes the slice; ends are disabled spans
- **Action**: `?tab=listings&listings_page=2`, then page 1.
- **Expect**: "Page 2 of N (TOTAL items)", Previous is `<a rel="prev">`. Page 1: Previous is `<span aria-disabled="true">`, never a link to nowhere.

### 4. Out-of-range clamps to the last populated page
- **Action**: `?tab=listings&listings_page=99999` ({owner}) and `?tab=favorites&favorites_page=99999` ({other}); repeat for `reviews_page`, `received_page`, `claims_page`.
- **Expect**: "Page N of N" with Next disabled and rows on screen (HTTP 200). An empty state is the regression (a clamp built on `found_posts` returns 0 past the end).

### 5. Reviews pagers are independent
- **Action**: `?tab=reviews&received_page=3`.
- **Expect**: received "Page 3 of M" (previous links `?tab=reviews&received_page=2`) while written keeps its own arg `reviews_page`; paging one does not reset the other.

### 6. Page size filter
- **Action**: scratch mu-plugin `add_filter( 'wb_listora_dashboard_per_page', fn() => 5 )`.
- **Expect**: every tab paginates at 5; filter args are `( $per_page, $context, $user_id )`, context one of `listings`, `reviews_written`, `reviews_received`, `favorites` (leads: `leads`).

### 7. Claims REST declares and returns the paginated envelope
- **Action**: `grep -n "'page'\|'per_page'\|'maximum'" includes/rest/class-dashboard-controller.php` near `/claims` (~line 182); then as $CLAIMANT with a REST nonce (`wp-admin/admin-ajax.php?action=rest-nonce`): `GET /wp-json/listora/v1/dashboard/claims?page=1&per_page=20` and `?page=2&per_page=20`.
- **Expect**: args `page` (integer, default 1, minimum 1) and `per_page` (default 20, minimum 1, maximum 50). HTTP 200 with `{claims, total, pages, has_more, page, per_page}`: page 1 has 20 claims, page 2 has `TOTAL - 20`; `total === TOTAL`, `pages === PAGES`, `has_more` true on page 1 and false on the last page; the pages share no claim IDs; each claim has `id, listing_id, listing_title, listing_url, status, proof_text, admin_notes, created_at, updated_at`. **Logged out**: 401. **Member without claims**: `total: 0`, `claims: []`.

### 8. Claims tab shows the nav below the list
- **Action**: open `$DASH/?tab=claims` as $CLAIMANT at 1280.
- **Verify**: `nav.listora-pagination[aria-label="Claims pagination"]`, its `.listora-pagination__status` text, `.listora-pagination__prev` `aria-disabled`, and `#dash-panel-claims .listora-dashboard__claim-row` count (other panels are present but `hidden`; count only this one).
- **Expect**: "Page 1 of PAGES", `aria-disabled="true"` (Previous is a `<span>`), 20 rows and a **Next** link `a.listora-pagination__next[rel=next]` -> `?tab=claims&claims_page=2`.

### 9. Next / Previous on Claims
- **Action**: click **Next** (or numbered "2", `aria-label="Page 2"`), then **Previous**.
- **Expect**: `?tab=claims&claims_page=2`, "Page 2 of PAGES", Previous is now a link (`rel=prev`), `TOTAL - 20` rows different from page 1; Previous returns to page 1 with 20 rows.

### 10. Positive control for "no pager below two pages"
- **Action**: open the dashboard as {admin} or a member with <= 20 rows (Listings 1 listing; Claims <= 20).
- **Expect**: no `nav.listora-pagination` for that tab, while step 1 shows the same markup for TOTAL rows (`total_pages < 2` returns early, `class-render-helpers.php:358`).

### 11. Mobile
- **Expect** at 390: pager nav visible and wraps, Previous / Next / page buttons >= 40px tall (`.listora-btn--sm` hit area via `getBoundingClientRect().height`), `document.documentElement.scrollWidth <= 390`. Not verified live.

## Role ladder
| Actor | Expect |
|---|---|
| Data owner ({owner}, {other}, $CLAIMANT) | steps 1-9 |
| {other} | sees only their own rows (0 or 1), never another member's; `claims_page` has no effect on another user's data |
| {editor} | own rows only |
| {guest} | `.listora-dashboard__login-prompt`, no pager; REST 401 |
| {admin} | control: own rows only (all-claims view is the admin Claims screen) |

## Pass criteria
1. Listings and Reviews received pagers show "Page 1 of N" with N > 1 on a seeded member; Favorites and Claims likewise.
2. Tile totals equal the pager total and REST `total`.
3. Next/Previous change the slice; disabled ends are `<span aria-disabled="true">`.
4. Out-of-range clamps to the last populated page.
5. Written / received (and every tab arg) move independently.
6. `wb_listora_dashboard_per_page` changes the size; no pager below two pages.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Tile says TOTAL, list shows 20, no pager | COUNT/clamp/LIMIT/OFFSET per tab | `blocks/user-dashboard/render.php:142-262` |
| Out-of-range shows empty state | clamp | `blocks/user-dashboard/render.php:259-262`, claims `:353-356` |
| Paging "written" resets "received" | arg names | `templates/blocks/user-dashboard/tab-reviews.php:84` (`reviews_page`), `:211` (`received_page`) |
| Pager on a 3-row tab | guard | `includes/class-render-helpers.php:358` |
| Pager markup differs between tabs | each tab must call the helper | `tab-favorites.php:64`, `tab-listings.php:790`, `tab-claims.php:136-138`, `tab-leads.php:70` |
| Endpoint ignores `page` / `per_page` | arg declaration | `includes/rest/class-dashboard-controller.php:182-215` |
| Page 2 shows page 1 rows | offset | `includes/core/class-dashboard-data.php:290` (`list_claims`), `render.php:357` |
| Wrong total / pages | count from rows, not `COUNT(*)` | `render.php:350-352` (`count_claims`) |
| Nav unstyled | pagination CSS | `blocks/user-dashboard/style.css`, `style-rtl.css` |

## Not verified
Reviews-written pager (no member had > 20 written reviews: seed 21+ under a QA- prefix or mark step 5 partial); 390px; step 6 read from source; step 7-9 click-through and 1280/390 rendering were observed by curl/DOM only.

## Cleanup
Delete seeded rows: `DELETE FROM ${PREFIX}listora_claims WHERE proof_text LIKE 'QA- claim %'`; remove the scratch mu-plugin; delete seeded reviews.
