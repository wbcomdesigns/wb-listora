---
journey: guest-submission-email-verify
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [guest-submission-flow, email-verification, expired-token-ux]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Setting: Submission → Allow guest submissions = ON"
  - "Setting: Submission → Require email verification = ON"
estimated_runtime_minutes: 5
---

# Anonymous visitor submits a listing with email verification

Logged-out visitor walks the submission wizard, submits, receives a verification email, clicks the link, listing transitions from `pending_verification` → `pending`. Tests the expired-token UX with a tampered link.

## Setup

- Site: `$SITE_URL`
- Confirm settings:
  ```bash
  wp option patch update wb_listora_settings allow_guest_submissions 1
  wp option patch update wb_listora_settings require_email_verification 1
  ```

## Steps

### 1. Open Add Listing logged-out
- **Action**: `playwright_navigate $SITE_URL/add-listing/`
- **Expect**: wizard renders. NO redirect to wp-login.

### 2. Walk wizard with an anonymous email
- **Action**: pick Business → fill basic info with email = `smoke-guest@test.local` → continue → fill required Details → submit
- **Expect**:
  - Final step shows "Check your email to verify"
  - Listing exists with `post_status='pending_verification'`

### 3. Verify email log
- **Action**:
  ```sql
  SELECT template, recipient FROM wp_listora_email_log
  WHERE template='listing-verify-email' AND recipient='smoke-guest@test.local'
  ORDER BY created_at DESC LIMIT 1;
  ```
- **Expect**: 1 row recent. Capture verification URL from email body or DB.

### 4. Click verification link
- **Action**: extract the verification token URL (e.g. `/?listora_verify_email=<token>`) and navigate to it
- **Expect**:
  - Success page or redirect with "Email verified" message
  - Listing transitions to `post_status='pending'`
  - `_listora_email_verified=1` postmeta set

### 5. Verify listing in admin pending queue
- **Action**:
  ```bash
  wp post list --post_type=listora_listing --post_status=pending --format=csv
  ```
- **Expect**: the new listing visible

### 6. Test expired-token UX
- **Action**: tamper with the URL — change a few chars in the token, navigate
- **Expect**:
  - Clear UX page: "This verification link has expired or is invalid. [Request a new link]"
  - NOT a generic 404
- **On fail**: token-validation handler returns 404 instead of friendly UX

### 7. Request new link from the UX
- **Action**: click "Request a new link" → enter email → submit
- **Expect**:
  - `POST /wp-json/listora/v1/submission/resend-verification` returns 200
  - New email log row appears
  - Per-listing 5-min cooldown enforced (per F-02)

### 8. Re-verify with new link
- **Action**: extract new token URL, navigate
- **Expect**: verification succeeds, listing → `pending`

## Pass criteria

1. Logged-out submission produces `pending_verification` listing
2. Verification email reaches the submitter's address
3. Clicking the link transitions to `pending` + sets `_listora_email_verified=1`
4. Tampered token shows clear "expired" UX with "Request new link" CTA
5. Resend endpoint enforces cooldown
6. Re-verification with new link works

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Logged-out form redirects to login | guest submissions setting not honored | submission wizard render guard |
| No verify email | template missing or cron not firing | `Email_Verification` class |
| Tampered link → 404 | UX gap | token-validation handler must return friendly page |
| Resend with no cooldown | rate limit broken | `Email_Verification::resend_verification` cooldown |
