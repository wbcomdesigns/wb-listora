---
journey: listing-address-no-duplication
plugin: wb-listora
priority: normal
covers:
  - BC 10194590988
likely_files:
  - includes/class-template-helpers.php
  - blocks/listing-detail/render.php
---

# The listing header address reads once, and completely

Stored address data is often ALREADY a formatted street line containing the
city and state, with city/state/postal also stored separately. Concatenating
them produced "247 West Broadway, Manhattan, NY 10013, Manhattan, NY".

## Steps

1. Listing with `address` = "247 West Broadway, Manhattan, NY 10013",
   city "Manhattan", state "NY", postal "10013".
   - **Expect:** `247 West Broadway, Manhattan, NY 10013` — each part once.
2. Listing with a BARE street ("247 West Broadway") plus separate city, state
   and postal code.
   - **Expect:** `247 West Broadway, Manhattan, NY 10013`.
   - **Fail if:** the postal code is missing. It was stored and never
     rendered on any listing header — the reported case passed only because
     its code happened to sit inside the street line.
3. Listing with no street, only city/state/postal.
   - **Expect:** `Manhattan, NY 10013`.
4. Listing with an empty address.
   - **Expect:** an empty line, not stray commas.
