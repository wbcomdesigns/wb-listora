---
journey: stale-need-count-gates
plugin: wb-listora-pro
priority: high
roles: [need-owner, vendor, anonymous, admin]
covers: [needs, response-count-drift, edit-banner, delete-gate, rest-gates, admin-quotes-column]
prerequisites:
  - "Free + Pro active (needs are a Pro feature; the journey lives in the Free folder because it guards a Free-visible dashboard tab)"
  - "A buyer account that owns a need (QA-J1-owner works), a vendor account, an admin"
estimated_runtime_minutes: 8
covers_card: 10350113286
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Needs gate on the live response count, never the stored meta (card 10350113286 / T-43)

`_listora_need_response_count` post meta can drift from the real rows in `{prefix}listora_need_responses`. Every gate must read `Need_Response_Manager::get_count_for_need()` / `get_counts_for_needs()` (a live COUNT), so a stale meta never blocks a delete or shows a false "has quotes" banner.

## Setup

- Page slugs on this site: post-need form `/submit-a-request/`, dashboard `/my-listings/` (Needs tab `?tab=needs`), public grid `/needs/`. Re-check with `wp eval 'echo wb_listora_get_dashboard_url();'` on another site.
- Plant a throwaway need owned by the buyer (QA-J1- title): `wp_insert_post( post_type listora_need, post_status publish )`, then `update_post_meta( $id, '_listora_need_response_count', 2 )` and `update_post_meta( $id, '_listora_need_status', 'open' )`. Confirm zero rows: `SELECT COUNT(*) FROM {prefix}listora_need_responses WHERE need_id = <id>` = 0.

## Steps (stale meta, live count is 0)

1. **Edit form banner.** As the buyer open `/submit-a-request/?edit_need={id}`.
   - Expect `p.listora-post-need__editing[role="status"]` reads exactly "You are editing your request." and the text "already has quotes" is absent.
2. **Dashboard row.** As the buyer open `/my-listings/?tab=needs`.
   - Expect `article.listora-dashboard__need-row[data-need-id="{id}"]` shows "0 responses", a **Delete** button (`.listora-dashboard__need-delete`) and **Close request**.
3. **Delete trashes.** Click Delete, confirm the dialog ("Delete this request? This cannot be undone.").
   - Expect redirect back with `?need_deleted=1`, notice "Your request was deleted.", and `post_status = trash` for the need (not closed / not `fulfilled`).
4. **Admin Quotes column.** Re-create the throwaway. As admin open `wp-admin/admin.php?page=listora-needs&need_status=published`.
   - Expect the row's Quotes column is `0`, not the stored 2.
5. **REST PUT.** As the buyer `PUT /listora/v1/needs/{id}` with `{"title":"QA-J1 renamed"}` (needs the `X-WP-Nonce`).
   - Expect 200 and `wb_listora_pro_need_updated_after_quotes` does not fire (listen with `add_action` in a `wp eval-file`).
6. **REST DELETE.** `DELETE /listora/v1/needs/{id}` as the buyer.
   - Expect `{"deleted":true}`, post trashed, never `{"closed":true}`.

## Positive control (real quote)

Insert one row: `{prefix}listora_need_responses (need_id, listing_id, user_id, message, status, created_at)` = `({id2}, 0, <vendor id>, 'QA-J1 quote', 'pending', now)` on a second QA need.
- Edit form banner reads "You are editing a request that already has quotes. Vendors who quoted will be told it changed."
- Dashboard row shows "1 response" with no Delete button, Close request still offered.
- `DELETE /listora/v1/needs/{id2}` returns HTTP 200 `{"deleted":false,"closed":true,"message":"This request has quotes, so it was closed instead of deleted. The vendors who quoted have been told."}`; post stays `publish`.
- Admin Quotes column is 1.

## Role ladder

| Actor | Expect |
|---|---|
| Buyer (owner) | steps 1-6 above |
| Another member | `PUT`/`DELETE /listora/v1/needs/{id}` refused 403; `/submit-a-request/?edit_need={id}` does not load the owner's data |
| Logged out | `GET /listora/v1/needs/{id}` works for a published need; `PUT`/`DELETE` 401/403 |
| Moderator / admin | can see the need in Listora > Needs with the live Quotes count (control, not the only role tested) |

## Mobile / theme

At 390 the dashboard row stacks, Delete and Close request stay >= 40px tall, no horizontal page scroll. Check `listora-dashboard__need-actions` in light and dark.

## Large data
The Needs admin list and dashboard page 20 at a time (2.0.0). Pre-condition: do not seed 2000 needs; instead confirm `get_counts_for_needs()` is one grouped query (`includes/needs/class-need-response-manager.php:461`) so the Quotes column is not per-row.

## Cleanup
Delete the throwaway needs and response rows (`wp_delete_post( $id, true )`, `DELETE FROM {prefix}listora_need_responses WHERE message = 'QA-J1 quote'`).

## Fail diagnostics
| Symptom | File |
|---|---|
| Banner says "already has quotes" on a 0-row need | `wb-listora-pro/templates/blocks/post-need/post-need.php:56` |
| Delete missing / "closed" on a 0-row need | `wb-listora-pro/templates/dashboard/tab-needs.php:173-195`, handler `includes/features/class-reverse-listings.php:174` |
| REST DELETE closes instead of trashing | `wb-listora-pro/includes/rest/class-needs-controller.php:873` |
| `need_updated_after_quotes` fires on 0 rows | `wb-listora-pro/includes/rest/class-needs-controller.php:779` |
| Admin Quotes column wrong | `wb-listora-pro/includes/features/class-reverse-listings.php:879,1036` |

## Not verified
Steps 3 (dashboard Delete form submit + `?need_deleted=1` notice) and 4 (admin Quotes column) were read from source only; steps 1, 2, 5/6 positive control and 6 were run with curl as QA-J1-owner on 2026-10-12. The "hook does not fire" assertion in step 5 was read from `class-needs-controller.php:779`, not observed. The another-member and logged-out rows of the role ladder were not run.

Verified via: curl/DOM with autologin cookie + REST nonce; wp-cli.
