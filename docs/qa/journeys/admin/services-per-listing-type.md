---
journey: services-per-listing-type
plugin: wb-listora
priority: normal
site_profile: required
roles: [admin, guest, owner, other]
covers: [listing-types, services, rest, dashboard, card-10217625415]
prerequisites:
  - "A {type} whose listings have services (call it $TYPE_A) and a second type that keeps services (control, $TYPE_B)"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unbridged default theme]
supersedes: []
---

# Services can be switched off for a listing type

## Why

The Listing Types editor has a **Services enabled** checkbox (`#listora-type-services`, `includes/admin/class-type-editor.php:441,645-649`). When off, every read and write of services for that type is gated (`Services::enabled_for_listing()`, `includes/core/class-services.php:54`; escape hatch `wb_listora_services_enabled`). Default is ON (`class-listing-type.php:71`) so types that predate the setting keep services. PHPUnit: `tests/unit/ServicesPerTypeTest.php`. This is the owner of the "type services toggle" behaviour; the pure save path is also touched by `admin/listing-types-crud.md` (cross-area).

## Setup

- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- `$LID_A` = a published listing of `$TYPE_A` owned by {owner} that has services; `$LID_B` = one of `$TYPE_B` with services. Count services first: `SELECT COUNT(*) FROM ${PREFIX}listora_services WHERE listing_id IN ($LID_A,$LID_B)` -> `$SVC0`.
- Changing a type is shared state: restore it in Cleanup, or run on a QA-created type.

## Steps

### 1. Default is on, including pre-existing types
- **Action**: open Listora > Listing Types and edit every type.
- **Expect**: Services enabled is ticked for each; listings still show services. Absent term meta reads as ON.
- **Read-back**: `wp eval` over `wb_listora_service('listing_type_registry')->get_all()` shows `services_enabled` true for every type (verified 2026-10-12).

### 2. Switch it off for one type
- **Action**: Listing Types > `$TYPE_A` > untick **Services enabled** > save.
- **Expect** on `$LID_A`: the listing page has no `#tab-services` and no `#panel-services`; `GET /listora/v1/listings/$LID_A/detail` returns `services: []` (`class-listings-controller.php:1028`); the owner dashboard has no services panel for it (`templates/blocks/user-dashboard/tab-listings.php:198,554`); the wp-admin edit screen has no "Services" meta box (`class-services-metabox.php:91`); `POST` create service returns 403 `listora_services_disabled` ("This listing type does not offer services.", `class-services.php:170-176`).

### 3. Nothing is deleted
- **Action**: tick it again, save.
- **Expect**: the previously saved services are listed again, unchanged.
- **Read-back**: the `listora_services` row count equals `$SVC0` before and after.

### 4. Other types untouched (positive control)
- **Expect**: `$LID_B` still shows services on all five surfaces while `$TYPE_A` is off.

### 5. Escape hatch
- **Action**: mu-plugin `add_filter('wb_listora_services_enabled','__return_true');` with `$TYPE_A` off.
- **Expect**: services reappear; remove the mu-plugin afterwards. Not verified live (added by consolidation).

### 6. Boundary and lifecycle
- **Action**: with `$TYPE_A` off, open a draft, pending, expired and trashed listing of that type, and one whose author was deleted.
- **Expect**: each hides services without a PHP notice. N=0, N=1 and N>1 saved services all behave identically. Not verified live.

## Role ladder

For a listing of `$TYPE_A` with Services off:

| Actor | Create service via REST | Sees panel |
|---|---|---|
| {owner} | 403 `listora_services_disabled` | no |
| {other} | 403 (not owner) | n/a |
| {guest} | 401 | no tab |
| {admin} (control) | 403 `listora_services_disabled` (the type gate, not capability) | no meta box |

## Pass criteria

1. Every existing type reads Services ON by default.
2. Off hides the tab, detail field, dashboard panel and meta box and refuses writes with 403 `listora_services_disabled`.
3. Switching back on restores the same rows; other types are never affected.

## Fail diagnostics

| Symptom | File |
|---|---|
| Tab still renders when off | `templates/blocks/listing-detail/tabs.php` gate on `enabled_for_listing()` |
| REST create succeeds when off | `includes/core/class-services.php:170-176` |
| Default off for old types | `class-listing-type.php:71` default |
| Checkbox does not save | `includes/admin/class-type-editor.php:645-649` |

## Not verified

Source read plus `wp eval` confirming all registered types report ON by default. The edit-and-save flow, dashboard panel and REST 403 were NOT executed (they change a shared type). Steps 5-6, 390px and dark mode not run.

## Cleanup

Re-tick Services enabled for `$TYPE_A`; remove any mu-plugin.
