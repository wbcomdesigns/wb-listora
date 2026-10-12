---
journey: term-helper-consolidation
plugin: wb-listora
priority: high
roles: [admin, system]
covers: [term-helper-shared-implementation, email-body-formatter-shared-implementation, inv-3-pro-via-extension-functions-not-class-refs]
prerequisites:
  - "Site reachable at $SITE_URL; WP_DEBUG + WP_DEBUG_LOG enabled"
  - "Both wb-listora and wb-listora-pro active at 2.0.0 (branch) or later"
  - "WP-CLI against the same install (`wp --path=<site root>`)"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
changed: true
---

# Term_Helper + Email_Body_Formatter: Free is the canonical implementation; Pro consumes via extension functions

Single-role system journey (CLI + source greps): no frontend screen, so no viewport/theme/role ladder. Free owns `\WBListora\ImportExport\Term_Helper` (`includes/import-export/class-term-helper.php`, `set_terms()` at `:138`, `set_location_terms()` at `:198`) and `\WBListora\Workflow\Email_Body_Formatter` (`includes/workflow/class-email-body-formatter.php:24`). Pro reaches them through the documented functions `wb_listora_set_taxonomy_terms()` (`includes/import-export/import-helpers.php:45`) and `wb_listora_email_html_to_text()` (`includes/workflow/email-helpers.php:31`), never through class references (INV-3). 2.0.0 removed the importers' own `set_taxonomy_terms()` shims and `Notifications::html_to_text()`.

## Setup
```bash
W="wp --path=/Users/varundubey/Local Sites/wb-listora/app/public"
> "/Users/varundubey/Local Sites/wb-listora/app/public/wp-content/debug.log"   # or note the byte count
CATS_BEFORE=$($W eval 'echo wp_count_terms( array( "taxonomy" => "listora_listing_cat", "hide_empty" => false ) );')
```

## Steps

### 1. The shared function creates terms once
- **Action**: in a `wp eval-file` create 3 draft QA-J1- listings and call `wb_listora_set_taxonomy_terms( $id, array( 'QA-J1 Cat A' ), 'listora_listing_cat' )` for A, B, C.
- **Expect**: term count delta exactly +3, three listing-term relationships. (2026-10-12: 120 -> 123.)

### 2. Same terms again create no duplicates
- **Action**: three more listings, same three names (this is what Pro's Visual_Importer does through `set_mapped_fields()`, `wb-listora-pro/includes/importexport/class-visual-importer.php:451`).
- **Expect**: +0 terms, +3 relationships. (2026-10-12: stayed 123.)
- **Positive control**: step 1 proves the function creates a term when none exists.

### 3. INV-3: Pro routes via the function, not the class
```bash
grep -n 'wb_listora_set_taxonomy_terms' wp-content/plugins/wb-listora-pro/includes/importexport/class-visual-importer.php
grep -rnE 'Term_Helper::|Email_Body_Formatter::' wp-content/plugins/wb-listora-pro/includes --include='*.php'
```
- **Expect**: first grep >= 1 match (line 451); second grep ZERO matches (verified 2026-10-12). Any hit is an INV-3 violation.

### 4. Email body parity
- **Action**: `wp eval 'echo wb_listora_email_html_to_text( "<p>Hello <a href=\"https://example.com\">friend</a> &mdash; please visit our <strong>site</strong>.</p>" );'`
- **Expect**: `Hello friend (https://example.com) — please visit our site.` (link inlined). Pro's `\WBListoraPro\Email_Helpers::with_plain_text_fallback( $html )` (`class-email-helpers.php:30`) calls the same function and sets `AltBody` on the next `phpmailer_init`; assert by firing `wp_mail()` to Mailpit and reading the text part. (Pro no longer has its own `html_to_text()` method.)

### 5. Debug log clean
- **Action**: `grep -E "PHP (Fatal|Warning|Notice|Deprecated)" debug.log | grep -v "wp-includes\|wp-admin"`
- **Expect**: empty.

## Teardown
Delete the six QA-J1- listings and the three `QA-J1 Cat *` terms (`wp_delete_term`); the term count returns to `CATS_BEFORE`.

## Fail diagnostics
| Symptom | File |
|---|---|
| Duplicate terms on re-run | `includes/import-export/class-term-helper.php:138` `set_terms()` |
| Pro references the Free class | `wb-listora-pro/includes/importexport/class-visual-importer.php` and any `Term_Helper::` hit from step 3 |
| Plain-text body differs | `includes/workflow/class-email-body-formatter.php`, `wb-listora-pro/includes/class-email-helpers.php:30` |

## Not verified
Steps 1, 2, 3 (first grep and the second) and the function half of step 4 were run on 2026-10-12. The Mailpit AltBody assertion and step 5 (clean debug.log for this run; the site log holds unrelated historic entries) were not run.

Verified via: wp-cli, grep.
