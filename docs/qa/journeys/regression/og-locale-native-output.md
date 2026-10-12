---
journey: og-locale-native-output
plugin: wb-listora
priority: normal
roles: [anonymous]
covers: [open-graph, og-locale, native-og-output, get-locale-underscore]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listora_listing (demo: /listing/the-golden-fork/)"
  - "No SEO plugin active (wb_listora_seo_plugin_active() is false): Listora's native OG output is active"
estimated_runtime_minutes: 3
covers_card: null
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
themes: [buddyx-pro light]
changed: true
---

# Native OG output emits one og:locale tag with the site locale (dashes to underscores)

Emit site: `includes/schema/class-schema-generator.php:585`, immediately after `og:site_name` (`:584`): `esc_attr( str_replace( '-', '_', get_locale() ) )`. The whole native OG block is skipped when `wb_listora_seo_plugin_active()` (`includes/class-features.php:299`, covers Yoast, Rank Math, AIOSEO, SEOPress, filter `wb_listora_seo_plugin_active`) is true (`class-schema-generator.php:557`).

Verified via: curl + grep of the live head and source read. Steps 3-4 were not executed (locale switch / SEO plugin).

## Steps

### 1. Emit-site in code
- **Action**: `grep -n "og:locale\|og:site_name" includes/schema/class-schema-generator.php`.
- **Expect**: `:584` og:site_name then `:585` og:locale using `str_replace( '-', '_', get_locale() )` inside `esc_attr()`.

### 2. Default locale -> og:locale present (positive control)
- **Action**: `curl -s $SITE_URL/listing/the-golden-fork/ | grep -o '<meta property="og:[a-z_:]*"[^>]*>'`.
- **Expect**: exactly one `<meta property="og:locale" content="en_US" />`, right after `og:site_name` (observed order: og:type, og:title, og:description, og:url, og:site_name, og:locale, og:image). `grep -c 'og:locale'` is 1.
```js
const m = document.querySelectorAll('meta[property="og:locale"]'); // length 1, m[0].content === "en_US"
```

### 3. Other locale normalises to underscores
- **Action**: `wp site switch-language en_GB` (or `add_filter( 'locale', fn() => 'en-GB' )` in an mu-plugin), reload the listing page.
- **Expect**: `content="en_GB"`, never `en-GB`. Restore the language.

### 4. SEO plugin active -> Listora's OG absent (negative control)
- **Action**: activate Yoast / Rank Math, or `add_filter( 'wb_listora_seo_plugin_active', '__return_true' )`. Reload.
- **Expect**: Listora's native OG block, including og:locale, is NOT emitted; the SEO plugin owns OG.

### 5. Role ladder
Head output is identical for logged-out and logged-in users; verify logged out (control) and once as admin that no second `og:locale` appears.

## Fail diagnostics
`class-schema-generator.php:585` tag missing / not normalised; `:557` guard; pairs with `seo-meta-output`, `schema-yoast-rankmath-guard`, `schema-duplicate-canonical`.
