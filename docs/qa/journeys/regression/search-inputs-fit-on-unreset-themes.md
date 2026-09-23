---
journey: search-inputs-fit-on-unreset-themes
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [listing-search, form-inputs, mobile]
prerequisites:
  - "A theme that does not reset input box-sizing (Twenty Twenty-Five)"
  - "The Directory page with the Listing Search block"
estimated_runtime_minutes: 3
covers_card: 10331467188
---

# The directory search bar fits a phone screen on themes that do not reset inputs

Regression sentinel for `.listora-input` box-sizing.

## Background

`.listora-input` set `width: 100%` and padding but no `box-sizing`. The keyword field is `type="search"`, which the browser sizes border-box; the Location field is `type="text"`, which it does not. On Twenty Twenty-Five the Location field rendered 75px wider than its column and the directory page scrolled sideways by 36px at 390px. Reign and similar themes reset inputs, so the Reign-based smoke never saw it. Found by the 1.8.0 pristine Docker install check.

## Steps

### 1. Twenty Twenty-Five, logged out, 390px
- **Action**: open the Directory page.
- **Expect**: `document.documentElement.scrollWidth === innerWidth`; the keyword and Location inputs are the same width and both `box-sizing: border-box`.

### 2. Same at 768 and 1280
- **Expect**: no horizontal scroll; at 1280 the Location field sits beside the keyword field.

### 3. Control on the customer theme (Reign)
- **Expect**: unchanged layout at 390 and 1280; dashboard Profile tab inputs stay inside their containers.

## Fail diagnostics
- Location wider than its column → `box-sizing` missing from `.listora-input` in `assets/css/listora-base.css`, or a later rule sets `content-box`.
