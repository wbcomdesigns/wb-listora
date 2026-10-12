---
journey: schema-rest-toggle-gate
plugin: wb-listora
priority: normal
roles: [anonymous]
covers: [schema-feature-toggle, listings-rest-schema-field, rest-page-jsonld-parity]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listing of a registered type (capture LISTING_ID + slug)"
  - "Schema.org JSON-LD feature ON at start (option wb_listora_features.schema)"
estimated_runtime_minutes: 4
covers_card: null
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [buddyx-pro light, one unknown default theme]
changed: true
---

# REST listing `schema` field is null when the Schema.org toggle is OFF, populated when ON

Verified via: `wp eval 'wb_listora_set_feature("schema", false)'` (restored) + curl. The field is public data, identical for every visitor; no role ladder.

`GET /listora/v1/listings/{id}/detail` carries `schema` (`includes/rest/class-listings-controller.php:1106-1111`): the generated object when `wb_listora_feature_enabled('schema')`, else `null`. It mirrors `Plugin::output_schema()` (`includes/class-plugin.php:1075`). The plain `GET /listings/{id}` and list routes carry NO `schema` key at all (observed: key absent), so assert on `/detail` only.

## Steps

### 1. Toggle ON: object present in REST and on the page (positive control)
- **Action**:
  ```
  curl -s $SITE_URL/wp-json/listora/v1/listings/LISTING_ID/detail | jq '.schema | type, ."@type"'
  curl -s $SITE_URL/listing/<slug>/ | grep -c 'application/ld+json'
  ```
- **Expect**: `"object"` and a populated `@type` (`TouristAttraction` for 774); the page has 2 JSON-LD blocks (listing + `BreadcrumbList`).

### 2. Toggle OFF: REST null AND no listing JSON-LD
- **Action**: Listora > Settings > Features: untick Schema.org JSON-LD and save (or `wp eval 'wb_listora_set_feature("schema", false);'`), then repeat the two commands.
- **Expect**: `/detail` `.schema` is `null`; the page has no `"@type":"TouristAttraction"` block (observed: 1 JSON-LD block left, the `BreadcrumbList`, which is the Breadcrumbs feature, not Schema).

### 3. Parity
- **Expect**: for the schema toggle, "no listing JSON-LD on the page" and `schema === null` in `/detail` always agree; also true with an SEO plugin constant defined (page emits none) - note REST still returns the object then (the REST gate only reads the toggle, `:1106`).

### Cleanup
Re-enable: `wp eval 'wb_listora_set_feature("schema", true);'`; confirm `wp option get wb_listora_features`.

## Fail diagnostics
| Symptom | File |
|---|---|
| REST schema populated while toggle off | `class-listings-controller.php:1106` gate removed |
| Page still prints listing JSON-LD while off | `class-plugin.php:1076` |

## Related
`seo-meta-output.md`, `schema-yoast-rankmath-guard.md`, `schema-duplicate-canonical.md`.
