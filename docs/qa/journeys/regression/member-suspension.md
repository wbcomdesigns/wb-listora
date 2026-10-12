---
journey: member-suspension
plugin: wb-listora
priority: critical
roles: [administrator, subscriber]
covers: [LST-F-10, member-suspension, write-gate, app-password-bypass, moderation]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "WP-CLI against THIS site's path (a bare `wp` may hit the wrong database)"
  - "curl; Application Passwords available (Local sites report environment type 'local')"
estimated_runtime_minutes: 9
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [buddyx-pro light, buddyx-pro dark]
changed: true
---

# Suspend a member, and prove they cannot write

Suspension is enforced in ONE REST filter (`Member_Suspension::block_rest_writes()`, `includes/core/class-member-suspension.php:276`, every POST/PUT/PATCH/DELETE under `listora/v1`) and ONE capability filter (`strip_write_caps()` `:380`), so endpoints added later are covered. Application Passwords bypass plugin login gates, so every write below is driven over Basic auth. Meta keys `_listora_member_suspended` (+ `_reason`) are separate from self-deactivation.

Verified via: curl with an Application Password against `http://wb-listora.local/wp-json/listora/v1`, `wp eval` for suspend/unsuspend and the audit log. The wp-admin Users-screen clicks (steps 2 and 7 UI) were not driven in a browser; the notice strings were read in source.

## Setup
- Throwaway member (never user 1, never a seeded user): `wp user create QA-J1-susp qa-j1-susp@example.test --role=subscriber --porcelain` then `wp user application-password create QA-J1-susp qa-journey --porcelain`. `AUTH="QA-J1-susp:<pw>"`, `B=$SITE_URL/wp-json/listora/v1`.
- `LID` = a published listing the member does NOT own (demo: 17).

## Steps

### 1. Baseline: the member can write (positive control)
- **Action**: `curl -u "$AUTH" -X POST -H 'Content-Type: application/json' -d '{"listing_id":17}' "$B/favorites"`
- **Expect**: `201` `{"favorited":true}`. Anything else: fixtures wrong, abort.

### 2. Suspend from the Users screen
- **Action**: wp-admin > Users, row action **Suspend** on `QA-J1-susp` (or `Member_Suspension::suspend( $id, 'reason', $admin_id )`).
- **Expect**: success notice "Member suspended. They can still sign in and browse, but cannot post or edit anything. Their existing reviews and listings are untouched." (`includes/admin/class-user-moderation.php:360`). The Users list shows a suspended cell.

### 3. Every write is refused over an Application Password
- **Action**: with `-u "$AUTH"` send complete payloads:
  - `POST $B/favorites {"listing_id":17}`
  - `POST $B/listings/17/reviews {"overall_rating":5,"title":"x","content":"a genuine review long enough to pass validation"}`
  - `POST $B/claims {"listing_id":17,"proof_text":"I own this business"}`
  - `POST $B/dashboard/profile {"description":"zz"}`
  - `POST $B/submit {"title":"zz","listing_type":"business"}`
  - `POST $B/listings/17/report {"reason":"spam"}`
- **Expect**: each `403` with code `listora_member_suspended` and message "Your account has been suspended, so you cannot post or edit content on this site. You can still browse. Contact th...".
- **Note**: incomplete payloads return `400 rest_missing_callback_param` first; that is not a gap.

### 4. Reading still works
- **Action**: `GET $B/search?per_page=1` with `-u "$AUTH"`.
- **Expect**: `200` with listings.

### 5. They cannot lift their own suspension
- **Action**: `POST $B/me/reactivate` and `POST $B/me/deactivate`.
- **Expect**: both `403` `listora_member_suspended` (`is_route_allowed_while_blocked()` `:333` only exempts these two for NON-suspended, self-deactivated members).

### 6. They CAN still erase their own account (last)
- **Action**: `DELETE $B/me?confirm=DELETE`.
- **Expect**: `200` `{"deleted":true,"erasers_run":["wb-listora","wb-listora-pro"],...}` and the user row is gone. Run this last; for step 7 re-create the user.

### 7. Reinstating restores writing
- **Action**: recreate the member, suspend, then Users > **Reinstate** (or `Member_Suspension::unsuspend()`), repeat step 1 with a fresh application password.
- **Expect**: `201` again; the Users cell returns to `-`.

### 8. The audit log recorded who did it
- **Action**: `SELECT action, object_type, object_id, details FROM wp_listora_audit_log WHERE action IN ('member_suspended','member_reinstated') ORDER BY id DESC`
- **Expect**: a `member_suspended` row and a `member_reinstated` row, `object_type='user'`, `details` JSON containing `member_login`, `member_email`, `reason`, `applied_by` (denormalised so it reads after the account is deleted). Requires Pro with the `audit_log` feature on (`wb-listora-pro/includes/features/class-audit-log.php:496`).
- **Note**: the table has columns `user_id, action, object_type, object_id, details, ip_address, created_at` (not `event_type`). Delete your QA-J1 audit rows afterwards.

### 9. Unblocked members are untouched (negative control)
- **Action**: `user_can( <admin id>, 'edit_posts' )` and for a normal member `user_can( <member id>, 'read' )`.
- **Expect**: true; the `user_has_cap` filter is invisible to anyone not blocked (`is_write_blocked()` `:100` early return).

### 10. Role ladder
| Actor | Result |
|---|---|
| Suspended member | steps 3-5 refused, 4 and 6 allowed |
| Another (unsuspended) member | all writes succeed |
| Logged out | 401 on writes (not the suspension code) |
| Moderator | can Suspend / Reinstate; cannot suspend yourself or another moderator ("skipped" count in the bulk notice) |
| Admin (control) | can do everything; cannot be suspended by self |

## Pass criteria
Steps 1-9 as written; no PHP notice/fatal in `wp-content/debug.log`; bulk Suspend/Reinstate on the Users screen reports changed and skipped counts separately.

## Fail diagnostics
| Symptom | Look at |
|---|---|
| A write succeeds while suspended | `class-member-suspension.php:276` route prefix / method list |
| Every write fails for everyone | `:100` `is_write_blocked()` for uid 0 |
| 400 instead of 403 | incomplete payload |
| Suspension does not stick | `:242`/`suspend()` meta write or a `wb_listora_is_member_suspended` filter (`:87`) |
| No audit row | Pro audit log feature off / listener `:218` |

## Teardown
`wp user delete QA-J1-susp --yes` if step 6 did not run; remove QA-J1 audit rows.
