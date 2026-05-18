# Business Directory Plugin (WPBDP) — Data Schema Audit

> Source-of-truth schema audit for Pro's WPBDP migrator. Compiled by `Explore` agent on 2026-05-18 against the live installation at `wp-content/plugins/business-directory-plugin/` (v6.4.23 / DB schema 18.7). Every claim cited by file:line.

## 1. Identity

- **Plugin slug:** `business-directory-plugin`
- **Version:** 6.4.23 (DB schema 18.7)
- **Main file:** `business-directory-plugin.php`
- **Constants:** `WPBDP_POST_TYPE = 'wpbdp_listing'`, `WPBDP_CATEGORY_TAX = 'wpbdp_category'`, `WPBDP_TAGS_TAX = 'wpbdp_tag'` (`class-wpbdp.php:66-68`)
- **Namespace prefix:** `WPBDP_` (legacy `WPBDM_` aliases retained)

## 2. Custom Post Types

| CPT slug | Singular | Supports |
|---|---|---|
| `wpbdp_listing` | Listing | `title`, `editor`, `author`, `thumbnail`, `excerpt`, `comments`, `custom-fields` |

Registered at `class-cpt-integration.php:44`. `show_in_rest: true`.

## 3. Taxonomies

| Slug | Hierarchical | File:line |
|---|---|---|
| `wpbdp_category` | true | `class-cpt-integration.php:57` |
| `wpbdp_tag` | false | `class-cpt-integration.php:72` |

Standard `wp_terms` / `wp_term_taxonomy` storage.

## 4. Custom Database Tables

WPBDP ships 5 custom tables (`{prefix}_wpbdp_*`):

### 4.1 `wpbdp_form_fields` — field catalog (CRITICAL)

Schema at `installer.php:88-101`. Admins define fields here; field values land in postmeta keyed by row `id`.

| Column | Type | Notes |
|---|---|---|
| `id` | `bigint(20)` AUTO_INCREMENT PK | referenced in postmeta key `_wpbdp[fields][{id}]` |
| `label` | `varchar(255)` | human label (e.g. "Business Phone") |
| `description` | `varchar(255)` | admin help text |
| `field_type` | `varchar(100)` | `textfield`, `textarea`, `url`, `select`, `multiselect`, `checkbox`, `radio`, `image`, `date`, `phone`, `social-twitter`, `social-facebook`, `social-linkedin`, ... |
| `association` | `varchar(100)` | **storage target — see §5**: `title`, `content`, `excerpt`, `category`, `tags`, `meta`, `custom` |
| `validators` | `text` | JSON array (`required`, `unique`, ...) |
| `weight` | `int(5)` | display order (higher first) |
| `display_flags` | `text` | JSON array of visibility flags (`excerpt`, `listing`, `search`, ...) |
| `field_data` | `blob` | JSON field-specific options |
| `shortname` | `varchar(255)` | machine slug |
| `tag` | `varchar(255)` | semantic tag — `title`, `website`, `email`, `phone`, `fax`, `address`, `zip` (when present, this is the migrator's quickest path to identify the field's semantic role) |

### 4.2 `wpbdp_listings` — per-listing plan + lifecycle (`installer.php:147-160`)

| Column | Type | Notes |
|---|---|---|
| `listing_id` | `bigint(20)` PK | FK to `wp_posts.ID` |
| `fee_id` | `bigint(20)` | FK to `wpbdp_plans.id` |
| `fee_price` | `decimal(10,2)` | cached |
| `fee_days` | `smallint unsigned` | validity period |
| `fee_images` | `smallint unsigned` | image-count limit |
| `expiration_date` | `timestamp` | auto-computed |
| `is_recurring` | `tinyint(1)` | subscription? |
| `is_sticky` | `tinyint(1)` | featured |
| `subscription_id` | `varchar(255)` | gateway subscription ref |
| `subscription_data` | `longblob` | serialised gateway data |
| `listing_status` | `varchar(255)` | enum — see §11 |
| `flags` | `varchar(255)` | CSV |

### 4.3 `wpbdp_plans` — fee plans catalog (`installer.php:103-119`)

