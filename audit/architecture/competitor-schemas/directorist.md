# Directorist — Data Schema Audit

> Source-of-truth schema audit for Pro's Directorist migrator. Compiled by `Explore` agent on 2026-05-18 against the live installation at `wp-content/plugins/directorist/` (v8.7.1). Every claim cited by file:line.

## 1. Identity

- **Plugin slug:** `directorist`
- **Version:** 8.7.1 (`directorist-base.php:6`)
- **Main plugin file:** `directorist-base.php`
- **Namespace:** `Directorist\*` (v7.1+ namespaced); legacy constants prefixed `ATBDP_*` for back-compat

## 2. Custom Post Types

| CPT slug | Label | File:line | supports[] |
|---|---|---|---|
| `at_biz_dir` | Listing | `includes/classes/class-custom-post.php:759` | `title`, `editor`, `author` |

Custom post statuses registered:
- `publish`, `pending`, `draft` (standard)
- `expired` (custom) — `class-custom-post.php:45`
- `rejected` (custom) — `class-custom-post.php:60`

## 3. Taxonomies

| Slug | Linked CPT | Hierarchical |
|---|---|---|
| `at_biz_dir-category` | `at_biz_dir` | true |
| `at_biz_dir-location` | `at_biz_dir` | true |
| `at_biz_dir-tags` | `at_biz_dir` | false |
| `atbdp_listing_types` | `at_biz_dir` | true (directory-type, multi-directory feature) |

All registered in `includes/classes/class-custom-taxonomy.php`.

**Term meta of note:**
- `_directory_type` (on categories/locations) — `includes/modules/multi-directory-setup/class-multi-directory-migration.php:52`
- `_default` (on directory types) — `class-multi-directory-migration.php:32`

## 4. Postmeta Keys (Listing Data)

Directorist stores everything in `wp_postmeta`. **No custom listing tables.** Field-key conventions:

### Contact
| Meta key | Type | Notes |
|---|---|---|
| `_phone` | string | primary phone |
| `_phone2` | string | secondary phone |
| `_email` | string | contact email |
| `_fax` | string | fax |
| `_website` | string | listing website URL |

### Address / Geo
| Meta key | Type | Notes |
|---|---|---|
| `_address` | string | full address as entered (single line, NOT split into street/city/state) |
| `_zip` | string | postal code |
| `_manual_lat` | string/float | latitude |
| `_manual_lng` | string/float | longitude |

Default fallback coords: `default_latitude` / `default_longitude` are site **options**, not per-listing meta — used when manual coords are empty. See `includes/classes/class-schema.php:263-264` + `includes/model/Listings.php:1564-1570`.

### Status & Expiration
| Meta key | Type | Notes |
|---|---|---|
| `_never_expire` | bool/int | if truthy, `_expiry_date` is ignored / deleted |
| `_expiry_date` | string | MySQL datetime |
| `_renewal_token` | string | renewal workflow token |
| `_refresh_renewal_token` | int | flag |
| `_renewal_reminder_sent` | int | tracking flag |
| `_listing_rejected_at` | string | MySQL datetime |
| `_listing_rejected_by` | int | user ID who rejected |
| `_listing_rejection_reason` | string | reason text |
| `_listing_status` | string | DEPRECATED — being phased to post_status in v7.10+ |

### Listing Management
| Meta key | Type | Notes |
|---|---|---|
| `_featured` | bool/int | featured flag |
| `_directory_type` | int | directory-type term ID (also stored as taxonomy term, see §3) |
| `_atbdp_post_views_count` | int | view counter |
| `_hide_contact_owner` | bool/int | hide contact form |
| `_hide_map` | bool/int | hide map widget |

### Multimedia
| Meta key | Type | Notes |
|---|---|---|
| `_listing_img` | serialised array | gallery — attachment IDs |
| `_listing_prv_img` | int | featured/preview image attachment ID |
| `_atbdp_listing_images` | int | alternate gallery storage (some versions) |
| `_videourl` | string | YouTube/Vimeo URL |
| `_thumbnail_id` | int | standard WP featured-image (also present) |

### SEO / Display
| Meta key | Type | Notes |
|---|---|---|
| `_tagline` | string | brief motto |
| `_excerpt` | string | mirrors `post_excerpt` (conditional — only if `display_excerpt_field` option enabled) |

