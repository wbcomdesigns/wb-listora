---
journey: modal-traps-focus
plugin: wb-listora
priority: high
roles: [anonymous, member]
covers: [a11y, modals, wcag, keyboard]
prerequisites:
  - "A published listing (demo: /listing/the-golden-fork/) with reviews, claiming and reporting enabled; one logged-in member who does not own it (author1)"
estimated_runtime_minutes: 7
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Tab must not walk out of an open modal

The listing-detail modals share `actions.openModal()` / `closeModal()` (`src/interactivity/store.js:1861-1960`): trap on `document` keydown scoped to `.listora-detail__modal.is-open [role="dialog"]`, focus-return via `state._modalTrigger`, `body.style.overflow` restore, listener removed on close. FOUR dialogs carry `aria-modal="true"`: Claim (`#listora-claim-modal`), Report listing, Login (`#listora-login-modal`) in `blocks/listing-detail/render.php:953/989/1076`, plus Report review (`#listora-report-review-dialog`, `templates/blocks/reviews/report-modal.php:20`).

Verified via: Playwright with REAL key presses (`page.keyboard.press('Tab')`), 14 Tab presses + Shift+Tab + Escape per modal, logged in as author1 (Claim, Report) and logged out (Login). The Report-review modal FAILS this journey; see step 5.

## Steps

### 1. Tab cycles, it does not escape
- **Action**: focus the trigger (`button[data-wp-on--click="actions.showClaimModal"]` "Claim"; `actions.openReportModal` "Report"; logged out the Report button opens Login), press Enter, then press Tab more times than the dialog has focusables.
- **Expect**: after every press `dialog.contains( document.activeElement )` is true (observed true for Claim, Report and Login over 14 presses).
- **Fails if**: focus lands on any background element while `.listora-detail__modal.is-open` exists.

### 2. Shift+Tab wraps backwards
- **Expect**: from the first element Shift+Tab goes to the last, not out (observed inside).

### 3. Focus starts inside
- **Expect**: on open focus is on the dialog (given `tabindex="-1"`) or its first focusable child.

### 4. Escape and focus return
- **Expect**: Escape closes; `document.activeElement` is the trigger (observed `actions.showClaimModal` / `actions.openReportModal`); `document.body.style.overflow === ''`. Focus the trigger BEFORE activating it (a programmatic `.click()` never focuses it, which makes focus-return look broken).

### 5. The Report-review modal (currently FAILS)
- **Action**: logged in as a member, open the Reviews tab of the listing, focus a `button.listora-detail__review-report-btn` (visible only on that tab), press Enter, then Tab 6+ times.
- **Expect (spec)**: focus stays inside `#listora-report-review-dialog`.
- **Observed 2026-10-12**: stays inside for 5 presses, then leaves the dialog (presses 6-11 all outside). `showReportModal()` (`src/interactivity/store.js:2152`) sets `state.activeModal` and focus but never installs the keydown trap that `openModal()` installs at `:1873`. Product bug; this step stays red until fixed.

### 6. The listener is removed on close
- **Action**: open and close the Claim modal 3 times, then Tab on the page.
- **Expect**: Tab moves through the page normally (`state._modalKeydown` is nulled in `closeModal()` `:1943`).

### 7. Role ladder
| Actor | Modals reachable |
|---|---|
| Logged out | Login (via Report / Favorite), no Claim button (claim needs login) |
| Member (not owner) | Claim, Report listing, Report review |
| Owner | no Claim button on own listing; owner bar instead |
| Moderator / admin (control) | same dialogs; trap must behave identically |

### 8. 390px
- **Expect**: dialog scrollable inside the viewport, close button >= 40px, no page scroll behind (`overflow: hidden`).

## Test-data trap
`dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Tab' } ) )` does NOT move focus in a real browser, so such a test passes with or without a trap. Always use the driver's real key press.

## Fail diagnostics
`src/interactivity/store.js:1861` (`openModal` trap), `:2152` (`showReportModal`, no trap), `assets/js/shared/confirm.js` (the reference trap implementation).
