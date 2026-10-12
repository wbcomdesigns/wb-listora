---
journey: submission-featured-image-drag-drop
group: submission
actor: customer
mode: browser
plugin: wb-listora
priority: normal
roles: [owner, guest, no-upload-member, admin]
covers: [BC-10208875694, featured_image, drag-and-drop, is-dragging, wp-v2-media, upload-caps]
prerequisites:
  - "Site reachable at $SITE_URL; {owner} member logged in (?autologin={owner})"
  - "{owner} has upload_files (Listora grants it to subscribers)"
  - "The cap is the SERVER limit wp_max_upload_size(); there is no Settings > Submissions max file size"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes: []
site_profile: required
---

# Dropping an image on the featured-image zone uploads it exactly like clicking to choose one

## Why
The zone says "Click to upload or drag & drop"; a drop that opens the file in the browser, skips the size/type checks, or diverges from the click path loses the owner's photo or bypasses the server cap.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). `{type}` a listing type with a media step; `$MAX` = `wp --path=$WP_PATH eval 'echo wp_max_upload_size();'`.
- Open `$SITE_URL/add-listing/?autologin={owner}`, pick `{type}`, Continue, fill `#listora-title`, `#listora-description` and a Category, Continue, fill the visible `input[name="meta_address[address]"]`, Continue: the `data-step="media"` panel shows the zone.
- Scope: `initFeaturedDropZone()` (`src/blocks/listing-submission/view.js:787`) wires `dragenter/dragover/dragleave/drop` onto `.listora-submission__upload-trigger[data-wp-context*="featured_image"]` only; the gallery "Add Photos" button (`.listora-submission__add-photos`) is not a drop target. A drop uploads through `POST /wp/v2/media` (`uploadFileViaRest()`, `view.js:1009`) and `applyPickedAttachments( 'featured_image', ... )` -> `applyFeaturedAttachment()`, the same path as the click picker. Errors are a toast (`.listora-toast--error`, `role="status"`, auto-removed ~4s): assert within a second of the drop.

## Steps

### 1. The zone highlights while dragging
- **Action**: drag an image file over the zone (Playwright: dispatch `dragenter` with a `DataTransfer`).
- **Expect**: `.is-dragging` on the trigger; cleared on `dragleave` to outside; must NOT clear when `relatedTarget` is a child of the zone.

### 2. Dropping uploads and previews
- **Action**: drop a valid JPG/PNG/WebP.
- **Expect**: `input[name="featured_image"]` gains a numeric attachment id; the trigger renders `img.listora-submission__media-preview`; the attachment exists in the Media Library owned by `{owner}`; **Replace** / **Remove** (`[data-listora-replace-media]`, `[data-listora-remove-media]` in `.listora-submission__media-actions`) become visible.

### 3. The dropped image survives submission
- **Action**: complete and submit.
- **Expect**: the listing's featured image is the dropped file (`_thumbnail_id` = the attachment id).

### 4. A non-image is refused (control pair with step 2)
- **Action**: drop a `.txt`, then a PDF, then a 0-byte file, then a double-extension `x.php.jpg` that is not an image.
- **Expect**: toast "That file is not an image. Use a JPG, PNG or WebP."; `input[name="featured_image"]` stays empty; no `POST /wp/v2/media` request.

### 5. The server size cap is enforced on this path too
- **Action**: drop an image larger than `listoraI18n.maxUploadBytes` (lower `upload_max_filesize` locally or fake `File.size`).
- **Expect**: toast "<name> is larger than the <$MAX label> this site accepts.", no upload, input unchanged. The hint under the trigger reads "Up to <label>, JPG/PNG/WebP".
- The value must come from `listoraI18n.maxUploadBytes` / `maxUploadLabel` (`includes/class-assets.php:390-391`), never a literal.

### 6. Click-to-upload reaches the same end state
- **Action**: press Enter on the trigger (Playwright `filechooser` event), pick an image.
- **Expect**: state identical to step 2.

### 7. Retry and parallel (rule 9)
- **Action**: drop the same file twice quickly; drop two files at once; drop, then drop a replacement before the first upload finishes.
- **Expect**: exactly one attachment is applied (the last completed drop wins), no orphaned duplicate attachment for the double drop, no console error. Not verified live.

### 8. Editing an existing listing
- **Action**: Dashboard > Edit on a listing with a featured image, drop a replacement.
- **Expect**: preview and hidden input update; the listing saves with the replacement.

### 9. Role ladder
| Actor | Expect |
|---|---|
| `{owner}` | steps 1-8 |
| member without `upload_files` (role stripped) | media POST returns 403; the toast shows the REST message, input unchanged |
| guest | `/add-listing/` shows sign-in; `POST /wp/v2/media` 401 |
| `{admin}` (control) | same flow |

### 10. 390px
- Zone fully visible, trigger >= 40px tall, no horizontal page scroll.

## Role ladder
See step 9.

## Pass criteria
1. Dragging highlights; a valid drop uploads, previews and survives submission.
2. Non-images and oversize files are refused with a toast and no request/state change.
3. Click and drop reach the identical end state; the cap comes from the server value.
4. Role ladder holds; 390px clean.

## Fail diagnostics
| Symptom | File |
|---|---|
| No `.is-dragging` / browser opens the file | `src/blocks/listing-submission/view.js:805-810` (dragenter and dragover must `preventDefault`) |
| Drop skips size/type checks | `view.js:830-846` (`fileTooLargeReason()`, image check) |
| Preview or input diverges from click path | `view.js:720` `applyFeaturedAttachment()`, `:939` `applyPickedAttachments()` |
| Cap hardcoded | `includes/class-assets.php:390` |
| Style missing | `blocks/listing-submission/style.css:514` `.is-dragging` |

## Not verified
Steps 2, 3, 6 (real upload, Media Library row, Enter-to-chooser), 7, 8 and 10 were not run. Steps 1, 4, 5 ran with synthetic DragEvents (Chromium, 1440) on 2026-10-12; a real OS drag was not performed.

## Cleanup
Delete attachments uploaded during the run and the probe listing; restore any lowered upload limit.
