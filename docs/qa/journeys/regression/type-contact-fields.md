---
journey: type-contact-fields
plugin: wb-listora
priority: normal
roles: [administrator, subscriber, logged-out]
covers: [listing-type-field-registry, contact-fields-collectable, submission-form-fields]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Types seeded from defaults (fresh install / reset): the change applies on (re)seed, not retroactively"
estimated_runtime_minutes: 5
covers_card: 9852373335
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Business-like types collect the contact fields they display

The listing-detail sidebar always displays phone/email/website, so business-like types must register those fields. Types are `listora_listing_type` terms with field groups in `_listora_field_groups` term meta, seeded from `Listing_Type_Defaults` (`includes/core/class-listing-type-defaults.php`). 2.0.0: the Education type gains Phone, Email and Website (provider contact, `education()` at `:1370`, `schema_prop` `provider.telephone/email/url`) on new installs.

## Steps

### 1. Type definitions expose contact fields (REST)
- **Action**: for each type, `GET /listora/v1/listing-types/<slug>` (note `real-estate` is hyphenated); read `field_groups[].fields[].key`.
- **Expect** (2026-10-12 on this seeded site):

| Type | `phone` | `email` | `website` | other |
|---|---|---|---|---|
| restaurant, hotel, healthcare, place, real-estate, event, business | yes | yes | yes | `address` |
| classified | n/a | n/a | n/a | `seller_name`, `seller_phone`, `seller_email`, `address` (intentional) |
| job | no | no | no | `address` (intentionally out of scope) |
| education | no on this site | no | no | **expected yes on a FRESH install (2.0.0)**; this site was seeded earlier, so re-seed (`wb_listora_needs_defaults` option set, then any page load) before asserting |

- **On fail**: `includes/core/class-listing-type-defaults.php`; confirm the types were re-seeded (`includes/core/class-listing-type-registry.php:130` consumes `wb_listora_needs_defaults`).

### 2. Submission form renders the fields
- **Action**: autologin as a member; Add Listing > Restaurant > Details step.
- **Expect**: Email and Website inputs render with Address and Phone (`input[name="meta_email"]`, `input[name="meta_website"]`; check the exact names in the DOM).

### 3. Round-trip + display
- **Action**: submit a restaurant with email + website filled; open the detail page.
- **Expect**: the sidebar contact card shows the submitted website and email (no orphaned data). With a field's "Show on listing page" switch off (2.0.0) it must be absent from the page, cards, API and structured data.

### 4. No duplicate field keys
- **Verify**: no listing type registers the same field key twice (would render duplicate inputs, last-wins meta). Verified 2026-10-12: zero duplicates in all ten types.

### 5. Role ladder
Member: steps 2-3. Logged out: steps 1 and the public detail page; the form is behind sign-in. Admin: step 1 plus the Type Editor (Listing Types > type > Fields) shows the same fields (control).

## Fail diagnostics
| Symptom | File |
|---|---|
| Field missing from a type | `includes/core/class-listing-type-defaults.php` (type method, e.g. `education()` `:1370`) |
| Fields exist but not on the form | `includes/submission-field-renderer.php` field loop |
| Detail card empty | listing-detail template contact block |

## Not verified
Step 1 and 4 were run via REST on 2026-10-12 (education fields absent on this older seed, consistent with the "new installs" scope). Steps 2, 3 and 5 were not run.

Verified via: REST (curl).
