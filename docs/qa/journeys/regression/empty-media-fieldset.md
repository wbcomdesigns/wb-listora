---
journey: empty-media-fieldset
plugin: wb-listora
priority: high
roles: [subscriber, anonymous, admin]
covers: [listing-submission-step-details, fieldset-suppression]
prerequisites:
  - "Site reachable at http://wb-listora.local"
  - "tester (subscriber, user 15) can open /add-listing/"
  - "The 10 built-in types registered: Business, Classified, Education, Event, Healthcare, Hotel, Job, Place, Real Estate, Restaurant"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# No empty fieldset in the Details step

The Details step (`[data-step="details"]`, `templates/blocks/listing-submission/step-details.php`) renders one `<fieldset class="listora-submission__fieldset">` per field group of the chosen type. Groups whose every field is a `gallery` or `video` (rendered on the Media step) must be skipped (`step-details.php:37-48`), otherwise a bare `<legend>` shows.

Verified via: curl + DOM parse of `/add-listing/` as tester: 26 fieldsets, 0 empty. The wizard walk (steps 2-4 clicks) not run in a browser.

## Steps

### 1. Open Add Listing as a member
- **Action**: `?autologin=tester` then `/add-listing/`.
- **Expect**: step stepper (`.listora-submission__progress`), Type step first; type tiles for the 10 types above.

### 2. Server-rendered DOM check (all types at once)
- **Action**: `[...document.querySelectorAll('fieldset.listora-submission__fieldset')].filter(f => !f.querySelector('input,select,textarea,button')).length`.
- **Expect**: `0`. Legends seen across types: Business Details, Contact Information, Contact, Location, Media, Item Details, Seller Contact, Provider, Course Details, Schedule, Event Details, Recurrence, Medical Details, Hotel Details, Company, Job Details, Place Details, Property Details, Restaurant Details.

### 3. POSITIVE CONTROL: a Media fieldset that does render
- **Expect**: fieldsets titled "Media" exist with controls (verified: 4 hold 7 inputs, the `meta_social_links[...]` fields; 1 holds `meta_virtual_tour_url`). So suppression is not over-eager.

### 4. Walk the wizard per type
- **Action**: for each type: pick tile -> Next to Basic Info -> Next to Details; run the step 2 query on the visible step.
- **Expect**: 0 empty fieldsets for all 10 types and Next reaches "Photos & Media" (`data-step="media"`) with no console error.

### 5. Role ladder
- Member (tester) steps 1-4. Another member and editor same. Anonymous: `/add-listing/` shows "Please log in or create an account to submit a listing." with Log In / Create Account, no `.listora-submission__progress` (verified). Admin is the control; a role that cannot submit sees the no-permission message with a My Dashboard link.

### 6. 390px
- **Expect**: fieldsets stack, `scrollWidth <= 390`. Not run.

## Fail diagnostics

| Symptom | File |
|---|---|
| Empty fieldset | `templates/blocks/listing-submission/step-details.php:37-48` (`$skip_field_types = array( 'gallery', 'video' )`) |
| Media fieldset never renders | same file, `$has_renderable` loop; keep in sync with `wb_listora_render_submission_field()` |
| Wizard stuck on Type | `Listing_Type_Registry` |
