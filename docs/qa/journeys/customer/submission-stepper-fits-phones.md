---
journey: submission-stepper-fits-phones
plugin: wb-listora
priority: high
roles: [owner, guest, admin]
covers: [listing-submission, mobile, card-10335688371]
prerequisites:
  - "A customer theme (primary theme from the site profile or Reign)"
  - "Pro active so the wizard has six steps (Plan step); also a Free-only run for five"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1280, 700, 390, 360]
themes: [buddyx-pro light, buddyx-pro dark, unbridged]
site_profile: required
supersedes: []
fails_on: "step line min-width 2rem inside the phone query; justify-content: center clipping both ends"
---

# The Add Listing step bar shows every step on a phone

## Why
Under 640px the step labels are hidden and the row is dots joined by lines. The lines had a desktop `min-width: 2rem`, so six steps needed about 370px in a 300px row and `justify-content: center` clipped both end dots, hiding step 1 and the last step from phone members.

The row now uses `justify-content: safe center` (`blocks/listing-submission/style.css`), the line minimum is relaxed in the phone query, and `.listora-submission__step-dot` is `box-sizing: border-box`.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Sign in as {owner} and open Add Listing.

## Steps

### 1. {owner} at 390 and 360
- **Action**: read `.listora-submission__progress`:
  ```js
  const p = document.querySelector('.listora-submission__progress');
  const r = p.getBoundingClientRect();
  const d = [...p.querySelectorAll('.listora-submission__step-dot')].map(e => e.getBoundingClientRect());
  ({ scrollWidth: p.scrollWidth, clientWidth: p.clientWidth, first: d[0].left - r.left, last: r.right - d.at(-1).right, n: d.length, dot: d[0].width,
     justify: getComputedStyle(p).justifyContent, hscroll: document.documentElement.scrollWidth > document.documentElement.clientWidth })
  ```
- **Expect** at 390 and again at 360: `scrollWidth === clientWidth`, `first >= 0` and `last >= 0`, `n === 6`, `justify: "safe center"`, `dot` about 35px, `.listora-submission__step-label` `display: none`, `hscroll: false`; dots fully visible, numbers readable. (The document `scrollWidth` can be smaller than the viewport because of the scrollbar; compare `scrollWidth <= clientWidth`.)

### 2. Same at 700 and 1280
- **Expect**: labels visible; the row does not overflow; at 1280 the desktop layout is centred.

### 3. More steps than fit (filter)
- **Action**: add two steps with `wb_listora_submission_steps`, or clone two indicators in the DOM, at 390.
- **Expect**: step 1 still starts at the row's left edge (`safe center`); only the far end may clip.

### 4. Positive control: the Free-only wizard
- **Action**: with Pro deactivated (scratch site) the wizard has five steps.
- **Expect**: the same checks hold with `n === 5`.

### 5. Dark mode
- **Action**: toggle dark.
- **Expect**: the dots keep contrast (active dot filled, done dot with check) and the same geometry.

### 6. RTL
- **Action**: `html[dir=rtl]`.
- **Expect**: first/last measurements mirror; no clipping. Not verified live.

## Role ladder
| Rung | Expect |
|---|---|
| {owner} | steps 1-6 |
| {guest} | Add Listing shows the sign-in prompt and no `.listora-submission__progress` (control for the member run) |
| {admin} | same wizard as {owner} |

## Pass criteria
1. At 390 and 360 every dot is fully visible, `first` and `last` >= 0, no row overflow.
2. Labels reappear at 700 and the desktop layout is unchanged.
3. Free-only (five steps) and filtered (more steps) cases degrade without clipping the start.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Both end dots clipped | `justify-content: safe center` lost, or `.listora-submission__step-line` regained its 2rem minimum inside the phone query | `blocks/listing-submission/style.css` (~:1281) |
| Dots 39px instead of 35px | `box-sizing: border-box` missing on `.listora-submission__step-dot` | same |
| RTL differs | hand-maintained twin | `blocks/listing-submission/style-rtl.css` |

## Not verified
Step 1 was measured at 390 (not 360), BuddyX Pro, light, via Playwright MCP on 2026-10-12. Steps 2-6 not run.

## Cleanup
Reactivate Pro if it was deactivated for step 4; delete any autosaved draft.
