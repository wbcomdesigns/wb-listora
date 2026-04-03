## Search Settings

Access search settings at **Listora > Settings > Search**.

### Results Per Page

Number of listings per page in search results. Default: 20.

### Default Sort Order

The initial sort when no search keyword is entered:

- **Featured** — Featured listings first, then by date
- **Newest** — Most recently published
- **Rating** — Highest rated first
- **Alphabetical** — A to Z

### Search Radius

Default radius for "Near Me" searches. Applied when users click the location button without specifying a distance.

### Autocomplete

Enable real-time search suggestions as users type. Suggestions include matching listings, categories, and locations.

### Indexing

WB Listora maintains a denormalized search index for performance. The index is updated automatically when listings are created, edited, or deleted.

To rebuild the search index manually:

```bash
wp listora reindex
```

This is useful after bulk imports or database changes.