Pricing plan definitions — `id`, `label`, `description`, `amount`, `days`, `images`, `sticky`, `recurring`, `pricing_model`, `pricing_details`, `supported_categories`, `enabled`, `extra_data`, `tag`. Migrator: usually map to Listora's plan system or ignore.

### 4.4 `wpbdp_payments` — payment history (`installer.php:121-145`)

Payment/transaction log. Migrator can usually skip (legacy payment data) unless explicit request.

### 4.5 `wpbdp_logs` — audit log (`installer.php:167-181`)

Listing/payment/admin event log. Not migration-critical; skip.

## 5. WPBDP's field-storage model — CRITICAL

**WPBDP does NOT use canonical postmeta keys.** Field values are stored at `_wpbdp[fields][{FIELD_ID}]` postmeta entries, where `{FIELD_ID}` is the numeric `id` from `wpbdp_form_fields`.

### Discovery workflow for a migrator

1. Query `wp_wpbdp_form_fields` to enumerate all defined fields.
2. For each field: read `id`, `label`, `field_type`, `association`, `tag`.
3. For each listing: read `wp_postmeta` rows with key `_wpbdp[fields][{id}]`.

### Association → storage-target map (`form-fields.php:32-39`)

| `association` | Storage | Key pattern |
|---|---|---|
| `title` | `wp_posts.post_title` | n/a |
| `content` | `wp_posts.post_content` | n/a |
| `excerpt` | `wp_posts.post_excerpt` | n/a |
| `category` | `wp_term_relationships` (taxonomy `wpbdp_category`) | n/a |
| `tags` | `wp_term_relationships` (taxonomy `wpbdp_tag`) | n/a |
| `meta` | `wp_postmeta` | `_wpbdp[fields][{id}]` |
| `custom` | `wp_postmeta` | `_wpbdp[fields][{id}]` (alias for `meta`) |

### Semantic tag fast path

When `wpbdp_form_fields.tag` is non-empty, it's the migrator's shortcut to identify the field. Standard tag values: `title`, `website`, `email`, `phone`, `fax`, `address`, `zip`. Use these to map source fields to Listora's canonical keys WITHOUT depending on the human label.

Multivalue fields (select/checkbox) store serialised arrays in the postmeta entry.

## 6. Other fixed postmeta keys

| Meta key | Stored on | Purpose |
|---|---|---|
| `_wpbdp[images]` | listing | serialised gallery — array of attachment IDs |
| `_wpbdp_image_weight` | each image attachment | int — gallery sort weight |
| `_wpbdp_image_caption` | each image attachment | string — caption |
| `_thumbnail_id` | listing | standard WP featured image (fallback source) |
| `_wpbdp[thumbnail_id]` | listing | explicit WPBDP thumbnail override |
| `_wpbdp[access_key]` | listing | SHA1 token — unlisted-listing access |
| `_wpbdp[import_sequence_id]` | listing | int — CSV import batch sequence ID |
| `_gateway_suscription_cancel_status` | listing | subscription cancellation state |

## 7. Review storage

**Standard `wp_comments`** with `comment_post_ID = listing_id`. No separate review/rating custom table. Ratings (if used) are managed via plugin hooks or stored as postmeta — NOT in WPBDP's custom tables.

`comments_open` filter at `class-cpt-integration.php:234-249` controls whether listing comments are accepted.

## 8. Image / gallery storage

- **Featured image:** `_thumbnail_id` (standard WP)
- **Gallery:** `_wpbdp[images]` postmeta = serialised array of attachment IDs
- **Per-image meta:** `_wpbdp_image_weight` (sort), `_wpbdp_image_caption` (caption) — both on the attachment post itself
- **Parent relationship:** attachments have `post_parent = listing_id`
- **Plan limit:** `wpbdp_listings.fee_images`

Get/set logic at `class-listing.php:45-176`.

## 9. Geo storage

**Not stored by WPBDP core.** Address fields are user-defined via `wpbdp_form_fields` — admins typically create custom textfields with `tag = 'address'` / `tag = 'zip'`. Lat/lng require an add-on.

Migrator strategy: query `wpbdp_form_fields WHERE tag IN ('address', 'zip')` then read the corresponding `_wpbdp[fields][{id}]` postmeta entries. Geocode address strings to get lat/lng if Listora needs coordinates.

