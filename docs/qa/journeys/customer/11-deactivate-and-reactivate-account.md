---
journey: deactivate-and-reactivate-account
plugin: wb-listora
priority: critical
roles: [owner, other, guest]
covers: [account-deactivation, account-reactivation, apple-5.1.1v, listing-status-restore, deactivated-write-block, profile-link-suppression, idempotency]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Dev auto-login mu-plugin installed (?autologin=<login>)"
  - "WP-CLI (`wp eval`) against THIS site; on Local use the Local-bundled wp-cli with --path (a bare `wp` can hit the wrong database)"
  - "Pro active only for the BuddyPress profile-link check in step 8; every other step is Free"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
themes: []
site_profile: required
supersedes: []
---

# A member can deactivate and reactivate their account, and the round trip restores every listing exactly

## Why
A member steps away and later comes back. Deactivation hides their published listings from the directory and stops them posting, but destroys nothing. Reactivation puts every listing back at the exact status it had before, so a `pending` listing is never auto-published (a moderation bypass inside a privacy feature would be a release blocker).

Entry points: REST only in Free (no web dashboard control; `viewports`/`themes` do not apply). Wp-admin has no equivalent; other tools act through WordPress user tools.

## Setup

- Resolve placeholders from docs/qa/sites/<site>.json (see README).

- Throwaway member (never user 1, never a seeded demo user; {owner} is NOT used because the journey changes the account), with a public display name so the profile link exists: `wp_insert_user( login QA-J1-deact, role subscriber )` then `wp_update_user( display_name 'Dee Deactivated' )`. Capture `UID`.
- Two listings by that member in DIFFERENT statuses: `QA-J1 Deact Pub` (`publish`) and `QA-J1 Deact Pend` (`pending`); capture `PUB_ID`, `PEND_ID`.
- Optional but recommended: one favourite, one pending claim and one review by the member (to prove nothing is erased).
- REST base: `B=$SITE_URL/wp-json/listora/v1`; log in with `?autologin=QA-J1-deact` into a cookie jar and send `X-WP-Nonce`.
- Read-back for steps 3-10 is the snapshot helper below (plus the row counts in step 6). The deactivated write refusal (step 5) uses the same REST gate as `member-write-gate`, with its own code `listora_account_deactivated`.
- Snapshot helper (WP-CLI eval): `get_post_status(PUB_ID) . '|' . get_post_status(PEND_ID) . '|' . var_export( wb_listora_is_account_deactivated(UID), true ) . '|[' . get_post_meta( PUB_ID, '_listora_account_deactivated_prior_status', true ) . ']'`.

## Steps

### 1. NEGATIVE: logged out cannot deactivate
- **Action**: `POST $B/me/deactivate` with no session.
- **Expect**: `401`, code `listora_unauthorized` ("You must be logged in to perform this action.").
- **On fail**: `includes/rest/class-account-controller.php:262` (`logged_in_permissions`)

### 2. Snapshot the exact BEFORE state
- **Action**: run the snapshot helper.
- **Expect**: `publish|pending|false|[]`. Abort if not (the fixtures did not build).

### 3. Deactivate
- **Action**: `POST $B/me/deactivate` as the member.
- **Expect**: `200` `{"deactivated":true,"already_deactivated":false,"listings_hidden":1,"message":"Your account has been deactivated. Your listings are hidden from the directory and nothing has been deleted - sign in and reactivate at any time to restore everything."}`. `listings_hidden` is `1`, not `2`: only the statuses in `wb_listora_account_deactivate_listing_statuses` (`publish`, `listora_expired`) are swept; the `pending` listing is already non-public.
- **On fail**: `includes/privacy/class-account-manager.php:96` (`deactivate`), `:210` (`hide_listings`)

### 4. The published listing is out of the directory, the pending one untouched
- **Action**: snapshot helper; then open `$SITE_URL/?post_type=listora_listing&p=PUB_ID` logged out; then `GET $B/search?keyword=Deact` logged out.
- **Expect**: `listora_deactivated|pending|true|[publish]`; the listing URL is `404` for visitors; search returns no `QA-J1 Deact Pub`. The marker `_listora_account_deactivated_prior_status` exists on `PUB_ID` only (`PEND_ID` has none: we never changed it).
- **On fail**: `includes/privacy/class-account-manager.php:210-250` (prior status not recorded makes exact restore impossible)

### 5. A deactivated member cannot write (but can read)
- **Action** (member session): `POST $B/favorites {"listing_id": <any>}`; `POST $B/listings/<id>/reviews {...valid review...}`; then `GET $B/search?per_page=1`.
- **Expect**: both writes return `403` code `listora_account_deactivated` ("Your account is deactivated, so you cannot post or edit content. Reactivate your account from your profile to start posting again."); the read returns `200`. Deactivation stops writing, not browsing.
- **On fail**: `includes/core/class-member-suspension.php:53,276` (`block_rest_writes` on `rest_request_before_callbacks`)

### 6. NOTHING was destroyed
- **Action**: count the member's rows: `listora_reviews`, `listora_favorites`, `listora_claims` where `user_id = UID`.
- **Expect**: each count equals the value taken before deactivation (verified: reviews 1, favourites 1, claims 1 before, during and after). Deactivation must never call the erasure map.
- **On fail**: `includes/privacy/class-account-manager.php:96-150`

