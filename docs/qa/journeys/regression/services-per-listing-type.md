---
journey: services-per-listing-type
plugin: wb-listora
priority: normal
roles: [administrator, anonymous, member-owner, another-member]
covers: [listing-types, services, rest, dashboard, card-10217625415]
prerequisites:
  - "A listing of a type that has services (this site: Job has 14 listings with services, Restaurant 18)"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Services can be switched off for a listing type

The Listing Types editor has a **Services enabled** checkbox (`#listora-type-services`, help "Off for types like Jobs. Saved services are hidden, not deleted.", `includes/admin/class-type-editor.php:441,645-649`) next to Map enabled and Frontend submission. The gate is `Services::enabled_for_listing()` (`includes/core/class-services.php:54`), used by every read and write; escape hatch `wb_listora_services_enabled`. Default is ON (`Listing_Type` prop `services_enabled => true`, `class-listing-type.php:71`), so types that predate the setting keep services. PHPUnit: `tests/unit/ServicesPerTypeTest.php` (4 tests).

Verified via: source read, and `wp eval` confirming all 10 registered types report services ON by default. The edit-and-save flow, the dashboard panel and the REST 403 were NOT executed here (they change a shared type: run with a QA-J1 type or restore afterwards).

## Steps

### 1. Default is on, including pre-existing types
- **Expect**: editor shows Services enabled ticked for every existing type; listings still show services. Absent term meta must read as ON.

### 2. Switch it off for one type (Job)
- **Action**: Listora > Listing Types > Job > untick **Services enabled** > save.
- **Expect** on a Job listing:
  - listing page: no `#tab-services` tab and no `#panel-services`;
  - `GET /listora/v1/listings/{id}/detail` returns `services: []` (`class-listings-controller.php:1028`);
  - owner dashboard (`/my-listings/`): no services panel for that listing (`templates/blocks/user-dashboard/tab-listings.php:198,554`);
  - wp-admin edit screen: no "Services" meta box (`class-services-metabox.php:91`);
  - `POST` create service returns 403 `listora_services_disabled` ("This listing type does not offer services.", `class-services.php:170-176`).

### 3. Nothing is deleted
- **Action**: tick it again, save.
- **Expect**: the previously saved services are listed again, unchanged (row count in `listora_services` identical before/after).

### 4. Other types untouched (positive control)
- **Expect**: a Restaurant listing still shows its services throughout, including while Job is off.

### 5. Role ladder (Job listing, Services off)
| Actor | Create service via REST | Sees panel |
|---|---|---|
| owner | 403 `listora_services_disabled` | no |
| another member | 403 (not owner) | n/a |
| logged out | 401 | no tab |
| administrator (control) | 403 `listora_services_disabled` (the type gate, not capability) | no meta box |

### Restore
Re-tick Services enabled for Job.
