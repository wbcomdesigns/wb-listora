# 06 — Database Architecture

## Scope

| | Free | Pro |
|---|---|---|
| All 13 custom tables | Yes | Yes |
| Search index with FULLTEXT | Yes | Yes |
| Geo index with spatial queries | Yes | Yes |
| Reviews table | Yes | Yes + multi-criteria columns |
| Favorites table | Yes | Yes + collections |
| Claims table | Yes | Yes |
| Hours table | Yes | Yes |
| Analytics table | — | Yes |
| Versioned migrations | Yes | Yes |

---

## Design Principles

1. **Canonical data lives in WP** — Posts, postmeta, terms are the source of truth
2. **Custom tables are regenerable indexes** — Can be rebuilt from WP data via `wp listora reindex`
3. **Optimize for read-heavy queries** — Directories are 99% reads, 1% writes
4. **Single-table queries** — Avoid JOINs for common operations (search, geo, listings)
5. **WordPress compatible** — Use `$wpdb`, `dbDelta()`, respect table prefix

---

## Table Schemas

### 1. `{prefix}listora_geo` — Geolocation Index

**Purpose:** Fast spatial queries (nearby, radius, bounding box, "near me")

```sql
CREATE TABLE {prefix}listora_geo (
    listing_id   BIGINT(20) UNSIGNED NOT NULL,
    lat          DECIMAL(10,7) NOT NULL DEFAULT 0,
    lng          DECIMAL(10,7) NOT NULL DEFAULT 0,
    address      VARCHAR(500) NOT NULL DEFAULT '',
    city         VARCHAR(200) NOT NULL DEFAULT '',
    state        VARCHAR(200) NOT NULL DEFAULT '',
    country      VARCHAR(100) NOT NULL DEFAULT '',
    postal_code  VARCHAR(20) NOT NULL DEFAULT '',
    geohash      VARCHAR(12) NOT NULL DEFAULT '',
    timezone     VARCHAR(50) NOT NULL DEFAULT '',
    PRIMARY KEY  (listing_id),
    KEY idx_lat_lng (lat, lng),
    KEY idx_city (city),
    KEY idx_country_state (country, state),
    KEY idx_geohash (geohash),
    KEY idx_postal (postal_code)
) {charset_collate};
```

**Population:** On `save_post_listora_listing`, extract `_listora_address` (map_location field), geocode if needed, insert/update row.

**Geohash:** Computed from lat/lng. Enables efficient proximity clustering and "nearby" queries without distance calculation on every row.

---

### 2. `{prefix}listora_search_index` — Denormalized Search Index

**Purpose:** Fast FULLTEXT + faceted search without touching wp_posts/wp_postmeta

```sql
CREATE TABLE {prefix}listora_search_index (
    listing_id    BIGINT(20) UNSIGNED NOT NULL,
    listing_type  VARCHAR(50) NOT NULL DEFAULT '',
    status        VARCHAR(20) NOT NULL DEFAULT 'publish',
    title         VARCHAR(500) NOT NULL DEFAULT '',
    content_text  TEXT NOT NULL,
    meta_text     TEXT NOT NULL,
    avg_rating    DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    review_count  INT(11) NOT NULL DEFAULT 0,
    is_featured   TINYINT(1) NOT NULL DEFAULT 0,
    is_verified   TINYINT(1) NOT NULL DEFAULT 0,
    is_claimed    TINYINT(1) NOT NULL DEFAULT 0,
    author_id     BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
    lat           DECIMAL(10,7) NOT NULL DEFAULT 0,
    lng           DECIMAL(10,7) NOT NULL DEFAULT 0,
    city          VARCHAR(200) NOT NULL DEFAULT '',
    country       VARCHAR(100) NOT NULL DEFAULT '',
    price_value   DECIMAL(15,2) NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (listing_id),
    KEY idx_type_status (listing_type, status),
    KEY idx_featured_rating (is_featured DESC, avg_rating DESC),
    KEY idx_rating (avg_rating DESC),
    KEY idx_created (created_at DESC),
    KEY idx_price (price_value),
    KEY idx_author (author_id),
    KEY idx_lat_lng (lat, lng),
    FULLTEXT idx_search (title, content_text, meta_text)
) {charset_collate};
```

