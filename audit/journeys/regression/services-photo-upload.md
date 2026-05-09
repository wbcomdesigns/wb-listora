---
journey: services-photo-upload
plugin: wb-listora
priority: high
roles: [administrator]
covers: [services-metabox, photo-column, media-library-picker]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Admin user (?autologin=1)"
  - "At least 1 listora_listing exists"
  - "Media library contains at least 1 image"
estimated_runtime_minutes: 3
---

# Services Photo upload regression sentinel

The Services meta box on Listing edit must support per-service photo upload via the WP media frame. Pre-fix #9872014083 had no Photo column; users couldn't attach images to services. Sentinel for commit `5eb3b33`.

## Setup

- Site: `$SITE_URL`
- Admin: `?autologin=1`
- Fixture: 1 listing. Capture `LISTING_ID`.

## Steps

### 1. Open listing edit screen
- **Action**: `playwright_navigate $SITE_URL/wp-admin/post.php?post=$LISTING_ID&action=edit&autologin=1`
- **Expect**: edit screen renders

### 2. Locate Services meta box
- **Action**: scroll to "Services" meta box. If absent, services may be hidden — open Screen Options and re-enable.
- **Expect**: meta box visible with table headers including a "Photo" column

### 3. Add a service row
- **Action**: click "Add Service" / equivalent. Fill in title (e.g. "Smoke Service"), price, duration.

### 4. Click Choose under the Photo column
- **Action**: click the "Choose" button in the Photo cell of the new row
- **Expect**:
  - WP media frame opens (filtered to images)
  - Title/header reads "Select Image" or similar
- **On fail**: `assets/js/admin/services-metabox.js` delegated handler missing OR `enqueue_assets` not registered. Sentinel for commit 5eb3b33.

### 5. Pick an image from media library
- **Action**: click an image thumbnail → click "Use this image" / "Select"
- **Expect**:
  - Media frame closes
  - Photo cell now shows a thumbnail preview
  - Hidden `image_id` input populated with the attachment ID
- **On fail**: media frame's onSelect callback not wired

### 6. Save the listing
- **Action**: click Update
- **Expect**: success notice

### 7. Verify persistence
- **Action**: hard-refresh the edit screen
- **Expect**: Photo column for the new service still shows the thumbnail. `$wpdb->prefix . listora_services` row has `image_id` column populated.
- **On fail**: `Services::save_meta` doesn't persist `image_id` field

### 8. Verify on frontend
- **Action**: `playwright_navigate $SITE_URL/listing/<slug>`
- **Expect**: services tab card for the new service shows the image
- **On fail**: `templates/blocks/listing-detail/services.php` doesn't render image

## Pass criteria

1. Services meta box has a Photo column
2. Choose button opens WP media frame filtered to images
3. Selected image previews + persists across reload
4. Frontend renders the image on the service card

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| No Photo column visible | regression of #9872014083 | `class-services-metabox.php::render_columns`, `render_photo_cell` |
| Choose button does nothing | JS not enqueued | `enqueue_assets` not registered, `assets/js/admin/services-metabox.js` missing |
| Media frame opens but selection doesn't persist | onSelect callback or image_id input wiring broken | services-metabox.js |
| Image saved but doesn't render on frontend | services template doesn't read image_id | `templates/blocks/listing-detail/services.php` |
