---
journey: listing-address-no-duplication
plugin: wb-listora
priority: normal
roles: [anonymous, admin]
covers:
  - BC 10194590988
  - address
prerequisites:
  - "Fixtures: four QA-J1 listings with the address meta below (or use live listings 551, 388, 466)"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# The listing header address reads once, and completely

Stored address data is often already a formatted street line containing city and state, with city/state/postal also stored separately. `wb_listora_format_address_parts()` (`includes/class-template-helpers.php:2139`) strips a component the street line already holds (whole-word match), appends the postal code to the state ("Manhattan, NY 10013"), and `wb_listora_format_address_line()` (`:2118`) joins street + locality for `address.listora-detail__address` (`blocks/listing-detail/render.php:183,513`). The meta is `_listora_address` (array: address, city, state, postal_code, country, lat, lng).

Verified via: `wb_listora_format_address_line()` on live meta and the rendered header of listing 551, 2026-10-12. Fixtures 2-4 are synthetic and were not created.

## Steps

1. **Formatted street line (POSITIVE CONTROL for step 2)**: `address` "247 West Broadway, Manhattan, NY 10013", city "Manhattan", state "NY", postal "10013".
   - **Expect**: `247 West Broadway, Manhattan, NY 10013`, each part once. Live: listing 551 renders "455 West 23rd St, Chelsea, NY 10011" with city Chelsea / state NY stored (verified).
2. **Bare street**: `address` "247 West Broadway" + city, state, postal.
   - **Expect**: `247 West Broadway, Manhattan, NY 10013`. **Fail if** the postal code is missing.
3. **No street**: only city/state/postal.
   - **Expect**: `Manhattan, NY 10013`.
4. **Empty address**: the `<address>` element is absent (the template guards `if ( $location )`), no stray commas.
5. **Postal inside the street line is not repeated**: live listing 466 "1 World Trade Center, NY 10007" + city New York renders "1 World Trade Center, NY 10007, New York" (verified; city is appended because it is not in the street).
6. **Role ladder**: public output: anonymous, member, owner, editor, admin identical. Admin is the control for editing the meta.
7. **390px**: address wraps, no horizontal scroll. Not run.

## Fail diagnostics

| Symptom | File |
|---|---|
| Parts repeated | `includes/class-template-helpers.php:2139` containment regex |
| Postal missing | same function, postal block (`:2171-2182`) |
| Stray commas | `wb_listora_format_address_line()` `:2118` `array_filter` |
