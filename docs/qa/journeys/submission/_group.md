---
group: submission
plugin: wb-listora
scope: Creating and editing a listing from the frontend - the Add Listing wizard and single-page form, every gate in front of it (login, permission, feature off, terms), drafts, media and field widgets, the success card, owner edit and edit permission, and the submit REST family behind it
journeys: 23
site_profile: required
---
# Submission: what an agent checks here

## Scope
Covers the Listing Submission block (`blocks/listing-submission/render.php`, `templates/blocks/listing-submission/*.php`, `src/blocks/listing-submission/view.js`, `src/utils/map-picker.js`, field widgets in `includes/submission-field-renderer.php`), the same form rendered inline in the dashboard for adding and editing (`templates/blocks/user-dashboard/tab-listings.php`, `layoutMode: single-form`), the REST family `POST /listora/v1/submit`, `PUT /submit/{id}`, `POST /submit/check-duplicate` (`includes/rest/class-submission-controller.php`), the Settings > Submissions tab, the Terms and Privacy legal rows, and the owner's access to their own non-public listing.

Does NOT cover (neighbouring owner in brackets):
- Pricing plans, the plan step and credit packs, expiry and renewal (plans-expiry-renewal, credits-checkout). Here only "cost is charged once" and the Buy Credits round trip are checked.
- Moderation queue, approve/reject, notification emails to staff (admin-listings, emails-alerts).
- The rest of the dashboard (tabs, listing rows, menus) (dashboard-account).
- The listing page after publishing (listing-detail), except the owner opening their own non-public listing, which starts at the dashboard here.
- Map engine and tiles behind the picker (map); the picker's search combobox and stacking are checked here.
- Importers and demo data (import-migration); they are compared with the form only for description markup and hours shapes.
- Rate limits and spam layers in general (access-security); the honeypot and the draft-vs-new limit are checked here.
- Type Editor screens in general (settings-setup); only `submission_enabled`, allowed categories and field groups are exercised from here.

## Surfaces
| # | Surface | URL / how to reach | Roles that see it | Rendered by (file) |
|---|---|---|---|---|
| S1 | Add Listing, logged out | `$SITE_URL{add_listing}` as guest | guest | `render.php` (login-required branch) |
| S2 | Add Listing, member without `submit_listora_listing` | `$SITE_URL{add_listing}` as {nocap} | {nocap} | `render.php` (no-permission branch) |
| S3 | Add Listing, submissions switched off | Settings > Features > Listing Submission off, then `{add_listing}` | all | `render.php` (closed empty state) |
| S4 | Add Listing, no listing type accepts submissions | every type has `submission_enabled` off | {owner}, {admin} | `render.php` (unavailable branch) |
| S5 | Wizard step 1 "Type" | `{add_listing}` as {owner} with 2+ submittable types | {owner} | `step-type.php` |
| S6 | Wizard step "Basic Info" | next step, or first step when one type | {owner} | `step-basic.php` |
| S7 | Wizard step "Details" | next | {owner} | `step-details.php`, `submission-field-renderer.php` |
| S8 | Wizard step "Media" | next | {owner} | `step-media.php` |
| S9 | Wizard step "Preview" (terms, credits banner) | next | {owner} | `step-preview.php` |
| S10 | Duplicate review panel | submit a title/address matching an existing listing | {owner} | `step-duplicate-review.php` |
| S11 | Success card, new listing | after Submit | {owner} | `submission.php` (`.listora-submission__success`) |
| S12 | Single-page form (setting or block attribute) | Settings > Submissions "Single page form", or dashboard Add | {owner} | `render.php` layout resolution, same step templates |
| S13 | Edit form, standalone | `{add_listing}?edit={listing_id}` | {owner}, staff (via dashboard link) | `render.php` edit-mode branch |
| S14 | Edit form, inside dashboard | `{dashboard}?tab=listings&action=edit&id={listing_id}` | {owner} | `tab-listings.php` inline block |
| S15 | Edit success card | after Update Listing | {owner} | `submission.php` |
| S16 | Draft resume / Buy credits round trip | Save Draft then Buy More Credits and return | {owner} | `view.js` draft logic, credits banner |
| S17 | Email verification landing | `/?listora_verify_email=...` | (guest path removed; see Known defects) | `includes/workflow/class-email-verification.php` |
| S18 | Settings > Submissions | `$SITE_URL/wp-admin/admin.php?page=listora-settings&tab=submissions` | {admin} | `includes/admin/class-settings-page.php` `render_submissions_tab` |
| S19 | Settings > Advanced "Mobile app: legal links" | `.../admin.php?page=listora-settings&tab=advanced` (journey names the General tab) | {admin} | `includes/admin/class-advanced-settings-tab.php` |
| S20 | Owner opens own non-public listing | `$SITE_URL/listing/{listing}/` for draft / pending / rejected / expired / unpaid, as {owner} | {owner} | `blocks/listing-detail/render.php`, `includes/core/class-post-types.php` access rule |
| S21 | REST | `POST|PUT /wp-json/listora/v1/submit...`, `POST /submit/check-duplicate`, `POST /wp/v2/media` (uploads) | per cap | `class-submission-controller.php` |
| S22 | wp-admin Type Editor | `.../admin.php?page=listora-listing-types` (submission_enabled, categories, field groups) | {admin} | `includes/admin/class-type-editor.php` |

