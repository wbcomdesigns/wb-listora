---
journey: credit-mappings-show-names
plugin: wb-listora
priority: normal
covers:
  - BC 10208171587
likely_files:
  - ../wb-listora-pro/includes/class-pro-plugin.php
roles: [administrator]
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
changed: true
---

# Active Mappings names its provider and product

A credit mapping is stored in the nested shape `{ adapter: { item_id: credits } }` (QA site: `wb-listora_credit_mappings` = `{"woocommerce":{"3627":50}}`) and carries no labels. Only the ADD path resolved names, so rows that arrived any other way reached the table with empty labels (and a display using `??`, which falls back only on an ABSENT key, not an empty string). Result then: a blank Provider cell and a bare `#`.

Surface: wp-admin > Listora > Settings > Credits (`/wp-admin/admin.php?page=listora-settings&tab=credits`), block "Active Mappings" (`#listora-active-mappings`, table `.listora-mappings-table`, columns Provider, Product / Plan, Credits, Pricing, Actions). Pro-owned (`wb-listora-pro/includes/admin/class-credit-mappings.php`).

Roles: administrator / site owner (the actor, capability `manage_listora_settings`); a member, an editor without the capability and logged out must not reach the page (redirect / "Sorry, you are not allowed"); admin is the control.

## Setup

- Pro active, WooCommerce active with a published product mapped to credits (QA site: product 3627 "50 Credit Pack" -> 50). Record `wp option get wb-listora_credit_mappings --format=json` to restore.

## Steps

### 1. A resolved mapping reads by name
- **Action**: open the Settings > Credits tab as admin and find Active Mappings.
- **Expect**: Provider cell `.listora-provider-badge` reads "WooCommerce"; Product / Plan cell `.listora-mapping-item__label` reads "50 Credit Pack" with `.listora-mapping-item__id` "#3627" beside it; Credits "50"; Pricing shows the product price ("$25.00"; a warning row appears under it when credits and price disagree at the credit rate, e.g. "This pack grants 50 credits for $25.00, but at your rate of 1 credit per USD 1 that price buys 25 credits. Check the credit amount or the rate.").
- **Fails if**: Provider is blank, or Product is just `#` / `#3627` while the product exists.

### 2. An unresolvable product degrades to identifiers, never blank
- **Action**: add a mapping to a product that does not exist (or delete the mapped product, or deactivate WooCommerce), e.g. `wp option update wb-listora_credit_mappings '{"woocommerce":{"3627":50,"999999":10}}' --format=json`, then reload.
- **Expect**: the row reads Provider "WooCommerce", Product / Plan "#999999" (label) and "#999999" (id span), Credits "10", Pricing "&mdash;" (unknown, no judgement), Actions Edit and Remove. An unresolvable name still says what is mapped; a blank cell reads as a corrupt row.
- Restore the option afterwards.

### 3. Credits column correct in both states
- **Expect**: "50" in step 1 and "10" in step 2; no cell blank in either state.

### 4. Positive control and role ladder
- **Control**: step 1 (resolved) vs step 2 (degraded) on the same page proves the label fallback is the only difference.
- **Another member / logged out**: `GET /wp-admin/admin.php?page=listora-settings&tab=credits` redirects to login or shows the permission error; no mapping data is exposed.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Blank Provider | `??` fallback on an empty label | `wb-listora-pro/includes/admin/class-credit-mappings.php:252` |
| Bare `#` product | same, item label | `wb-listora-pro/includes/admin/class-credit-mappings.php:257-262` |
| Labels missing on nested rows | name resolution only on add | `wb_listora_get_credit_mappings()` and `Credit_Mappings` label resolution |

## Not verified

Steps 1 and 2 were run live (curl on the admin tab as `varundubey`; the temporary mapping was removed and the option restored). The role refusals in step 4 and the 390 layout of the table (mobile `data-label` stacking) were NOT run.
