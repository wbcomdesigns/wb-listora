# How to Migrate from HivePress to WB Listora

> **Since 1.2.0.** The HivePress migrator is built into WB Listora Free.

HivePress is a plugin-based directory framework that stores listings as a custom post type (`hp_listing`). Migrating to WB Listora moves your listings, categories, images, and reviews into Listora's data model while keeping your WordPress setup and theme intact.

![Migrate From Hivepress - migration tool in the Listora admin](images/migrate-from-listingpro.png)

## Before you begin

1. **Back up your site.** Run a full backup (files + database) before starting. Use UpdraftPlus, BlogVault, or `wp db export`.
2. **Keep HivePress active or its data in the database.** The migrator reads HivePress data directly from the database. The plugin does not need to be active, but the `hp_listing` posts and their metadata must still be in `wp_posts` and `wp_postmeta`.
3. **Install and activate WB Listora.** Complete the setup wizard so your listing types, categories, and pages are ready to receive imported data.

## What gets migrated

| Data type | HivePress source | WB Listora destination |
|---|---|---|
| Listings | `hp_listing` custom post type | `listora_listing` CPT |
| Categories | `hp_listing_category` taxonomy | `listora_listing_cat` taxonomy |
| Featured images | `_thumbnail_id` post meta | Post thumbnail |
| Gallery images | Child attachment posts linked by `hp_parent_field = 'images'` | Listing gallery |
| Listing status | `publish`, `pending`, `draft`, `private`, `future` | Mapped to the equivalent Listora status |
| Ownership | Resolved via HivePress vendor chain (`post_parent` → `hp_vendor` → `post_author`) or direct `post_author` | `post_author` on the new listing |
| Reviews (if present) | WP comment meta with a rating value (from HivePress reviews extensions) | `listora_reviews` table |

## What does not get migrated

- **HivePress-specific meta fields** that have no direct equivalent in WB Listora's field model (e.g., package/pricing meta from HivePress monetization extensions).
- **Vendor profiles** - HivePress vendors are a separate post type. Ownership transfers to the WordPress user behind each vendor, but the vendor post itself does not become a Listora entity.
- **Bookings, packages, orders** - HivePress commerce extensions store data in their own tables. Listora does not read these.
- **Geo coordinates** - HivePress core does not store lat/lng (no custom geo table). If you use a HivePress maps extension that stores coordinates in meta, re-enter addresses after migration via the listing edit screen so Listora can geocode them.

## Step-by-step migration guide

### Step 1: Install WB Listora alongside HivePress

Install and activate WB Listora. The two plugins do not conflict - they use different post types. Run the Setup Wizard to configure your listing types.

### Step 2: Open the migration tool

Go to **Listora → Tools → Migrate**. If HivePress data is detected in your database (specifically `hp_listing` posts), you see a **HivePress** source card with a count of listings found.

### Step 3: Preview the migration

Click the HivePress source card. You can see:
- How many listings will be imported.
- How many categories and how many images were found.
- Whether any reviews were detected.

### Step 4: Run the migration

Click **Start Migration**. The migrator processes listings in batches. You can watch progress on screen; the tool shows how many have been processed and how many remain.

For large directories, use WP-CLI to avoid browser timeout:

```bash
wp listora migrate --from=hivepress --dry-run   # preview first
wp listora migrate --from=hivepress              # run the migration
wp listora migrate --from=hivepress --batch-size=50
```

If you need to re-run (for example after a connectivity issue), the migrator skips listings already transferred - duplicate detection prevents double-imports.

### Step 5: Verify your data

1. Browse **Listora → All Listings** and open a few listings to check content, categories, and images.
2. Go to the frontend directory page and confirm listings appear with the right type and category filters.
3. If your HivePress site had reviews, open a listing's Reviews tab and confirm they transferred.
4. Run a search index rebuild: `wp listora reindex --all`.

### Step 6: Address missing geo data

Because HivePress core does not store coordinates, any listing that had an address field in HivePress needs its location set in Listora. Open each listing in the Listora admin or from the frontend dashboard, find the address field, and re-enter the address. Listora geocodes it automatically on save.

### Step 7: Set up redirects

Create 301 redirects from your old HivePress listing URLs (`/listings/{slug}/`) to the new Listora URLs (`/listings/{slug}/` if slugs match, otherwise map individually). Use a redirection plugin or update your `.htaccess` / Nginx config.

## Frequently asked questions

### Can I run the migration after deactivating HivePress?

Yes, as long as the `hp_listing` posts still exist in `wp_posts`. The migrator reads from the database directly. You can deactivate HivePress before migrating; just do not delete the plugin (which would remove its post data) until after you have verified the migration.

### What happens to listing slugs?

Listora preserves the original `post_name` (URL slug) from each HivePress listing. If the slug is already taken by another Listora listing, WordPress appends a suffix (`-2`, `-3`, etc.) to avoid conflicts.

### Will my existing HivePress URLs still work?

Not automatically. HivePress and Listora use different URL structures. Set up 301 redirects as described in Step 7 to preserve any inbound links or bookmarks.

### Are image files re-downloaded?

No. Images already in your WordPress media library are reused in place. The migrator links them to the new Listora listings without creating duplicates.

## Related

- [Import & Export](features/import-export.md)
- [WP-CLI Commands](developer-guide/wp-cli-commands.md)
- [From GeoDirectory](migrate-from-geodirectory.md)
- [From Directorist](migrate-from-directorist.md)
- [From Business Directory Plugin](migrate-from-business-directory-plugin.md)
- [From ListingPro](migrate-from-listingpro.md)
