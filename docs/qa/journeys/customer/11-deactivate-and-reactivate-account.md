---
journey: deactivate-and-reactivate-account
plugin: wb-listora
priority: critical
roles: [subscriber]
covers: [account-deactivation, account-reactivation, apple-5.1.1v, erasure-map, listing-status-restore, deactivated-write-block]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "WP-CLI available via the mcp-local-wp MCP (a bare `wp --path=` hits the WRONG database)"
  - "curl available for REST calls"
  - "Free 1.2.3+ active (POST /listora/v1/me/deactivate registered)"
estimated_runtime_minutes: 6
---

# Deactivate and reactivate an account

A member steps away from the directory and later comes back. Deactivation must hide them from the public directory while destroying NOTHING — the owner's explicit requirement is that a member who paid for something can have their profile restored. Reactivation must put every listing back at the EXACT status it had before, not blanket-republish.

The sharp edge this journey guards: a member with a `pending` listing awaiting moderation. If reactivation republishes everything to `publish`, that listing goes live without ever being approved — a moderation bypass shipped inside a privacy feature. The journey fails if the pending listing comes back as anything other than `pending`.

## Setup

- Site: `$SITE_URL`
- Throwaway user (NEVER user 1, never a seeded demo user):
  ```
  wp eval '$id = wp_insert_user(array("user_login"=>"qa_deact","user_email"=>"qa_deact@example.test","user_pass"=>wp_generate_password(24),"role"=>"subscriber")); echo $id;'
  wp user application-password create qa_deact qa-journey --porcelain
  ```
- Fixtures — two listings owned by the throwaway user, in DIFFERENT statuses:
  ```
  wp eval '
    $u = get_user_by("login","qa_deact")->ID;
    echo wp_insert_post(array("post_type"=>"listora_listing","post_title"=>"QA Pub","post_status"=>"publish","post_author"=>$u)) . " ";
    echo wp_insert_post(array("post_type"=>"listora_listing","post_title"=>"QA Pend","post_status"=>"pending","post_author"=>$u));
  '
  ```
  Capture `PUB_ID` and `PEND_ID`. Export `AUTH="qa_deact:<app-password>"` and `B="$SITE_URL/wp-json/listora/v1"`.

## Steps

### 1. Unauthenticated deactivate is rejected
- **Action**: `curl -s -o /dev/null -w "%{http_code}" -X POST "$B/me/deactivate"`
- **Expect**: `401`, body code `listora_unauthorized`
- **On fail**: `includes/rest/class-account-controller.php` — `logged_in_permissions()` not wired or returning bare `false` instead of `WP_Error`

### 2. Snapshot the exact BEFORE state
- **Action**: `wp eval 'echo get_post_status(PUB_ID) . "|" . get_post_status(PEND_ID) . "|" . var_export(wb_listora_is_account_deactivated(UID), true);'`
- **Expect**: `publish|pending|false`
- **On fail**: fixtures did not build; abort rather than continue against a wrong baseline

### 3. Deactivate the account
- **Action**: `curl -s -u "$AUTH" -X POST "$B/me/deactivate"`
- **Expect**: `200`; JSON `deactivated: true`, `already_deactivated: false`, `listings_hidden: 1`
- **Note**: `listings_hidden` is 1, NOT 2 — only publicly-visible statuses are swept. The `pending` listing is already non-public, so hiding it would add zero privacy benefit and one reactivation edge case.
- **On fail**: `includes/privacy/class-account-manager.php::hide_listings()` — status sweep list or the `wb_listora_account_deactivate_listing_statuses` filter

### 4. The published listing is out of the directory; the pending one is untouched
- **Action**: `wp eval 'echo get_post_status(PUB_ID) . "|" . get_post_meta(PUB_ID,"_listora_account_deactivated_prior_status",true) . "|" . get_post_status(PEND_ID) . "|[" . get_post_meta(PEND_ID,"_listora_account_deactivated_prior_status",true) . "]";'`
- **Expect**: `listora_deactivated|publish|pending|[]` — the marker records where the published listing came FROM; the pending listing carries no marker because we never changed it
- **On fail**: `class-account-manager.php::hide_listings()` — prior-status meta not recorded, which makes reactivation unable to restore exactly

### 5b. A deactivated member cannot write

- **Action**: `curl -s -u "$AUTH" -X POST -H "Content-Type: application/json" -d '{"listing_id":PUB_ID}' "$B/favorites"`
- **Expect**: `403`, body code `listora_account_deactivated`, and a message telling the member to reactivate from their profile
- **Why this exists**: from 1.2.3 until 1.5.0 this returned `201`. The flag was enforced in exactly ONE place — hiding the profile link — so a member who deactivated their own account kept posting reviews, favourites and listings. Reproduced before the fix.
- **Also expect**: a READ still works — `curl -s -o /dev/null -w "%{http_code}" -u "$AUTH" "$B/search?per_page=1"` returns `200`. Deactivation stops writing, not browsing.
- **On fail**: `includes/core/class-member-suspension.php` — `block_rest_writes()` on `rest_request_before_callbacks`, or `is_write_blocked()` not consulting `wb_listora_is_account_deactivated()`

