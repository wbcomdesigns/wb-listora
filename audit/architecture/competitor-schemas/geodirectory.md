# GeoDirectory — Data Schema Audit

> Source-of-truth schema audit for Pro's GeoDirectory migrator. Compiled by `Explore` agent on 2026-05-18 against the live installation at `wp-content/plugins/geodirectory/` (v2.8.161). Every claim cited by file:line.

## 1. Identity

- **Plugin slug:** `geodirectory`
- **Version:** 2.8.161
- **Main plugin file:** `includes/geodirectory.php`
- **Namespace:** Global classes prefixed `GeoDir_` (e.g., `GeoDir_Admin_Install`, `GeoDir_Post_types`, `GeoDir_Comments`). No PHP namespace declaration.

## 2. Custom Post Types

| CPT slug | Label | File:line | supports[] |
|---|---|---|---|
| `gd_place` | Place | `includes/class-geodir-post-types.php:314` | `title`, `editor`, `author`, `thumbnail`, `excerpt`, `custom-fields`, `comments`, `revisions`. Optional (per CPT settings): `business_hours`, `featured`, `special_offers`, `service_distance`, `private_address`, `events` |

`hierarchical: false` (memory perf). Listing archive base: `places` (filterable).

## 3. Taxonomies

| Slug | Linked CPT | Hierarchical | File:line |
|---|---|---|---|
| `gd_placecategory` | `gd_place` | true | `includes/class-geodir-post-types.php:227-244` |
| `gd_place_tags` | `gd_place` | false | `includes/class-geodir-post-types.php:199-221` |

URL: `/places/{category}` and `/places/tags/{tag}`. Bases filterable via `permalink_category_base` / `permalink_tag_base` options.

## 4. Custom Database Tables

**Prefix:** `wp_geodir_` (substitute `$wpdb->prefix`).

### 4.1 `wp_geodir_gd_place_detail` — listing data home

One row per published place. Schema at `includes/admin/class-geodir-admin-install.php:690-703` via `db_cpt_default_columns()` + `db_cpt_default_keys()`.

**Standard columns:**
| Column | Type | Notes |
|---|---|---|
| `post_id` | `bigint(20)` | PK — FK to `wp_posts.ID` |
| `post_title` | `text` | denormalized |
| `_search_title` | `text` | denormalized full-text |
| `post_status` | `varchar(20)` | mirrors `wp_posts.post_status` |
| `post_tags` | `text` | comma-separated tag IDs |
| `post_category` | `text` | comma-separated category IDs |
| `default_category` | `int` | primary-category term ID |
| `featured` | `tinyint(1)` | 0/1 |
| `featured_image` | `varchar(254)` | filename / URL |
| `submit_ip` | `varchar(100)` | submitter IP |
| `overall_rating` | `float` | aggregated 0.0–5.0 |
| `rating_count` | `int(11)` | total reviews |

**Location columns** (present unless CPT has `disable_location`):
| Column | Type |
|---|---|
| `street` | `varchar(254)` |
| `street2` | `varchar(254)` |
| `city` | `varchar(50)` |
| `region` | `varchar(50)` — state / province |
| `country` | `varchar(50)` — code or name |
| `zip` | `varchar(50)` |
| `latitude` | `varchar(22)` — decimal as string |
| `longitude` | `varchar(22)` — decimal as string |
| `mapview` | `varchar(15)` — ROADMAP / SATELLITE / HYBRID / TERRAIN |
| `mapzoom` | `varchar(3)` — zoom level |

**Indices:** PRIMARY KEY on `post_id`; KEY on `country`, `region`, `city`.

**Dynamic columns:** every admin-defined custom field adds its OWN column to this table. Column name = the field's `htmlvar_name`. **No separate postmeta table for custom-field values.**

### 4.2 `wp_geodir_custom_fields` — field catalog

Schema at `includes/admin/class-geodir-admin-install.php:632-665`. Admin-defined fields. Each row defines one field; that field's value gets stored as a dynamic column on `_gd_place_detail`.

Key columns: `post_type`, `data_type`, `field_type`, `field_type_key`, `htmlvar_name`, `admin_title`, `frontend_title`, `default_value`, `is_active`, `is_required`, `option_values` (serialised JSON for select/radio), `validation_pattern`, `decimal_point`, `extra_fields` (JSON).

### 4.3 `wp_geodir_attachments` — gallery + media

Schema at `includes/admin/class-geodir-admin-install.php:707-725`. **Files are NOT stored as `wp_posts.post_type='attachment'`.** GeoDirectory uses this dedicated table.