**`meta_text` column:** Concatenated searchable field values. Example:
```
"Italian Pizza Pasta Fine Dining Manhattan WiFi Outdoor Seating"
```
This allows FULLTEXT search to match custom field values without JOINing postmeta.

**Population:** On `save_post_listora_listing`:
1. Fetch title, excerpt, content (strip HTML)
2. Fetch all searchable meta fields for this listing type
3. Concatenate into `meta_text`
4. Fetch current avg_rating and review_count from reviews table
5. Insert/update row

---

### 3. `{prefix}listora_reviews` — Review System

**Purpose:** Structured reviews with multi-criteria, photos, owner replies (not WP comments)

```sql
CREATE TABLE {prefix}listora_reviews (
    id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id      BIGINT(20) UNSIGNED NOT NULL,
    user_id         BIGINT(20) UNSIGNED NOT NULL,
    overall_rating  TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    criteria_ratings TEXT DEFAULT NULL,
    title           VARCHAR(500) NOT NULL DEFAULT '',
    content         TEXT NOT NULL,
    status          VARCHAR(20) NOT NULL DEFAULT 'pending',
    photos          TEXT DEFAULT NULL,
    helpful_count   INT(11) NOT NULL DEFAULT 0,
    owner_reply     TEXT DEFAULT NULL,
    owner_reply_at  DATETIME DEFAULT NULL,
    ip_address      VARCHAR(45) NOT NULL DEFAULT '',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    KEY idx_listing_status (listing_id, status),
    KEY idx_user (user_id),
    KEY idx_rating (overall_rating DESC),
    KEY idx_created (created_at DESC),
    UNIQUE KEY idx_user_listing (user_id, listing_id)
) {charset_collate};
```

**`criteria_ratings`:** JSON for Pro multi-criteria. Example:
```json
{"food": 4, "service": 5, "ambiance": 3, "value": 4}
```

**`photos`:** JSON array of attachment IDs. Example: `[123, 456, 789]`

**`UNIQUE KEY idx_user_listing`:** One review per user per listing.

---

### 4. `{prefix}listora_favorites` — User Bookmarks

```sql
CREATE TABLE {prefix}listora_favorites (
    user_id      BIGINT(20) UNSIGNED NOT NULL,
    listing_id   BIGINT(20) UNSIGNED NOT NULL,
    collection   VARCHAR(100) NOT NULL DEFAULT 'default',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (user_id, listing_id),
    KEY idx_listing (listing_id),
    KEY idx_user_collection (user_id, collection)
) {charset_collate};
```

**Collections (Pro):** Users can organize favorites into named collections ("Want to Visit", "Date Night Spots").

---

### 5. `{prefix}listora_claims` — Claim Requests

```sql
CREATE TABLE {prefix}listora_claims (
    id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id   BIGINT(20) UNSIGNED NOT NULL,
    user_id      BIGINT(20) UNSIGNED NOT NULL,
    status       VARCHAR(20) NOT NULL DEFAULT 'pending',
    proof_text   TEXT NOT NULL DEFAULT '',
    proof_files  TEXT DEFAULT NULL,
    admin_notes  TEXT DEFAULT NULL,
    reviewed_by  BIGINT(20) UNSIGNED DEFAULT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    KEY idx_listing (listing_id),
    KEY idx_user (user_id),
    KEY idx_status (status)
) {charset_collate};
```

**Statuses:** `pending`, `approved`, `rejected`, `revoked`

---

### 6. `{prefix}listora_hours` — Business Hours (Denormalized)

**Purpose:** Fast "open now" queries without parsing JSON from postmeta

