---
journey: submission-success-centered
plugin: wb-listora
priority: normal
roles: [member]
covers: ["#9962418696", "post-submission success card alignment"]
prerequisites:
  - "Submittable listing type; logged-in member"
estimated_runtime_minutes: 3
---

# Post-submission success card is centered as a unit on every theme width

Card #9962418696: after submitting a listing, the success message read as
misaligned. `.listora-submission__success` had `text-align: center` but no
width cap — it spanned the theme's full content column, so on wide/odd
content columns the message didn't read as a centered unit, and at mobile
widths the two action buttons sat edge-to-edge instead of centered.

Fix (`blocks/listing-submission/style.css` + RTL twin): the success card gets
`max-width: 520px; margin-inline: auto` (mirroring the verify-email card),
`flex-wrap` on the actions row, and a ≤640px breakpoint that stacks the
buttons full-width with tighter padding.

## Steps

### 1. Real submission → centered card (desktop)
- **Action**: walk Add Listing end-to-end (type → basic info → details with
  address → media with featured image → plan → preview, tick Terms) and
  submit for real.
- **Expect**: form hides; `.listora-submission__success` shows, computed
  width ≤ 520px, horizontally centered in its container (icon, heading, text,
  and both buttons centered).

### 2. 390px
- **Action**: repeat (or re-render the success state) at 390px.
- **Expect**: card centered with no horizontal overflow; action buttons
  stacked vertically, full-width, inside the card.

### 3. Cleanup
- Trash the pending listing the walk created.
