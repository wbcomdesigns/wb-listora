---
journey: sign-in-required-to-submit
group: submission
actor: customer
mode: mixed
plugin: wb-listora
priority: high
roles: [guest, owner, nocap, admin]
covers: [submission-login-gate, guest-submission-removed, submit-rest-401, legacy-pending-verification, verification-landing-content-type, bc-10393853703]
prerequisites:
  - "Site reachable at $SITE_URL; WP-CLI at $WP_PATH"
  - "The Add Listing page is mapped ({add_listing} in the site profile)"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free da4471a2 / pro 8d4eeb3
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unbridged default theme]
supersedes: ["ora:customer/07-guest-submission-with-email-verify.md", "ora:customer/07-guest-submission.md"]
fails_on: "step 8: unknown (no sha recorded)"
site_profile: required
---

# A logged-out visitor must sign in to submit, and listings left over from guest submission can still be verified

## Why
Guest submission was removed: a visitor who is not signed in gets a sign-in prompt, never a form. If the gate leaks, anonymous listings appear; if the prompt is a dead end, the site loses submissions. Listings created by the old guest flow still sit in `pending_verification` and must stay recoverable.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Steps 1-4 need no data. Steps 5-9 seed one legacy listing:
  ```bash
  cd "$WP_PATH"
  OWNER_ID=$(wp user get {owner} --field=ID)
  LEGACY_ID=$(wp post create --post_type=listora_listing --post_status=pending_verification --post_title='QA-RPL legacy unverified' --post_author=$OWNER_ID --porcelain)
  TOKEN=$(wp eval "echo \WBListora\Workflow\Email_Verification::generate_token($LEGACY_ID,$OWNER_ID);")
  VERIFY_URL=$(wp eval "echo \WBListora\Workflow\Email_Verification::get_verify_url($LEGACY_ID,'$TOKEN');")
  ```

## Steps

### 1. The Add Listing page shows a sign-in prompt to a guest
- **Action**: as {guest}, open `$SITE_URL{add_listing}`.
- **Expect**: the block root has class `listora-submission--login-required`; heading "Add Your Listing"; the text "Please log in or create an account to submit a listing."; two buttons, "Log In" (`.listora-btn--primary`) and "Create Account" (`.listora-btn--secondary`). No wizard step (`.listora-submission__step` count is 0) and no file input.
- **Read-back**: `curl -s $SITE_URL{add_listing} | grep -c 'listora-submission__step'` prints `0`.

### 2. Log In returns the visitor to the Add Listing page
- **Action**: read the "Log In" link, then sign in through it as {owner}.
- **Expect**: the link is `wp_login_url()` with `redirect_to` equal to the Add Listing page URL; after sign-in the browser lands on `$SITE_URL{add_listing}` and the wizard renders (first step visible).
- **Read-back**: the `redirect_to` query value, URL-decoded, equals `$SITE_URL{add_listing}`.

### 3. Create Account goes to registration and can be filtered away
- **Action**: read the "Create Account" link. Then, in a temporary mu-plugin, `add_filter( 'wb_listora_submission_register_url', '__return_empty_string' );` and reload as {guest}.
- **Expect**: the link is `wp_registration_url()` (a theme or membership plugin may map it to its own register page). With the filter returning an empty string the "Create Account" button is absent and "Log In" remains. Remove the mu-plugin.
- **Read-back**: button count is 2 without the filter and 1 with it.

### 4. The REST route refuses a guest, and says why
- **Action**: `curl -s -w ' [%{http_code}]' -X POST $SITE_URL/wp-json/listora/v1/submit -H 'Content-Type: application/json' -d '{"title":"QA-RPL guest"}'`
- **Expect**: `401` with `code: listora_unauthorized` and the message "Please log in to submit a listing."
- **Read-back**: `wp post list --post_type=listora_listing --s='QA-RPL guest' --post_status=any --format=count` prints `0`.
- **Positive control**: the same POST with {owner}'s cookie and a REST nonce does not return 401, so the refusal is the guest gate and not a broken route.

### 5. A wrong or expired verification link shows a page, not a 404 or JSON
- **Action**: as {guest}, open `${VERIFY_URL}x` (tampered token).
- **Expect**: HTTP `410`, `Content-Type: text/html`, heading "Verification link is invalid", and a control to request a new link. The listing is still `pending_verification`.
- **Read-back**: `wp post get $LEGACY_ID --field=post_status`.

### 6. Requesting a new link needs the owner or the owner's email, and has a cooldown
- **Action**: `POST /wp-json/listora/v1/submission/resend-verification` with `{"listing_id": $LEGACY_ID}` as {guest}; then again with `"email"` set to {owner}'s email; then a third time straight away.
- **Expect**: without a matching email, `403` `{"sent":false,"error":"forbidden"}` and no mail; with the owner's email, `200` and one new mail to that address; the immediate repeat is refused by the 5-minute cooldown and sends nothing.
- **Read-back**: the mail catcher holds exactly one new message for {owner}; `_listora_verify_last_sent_at` on the listing changed once. Not verified live (the 403 branch was run; the email and cooldown branches were not).

