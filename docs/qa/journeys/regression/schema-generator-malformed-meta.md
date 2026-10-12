---
journey: schema-generator-malformed-meta
plugin: wb-listora
priority: high
roles: [system, anonymous]
covers: [schema-generator-defense, social-links-json-recovery, address-malformed-skip, business-hours-malformed-skip]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listora_listing whose type shows the address, social_links and business_hours fields on the listing page (capture LISTING_ID; verified with 774 union-square-greenmarket, type place)"
  - "WP_DEBUG and WP_DEBUG_LOG on (they are on at this site)"
estimated_runtime_minutes: 4
covers_card: 9905075024
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [buddyx-pro light, one unknown default theme]
changed: true
---

# Schema generator survives malformed meta (no fatal on the listing page)

Regression sentinel for BC 9905075024 (`[] operator not supported for strings` in the schema generator after a listing was saved). `Schema_Generator::for_listing()` (`includes/schema/class-schema-generator.php:25`) runs `normalize_meta_for_schema()` (`:58-77`) once, coercing `address`, `social_links`, `business_hours`, `gallery`, `features`, `price`, `map_location` to arrays (JSON strings are decoded, garbage becomes `[]`).

Verified via: `wp eval-file` against the live site (meta restored afterwards) plus curl of the page. Output is the same for every visitor (no role ladder; the generator has no per-user branch). Note: since 2.0.0 the generator reads `Meta_Handler::get_visible_values()` (`includes/core/class-meta-handler.php:353`), so a field switched off with "Show on listing page" is absent from the JSON-LD by design: inject only into fields the type shows.

## Steps

### 1. Baseline (positive control)
- **Action**: `wp eval '$g=\WBListora\Schema\Schema_Generator::for_listing(LISTING_ID); echo count($g->get_data());'`
- **Expect**: an integer >= 5 (observed 16, `@type` `TouristAttraction`); nothing new in `wp-content/debug.log`.

### 2. String-typed social_links: no fatal
- **Action**: `wp post meta update LISTING_ID _listora_social_links 'https://facebook.com/example'`, then build the generator and call `get_data()` inside try/catch.
- **Expect**: no Throwable.

### 3. String-typed address + business_hours together
- **Action**: set `_listora_address` and `_listora_business_hours` to `bad-string-not-array`, then read `get_data()`.
- **Expect**: no fatal; `openingHoursSpecification` is absent.
- **Observed**: `address` is NOT absent: it is `{"@type":"Place","address":{"@type":"PostalAddress","streetAddress":"","addressLocality":"","addressRegion":"","postalCode":"","addressCountry":""}}`, an empty PostalAddress from the type-property mapper (reported as a product bug). Pass criterion for this run: no fatal, hours absent, and the JSON-LD still valid JSON.

### 4. JSON-string-of-array recovers
- **Action**: set `_listora_social_links` to `{"facebook":"https://facebook.com/x","twitter":"https://twitter.com/x"}`.
- **Expect**: `sameAs` contains both URLs (observed `["<permalink>","https://facebook.com/x","https://twitter.com/x"]`; the permalink is first because the listing has a website).

### 5. Bulk sweep over published listings
- **Action**: loop all published `listora_listing` ids (81 on this site) through `for_listing()->get_data()` in try/catch.
- **Expect**: `fatals: 0`. (The 2,709 drafts are not public and not rendered; include them with `post_status=any` for the large-data pass.)

### 6. Browser path
- **Action**: `curl -s -o /dev/null -w "%{http_code}" $SITE_URL/listing/<slug>/` with the malformed meta in place; then restore.
- **Expect**: 200, no "critical error", and two `<script type="application/ld+json">` blocks: the listing graph and `BreadcrumbList`.

### Cleanup
`wp post meta delete LISTING_ID _listora_social_links` (and `_listora_address`, `_listora_business_hours`) or `update` them back to the saved originals (read them first: the demo listing had a full address array).

## Fail diagnostics
| Symptom | File |
|---|---|
| Fatal `[] operator` | appender on a non-array: `class-schema-generator.php:62` `$array_keys` list missing a key |
| JSON string not recovered | `normalize_meta_for_schema()` `:66-76` |
| Empty PostalAddress emitted for garbage address | type-property mapper `class-schema-generator.php:245` (skips props already set) |
