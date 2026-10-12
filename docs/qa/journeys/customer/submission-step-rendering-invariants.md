---
journey: submission-step-rendering-invariants
plugin: wb-listora
priority: critical
roles: [subscriber, guest, admin]
covers: [optional-category-when-none, empty-fieldset-suppression, fieldset-min-width, upload-limit-follows-server, BC-10180373117, BC-10163072337, bc-10360671784, "#9867347053"]
prerequisites:
  - "Site reachable at $SITE_URL; Pro active when the six-step wizard (with Plan) is under test"
  - "Fixture A: one listing type {type} with an EMPTY allowed-categories list (back up `_listora_allowed_categories` term meta of a type, set `array()`, restore at the end)"
  - "Fixture B: a type {type_b} WITH categories; a type with a wide field group (Event Schedule is the repro)"
  - "Three test JPEGs: 370 KB, 8 MB, 27 MB (ImageMagick, under the scratch dir) and a mu-plugin `add_filter( 'upload_size_limit', fn() => 10 * MB_IN_BYTES );` (delete afterwards)"
estimated_runtime_minutes: 14
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
site_profile: required
supersedes:
  - "ora:regression/submission-category-optional-when-none.md"
  - "ora:regression/empty-media-fieldset.md"
  - "ora:regression/submission-fieldset-min-width.md"
  - "ora:regression/gallery-follows-server-limit.md"
fails_on: []
---

# Each wizard step renders only what the member can act on, inside the column, within the server limit

## Why
A type with no categories dead-ends on Basic Info; a bare `<legend>` or a fieldset wider than the form pushes Continue off a phone; a Listora-invented 5 MB cap refuses photos the server accepts. The member cannot finish the listing.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Add Listing page = `wb_listora_get_page_url( 'submission' )`. Autologin as {owner} (subscriber). Panels are `.listora-submission__step[data-step=...]`, type cards `.listora-submission__type-card`.
- `GET /listora/v1/listing-types/{type}/categories` must return `[]` and `.../{type_b}/categories` a non-empty list; capture `N_CATS` = its length.
- Cleanup: restore `_listora_allowed_categories`, delete the mu-plugin, delete any `QA-J1 ...` draft the walk autosaves.

## Steps

### 1. A type with NO categories hides the Category field
- **Action**: click the {type} card, wait for the categories fetch.
- **Expect**: `[name="category"]` has one option (placeholder), `required === false`, its `.listora-submission__field` wrapper `hidden === true`.
- **On fail**: `src/blocks/listing-submission/view.js:215` (`syncCategoryApplicability()`)

### 2. ...and the wizard advances
- **Action**: Continue; fill `#listora-title`, `#listora-description`; Continue.
- **Expect**: visible step is `details` (staying on `basic` is the regression).

### 3. Positive control: a type WITH categories shows and requires it
- **Action**: reload, pick {type_b}.
- **Expect**: option count = `N_CATS + 1`, `required === true`, wrapper not hidden.

### 4. ...blocks when left empty, passes once picked
- **Action**: Continue with title and description filled and Category unset; then select the first real option, Continue.
- **Expect**: first Continue stays on `basic` with `.listora-submission__field-error` "This field is required."; second reaches `details`.

### 5. Switching type re-evaluates (no stale `required`)
- **Action**: reload, pick {type_b}, then pick {type}.
- **Expect**: back to one option, `required === false`, wrapper `hidden`.
- **On fail**: reset path `view.js:241`; single-form layout is server-rendered (`templates/blocks/listing-submission/step-basic.php:43`).

### 6. No empty fieldset on Details, for every type
- **Action**: for each registered type: pick, Continue to Details, run `[...document.querySelectorAll('fieldset.listora-submission__fieldset')].filter(f => !f.querySelector('input,select,textarea,button')).length`.
- **Expect**: `0` for every type, and Continue reaches `data-step="media"` with no console error.
- **On fail**: `templates/blocks/listing-submission/step-details.php:37-48` (`$skip_field_types = array( 'gallery', 'video' )`).

### 7. Positive control: a Media-titled fieldset that does hold controls still renders
- **Expect**: fieldsets titled "Media" exist with controls (social links / virtual tour URL), so suppression is not over-eager.