## 10. Categories

Standard WP `wp_term_relationships` against taxonomy `wpbdp_category`. Multi-category supported (`wp_set_post_terms()`). Logic at `class-listing.php:321-331`.

## 11. Listing status enum

`wpbdp_listings.listing_status` values (`class-listing.php:1040-1055`):

`unknown` (initial) · `legacy` (v5 holdover) · `incomplete` (no fee plan) · `pending_payment` · `complete` (active) · `pending_upgrade` · `expired` · `pending_renewal` · `abandoned`

**Distinct from `wp_posts.post_status`** — listings with `post_status='publish'` can still have `listing_status='expired'`. Migrator should treat `wpbdp_listings.listing_status` as the source of truth for "is this listing actually live."

## 12. Author / owner

Standard `wp_posts.post_author`. No separate vendor entity in WPBDP core.

## 13. Tricky bits / migration gotchas

- **v5.x → v6.x payment-data shape changed** — payment_items blob is serialised differently. If migrating from v5 install, double-check the unserialise.
- **Field IDs are NOT stable across sites** — never hardcode. Migrator must rebuild the field-id map from `wpbdp_form_fields` for each source site.
- **Listing status ≠ post_status** — must read BOTH. A `complete` listing is live; `expired` is not.
- **Plan caching** — `fee_price`, `fee_days`, `fee_images` are CACHED in `wpbdp_listings` at assignment time. If source admin deleted a plan, the listing retains its cache. Migrator should use the cached values, not chase the plan row.
- **No geo storage in core** — assume lat/lng are absent unless the site has the WPBDP Geolocation add-on.
- **Image attachment per-attachment meta** — `_wpbdp_image_weight` / `_wpbdp_image_caption` are on the IMAGE post, not the listing. Migrator must read attachment postmeta separately.
- **Default fields** — `installer.php:43` creates default custom fields on install. Field labels vary by plugin version. Always introspect `wpbdp_form_fields` rather than assume canonical labels.

## Migration field-mapping outline (for Pro's `WPBDP_Migrator`)

```php
// Step 1: build field-id map per source site
$fields = $wpdb->get_results( "SELECT id, label, field_type, association, tag FROM {$wpdb->prefix}wpbdp_form_fields" );
$by_tag = array();
foreach ( $fields as $f ) {
    if ( ! empty( $f->tag ) ) {
        $by_tag[ $f->tag ] = $f;
    }
}

// Step 2: per-listing extraction uses the map
return array(
    'title'       => fn ( $post ) => $post->post_title,
    'description' => fn ( $post ) => $post->post_content,
    'excerpt'     => fn ( $post ) => $post->post_excerpt,
    'phone'       => fn ( $post ) => self::read_wpbdp_field( $post->ID, $by_tag['phone']->id ?? 0 ),
    'email'       => fn ( $post ) => self::read_wpbdp_field( $post->ID, $by_tag['email']->id ?? 0 ),
    'website'     => fn ( $post ) => self::read_wpbdp_field( $post->ID, $by_tag['website']->id ?? 0 ),
    'address'     => fn ( $post ) => self::read_wpbdp_field( $post->ID, $by_tag['address']->id ?? 0 ),
    'postal'      => fn ( $post ) => self::read_wpbdp_field( $post->ID, $by_tag['zip']->id ?? 0 ),
    'gallery'     => fn ( $post ) => maybe_unserialize( get_post_meta( $post->ID, '_wpbdp[images]', true ) ?: array() ),
    'categories'  => fn ( $post ) => wp_get_post_terms( $post->ID, 'wpbdp_category', array( 'fields' => 'ids' ) ),
    'tags'        => fn ( $post ) => wp_get_post_terms( $post->ID, 'wpbdp_tag', array( 'fields' => 'ids' ) ),
    'lifecycle'   => fn ( $post ) => $wpdb->get_row( $wpdb->prepare( "SELECT listing_status, expiration_date, is_sticky, fee_id FROM {$wpdb->prefix}wpbdp_listings WHERE listing_id = %d", $post->ID ) ),
);
```
