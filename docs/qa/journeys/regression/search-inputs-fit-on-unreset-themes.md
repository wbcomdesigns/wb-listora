---
journey: search-inputs-fit-on-unreset-themes
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [listing-search, form-inputs, mobile]
prerequisites:
  - "A theme that does not reset input box-sizing (Twenty Twenty-Five) - installed per browser, never switched site-wide"
  - "The Directory page ($SITE_URL/listings/) with the Listing Search block"
estimated_runtime_minutes: 3
covers_card: 10331467188
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820, 390]
themes: [buddyx-pro light, buddyx-pro dark, Twenty Twenty-Five]
changed: true
---

# The directory search bar fits a phone screen on themes that do not reset inputs

Regression sentinel for `.listora-input` box-sizing. Verified via: source read (`assets/css/listora-base.css:459-465` sets `box-sizing: border-box` on `.listora-input`, with the card number in a comment). The 390px layout measurement was NOT run in a browser (shared browser unavailable) - the three steps below are the contract to run.

## Steps

### 1. Twenty Twenty-Five, logged out, 390px
- **Action**: preview Twenty Twenty-Five (`?customize_theme=twentytwentyfive` as admin, or a pristine install), open the Directory.
- **Expect**: `document.documentElement.scrollWidth === innerWidth`; `#listora-keyword` (type=search) and `#listora-location` (type=text) have the same `getBoundingClientRect().width` and `getComputedStyle(el).boxSizing === 'border-box'`; Search button (`.listora-search__submit`) >= 40px tall.

### 2. Same at 820 and 1440
- **Expect**: no horizontal scroll; at 1440 the Location field sits beside the keyword field.

### 3. Control on the customer theme (BuddyX Pro, light and dark)
- **Expect**: layout unchanged vs. 390/1440; dashboard Profile tab inputs stay inside their containers; positive control that proves the check can fail: on Twenty Twenty-Five temporarily override `.listora-input{box-sizing:content-box}` and confirm the Location field overflows.

### 4. Role ladder
Anonymous (the bounce) and a logged-in member (author1) see the same bar; admin as control. The bar has no role-dependent markup.

## Fail diagnostics
- Location wider than its column: `box-sizing` missing from `.listora-input` in `assets/css/listora-base.css:459-465` (also in the `.layer.css` and `-rtl` twins) or a later rule sets `content-box`.
