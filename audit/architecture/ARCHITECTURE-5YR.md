# WB Listora — 5-Year Architecture & Roadmap

**Status**: SOURCE OF TRUTH for long-term decisions
**Owner**: Varun
**Sibling docs**:
- [`1.0.0-release-plan.md`](1.0.0-release-plan.md) — what ships in v1.0.0
- [`release-issues-and-flow-tests.md`](release-issues-and-flow-tests.md) — current punch list

> **Premise**: this plugin will run on tens of thousands of installs over 5+ years. Every schema column, every capability slug, every hook name shipped in 1.0.0 is a contract we must honor. We design once, ship once, evolve **additively** — never break existing data, hooks, or REST contracts.
>
> **North star**: ship plumbing that feels like WordPress core. Small free functions. Direct `$wpdb` in models. Filter-everything. No god classes. No frameworks-on-frameworks. Backwards-compat as a religion.

---

## 1. Scale targets (5-year horizon)

| Year | Listings | Users | Searches/day | Reviews | Tables strategy |
|---|---|---|---|---|---|
| Year 1 (v1.0–1.5) | 10K avg site, 100K p99 | 1K avg, 10K p99 | 5K | 20K | Single-DB, no partitioning |
| Year 2 (v2.x) | 100K avg, 1M p99 | 10K avg, 100K p99 | 50K | 500K | Add caching layer (Redis), composite indexes finalized |
| Year 3 (v3.x) | 1M avg, 10M p99 | 100K avg, 1M p99 | 500K | 5M | Search offload (Meilisearch optional), analytics partitioned |
| Year 4 (v4.x) | 10M avg, 100M p99 | 1M avg, 10M p99 | 5M | 50M | Read replicas, audit log archival to cold storage |
| Year 5 (v5.x) | 100M+ multi-tenant | 10M+ | 50M | 500M | Sharding ready, federated search |

**Single-install commitment**: 1.0.0 must work cleanly on a $20/mo VPS up to year-2 scale (1M listings) with no schema migration. Beyond that, add caching/search offload — never break the schema.

---

## 2. Schema lock-in (the most important section)

These tables ship in 1.0.0 and **the schema is frozen for 5 years**. Migrations are **additive only**: ADD COLUMN, ADD INDEX, no DROP, no RENAME, no MODIFY existing columns.

### Naming rules (frozen)

- Table prefix: `listora_` (so `{$wpdb->prefix}listora_*` → `wp_listora_*` on default sites)
- Primary keys: `id BIGINT UNSIGNED AUTO_INCREMENT` for ledger/log tables; otherwise `<resource>_id` matching parent (e.g., `listing_id` in `listora_geo`)
- Charset: `utf8mb4_unicode_520_ci` (universal, sortable, emoji-safe — never `utf8mb3` or `latin1`)
- Engine: `InnoDB`
- Row format: `DYNAMIC` (required for `utf8mb4` + large columns)
- Timestamps: `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`, `updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` — every mutable table
- Soft-delete: `deleted_at DATETIME NULL` only on tables that need recovery; never use status enums for deletion

### Index philosophy

1. **Every foreign-key column has its own index.** No exceptions.
2. **Composite indexes prefix the most-selective column first.** Document each composite index purpose in a SQL comment.
3. **No covering indexes >5 columns.** If you need that, redesign the query.
4. **Add indexes on day 1, even if not yet hot.** Adding indexes on a 10M-row table mid-product is painful.
5. **FULLTEXT only on dedicated search tables.** Never on `listora_listings` post table directly — use `listora_search_index`.

### The 11 core tables (Free)

#### 2.1 `listora_geo` — listing geolocation (1:1 with listing)

```sql
CREATE TABLE {$prefix}listora_geo (
    listing_id      BIGINT UNSIGNED NOT NULL,
    lat             DECIMAL(10,7) NOT NULL,
    lng             DECIMAL(10,7) NOT NULL,
    geohash         CHAR(12) NOT NULL,                -- precision-12 for tile queries
    address         VARCHAR(255) NULL,
    city            VARCHAR(120) NULL,
    state           VARCHAR(120) NULL,
    country         CHAR(2) NULL,                     -- ISO 3166-1 alpha-2
    postal_code     VARCHAR(20) NULL,
    timezone        VARCHAR(64) NULL,                 -- IANA tz, e.g. 'Asia/Kolkata'
    accuracy_meters INT UNSIGNED NULL,                -- geocoder confidence
    source          VARCHAR(32) NOT NULL DEFAULT 'manual', -- manual|google|osm|import
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (listing_id),
    KEY geohash_idx (geohash),                        -- prefix-search for tile queries
    KEY country_city_idx (country, city),
    KEY latlng_idx (lat, lng)                         -- bounding-box queries
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC;
```

**Why these columns now**: geocoder accuracy + source already there for year-3 ML re-geocoding. Country as ISO-2 (not text) saves bytes and enables joins.

#### 2.2 `listora_search_index` — denormalized search (1:1 with listing)

