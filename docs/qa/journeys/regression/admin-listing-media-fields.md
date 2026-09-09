---
journey: admin-listing-media-fields
plugin: wb-listora
priority: high
roles: [administrator]
covers: [10272654379, admin-field-parity, listing-fields-metabox, gallery-sanitize]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "At least 3 images in the media library"
  - "A Job listing (the type carrying both a `file` field and a `toggle` field)"
  - "Migration 1.8.0 has run (wb_listora_db_version >= 1.8.0)"
estimated_runtime_minutes: 8
---

# The listing editor's media fields must actually work

The wp-admin listing editor builds itself from a listing type's fields and renders them with the frontend submission renderer. That renderer's media controls are Interactivity API bindings, and wp-admin never loads that store — so every media control it emitted was inert.

Four separate customer-visible failures came out of that one cause: the gallery was skipped outright (empty Media box), a `file` field printed an upload zone that did nothing when clicked, `video` had no field to render at all, and a `toggle` rendered as a free-text box while the save handler stored a boolean.

Two of the fixes have their own failure modes worth guarding: the `video` field only reaches an existing site through migration 1.8.0 (field groups are frozen in term meta), and adding it made the frontend wizard render a duplicate video input for every listing type.

## Setup

- Site: `$SITE_URL`
- Admin: user 1 (autologin via `?autologin=1`)
- Create the fixture:
  ```bash
  ID=$(wp post create --post_type=listora_listing --post_title="QA media fields" --post_status=publish --porcelain)
  wp post term set $ID listora_listing_type job
  echo $ID
  ```

## Steps

### 1. Every type has a media group carrying `video`
- **Action**:
  ```bash
  wp eval '
  foreach ( get_terms( array( "taxonomy" => "listora_listing_type", "hide_empty" => false ) ) as $t ) {
      $fg = get_term_meta( $t->term_id, "_listora_field_groups", true );
      $v = false; $m = false;
      foreach ( (array) $fg as $g ) {
          if ( "media" === ( $g["key"] ?? "" ) ) { $m = true; }
          foreach ( (array) ( $g["fields"] ?? array() ) as $f ) { if ( "video" === ( $f["key"] ?? "" ) ) { $v = true; } }
      }
      printf( "%s media:%s video:%s\n", $t->slug, $m ? "y" : "N", $v ? "y" : "N" );
  }'
  ```
- **Expect**: every row `media:y video:y` — including `job`, which shipped with no media group at all
- **On fail**: migration 1.8.0 did not apply. It defers to `init` because `maybe_migrate()` runs on `plugins_loaded` 11, before the taxonomy exists. If the version is already stamped, reset and re-run: `wp option update wb_listora_db_version 1.6.0` then load any page.

### 2. The Media box renders with a gallery and a video field
- **Action**: `playwright_navigate $SITE_URL/wp-admin/post.php?post=<ID>&action=edit&autologin=1`, expand the Meta Boxes drawer, then
  ```js
  ({
    mediaBox: [...document.querySelectorAll('.postbox')].some(b => /Media/.test(b.querySelector('.hndle,h2')?.textContent || '')),
    gallery:  !!document.querySelector('[data-listora-admin-gallery]'),
    addBtn:   !!document.querySelector('[data-listora-gallery-add]'),
    video:    document.querySelector('[name="meta_video"]')?.type,
    toggle:   document.querySelector('[name="meta_position_filled"]')?.type,
    script:   !!document.querySelector('script[src*="listing-media-fields.js"]')
  })
  ```
- **Expect**: `mediaBox`, `gallery`, `addBtn`, `script` all true; `video` is `"url"`; `toggle` is `"checkbox"`
- **On fail**: `video` absent → step 1 lied or the renderer lost its case. `toggle` is `"text"` → the `toggle` case fell back to default. See `includes/submission-field-renderer.php`.

### 3. The media frame opens (this is the part that was dead)
- **Action**: click `[data-listora-gallery-add]`, then check `!!document.querySelector('.media-modal')`
- **Expect**: `true`
- **On fail**: nothing is bound to `wp.media`. Confirm `assets/js/admin/listing-media-fields.js` is enqueued with a `jquery` dependency in `class-listing-fields-metabox.php::enqueue_assets()`. Note `wp.media` itself being defined is NOT sufficient — it always is; the binding is what regressed.

### 4. Selecting images updates thumbs and the hidden input
- **Action**: select 3 attachments, confirm the frame, then
  ```js
  ({ thumbs: document.querySelectorAll('.listora-admin-gallery__thumb').length,
     value:  document.querySelector('[data-listora-gallery-input]').value })
  ```
- **Expect**: `thumbs` is 3 and `value` is a 3-id comma-separated list
- **Capture**: `GALLERY_IDS` <- that value

