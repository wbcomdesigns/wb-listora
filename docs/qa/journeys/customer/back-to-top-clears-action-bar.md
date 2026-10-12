---
journey: back-to-top-clears-action-bar
plugin: wb-listora
priority: high
roles: [guest, other]
covers: [listing-detail, theme-fit, mobile]
prerequisites:
  - "BuddyX Pro (or BuddyX / Reign) active: the theme's back-to-top button is `#scrollUp`"
  - "A published {listing} with a phone or website (so the bar has Call / Visit)"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [390, 820, 1440]
themes: [buddyx-pro light, buddyx-pro dark, unbridged]
site_profile: required
supersedes: []
fails_on: "body:has(.listora-detail__mobile-bar) #scrollUp rule missing from listora-isolation.css"
---

# The theme back-to-top button does not cover the listing action bar

## Why
Reign and BuddyX pin `#scrollUp` to the bottom corner above everything. On a phone it sat on top of the Save button in the fixed Call / Visit / Save bar (`.listora-detail__mobile-bar`) and took its taps, so members could not save a listing.

The fix lives in `assets/css/listora-isolation.css`, the one unlayered Listora stylesheet, because Listora's layered CSS loses to the theme's unlayered rule: `@media (max-width: 768px) { body:has(.listora-detail__mobile-bar) #scrollUp { bottom: calc(4.5rem + env(safe-area-inset-bottom, 0px)); } }`.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Hard reload so `listora-isolation.css` is fresh.

## Steps

### 1. Listing page at 390
- **Action**: open {listing} at 390x844 as {guest}; scroll until the back-to-top button shows (`#scrollUp`; the theme injects it on scroll).
- **Expect**: `#scrollUp` rect bottom <= `.listora-detail__mobile-bar` rect top; `document.elementFromPoint` at 10%, 50% and 90% across the bar returns its Call, Visit and Save controls (`a.listora-btn--primary` "Call", `a.listora-btn--secondary` "Visit", `button[aria-label="Save to favorites"]`), never `#scrollUp`.

### 2. POSITIVE CONTROL: a page without the bar
- **Action**: home page at 390, scrolled.
- **Expect**: back-to-top keeps the theme's own position (the unchanged theme rule), proving the lift is scoped by `body:has(...)`.

### 3. Above the breakpoint
- **Action**: the listing page at 1440 and 820.
- **Expect**: the bar is mobile-only and `#scrollUp` sits at the theme's own bottom offset.

### 4. Dark mode and an unknown theme
- **Expect**: same geometry under the dark body class. On a theme with no `#scrollUp` the rule is inert and nothing errors.

### 5. Bar contents per state
- **Action**: a listing with only a phone, only a website, neither (N=0); logged-in {other}.
- **Expect**: the lift applies whenever the bar is rendered; the bar is rendered regardless of login; the Save button opens the login modal for {guest}. Not verified live.

## Role ladder
| Rung | Expect |
|---|---|
| {guest} | steps 1-4 |
| {other} (control) | same bar and same lift; Save toggles favourite instead of opening the login modal |

## Pass criteria
1. On a bar page at <= 768px the back-to-top button clears the bar and never intercepts its taps.
2. Pages without the bar and widths above 768px keep the theme position.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Button overlaps the bar | rule missing or moved into a layered stylesheet | `assets/css/listora-isolation.css` (~:50-61) |
| Bar buttons not rendered | needs `$phone`, `$website` or favourites on | `blocks/listing-detail/render.php` (~:1027-1043) |

## Not verified
Overlap geometry needs a browser at 390 and was not measured in the observed pass (source and served HTML only).

## Cleanup
None.
