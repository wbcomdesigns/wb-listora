---
journey: submission-upload-keyboard-access
plugin: wb-listora
priority: critical
roles: [member]
covers: [LST-F-08, a11y, keyboard, submission-blocker, WCAG-2.1.1]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A listing type with categories and a required address (`restaurant` works)"
  - "Auto-login mu-plugin present (?autologin=1)"
estimated_runtime_minutes: 5
covers_card: null
---

# The featured-image control must be operable by keyboard (LST-F-08 sentinel)

The featured image is **required** on a new submission. Its upload control used to
be a `<div>` carrying `data-wp-on--click` — no tab stop, no Enter/Space, no focus
ring, and no role for a screen reader to announce. That did not make one control
awkward; it made **the entire form impossible to complete without a mouse**.

The same `<div>` pattern was also in the generic field renderer
(`includes/submission-field-renderer.php`), so it affected **every `file` custom
field on every listing type**, not just the featured image.

Fix: both are real `<button type="button">` elements now. The platform supplies
the tab stop, Enter/Space activation and focus semantics, so there is no
`role`/`tabindex`/`keydown` trio to keep in sync — and nothing to rot. The Gallery
control was always a real button; this brought the others into line.

**Test this with the keyboard.** Clicking it proves nothing — it worked with a
mouse before the fix too.

## Setup

- `$SITE_URL/add-listing/?autologin=1` at 1440×900
- Walk to the **Media** step: pick **Restaurant** → Continue → fill Title,
  Description, pick a Category → Continue → fill the visible Address → Continue.

## Steps

### 1. The control is a real button
- **Action**: `browser_evaluate` on
  `.listora-submission__step[data-step="media"] .listora-submission__upload-trigger`.
- **Expect**: `tagName === 'BUTTON'`, `type === 'button'`, non-zero size, and an
  accessible name from its own text ("Click to upload…"). **A `DIV` here is the
  regression.**

### 2. No `<div>` anywhere still carries the click handler
- **Action**: `document.querySelectorAll('div[class*="upload-zone"][data-wp-on--click]').length`
- **Expect**: **0**. Catches the field-renderer copy coming back.

### 3. Reachable by Tab alone
- **Action**: focus the step's `<h2>` (`tabindex="-1"`), then press **Tab**
  repeatedly, up to 10 times, checking `document.activeElement` each press.
- **Expect**: focus lands on the trigger. On the reference run it was the **first**
  Tab.

### 4. The focus ring is visible
- **Action**: with focus arrived **via Tab** (not `.focus()` — programmatic focus
  does not set `:focus-visible`), read the computed outline.
- **Expect**: `outline-width: 2px`, `outline-style: solid`, `outline-offset: 2px`,
  and `el.matches(':focus-visible') === true`.

### 5. Enter activates it
- **Action**: press **Enter**.
- **Expect**: the WordPress media frame opens (`.media-modal, .media-frame`
  present). Escape to close.

### 6. Space activates it too
- **Action**: re-focus the trigger, press **Space**.
- **Expect**: the media frame opens again. Both keys matter — `<button>` responds
  to both, and a hand-rolled `keydown` handler is exactly what tends to implement
  only one.

### 7. Dark mode keeps the ring
- **Action**: set `data-bx-mode="dark"` on `<html>`, Tab to the trigger, re-read
  the outline.
- **Expect**: still `2px solid`, colour resolved from `--listora-primary`
  (reference run: `rgb(151, 192, 238)`). A ring that vanishes in one palette is
  not a ring.

### 8. The Gallery control is unaffected
- **Action**: read `.listora-submission__add-photos`.
- **Expect**: still `BUTTON`, `tabIndex 0`.

### 9. 390px
- **Action**: resize to 390×844.
- **Expect**: trigger height ≥ 40px (tap-target floor), no horizontal page scroll.

### 10. Edit mode — the remove control is a SIBLING, not a child
- **Action**: open an existing listing with a featured image for edit; inspect the
  markup.
- **Expect**: `.listora-submission__media-remove` is a sibling of the trigger
  inside `.listora-submission__upload-zone`, **not nested inside the button**. A
  button inside a button is invalid HTML, and nesting also lets the JS preview
  path (`zone.textContent = ''`) wipe the remove control out.

## Pass criteria

1. Trigger is `<button type="button">`; zero `div[class*="upload-zone"][data-wp-on--click]`
2. Reachable by Tab alone
3. Visible focus ring (2px solid, 2px offset) with `:focus-visible` matching
4. **Enter** activates · 5. **Space** activates
6. Ring survives dark mode · 7. Gallery still a button
8. ≥40px tall and no overflow at 390px
9. Remove control is a sibling of the trigger

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Trigger is a `DIV` again | template regressed | `templates/blocks/listing-submission/step-media.php` |
| A custom `file` field is unreachable | the generic renderer regressed | `includes/submission-field-renderer.php` — the `case 'file':` branch |
| Reachable but no visible ring | the `:focus-visible` rule was dropped or merged into `:hover` | `blocks/listing-submission/style.css` — `.listora-submission__upload-trigger:focus-visible`. A hover tint is not a focus indicator |
| Ring visible in light, gone in dark | the outline colour was hard-coded instead of `var(--listora-primary)` | same rule |
| Enter works, Space does not | someone replaced the `<button>` with a div plus a `keydown` handler | revert to a `<button>` — that is the entire point of the fix |
| Screen reader announces just "button" after picking an image | the preview `<img>` lost its `alt`, so the button has no accessible name | `src/blocks/listing-submission/view.js` — `img.alt` on the preview-insert path |
| RTL differs from LTR | hand-maintained twin not updated (`build-css.mjs` does not generate this one) | `blocks/listing-submission/style-rtl.css` |
