---
journey: plus-code-search-and-detail
group: directory-search
actor: customer
mode: browser
plugin: wb-listora
priority: normal
roles: [visitor]
covers: [search, plus-codes, listing-detail, rest-search, "10439305996"]
prerequisites:
  - "A published listing with coordinates (note its lat/lng)"
estimated_runtime_minutes: 4
last_verified: 2026-11-09
verified_against: free 2.1.1
viewports: [1440, 390]
themes: [reign]
site_profile: required
---

# A Plus Code is shown on the listing and finds listings near it

## Why
Places without a street address (a lot, a trailhead, a rural farm) are named by a Plus Code. The listing shows its code, and typing a full code in search means "near here" instead of a text match that finds nothing.

## Steps
### 1. The code on the listing
- **Action**: open the listing page at 1440.
- **Expect**: the Location card shows "Plus code" and a ten-character code like `87G7GQ54+J2` under the address; the code matches the listing's coordinates (any Open Location Code encoder gives the same code).

### 2. Search by a full code
- **Action**: on `{directory}` type that code in the search box and submit (or call `/listora/v1/search?q=<code>`).
- **Expect**: the listing is in the results; the keyword is not treated as text.

### 3. A code elsewhere finds nothing
- **Action**: search `8FVC9G8F+6W` (Zurich).
- **Expect**: no results, no error.

### 4. Text and short codes are not places
- **Action**: search `pizza`, then `9G8F+6W`.
- **Expect**: both are plain keyword searches (a short code needs a reference town and is not accepted).

### 5. Phone width
- **Action**: repeat step 1 at 390.
- **Expect**: the code sits on one line or wraps inside the card; `scrollWidth` is 390.

### 6. REST
- **Action**: GET `/listora/v1/listings/{id}/detail`.
- **Expect**: `geo.plus_code` equals the code shown on the page.