## Components on each surface
Check every item on every surface where it appears; this is the MICRO_CHECKS sweep list.

**Gates (S1-S4)** wrapper `.listora-submission` with a modifier. S1 `--login-required`: `.listora-submission__login-prompt` with h2 "Add Your Listing", text "Please log in or create an account to submit a listing.", buttons "Log In" and "Create Account" (the latter filterable, hidden when the filter returns empty). S2 `--no-permission`: lock icon, h2 "You cannot add listings", message (filter `wb_listora_submission_no_permission_message`), button "Go to My Dashboard". S3: shared empty state `.listora-submission__closed` "New listings are closed". S4 `--unavailable`: h2 "Submissions are not open", text, and for users with `manage_listora_types` the owner hint to enable a type.

**Form shell (S5-S13)** `.listora-submission.listora-page--booking|--list` (`--wizard` or `--single-form`), `form.listora-submission__form[novalidate]` with hidden `listing_type`, `listing_id`, `agree_terms` (edit), and a honeypot `.listora-honeypot input[name=listora_hp_field]` (aria-hidden, tabindex -1).
1. Progress `.listora-submission__progress[role=progressbar][aria-valuemin=1][aria-valuemax=N]` > `.listora-submission__step-indicator[data-step]` (current `.is-current[aria-current=step]`) with `.listora-submission__step-dot`, `.listora-submission__step-num`, `.listora-submission__step-check`, `.listora-submission__step-label`, and `.listora-submission__step-line`. Steps: Type (only with 2+ types and no preset), Basic Info, Details, Media, Preview, plus any added by `wb_listora_submission_steps` (Pro plan step).
2. Step panels `.listora-submission__step[data-step=type|basic|details|media|preview]`, non-current panels `hidden`.
3. Navigation `.listora-submission__nav`: Back (`.listora-submission__back`, hidden on step 1), Save Draft (`.listora-submission__save-draft`), autosave indicator `.listora-submission__autosave[aria-live=polite]`, Continue (`.listora-submission__next`), Submit Listing / Update Listing (`.listora-submission__submit-btn`, shown on the last step).
4. Error banner `.listora-submission__error[role=alert]`; success card `.listora-submission__success`.

**Type step (S5)** h2 "What type of listing are you adding?"; `.listora-submission__type-grid` > `label.listora-submission__type-card` with a radio `name=listing_type` and `.listora-submission__type-card-inner.listora-type--{slug}` (icon, `.listora-submission__type-name`); the owner-configured default type is pre-checked but does not hide the step.

**Basic step (S6)** h2 "Basic Information" (edit: "Edit Basic Information"): `#listora-title` (required), `#listora-category` select (first option "Select a category", children of an allowed parent indented), tags `.listora-tags-input` (chips `[data-listora-tags-chips]`, `#listora-tags`, hint "Press Enter or a comma after each tag."), Features & Amenities `.listora-submission__checkbox-grid` (`name=features[]`), `#listora-description` (required textarea, 6 rows).

**Details step (S7)** h2 "Details", placeholder "Select a listing type above to see the fields for that type." until a type is mounted, then per field group `fieldset.listora-submission__fieldset` > `legend.listora-submission__fieldset-legend` and field widgets by type: text, textarea, number, price (`.listora-submission__price-field`, currency prefix), select, multiselect, radio, checkbox group, toggle, date, time, datetime, url, email, phone, color, file (`.listora-submission__upload-zone--small`), gallery, video, social links (`.listora-submission__social-links` rows), map location (`.listora-submission__map-field`: address input, picker, coordinates, City, State / Region, ZIP / Postal Code), business hours (`.listora-submission__hours-builder`: per day `.listora-submission__hours-day`, toggle `.listora-submission__hours-toggle`, ranges `.listora-submission__hours-ranges` with `.listora-submission__hours-input` pickers, add `.listora-submission__hours-add`, remove `.listora-submission__hours-remove`, copy-to-other-days `.listora-submission__hours-copy`, "Closed" state). Conditional fields carry `.listora-submission__field--conditional-hidden` when hidden.

