---
journey: submission-category-optional-when-none
plugin: wb-listora
priority: critical
roles: [member, logged-out, admin]
covers: [BC-10180373117, submission-wizard, allowed_categories, dead-end-guard]
prerequisites:
  - "Site reachable at $SITE_URL; Pro active (wizard has six steps)"
  - "FIXTURE: one listing type with an EMPTY allowed-categories list. On this site every built-in type has categories, so empty one on a copy: back up `_listora_allowed_categories` term meta of `place`, set it to `array()`, restore at the end"
  - "At least one listing type WITH categories (restaurant has 15)"
estimated_runtime_minutes: 6
covers_card: 10180373117
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# A type with no categories must not dead-end the wizard (BC 10180373117 sentinel)

`allowed_categories` is a per-type allowlist and an empty one is legitimate (Listing Types list shows a "No categories" badge for it). In the wizard the member picks the type on step 1 in the browser, so the server prints the Category select unconditionally. `syncCategoryApplicability()` in `src/blocks/listing-submission/view.js:190` enforces one invariant: required if and only if there is something to pick, on the reset, success and failure paths. The server was never the blocker: `POST /submit` accepts a listing with no category.

## Setup
- Fixture above. `GET /listora/v1/listing-types/place/categories` must return `[]` and `.../restaurant/categories` a non-empty list.
- `$SITE_URL/add-listing/?autologin=QA-J1-member` at 1440x900. Step panels are `.listora-submission__step[data-step=...]`; type cards are `.listora-submission__type-card`; Continue is the visible button with text "Continue".

## Steps

### 1. A type with NO categories hides the field
- **Action**: click the **Place** type card, wait ~1.5s for the categories fetch.
- **Expect**: `[name="category"]` has `options.length === 1` (placeholder), `required === false`, and its `.listora-submission__field` wrapper has `hidden === true`.

### 2. ...and the wizard advances
- **Action**: Continue; fill `#listora-title` and `#listora-description` (textarea); Continue.
- **Expect**: visible step is `details`. Staying on `basic` is the regression.

### 3. Positive control: a type WITH categories still shows and requires it
- **Action**: reload, pick **Restaurant**, wait.
- **Expect**: `options.length === 16` (15 + placeholder), `required === true`, wrapper not hidden.

### 4. ...and still blocks when left empty
- **Action**: Continue, fill title and description, leave Category unset, Continue.
- **Expect**: stays on `basic`; a visible `.listora-submission__field-error` reads "This field is required." (2.0.0 wording).

### 5. ...and passes once a category is picked
- **Action**: select the first real option, Continue.
- **Expect**: step `details`.

### 6. Switching type re-evaluates (no stale `required`)
- **Action**: reload, pick **Restaurant**, wait, then pick **Place**, wait.
- **Expect**: back to `options.length === 1`, `required === false`, wrapper `hidden`.

### 7. 390px
- **Action**: resize to 390x844 on the Basic step.
- **Expect**: `document.documentElement.scrollWidth <= clientWidth`, Continue >= 40px tall.

## Role ladder
Member: steps 1-7. Logged out: `/add-listing/` shows the sign-in prompt, no wizard (positive control for the member run). Admin: same wizard (control). A role that cannot submit sees "reason + My Dashboard link" (2.0.0), not a blank page.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Place stuck on Basic Info | `syncCategoryApplicability()` not called on the fetch-success path | `src/blocks/listing-submission/view.js:215` |
| Restaurant no longer requires a category | invariant inverted | same helper, `view.js:190-199` (`required = options.length > 1`) |
| Field stays hidden after Place -> Restaurant | helper not called on reset path | `view.js:241` |
| Works in wizard, broken single-form | single-form is server-rendered | `templates/blocks/listing-submission/step-basic.php:43` |
| Fix in `src/` but not on page | stale bundle | `npm run build`, never hand-edit `build/` |

## Cleanup
Restore `_listora_allowed_categories` of `place` (2026-10-12 original: term ids 90-100).

Verified via: Playwright MCP (Chromium, shared browser) at 1440 for steps 1-6; step 7 and the 390 stepper were checked on the media step only; dark and unknown theme not checked.
