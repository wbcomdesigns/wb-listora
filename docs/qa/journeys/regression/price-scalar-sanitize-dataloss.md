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
---

# A submitted price must survive the save and prefill on edit

Basecamp 10171941201. Reported as a cosmetic PHP warning
(`Array to string conversion` at `includes/submission-field-renderer.php:264`);
the warning was the visible tail of **silent data loss**.

`price` was mapped to `Field::sanitize_json()`. Both the submission form and
the wp-admin fields metabox render price as a single `<input type="number">`,
so the value arriving at the sanitizer is a bare scalar. `json_decode( "275" )`
returns `int 275`, fails the `is_array()` test, and the sanitizer returned
`array()` — **every price save discarded the amount**. The edit form then
rendered the empty array as `value="Array"` and raised the warning.

Fixed by a dedicated `Field::sanitize_price()` that promotes a scalar to the
documented `{ amount, currency }` object shape, plus an `is_scalar()` guard in
the renderer so no stored shape can warn again.

**Note on old data:** amounts destroyed by pre-1.4.2 saves are not
recoverable — the number never reached the database. This journey guards the
forward path only.

## Setup

- Site: `$SITE_URL`
- Submission page: `$SITE_URL/add-listing/`
- Baseline debug.log byte count.

## Steps

### 1. Sanitizer round-trip (the core contract)
- **Action**: `wp eval` —
  ```php
  $f = new \WBListora\Core\Field( array( 'key' => 'price', 'type' => 'price', 'label' => 'Price' ) );
  $c = $f->get_sanitize_callback();
  var_export( call_user_func( $c, '275' ) );
  var_export( call_user_func( $c, array( 'amount' => 450, 'currency' => 'EUR' ) ) );
  var_export( call_user_func( $c, '' ) );
  ```
- **Expect**:
  - `'275'` → `array( 'amount' => 275.0, 'currency' => '<site currency>' )` (pre-fix: `array()`)
  - canonical array → preserved, `currency` `EUR` intact
  - `''` → `array()` (a cleared price stays cleared)
- **On fail**: `price` has been remapped to `sanitize_json()` — check the
  callback map in `Field::get_sanitize_callback()`.

### 2. Submit a price through the frontend form
- **Action**: `playwright_navigate $SITE_URL/add-listing/?autologin=1`, choose a
  type carrying a price field, fill the required fields, enter `275` in Price,
  complete the wizard and submit.
- **Expect**: submission succeeds; then
  `wp post meta get <new_id> _listora_price` returns an array with
  `amount => 275`, NOT `a:0:{}`.

### 3. Edit the listing — the price prefills
- **Action**: `playwright_navigate "$SITE_URL/add-listing/?action=edit&id=<new_id>&autologin=1"`
- **Expect**:
  - the Price input carries `value="275"`
  - it does NOT carry `value="Array"`
- **On fail**: the renderer's `amount` branch regressed.

### 4. Pre-fix corrupted rows render clean (renderer guard)
- **Action**: `wp eval 'update_post_meta( <id>, "_listora_price", array() );'`
  then reload the edit form.
- **Expect**: Price input renders **empty** — no `value="Array"`, and no
  `Array to string conversion` in debug.log.
- **On fail**: the `is_scalar()` guard at the renderer's price branch is gone.

### 5. wp-admin metabox uses the same sanitizer
- **Action**: wp-admin → edit the listing → set Price to `310` → Update.
- **Expect**: `_listora_price` stores `amount => 310`. (The metabox calls
  `get_sanitize_callback()`, so it shared the identical data loss pre-fix.)

### 6. Verify zero warnings
- **Action**: tail debug.log diff since baseline.
- **Expect**: ZERO new entries containing `Array to string conversion`.

### 7. Cleanup
- **Action**: `wp post delete <new_id> --force`
