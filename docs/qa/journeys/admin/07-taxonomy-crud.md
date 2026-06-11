---
journey: admin-taxonomy-crud
plugin: wb-listora
priority: normal
roles: [administrator]
covers: [taxonomy-admin, category-cap-map, location-hierarchy, feature-flat]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Admin user (?autologin=1)"
estimated_runtime_minutes: 4
---

# Admin manages Categories / Locations / Features taxonomies

Admin adds a hierarchical Category, a hierarchical Location (Country > State > City), and a flat Feature. Assigns one to a listing. Verifies frontend filter URL uses the slug. Sentinel for taxonomy capability map.

## Setup

- Site: `$SITE_URL`
- Admin: `?autologin=1`

## Steps

### 1. Open Categories admin
- **Action**: `playwright_navigate $SITE_URL/wp-admin/edit-tags.php?taxonomy=listora_listing_cat&post_type=listora_listing&autologin=1`
- **Expect**: edit-tags screen renders with name + slug + parent + description fields

### 2. Add a category
- **Action**: name = `Smoke Cat`, slug = `smoke-cat`, parent = none → Add New
- **Expect**: success notice, term appears in list

### 3. Add a child category
- **Action**: name = `Smoke Cat Sub`, slug = `smoke-cat-sub`, parent = `Smoke Cat` → Add New
- **Expect**: nested under parent in list

### 4. Open Locations admin
- **Action**: `playwright_navigate $SITE_URL/wp-admin/edit-tags.php?taxonomy=listora_listing_location&post_type=listora_listing&autologin=1`
- **Expect**: same shape, hierarchical

### 5. Add Country > State > City
- **Action**: add `Smokeland` (top), `Smoke State` (parent: Smokeland), `Smoke City` (parent: Smoke State)
- **Expect**: 3-level nesting visible

### 6. Open Features admin
- **Action**: `playwright_navigate $SITE_URL/wp-admin/edit-tags.php?taxonomy=listora_listing_feature&post_type=listora_listing&autologin=1`
- **Expect**: edit-tags screen but NO parent dropdown (flat taxonomy)

### 7. Add a feature
- **Action**: name = `Smoke WiFi`, slug = `smoke-wifi` → Add New
- **Expect**: term appears

### 8. Assign to a listing
- **Action**: edit any published listing → Categories meta box → tick `Smoke Cat Sub` → Features meta box → tick `Smoke WiFi` → Update

### 9. Verify on frontend
- **Action**: `playwright_navigate $SITE_URL/?listora_listing_cat=smoke-cat-sub`
- **Expect**: results include the listing assigned in step 8
- **On fail**: rewrite rules out of sync — `wp rewrite flush`

### 10. Verify cap-context fix (sentinel)
- **Action**:
  ```bash
  wp eval 'echo (current_user_can("manage_listora_categories") ? "YES" : "NO");'
  ```
- **Expect**: `YES` for admin (cap exists per taxonomy capability map fix from commit 9abbfcb).

### 11. Cleanup
- **Action**:
  ```bash
  wp term delete listora_listing_cat smoke-cat --by=slug
  wp term delete listora_listing_cat smoke-cat-sub --by=slug
  wp term delete listora_listing_location smokeland --by=slug
  wp term delete listora_listing_feature smoke-wifi --by=slug
  ```

### Tags + service categories (the two remaining taxonomies)

```bash
# listora_listing_tag — flat tag taxonomy on listings
wp term create listora_listing_tag "Smoke Tag" --slug=smoke-tag
wp post term add LISTING_ID listora_listing_tag smoke-tag --by=slug
# Expect: tag assigned; tag archive/filter resolves; taxonomy is flat (no parent field).

# listora_service_cat — categorizes Services
wp term create listora_service_cat "Smoke Service Cat" --slug=smoke-svc-cat
# Expect: term creates; appears in the Services meta box service-category control;
# a service assigned to it is filterable. No fatal; correct per-taxonomy caps.

# cleanup
wp term delete listora_listing_tag smoke-tag --by=slug
wp term delete listora_service_cat smoke-svc-cat --by=slug
```

## Pass criteria

1. Hierarchical categories work (parent + child)
2. 3-level locations work (Country > State > City)
3. Features taxonomy has NO parent field (flat)
4. Assigning to a listing persists + filters correctly on frontend
5. Capability map gives admin the per-taxonomy caps
6. `listora_listing_tag` (flat) + `listora_service_cat` create/assign/filter without error

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Hierarchical fields missing | taxonomy registered as flat | `class-taxonomies.php` `hierarchical` flag |
| Cap-context mismatch | regression of 9abbfcb | `class-taxonomies.php` capabilities map |
| Filter URL returns 404 | rewrite rules stale | `wp rewrite flush` |
