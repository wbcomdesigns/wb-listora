---
journey: og-locale-native-output
plugin: wb-listora
priority: normal
roles: [anonymous]
covers: [open-graph, og-locale, native-og-output, get-locale-underscore]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listora_listing singular (capture LISTING_ID + slug)"
  - "No SEO plugin active (Yoast + Rank Math inactive — Listora's native OG output is active)"
estimated_runtime_minutes: 3
covers_card: null
covers_commit: deb68b6
---

# Native OG output emits one og:locale tag with the site locale (dashes → underscores)

Regression sentinel for M11 (`deb68b6`). When Listora's native Open Graph output
is active (neither Yoast nor Rank Math installed), the listing `<head>` was
missing an `og:locale` tag. The fix
(`includes/schema/class-schema-generator.php:444`) emits exactly one
`<meta property="og:locale" content="...">` whose content is `get_locale()` with
dashes replaced by underscores (WordPress stores `en_GB`, but a site locale like
`en-GB` must become `en_GB` for the OG spec). It follows the `og:site_name` line
(`:443`) and is HTML-attribute-escaped.

## Setup

- Site: `$SITE_URL`; `LISTING_ID` + slug; Yoast + Rank Math inactive.

## Steps

### 1. Emit-site in code
- **Action**:
  ```
  grep -n "og:locale\|og:site_name\|str_replace( '-', '_', get_locale() )" includes/schema/class-schema-generator.php
  ```
- **Expect**: the `og:locale` echo (`:444`) uses `esc_attr( str_replace( '-', '_', get_locale() ) )` and sits immediately after the `og:site_name` echo (`:443`).
- **On fail**: `deb68b6` — tag missing, raw (un-escaped), or not underscore-normalised.

### 2. Default locale → og:locale present
- **Action**: view source of `$SITE_URL/listing/<slug>/`.
- **Expect**: exactly one `<meta property="og:locale" content="en_US" />` (for a default en_US site), positioned right after `<meta property="og:site_name" ...>`.
- **Verify**:
  ```js
  const m = document.querySelectorAll('meta[property="og:locale"]');
  m.length;            // expect 1
  m[0].content;        // expect "en_US" (underscores, never dashes)
  ```
- **On fail**: tag absent or content carries a dash.

### 3. Dash-locale normalises to underscore
- **Action**: set a hyphenated-style locale that resolves to `en-GB` form (`wp site switch-language en_GB` then, if the site locale string contains a dash in any code path, the str_replace handles it). View source.
- **Expect**: `content="en_GB"` — the str_replace converts any `-` to `_`. Never `en-GB`.
- **On fail**: missing `str_replace`.

### 4. SEO plugin active → Listora's OG (and og:locale) absent
- **Action**: activate Yoast (or `define('WPSEO_VERSION','99.9')`). Reload.
- **Expect**: Listora's native OG block (including og:locale) is NOT emitted — the SEO plugin owns OG. (Guarded by the same `WPSEO_VERSION`/`RANK_MATH_VERSION` check at `class-schema-generator.php:416`.)

### Cleanup
- Restore the site locale + plugin activation state.

## Notes
- og:locale uses the WP-spec underscore form (`ll_CC`). This is the one OG tag most often dropped; this sentinel keeps it wired. Pairs with `seo-meta-output.md` (OG toggle), `schema-yoast-rankmath-guard.md`, `schema-duplicate-canonical.md`.
