---
journey: admin-add-listing-from-wp-admin
plugin: wb-listora
priority: high
roles: [administrator]
covers: [cpt-edit-screen, services-metabox, expiration-setting-no-block]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Admin user (?autologin=1)"
  - "Submission settings 'Days before expiration' is set to a non-zero value (e.g. 30)"
estimated_runtime_minutes: 4
---

# Admin creates a listing from wp-admin

Admin opens `post-new.php?post_type=listora_listing`, fills required fields, saves. Verifies the create works even when `days_before_expiration` is non-zero (pre-2026-05-08 cannot-reproduce of card #9857011539 that had been blocked here). Also exercises the Services meta box's Photo upload regression.

## Setup

- Site: `$SITE_URL`
- Admin: `?autologin=1`
- Confirm setting:
  ```bash
  wp eval 'echo get_option("wb_listora_settings")["expiration_days"] ?? "unset";'
  ```
  If unset or zero, set to 30:
  ```bash
  wp option patch update wb_listora_settings expiration_days 30
  ```

## Steps

### 1. Open new-listing screen
- **Action**: `playwright_navigate $SITE_URL/wp-admin/post-new.php?post_type=listora_listing&autologin=1`
- **Expect**: classic editor renders with title field, content area, and Listora-specific meta boxes (Type, Categories, Hours, Services, Geo)

### 2. Fill core fields
- **Action**: title = "Smoke Admin Listing", category = pick first, type = Business
- Add a service row in Services meta box: title = "Smoke Service", price = 10
- (Optional) Click Photo "Choose" to attach an image — exercise the regression sentinel

### 3. Save
- **Action**: click "Publish" / "Save Draft"
- **Expect**:
  - Success notice
  - URL becomes `post.php?post=<NEW_ID>&action=edit`
  - NO PHP fatal anywhere (debug.log clean)
- **On fail**: card #9857011539 was a cannot-reproduce report — if it now reproduces with non-zero expiration, regression. See `Listings_Controller::create_listing` or wp-admin save_post hook.

### 4. Verify expiration meta computed correctly
- **Action**:
  ```bash
  wp post meta get $NEW_ID _listora_expiration_date
  ```
- **Expect**: a future date approximately `now + 30 days`. Filter `wb_listora_listing_expiration_date` (Pro overrides this if Pricing_Plans toggle is on).

### 5. Verify Services row persisted
- **Action**:
  ```sql
  SELECT title, price, image_id FROM wp_listora_services WHERE listing_id=$NEW_ID;
  ```
- **Expect**: 1 row, title = "Smoke Service", price = 10, image_id = either null or a valid attachment ID

### 6. View on frontend
- **Action**: `playwright_navigate $SITE_URL/listing/<slug>`
- **Expect**: detail page renders correctly

### 7. Cleanup
- **Action**:
  ```bash
  wp post delete $NEW_ID --force
  ```

## Pass criteria

1. New listing creates successfully via wp-admin even with non-zero expiration setting
2. `_listora_expiration_date` postmeta carries a sensible future date
3. Services row persists
4. Frontend renders the new listing
5. No fatals during the walk

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Save throws fatal with non-zero expiration | regression of #9857011539 | save_post hook expiration calc, `Status_Manager::on_publish` |
| Services row not saved | metabox save_meta missing | `Services::save_meta` |
| Expiration date is in the past | filter returning 0 | `wb_listora_listing_expiration_date` filter (Pro Pricing_Plans listener) |