```sql
CREATE TABLE {$prefix}listora_search_index (
    listing_id      BIGINT UNSIGNED NOT NULL,
    listing_type    VARCHAR(64) NOT NULL,             -- restaurant, hotel, etc.
    status          VARCHAR(20) NOT NULL,             -- publish, pending, expired, etc.
    title           VARCHAR(255) NOT NULL,
    excerpt         VARCHAR(500) NULL,
    content_text    MEDIUMTEXT NULL,                  -- stripped post_content
    meta_text       MEDIUMTEXT NULL,                  -- concatenated indexable meta
    services_text   MEDIUMTEXT NULL,                  -- concatenated service titles
    avg_rating      DECIMAL(3,2) NULL,
    review_count    INT UNSIGNED NOT NULL DEFAULT 0,
    is_featured     TINYINT(1) NOT NULL DEFAULT 0,
    is_verified     TINYINT(1) NOT NULL DEFAULT 0,
    is_claimed      TINYINT(1) NOT NULL DEFAULT 0,
    author_id       BIGINT UNSIGNED NOT NULL,
    lat             DECIMAL(10,7) NULL,
    lng             DECIMAL(10,7) NULL,
    geohash         CHAR(12) NULL,
    city            VARCHAR(120) NULL,
    country         CHAR(2) NULL,
    price_value     DECIMAL(12,2) NULL,
    price_currency  CHAR(3) NULL,                     -- ISO 4217
    price_unit      VARCHAR(32) NULL,                 -- per_night|per_hour|fixed
    expires_at      DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (listing_id),
    FULLTEXT KEY ft_search (title, excerpt, content_text, meta_text, services_text),
    KEY type_status_idx (listing_type, status),
    KEY author_idx (author_id),
    KEY featured_idx (is_featured, status, created_at),
    KEY rating_idx (avg_rating, review_count),
    KEY geohash_idx (geohash),
    KEY country_city_idx (country, city),
    KEY expires_idx (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC;
```

**Year-3 escape hatch**: when MySQL FULLTEXT can't keep up, swap to Meilisearch. The schema still holds — `Search_Engine` service abstracts the backend.

#### 2.3 `listora_field_index` — custom field facet index (M:N)

```sql
CREATE TABLE {$prefix}listora_field_index (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id      BIGINT UNSIGNED NOT NULL,
    listing_type    VARCHAR(64) NOT NULL,
    field_key       VARCHAR(64) NOT NULL,
    field_value     VARCHAR(255) NULL,
    numeric_value   DECIMAL(20,6) NULL,               -- for range facets
    PRIMARY KEY (id),
    KEY listing_idx (listing_id),
    KEY type_field_value_idx (listing_type, field_key, field_value),
    KEY type_field_numeric_idx (listing_type, field_key, numeric_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC;
```

**Why two value columns**: text faceting (cuisine="Italian") AND numeric range (price BETWEEN 10 AND 50). One row per (listing, field).

#### 2.4 `listora_reviews`

```sql
CREATE TABLE {$prefix}listora_reviews (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id          BIGINT UNSIGNED NOT NULL,
    user_id             BIGINT UNSIGNED NOT NULL,
    overall_rating      TINYINT UNSIGNED NOT NULL,    -- 1-5
    criteria_ratings    JSON NULL,                    -- {"food":5,"service":4,...}
    title               VARCHAR(255) NULL,
    content             TEXT NULL,
    status              VARCHAR(20) NOT NULL DEFAULT 'pending', -- pending|approved|rejected|spam
    photos              JSON NULL,                    -- [att_id, att_id, ...]
    helpful_count       INT UNSIGNED NOT NULL DEFAULT 0,
    owner_reply         TEXT NULL,
    owner_reply_at      DATETIME NULL,
    flag_count          INT UNSIGNED NOT NULL DEFAULT 0,
    ip_address          VARBINARY(16) NULL,           -- INET6_ATON; auto-purged after 90d
    user_agent_hash     CHAR(40) NULL,                -- sha1(user_agent) for dedupe
    moderated_by        BIGINT UNSIGNED NULL,
    moderated_at        DATETIME NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY listing_user_idx (listing_id, user_id, deleted_at),
    KEY status_listing_idx (status, listing_id),
    KEY user_idx (user_id),
    KEY moderation_idx (status, moderated_at),
    KEY rating_idx (listing_id, overall_rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC;
```

**IP as VARBINARY(16) not VARCHAR(45)** — saves bytes and supports v4+v6 with `INET6_ATON()`. Day-1 GDPR move.

#### 2.5 `listora_review_votes`

```sql
CREATE TABLE {$prefix}listora_review_votes (
    user_id     BIGINT UNSIGNED NOT NULL,
    review_id   BIGINT UNSIGNED NOT NULL,
    vote        TINYINT NOT NULL DEFAULT 1,           -- 1=helpful, -1=not (reserved)
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, review_id),
    KEY review_idx (review_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

**Composite PK** = natural dedup. No surrogate id needed.

#### 2.6 `listora_favorites`

```sql
CREATE TABLE {$prefix}listora_favorites (
    user_id      BIGINT UNSIGNED NOT NULL,
    listing_id   BIGINT UNSIGNED NOT NULL,
    collection   VARCHAR(64) NOT NULL DEFAULT 'default',
    notes        TEXT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, listing_id, collection),
    KEY listing_idx (listing_id),
    KEY user_collection_idx (user_id, collection)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

