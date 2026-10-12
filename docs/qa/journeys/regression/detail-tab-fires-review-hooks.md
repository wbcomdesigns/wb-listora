---
journey: detail-tab-fires-review-hooks
plugin: wb-listora
priority: high
roles: [anonymous, member, admin]
covers: [reviews, photo-reviews, hooks, free-pro-seam]
prerequisites:
  - "Combo mode, photo_reviews ON"
  - "Listing 17 /listing/the-golden-fork/ has approved reviews whose `photos` column holds image URLs (demo data, not attachment IDs)"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# The detail page's Reviews tab fires the same review hooks as the Reviews block

Two surfaces render reviews: `templates/blocks/listing-reviews/review-card.php` (Reviews block) and the Reviews tab in `templates/blocks/listing-detail/tabs.php`. Since 2.0.0 both share ONE `review-form.php` (included from `tabs.php:722` and `reviews.php:92`), so `wb_listora_review_form_after_content` fires from that shared file only; `wb_listora_review_after_content` is still fired separately by `review-card.php:58` and `tabs.php:569`. Pro listens in `wb-listora-pro/includes/features/class-photo-reviews.php:34-37`.

Verified via: curl + DOM on listing 17 with a temporary attachment-ID fixture (restored), 2026-10-12.

## Steps

### 1. Both hooks reachable from the detail tab
```bash
grep -n "wb_listora_review_after_content" templates/blocks/listing-detail/tabs.php          # 569
grep -n "wb_listora_review_form_after_content" templates/blocks/listing-reviews/review-form.php   # 92
grep -n "listing-reviews/review-form.php" templates/blocks/listing-detail/tabs.php            # 722
```
- **Fails if** any is missing.

### 2. Photos render on the detail page (POSITIVE CONTROL)
- **Action**: point one approved review's `photos` at a real attachment id (`UPDATE wp_listora_reviews SET photos='[19]' WHERE id=1`), `wp cache flush`, clear `_transient%listora%`, open listing 17.
- **Expect**: `.listora-review-photos__gallery` containing `img.listora-review-photos__gallery-img` (verified: 3 galleries, 1 image). Restore the original value afterwards.

### 3. Demo data shows an empty gallery by design
- **Expect**: with the shipped demo rows (photo URLs, not ids) the galleries render with 0 `<img>`: `absint( 'https://...' )` is 0 so `render_photos()` skips them. This is correct on unrepresentative data; do not file it.

### 4. Arg shape
- Both fire sites pass the review ROW array; `render_photos( $review )` reads `$review['photos']` (JSON). Fails if a site passes an id or a reshaped array (the listener returns silently).

### 5. photo_reviews OFF removes them from both surfaces
- **Action**: set `photo_reviews` false in `wb_listora_pro_features`.
- **Expect**: no `.listora-review-photos__gallery` on the detail tab or the Reviews block; the upload UI (`render_upload_ui`) is gone from the form. Restore.

### 6. Role ladder
- Reading is public: anonymous, another member, owner, editor and admin see identical galleries. Posting photos needs a logged-in reviewer (member, not the listing owner); admin is only the control.

## Fail diagnostics

| Symptom | File |
|---|---|
| Photos missing on detail tab only | `templates/blocks/listing-detail/tabs.php:569` |
| Upload field missing | `templates/blocks/listing-reviews/review-form.php:92` |
| Empty gallery | `wb-listora-pro/includes/features/class-photo-reviews.php:351` `render_photos()` |
| Stale after DB edit | review list cache: flush cache and `_transient%listora%` |
