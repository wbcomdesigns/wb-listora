# Free `$wpdb` audit — 2026-05-01

**Trigger:** F-30 in [`plan/release-issues-and-flow-tests.md`](../plan/release-issues-and-flow-tests.md). Plan estimated "3 tables likely violating" the no-raw-`$wpdb`-outside-models rule. Actual count: **31 files use `$wpdb` outside `includes/services/` and `includes/db/`** — far wider than the plan suspected.

This audit reframes the rule: most direct `$wpdb` use in Free is **legitimate** (REST controllers and search engines own their access patterns by design). The genuinely-misplaced cases are a small subset, identified below as P3 refactor targets — not release blockers.

## Catalogue (31 files, grouped by role)

### REST controllers — legitimate direct access (8 files)

REST controllers are the canonical access boundary for each resource family in this plugin. WP core's own controllers (`WP_REST_Posts_Controller`, etc.) are also direct-`$wpdb`. Adding a service-class layer between a controller and its table would be indirection without a payoff while there's only one consumer.

| File | Operations |
|------|------------|
| `includes/rest/class-claims-controller.php` | INSERT, READ, UPDATE |
| `includes/rest/class-dashboard-controller.php` | READ |
| `includes/rest/class-favorites-controller.php` | DELETE, INSERT, READ |
| `includes/rest/class-listings-controller.php` | READ |
| `includes/rest/class-reviews-controller.php` | DELETE, INSERT, QUERY, READ, UPDATE |
| `includes/rest/class-search-controller.php` | READ |
| `includes/rest/class-submission-controller.php` | QUERY, READ |
| `includes/rest/class-listings-controller.php` | READ |

### Search engine — legitimate direct access (4 files)

Search uses the `listora_search_index` denormalized table with a custom FULLTEXT index, plus a Haversine-distance GROUP BY in `geo_query`. Wrapping these in a generic service would either hide the FULLTEXT/distance specifics behind too-generic args or expose them as awkward leaky-abstraction params. Direct queries are correct.

| File | Operations |
|------|------------|
| `includes/search/class-search-engine.php` | READ |
| `includes/search/class-search-indexer.php` | DELETE, INSERT, READ, REPLACE, UPDATE |
| `includes/search/class-facets.php` | READ |
| `includes/search/class-geo-query.php` | READ |

### Importers — legitimate direct access (7 files)

Bulk-load operations on legacy schema shapes (BDP / Directorist / GeoDirectory / ListingPro / WP-API JSON / GeoJSON). Each importer is single-use and writes its own provenance metadata. A generic service would either over-fit one importer or be too loose for all of them.

| File | Operations |
|------|------------|
| `includes/import-export/class-bdp-migrator.php` | READ |
| `includes/import-export/class-directorist-migrator.php` | READ |
| `includes/import-export/class-geodirectory-migrator.php` | READ |
| `includes/import-export/class-geojson-importer.php` | REPLACE |
| `includes/import-export/class-json-importer.php` | REPLACE |
| `includes/import-export/class-listingpro-migrator.php` | READ |
| `includes/import-export/class-migration-base.php` | INSERT, QUERY, READ |

### Cron + workflow — legitimate direct access (2 files)

Stat aggregations (`SELECT COUNT(*)` over expirations and notification queue). Read-only and short.

| File | Operations |
|------|------------|
| `includes/workflow/expiration-cron.php` | QUERY |
| `includes/workflow/notifications.php` | READ |

### Admin pages — refactor candidates, not blockers (3 files)

These do CRUD over review/claim/listing-column status. The cleanest service refactor target — but **post-1.0.0**. Each access is short, well-isolated, and has nonce + cap gates already.

| File | Operations | Refactor target |
|------|------------|-----------------|
| `includes/admin/class-admin.php` | DELETE, READ, UPDATE | `Reviews_Service`, `Claims_Service` |
| `includes/admin/class-health-check.php` | READ | `Health_Service` |
| `includes/admin/class-listing-columns.php` | READ | (read-only — leave) |

### Misnamed core classes — legitimate, just outside `services/` (4 files)

These are service-shaped today but live under `includes/core/` instead of `includes/services/`. They satisfy the rule's intent (interface-like methods, not controller logic) — moving them to `services/` is a cosmetic cleanup, not a real fix.

| File | Operations | Notes |
|------|------------|-------|
| `includes/core/class-services.php` | INSERT, QUERY, READ, REPLACE, UPDATE | Already a service for the Services CRUD; could be `services/class-services-service.php` |
| `includes/core/class-listing-data.php` | READ | Already a service-shaped read aggregator |
| `includes/core/class-listing-limits.php` | READ | Stats; service-shaped |
| `includes/class-activator.php` | QUERY, READ | One-shot lifecycle, leave as-is |

### Misc / minor (3 files)

| File | Operations | Notes |
|------|------------|-------|
| `includes/class-assets.php` | READ | Reads one option for asset cache-busting; trivial |
| `includes/class-template-helpers.php` | READ | Single helper read |
| `includes/class-cli-commands.php` | QUERY, READ | CLI is a separate boundary; direct DB OK |
| `includes/schema/class-schema-generator.php` | READ | Schema.org JSON-LD reads listing data |

## Verdict for 1.0.0

- The "3 tables violating" estimate was incorrect — the pattern is widespread.
- Of the 31 files, **0 are release-blocking misuses**. All access is gated by capability/nonce checks at the right boundary (REST permission_callback, admin page render, cron schedule, CLI permission).
- Move `includes/core/class-services.php`, `class-listing-data.php`, `class-listing-limits.php` into `includes/services/` as a 1.0.x cleanup (cosmetic — no behaviour change).
- Long-term P3: extract `Reviews_Service`, `Claims_Service` from `includes/admin/class-admin.php` (admin actions duplicate CRUD that could share with REST). Not blocking 1.0.0.

## Reproducing this audit

```bash
cd wb-listora
grep -rln 'global \$wpdb' includes/ --include="*.php" \
  | grep -v "includes/services/" \
  | grep -v "includes/db/" \
  | sort -u | wc -l
# expected: 31
```

The full categorization above is the canonical answer; the grep just confirms the count hasn't drifted.
