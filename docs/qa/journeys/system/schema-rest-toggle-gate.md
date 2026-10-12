---
journey: schema-rest-toggle-gate
plugin: wb-listora
priority: normal
roles: [anonymous, administrator]
covers: [schema-feature-toggle, listings-rest-schema-field, rest-page-jsonld-parity]
prerequisites:
  - "Site reachable at $SITE_URL; WP-CLI at $WP_PATH"
  - "A published {listing} of a registered type (capture its id and slug)"
  - "Schema.org JSON-LD feature ON at start (option wb_listora_features.schema)"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [buddyx-pro light, one unbridged default theme]
supersedes:
  - ora:regression/schema-rest-toggle-gate.md
fails_on: unknown (pre-fix sha not recorded)
site_profile: required
---

# The REST listing `schema` field is null when the Schema.org toggle is OFF and populated when ON

## Why
`GET /listora/v1/listings/{id}/detail` carries `schema`: the generated object when `wb_listora_feature_enabled('schema')`, else `null`, mirroring `Plugin::output_schema()` on the page. REST and page must agree. The plain `GET /listings/{id}` and list routes carry NO `schema` key (assert on `/detail` only). Related: `seo-meta-output.md`, `schema-yoast-rankmath-guard.md`, `schema-duplicate-canonical.md` (area G).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).

## Steps

### 1. Toggle ON: object present in REST and on the page (positive control)
- **Action**: `curl -s $SITE_URL/wp-json/listora/v1/listings/<id>/detail | jq '.schema | type, ."@type"'`; `curl -s $SITE_URL/listing/<slug>/ | grep -c 'application/ld+json'`.
- **Expect**: `"object"` and a populated `@type`; the page has 2 JSON-LD blocks (listing + `BreadcrumbList`).

### 2. Toggle OFF: REST null AND no listing JSON-LD
- **Action**: Settings > Features: untick Schema.org JSON-LD and save (or `wp eval 'wb_listora_set_feature("schema", false);'`), repeat step 1.
- **Expect**: `/detail` `.schema` is `null`; the page has no listing `@type` block (one JSON-LD block remains: the `BreadcrumbList`, owned by the Breadcrumbs feature, not Schema).

### 3. Parity
- **Expect**: "no listing JSON-LD on the page" and `schema === null` always agree for the schema toggle. With an SEO plugin constant defined the page emits none yet REST still returns the object (the REST gate reads only the toggle).

### 4. Sibling surfaces (added)
- **Action**: check `GET /listings/<id>` and `GET /listings?per_page=5` in both toggle states.
- **Expect**: no `schema` key in either state. Not verified live.

## Role ladder
Public data, identical for every visitor: check once as {guest} and once as {admin} (control).

## Pass criteria
REST `schema` is an object with `@type` when ON, `null` when OFF, and the page JSON-LD agrees.

## Fail diagnostics

| Symptom | File |
|---|---|
| REST schema populated while off | `includes/rest/class-listings-controller.php` `/detail` gate |
| Page prints listing JSON-LD while off | `includes/class-plugin.php` `output_schema()` |

## Not verified
Step 4.

## Cleanup
`wp eval 'wb_listora_set_feature("schema", true);'`; confirm `wp option get wb_listora_features`.
