---
journey: listing-media-fields-and-retention
plugin: wb-listora
priority: critical
roles: [administrator, moderator, subscriber, guest]
covers: [admin-field-parity, listing-fields-metabox, gallery-sanitize, media-helpers, media-kept-on-others-save, deleted-attachments-render, migration-1.8.0, frontend-single-video-input, bc-10350135619, bc-10272654379, card-10331918084, card-10331922348]
prerequisites:
  - "Site reachable at $SITE_URL; {admin} autologin; at least 3 images in the media library"
  - "A Job listing fixture (the type carrying both a `file` field and a `toggle` field) created in Setup; a member-owned listing {listing} whose featured image and gallery were uploaded by {admin}; a user with the Listora Moderator role {moderator}"
  - "Migration 1.8.0 has run (`wb_listora_db_version` >= 1.8.0)"
estimated_runtime_minutes: 12
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default, buddyx-pro light]
site_profile: required
supersedes:
  - "ora:regression/admin-listing-media-fields.md"
  - "ora:regression/listing-media-kept-on-others-save.md (steps 1 and 5; steps 2-3 live in customer/17-edit-my-listing.md)"
fails_on: [46ba223e]
---

# The listing editor's media fields work, survive a save by someone else, and a gallery with deleted files paints nothing

## Why
The wp-admin listing editor renders fields with the frontend renderer whose media controls are Interactivity API bindings that wp-admin never loads, so the gallery, file upload, video and toggle were inert; a moderator Update or a member edit wiped photos they did not upload; deleted attachments left broken slides.

Two of the fixes have their own failure modes: the `video` field reaches an existing site only through migration 1.8.0 (field groups are frozen in term meta), and adding it made the frontend wizard render a duplicate video input for every listing type.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
  ```bash
  ID=$(wp post create --post_type=listora_listing --post_title="QA media fields" --post_status=publish --porcelain)
  wp post term set $ID listora_listing_type job
  ```
- Teardown: `wp post delete $ID --force`.

## Steps

### 1. Every type has a media group carrying `video`
- **Action**:
  ```bash
  wp eval 'foreach ( get_terms( array( "taxonomy" => "listora_listing_type", "hide_empty" => false ) ) as $t ) {
    $fg = get_term_meta( $t->term_id, "_listora_field_groups", true ); $v = false; $m = false;
    foreach ( (array) $fg as $g ) { if ( "media" === ( $g["key"] ?? "" ) ) { $m = true; }
      foreach ( (array) ( $g["fields"] ?? array() ) as $f ) { if ( "video" === ( $f["key"] ?? "" ) ) { $v = true; } } }
    printf( "%s media:%s video:%s\n", $t->slug, $m ? "y" : "N", $v ? "y" : "N" ); }'
  ```
- **Expect**: every row `media:y video:y`, including `job`, which shipped with no media group.
- **On fail**: migration 1.8.0 did not apply; it defers to `init` because `maybe_migrate()` runs on `plugins_loaded` 11, before the taxonomy exists (`includes/db/class-migrator.php:214-226`, `:244`). If the version is already stamped: `wp option update wb_listora_db_version 1.6.0` then load any page.

### 2. The Media box renders a gallery, a video field and a toggle
- **Action**: open `post.php?post=$ID&action=edit` as {admin}, expand the Meta Boxes drawer (boxes `#wb_listora_fields_media`, `#wb_listora_fields_company`, `#wb_listora_fields_job_details`); evaluate
  `({mediaBox:[...document.querySelectorAll('.postbox')].some(b=>/Media/.test(b.querySelector('.hndle,h2')?.textContent||'')), gallery:!!document.querySelector('[data-listora-admin-gallery]'), addBtn:!!document.querySelector('[data-listora-gallery-add]'), video:document.querySelector('[name="meta_video"]')?.type, toggle:document.querySelector('[name="meta_position_filled"]')?.type, script:!!document.querySelector('script[src*="listing-media-fields.js"]')})`.