### 5. The member's profile stops being linkable
- **Action**: `wp eval 'echo "[" . apply_filters("wb_listora_member_profile_url","https://x.test/u/",UID,"review_user") . "]";'`
- **Expect**: `[]` (empty)
- **On fail**: `includes/class-plugin.php::hide_deactivated_member_profile_url()` — filter not registered, or registered at a priority that Pro's BuddyPress listener (priority 10) overrides

### 6. NOTHING was destroyed — every row survives
- **Action**:
  ```sql
  SELECT
    (SELECT COUNT(*) FROM wp_listora_reviews    WHERE user_id = UID) AS reviews,
    (SELECT COUNT(*) FROM wp_listora_favorites  WHERE user_id = UID) AS favorites,
    (SELECT COUNT(*) FROM wp_listora_claims     WHERE user_id = UID) AS claims;
  ```
- **Expect**: every count identical to its pre-deactivation value. Deactivation RETAINS — this is the whole reason it exists alongside deletion.
- **On fail**: `class-account-manager.php::deactivate()` is erasing something. Deactivation must never call the erasure map.

### 7. Deactivating twice is a safe no-op
- **Action**: `curl -s -u "$AUTH" -X POST "$B/me/deactivate"`
- **Expect**: `200`; `already_deactivated: true`, `listings_hidden: 0`
- **Why it matters**: without the idempotency guard the second pass would record `listora_deactivated` as the "prior" status, and reactivation would then restore the listing to deactivated — a double-tapped button silently destroying reversibility.
- **On fail**: `class-account-manager.php::deactivate()` — missing `wb_listora_is_account_deactivated()` early return

### 8. Reactivate
- **Action**: `curl -s -u "$AUTH" -X POST "$B/me/reactivate"`
- **Expect**: `200`; `reactivated: true`, `listings_restored: 1`
- **On fail**: `class-account-manager.php::reactivate()`

### 9. THE ROUND-TRIP MUST BE EXACT
- **Action**: `wp eval 'echo get_post_status(PUB_ID) . "|" . get_post_status(PEND_ID) . "|" . var_export(wb_listora_is_account_deactivated(UID), true) . "|[" . get_post_meta(PUB_ID,"_listora_account_deactivated_prior_status",true) . "]";'`
- **Expect**: `publish|pending|false|[]` — byte-identical to the step-2 snapshot, and the marker meta cleaned up
- **THE ASSERTION THAT MATTERS**: `PEND_ID` is `pending`. If it is `publish`, reactivation blanket-republished and a listing bypassed moderation. FAIL.
- **On fail**: `class-account-manager.php::restore_listings()` — it must read `_listora_account_deactivated_prior_status`, never hardcode `publish` the way the per-listing `reactivate_listing` endpoint legitimately does

### 10. The profile link is back
- **Action**: `wp eval 'echo "[" . apply_filters("wb_listora_member_profile_url","https://x.test/u/",UID,"review_user") . "]";'`
- **Expect**: `[https://x.test/u/]` — read-time suppression, so it returns on its own with no data to migrate back
- **On fail**: `includes/class-plugin.php::hide_deactivated_member_profile_url()` — reading a stale flag

## Pass criteria

ALL must hold:
- Unauthenticated deactivate returns 401
- Deactivate hides ONLY publicly-visible listings (`listings_hidden: 1`, not 2)
- Prior status is recorded on changed listings and on no others
- Profile URL resolves empty while deactivated, and non-empty after reactivation
- Review / favorite / claim row counts are UNCHANGED throughout — deactivation destroys nothing
- Deactivating twice is a no-op that does not corrupt the recorded prior statuses
- After reactivation every listing is at its EXACT pre-deactivation status
- The `pending` listing is still `pending` — never auto-published
- No new PHP notices/warnings in `wp-content/debug.log`

## Fail diagnostics

| Symptom | Likely file |
|---|---|
| 401 on an authenticated call | `includes/rest/class-account-controller.php` — `logged_in_permissions()` |
| Route 404s | `includes/class-plugin.php::register_rest_routes()` — `Account_Controller` missing from the `$controllers` array |
| `listings_hidden: 2` | `class-account-manager.php::hide_listings()` — sweeping non-public statuses |
| Pending listing comes back `publish` | `class-account-manager.php::restore_listings()` — the moderation-bypass regression |
| Rows missing after deactivate | `class-account-manager.php::deactivate()` — calling the erasure map; it must not |
| Profile link still renders | `includes/class-plugin.php::hide_deactivated_member_profile_url()` (priority must beat Pro's BP listener at 10) |
| Restore leaves the marker meta behind | `class-account-manager.php::restore_listings()` — missing `delete_post_meta` |

## Teardown

```
wp eval '
  $u = get_user_by("login","qa_deact");
  if ( $u ) {
    foreach ( get_posts(array("post_type"=>"listora_listing","author"=>$u->ID,"post_status"=>"any","fields"=>"ids","posts_per_page"=>-1)) as $id ) { wp_delete_post( $id, true ); }
    require_once ABSPATH . "wp-admin/includes/user.php";
    wp_delete_user( $u->ID );
  }
'
```
Confirm the directory is back to its baseline listing count and no `qa_deact` user remains.
