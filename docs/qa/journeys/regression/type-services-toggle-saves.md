---
journey: type-services-toggle-saves
plugin: wb-listora
priority: high
roles: [administrator, subscriber, logged-out]
covers: [type-editor, services, user-dashboard]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A member (QA-J1-owner) with a published listing of the type under test (Business here)"
estimated_runtime_minutes: 5
covers_card: 10331936497
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# "Services enabled" on a listing type saves and hides services

The Type Editor sends `services_enabled` (`assets/js/admin/type-editor.js:922`, checkbox `#listora-type-services` rendered at `includes/admin/class-type-editor.php:644-649`, with the hint "Off for types like Jobs. Saved services are hidden, not deleted."); the registry stores `1`/`0` in term meta `_listora_services_enabled` (`includes/core/class-listing-type-registry.php:285`), reading a missing value as ON (`bool_meta( ..., true )`, `:214`). The member dashboard hides the services count (`.listora-dashboard__services-count`), the **Services** button (`button.listora-dashboard__services-toggle`) and its panel (`.listora-dashboard__services-panel`) for a type with services off (`templates/blocks/user-dashboard/tab-listings.php:199, 276, 465`), and the wp-admin Services box (`#wb_listora_services`) is not registered.

## Steps

### 1. Switch off and reload
- **Action**: Listora > Listing Types > (a type) > untick **Services enabled** > **Save Type**. Reload.
- **Expect**: still unticked; term meta `_listora_services_enabled` is `0`. API: `PUT /listora/v1/listing-types/business {"services_enabled":false}` -> 200, meta `'0'`, other settings (moderation `global`, map `1`) unchanged (verified 2026-10-12; the partial update did not reset the rest, 2.0.0).

### 2. Dashboard
- **Action**: as a member with a listing of that type open `/my-listings/`.
- **Expect**: that row has no `.listora-dashboard__services-toggle` and no services panel; other types' rows keep theirs. Verified 2026-10-12 via curl as QA-J1-owner: with services off, `listora-dashboard__services-toggle` count 0 and panel count 0; with services on, 1 and 1.

### 3. Switch back on (positive control)
- **Action**: tick it again, save, reload.
- **Expect**: ticked; the "Services" button and panel reappear with previously saved services intact (rows were hidden, not deleted).

### 4. Pre-1.8.0 types default ON
- **Expect**: a type with no `_listora_services_enabled` meta reads as enabled (`class-listing-type-registry.php:214`).

### 5. Role ladder
| Actor | Expect |
|---|---|
| Admin | steps 1, 3 |
| Owner member | steps 2-3 (button present/absent) |
| Other member | cannot open the owner's services panel; `PUT /services/{id}` 403 |
| Logged out | the public listing page shows no "Services" section for a services-off type (control: shown when on) |

### 6. Mobile
At 390 the row actions (Services, Edit, View, More) wrap, each >= 40px tall, no horizontal scroll.

## Fail diagnostics
| Symptom | File |
|---|---|
| Untick reverts | `assets/js/admin/type-editor.js:922` (not sent) or `includes/rest/class-listing-types-controller.php:590, 705` |
| Stored `''` read as ON | `includes/core/class-listing-type-registry.php:285` must write `1`/`0` |
| Dashboard still shows button | `templates/blocks/user-dashboard/tab-listings.php:199` (`$dash_svc_on`) |

## Not verified
Steps 1 (REST half) and 2 were run on 2026-10-12 and the type restored to ON. The editor UI save, steps 4-6 and the public-page row were not run. The button now reads "Services" with a wrench icon (not an icon-only wrench).

Verified via: wp-cli REST, curl/DOM.