- **Expect**: `mediaBox`, `gallery`, `addBtn`, `script` true; `video` is `"url"` (`#listora-field-video`); `toggle` is `"checkbox"` (`#listora-field-position_filled`).
- **On fail**: `video` absent: step 1 lied or the renderer lost its case; `toggle` `"text"`: the `toggle` case fell back to default (`includes/submission-field-renderer.php`).

### 3. The media frame opens (the part that was dead)
- **Action**: click `[data-listora-gallery-add]`; check `!!document.querySelector('.media-modal')`.
- **Expect**: `true`.
- **On fail**: nothing bound to `wp.media`; confirm `assets/js/admin/listing-media-fields.js` is enqueued with a `jquery` dependency (`includes/admin/class-listing-fields-metabox.php:379-410`). `wp.media` being defined is NOT sufficient.

### 4. Selecting images updates thumbs and the hidden input
- **Action**: select 3 attachments, confirm; `({thumbs:document.querySelectorAll('.listora-admin-gallery__thumb').length, value:document.querySelector('[data-listora-gallery-input]').value})`.
- **Expect**: `thumbs` 3 and `value` a 3-id comma-separated list. Capture `GALLERY_IDS`.

### 5. Save, and confirm the gallery persists
- **Action**: set `[name="meta_video"]` to `https://youtube.com/watch?v=qa18`, tick `[name="meta_position_filled"]`, click Save, wait 3 s, then
  `wp eval 'foreach ( array( "video","gallery","position_filled" ) as $k ) { $v = WBListora\Core\Meta_Handler::get_value( <ID>, $k ); printf( "%s => %s\n", $k, is_array($v) ? json_encode($v) : var_export($v,true) ); }'`.
- **Expect**: `video` is the URL, `gallery` an array of the 3 ids, `position_filled` `'1'`.
- **On fail**: `gallery => []`: the meta box posts CSV and `Field::sanitize_id_array()` stopped accepting it (`includes/core/class-field.php:676`); a one-image gallery failing while three succeed means a null check replaced `is_array( $decoded )` (`json_decode('12')` returns an int).

### 6. Saved state renders back, and removing persists
- **Action**: reload the edit screen; click one `[data-listora-gallery-remove]`, save, re-read the meta.
- **Expect**: 3 thumbnails, hidden input = `GALLERY_IDS`, video populated, toggle checked; after remove `gallery` holds exactly 2 ids, the removed one absent.

### 7. A `file` field's upload button opens the frame
- **Action**: click `.listora-submission__upload-trigger` for Company Logo.
- **Expect**: `.media-modal` present; selecting an image sets `[name="meta_company_logo"]` to that attachment id.
- **On fail**: shares the binding from step 3; never reported by QA and easy to lose.

### 8. A moderator's Update keeps the gallery
- **Action**: as {moderator} open `post.php?post=<{listing} id>&action=edit` for a listing whose gallery {admin} uploaded; change nothing in Media; Update.
- **Expect**: `_listora_gallery`, `_thumbnail_id` and file fields (e.g. Company Logo) byte-identical (`wp post meta get <id> _listora_gallery`).
- **On fail**: `class-listing-fields-metabox.php:140,162` (`save_post()` must call `wb_listora_keep_listing_media()` before the field loop). Not verified live.

### 9. A gallery whose attachments were deleted paints nothing (card 10350135619)
- **Action**: listing G has `_listora_gallery` = 3 attachment ids (a, b, c). As {admin} delete a and b permanently; load G as {guest} at 1440 and 390; repeat after also deleting c.
- **Expect**: after a and b: the carousel shows 1 slide, label "View all photos (1)", every `.listora-detail__gallery img` has a non-empty `src` returning 200; no slide renders an empty or broken image. After c: no gallery block, no "View all photos" text, no `img[src=""]`, no carousel controls.
- **Read-back**: `get_post_meta( G, '_listora_gallery', true )` still lists the 3 ids (render filters, meta untouched), and the `img` count equals the count of ids whose `get_post_status` is `inherit`; negative control: the untouched twin keeps its 3 slides.
- **fails_on**: 46ba223e (parent of 66994034). Not verified live.

