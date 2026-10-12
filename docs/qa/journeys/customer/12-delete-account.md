---
journey: delete-account
plugin: wb-listora
priority: critical
roles: [owner-account (throwaway), other, guest]
covers: [account-deletion, apple-5.1.1v, erasure-map, gdpr-art-17, two-path-policy, payments-retention, leads-erasure]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "WP-CLI against THIS site (`--path=$WP_PATH`; a bare `wp` can hit the wrong database)"
  - "curl available for REST calls"
  - "Free 1.2.3+ active (DELETE /listora/v1/me registered)"
  - "Pro active for the coupon_usage / need_responses / audit_log assertions (skip those rows in Free-only mode)"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
themes: []
site_profile: required
supersedes: []
fails_on:
  - unknown (step 9 regressed once during development; no sha recorded)
---

# Deleting an account permanently erases the right data, keeps the right records, and cannot be undone

## Why
Entry point: REST only (`DELETE /listora/v1/me?confirm=DELETE` from the app or API); the web dashboard Profile tab has no delete button, so there are no viewports or themes. Apple App Store Guideline 5.1.1(v) explicitly names "only offering to deactivate or suspend the account" as non-compliant, so this path must ACTUALLY delete: `wp_delete_user()` runs and the `wp_users` row is destroyed. There is no undo. The web dashboard Profile tab has no delete button: the flow is `DELETE /listora/v1/me?confirm=DELETE` from the app or API.

The failure mode here is silent. An erasure that reports success while leaving PII behind looks identical, from the outside, to one that worked. So every table gets probed by hand.

It also locks the distinction that is easiest to get wrong: **account deletion and WordPress' Erase Personal Data tool get DIFFERENT answers.** Deletion destroys the `wp_users` row, so a leftover `user_id` is an orphaned integer pointing at nobody — retaining it is fine. The privacy tool does NOT delete the account, so the same integer still resolves to a living person — retaining it is not fine. Step 9 is the sentinel for that divergence; it regressed once already during development, when account deletion drove the registered eraser and inherited the privacy-tool policy.

## Setup

- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Site: `$SITE_URL`
- Throwaway user (NEVER user 1, never a seeded demo user):
  ```
  wp eval '$id = wp_insert_user(array("user_login"=>"qa_del","user_email"=>"qa_del@example.test","user_pass"=>wp_generate_password(24),"role"=>"subscriber")); echo $id;'
  wp user application-password create qa_del qa-journey --porcelain
  ```
  (Application Passwords are available on http for a `local` environment type; on other sites the journey needs https.)
