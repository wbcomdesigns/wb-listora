---
journey: submission-featured-image-drag-drop
plugin: wb-listora
roles: [member]
priority: normal
covers: [BC-10208875694, featured_image, drag-and-drop, is-dragging, wp/v2/media, upload-caps]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Auto-login mu-plugin present (?autologin=1)"
  - "Test member has upload_files (Listora grants this to subscribers)"
  - "Settings → Submissions → Max file size known (5 MB by default)"
estimated_runtime_minutes: 5
---

# Dropping an image on the featured-image zone actually uploads it

The zone's label has always read **"Click to upload or drag & drop"** and
`blocks/listing-submission/style.css` has always carried an `.is-dragging` state — but no drop
handling was ever written. Dropping a file did nothing at all: no highlight, no upload, no error.
The copy and the CSS had got ahead of the interaction, and the dead style was the tell.

Scope is deliberately the **featured image** zone only. The gallery is an "Add Photos" button, not a
drop target, and its copy does not promise otherwise — so it is out of scope until someone decides
it should be a drop zone too.

> Uploads go through `POST /wp/v2/media`, the same `upload_files` gate the media modal enforces, and
> the resulting attachment is handed to the **same** `applyFeaturedAttachment()` the modal's select
> handler calls. A dropped image and a picked image must land in identical state — if they diverge,
> someone added a second upload path instead of sharing the first.

## Steps

### 1 — The zone highlights while dragging

Open `$SITE_URL/add-listing/?autologin=1` and drag an image file over the featured-image box.

- **Expect** `.is-dragging` on `.listora-submission__upload-trigger` while the pointer is over it.
- **Expect** it to clear on drag-leave, and **not** to flicker off while moving across child
  elements inside the zone (the handler ignores `dragleave` toward a descendant).

### 2 — Dropping uploads and previews

Drop a valid JPG/PNG/WebP.

- **Expect** `input[name="featured_image"]` to gain a real attachment ID.
- **Expect** the zone to render `img.listora-submission__media-preview` pointing at the uploaded
  file.
- **Expect** the attachment to exist in the Media Library, owned by the submitting member.

### 3 — The dropped image survives submission

Complete and submit the listing.

- **Expect** the listing's featured image to be the dropped file. A preview that never made it into
  the saved post means the hidden input was not the one the form reads.

### 4 — A non-image is refused

Drop a `.txt` (or a PDF).

- **Expect** a clear message ("That file is not an image…") and **no upload**.
- **Expect** `input[name="featured_image"]` unchanged.

### 5 — The site's size cap is enforced on this path too

Drop an image larger than **Settings → Submissions → Max file size**.

- **Expect** the same "exceeds N MB" message the media-modal path gives, and no upload.
- This is the step that matters most: a drop path with looser limits is a way around the owner's
  configured cap. The cap value must come from `listoraI18n.maxUploadSizeMb`, not a hardcoded 5.

### 6 — Click-to-upload still works

Click the same zone and pick an image from the modal.

- **Expect** identical end state to step 2. Both paths call `applyFeaturedAttachment()`; if only one
  sets the preview or only one sets the input, they have drifted.

### 7 — Editing an existing listing

Open `?edit=<id>` for a listing that already has a featured image and drop a replacement.

- **Expect** the preview and the hidden input to update to the new attachment, and the listing to
  save with the replacement.

## Cleanup

Delete any attachments uploaded during the run (they are real Media Library entries), and delete the
probe listing if step 3 created one.
