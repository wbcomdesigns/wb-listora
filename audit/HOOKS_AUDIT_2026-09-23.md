# Developer hooks audit - WB Listora Free 1.8.0 (2026-09-23)

Scope: every action, filter, REST response filter, template override and public helper a site developer or Pro relies on. Read-only sweep of HEAD, then each high-impact finding reproduced before it was acted on. Pro's audit is `../wb-listora-pro/audit/HOOKS_AUDIT_2026-09-23.md`.

**Counts:** 363 hook names fired from 420 sites. 13 of 13 `before_*` filters abort correctly on WP_Error. 0 hooks fire with different argument counts at different sites. 61 of 61 templates are theme-overridable.

## Fixed in 1.8.0 (this audit)

| Finding | Evidence | Fix |
|---|---|---|
| Listora → Reviews approve/reject/delete (single + bulk) were raw `$wpdb` writes: no review hooks, **and no rating recompute**, so an approved review never counted on the listing | Listing 6324 had 2 approved reviews, index said 0 | `Admin::moderate_review()` dispatches the review REST routes |
| Bulk Apply (Reviews, Claims) and Claims Filter never submitted | No `submit` event, no POST | `submit-lock.js` locks on `submit`, not `click` |
| `wb_listora_rest_prepare_dashboard_stats` skipped on a cache hit | Probe field present cold, missing warm | Filter applied on both paths |
| Docs: `wb_listora_get_template` listed as an action (it is a function); `wb_listora_listing_paused/resumed` (real names carry `pro_`) | - | Corrected; 7 undocumented hooks added to hooks-reference |

## Open - write paths that skip before_/after_ hooks

| Path | Where | Gap | Sibling done right |
|---|---|---|---|
| Admin claim approve/reject | `class-admin.php` `fire_claim_updated()` | `after_update_claim` fires, `before_update_claim` does not - an extension cannot veto an admin approval; `$request` is `null` | `class-claims-controller.php` update path |
| Admin claim delete (single + bulk) | `class-admin.php` claims handlers | Raw delete, no hook; no delete_claim pair exists anywhere | Services delete pair in `class-services.php` |
| `owner_reply` | `class-reviews-controller.php` | Only `wb_listora_review_reply`; no before_ filter, no `after_update_review`, no `rest_prepare_review` | `update_review()` |
| Listing trash outside REST DELETE | bulk-moderate trash (`class-listings-controller.php`), account manager, email verification | `before_/after_delete_listing` skipped | `delete_listing()` |
| Importers + migrators | CSV/JSON/GeoJSON/background/migration-base | No `before_/after_create_listing`; imported reviews fire no `after_create_review` | `class-submission-controller.php` create path |
| Privacy eraser | `class-privacy-eraser.php` | Deletes favorites/reviews with no after_ hooks (may be intentional for GDPR - document it either way) | - |

## Open - REST responses without the documented prepare filter

`GET /listings` collection and `/related` (only core `rest_prepare_listora_listing`), dashboard `get_listings` / `get_my_claims` / `get_reviews`, `delete_review`, `owner_reply`, `delete_service`, listing-types create/update/delete. One listing shape has three filters (`rest_prepare_listing`, `rest_listing_response`, core's) and search runs two back to back (`wb_listora_search_results`, `wb_listora_rest_prepare_search_result`).

## Open - extension points missing where siblings have them

- Query-args filter: categories (`get_terms` unfiltered), calendar (raw queries, output filter only), reviews block.
- Wrapper actions: listing-submission templates have none; category-card has no per-card before/after.

## Open - docs

- `hooks-reference.md` is generated from `audit/manifest.json` at 1.7.0: 268 of 335 file:line cells are stale. Regenerating from today's manifest DROPS ~120 hand-added rows, so refresh the manifest first (`/wp-plugin-onboard --refresh`), then run `bin/build-hooks-reference.py`.
- Naming is mixed (`wb_listora_before_*` vs `wb_listora_grid_after_card`); several pairs duplicate each other (`listing_submitted` / `after_create_listing`, `claim_submitted` / `after_submit_claim`). Document which one to use; do not rename (production rule 2).

## Suggested order after 1.8.0

1. Claims admin paths through the claims REST routes (same pattern as reviews).
2. Importers fire `after_create_listing` with `'context' => 'import'`.
3. `owner_reply` gets the before/after pair and `rest_prepare_review`.
4. Missing prepare filters on dashboard collections.
5. Manifest refresh + hooks-reference regeneration.
