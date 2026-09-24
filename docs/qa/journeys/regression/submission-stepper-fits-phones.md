---
journey: submission-stepper-fits-phones
plugin: wb-listora
priority: high
roles: [member-owner]
covers: [listing-submission, mobile]
prerequisites:
  - "Reign or BuddyX (customer theme)"
  - "WB Listora Pro active, so the wizard has six steps (Plan step)"
estimated_runtime_minutes: 3
covers_card: 10335688371
---

# The Add Listing step bar shows every step on a phone

Regression sentinel for the submission progress row at phone width.

## Background

Under 640px the step labels are hidden, so the row is dots and connecting lines only. The lines kept their desktop `min-width: 2rem`, so six steps needed about 370px while a 390px phone gives the row about 300px. The row is `justify-content: center`, which clips overflow evenly on both sides: step 1 lost its left edge and step 6 its right edge. The dot was also content-box, so it grew 4px on themes that do not reset sizing.

## Steps

### 1. Member, 390px and 360px
- **Action**: as a member, open Add Listing.
- **Expect**: the progress row's `scrollWidth` equals its width; the first dot's left edge and the last dot's right edge are inside the row; all six numbers are fully visible; no horizontal page scroll.

### 2. Same at 700 and 1280
- **Expect**: labels visible; row does not overflow; 1280 keeps the centred desktop layout.

### 3. More steps than fit (filter)
- **Action**: add two extra steps with `wb_listora_submission_steps`, or clone two indicators in the DOM, at 390px.
- **Expect**: step 1 still starts at the row's left edge (`safe center`); only the far end may clip.

## Fail diagnostics
- Both end dots clipped → `.listora-submission__progress` lost `justify-content: safe center`, or `.listora-submission__step-line` got its 2rem minimum back inside the 768px query.
- Dots 39px instead of 35px → `box-sizing: border-box` missing from `.listora-submission__step-dot`.
