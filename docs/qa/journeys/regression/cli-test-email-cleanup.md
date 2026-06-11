---
journey: cli-test-email-cleanup
plugin: wb-listora
priority: normal
roles: [cli]
covers: [43ded68, wp-cli, listora test-email, listora cleanup, C.cli]
prerequisites:
  - "WP-CLI reachable for the site (Local socket: see runbook global preconditions)"
  - "WB Listora active"
estimated_runtime_minutes: 2
covers_card: null
---

# `wp listora test-email` + `wp listora cleanup` subcommands (43ded68 — new feature)

The smoke runbook (C.cli + C.notifications) documents
`wp listora test-email <template> --to=<email>` and `wp listora cleanup`, but
neither was registered. Commit 43ded68 implements both in
`includes/class-cli-commands.php`, wired to existing code (no reimplementation):

- `test-email <template>` calls `Notifications::send_test()` over the 15 known
  event keys. A delivery failure WARNS (non-fatal exit 0) rather than hard-fails,
  so a missing local mail transport doesn't mask an otherwise-healthy render
  (a broken template throws inside `send_test()` and surfaces as a real fatal).
- `test-email` with no template arg LISTS the 15 templates, then exits via
  `WP_CLI::error` ("Specify a template…") — this is the documented "list" path
  and intentionally returns a non-zero exit (it's a usage error, not a send).
- `cleanup` runs `Notifications::prune_log()` plus the `wb_listora_daily_cleanup`
  and `wb_listora_cleanup_unverified_listings` cron hooks via `do_action`, then
  prints "Cleanup complete." and exits 0.

This is a NEW feature → also recorded in `audit/manifest.json` wp-cli
subcommands (`test-email`, `cleanup`) and the wp-cli count in
`audit/manifest.summary.json`.

## Setup

- Use the site's WP-CLI invocation (Local sites need the mysql socket flag —
  see `AGENT_SMOKE_RUNBOOK.md` global preconditions).
- Pick any deliverable address for `--to` (delivery may not succeed locally;
  the contract tolerates a delivery WARNING).

## Steps

### 1. `test-email` send path exits 0
- **Action**: `wp listora test-email listing_approved --to=<email>`
- **Expect**: exit code `0`. Output is EITHER
  `Success: Sent "listing_approved" to <email> (subject: …)`
  OR a non-fatal `Warning: Template "listing_approved" rendered, but delivery to
  <email> failed…`. Both are acceptable — the template rendered without a fatal.
- **On fail**: a PHP fatal / stack trace, or a hard `Error:` from a template
  that failed to render.

### 2. `cleanup` exits 0 and reports completion
- **Action**: `wp listora cleanup`
- **Expect**: exit code `0`; output ends with `Success: Cleanup complete.` and
  includes the three task lines (email-log retention, analytics retention prune,
  stale unverified listings). No fatal, no uncaught warning.

### 3. `test-email` with no arg lists all 15 templates
- **Action**: `wp listora test-email`
- **Expect**: prints `Available templates:` followed by exactly 15 `  - <key>`
  lines (`listing_submitted`, `listing_approved`, `listing_rejected`,
  `listing_expired`, `listing_expiring_soon`, `listing_renewed`,
  `listing_pending_admin`, `review_received`, `review_reply`, `review_helpful`,
  `claim_submitted`, `claim_approved`, `claim_rejected`, `draft_reminder`,
  `listing_verify_email`), then the usage `Error: Specify a template…`.
- **Note**: this listing path exits NON-ZERO by design (it's a `WP_CLI::error`
  usage prompt, not a send). The journey asserts the 15-line list + the prompt,
  not a zero exit, for this step.

### 4. Unknown template is rejected cleanly
- **Action**: `wp listora test-email not_a_real_template --to=<email>`
- **Expect**: `Error: Unknown template "not_a_real_template"…` — a clean
  validation error, not a fatal.

## Pass criteria

1. `test-email <valid> --to=<email>` exits 0 with a Success "Sent" OR a delivery WARNING
2. `cleanup` prints "Cleanup complete." and exits 0 with no fatal
3. `test-email` (no arg) lists all 15 template keys
4. An unknown template name produces a clean validation `Error:`, not a fatal
5. Both subcommands are registered (they appear under `wp listora` help)

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| `wp listora test-email` → "is not a registered subcommand" | regression of 43ded68 — `@subcommand test-email` method dropped | `includes/class-cli-commands.php` `test_email()` |
| `wp listora cleanup` → not a registered subcommand | `cleanup()` method dropped | `includes/class-cli-commands.php` `cleanup()` |
| `test-email` hard-fails (exit ≠ 0) when local mail can't deliver | delivery failure escalated to `WP_CLI::error` instead of `WP_CLI::warning` | same file — the no-`sent` branch must `WP_CLI::warning`, not error |
| Template count ≠ 15 | template key list drifted from `Notifications::send_test()` | keep `$templates` in `test_email()` in sync with the Notifications event keys |
| `cleanup` fatals on `prune_log` / cron hook | wiring drift | `Workflow\Notifications::prune_log()`, `wb_listora_daily_cleanup`, `Workflow\Email_Verification::CRON_HOOK` listeners |
