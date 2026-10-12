---
journey: listing-seo-head-output
plugin: wb-listora
priority: high
roles: [guest, admin]
covers: [schema-jsonld, opengraph-tags, og-locale, twitter-card, breadcrumbs, sitemap-inclusion, seo-feature-toggles, canonical-link, duplicate-canonical-guard, seo-plugin-guard, yoast-guard, rankmath-guard, output-schema-early-return, schema-feature-toggle, listings-rest-schema-field, rest-page-jsonld-parity]
prerequisites:
  - "Site reachable at $SITE_URL; a published {listing} of a registered type"
  - "schema, opengraph, breadcrumbs, sitemap features ON at start (`wb_listora_features`); no third-party SEO plugin"
  - "Ability to add a temporary mu-plugin defining `WPSEO_VERSION` / `RANK_MATH_VERSION` or filtering `wb_listora_seo_plugin_active`"
estimated_runtime_minutes: 12
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, unbridged]
site_profile: required
supersedes: [ora:regression/seo-meta-output.md, ora:regression/schema-yoast-rankmath-guard.md, ora:regression/schema-duplicate-canonical.md, ora:regression/og-locale-native-output.md, ora:system/schema-rest-toggle-gate.md]
---

# A listing page emits exactly one set of schema, Open Graph, breadcrumb and canonical tags, honours its four toggles, and goes silent when an SEO plugin owns the head

## Why
Search engines and link previews read the head. Doubled canonicals or JSON-LD next to Yoast/Rank Math confuse crawlers; a toggle that does not switch off leaves owners unable to opt out; a dash in `og:locale` is invalid for Facebook.

Each toggle (Listora > Settings > Features; `wb_listora_features`, read via `wb_listora_feature_enabled()`) emits its markup when ON and none when OFF, independently. `Plugin::output_schema()` and `Schema_Generator` return early when `wb_listora_seo_plugin_active()` is true (Yoast, Rank Math, AIOSEO, SEOPress by constant or class, plus the filter). Core's `rel_canonical()` is removed and replaced by one canonical at the permalink only when no SEO plugin owns it.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). `URL` = the {listing} permalink.
- Record `wp option get wb_listora_features` to restore at the end.

## Steps

### 1. Baseline, no SEO plugin (positive control)
- **Action**: `curl -s $URL | grep -c 'application/ld+json'`; `grep -o '<meta property="og:[a-z_:]*"[^>]*>'`; `grep -c 'rel="canonical"'`.
- **Expect**: `2` JSON-LD blocks (the type's listing graph with `name`, `address` PostalAddress, `aggregateRating` only when reviews are on and rating > 0; and one `BreadcrumbList`); og:type, og:title, og:description, og:url, og:site_name, then exactly one `og:locale`, then og:image; `twitter:card`; exactly `1` canonical whose href equals the permalink (`document.querySelectorAll('link[rel="canonical"]').length === 1`).

### 2. Schema toggle
- **Action**: `wb_listora_set_feature( 'schema', false )`; reload; also `GET /listings/{id}/detail`.
- **Expect**: no listing JSON-LD (the BreadcrumbList remains, owned by the Breadcrumbs feature); `/detail` `.schema` is `null`. ON again restores it: `.schema | type` is `"object"` with a populated `@type`.
- **Sibling routes**: plain `GET /listings/{id}` and `GET /listings?per_page=5` carry NO `schema` key in either state (assert on `/detail` only). Not verified live.

### 3. Open Graph toggle
- **Expect** OFF: every `og:*` and `twitter:*` tag absent. ON: all present.

### 4. Breadcrumbs toggle
- **Expect** ON: visible `<nav class="listora-detail__breadcrumb" aria-label="Breadcrumb">` AND a `BreadcrumbList` block. OFF: both absent. At 390 the nav is visible without horizontal scroll, in light and dark.

### 5. Sitemap toggle
- **Action**: `$SITE_URL/wp-sitemap.xml`.
- **Expect** ON: lists `wp-sitemap-posts-listora_listing-1.xml`, which lists `/listing/<slug>/` URLs. OFF: the index no longer references `listora_listing`.

### 6. Toggles are independent
- **Expect**: turning each of the four OFF in turn leaves the other three emitting.

### 7. og:locale uses underscores
- **Action**: `wp site switch-language en_GB` (or `add_filter( 'locale', fn() => 'en-GB' )`); reload.
- **Expect**: `content="en_GB"`, never `en-GB`; still exactly one tag. Restore the language.

### 8. Yoast constant: Listora is silent, core canonical stays
- **Action**: mu-plugin `define('WPSEO_VERSION','99.9');`; reload.
- **Expect**: zero Listora JSON-LD, OG, Twitter and BreadcrumbList; still exactly 1 canonical (core's). A real Yoast would print its own `@graph`; assert only Listora's blocks are absent.

### 9. Rank Math constant, then filter-only plugins
- **Action**: swap to `define('RANK_MATH_VERSION','99.9');`; then remove it and use `add_filter( 'wb_listora_seo_plugin_active', '__return_true' )` (stands in for AIOSEO and SEOPress).
- **Expect**: same as step 8 for each.
- **REST parity**: with an SEO-plugin constant defined the page emits no Listora JSON-LD yet `/detail` `.schema` is still the object (the REST gate reads only the Schema toggle, not the SEO-plugin guard).

### 10. Guard order in code
- **Action**: `grep -n "wb_listora_seo_plugin_active\|remove_action( 'wp_head', 'rel_canonical' )" includes/class-plugin.php includes/schema/class-schema-generator.php`.
- **Expect**: the active-SEO check precedes `remove_action` and every echo.

### 11. Restore
- Remove the mu-plugin; confirm step 1 numbers return; `wp option update wb_listora_features` back to the recorded value and compare.

## Role ladder
| Rung | Action | Expect |
|---|---|---|
| {guest} | steps 1-10 | as written |
| {admin} (control) | step 1 | identical head output; admin bar adds no second `og:locale` or canonical |
| {owner}, {other} | step 1 | identical (no per-user branch) |

## Pass criteria
1. Exactly one JSON-LD listing graph, one BreadcrumbList, one OG set with one `og:locale`, one canonical.
2. Each toggle emits its markup ON and none OFF, independently.
3. With any SEO plugin signal true Listora emits no head markup and core's canonical remains.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Listora JSON-LD next to the SEO plugin's | guard missing or after the echo | `includes/class-plugin.php:1075-1093` |
| Breadcrumb JSON-LD still printed under SEO plugin | guard missing | `includes/schema/class-schema-generator.php` (~:689) |
| 2 canonicals, no SEO plugin | `remove_action` not reached | `class-schema-generator.php` `output_canonical()` (~:724-742) |
| 0 canonicals with SEO plugin | Listora removed core's tag before the guard | same |
| `og:locale` with a dash or duplicated | normalisation lost | `class-schema-generator.php` (~:585) |
| Detector wrong for AIOSEO/SEOPress | signal list | `includes/class-features.php:299` |
| Wrong priority | `init_seo()` | `class-schema-generator.php` (~:747), wp_head 5 |

## Not verified
Step 4 at 390 and dark were not screenshotted; steps 7 and 9 (locale switch, filter-only) were not run live. Pro's SEO pages defer through the same detector (see `pro:customer/14-seo-pages-render.md`).

## Cleanup
Delete the mu-plugin stub; restore `wb_listora_features` and the site language.
