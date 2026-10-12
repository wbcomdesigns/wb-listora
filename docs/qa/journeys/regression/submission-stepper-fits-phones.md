---
journey: submission-stepper-fits-phones
plugin: wb-listora
priority: high
roles: [member-owner, logged-out]
covers: [listing-submission, mobile]
prerequisites:
  - "BuddyX Pro, Reign or another customer theme"
  - "WB Listora Pro active, so the wizard has six steps (Plan step)"
estimated_runtime_minutes: 4
covers_card: 10335688371
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1280, 700, 390, 360]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# The Add Listing step bar shows every step on a phone

Under 640px the step labels are hidden and the row is dots and connecting lines. The lines had a desktop `min-width: 2rem`, so six steps needed about 370px in a 300px row, and `justify-content: center` clipped both ends. The row now uses `justify-content: safe center` (`blocks/listing-submission/style.css:1281`), the line minimum is relaxed in the phone query, and `.listora-submission__step-dot` is `box-sizing: border-box`.

## Steps

### 1. Member at 390 and 360
- **Action**: as a member open `/add-listing/`; read `.listora-submission__progress`.
```js
const p = document.querySelector('.listora-submission__progress');
const r = p.getBoundingClientRect();
const d = [...p.querySelectorAll('.listora-submission__step-dot')].map(e => e.getBoundingClientRect());
({ scrollWidth: p.scrollWidth, clientWidth: p.clientWidth, first: d[0].left - r.left, last: r.right - d.at(-1).right, n: d.length, dot: d[0].width,
   justify: getComputedStyle(p).justifyContent, hscroll: document.documentElement.scrollWidth > document.documentElement.clientWidth })
```
- **Expect** at 390 (reference run 2026-10-12, BuddyX Pro): `scrollWidth === clientWidth` (313), `first >= 0` and `last >= 0` (0 and 0), `n === 6`, `justify: "safe center"`, `dot` about 35px, labels `.listora-submission__step-label` `display: none`, `hscroll: false`. Dots need to be fully visible, numbers readable.

### 2. Same at 700 and 1280
- **Expect**: labels visible; the row does not overflow; at 1280 the desktop layout is centred.

### 3. More steps than fit (filter)
- **Action**: add two steps with `wb_listora_submission_steps`, or clone two indicators in the DOM, at 390.
- **Expect**: step 1 still starts at the row's left edge (`safe center`); only the far end may clip.

### 4. Positive control: the Free-only wizard
- **Action**: with Pro deactivated (or on a site without Pro) the wizard has five steps; same checks hold with `n === 5`.

### 5. Role ladder
Member: steps 1-3. Logged out: `/add-listing/` shows the sign-in prompt and no `.listora-submission__progress` (control for the member run). Admin: same wizard as member.

### 6. Dark mode
Toggle dark; the dots keep contrast (active dot filled, done dot with check) and the same geometry.

## Fail diagnostics
- Both end dots clipped -> `.listora-submission__progress` lost `justify-content: safe center` (`blocks/listing-submission/style.css:1281`), or `.listora-submission__step-line` regained its 2rem minimum inside the phone query.
- Dots 39px instead of 35px -> `box-sizing: border-box` missing from `.listora-submission__step-dot`.
- RTL differs -> `blocks/listing-submission/style-rtl.css` (hand-maintained).

## Not verified
Step 1 was measured at 390 (not 360), BuddyX Pro, default (light) palette, via Playwright MCP on 2026-10-12. Steps 2-6 were not run. The previous note "scrollWidth stays 390" was wrong for this theme: the document's scrollWidth is 375 because of the scrollbar; compare `scrollWidth <= clientWidth` instead.

Verified via: Playwright MCP (Chromium).
