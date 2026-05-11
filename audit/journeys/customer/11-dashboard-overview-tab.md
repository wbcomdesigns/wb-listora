---
journey: dashboard-overview-tab
plugin: wb-listora
priority: high
roles: [member]
covers: [user-dashboard, overview-tab, dashboard-stats, stats-cache]
prerequisites:
  - "Test member has ≥ 1 published listing, ≥ 1 review (received), ≥ 1 favourite saved, ≥ 1 claim history"
estimated_runtime_minutes: 3
---

# Member sees accurate stats on dashboard Overview tab

Verifies the Overview tab (which became the first tab in commit 7bc2bc0) shows DB-accurate counts for listings, reviews, claims, favourites, and that stat-cache invalidation works on writes.

## Setup

- Site: `$SITE_URL`
- Test member: `member1` (autologin `?autologin=member1`); capture `USER_ID`
- Seed counts via direct DB (or via the natural API flows):
  ```sql
  -- baseline counts (do not modify; just record them)
  SELECT
    (SELECT COUNT(*) FROM wp_posts WHERE post_type='listora_listing' AND post_status='publish' AND post_author=$USER_ID) AS listings,
    (SELECT COUNT(*) FROM wp_listora_reviews WHERE listing_id IN (SELECT ID FROM wp_posts WHERE post_author=$USER_ID)) AS reviews_received,
    (SELECT COUNT(*) FROM wp_listora_favorites WHERE user_id=$USER_ID) AS favorites,
    (SELECT COUNT(*) FROM wp_listora_claims WHERE user_id=$USER_ID) AS claims;
  ```
- Note the baseline tuple as `EXPECTED_LISTINGS`, `EXPECTED_REVIEWS`, `EXPECTED_FAVS`, `EXPECTED_CLAIMS`.

## Steps

### 1. Open dashboard
- **Action**: `playwright_navigate $SITE_URL/dashboard/?autologin=member1`
- **Expect**: Overview tab active (URL has no #fragment OR `#overview`)

### 2. Stats render in 4 tiles
- **Action**: `browser_evaluate "Array.from(document.querySelectorAll('.listora-dashboard__stat')).map(el => ({label: el.querySelector('.listora-dashboard__stat-label')?.textContent.trim(), value: el.querySelector('.listora-dashboard__stat-value')?.textContent.trim()}))"`
- **Expect**: 4 tiles with labels Listings, Reviews, Claims, Favourites; values match baseline

### 3. Values match DB (data flow)
- Compare tile values to the baseline tuple from setup.
- **Expect**: exact equality.

### 4. Add a new favourite → counter increments
- **Action**: navigate to a listing, click Favourite; return to dashboard
- **Expect**: Favourites tile = `EXPECTED_FAVS + 1` after page refresh
- **Verify cache invalidation**: `wp transient delete-all` should NOT be needed — the after_add_favorite hook MUST invalidate the dashboard stats transient

### 5. Stats transient exists
- **Action**:
  ```bash
  wp transient get "listora_dash_stats_$USER_ID"
  ```
- **Expect**: non-empty JSON with the 4 counts. 60-second TTL per the 2026-04-06 perf optimization.

### 6. REST shape check
- **Action**:
  ```bash
  curl -s "$SITE_URL/wp-json/listora/v1/dashboard/stats" -H "X-WP-Nonce: $NONCE" --cookie "..." | jq 'keys | sort | join(",")'
  ```
- **Expect**: `claims,favourites,listings,reviews,user_id` (plus any extension keys). The `wb_listora_rest_prepare_dashboard_stats` filter must be in play.

### 7. Empty member sees friendly empty-state
- **Action**: autologin a brand-new user (`member-new`), open dashboard
- **Expect**: 4 tiles with value `0`; one empty-state card "Get started by submitting your first listing" with CTA button

### 8. Recent activity feed renders
- **Action**: `browser_evaluate "document.querySelectorAll('.listora-dashboard__activity-item').length"`
- **Expect**: ≥ 1 if member has any recent activity in last 30 days

### 9. Developer extension hook (filter prepare_dashboard_stats)
- **Action**: `wp eval 'add_filter("wb_listora_rest_prepare_dashboard_stats", function($d){ $d["custom_count"] = 7; return $d; });'` reload dashboard
- **Expect**: REST response includes `custom_count: 7`. Pro consumes this hook to add credit-balance etc.

### 10. Sign out → tab not accessible
- **Action**: log out; visit dashboard URL
- **Expect**: redirect to login OR friendly "Please log in" gate. No fatal.

## Pass criteria

1. 4 stat tiles render with DB-accurate values
2. Cache invalidates on member writes (favourite/listing/review)
3. Transient stored + REST shape stable
4. Empty member sees empty-state, not zeros
5. `wb_listora_rest_prepare_dashboard_stats` filter accepted
6. Auth gate prevents anonymous access

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Tile values wrong | aggregation query incorrect | `class-dashboard-controller.php::get_stats` |
| Counter doesn't increment | cache-bust hook missing on after_add_favorite | `class-favorites-controller.php::after_create` |
| Transient absent | 60s cache layer disabled OR delete-all hit | `class-dashboard-controller.php::cache_key` |
| Empty member shows zeros instead of CTA | empty-state template condition wrong | `templates/blocks/user-dashboard/tab-overview.php` |
| Filter not honored | `wb_listora_rest_prepare_dashboard_stats` not fired | `class-dashboard-controller.php::prepare_response` |