```sql
CREATE TABLE {prefix}listora_hours (
    listing_id   BIGINT(20) UNSIGNED NOT NULL,
    day_of_week  TINYINT(1) UNSIGNED NOT NULL,
    open_time    TIME DEFAULT NULL,
    close_time   TIME DEFAULT NULL,
    is_closed    TINYINT(1) NOT NULL DEFAULT 0,
    is_24h       TINYINT(1) NOT NULL DEFAULT 0,
    timezone     VARCHAR(50) NOT NULL DEFAULT 'UTC',
    PRIMARY KEY  (listing_id, day_of_week),
    KEY idx_open (day_of_week, open_time, close_time, is_closed)
) {charset_collate};
```

**"Open Now" Query Logic:**
```sql
WHERE day_of_week = DAYOFWEEK(CONVERT_TZ(NOW(), 'UTC', timezone)) - 1
  AND is_closed = 0
  AND (is_24h = 1 OR (
    CONVERT_TZ(NOW(), 'UTC', timezone) BETWEEN
      CONCAT(CURDATE(), ' ', open_time)
      AND CONCAT(CURDATE(), ' ', close_time)
  ))
```

**Late-night handling:** If `close_time < open_time` (e.g., bar open 20:00-02:00), the query checks across midnight.

---

### 7. `{prefix}listora_analytics` (Pro Only)

```sql
CREATE TABLE {prefix}listora_analytics (
    id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id   BIGINT(20) UNSIGNED NOT NULL,
    event_type   VARCHAR(30) NOT NULL,
    event_date   DATE NOT NULL,
    count        INT(11) NOT NULL DEFAULT 1,
    meta         TEXT DEFAULT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY idx_listing_event_date (listing_id, event_type, event_date),
    KEY idx_date (event_date),
    KEY idx_listing (listing_id)
) {charset_collate};
```

**Event types:** `view`, `search_impression`, `phone_click`, `website_click`, `email_click`, `direction_click`, `favorite`, `share`

**Aggregation:** Uses `INSERT ... ON DUPLICATE KEY UPDATE count = count + 1` for efficient daily aggregation.

---

### 8. `{prefix}listora_field_index` — Custom Field Filter Index

**This is the CRITICAL table that enables type-specific field filtering without JOINing wp_postmeta.**

**Purpose:** Denormalized index of all filterable custom field values. One row per listing per filterable field. This is what makes filtering by "bedrooms >= 3" or "cuisine = Italian" fast at scale.

```sql
CREATE TABLE {prefix}listora_field_index (
    listing_id    BIGINT(20) UNSIGNED NOT NULL,
    field_key     VARCHAR(100) NOT NULL DEFAULT '',
    field_value   VARCHAR(500) NOT NULL DEFAULT '',
    numeric_value DECIMAL(15,2) DEFAULT NULL,
    listing_type  VARCHAR(50) NOT NULL DEFAULT '',
    PRIMARY KEY  (listing_id, field_key),
    KEY idx_field_value (field_key, field_value),
    KEY idx_field_numeric (field_key, numeric_value),
    KEY idx_type_field (listing_type, field_key, field_value)
) {charset_collate};
```

**How it works:**
- `field_value` stores the string representation (for select, multiselect, checkbox filters)
- `numeric_value` stores numeric representation when applicable (for price, bedrooms, area range filters)
- For multiselect fields (e.g., cuisine = ["Italian", "Chinese"]), insert **ONE ROW PER VALUE** — so a listing with 3 cuisines gets 3 rows with the same `listing_id` but different `field_value`s
- **Population:** On `save_post_listora_listing`, for each filterable field in the listing type, insert/update rows
- This enables: `WHERE field_key = 'bedrooms' AND numeric_value >= 3` — single indexed table, no JOINs
- For facet counts: `SELECT field_value, COUNT(*) FROM listora_field_index WHERE field_key = 'cuisine' AND listing_id IN (...matched_ids...) GROUP BY field_value`

