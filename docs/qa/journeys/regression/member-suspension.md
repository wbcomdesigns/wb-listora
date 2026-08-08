---
journey: member-suspension
plugin: wb-listora
priority: critical
roles: [administrator, subscriber]
covers: [LST-F-10, member-suspension, write-gate, app-password-bypass, moderation]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "WP-CLI available via the mcp-local-wp MCP (a bare `wp --path=` hits the WRONG database)"
  - "curl available for REST calls"
  - "Free 1.5.0+ active"
estimated_runtime_minutes: 8
---

# Suspend a member, and prove they cannot write

An owner has an abusive member and needs them to stop. Before 1.5.0 there was no
way: the REST controllers gate on `is_user_logged_in()` and ownership, not on the
`edit_posts`-family capabilities, so stripping a member's capabilities did
nothing and the admin UI implied a control that did not exist.

The sharp edge this journey guards is **coverage**. The block is deliberately NOT
written into each of the ~45 member-facing write endpoints — it lives in one REST
filter and one capability filter, so an endpoint added later is covered without
anyone remembering. If someone "optimises" that into per-endpoint checks, this
journey is what catches the endpoints they missed.

The second edge is **Application Passwords**. They are minted by WP core and
bypass plugin login gates entirely, so a block enforced only at login is no block
at all for the mobile app. Every write below is driven over Basic auth for that
reason.

## Setup

- Site: `$SITE_URL`, REST base `$B="$SITE_URL/wp-json/listora/v1"`
- Throwaway member (NEVER user 1, never a seeded demo user):
  ```
  wp eval '$id = wp_insert_user(array("user_login"=>"qa_susp","user_email"=>"qa_susp@example.test","user_pass"=>wp_generate_password(24),"role"=>"subscriber")); echo $id;'
  wp user application-password create qa_susp qa-journey --porcelain
  ```
  Export `AUTH="qa_susp:<app-password>"` and `UID=<id>`.
- A published listing the member does NOT own — `LID`.

## Steps

### 1. Baseline: the member can write

- **Action**: `curl -s -o /dev/null -w "%{http_code}" -u "$AUTH" -X POST -H "Content-Type: application/json" -d "{\"listing_id\":$LID}" "$B/favorites"`
- **Expect**: `201`
- **On fail**: fixtures wrong; abort rather than continue against a baseline that already cannot write

### 2. Suspend from the Users screen, not the database

- **Action**: in wp-admin, Users → row action **Suspend** on `qa_susp`
- **Expect**: success notice reading *"Member suspended. They can still sign in and browse, but cannot post or edit anything. Their existing reviews and listings are untouched."*
- **Why the wording matters**: an owner who is not told that existing content survives will assume the reviews vanished and reinstate in a panic
- **On fail**: `includes/admin/class-user-moderation.php` — `handle_row_action()` nonce/capability gate, or `render_notice()`

### 3. Every write is refused, over an Application Password

- **Action**: for each of these, with `-u "$AUTH"`:
  - `POST $B/favorites` `{"listing_id":LID}`
  - `POST $B/listings/LID/reviews` `{"overall_rating":5,"title":"x","content":"a genuine review long enough to pass validation"}`
  - `POST $B/claims` `{"listing_id":LID,"proof_text":"I own this business"}`
  - `POST $B/dashboard/profile` `{"description":"zz"}`
  - `POST $B/submit` `{"title":"zz","listing_type":"business"}`
- **Expect**: every one returns `403` with body code `listora_member_suspended`
- **Note**: send COMPLETE payloads. WP validates required args before this gate runs, so a malformed request returns `400 rest_missing_callback_param` — which is not a gap, but will read as one
- **On fail**: `includes/core/class-member-suspension.php::block_rest_writes()`

### 4. Reading still works

- **Action**: `curl -s -o /dev/null -w "%{http_code}" -u "$AUTH" "$B/search?per_page=1"`
- **Expect**: `200`
- **Why**: a suspended member must be able to see the site and read the explanation, rather than meeting silent failures

### 5. They cannot lift their own suspension

- **Action**: `curl -s -u "$AUTH" -X POST "$B/me/reactivate"`
- **Expect**: `403`, code `listora_member_suspended`
- **Why this exists**: suspension and self-deactivation are separate meta keys precisely so this route cannot be used to self-reinstate. If they ever share a flag, this step goes green-to-red first
- **On fail**: `Member_Suspension::is_route_allowed_while_blocked()`

### 6. They CAN still delete their account

- **Action**: `curl -s -o /dev/null -w "%{http_code}" -u "$AUTH" -X DELETE "$B/me?confirm=DELETE"`
- **Expect**: `200`, and the user row is gone
- **Why**: blocking erasure would turn a moderation tool into a data-protection problem
- **Note**: this destroys the throwaway user — run it last, or re-create for step 7

### 7. Reinstating restores writing

- **Action**: recreate the member, suspend, then Users → **Reinstate**; repeat step 1
- **Expect**: `201` again, and the Users-list cell returns to `—`
- **On fail**: `Member_Suspension::unsuspend()` not clearing all four meta keys

### 8. The audit log recorded who did it

- **Action**: `wp eval 'global $wpdb; echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}listora_audit_log WHERE action IN (\"member_suspended\",\"member_reinstated\")");'`
- **Expect**: at least 2, each carrying actor, IP, and the member's login and email
- **Why denormalised**: the entry must still read correctly after the account is deleted, which is a likely sequel to a suspension
- **On fail**: Pro `class-audit-log.php` — `on_member_suspended()` listener not registered, or the `audit_log` feature toggle is off

### 9. Unblocked members are untouched

- **Action**: `wp eval 'foreach (array(1,3) as $u) { echo $u . "=" . var_export(user_can($u,"edit_posts"),true) . " "; }'`
- **Expect**: both `true` — the `user_has_cap` filter must be invisible to anyone not blocked
- **On fail**: `Member_Suspension::strip_write_caps()` is over-reaching; check the `is_write_blocked()` early return

## Pass criteria

- Steps 1-9 all as expected
- No PHP notice, warning or fatal in `wp-content/debug.log` during the run
- Bulk Suspend / Reinstate on the Users screen reports the changed count AND the skipped count separately

## Fail diagnostics

| Symptom | Look at |
|---|---|
| A write succeeds while suspended | `block_rest_writes()` — route prefix match, or the method not in the write list |
| Every write fails for everyone | `is_write_blocked()` returning true for uid 0 or unblocked users |
| 400 instead of 403 | incomplete payload — WP validates required args first; not a gap |
| Suspension does not stick | `Member_Suspension::suspend()` meta write, or a `wb_listora_is_member_suspended` filter overriding it |

## Teardown

```
wp eval 'if ($u = get_user_by("login","qa_susp")) { wp_delete_user($u->ID); }'
```
