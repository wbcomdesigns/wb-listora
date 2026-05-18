# P2-09 — Visual Import Field Mapping UI

## Scope: Pro Only

---

## Overview

A visual, interactive field mapping interface for importing listings from CSV, JSON, and GeoJSON files. Users upload a file, see a preview of the first 3 rows, then drag-and-drop (or select from dropdowns) to map source columns to Listora fields. Common column names are auto-detected. Mappings can be saved as named templates for reuse. Includes dry-run preview, batch processing with progress bar, and error reporting.

This extends the free plugin's basic CLI/API import (documented in `24-import-export.md`) with a rich admin UI.

### Why It Matters

- Most directory owners start by importing existing data (Google Sheets, old system exports, scraped data)
- Manual column-to-field mapping in code/CLI is error-prone and developer-dependent
- Auto-detection of common field names (title, address, phone) reduces setup time from minutes to seconds
- Saved mapping templates let site owners re-run imports monthly (e.g., franchise updates)
- Dry run prevents data corruption — preview before committing

---

## User Stories

| # | As a... | I want to... | So that... |
|---|---------|-------------|-----------|
| 1 | Site owner | Upload a CSV and see my data previewed | I know the file is being read correctly before importing |
| 2 | Site owner | Have common columns auto-mapped to Listora fields | I don't have to manually map "Name" to "Title" — it's obvious |
| 3 | Site owner | Save my mapping as "Franchise CSV Template" | I can re-use it for monthly imports without reconfiguring |
| 4 | Agency dev | Import from GeoJSON with auto-detected lat/lng | Location data from mapping tools works seamlessly |
| 5 | Site owner | Do a dry run and preview what will be created | I catch mapping errors before they create 500 bad listings |
| 6 | Site owner | See a progress bar during import | I know it's working and how long it will take |
| 7 | Site owner | View import results with error details | I can fix problem rows and re-import them |

---

## Technical Design

### Import Flow

```
Step 1: Upload File
  → User selects CSV, JSON, or GeoJSON
  → Server parses first 3 rows for preview

Step 2: Map Fields
  → Source columns shown with dropdown selectors
  → Auto-detection pre-fills common mappings
  → User confirms/adjusts mappings
  → Optional: save as named template

Step 3: Preview (Dry Run)
  → Show first 10 rows as they would be imported
  → Flag validation errors (missing required fields, bad coordinates)
  → User confirms

Step 4: Import
  → Batch process in groups of 50
  → Progress bar with real-time updates
  → Error log for failed rows

Step 5: Results
  → Summary: X imported, Y skipped, Z errors
  → Download error log CSV
  → Link to view imported listings
```

### Auto-Detection Rules

| Source Column Name (case-insensitive) | Listora Field |
|--------------------------------------|---------------|
| `title`, `name`, `business_name`, `business name`, `listing_name` | Title |
| `description`, `desc`, `about`, `content` | Content |
| `address`, `full_address`, `street_address`, `location` | Address |
| `phone`, `telephone`, `phone_number`, `tel` | Phone |
| `email`, `email_address`, `contact_email` | Email |
| `website`, `url`, `website_url`, `site`, `web` | Website |
| `price`, `price_range`, `cost` | Price Range |
| `lat`, `latitude`, `y` | Latitude |
| `lng`, `lon`, `longitude`, `x` | Longitude |
| `category`, `categories`, `cat`, `type` | Category |
| `image`, `photo`, `image_url`, `featured_image`, `thumbnail` | Featured Image |
| `gallery`, `images`, `photos`, `gallery_images` | Gallery |
| `city`, `town` | City (geo) |
| `state`, `province`, `region` | State (geo) |
| `country` | Country (geo) |
| `zip`, `postal_code`, `postcode` | Postal Code |
| `rating`, `avg_rating`, `stars` | Average Rating |
| `hours`, `business_hours`, `opening_hours` | Business Hours |

### Mapping Template Storage

```
Option: listora_import_templates
Value: JSON array of templates:

[
  {
    "id": "tmpl_abc123",
    "name": "Franchise CSV Template",
    "format": "csv",
    "listing_type": "restaurant",
    "mapping": {
      "Business Name": "title",
      "Description": "content",
      "Street Address": "_listora_address",
      "Phone Number": "_listora_phone",
      "Website URL": "_listora_website",
      "Cuisine Type": "listora_listing_cat",
      "Latitude": "_listora_lat",
      "Longitude": "_listora_lng"
    },
    "options": {
      "skip_first_row": true,
      "update_existing": false,
      "status": "pending"
    },
    "created_at": "2026-03-15T10:00:00Z",
    "last_used": "2026-04-01T14:00:00Z"
  }
]
```

