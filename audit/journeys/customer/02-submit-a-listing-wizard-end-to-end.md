---
journey: submit-a-listing-wizard-end-to-end
plugin: wb-listora
priority: critical
roles: [subscriber, contributor]
covers: [submission-wizard, conditional-fields, featured-image-aria-required, recaptcha-bypass-in-dev]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Dev auto-login mu-plugin installed"
  - "At least 1 listora_listing_type registered (e.g. 'business')"
  - "At least 1 category in listora_listing_cat under that type"
  - "spam-protection in dev mode (recaptcha disabled or test-key)"
  - "Test user 'submitter' exists (subscriber role)"
estimated_runtime_minutes: 7
---

# Submit a listing wizard, end-to-end

A frontend user walks the multi-step listora/listing-submission block from start (type picker) to finish (success screen) and lands a `listora_listing` row in `wp_posts` with `_listora_*` meta written. Verifies every stage of the wizard: type selection (`/listing-types/{slug}/fields`), conditional fields rendering, the featured-image visual-required + aria-required guard (commit 098ba2c), the duplicate-check pre-submission, the final POST `/submit`, and the success-state activation. If any step's REST contract drifts, conditional fields don't load, or featured-image upload silently submits without an image, this journey fails fast.

## Setup

- Site: `$SITE_URL`
- Test user: `submitter` (autologin via `?autologin=submitter`)
- Fixtures needed:
  - 1 type slug `business` registered with at least 3 fields (name, address, phone)
  - 1 category `restaurants` under that type
  - 1 small JPG/PNG fixture file (`audit/journeys/fixtures/test-image.jpg` if available, else any local file ≤ 1MB)
- DB clean:
  ```sql
  DELETE FROM wp_posts WHERE post_type='listora_listing' AND post_title='Journey Test Listing';
  ```

## Steps

### 1. Auto-login as submitter
- **Action**: `playwright_navigate $SITE_URL/?autologin=submitter`
- **Expect**: redirect to home; user is authenticated
- **On fail**: dev-auto-login mu-plugin missing

### 2. Open the submission page
- **Action**: `playwright_navigate $SITE_URL/submit-listing` (or whichever page hosts the `listora/listing-submission` block)
- **Expect**: DOM contains `.wp-block-listora-listing-submission`; first step (type picker) is visible; `data-wp-class--is-active` resolves to `.listora-submission__step.is-active` on step 1
- **On fail**: `blocks/listing-submission/render.php`, IAPI store `currentStep` state

### 3. Pick a listing type
- **Action**: `playwright_click '.listora-submission__type-card[data-type-slug="business"]'`
- **Expect**:
  - `apiFetch GET /wp-json/listora/v1/listing-types/business/fields` returns `200 OK` with non-empty fields array
  - Wizard advances to step 2 (Categories)
  - `state.selectedType === 'business'` and `state.typeFieldConfig.fields.length > 0`
- **On fail**: `Listing_Types_Controller::get_fields`, `src/interactivity/store.js::selectType` action

### 4. Pick a category
- **Action**: `playwright_click '.listora-submission__category-card[data-cat-slug="restaurants"]'`
- **Expect**: wizard advances to step 3 (Details); `state.selectedCategory === 'restaurants'`
- **On fail**: `Listing_Types_Controller::get_categories` or `selectCategory` action

### 5. Fill required details
- **Action**: type values into the fields rendered for type=business
  - `playwright_fill_form '#listora-listing-name' 'Journey Test Listing'`
  - `playwright_fill_form '#listora-listing-address' '123 Test Street'`
  - `playwright_fill_form '#listora-listing-phone' '+1-555-0100'`
- **Expect**: each input updates `state.formData[<key>]`; no validation error toast on blur
- **On fail**: `templates/blocks/listing-submission/step-details.php`, conditional-field render

### 6. Step to Media — try to advance WITHOUT a featured image
- **Action**: `playwright_click 'button.listora-submission__next'`
- **Expect**:
  - Wizard does NOT advance
  - Featured image field shows the visual-required indicator AND has `aria-required="true"` (commit 098ba2c)
  - Inline error message announces missing featured image
- **On fail**: `templates/blocks/listing-submission/step-media.php` — aria-required regression of commit 098ba2c, or JS validator skipped the hidden input

### 7. Upload a featured image
- **Action**: `playwright_file_upload 'input.listora-submission__featured-input' audit/journeys/fixtures/test-image.jpg`
- **Expect**: upload preview appears; hidden input's value is the new attachment ID
- **On fail**: media-library REST endpoint, or `src/interactivity/store.js` upload action

### 8. Submit
- **Action**: `playwright_click 'button.listora-submission__submit'`
- **Expect**:
  - `apiFetch POST /wp-json/listora/v1/submit/check-duplicate` returns `200 { duplicate: false }`
  - `apiFetch POST /wp-json/listora/v1/submit` returns `200 OK` with `{ id: <int>, edit_url, ... }`
  - Wizard switches to success step; `state.submissionId > 0`
- **On fail**: `Submission_Controller::submit_listing`, `wb_listora_before_create_listing` filter returning WP_Error, or recaptcha rejecting in dev

### 9. Verify post + meta in DB
- **Action**: `mysql_query "SELECT ID, post_title, post_status FROM wp_posts WHERE post_type='listora_listing' AND post_title='Journey Test Listing'"`
- **Expect**: exactly 1 row; `post_status` is `pending` or `publish` per setting
- **Action**: `mysql_query "SELECT meta_key FROM wp_postmeta WHERE post_id=<ID> AND meta_key LIKE '_listora_%'"`
- **Expect**: ≥ 3 distinct meta keys (`_listora_address`, `_listora_phone`, etc.)
- **On fail**: meta-handler or submission controller's `wp_insert_post` call

### 10. Verify success state UI
- **Action**: assert DOM contains `.listora-submission__success` visible
- **Expect**: contains the new listing's edit-link
- **On fail**: success-step template or `view.js` step transition

## Pass criteria

ALL of the following hold:
1. Type picker fetches fields and advances
2. Category step advances
3. Details step records all required values into `state.formData`
4. Featured-image required-guard blocks advancement when missing (commit 098ba2c)
5. Final POST `/submit` returns 200 with a new listing ID
6. `wp_posts` contains exactly 1 row for `Journey Test Listing` with `_listora_*` meta
7. Success state visible

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Step 1 type-fields fetch returns 404 | type slug typo or controller not registered | `includes/rest/class-listing-types-controller.php` |
| Featured-image-missing slips through | aria-required / JS validator drift | `templates/blocks/listing-submission/step-media.php` (commit 098ba2c), submission view.js validation |
| Submit POST returns 400 with "duplicate" | check-duplicate is overzealous | `Submission_Controller::check_duplicate_endpoint` |
| Submit POST returns 403 | logged-out OR cap mismatch on `submit_listora_listing` | `Submission_Controller::permissions_check`, `Capabilities` class |
| Submit POST returns 200 but no row in `wp_posts` | `wb_listora_before_create_listing` filter returning WP_Error silently | grep `before_create_listing` for any blocking listener |
| Meta missing post-submit | meta-handler not called from submission controller | `Submission_Controller::submit_listing`, `Meta_Handler` |
