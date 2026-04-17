# 24 — Import / Export

## Scope

| | Free | Pro |
|---|---|---|
| CSV import with field mapping | Yes | Yes |
| CSV export | Yes | Yes |
| JSON/GeoJSON import | Yes | Yes |
| Demo content import | Yes | Yes |
| WP-CLI import/export | Yes | Yes |
| Visual field mapping UI | — | Yes |
| Competitor migration tools | — | Yes |
| Scheduled imports | — | Yes |

---

## CSV Import

### Flow

**Step 1: Upload CSV**
```
┌─────────────────────────────────────────────────────┐
│ Import Listings                                     │
│                                                     │
│ Upload a CSV file with your listings.               │
│                                                     │
│ [📁 Choose CSV File]                               │
│                                                     │
│ Listing Type: [ Restaurant ▾ ]                      │
│                                                     │
│ Options:                                            │
│ ☑ First row contains column headers               │
│ ☐ Update existing listings (match by title)        │
│ ☐ Dry run (preview only, don't import)             │
│                                                     │
│                              [Upload & Continue →]  │
└─────────────────────────────────────────────────────┘
```

**Step 2: Map Columns**
```
┌─────────────────────────────────────────────────────┐
│ Map CSV Columns to Listing Fields                   │
│                                                     │
│ CSV Column          →  Listing Field                │
│ ─────────────────────────────────────               │
│ "Business Name"     →  [ Title ▾ ]                  │
│ "Description"       →  [ Content ▾ ]                │
│ "Street Address"    →  [ Address ▾ ]                │
│ "Phone Number"      →  [ Phone ▾ ]                  │
│ "Type of Cuisine"   →  [ Cuisine ▾ ]               │
│ "Price"             →  [ Price Range ▾ ]            │
│ "Latitude"          →  [ Latitude ▾ ]               │
│ "Longitude"         →  [ Longitude ▾ ]              │
│ "Website URL"       →  [ Website ▾ ]                │
│ "Category"          →  [ Category ▾ ]               │
│ "Image URL"         →  [ Featured Image ▾ ]         │
│ "Unknown Column"    →  [ — Skip — ▾ ]              │
│                                                     │
│ Preview (first 3 rows):                             │
│ ┌───────────────────────────────────────────────┐   │
│ │ Pizza Palace | Italian | $$$ | 40.71 | -74.00│   │
│ │ Sushi House  | Japanese| $$  | 40.72 | -73.99│   │
│ │ Taco Shop    | Mexican | $   | 40.70 | -74.01│   │
│ └───────────────────────────────────────────────┘   │
│                                                     │
│ [← Back]                            [Start Import →]│
└─────────────────────────────────────────────────────┘
```

**Step 3: Import Progress**
```
┌─────────────────────────────────────────────────────┐
│ Importing...                                        │
│                                                     │
│ ████████████████████████░░░░░░░░  156 / 245        │
│                                                     │
│ ✓ 150 imported successfully                        │
│ ⚠ 4 imported with warnings                         │
│ ✗ 2 failed (invalid data)                          │
│                                                     │
│ Geocoding addresses: 89 / 156 complete             │
│                                                     │
│ Errors:                                             │
│ Row 45: Invalid email "not-an-email"               │
│ Row 89: Missing required field "title"             │
│                                                     │
│                                   [Cancel Import]   │
└─────────────────────────────────────────────────────┘
```

**Step 4: Results**
```
┌─────────────────────────────────────────────────────┐
│ Import Complete                                     │
│                                                     │
│ ✓ 239 listings imported                            │
│ ⚠ 4 with warnings (missing optional fields)        │
│ ✗ 2 failed                                         │
│                                                     │
│ Geocoding: 45 addresses queued for background       │
│ processing (rate limited to 1/sec).                 │
│                                                     │
│ [Download Error Report]  [View Imported Listings]   │
└─────────────────────────────────────────────────────┘
```

### Batch Processing
- CSV parsed in chunks of 100 rows
- AJAX-based progress (admin stays responsive)
- Geocoding queued as background tasks (Nominatim rate limit: 1/sec)
- Search index updated in batch after import completes

---

## CSV Export

```
┌─────────────────────────────────────────────────────┐
│ Export Listings                                      │
│                                                     │
│ Listing Type: [ All Types ▾ ]                       │
│ Status:       [ Published ▾ ]                       │
│ Category:     [ All ▾ ]                             │
│ Date Range:   [ From ] to [ To ]                    │
│                                                     │
│ Fields to export:                                   │
│ ☑ Title  ☑ Description  ☑ All meta fields         │
│ ☑ Categories  ☑ Location  ☑ Rating                │
│ ☐ Author info  ☐ Internal IDs                      │
│                                                     │
│ Format: (•) CSV  ( ) JSON                           │
│                                                     │
│                              [Export & Download]    │
└─────────────────────────────────────────────────────┘
```

---

## WP-CLI Import/Export

```bash
# Import CSV
wp listora import listings.csv --type=restaurant --dry-run
wp listora import listings.csv --type=restaurant --batch-size=100

# Import JSON/GeoJSON
wp listora import locations.geojson --type=place --format=geojson

# Export
wp listora export --type=restaurant --format=csv --output=restaurants.csv
wp listora export --format=json --status=publish --output=all-listings.json

# Import from competitor (Pro)
wp listora migrate --from=geodirectory
wp listora migrate --from=directorist --dry-run
```

---

## Pro: Competitor Migration Tools

| Source | Mapping |
|--------|---------|
| GeoDirectory | CPT `gd_place` → `listora_listing`, custom table → meta |
| Directorist | CPT `at_biz_dir` → `listora_listing`, meta → meta |
| HivePress | CPT `hp_listing` → `listora_listing`, meta → meta |
| Business Directory Plugin | CPT `wpbdp_listing` → `listora_listing` |

Each migration tool:
1. Reads source data (don't modify source)
2. Maps fields to Listora fields
3. Imports with progress
4. Preserves: images, categories, reviews (where possible), authors
5. Reports unmapped fields