### 7. The valid link verifies once, and a second click is harmless
- **Action**: as {guest}, open `$VERIFY_URL` (regenerate it if step 6 issued a new token), then open it again.
- **Expect**: first open: HTTP `200`, `text/html`, heading "Email verified - your listing is being reviewed" (wording follows the type's moderation: a type set to auto-approve publishes instead). Second open: HTTP `200`, heading "This listing is already verified". The status moves once.
- **Read-back**: `wp post get $LEGACY_ID --field=post_status` is `pending` (or `publish` for an auto-approve type) after both opens; `_listora_verified_at` is set.

### 8. Email landing pages are HTML documents (bc-10393853703)
- **Action**: `curl -sI` the verify link from step 7 and an unsubscribe link: `wp eval 'echo \WBListora\REST\Unsubscribe_Controller::build_url( '"$OWNER_ID"', "review_reminder" );'`. Open both in a real browser.
- **Expect**: both answer `Content-Type: text/html; charset=UTF-8` and render as a page ("Email verified", "You have been unsubscribed").
- **expected-fail**: bc-10393853703. On 2.0.0 the verify page is `text/html`, but the unsubscribe page still answers `application/json; charset=UTF-8` with an HTML body.
- **Read-back**: `curl -sI <url> | grep -i content-type`. The unsubscribe click sets user meta `_listora_notify_review_reminder` to `0`; delete it in Cleanup.

### 9. The owner and the site owner can see and rescue a legacy listing
- **Action**: seed a second `pending_verification` listing. As {owner}, open `$SITE_URL{dashboard}`; as {admin}, open wp-admin > Listings.
- **Expect**: the owner's dashboard counts it under its verification status and does not show it as published; the admin list shows the status "Pending Email Verification" and a "Mark verified" row action that moves it to the type's moderation status; it is absent from the public directory and search.
- **Read-back**: `wp post get <id> --field=post_status` after "Mark verified"; `curl -s "$SITE_URL/wp-json/listora/v1/search?keyword=QA-RPL" ` has no row for it before verification. Not verified live.

### 10. Layout of the prompt and of the result pages
- **Action**: open the step 1 prompt and the step 5 and 7 pages at 1440 and 390, light and dark, on the primary and the unbridged theme.
- **Expect**: no horizontal scroll; the two buttons stack or sit side by side without overflow and are at least 40 px tall at 390; the icon, heading and text are centred and readable in dark mode; the buttons are styled by the plugin on the unbridged theme. Run `MICRO_CHECKS.md` P-01, P-07, P-08, P-09, P-10, P-11 on each. Not verified live.

## Role ladder
| Rung | Expect |
|---|---|
| {guest} | prompt only (step 1); REST 401 (step 4); can open verify links and the result pages (steps 5, 7) |
| {owner} | wizard after sign-in (step 2); can request a new link for their own legacy listing (step 6); sees it in the dashboard (step 9) |
| {nocap} | signed in but without `submit_listora_listing`: sees "You cannot add listings" with a dashboard link, not the wizard and not the sign-in prompt (owned by `add-listing-no-permission-message`) |
| {admin} (control) | wizard; "Mark verified" row action in wp-admin (step 9) |

## Pass criteria
1. A guest never sees a submission form and cannot create a listing over REST.
2. The Log In link returns to the Add Listing page; the Create Account link can be removed by filter.
3. A legacy `pending_verification` listing verifies exactly once through its link, and a bad link shows an HTML page with a way to get a new one.
4. Only the owner, or someone who knows the owner's email, can trigger a resend, and not more than once per 5 minutes.
5. Step 8 is a known defect until the unsubscribe page is served as HTML.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Guest sees wizard steps | login gate skipped | `blocks/listing-submission/render.php` (`$is_guest` branch) |
| REST creates a listing for a guest | permission callback | `includes/rest/class-submission-controller.php::submit_listing_permissions` |
| Log In does not return to the page | `redirect_to` lost | `render.php` (`wp_login_url( get_permalink() )`), theme login override |
| Bad link is a bare 404 or JSON | result page not rendered | `includes/workflow/class-email-verification.php::handle_verify_url`, `render_result_page` |
| Resend works for anyone | owner/email check | `class-submission-controller.php::resend_verification_endpoint` |
| Unsubscribe page shows source or downloads | served as JSON | `includes/rest/class-unsubscribe-controller.php` |

## Not verified
- Run live on 2026-10-12 (curl as guest, WP-CLI read-backs): steps 1, 4, 5, 7, 8 and the 403 branch of step 6, plus the link targets in steps 2 and 3.
- Not run: the sign-in round trip in step 2, the filter in step 3, the email and cooldown branches of step 6, step 9, and all of step 10 (no browser pass at 390 or in dark mode).

## Cleanup
```bash
wp post delete $LEGACY_ID --force          # and the second listing from step 9
wp user meta delete $OWNER_ID _listora_notify_review_reminder
```
Remove the temporary mu-plugin from step 3. Delete the mails this run sent from the mail catcher.
