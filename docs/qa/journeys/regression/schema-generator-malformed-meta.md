---
journey: schema-generator-malformed-meta
plugin: wb-listora
priority: high
roles: [anonymous, administrator]
covers: [schema-generator-defense, social-links-json-recovery, address-malformed-skip, business-hours-malformed-skip]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listora_listing exists with a registered listing-type term (capture as LISTING_ID)"
  - "WP_DEBUG and WP_DEBUG_LOG can be enabled (used to detect any silent PHP error during render)"
estimated_runtime_minutes: 4
covers_card: 9905075024
covers_commit: aa1b39a
---

# Schema generator survives malformed meta (no fatal on listing detail page)

Regression sentinel for BC 9905075024 — the reporter saw an `Uncaught Error: [] operator not supported for strings` at `class-schema-generator.php:154` after editing + saving a listing, then visiting it. The root cause: any `array`-typed meta key (`social_links`, `address`, `business_hours`, `gallery`, `features`, `price`, `map_location`) that gets persisted as a non-array (raw string from a buggy filter, JSON string from a legacy migration, etc.) would reach `$arr[] = $x` appenders inside the schema generator and fatal the request.

The fix (`aa1b39a`) is a single defensive coercion in `Schema_Generator::for_listing()` — `normalize_meta_for_schema()` runs once at factory time and coerces every array-typed meta key to a real array (decoding JSON-strings, falling back to `array()` on garbage). Every consumer downstream can keep using `is_array()` guards but no longer has to defend against pathological data.

This journey blocks regressions by injecting the exact malformed shapes a buggy filter or migration could leave behind, then invoking the schema generator and asserting (a) no fatal, (b) the schema still emits, (c) JSON-string-of-array auto-recovers.

## Setup

- Site: `$SITE_URL`
- Capture: `LISTING_ID` = a published `listora_listing` post that has a registered type term (otherwise `Schema_Generator::for_listing()` returns null before the bug surface).
- Baseline `debug.log` length so any new entry during the run is detectable.

## Steps

### 1. Baseline — well-shaped meta, no fatal
- **Action**: `wp eval "\$g = \WBListora\Schema\Schema_Generator::for_listing(LISTING_ID); echo \$g ? count(\$g->get_data()) : 'null';"`
- **Expect**: prints an integer ≥ 5 (schema keys emitted). No "FATAL"/"Error" appears in `debug.log` between start and end of this step.

### 2. Inject string-typed social_links → no fatal
- **Action**:
  ```
  wp post meta update LISTING_ID _listora_social_links 'https://facebook.com/example'
  wp eval "\$g = \WBListora\Schema\Schema_Generator::for_listing(LISTING_ID); try { \$g->get_data(); echo 'ok'; } catch (\Throwable \$e) { echo 'FATAL: '.\$e->getMessage(); }"
  ```
- **Expect**: prints `ok`. No fatal even though `_listora_social_links` is a bare string.

### 3. Inject string-typed address + business_hours together → no fatal, sections skipped cleanly
- **Action**:
  ```
  wp post meta update LISTING_ID _listora_address 'bad-string-not-array'
  wp post meta update LISTING_ID _listora_business_hours 'bad-string-not-array'
  wp eval "\$d = (\WBListora\Schema\Schema_Generator::for_listing(LISTING_ID))->get_data(); echo isset(\$d['address']) ? 'address-present' : 'address-absent'; echo PHP_EOL; echo isset(\$d['openingHoursSpecification']) ? 'hours-present' : 'hours-absent';"
  ```
- **Expect**: prints `address-absent` and `hours-absent` (coerced to `[]` → empty → block skipped). No fatal.

### 4. JSON-string-of-array auto-recovery
- **Action**:
  ```
  wp post meta update LISTING_ID _listora_social_links '{"facebook":"https://facebook.com/x","twitter":"https://twitter.com/x"}'
  wp eval "\$d = (\WBListora\Schema\Schema_Generator::for_listing(LISTING_ID))->get_data(); echo isset(\$d['sameAs']) && count(\$d['sameAs']) >= 2 ? 'sameAs-recovered' : 'sameAs-missing';"
  ```
- **Expect**: prints `sameAs-recovered`. The JSON string is auto-decoded and the URLs are emitted as schema.org `sameAs`.

### 5. Bulk listing sweep (regression detector for new pathological shapes)
- **Action**:
  ```
  wp eval "\$f=0; foreach (get_posts(['post_type'=>'listora_listing','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids']) as \$p) { try { \$g=\WBListora\Schema\Schema_Generator::for_listing(\$p); if (\$g) \$g->get_data(); } catch (\Throwable \$e) { \$f++; } } echo 'fatals: '.\$f;"
  ```
- **Expect**: prints `fatals: 0` across every published listing.

### 6. Browser path — visit the listing's frontend URL (catches anything wp_head emits)
- **Action**: navigate to `$SITE_URL/?p=LISTING_ID` (the canonical permalink). View page source.
- **Expect**:
  - HTTP 200, no "There has been a critical error" or "Cannot modify header" in the response body.
  - At least one `<script type="application/ld+json">` block present with valid JSON.
  - `debug.log` byte count unchanged (or new entries are unrelated, e.g. theme/SDK textdomain notices).

### Cleanup
- Delete the injected meta:
  ```
  wp post meta delete LISTING_ID _listora_social_links
  wp post meta delete LISTING_ID _listora_address
  wp post meta delete LISTING_ID _listora_business_hours
  ```

## Notes

- The defensive coercion runs at the `Schema_Generator::for_listing()` factory entry point. Adding new array-typed meta keys requires extending the `$array_keys` list in `normalize_meta_for_schema()` — keep the journey in sync when that list grows.
- The bug was reported on Reign theme; this journey tests theme-agnostic. If Reign-specific symptoms recur, add a Reign-active step.
- The original report's line number (154) is from a pre-fix code state. Current main has the proper guard at the same line.
