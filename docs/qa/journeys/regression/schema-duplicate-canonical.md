---
journey: schema-duplicate-canonical
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [canonical-link, duplicate-canonical-guard, rel-canonical-removal, seo-plugin-ownership]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listora_listing singular (capture LISTING_ID + slug)"
  - "No SEO plugin active at start (Yoast + Rank Math inactive); ability to define WPSEO_VERSION/RANK_MATH_VERSION"
estimated_runtime_minutes: 4
covers_card: null
covers_commit: 8ecd82e
---

# Exactly one canonical tag on a listing — and ZERO when an SEO plugin owns it

Regression sentinel for M10 (`8ecd82e`). On a `listora_listing` singular, the
page was emitting TWO `<link rel="canonical">` tags — WordPress core's
`rel_canonical()` (wp_head priority 10) AND the Schema_Generator's own. The fix
(`includes/schema/class-schema-generator.php:548-558`) removes core's
`rel_canonical` action and emits exactly one canonical pointing at
`get_permalink()`. When `WPSEO_VERSION` or `RANK_MATH_VERSION` is defined, the
generator emits ZERO canonical tags AND does NOT call `remove_action` on
`rel_canonical` (the SEO plugin owns canonical and core's behaviour is left
untouched).

## Setup

- Site: `$SITE_URL`; `LISTING_ID` + slug; Yoast + Rank Math inactive.

## Steps

### 1. Guard + single-emit logic in code
- **Action**:
  ```
  grep -n "WPSEO_VERSION\|RANK_MATH_VERSION\|remove_action( 'wp_head', 'rel_canonical' )\|rel=.canonical" includes/schema/class-schema-generator.php
  ```
- **Expect**: the SEO-plugin check (line ~548) returns BEFORE `remove_action('wp_head','rel_canonical')` (line ~556) and before the `<link rel="canonical">` echo (line ~558). With no SEO plugin: core's canonical is removed, one Listora canonical emitted.
- **On fail**: `8ecd82e` — either no removal of core's canonical, or removal happens even when an SEO plugin is active.

### 2. No SEO plugin → exactly ONE canonical, href == permalink
- **Action**: view source of `$SITE_URL/listing/<slug>/`. Count `<link rel="canonical">`.
- **Expect**: exactly **one** `<link rel="canonical">`; its `href` equals `get_permalink(LISTING_ID)` (the listing's clean permalink). Not two, not zero.
- **Verify**:
  ```js
  document.querySelectorAll('link[rel="canonical"]').length          // expect 1
  document.querySelector('link[rel="canonical"]').href               // expect the listing permalink
  ```
- **On fail**: core's `rel_canonical` not removed (→ 2 tags) or the generator's canonical missing (→ 0).

### 3. Yoast active → ZERO Listora canonical, core untouched
- **Action**: activate Yoast (or `define('WPSEO_VERSION','99.9')`). Reload.
- **Expect**: Listora emits NO canonical tag; the Schema_Generator does NOT call `remove_action('wp_head','rel_canonical')` (so the SEO plugin / core canonical behaviour is intact). The page's canonical is Yoast's, exactly one.
- **On fail**: Listora still emits its canonical alongside the SEO plugin's (→ duplicate again), or it strips core's `rel_canonical` and leaves the page with zero.

### 4. Rank Math active → same
- **Action**: swap to `RANK_MATH_VERSION`. Reload. Same expectation as step 3.

### Cleanup
- Remove the mu-plugin stub / restore activation state.

## Notes
- The "exactly one canonical, owned by whoever is in charge" contract: Listora owns it when no SEO plugin is present; the SEO plugin owns it otherwise. The guard constant matches M9 (`schema-yoast-rankmath-guard.md`) and M11 (`og-locale-native-output.md`).