### Batch Processing

```php
class Visual_Importer {
    const BATCH_SIZE = 50;

    public function import( string $file_path, array $mapping, array $options ): array {
        $parser  = $this->get_parser($file_path); // CSV, JSON, or GeoJSON
        $total   = $parser->count();
        $results = ['imported' => 0, 'skipped' => 0, 'errors' => []];

        // Process in batches via WP cron
        $batch_id = wp_generate_uuid4();
        update_option("listora_import_{$batch_id}", [
            'file'     => $file_path,
            'mapping'  => $mapping,
            'options'  => $options,
            'total'    => $total,
            'progress' => 0,
            'status'   => 'running',
            'results'  => $results,
        ]);

        // Schedule first batch
        wp_schedule_single_event(
            time(),
            'listora_import_batch',
            [$batch_id, 0]
        );

        return ['batch_id' => $batch_id, 'total' => $total];
    }

    public function process_batch( string $batch_id, int $offset ): void {
        $import = get_option("listora_import_{$batch_id}");
        $parser = $this->get_parser($import['file']);
        $rows   = $parser->get_rows($offset, self::BATCH_SIZE);

        foreach ($rows as $i => $row) {
            try {
                $this->import_row($row, $import['mapping'], $import['options']);
                $import['results']['imported']++;
            } catch (\Exception $e) {
                $import['results']['errors'][] = [
                    'row'     => $offset + $i + 1,
                    'message' => $e->getMessage(),
                    'data'    => $row,
                ];
            }
            $import['progress'] = $offset + $i + 1;
        }

        // Schedule next batch or mark complete
        $next_offset = $offset + self::BATCH_SIZE;
        if ($next_offset < $import['total']) {
            $import['status'] = 'running';
            wp_schedule_single_event(time(), 'listora_import_batch', [$batch_id, $next_offset]);
        } else {
            $import['status'] = 'complete';
        }

        update_option("listora_import_{$batch_id}", $import);
    }
}
```

### GeoJSON Support

```json
// GeoJSON input
{
  "type": "FeatureCollection",
  "features": [
    {
      "type": "Feature",
      "geometry": {
        "type": "Point",
        "coordinates": [-74.006, 40.7128]
      },
      "properties": {
        "name": "Pizza Palace",
        "address": "123 Main St",
        "phone": "+1-555-0123"
      }
    }
  ]
}
```

Auto-detected mapping:
- `geometry.coordinates[0]` -> Longitude
- `geometry.coordinates[1]` -> Latitude
- `properties.*` -> Available as source columns

### Files to Create (wb-listora-pro)

| File | Purpose |
|------|---------|
| `includes/import-export/class-visual-importer.php` | Batch import engine |
| `includes/import-export/class-field-auto-detector.php` | Auto-detection rules |
| `includes/import-export/class-import-template-manager.php` | Template CRUD |
| `includes/import-export/class-import-preview.php` | Dry run + preview logic |
| `includes/rest/class-visual-import-controller.php` | REST endpoints for import UI |
| `includes/admin/class-visual-import-page.php` | Admin import wizard page |
| `assets/js/admin/visual-import.js` | Frontend wizard UI (vanilla JS) |
| `assets/css/admin/visual-import.css` | Wizard styles |

### Files to Modify (wb-listora free)

| File | Change |
|------|--------|
| `includes/import-export/class-csv-importer.php` | Extract reusable row import logic into shared method |
| `includes/import-export/class-json-importer.php` | Same |
| `includes/import-export/class-geojson-importer.php` | Same |

### API Endpoints

| Method | Endpoint | Auth | Purpose |
|--------|----------|------|---------|
| `POST` | `/listora/v1/import/upload` | Admin | Upload file, return preview + detected fields |
| `POST` | `/listora/v1/import/preview` | Admin | Dry run with mapping, return preview rows |
| `POST` | `/listora/v1/import/start` | Admin | Start batch import |
| `GET` | `/listora/v1/import/status/{batch_id}` | Admin | Check progress |
| `GET` | `/listora/v1/import/templates` | Admin | List saved templates |
| `POST` | `/listora/v1/import/templates` | Admin | Save template |
| `PUT` | `/listora/v1/import/templates/{id}` | Admin | Update template |
| `DELETE` | `/listora/v1/import/templates/{id}` | Admin | Delete template |

---

## UI Mockup

### Step 1: Upload

