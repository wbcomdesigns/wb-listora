---
journey: submit-a-listing-wizard-end-to-end
plugin: wb-listora
priority: critical
roles: [subscriber, contributor, guest, role-without-submit-cap, editor, admin]
covers: [submission-wizard, required-field-messages, featured-image-aria-required, terms-gate, plan-step-pro, submit-rest, login-gate, moderation-success-text, category-tree, wizard-reload, social-links-gate, field-aware-validation-copy, bc-10355776893, bc-10354810033, bc-10350859556, bc-10351182660, bc-10393557323, bc-10393575233]
prerequisites:
  - "Site reachable at $SITE_URL; dev auto-login mu-plugin installed"
  - "Listing type {type} registered with at least one category {cat}; one fixture member {owner} (subscriber) with no listings"
  - "A small PNG/JPG fixture (<= 1 MB)"
  - "Pro path only: {owner} holds at least the cost of the Standard plan (derive cost from the plan, see Setup)"
estimated_runtime_minutes: 9
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
site_profile: required
supersedes:
  - "ora:regression/media-step-field-prompt.md"
fails_on: [aef67de3, d2f9d6eb, 767dfe31, e04d632a, c43ce51f, 16bbd3b7, d7aa3821]
---

# A member walks the Add Listing wizard and lands a listing; every gate says why it blocks

## Why
A member who cannot tell which field blocks Continue, or whose submit succeeds with the wrong wording, abandons the listing. Logged-out and no-permission users must see the right gate, never a broken form.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Add Listing page = `wb_listora_get_page_url( 'submission' )`; dashboard = `wb_listora_get_dashboard_url()`.
- Actors: {owner} (subscriber), {other} (contributor), {editor}, {admin} (control), {guest}, and {nocap} (a role lacking `submit_listora_listing`, e.g. WooCommerce customer).
- Title used: `QA-J1 Wizard Listing`. Clean start: delete any post with that title and its attachment.
- Pro only: read the plan cost `$COST` and the balance `$BALANCE` (`\WBListoraPro\Features\Credit_System`); top up `{owner}` with `add_credits( $uid, $COST + 5, 'fixture', 'admin' )`; remove the credits and the listing afterwards.
- Credit-refusal steps (402) live in `customer/submission-cost-charged-once.md`; drafts live in `customer/submission-save-draft-and-resume.md`; step-rendering invariants (empty fieldset, width, optional category, upload limit) in `customer/submission-step-rendering-invariants.md`.

## Steps

### 1. Logged out sees the gate, not the form
- **Action**: as {guest} open the Add Listing page.
- **Expect**: `.listora-submission--login-required` shows `<h2>` "Add Your Listing", text "Please log in or create an account to submit a listing.", buttons "Log In" and "Create Account"; no `.listora-submission__form`.
- **Read-back**: `POST /wp-json/listora/v1/submit` with no session returns `401` `listora_unauthorized` ("Please log in to submit a listing.").
- **On fail**: `blocks/listing-submission/render.php:147-195`, `includes/rest/class-submission-controller.php:229`

### 2. Log in and open the wizard
- **Action**: autologin as {owner}, open the Add Listing page.
- **Expect**: `.listora-submission--wizard`; `.listora-submission__progress[role=progressbar]` with `data-step` indicators type, basic, details, media, plan, preview (Free-only sites have no `plan`); first `h2` "What type of listing are you adding?"; `type` indicator has `is-current` and `aria-current="step"`.
- **Read-back**: indicator count equals the step panels count in the DOM.

### 3. Pick a type, then Continue (no auto-advance)
- **Action**: check `input[name="listing_type"][value="{type}"]`, click `.listora-submission__next`.
- **Expect**: step `basic`, `h2` "Basic Information"; `#listora-title`, `#listora-category`, `#listora-description` required; category first option "Select a category"; `type` indicator `is-completed`, `aria-valuenow` = 2.
- **On fail**: `src/blocks/listing-submission/view.js:154`, `:26`

