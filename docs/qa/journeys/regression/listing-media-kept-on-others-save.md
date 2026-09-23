---
journey: listing-media-kept-on-others-save
plugin: wb-listora
priority: critical
roles: [listora_moderator, subscriber]
covers: [media-helpers, listing-fields-metabox, submission-update, gallery, file-fields]
prerequisites:
  - "Site reachable at $SITE_URL"
estimated_runtime_minutes: 5
covers_card: 10331918084, 10331922348
---

# Saving a listing keeps media its editor did not upload

Regression sentinel for the 1.8.0 QA bounce.

## Background

The 1.7.0 ownership rule (`wb_listora_user_can_attach()`) stops a member binding someone else's file. Re-saving a listing re-posts every file on it, so the rule also dropped files already there: a Listora Moderator's wp-admin Update emptied the gallery and file fields, and a member's frontend edit removed photos an admin had added. `wb_listora_keep_listing_media()` records the listing's current media when the owner or an editor starts a save (fields metabox, `update_listing()`, the `/wp/v2` write guard); those IDs stay attachable for that request.

## Steps

### 1. Moderator keeps the gallery
- **Action**: as a Listora Moderator, open in wp-admin a listing whose gallery was added by someone else. Change nothing in Media; Update.
- **Expect**: `_listora_gallery` unchanged, file fields (e.g. Company Logo) unchanged.
- **On fail**: confirm `save_post()` in `class-listing-fields-metabox.php` calls `wb_listora_keep_listing_media()` before the field loop.

### 2. Member keeps admin-added photos
- **Action**: as admin, add photos to a member's listing. As the member, Dashboard > Edit > Update Listing without touching photos.
- **Expect**: all admin photos still in the gallery.

### 3. The ownership rule still holds
- **Action**: as a member, POST `/listora/v1/submit` for your listing with `gallery` / `featured_image` set to an admin attachment NOT on the listing.
- **Expect**: dropped; gallery and thumbnail unchanged.