| Column | Type | Notes |
|---|---|---|
| `ID` | `int(11)` | AUTO_INCREMENT PK |
| `post_id` | `bigint(20)` | FK to `wp_posts.ID` |
| `date_gmt` | `datetime` | upload time UTC |
| `user_id` | `int(11)` | uploader |
| `other_id` | `int(11)` | secondary linked post |
| `title` | `varchar(254)` | attachment title |
| `caption` | `varchar(254)` | caption |
| `file` | `varchar(254)` | relative path under uploads dir |
| `mime_type` | `varchar(150)` | image/jpeg etc. |
| `menu_order` | `int(11)` | gallery sort order |
| `featured` | `tinyint(1)` | featured-image flag |
| `is_approved` | `tinyint(1)` | 0=pending, 1=approved |
| `metadata` | `text` | serialised image-meta (width / height / sizes) |
| `type` | `varchar(254)` | usually `post_images` |

### 4.4 `wp_geodir_post_review` — review rating metadata

Schema at `includes/admin/class-geodir-admin-install.php:752-766`. One row per `wp_comments.comment_ID` that's a review.

| Column | Type | Notes |
|---|---|---|
| `comment_id` | `bigint(20) UNSIGNED` | PK — FK to `wp_comments.comment_ID` |
| `post_id` | `bigint(20)` | FK to listing |
| `user_id` | `bigint(20)` | reviewer |
| `rating` | `float` | aggregate 0–5 |
| `ratings` | `text` | serialised multi-dimensional breakdown |
| `attachments` | `text` | serialised review-image IDs |
| `post_type` | `varchar(20)` | mirrors CPT slug |
| `city` / `region` / `country` | `varchar(50)` | reviewer geo |
| `latitude` / `longitude` | `varchar(22)` | reviewer coords |

### 4.5 Other tables (less migration-critical but documented)

- **`wp_geodir_tabs_layout`** (`class-geodir-admin-install.php:669-682`) — frontend detail-page tab config (Profile / Photos / Map / Reviews etc.). Migrator can ignore (purely presentational).
- **`wp_geodir_custom_sort_fields`** (`class-geodir-admin-install.php:729-743`) — searchable-field metadata. Migrator can ignore.
- **`wp_geodir_post_report`** (`class-geodir-admin-install.php:786-801`) — spam/abuse reports. Migrator can skip unless explicit request.
- **`wp_geodir_api_keys`** (`class-geodir-admin-install.php:770-783`) — REST API tokens. Not part of listing data; skip.

## 5. Postmeta Keys

**GeoDirectory does NOT use postmeta for listing data.** All listing details live in the detail table columns (§4.1).

Standard WordPress postmeta still applies:
- `_thumbnail_id` — featured image attachment ID (standard WP)

Save flow at `includes/post-functions.php:106-145` (`geodir_save_post_meta()`) writes to the detail TABLE, not to `wp_postmeta`, despite the function name.

## 6. Listing Detail Row Structure

`geodir_get_post_info($post_id)` at `includes/post-functions.php:22-90` JOINs `wp_posts` + `wp_geodir_gd_place_detail` and returns a merged stdClass object. Cached at object-cache key `gd_post_{post_id}` under group `gd_post`.

Migrator read pattern:
```sql
SELECT p.*, d.*
FROM wp_posts p
JOIN wp_geodir_gd_place_detail d ON d.post_id = p.ID
WHERE p.post_type = 'gd_place' AND p.post_status = 'publish'
```

## 7. Review Storage

**Two-table pattern:**
1. **`wp_comments`** holds the review body (`comment_content`), reviewer name / email / date, and approval flag (`comment_approved`).
2. **`wp_geodir_post_review`** holds the star rating and reviewer geo.

Migration query:
```sql
SELECT c.*, r.rating, r.ratings, r.attachments
FROM wp_comments c
JOIN wp_geodir_post_review r ON r.comment_id = c.comment_ID
WHERE r.post_id = %d AND c.comment_parent = 0
```

`comment_parent > 0` rows are **replies**, not reviews — exclude from migration unless target supports threaded replies. Save logic at `includes/class-geodir-comments.php:412-485`.

Rating-aggregate refresh logic at `includes/class-geodir-comments.php:189-234` — `overall_rating` and `rating_count` in the detail table are recalculated on every save/approval.

## 8. Image / Gallery Storage

**Featured image:** standard WP `_thumbnail_id`.

**Gallery:** `wp_geodir_attachments` table (NOT `wp_posts.post_type='attachment'`). Migration must:
1. Read `wp_geodir_attachments WHERE post_id = %d AND is_approved = 1 ORDER BY menu_order`
2. Resolve `file` column to a real upload path under `wp-content/uploads/`
3. Use `media_sideload_image()` or `wp_insert_attachment()` to create proper WP attachments in the target plugin
4. Optionally re-link the row's `featured=1` row to be set as `_thumbnail_id` on the new listing

## 9. Geo Storage