### 4. NEGATIVE: Continue with empty required fields
- **Action**: click Continue on the empty Basic step.
- **Expect**: stays on `basic`; three `.listora-submission__field-error` read "This field is required." (title, category, description); each input has `aria-invalid="true"`; no network request.
- **Read-back**: `[data-listora-field-error]` count equals the number of empty required fields (bc-10393557323).
- **On fail**: `view.js:495`, `window.listoraI18n.requiredFieldError`

### 5. Fill Basic Info and continue
- **Action**: title `QA-J1 Wizard Listing`, category = first real option of {type}, description `Journey wizard listing created by QA.`; Continue.
- **Expect**: step `details`, `h2` "Details"; the Address field `#listora-field-address` (`name="meta_address[address]"`) is the only required field.

### 6. NEGATIVE: Continue without the address
- **Action**: click Continue.
- **Expect**: stays on `details`; "This field is required." under Address.

### 7. Fill Details and continue to Media
- **Action**: address `1 QA Street`, city `QA City`; Continue.
- **Expect**: step `media`, `h2` "Photos & Media"; `.listora-submission__upload-trigger`, button "Add Photos", `#listora-video`; the hidden featured input has `aria-required="true"` and `data-listora-required="featured_image"`.

### 8. NEGATIVE: Continue without a featured photo (field-aware prompt)
- **Action**: click Continue with no photo.
- **Expect**: stays on `media`; `p.listora-submission__field-error--featured-image[role=alert]` reads "Add a featured photo to continue." (not "This field is required."); its `.listora-submission__field` gets `is-invalid`; focus lands on `button.listora-submission__upload-trigger`.
- **On fail**: `templates/blocks/listing-submission/step-media.php:86-87`, `includes/class-assets.php:274-281`, `view.js:1763-1800`

### 9. Upload the featured photo (recovery)
- **Action**: click `.listora-submission__upload-trigger`, choose the fixture (the `<input type=file>` is created on click).
- **Expect**: `POST /wp-json/wp/v2/media` returns `201`; the hidden `[data-listora-required="featured_image"]` value becomes the attachment id; the step 8 error hides; Continue advances.
- **On fail**: `view.js:1219` (`openMediaForTarget`)

### 10. Field-aware messages are filterable, generic copy is the fallback
- **Action**: mu-plugin `add_filter( 'wb_listora_required_field_messages', fn( $m ) => array_merge( $m, array( 'featured_image' => 'QA custom' ) ) );`, repeat step 8; then remove it and leave the Basic title empty and Continue.
- **Expect**: step 8 error reads "QA custom"; the empty title shows the generic "This field is required." (`p.listora-submission__field-error[role=alert]`, `aria-invalid` on the input).
- **Read-back**: `listoraI18n.requiredFieldMessages` contains `featured_image`, `gallery` ("Add at least one photo to continue.") and `agree_terms`.

### 11. Plan step (Pro: credits)
- **Action**: after Media read the plan step.
- **Expect (balance below cost)**: `h2` "Choose a Listing Plan", "Your credit balance: $BALANCE credits", a card "Standard Listing / $COST credits / Never expires" with `listora-plan-card--unaffordable`, its `input[name="plan_id"]` `disabled`, "Not enough credits." and a "Buy Credits" link.
- **Expect (balance >= cost)**: the radio is enabled; click the card, `.listora-plan-card__radio` is `checked`; Continue.
- **Read-back**: positive control: the affordable state proves "unaffordable" is not "plans never selectable".

### 12. Preview and the Terms gate
- **Action**: on `preview` click `.listora-submission__submit-btn` without ticking `input[name="agree_terms"]`.
- **Expect**: stays on `preview`; `.listora-submission__field-error--agree-terms` reads "Please accept the Terms of Service to continue."; no POST. Preview shows the listing card, per-section "Edit" links, and (Pro) "This listing will cost: $COST credits / Your balance: $BALANCE credits / Remaining after submit: {$BALANCE-$COST} credits".

