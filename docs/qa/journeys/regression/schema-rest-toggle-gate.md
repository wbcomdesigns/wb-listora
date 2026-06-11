---
journey: schema-rest-toggle-gate
plugin: wb-listora
priority: normal
roles: [anonymous]
covers: [schema-feature-toggle, listings-rest-schema-field, rest-page-jsonld-parity]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listing of a registered type (capture LISTING_ID + slug)"
  - "schema feature ON at start"
estimated_runtime_minutes: 4
covers_card: null
covers_commit: c0995ab
---

# REST listing `schema` field is null when the Schema.org toggle is OFF, populated when ON

Regression sentinel for M8 (`c0995ab`). The REST listing endpoints exposed a
generated `schema` object unconditionally, ignoring the Schema.org feature
toggle — so a headless client kept emitting LocalBusiness/Place JSON-LD even
after the site owner disabled schema. The fix gates the REST field on
`wb_listora_feature_enabled('schema')`
(`includes/rest/class-listings-controller.php:1066-1071`), mirroring
`Plugin::output_schema()`: ON → `data.schema` is the generated object; OFF →
`data.schema === null`.

## Setup

- Site: `$SITE_URL`; `LISTING_ID` + slug.

## Steps

### 1. REST field is gated by the toggle in code
- **Action**:
  ```
  grep -n "wb_listora_feature_enabled( 'schema' )\|\$data\['schema'\]" includes/rest/class-listings-controller.php
  ```
- **Expect**: `data['schema']` is set to the generated object only inside `if ( wb_listora_feature_enabled( 'schema' ) )`, else `null`.
- **On fail**: `c0995ab` — the field must be toggle-gated.

### 2. Toggle ON → schema object present in REST + JSON-LD on the page
- **Action** (schema ON):
  ```
  curl -s "$SITE_URL/wp-json/listora/v1/listings/LISTING_ID/detail" | jq '.schema | type, (."@type" // .data.schema."@type")'
  curl -s "$SITE_URL/wp-json/listora/v1/listings/LISTING_ID" | jq '.schema | type'
  ```
- **Expect**: `data.schema` is a populated object (has `@type`, `name`, etc.), NOT null, on BOTH `/detail` and `/{id}`. The page source at `$SITE_URL/listing/<slug>/` contains a `<script type="application/ld+json">` block.
- **On fail**: schema generator returning null for a typed listing, or the gate inverted.

### 3. Toggle OFF → REST schema is null AND page emits no JSON-LD
- **Action**: disable Schema.org (Settings → Features / `wb_listora_features['schema']=false`); reload.
  ```
  curl -s "$SITE_URL/wp-json/listora/v1/listings/LISTING_ID/detail" | jq '.schema'
  curl -s "$SITE_URL/wp-json/listora/v1/listings/LISTING_ID" | jq '.schema'
  ```
- **Expect**: `data.schema` is `null` on both endpoints — never a populated object and never a stale cached one. The page source has NO Listora `<script type="application/ld+json">` block (parity with `output_schema()` early return).
- **On fail**: the REST field still computes schema when the page-level JSON-LD is suppressed.

### 4. Parity assertion
- **Expect**: for every toggle state, `output_schema()` emitting no JSON-LD ⇔ REST `data.schema === null`. The two never disagree.

### Cleanup
- Re-enable the schema feature.

## Notes
- Pairs with `seo-meta-output.md` (page-level schema toggle), `schema-rest-yoast-rankmath-guard.md` (M9), and `schema-duplicate-canonical.md` (M10). This sentinel locks the REST↔page parity specifically.