### Pricing
| Meta key | Type | Notes |
|---|---|---|
| `_price` | string/float | numeric price |
| `_price_range` | string | tier ('cheap', 'moderate', 'expensive') |
| `_atbd_listing_pricing` | serialised array | structured pricing data |

### Reviews (aggregate on listing)
| Meta key | Type | Notes |
|---|---|---|
| `_directorist_listing_rating` | float | average 0–5 |
| `_directorist_listing_rating_counts` | serialised array | per-star counts, keys 1..5 |
| `_directorist_listing_review_count` | int | total reviews |

### Social
| Meta key | Type | Notes |
|---|---|---|
| `_social` | serialised array | per-platform URLs (facebook, twitter, linkedin, ...) |

### Custom Fields
Dynamic — directory-builder defines `field_key` from form configuration; postmeta key generally matches `field_key` with `_` prefix (e.g. widget_key `phone` → meta key `_phone`). Authoritative mapping at `includes/modules/multi-directory-setup/class-multi-directory-migration.php:137-250`.

## 5. Custom Database Tables

**None.** Directorist is 100% on WordPress core tables:
- `wp_posts` — listings
- `wp_postmeta` — listing data
- `wp_term_taxonomy` / `wp_terms` / `wp_termmeta` — taxonomies (incl. directory types)
- `wp_comments` / `wp_commentmeta` — reviews (v7.1+; migrated from legacy custom `atbdp_reviews` table — see `class-installation.php:28` for the migrator)

## 6. Review Storage

**v7.1+ uses `wp_comments`.**

- Listing FK: `comment_post_ID`
- Author: `comment_author` (guest) or `user_id` (logged-in)
- Body: `comment_content`
- Approval: `comment_approved`
- Type: `comment_type` ('' or 'review')

**Rating metadata** in `wp_commentmeta`:
- `_directorist_reviewer_rating` — int 1–5 (star rating)
- `_reviewer_details` — serialised array (name, email)
- `_review_status` — string

Aggregate values are denormalised onto the listing as `_directorist_listing_rating` / `_directorist_listing_rating_counts` / `_directorist_listing_review_count` (see §4).

Meta key constants at `includes/review/class-listing-review-meta.php:16-46`.

**v7.0 → v7.1 migration:** old installs may still have stale rows in deleted `atbdp_reviews` table. Migrator should query `wp_comments` only.

## 7. Image / Gallery Storage

**Featured image:** standard WP `_thumbnail_id`, ALSO mirrored as `_listing_prv_img`.

**Gallery:** `_listing_img` postmeta — **serialised array** of attachment IDs. Example: `a:3:{i:0;i:123;i:1;i:456;i:2;i:789;}`. Retrieved via `directorist_get_listing_gallery_images()` at `includes/helper-functions.php:4899`. Use `wp_parse_id_list()` which tolerates both serialised array and comma-separated string forms.

Save sites: `includes/custom-actions.php:169`, `includes/classes/class-add-listing.php:662`.

## 8. Geo Storage

**Address:** `_address` — **single-string format** (full address as entered by submitter). NOT split into street/city/state/zip — migrator must parse if downstream wants components.

**Postal code:** `_zip` (separate field).

**Coords:** `_manual_lat` / `_manual_lng` (per-listing, may be empty).

**Fallback:** site options `default_latitude` / `default_longitude` used when `_manual_lat` is empty.

## 9. Categories / Tags

- Categories: taxonomy `at_biz_dir-category` (hierarchical)
- Tags: taxonomy `at_biz_dir-tags` (flat)
- Locations: taxonomy `at_biz_dir-location` (hierarchical — IS used as taxonomy, not just postmeta)
- Directory type: taxonomy `atbdp_listing_types` AND mirrored as postmeta `_directory_type` (taxonomy is source of truth)

**Does NOT use:** WP-native `category` / `post_tag`.

## 10. Author / Owner

Standard WP `post_author`. No separate ownership meta or vendor model.

## 11. Tricky Bits / Migration Gotchas

