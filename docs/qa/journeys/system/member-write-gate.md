---
journey: member-write-gate
plugin: wb-listora
priority: critical
roles: [admin, moderator, owner, other, role-stripped-member, guest]
covers: [LST-F-10, member-suspension, role-stripped, write-gate, app-password-bypass, moderation, rest-write-gate, card-10100523205]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "WP-CLI against THIS site (`--path=$WP_PATH`; a bare `wp` may hit the wrong database)"
  - "curl; Application Passwords available (Local sites report environment type 'local')"
  - "Pro active with the audit_log feature on for step 11 only"
estimated_runtime_minutes: 14
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
themes: []
site_profile: required
supersedes:
  - ora:regression/member-suspension.md
  - ora:regression/role-stripped-account-cannot-write.md
---

# A suspended or role-stripped account cannot write anywhere, and can still read and erase itself

## Why
Moderation needs a lever that stops a bad account posting without deleting it. Suspension is enforced in ONE REST filter (`Member_Suspension::block_rest_writes()`, `includes/core/class-member-suspension.php:276`, every POST/PUT/PATCH/DELETE under `listora/v1`, Free and Pro) and ONE capability filter (`strip_write_caps()` `:380`), so endpoints added later are covered. Application Passwords bypass plugin login gates, so every write is driven over Basic auth. Two blocked states share the gate:

| State | Set by | Error |
|---|---|---|
| Suspended | Users screen / `Member_Suspension::suspend()`, meta `_listora_member_suspended` (+ `_reason`), separate from self-deactivation | 403 `listora_member_suspended` |
| Role-stripped (`is_role_stripped()` `:132`) | removing the user's role | 403 `listora_account_restricted` ("Your account does not have a role on this site, so you cannot post or edit content. You can still browse. Contact the site administrator if you think this is a mistake.") |

Reads are never gated and `DELETE /me` always passes. A self-deactivated account has its own code (`listora_account_deactivated`, see `11-deactivate-and-reactivate-account`). Escape hatch: `add_filter( 'wb_listora_user_can_act', '__return_true' )` (`:156`). The gate runs AFTER REST argument validation, so a write test needs a VALID body; an invalid body returns the 400 first.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Throwaway members, never user 1 or a seeded persona: `wp user create QA-J1-susp qa-j1-susp@example.test --role=subscriber --porcelain`, `wp user application-password create QA-J1-susp qa-journey --porcelain` (`AUTH="QA-J1-susp:<pw>"`), `B=$SITE_URL/wp-json/listora/v1`. For the role-stripped steps create `QA-J1-strip-other`, `QA-J1-strip-owner`, `QA-J1-strip-zero` (subscribers), publish `QA-J1 strip owned` authored by `-owner`, then `wp user remove-role QA-J1-strip-<x> subscriber` for all three.
- `LID` = a published listing the member does NOT own (any from `wp post list --post_type=listora_listing --post_status=publish --field=ID`); `RID` = an approved review on it (`SELECT id FROM ${PREFIX}listora_reviews WHERE status='approved' LIMIT 1`).
- REST nonce for cookie logins: `curl -b <cookie> "$SITE_URL/wp-admin/admin-ajax.php?action=rest-nonce"`.

## Steps

### 1. Baseline: the member can write (positive control)
- **Action**: `curl -u "$AUTH" -X POST -H 'Content-Type: application/json' -d '{"listing_id":LID}' "$B/favorites"`
- **Expect**: `201` `{"favorited":true}`. Anything else: fixtures wrong, abort.
- **Read-back**: row in `${PREFIX}listora_favorites` for the user.

### 2. Suspend from the Users screen
- **Action**: wp-admin > Users, row action **Suspend** on `QA-J1-susp` (or `Member_Suspension::suspend( $id, 'reason', $admin_id )`).
- **Expect**: notice "Member suspended. They can still sign in and browse, but cannot post or edit anything. Their existing reviews and listings are untouched." (`includes/admin/class-user-moderation.php:360`); the Users list shows a suspended cell.
- **Read-back**: `wp user meta get <id> _listora_member_suspended` non-empty.

### 3. Every write is refused over an Application Password
- **Action**: with `-u "$AUTH"` send complete payloads: `POST $B/favorites {"listing_id":LID}`; `POST $B/listings/LID/reviews {"overall_rating":5,"title":"x","content":"a genuine review long enough to pass validation"}`; `POST $B/claims {"listing_id":LID,"proof_text":"I own this business"}`; `POST $B/dashboard/profile {"description":"zz"}`; `POST $B/submit {"title":"zz","listing_type":"{type}"}`; `POST $B/listings/LID/report {"reason":"spam"}`.
- **Expect**: each `403` code `listora_member_suspended`, message "Your account has been suspended, so you cannot post or edit content on this site. You can still browse. Contact th...". Incomplete payloads return `400 rest_missing_callback_param` first; that is not a gap.
- **Read-back**: no new rows in favorites, reviews, claims; profile bio unchanged.

### 4. Reading still works
- **Action**: `GET $B/search?per_page=1` with `-u "$AUTH"`.
- **Expect**: `200` with listings.
- **Read-back**: n/a (no write).

### 5. They cannot lift their own suspension
- **Action**: `POST $B/me/reactivate` and `POST $B/me/deactivate`.
- **Expect**: both `403 listora_member_suspended` (`is_route_allowed_while_blocked()` `:333` exempts these two only for NON-suspended, self-deactivated members).
- **Read-back**: suspension meta still set.

