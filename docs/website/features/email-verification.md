# Email Verification

Built into WB Listora **Free**.

Token-gated verification for **guest-submission** flows. When a visitor submits a listing without being logged in, the listing stays in a draft-equivalent state until they prove ownership of the email address they used — by clicking a unique link in the verification email. Stops anonymous spam without forcing every visitor to register.

![Email Verification — verification email entry in the admin Email Log showing token-link delivery](../images/email-log-page.png)

## What it is

A token-and-cron pipeline that runs around the guest-submission flow:

- **Token generation.** On guest submission, the system creates a 64-char hex token, stores it as `_listora_verify_token` post-meta, sets `_listora_verify_expires_at` 7 days out, and emails the verification link to the submitter.
- **Verification.** Visitor clicks `/?listora-verify=1&listing=<id>&token=<token>`. The `template_redirect` handler matches the token, sets `_listora_verified_at` to now, deletes the token meta, and transitions the listing through Listora's normal moderation flow (auto-publish or queue depending on your Submissions settings).
- **Resend (rate-limited).** If the email got lost, the submitter can request a fresh token from a "Resend verification" link. New tokens are gated by a 5-minute cooldown to prevent abuse.
- **Auto-cleanup.** A daily cron (`wb_listora_cleanup_unverified_listings`) sweeps up listings still in the unverified state after 7 days. They get hard-deleted along with any attached media — the assumption is that 7 days is plenty for a real submitter to click the link.

The flow is invisible to admins doing manual submissions (they're already logged in, so verification skips). It only applies to the guest path.

## Settings & options

The feature is gated by **Settings → Submissions → Allow guest submissions** — turn that off and email verification doesn't run because guests can't submit in the first place. Tune the verification timing via filters; no admin UI for it.

| Setting | Default | Where |
|---|---|---|
| Guest submissions allowed | On | Settings → Submissions |
| Token expiration window | 7 days | `wb_listora_verify_token_ttl` filter (seconds) |
| Resend cooldown | 300 seconds | `Email_Verification::RESEND_COOLDOWN` constant |
| Unverified cleanup window | 7 days | `wb_listora_unverified_listing_max_age` filter (seconds) |
| Cleanup cron schedule | Daily | `wb_listora_cleanup_unverified_listings` action |

## How you use it

### As a visitor (guest submitter)

1. **Submit a listing** from `/add-listing/` without being logged in.
2. **Enter your email** in the wizard's contact step.
3. Wizard shows "**Check your email** — we sent a verification link to {email}. Click it to publish your listing."
4. **Open the email** within 7 days. Click the verify link.
5. **Listing transitions** to whatever your site's Submissions settings dictate — either straight to `publish` (auto-approve) or to `pending` for admin review.

If the email got lost, scroll to the bottom of the same screen and click **Resend verification** — limited to one fresh email per 5 minutes per listing.

### As an admin debugging a stuck guest submission

1. Open **WP Admin → Listings** and filter by status `listora_pending_verification` (or whatever the unverified state is).
2. Find the row. The `_listora_verify_token` post-meta confirms a token exists.
3. Open the **Email Log** to confirm the verification email actually sent.
4. If sent → the visitor never clicked. Either ping them out-of-band or wait for the cleanup cron to drop the abandoned listing.
5. If never sent → the issue is in your mail stack. Try **Settings → Notifications → Send Test Email** with the same recipient to confirm.

### Manual override (bypass verification for a trusted submitter)

```bash
wp post meta delete <listing_id> _listora_verify_token
wp post meta delete <listing_id> _listora_verify_expires_at
wp post meta update <listing_id> _listora_verified_at "$(date '+%Y-%m-%d %H:%M:%S')"
wp post update <listing_id> --post_status=publish
```

## How it interacts with the rest of the system

- **Settings → Submissions** controls whether guest submissions are allowed in the first place. Turn this off and email verification never runs.
- **Settings → Notifications** has a `listing_verify_email` event toggle. Off → no verification email sent, which effectively breaks the guest flow. Keep on.
- **Anti-spam pipeline** runs BEFORE token generation — honeypot, Akismet, blacklist, URL density. A spammy submission never gets a verification email; the verification gate is the SECOND defence layer.
- **Cleanup cron** runs daily via [Action Scheduler](../developer-guide/rest-api.md) (or WP-Cron fallback). Verify it's queued via **WP Admin → Tools → Scheduled Actions** if cleanup seems stuck.
- **REST mirror** at `GET /listora/v1/submission/verify` (token in query string) gives headless / mobile clients the same verify path the public URL handler uses.
- **Resend REST** at `POST /listora/v1/submission/resend-verification` accepts the listing ID + the submitter's email (must match the stored value).

## Email template

The verification email uses the `listing_verify_email` template. Override per [Email Templates](email-templates.md) — copy `wp-content/plugins/wb-listora/templates/emails/listing_verify_email.php` to `{your-theme}/wb-listora/emails/listing_verify_email.php` and edit. Template variables:

- `{listing_title}` — the listing title the submitter entered.
- `{verify_url}` — the full `?listora-verify=...` link.
- `{expires_in}` — human-readable expiration window ("7 days").
- `{site_name}`, `{site_url}` — standard.

## Permissions

| Capability | Who has it | What it gates |
|---|---|---|
| (anonymous) | Anyone | Click a valid `?listora-verify=` link |
| `manage_listora_settings` | Administrator | Manually clear / set verification meta via the Listings admin |

The verify URL is unauthenticated by design — anyone with a valid token can complete verification. The token itself is the auth.

## Related

- [Frontend Submission](frontend-submission.md) — the wizard that triggers verification on guest submissions.
- [Spam Protection](spam-protection.md) — the layer that runs BEFORE verification email send.
- [Email Log](email-log.md) — trace whether verification emails actually delivered.
- [Email Templates](email-templates.md) — customize the verification email body.
- [Notifications Settings](../settings/notifications-settings.md) — toggle the `listing_verify_email` event.
- [REST API](../developer-guide/rest-api.md) — `/submission/verify` + `/submission/resend-verification` endpoints.
