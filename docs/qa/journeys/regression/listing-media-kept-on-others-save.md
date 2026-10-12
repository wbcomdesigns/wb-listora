---
journey: listing-media-kept-on-others-save
plugin: wb-listora
priority: critical
roles: [listora_moderator, subscriber]
covers: [media-helpers, listing-fields-metabox, submission-update, gallery, file-fields]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A member-owned listing (demo: qa_vendor_01's listing 845) whose featured image and gallery were uploaded by admin"
  - "A user with the Listora Moderator role (demo: qa_editor)"
estimated_runtime_minutes: 6
covers_card: 10331918084, 10331922348
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Saving a listing keeps media its editor did not upload

Regression sentinel for the 1.8.0 QA bounce.

## Background
The ownership rule `wb_listora_user_can_attach()` (`includes/media-helpers.php:31`) stops a member binding someone else's file. A re-save re-posts every file already on the listing, so the rule also dropped those. `wb_listora_keep_listing_media( $listing_id )` (`media-helpers.php:75`) records the listing's current thumbnail, `_listora_gallery` and gallery/file field values when the owner or someone who can `edit_post` starts a save; those IDs stay attachable for that request. Callers: fields metabox `includes/admin/class-listing-fields-metabox.php:162`, services metabox `class-services-metabox.php:476`, `/wp/v2` write guard `includes/rest/class-listing-write-guards.php:49`, frontend update `includes/rest/class-submission-controller.php:1088`.

Verified via: source read only. A live run needs wp-admin file uploads by two users and was not executed in this pass; see `## Not verified`.

## Steps

### 1. Moderator keeps the gallery (positive control for the guard)
- **Action**: as qa_editor open `wp-admin/post.php?post=<ID>&action=edit` for a listing whose gallery admin uploaded. Change nothing in Media; click Update.
- **Expect**: `_listora_gallery`, `_thumbnail_id` and file fields (e.g. Company Logo) are byte-identical afterwards (`wp post meta get <ID> _listora_gallery`).

### 2. Member keeps admin-added photos
- **Action**: as admin add photos to a member's listing. As the member open `/my-listings/?tab=listings`, Edit, change only the description, click "Update Listing".
- **Expect**: all admin photos still present in the gallery and as the featured image.

### 3. The ownership rule still holds (negative control)
- **Action**: as the member, `PUT /listora/v1/submit/<ID>` with `gallery` / `featured_image` set to an admin attachment that is NOT on this listing (nonce from `admin-ajax.php?action=rest-nonce`).
- **Expect**: the foreign ID is dropped: gallery and thumbnail unchanged, response 200.

### 4. Role ladder
| Actor | Expect on re-save |
|---|---|
| Owner (member) | step 2 |
| Another member (`subscriber2`) | `PUT /submit/<ID>` 403 `listora_forbidden`, nothing changes |
| Logged out | 401 |
| Moderator (qa_editor) | step 1 |
| Admin (control) | keeps everything |

## Not verified
Steps 1-3 were not executed live in this pass (no member-owned listing with an admin-authored thumbnail exists in the demo data; seed one with `QA-J1-` prefix).

## Fail diagnostics
- Moderator Update empties gallery: `class-listing-fields-metabox.php:140` `save_post()` must call `wb_listora_keep_listing_media()` (`:162`) before the field loop.
- Member edit drops photos: `class-submission-controller.php:1072` `update_listing()` lost the call at `:1088`.
- Foreign file accepted: `media-helpers.php:31` `wb_listora_user_can_attach()`.