- Fixtures — one row in every user-keyed table. Pick `OTHER` as a published listing owned by SOMEONE ELSE.
  **`OTHER` must be a listing with no other anonymised review on it** — see the known issue in Fail diagnostics.
  ```
  wp eval '
    global $wpdb; $p = $wpdb->prefix . "listora_"; $u = get_user_by("login","qa_del")->ID;
    $mine  = wp_insert_post(array("post_type"=>"listora_listing","post_title"=>"QA Del Listing","post_status"=>"publish","post_author"=>$u));
    $other = (int) $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_type=\"listora_listing\" AND post_author != {$u} AND post_status=\"publish\" LIMIT 1");
    $wpdb->insert($p."reviews", array("listing_id"=>$other,"user_id"=>$u,"overall_rating"=>4,"title"=>"QA secret","content"=>"QA secret body","status"=>"approved","ip_address"=>"203.0.113.9"));
    $rev = (int) $wpdb->insert_id;
    $wpdb->insert($p."favorites",    array("user_id"=>$u,"listing_id"=>$other,"created_at"=>current_time("mysql",true)));
    $wpdb->insert($p."claims",       array("listing_id"=>$other,"user_id"=>$u,"status"=>"pending","proof_text"=>"QA proof","created_at"=>current_time("mysql",true),"updated_at"=>current_time("mysql",true)));
    $wpdb->insert($p."review_votes", array("user_id"=>$u,"review_id"=>$rev,"created_at"=>current_time("mysql",true)));
    update_user_meta($u,"_listora_saved_searches",array(array("name"=>"QA saved")));
    $wpdb->insert($p."need_responses",array("need_id"=>1,"listing_id"=>$other,"user_id"=>$u,"message"=>"QA msg PII","status"=>"pending","created_at"=>current_time("mysql",true)));
    $wpdb->insert($p."coupon_usage", array("coupon_id"=>1,"user_id"=>$u,"plan_id"=>1,"listing_id"=>$other,"discount"=>5.00,"created_at"=>current_time("mysql",true)));
    $wpdb->insert($p."audit_log",    array("user_id"=>$u,"action"=>"qa_probe","object_type"=>"listing","object_id"=>$other,"details"=>"qa","ip_address"=>"203.0.113.9","created_at"=>current_time("mysql",true)));
    $wpdb->insert($p."leads",        array("listing_id"=>$other,"owner_id"=>1,"sender_id"=>$u,"name"=>"QA sender","email"=>"qa_del@example.test","phone"=>"+1 555","message"=>"QA lead from member","created_at"=>current_time("mysql",true)));
    $wpdb->insert($p."payments",     array("user_id"=>$u,"listing_id"=>$mine,"gateway"=>"stripe","gateway_payment_id"=>"qa_pi","amount"=>99.00,"currency"=>"USD","status"=>"completed","payment_type"=>"one_time","invoice_number"=>"QA-INV","billing_name"=>"QA Real Name","billing_email"=>"qa_del@example.test","created_at"=>current_time("mysql",true)));
    echo "UID=$u MINE=$mine OTHER=$other REV=$rev PAY=" . $wpdb->insert_id;
  '
  ```
  Export `AUTH="qa_del:<app-password>"` and `B="$SITE_URL/wp-json/listora/v1"`.

## Steps

### 1. Unauthenticated DELETE is rejected
- **Action**: `curl -s -o /dev/null -w "%{http_code}" -X DELETE "$B/me?confirm=DELETE"`
- **Expect**: `401`, code `listora_unauthorized`
- **On fail**: `includes/rest/class-account-controller.php:262` (`logged_in_permissions`)

### 2. An accidental DELETE cannot nuke an account
- **Action**: `curl -s -u "$AUTH" -o /tmp/o -w "%{http_code}" -X DELETE "$B/me"` (no `confirm`)
- **Expect**: `400`, code `rest_missing_callback_param` ("Missing parameter(s): confirm")
- **Why**: a stray prefetch, a double-submit, or a mis-wired client must not be able to destroy an account. The nonce proves the request came from our UI; the confirmation proves the human meant it.
- **On fail**: `includes/rest/class-account-controller.php:96` — `confirm` arg not `required => true`

### 3. A wrong confirmation value is rejected
- **Action**: `curl -s -u "$AUTH" -o /tmp/o -w "%{http_code}" -X DELETE "$B/me?confirm=yes"`
- **Expect**: `400`, code `rest_invalid_param`, with `details.confirm.code` = `listora_confirmation_required` and the message `Account deletion is permanent and cannot be undone. Send confirm="DELETE" to proceed.`
- **On fail**: `includes/rest/class-account-controller.php:276` (`validate_delete_confirmation`)

### 4. The account still exists after those refusals
- **Action**: `wp eval 'echo var_export((bool) get_user_by("login","qa_del"), true);'`
- **Expect**: `true` — refusals must not have partially erased anything
- **On fail**: `includes/privacy/class-account-manager.php:353` (`delete`) is running before validation; the guard belongs at the args layer

### 5. Delete for real
- **Action**: `curl -s -u "$AUTH" -X DELETE "$B/me?confirm=DELETE"`
- **Expect**: `200`; `deleted: true`; `erasers_run: ["wb-listora","wb-listora-pro"]`; `rows_erased` shows `payments: 1, need_responses: 1` (saved searches are user meta, so they are removed with the user and are not counted); `listings: {strategy: "trash", count: 1}`; message "Your account and personal data have been permanently deleted."
- **CRITICAL**: `rows_erased.payments` must be `1`, NOT `0`. A `0` means the registered eraser already applied the privacy-tool policy and the two paths have collapsed — see step 9.
- **On fail**: `includes/privacy/class-account-manager.php:353` (`delete`)

