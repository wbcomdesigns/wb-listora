---
journey: schema-yoast-rankmath-guard
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [schema-jsonld, seo-plugin-guard, yoast-guard, rankmath-guard, output-schema-early-return]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listora_listing of a registered type (capture LISTING_ID + slug)"
  - "schema feature ON; ability to (de)activate Yoast or Rank Math, or define WPSEO_VERSION/RANK_MATH_VERSION via mu-plugin"
estimated_runtime_minutes: 5
covers_card: null
covers_commit: d412d58
---

# Listora emits ZERO JSON-LD on a listing when Yoast or Rank Math is active

Regression sentinel for M9 (`d412d58`). When an SEO plugin owns structured data,
Listora must not also emit LocalBusiness/Place JSON-LD — duplicate/competing
schema confuses search engines. `Plugin::output_schema()`
(`includes/class-plugin.php:483-496`) early-returns when `WPSEO_VERSION` or
`RANK_MATH_VERSION` is defined, AND when `wb_listora_feature_enabled('schema')`
is false. When neither SEO plugin is active and the schema feature is ON, it
emits exactly one JSON-LD block.

## Setup

- Site: `$SITE_URL`; `LISTING_ID` + slug; schema feature ON.

## Steps

### 1. The guard exists in code
- **Action**:
  ```
  grep -n "WPSEO_VERSION\|RANK_MATH_VERSION\|wb_listora_feature_enabled( 'schema' )\|application/ld" includes/class-plugin.php
  ```
- **Expect**: `output_schema()` returns early if `! wb_listora_feature_enabled('schema')` (line ~484) and again if `defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION')` (line ~489), BEFORE any `echo '<script type="application/ld+json">'` (line ~496).
- **On fail**: `d412d58` — the SEO-plugin early return is missing or after the echo.

### 2. Neither SEO plugin active → exactly ONE Listora JSON-LD
- **Action**: ensure Yoast + Rank Math are deactivated. View source of `$SITE_URL/listing/<slug>/`.
- **Expect**: exactly **one** `<script type="application/ld+json">` emitted by Listora (a LocalBusiness/Place graph with the listing `name`). Count must be 1, not 0, not 2.
- **On fail**: feature gate wrong, or generator returning null for a typed listing.

### 3. Yoast active → ZERO Listora JSON-LD
- **Action**: define the guard condition (activate Yoast, or drop a mu-plugin `define('WPSEO_VERSION','99.9');`). Reload the listing source.
- **Expect**: NO Listora-emitted LocalBusiness/Place JSON-LD. (Yoast's own `@graph` may be present — that's Yoast's, not Listora's; assert Listora's specific block is absent.) `output_schema()` early-returned.
- **On fail**: Listora still echoes its JSON-LD alongside the SEO plugin's.

### 4. Rank Math active → ZERO Listora JSON-LD
- **Action**: swap to `define('RANK_MATH_VERSION','99.9');` (deactivate Yoast). Reload.
- **Expect**: same as step 3 — Listora emits nothing; Rank Math owns schema.

### 5. Remove the SEO plugin → Listora resumes (exactly one)
- **Action**: undefine the constant / deactivate the SEO plugin. Reload.
- **Expect**: back to exactly one Listora JSON-LD block (step 2 state).

### Cleanup
- Remove any mu-plugin stub; restore the original plugin activation state.

## Notes
- The constant guard (`WPSEO_VERSION`/`RANK_MATH_VERSION`) is the same condition used by the Schema_Generator's OG + canonical guards (M10/M11) — they must all agree on "an SEO plugin is present." See `schema-duplicate-canonical.md` and `og-locale-native-output.md`.
