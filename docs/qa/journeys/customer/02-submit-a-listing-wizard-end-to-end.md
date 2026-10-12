---
journey: submit-a-listing-wizard-end-to-end
plugin: wb-listora
priority: critical
roles: [subscriber, contributor, logged-out, role-without-submit-cap, editor, admin]
covers: [submission-wizard, required-field-messages, featured-image-aria-required, terms-gate, plan-step-pro, credit-gate, submit-rest, login-gate]
prerequisites:
  - "Site reachable at $SITE_URL (Local: http://wb-listora.local)"
  - "Dev auto-login mu-plugin installed"
  - "Listing type `business` registered with at least one category (Automotive, Education, Finance, ...)"
  - "A subscriber with no listings (QA-J1-member). For the PRO path the member needs >= 10 credits (Standard Listing plan); Free-only sites have no Plan step"
  - "A small PNG/JPG fixture (any local file <= 1 MB)"
estimated_runtime_minutes: 7
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Submit a listing wizard, end to end

A logged-in member walks Add Listing (Type, Basic Info, Details, Media, Plan, Preview) and lands a `listora_listing` post with its `_listora_*` meta. Required fields say "This field is required." under the field, a missing featured photo and a missing Terms tick each block with their own message, and a logged-out visitor only sees the login gate.

Verified via: Playwright (isolated context per role, real file upload) plus curl and WP-CLI for REST and DB. Pro was active (credits + Standard Listing plan).

## Setup

- Add Listing page: `$SITE_URL/add-listing/` (page id 6; also the page `submission_page` in `wb_listora_settings`).
- Dashboard: `$SITE_URL/my-listings/`.
- Actors: `QA-J1-member` (subscriber), `contributor1`, `qa_editor`, `varundubey` (admin, control), a logged-out visitor, and a user whose role lacks `submit_listora_listing` (WooCommerce `customer` role: `QA-J1-nocap`).
- Titles used: `QA-J1 Wizard Listing`. Clean start: delete any post with that title and its attachment.
- Pro only: grant the member credits first (`\WBListoraPro\Features\Credit_System::add_credits( $uid, 15, 'fixture', 'admin' )`); remove them and the listing afterwards.

## Steps

### 1. Logged out sees the gate, not the form
- **Action**: open `$SITE_URL/add-listing/` with no session.
- **Expect**: `.listora-submission--login-required` shows `<h2>` "Add Your Listing", text "Please log in or create an account to submit a listing.", buttons "Log In" and "Create Account"; there is no `.listora-submission__form`.
- **API**: `POST /wp-json/listora/v1/submit` with no session returns `401` code `listora_unauthorized` ("Please log in to submit a listing.").
- **On fail**: `blocks/listing-submission/render.php:147-195`, `includes/rest/class-submission-controller.php:229`

### 2. Log in and open the wizard
- **Action**: `$SITE_URL/?autologin=QA-J1-member`, then open `/add-listing/`.
- **Expect**: `.wp-block-listora-listing-submission.listora-submission--wizard`; progress bar `.listora-submission__progress[role=progressbar]` with six indicators `data-step` = type, basic, details, media, plan, preview (Free-only sites have no `plan`); first step `h2` "What type of listing are you adding?"; the `type` indicator has `is-current` and `aria-current="step"`.
- **On fail**: `templates/blocks/listing-submission/stepper.php`, `step-type.php`

### 3. Pick a type, then Continue (it does NOT auto-advance)
- **Action**: check `input[name="listing_type"][value="business"]`, then click `.listora-submission__next` ("Continue").
- **Expect**: step `basic` visible, `h2` "Basic Information"; `#listora-title`, `#listora-category` and `#listora-description` are required; `#listora-category` first option "Select a category". The `type` indicator becomes `is-completed`; `aria-valuenow` is `2`.
- **On fail**: `src/blocks/listing-submission/view.js:154` (`selectSubmissionType`), `view.js:26` (`nextSubmissionStep`)

### 4. NEGATIVE: Continue with empty required fields
- **Action**: click Continue on the empty Basic step.
- **Expect**: the wizard stays on `basic`; three `.listora-submission__field-error` elements read "This field is required." (title, category, description). Nothing is sent over the network.
- **On fail**: `src/blocks/listing-submission/view.js:495` (`validateField`), `window.listoraI18n.requiredFieldError`

