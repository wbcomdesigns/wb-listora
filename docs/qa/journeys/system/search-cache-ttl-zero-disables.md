---
journey: search-cache-ttl-zero-disables
plugin: wb-listora
priority: high
roles: [admin, editor, system]
covers: [BC-10203769600, Cache-ttl, search_cache_ttl, transient-expiry, migration-1.6.0]
prerequisites:
  - "Site reachable at $SITE_URL; admin access to Listora > Settings > Advanced"
  - "At least one listing type with published listings ({type})"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [wp-admin default]
supersedes: []
fails_on: BC-10203769600
site_profile: required
---

# A search cache TTL of 0 disables caching instead of caching forever

## Why
A TTL of 0 once wrote transients with no expiry, so search results never refreshed. The setting is admin-only and the cache is not per user, so there is no owner/member ladder.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Record the original TTL (`wp --path=$WP_PATH option pluck wb_listora_settings search_cache_ttl`, default 15) to restore.
- Control: Listora > Settings > Advanced > Cache > "Search results TTL" (`#search_cache_ttl`, `wb_listora_settings[search_cache_ttl]`, minutes, bounds 0-120; `includes/admin/class-advanced-settings-tab.php:205-211`, bounds `includes/admin/class-settings-page.php:140`). Help text: "Set to 0 to disable caching for search queries." `Cache::ttl()` (`includes/core/class-cache.php:125`) returns 0 for non-positive and the engine skips the cache on read (`includes/search/class-search-engine.php:68`) and write (`:1878-1884`).
- 2.0.0 removed `facet_cache_ttl` and the `Facets` class (a migration clears the stored key); no facet-cache step remains. The Advanced tab block description still says "cached search results and facet counts" (stale copy, to correct).

## Steps

### 1. TTL 0 writes nothing
- **Action**: set Search results TTL to `0`, save. Then:
  ```bash
  wp --path=$WP_PATH eval 'global $wpdb; $q="SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE \"\\_transient\\_listora\\_search\\_%\"";
  $b=(int)$wpdb->get_var($q);
  wb_listora_service("search_engine")->search(["type"=>"{type}","per_page"=>5,"keyword"=>"QA-zero"]);
  echo "before=$b after=".(int)$wpdb->get_var($q);'
  ```
- **Expect**: `before` equals `after`, and 0 rows exist without a `_transient_timeout_` row.

### 2. TTL 0 does not SERVE a permanent row
- **Action**: still at 0, plant a permanent entry at the REAL key (a plain probe key proves nothing) and search with the same args:
  ```php
  $e = wb_listora_service('search_engine'); $a = ['type'=>'{type}','per_page'=>5,'keyword'=>'QA-probe'];
  $p = new ReflectionMethod($e,'parse_args'); $p->setAccessible(true);
  $k = new ReflectionMethod($e,'build_cache_key'); $k->setAccessible(true);
  $key = $k->invoke($e, $p->invoke($e,$a));
  set_transient($key, ['listing_ids'=>[999999],'total'=>1,'pages'=>1,'facets'=>[],'distances'=>[]], 0);
  $r = $e->search($a);
  ```
- **Expect**: fresh results; `999999` NOT in `$r['listing_ids']`.

### 3. A real TTL caches WITH an expiry (positive control)
- **Action**: set TTL `15`, repeat the same search (planted row still there).
- **Expect**: `999999` IS served (cache read works); a new search adds rows; the "permanent rows" query (cached rows with no `_transient_timeout_` partner, SQL in `includes/db/class-migrator.php:179-198`) returns 0.

### 4. Boundary values
- **Action**: save TTL `-1`, `121`, `abc`, empty, `1.5`.
- **Expect**: stored value clamps to 0-120 as an integer (negative -> 0, 121 -> 120, `abc`/empty -> default or 0, named in the run notes); never stores a non-numeric string. Read back with `option pluck`.

### 5. Migration 1.6.0 swept the old rows
- **Action** (upgrade path): plant 3 permanent `_transient_listora_search_*` rows, reset the stored DB version to before 1.6.0 on a throwaway copy, load wp-admin.
- **Expect**: 0 `_transient_listora_search_%` rows lacking a timeout row; healthy cached entries (with a timeout row) survive.

### 6. Role ladder
- Admin (control) saves. `{editor}` cannot reach `admin.php?page=listora-settings` (capability `manage_listora_settings`): redirect/403 and `POST options.php` rejected. Guest: login redirect.

## Role ladder
See step 6.

## Pass criteria
1. TTL 0 writes no search transients and serves no planted permanent row.
2. A positive TTL caches with an expiry and the permanent-rows query is empty.
3. Bounds clamp; the migration leaves no permanent rows.

## Fail diagnostics
| Symptom | File |
|---|---|
| Rows written at TTL 0 | `includes/search/class-search-engine.php:1878-1884` |
| Planted row served at 0 | read gate `class-search-engine.php:68` |
| Wrong unit/zero handling | `includes/core/class-cache.php:125-133` |
| Permanent rows after upgrade | `includes/db/class-migrator.php:179-198` |

## Not verified
Steps 4 (boundary), 5 (migration on an upgrade path) and the non-admin half of step 6 were not run; steps 1-3 ran via `wp eval-file`.

## Cleanup
Restore the original TTL; `delete_transient($key)` for the probe key.