### 6. Reinstating restores writing
- **Action**: Users > **Reinstate** (or `Member_Suspension::unsuspend()`), repeat step 1 with a fresh application password.
- **Expect**: `201` again; the Users cell returns to `-`.
- **Read-back**: suspension meta gone.

### 7. Role-stripped: writes blocked (QA-J1-strip-other)
- **Action**: `POST /listora/v1/favorites {"listing_id":LID}`, `POST /listora/v1/reviews/RID/helpful`, `POST /listora/v1/listings/LID/report {"reason":"spam"}`.
- **Expect**: each `403 listora_account_restricted`; no rows written.
- **Read-back**: favorites/votes/reports counts unchanged.

### 8. Role-stripped: reads work, owner routes blocked
- **Action**: `GET /listora/v1/favorites`, `GET /listora/v1/dashboard/stats`; as `QA-J1-strip-owner` `POST /listings/{own id}/deactivate`, `PUT /submit/{own id} {"title":"x"}`, `POST /listings/{own id}/services {"title":"x","price":1}`.
- **Expect**: reads `200` (`{"favorites":[],"total":0,...}` and the stats object); owner routes each `403 listora_account_restricted`; the listing stays `publish`.
- **Read-back**: `wp post get <id> --field=post_status` = `publish`.

### 9. Erasure is always allowed
- **Action**: as `QA-J1-strip-zero`: `DELETE /listora/v1/me {"confirm":"DELETE"}`; and for the suspended member (run last): `DELETE $B/me?confirm=DELETE`.
- **Expect**: `200 {"deleted":true,"erasers_run":["wb-listora","wb-listora-pro"],...,"message":"Your account and personal data have been permanently deleted."}` and the user row is gone. Re-create the suspended user if you still need it for step 6.
- **Read-back**: `wp user get <id>` fails.

### 10. Controls and unaffected users
- **Action**: `wp user add-role QA-J1-strip-other subscriber`; `POST` then `DELETE /favorites/LID`; logged out `POST /favorites`; `user_can( <admin id>, 'edit_posts' )` and for a normal member `user_can( <member id>, 'read' )`.
- **Expect**: restored role `201 {"favorited":true}` then `200 {"favorited":false}`; logged out `401 listora_unauthorized` (not the gate's code); both `user_can` true (the `user_has_cap` filter is invisible to anyone not blocked, `is_write_blocked()` `:100` early return).
- **Read-back**: favorites count returns to baseline.

### 11. The audit log recorded who did it
- **Action**: `SELECT action, object_type, object_id, details FROM ${PREFIX}listora_audit_log WHERE action IN ('member_suspended','member_reinstated') ORDER BY id DESC`.
- **Expect**: one `member_suspended` and one `member_reinstated` row, `object_type='user'`, `details` JSON containing `member_login`, `member_email`, `reason`, `applied_by` (denormalised so it reads after the account is deleted). Requires Pro with `audit_log` on (`wb-listora-pro/includes/features/class-audit-log.php:496`). Columns: `user_id, action, object_type, object_id, details, ip_address, created_at`.
- **Read-back**: delete your QA-J1 audit rows afterwards.

### 12. Bulk, retry and concurrency
- **Action**: bulk Suspend then Reinstate on the Users screen with a mix of members, a moderator and yourself; send the same write twice in parallel; suspend an already-suspended member.
- **Expect**: the bulk notice reports changed and skipped counts separately (moderators and self are skipped); a repeated suspend is a no-op; parallel writes both refused with the same code.
- **Read-back**: notice text; no PHP notice or fatal in `wp-content/debug.log`. Not verified live.

## Role ladder
| Rung | Expect |
|---|---|
| Suspended member | steps 3-5 refused, 4 and 9 allowed |
| Role-stripped member | steps 7-8 refused, reads and `DELETE /me` allowed |
| {other} (unblocked) | all writes succeed |
| {guest} | 401 on writes (not the suspension code) |
| {editor}/moderator | can Suspend / Reinstate; cannot suspend self or another moderator ("skipped" in the bulk notice) |
| {admin} (control) | can do everything; cannot be suspended by self |

## Pass criteria
1. Steps 1-11 as written; every Free and Pro write refused for both blocked states, reads and erase allowed.
2. Reinstating or restoring the role restores writing.
3. No PHP notice/fatal in `wp-content/debug.log`; bulk actions report changed and skipped separately.

## Fail diagnostics
| Symptom | Look at |
|---|---|
| A write succeeds while blocked | `class-member-suspension.php:276` route prefix / method list |
| Every write fails for everyone | `:100` `is_write_blocked()` for uid 0 |
| 400 instead of 403 | incomplete payload |
| Suspension does not stick | `:242` `suspend()` meta write or `wb_listora_is_member_suspended` filter (`:87`) |
| Role-stripped passes | `:132` `is_role_stripped()`, `:183` error code |
| No audit row | Pro audit log off / listener `:218` |

## Not verified
Users-screen clicks (steps 2 and 6 UI) were not driven in a browser (notice strings read in source); Pro-owned routes (needs, plan activation) were not called; they share the namespace gate. Step 12.

## Cleanup
`wp user delete QA-J1-susp QA-J1-strip-other QA-J1-strip-owner --yes` (the others are gone), `wp post delete <strip listing> --force`; remove QA-J1 audit rows; confirm no `qa-j1-*` user remains.
