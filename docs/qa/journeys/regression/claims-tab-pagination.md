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
---

# Dashboard Claims tab paginates past 20 claims (prev / Page X of Y / next)

Regression sentinel for M6+M7 (`fcbf0d1`). The dashboard Claims tab hard-capped
at `LIMIT 20` with no pagination nav — a member with more than 20 claims could
never reach claims 21+. The fix:

- **REST** (`includes/rest/class-dashboard-controller.php`) registers `page`
  (>=1, default 1) + `per_page` (1-50, default 20) args on
  `GET /listora/v1/dashboard/claims`, both `absint`-sanitised. The handler
  already had `LIMIT/OFFSET` + a dedicated `COUNT(*)` + the
  `{claims, total, pages, has_more}` envelope — only the arg declaration was
  missing.
- **SSR** (`blocks/user-dashboard/render.php`) reads `?claims_page` from the
  URL, runs a dedicated `COUNT(*)` for total pages, clamps an out-of-range page
  to the last real page, and fetches the slice via `LIMIT %d OFFSET %d` ordered
  `created_at DESC, id DESC`. The pending-claim badge keeps its own `COUNT(*)`.
- **Template** (`templates/blocks/user-dashboard/tab-claims.php`) renders a
  prev / `Page X of Y` / next nav on the shared `.listora-pagination`
  vocabulary, each link reloading with `?tab=claims&claims_page=N`.

## Setup

- Seed > 20 claims for a member so pagination is exercised:
  ```bash
  wp --path="/Users/varundubey/Local Sites/directory/app/public" eval '
    global $wpdb; $u = (int) get_user_by("login","combo")->ID;
    $t = $wpdb->prefix."listora_claims";
    // grab any published listing id to attach claims to
    $lid = (int) $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_type=\"listora_listing\" AND post_status=\"publish\" LIMIT 1");
    for ( $i = 1; $i <= 25; $i++ ) {
      $wpdb->insert( $t, array(
        "listing_id" => $lid, "user_id" => $u, "status" => "pending",
        "message" => "Smoke claim $i", "created_at" => current_time("mysql"),
      ) );
    }
    echo "seeded:".$wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE user_id=%d",$u) )."\n";
  '
  ```
- Capture `TOTAL_CLAIMS` from that count. `PER_PAGE = 20`, so `PAGES = ceil(TOTAL_CLAIMS/20)` (>= 2).

## Steps

### 1. REST route declares page + per_page args
- **Action**: `grep -n "dashboard/claims\|'page'\|'per_page'\|'maximum' *=> *50\|absint" includes/rest/class-dashboard-controller.php`
- **Expect**: the `GET /dashboard/claims` route registers a `page` arg (`type integer`, `default 1`, `minimum 1`, `sanitize_callback absint`) and a `per_page` arg (`default 20`, `minimum 1`, `maximum 50`, `absint`).
- **On fail**: `fcbf0d1` — the arg declaration regressed.

### 2. REST endpoint accepts the args and returns the paginated envelope
- **Action**:
  ```
  curl -s --cookie "<combo-cookie>" -H "X-WP-Nonce: <nonce>" \
    "$SITE_URL/wp-json/listora/v1/dashboard/claims?page=2&per_page=20"
  ```
- **Expect**: HTTP 200 with a `{ claims, total, pages, has_more }` envelope: `total === TOTAL_CLAIMS`, `pages === ceil(total/20)`, `claims` holds the SECOND slice (claims 21-40, i.e. `min(20, total-20)` rows), and `has_more` is `(offset+count) < total` (true on page 2 only if total > 40). Page 1 (`?page=1`) returns claims 1-20. The two pages share NO claim IDs.
- **On fail**: args ignored (page 2 returns the same rows as page 1), or `pages` computed from `count(rows)` instead of a `COUNT(*)`.

### 3. SSR Claims tab shows the nav below the list
- **Action**: open `$SITE_URL/my-dashboard/?autologin=combo&tab=claims` at 1280px.
- **Verify**:
  ```js
  // pagination nav rendered below the claim list
  const nav = document.querySelector('.listora-dashboard__pagination, nav.listora-pagination');
  nav !== null;
  nav.querySelector('.listora-pagination__status').textContent.match(/Page\s+1\s+of\s+/);  // "Page 1 of Y"
  // exactly PER_PAGE claim rows on page 1
  document.querySelectorAll('[data-claim-id], .listora-dashboard__claim-row').length === 20;
  ```
- **Expect**: nav present; status reads "Page 1 of Y" (Y = PAGES); Previous is disabled (`aria-disabled="true"`) on page 1; 20 claim rows rendered.
- **On fail**: no nav at all (the pre-fix bug) → `templates/blocks/user-dashboard/tab-claims.php`.

### 4. Next reloads at ?tab=claims&claims_page=2 and shows the next slice
- **Action**: click **Next** (or the page-2 link). Confirm the URL becomes `.../my-dashboard/?...tab=claims&claims_page=2`.
- **Verify**:
  ```js
  new URLSearchParams(location.search).get('claims_page') === '2';
  document.querySelector('.listora-pagination__status').textContent.match(/Page\s+2\s+of\s+/);
  ```
- **Expect**: page 2 SSR-renders claims 21-40 (different "Smoke claim N" messages than page 1), Previous now enabled, status "Page 2 of Y".
- **On fail**: page-2 link absent or `claims_page` ignored by `render.php`.

### 5. Previous returns to page 1
- **Action**: click **Previous** from page 2.
- **Expect**: URL back to `claims_page=1` (or no `claims_page`), claims 1-20 shown again.

### 6. Out-of-range page clamps to the last real page (no 500)
- **Action**: visit `$SITE_URL/my-dashboard/?autologin=combo&tab=claims&claims_page=999`.
- **Expect**: HTTP 200, render clamps to the last real page (`render.php`'s `$claims_page > $claims_total_pages` guard), status reads "Page Y of Y" — not a blank panel or fatal.
- **On fail**: clamp guard missing in `blocks/user-dashboard/render.php`.

### Cleanup
- `wp --path="/Users/varundubey/Local Sites/directory/app/public" eval 'global $wpdb; $wpdb->query("DELETE FROM {$wpdb->prefix}listora_claims WHERE message LIKE \"Smoke claim %\"");'`

## Fail diagnostics
- Endpoint rejects page/per_page → `includes/rest/class-dashboard-controller.php`.
- SSR doesn't honour `?claims_page` / wrong total → `blocks/user-dashboard/render.php`.
- Nav not rendered → `templates/blocks/user-dashboard/tab-claims.php`.
- Pagination unstyled → `blocks/user-dashboard/style.css` + `style-rtl.css`.

## Notes
- Big-site-readiness pattern: `LIMIT/OFFSET` + dedicated `COUNT(*)` (never `count(get_results())`) + a clamped page + server-rendered nav. Pairs with the dashboard-pagination C row (`C.member.dashboard-pagination`) which covers the other tabs.
