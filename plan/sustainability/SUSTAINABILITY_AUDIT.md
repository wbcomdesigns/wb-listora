# WB Listora — Sustainability & Scale Audit

**Scope:** WB Listora Free 1.0.0 (with Pro 1.x context). Two questions:

1. Will this skeleton stay healthy for 5 years of feature work without rewrites?
2. Will it survive 100k+ listings, millions of reviews, tens of millions of audit-log rows?

**Methodology:** read DDL, search engine, indexer, REST controllers, plugin orchestrator, Pro coupling points.

---

## Section A — Skeleton Health

| # | Concern | Grade | Evidence |
|---|---|---|---|
| 1 | Class organization (PSR-4, SRP, depth) | **B+** | Namespaces map cleanly to dirs (`WBListora\Core\*`, `WBListora\REST\*`, `WBListora\Search\*`). Classes 200-700 lines mostly. Largest: `class-listings-controller.php` 1,378 lines and `class-listing-type-defaults.php` 1,636 lines — already at the size where they should be split. No deep inheritance — only `Listings_Controller extends WP_REST_Posts_Controller` (1 level, intentional). |
| 2 | Boundary discipline (REST → service → data) | **C** | REST controllers do their own SQL freely. `Dashboard_Controller::get_stats` runs three raw `$wpdb` queries against posts/reviews/favorites at `class-dashboard-controller.php:198-223`. There is **no service layer** — `Search_Engine` is the only thing close, and even it mixes phase-orchestration with raw SQL. Result: every controller is a god-object. |
| 3 | Hook contract | **A-** | Disciplined `before_/after_` write hook pairs (CLAUDE.md lists them). REST `wb_listora_rest_prepare_*` filters on every endpoint. Block render hooks consistently named (`before_*`, `after_*`, `*_query_args`). 145 hook calls across `includes/`. The contract is the single best thing in the plugin. |
| 4 | Block architecture | **B+** | All 11 blocks `apiVersion: 3`, 20 standardized attributes, shared `Block_CSS` per-instance scoping. `viewScriptModule` pattern is consistent (post-recent migration). One quirk: blocks are namespaced `listora/*` but a comment in `class-plugin.php:162` checks for both `listora/` and `wb-listora/` — sign of a legacy migration not fully completed. |
| 5 | Template override system | **B** | Real, not a marketing claim. `wb_listora_locate_template()` is invoked in 11 block render.php files plus 14 email templates and 2 theme files = 27 actual override points (`grep` count: 81 references). Themes can drop into `{theme}/wb-listora/...`. Caveat: dashboard, search, and submission render.php files are large (300+ lines) and inline a lot of HTML that bypasses the template system — themes can override the wrapper but not internal pieces. |
| 6 | Error handling | **B** | REST settings controller uses 0 `WP_Error` — concerning. Listings (37), reviews (28), claims (22), submission (21) use it well. A few CRUD paths still `return false`. Consistency level: ~80%. |
| 7 | Coupling: Pro into Free | **D** | Pro reaches **directly** into Free classes 25+ times: `\WBListora\Core\Listing_Type_Registry::instance()`, `\WBListora\Core\Meta_Handler::set_value()`, `\WBListora\Core\Featured::is_featured()`, `\WBListora\Core\Services::get_services()`, `new \WBListora\Search\Search_Engine()`, `new \WBListora\Search\Search_Indexer()`. (See `class-google-places.php:308,585,594,599,611,620`, `class-comparison.php:1044,1053`, `class-pricing-plans.php:711`, `class-advanced-search.php:294`, `class-infinite-scroll.php:295`, `class-visual-importer.php:384,415,499`.) Free has no published service interface. Renaming any Free class breaks Pro silently. |
| 8 | Static state / singletons | **B+** | Three singletons: `Plugin`, `Listing_Type_Registry`, `Field_Registry`. Plus `static $settings = null` cache in `wb_listora_get_setting()` — global cache invalidated only by process restart. Plus `static $rendering` re-entry guard in `inject_listing_detail()`. Modest. Tests can still construct most classes directly. |
| 9 | Function naming | **A** | Helpers consistently `wb_listora_*` prefixed. Constants `WB_LISTORA_*`. Hooks `wb_listora_*`. No drift. |
| 10 | PSR-4 autoload | **A** | Composer PSR-4 + a hand-rolled fallback (`wb_listora_autoload`) in `wb-listora.php:104-131`. Kebab-case file naming. Three `require_once` calls remain in the entry file (`class-template-helpers.php`, `class-features.php`, `submission-field-renderer.php`) — only one (`submission-field-renderer.php`) is procedural code that genuinely can't be autoloaded. Acceptable. |