**Media step (S8)** h2 "Photos & Media": Featured Image (required asterisk plus sr-only "(required)"), `.listora-submission__upload-zone` with button `.listora-submission__upload-trigger` ("Click to upload or drag & drop", hint "Up to {server max}, JPG/PNG/WebP"), preview `img.listora-submission__media-preview`, actions Replace / Remove (`.listora-submission__media-actions`), error `.listora-submission__field-error--featured-image[role=alert]`, hidden `featured_image`; Gallery "Gallery (up to N photos)" with `.listora-submission__gallery-thumbs#listora-gallery-thumbs` (per thumb remove button and move earlier/later buttons), "Add Photos" button, hidden `gallery`; `#listora-video` ("Video URL (optional)").

**Preview step (S9)** h2 "Preview Your Listing", `.listora-submission__preview-card#listora-preview-content` (placeholder "Preview will appear here after filling in the form."), credits banner `.listora-submission__credit-*` ("This listing will cost: N", "Your balance: N", "Remaining after submit: N", "Buy More Credits") only when credits are on, terms row `.listora-submission__terms` with `input[name=agree_terms]` + link "Terms of Service" and `.listora-submission__field-error--agree-terms`.

**Duplicate review (S10)** `.listora-submission__duplicate-review[hidden]`: h2 "We found similar listings - is yours different?", `ul.listora-submission__duplicate-list[aria-live=polite]`, checkbox `listora_dup_confirm` ("I confirm this is a different business, not a duplicate of the above"), textarea `listora_dup_explanation` (at least 20 characters), error `[role=alert]`, buttons "Cancel - change my listing" and "Submit anyway".

**Success (S11, S15)** `.listora-submission__success`: icon, h2 "Listing Submitted!" (or "Listing Updated!"), text ("Your listing has been submitted and is pending review..." or the published wording), actions "Go to Dashboard" (registered Dashboard URL), "Add Another Listing" / "View Listing".

**Settings > Submissions (S18)** "Submission Rules" block: Moderation radios (`moderation` manual / auto_approve), Submission form style radios (`submission_form_style` wizard / single_form) with the note on block attribute and dashboard behaviour, Max file size (read-only, server value), `#max_gallery_images`, `#captcha_provider` select, `#captcha_site_key`, `#captcha_secret_key`; "Social Links" block with platform checkboxes (`social_platforms[]`).

**Legal rows (S19)** `#legal_privacy_policy_url` (read-only, with Publish it link when the page is a draft), `#legal_terms_page_id` (page select listing drafts), `#legal_terms_url`.

## States to force
| Surface | State | How |
|---|---|---|
| S1 | Logged out | no session; also `wb_listora_submission_register_url` filter returning empty |
| S2 | Member without capability | `wp user remove-cap {nocap} submit_listora_listing` (restore afterwards) |
| S3 | Feature off | Settings > Features > Listing Submission off; a verified owner edit still works (S13) |
| S4 | No submittable type | uncheck submission on every type |
| S5-S9 | One type vs several | disable all but one type: Type step skipped and type applied; default type set vs not |
| S6 | Type without categories / with categories / nested | Type Editor allowed categories |
| S7 | Every field kind | a QA type with one field of each widget; required and optional; conditional fields |
| S7 | Hours: 1, 2, max ranges | business hours builder to the cap; remove the middle range |
| S8 | Server upload limit | mixed sizes around `wp_max_upload_size()` (a 10 MB server fixture) |
| S8 | Non-image, oversized, drag and drop, keyboard | drop a PDF; Enter/Space on the trigger |
| S9 | Terms unaccepted / accepted; credits on and short balance | `show_terms`, credits fixtures |
| S10 | Duplicate title/address | submit the same title and address as an existing published listing |
| S11 | Moderation manual vs auto_approve | Settings > Submissions moderation; success text differs |
| S12/S14 | Single form vs wizard, dashboard | Settings > Submissions style; block attribute `layoutMode`; dashboard Add |
| S13 | Edit own / other's / staff / nonexistent | `?edit=` with each id and role |
| S16 | Save Draft, reload, return from checkout | Save Draft then `Buy More Credits`, complete an order, return URL |
| S20 | Each non-public status | draft, pending, rejected, expired, paused, unpaid, trashed |
| any | Failed request, double click, two tabs | throttle/block `POST /submit`; double click Submit; submit from two tabs |
| any | 390, 360, 820, 1440; dark; unbridged theme; RTL | per matrix |

