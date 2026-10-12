---
journey: role-stripped-account-cannot-write
plugin: wb-listora
priority: critical
roles: [subscriber, role-stripped-member, anonymous, admin]
covers: [member-suspension, rest-write-gate, card-10100523205]
prerequisites:
  - "Three disposable subscribers (prefix QA-J1-) and one listing owned by the second (the old journey_owner/journey_other personas do not exist on this site)"
  - "A listing with an approved review (listing 59, review 14 here)"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
changed: true
---

# An account with its role removed cannot write anywhere, and can still read and erase itself

Removing a user's role is WordPress's moderation lever. `Member_Suspension::is_write_blocked()` has a third blocked state,
`is_role_stripped()` (`includes/core/class-member-suspension.php:132`), applied by the namespace-wide gate on
`rest_request_before_callbacks` to every `listora/v1` write, Free and Pro. Reads are never gated and `DELETE /me` always passes. Error:
403 `listora_account_restricted` "Your account does not have a role on this site, so you cannot post or edit content. You can still
browse. Contact the site administrator if you think this is a mistake." Escape hatch: `add_filter( 'wb_listora_user_can_act',
'__return_true' )` (`:156`).

Role ladder: role-stripped member (all writes blocked, reads and erase allowed), the same account with its role restored (control,
writes pass), logged out (401 `listora_unauthorized`), admin unaffected.
Note: the gate runs AFTER REST argument validation, so a write test needs a VALID body; an invalid body returns the 400 first.

## Setup (verified 2026-10-12; all fixtures deleted afterwards)
```bash
wp user create qa-j1-strip-other qa-j1-strip-other@example.test --role=subscriber
wp user create qa-j1-strip-owner qa-j1-strip-owner@example.test --role=subscriber
wp user create qa-j1-strip-zero  qa-j1-strip-zero@example.test  --role=subscriber
wp post create --post_type=listora_listing --post_status=publish --post_title="QA-J1 strip owned" --post_author=<owner id>
wp user remove-role qa-j1-strip-other subscriber   # and -owner, -zero
```
Get a REST nonce while logged in: `curl -b <cookie> "$SITE_URL/wp-admin/admin-ajax.php?action=rest-nonce"` (`?autologin=<login>` logs in).

## Steps

### 1. Reviewer writes blocked (qa-j1-strip-other)
- **Action**: `POST /listora/v1/favorites {"listing_id":59}`, `POST /listora/v1/reviews/14/helpful`, `POST /listora/v1/listings/59/report {"reason":"spam"}`.
- **Expect**: each 403 `listora_account_restricted`; no rows written.

### 2. Reads still work
- **Action**: `GET /listora/v1/favorites`, `GET /listora/v1/dashboard/stats`.
- **Expect**: 200 (`{"favorites":[],"total":0,...}` and the stats object).

### 3. Owner routes blocked (qa-j1-strip-owner)
- **Action**: `POST /listings/{own id}/deactivate`, `PUT /submit/{own id} {"title":"x"}`, `POST /listings/{own id}/services {"title":"x","price":1}`.
- **Expect**: each 403 `listora_account_restricted` (the listing stays published).

### 4. Erasure always allowed (qa-j1-strip-zero)
- **Action**: `DELETE /listora/v1/me {"confirm":"DELETE"}`.
- **Expect**: 200 `{"deleted":true,"erasers_run":["wb-listora","wb-listora-pro"],...,"message":"Your account and personal data have been permanently deleted."}`; the user no longer exists.

### 5. Controls
- **Action**: `wp user add-role qa-j1-strip-other subscriber`; `POST` then `DELETE /favorites/59`.
- **Expect**: 201 `{"favorited":true}` then 200 `{"favorited":false}`. Logged out `POST /favorites` -> 401 `listora_unauthorized`.

### 6. Teardown
Delete the two remaining users and the listing (`wp user delete ... --yes`, `wp post delete <id> --force`); confirm no `qa-j1-strip-*` user remains.

## Fail diagnostics
`includes/core/class-member-suspension.php:132` (`is_role_stripped()`), `:156` (filter), `:183` (error code); the gate hook is in the same class.

## Not verified
Pro-owned routes (needs, plan activation) were not called; they share the namespace gate. Mobile/theme not applicable (REST only).
