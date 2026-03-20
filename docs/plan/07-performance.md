# 07 — Performance & Caching

## Scope

| | Free | Pro |
|---|---|---|
| Custom database tables | Yes | Yes |
| Object cache integration | Yes | Yes |
| Transient caching | Yes | Yes |
| Search index optimization | Yes | Yes |
| Geo query optimization | Yes | Yes |
| Lazy loading assets | Yes | Yes |
| Batch reindex (WP-CLI) | Yes | Yes |
| Elasticsearch integration | — | Future |

---

## Performance Targets

| Metric | Target | At Scale (100K listings) |
|--------|--------|--------------------------|
| Search query | < 200ms | < 500ms |
| Listing detail page | < 150ms TTFB | < 200ms TTFB |
| Map marker load (viewport) | < 300ms | < 500ms |
| Card grid render (20 items) | < 100ms | < 100ms |
| Facet count calculation | < 300ms | < 800ms (cached) |
| REST API response | < 200ms | < 400ms |

---

## Query Architecture

### Search Flow (Hot Path)
```
User searches "Italian restaurant in Manhattan, 3+ bedrooms"
  ↓
1. Check transient cache → key: md5(normalized + sorted args)
  ↓ (cache miss)
2. Phase 1 — Candidate Selection (search_index):
   - WHERE listing_type = 'restaurant' AND status = 'publish'
   - AND MATCH(title, content_text, meta_text) AGAINST('Italian' IN BOOLEAN MODE)
   - AND city = 'Manhattan'
   → Returns ~500 candidate IDs
  ↓
3. Phase 2 — Custom Field Filtering (field_index):
   - WHERE listing_id IN (...candidates...)
   - AND field_key = 'bedrooms' AND numeric_value >= 3
   → Narrows to ~80 matching IDs
  ↓
4. Phase 3 — Sort + Paginate:
   - ORDER BY is_featured DESC, avg_rating DESC
   - LIMIT 20 OFFSET 0
  ↓
5. Phase 4 — Hydrate:
   - WP_Query(['post__in' => $page_ids, 'orderby' => 'post__in'])
   - update_meta_cache('post', $page_ids)
  ↓
6. Phase 5 — Facets (parallel):
   - Facet counts from field_index on candidate set
  ↓
7. Cache results in transient
  ↓
8. Return response
```

