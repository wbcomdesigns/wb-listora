---
journey: term-helper-consolidation
plugin: wb-listora
priority: high
roles: [admin, system]
covers: [term-helper-shared-implementation, email-body-formatter-shared-implementation, inv-3-pro-via-extension-functions-not-class-refs]
prerequisites:
  - "Site reachable at $SITE_URL; WP_DEBUG + WP_DEBUG_LOG enabled; WP-CLI against the same install"
  - "wb-listora and wb-listora-pro active at the same release (Pro half of steps 3-4)"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
themes: []
supersedes: []
requires: [wb-listora-pro]
site_profile: required
---

# Term_Helper and Email_Body_Formatter: Free is the canonical implementation; Pro consumes via extension functions

## Why
Two copies of term-setting or html-to-text drift (duplicate terms, differently formatted plain-text mail). Free owns `\WBListora\ImportExport\Term_Helper` (`includes/import-export/class-term-helper.php`, `set_terms()` `:138`, `set_location_terms()` `:198`) and `\WBListora\Workflow\Email_Body_Formatter` (`includes/workflow/class-email-body-formatter.php:24`); Pro reaches them only through `wb_listora_set_taxonomy_terms()` (`includes/import-export/import-helpers.php:45`) and `wb_listora_email_html_to_text()` (`includes/workflow/email-helpers.php:31`), never class references (INV-3).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). CLI-only system journey: no viewport, theme or UI role ladder.
- ```bash
  W="wp --path=$WP_PATH"
  LOG="$WP_PATH/wp-content/debug.log"; : > "$LOG"      # or note the byte count
  CATS_BEFORE=$($W eval 'echo wp_count_terms( array( "taxonomy" => "listora_listing_cat", "hide_empty" => false ) );')
  ```

## Steps

### 1. The shared function creates terms once
- **Action**: in a `wp eval-file` create 3 draft QA listings and call `wb_listora_set_taxonomy_terms( $id, array( 'QA Cat A' ), 'listora_listing_cat' )` for A, B, C.
- **Expect**: term count delta exactly +3, three listing-term relationships.

### 2. Same terms again create no duplicates
- **Action**: three more listings, same three names (what Pro's `Visual_Importer::set_mapped_fields()` does, `wb-listora-pro/includes/importexport/class-visual-importer.php:451`).
- **Expect**: +0 terms, +3 relationships. Positive control: step 1 proves the function creates a term when none exists.

### 3. INV-3: Pro routes via the function, not the class
```bash
grep -n 'wb_listora_set_taxonomy_terms' $PRO_PATH/includes/importexport/class-visual-importer.php
grep -rnE 'Term_Helper::|Email_Body_Formatter::' $PRO_PATH/includes --include='*.php'
```
- **Expect**: first grep >= 1 match; second grep ZERO matches. Any hit is an INV-3 violation.

### 4. Email body parity
- **Action**: `wp eval 'echo wb_listora_email_html_to_text( "<p>Hello <a href=\"https://example.com\">friend</a> &mdash; please visit our <strong>site</strong>.</p>" );'`
- **Expect**: `Hello friend (https://example.com) [em-dash] please visit our site.` (link inlined; `[em-dash]` stands for the literal U+2014 glyph, preserved). Pro's `\WBListoraPro\Email_Helpers::with_plain_text_fallback( $html )` (`class-email-helpers.php:30`) calls the same function and sets `AltBody` on the next `phpmailer_init`; assert by firing `wp_mail()` to Mailpit and reading the text part. Pro has no `html_to_text()` of its own.
- **Boundary**: empty string, only whitespace, nested lists, `<script>` content, 100 KB body: no notice, no fatal, script content not emitted as text.

### 5. Debug log clean
- **Action**: `grep -E "PHP (Fatal|Warning|Notice|Deprecated)" $LOG | grep -v "wp-includes\|wp-admin"`
- **Expect**: empty.

## Role ladder
Single-role system journey (WP-CLI as the site user); no frontend role ladder applies.

## Pass criteria
1. Term count delta +3 then +0; relationships match.
2. Pro never references `Term_Helper::` or `Email_Body_Formatter::`.
3. Plain-text mail body is produced by Free's formatter; debug log clean.

## Fail diagnostics
| Symptom | File |
|---|---|
| Duplicate terms on re-run | `includes/import-export/class-term-helper.php:138` |
| Pro references the Free class | `class-visual-importer.php` and any `Term_Helper::` hit from step 3 |
| Plain-text body differs | `includes/workflow/class-email-body-formatter.php`, `wb-listora-pro/includes/class-email-helpers.php:30` |

## Not verified
The Mailpit AltBody assertion, step 4 boundaries and step 5 for this run were not executed (the site log holds unrelated historic entries).

## Cleanup
Delete the six QA listings and the three `QA Cat *` terms (`wp_delete_term`); the term count returns to `CATS_BEFORE`.
