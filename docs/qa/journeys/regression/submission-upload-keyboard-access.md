---
journey: submission-upload-keyboard-access
plugin: wb-listora
priority: critical
roles: [member, logged-out, admin]
covers: [LST-F-08, a11y, keyboard, submission-blocker, WCAG-2.1.1]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A listing type with categories and a required address (`restaurant` works)"
  - "Member session ?autologin=QA-J1-member"
estimated_runtime_minutes: 6
covers_card: null
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# The featured-image control must be operable by keyboard (LST-F-08 sentinel)

The featured image is required on a new submission. The upload control is a real `<button type="button">` (`templates/blocks/listing-submission/step-media.php:45`), so the platform supplies tab stop, Enter/Space and focus semantics. The generic field renderer's `file` field is also a `<button>` (`includes/submission-field-renderer.php:609`). The Gallery control (`.listora-submission__add-photos`) was always a button. Test with the keyboard: a mouse click worked before the fix too.

## Setup
- `$SITE_URL/add-listing/` at 1440x900. Walk to the Media step: pick **Restaurant**, Continue, fill `#listora-title`, `#listora-description`, pick a `[name="category"]` option, Continue, fill `input[name="meta_address[address]"]` (the visible one; inactive type blocks are `disabled` and duplicate the name), Continue.
- The panel is `.listora-submission__step[data-step="media"]`.

## Steps

### 1. The control is a real button
- **Action**: `browser_evaluate` on `.listora-submission__step[data-step="media"] .listora-submission__upload-trigger`.
- **Expect**: `tagName === 'BUTTON'`, `type === 'button'`, non-zero size, accessible name from its text ("Click to upload or drag & drop ... Up to 300 MB, JPG/PNG/WebP"). A `DIV` is the regression. Verified 2026-10-12.

### 2. No `<div>` still carries the click handler
- **Action**: `document.querySelectorAll('div[class*="upload-zone"][data-wp-on--click]').length`
- **Expect**: `0` (verified).

### 3. Reachable by Tab alone
- **Action**: put focus before the step content (the step heading `h2` carries no `tabindex` now; click the step title or press Shift+Tab from the first field), then press Tab up to 10 times checking `document.activeElement`.
- **Expect**: focus lands on `.listora-submission__upload-trigger` (first Tab on the reference run).

### 4. The focus ring is visible
- **Action**: with focus arrived via Tab (programmatic `.focus()` still matches `:focus-visible` in Chromium here but Tab is the contract), read the computed outline.
- **Expect**: `outline-width: 2px`, `outline-style: solid`, `outline-offset: 2px`, `matches(':focus-visible') === true`, colour `var(--listora-primary)` (reference run on BuddyX Pro: light `outline: 2px solid`; dark `rgb(255, 107, 107)`). The ring is defined at `blocks/listing-submission/style.css:508-512` (also sets `border-color`).

### 5. Enter activates it
- **Action**: press Enter.
- **Expect**: the OS file chooser opens (Playwright: `[File chooser]` modal state / `filechooser` event). The trigger calls `input.click()` on a hidden file input and uploads through `POST /wp/v2/media`; it does not open wp.media. Verified; dismiss with `browser_file_upload` without paths.

### 6. Space activates it too
- **Action**: re-focus the trigger, press Space.
- **Expect**: the file chooser opens again (verified).

### 7. Dark mode keeps the ring
- **Action**: set `data-bx-mode="dark"` on `<html>`, focus the trigger, re-read the outline.
- **Expect**: still `2px solid` with a colour resolved from `--listora-primary` (do not assert a literal RGB; the brand colour is theme dependent).

### 8. The Gallery control is unaffected
- **Expect**: `.listora-submission__add-photos` is a `BUTTON`, `tabIndex 0` (verified).

### 9. 390px
- **Action**: resize to 390x844.
- **Expect**: trigger height >= 40px (reference 186px), `document.documentElement.scrollWidth <= clientWidth`.

### 10. Edit mode: Replace/Remove are siblings, not children
- **Action**: open a listing with a featured image for edit (`/add-listing/?edit=<id>`); inspect markup.
- **Expect**: `.listora-submission__media-actions` (`[data-listora-media-actions="featured_image"]`) holds `button[data-listora-replace-media]` "Replace" and `button[data-listora-remove-media]` "Remove" as a sibling of the trigger inside `.listora-submission__upload-zone`, never nested inside the trigger button. The preview `img.listora-submission__media-preview` is inside the trigger and carries `alt="Featured image preview"`.

### 11. Role ladder
Member: all steps. Logged out: `/add-listing/` shows the sign-in prompt, no trigger (control). Admin: same control present. A role that cannot submit sees "reason + My Dashboard link" (2.0.0), not a trigger.

## Pass criteria
1. Trigger is `<button type="button">`; zero `div[class*="upload-zone"][data-wp-on--click]`.
2. Reachable by Tab alone; visible 2px ring with `:focus-visible`.
3. Enter and Space both open the file chooser.
4. Ring survives dark mode; Gallery still a button.
5. >= 40px tall, no overflow at 390.
6. Replace/Remove are siblings of the trigger.

## Fail diagnostics
| Symptom | File |
|---|---|
| Trigger is a `DIV` | `templates/blocks/listing-submission/step-media.php:45` |
| A custom `file` field unreachable | `includes/submission-field-renderer.php:598-610` (`case 'file'`) |
| Reachable, no ring | `blocks/listing-submission/style.css:508` (`.listora-submission__upload-trigger:focus-visible`); hover tint is not a focus indicator |
| Ring gone in dark | outline colour hard-coded instead of `var(--listora-primary)` (same rule) |
| Enter works, Space not | a div + keydown replaced the `<button>` |
| Screen reader says only "button" after picking | preview `<img>` lost `alt`: `src/blocks/listing-submission/view.js:744` (`featuredPreviewAlt`) |
| RTL differs | `blocks/listing-submission/style-rtl.css` (hand-maintained) |

## Not verified
Steps 1-6, 8, 9 (trigger height only) were run in Playwright (Chromium, BuddyX Pro) on 2026-10-12; the dark ring colour was read after setting `data-bx-mode` by script, not via the theme toggle. Step 10 (edit-mode markup) was read from `step-media.php`, not rendered. Light-only for steps 1-6; unknown theme not run.

Verified via: Playwright MCP, source.