### 7. Deactivating twice is a safe no-op
- **Action**: `POST $B/me/deactivate` again.
- **Expect**: `200` `{"deactivated":true,"already_deactivated":true,"listings_hidden":0,"message":"Your account is already deactivated."}`; the prior-status marker on `PUB_ID` still says `publish` (a second pass must not record `listora_deactivated` as the prior status).
- **On fail**: `includes/privacy/class-account-manager.php:108-115`

### 8. The public profile link is suppressed
- **Action**: WP-CLI eval `apply_filters( 'wb_listora_member_profile_url', 'https://x.test/u/', UID, 'review_user' )` before and during deactivation.
- **Expect**: before: the member's real profile URL (with Pro BuddyPress: `$SITE_URL/members/qa-j1-deact/`); during: `''` (empty). A member whose display name equals their login also gets `''` even when active (2.0.0: a never-named member has no profile link), which is why this journey names the fixture member.
- **On fail**: `includes/class-plugin.php:411,487` (`hide_deactivated_member_profile_url`, priority 20 so it beats Pro's priority-10 BuddyPress listener)

### 9. Reactivate
- **Action**: `POST $B/me/reactivate`.
- **Expect**: `200` `{"reactivated":true,"already_active":false,"listings_restored":1,"message":"Welcome back - your account is active again and your listings have been restored."}`.
- **On fail**: `includes/privacy/class-account-manager.php:154` (`reactivate`), `:268` (`restore_listings`)

### 10. THE ROUND-TRIP MUST BE EXACT
- **Action**: snapshot helper.
- **Expect**: `publish|pending|false|[]`, byte-identical to step 2, and the prior-status marker is gone. **The assertion that matters**: `PEND_ID` is `pending`. If it is `publish`, reactivation blanket-republished and a listing bypassed moderation: FAIL.
- **Also expect**: the profile URL filter returns the real profile URL again (read-time suppression, nothing to migrate back); writes work again (`POST /favorites` returns `201`); a second `POST /me/reactivate` returns `200 {"reactivated":true,"already_active":true,"listings_restored":0}`.
- **On fail**: `includes/privacy/class-account-manager.php:268-310` (must read the stored prior status, never hardcode `publish`)

### 11. Scale
- **Action**: give the member 200 `publish` listings (WP-CLI loop), run steps 3 and 9.
- **Expect**: `listings_hidden` / `listings_restored` = 200 and the calls complete within the request time limit; the sweep is one `get_posts( fields=ids )` and one update per listing (`includes/privacy/class-account-manager.php:210-300`), so a member with thousands of listings may need a background job.
- **Read-back**: `wp post list --author=UID --post_status=publish --format=count` is 200 after step 9 and 0 after step 3. Not verified live.

## Role ladder
| Actor | Action | Expect |
|---|---|---|
| Logged out | `POST /me/deactivate`, `/me/reactivate` | `401 listora_unauthorized` |
| The member | steps 3-10 | as above |
| Another member ({other}) | stays untouched | their listings and writes are unaffected during the member's deactivation (the endpoints act only on the caller; there is no user_id parameter) |
| Admin (control) | not applicable | admin deactivating or reactivating someone else is done through WordPress user tools, not these routes; do NOT deactivate user 1 |

## Pass criteria

1. Logged-out deactivate is `401`.
2. Deactivate hides only publicly visible listings and records the prior status on those only.
3. Deactivated member's writes return `403 listora_account_deactivated`; reads work.
4. Reviews, favourites and claims rows are unchanged throughout.
5. Deactivating twice and reactivating twice are no-ops that do not corrupt prior statuses.
6. After reactivation every listing is at its exact pre-deactivation status; the `pending` listing is still `pending`.
7. Profile link is empty while deactivated and back afterwards (named member).
8. No new PHP notices in `wp-content/debug.log`.

## Fail diagnostics

| Symptom | Likely file |
|---|---|
| 401 on an authenticated call | `includes/rest/class-account-controller.php:262` |
| Route 404s | `includes/class-plugin.php` (`Account_Controller` in the controllers array) |
| `listings_hidden: 2` | `includes/privacy/class-account-manager.php:210` (sweeping non-public statuses) |
| Pending listing comes back `publish` | `includes/privacy/class-account-manager.php:268` |
| Rows missing after deactivate | `includes/privacy/class-account-manager.php:96` (calling the erasure map) |
| Writes still accepted while deactivated | `includes/core/class-member-suspension.php:276` |
| Profile link still renders | `includes/class-plugin.php:487` (priority must beat Pro's listener at 10) |
| Marker meta left behind after restore | `includes/privacy/class-account-manager.php:268-300` |

## Not verified
Step 11 (scale, 200 listings) and the BuddyPress profile-link value (needs Pro with BuddyPress) were not run live; the step-4 visitor 404 and step 5 write refusals were verified via curl REST with a cookie session plus nonce.

## Cleanup

Delete the member's listings (`wp_delete_post( $id, true )`), delete their reviews / favourites / claims rows, then `wp_delete_user( UID )`; confirm the Directory total is back to baseline and no `QA-J1-deact` user remains.
