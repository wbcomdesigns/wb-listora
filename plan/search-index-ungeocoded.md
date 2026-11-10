# Search index: the lat/lng columns default to 0

**Open, cosmetic, logged 2026-11-10 for 2.2.0.**

`Search_Indexer::index_listing()` (`includes/search/class-search-indexer.php`) writes `0` into the `search_index` lat/lng columns for a listing with no coordinates, or with a latitude and no longitude, because the columns are NOT NULL.

This no longer changes any result: every geo filter (radius, map bounds, `has_geo`) asks the geo table through `Geo_Query::has_place_sql()`, and that table holds a row only when both coordinates exist.

**Remaining cleanup:** make the columns nullable and store NULL for a missing coordinate. That is a schema change, so it needs a minor release and a `DB_VERSION` bump (production rule 4).