**Why this table AND search_index:**
- `search_index` handles: FULLTEXT keyword search, status/type filtering, rating/featured sorting, geo pre-filter
- `field_index` handles: structured custom field filtering (exact match, range, multi-value)
- The search flow becomes: 1) Query `search_index` for keyword+status+type+geo → get candidate IDs, 2) Filter candidates via `field_index` for custom field filters, 3) Final ordering and pagination

---

### 9. `{prefix}listora_review_votes` — Review Helpful Vote Tracking

**Purpose:** Prevent duplicate "helpful" votes per user per review.

```sql
CREATE TABLE {prefix}listora_review_votes (
    user_id      BIGINT(20) UNSIGNED NOT NULL,
    review_id    BIGINT(20) UNSIGNED NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (user_id, review_id),
    KEY idx_review (review_id)
) {charset_collate};
```

---

### 10. `{prefix}listora_payments` — Payment Records (Pro)

**Purpose:** Track all payment transactions for listing plans, claims, and featured upgrades.

```sql
CREATE TABLE {prefix}listora_payments (
    id                    BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id               BIGINT(20) UNSIGNED NOT NULL,
    listing_id            BIGINT(20) UNSIGNED DEFAULT NULL,
    plan_id               BIGINT(20) UNSIGNED DEFAULT NULL,
    gateway               VARCHAR(30) NOT NULL DEFAULT '',
    gateway_payment_id    VARCHAR(255) NOT NULL DEFAULT '',
    gateway_subscription_id VARCHAR(255) DEFAULT NULL,
    amount                DECIMAL(10,2) NOT NULL DEFAULT 0,
    currency              VARCHAR(3) NOT NULL DEFAULT 'USD',
    tax_amount            DECIMAL(10,2) NOT NULL DEFAULT 0,
    tax_rate              DECIMAL(5,2) NOT NULL DEFAULT 0,
    coupon_code           VARCHAR(50) DEFAULT NULL,
    discount_amount       DECIMAL(10,2) NOT NULL DEFAULT 0,
    status                VARCHAR(20) NOT NULL DEFAULT 'pending',
    payment_type          VARCHAR(30) NOT NULL DEFAULT 'one_time',
    invoice_number        VARCHAR(50) DEFAULT NULL,
    billing_name          VARCHAR(200) DEFAULT NULL,
    billing_email         VARCHAR(200) DEFAULT NULL,
    billing_address       TEXT DEFAULT NULL,
    vat_number            VARCHAR(50) DEFAULT NULL,
    refund_amount         DECIMAL(10,2) NOT NULL DEFAULT 0,
    refund_reason         TEXT DEFAULT NULL,
    refunded_at           DATETIME DEFAULT NULL,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at            DATETIME DEFAULT NULL,
    PRIMARY KEY  (id),
    KEY idx_user (user_id),
    KEY idx_listing (listing_id),
    KEY idx_status (status),
    KEY idx_gateway_payment (gateway, gateway_payment_id),
    KEY idx_subscription (gateway_subscription_id),
    KEY idx_invoice (invoice_number),
    KEY idx_created (created_at DESC)
) {charset_collate};
```

**Statuses:** `pending`, `completed`, `failed`, `refunded`, `partially_refunded`, `cancelled`

**Payment types:** `one_time`, `subscription`, `renewal`, `upgrade`, `claim_fee`

---

## Migration System

### Version Tracking
```php
$current_db_version = get_option('wb_listora_db_version', '0');
```

### Migration Class (`class-migrator.php`)

```
Migrations are versioned functions:
  migrate_1_0_0() → Initial table creation
  migrate_1_1_0() → Add timezone column to hours table
  migrate_1_2_0() → Add analytics table (Pro)
```

### Migration Flow
1. On `plugins_loaded`, compare `WB_LISTORA_DB_VERSION` constant with stored version
2. If different, run each migration between stored and current versions sequentially
3. Each migration uses `dbDelta()` for table modifications (safe, idempotent)
4. Update `wb_listora_db_version` after all migrations complete
5. Log migration results

### Rollback Strategy
- No automatic rollback — migrations should be forward-only
- Document manual rollback SQL in migration comments
- `wp listora db:status` shows current schema version and health

