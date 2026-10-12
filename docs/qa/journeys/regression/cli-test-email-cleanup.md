---
journey: cli-test-email-cleanup
plugin: wb-listora
priority: normal
roles: [cli]
covers: [wp-cli, listora test-email, listora cleanup, C.cli]
prerequisites:
  - "WP-CLI reachable for the site (Local socket: see runbook global preconditions)"
  - "WB Listora active"
estimated_runtime_minutes: 2
covers_card: null
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
changed: true
---

# `wp listora test-email` and `wp listora cleanup` subcommands

Both subcommands are registered in `includes/class-cli-commands.php` and wired to existing code:

- `test-email <template> --to=<email>` calls `Notifications::send_test()`. A delivery failure WARNS (exit 0) so a missing local mail transport does not mask a healthy render; a broken template throws and surfaces as a real fatal.
- `test-email` with no template LISTS the 18 templates, then exits non-zero via `WP_CLI::error` ("Specify a template..."). That is the documented "list" path and a usage error by design.
- `cleanup` runs the daily housekeeping: email-log retention (`Notifications::prune_log()`), the `wb_listora_daily_cleanup` action (analytics retention), the unverified-listing cleanup hook, and the orphaned listing-row purge, then prints "Cleanup complete." and exits 0.

Single-role journey by design: WP-CLI runs as the server user with full authority, there is no end-user role ladder. The surface a site owner sees is the command output.

## Setup

- A shell where `wp` reaches the site. On Local, use the site's bundled wp-cli binary with `--path="<site>/app/public"`.
- Any `--to` address (`qa-j1@example.test`). Delivery may fail locally; the contract tolerates a warning. A local mail catcher (Mailpit) shows the message on success.

## Steps

### 1. `test-email` send path exits 0
- **Action**: `wp listora test-email listing_approved --to=qa-j1@example.test`
- **Expect**: exit 0. Output is EITHER `Success: Sent "listing_approved" to qa-j1@example.test (subject: [TEST] Your listing has been approved: [Test] Sample Listing).` OR a non-fatal `Warning: Template "listing_approved" rendered, but delivery to ... failed...`. Both pass: the template rendered without a fatal.
- **Mailpit** (when the transport is local): one message to that address with the `[TEST]` subject, branded header and footer.
- **Fail**: a PHP fatal / stack trace, or a hard `Error:` from a template that failed to render.

### 2. `cleanup` exits 0 and reports completion
- **Action**: `wp listora cleanup`
- **Expect**: exit 0, output:
  `Running WB Listora cleanup tasks...`, `  Email log retention: N row(s) pruned.`, `  Analytics retention prune: done.`, `  Stale unverified listings: cleaned up.`, `  Orphaned listing rows purged: N.`, `Success: Cleanup complete.`

### 3. `test-email` with no argument lists all 18 templates
- **Action**: `wp listora test-email`
- **Expect**: `Available templates:` then exactly 18 `  - <key>` lines: `listing_submitted`, `listing_approved`, `listing_reported`, `lead_received`, `listing_rejected`, `listing_expired`, `listing_expiring_soon`, `listing_renewed`, `listing_pending_admin`, `review_received`, `review_reply`, `review_helpful`, `claim_submitted`, `claim_approved`, `claim_rejected`, `draft_reminder`, `review_reminder`, `listing_verify_email`; then `Error: Specify a template: wp listora test-email <template> --to=<email>` with exit 1 (by design).

### 4. Unknown template is rejected cleanly
- **Action**: `wp listora test-email not_a_real_template --to=qa-j1@example.test`
- **Expect**: `Error: Unknown template "not_a_real_template". Run \`wp listora test-email\` to list valid templates.` A clean validation error, not a fatal; nothing sent.

### 5. Positive control
- **Control**: step 1 (a valid key sends) is the positive control for step 4 (an invalid key sends nothing). Run step 4 first with a mail catcher open and confirm no new message, then step 1 and confirm exactly one.
- Each of the 18 keys should also pass step 1 once per release: `for k in $(wp listora test-email 2>&1 | awk '/^  - /{print $2}'); do wp listora test-email $k --to=qa-j1@example.test; done`.

### 6. Cleanup of the probe
- Remove the test row from the log if desired: `DELETE FROM {prefix}listora_email_log WHERE recipient = 'qa-j1@example.test'`.

## Pass criteria

1. `test-email <valid> --to=<email>` exits 0 with a Success "Sent" or a delivery Warning.
2. `cleanup` prints "Cleanup complete." and exits 0 with no fatal.
3. `test-email` with no argument lists all 18 template keys, then the usage Error.
4. An unknown template produces a clean `Error:`, not a fatal.
5. Both subcommands appear under `wp help listora`.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| `test-email` "is not a registered subcommand" | `@subcommand test-email` method dropped | `includes/class-cli-commands.php:182` |
| `cleanup` not registered | `cleanup()` method dropped | `includes/class-cli-commands.php:262-267` |
| `test-email` exits non-zero when mail cannot deliver | failure escalated to `WP_CLI::error` | `includes/class-cli-commands.php:246-255` (must be `WP_CLI::warning`) |
| Template count is not 18 | key list drifted from the notification events | `$templates` in `test_email()` (`includes/class-cli-commands.php:190-211`) and `Workflow\Notifications::send_test()` |
| `cleanup` fatals | wiring drift | `Workflow\Notifications::prune_log()`, `wb_listora_daily_cleanup` listeners, `Workflow\Email_Verification::CRON_HOOK`, `Core\Listing_Data_Eraser::purge_orphans()` |

## Verified

Steps 1 (Success path), 2, 3 (18 keys, exit 1), 4 and the `wp help listora` entries were run live on 2026-10-12 with the site's wp-cli; the probe email-log row was deleted. Mailpit receipt was not checked. Verified via: wp-cli.