**Skeleton verdict:** healthy spine, brittle joints. The hook contract is excellent and will sustain extension for years. The big risks are (a) controllers as god-objects (no service layer), and (b) Pro coupling that will make any Free refactor cause Pro regressions.

---

## Section B — Database & Scale Audit

### Per-table evaluation

| Table | Growth at 100k listings | PK | Read paths | Write paths | Verdict |
|---|---|---|---|---|---|
| `listora_geo` | 100k rows | `listing_id` | bbox `lat BETWEEN/lng BETWEEN`, geohash equality, city, postal | 1 row per listing | **OK.** Composite `idx_lat_lng` covers bbox. Geohash indexed but never queried (see #17). |
| `listora_search_index` | 100k rows | `listing_id` | FULLTEXT match, type+status, featured+rating, price, author, lat/lng, city/country | indexer | **OK** for 100k. FULLTEXT index exists. Risk: at 1M+, MyISAM-style FULLTEXT on InnoDB performs reasonably but `IN BOOLEAN MODE` queries with no LIMIT (see Phase 1 below) return full result sets. |
| `listora_field_index` | ~2M (avg 20 filterable fields × 100k listings) | `(listing_id, field_key, field_value)` | per-field equality, range scans | bulk insert per-listing | **At-risk.** PK is composite but unique on listing+key+value — fine for de-dup. Missing single-column index on `listing_id` alone for the `WHERE listing_id IN (...)` pattern in phase_2_field_filter. The composite PK can serve it via leading column, so it's OK on InnoDB. Will be the slowest table at scale. |
| `listora_reviews` | 1M+ at scale | `id` | `listing_id+status`, user, rating, created_at | per-review | **Good.** 4 indexes plus UNIQUE `(user_id, listing_id)`. |
| `listora_review_votes` | 10M+ (one row per helpful click) | `(user_id, review_id)` | review lookups | per-vote | **OK.** PK supports the dominant query. |
| `listora_favorites` | 5M+ | `(user_id, listing_id)` | listing aggregation, user collections | per-favorite | **OK.** |
| `listora_claims` | low volume | `id` | listing, user, status | per-claim | **OK.** |
| `listora_hours` | 700k (7 days × 100k) | `(listing_id, day_of_week)` | open-now query | per-listing | **OK.** Index `idx_open` on (day_of_week, open_time, close_time, is_closed) is well-targeted. |
| `listora_analytics` | huge — 100k listings × 365 days × 5 event types ≈ 180M rows/year | `id` + UNIQUE on `(listing_id, event_type, event_date)` | dashboard charts | per page-view | **High risk.** No partitioning. No retention cron in Free. Pro populates this without any cleanup. Will be the biggest table on a real site. |
| `listora_payments` | low volume | `id` | by status, gateway, user | per-transaction | **OK.** |
| `listora_services` | ~5 per listing → 500k | `id` | by listing+status, sort | per-service | **OK.** |
| `listora_audit_log` (Pro) | 90M rows at 1M actions/day | `id` | filtered queries by user, action, object, date | per-action | **At-risk.** See #13. |

### Specific findings

**11. Two-phase search engine (`class-search-engine.php`).** At 100k listings:

- *Empty keyword + 5 filters + 25km radius:* `phase_1_candidates` issues one `SELECT` against `search_index` with bbox `BETWEEN`. Fine — index covers bbox. **But** the result set is **unbounded** — there is no `LIMIT`. If 30k listings are in the bbox, all 30k come back. Then `Geo_Query::haversine_distance` runs on every row in PHP (line 268-289) — that's 30k `sin/cos/asin` calls per request. Then `phase_2_field_filter` issues an `IN (..30k ids..)` query — MySQL has a `max_allowed_packet` ceiling and InnoDB plans for IN-lists degrade rapidly past ~5k items.
- *Keyword "pizza" + city filter at 1M listings:* FULLTEXT match on title/content/meta returns all matches (no `LIMIT`), then PHP `usort` sorts the full set. At 50k matches, that's 50k objects in PHP memory.
- *Pagination to page 100 (per_page=20):* `array_slice($sorted_ids, 1980, 20)` after sorting **all** 50k. So the engine effectively does an "in-PHP OFFSET" — same O(N) cost as MySQL OFFSET, just in app-space.

**The engine is correctness-first, scale-second.** Designed for sites with <10k matching candidates. It will work at 100k *if* most queries narrow via type/category early. It will not work at 1M.

**12. Reindexing (`Search_Indexer::batch_reindex`).** Loops with `WP_Query` 500 at a time, calling `index_listing` per row. Each `index_listing` does 1 `wpdb->replace`, 1 delete, N `wpdb->insert`s, 1 delete + N inserts for hours, plus a `Featured::is_featured()` check. For 100k listings: ~100k `replace`s + ~2M individual inserts to `field_index`. **Not batched, no transaction, no `INSERT … VALUES (), ()`.** Estimated runtime: 60-90 minutes on a small VPS. Calls `wp_cache_flush()` once per batch — that's good. Doesn't lock tables but will flood the slow query log on a busy site.

**13. Audit log retention (`class-audit-log.php:882-901`).** **This part is well-done.** Cleanup uses `DELETE … WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY) LIMIT 1000` looped until the batch returns less than 1000. `idx_created` covers the WHERE. **However:** the cleanup is registered with `wp_schedule_event(..., 'daily', ...)` which only runs when WP-Cron triggers — on a busy site cron is unreliable and the cleanup may skip days, letting the table balloon. The default retention is 90 days; at 1M actions/day that's 90M rows, with 1k batches per day cleanup deletes 1k/day from the front and 1M arrive at the back — **net growth, not cleanup.** Cleanup needs to delete in larger batches or run hourly.

**14. Stat queries (`Dashboard_Controller::get_stats`).** Uses `wp_cache_get/set` with `HOUR_IN_SECONDS` TTL. Three queries: posts grouped by status, reviews by user, favorites by user. At 100k listings the user-filtered counts are fast (indexed). With persistent object cache (Redis) this scales fine. Without it the cache is request-scoped only — every dashboard load runs the queries.

**15. REST pagination — cursor vs offset.** Offset everywhere. `Dashboard_Controller`, `Reviews_Controller`, `Audit_Log::query_log` all use `LIMIT %d OFFSET %d`. None implement cursor pagination. Page 100 of audit log = `OFFSET 4900` — InnoDB still scans the leading 4900 rows. At 100M rows page 1000 is a death spiral.

**16. Object cache compatibility.** Mixed. Search engine uses **transients** (option-table-backed when no persistent cache) — fine when Redis is plugged in (transients route through `wp_cache_*` then). Dashboard uses `wp_cache_*` directly. The cache invalidation in `Search_Indexer::invalidate_caches()` runs `DELETE FROM wp_options WHERE option_name LIKE '_transient_listora_search_%'` (lines 461-473) — **this query bypasses the object cache and hits the options table directly, which on a 100k-listing site with persistent cache is both wrong (transients aren't there) and slow (options table LIKE scan).** Bug.

**17. Geo indexing.** `idx_geohash` exists on `listora_geo` (activator line 128), but **no code path queries by geohash**. `Geo_Query::find_nearby` and `Search_Engine::phase_1_candidates` both use bbox prefilter (good), then exact Haversine in PHP (not in SQL — good for index use). The geohash column is dead weight. Bbox query plan is correct.

**18. FULLTEXT min_word_len.** No code configures `innodb_ft_min_token_size` (default 3) or `ft_min_word_len` (default 4). 2-letter searches like "NY" silently return zero results. No documentation of this.

**19. WP_Query meta_query usage.** Limited but present:
- `class-featured.php:80` — daily cron sweep, single query.
- `class-listing-columns.php:332-337` — admin sort column, fires only on `wp-admin/edit.php?post_type=listora_listing`.
- `class-expiration-cron.php:66, 110, 144, 194` — twice-daily cron sweeps.
None on the hot path. **The architecture genuinely uses `search_index` for searches, not post_meta.** This is the right choice and was avoided as a GeoDirectory-style mistake.

**20. Schema migrations (`class-migrator.php`).** The `Migrator` class registers versioned migrations. Both 1.0.0 and 1.1.0 just call `Activator::activate()` which re-runs `dbDelta`. **`dbDelta` cannot ALTER TABLE on a 10M row table without locking** — it issues a plain `ALTER TABLE` which on InnoDB before 5.6 was a full table rebuild, and even with `ALGORITHM=INPLACE` defaults it can lock for minutes. There is no `pt-online-schema-change` style mechanism, no chunked column rewrite, no `INSTANT` column hint. A future column add to `audit_log` at 90M rows will brown out the site.

---

## Section C — Top 10 risks for the next 5 years

| # | Risk | Why it'll bite | Trigger | Hardening (file:line, effort) |
|---|---|---|---|---|
| 1 | **Pro coupling to Free class internals** | 25+ direct class references, no published service interface. Renaming `Meta_Handler::set_value` or `Search_Indexer` constructor breaks Pro silently. Fixing one Free bug requires coordinated Pro release. | Day 1 of any Free refactor. | Define `WBListora\Contracts\*` interfaces, route Pro through `wb_listora_service('meta_handler')` lookup. Audit `class-google-places.php`, `class-visual-importer.php`, `class-comparison.php`. **3-5 days.** |
| 2 | **Audit log unbounded growth on busy sites** | Daily cron + 1k batch limit can't keep up with 1M+ actions/day. WP-Cron is missed-run-prone. Site grows past Redis memory once table hits 100M rows because every page loads 5 audit insertions. | 30 days after a busy site enables audit_log feature. | Switch to Action Scheduler (`as_schedule_recurring_action`). Increase batch to 10k. Add hourly schedule option. `class-audit-log.php:46-49`. **4 hrs.** |
| 3 | **Search engine returns unbounded result sets** | Phase 1 has no SQL `LIMIT`. At 100k listings with weak filters, the engine pulls 30k rows into PHP, sorts them, then array_slices. Memory, CPU, and request time scale linearly with result-set size, not page size. | First popular site that crosses 50k listings. | Add `LIMIT N` (configurable, default 5000) to phase_1 query. Move sort into SQL `ORDER BY` for non-distance sorts. Add `ORDER BY relevance LIMIT %d` for FULLTEXT. `class-search-engine.php:240-246`. **2 days.** |
| 4 | **Cache invalidation hits options table** | `Search_Indexer::invalidate_caches()` runs `DELETE FROM wp_options WHERE option_name LIKE '_transient_listora_search_%'` on every listing save. With Redis active, transients aren't there → no cache cleared. Without Redis, the LIKE scan on a 50k-row options table is slow. Plus it generates 4 DB writes on every save. | Already broken on Redis sites. | Use `wp_cache_flush_group('listora_search')` and add a cache-version int to keys (incrementing on save) for sites without a real flush. `class-search-indexer.php:446-475`. **3 hrs.** |
| 5 | **Reindex not chunked at SQL level** | 100k-listing reindex performs ~2M individual `wpdb->insert` calls into `field_index`. Each is a round-trip. Estimated 1-2 hours, locks `field_index` and bloats binlog. | Site re-import or manual rebuild on a real-data tenant. | Refactor `Search_Indexer::update_field_index()` to build VALUES tuples and run one multi-row `INSERT … VALUES (), (), …` per 500 rows. Wrap in transaction. `class-search-indexer.php:238-317`. **1 day.** |
| 6 | **Schema migrations use dbDelta on big tables** | Every `Migrator::migrate_*` is `Activator::activate()` which re-runs `dbDelta`. ALTER TABLE on a 90M-row audit_log will lock for minutes. No progressive migration framework. | The first time a column is added to a popular table post-launch. | Build a migration runner that detects table size, uses `ALGORITHM=INPLACE, LOCK=NONE` for safe ALTERs and a row-copy pattern (gh-ost / pt-osc style) for dangerous ones. Alternatively gate big ALTERs behind an admin-confirmed maintenance task. `class-migrator.php:73`. **3 days.** |
| 7 | **REST pagination is OFFSET everywhere** | At page 1000+ on audit_log, dashboard listings, reviews, MySQL still scans the skipped rows. UI doesn't go that deep, but admin filters + CSV exports do. | Reports/exports on a year-old busy site. | Add cursor pagination to `Audit_Log::query_log` and `Reviews_Controller::get_items` — token = base64(`created_at,id`). `class-audit-log.php:805-873`, `class-reviews-controller.php:204-340`. **1 day each.** |
| 8 | **Field_index PK shape limits queries** | PK is `(listing_id, field_key, field_value)`. Queries use `WHERE listing_id IN (..) AND field_key = %s AND field_value = %s` — covered. But future "find listings where field_value = X across all listings" (admin search, bulk update) has no index. `idx_field_value` exists but is not on `(listing_value, listing_id)`. | First admin "find listings with cuisine=Italian" report. | Add `KEY idx_value_listing (field_key, field_value, listing_id)`. `class-activator.php:175-178`. **30 min.** |
| 9 | **Settings cached in static + read frequently** | `wb_listora_get_setting()` uses `static $settings` — populated once per request. Fine. **But** any direct `update_option('wb_listora_settings', …)` in the same request will not be reflected. Subtle bug-class, will surface in tests and admin AJAX. | Within first 6 months of feature work. | Make the static a class with explicit invalidation hook on `update_option_wb_listora_settings`. `wb-listora.php:205-219`. **1 hr.** |
| 10 | **Analytics table has no retention** | Pro populates it on every page-view. 180M rows/year on a busy site. No daily cleanup, no rollup table. Reading dashboards becomes unbounded scan. | 6-12 months after a popular site goes live. | Add daily cron in Pro to roll daily granularity into monthly past 90 days, drop raw rows >180 days. `class-analytics.php`. **1 day.** |

---

## Section D — Concrete hardening recommendations (ROI-ranked)

| # | Change | File | Effort | Pre-launch | Why |
|---|---|---|---|---|---|
| 1 | Define `\WBListora\Contracts\` interfaces for Meta_Handler, Listing_Type_Registry, Search_Engine, Search_Indexer; expose via `wb_listora_service('search.engine')` lookup | new `includes/contracts/`, register in `class-plugin.php:65` | 3-5 d | **Pre** | Every other refactor depends on this. Pro currently calls Free class names directly 25× — first Free rename = Pro fatal. |
| 2 | Add SQL `LIMIT` to `phase_1_candidates` | `class-search-engine.php:240` (add `LIMIT %d` configurable via `wb_listora_search_max_candidates` filter, default 5000) | 4 h | **Pre** | Single biggest scale risk for the search hot path. |
| 3 | Cursor pagination for audit log + reviews | `class-audit-log.php:805`, `class-reviews-controller.php:204` (cursor token = base64 of `created_at_id`, decode in WHERE clause) | 1 d each | Post | Once data is big, you can't switch — front-end has to know. Ship with both. |
| 4 | Object-cache-aware invalidation | `class-search-indexer.php:446` — replace LIKE-DELETE with `wp_cache_set('listora_search_version', time(), 'listora')`; cache key prepends version | 3 h | **Pre** | Already broken on Redis sites. |
| 5 | Bulk insert in indexer + transaction | `class-search-indexer.php:238` — accumulate field_index rows, single `INSERT … VALUES` per 500 | 1 d | Post | Reduces full-rebuild time by 10-20×. |
| 6 | Replace WP-Cron with Action Scheduler for high-volume jobs | `class-audit-log.php:48`, `class-expiration-cron.php:26-34` | 1 d | Post | WP-Cron drops jobs on busy sites. AS persists to DB and retries. |
| 7 | Add `idx_value_listing` to field_index; add LIMIT to FULLTEXT MATCH | `class-activator.php:175`, `class-search-engine.php:228-232` | 1 h | **Pre** | Cheap win, blocks future regression. |
| 8 | Settings observer pattern (invalidate `static $settings`) | `wb-listora.php:205` | 1 h | **Pre** | Subtle correctness bug class. |
| 9 | Analytics retention cron (Pro) — rollup + cleanup | new `class-analytics-retention.php` | 1 d | Post (Pro) | Prevents Pro from being the reason sites die. |
| 10 | Migration framework with INPLACE + chunked rewrites | `class-migrator.php:73` | 2-3 d | Post | Buys safe ALTERs for the next 5 years of schema work. |

---

## Section E — Architectural strengths (don't break these)

1. **Denormalized `search_index` design.** Independent of post_meta. Avoids the GeoDirectory mistake entirely. Sustainable for years.
2. **Hook contract is exemplary.** `before_/after_` write pairs + `wb_listora_rest_prepare_*` filters mean Pro/extensions extend without touching Free internals — *if* Pro actually used them. The contract exists, Pro just ignores half of it.
3. **PSR-4 autoload + kebab-case discipline.** Consistent and toolchain-friendly.
4. **Block standardization.** 20 standardized attrs across 11 blocks via shared schemas means new blocks ship in hours, not days. The shared `Block_CSS` per-instance scoping is a clean solution.
5. **Bbox-prefilter geo strategy.** Uses indexed `lat BETWEEN/lng BETWEEN`, then exact Haversine in PHP only on shortlisted rows. Right call.
6. **Versioned `Migrator`.** Even though current implementations are stubs, the framework exists.
7. **REST controllers extend WP core.** `Listings_Controller extends WP_REST_Posts_Controller` reuses years of WordPress hardening for free.
8. **Capability-driven permissions, not role checks.** Future-proof multisite/membership integrations.

---

## TL;DR for product

- **Skeleton: B+.** Will scale to 10k-listing sites today. Will need 4-5 weeks of focused hardening to reach 100k. Will need a real service-interface refactor to reach 500k.
- **Worst single risk:** Pro reaching into Free class internals. Fix in next sprint, before any Free refactor lands.
- **Worst scale risk:** unbounded result sets in phase 1 of search. Add `LIMIT` before launch.
- **Cheapest wins:** items 4, 7, 8, 9 in Section D — all under 4 hours each, all eliminate active or near-future bug classes.
