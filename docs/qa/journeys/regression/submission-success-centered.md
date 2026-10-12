---
journey: submission-success-centered
plugin: wb-listora
priority: normal
roles: [member, logged-out, admin]
covers: ["#9962418696", "post-submission success card alignment"]
prerequisites:
  - "Submittable listing type; logged-in member with credits for the plan/cost (or credits cost 0 and no published plan)"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Post-submission success card is centered as a unit on every theme width

`.listora-submission__success` (`templates/blocks/listing-submission/submission.php:109`) has `text-align: center`, `max-width: 520px; margin-inline: auto` (mirrors the verify-email card), the actions row `.listora-submission__success-actions` wraps, and at <= 640px the buttons stack full width with tighter padding. The card lives outside the form: `handleSubmission()` hides the form and shows the card.

## Steps

### 1. Real submission -> centered card (desktop)
- **Action**: walk Add Listing end to end (type, basic info, details with address, media with featured image, plan, preview; tick Terms) and submit.
- **Expect**: form hidden; `.listora-submission__success` visible, computed width <= 520px, horizontally centred (left gap equals right gap within 1px); icon, `h2` "Listing Submitted!", the paragraph and two buttons ("Go to Dashboard", "Add Another Listing") centred.
- **Positive control**: before submit the same element is `hidden`.

### 2. 390px
- **Action**: repeat (or force-show the card: `s.hidden = false`).
- **Expect**: card inside the viewport with equal side gaps, no horizontal scroll, the two buttons stacked vertically at card width, each >= 40px tall. Reference 2026-10-12 (force-shown, BuddyX Pro, 390): card 297px wide at left 38.8, buttons 267x44 each, `scrollWidth <= clientWidth`.

### 3. Edit mode variant
- **Action**: Dashboard > Edit > Update Listing.
- **Expect**: heading "Listing Updated!"; actions "Go to Dashboard" and "View Listing"; same centring. A saved draft submitted from the dashboard reads "Listing Submitted!" (2.0.0).

### 4. Role ladder
Member: steps 1-3. Logged out: no wizard, so no card. Admin: same card (control).

### 5. Cleanup
Trash the pending listing the walk created.

## Fail diagnostics
| Symptom | File |
|---|---|
| Card spans full column | `blocks/listing-submission/style.css:1202` (`max-width: 520px`; RTL twin `style-rtl.css`) |
| Buttons edge to edge on phone | `blocks/listing-submission/style.css:1220-1240` (`.listora-submission__success-actions`, <= 640px query at 1232) |
| Card hidden after success | `src/blocks/listing-submission/view.js:372` success branch |

## Not verified
A real end-to-end submission was not run (needs a plan or credits and a real featured upload). Only step 2's geometry on a force-shown card was measured (Playwright, 390, BuddyX Pro light). Desktop 1440 width, dark mode and edit variant were not measured.

Verified via: Playwright MCP (force-shown card).