### 10. The FRONTEND wizard still shows exactly ONE video input
- **Action**: as a member open Add Listing; evaluate
  `(()=>{const v=[...document.querySelectorAll('input[name="video"], input[name="meta_video"]')]; const ids=v.map(i=>i.id); return {total:v.length, byName:v.reduce((a,i)=>{a[i.name]=(a[i.name]||0)+1;return a;},{}), dupIds: ids.length!==new Set(ids).size, emptyFieldsets:[...document.querySelectorAll('.listora-submission__fieldset')].filter(f=>!f.querySelector('input,select,textarea')).length};})()`.
- **Expect**: `total` 1, `byName` `{video:1}`, `dupIds` false (id `listora-video`), `emptyFieldsets` 0.
- **On fail**: the renderer must skip `gallery` and `video` when `! is_admin()` (`submission-field-renderer.php:89`, `step-details.php`); `emptyFieldsets > 0` is a separate regression (`customer/submission-step-rendering-invariants.md`).

### 11. Mobile and RTL
- **Action**: resize to 390x844 on the edit screen; then `document.documentElement.setAttribute('dir','rtl')` and measure the remove control against its thumbnail.
- **Expect**: `scrollWidth === 390`, remove control >= 28px square; it hugs the LEFT edge in RTL and the RIGHT edge in LTR (logical properties).

## Role ladder
| Rung | Expect |
|---|---|
| {admin} | steps 1-7 (the actor) |
| {moderator} / editor | same Media box (control); step 8 keeps the gallery |
| subscriber owner | edits the fields from the frontend wizard (step 10 for the video input; photo retention in `customer/17-edit-my-listing.md`); cannot open `post.php` (403) |
| {guest} | step 9 public rendering |

## Pass criteria
1. Every listing type has a media group containing `video`.
2. The Media box renders a gallery control, a `url` video input and a `checkbox` toggle; the media frame opens from the gallery button and a `file` field.
3. Video, gallery and toggle survive a save and reload; removal persists; a moderator Update keeps media.
4. Deleted attachments never paint a broken slide.
5. The frontend wizard renders exactly one video input; no overflow at 390; remove control follows the writing direction.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Media box missing on Job | migration did not create the group | `includes/db/class-migrator.php:244` `add_video_field_to_types()` |
| Migration "completed" but changed nothing | ran before `init` | `class-migrator.php:214-226` `migrate_1_8_0()` |
| Add Photos does nothing | script not enqueued / jQuery dep dropped | `class-listing-fields-metabox.php:379-410` |
| Gallery saves as `[]` | CSV rejected | `includes/core/class-field.php:676` |
| One-image gallery empties | null check on decode | same |
| Two video inputs on Add Listing | frontend skip list lost `video` | `submission-field-renderer.php:89` |
| Moderator Update empties gallery | keep-media call missing | `class-listing-fields-metabox.php:140,162` |

## Not verified
- Steps 3-8, 11 are interactive and were not run; markers, field types, migration state (all types media:y video:y) and the single frontend video input were checked live by curl and wp eval. Unit twin: `composer phpunit -- --filter GalleryIdSanitizeTest`.

## Cleanup
- `wp post delete $ID --force` for the "QA media fields" listing and for listing G of step 9.
- Delete the attachments added to the gallery: `wp post delete <GALLERY_IDS> --force`; the attachments deleted in step 9 are already gone.
- If step 1 forced a re-migration, restore `wb_listora_db_version` to its prior value (`wp option update wb_listora_db_version <value>`).
- Reset the viewport and remove the `dir="rtl"` attribute set in step 11 by reloading the page.