## Settings that change this group
| Setting (option key / admin location) | Values | What must visibly change, where |
|---|---|---|
| Settings > Features > Listing Submission (`submission` feature) | on / off | S3 closed state on `{add_listing}`; `POST /submit` refused for every role including admin; owner edit unaffected |
| Settings > Submissions > Moderation (`moderation`) | manual, auto_approve | success text, resulting `post_status` (pending vs publish), owner email |
| Settings > Submissions > Submission form style (`submission_form_style`) | wizard, single_form | S12 vs S5-S9 on the standalone page; dashboard always single form; explicit block `layoutMode` wins |
| Settings > Submissions > Max gallery images (`max_gallery_images`) | integer | Gallery label "Gallery (up to N photos)" and the cap enforced client and server side |
| Settings > Submissions > CAPTCHA provider and keys | none, reCAPTCHA v3, Turnstile | widget script loaded on the form, refusal without a token |
| Settings > Submissions > Social Links platforms (`social_platforms`) | subset | platforms offered on the form, sidebar and dashboard profile; fieldset absent when none |
| Settings > Advanced > Terms of service (`legal_terms_page_id`, `legal_terms_url`) | page / URL | Terms link and requirement on S9; draft page warning |
| Settings > General > Default listing credit cost, Pricing plans | numbers | credits banner on S9, charge on submit |
| Block attributes `listingType`, `showTypeStep`, `layoutMode`, `showTerms`, `redirectAfterSubmit` | per block | Type step hidden/preset, layout, Terms row, redirect target |
| Type Editor: `submission_enabled`, allowed categories, field groups, field required | per type | S5 type cards, S6 category list, S7 fields and required markers |
| Filters `wb_listora_submission_steps`, `wb_listora_submission_register_url`, `wb_listora_submission_no_permission_message`, `wb_listora_edit_auto_single_form` | code | steps, button, message, edit layout |
| WordPress `upload_max_filesize` / `post_max_size` | server | hint text on S8 and Settings read-only row |

## Fixtures and setup
- Personas: `bash bin/qa-fixtures.sh` ({owner}, {other}, {nocap}, {editor}, {admin}, {contributor}); placeholders from `docs/qa/sites/<site-host>.json`.
- Pages: `wp eval 'echo \WBListora\Core\Page_Registry::get_id("add_listing");'` (and `dashboard`). The Add Listing page must hold the Listing Submission block.
- Types: a QA type `qa-all-fields` with one field per widget, and the normal site types; one type with no categories; one with services off. Create via Type Editor or `wp listora` import.
- Listings (QA- prefix): published, draft, pending, rejected, expired, unpaid owned by {owner}; one owned by {other}.
- Credits: only when the credits journeys run (Pro/credits fixtures in the credits-checkout group).
- Record before: `wp option get wb_listora_settings --format=json > $SCRATCH/settings-before.json`, the caps of {nocap}.
- Restore afterwards: write settings JSON back, re-add the removed capability, delete QA listings, QA types, drafts and uploaded media (`wp post delete $(wp post list --post_type=listora_listing --s=QA- --format=ids) --force`), reset Playwright viewport/theme.

