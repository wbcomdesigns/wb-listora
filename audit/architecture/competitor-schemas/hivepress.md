# HivePress — Data Schema Audit

> Source-of-truth schema audit for Pro's HivePress migrator. Compiled by `Explore` agent on 2026-05-18 against the live installation at `wp-content/plugins/hivepress/` (v1.7.23). Every claim cited by file:line.

## 1. Identity

- **Plugin slug:** `hivepress`
- **Version:** 1.7.23
- **Main file:** `hivepress.php`
- **Namespaces:** `HivePress\Models\`, `HivePress\Helpers\`, `HivePress\Fields\`
- **Prefix:** `hp_` — every postmeta and termmeta key uses it. Applied automatically by `hp\prefix()` (`includes/helpers.php:19-34`)

## 2. Custom Post Types

| CPT slug | Singular | Supports | File:line | Notes |
|---|---|---|---|---|
| `hp_listing` | Listing | title, editor, thumbnail | `includes/configs/post-types.php:55-78` | rewrite slug `listing` |
| `hp_vendor` | Vendor | title, editor, thumbnail | `includes/configs/post-types.php:80-104` | rewrite slug `vendor` |
| `hp_email` | Email | title, editor | `includes/configs/post-types.php:14-32` | internal — template system |
| `hp_template` | Template | title, editor | `includes/configs/post-types.php:34-53` | internal — block templates |

## 3. Taxonomies

| Slug | Linked CPT | Hierarchical | File:line |
|---|---|---|---|
| `hp_listing_category` | `hp_listing` | true | `includes/configs/taxonomies.php:14-31` |
| `hp_vendor_category` | `hp_vendor` | true | `includes/configs/taxonomies.php:33-50` |

## 4. Postmeta keys

HivePress's Model abstraction (`includes/models/class-model.php:136-138`) auto-prefixes `hp_`. Fields with `_external: true` and no `_alias` land in postmeta with key `hp_{field_name}`.

### Listing postmeta (`class-listing.php:85-130`)

| Meta key | Type | Notes |
|---|---|---|
| `hp_drafted` | bool | draft workflow flag |
| `hp_featured` | bool | featured listing |
| `hp_verified` | bool | admin verification |
| `hp_expired_time` | int (Unix ts) | when listing auto-expires |
| `hp_featured_time` | int (Unix ts) | when featured status ends |

### Vendor postmeta (`class-vendor.php:57-60`)

| Meta key | Type |
|---|---|
| `hp_verified` | bool |

### User postmeta (`class-user.php:76-138`)

| Meta key | Type | Notes |
|---|---|---|
| `hp_first_name` | string ≤ 64 chars | (overrides WP's standard `first_name`) |
| `hp_last_name` | string ≤ 64 chars | (overrides WP's standard `last_name`) |
| `hp_description` | string ≤ 2048 chars | bio |
| `hp_verified` | bool | |
| `hp_image__id` | int | attachment post ID — profile image |

### Category termmeta (`class-listing-category.php:51-76` and parallel for vendor categories)

| Meta key | Type |
|---|---|
| `hp_sort_order` | int — display order |
| `hp_image__id` | int — category thumbnail attachment ID |

### Attachment postmeta — gallery linking (`class-attachment.php:66-76`)

| Meta key | Type | Notes |
|---|---|---|
| `hp_parent_model` | string | which model owns this attachment (`listing`, `vendor`) |
| `hp_parent_field` | string | which field on parent (`images`, `image`) |

## 5. Custom database tables

**None.** Everything lives in standard WP tables: `wp_posts`, `wp_postmeta`, `wp_terms`, `wp_termmeta`, `wp_users`, `wp_usermeta`, `wp_comments`, `wp_commentmeta`.

## 6. Listing Model field map (`class-listing.php:39-173`)

Inherits from `class-post.php`. Storage rules:
- `_alias = post_title` etc. → stored in `wp_posts` column
- `_external: true` → `wp_postmeta` with key `hp_{field_name}`
- `_relation: 'many_to_many'` → `wp_term_relationships`
- `_relation: 'one_to_many'` → child posts with back-reference meta

| Field | Type | Storage | Notes |
|---|---|---|---|
| `title` | text | `post_title` | ≤256 chars |
| `slug` | text | `post_name` | |
| `description` | textarea | `post_content` | ≤10,240 chars, HTML OK |
| `status` | select | `post_status` | publish / draft / pending / future / private / trash / auto-draft / inherit |
| `drafted` | checkbox | `hp_drafted` postmeta | |
| `featured` | checkbox | `hp_featured` postmeta | |
| `verified` | checkbox | `hp_verified` postmeta | |
| `created_date` | date | `post_date` | |
| `created_date_gmt` | date | `post_date_gmt` | |
| `modified_date` | date | `post_modified` | |
| `expired_time` | number | `hp_expired_time` postmeta | Unix ts |
| `featured_time` | number | `hp_featured_time` postmeta | Unix ts |
| `user` | id | `post_author` | creator |
| `vendor` | id | `post_parent` | optional `hp_vendor` post ID |
| `categories` | select (multi) | `hp_listing_category` taxonomy | many-to-many |
| `image` | id | `_thumbnail_id` postmeta | standard WP featured image |
| `images` | attachment_upload (multi) | child attachments via `hp_parent_field` postmeta | gallery; max 10 files (jpg/jpeg/png) |

## 7. Review storage

**Not stored by HivePress core (v1.7.23).** No built-in reviews or ratings. The `HivePress\Models\Comment` abstract base exists but is only used for internal email/template comment behaviour, NOT customer reviews.

Migrator: skip reviews unless source site has a HivePress reviews extension installed (in which case the schema lives in that extension).

## 8. Image / gallery storage

- **Featured image:** standard WP `_thumbnail_id` postmeta
- **Gallery:** SEPARATE attachment posts (`post_type = 'attachment'`) linked back via:
  - `post_parent = {listing_id}`
  - `hp_parent_model = 'listing'` postmeta
  - `hp_parent_field = 'images'` postmeta
  - `menu_order` for gallery sort
- **Retrieval:** `get_attached_media()` filtered by `hp_parent_field` meta (`class-listing.php:220-224`). `get_images__id()` includes both featured + gallery (`class-listing.php:201-244`).

## 9. Geo storage

**Not in core.** Lat/lng/address are handled by the HivePress Geolocation add-on (separate plugin). If installed, it adds its own field set; migrator must detect presence.

## 10. Categories

Standard WP `wp_term_relationships` against `hp_listing_category` (hierarchical). Multi-category supported.

Term fields (`class-listing-category.php:28-77`):
- `name`, `description`, `parent` — standard
- `sort_order` → `hp_sort_order` termmeta
- `image` → `hp_image__id` termmeta

## 11. Author / Owner

- **Direct ownership:** `post_author` (WP standard) → User model
- **Vendor relationship:** `post_parent` (optional) → `hp_vendor` post → that vendor's `post_author` is the owning user

Two-level pattern: User → Vendor → Listing(s). A listing may have NO vendor (post_parent=0), in which case ownership is directly via post_author.

## 12. Pricing / inventory / booking

**Not in core (v1.7.23).** Handled by:
- HivePress Bookings extension
- HivePress Marketplace extension

Neither is part of the core schema; migrator should skip unless detecting the add-on.

## 13. Tricky bits / migration gotchas

- **Model abstraction layer:** field names in code (`featured`) DON'T equal meta keys directly. The Model base class applies `hp_` prefix when `_external: true` and no `_alias`. Always trace through the Model class to find the real meta key.
- **Image field duality:** `image` field (singular) = featured (`_thumbnail_id`); `images` field (plural) = gallery via child attachments with `hp_parent_field='images'` meta. They're DIFFERENT storage mechanisms.
- **Vendor as optional parent:** `post_parent` can be 0 (no vendor) or a `hp_vendor` post ID. Validate before assuming ownership chain.
- **No review tables:** core has no review/rating storage. If migrating a site with reviews, the source must have a HivePress reviews extension and the migrator needs to know that extension's schema.
- **Prefix is universal:** every `hp_*` key is generated via `hp\prefix()`. Never hardcode prefixes — always reference the field name + model layer.
- **Relation types:**
  - `many_to_many` → `wp_term_relationships`
  - `one_to_many` → child posts back-linked by meta
  - no explicit relation → postmeta
- **User-meta override:** HivePress's `hp_first_name`/`hp_last_name` OVERRIDE WP's standard `first_name`/`last_name` meta — both may exist. Migrator should prefer `hp_*` versions.
- **Listing status enum:** `drafted` field is a SEPARATE checkbox from `post_status`. A listing can be `post_status='draft'` OR `post_status='publish'` with `hp_drafted=1` (different semantics). Read both.
- **No custom tables = no schema drift:** all data is in standard WP tables. Migration is mechanically simpler than GeoDirectory or WPBDP.
- **Extension stacking:** if the source has HivePress Bookings + Marketplace + Geolocation + Reviews installed, each adds its own fields via hooks (`hivepress/v1/models/listing`). Migrator must enumerate active add-ons before assuming the core schema is complete.

## Migration field-mapping outline (for Pro's `HivePress_Migrator`)

```php
return array(
    'title'         => fn ( $post ) => $post->post_title,
    'description'   => fn ( $post ) => $post->post_content,
    'slug'          => fn ( $post ) => $post->post_name,
    'drafted'       => fn ( $post ) => (bool) get_post_meta( $post->ID, 'hp_drafted', true ),
    'featured'      => fn ( $post ) => (bool) get_post_meta( $post->ID, 'hp_featured', true ),
    'verified'      => fn ( $post ) => (bool) get_post_meta( $post->ID, 'hp_verified', true ),
    'expired_time'  => fn ( $post ) => (int) get_post_meta( $post->ID, 'hp_expired_time', true ),
    'featured_time' => fn ( $post ) => (int) get_post_meta( $post->ID, 'hp_featured_time', true ),
    'owner_user'    => fn ( $post ) => (int) $post->post_author,
    'vendor_id'     => fn ( $post ) => (int) $post->post_parent,
    'categories'    => fn ( $post ) => wp_get_post_terms( $post->ID, 'hp_listing_category', array( 'fields' => 'ids' ) ),
    'featured_img'  => fn ( $post ) => (int) get_post_meta( $post->ID, '_thumbnail_id', true ),
    'gallery'       => function ( $post ) {
        return get_posts( array(
            'post_type'      => 'attachment',
            'post_parent'    => $post->ID,
            'meta_query'     => array(
                array( 'key' => 'hp_parent_field', 'value' => 'images' ),
            ),
            'orderby'        => 'menu_order',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ) );
    },
    // reviews: skip — not in core
    // geo: skip — only present if Geolocation extension installed
    // price: skip — only present if Bookings/Marketplace installed
);
```