- **Expiration dual-key:** `_never_expire` takes precedence; `_expiry_date` is deleted when `_never_expire` is set (`class-custom-post.php:425-427`). Migrator must check `_never_expire` first.
- **Gallery serialisation:** `_listing_img` is serialised PHP array — use `maybe_unserialize()` + `wp_parse_id_list()` (the latter tolerates both forms).
- **Directory-type dual storage:** stored in BOTH `atbdp_listing_types` taxonomy AND `_directory_type` postmeta — taxonomy is canonical; postmeta is denormalised backup. See `class-multi-directory-migration.php:73`.
- **Review-table v7.1.0 break:** old installs may have leftover rows in the deleted custom `atbdp_reviews` table. Don't migrate those — they're already in `wp_comments` after the in-plugin migration callback `directorist_710_migrate_reviews_table_to_comments_table` (`class-installation.php:28`).
- **Status enum shift in v7.10+:** `_listing_status` meta deprecated; `_expiry_date` "expired" stage being phased into `post_status='expired'`. See `class-installation.php:34` (`directorist_7100_migrate_expired_meta_to_expired_status`).
- **Conditional fields:** some postmeta keys (`_excerpt`, `_tagline`, `_price`, `_price_range`) only present if the corresponding `display_*_field` option is enabled. Migrator must not assume presence.
- **Multi-directory mode (v8.0+):** `_directory_type` is REQUIRED on every listing. Single-directory mode is permissive (assumes default type).
- **Widget-key ≠ meta-key always:** `widget_key=listing_content` → uses `post_content` (NOT a meta key). `widget_key=listing_title` → `post_title`. Authoritative mapping at `class-multi-directory-migration.php:137-250`.
- **Address single-string:** unlike GeoDirectory/HivePress, Directorist stores the full address as one string. Migrator must geocode or string-parse to extract components.

## Migration field-mapping outline (for Pro's `Directorist_Migrator`)

```php
return array(
    'title'             => fn ( WP_Post $source ) => $source->post_title,
    'description'       => fn ( $source ) => $source->post_content,
    'excerpt'           => fn ( $source ) => $source->post_excerpt
                              ?: (string) get_post_meta( $source->ID, '_excerpt', true ),
    'phone'             => fn ( $source ) => (string) get_post_meta( $source->ID, '_phone', true ),
    'phone_alt'         => fn ( $source ) => (string) get_post_meta( $source->ID, '_phone2', true ),
    'email'             => fn ( $source ) => (string) get_post_meta( $source->ID, '_email', true ),
    'fax'               => fn ( $source ) => (string) get_post_meta( $source->ID, '_fax', true ),
    'website'           => fn ( $source ) => (string) get_post_meta( $source->ID, '_website', true ),
    'tagline'           => fn ( $source ) => (string) get_post_meta( $source->ID, '_tagline', true ),
    'price'             => fn ( $source ) => (string) get_post_meta( $source->ID, '_price', true ),
    'lat'               => fn ( $source ) => (float) get_post_meta( $source->ID, '_manual_lat', true ),
    'lng'               => fn ( $source ) => (float) get_post_meta( $source->ID, '_manual_lng', true ),
    'address'           => fn ( $source ) => (string) get_post_meta( $source->ID, '_address', true ),
    'postal'            => fn ( $source ) => (string) get_post_meta( $source->ID, '_zip', true ),
    'expiry'            => $expiryResolver,  // checks _never_expire first, then _expiry_date
    'featured'          => fn ( $source ) => (bool) get_post_meta( $source->ID, '_featured', true ),
    'video_url'         => fn ( $source ) => (string) get_post_meta( $source->ID, '_videourl', true ),
    'social_links'      => $socialResolver,  // unserialise _social
    'gallery'           => $galleryResolver, // unserialise _listing_img + featured
    'rating_avg'        => fn ( $source ) => (float) get_post_meta( $source->ID, '_directorist_listing_rating', true ),
    'rating_count'      => fn ( $source ) => (int) get_post_meta( $source->ID, '_directorist_listing_review_count', true ),
    'reviews'           => $reviewsResolver, // wp_comments + commentmeta (rating + reviewer details)
    'categories'        => fn ( $source ) => wp_get_post_terms( $source->ID, 'at_biz_dir-category', array( 'fields' => 'ids' ) ),
    'tags'              => fn ( $source ) => wp_get_post_terms( $source->ID, 'at_biz_dir-tags', array( 'fields' => 'ids' ) ),
    'locations'         => fn ( $source ) => wp_get_post_terms( $source->ID, 'at_biz_dir-location', array( 'fields' => 'ids' ) ),
    'directory_type'    => fn ( $source ) => wp_get_post_terms( $source->ID, 'atbdp_listing_types', array( 'fields' => 'ids' ) )[0] ?? 0,
);
```
