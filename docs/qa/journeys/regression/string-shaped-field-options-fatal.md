---
journey: string-shaped-field-options-fatal
plugin: wb-listora
priority: critical
roles: [member, admin]
covers: [submission-field-renderer, field-options-normalization, type-editor-options, search-filters-render]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Add Listing page exists (listing-submission block)"
  - "WP-CLI access for the corrupted-shape injection"
estimated_runtime_minutes: 4
---

# String-shaped field options must not fatal the submission form

Live-site fatal (Wordfence report, WB Listora 1.4.0, PHP 8.4):
`TypeError: Cannot access offset of type string on string` at
`includes/submission-field-renderer.php:204`. Root cause: the admin Type
Editor (pre-1.4.1 `assets/js/admin/type-editor.js`) persisted owner-added
select/radio/multiselect options as **plain strings** inside
`_listora_field_groups` term meta, while every PHP reader assumes the
`{ value, label }` array shape. On PHP 8 the string offset access throws
and the public submission page 500s. Fix is three-layer:
`Field::normalize_options()` in the constructor (heals already-corrupted
sites on read), save-path normalization in
`Listing_Type_Registry::create_type_from_data()`, and the Type Editor JS
always writing the object shape.

## Setup

- Site: `$SITE_URL`
- Submission page: `$SITE_URL/add-listing/` (or the configured submit page)
- Baseline debug.log byte count.

## Steps

### 1. Inject the corrupted shape (simulates pre-1.4.1 stored data)
- **Action**: `wp eval` —
  ```php
  $reg = \WBListora\Core\Listing_Type_Registry::instance();
  $type = $reg->get( 'business' );
  $groups = array_map( function( $g ) { return $g->to_array(); }, $type->get_field_groups() );
  $groups[0]['fields'][] = array(
      'key' => 'qa_string_opts', 'label' => 'QA String Opts', 'type' => 'multiselect',
      'options' => array( 'Alpha', 'Beta' ),
  );
  $props = $type->to_array(); unset( $props['field_groups'] );
  $reg->save_type( 'business', array( 'props' => $props, 'field_groups' => $groups ) );
  ```
- **Note**: post-1.4.1 the save path normalizes on write, so the stored meta
  will already be object-shaped — that alone is a PASS signal for the save
  layer. To test the READ layer in isolation, write the term meta directly:
  ```php
  $term = get_term_by( 'slug', 'business', 'listora_listing_type' );
  $raw  = get_term_meta( $term->term_id, '_listora_field_groups', true );
  foreach ( $raw as &$g ) { foreach ( $g['fields'] as &$f ) {
      if ( 'qa_string_opts' === $f['key'] ) { $f['options'] = array( 'Alpha', 'Beta' ); }
  } }
  update_term_meta( $term->term_id, '_listora_field_groups', $raw );
  wp_cache_flush();
  ```

### 2. Load the submission page logged in
- **Action**: `playwright_navigate $SITE_URL/add-listing/?autologin=1`
- **Expect**:
  - HTTP 200 (pre-fix: 500)
  - `input[name="meta_qa_string_opts[]"]` checkboxes exist with values
    `alpha` / `beta` and labels `Alpha` / `Beta`
- **On fail**: regression of the 1.4.1 options-normalization fix. Check
  `Field::__construct()` calls `Field::normalize_options()`.

### 3. Verify zero fatals
- **Action**: tail debug.log diff since baseline
- **Expect**: ZERO new entries containing `Cannot access offset` OR `Fatal`

### 4. Type Editor writes canonical shape
- **Action**: wp-admin → Listing Types → edit Business → Edit the
  `qa_string_opts` field → Add Option → type `Gamma Ray` → Save
- **Expect**: raw `_listora_field_groups` term meta for that field contains
  `array( 'value' => 'gamma-ray', 'label' => 'Gamma Ray' )` — no plain
  strings anywhere in any field's `options`.

### 5. Search filter surface (same reader family)
- **Action**: mark the field `filterable`, load the listing-search page
- **Expect**: filter renders options without fatal (filters.php reads the
  same normalized shape via `Field::get('options')`).

### 6. Cleanup
- **Action**: remove the `qa_string_opts` field from the Business type via
  the Type Editor (delete field → Save).
