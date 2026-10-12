---
journey: seo-meta-output
plugin: wb-listora
priority: normal
roles: [anonymous, administrator]
covers: [schema-jsonld, opengraph-tags, breadcrumbs, sitemap-inclusion, seo-feature-toggles]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listing (verified: 774 /listing/union-square-greenmarket/)"
  - "schema, opengraph, breadcrumbs, sitemap features ON at start; no third-party SEO plugin"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# SEO / meta output (schema, Open Graph, breadcrumbs, sitemap) honours its toggles

Each of the four toggles (Listora > Settings > Features; option `wb_listora_features`, read through `wb_listora_feature_enabled()`) must emit its markup when ON and none when OFF, independently. Verified via: `wb_listora_set_feature()` per toggle (restored) + curl of the listing page, sitemap and `/detail`; the visible breadcrumb at 390 was not screenshotted.

Anonymous sees the markup; administrator (control) sees the same head output (no per-role branch). A third-party SEO plugin silences all of it (`schema-yoast-rankmath-guard.md`).

## Steps

### 1. Schema (feature `schema`)
- **ON**: `<script type="application/ld+json">` with the type's `@type` (`TouristAttraction` for 774), `name`, `address` (`PostalAddress`), `aggregateRating` only when reviews are on for the listing and rating > 0.
- **OFF**: no listing JSON-LD (observed: 1 block left, the BreadcrumbList); `/detail` `.schema` is `null`.
- **On fail**: `includes/class-plugin.php:1075-1093`, `includes/schema/class-schema-generator.php`.

### 2. Open Graph + Twitter (feature `opengraph`)
- **ON** (observed): `og:title`, `og:description`, `og:image`, `og:type`, `og:url`, `og:site_name`, `og:locale`, and `twitter:card`.
- **OFF**: all of them absent (observed 0).
- **On fail**: `class-schema-generator.php:557` guard and the OG emitter; `og-locale-native-output.md`.

### 3. Breadcrumbs (feature `breadcrumbs`)
- **ON**: visible `<nav class="listora-detail__breadcrumb" aria-label="Breadcrumb">` AND a `BreadcrumbList` JSON-LD block.
- **OFF**: both absent (observed nav 0, BreadcrumbList 0).

### 4. Sitemap (feature `sitemap`)
- **Action**: `$SITE_URL/wp-sitemap.xml` lists `wp-sitemap-posts-listora_listing-1.xml`; that file lists `/listing/<slug>/` URLs.
- **OFF**: the index no longer references `listora_listing` (observed 0 hits).

### 5. Toggles are independent
- Turning one OFF leaves the other three emitting (observed for each of the four).

### Cleanup
Restore `wb_listora_features` exactly (all four true here) and confirm with `wp option get wb_listora_features`.
