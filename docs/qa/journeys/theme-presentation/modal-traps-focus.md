---
journey: modal-traps-focus
group: theme-presentation
actor: customer
mode: browser
plugin: wb-listora
priority: high
roles: [guest, other, owner, admin]
covers: [a11y, modals, wcag, keyboard]
prerequisites:
  - "A published {listing} with reviews, claiming and reporting enabled; {other} is a logged-in member who does not own it"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, unbridged]
site_profile: required
supersedes: []
fails_on: "showReportModal() without the keydown trap (Report-review modal)"
---

# Tab must not walk out of an open modal

## Why
A keyboard or screen-reader member who tabs out of an open dialog ends up operating the page behind it (WCAG 2.4.3, 2.1.2), may submit a report or claim unintentionally, and cannot find their way back.

The listing-detail modals share `actions.openModal()` / `closeModal()` in `src/interactivity/store.js`: trap on `document` keydown scoped to `.listora-detail__modal.is-open [role="dialog"]`, focus-return via `state._modalTrigger`, `body.style.overflow` restore, listener removed on close. Four dialogs carry `aria-modal="true"`: Claim (`#listora-claim-modal`), Report listing, Login (`#listora-login-modal`) in `blocks/listing-detail/render.php`, plus Report review (`#listora-report-review-dialog`, `templates/blocks/reviews/report-modal.php`).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Use REAL key presses (`page.keyboard.press('Tab')`), 14 Tab presses + Shift+Tab + Escape per modal. Sign in as {other} for Claim and Report; stay {guest} for Login.
- Test-data trap: `dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Tab' } ) )` does NOT move focus in a real browser, so such a test passes with or without a trap.

## Steps

### 1. Tab cycles, it does not escape
- **Action**: focus the trigger BEFORE activating it (`button[data-wp-on--click="actions.showClaimModal"]` "Claim"; `actions.openReportModal` "Report"; as {guest} the Report button opens Login), press Enter, then Tab more times than the dialog has focusables.
- **Expect**: after every press `dialog.contains( document.activeElement )` is true (Claim, Report, Login over 14 presses). Fails if focus lands on any background element while `.listora-detail__modal.is-open` exists.

### 2. Shift+Tab wraps backwards
- **Expect**: from the first element Shift+Tab goes to the last, not out.

### 3. Focus starts inside
- **Expect**: on open focus is on the dialog (`tabindex="-1"`) or its first focusable child.

### 4. Escape and focus return
- **Expect**: Escape closes; `document.activeElement` is the trigger; `document.body.style.overflow === ''`. (A programmatic `.click()` never focuses the trigger, which makes focus-return look broken.)

### 5. The Report-review modal
- **Action**: as {other} open the Reviews tab, focus a `button.listora-detail__review-report-btn` (visible only on that tab), press Enter, then Tab 6+ times.
- **Expect**: focus stays inside `#listora-report-review-dialog` for every press.
- **expected-fail: report-review-modal keydown trap missing**: observed focus stays inside for 5 presses then leaves (presses 6-11 outside); `showReportModal()` (`src/interactivity/store.js` ~:2152) sets `state.activeModal` and focus but never installs the keydown trap `openModal()` installs (~:1873). Product bug; stays red until fixed.

### 6. The listener is removed on close
- **Action**: open and close the Claim modal 3 times, then Tab on the page.
- **Expect**: Tab moves through the page normally (`state._modalKeydown` nulled in `closeModal()`, ~:1943).

### 7. 390px
- **Expect**: dialog scrollable inside the viewport, close button >= 40px, no page scroll behind (`overflow: hidden`).

### 8. Dark mode
- **Expect**: same trap and a visible focus ring on dialog controls in dark mode.

## Role ladder
| Actor | Modals reachable |
|---|---|
| {guest} | Login (via Report / Favorite); no Claim button (claim needs login) |
| {other} | Claim, Report listing, Report review |
| {owner} | no Claim button on own listing; owner bar instead |
| {admin} / moderator (control) | same dialogs; the trap behaves identically |

## Pass criteria
1. In Claim, Report listing and Login Tab/Shift+Tab never leave the dialog, Escape returns focus to the trigger and restores scroll.
2. The trap listener is removed on close.
3. Report-review modal: spec is the same; currently expected-fail (step 5).

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Focus escapes Claim/Report/Login | `openModal` trap | `src/interactivity/store.js` (~:1861-1960) |
| Focus escapes Report review | no trap in `showReportModal` | `store.js` (~:2152) |
| Page scroll locked after close | `closeModal` overflow restore | `store.js` (~:1943) |
| Reference trap implementation | | `assets/js/shared/confirm.js` |

## Not verified
Observed 2026-10-12 with Playwright real key presses for steps 1-6; 390 and dark not run.

## Cleanup
None (no data written; close any open dialog).
