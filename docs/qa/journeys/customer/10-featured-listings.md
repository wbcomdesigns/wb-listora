---
journey: featured-listings-expiry
plugin: wb-listora
priority: normal
roles: [anonymous]
covers: [listing-featured-block, expire-featured-cron, featured-flag-meta]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "At least 3 listora_listing posts with _listora_is_featured=1"
  - "A page hosting the listora/listing-featured block"
estimated_runtime_minutes: 3
---

# Anonymous visitor sees featured listings + expiry cron

Visit a page with the listing-featured block. Verifies the featured listings render. Then expires one listing's featured period and runs the expiry cron to verify it drops out of the block.

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

### 3. Capture pre-expiry snapshot
- **Action**: save the displayed IDs as `$BEFORE`

### 4. Expire one featured listing and run the cron
- **Action**:
  ```bash
  wp post meta update $ID _listora_featured_until 1
  wp action-scheduler run --hooks=wb_listora_expire_featured
  ```
- **Expect**: job completes successfully

### 5. Refresh featured page + capture post-expiry
- **Action**: hard refresh, capture displayed IDs as `$AFTER`
- **Expect**: the expired listing is gone from `$AFTER` and `_listora_is_featured` is no longer 1 for it
- **On fail**: expiry cron didn't unfeature the listing OR cache isn't busted

### 6. Verify carousel responsive at mobile
- **Action**: `playwright_resize 390 844`, refresh
- **Expect**: carousel uses `min(260px, 80vw)` per width or stacks correctly. No horizontal scrollbar.

### 7. Verify hooks fired
- **Action**: register temp listeners on `wb_listora_before_featured_listings` and `wb_listora_after_featured_listings`, refresh
- **Expect**: both fire exactly once per render

## Pass criteria

1. Featured block renders only listings with `_listora_is_featured=1`
2. Expiry cron unfeatures listings past `_listora_featured_until`
3. Mobile carousel responsive without overflow
4. before/after hooks fire correctly

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Non-featured listings appear | wrong query | `blocks/listing-featured/render.php` `meta_query` |
| Expiry cron does nothing | `_listora_featured_until` not set OR cache stale | `includes/core/class-featured.php` |
| Mobile horizontal scroll | width math broken | `blocks/listing-featured/style.css` carousel rule |
| Hooks don't fire | hooks missing from render.php | check `do_action` calls |
