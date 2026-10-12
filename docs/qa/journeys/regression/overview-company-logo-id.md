---
journey: overview-company-logo-id
plugin: wb-listora
priority: normal
roles: [anonymous]
covers: [listing-detail-tabs, file-field-skip-on-overview]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published Job listing with Company Logo (file field, attachment ID) set. The demo data has NONE: seed one with prefix QA-J1 (type job, meta _listora_company_logo = an image attachment id)"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Overview tab never prints a raw attachment ID (and the logo should render on its own tab)

`templates/blocks/listing-detail/tabs.php:188` skips `file` fields in the Overview loop. The field's own group tab (Job: `company`, tab id `tab-company`, panel `#panel-company`) has a branch that renders the image or a download link (`:305-325`, `.listora-detail__field-item--file`, `img.listora-detail__field-image`).

Verified via: curl of a seeded temporary job listing (created and deleted; `QA-J1 job logo`). **Step 3 FAILS on the current build** (see below).

## Steps

### 1. Open the Job listing
- **Action**: `GET $SITE_URL/listing/<slug>/` logged out.
- **Expect**: HTTP 200, Overview is the active tab (`#tab-overview[aria-selected=true]`), tabs `tab-overview`, `tab-company`, `tab-reviews`.

### 2. Critical assertion - no raw attachment ID
- **Action**: `document.querySelector( '#panel-overview' ).textContent`.
- **Expect**: no match for `/Company Logo:?\s*\d+/`. Observed: PASS (Overview showed body text and "Company Name ...", no logo row).

### 3. Company tab renders the logo as an image (currently FAILS)
- **Action**: click the "Company" tab (`#tab-company`).
- **Expect (spec)**: `#panel-company img.listora-detail__field-image` with alt "Company Logo" and a working `src` (the medium size).
- **Observed 2026-10-12**: `#panel-company` contains an empty `<dl class="listora-detail__field-list"></dl>`; no `img`, no `.listora-detail__field-item--file`. Cause: the loop asks `wb_listora_field_display( $field, $meta, $type, true )` first (`tabs.php:292`) and that returns `value: ""` for `file` fields (`includes/class-template-helpers.php:1390`; confirmed with `wb_listora_field_display()` and `wb_listora_format_card_value()` both returning `""`), so the `continue` at `:294-296` fires before the file branch at `:305` can run. Product bug; logo never shows on the listing page.

### 4. Other field types still render on Overview (control)
- **Expect**: text / select / url type fields still render with label and value (observed: "Company Name" row).

### 5. Role ladder
Public content, identical for logged-out, member, moderator and admin; owner additionally sees the owner bar. Verify once logged out and once as admin that neither shows the raw ID.

### 6. 390px
- **Expect**: logo `img` is max 100% width, no horizontal scroll.

## Fail diagnostics
| Symptom | File |
|---|---|
| Raw attachment ID visible on Overview | `templates/blocks/listing-detail/tabs.php:184-190` file skip removed |
| Logo missing on its own tab | `tabs.php:290-296` empty-display `continue` before the `file` branch `:305`; `includes/class-template-helpers.php:1390` `wb_listora_field_display()` has no `file` renderer |
| Logo missing everywhere after a "fix" | skip applied to the group tab as well |