---

## Index Maintenance

### Automatic (Hooks)

| Event | Action |
|-------|--------|
| `save_post_listora_listing` | Reindex single listing (search_index, geo, hours, field_index) |
| `transition_post_status` | Update status in search_index |
| `deleted_post` | Remove from all index tables |
| Review created/updated | Update avg_rating + review_count in search_index |
| Claim approved | Set is_claimed=1 in search_index |
| Listing meta updated | Rebuild meta_text in search_index, rebuild field_index rows for listing |

### Manual (WP-CLI)

```bash
wp listora reindex                    # Full reindex (all tables)
wp listora reindex --type=restaurant  # Reindex one type
wp listora reindex --table=geo        # Reindex one table
wp listora reindex --dry-run          # Show what would be indexed
wp listora reindex --batch-size=500   # Custom batch size
wp listora db:status                  # Show table sizes, index health
wp listora db:repair                  # Fix inconsistencies
wp listora db:clean                   # Remove orphaned rows
```

### Batch Reindex Process
1. Get total listing count
2. Process in batches of 500 (configurable)
3. For each batch: fetch posts → build index rows → batch INSERT/UPDATE
4. Show progress bar (WP-CLI)
5. Report: indexed X listings, Y errors, Z skipped

---

## Backup Compatibility

### Problem
Standard WP backup tools (UpdraftPlus, BlogVault, etc.) back up core WP tables but may skip custom tables.

### Solution
1. Register custom tables with WordPress's `$wpdb->tables` array
2. Document for users: "These tables are indexes and can be regenerated with `wp listora reindex`"
3. Include `wp listora reindex` in post-restore documentation
4. Optionally: filter into UpdraftPlus/BackupBuddy table lists if those plugins are detected

---

## Performance Considerations

| Table | Expected Size (100K listings) | Key Optimization |
|-------|-------------------------------|------------------|
| listora_search_index | 100K rows, ~50MB | FULLTEXT index, single-table queries |
| listora_geo | 100K rows, ~10MB | Geohash index for proximity, lat/lng composite index |
| listora_reviews | 500K rows, ~100MB | Listing+status composite index |
| listora_favorites | 200K rows, ~5MB | Composite PK, minimal columns |
| listora_hours | 700K rows, ~15MB | Day+time composite index |
| listora_claims | 10K rows, ~2MB | Status index |
| listora_field_index | 500K rows (100K listings x ~5 filterable fields avg), ~30MB | Composite field_key+field_value index |
| listora_review_votes | 300K rows, ~5MB | Composite PK |
| listora_payments | 50K rows, ~10MB | Gateway+payment_id index |

### Query Optimization
- Search queries use a two-phase approach: Phase 1 queries `listora_search_index` for keyword/status/type/geo candidates. Phase 2 filters candidates via `listora_field_index` for custom field filters. Both are indexed single-table queries — no wp_postmeta JOINs.
- Geo queries pre-filter with bounding box before Haversine distance calculation
- Rating aggregation pre-computed in search_index (not calculated per query)
- Facet counts cached as transients (15-30 min TTL)
- Object cache for individual listing data (1 hour TTL)

---

## Multisite Compatibility

- Each site in a multisite network gets its own tables (using site-specific prefix)
- `dbDelta()` handles per-site table creation via `switch_to_blog()` during network activation
- No cross-site queries (each directory is independent)

---

## Uninstall Data Cleanup

When user deletes the plugin AND has opted into data removal:

```php
// Drop all custom tables
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}listora_geo");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}listora_search_index");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}listora_reviews");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}listora_favorites");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}listora_claims");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}listora_hours");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}listora_analytics");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}listora_field_index");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}listora_review_votes");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}listora_payments");

// Delete all postmeta
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_listora_%'");

// Delete all options
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wb_listora_%'");

// Delete all term meta
$wpdb->query("DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE '_listora_%'");

// Remove capabilities from all roles
// Delete CPT posts
// Delete taxonomy terms
```
