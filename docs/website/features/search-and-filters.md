## Search & Filters

WB Listora includes a powerful search system with full-text search, faceted filtering, and geographic queries.

### Search Features

- **Full-text search:** Searches titles, descriptions, and custom field values
- **Autocomplete:** Real-time suggestions as you type
- **Location search:** Find listings near an address or use "Near Me"
- **Type filtering:** Filter by listing type with quick tabs
- **Category filtering:** Drill down by category
- **Feature filtering:** Filter by amenities and features
- **Price range:** Filter by price or price range
- **Rating filter:** Minimum star rating
- **Radius search:** Find listings within X km/miles

### How Search Works

WB Listora uses a denormalized search index table for fast queries at scale. When a listing is published or updated, the search indexer:

1. Extracts all searchable text (title, description, field values)
2. Stores it in the `listora_search_index` table
3. Links to the `listora_geo` table for location queries
4. Links to the `listora_field_index` table for field-specific filters

This approach handles 100K+ listings without performance degradation.

### Sort Options

- **Relevance** (default for keyword searches)
- **Newest** / Oldest
- **Rating** (highest first)
- **Distance** (requires location)
- **Featured** (featured listings first)
- **Alphabetical**

### Saved Searches (Pro)

With WB Listora Pro, users can save their search criteria and receive email alerts when new matching listings are published.

### Settings

Under **Listora > Settings > Search:**

- **Results per page:** Number of listings per page
- **Default sort:** Initial sort order
- **Distance unit:** Kilometers or miles
- **Search radius:** Default radius for location searches
