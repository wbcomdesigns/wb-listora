---
journey: modal-traps-focus
plugin: wb-listora
priority: high
roles: [anonymous, member]
covers: [a11y, modals, wcag, keyboard]
prerequisites: ["A published listing rendering the Claim / Report / Login modals"]
estimated_runtime_minutes: 5
---

# Tab must not walk out of an open modal

The listing-detail modals (claim, report, login) handled Escape-to-close,
backdrop-click-to-close, and focus-return-on-close. Every visible affordance
worked, so they read as complete. But **Tab walked straight out of the dialog
into the page behind it** while the modal stayed open and blocking: seven Tab
presses from an open Claim modal landed on the site footer credit link.

`assets/js/shared/confirm.js` had implemented the trap correctly all along; the
detail modal family simply never adopted it. WAI-ARIA APG requires it for
anything carrying `aria-modal="true"`.

## Steps

### 1. Tab cycles, it does not escape
Open the Claim modal. Press **real browser Tab** (not a synthesized
`KeyboardEvent` - a dispatched event does not move focus, so a synthetic test
passes against broken code) more times than the modal has focusable elements.
- `dialog.contains( document.activeElement )` stays `true` throughout.
- **Fails if** focus ever lands on a background element while the modal is open.

### 2. Shift+Tab wraps backwards
From the first element, Shift+Tab moves to the last, not out.

### 3. Focus starts inside
On open, focus is on the dialog or its first focusable child - never left on the
page behind.

### 4. Escape and focus-return still work
Escape closes; focus returns to the element that opened it; `body.style.overflow`
is restored.
- Focus the trigger BEFORE activating it. A programmatic `.click()` never focuses
  the element, so `_modalTrigger` captures whatever had focus instead and
  focus-return appears broken when it is not.

### 5. All three modals
Claim, report and login share the markup and the handler. Check each.

### 6. The listener is removed on close
Open and close 3 times, then Tab on the page.
- **Fails if** Tab is still being intercepted - the keydown listener leaked.

## Test-data trap

Dispatching `new KeyboardEvent('keydown',{key:'Tab'})` does NOT move focus in a
real browser; the assertion then passes whether or not a trap exists. Use the
driver's real key press.
