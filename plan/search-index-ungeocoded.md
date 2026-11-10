# Search index: ungeocoded listings sit at 0,0

**Open, logged 2026-11-10 for 2.2.0.** Found by QA during the 2.1.1 pass; not a 2.1.1 blocker.

`Search_Indexer::index_listing()` (`includes/search/class-search-indexer.php`, the `$lat` / `$lng` casts near the top of the method) writes `0` into the `search_index` lat/lng columns for a listing with no coordinates, or with a latitude and no longitude. A radius search centred exactly on 0,0 therefore returns every ungeocoded listing.

The geo table (`update_geo_index()`) is already right: it holds a row only when both coordinates exist.

**Fix:** make the `search_index` lat/lng columns nullable and store NULL for a missing coordinate, then add a lat/lng-not-null condition to the radius query. That is a schema change, so it needs a minor release and a `DB_VERSION` bump (production rule 4).
