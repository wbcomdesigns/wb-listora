---
journey: price-scalar-sanitize-dataloss
plugin: wb-listora
priority: critical
roles: [member, admin]
covers: [field-sanitize-price, submission-field-renderer, listing-fields-metabox, submission-controller]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Add Listing page exists (listing-submission block)"
  - "A listing type exposing a `price` field (classified / real-estate)"
  - "WP-CLI access"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# A submitted price must survive the save and prefill on edit

Basecamp 10171941201. `price` was mapped to the JSON sanitizer; the form posts a bare scalar so every save returned `array()`
(silent data loss) and the edit form rendered `value="Array"`. Fixed by `Field::sanitize_price()`
(`includes/core/class-field.php:474`, mapped at `:294`) and the `is_scalar()` guard in the renderer
(`includes/submission-field-renderer.php:275-290`). Old destroyed amounts are not recoverable; this guards the forward path.

Role ladder: the member owner saves and edits; an admin can edit any listing (control: same prefill); logged out is redirected
(no edit form). Add Listing URL: `wb_listora_get_page_url('submission')` (`$SITE_URL/add-listing/` here); edit form is
`?action=edit&id=<id>` (or `?edit=<id>`), only for users passing `wb_listora_user_can_edit_listing()`.

## Steps

### 1. Sanitizer round-trip (verified)
- **Action**: `wp eval` -
  ```php
  $f = new \WBListora\Core\Field( array( 'key' => 'price', 'type' => 'price', 'label' => 'Price' ) );
  $c = $f->get_sanitize_callback();
  var_export( call_user_func( $c, '275' ) );
  var_export( call_user_func( $c, array( 'amount' => 450, 'currency' => 'EUR' ) ) );
  var_export( call_user_func( $c, '' ) );
  ```
- **Expect**: `'275'` -> `array('amount' => 275.0, 'currency' => '<site currency, USD here>')`; the canonical array is preserved
  (`EUR` intact); `''` -> `array()` (a cleared price stays cleared).

### 2. Submit a price through the frontend form
- **Action**: as a member, `$SITE_URL/add-listing/`, choose Classified or Real Estate, fill required fields, enter `275` in
  **Price** (`#listora-field-price`, `name="meta_price"`, `type=number`, `step=0.01`), submit.
- **Expect**: success; `wp post meta get <id> _listora_price` returns `amount => 275`, NOT `a:0:{}`.

### 3. Edit the listing: the price prefills (verified)
- **Action**: open `$SITE_URL/add-listing/?action=edit&id=<id>` as the owner (and as admin).
- **Expect**: `#listora-field-price` carries `value="275"` (verified on listing 311: `value="6500"`), never `value="Array"`.

### 4. Pre-fix corrupted rows render clean (verified)
- **Action**: `update_post_meta( <id>, '_listora_price', array() )`, reload the edit form.
- **Expect**: the input renders with NO `value` attribute (empty); no `Array to string conversion` in `wp-content/debug.log`.

### 5. wp-admin metabox uses the same sanitizer
- **Action**: wp-admin > edit the listing > set Price `310` > Update.
- **Expect**: `_listora_price` stores `amount => 310`.

### 6. Verify zero warnings
- **Action**: diff `wp-content/debug.log` since a baseline byte count.
- **Expect**: no new `Array to string conversion` from `submission-field-renderer.php`.
  (Note 2026-10-12: one unrelated warning exists at `includes/search/class-search-engine.php:1513`, see report.)

### 7. Cleanup
`wp post delete <new_id> --force`.

## Fail diagnostics
- `price` mapped back to `sanitize_json()`: `includes/core/class-field.php:294`.
- `value="Array"` back: `includes/submission-field-renderer.php:275` price branch.

## Not verified
Steps 2 and 5 (actual form submit and metabox save in a browser) were not run; 390px layout not checked.