## Run order
Browser journeys first, in the order a member meets the flow; mixed after; no api-only journey in this group.
1. `submit-a-listing-wizard-end-to-end.md` - mode browser, critical: login gate, every step's required messages, terms, submit, DB read-back, role ladder, mobile and dark.
2. `add-listing-no-permission-message.md` - mode browser, high: a member who cannot submit gets a reason.
3. `submission-step-rendering-invariants.md` - mode browser, critical: only usable fields per step, fieldsets inside the column, upload limit.
4. `submission-stepper-fits-phones.md` - mode browser, high: step bar fits 390 and 360.
5. `submission-form-style-setting.md` - mode browser, normal: wizard vs single page, block attribute wins.
6. `business-hours-multiple-ranges.md` - mode browser, high: add/remove ranges, picker on any browser, readers agree.
7. `submission-map-picker.md` - mode browser, normal: address search combobox and picker stacking.
8. `submission-featured-image-drag-drop.md` - mode browser, normal: drop equals click upload.
9. `submission-upload-keyboard-access.md` - mode browser, critical: upload control operable by keyboard.
10. `toggle-untick-persists-frontend-edit.md` - mode browser, high: unticking a toggle on edit saves.
11. `submission-success-card-centered.md` - mode browser, normal: success card centred at every width.
12. `owner-opens-own-unpublished-listing.md` - mode browser, critical: owner opens own draft/rejected/paused/unpaid; others 404; expired notice.
14. `submission-save-draft-and-resume.md` - mode mixed, critical: Save Draft unlimited, id carried, Buy Credits keeps the draft.
15. `submission-terms-acceptance-enforced.md` - mode mixed, critical: no create/update without Terms, box always reachable.
17. `edit-my-listing.md` - mode mixed, high: owner edit, media kept, staff Edit button, 2 expected-fail steps.
18. `edit-permission-same-on-form-and-rest.md` - mode mixed, critical: one rule for form, POST and PUT.
19. `frontend-edit-keeps-description-markup.md` - mode mixed, critical: edit keeps images, video, formatting.
20. `submission-preserves-multi-category.md` - mode mixed, critical: edit keeps categories the form cannot show.
21. `submission-field-values-round-trip.md` - mode mixed, critical: declared options and price round trip form, API, display.
22. `submission-dashboard-url-registry.md` - mode mixed, normal: success card uses the registered Dashboard URL.
23. `title-is-not-a-url.md` - mode mixed, normal: URL-shaped title refused.
24. `submission-rest-feature-gate.md` - mode mixed (system), high: POST /submit refused with the feature off.
25. `guest-submission-with-email-verify.md` - mode mixed, high: STALE, see Known defects; do not run until rewritten.