In the detail table:
- **Coordinates:** `latitude` + `longitude` (varchar — decimal-as-string, not float)
- **Address (split):** `street`, `street2`, `city`, `region`, `country`, `zip`
- **Map presentation:** `mapview`, `mapzoom`

Write site: `includes/post-functions.php:106-145` (detail table) + `includes/class-geodir-comments.php:434-459` (reviewer geo).

## 10. Categories

- Standard WP terms in `wp_terms` + `wp_term_taxonomy` (taxonomy='gd_placecategory').
- Many-to-many via standard `wp_term_relationships`.
- **Primary category** denormalised into `wp_geodir_gd_place_detail.default_category` (single term ID).
- Comma-separated mirror of all category term IDs in `wp_geodir_gd_place_detail.post_category` for fast lookup.

## 11. Custom Fields System

Two-step storage:
1. **Catalog** — `wp_geodir_custom_fields` row defines the field (`htmlvar_name`, `field_type`, `option_values`, etc.).
2. **Value** — added as a dynamic column on the detail table (column name = `htmlvar_name`). NOT stored as postmeta.

`geodir_get_post_info()` returns all columns, so custom field values are accessible as object properties (e.g. `$post->business_phone`).

A migrator must query `wp_geodir_custom_fields WHERE post_type = 'gd_place'` to discover which custom fields exist for that CPT — column names aren't known until runtime.

## 12. Author / Owner

Standard WordPress `post_author` (FK to `wp_users.ID`). No separate ownership model.

## 13. Tricky Bits

- **Multi-CPT.** GeoDirectory supports multiple post types (e.g. `gd_place`, `gd_event`). Each gets its own detail table (`wp_geodir_gd_event_detail`, etc.). Migration must dispatch per-CPT.
- **Coords as VARCHAR.** Lat/lng are stored as strings, not floats. Migrator must `(float)` cast before computing distance / writing to Listora's `listora_geo` table.
- **Review-vs-reply distinction.** Threaded comment replies look identical to reviews; only `comment_parent = 0` qualifies as a review.
- **Rating refresh.** `overall_rating` is denormalised — recompute or accept GeoDirectory's stored value when migrating; don't trust it from a stale dump.
- **Cache trap.** `geodir_get_post_info()` is cached under group `gd_post`. If a migration runs on a live site, stale cache entries can leak into the read; flush `gd_post` group before reading.
- **Search-title column.** `_search_title` is denormalized for full-text search — migration can usually ignore (Listora's own indexer rebuilds search-index after import).
- **v1 → v2 schema break.** GeoDirectory v1 used a monolithic `wp_geodir_gd_posts` table. v2+ uses per-CPT detail tables. A v1 source needs an extra migration step before the v2 schema applies. Detection: check if `wp_geodir_gd_posts` exists.
- **Module-conditional columns.** Columns like `business_hours`, `events_*`, `service_distance` only exist if the corresponding GeoDirectory add-on is installed. Migrator must `SHOW COLUMNS FROM wp_geodir_gd_place_detail` before assuming a column exists.
- **WPML / Polylang.** No native integration at the schema level — multi-language listings need WPML/Polylang post-translation tables in addition to this schema.

## Migration field-mapping outline (for Pro's `GeoDirectory_Migrator`)

```php
return array(
    // Listora-side key  =>  source resolver
    'title'              => fn ($source) => $source->post_title,
    'description'        => fn ($source) => $source->post_content,
    'phone'              => fn ($source) => $source->phone ?? '',                  // custom-field column
    'email'              => fn ($source) => $source->email ?? '',                  // custom-field column
    'website'            => fn ($source) => $source->website ?? '',                // custom-field column
    'lat'                => fn ($source) => (float) $source->latitude,
    'lng'                => fn ($source) => (float) $source->longitude,
    'address.street'     => fn ($source) => trim( ($source->street ?? '') . ' ' . ($source->street2 ?? '') ),
    'address.city'       => fn ($source) => $source->city,
    'address.region'     => fn ($source) => $source->region,
    'address.country'    => fn ($source) => $source->country,
    'address.postal'     => fn ($source) => $source->zip,
    'featured'           => fn ($source) => (bool) $source->featured,
    'rating.average'     => fn ($source) => (float) $source->overall_rating,
    'rating.count'       => fn ($source) => (int) $source->rating_count,
    'categories'         => fn ($source) => array_filter( explode( ',', (string) $source->post_category ) ),
    'primary_category'   => fn ($source) => (int) $source->default_category,
    'gallery'            => $loadFromGeodirAttachments,        // see §8
    'reviews'            => $loadFromGeodirPostReview,         // see §7
    'business_hours'     => fn ($source) => $source->business_hours ?? null, // only if Events add-on installed
);
```

`phone`, `email`, `website`, `business_hours` etc. assume they exist as custom-field columns. Migrator must introspect `wp_geodir_custom_fields` first to determine which columns the source site actually has.