### 6. The `wp_users` row is GONE — this is what Apple requires
- **Action**: `wp eval 'echo var_export((bool) get_userdata(UID), true) . "|" . (int) $GLOBALS["wpdb"]->get_var("SELECT COUNT(*) FROM {$GLOBALS[\"wpdb\"]->usermeta} WHERE user_id=UID");'`
- **Expect**: `false|0` — the account is destroyed and core wiped its usermeta
- **On fail**: `includes/privacy/class-account-manager.php:353` (`delete`) — most commonly `wp_delete_user()` is undefined because `wp-admin/includes/user.php` was not required (it is NOT loaded on a REST request)

### 7. The same credentials no longer authenticate
- **Action**: `curl -s -u "$AUTH" -o /dev/null -w "%{http_code}" -X POST "$B/me/deactivate"`
- **Expect**: `401`
- **On fail**: the user row survived; re-check step 6

### 8. Reviews: ANONYMISED, not deleted — the rating survives
- **Action**: `wp eval 'global $wpdb; echo json_encode($wpdb->get_row("SELECT user_id,title,content,ip_address,overall_rating FROM {$wpdb->prefix}listora_reviews WHERE id=REV", ARRAY_A));'`
- **Expect**: `user_id: 0`, `title: ""`, `content: ""`, `ip_address: ""`, **`overall_rating: 4` (UNCHANGED)**
- **Why**: deleting reviews would silently rewrite the listing's star rating every time a member leaves, punishing the listing owner for someone else's account closure. The row stays so the aggregate stays honest.
- **On fail**: `includes/privacy/class-privacy-eraser.php:169` (`anonymize_reviews`). If `user_id` is still the real ID, see the UNIQUE-index issue in Fail diagnostics.

### 9. Pointer-only rows are RETAINED — the two-path sentinel
- **Action**:
  ```sql
  SELECT
    (SELECT COUNT(*) FROM wp_listora_review_votes WHERE user_id = UID) AS votes,
    (SELECT COUNT(*) FROM wp_listora_coupon_usage WHERE user_id = UID) AS coupons;
  ```
- **Expect**: `votes = 1`, `coupons = 1` — **RETAINED**
- **THIS IS THE ASSERTION THAT REGRESSED ONCE.** These rows are pointers with no PII in them. `wp_delete_user()` has destroyed the `wp_users` row, so `user_id` is now an orphaned integer resolving to nobody — not personal data. Retaining it keeps each review's `helpful_count` and each coupon's redemption total honest.
  If these are `0`, account deletion has applied the `on_privacy_erasure` policy (which DOES delete them, correctly, because on that path the account survives). The paths have collapsed.
- **On fail**: `includes/privacy/class-account-manager.php:79` (`is_deleting_account`) / the guard at `includes/privacy/class-privacy-eraser.php:118` that skips the map when Account_Manager is driving

### 10. Member-authored free text is GONE
- **Action**:
  ```sql
  SELECT
    (SELECT COUNT(*) FROM wp_listora_favorites      WHERE user_id = UID) AS favs,
    (SELECT COUNT(*) FROM wp_listora_claims         WHERE user_id = UID) AS claims,
    (SELECT COUNT(*) FROM wp_listora_need_responses WHERE user_id = UID) AS needs;
  ```
  Saved searches are user meta (`_listora_saved_searches`), removed by `wp_delete_user()`; there is no `listora_saved_searches` table.
- **Expect**: all `0`
- **On fail**: `includes/privacy/class-privacy-eraser.php` (favorites/claims) or the map's `account_eraser` entries (need_responses) in `wb-listora-pro/includes/privacy/pro-privacy-helpers.php`

