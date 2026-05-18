---
journey: admin-listing-types-crud
plugin: wb-listora
priority: high
roles: [administrator]
covers: [listing-types-admin, type-registry, submission-wizard-type-step]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Admin user (?autologin=1)"
estimated_runtime_minutes: 4
---

# Admin creates, edits, and deletes a custom listing type

Admin opens Listing Types page, creates a new type with custom field groups, edits it, deletes it. Verifies the new type appears as a tab on `/listings/` and as a dropdown option in the submission wizard.

## Setup

- Site: `$SITE_URL`
- Admin: `?autologin=1`
- Reset:
  ```bash
  wp eval 'delete_option("wb_listora_custom_types_smoke");'
  ```

## Steps

### 1. Open Listing Types admin
- **Action**: `playwright_navigate $SITE_URL/wp-admin/admin.php?page=listora-listing-types&autologin=1`
- **Expect**: list of existing types (Business, Restaurant, Hotel, etc.) with edit + delete + clone actions per row. "Add New" button visible.

### 2. Add new type
- **Action**: click "Add New" → fill: slug = `smoke-type`, label = `Smoke Type`, icon = pick a Lucide icon, field groups = pick 2-3 from existing
- **Expect**: success notice, redirect to type list with new row

### 3. Verify type registered
- **Action**:
  ```bash
  wp eval 'echo (\WBListora\Core\Listing_Type_Registry::get("smoke-type") ? "REGISTERED" : "MISSING");'
  ```
- **Expect**: `REGISTERED`

### 4. Verify type appears on directory page
- **Action**: `playwright_navigate $SITE_URL/listings/`
- **Expect**: type-tab list includes "Smoke Type"

### 5. Verify type appears in submission wizard
- **Action**: `playwright_navigate $SITE_URL/add-listing/?autologin=tester`
- **Expect**: Type step's grid includes "Smoke Type" with the chosen icon

### 6. Edit the type
- **Action**: back in admin, click Edit on Smoke Type row → change label to "Smoke Type Edited" → save
- **Expect**: success notice, label updated

### 7. Verify edit propagated
- **Action**: refresh directory page
- **Expect**: type tab now reads "Smoke Type Edited"

### 8. Delete the type
- **Action**: click Delete on Smoke Type row → confirm via `listoraConfirm` (NOT native confirm)
- **Expect**: row removed from list

### 9. Verify cleanup
- **Action**:
  ```bash
  wp eval 'echo (\WBListora\Core\Listing_Type_Registry::get("smoke-type") ? "REGISTERED" : "REMOVED");'
  ```
- **Expect**: `REMOVED`

### 10. Verify no orphan listings
- **Action**:
  ```sql
  SELECT COUNT(*) FROM wp_term_relationships tr
  JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id=tt.term_taxonomy_id
  JOIN wp_terms t ON tt.term_id=t.term_id
  WHERE t.slug='smoke-type';
  ```
- **Expect**: 0 (no orphans). Delete should warn and either prevent or cascade-cleanup if listings of this type exist.

## Pass criteria

1. New type creates and registers
2. Appears on directory + submission wizard
3. Edit propagates label change
4. Delete removes type via `listoraConfirm` modal
5. No orphan term relationships left

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Add New form doesn't render | admin page handler missing | `class-listing-types-admin.php` |
| Type not in registry after save | save handler doesn't write to option | type save_meta or option update |
| Native confirm() on delete | `listoraConfirm` not used | type list JS |
| Orphan term relationships | delete doesn't cascade | type delete handler |
