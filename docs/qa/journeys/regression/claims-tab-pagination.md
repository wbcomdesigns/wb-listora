---
journey: claims-tab-pagination
plugin: wb-listora
priority: high
roles: [subscriber]
covers: [user-dashboard, claims-tab, dashboard-claims-rest, pagination, big-site-readiness, count-star]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A member with MORE THAN 20 listora_claims rows exists (seed in Setup; capture USER_ID + USER_LOGIN)"
  - "The My Dashboard page is published (registry slug — default /my-dashboard/)"
estimated_runtime_minutes: 6
covers_card: null
covers_commit: fcbf0d1
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Dashboard Claims tab paginates past 20 claims (Previous / Page X of Y / Next)

The member dashboard Claims tab once hard-capped at `LIMIT 20` with no navigation, so a member with more than 20 claims could never reach claim 21. Contract now:

- **REST** `GET /listora/v1/dashboard/claims` accepts `page` (>= 1, default 1) and `per_page` (1-50, default 20) and returns `{claims, total, pages, has_more, page, per_page}`, with a dedicated `COUNT(*)`.
- **SSR** (`blocks/user-dashboard/render.php`) reads `?claims_page=N`, clamps an out-of-range page to the last real page, and fetches the slice via `Dashboard_Data::list_claims( user, 20, offset )`.
- **Template** (`templates/blocks/user-dashboard/tab-claims.php`) renders `nav.listora-pagination.listora-dashboard__pagination[aria-label="Claims pagination"]` through `wb_listora_render_pagination()`: Previous, numbered pages, status "Page X of Y", Next.

Role ladder: the member who owns the claims (`author1`, 35 claims on the QA site), another member (sees only their own: `qa_vendor_01` has 0), logged out (401 on REST, login prompt on the page), administrator as control (own claims only: `varundubey` has 2; the admin Claims screen is the all-claims view).

## Setup

- The dashboard page is the page stored in option `wb_listora_dashboard_page_id`; on the QA site that is **`$SITE_URL/my-listings/`** (page 7), NOT `/my-dashboard/` (that URL 404s here). Read it with `wp eval 'echo wb_listora_get_dashboard_url();'`.
- A member with more than 20 claims. QA site: `author1` (35 claims -> 2 pages). On a site without one, seed 25 rows (columns are `listing_id, user_id, status, proof_text, created_at`):
  ```bash
  wp eval 'global $wpdb; $u=(int) get_user_by("login","qa_vendor_01")->ID; $l=(int) $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_type=\"listora_listing\" AND post_status=\"publish\" LIMIT 1");
  for($i=1;$i<=25;$i++){ $wpdb->insert($wpdb->prefix."listora_claims",["listing_id"=>$l,"user_id"=>$u,"status"=>"pending","proof_text"=>"QA-J1- claim $i","created_at"=>current_time("mysql")]); }'
  ```
- Capture `TOTAL_CLAIMS` = `SELECT COUNT(*) FROM wp_listora_claims WHERE user_id=<id>`; `PAGES = ceil(TOTAL_CLAIMS / 20)` (>= 2).

## Steps

### 1. REST route declares page + per_page
- **Action**: `grep -n "'page'\|'per_page'\|'maximum'" includes/rest/class-dashboard-controller.php` near the `/claims` route (line ~182).
- **Expect**: `page` (integer, default 1, minimum 1) and `per_page` (default 20, minimum 1, maximum 50).

### 2. REST returns the paginated envelope
- **Action**: logged in as the member with a REST nonce (`wp-admin/admin-ajax.php?action=rest-nonce`): `GET /wp-json/listora/v1/dashboard/claims?page=2&per_page=20` and `?page=1&per_page=20`.
- **Expect**: HTTP 200. Page 1: 20 claims; page 2: `TOTAL_CLAIMS - 20` claims (15 for `author1`). `total === TOTAL_CLAIMS`, `pages === PAGES`, `has_more` true on page 1 and false on the last page; the two pages share no claim IDs. Each claim has `id, listing_id, listing_title, listing_url, status, proof_text, admin_notes, created_at, updated_at`.
- **Logged out**: 401. **Member without claims**: `total: 0`, `claims: []`.