### 5. Fill Basic Info and continue
- **Action**: `#listora-title` = `QA-J1 Wizard Listing`; `#listora-category` = any real option (e.g. Automotive); `#listora-description` = `Journey wizard listing created by QA-J1.`; Continue.
- **Expect**: step `details`, `h2` "Details". The `Address` field `#listora-field-address` (`name="meta_address[address]"`) is the only required field; phone `#listora-field-phone`, email, website, business-hours rows `meta_business_hours[0..6]`, price range, year established, social links are optional.

### 6. NEGATIVE: Continue without the address
- **Action**: click Continue.
- **Expect**: stays on `details`; "This field is required." under the Address field.

### 7. Fill Details and continue to Media
- **Action**: `#listora-field-address` = `1 QA Street, Manhattan`; `#listora-field-address-city` = `Manhattan`; Continue.
- **Expect**: step `media`, `h2` "Photos & Media"; `.listora-submission__upload-trigger` ("Click to upload or drag & drop"), a button "Add Photos", `#listora-video`. The hidden featured input has `aria-required="true"` and `data-listora-required="featured_image"`.

### 8. NEGATIVE: Continue without a featured photo
- **Action**: click Continue.
- **Expect**: stays on `media`; `.listora-submission__field-error--featured-image` reads "Add a featured photo to continue."
- **On fail**: `templates/blocks/listing-submission/step-media.php:86`, `blocks/listing-submission/render.php` (`requiredFieldMessages`)

### 9. Upload the featured photo
- **Action**: click `.listora-submission__upload-trigger` and choose the fixture in the file chooser (the `<input type="file">` is created on click, it is not in the DOM before).
- **Expect**: network `POST /wp-json/wp/v2/media` returns `201`; the hidden `[data-listora-required="featured_image"]` input value becomes the attachment id; the error from step 8 clears; Continue now advances.
- **On fail**: `src/blocks/listing-submission/view.js:1219` (`openMediaForTarget`) and the REST uploader

### 10. Plan step (Pro: credits)
- **Action**: after Media, read the step.
- **Expect (member with 0-9 credits)**: `h2` "Choose a Listing Plan", "Your credit balance: N credits", one card "Standard Listing / 10 credits / Never expires" with class `listora-plan-card--unaffordable` and its radio `input[name="plan_id"]` `disabled`, text "Not enough credits." and a "Buy Credits" link.
- **Expect (member with >= 10 credits)**: the radio is enabled; click the card, `.listora-plan-card__radio` becomes `checked`; Continue.
- **POSITIVE CONTROL**: the enabled/affordable state above is what proves the "unaffordable" state is not simply "plans never selectable".

### 11. Preview, Terms gate
- **Action**: on `preview`, click `.listora-submission__submit-btn` ("Submit Listing") without ticking `input[name="agree_terms"]` ("I agree to the Terms of Service").
- **Expect**: stays on `preview`; `.listora-submission__field-error--agree-terms` reads "Please accept the Terms of Service to continue."; no POST. Preview shows the listing card, "Edit" links per section, a Plan section ("Standard Listing, Cost 10 credits") and "This listing will cost: 10 credits / Your balance: 15 credits / Remaining after submit: 5 credits".

### 12. Submit
- **Action**: tick Terms, click "Submit Listing".
- **Expect**: network `POST /wp-json/listora/v1/submit` returns `201` with `{ id, status, url, message }`. With Pro and an affordable plan: `status: "publish"`, `message: "Your listing is live."`. Free-only / manual moderation: `status: "pending"`, message "Your listing has been submitted and is pending review. We'll notify you once it's approved.". The form is replaced by `.listora-submission__success`: `h2` "Listing Submitted!", the server message, links "Go to Dashboard" (`/my-listings/`) and "Add Another Listing".
- **On fail**: `src/blocks/listing-submission/view.js:266` (`handleSubmission`), `includes/rest/class-submission-controller.php`

### 13. DB check
- **Action**: `SELECT ID, post_status, post_author, post_title FROM wp_posts WHERE post_type='listora_listing' AND post_title='QA-J1 Wizard Listing'`; then `get_post_meta` keys beginning `_listora_`; terms and thumbnail.
- **Expect**: exactly one row, `post_author` = the member; meta includes `_listora_address` (array with `address`, `city`), `_listora_business_hours`, `_listora_terms_accepted` (timestamp), and with a plan `_listora_plan_id`, `_listora_plan_activated`; terms `listora_listing_type=business` and the chosen `listora_listing_cat`; `_thumbnail_id` = the uploaded attachment; credit balance dropped by 10.