### 10a. Contact messages the member SENT are erased (2.0.0 leads)
- **Action**: `SELECT id, sender_id, name, email, phone, message FROM wp_listora_leads WHERE name = "QA sender";`
- **Expect (per the 2.0.0 readme: leads are covered by personal data erase)**: no row.
- **expected-fail: PRODUCT-BUGS leads eraser id (`wb-listora-leads` not run by account deletion).** Observed 2026-10-12: the row survives with `sender_id` = the deleted id and name, email, phone and message intact, because account deletion runs only the eraser ids `wb-listora` and `wb-listora-pro`, and the leads eraser is registered as `wb-listora-leads`. Leave this step failing until fixed.
- **Read-back**: the SELECT above; positive control: the same query before step 5 returns the row.
- **On fail**: `includes/privacy/class-account-manager.php:465` (default `wb_listora_account_deletion_eraser_ids`), eraser registered at `includes/core/class-leads.php:307`.
- **Also check (owner side)**: leads RECEIVED by the deleted member (`owner_id = UID`) remain until the retention window prunes them (Listings > Leads > "Keep leads for", default 12 months); that is by design, they hold the visitor's data, not the member's.

### 11. Payments: the ROW SURVIVES, the identity does not — the money trail
- **Action**: `wp eval 'global $wpdb; echo json_encode($wpdb->get_row("SELECT user_id,billing_name,billing_email,amount,invoice_number,status FROM {$wpdb->prefix}listora_payments WHERE id=PAY", ARRAY_A));'`
- **Expect**: the row EXISTS; `billing_name: ""`, `billing_email: ""`; `amount: 99.00`, `invoice_number: "QA-INV"`, `status: "completed"` all UNCHANGED
- **Why**: a financial record. GDPR Art. 17(3)(b) is a lawful basis to retain it, so the site owner keeps an intact money trail; only the identity columns go. `user_id` is deliberately kept — post-deletion it is an orphaned integer, and it is what lets the owner still group a former customer's payments for reconciliation.
- **On fail**: the `payments` entry in `includes/privacy/privacy-helpers.php`, or `includes/privacy/class-account-manager.php:684` (`anonymize_rows`)

### 12. Audit log: anonymised in place
- **Action**: `wp eval 'global $wpdb; echo json_encode($wpdb->get_results("SELECT user_id,action,ip_address FROM {$wpdb->prefix}listora_audit_log WHERE action=\"qa_probe\"", ARRAY_A));'`
- **Expect**: row exists with `user_id: 0` and `ip_address: ""`
- **On fail**: `wb-listora-pro/includes/privacy/class-personal-data-tools.php:349` (`anonymize_audit_rows`)

### 13. SDK financial tables retained
- **Action**:
  ```sql
  SELECT
    (SELECT COUNT(*) FROM wp_listora_credit_ledger      WHERE user_id = UID) AS ledger,
    (SELECT COUNT(*) FROM wp_listora_credit_gateway_log WHERE user_id = UID) AS gateway;
  ```
- **Expect**: retained (non-zero if fixtures seeded them). SDK-owned financial records — Listora does not write another plugin's ledger, and Art. 17(3)(b) covers the retention.
- **On fail**: the map is reaching tables it should not own

### 14. Listings followed the configured strategy
- **Action**: `wp eval 'echo get_post_status(MINE) ?: "GONE";'`
- **Expect**: `trash` (the default strategy)
- **Why**: a directory is a shared asset. Trash removes the member's presence immediately while leaving the owner a recovery window; core's normal trash purge finishes the job. Override with `wb_listora_account_deletion_listing_strategy`.
- **On fail**: `includes/privacy/class-account-manager.php:746` (`handle_listings_on_delete`)

### 15. No new PHP notices
- **Action**: diff `wp-content/debug.log` against its pre-run watermark
- **Expect**: no new warnings/notices/deprecations attributable to the account-deletion stack
- **On fail**: read the trace in the log — it names the exact eraser method

## Role ladder
| Rung | Expect |
|---|---|
| The account owner (throwaway) | steps 2-15 |
| {guest} | `401 listora_unauthorized` (step 1); also `DELETE /me` from a cookie-less client |
| {other} | cannot delete anyone else: the route takes no user id and acts only on the caller; deleting as {other} removes {other}, never the throwaway (do not run against a seeded persona) |
| {admin} | control: never use user 1; admin deleting another user goes through WordPress user tools, not this route |

## Pass criteria