### 13. Submit
- **Action**: tick Terms, click "Submit Listing".
- **Expect**: `POST /wp-json/listora/v1/submit` returns `201` `{ id, status, url, message }`; form replaced by `.listora-submission__success` with `h2` "Listing Submitted!", the server message, links "Go to Dashboard" and "Add Another Listing".
- **Read-back**: see step 14 for the DB check; the message text per moderation setting is step 18.
- **On fail**: `view.js:266` (`handleSubmission`), `includes/rest/class-submission-controller.php`

### 14. DB read-back
- **Action**: `SELECT ID, post_status, post_author, post_title FROM wp_posts WHERE post_type='listora_listing' AND post_title='QA-J1 Wizard Listing'`; then `_listora_` meta, terms, thumbnail.
- **Expect**: exactly one row, `post_author` = {owner}; meta `_listora_address` (array with `address`, `city`), `_listora_business_hours`, `_listora_terms_accepted` (timestamp); with a plan `_listora_plan_id`, `_listora_plan_activated`; term `listora_listing_type={type}` and the chosen `listora_listing_cat`; `_thumbnail_id` = uploaded attachment; credit balance dropped by `$COST`.

### 15. Role ladder
| Actor | Action | Expect |
|---|---|---|
| {guest} | open Add Listing, `POST /submit` | login gate; `401 listora_unauthorized` |
| {nocap} | open Add Listing | `.listora-submission--no-permission`, `h2` "You cannot add listings", "Your account does not have permission to add listings on this site. If you think it should, please contact the site owner.", button "Go to My Dashboard"; no form; `POST /submit` returns `401 listora_unauthorized`; dashboard offers no "Add Listing" |
| subscriber, {other}, {editor} | steps 2-14 | same wizard; contributor and editor can submit (`submit_listora_listing` is true for subscriber..administrator) |
| {admin} (control) | steps 2-14 | same result |
- Opening a pending listing as another member / guest is covered in `customer/owner-opens-own-unpublished-listing.md` (not repeated).

### 16. Mobile
- **Action**: repeat steps 2-9 at 390x844.
- **Expect**: `scrollWidth <= 390`; Continue / Back / Save Draft >= 40px tall; type cards stack. The stepper geometry (fits 360/390/700/1280, more steps than fit, RTL) is `customer/submission-stepper-fits-phones.md`; the success card at 390 is `customer/submission-success-card-centered.md`.

### 17. Dark theme and unknown theme
- **Action**: repeat steps 2-4 in buddyx-pro dark and on the unknown default theme.
- **Expect**: error text, progress bar and type cards keep readable contrast and layout. Not verified live.

### 18. The success message matches the status that was written, for every moderation setting
- **Action**: as {owner} submit one listing per state of Settings > Moderation (`moderation` = `auto_approve`, then `manual`), fresh titles.
- **Expect**: `auto_approve`: `201` `status: "publish"`, card text "Your listing is live." with no "pending review". `manual`: `status: "pending"`, text "Your listing has been submitted and is pending review...". Never "pending review" with a published row, never "live" with a pending row.
- **Read-back**: `SELECT post_status FROM wp_posts WHERE ID=<response id>` equals the response `status`; negative control: the other setting produces the other text.
- **fails_on**: aef67de3 (parent of wording fix a48459e4; unconfirmed). Not verified live.

### 19. Category dropdown lists children of an allowed parent, indented and selectable
- **Action**: type {type} allows parent {cat} with children Child-A, Child-B (and a grandchild); open Basic as {owner}.
- **Expect**: `#listora-category` options include parent, Child-A, Child-B, grandchild in tree order with increasing depth indentation; selecting Child-A and submitting stores it.
- **Read-back**: `wp_get_object_terms( ID, 'listora_listing_cat', ['fields'=>'names'] )` = `["Child-A"]`; negative control: a category outside the allowed tree is absent.
- **fails_on**: d2f9d6eb (parent of e2923de5). Not verified live.

