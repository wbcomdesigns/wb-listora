# Tags

> **Availability:** Free + Pro. Tags are **Free**.

Tags are the flat, free-form dimension alongside the hierarchical [Categories](listing-categories.md) and [Locations](locations.md). Since 1.6.0 they are a real discovery route rather than metadata: they filter search, appear as a facet, and render as clickable chips.

## What it is

Categories answer "what kind of thing is this" and are a tree a site owner curates. Tags answer "what else is true about it" and are a flat list that can grow as listings are added. A restaurant sits in one category and may carry the tags `outdoor-seating`, `dog-friendly` and `late-night`.

Before 1.6.0, tags could be assigned but did almost nothing. Now:

- **Search filters by tag.** A tag can narrow a result set like any other facet.
- **Tags are returned as a facet**, with counts, so a search surface can offer the tags present in the current results rather than a fixed list.
- **Tags render as chips** on cards and listing pages, each linking to the directory filtered to that tag.

## How you use it

### As a site owner - decide who creates tags

Tags behave like WordPress tags. Members submitting a listing can be allowed to type new ones, or restricted to choosing from what exists - see [Frontend Submission](frontend-submission.md) for the submission field settings. Manage the full list under **Listora > Tags**.

A word of planning: tags are only useful when they are shared. Twenty listings each carrying a unique tag produce twenty facets of one result each, which helps nobody. If you want a controlled vocabulary, restrict creation and seed the list yourself.

### As a visitor

Click any tag chip on a card or listing page to see every listing carrying it. In search, tags appear as a facet you can narrow by, alongside categories, locations and features.

### As a developer

The taxonomy is `listora_listing_tag`, non-hierarchical, registered against `listora_listing` with the rewrite base `listing-tag`. It behaves like any WordPress taxonomy - `get_the_terms()`, `wp_set_object_terms()` and `WP_Query`'s `tax_query` all work as usual.

Search accepts tags as a filter parameter and returns them among its facets; see [REST API](../developer-guide/rest-api.md) for the request and response shape.

## Good to know

- **Tags are not [Features](amenities.md).** Features are a curated set the site owner defines, attached to listing types and rendered as checkboxes - "has parking", "wheelchair accessible". Tags are open vocabulary. Use features for the attributes you want every listing compared on, and tags for the long tail.
- **Tags are global, not per listing type.** Unlike categories and features, which can be restricted to specific listing types via the [Type Editor](type-editor.md), a tag is available everywhere. If you need per-type separation, use features.

## Related

- [Listing Categories](listing-categories.md) - the hierarchical dimension
- [Amenities & Features](amenities.md) - the curated per-type dimension
- [Search & Filters](search-and-filters.md) - where tags act as a facet
- [Type Editor](type-editor.md) - restricting categories and features per type
