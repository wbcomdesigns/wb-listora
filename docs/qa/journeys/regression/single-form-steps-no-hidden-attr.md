---
journey: single-form-steps-no-hidden-attr
plugin: wb-listora
priority: critical
roles: [subscriber, administrator]
covers: [submission-single-form-layout, submission-step-visibility, agree-terms-validation, dashboard-inline-edit, custom-required-pattern]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listing owned by the test user exists (capture as LISTING_ID)"
  - "Terms checkbox enabled (Settings -> Submission, default ON)"
estimated_runtime_minutes: 6
covers_card: 10153910549
covers_commits: [1.3.1-submission-hidden-attr]
---

# Single-form submission never emits `hidden` on its steps

Regression sentinel for the "Update Listing does nothing" bug.

Single-form layout (the default in dashboard edit mode) used to render every
step with the `hidden` attribute and then fight it from CSS:
`.listora-submission--single-form .listora-submission__step[hidden] { display: block }`.
That override only wins while nothing outranks it. Any theme shipping
`[hidden] { display: none !important }` — common in normalize/reset bundles —
wins outright, every step collapses, and the `agree_terms` checkbox inside the
preview step becomes unreachable.

Combined with `agree_terms` using the **native** `required` attribute, that
produced total silence: native constraint validation runs before the block's
own submit handler, refuses to submit an unfocusable required control, and
emits no message, no console entry, and no network request. The listing could
never be edited.

The fix removes the cascade fight entirely — single-form does not emit `hidden`
at all — and moves `agree_terms` onto the block's own `data-listora-required`
pattern (the same one `featured_image` uses in `step-media.php`).

## Setup

- `blocks/listing-submission/render.php` passes `is_single_form` into `$view_data`.
- Each step template emits `hidden` only when `empty( $is_single_form )`, so
  wizard mode is byte-identical to before and single-form emits nothing.
- The CSS override rule is retained purely as a safety net for stale theme
  template overrides that still hardcode `hidden`.

## Steps

### 1. Single-form (dashboard edit) emits no `hidden`
- **Action**: Open `$SITE_URL/my-listings/?tab=listings&action=edit&id=LISTING_ID`.
  In the console, evaluate:
  `[...document.querySelectorAll('.listora-submission__step')].map(s => [s.dataset.step, s.hasAttribute('hidden')])`
- **Expect**: wrapper carries `listora-submission--single-form`; **every** step
  reports `false` for the hidden attribute and computes `display: block`.
- **Fail means**: a step template regressed to a hardcoded `hidden`.

### 2. Survives a hostile theme reset
- **Action**: Inject `[hidden]{display:none!important}` into the page, then
  re-read every step's computed `display`.
- **Expect**: all steps still `block`; the `agree_terms` checkbox still has a
  non-null `offsetParent`; `form.checkValidity()` is `true`.
- **Fail means**: the markup is emitting `hidden` again and the plugin is once
  more depending on winning a CSS cascade it does not control.

### 3. Terms uses custom validation, not native `required`
- **Action**: Evaluate `document.querySelector('input[name="agree_terms"]').required`
  and `.dataset.listoraRequired`.
- **Expect**: `required === false`, `listoraRequired === 'agree_terms'`.
- **Fail means**: native constraint validation is back and can silently block
  submission whenever the control is not rendered.

### 4. Negative path is visible, not silent
- **Action**: Without ticking terms, click **Update Listing**.
- **Expect**: no navigation and no POST; `.listora-submission__field-error--agree-terms`
  un-hides with "Please accept the Terms of Service to continue." in
  `--listora-danger`; the field wrapper gains `is-invalid`; the checkbox shows a
  2px danger outline.
- **Fail means**: the `is-invalid` styling or the `requiredFieldMessages` entry
  was dropped — the validator would refuse to submit while showing the user
  nothing.

### 5. Happy path actually saves
- **Action**: Tick terms, click **Update Listing**.
- **Expect**: `POST /wp-json/listora/v1/submit` returns 200; the listing's
  `post_modified` advances (`wp post get LISTING_ID --field=post_modified`);
  no new plugin lines in `wp-content/debug.log`.
- **Fail means**: the submit handler is blocked again — re-check steps 1-3.

### 6. Wizard mode is unchanged
- **Action**: Open `$SITE_URL/add-listing/` (wizard layout) and re-read the step
  hidden attributes plus the progress indicator.
- **Expect**: only the first step (`type`) is visible; `basic` / `details` /
  `media` / `preview` all carry `hidden` and compute `display: none`; the
  stepper is visible.
- **Fail means**: the `is_single_form` guard leaked into wizard mode and broke
  step-by-step navigation.

## Likely files

- `blocks/listing-submission/render.php` (`is_single_form` in `$view_data`)
- `templates/blocks/listing-submission/step-{basic,details,media,preview}.php`
- `templates/blocks/listing-submission/step-preview.php` (`agree_terms` input + error element)
- `includes/class-assets.php` (`requiredFieldMessages.agree_terms`)
- `blocks/listing-submission/style.css` + `style-rtl.css` (`is-invalid`, `field-error:not([hidden])`)
- `src/blocks/listing-submission/view.js` (`validateStep()` custom-required loop)

## Notes

`agree_terms` is a **client-side** gate only — no server-side enforcement exists
in the submission controller. That is pre-existing behaviour and unchanged by
this fix; adding server-side enforcement would change the REST contract and
belongs in its own card.
