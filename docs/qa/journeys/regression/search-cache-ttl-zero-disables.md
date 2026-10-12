---
journey: search-cache-ttl-zero-disables
plugin: wb-listora
roles: [admin, system]
priority: high
covers: [BC-10203769600, Cache::ttl, search_cache_ttl, transient-expiry, migration-1.6.0]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Admin access to Listora > Settings > Advanced"
  - "At least one listing type with published listings"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
changed: true
---

# A search cache TTL of 0 disables caching instead of caching forever

Verified via: `wp eval-file` (TTL flipped 0 / 15 and restored to the original `15`). Single-role/system behaviour: the setting is admin-only and the cache is not per-user; there is no owner/member ladder. Admin-only control: a non-admin (qa_editor) cannot reach `admin.php?page=listora-settings` (capability `manage_listora_settings`).

The control is **Listora > Settings > Advanced > Cache > "Search results TTL"** (`#search_cache_ttl`, option `wb_listora_settings[search_cache_ttl]`, minutes, bounds 0-120, default 15; `includes/admin/class-advanced-settings-tab.php:205-211`, bounds `includes/admin/class-settings-page.php:140`). Help text: "Set to 0 to disable caching for search queries." `Cache::ttl()` (`includes/core/class-cache.php:125`) returns 0 for a non-positive value and the engine then skips the cache on read (`includes/search/class-search-engine.php:68`) and write (`:1878-1884`).

2.0.0 removed the `facet_cache_ttl` setting and the `Facets` class (a migration clears the stored key), so there is no separate facet-cache step any more. The Advanced tab block description still says "cached search results and facet counts" (stale copy, reported).

## Steps

### 1. TTL 0 writes nothing
- **Action**: set Search results TTL to `0`, save. Then:
  ```bash
  wp eval 'global $wpdb; $q="SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE \"\\_transient\\_listora\\_search\\_%\"";
  $b=(int)$wpdb->get_var($q);
  wb_listora_service("search_engine")->search(["type"=>"restaurant","per_page"=>5,"keyword"=>"QA-J1-zero"]);
  echo "before=$b after=".(int)$wpdb->get_var($q);'
  ```
- **Expect**: `before` equals `after`. Observed 233 = 233, and 0 rows without a `_transient_timeout_` row.

### 2. TTL 0 does not SERVE a permanent row
- **Action**: still at 0, plant a permanent entry at the REAL key (a plain probe key proves nothing) and search with the same args:
  ```php
  $e = wb_listora_service('search_engine'); $a = ['type'=>'restaurant','per_page'=>5,'keyword'=>'QA-J1-probe'];
  $p = new ReflectionMethod($e,'parse_args'); $p->setAccessible(true);
  $k = new ReflectionMethod($e,'build_cache_key'); $k->setAccessible(true);
  $key = $k->invoke($e, $p->invoke($e,$a));
  set_transient($key, ['listing_ids'=>[999999],'total'=>1,'pages'=>1,'facets'=>[],'distances'=>[]], 0);
  $r = $e->search($a);   // expect 999999 NOT in $r['listing_ids']
  ```
- **Expect**: fresh results (observed: planted value ignored).

### 3. A real TTL caches WITH an expiry (positive control)
- **Action**: set TTL `15`, repeat the same search (planted row still there).
- **Expect**: `999999` IS served (cache read works), a new search adds rows, and the "permanent rows" query (cached rows with no `_transient_timeout_` partner, SQL in the migration at `includes/db/class-migrator.php:179-198`) returns 0.

### 4. Migration 1.6.0 swept the old rows
- **Expect** on an upgraded site: 0 `_transient_listora_search_%` rows lacking a timeout row; healthy cached entries (with a timeout row) survive the sweep.

### Cleanup
Restore the original TTL (15). `delete_transient($key)` for the probe key.

## Fail diagnostics
| Symptom | File |
|---|---|
| Rows written at TTL 0 | `class-search-engine.php:1878-1884` |
| Planted row served at 0 | read gate `class-search-engine.php:68` |
| Wrong unit/zero handling | `class-cache.php:125-133` |
