---
journey: submission-success-card-centered
plugin: wb-listora
priority: normal
roles: [owner, guest, admin]
covers: ["#9962418696", "post-submission success card alignment"]
prerequisites:
  - "A submittable listing type; logged-in member {owner} with credits for the plan/cost (or credits cost 0 and no published plan)"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes:
  - ora:regression/submission-success-centered.md
fails_on: unrecorded
site_profile: required
---

# The post-submission success card is centred as a unit at every width

## Why
`.listora-submission__success` (`templates/blocks/listing-submission/submission.php:109`) has `text-align: center`, `max-width: 520px; margin-inline: auto` (mirrors the verify-email card); the actions row wraps and at <= 640px the buttons stack full width. The card lives outside the form: `handleSubmission()` hides the form and shows the card.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).

## Steps

### 1. Real submission -> centred card (desktop)
- **Action**: walk Add Listing end to end (type, basic info, details with address, media with featured image, plan, preview; tick Terms) and submit.
- **Expect**: form hidden; `.listora-submission__success` visible, computed width <= 520px, horizontally centred (left gap equals right gap within 1px); icon, `h2` "Listing Submitted!", the paragraph and two buttons ("Go to Dashboard", "Add Another Listing") centred.
- **Positive control**: before submit the same element is `hidden`.

### 2. 390px
- **Action**: repeat (or force-show the card: `s.hidden = false`).
- **Expect**: card inside the viewport with equal side gaps, no horizontal scroll, the two buttons stacked at card width, each >= 40px tall (reference force-shown on BuddyX Pro at 390: card 297px wide, buttons 267x44, `scrollWidth <= clientWidth`).

### 3. Edit mode variant
- **Action**: Dashboard > Edit > Update Listing.
- **Expect**: heading "Listing Updated!"; actions "Go to Dashboard" and "View Listing"; same centring. A saved draft submitted from the dashboard reads "Listing Submitted!" (2.0.0).

### 4. Dark mode and RTL (added)
- **Expect**: card readable in dark (token colours), mirrored correctly in RTL (`style-rtl.css`). Not verified live.

## Role ladder
| Actor | Expect |
|---|---|
| {owner} | steps 1-3 |
| {guest} | no wizard, so no card |
| {admin} | same card (control) |

## Pass criteria
Card is <= 520px, centred with equal gaps at 1440 and 390, buttons stack at <= 640px, edit variant uses its own headings.

## Fail diagnostics
| Symptom | File |
|---|---|
| Card spans full column | `blocks/listing-submission/style.css:1202` (`max-width: 520px`; RTL twin `style-rtl.css`) |
| Buttons edge to edge on phone | `style.css:1220-1240` (`.listora-submission__success-actions`, <= 640px query at 1232) |
| Card hidden after success | `src/blocks/listing-submission/view.js:372` success branch |

## Not verified
A real end-to-end submission (needs a plan or credits and a real featured upload); only step 2's geometry on a force-shown card was measured (390, BuddyX Pro light). 1440, dark and the edit variant were not measured.

## Cleanup
Trash the pending listing the walk created.
