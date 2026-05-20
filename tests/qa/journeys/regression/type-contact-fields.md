---
journey: type-contact-fields
plugin: wb-listora
priority: normal
roles: [administrator, subscriber]
covers: [listing-type-field-registry, contact-fields-collectable, submission-form-fields]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Types seeded from defaults (fresh install / reset)"
estimated_runtime_minutes: 4
covers_card: 9852373335
---

# Business-like types collect the contact fields they display

Regression sentinel for the type contact-field reconciliation. The listing-detail sidebar always displays phone/email/website, but several types' field registries didn't register them, so a submitter couldn't enter what the detail page would show. Business-like types must now collect the common contact fields.

## Background

Types are stored as `listora_listing_type` taxonomy terms with field groups in `_listora_field_groups` term meta, seeded from `Listing_Type_Defaults`. The change is applied on (re)seed, not retroactively.

## Steps

### 1. Type definitions expose contact fields (REST)
- **Action**: for each type, `GET /listora/v1/listing-types/<slug>` (note `real-estate` is hyphenated).
- **Expect** the field-group keys include:
  - restaurant, hotel, healthcare: `email` + `website` (plus existing `address`, `phone`)
  - place: `email`
  - real-estate, event: `phone` + `email` + `website`
  - business: unchanged (already had all)
  - job, education, classified: unchanged (intentionally not in scope; classified uses seller_* fields)
- **On fail**: `includes/core/class-listing-type-defaults.php` — and confirm types were re-seeded (set `wb_listora_needs_defaults` then load a page; `create_type_from_data` overwrites `_listora_field_groups`).

### 2. Submission form renders the new fields
- **Action**: autologin; Add Listing → select "Restaurant" → reach the Contact step.
- **Expect**: Email + Website inputs render (alongside Address + Phone).
- **On fail**: submission field renderer / field-group loop.

### 3. Round-trip + display
- **Action**: submit a restaurant with email + website filled; open the detail page.
- **Expect**: the sidebar contact card shows the website + email that were submitted (no orphaned data).

### 4. No duplicate field keys
- **Verify**: no listing type registers any field key twice (would render duplicate inputs / last-wins meta).
