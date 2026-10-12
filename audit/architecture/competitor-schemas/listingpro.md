# ListingPro — Data Schema Audit

> What Free's ListingPro migrator reads, written from `includes/import-export/class-listingpro-migrator.php` on branch 2.0.0 (2026-10-12). ListingPro is a theme plus companion plugin, not a WordPress.org plugin, so this is the shape the migrator expects rather than an audit of a vendor checkout. Every claim cites the migrator.

## 1. Identity and detection

- **Source slug:** `listingpro` (`class-listingpro-migrator.php:29`)
- **Detected when** the CPT `listing` and the taxonomy `listing-category` are registered (theme active), or, with the theme inactive, when `listing` posts exist and at least one carries `_lp_listingpro_options` (`:37-63`).

## 2. Custom Post Types

| CPT slug | Used for | Migrator |
|---|---|---|
| `listing` | Listings (statuses publish, pending, draft, private) | `:53`, `:96` (batched by ID, `LIMIT/OFFSET`) |
| `developer_developer_review` | Reviews, `post_parent` = listing ID | `:353-358` |

## 3. Taxonomies

| Slug | Becomes |
|---|---|
| `listing-category` | Listing category (`:267`) |
| `location` | Listing location (`:273`) |
| `features` | Listing features (`:279`) |

## 4. Postmeta keys

| Meta key | Type | Becomes |
|---|---|---|
| `_phone` | string | `phone` |
| `_email` | string | `email` |
| `_website` | string | `website` |
| `_address` | string | `address` |
| `_price` | string | `price` |
| `_price_range` | string | `price_range` (`:230-232`) |
| `_lp_listingpro_options` | serialized array | `business_hours` from `['business_hours']` (raw copy kept in `_migrated_hours_raw` when it cannot be read), `social_links` from `['social']` (`:236-250`) |
| `_gallery` | attachment IDs, array or comma-separated | `gallery` (`:296-318`) |

## 5. Reviews

1. `developer_developer_review` posts (publish, pending) with `_review_rating` postmeta; publish becomes approved, pending stays pending (`:345-380`).
2. If a listing has none, WordPress comments on the listing are used instead: rating from comment meta `rating`, approved comments become approved, trash and spam are skipped (`:393-425`).

## 6. Not migrated

Claims, favourites, packages/plans and payments are not read. Re-running the migrator is safe: listings already migrated are skipped by their source ID (Migration_Base).
