---
journey: surfaces-location-and-service-photos
plugin: wb-listora
priority: high
roles: [administrator, subscriber]
covers: [location-taxonomy, services, media-helpers, wp-admin]
prerequisites:
  - "Site reachable at $SITE_URL"
estimated_runtime_minutes: 6
covers_card: 10331867610, 10331922348, 10331936497
---

# Location terms, service photos and the Services box on every surface

Regression sentinel for the third 1.8.0 QA pass.

## Background

Follow-up audit of the first-round fixes. Location terms now follow the address from any write (frontend, wp-admin fields box, block editor, API), only when the address changes, so a hand-picked location survives an unchanged save. A member editing a service keeps a photo an admin added (same keep-media rule as the gallery). The wp-admin Services box is hidden for a type with services off.

## Steps

### 1. Location from wp-admin
- **Action**: change a listing's address in the wp-admin fields box; Update.
- **Expect**: Locations panel follows the new city/state/country.

### 2. Manual location survives
- **Action**: pick a location by hand, save without touching the address.
- **Expect**: the hand-picked location stays.

### 3. Service photo
- **Action**: admin sets a service photo on a member's listing; member edits that service's title.
- **Expect**: photo kept. Swapping in another member's file is still refused.

### 4. Services box
- **Expect**: no Services box in wp-admin for a type with services off; present when on.
