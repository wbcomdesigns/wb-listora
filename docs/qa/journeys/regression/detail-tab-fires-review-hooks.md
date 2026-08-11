---
journey: detail-tab-fires-review-hooks
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [reviews, photo-reviews, hooks, free-pro-seam]
prerequisites:
  - "Combo mode, photo_reviews ON"
  - "An approved review whose `photos` column holds real ATTACHMENT IDs"
estimated_runtime_minutes: 5
---

# The detail page's Reviews tab fires the same review hooks as the reviews block

There are two review-rendering surfaces and they do not share a partial:
`templates/blocks/listing-reviews/review-card.php` and the Reviews tab inside
`templates/blocks/listing-detail/tabs.php`. The tab renders its own markup.

It fired `wb_listora_review_form_after_content` (the photo UPLOAD field) but not
`wb_listora_review_after_content` (the photo RENDER). So on the most-visited
review surface in the product, members uploaded photos successfully and those
photos were never shown back to anyone.

This is the second bug caused by that split - the "Former member" author fix had
to be applied to both files too. Anything added to one MUST be mirrored in the
other until they share a partial.

## Steps

### 1. Both hooks fire from the detail tab
```bash
grep -n "wb_listora_review_after_content\|wb_listora_review_form_after_content" \
  templates/blocks/listing-detail/tabs.php
```
- **Fails if** either is missing.

### 2. Photos actually render on the detail page
Open a listing whose approved reviews carry photos, go to the Reviews tab.
- One `.listora-review-photos__gallery` per review with photos.
- Each gallery contains `<img>` elements, computed-visible.
- **Fails if** galleries render with zero images - see the trap below before
  concluding the hook is broken.

### 3. The arg shape matches the other surface
Both fire sites pass the review ROW array. Pro's `render_photos( $review )`
reads `$review['photos']`.
- **Fails if** the tab passes an ID or a reshaped array - the listener would
  return silently and look identical to the hook not firing at all.

### 4. Toggling photo_reviews OFF removes them from BOTH surfaces
No galleries on either the detail tab or the reviews block.

## Test-data traps

Two, and both produce a convincing false FAIL:

1. **Seeded demo reviews store image URLs, not attachment IDs.** The real upload
   path stores `array_map( 'absint', ... )`. `absint( 'https://...' )` is 0, so
   the renderer skips every entry and emits an empty gallery. That is correct
   behaviour on unrepresentative data. Point the fixture at real attachment IDs
   before judging.
2. **The review list is cached.** After editing the `photos` column directly,
   `wp cache flush` and clear `_transient%listora%`, or the page serves the
   previous render and the fix appears not to work.
