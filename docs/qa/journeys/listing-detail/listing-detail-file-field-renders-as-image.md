---
journey: listing-detail-file-field-renders-as-image
group: listing-detail
actor: customer
mode: mixed
plugin: wb-listora
priority: normal
roles: [anonymous, member-other, administrator]
covers: [listing-detail-tabs, file-field-skip-on-overview, file-field-image-on-group-tab, "#9867775853"]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listing of a type with a file field (Job: Company Logo, attachment id in `_listora_company_logo`; the demo data has none: seed one with prefix QA and an image attachment)"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unbridged default theme]
supersedes:
  - ora:regression/overview-company-logo-id.md
fails_on: step 2 sentinel for #9867775853 (pre-fix sha unknown); step 3 fails on the current build (expected-fail)
site_profile: required
---

# A file field never prints a raw attachment ID on Overview and renders as an image on its own tab

## Why
`templates/blocks/listing-detail/tabs.php` skips `file` fields in the Overview loop; the field's own group tab (Job: `company`, panel `#panel-company`) renders the image or a download link (`.listora-detail__field-item--file`, `img.listora-detail__field-image`). A visitor must never see "Company Logo: 1234".

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Capture `{listing}` URL.

## Steps

### 1. Open the listing
- **Action**: `GET` the permalink as {guest}.
- **Expect**: HTTP 200, Overview active (`#tab-overview[aria-selected=true]`), tabs `tab-overview`, `tab-company`, `tab-reviews`.

### 2. No raw attachment ID on Overview (critical)
- **Action**: `document.querySelector('#panel-overview').textContent`.
- **Expect**: no match for `/Company Logo:?\s*\d+/`.

### 3. The Company tab renders the logo as an image
- **Action**: click the "Company" tab (`#tab-company`).
- **Expect**: `#panel-company img.listora-detail__field-image` with alt "Company Logo" and a working `src` (medium size). `expected-fail: product bug`: today `#panel-company` holds an empty `<dl class="listora-detail__field-list"></dl>` because the loop asks `wb_listora_field_display( $field, $meta, $type, true )` first and that returns `""` for `file` fields, so the `continue` fires before the file branch (`tabs.php`, `includes/class-template-helpers.php` `wb_listora_field_display`). File the bug; the step stays.

### 4. Other field types still render on Overview (control)
- **Expect**: text / select / url fields render label and value (e.g. "Company Name").

### 5. A file field that is not an image (added)
- **Action**: set the file field to a PDF attachment, and to a deleted attachment id.
- **Expect**: PDF shows a download link; a missing attachment renders nothing, never the number or a broken image. Not verified live.

### 6. 390px
- **Expect**: logo `img` max 100% width, no horizontal scroll.

## Role ladder

| Rung | Expect |
|---|---|
| {guest}, {other}, {admin} | identical output; none shows the raw ID |
| owner | additionally sees the owner bar; still no raw ID |

## Pass criteria
No raw attachment ID on Overview for any role; the logo renders on its own tab (currently failing, tracked as `expected-fail`).

## Fail diagnostics

| Symptom | File |
|---|---|
| Raw ID on Overview | `templates/blocks/listing-detail/tabs.php` file skip removed |
| Logo missing on its own tab | `tabs.php` empty-display `continue` before the `file` branch; `wb_listora_field_display()` has no `file` renderer |
| Logo missing everywhere after a fix | skip applied to the group tab as well |

## Not verified
Steps 5; step 3 observed failing on 2026-10-12.

## Cleanup
Delete the QA listing and attachment.