### 14. ROLE LADDER
| Actor | Action | Expect |
|---|---|---|
| Logged out | open Add Listing, POST `/submit` | login gate; `401 listora_unauthorized` |
| Role without `submit_listora_listing` (WooCommerce customer) | open Add Listing | `.listora-submission--no-permission`, `h2` "You cannot add listings", "Your account does not have permission to add listings on this site. If you think it should, please contact the site owner.", button "Go to My Dashboard" (`/my-listings/`); no form; `POST /submit` returns `401 listora_unauthorized`; the dashboard does not offer "Add Listing". |
| Subscriber, contributor, editor | steps 2-13 | same wizard; contributor/editor can submit (`submit_listora_listing` is true for subscriber..administrator) |
| Admin (control) | steps 2-13 | same result; proves it is the same wizard for every capable role |
| Another member / logged out | while the listing is pending (e.g. Free + manual moderation) open the link the dashboard uses, `$SITE_URL/?post_type=listora_listing&p=<ID>` | `404` for another member and for logged out; `200` for the owner (even a Subscriber), `qa_editor` and admin. The pretty `/listing/<slug>/` URL of a non-published listing is `404` for everyone, that is expected. |

### 15. Credit gate (Pro)
- **Action**: a member with 0 credits, default plan unaffordable: reach Preview, tick Terms, Submit.
- **Expect**: `POST /submit` returns `402` code `insufficient_credits` ("Submitting a listing costs 2 credits and you have 0. Buy credits, then submit again.", `data.credits_buy_url` = `/my-listings/?tab=credits`); the form stays, `.listora-submission__error` shows the message and a "Buy credits" link. With 5 credits and the plan still unaffordable the response is `400 listora_plan_required` ("Please select a listing plan to continue.").

### 16. Mobile
- **Action**: repeat steps 2-9 at 390x844.
- **Expect**: `scrollWidth <= 390`; Continue / Back / Save Draft are at least 40px tall; the type cards stack; the stepper does not overflow.

## Pass criteria

1. Logged out gets the login gate and a 401 from the API.
2. Type pick needs an explicit Continue.
3. Empty required fields show "This field is required." and block advancing (basic, details).
4. Featured photo missing blocks with "Add a featured photo to continue."; the hidden input has `aria-required="true"`.
5. Terms unticked blocks with "Please accept the Terms of Service to continue."
6. `POST /submit` returns 201 and the success card reads "Listing Submitted!" with the server's outcome message.
7. One post with the expected meta/terms/thumbnail; credits deducted by the plan cost (Pro).
8. A role without the capability sees "You cannot add listings".

## Not verified

- Free-only (no Pro) wizard: the Plan step and credit messages are Pro (`includes/features/class-pricing-plans.php`); the Free-only step list was read in `templates/blocks/listing-submission/` but not walked on a Free-only site.
- Draft resume (`Save Draft`) is covered by the dashboard drafts journey, not here.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Type pick does nothing | handler not bound / type grid markup changed | `templates/blocks/listing-submission/step-type.php`, `src/blocks/listing-submission/view.js:154` |
| No "This field is required." text | message map not localised | `src/blocks/listing-submission/view.js:495`, `includes/class-assets.php` (`requiredFieldError`) |
| Featured-photo guard missing | input lost `aria-required` / `data-listora-required` | `templates/blocks/listing-submission/step-media.php:86` |
| Submit 401 as a logged-in member | role lacks the cap | `includes/rest/class-submission-controller.php:229`, `includes/core/class-capabilities.php:146` |
| Submit 402 / 400 plan_required | credits or plan not chosen (Pro) | `wb-listora-pro/includes/features/class-pricing-plans.php`, `includes/features/class-credit-system.php` |
| 201 but no row / no meta | meta handler | `includes/rest/class-submission-controller.php`, `includes/core/class-meta-handler.php` |
| Success card says "Listing Updated!" for a new submission | draft-submitted copy regression | `src/blocks/listing-submission/view.js:350-375` |
