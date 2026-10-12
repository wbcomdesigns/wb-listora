---
journey: one-rule-per-behaviour
plugin: wb-listora
priority: critical
roles: [admin, member-owner, member-other, anonymous]
covers: [edit-permission-one-rule, moderation-transition-guard, listing-type-partial-put, open-now-one-rule, demo-packs-one-list, page-ids-from-registry]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listing owned by a member (demo: qa_vendor_01's listing 845; author1 owns none) and a second member (priya_raman, id 19)"
  - "A listing in listora_rejected (demo: 7380) and one in pending"
estimated_runtime_minutes: 15
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [buddyx-pro light]
changed: true
---

# One rule per behaviour: edit permission, moderation, type updates, open now

Found by the 2026-10-11 duplicate-logic audit: each behaviour was coded twice and the copies disagreed. Verified via `rest_do_request()` / `wp eval` as the stated users; the frontend edit form and the Settings > Pages switch were not driven in a browser.

## Steps

### 1. Admin edits a member's listing from the frontend
- **Action**: as admin open `/add-listing/?action=edit&id=<member listing>`, tick Terms only if shown (2.0.0: editing as admin or editor no longer asks for the terms again), click "Update Listing".
- **Expect**: "Listing Updated!"; same ID, still owned by the member; no duplicate-review panel; no new listing.
- **Action**: as priya_raman `POST /listora/v1/submit` with that `listing_id`, and `PUT /listora/v1/submit/<id>` (nonce from `admin-ajax.php?action=rest-nonce`).
- **Expect** (observed): `POST` 403 `listora_forbidden` "You do not have permission to edit this listing."; `PUT` 403 `listora_forbidden` "You do not have permission to perform this action."; title unchanged; no listing created. As the owner the PUT returns 200.
- **Role ladder**: owner 200; another member 403; logged out 401; moderator (qa_editor) allowed via `wb_listora_user_can_edit_listing()` (`includes/helpers.php:932`); admin 200 (control).

### 2. REST moderation follows the transition map
- **Action**: as admin `POST /listora/v1/listings/bulk-moderate {"ids":[<rejected id>],"action":"approve"}`, then the same for a pending listing.
- **Expect** (observed): rejected one in `failed[]` with `error: listora_invalid_transition`, status stays `listora_rejected`; the pending one is published and the approval email is sent (check Mailpit / `wp_listora_email_log` `listing_approved`). A rejected listing needs Pending Review first (2.0.0), via `Status_Manager::transition_allowed()`.
- **Negative control**: approving the pending one proves the endpoint works (otherwise step 2 is vacuous).

### 3. Partial PUT of a listing type keeps what it does not mention
- **Action**: `PUT /listora/v1/listing-types/place {"review_enabled":false}`, then `{"name":"Place"}`.
- **Expect** (observed): after the first, `review_enabled:false` and `reviews:"off"`; after the name-only PUT both unchanged and `is_builtin:true`; restore with `{"review_enabled":true}` (reviews back to `"global"`). `GET /listing-types/place` after: same `review_enabled`, `reviews`, `settings` and `is_builtin` as before.

### 4. Open now: badge and search agree
- **Action**: give a listing two slots today (now inside the first, then inside the second) and open its page; query `GET /listora/v1/search?open_now=1`.
- **Expect**: `span.listora-badge--open[role=status]` reading "Open now" (`blocks/listing-detail/render.php:468-475`) AND the listing is in the search result; at exactly closing time both say closed. `tests/integration/OpenNowTest.php` covers the rule (`Business_Hours::open_now_ids()` `includes/core/class-business-hours.php:209`).
- **Not run live** in this pass.

### 5. Demo packs and page IDs come from one place
- **Action**: Listora > Settings > Tools (Re-run Demo Import; `data-packs` attribute from `wb_listora_get_demo_packs()`); `wp listora demo seed --pack=nope`.
- **Expect** (observed): the CLI error lists the same ten packs: `restaurant, hotel, real-estate, job-board, general, classified, education, healthcare, place, event`. `wb_listora_get_demo_packs()` returns 10 entries (the wizard's "All packs" is the same list). The old "9 packs" is stale: the Events pack is the tenth.
- **Action**: in Settings > Pages switch the Dashboard page to another page; reload a member page.
- **Expect**: "Buy credits" links and the `listora-has-dashboard` body class (`includes/class-plugin.php:1028`) follow the new page. Not run live; restore the mapping afterwards.

### 6. Large site
Not a list journey. The relevant 2000+ rows check is that `bulk-moderate` stays bounded (max 100 ids per request, `includes/rest/class-listings-controller.php:559`; admin bulk `BATCH_LIMIT` 100, `class-listing-bulk-actions.php:31`) and reports `requested`/`succeeded`/`failed` counts.

## Pass criteria
1. Edit permission is the same on the form and both save routes. 2. No moderation path writes a status the transition map refuses. 3. A partial type update changes only what it sends. 4. The listing page badge and the search filter give the same answer. 5. One demo-pack list.

## Fail diagnostics
`wb_listora_get_demo_packs()` (`includes/import-export/import-helpers.php:80`), `Page_Registry::get_id()` (`includes/core/class-page-registry.php:101`), `wb_listora_user_can_edit_listing()` (`includes/helpers.php:932`), `Status_Manager::change_status()` / `transition_allowed()` (`includes/workflow/class-status-manager.php`), `Listing_Types_Controller::get_type_update_args()` (`includes/rest/class-listing-types-controller.php:762`), `Business_Hours::open_now_ids()` (`includes/core/class-business-hours.php:209`).
