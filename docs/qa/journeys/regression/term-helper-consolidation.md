---
journey: term-helper-consolidation
plugin: wb-listora
priority: high
roles: [admin, system]
covers: [term-helper-shared-implementation, email-body-formatter-shared-implementation, inv-3-pro-via-extension-functions-not-class-refs]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "WP_DEBUG + WP_DEBUG_LOG enabled"
  - "Both wb-listora and wb-listora-pro active at 1.1.0+"
estimated_runtime_minutes: 3
---

# Term_Helper + Email_Body_Formatter — Free is the canonical implementation; Pro consumes via extension functions

Phase 2 of the 1.1.0 migrator-consolidation extracted two byte-identical duplicate methods (`set_taxonomy_terms` and `html_to_text`) from Free + Pro into Free-side helper classes (`\WBListora\Import\Term_Helper`, `\WBListora\Workflow\Email_Body_Formatter`). Pro reaches them via documented extension functions (`wb_listora_set_taxonomy_terms()`, `wb_listora_email_html_to_text()`), never via direct class refs (INV-3 compliance).

This journey verifies that:
1. Both Free's universal importers AND Pro's Visual_Importer produce IDENTICAL taxonomy-term-relationship rows for the same input.
2. Pro's `Email_Helpers::with_plain_text_fallback()` produces the same plain-text body as Free's `Notifications` does internally.
3. Pro's call paths route through the function (not direct class ref) — verified by inspecting the source code (INV-3 enforcement gate, not just runtime behavior).

Pre-fix discovered 2026-05-18 during the onboard-skill duplicate-detector scan (5 cross-plugin duplicates → 3).

## Setup

- Site: `$SITE_URL`
- Truncate debug.log:
  ```bash
  > /Users/varundubey/Local\ Sites/directory/app/public/wp-content/debug.log
  ```
- Capture baseline taxonomy state:
  ```bash
  CATS_BEFORE=$(wp eval 'echo wp_count_terms( array( "taxonomy" => "listora_listing_cat", "hide_empty" => false ) );')
  ```

## Steps

### 1. Free's JSON importer creates terms via Term_Helper
- **Action**: Import a JSON file with 3 listings, each having a unique category term ("Coffee Shop A", "Coffee Shop B", "Coffee Shop C"):
  ```bash
  echo '[
    {"title":"Listing A","category":"Coffee Shop A"},
    {"title":"Listing B","category":"Coffee Shop B"},
    {"title":"Listing C","category":"Coffee Shop C"}
  ]' > /tmp/term-helper-test.json
  wp eval-file <(echo '<?php
    require_once "wp-content/plugins/wb-listora/includes/import-export/class-json-importer.php";
    $items = json_decode( file_get_contents( "/tmp/term-helper-test.json" ), true );
    foreach ( $items as $item ) {
        $post_id = wp_insert_post( array( "post_title" => $item["title"], "post_type" => "listora_listing", "post_status" => "publish" ) );
        wb_listora_set_taxonomy_terms( $post_id, array( $item["category"] ), "listora_listing_cat" );
    }
  ')
  ```
- **Expected**: 3 new terms created in `listora_listing_cat`, 3 listing-term relationships in `wp_term_relationships`.
- **Assert**:
  ```bash
  CATS_AFTER=$(wp eval 'echo wp_count_terms( array( "taxonomy" => "listora_listing_cat", "hide_empty" => false ) );')
  echo "Cats: before=$CATS_BEFORE after=$CATS_AFTER  delta=$(( CATS_AFTER - CATS_BEFORE ))"
  # delta should be exactly 3
  ```

### 2. Pro's Visual_Importer uses the SAME function for the SAME terms
- **Action**: Run Pro's Visual_Importer against the same 3 terms (they should NOT create duplicate terms — `term_exists()` should match):
  ```bash
  wp eval '
  $importer = "\\WBListoraPro\\ImportExport\\Visual_Importer";
  if ( method_exists( $importer, "set_taxonomy_terms" ) ) {
      $r = new ReflectionMethod( $importer, "set_taxonomy_terms" );
      $r->setAccessible( true );
      // 3 listings, same 3 category names — should NOT create duplicate terms
      foreach ( array( "Coffee Shop A", "Coffee Shop B", "Coffee Shop C" ) as $cat ) {
          $post_id = wp_insert_post( array( "post_title" => "Pro Listing " . $cat, "post_type" => "listora_listing", "post_status" => "publish" ) );
          $r->invoke( null, $post_id, array( $cat ), "listora_listing_cat" );
      }
  }
  '
  ```
