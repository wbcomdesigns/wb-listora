---
journey: guest-submission-with-email-verify
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [guest-submission-flow, email-verification, expired-token-ux, verification-landing-content-type, bc-10393853703]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Settings > Submission: Allow guest submissions ON, Require email verification ON"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, one unknown default theme]
site_profile: required
supersedes: []
fails_on: "step 9: unknown (no sha recorded)"
---

# A logged-out visitor submits a listing and verifies their email

## Why
Guests can only publish through the emailed link; an expired or tampered token that shows a 404 or JSON, a missing mail, or no resend cooldown strands the submitter or opens an abuse path.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). `GUEST_EMAIL` = `{guest}-qa@example.test`.
- `wp option patch update wb_listora_settings allow_guest_submissions 1` and `require_email_verification 1`.
- Code map: `Email_Verification` class (`resend_verification` cooldown), submission wizard render guard, token-validation handler.

## Steps

### 1. Open Add Listing logged-out
- **Action**: navigate to `$SITE_URL/add-listing/`.
- **Expect**: wizard renders, NO redirect to wp-login.
- **Read-back**: HTTP 200.

### 2. Walk the wizard with an anonymous email
- **Action**: pick the {type}, fill basic info with email = GUEST_EMAIL, continue, fill required Details, submit.
- **Expect**: final step "Check your email to verify".
- **Read-back**: the listing exists with `post_status='pending_verification'`.

### 3. Verification email is logged
- **Action**: `SELECT template, recipient FROM ${PREFIX}listora_email_log WHERE template='listing-verify-email' AND recipient=GUEST_EMAIL ORDER BY created_at DESC LIMIT 1`.
- **Expect**: one recent row; capture the verification URL (e.g. `/?listora_verify_email=<token>`) from the body or DB.

### 4. Click the verification link
- **Action**: navigate to the token URL.
- **Expect**: "Email verified" page; listing transitions to `pending`.
- **Read-back**: `post_status='pending'` and postmeta `_listora_email_verified=1`.

### 5. The listing is in the admin pending queue
- **Action**: `wp post list --post_type=listora_listing --post_status=pending --format=csv`.
- **Expect**: the new listing is listed.

### 6. Expired/tampered token UX
- **Action**: change a few characters in the token and navigate.
- **Expect**: a clear page "This verification link has expired or is invalid. [Request a new link]", NOT a generic 404.
- **Read-back**: HTTP status is the friendly page's (not a bare 404).

### 7. Request a new link
- **Action**: click "Request a new link", enter the email, submit; click again immediately.
- **Expect**: `POST /wp-json/listora/v1/submission/resend-verification` `200`; a new email-log row; the second request within 5 minutes is refused by the per-listing cooldown.
- **Read-back**: email-log row count +1 only.

### 8. Re-verify with the new link
- **Action**: extract the new token URL and navigate.
- **Expect**: verification succeeds, listing -> `pending`.

### 9. Landing pages are HTML documents, not JSON (bc-10393853703)
- **Action** (anonymous, real browser and `curl -sI`): open the email-verify link from step 4 and an unsubscribe link (`\WBListora\REST\Unsubscribe_Controller::build_url( <user>, 'review_reminder' )`).
- **Expect**: both `Content-Type: text/html; charset=UTF-8`, no `X-Content-Type-Options: nosniff` paired with `application/json`; the browser renders "Email verified" / "You have been unsubscribed" instead of source or a download.
- **Read-back**: `curl -sI <url> | grep -i content-type`; positive control: the REST route `/wp-json/listora/v1/...` still answers `application/json`. Not verified live.

### 10. Boundary and lifecycle
- **Action**: repeat step 2 with an invalid email, a 255+ char email, and an email already registered as a member; click the link twice; click a link for a deleted listing.
- **Expect**: invalid/over-long refused with a field error; an existing member's email is handled without leaking account existence; second click is idempotent (still `pending`, friendly page); deleted listing shows the friendly invalid-link page. Not verified live.

## Role ladder
| Rung | Expect |
|---|---|
| {guest} | steps 1-10 |
| Logged-in member | does not see the guest verification path (submits directly) |
| {admin} (control) | sees the pending listing in Moderation after step 4 |

## Pass criteria
1. A logged-out submission produces a `pending_verification` listing and an email to the submitter.
2. The link moves it to `pending` and sets `_listora_email_verified=1`.
3. A tampered token shows the friendly "expired" page with a new-link CTA; resend enforces the cooldown.
4. Landing pages are HTML.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Logged-out form redirects to login | guest setting not honoured | submission wizard render guard |
| No verify email | template missing or cron not firing | `Email_Verification` class |
| Tampered link 404 | UX gap | token-validation handler |
| Resend with no cooldown | rate limit | `Email_Verification::resend_verification` |

## Not verified
Steps 9 and 10; 390 and dark layout.

## Cleanup
Delete the QA listing and its email-log rows (by id); restore the two settings.
