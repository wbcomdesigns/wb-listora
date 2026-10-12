---
journey: schema-duplicate-canonical
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [canonical-link, duplicate-canonical-guard, rel-canonical-removal, seo-plugin-ownership]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listora_listing singular (capture LISTING_ID + slug; verified with 774 /listing/union-square-greenmarket/)"
  - "No SEO plugin active at start; ability to define WPSEO_VERSION / RANK_MATH_VERSION via a temporary mu-plugin"
estimated_runtime_minutes: 4
covers_card: null
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [buddyx-pro light, one unknown default theme]
changed: true
---

# Exactly one canonical tag on a listing, and Listora emits none when an SEO plugin owns it

Verified via: curl against the live listing page with a temporary mu-plugin defining the constants (removed afterwards), plus a code read. Head output is theme-independent, so one theme is enough; the page is anonymous-only (no role ladder: the output is identical for every visitor, admin included).

On a `listora_listing` singular WordPress core emits `rel_canonical()` (wp_head priority 10). `Schema_Generator::output_canonical()` (`includes/schema/class-schema-generator.php:724-742`) removes core's `rel_canonical` and prints one canonical at `get_permalink()`. When `wb_listora_seo_plugin_active()` is true (Yoast, Rank Math, AIOSEO or SEOPress; constant or bootstrap class; filter `wb_listora_seo_plugin_active`, `includes/class-features.php:299`) it returns first, prints nothing and leaves core's tag in place.

## Steps

### 1. Guard order in code
- **Action**: `grep -n "wb_listora_seo_plugin_active\|remove_action( 'wp_head', 'rel_canonical' )\|rel=\"canonical\"" includes/schema/class-schema-generator.php`
- **Expect**: the active-SEO check (`:731`) precedes `remove_action` (`:739`) and the echo (`:741`).

### 2. No SEO plugin: exactly one canonical (positive control)
- **Action**: `curl -s $SITE_URL/listing/<slug>/ | grep -c 'rel="canonical"'`
- **Expect**: `1`, and `href` equals the listing permalink. In the browser: `document.querySelectorAll('link[rel="canonical"]').length === 1`.

### 3. Yoast constant defined: Listora emits none, core untouched
- **Action**: temporary mu-plugin `define('WPSEO_VERSION','99.9');` then reload.
- **Expect**: the page still has exactly 1 canonical tag, now WordPress core's (with the real Yoast it would be Yoast's); Listora's JSON-LD, Open Graph and Twitter tags are all absent (they share the same guard).
- **Observed**: canonical=1, ld+json=0, og:title=0, twitter:card=0.

### 4. Rank Math constant: same
- **Action**: swap to `define('RANK_MATH_VERSION','99.9');`. **Expect**: same as step 3.

### 5. Remove the stub: back to step 2.

### Cleanup
Delete the mu-plugin stub.

## Fail diagnostics
| Symptom | File |
|---|---|
| 2 canonicals with no SEO plugin | `remove_action` not reached: `class-schema-generator.php:739` |
| Listora tag next to an SEO plugin's | guard `:731` missing |
| 0 canonicals with SEO plugin | Listora removed core's tag before the guard |
| Hooked at wrong priority | `init_seo()` `class-schema-generator.php:747` (wp_head priority 5) |

## Related
`schema-yoast-rankmath-guard.md`, `og-locale-native-output.md`.
