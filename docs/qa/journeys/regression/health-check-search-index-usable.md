---
slug: health-check-search-index-usable
priority: high
covers:
  - BC 10167581651
likely_files:
  - includes/admin/class-health-check.php
---

# Health Check reports whether the search index is USABLE

The table check confirmed `search_index` exists — the one thing that is almost
never wrong, and not what breaks search. An empty-but-present index is
reachable in normal operation (listings imported before the indexer ran, a
restored database, a truncated table) and makes search return NOTHING while
every Health Check card stays green.

## Steps

1. On a healthy site, open Listora → Health Check.
   - **Expect:** a "Search index" card, PASS, naming the indexed row count and
     confirming the FULLTEXT index.
2. Empty the index (`DELETE FROM {prefix}listora_search_index`) with published
   listings present, and reload.
   - **Expect:** FAIL, saying search returns no results, with a working
     Rebuild Search Index action.
   - **Fail if:** the page is all-green. That is the entire bug — a green
     health check on a directory whose search is dead.
3. Click the offered Rebuild control.
   - **Expect:** it schedules the reindex; after it runs the card returns to
     PASS.
4. Delete the FULLTEXT index only, leaving rows in place.
   - **Expect:** WARN — keyword search degrades rather than dies, so this is
     not a FAIL.
5. On a site with ZERO published listings, empty index.
   - **Expect:** PASS. An empty index is correct there, and crying wolf on a
     new install trains owners to ignore the screen.
