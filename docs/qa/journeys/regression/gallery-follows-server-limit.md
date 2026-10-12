---
journey: gallery-follows-server-limit
plugin: wb-listora
priority: high
roles: [subscriber, anonymous, admin]
covers: [10360671784, photo-upload, submission]
prerequisites:
  - "Site reachable at http://wb-listora.local"
  - "Three test JPEGs: 370 KB, 8 MB, 27 MB (generate with ImageMagick under ~/work-artifacts/scratch/)"
  - "A scratch mu-plugin `add_filter( 'upload_size_limit', fn() => 10 * MB_IN_BYTES );` (delete afterwards)"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Gallery uploads follow the server limit and keep the photos that fit

There is no Listora size cap: the limit is `wp_max_upload_size()`, passed to the form as `listoraI18n.maxUploadBytes` / `maxUploadLabel` (`includes/class-assets.php:390-391`). `src/blocks/listing-submission/view.js:1049` `fileTooLargeReason()` refuses oversized files BEFORE upload, naming the file; the rest upload sequentially with `role="status"` text "Uploading photo %1$d of %2$d…" (`view.js:1166`); refusals show in `.listora-form__error[data-listora-upload-error][role="alert"]` under the uploader (`view.js:909-915`). Settings > Submissions shows "Max file size" read-only (`includes/admin/class-settings-page.php:1079`).

Verified via: source read; the upload run itself needs a browser and large files and was NOT run (shared Playwright pane busy). Static: step-media hint string present.

## Steps

### 1. Mixed pick on a 10 MB server
- **Action**: mu-plugin active; as subscriber on `/add-listing/` at 390, reach "Photos & Media" (`data-step="media"`), click "Add Photos" and pick the 370 KB, 8 MB and 27 MB files together.
- **Expect**: status line "Uploading photo 1 of 2…" then 2 thumbnails; exactly 2 `POST /wp-json/wp/v2/media`; a red inline `role="alert"` under the gallery naming the 27 MB file and "10 MB" ("<file> is larger than the 10 MB this site accepts."), still visible after scrolling.

### 2. POSITIVE CONTROL: under the limit
- **Action**: pick only the 8 MB file.
- **Expect**: uploads (5-10 MB phone photos work); no alert. (The old 5 MB Listora cap would have refused it.)

### 3. Featured image
- **Action**: pick the 27 MB file for Featured Image.
- **Expect**: same inline refusal, no upload request; the hint reads "Up to 10 MB, JPG/PNG/WebP" (`templates/blocks/listing-submission/step-media.php:58`).

### 4. Settings
- **Action**: Listora > Settings > Submissions (admin).
- **Expect**: "Max file size" shows the server limit in `<strong>`, no input; "Max gallery images" is a number input.

### 5. No orphans
- **Action**: `SELECT COUNT(*) FROM wp_posts WHERE post_type='attachment' AND post_author=<subscriber>` before/after.
- **Expect**: +2 after step 1, not +3.

### 6. Role ladder
- Subscriber (member who can submit) steps 1-3. Another member same. Anonymous gets the login prompt on `/add-listing/`. Editor/admin same limit (a limit is not role-based). Admin only for step 4.

### 7. 390px
- **Expect**: Add Photos button >= 40px, error readable, no horizontal scroll.

## Pass criteria

1. Files under the server limit upload, including 5-10 MB phone photos.
2. Oversized files are refused before upload, by name, inline.
3. No orphan attachments from refused files.

## Fail diagnostics

| Symptom | File |
|---|---|
| Old cap still enforced | `src/blocks/listing-submission/view.js:1049`, `includes/class-assets.php:390` |
| Error toast vanishes | `view.js:909-915` inline alert element |
| Settings shows an input | `includes/admin/class-settings-page.php:1079` |
