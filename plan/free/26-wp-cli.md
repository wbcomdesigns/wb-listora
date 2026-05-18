# 26 — WP-CLI Commands

## Scope: Free

---

## Commands

### `wp listora reindex`
Rebuild search index, geo index, and/or hours table.

```bash
wp listora reindex                         # Full reindex (all tables)
wp listora reindex --type=restaurant       # Only restaurant listings
wp listora reindex --table=search          # Only search_index table
wp listora reindex --table=geo             # Only geo table
wp listora reindex --table=hours           # Only hours table
wp listora reindex --batch-size=500        # Custom batch size
wp listora reindex --dry-run               # Preview, don't write
```

Output:
```
Reindexing 12,345 listings...
 [████████████████████████████████] 100% (12,345/12,345)
Done. 12,340 indexed, 5 skipped (draft), 0 errors.
```

### `wp listora stats`
Show directory statistics.

```bash
wp listora stats
```

Output:
```
Directory Statistics
──────────────────────────────
Listings:     12,345
  Published:  11,890
  Pending:    234
  Draft:      156
  Expired:    65
Reviews:      34,567
  Approved:   33,200
  Pending:    1,367
Favorites:    45,678
Claims:       89 (12 pending)

Index Health
──────────────────────────────
Search index: 11,890 / 11,890 (100% synced)
Geo index:    11,456 / 11,890 (96.3% — 434 missing coordinates)
Hours table:  8,234 / 11,890 (69.3% — not all types have hours)

Database Size
──────────────────────────────
listora_search_index:  48.2 MB
listora_geo:           8.5 MB
listora_reviews:       67.3 MB
listora_favorites:     4.1 MB
listora_hours:         12.8 MB
listora_claims:        0.3 MB
Total:                 141.2 MB
```

### `wp listora import`
Import listings from CSV/JSON.

```bash
wp listora import listings.csv --type=restaurant
wp listora import listings.csv --type=restaurant --dry-run
wp listora import locations.geojson --type=place --format=geojson
```

### `wp listora export`
Export listings.

```bash
wp listora export --type=restaurant --format=csv --output=restaurants.csv
wp listora export --format=json --output=all.json
wp listora export --status=publish --after=2026-01-01
```

### `wp listora listing-types`
List registered listing types.

```bash
wp listora listing-types
```

Output:
```
| Slug       | Name        | Fields | Listings | Schema           |
|------------|-------------|--------|----------|------------------|
| business   | Business    | 9      | 3,456    | LocalBusiness    |
| restaurant | Restaurant  | 12     | 5,678    | Restaurant       |
| real-estate| Real Estate | 11     | 2,345    | RealEstateListing|
| hotel      | Hotel       | 10     | 866      | Hotel            |
```

### `wp listora db:status`
Database schema health check.

```bash
wp listora db:status
```

### `wp listora db:repair`
Fix index inconsistencies.

```bash
wp listora db:repair              # Fix orphaned rows, missing entries
wp listora db:repair --dry-run    # Preview repairs
```

### `wp listora db:clean`
Remove orphaned data.

```bash
wp listora db:clean               # Remove rows for deleted posts
wp listora db:clean --dry-run
```

### `wp listora geocode`
Geocode listings missing coordinates.

```bash
wp listora geocode                 # Process all missing
wp listora geocode --limit=100     # Process 100 at a time
wp listora geocode --type=restaurant
```

### `wp listora demo`
Manage demo content.

```bash
wp listora demo install --type=restaurant --location="New York"
wp listora demo remove             # Remove all demo content
```
