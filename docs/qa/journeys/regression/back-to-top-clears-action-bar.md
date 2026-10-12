---
journey: back-to-top-clears-action-bar
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [listing-detail, theme-fit, mobile]
prerequisites:
  - "BuddyX Pro (or BuddyX / Reign) active: the theme's back-to-top button is `#scrollUp`; the reference site runs buddyx-pro"
  - "A published listing with a phone or website (so the bar has Call / Visit), e.g. /listing/fitlab-performance-gym/"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# The theme back-to-top button does not cover the listing action bar

## Background

Reign and BuddyX pin `#scrollUp` to the bottom corner above everything (BuddyX Pro: `bottom: 20px; right: 20px; 50x50`, `assets/css/src/global.css:37`). On a phone it sat on top of the Save button in the fixed Call / Visit / Save bar (`.listora-detail__mobile-bar`) and took its taps. The fix lives in `assets/css/listora-isolation.css:50-61`, the one unlayered Listora stylesheet, because Listora's layered CSS loses to the theme's unlayered rule: `@media (max-width: 768px) { body:has(.listora-detail__mobile-bar) #scrollUp { bottom: calc(4.5rem + env(safe-area-inset-bottom, 0px)); } }`.

## Steps

### 1. Listing page at 390
- **Action**: clear the browser cache (or hard reload so `listora-isolation.css` is fresh), open a listing at 390x844, scroll until the back-to-top button shows (`#scrollUp`; the theme injects/reveals it on scroll, it is not in the served HTML).
- **Expect**: the button's bottom edge is above the bar's top edge (`#scrollUp` rect bottom <= `.listora-detail__mobile-bar` rect top); `document.elementFromPoint` at 10%, 50% and 90% across the bar's width returns the bar's Call, Visit and Save controls (`a.listora-btn--primary` "Call", `a.listora-btn--secondary` "Visit", `button[aria-label="Save to favorites"]` "Save"), never `#scrollUp`.

### 2. POSITIVE CONTROL: a page without the bar
- **Action**: home page at 390, scrolled.
- **Expect**: back-to-top keeps the theme's own position (`bottom: 20px` on BuddyX Pro, the unchanged theme rule). This proves the lift is scoped to pages that have the bar (`body:has(...)`), not applied globally.

### 3. Above the breakpoint
- **Action**: the listing page at 1440 (and 820).
- **Expect**: the bar is a mobile-only element and `#scrollUp` sits at the theme's `bottom: 20px`; the media query (<= 768px) does not apply.

### 4. Role ladder
Presentation only and the same for every viewer. Control: a logged-in member sees the same bar and the same lift (the bar is rendered regardless of login; its Save button, when logged out, opens the login modal).

### 5. Dark mode and an unknown theme
- **Expect**: same geometry under the dark body class. On a theme with no `#scrollUp` the rule is inert and nothing errors.

## Fail diagnostics
- Button overlaps the bar: the `body:has(.listora-detail__mobile-bar) #scrollUp` rule is missing from `assets/css/listora-isolation.css:57-61` or was moved into a layered stylesheet.
- Bar buttons not rendered: `blocks/listing-detail/render.php:1027-1043` (needs `$phone`, `$website` or favorites on).

Verified via: source for the rule and the bar markup, plus the served listing HTML (bar contents). The overlap geometry itself needs a browser at 390px and was not measured in this pass.
