---
journey: listing-mobile-bar-clears-footer
group: listing-detail
actor: customer
mode: browser
plugin: wb-listora
priority: high
roles: [guest, owner]
covers: [listing-detail, mobile, theme-cascade]
prerequisites:
  - "Reign or BuddyX active; a published listing"
estimated_runtime_minutes: 5
last_verified: 2026-11-08
verified_against: free 2.1.1
viewports: [390, 1440]
themes: [reign, buddyx-pro]
site_profile: required
---

# The sticky Call / Save bar never covers the footer

## Why
The bar is `position: fixed` and 64 px tall. Its room was reserved with `body` padding inside `@layer listora`, which any unlayered theme rule (`body { padding: 0 }`) beats, so the footer could not scroll clear of the bar. A `body::after` spacer is not something themes style.

## Steps
### 1. Footer clears the bar at 390
- **Action**: open `$SITE_URL/listing/{listing}/` at 390x844 on Reign, then BuddyX and a Twenty-series theme; scroll to the very bottom; read `footer.getBoundingClientRect().bottom` and `.listora-detail__mobile-bar` top.
- **Expect**: footer bottom <= bar top on every theme; footer text readable.

### 2. Nothing at desktop
- **Action**: same page at 1440.
- **Expect**: the bar is `display: none` and `getComputedStyle(document.body, '::after').content` is `none`.