ALL must hold:
- 401 unauthenticated; 400 without `confirm`; 400 with a wrong `confirm`; account intact after all three
- `wp_users` row GONE after a confirmed delete; usermeta gone; credentials stop working
- Reviews anonymised (`user_id = 0`, text stripped) with `overall_rating` UNCHANGED
- `review_votes` + `coupon_usage` RETAINED (orphaned pointers) — the two-path sentinel
- favorites, claims, saved_searches, need_responses all GONE
- `payments` row STILL PRESENT with `billing_name`/`billing_email` scrubbed and amount/invoice/status intact
- `audit_log` anonymised in place, not deleted
- Leads the member sent are erased (step 10a, expected-fail until the eraser id is added)
- Listings trashed per the default strategy
- No new PHP notices in `wp-content/debug.log`

## Fail diagnostics

| Symptom | Likely file |
|---|---|
| `wp_delete_user()` undefined / 500 | `includes/privacy/class-account-manager.php:353` (`delete`) — `wp-admin/includes/user.php` not required (it is not loaded on REST) |
| `rows_erased.payments: 0` and votes/coupons gone | `includes/privacy/class-account-manager.php:79` (`is_deleting_account`) guard missing → the two paths collapsed; the registered eraser applied `on_privacy_erasure` during a deletion |
| Erasers no-op, all PII survives | `includes/privacy/class-account-manager.php:353` (`delete`) — `wp_delete_user()` ran BEFORE the erasers. Every eraser resolves the subject via `get_user_by('email', ...)`; delete the user first and they all silently no-op. Order is load-bearing. |
| Only the first 100 rows erased | `includes/privacy/class-account-manager.php:447` (`run_registered_erasers`) — not looping until `done`; the WP eraser contract is paginated |
| Review keeps `user_id`/title/content; debug.log shows `Duplicate entry '0-<listing_id>' for key 'idx_user_listing'` | **KNOWN PRE-EXISTING BUG** (not a regression). `wp_listora_reviews` has `UNIQUE idx_user_listing(user_id, listing_id)`; anonymising sets `user_id = 0`, so only the FIRST anonymised review per listing fits. Any later member erased who reviewed the SAME listing silently fails to anonymise, and `Privacy_Eraser` ignores `$wpdb->update()`'s return value so it still reports success. Needs a schema change (the UNIQUE index must tolerate multiple `user_id = 0` rows per listing) — a minor release, not a patch. Until then, pick an `OTHER` listing with no existing anonymised review. |
| Map entry ignored | `includes/privacy/privacy-helpers.php` — `handled_by` must be `account_eraser` for the generic executor to run it |
| Pro tables untouched | `wb-listora-pro/wb-listora-pro.php` — `includes/privacy/pro-privacy-helpers.php` not eager-required, so the bare function is missing when the filter fires |

## Not verified
Verified through REST + DB with a throwaway user (curl + wp eval), not in a browser. Retry/double-submit (a second DELETE after success returns 401), a member with 200+ listings, the 390 and theme matrices (n/a, REST only), and the Free-only mode (Pro rows skipped) were not run.

## Cleanup

The account is already gone. Purge the rows the policy deliberately retained, plus the fixtures:

```
wp eval '
  global $wpdb; $p = $wpdb->prefix . "listora_";
  foreach ( array("reviews","favorites","claims","review_votes","need_responses","coupon_usage","audit_log","credit_ledger","credit_gateway_log","payments") as $t ) {
    $wpdb->query( "DELETE FROM {$p}{$t} WHERE user_id = UID" );
  }
  $wpdb->query( "DELETE FROM {$p}reviews   WHERE id = REV" );
  $wpdb->query( "DELETE FROM {$p}payments  WHERE id = PAY" );
  $wpdb->query( "DELETE FROM {$p}audit_log WHERE action = \"qa_probe\"" );
  $wpdb->query( "DELETE FROM {$p}leads WHERE name = \"QA sender\"" );
  wp_delete_post( MINE, true );
'
```
Confirm: the directory is back to its baseline listing count, no `qa_del` user, no rows for `UID` in any `listora_*` table.
