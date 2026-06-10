---
journey: dashboard-stats-transient-bust
plugin: wb-listora
priority: normal
roles: [member]
covers: ["#9982046916", "dashboard stat cache busting", "transient vs object-cache API mismatch"]
prerequisites:
  - "Logged-in user; at least 1 published listing"
estimated_runtime_minutes: 2
---

# Dashboard stat counts refresh immediately after favorite/review writes

Card #9982046916: the dashboard stats cache is a TRANSIENT
(`user-dashboard/render.php` get/set_transient, 60s TTL) but the four
write-side busts used `wp_cache_delete( ..., 'listora' )` — a no-op against a
transient on EVERY setup (without a persistent backend it clears nothing;
with one, transients live under the `transient` group anyway). The "Saved: N"
/ review-count stats stayed stale up to 60s after favoriting or reviewing.

Fix: the four sites (favorites add/remove, review create/delete) call
`delete_transient( 'listora_dashboard_stats_' . $user_id )`. The
`listora_dashboard_reviews_` busts were removed entirely — that key has no
reader in Free or Pro (dead code). `listora_review_stats_` keeps
`wp_cache_delete` (its read path IS `wp_cache_get`, correctly paired).

## Steps

### 1. Favorite busts the stat cache
- **Action**: `set_transient( 'listora_dashboard_stats_<uid>', ['favorites'=>999], 60 )`,
  then POST `/listora/v1/favorites` (real REST) as that user.
- **Expect**: HTTP 201 and `get_transient(...)` returns false — next dashboard
  render recomputes; the stat shows the real count, not 999.

### 2. Unfavorite + review create/delete
- **Action**: repeat with DELETE `/favorites/{id}`, then with review
  create/delete.
- **Expect**: transient gone after each write.

### 3. UI smoke
- **Action**: in the browser, favorite a listing from the grid, open the
  dashboard immediately.
- **Expect**: the Favorites stat reflects the new count without waiting 60s.
