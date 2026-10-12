---
journey: listing-already-flags
plugin: wb-listora
priority: normal
roles: [member-owner, another-member, anonymous, editor]
covers: [rest, dashboard, listings, i18n, card-10154925210]
prerequisites:
  - "A member who owns at least one published listing (create QA-J1 flags, author tester, type business)"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Deactivate and reactivate say whether they changed anything

Both routes (`POST /listora/v1/listings/{id}/deactivate|reactivate`, `includes/rest/class-listings-controller.php:326,345`) return a machine flag: `already_deactivated` / `already_active`, `false` when they acted and `true` on the no-op branch (still HTTP 200). The mobile app and the dashboard toast read the flag, not the prose. PHPUnit: `tests/unit/ListingAlreadyFlagsTest.php`.

Verified via: `rest_do_request()` as owner (tester), another member (subscriber2), anonymous and editor on a throwaway listing, 2026-10-12. The dashboard toast (step 2) not run in a browser.

## Steps

### 1. REST, both routes, called twice
- **Action**: as the owner POST deactivate twice, then reactivate twice.
- **Expect** (all verified):
  - deactivate #1 -> 200 `{"deactivated":true,"already_deactivated":false,"message":"Listing deactivated successfully."}`, status `listora_deactivated`
  - deactivate #2 -> 200 `already_deactivated:true`, "Listing is already deactivated."
  - reactivate #1 -> 200 `{"reactivated":true,"already_active":false,...}`, status `publish`
  - reactivate #2 -> 200 `already_active:true`, "Listing is already active."

### 2. The dashboard says which happened
- **Action**: in My Listings deactivate a listing; in a second tab opened before that, click Deactivate on the same listing.
- **Expect**: first shows "Listing deactivated."; the stale tab shows "That listing is already deactivated." (`includes/class-assets.php:376-377`, `src/interactivity/store.js:1631`). Not run.

### 3. Translated site
- **Expect**: the flags are unchanged by translation; nothing downstream reads the message text.

### 4. Role ladder
- Owner: step 1. Another member (subscriber2) reactivate -> 403 `listora_forbidden` (verified); anonymous -> 401 `listora_unauthorized` (verified); editor (`qa_editor`) -> 200 with the same flags (verified); admin only as control. A pending listing cannot be deactivated: 409 `listora_invalid_state` (`class-listings-controller.php:1328`).

### 5. POSITIVE CONTROL
- The `false` flags in step 1 (acting branch) prove the flag is not constantly true.

## Pass criteria

1. Both routes carry the flag on both branches.
2. The dashboard toast follows the flag, not the prose.

## Fail diagnostics

| Symptom | File |
|---|---|
| Flag missing | `includes/rest/class-listings-controller.php:1297` `deactivate_listing()`, `:1517` `reactivate_listing()` |
| Toast follows prose | `src/interactivity/store.js:1631` |