### 20. A page reload keeps the wizard data
- **Action**: as {owner} fill Type, Basic (title `QA-J1 Reload Draft`), Details (address), upload the featured photo, press F5.
- **Expect**: wizard reopens with title, category, description, address and featured photo restored (`#listora-title` value `QA-J1 Reload Draft`), on the step the member left.
- **Read-back**: `wp_posts` has one draft `QA-J1 Reload Draft` for {owner} (or a `_listora_draft` payload on the user); negative control: {other}'s session sees none of it.
- **fails_on**: 767dfe31 (parent of d2f9d6eb, autosave wiring). Not verified live.

### 21. Social Links fieldset absent when every social platform is disabled
- **Action**: Settings > Listing > Social platforms: untick every platform, Save; open Details of a type with a social field as {owner}.
- **Expect**: no `fieldset`/heading "Social Links" and no `meta_social_*` inputs. Positive control: re-enable one platform and exactly that one input appears.
- **Read-back**: `wp option get wb_listora_settings --format=json | jq .social_platforms` is `[]`.
- **fails_on**: e04d632a (parent of 299118df). Not verified live.

### 22. Draft wording and per-type features (bc-10393557323)
- **Action**: as {owner} save a draft, reopen it and submit; on a second run pick type A then type B and open Features.
- **Expect**: submitting a saved draft ends with the "updated" wording, not "submitted for review" twice; type A's Features list has no feature allowed only for type B.
- **Read-back**: `wp term meta get <type A id> _listora_allowed_features` equals the rendered options.
- **fails_on**: c43ce51f (wizard errors, e52a973f), 16bbd3b7 (feature scope, 21b9989d). Not verified live.

### 23. A term ID is not a listing type (bc-10393575233)
- **Action**: as {owner} (cookie + `X-WP-Nonce`) `TID=$(wp term list listora_listing_type --field=term_id | head -1)`; `POST /listora/v1/submit` `{"title":"QA bc term id","description":"x","listing_type":"<TID>"}`.
- **Expect**: `400 listora_listing_type_invalid`. Positive control: the same body with the slug of that type is 201.
- **Read-back**: `wp post list --post_type=listora_listing --s="QA bc term id" --format=count` is 0 for the refused call; no new type named after the ID.
- **fails_on**: d7aa3821. Not verified live.

## Role ladder
See step 15.

## Pass criteria
1. Logged out gets the login gate and a 401; a role without the cap gets "You cannot add listings".
2. Type pick needs an explicit Continue.
3. Empty required fields block with their own message (field-aware where defined, generic otherwise).
4. Terms unticked blocks with "Please accept the Terms of Service to continue."
5. `POST /submit` returns 201; the success card text matches the written status; one post with expected meta/terms/thumbnail.
6. Term IDs are refused as listing types.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Type pick does nothing | handler not bound / grid markup changed | `templates/blocks/listing-submission/step-type.php`, `view.js:154` |
| No "This field is required." text | message map not localised | `view.js:495`, `includes/class-assets.php` |
| Featured-photo guard missing | input lost `aria-required` / `data-listora-required` | `step-media.php:86` |
| Submit 401 as a member | role lacks cap | `class-submission-controller.php:229`, `includes/core/class-capabilities.php:146` |
| 201 but no row / meta | meta handler | `class-submission-controller.php`, `includes/core/class-meta-handler.php` |
| "Listing Updated!" on a new submission | draft-submitted copy | `view.js:350-375` |

## Not verified
- Free-only (no Pro) wizard: Plan step and credit text are Pro; Free-only step list read, not walked.
- Steps 17-23 are carried from earlier journeys or added in consolidation: not run live.

## Cleanup
Delete `QA-J1 ...` listings and attachments; remove granted credits; restore any Settings changed in steps 18 and 21.