```
┌─────────────────────────────────────────────────────────────┐
│ Import Listings                                     Step 1/4│
│                                                             │
│ Upload your data file:                                      │
│                                                             │
│ ┌───────────────────────────────────────────────────────┐   │
│ │                                                       │   │
│ │        Drag & drop your file here                     │   │
│ │        or [Choose File]                               │   │
│ │                                                       │   │
│ │        Supports: CSV, JSON, GeoJSON                   │   │
│ └───────────────────────────────────────────────────────┘   │
│                                                             │
│ Listing Type: [ Restaurant ▾ ]                              │
│                                                             │
│ Options:                                                    │
│ ☑ First row contains column headers                       │
│ ☐ Update existing listings (match by title + address)      │
│ Default status: [ Pending Review ▾ ]                        │
│                                                             │
│ ── Saved Templates ──────────────────────────────────────── │
│ Load template: [ Franchise CSV Template ▾ ] [Load]          │
│                                                             │
│                                          [Upload & Map →]   │
└─────────────────────────────────────────────────────────────┘
```

### Step 2: Map Fields

```
┌─────────────────────────────────────────────────────────────┐
│ Map Fields                                          Step 2/4│
│                                                             │
│ We detected 12 columns. 8 were auto-mapped (✓)             │
│                                                             │
│ Source Column          →  Listora Field          Status     │
│ ────────────────────────────────────────────────────────    │
│ ✓ "Business Name"     →  [ Title            ▾ ]  Auto     │
│ ✓ "Description"       →  [ Content          ▾ ]  Auto     │
│ ✓ "Street Address"    →  [ Address          ▾ ]  Auto     │
│ ✓ "Phone Number"      →  [ Phone            ▾ ]  Auto     │
│ ✓ "Website URL"       →  [ Website          ▾ ]  Auto     │
│ ✓ "Latitude"          →  [ Latitude         ▾ ]  Auto     │
│ ✓ "Longitude"         →  [ Longitude        ▾ ]  Auto     │
│ ✓ "Cuisine"           →  [ Category         ▾ ]  Auto     │
│   "Price Level"       →  [ Price Range      ▾ ]  Manual   │
│   "Year Established"  →  [ — Skip —         ▾ ]  Skipped  │
│   "Owner Name"        →  [ — Skip —         ▾ ]  Skipped  │
│   "Image Link"        →  [ Featured Image   ▾ ]  Manual   │
│                                                             │
│ ── Data Preview (first 3 rows) ──────────────────────────── │
│                                                             │
│ ┌──────────────────────────────────────────────────────┐    │
│ │ #  │ Title          │ Address         │ Phone       │    │
│ ├────┼────────────────┼─────────────────┼─────────────┤    │
│ │ 1  │ Pizza Palace   │ 123 Main St     │ 555-0123    │    │
│ │ 2  │ Sushi House    │ 456 Oak Ave     │ 555-0456    │    │
│ │ 3  │ Taco Shop      │ 789 Elm Blvd    │ 555-0789    │    │
│ └──────────────────────────────────────────────────────┘    │
│                                                             │
│ ☐ Save this mapping as template                            │
│   Template name: [ Franchise CSV Template ]                 │
│                                                             │
│ [← Back]                                   [Preview →]      │
└─────────────────────────────────────────────────────────────┘
```

### Step 3: Dry Run Preview

```
┌─────────────────────────────────────────────────────────────┐
│ Import Preview                                      Step 3/4│
│                                                             │
│ 245 rows detected. Showing first 10:                        │
│                                                             │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ ✓ Row 1: Pizza Palace                                  │ │
│ │   Type: Restaurant · 123 Main St · 40.71, -74.00       │ │
│ │   Category: Italian                                     │ │
│ ├─────────────────────────────────────────────────────────┤ │
│ │ ✓ Row 2: Sushi House                                   │ │
│ │   Type: Restaurant · 456 Oak Ave · 40.72, -73.99       │ │
│ │   Category: Japanese                                    │ │
│ ├─────────────────────────────────────────────────────────┤ │
│ │ ⚠ Row 3: Taco Shop                                    │ │
│ │   Warning: No coordinates found, will geocode address   │ │
│ ├─────────────────────────────────────────────────────────┤ │
│ │ ✗ Row 4: (empty)                                       │ │
│ │   Error: Missing required field "Title"                 │ │
│ └─────────────────────────────────────────────────────────┘ │
│                                                             │
│ Summary:                                                    │
│ ✓ 240 valid rows ready to import                           │
│ ⚠ 3 rows with warnings (will attempt import)              │
│ ✗ 2 rows with errors (will be skipped)                     │
│                                                             │
│ [← Back to Mapping]                    [Start Import →]     │
└─────────────────────────────────────────────────────────────┘
```

### Step 4: Import Progress

