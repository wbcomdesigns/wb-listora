---
journey: submission-upload-keyboard-access
group: submission
actor: customer
mode: browser
plugin: wb-listora
priority: critical
roles: [owner, guest, admin]
covers: [LST-F-08, a11y, keyboard, submission-blocker, WCAG-2.1.1]
prerequisites:
  - "Site reachable at $SITE_URL; a listing type with categories and a required address ({type}); {owner} session"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, unbridged]
site_profile: required
supersedes: []
fails_on: "featured-image trigger rendered as a DIV with a click handler (LST-F-08)"
---

# The featured-image upload control is operable by keyboard alone

## Why
The featured image is required on a new submission. When the control was a `<div>` with a click handler, keyboard-only and screen-reader members could not add it and so could not submit at all (WCAG 2.1.1). A mouse click worked before the fix, so only a real keyboard run catches it.

The trigger is a real `<button type="button">` so the platform supplies tab stop, Enter/Space and focus semantics; the generic `file` field and the Gallery control (`.listora-submission__add-photos`) are buttons too.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Open Add Listing at 1440x900 as {owner}: pick {type}, Continue, fill `#listora-title`, `#listora-description`, pick a `[name="category"]` option, Continue, fill the visible `input[name="meta_address[address]"]` (inactive type blocks are `disabled` and duplicate the name), Continue to the Media step. Panel: `.listora-submission__step[data-step="media"]`.

## Steps

### 1. The control is a real button
- **Action**: `browser_evaluate` on `.listora-submission__step[data-step="media"] .listora-submission__upload-trigger`.
- **Expect**: `tagName === 'BUTTON'`, `type === 'button'`, non-zero size, accessible name from its text ("Click to upload or drag & drop ... Up to <size>, JPG/PNG/WebP"). A `DIV` is the regression.

### 2. No `<div>` still carries the click handler
- **Action**: `document.querySelectorAll('div[class*="upload-zone"][data-wp-on--click]').length`.
- **Expect**: `0`.

### 3. Reachable by Tab alone
- **Action**: put focus before the step content (click the step title or Shift+Tab from the first field); press Tab up to 10 times checking `document.activeElement`.
- **Expect**: focus lands on `.listora-submission__upload-trigger`.

### 4. The focus ring is visible
- **Action**: arrive via Tab (programmatic `.focus()` matches `:focus-visible` in Chromium, but Tab is the contract); read the computed outline.
- **Expect**: `outline-width: 2px`, `outline-style: solid`, `outline-offset: 2px`, `matches(':focus-visible') === true`, colour resolved from `var(--listora-primary)` (do not assert a literal RGB; the brand colour is theme dependent). Ring defined at `blocks/listing-submission/style.css` `.listora-submission__upload-trigger:focus-visible` (also sets `border-color`).

### 5. Enter activates it
- **Action**: press Enter.
- **Expect**: the OS file chooser opens (Playwright `[File chooser]` modal state). The trigger calls `input.click()` on a hidden file input and uploads via `POST /wp/v2/media`; it does not open wp.media. Dismiss with `browser_file_upload` without paths.

### 6. Space activates it too
- **Action**: re-focus the trigger, press Space.
- **Expect**: the file chooser opens again.

### 7. Dark mode keeps the ring
- **Action**: set `data-bx-mode="dark"` on `<html>` (or use the theme toggle), focus the trigger, re-read the outline.
- **Expect**: still `2px solid`, colour from `--listora-primary`.

### 8. The Gallery control is unaffected
- **Expect**: `.listora-submission__add-photos` is a `BUTTON` with `tabIndex 0`.

### 9. 390px
- **Action**: resize to 390x844.
- **Expect**: trigger height >= 40px, `document.documentElement.scrollWidth <= clientWidth`.

### 10. Edit mode: Replace/Remove are siblings, not children
- **Action**: open a listing with a featured image for edit (`?edit=<id>`); inspect markup.
- **Expect**: `.listora-submission__media-actions` (`[data-listora-media-actions="featured_image"]`) holds `button[data-listora-replace-media]` "Replace" and `button[data-listora-remove-media]` "Remove" as siblings of the trigger inside `.listora-submission__upload-zone`, never nested in the trigger; the preview `img.listora-submission__media-preview` is inside the trigger with `alt="Featured image preview"`.

### 11. Upload retry and parallel
- **Action**: choose a file, then immediately activate the trigger again; repeat with a rejected file type and an oversize file.
- **Expect**: one upload per choice, an inline error for the rejected file, and the trigger remains focusable afterwards. Not verified live.

## Role ladder
| Rung | Expect |
|---|---|
| {owner} | all steps |
| {guest} | Add Listing shows the sign-in prompt, no trigger (control) |
| {admin} | same control present |
| member without submit capability | "reason + My Dashboard link" (see `submission/add-listing-no-permission-message.md`), not a trigger |

## Pass criteria
1. Trigger is `<button type="button">`; zero `div[class*="upload-zone"][data-wp-on--click]`.
2. Reachable by Tab alone with a visible 2px `:focus-visible` ring that survives dark mode.
3. Enter and Space both open the file chooser; Gallery is still a button.
4. >= 40px tall with no overflow at 390; Replace/Remove are siblings of the trigger.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Trigger is a `DIV` | markup regressed | `templates/blocks/listing-submission/step-media.php` (~:45) |
| A custom `file` field unreachable | field renderer | `includes/submission-field-renderer.php` `case 'file'` (~:598-610) |
| Reachable, no ring | focus-visible rule removed | `blocks/listing-submission/style.css` (~:508) |
| Ring gone in dark | outline colour hard-coded | same rule |
| Enter works, Space not | a div + keydown replaced the `<button>` | step-media.php |
| Screen reader says only "button" after picking | preview `alt` lost | `src/blocks/listing-submission/view.js` (`featuredPreviewAlt`, ~:744) |
| RTL differs | hand-maintained twin | `blocks/listing-submission/style-rtl.css` |

## Not verified
Steps 1-6, 8, 9 (trigger height) ran in Playwright (Chromium, BuddyX Pro) on 2026-10-12; the dark ring was read after setting `data-bx-mode` by script, not via the toggle. Step 10 was read from source, step 11 not run. Light only for steps 1-6; unbridged theme not run.

## Cleanup
The wizard autosaves a draft once Title is filled: delete the QA draft listing.
