# How to Migrate from Business Directory Plugin to WB Listora

WB Listora includes a built-in migration tool for Business Directory Plugin (BDP). It reads directly from BDP's custom database tables and post data, transferring your listings, categories, tags, and custom field values into Listora's system automatically.

This guide covers the complete migration process, what data transfers over, and the features you gain by switching.

## Why Switch from Business Directory Plugin to WB Listora?

Business Directory Plugin has been around for years and handles basic directory functionality well. However, it was designed in an era before the block editor, the Interactivity API, and modern WordPress development patterns. Several features that directory sites need -- reviews, claims, events, frontend dashboards -- are either missing or require paid add-ons.

WB Listora provides a more complete package in the free version. It is built on Gutenberg blocks and the Interactivity API, supports 10 listing types out of the box, and includes features like reviews, claims, business hours, and an event calendar at no additional cost.

### Feature Comparison: Business Directory Plugin vs WB Listora

| Feature | BDP (Free) | BDP (Paid Modules) | WB Listora (Free) |
|---------|-----------|--------------------|--------------------|
| Listing types | 1 | Not available | 10 pre-built types |
| Custom fields | Via form fields table | Included | Full field system per listing type |
| Reviews & ratings | Not included | Paid module (~$49) | Star ratings, helpful votes, owner replies, moderation |
| Claim listings | Not included | Paid module (~$49) | Included free |
| Frontend submission | Basic form | Enhanced via paid module | Multi-step wizard with type selection and validation |
| User dashboard | Not included | Not available | Full frontend dashboard block |
| Events & calendar | Not included | Not available | Calendar block included |
| Business hours | Not included | Not available | Weekly schedule with "open now" filter |
| Map integration | Google Maps (API key required) | Included | OpenStreetMap (free, no API key) with clustering |
| Search & filters | Basic | Enhanced via paid module | Two-phase FULLTEXT search with faceted counts |
| CSV import/export | Basic | Advanced via paid module | Full import/export with column mapping |
| JSON import | Not available | Not available | JSON import included |
| GeoJSON import | Not available | Not available | GeoJSON import included |
| Block editor support | Limited | Limited | 11 native Gutenberg blocks |
| Interactivity API | No | No | Yes, zero jQuery dependency |
| Schema.org | Basic | Advanced via paid module | Automatic JSON-LD included |
| REST API | Limited | Limited | 41 endpoints |
| WP-CLI | Limited | Limited | Full CLI: stats, reindex, import, export, demo |
| Favorites | Not included | Not available | Included with collections |

### Import Format Flexibility

One area where WB Listora stands apart is import flexibility. In addition to CSV import/export, Listora supports JSON and GeoJSON import. If you have location data from external sources -- government open data portals, mapping services, or third-party APIs -- you can import it directly into Listora without converting to CSV first. GeoJSON FeatureCollection files automatically map coordinates to listings with proper geo indexing.

## Before You Begin

1. **Back up your database.** Always create a full database backup before any migration. Use `wp db export` or your preferred backup plugin.
2. **Keep BDP data in place.** The migrator reads from BDP's `wpbdp_listing` post type and its `wpbdp_form_fields` table. BDP can be active or deactivated -- the migrator detects its data tables regardless of plugin status.
3. **Install and activate WB Listora.** Run through the setup wizard to configure your listing types and create required pages.

## Step-by-Step Migration Guide

### Step 1: Install WB Listora

Install WB Listora from WordPress.org and activate it. Complete the setup wizard to configure listing types, location settings, and generate the directory, submission, and dashboard pages.

### Step 2: Open the Migration Tool

Navigate to **Listora > Tools > Migration**. The plugin scans for BDP data by checking for the `wpbdp_listing` post type and the `wpbdp_form_fields` database table. If either is found, you will see a "Business Directory Plugin" source card with the available listing count.

### Step 3: Review What Will Be Migrated

Click the BDP source to see the migration summary:

