---
journey: one-path-delete-and-money
plugin: wb-listora
priority: normal
roles: [administrator, subscriber, anonymous]
covers: [claim-delete, badge-delete, admin-money-format]
prerequisites:
  - "WB Listora Pro active (badges, payments)"
  - "Test rows prefixed QA-J1: a pending claim, a badge, a JPY payment of 12,500 refunded 2,500 (insert via wp eval)"
estimated_runtime_minutes: 10
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light]
changed: true
---

# Claim and badge delete, and admin money, each have one path

Code standard card 10393294523. Claim delete: `Claims_Model::delete()` (`includes/core/class-claims-model.php:106` fires `wb_listora_after_delete_claim`; REST `DELETE /listora/v1/claims/{id}` at `includes/rest/class-claims-controller.php:145`; wp-admin Claims screen). Badge delete: `Badges::delete_badge()` (`wb-listora-pro/includes/features/class-badges.php:1416`; REST `DELETE /listora/v1/badges/{id}`; admin screen `admin.php?page=listora-badges`). Money: `wb_listora_format_currency( $amount, $currency, $exact = true )` (`includes/class-template-helpers.php:1811`).

Verified via: `rest_do_request()` as anonymous / subscriber / admin with QA-J1 rows (claims, badge), and `wb_listora_format_currency( 12500, 'JPY', true )`. The wp-admin screens (Moderation > Claims, Badges, Monetization > Transactions > Payments) were not driven in a browser in this pass.

## Steps

### 1. Claim delete
- **Action**: insert two QA-J1 claims. `DELETE /listora/v1/claims/<id>` as anonymous, as subscriber2, as admin; then delete the second from the wp-admin claims screen (`admin.php?page=listora-claims`).
- **Expect** (REST observed): anonymous 401 `listora_unauthorized`; subscriber 403 `listora_forbidden`; admin 200 `{"deleted":true}` and `wb_listora_after_delete_claim` fires once; a second admin DELETE is 404 `listora_not_found` ("Claim not found."). The admin-screen delete removes the row and fires the same action. An approved claim's ownership (`post_author`) stays.

### 2. Badge delete
- **Action**: create `POST /listora/v1/badges {"label":"QA-J1 badge","slug":"qa-j1-badge","color":"#ff0000","text_color":"#ffffff","icon":"star","assignment":"manual"}` (201), then `DELETE /badges/<id>` as anonymous, then as admin; then `DELETE /badges/17` (a LISTING id).
- **Expect** (observed): anonymous 403; admin 200 `{"deleted":true}`; again 404; the listing id returns 404 `Badge not found.` and the listing (17) is still `publish`.
- **Note**: anonymous gets 403 on badges but 401 on claims; both are refusals.

### 3. Money in admin
- **Action**: with a JPY payment of 12,500 refunded 2,500, open `wp-admin/admin.php?page=listora-transactions&tab=payments` and the Listora dashboard activity.
- **Expect**: "¥12,500" and "¥2,500 refunded" in the site's currency format (`class-transactions-page.php:180` builds "%s refunded"), no "12,500.00 JPY", no "¥12.5K". `wb_listora_format_currency( 12500, 'JPY', true )` returns `¥12,500` and `( 2500, ... )` `¥2,500` (observed).

### 4. Role ladder
| Actor | Claim / badge delete |
|---|---|
| Anonymous | 401 (claims) / 403 (badges) |
| Member (subscriber) | 403 |
| Moderator | claims: allowed only if the role holds the claims-manage cap (check `delete_claim` permission callback); badges: 403 without `manage_listora_settings` |
| Admin (control) | 200 |

### 5. Large site
Claims and Payments screens page their rows (the screens use `LIMIT/OFFSET` list queries; `Claims_Model::get_list_count()` for totals, `class-claims-controller.php:430`); with many rows deleting one must not reload the whole table.

## Fail diagnostics
`includes/core/class-claims-model.php:106`, `includes/rest/class-claims-controller.php:145`, `wb-listora-pro/includes/features/class-badges.php:1416`, `includes/class-template-helpers.php:1811`.

## Cleanup
Delete QA-J1 claim, badge and payment rows.
