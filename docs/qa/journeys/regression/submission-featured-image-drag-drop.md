---
journey: submission-featured-image-drag-drop
plugin: wb-listora
roles: [member, logged-out, admin]
priority: normal
covers: [BC-10208875694, featured_image, drag-and-drop, is-dragging, wp/v2/media, upload-caps]
prerequisites:
  - "Site reachable at $SITE_URL; member logged in (?autologin=QA-J1-member)"
  - "Member has upload_files (Listora grants it to subscribers)"
  - "The cap is the SERVER limit: `wp_max_upload_size()` (this site 300 MB). 2.0.0 removed the `max_upload_size` setting; there is no Settings > Submissions max file size any more"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Dropping an image on the featured-image zone actually uploads it

The zone's label reads "Click to upload or drag & drop" and `initFeaturedDropZone()` (`src/blocks/listing-submission/view.js:787`) wires `dragenter`/`dragover`/`dragleave`/`drop` onto `.listora-submission__upload-trigger[data-wp-context*="featured_image"]`. Scope is the featured-image zone only; the gallery is the "Add Photos" button (`.listora-submission__add-photos`), not a drop target.

A dropped image uploads through `POST /wp/v2/media` (`uploadFileViaRest()`, `view.js:1009`) and goes through `applyPickedAttachments( 'featured_image', ... )` -> `applyFeaturedAttachment()`, the same path the click picker uses. Errors show as a toast (`.listora-toast--error`, `role="status"`, auto-removed after ~4s), so assert within a second of the drop.

## Setup
Open `$SITE_URL/add-listing/?autologin=QA-J1-member`, pick **Restaurant**, Continue, fill `#listora-title`, `#listora-description` and a Category, Continue, fill the visible `input[name="meta_address[address]"]`, Continue: the `data-step="media"` panel shows the zone.

## Steps

### 1. The zone highlights while dragging
- **Action**: drag an image file over the zone (Playwright: dispatch `dragenter` with a `DataTransfer`).
- **Expect**: `.is-dragging` on the trigger; cleared on `dragleave` to outside (verified 2026-10-12 with synthetic events: true then false). It must not clear when `relatedTarget` is a child of the zone.

### 2. Dropping uploads and previews
- **Action**: drop a valid JPG/PNG/WebP.
- **Expect**: `input[name="featured_image"]` gains a numeric attachment id; the trigger renders `img.listora-submission__media-preview`; the attachment exists in the Media Library owned by the member; the **Replace** / **Remove** buttons (`[data-listora-replace-media]`, `[data-listora-remove-media]` inside `.listora-submission__media-actions`) become visible.

### 3. The dropped image survives submission
- **Action**: complete and submit. **Expect** the listing's featured image is the dropped file.

### 4. A non-image is refused (positive-control pair with step 2)
- **Action**: drop a `.txt` (or PDF).
- **Expect**: toast "That file is not an image. Use a JPG, PNG or WebP."; `input[name="featured_image"]` stays empty; no `POST /wp/v2/media` request. Verified 2026-10-12.

### 5. The server size cap is enforced on this path too
- **Action**: drop an image larger than `listoraI18n.maxUploadBytes` (300 MB here; lower `upload_max_filesize` locally or fake `File.size` as done on 2026-10-12).
- **Expect**: toast "huge.jpg is larger than the 300 MB this site accepts." (`%1$s is larger than the %2$s this site accepts.`), no upload, input unchanged. The hint under the trigger reads "Up to 300 MB, JPG/PNG/WebP".
- A drop path with looser limits is a way around the cap; the value must come from `listoraI18n.maxUploadBytes`/`maxUploadLabel` (`includes/class-assets.php:390-391`), never a literal.

### 6. Click-to-upload reaches the same end state
- **Action**: press Enter on the trigger (opens the OS file chooser; Playwright `filechooser` event), pick an image.
- **Expect**: identical state to step 2.

### 7. Editing an existing listing
- **Action**: Dashboard > Edit on a listing with a featured image, drop a replacement.
- **Expect**: preview and hidden input update; the listing saves with the replacement.

### 8. Role ladder
| Actor | Expect |
|---|---|
| Member (owner) | steps 1-7 |
| Member without `upload_files` (e.g. role stripped) | media POST returns 403; the error toast reads the REST message, input unchanged |
| Logged out | `/add-listing/` shows sign-in; `POST /wp/v2/media` 401 |
| Admin | control (same flow) |

### 9. 390px
Zone fully visible, trigger >= 40px tall, no horizontal page scroll.

## Cleanup
Delete attachments uploaded during the run and the probe listing.

## Fail diagnostics
| Symptom | File |
|---|---|
| No `.is-dragging` / browser opens the file | `view.js:805-810` (both dragenter and dragover must `preventDefault`) |
| Drop skips size/type checks | `view.js:830-846` (`fileTooLargeReason()`, image check) |
| Preview or input diverges from the click path | `view.js:720` `applyFeaturedAttachment()`, `view.js:939` `applyPickedAttachments()` |
| Cap hardcoded | `includes/class-assets.php:390` |
| Style missing | `blocks/listing-submission/style.css:514` `.is-dragging` |

## Not verified
Steps 2, 3, 6 (real upload, Media Library row, Enter-to-chooser then pick), 7 and 9 were not run. Steps 1, 4, 5 were run with synthetic DragEvents in Playwright (Chromium, 1440) on 2026-10-12; a real OS drag was not performed.

Verified via: Playwright MCP (synthetic DragEvent), source.