- **Listings** -- All `wpbdp_listing` posts (published, pending, draft).
- **Categories** -- `wpbdp_category` terms mapped to `listora_listing_cat`.
- **Tags** -- `wpbdp_tag` terms preserved.
- **Custom field values** -- BDP stores field data in post meta with keys like `_wpbdp[fields][{id}]`. The migrator reads the field definitions from `wpbdp_form_fields` and maps values to Listora's field system.
- **Images** -- Featured images and any attached media.

### Step 4: Run the Migration

Click **Start Migration**. Processing runs in batches of 50 listings. The progress bar tracks completion. Already-migrated listings are detected and skipped, making it safe to re-run.

For sites with hundreds or thousands of listings, use the CLI:

```bash
wp listora migrate --source=bdp --batch-size=100
```

### Step 5: Verify and Reindex

After migration:

- Check **Listora > Listings** for your migrated entries.
- Open individual listings to confirm custom field values, categories, and images.
- Rebuild the search index at **Listora > Tools > Reindex** or via CLI: `wp listora reindex --all`.
- Test the frontend directory page to verify listings appear in search results and on the map.

### Step 6: Replace BDP Shortcodes

BDP uses shortcodes for its frontend pages. Replace them with Listora blocks:

| BDP Shortcode | WB Listora Block |
|--------------|------------------|
| `[businessdirectory]` | Listing Grid block |
| `[businessdirectory-submitlisting]` | Listing Submission block |
| `[businessdirectory-managelisting]` | User Dashboard block |

Open each page in the block editor, remove the shortcode block, and add the corresponding Listora block. Each block is configurable through the sidebar panel.

## What Gets Migrated

| Data Type | Source (BDP) | Destination (WB Listora) |
|-----------|-------------|--------------------------|
| Listings | `wpbdp_listing` CPT | `listora_listing` CPT |
| Categories | `wpbdp_category` taxonomy | `listora_listing_cat` taxonomy |
| Tags | `wpbdp_tag` taxonomy | Post tags |
| Custom fields | `wpbdp_form_fields` table + post meta | Listora field index + meta |
| Images | Post thumbnail + attachments | Post thumbnail + attachments |
| Geo data | Address/coordinates meta (if present) | `listora_geo` table |

## What Does Not Get Migrated

- **Payment and fee data** -- BDP's payment transactions, listing fees, and plan assignments are not migrated. WB Listora Pro handles monetization through its own pricing plan system.
- **BDP modules data** -- Data from paid BDP modules (ratings, claim, discount codes) uses BDP-specific storage formats that do not have direct equivalents in the free migration.
- **Shortcode layouts** -- BDP shortcodes must be replaced with Listora blocks (see the table above).

## Frequently Asked Questions

### Can I migrate with Business Directory Plugin deactivated?

Yes. The migrator checks for the `wpbdp_form_fields` table and `wpbdp_listing` posts directly in the database. BDP does not need to be active.

### How are BDP custom fields handled?

BDP stores field definitions in the `wpbdp_form_fields` table and values in post meta. The migrator reads both, extracts the field values, and maps them to Listora's meta system. Standard fields (address, phone, email, website) are mapped to their Listora equivalents. Other fields are stored as custom meta that you can register in Listora's field system for display.

### Does BDP use the same map provider?

BDP defaults to Google Maps, which requires an API key. WB Listora uses OpenStreetMap by default, which is free and requires no API key. The Pro version adds Google Maps as an option if you prefer it.

### What about my existing URLs and SEO?

BDP uses the `wpbdp_listing` post type slug in its URLs. Listora uses `listora_listing`. Set up 301 redirects from your old BDP URLs to the new Listora URLs. Listora's automatic Schema.org JSON-LD output helps maintain structured data in search results.

### Can I import additional data from external sources after migration?

Yes. Listora supports CSV, JSON, and GeoJSON imports. If you have listing data from government databases, open data portals, or API exports, you can import them directly into Listora using the appropriate format. GeoJSON is particularly useful for location data with coordinates.

## Ready to Switch?

Install WB Listora free from WordPress.org and migrate your Business Directory Plugin data in minutes. You gain 10 listing types, reviews, claims, events, a user dashboard, GeoJSON import, and 11 Gutenberg blocks -- all included in the free version.

[Install WB Listora from WordPress.org](https://wordpress.org/plugins/wb-listora/)
