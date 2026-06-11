---
journey: seo-meta-output
plugin: wb-listora
priority: normal
roles: [anonymous, administrator]
covers: [schema-jsonld, opengraph-tags, breadcrumbs, sitemap-inclusion, seo-feature-toggles]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listing exists (LISTING_ID + slug)"
  - "schema, opengraph, breadcrumbs, sitemap features ON at start"
estimated_runtime_minutes: 5
---

# SEO / meta output (schema, Open Graph, breadcrumbs, sitemap) honour their toggles

Coverage for the four SEO/meta feature toggles, which had no dedicated journey. Each must emit its markup when ON and be absent when OFF (the toggles live in the `wb_listora_features` option and gate via `wb_listora_feature_enabled()`).

## Steps

### 1. Schema.org JSON-LD (feature: schema)
- **Action**: view `$SITE_URL/listing/<slug>` source.
- **Expect (ON)**: a `<script type="application/ld+json">` block with the type's `@type` (e.g. `Restaurant`/`LocalBusiness`), `name`, `address`, and `aggregateRating` only when Reviews are on.
- **Toggle OFF**: disable "Schema.org JSON-LD" → reload → no Listora JSON-LD block.
- **On fail**: `includes/schema/class-schema-generator.php` + its `wb_listora_feature_enabled('schema')` gate.

### 2. Open Graph + Twitter cards (feature: opengraph)
- **Expect (ON)**: `<meta property="og:title">`, `og:description`, `og:image` (when a featured image exists), `og:type`, and `twitter:card` in `<head>`.
- **Toggle OFF**: tags absent.
- **On fail**: the Open Graph feature class + `wb_listora_feature_enabled('opengraph')`.

### 3. Breadcrumbs (feature: breadcrumbs)
- **Expect (ON)**: a breadcrumb trail renders on the listing (Home > Category/Location > Listing) AND a `BreadcrumbList` JSON-LD is emitted.
- **Toggle OFF**: breadcrumb trail + BreadcrumbList absent.
- **On fail**: breadcrumbs feature class + `wb_listora_feature_enabled('breadcrumbs')`.

### 4. Sitemap inclusion (feature: sitemap)
- **Action**: open `$SITE_URL/wp-sitemap.xml` (or the listings sub-sitemap).
- **Expect (ON)**: `listora_listing` URLs are included.
- **Toggle OFF**: listings excluded from the sitemap.
- **On fail**: sitemap feature wiring + `wb_listora_feature_enabled('sitemap')`.

### 5. Toggles are independent
- **Expect**: disabling one (e.g. schema) does not affect the others (OG/breadcrumbs/sitemap still emit). Each reads its own key in `wb_listora_features`.
