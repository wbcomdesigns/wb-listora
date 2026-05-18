# 33 — Competitor Migration Tools (Pro)

## Scope: Pro Only

---

## Overview

Switching directory plugins is one of the biggest pain points. Migration tools that import from GeoDirectory, Directorist, HivePress, and Business Directory Plugin will be a major adoption driver.

---

## Supported Sources

| Source Plugin | CPT | Complexity |
|--------------|-----|------------|
| GeoDirectory | `gd_place` + custom tables | High (custom table → meta mapping) |
| Directorist | `at_biz_dir` | Medium (standard meta) |
| HivePress | `hp_listing` | Medium (standard meta) |
| Business Directory Plugin | `wpbdp_listing` | Medium (standard meta) |

---

## Migration Flow

### Admin UI
```
Listora → Import/Export → Migrate from Another Plugin

┌─────────────────────────────────────────────────────┐
│ Migrate to WB Listora                               │
│                                                     │
│ Select source plugin:                               │
│ ┌──────────────┐ ┌──────────────┐                  │
│ │ GeoDirectory │ │ Directorist  │                  │
│ └──────────────┘ └──────────────┘                  │
│ ┌──────────────┐ ┌──────────────┐                  │
│ │ HivePress    │ │ Business Dir │                  │
│ └──────────────┘ └──────────────┘                  │
│                                                     │
│ ⚠ Source plugin must be active during migration.    │
│ Migration reads data — it never modifies the source.│
│                                                     │
│                              [Start Migration →]    │
└─────────────────────────────────────────────────────┘
```

### Step 2: Field Mapping
```
┌─────────────────────────────────────────────────────┐
│ Map Fields                                          │
│                                                     │
│ We detected 245 listings with these fields:         │
│                                                     │
│ Source Field       →  Listora Field                 │
│ ──────────────────────────────────                  │
│ "post_title"      →  [ Title ▾ ]                    │
│ "post_content"    →  [ Content ▾ ]                  │
│ "geodir_phone"    →  [ Phone ▾ ]                    │
│ "geodir_website"  →  [ Website ▾ ]                  │
│ "geodir_email"    →  [ Email ▾ ]                    │
│ "geodir_timing"   →  [ Business Hours ▾ ]           │
│ "default_category"→  [ Category ▾ ]                 │
│                                                     │
│ Listing Type: [ Restaurant ▾ ]                      │
│ ☑ Import categories                                │
│ ☑ Import images/media                               │
│ ☑ Import reviews (if available)                     │
│ ☐ Dry run (preview only)                            │
│                                                     │
│                              [Run Migration →]      │
└─────────────────────────────────────────────────────┘
```

### Step 3: Progress
Same progress UI as CSV import — batch processing with error reporting.

---

## What Gets Migrated

| Data | Migrated |
|------|----------|
| Listing posts | Yes (title, content, excerpt, status, date, author) |
| Featured images | Yes (re-attached to new CPT) |
| Gallery images | Yes (if source stores attachment IDs) |
| Custom field values | Yes (mapped to Listora meta keys) |
| Categories | Yes (created as `listora_listing_cat` terms) |
| Tags | Yes |
| Locations | Yes (if source has location data) |
| Reviews/ratings | Yes (converted to `listora_reviews` table) |
| Authors/users | Preserved (same WordPress users) |
| Geo data | Yes (lat/lng → `listora_geo` table) |
| Business hours | Best effort (format varies between plugins) |
| Payment/subscription data | No (too complex, varies by gateway) |

---

## WP-CLI

```bash
wp listora migrate --from=geodirectory --dry-run
wp listora migrate --from=geodirectory --type=restaurant
wp listora migrate --from=directorist --batch-size=100
```

---

## Safety

- Source plugin data is NEVER modified
- Migration can be run multiple times (skips already-migrated via `_listora_migrated_from` meta)
- Dry-run mode shows what would be imported without writing data
- Error report downloadable as CSV
- Admin can undo migration: bulk-delete listings with `_listora_migrated_from` meta
