---
journey: schema-yoast-rankmath-guard
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [schema-jsonld, seo-plugin-guard, yoast-guard, rankmath-guard, output-schema-early-return]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listora_listing of a registered type (capture LISTING_ID + slug)"
  - "Schema feature ON; ability to define WPSEO_VERSION / RANK_MATH_VERSION via a temporary mu-plugin"
estimated_runtime_minutes: 5
covers_card: null
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [buddyx-pro light, one unknown default theme]
changed: true
---

# Listora emits no JSON-LD (listing or breadcrumbs) when Yoast or Rank Math is active

Verified via: curl with a temporary mu-plugin (removed afterwards). Anonymous-only surface; identical for every role.

`Plugin::output_schema()` (`includes/class-plugin.php:1075-1093`) returns early when the Schema feature is off, then when `wb_listora_seo_plugin_active()` (`includes/class-features.php:299`) is true. That helper covers Yoast (`WPSEO_VERSION`/`WPSEO_Options`), Rank Math (`RANK_MATH_VERSION`/`RankMath`), AIOSEO and SEOPress, plus the `wb_listora_seo_plugin_active` filter.

## Steps

### 1. Guard in code
- `grep -n "wb_listora_seo_plugin_active\|feature_enabled( 'schema' )\|application/ld" includes/class-plugin.php`
- **Expect**: feature gate `:1076`, SEO guard `:1083`, echo `:1090`.

### 2. No SEO plugin (positive control)
- **Action**: `curl -s $SITE_URL/listing/<slug>/ | grep -c 'application/ld+json'`
- **Expect**: `2` - one listing graph (`"@type":"TouristAttraction"` for 774; the type's schema type) and one `BreadcrumbList` (Breadcrumbs feature). Not "exactly one" as older notes said.

### 3. Yoast constant: none
- **Action**: mu-plugin `define('WPSEO_VERSION','99.9');`, reload.
- **Expect**: `0` Listora JSON-LD blocks, and the Open Graph/Twitter tags and BreadcrumbList are also gone (same guard: `class-schema-generator.php:557`, `:689`). A real Yoast would print its own `@graph`; assert only Listora's blocks are absent.

### 4. Rank Math constant: none (`define('RANK_MATH_VERSION','99.9');`).

### 5. Remove the stub: back to 2 blocks.

### Cleanup
Delete the mu-plugin stub.

## Fail diagnostics
| Symptom | File |
|---|---|
| Listora JSON-LD next to the SEO plugin's | `class-plugin.php:1083` guard missing or after the echo |
| Breadcrumb JSON-LD still printed | `class-schema-generator.php:689` |