- **Expected**: 3 NEW listing-term relationships, but ZERO new terms created (the 3 from step 1 are reused).
- **Assert**:
  ```bash
  CATS_FINAL=$(wp eval 'echo wp_count_terms( array( "taxonomy" => "listora_listing_cat", "hide_empty" => false ) );')
  echo "Cats final: $CATS_FINAL  (should equal CATS_AFTER from step 1)"
  # Final must equal the count after step 1 — no duplicates introduced
  ```

### 3. INV-3 compliance — Pro routes via the function, not class ref
- **Action**:
  ```bash
  grep -n 'wb_listora_set_taxonomy_terms' wp-content/plugins/wb-listora-pro/includes/importexport/class-visual-importer.php
  grep -nE '\\\\WBListora\\\\Import\\\\Term_Helper::' wp-content/plugins/wb-listora-pro/includes/ --include='*.php' -r
  ```
- **Expected**:
  - First grep finds at least one match — Pro uses the function
  - Second grep finds ZERO matches — Pro never references the Free internal class directly
- **Fail if**: second grep returns any matches → INV-3 violation. Pro must reach Free helpers through `wb_listora_*()` functions only.

### 4. Email_Body_Formatter parity
- **Action**:
  ```bash
  wp eval '
  $html = "<p>Hello <a href=\"https://example.com\">friend</a> &mdash; please visit our <strong>site</strong>.</p>";
  $via_free_function = wb_listora_email_html_to_text( $html );
  $via_pro_helper = \\WBListoraPro\\Email_Helpers::html_to_text( $html );
  echo "Match: " . ( $via_free_function === $via_pro_helper ? "YES" : "NO" ) . PHP_EOL;
  echo "Body: " . $via_free_function . PHP_EOL;
  '
  ```
- **Expected**: `Match: YES` AND body contains `friend (https://example.com)` (link inlined).
- **Fail if**: outputs differ → Pro's shim isn't delegating to Free's function, indicating a regression.

### 5. Debug log clean
- **Action**:
  ```bash
  tail -200 /Users/varundubey/Local\ Sites/directory/app/public/wp-content/debug.log | grep -E "PHP (Fatal|Warning|Notice|Deprecated)" | grep -v "wp-includes\|wp-admin"
  ```
- **Expected**: empty.

## Teardown

```bash
wp eval '
$posts = get_posts( array( "post_type" => "listora_listing", "title" => array( "Listing A", "Listing B", "Listing C" ), "posts_per_page" => -1, "fields" => "ids", "post_status" => "any" ) );
$pro_posts = get_posts( array( "post_type" => "listora_listing", "s" => "Pro Listing Coffee Shop", "posts_per_page" => -1, "fields" => "ids", "post_status" => "any" ) );
foreach ( array_merge( $posts, $pro_posts ) as $id ) { wp_delete_post( $id, true ); }
foreach ( array( "Coffee Shop A", "Coffee Shop B", "Coffee Shop C" ) as $name ) {
    $t = get_term_by( "name", $name, "listora_listing_cat" );
    if ( $t ) wp_delete_term( $t->term_id, "listora_listing_cat" );
}
'
rm -f /tmp/term-helper-test.json
> /Users/varundubey/Local\ Sites/directory/app/public/wp-content/debug.log
```

## Pass criteria

- Step 1: +3 terms, +3 listings
- Step 2: +0 terms (no duplicates), +3 listings
- Step 3: Pro uses function, never references `\WBListora\Import\Term_Helper::` directly (INV-3)
- Step 4: Pro + Free produce IDENTICAL plain-text email output
- Step 5: clean debug.log

Fails any → fix at: `wb-listora-pro/includes/importexport/class-visual-importer.php` (terms), `wb-listora-pro/includes/class-email-helpers.php` (email).