### Why This Is Fast
- Phase 1 is a **single-table query** on `listora_search_index` with FULLTEXT index
- Phase 2 uses a dedicated `listora_field_index` table — no JOINs to `wp_postmeta`
- No JOINs to `wp_postmeta` (the #1 bottleneck in every other plugin)
- Phase 4 uses WP's built-in object cache for individual posts
- Subsequent identical searches hit transient cache (step 1)

### Geo Search Flow
```
1. Calculate bounding box from center + radius
2. Pre-filter: WHERE lat BETWEEN ? AND ? AND lng BETWEEN ? AND ?
3. Calculate exact Haversine distance on pre-filtered set
4. Sort by distance
5. Return listing IDs
```

The bounding box pre-filter eliminates 95%+ of rows before the expensive Haversine calculation.

---

## Caching Strategy

### Layer 1: Object Cache (Persistent)

| Data | Cache Key | TTL | Invalidation |
|------|-----------|-----|--------------|
| Listing type registry | `wb_listora_type_registry` | Until flush | Type meta updated |
| Single listing meta bundle | `wb_listora_meta_{post_id}` | 1 hour | `save_post` |
| Category tree per type | `wb_listora_cats_{type_slug}` | Until flush | Term created/deleted |
| Location hierarchy | `wb_listora_locations` | Until flush | Term created/deleted |
| Feature list | `wb_listora_features` | Until flush | Term created/deleted |

**Without persistent object cache (shared hosting):** These degrade to per-request memory cache — still useful for avoiding duplicate queries within a single page load.

### Layer 2: Transients

| Data | Transient Key | TTL | Invalidation |
|------|---------------|-----|--------------|
| Search results | `listora_search_{hash}` | 15 min | Any listing save |
| Facet counts | `listora_facets_{hash}` | 30 min | Any listing save |
| Schema JSON-LD | `listora_schema_{post_id}` | 1 hour | `save_post` |
| Listing count per type | `listora_count_{type}` | 30 min | Any listing save |
| "Open now" status | Not cached | — | Computed per request |

**Selective cache invalidation:**

When a listing is saved, only invalidate transients that could include that listing:
1. Get the listing's type slug and location (city)
2. Delete transients matching: `listora_search_{type}_*` and `listora_search_all_*`
3. Delete facet transients: `listora_facets_{type}_*`
4. Keep transients for OTHER types intact

This prevents the thundering herd problem where saving one restaurant listing invalidates all real estate search caches.

Implementation: Transient keys encode the listing type:
- `listora_search_restaurant_{args_hash}`
- `listora_search_all_{args_hash}` (cross-type searches)
- `listora_facets_restaurant_{args_hash}`

On save of a restaurant listing → delete `listora_search_restaurant_*` and `listora_search_all_*` only.

Note: On sites without persistent object cache, transients go to `wp_options` table. For sites with 100K+ listings generating many unique searches, this can bloat `wp_options`. Mitigation: limit transient variants (normalize search args, round lat/lng to 3 decimal places).

### Layer 3: Page Cache Compatibility

- All dynamic content loaded via Interactivity API (client-side state updates)
- REST API responses include proper `Cache-Control` headers
- Search results pages are NOT page-cached (dynamic)
- Listing detail pages CAN be page-cached (invalidated on save)
- Set `Vary: Cookie` on authenticated responses

---

## Asset Loading

### Block-Based Loading
Each block registers assets via `block.json` — CSS/JS only load when the block is on the page:
```json
{
  "style": "file:./style.css",
  "viewScriptModule": "file:./view.js",
  "editorScript": "file:./edit.js"
}
```

### Map Library
- Leaflet JS (~40KB gzipped) loaded ONLY when `listora/listing-map` block is present
- Not loaded on listing detail if map is disabled for that type
- Google Maps JS (Pro) loaded from CDN, deferred

### Shared Store
- Interactivity API store (`listora/directory`) loaded when ANY interactive block is present
- Tree-shakeable — only imported actions/state are included

### Admin Assets
- Admin CSS/JS loaded ONLY on Listora admin pages
- jQuery UI Sortable loaded ONLY on listing type editor

---

## Database Optimization

### Index Design

**Search index FULLTEXT:**
```sql
FULLTEXT idx_search (title, content_text, meta_text)
```
MySQL's built-in FULLTEXT handles relevance scoring, boolean mode, and partial matching. No external search engine needed for < 500K listings.

**Composite indexes for common queries:**
```sql
-- "Featured first, then by rating" (default sort)
KEY idx_featured_rating (is_featured DESC, avg_rating DESC)

-- "By type and status" (most common filter)
KEY idx_type_status (listing_type, status)

-- "By location" (geo search pre-filter)
KEY idx_lat_lng (lat, lng)
```

### Query Patterns to Avoid
- `SELECT *` — always specify needed columns
- `ORDER BY RAND()` — use pre-computed random scores
- Unbounded queries — always `LIMIT`
- `LIKE '%keyword%'` — use FULLTEXT instead
- Multiple postmeta JOINs — use search_index table

### Batch Operations
- Reindex: 500 listings per batch, with progress tracking
- CSV import: 100 rows per batch, with progress bar
- Search index rebuild: truncate + batch insert (faster than update)

---

## Scaling Tiers

### Tier 1: 0-10,000 Listings (Most Sites)
- Everything works out of box on shared hosting
- No special optimization needed
- Transient cache is sufficient

### Tier 2: 10,000-100,000 Listings
- Recommend persistent object cache (Redis/Memcached)
- Recommend dedicated database server or managed hosting
- FULLTEXT search still performant
- May need to increase MySQL `innodb_buffer_pool_size`

### Tier 3: 100,000+ Listings
- Persistent object cache required
- Consider MySQL read replicas for search queries
- Consider Elasticsearch for search (Pro future feature)
- Geohash-based clustering for map (don't load 100K markers)
- Aggressive transient caching with longer TTLs
- Consider CDN for REST API responses

---

## Monitoring

### WP-CLI Health Check
```bash
wp listora stats
```
Output:
```
Listings:     125,432 (published: 118,201, draft: 4,231, pending: 3,000)
Search index: 118,201 rows (synced)
Geo index:    115,890 rows (2,311 missing coordinates)
Reviews:      45,123
Favorites:    89,456
DB size:      ~180MB total
Cache hit rate: 78% (object cache)
```

### Admin Dashboard Widget
- Show index sync status
- Alert if > 1% of listings are missing from search index
- Show last reindex timestamp
- Quick "Reindex Now" button

---

## Shared Hosting Considerations

Most directory sites start on shared hosting ($5-15/mo). The plugin MUST work well here:

| Constraint | How We Handle It |
|-----------|------------------|
| No persistent object cache | Per-request memory cache + transients |
| Low PHP memory (128MB) | Batch processing, never load all listings at once |
| Slow MySQL | Single-table queries, minimal JOINs |
| No WP-CLI access | Admin "Reindex" button, auto-reindex on save |
| PHP time limit (30s) | Batch reindex with AJAX continuation |
| No cron reliability | Fallback to `wp_cron` with `DISABLE_WP_CRON` detection |
