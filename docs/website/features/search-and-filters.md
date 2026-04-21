# Search and Filters

## What it does

WB Listora's search system lets visitors find listings by keyword, type, category, location, and distance. It updates results reactively without reloading the page and supports geo-radius queries so users can find listings near them.

## Why you'd use it

- Visitors find what they need without scrolling through hundreds of listings.
- Location-based search with "Near Me" geolocation reduces friction on mobile.
- Faceted filters (category, feature, price, rating) let users narrow results in any combination.
- Results stay fast at scale — WB Listora uses a dedicated search index table, not generic WordPress queries.

## How to use it

### For site owners (admin steps)

1. Go to **Listora → Settings → Search** to configure defaults:
   - **Results per page** — how many listings appear per page (default: 12).
   - **Default sort** — the initial sort order when no keyword is entered.
   - **Distance unit** — Kilometers or Miles.
   - **Default radius** — the radius used when a user searches by location without adjusting the slider.
2. Add the **Listing Search** block to your directory page. See [Creating Your Directory Page](../getting-started/creating-directory-page.md).
3. Optionally set the **Listing Search** block to **Stacked** layout for a taller, full-width appearance suited to homepage hero sections.

### For end users (visitor/user-facing)

**Keyword search:** Type any word into the search bar. Autocomplete shows matching listing names. Results search titles, descriptions, custom field values, and service names.

**Location search:** Type an address into the location field, or click **Near Me** to use your device's GPS. Results are filtered by distance from that point.

**Type tabs:** Click a type tab (All, Restaurant, Hotel, etc.) to show only that type.

**Advanced filters:** Click the filters button to expand the panel. Available filters depend on your listing types:

| Filter | Description |
|--------|-------------|
| Category | Filter by listing category (e.g., Italian, French) |
| Features | Filter by amenity checkboxes (WiFi, Parking, etc.) |
| Price range | Minimum and maximum price slider |
| Rating | Minimum star rating |
| Geo-radius | Distance slider (requires a location to be entered) |
| Date range | Start and end date (for Event listing types only) |

**Active filter pills:** Applied filters appear as removable pills below the search bar. Click the × on any pill to remove that filter.

**Sort options:** Use the sort dropdown in the grid toolbar to change order:

- Relevance (default for keyword searches)
- Newest / Oldest
- Rating (highest first)
- Distance (requires a location)
- Featured (featured listings first)
- Alphabetical (A–Z)

## Tips

- Add the **Listing Map** block alongside the grid to let users see results geographically while filtering — the map updates with the same filters.
- The geo-radius filter only activates once the user enters a location or clicks Near Me. Without a location, the radius slider is hidden.
- For event directories, the date filter is specific to the **Event** listing type. Configure date fields in **Listora → Listing Types → Event**.
- Saved Searches (Pro) let logged-in users save any filter combination and receive email alerts when new matching listings are published. See [Saved Searches](saved-searches.md).
- Re-indexing: if you bulk-import listings and search results don't reflect them, go to **Listora → Settings → Search** and click **Rebuild Search Index**.

## Common issues

| Symptom | Fix |
|---------|-----|
| Keyword search returns no results | Check that the search index was built — activate, then deactivate and reactivate the plugin to trigger a rebuild |
| "Near Me" button does nothing | The user's browser must allow location access; HTTPS is required |
| Distance filter not appearing | A location must be entered in the location field first |
| Date filter missing | Confirm the listing type has date fields configured in **Listora → Listing Types** |

## Related features

- [Blocks Overview](blocks-overview.md)
- [Listing Types](../getting-started/listing-types.md)
- [Creating Your Directory Page](../getting-started/creating-directory-page.md)