### 5. Save, and confirm the gallery actually persists
- **Action**: set `[name="meta_video"]` to `https://youtube.com/watch?v=qa18`, tick `[name="meta_position_filled"]`, click the editor Save button, wait 3s, then
  ```bash
  wp eval 'foreach ( array( "video", "gallery", "position_filled" ) as $k ) {
      $v = WBListora\Core\Meta_Handler::get_value( <ID>, $k );
      printf( "%s => %s\n", $k, is_array( $v ) ? json_encode( $v ) : var_export( $v, true ) );
  }'
  ```
- **Expect**: `video` is the URL, `gallery` is an array of the 3 captured ids, `position_filled` is `'1'`
- **On fail**: `gallery => []` is the specific regression — the meta box posts CSV and `Field::sanitize_id_array()` stopped accepting it. See `includes/core/class-field.php`. A single-image gallery failing while three succeed means the `is_array( $decoded )` test reverted to a null check (`json_decode('12')` returns an int).

### 6. Saved state renders back on reload
- **Action**: reload the edit screen
- **Expect**: 3 thumbnails, the hidden input equal to `GALLERY_IDS`, the video field populated, the toggle checked

### 7. Removing an image persists
- **Action**: click one `[data-listora-gallery-remove]`, save, re-read the meta
- **Expect**: `gallery` now holds exactly 2 ids, the removed one absent

### 8. A `file` field's upload button opens the frame
- **Action**: click the `.listora-submission__upload-trigger` for Company Logo
- **Expect**: `.media-modal` present. Selecting an image sets `[name="meta_company_logo"]` to that attachment id.
- **On fail**: this was never reported by QA and is easy to lose again — it shares the binding from step 3.

### 9. The FRONTEND wizard still shows exactly ONE video input
- **Action**: `playwright_navigate $SITE_URL/add-listing/`, then
  ```js
  (() => { const v = [...document.querySelectorAll('input[name="video"], input[name="meta_video"]')];
           const ids = v.map(i => i.id);
           return { total: v.length,
                    byName: v.reduce((a,i)=>{a[i.name]=(a[i.name]||0)+1;return a;},{}),
                    dupIds: ids.length !== new Set(ids).size,
                    emptyFieldsets: [...document.querySelectorAll('.listora-submission__fieldset')].filter(f=>!f.querySelector('input,select,textarea')).length }; })()
  ```
- **Expect**: `total` is 1, `byName` is `{video: 1}`, `dupIds` false, `emptyFieldsets` 0
- **On fail**: making `video` a real field made the details-step loop render it again, once per pre-rendered listing type, all sharing `id="listora-field-video"`. The renderer must skip `gallery` and `video` when `! is_admin()`. See `includes/submission-field-renderer.php` and `templates/blocks/listing-submission/step-details.php`. `emptyFieldsets > 0` is a separate, older regression (#9867347053).

### 10. Mobile
- **Action**: resize to 390x844 on the edit screen
- **Expect**: `document.documentElement.scrollWidth === 390` and the gallery remove control is >= 28px square

### 11. RTL
- **Action**: `document.documentElement.setAttribute('dir','rtl')` and measure the remove control against its thumbnail
- **Expect**: it hugs the LEFT edge in RTL and the RIGHT edge in LTR (logical properties, not `left`/`right`)

## Teardown

```bash
wp post delete <ID> --force
```

## Pass criteria

ALL of the following hold:
1. Every listing type has a media group containing `video`.
2. The Media box renders a gallery control, a `url` video input and a `checkbox` toggle.
3. The media frame opens from both the gallery button and a `file` field's upload zone.
4. Video, gallery and toggle all survive a save and reload; removal persists too.
5. The frontend wizard renders exactly one video input, no duplicate ids, no empty fieldsets.
6. No horizontal overflow at 390px; the remove control follows the writing direction.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Media box missing entirely on Job | migration did not create the group | `includes/db/class-migrator.php::add_video_field_to_types()` |
| Migration logged "completed" but changed nothing | it ran before `init`, so `get_terms()` returned WP_Error | `migrate_1_8_0()` — the `taxonomy_exists()` deferral |
| Clicking Add Photos does nothing | script not enqueued, or jQuery dep dropped | `class-listing-fields-metabox.php::enqueue_assets()` |
| Gallery saves as `[]` | CSV rejected by the sanitizer | `includes/core/class-field.php::sanitize_id_array()` |
| One-image gallery empties, three work | null check instead of `is_array()` on the decode | same |
| Two video inputs on Add Listing | frontend skip list lost `video` | `includes/submission-field-renderer.php` |

## Unit-level twin

`tests/unit/GalleryIdSanitizeTest.php` covers the CSV / JSON / array / single-id / empty cases without a browser:

```bash
composer phpunit -- --filter GalleryIdSanitizeTest
```
