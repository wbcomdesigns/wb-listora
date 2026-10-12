---
journey: media-step-field-prompt
plugin: wb-listora
priority: normal
roles: [member]
covers: ["field-aware validation copy", "media step abandonment friction"]
prerequisites:
  - "A member login (author1) and a listing type with a required featured image (default for new listings)"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# The Media step says what to do, not "required"

Custom-required submission fields are hidden inputs with `data-listora-required="<ctx>"` (`templates/blocks/listing-submission/step-media.php:86`). `listoraI18n.requiredFieldMessages` (`includes/class-assets.php:274`, filter `wb_listora_required_field_messages`) holds `featured_image` "Add a featured photo to continue.", `gallery` "Add at least one photo to continue.", `agree_terms` "Please accept the Terms of Service to continue."; the generic "This field is required." (`requiredFieldError`) is the last fallback. Logic: `src/blocks/listing-submission/view.js:1763-1800`.

Verified via: Playwright as author1 at 1440 (Event not needed; Restaurant used). Photo upload itself was simulated by setting the hidden input, not by uploading a file.

## Steps

### 1. Featured image prompt
- **Action**: `/add-listing/` > pick a type > Continue > Title, Category, Description > Continue (Details) > Continue to the Media step; click Continue with no photo.
- **Expect**: `p.listora-submission__field-error--featured-image[role=alert]` becomes visible reading "Add a featured photo to continue." (NOT "This field is required."); its `.listora-submission__field` wrapper gets `is-invalid`; focus lands on `button.listora-submission__upload-trigger`; the wizard stays on the Media step.

### 2. Recovery
- **Action**: choose a featured photo.
- **Expect**: the error hides on change (`hidden`) and Continue advances to the Plan step.

### 3. Filter override
- **Action**: `add_filter( 'wb_listora_required_field_messages', fn( $m ) => array_merge( $m, array( 'featured_image' => 'QA-J1 custom' ) ) );`
- **Expect**: step 1 shows "QA-J1 custom".

### 4. Generic fallback (control)
- **Action**: leave a required text field empty on the Basic step (title) and click Continue.
- **Expect**: that field shows "This field is required." (`p.listora-submission__field-error`, `role=alert`, `aria-invalid` on the input) - the generic copy still exists where no field-aware prompt is defined.

### 5. Role ladder
Member: as above. Logged out: Add Listing shows the sign-in prompt, no wizard. Admin (control): same wizard. Subscriber role without submit capability: the "no permission" message with a My Dashboard link (2.0.0), no wizard.

### 6. 390px
- **Expect**: error visible under the upload zone without scroll jump, upload trigger >= 40px, no horizontal page scroll.

## Fail diagnostics
`includes/class-assets.php:274-281` (map), `src/blocks/listing-submission/view.js:1776-1788` (lookup), `templates/blocks/listing-submission/step-media.php:86-87` (data attribute + error element).

## Cleanup
The wizard autosaves a draft listing once a Title is typed (observed during verification). Delete any `QA-J1 ...` draft the walk leaves behind.