```
┌─────────────────────────────────────────────────────────────┐
│ Importing...                                        Step 4/4│
│                                                             │
│ ████████████████████████░░░░░░░░  156 / 245                │
│                                                             │
│ ✓ 150 imported successfully                                │
│ ⚠ 4 skipped (duplicate detection)                          │
│ ✗ 2 failed (see error log)                                 │
│                                                             │
│ Estimated time remaining: ~45 seconds                       │
│                                                             │
│ ── Live Feed ──────────────────────────────────────────────  │
│ ✓ Imported: Pizza Palace                                   │
│ ✓ Imported: Sushi House                                    │
│ ⚠ Skipped: Taco Shop (duplicate: existing listing #89)     │
│ ✓ Imported: Burger Joint                                   │
│                                                             │
│ [Cancel Import]                                             │
└─────────────────────────────────────────────────────────────┘
```

### Step 4b: Import Complete

```
┌─────────────────────────────────────────────────────────────┐
│ Import Complete!                                            │
│                                                             │
│ ████████████████████████████████  245 / 245                │
│                                                             │
│ ✓ 238 imported successfully                                │
│ ⚠ 5 skipped (duplicates)                                   │
│ ✗ 2 failed                                                 │
│                                                             │
│ [View Imported Listings]  [Download Error Report (CSV)]     │
│ [Import Another File]                                       │
└─────────────────────────────────────────────────────────────┘
```

### Template Manager

```
┌─────────────────────────────────────────────────────────────┐
│ Saved Import Templates                                      │
│                                                             │
│ | Name                   | Format | Type       | Last Used  │
│ |------------------------|--------|------------|------------│
│ | Franchise CSV Template | CSV    | Restaurant | Apr 1      │
│ | Google Places Export   | JSON   | Business   | Mar 15     │
│ | City GeoJSON Data      | GeoJSON| All Types  | Mar 10     │
│                                                             │
│ [Edit] [Delete] per row                                     │
└─────────────────────────────────────────────────────────────┘
```

---

## Implementation Steps

| # | Task | Est. Hours |
|---|------|-----------|
| 1 | File upload handler with validation (CSV, JSON, GeoJSON) | 2 |
| 2 | File parser — extract columns + first 3 rows for preview | 3 |
| 3 | Auto-detection engine (column name matching rules) | 3 |
| 4 | Mapping UI — dropdowns, preview table, auto-detect indicators | 5 |
| 5 | Template save/load/delete system | 2 |
| 6 | Dry run engine — validate all rows, report issues | 3 |
| 7 | Dry run preview UI — row-by-row with status icons | 2 |
| 8 | Batch import engine (cron-based, 50 rows/batch) | 4 |
| 9 | Progress tracking (REST polling endpoint) | 2 |
| 10 | Progress bar UI with live feed | 3 |
| 11 | Error reporting — error log, CSV download | 2 |
| 12 | Import complete summary page | 1 |
| 13 | GeoJSON geometry extraction (coordinates → lat/lng) | 1 |
| 14 | Image URL import (download external images to media library) | 3 |
| 15 | Duplicate detection during import | 2 |
| 16 | REST endpoints for all steps | 4 |
| 17 | Admin page wrapper + step navigation | 2 |
| 18 | Automated tests + documentation | 3 |
| **Total** | | **47 hours** |

---

## Competitive Context

| Competitor | Visual Import? | Our Advantage |
|-----------|---------------|---------------|
| GeoDirectory | Basic CSV import (no visual mapper) | Full visual wizard with auto-detection |
| Directorist | CSV import addon ($39) | Included in Pro, saved templates, dry run |
| HivePress | No import UI | Complete 4-step wizard with preview |
| ListingPro | Basic CSV import | Auto-detection, GeoJSON support, batch processing |
| MyListing | CSV import (basic) | Saved mapping templates, progress bar |
| WP All Import | Yes (general purpose, $99) | Listora-specific field awareness, free with Pro |

**Our edge:** Listora-specific field awareness means auto-detection knows about business hours, price ranges, listing types, and gallery images — general-purpose importers treat these as generic text fields. The saved template system makes recurring imports (franchise updates, monthly data refreshes) a one-click operation. GeoJSON support is rare among directory plugins and essential for mapping/GIS data sources.

---

## Effort Estimate

**Total: ~47 hours (6 dev days)**

- File parsing + auto-detection: 8h
- Mapping UI: 5h
- Templates: 2h
- Dry run + preview: 5h
- Batch import: 6h
- Progress tracking + UI: 5h
- Error reporting: 3h
- REST API: 4h
- GeoJSON + images: 4h
- Admin wrapper: 2h
- Tests + docs: 3h
