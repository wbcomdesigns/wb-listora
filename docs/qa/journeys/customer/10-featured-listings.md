---
journey: featured-listings-rotation
plugin: wb-listora
priority: normal
roles: [anonymous]
covers: [listing-featured-block, rotate-featured-cron, featured-flag-meta]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "At least 3 listora_listing posts with _listora_is_featured=1"
  - "A page hosting the listora/listing-featured block"
estimated_runtime_minutes: 3
---

# Anonymous visitor sees featured listings + rotation cron

Visit a page with the listing-featured block. Verifies the featured listings render. Then triggers the featured-listing rotation cron and verifies the displayed set changes (if rotation logic is configured).

## Setup

- Site: `$SITE_URL`
- Featured page URL: `$FEAT_URL`

## Steps

### 1. Open featured page
- **Action**: `playwright_navigate $FEAT_URL`
- **Expect**: featured listings render in a carousel or grid. Each card shows the featured badge.

### 2. Verify all displayed are featured
- **Action**: capture displayed listing IDs:
  ```js
  Array.from(document.querySelectorAll('.listora-card[data-listing-id]')).map(c => c.dataset.listingId)
  ```
- For each ID:
  ```bash
  wp post meta get $ID _listora_is_featured
  ```
- **Expect**: `1` for each

### 3. Capture pre-rotation snapshot
- **Action**: save the displayed IDs as `$BEFORE`

### 4. Trigger rotation cron
- **Action**:
  ```bash
  wp action-scheduler run --hooks=wb_listora_rotate_featured_listings
  ```
- **Expect**: job completes successfully

### 5. Refresh featured page + capture post-rotation
- **Action**: hard refresh, capture displayed IDs as `$AFTER`
- **Expect**: order or set differs from `$BEFORE` IF rotation algorithm randomizes (depends on rotation strategy — default may be random subset)
- **On fail**: rotation cron didn't update featured set OR cache isn't busted

### 6. Verify carousel responsive at mobile
- **Action**: `playwright_resize 390 844`, refresh
- **Expect**: carousel uses `min(260px, 80vw)` per width or stacks correctly. No horizontal scrollbar.

### 7. Verify hooks fired
- **Action**: register temp listeners on `wb_listora_before_featured_listings` and `wb_listora_after_featured_listings`, refresh
- **Expect**: both fire exactly once per render

## Pass criteria

1. Featured block renders only listings with `_listora_is_featured=1`
2. Rotation cron updates the featured set
3. Mobile carousel responsive without overflow
4. before/after hooks fire correctly

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Non-featured listings appear | wrong query | `blocks/listing-featured/render.php` `meta_query` |
| Rotation cron does nothing | rotation algorithm trivial OR cache stale | `class-featured-rotation.php` |
| Mobile horizontal scroll | width math broken | `blocks/listing-featured/style.css` carousel rule |
| Hooks don't fire | hooks missing from render.php | check `do_action` calls |
