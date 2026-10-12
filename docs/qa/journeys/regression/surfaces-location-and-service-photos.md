---
journey: surfaces-location-and-service-photos
plugin: wb-listora
priority: high
roles: [administrator, owner-member, other-member, logged-out]
covers: [location-taxonomy, services, media-helpers, wp-admin]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A listing owned by QA-J1-owner (type with services ON), an admin, a second member"
estimated_runtime_minutes: 7
covers_card: "10331867610, 10331922348, 10331936497"
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Location terms, service photos and the Services box on every surface

Location terms follow the address from any write (frontend, wp-admin fields box, block editor, API) through the meta hook `Meta_Handler::sync_location_terms()` (`includes/core/class-meta-handler.php:55`), only when the address meta changes, so a hand-picked location survives an unchanged save. A member editing a service keeps a photo an admin added (`wb_listora_keep_listing_media()`, `includes/media-helpers.php:75`; used at `includes/rest/class-services-controller.php:360`) and can attach only files they own (`wb_listora_user_can_attach()`, `:316` and `:365`). The wp-admin Services box is hidden for a type with services off (`includes/admin/class-services-metabox.php:89-92`).

## Steps

### 1. Location from wp-admin
- **Action**: change a listing's address in the wp-admin Listing Fields box; Update.
- **Expect**: Locations panel follows the new city/state/country (meta-level: verified 2026-10-12, see `submission-sets-location-terms.md`).

### 2. Manual location survives
- **Action**: tick a Location term by hand, Update without touching the address.
- **Expect**: the hand-picked term stays.

### 3. Service photo kept on a member edit
- **Action**: admin sets a service photo on a member's service (`PUT /listora/v1/services/{id}` with `image_id`); the member then changes only the service title (`PUT /listora/v1/services/{id}` `{"title": "QA-J1 renamed"}` as the owner).
- **Expect**: `image_id` unchanged after the member edit. Swapping in another member's attachment as `image_id` is refused (the field is dropped or the call 403s; record which).
- **Positive control**: the member's own uploaded attachment IS accepted as `image_id`.

### 4. Services box
- **Action**: Listora > Listing Types: untick "Services enabled" for the type, Save Type; open one of its listings in wp-admin. Then tick it again.
- **Expect**: the "Services" meta box (`#wb_listora_services`) is absent with services off and present with it on (the dashboard "Services" button follows the same flag, verified 2026-10-12 via REST toggle).

### 5. Role ladder
| Actor | Expect |
|---|---|
| Owner | steps 1-3 own listing only |
| Other member | `PUT /services/{id}` of someone else's listing 403 `listora_forbidden` |
| Logged out | 401/403 |
| Admin | steps 1-4 (control) |

## Fail diagnostics
| Symptom | File |
|---|---|
| Location resets on a plain save | `includes/core/class-meta-handler.php:66-77` |
| Member edit drops the admin photo | `includes/rest/class-services-controller.php:358-368`, `includes/media-helpers.php:75` |
| Foreign attachment accepted | `wb_listora_user_can_attach()` calls at `class-services-controller.php:316, 365` |
| Services box shown for a services-off type | `includes/admin/class-services-metabox.php:89` |

## Not verified
Only the meta-hook behaviour of step 1 and the services-off dashboard flag (step 4's REST half) were run. Steps 2, 3, the wp-admin box rendering and the role ladder were not run; their pointers are from source.

Verified via: wp-cli, source.
