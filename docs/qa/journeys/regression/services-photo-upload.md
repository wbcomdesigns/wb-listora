---
journey: services-photo-upload
plugin: wb-listora
priority: high
roles: [administrator, member-owner, anonymous]
covers: [services-metabox, photo-column, media-library-picker]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Admin user (?autologin=varundubey)"
  - "A listing of a type with services enabled; capture LISTING_ID (use a QA-J1- listing)"
  - "Media library contains at least 1 image"
estimated_runtime_minutes: 3
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Services photo upload sentinel

The Services meta box (`includes/admin/class-services-metabox.php`, registered on `add_meta_boxes_listora_listing`) has a **Photo** column per service with a **Choose** button that opens the WP media frame (script `assets/js/admin/services-metabox.js`, enqueued only on the `listora_listing` screen via `enqueue_assets`, `:56`). Frame title "Choose service photo", confirm button "Use this photo" (localized `listoraServicesMetabox`, `:76-79`).

Verified via: source read of the metabox, the JS file's presence and the frontend template; the browser flow was NOT executed here (media frame needs a real browser). 2.0.0 note: the Services box now lists categories as checkboxes (a "Category" column), and a type with services off shows no box.

## Steps

### 1. Open the listing edit screen
- **Action**: `$SITE_URL/wp-admin/post.php?post=$LISTING_ID&action=edit&autologin=varundubey`. **Expect**: edit screen renders.

### 2. Services box
- **Action**: find the **Services** box (`#wb_listora_services`; Screen Options if hidden).
- **Expect**: table with headers Photo, Title, Price, Type, Duration (min), Status, Category, Delete (existing rows), and an "Add new services" table with the button **+ Add Another Service** (`data-listora-svc-add-row`).

### 3. Add a row and Choose
- **Action**: fill title `QA-J1-service`, price, duration; click **Choose** (`[data-listora-svc-choose]`) in the Photo cell.
- **Expect**: WP media frame opens titled "Choose service photo" (images only). **On fail**: `services-metabox.js` delegated handler or `enqueue_assets`.

### 4. Pick an image
- **Action**: select a thumbnail, click **Use this photo**.
- **Expect**: frame closes; 48x48 preview `[data-listora-svc-preview]` shows the image; the button reads "Change"; **Remove** appears; hidden `[data-listora-svc-input]` (`name=wb_listora_services[new][N][image_id]`) holds the attachment id.

### 5. Save and persist
- **Action**: **Update**, reload.
- **Expect**: the thumbnail is still in the row; DB: `listora_services.image_id` equals the attachment id (`save_post()` `:452`, `image_id` branch `:578-583`).

### 6. Frontend (anonymous positive control)
- **Action**: logged out, `$SITE_URL/listing/<slug>/`, Services tab `#tab-services`.
- **Expect**: the service card shows the image (`templates/blocks/listing-detail/tabs.php:355-360`).

### 7. Remove clears it
- **Action**: **Remove**, Update. **Expect**: `image_id` NULL, card has no image.

### Role ladder
Administrator edits any listing's services here; a member-owner edits via the dashboard (no wp-admin box); another member cannot (403 on REST). `contributor1` has no `edit_others` and opening another's `post.php` is refused.

### Cleanup
Delete the `QA-J1-service` row and the QA listing.

## Fail diagnostics
| Symptom | File |
|---|---|
| No Photo column | `class-services-metabox.php:138` (header), `render_photo_cell` `:382` |
| Choose does nothing | `enqueue_assets` `:56`, `assets/js/admin/services-metabox.js` |
| Selection not saved | `save_post()` `:452`, `:578-583` |
| Image missing on frontend | `templates/blocks/listing-detail/tabs.php:355` |