### 3. Dashboard Claims tab shows the nav below the list
- **Action**: open `$SITE_URL/my-listings/?tab=claims` as the member at 1280px.
- **Verify**:
  ```js
  const nav = document.querySelector('nav.listora-pagination[aria-label="Claims pagination"]');
  ({ nav: !!nav,
     status: nav.querySelector('.listora-pagination__status').textContent.trim(),
     prevDisabled: nav.querySelector('.listora-pagination__prev').getAttribute('aria-disabled'),
     rows: document.querySelectorAll('#dash-panel-claims .listora-dashboard__claim-row').length })
  ```
- **Expect**: `status` "Page 1 of 2", `prevDisabled` "true" (Previous is a `<span>`, not a link, on page 1), `rows` 20, and a **Next** link `a.listora-pagination__next[rel=next]` pointing at `?tab=claims&claims_page=2`. Other dashboard panels are present but `hidden`; count only `#dash-panel-claims`.

### 4. Next reloads at `?tab=claims&claims_page=2`
- **Action**: click **Next** (or the numbered "2" link, `aria-label="Page 2"`).
- **Expect**: URL `.../my-listings/?tab=claims&claims_page=2`, status "Page 2 of 2", Previous is now a link (`rel=prev`), 15 rows, and different listing/claim rows than page 1 (the REST ids 16 down to 2 for `author1`, not 40 down to 21).

### 5. Previous returns to page 1
- **Action**: click **Previous**.
- **Expect**: back to page 1, 20 rows, "Page 1 of 2".

### 6. Out-of-range page clamps to the last real page
- **Action**: open `$SITE_URL/my-listings/?tab=claims&claims_page=999`.
- **Expect**: HTTP 200, status "Page 2 of 2" (last real page), 15 rows, not a blank panel or fatal.

### 7. Positive control, mobile, role ladder
- **Control**: a member with 20 or fewer claims (the administrator, 2 claims) shows NO pagination nav at all; with 21 or more it appears. This proves the nav is conditional on the count and not always rendered.
- **Mobile**: at 390, the nav wraps without horizontal page scroll; Previous / Next / page buttons are >= 40px tall (`.listora-btn--sm` hit areas, check `getBoundingClientRect().height`).
- **Another member** (`qa_vendor_01`): the Claims tab lists only their claims, never `author1`'s; passing `claims_page` has no effect on another user's data.
- **Logged out**: the dashboard page shows the login prompt, REST returns 401.

## Cleanup

If you seeded: `wp eval 'global $wpdb; $wpdb->query("DELETE FROM {$wpdb->prefix}listora_claims WHERE proof_text LIKE \"QA-J1- claim %\"");'`.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Endpoint ignores `page` / `per_page` | arg declaration regressed | `includes/rest/class-dashboard-controller.php:182-215` |
| Page 2 shows page 1 rows | offset not applied | `includes/core/class-dashboard-data.php:290` (`list_claims`) and `blocks/user-dashboard/render.php:357` |
| Wrong total / pages | count derived from rows, not `COUNT(*)` | `blocks/user-dashboard/render.php:350-352` (`count_claims`) |
| `claims_page=999` blank | clamp missing | `blocks/user-dashboard/render.php:353-356` |
| No nav at all | template dropped the helper call | `templates/blocks/user-dashboard/tab-claims.php:136` |
| Nav unstyled | pagination CSS | `blocks/user-dashboard/style.css`, `style-rtl.css`, `wb_listora_render_pagination()` at `includes/class-render-helpers.php:349` |

## Not verified

Steps 2, 3 (via curl/DOM: 20 rows, "Page 1 of 2", disabled Previous, Next href), 4 (15 rows, "Page 2 of 2"), 6 and the logged-out/other-member checks were run live as `author1`, `qa_vendor_01` and `varundubey`. The click-through, 1280/390 rendering and 40px tap-target check were NOT run in a browser.
