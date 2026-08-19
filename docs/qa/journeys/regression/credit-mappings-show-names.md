---
journey: credit-mappings-show-names
plugin: wb-listora
priority: normal
covers:
  - BC 10208171587
likely_files:
  - ../wb-listora-pro/includes/class-pro-plugin.php
---

# Active Mappings names its provider and product

A mapping stored in the nested shape carries no labels — it is literally
`[ adapter => [ id => credits ] ]`. Only the ADD path resolved names, so rows
that arrived any other way reached the table with empty labels, and the
display used `??`, which falls back only on an ABSENT key and not on an empty
string. Result: a blank Provider cell and a bare `#`.

## Steps

1. With a WooCommerce credit mapping configured, open
   Listora → Settings → Credits and find Active Mappings.
   - **Expect:** Provider reads "WooCommerce" and Product reads the product
     name, e.g. "50 Credit Pack".
   - **Fail if:** Provider is blank or Product is just `#` or `#3627`.
2. Delete the mapped product (or deactivate WooCommerce) and reload.
   - **Expect:** the row degrades to the raw identifiers — `woocommerce` and
     `#3627` — never to blank cells. An unresolvable name still has to say
     what is mapped; a blank cell reads as a corrupt row.
3. Confirm the credits column is correct in both states.
