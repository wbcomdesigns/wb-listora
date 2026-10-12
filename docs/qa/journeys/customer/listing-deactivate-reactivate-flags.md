---
journey: listing-deactivate-reactivate-flags
plugin: wb-listora
priority: normal
roles: [owner, other, guest, editor, admin]
covers: [rest, dashboard, listings, i18n, card-10154925210]
prerequisites:
  - "A member {owner} who owns a published listing {listing} (create a throwaway QA- listing, type {type})"
  - "A second member {other}; an editor {editor}"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes:
  - ora:regression/listing-already-flags.md
fails_on: unrecorded
site_profile: required
---

# Deactivate and reactivate say whether they changed anything

## Why
Both routes (`POST /listora/v1/listings/{id}/deactivate|reactivate`, `includes/rest/class-listings-controller.php:326,345`) return a machine flag `already_deactivated` / `already_active` (`false` when they acted, `true` on the no-op branch, still HTTP 200). The mobile app and the dashboard toast read the flag, not the prose.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Call via `rest_do_request()` or REST with a nonce. PHPUnit: `tests/unit/ListingAlreadyFlagsTest.php`.

## Steps

### 1. REST, both routes, called twice
- **Action**: as {owner} POST deactivate twice, then reactivate twice.
- **Expect**:
  - deactivate #1 -> 200 `{"deactivated":true,"already_deactivated":false,"message":"Listing deactivated successfully."}`, status `listora_deactivated`
  - deactivate #2 -> 200 `already_deactivated:true`, "Listing is already deactivated."
  - reactivate #1 -> 200 `{"reactivated":true,"already_active":false,...}`, status `publish`
  - reactivate #2 -> 200 `already_active:true`, "Listing is already active."
- **Positive control**: the `false` flags on the acting branch prove the flag is not constantly true.

### 2. The dashboard says which happened
- **Action**: in My Listings deactivate {listing}; in a second tab opened before that, click Deactivate on the same listing.
- **Expect**: first shows "Listing deactivated."; the stale tab shows "That listing is already deactivated." (`includes/class-assets.php:376-377`, `src/interactivity/store.js:1631`).

### 3. Translated site
- **Expect**: the flags are unchanged by translation; nothing downstream reads the message text.

### 4. Invalid state
- **Action**: deactivate a pending listing.
- **Expect**: 409 `listora_invalid_state` (`class-listings-controller.php:1328`).

## Role ladder
| Actor | Expect |
|---|---|
| {owner} | steps 1-2 |
| {other} reactivate | 403 `listora_forbidden` |
| {guest} | 401 `listora_unauthorized` |
| {editor} | 200 with the same flags |
| {admin} | control only |

## Pass criteria
1. Both routes carry the flag on both branches.
2. The dashboard toast follows the flag, not the prose.

## Fail diagnostics
| Symptom | File |
|---|---|
| Flag missing | `includes/rest/class-listings-controller.php:1297` `deactivate_listing()`, `:1517` `reactivate_listing()` |
| Toast follows prose | `src/interactivity/store.js:1631` |

## Not verified
The dashboard toast (step 2) was not run in a browser; REST rungs were run with `rest_do_request()` as owner, another member, anonymous and editor. `customer/14-dashboard-listings-tab-manage.md` repeats step 1's REST bodies for its own flow.

## Cleanup
Delete the throwaway listing.
