---
journey: back-to-top-clears-action-bar
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [listing-detail, theme-fit, mobile]
prerequisites:
  - "Combo, Reign or BuddyX theme, 390px viewport"
estimated_runtime_minutes: 3
---

# The theme back-to-top button does not cover the listing action bar

## Background

Found in the 1.9.0 overlay audit. Reign and BuddyX pin `#scrollUp` to the bottom corner above everything; on a phone it sat on top of the Save button in the fixed Call / Visit / Save bar and took its taps. The first fix was written in the block stylesheet and did nothing: Listora CSS is layered and loses to the theme's unlayered rule. It lives in `listora-isolation.css`, the one unlayered sheet.

## Steps

### 1. Listing page at 390
Clear the browser cache, open a listing, scroll down until the back-to-top button shows.
- **Expect**: the button's bottom edge is above the bar's top edge; `elementFromPoint` at 10%, 50% and 90% across Call, Visit and Save returns that button.

### 2. A page without the bar
Home page at 390, scrolled.
- **Expect**: back-to-top keeps the theme's own position (20px from the bottom on Reign).

## Fail diagnostics
- Button overlaps the bar -> the `body:has(.listora-detail__mobile-bar) #scrollUp` rule is missing from `assets/css/listora-isolation.css`, or was moved into a layered stylesheet.