#### 2.7 `listora_claims`

```sql
CREATE TABLE {$prefix}listora_claims (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id      BIGINT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NOT NULL,
    status          VARCHAR(20) NOT NULL DEFAULT 'pending',
    proof_text      TEXT NULL,
    proof_files     JSON NULL,
    admin_notes     TEXT NULL,
    reviewed_by     BIGINT UNSIGNED NULL,
    reviewed_at     DATETIME NULL,
    rejection_reason VARCHAR(255) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY listing_idx (listing_id),
    KEY status_idx (status, created_at),
    KEY user_idx (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC;
```

#### 2.8 `listora_hours` — denormalized for "open now"

```sql
CREATE TABLE {$prefix}listora_hours (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id   BIGINT UNSIGNED NOT NULL,
    day_of_week  TINYINT UNSIGNED NOT NULL,           -- 0=Sun..6=Sat (ISO 8601 sunday-first kept for compat with strftime)
    open_time    TIME NULL,
    close_time   TIME NULL,
    is_closed    TINYINT(1) NOT NULL DEFAULT 0,
    is_24h       TINYINT(1) NOT NULL DEFAULT 0,
    season_start DATE NULL,
    season_end   DATE NULL,
    PRIMARY KEY (id),
    KEY listing_day_idx (listing_id, day_of_week),
    KEY day_open_idx (day_of_week, open_time, close_time)  -- for "open now" cross-listing
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

**Timezone math**: stored in listing's local time. The "open now" check converts user query time to listing's `geo.timezone` server-side. Document this clearly — it's the source of timezone bugs.

#### 2.9 `listora_analytics` — partitioned from day 1

```sql
CREATE TABLE {$prefix}listora_analytics (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id   BIGINT UNSIGNED NOT NULL,
    event_type   VARCHAR(32) NOT NULL,                -- view|click_phone|click_website|click_directions|favorite|share|...
    event_date   DATE NOT NULL,
    event_hour   TINYINT UNSIGNED NULL,               -- 0-23 for hourly aggregation
    count        INT UNSIGNED NOT NULL DEFAULT 1,
    user_id      BIGINT UNSIGNED NULL,                -- NULL for anonymous
    referrer     VARCHAR(255) NULL,
    meta         JSON NULL,
    PRIMARY KEY (id, event_date),                     -- partition key in PK
    KEY listing_event_date_idx (listing_id, event_type, event_date),
    KEY date_idx (event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC
  PARTITION BY RANGE (TO_DAYS(event_date)) (
    PARTITION p_init VALUES LESS THAN (TO_DAYS('2026-01-01')),
    PARTITION p_future VALUES LESS THAN MAXVALUE
  );
```

**Partition by month** added programmatically by maintenance cron once monthly volume justifies it. Schema **already partition-ready** — partitions can be added without migration.

**Year-4 escape hatch**: when MySQL partitions hit limits, write a daily aggregator that rolls events into `listora_analytics_daily` (date, listing_id, event_type, count) and archive raw events to S3.

#### 2.10 `listora_payments` — financial truth (immutable)

```sql
CREATE TABLE {$prefix}listora_payments (
    id                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id                  BIGINT UNSIGNED NULL,
    listing_id               BIGINT UNSIGNED NULL,
    plan_id                  BIGINT UNSIGNED NULL,
    gateway                  VARCHAR(32) NOT NULL,    -- stripe|paypal|edd|woo|manual
    gateway_payment_id       VARCHAR(255) NULL,
    gateway_subscription_id  VARCHAR(255) NULL,
    idempotency_key          VARCHAR(64) NULL,        -- gateway-side; UNIQUE
    amount                   DECIMAL(12,2) NOT NULL,
    currency                 CHAR(3) NOT NULL,        -- ISO 4217
    tax_amount               DECIMAL(12,2) NOT NULL DEFAULT 0,
    coupon_code              VARCHAR(64) NULL,
    discount_amount          DECIMAL(12,2) NOT NULL DEFAULT 0,
    status                   VARCHAR(20) NOT NULL,    -- pending|completed|failed|refunded|partially_refunded
    payment_type             VARCHAR(20) NOT NULL DEFAULT 'one_time', -- one_time|subscription|renewal
    invoice_number           VARCHAR(64) NULL,
    billing_name             VARCHAR(255) NULL,
    billing_email            VARCHAR(255) NULL,
    billing_country          CHAR(2) NULL,
    refund_amount            DECIMAL(12,2) NULL,
    refund_reason            VARCHAR(255) NULL,
    refunded_at              DATETIME NULL,
    raw_payload              JSON NULL,               -- gateway webhook body for audit
    created_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at               DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY idempotency_idx (idempotency_key),
    KEY gateway_payment_idx (gateway, gateway_payment_id),
    KEY user_idx (user_id, created_at),
    KEY status_idx (status, created_at),
    KEY plan_idx (plan_id),
    KEY listing_idx (listing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC;
```

**Immutable rule**: this table is **insert-only and refund-update-only**. No DELETE ever. Refunds add a row OR update `refund_amount` on the original row. **Ledger discipline.**

#### 2.11 `listora_services`

```sql
CREATE TABLE {$prefix}listora_services (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id        BIGINT UNSIGNED NOT NULL,
    title             VARCHAR(255) NOT NULL,
    description       TEXT NULL,
    price             DECIMAL(12,2) NULL,
    price_currency    CHAR(3) NULL,
    price_type        VARCHAR(32) NOT NULL DEFAULT 'fixed',  -- fixed|from|hourly|negotiable
    duration_minutes  INT UNSIGNED NULL,
    image_id          BIGINT UNSIGNED NULL,
    video_url         VARCHAR(500) NULL,
    gallery           JSON NULL,
    sort_order        INT NOT NULL DEFAULT 0,
    status            VARCHAR(20) NOT NULL DEFAULT 'active',
    booking_url       VARCHAR(500) NULL,                     -- v1.4 bookings hook
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY listing_status_sort_idx (listing_id, status, sort_order),
    KEY price_idx (listing_id, price)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC;
```

### Pro tables (7)

#### 2.12 `listora_credit_log` — immutable ledger

```sql
CREATE TABLE {$prefix}listora_credit_log (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         BIGINT UNSIGNED NOT NULL,
    delta_credits   INT NOT NULL,                     -- positive = credit, negative = debit
    balance_after   INT NOT NULL,
    source          VARCHAR(32) NOT NULL,             -- purchase|refund|admin_grant|listing_create|listing_renew|coupon
    source_id       BIGINT UNSIGNED NULL,             -- payment_id, plan_id, listing_id, etc.
    source_type     VARCHAR(32) NULL,                 -- 'payment', 'listing', etc.
    note            VARCHAR(255) NULL,
    actor_id        BIGINT UNSIGNED NULL,             -- who initiated (NULL for system)
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_created_idx (user_id, created_at),
    KEY source_idx (source, source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

**Insert-only.** Balance derived as `SUM(delta_credits) WHERE user_id = ?` OR cached in user_meta with reconciliation cron.

#### 2.13 `listora_audit_log` — partitioned compliance log

```sql
CREATE TABLE {$prefix}listora_audit_log (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_id      BIGINT UNSIGNED NULL,
    actor_role    VARCHAR(64) NULL,
    action_type   VARCHAR(64) NOT NULL,               -- listing.create|review.approve|coupon.delete|...
    object_type   VARCHAR(64) NULL,                   -- listing|review|user|coupon|...
    object_id     BIGINT UNSIGNED NULL,
    ip_address    VARBINARY(16) NULL,
    user_agent    VARCHAR(255) NULL,
    meta          JSON NULL,                          -- before/after diff, context
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id, created_at),
    KEY actor_created_idx (actor_id, created_at),
    KEY object_idx (object_type, object_id, created_at),
    KEY action_idx (action_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC
  PARTITION BY RANGE (TO_DAYS(created_at)) (
    PARTITION p_init VALUES LESS THAN (TO_DAYS('2026-01-01')),
    PARTITION p_future VALUES LESS THAN MAXVALUE
  );
```

#### 2.14 `listora_saved_searches`

```sql
CREATE TABLE {$prefix}listora_saved_searches (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         BIGINT UNSIGNED NOT NULL,
    name            VARCHAR(120) NOT NULL,
    query           JSON NOT NULL,                    -- full search params
    alert_frequency VARCHAR(16) NOT NULL DEFAULT 'never',  -- never|daily|weekly|instant
    alerts_enabled  TINYINT(1) NOT NULL DEFAULT 1,
    last_seen_ids   JSON NULL,                        -- IDs we already alerted on
    last_run_at     DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_idx (user_id),
    KEY frequency_run_idx (alert_frequency, alerts_enabled, last_run_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC;
```

#### 2.15 `listora_coupon_usage`

```sql
CREATE TABLE {$prefix}listora_coupon_usage (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    coupon_id    BIGINT UNSIGNED NOT NULL,            -- listora_coupon CPT post ID
    user_id      BIGINT UNSIGNED NOT NULL,
    payment_id   BIGINT UNSIGNED NULL,                -- listora_payments.id
    discount_amount DECIMAL(12,2) NOT NULL,
    used_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY coupon_idx (coupon_id, used_at),
    KEY user_idx (user_id),
    KEY payment_idx (payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

#### 2.16 `listora_webhook_log`

```sql
CREATE TABLE {$prefix}listora_webhook_log (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    webhook_id      BIGINT UNSIGNED NOT NULL,         -- listora_webhook CPT post ID
    event_type      VARCHAR(64) NOT NULL,
    object_id       BIGINT UNSIGNED NULL,
    request_body    MEDIUMTEXT NULL,
    response_code   SMALLINT NULL,
    response_body   MEDIUMTEXT NULL,
    delivery_ms     INT UNSIGNED NULL,
    attempt         TINYINT UNSIGNED NOT NULL DEFAULT 1,
    status          VARCHAR(20) NOT NULL DEFAULT 'pending',  -- pending|success|failed|retrying
    next_retry_at   DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at    DATETIME NULL,
    PRIMARY KEY (id),
    KEY webhook_status_idx (webhook_id, status, created_at),
    KEY retry_idx (status, next_retry_at),
    KEY event_idx (event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC;
```

#### 2.17 `listora_need_responses`

```sql
CREATE TABLE {$prefix}listora_need_responses (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    need_id      BIGINT UNSIGNED NOT NULL,            -- listora_need CPT
    listing_id   BIGINT UNSIGNED NOT NULL,            -- responding listing
    user_id      BIGINT UNSIGNED NOT NULL,            -- listing owner
    message      TEXT NULL,
    price_offered DECIMAL(12,2) NULL,
    status       VARCHAR(20) NOT NULL DEFAULT 'pending', -- pending|accepted|rejected|withdrawn
    moderated_at DATETIME NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY need_listing_idx (need_id, listing_id),  -- one response per listing per need
    KEY need_status_idx (need_id, status),
    KEY user_idx (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=DYNAMIC;
```

### Migration policy

Every schema change goes through `Migrator::run()` with a version key:

```
wb_listora_db_version = '1.0.0'
wb_listora_pro_db_version = '1.0.0'
```

Allowed migrations:
- `ADD COLUMN ... DEFAULT NULL` — always safe
- `ADD INDEX` — runs `pt-online-schema-change` style if table > 1M rows
- `ADD PARTITION` — analytics + audit_log
- `ALTER TABLE ... ALGORITHM=INSTANT` (MySQL 8+) — preferred when available

Forbidden migrations (committed: never):
- `DROP COLUMN`
- `RENAME COLUMN`
- `MODIFY COLUMN` (changing type)
- `DROP TABLE`

If we need to deprecate a column: add the new one, write to both for one minor version, read from new one only after that, leave old column as-is forever (or until we ship a major version that announces removal 6 months ahead).

### Schema versioning rule

Bump `wb_listora_db_version` only when adding migrations. The `Migrator` runs all migrations between current DB version and code version on `plugins_loaded` (with a `wp_cache` lock).

---

## 3. Code architecture — minimal helpers, WP-core-quality

### 3.1 Layered structure (no frameworks-on-frameworks)

```
includes/
├── class-plugin.php              # bootstrap, lifecycle, service registration
├── class-activator.php           # one-time on activate
├── class-deactivator.php         # one-time on deactivate
├── class-service-locator.php     # tiny DI; map of key → class
├── core/                         # domain primitives — no IO
│   ├── class-post-types.php
│   ├── class-taxonomies.php
│   ├── class-capabilities.php
│   ├── class-listing-type.php
│   ├── class-field.php
│   └── class-listing-type-registry.php
├── db/                           # schema + migrations only — no business logic
│   ├── class-schema.php
│   ├── class-migrator.php
│   └── migrations/
│       ├── 001-initial.php
│       └── 002-add-coupon-usage.php  # future
├── models/                       # one file per table; ALL $wpdb lives here
│   ├── class-listing-model.php
│   ├── class-review-model.php
│   ├── class-geo-model.php
│   ├── class-search-index-model.php
│   ├── class-favorite-model.php
│   ├── class-claim-model.php
│   ├── class-hours-model.php
│   ├── class-services-model.php
│   ├── class-payment-model.php
│   └── class-analytics-model.php
├── services/                     # business logic; depend on models
│   ├── class-search-engine.php
│   ├── class-search-indexer.php
│   ├── class-geo-query.php
│   ├── class-rate-limiter.php
│   ├── class-captcha.php
│   └── class-block-css.php
├── workflow/                     # cross-cutting flows: notifications, expiration, etc.
│   ├── class-notifications.php
│   ├── class-expiration-cron.php
│   ├── class-email-verification.php
│   └── class-status-manager.php
├── rest/                         # thin controllers; delegate to services + models
│   ├── class-listings-controller.php
│   ├── class-reviews-controller.php
│   ├── ...
│   └── class-rest-helpers.php    # nonce check, rate-limit pre-flight, etc.
├── admin/                        # admin pages, columns, notices
│   └── ...
├── functions.php                 # template tags / public helpers (small free functions)
└── (no `core-functions.php` god file — split by domain)
```

### 3.2 Free function policy (the WP idiom)

Every model has a corresponding set of free functions that template authors call:

```php
wb_listora_get_listing( $id )                  // wraps Listing_Model::get
wb_listora_get_listings( $args )               // wraps Listing_Model::find
wb_listora_get_geo( $listing_id )              // wraps Geo_Model::get
wb_listora_get_reviews( $listing_id, $args )   // wraps Review_Model::find
wb_listora_get_avg_rating( $listing_id )       // computed, cached
wb_listora_is_open_now( $listing_id )          // hours + tz math
wb_listora_get_services( $listing_id )         // Services_Model::find
wb_listora_get_template( $name, $args )        // template loader
wb_listora_template_part( $slug, $name, $args )// like get_template_part
```

**Rules**:
- Every public free function has `@since 1.0.0` docblock.
- Every public free function is in `includes/functions.php` (or a domain-named file).
- Every function returns a `WP_Error`, `null`, or strict-typed result — no exceptions thrown to callers.
- No new free functions added without a corresponding `_doing_it_wrong` deprecation slot reserved.
- Every function is filterable: returns `apply_filters( 'wb_listora_get_<resource>', $result, $args )`.

### 3.3 Models — the ONLY place `$wpdb` lives

Rule: any code outside `includes/models/` and `includes/db/` that uses `$wpdb` is a bug.

Each model exposes:
- `get( $id )` — single by PK, cached
- `find( $args )` — list by criteria, cached
- `count( $args )` — count, cached
- `insert( $data )` — returns id or WP_Error
- `update( $id, $data )` — returns true or WP_Error
- `delete( $id )` — returns true or WP_Error (soft if `deleted_at` present)
- `prepare_for_response( $row )` — public API shape; passes through `wb_listora_rest_prepare_<resource>` filter
- Cache invalidation: `wp_cache_set_last_changed( 'listora_<resource>' )` after every write — per project memory's preference for WP-core idioms.

PHPStan-typed. `@phpstan-type` aliases for row arrays.

### 3.4 Caching policy

Three layers, in order of preference:

1. **Object cache** (`wp_cache_get`/`set`) — for hot reads (listing detail, geo lookup, avg_rating). Group: `listora_<resource>`. Invalidate via `wp_cache_set_last_changed` (last-changed pattern).
2. **Transients** — for cross-request expensive computations (search facet counts, dashboard stats). 5-60 minute TTL.
3. **No DB-level caching** — leave that to MySQL query cache + InnoDB buffer pool.

Per project memory: **default to WP-core idioms (`wp_cache_set_last_changed`, transients) — not custom cache.**

### 3.5 No service container framework

`Service_Locator` is a 50-line class that maps string keys → instances. No factories, no auto-wiring, no reflection. If you need DI complexity beyond that, redesign.

```php
$plugin->services()->get( 'search_engine' );  // returns Search_Engine instance
```

Adding a new service: register key + class in `Plugin::register_services()`. That's it.

### 3.6 No abstract base classes for entities

Resist the urge to write `abstract class Model`. Each model is a self-contained class. Duplication < wrong abstraction.

### 3.7 Hooks are the API

Every meaningful action fires:
- `wb_listora_before_<verb>_<resource>` — filter, return WP_Error to abort
- `wb_listora_after_<verb>_<resource>` — action, side-effects only

Every REST response passes through:
- `wb_listora_rest_prepare_<resource>` — filter, allows extension fields

Hook deprecation: when renaming a hook, register an alias forwarder for 3 minor versions. **Never silently drop a hook.**

---

## 4. Capability + role lock-in

### 4.1 Capability namespace (frozen)

```
wb_listora_<verb>_<resource>
```

Verbs: `read`, `edit`, `publish`, `delete`, `manage`, `moderate`, `submit`, `claim`, `review`.

Resources: `listing`, `listings`, `others_listings`, `published_listings`, `private_listings`, `review`, `reviews`, `claim`, `claims`, `settings`, `types`, `analytics`, `moderators`.

Reserved (will ship later, namespace claimed now):
- `wb_listora_manage_team` (v1.5+ team workflows)
- `wb_listora_manage_partners` (v3.x marketplace)
- `wb_listora_export_data` (v1.1 GDPR self-export)
- `wb_listora_view_pii` (v1.1 GDPR access controls)

### 4.2 Roles (frozen slugs)

| Role slug | Ships in | Purpose |
|---|---|---|
| `listora_moderator` | 1.0.0 (Pro) | Approve/reject listings, reviews, claims |
| `listora_team_lead` | 1.5+ (Pro) | Manages a team of moderators |
| `listora_partner` | 3.x (Pro) | Multi-tenant partner sub-admin |

**Slug discipline**: never rename. Adding caps to a role = forward-compat. Removing caps = breaking change requiring 6-month deprecation notice.

### 4.3 Capability registry (centralized)

```php
WBListora\Core\Capabilities::can( $action, $object = null, $user_id = null )
```

Encodes "can $user do $action on $object?" with one source of truth. No raw `current_user_can( 'wb_listora_edit_listing' )` scattered through controllers.

---

## 5. Hook namespace policy (frozen)

| Pattern | Used for | Frozen? |
|---|---|---|
| `wb_listora_*` | Free hooks | ✅ |
| `wb_listora_pro_*` | Pro hooks | ✅ |
| `wb_listora_<verb>_<resource>` | Action lifecycle | ✅ |
| `wb_listora_before_<verb>_<resource>` | Vetoable filter | ✅ |
| `wb_listora_after_<verb>_<resource>` | Post-action | ✅ |
| `wb_listora_rest_prepare_<resource>` | REST shape filter | ✅ |
| `wb_listora_email_<event>` | Email subject/content/recipients | ✅ |
| `wb_listora_<block>_query_args` | Block render query | ✅ |
| `wb_listora_dashboard_<section>` | Dashboard tab extension | ✅ |

Never rename a public hook. To replace one: register both for 3 minor versions, document migration in changelog, set `Deprecation` notice via `_doing_it_wrong` only after that.

---

## 6. REST API stability

### 6.1 Versioning

- `/listora/v1` — frozen for 5+ years. New endpoints can be added; existing endpoints **never change shape**.
- `/listora/v2` — only created if a breaking change is unavoidable. v1 stays alive for 12 months overlap minimum.

### 6.2 Response envelope (frozen)

```json
{
  "data": { ... },
  "meta": { "total": 123, "page": 1, "per_page": 20, "next_cursor": "..." },
  "links": { "self": "...", "next": "...", "prev": "..." }
}
```

For collections. For single resource: `{ "data": {...} }`.

For errors: WP_Error → standard `WP_REST_Response` shape: `{ "code": "...", "message": "...", "data": { "status": 4xx } }`.

### 6.3 Cursor pagination

All list endpoints support cursor pagination (`?cursor=...&per_page=20`). Offset pagination still works for backward compat but cursor is preferred for >1k results.

### 6.4 Deprecation policy

Field deprecation: `meta.deprecations: [{ "field": "old_name", "replacement": "new_name", "remove_in": "2.0.0" }]` in response.

---

## 7. License + fail-soft contract (Pro)

Codifies ADR-004 from the release plan as long-term doctrine:

- License inactive ⇒ Pro features disable cleanly. Free continues at full functionality.
- Pro data is **never deleted** when license lapses. Reactivation restores full access.
- Pro REST endpoints respond `503 wb_listora_pro_license_expired` with reactivation URL.
- Admin sees a non-dismissible reactivate notice.
- License check is cached for 7 days; treat unreachable license server as "active" not "expired" (network outages must not break customer sites).

---

## 8. Security baseline (frozen as policy)

1. **Every public REST endpoint** is rate-limited or session-gated (ADR-001).
2. **Every write endpoint** requires nonce, HMAC, or captcha (ADR-002).
3. **All file uploads** go through `wp_handle_upload` with mime allowlist.
4. **All raw SQL** uses `$wpdb->prepare`. Reviewer rule: PR comment if any `$wpdb->query()` call doesn't use prepare.
5. **PII (IP, email, user agent)** in logs is purged after 90 days via cron.
6. **Webhook payloads** verified by HMAC SHA-256 + idempotency key (no replay).
7. **No secrets in client JS.** Public API key fields documented as such; service-account keys server-side only.
8. **GDPR data-export and erasure** hooks (`wp_privacy_personal_data_exporters`, `..._erasers`) — ship in 1.1.

---

## 9. Performance budgets (5-year)

| Metric | Year 1 | Year 3 | Year 5 |
|---|---|---|---|
| Search p95 latency | < 500ms | < 300ms | < 150ms (search offload required) |
| Detail page LCP (mobile) | < 2.5s | < 2.0s | < 1.5s |
| REST `/listings` p95 | < 200ms | < 150ms | < 100ms |
| Admin list view (1k rows) | < 1s | < 800ms | < 500ms |
| Dashboard stats endpoint | < 100ms (cached) | < 50ms | < 30ms |

Budgets enforced in CI via Lighthouse + REST timing tests. Regressions block merge.

---

## 10. 5-year feature roadmap

### Year 1 (1.0–1.5) — directory fundamentals

- **1.0** First public release (this plan)
- **1.1** Multilingual bundles (Polylang/WPML), GDPR exporters/erasers, multisite network admin
- **1.2** Mobile companion app shell (REST already supports it; ship React Native open-source repo)
- **1.3** Stripe Connect marketplace payouts (split owner / platform), tax tables
- **1.4** Bookings / reservations layer (uses `services.booking_url` slot already in schema)
- **1.5** Recurring events engine v2 (RRULE-based), team workflows (`listora_team_lead` role)

### Year 2 (2.x) — performance + intelligence

- **2.0** Headless-first mode (decoupled storefront via Next.js starter)
- **2.1** AI listing enrichment (auto-categorize, photo tagging, content moderation hooks)
- **2.2** Real-time search (Meilisearch optional adapter)
- **2.3** Predictive duplicate detection at submission

### Year 3 (3.x) — multi-tenant + marketplace

- **3.0** Multi-tenant: one install hosts multiple branded directories
- **3.1** Marketplace API (third-party listings via REST)
- **3.2** End-user subscription pricing (premium listings, featured rotation)
- **3.3** Partner / sub-admin role (`listora_partner`)

### Year 4 (4.x) — automation

- **4.0** AI-driven recommendations (similar listings, "you might also like")
- **4.1** Predictive moderation (auto-flag suspicious submissions)
- **4.2** Geo-fencing for hyperlocal search
- **4.3** Automated SEO suggestions per listing

### Year 5 (5.x) — federation

- **5.0** White-label SaaS distribution mode
- **5.1** ActivityPub federation (directories interoperate, syndicate listings)
- **5.2** Decentralized moderation network

Each major release ships its own `plan/<version>-release-plan.md`. This roadmap document is updated as features land or shift; it is the **only** place where future direction lives.

---

## 11. Decision log — the locked-in choices

These choices ship in 1.0.0 and **cannot be changed without a major version bump + 6-month deprecation cycle**:

| # | Decision | Locked since | Notes |
|---|---|---|---|
| D-01 | CPT slug `listora_listing` | 1.0.0 | Renaming breaks every customer's data |
| D-02 | Table prefix `listora_` (so `wp_listora_*`) | 1.0.0 | DB compat |
| D-03 | REST namespace `listora/v1` | 1.0.0 | Frozen for 5+ years |
| D-04 | Option key prefix `wb_listora_` (Free) / `wb_listora_pro_` (Pro) | 1.0.0 | |
| D-05 | Meta key prefix `_listora_` (private) | 1.0.0 | Underscore = hidden from REST by default |
| D-06 | Capability format `wb_listora_<verb>_<resource>` | 1.0.0 | |
| D-07 | Hook prefix `wb_listora_*` / `wb_listora_pro_*` | 1.0.0 | |
| D-08 | Role slug `listora_moderator` | 1.0.0 (Pro) | |
| D-09 | Block namespace `listora/*` (Free) / `listora-pro/*` (Pro) | 1.0.0 | |
| D-10 | Schema versioning via `wb_listora_db_version` option | 1.0.0 | |
| D-11 | IP storage as `VARBINARY(16)` (INET6_ATON) | 1.0.0 | GDPR-safer + bytes-cheaper than VARCHAR(45) |
| D-12 | Charset `utf8mb4_unicode_520_ci` | 1.0.0 | Universal, sortable, emoji-safe |
| D-13 | Currency stored as ISO 4217 (CHAR(3)) | 1.0.0 | Never store as VARCHAR(255) "USD"/"dollar"/etc |
| D-14 | Country stored as ISO 3166-1 alpha-2 (CHAR(2)) | 1.0.0 | Joins with future country tables |
| D-15 | Timezone stored as IANA tz name | 1.0.0 | "Asia/Kolkata" not "+05:30" |
| D-16 | `payments` table is insert-only (refunds update; never DELETE) | 1.0.0 | Financial ledger |
| D-17 | `credit_log` is insert-only | 1.0.0 | Financial ledger |
| D-18 | `analytics` + `audit_log` partition-by-range from day 1 | 1.0.0 | Avoids painful re-partitioning at scale |
| D-19 | All `$wpdb` access lives in `includes/models/` | 1.0.0 | Architectural rule |
| D-20 | All public hooks use `before_/after_` lifecycle pattern | 1.0.0 | Predictable extension |
| D-21 | License fail-soft never deletes Pro data | 1.0.0 | Customer trust |

When a decision needs to change, document in this log: original decision, new decision, deprecation cycle, migration path.

---

## 12. Backwards-compat commitments

We commit to these for the **5-year horizon**:

1. **No DB column drops, renames, or type changes.** Add columns only.
2. **No REST endpoint shape changes.** Add fields only. Deprecate fields with `meta.deprecations`.
3. **No hook signature reductions.** `apply_filters( 'X', $a, $b )` stays at 2 args minimum forever.
4. **No capability slug renames.** Add new caps; old ones live forever.
5. **No template path changes.** Themes overriding `{theme}/wb-listora/blocks/listing-card/card.php` stay working.
6. **No CSS class name removals from rendered HTML.** Add classes; never remove.

---

## 13. Operational rules

### Before every minor release
- [ ] `audit/manifest.json` refreshed via `/wp-plugin-onboard --refresh`
- [ ] Coverage gate ≥95% per category
- [ ] No new hooks without consumers OR explicit "extension surface" docblock
- [ ] No new tables outside this document's schema lock-in (any new table requires updating Section 2 first)
- [ ] PHPStan L7 + WPCS + PCP clean

### Before every major release
- [ ] Decision log reviewed; any breaking decision change has 6-month deprecation runway
- [ ] Migration plan published 30 days before release
- [ ] Beta program runs minimum 14 days

---

## 14. What 1.0.0 must establish (so the next 5 years are easy)

**Day-1 commitments**:
1. ✅ All 11 Free + 7 Pro tables shipped at the locked schemas above
2. ✅ Every column listed in Section 2 exists from day 1 (even if not used until later)
3. ✅ Every hook listed in `audit/manifest.json` documented as extension surface OR removed
4. ✅ Every public free function (`wb_listora_get_*`) has `@since 1.0.0` and a docs page
5. ✅ Every capability in Section 4.1 registered (even reserved ones — no-op until used)
6. ✅ Every role slug in Section 4.2 reserved (registered with empty caps if not yet used)
7. ✅ REST envelope (`{data, meta, links}`) consistent across all endpoints
8. ✅ Pro license fail-soft tested end-to-end
9. ✅ All `$wpdb` access confined to `includes/models/` (no exceptions)
10. ✅ Phase 2.5 coverage gate ≥95% on both manifests

If any of these slip past 1.0.0, we eat technical debt for years. **Do not release until all 10 are met.**

---

## 15. References

- WordPress Core development handbook: https://developer.wordpress.org/core/
- WordPress data dictionary: https://codex.wordpress.org/Database_Description
- MySQL partitioning: https://dev.mysql.com/doc/refman/8.0/en/partitioning.html
- WP REST API guidelines: https://developer.wordpress.org/rest-api/
- WP Coding Standards: https://developer.wordpress.org/coding-standards/
- This plugin's hook conventions: `audit/manifest.json` → `hooks_fired`

This document is the contract. Code disagrees with this doc → fix the code. Need to disagree with this doc → update this doc with rationale before merging.
