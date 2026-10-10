---
journey: one-rule-per-behaviour
plugin: wb-listora
priority: critical
roles: [admin, member-owner, member-other, anonymous]
covers: [edit-permission-one-rule, moderation-transition-guard, listing-type-partial-put, open-now-one-rule]
prerequisites:
  - "Site reachable at $SITE_URL; a listing owned by a member (author1); a second member (priya.raman)"
estimated_runtime_minutes: 15
---

# One rule per behaviour: edit permission, moderation, type updates, open now

Found by the 2026-10-11 duplicate-logic audit: each behaviour was coded twice and the copies disagreed.

## Steps

### 1. Admin edits a member's listing from the frontend
- **Action**: as admin open `/add-listing/?action=edit&id=<author1 listing>`, tick Terms, Update Listing.
- **Expect**: "Listing Updated!"; same ID, still owned by author1; no duplicate-review panel; no new listing.
- **Action**: as priya.raman POST `/listora/v1/submit` with that `listing_id`, and PUT `/listora/v1/submit/<id>` (nonce from `admin-ajax.php?action=rest-nonce`).
- **Expect**: 403 `listora_forbidden` on both; no listing created or changed. As author1 the PUT returns 200.

### 2. REST moderation follows the transition map
- **Action**: as admin POST `/listora/v1/listings/bulk-moderate` `{ids:[<listora_rejected id>], action:"approve"}`, then the same for a pending listing.
- **Expect**: rejected one in `failed[]` with `listora_invalid_transition`, status unchanged; pending one published, approval email in Mailpit.

### 3. Partial PUT of a listing type keeps what it does not mention
- **Action**: PUT `/listora/v1/listing-types/place` `{review_enabled:false}`, then `{name:"Place"}`.
- **Expect**: `review_enabled` still false after the name-only PUT. Put it back.

### 4. Open now: badge and search agree
- **Action**: give a listing two slots today (now inside the first, slot 0 and slot 1) and open its page; query `/listora/v1/search?open_now=1`.
- **Expect**: badge "Open now" and the listing is in the search result. At exactly closing time both say closed. `tests/integration/OpenNowTest.php` covers the rule.
- **On fail**: `wb_listora_user_can_edit_listing()` (includes/helpers.php), `Status_Manager::change_status()`, `get_type_update_args()`, `Business_Hours::open_now_ids()`.

## Pass criteria

ALL of the following hold:
1. Edit permission is the same on the form and both save routes.
2. No moderation path writes a status the transition map refuses.
3. A partial type update changes only what it sends.
4. The listing page badge and the search filter give the same answer.
