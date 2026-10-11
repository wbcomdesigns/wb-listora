---
journey: one-path-delete-and-money
plugin: wb-listora
priority: normal
roles: [administrator]
covers: [claim-delete, badge-delete, admin-money-format]
prerequisites:
  - "WB Listora Pro active"
estimated_runtime_minutes: 10
---

# Claim and badge delete, and admin money, each have one path

Code standard card 10393294523.

## Steps

### 1. Claim delete
- **Action**: Moderation > Claims, delete a test claim; then `DELETE /listora/v1/claims/{id}` for another (admin nonce).
- **Expect**: both remove the row and fire `wb_listora_after_delete_claim`; a second REST delete returns 404; anonymous returns 401. An approved claim's ownership stays.

### 2. Badge delete
- **Action**: Listing Types > Badges, delete a test badge; `DELETE /listora/v1/badges/{id}` for another; `DELETE /listora/v1/badges/{listing id}`.
- **Expect**: first two delete; the listing ID returns 404 and the listing is untouched.

### 3. Money in admin
- **Action**: with a JPY payment of 12,500 refunded 2,500, open Monetization > Transactions > Payments and the Listora dashboard activity.
- **Expect**: "¥12,500" and "¥2,500 refunded" in the site's currency format (no "12,500.00 JPY", no "¥12.5K").
- **On fail**: `Claims_Model::delete()`, Pro `Badges::delete_badge()`, `wb_listora_format_currency( $amount, $currency, true )`.