### 8. Fieldsets stay inside the form column
- **Action**: on Details of Event, for each visible `.listora-submission__fieldset` read `getComputedStyle(f).minWidth`, `getBoundingClientRect().width`, parent width; read `document.documentElement.scrollWidth`.
- **Expect**: `min-width` is `0px`; every fieldset width <= parent width; `scrollWidth <= clientWidth`; at 390 the Continue button is inside the viewport and >= 40px tall.
- **Read-back**: positive control: a bare `fieldset` appended to `body` reports the UA `min-content`/`auto`, proving the reset (not the UA) holds.
- **On fail**: `blocks/listing-submission/style.css:322-342`; RTL twin `style-rtl.css:322-342` is hand-maintained.

### 9. Edit mode renders the same fieldsets
- **Action**: open the dashboard Edit form for a listing of the same type; repeat step 8 once.
- **Expect**: same widths, no overflow.

### 10. Mixed upload pick on a 10 MB server (390)
- **Action**: on Media click "Add Photos" and pick the 370 KB, 8 MB and 27 MB files together.
- **Expect**: status "Uploading photo 1 of 2..." then two thumbnails; exactly two `POST /wp-json/wp/v2/media`; a red `.listora-form__error[data-listora-upload-error][role="alert"]` naming the 27 MB file and "10 MB" ("<file> is larger than the 10 MB this site accepts."), still visible after scrolling.
- **Read-back**: `SELECT COUNT(*) FROM wp_posts WHERE post_type='attachment' AND post_author={owner}` rises by 2, not 3.

### 11. Positive control: under the limit
- **Action**: pick only the 8 MB file.
- **Expect**: uploads, no alert (a 5 MB Listora cap would have refused it).

### 12. Featured image follows the same limit
- **Action**: pick the 27 MB file as Featured Image.
- **Expect**: same inline refusal, no upload request; the hint reads "Up to 10 MB, JPG/PNG/WebP" (`step-media.php:58`).

### 13. Settings shows the limit read-only
- **Action**: as {admin} open Listora > Settings > Submissions.
- **Expect**: "Max file size" shows the server limit in `<strong>` with no input; "Max gallery images" is a number input.

### 14. Dark and unknown theme
- **Action**: repeat steps 1, 6, 8 in buddyx-pro dark (`html[data-bx-mode="dark"]`) and on the unknown theme.
- **Expect**: identical widths and behaviour. Not verified live.

## Role ladder
| Rung | Expect |
|---|---|
| {owner}, {other}, {editor} | steps 1-12 identical (a limit and a step layout are not role-based) |
| {guest} | Add Listing shows the sign-in prompt, no wizard, no fieldset |
| role without submit cap | "You cannot add listings" with a My Dashboard link, no wizard |
| {admin} | steps 1-12 as control, plus step 13 |

## Pass criteria
1. Required if and only if there is a category to pick, on reset, success and failure paths.
2. Zero empty fieldsets for every type; Media fieldsets with controls still render.
3. Computed `min-width` is `0px`; no fieldset wider than its parent at 1440, 820 and 390; no page scroll.
4. Files under the server limit upload; oversized are refused before upload, by name, inline; no orphan attachments.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Stuck on Basic Info | `syncCategoryApplicability()` not called on fetch-success | `view.js:215` |
| Type with categories no longer requires one | invariant inverted | `view.js:190-199` |
| Bare legend | gallery/video-only group not skipped | `step-details.php:37-48` |
| Fieldset wider than parent | UA `min-width: min-content` back | `blocks/listing-submission/style.css:322-342` |
| Old cap enforced | client limit | `view.js:1049`, `includes/class-assets.php:390` |
| Error toast vanishes | inline alert element missing | `view.js:909-915` |
| Fix in `src/` not on page | stale bundle | `npm run build` |

## Not verified
- Steps 1-6 were run in a browser at 1440 light (Playwright) for the optional-category part; the upload limit (steps 10-13) was source-read only (large files not run); empty-fieldset DOM check ran via curl (0 empty over all built-in types); Event Schedule at 820/390, dark and RTL not measured. Steps 7, 9, 14 added in consolidation: not run live.

## Cleanup
- Restore the `_listora_allowed_categories` term meta backed up for {type} (`wp term meta update <term id> _listora_allowed_categories '<backup>'`).
- Delete the mu-plugin that filters `upload_size_limit` to 10 MB and the three test JPEGs under the scratch dir.
- Delete every `QA-J1 ...` draft the walk autosaved and the attachments uploaded in steps 10-12 (`wp post delete <ids> --force`).