## Group-specific micro checks
| id | Check |
|---|---|
| G-01 | `.listora-submission__progress` `aria-valuenow` equals the 1-based index of `.is-current`, `aria-valuemax` equals the number of indicators, and the completed indicators show the check icon instead of their number. |
| G-02 | With exactly one submittable type the Type step and its indicator are absent, the first indicator is "Basic Info" numbered 1, and the type is applied (Details shows that type's fields, not the "Select a listing type" placeholder). |
| G-03 | The Back button is `hidden` on the first step, Continue is hidden and Submit visible only on the last step, and the Submit label is "Update Listing" in edit mode, "Submit Listing" otherwise. |
| G-04 | Required markers: Title, Category (only when the type has categories), Description, Featured Image and Terms show an asterisk; an optional field never does; the asterisk has an sr-only "(required)" where it is the only cue. |
| G-05 | Each blocked Continue names the missing field in the message under that field (`role=alert`), moves focus to the first invalid control, keeps all other typed values, and the generic fallback appears only when no field-aware message exists. |
| G-06 | The upload hint "Up to {X}, JPG/PNG/WebP" prints the same size as Settings > Submissions "Max file size" and `wp_max_upload_size()`; the refusal for a file over the limit quotes the same figure. |
| G-07 | The Gallery label number equals Settings "Max gallery images"; adding one photo more than the cap is refused with a message and the thumbs stay at the cap; move earlier/later buttons are disabled at the ends and have accessible names. |
| G-08 | Save Draft: the button shows "Saving...", then the autosave indicator reads "Draft saved" (translated) and the button briefly reads "✓ Saved" (hard-coded in `view.js`, not passed through `t()`): record an untranslated string when the site language is not English. A failed save shows "Your draft could not be saved. Please try again." and never the saved state. |
| G-09 | Autosave is a 30-second debounce after the visitor stops typing or toggling (not every 30 seconds): typing continuously delays it, and an idle form fires nothing. Two autosaves update the same draft id (one `post` row), and a draft save never counts toward the new-listing rate limit. |
| G-10 | Hours builder: the "Add range" control disappears at the maximum ranges per day (default cap from `Search_Indexer::max_hours_slots()`), removing the middle range renumbers the inputs (`name` indexes contiguous) and keeps every survivor's times, and a "Closed" day posts no times. |
| G-11 | Hours pickers are the plugin's flatpickr time input (`H:i`, 24-hour, 15-minute steps, free typing allowed), not the browser's native time control, with a visible focus ring and an accessible name per input; record that the picker is 24-hour even when the site time format is 12-hour, and that the typed value `9:5` or `25:00` is normalised or refused with a message. |
| G-12 | The map field's City, State / Region, ZIP / Postal Code inputs fill from a picked match and stay editable; the Leaflet map never paints over the sticky header or the open wizard nav at 390 (stacking context confined to the box). |
| G-13 | Price field prefix shows the site currency symbol, rejects negative and non-numeric input with a field message, and the preview and the published page show the same formatted value as typed (zero-decimal currency prints no decimals). |
| G-14 | The Terms checkbox is reachable in every layout: in the wizard on Preview, in the single form at the bottom, never `hidden` in single-form; its error appears under it (`--agree-terms`), and the link "Terms of Service" opens in a new tab with `rel` set. |
| G-15 | Success wording matches what was stored: manual moderation prints "pending review", auto-approve prints the published wording, edit prints "Listing Updated!"; "Go to Dashboard" href equals the registered Dashboard URL (not a hard-coded slug) and "Add Another Listing" reloads a clean form. |
| G-16 | Double click on Submit or Update creates exactly one listing/update and the button is disabled with its label kept while the request runs; after a 4xx the button is re-enabled. |
| G-17 | The honeypot input is not focusable by Tab and not read by assistive tech; a request with `listora_hp_field` filled is refused server side with HTTP 403 "Submission blocked." and no listing row. |
| G-18 | Title boundary: whitespace-only, 1 character, 200 characters and a URL (`https://example.test`) each give a field message; a real name containing a dot (`Joe's Cafe.com Ltd` style) is accepted according to the URL rule in `title-is-not-a-url`. |
| G-19 | Reloading mid-wizard restores the typed values and the current step (or clearly starts over, record which), and the Back button after a browser Back navigation never resubmits. |
| G-20 | Edit mode pre-fills every field including featured image preview, gallery thumbs, features, tags (chips), hours and map coordinates; saving without touching them leaves each stored value byte-identical (compare `_listora_*` meta before and after). |
| G-21 | `?edit={id}` for a listing the user may not edit renders the normal blank add form or the refusal (record which; see edit-my-listing expected-fail), never any value from that listing in the HTML source. |
| G-22 | Stepper at 360 and 390: every indicator label is visible or has a text alternative, no horizontal scroll, the current step is scrolled into view; with 7+ steps (Pro filter) the bar stays inside the container. |

## Known defects (expected-fail)
- `edit-my-listing.md` step 2: a non-owner opening `?edit={id}` sees "Edit Listing" over a blank new-listing wizard (should show a clear "cannot edit" message).
- `edit-my-listing.md` step 9 (empty title over REST): `PUT /submit/{id}` with an empty title answers 200 and can save an empty title (should be 400 `field: title`).
- `submission-save-draft-and-resume.md` step 4 (read-back): re-saving an existing draft with the title emptied answers 200 and silently keeps the old title (should be 400).
- `guest-submission-with-email-verify.md`: not an expected-fail but stale. CHANGELOG records that anonymous guest submission and its email-verification step were removed, and `render.php` shows the login prompt to every logged-out visitor ("Submission is account-only"). Steps 1-2 (wizard renders without login, `pending_verification`) cannot pass; the settings it sets (`allow_guest_submissions`, `require_email_verification`) are not read. Rewrite as "logged-out visitor sees the gate" (already step 1 of the wizard journey) and keep only the landing-page tests that still apply to `Email_Verification` for other flows, or retire it.

## Not covered yet
- Duplicate review panel (S10): `POST /submit/check-duplicate`, the confirm checkbox, the 20-character explanation and "Submit anyway" have no journey.
- CAPTCHA widgets on the submission form (provider none/reCAPTCHA/Turnstile) and the refusal without a token.
- Type step behaviour: default type pre-selection, switching type with typed data (re-evaluated required fields is step 5 of the invariants journey; losing or keeping typed Details is not).
- Tags input (chips, comma, Enter, duplicates, maximum) and Features & Amenities checkbox grid including the allowlist per type.
- Gallery reorder buttons and the cap; video URL validation and embed preview.
- Field kinds with no round-trip step: date/time/datetime, color, multiselect, radio, social links, file field on the form, toggle-conditional fields.
- `redirectAfterSubmit` block attribute and the leftover `requireLogin` attribute in `block.json` (the control was removed; the attribute still defaults to true).
- Autosave every 30 seconds and recovery after the tab is closed.
- RTL for the form, stepper and hours builder; 200% zoom.
- Pro plan step and credits banner on S9 beyond cost-charged-once (plans-expiry-renewal owns the plan step, but nothing opens S9's banner states: balance short, enough, plan chosen).
