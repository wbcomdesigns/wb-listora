# Background-Jobs Standard (portable)

> Plugin-agnostic normative standard (cron + Action Scheduler) for **every Wbcom plugin/theme with
> background work**. Each plugin keeps a synced copy at `docs/standards/background-jobs.md` with its
> own schedule inventory + reference files. Do not re-explain the rules per plugin — link here.
> Reference implementations: BuddyNext (`includes/Core/CronScheduler.php`), Learnomy
> (`includes/class-cron.php` + Pro `class-queue.php`).

## 1. The one principle

**A plugin must be fast by default on a vanilla install with zero environment changes.** Background
work is a selling point, not an afterthought. Never rely on the owner configuring server cron, and
never make a site-wide change on their behalf.

## 2. Decision tree — pick the lightest mechanism that fits (stop at the first that works)

| # | If the work is… | Use | Result |
|---|---|---|---|
| 1 | Derivable on demand | **Lazy compute + cache** (transient + object cache) | No job at all |
| 2 | A response to an event | **Reactive single-shot** (`as_enqueue_async_action`, fallback `wp_schedule_single_event`) | Fires only on the event |
| 3 | Due at a known future time | **Single event armed at that time**, re-armed after it runs | Zero polling |
| 4 | Event-driven but bursty (a queue that drains) | **Self-(un)scheduling recurring** — arm on write, disarm when empty | Dormant when idle |
| 5 | Genuinely always-on (digests, cleanups, reconciles) | **AS recurring**, lowest acceptable cadence | One observable, retrying queue |

**Anti-pattern (delete on sight):** a perpetual sub-hourly recurring poll that runs whether or not
there is work. That is the "heavy plugin" smell.

## 3. AS-first with WP-Cron fallback (the house pattern)

Guard every scheduling path on `function_exists('as_schedule_recurring_action')` /
`as_schedule_single_action` and fall back to `wp_schedule_event` / `wp_schedule_single_event`. Never
assume Action Scheduler is present.

## 4. Timing, groups, deactivation — the three that bite

- **Schedule on `init` / `action_scheduler_init`, NEVER `plugins_loaded`.** AS is not initialised
  until `init`; an `as_schedule_*` call before that silently no-ops — and if you cleared the WP-Cron
  duplicate first, the job ends up unscheduled entirely. Verify scheduling happened.
- **One group per plugin** so everything is observable in Tools → Scheduled Actions and
  bulk-cancelable. If you must use more than one group, the deactivate/uninstall clear-list must name
  **every** group.
- **Deactivation clears BOTH systems for EVERY group** — `wp_clear_scheduled_hook()` for each
  WP-Cron hook AND `as_unschedule_all_actions('', [], $group)` for each AS group. A job that outlives
  its plugin runs against a schema nobody maintains — a fatal on the next tick once the class is gone.

## 5. The cron policy — never force, detect + guide

- **Never** `define('DISABLE_WP_CRON', true)` in plugin code or require it — it disables cron for
  every plugin on the site.
- Action Scheduler off WP-Cron is fine by default; a real system cron is an optional owner-applied
  optimisation, never a requirement for the plugin to be fast.
- **Detect the failure case** (DISABLE_WP_CRON on + overdue AS actions) in a Tools/Site-Health
  surface and show a paste-ready `*/5 * * * * wget … wp-cron.php` line built from the site's own URL —
  only when actually stalled.

## 6. Effectiveness rules

1. One group per plugin. 2. Idempotent guards (`as_next_scheduled_action` before scheduling; handlers
idempotent — AS retries). 3. Batch sweeps with a cursor/watermark; per-item async fan-outs so each
retries independently. 4. Leave AS retention (`actionscheduler_*` pruning) on.

## 7. Per-job checklist

1. Could this be lazy-on-read instead? 2. Does it poll when idle? (must not) 3. Group tag = the
plugin's primary group? 4. Idempotent guard before scheduling? 5. Scheduled on `init` /
`action_scheduler_init`, not `plugins_loaded`? 6. Cleared on deactivate (AS **and** WP-Cron, every
group)? 7. Works with AS absent (WP-Cron fallback)? 8. Zero dependency on `DISABLE_WP_CRON` or
site-specific config?
9. Registered with the plugin family's ONE scheduler (§8), not armed by its own code? 10. If it
writes a log/history table, does that table prune itself (§9)?

## 8. One scheduler per plugin family — reuse it, never add a second

Each plugin family (a Free plugin and its Pro add-on) has exactly one class that arms recurring jobs,
in the FREE plugin: a job list (hook => recurrence) plus a filter add-ons register on. It does the
AS-first/WP-Cron choice, the idempotent any-group check, clears the WP-Cron copy on switch-over, runs
only on scheduling requests (cron, wp-admin, WP-CLI — never a visitor's page view), and is cleared on
deactivation. Pro registers through the filter and lists its hooks so its own deactivation clears
them through the same class. Pro always requires Free, so Pro never grows its own copy; if the
scheduler cannot do something, extend it in Free.

Enforce it with an architecture gate in both plugins: only the scheduler file may call
`as_schedule_recurring_action()`, `as_schedule_cron_action()` or `wp_schedule_event()`. A written rule
alone does not survive the next developer. Before adding any job, helper, schedule key or AS group,
look in the manifest for the one that exists.

Why: Learnomy Pro built a parallel scheduler that queried Action Scheduler for each of its jobs on
every page view (25 AS queries per front-end page, 0 after consolidating) and left its jobs running
after deactivation, while Free's own core jobs were WP-Cron only and silently stopped on a host with
DISABLE_WP_CRON (Basecamp 10393706291, 2026-10-11). Reference: Learnomy `includes/class-cron.php`
(`scheduled_events()`, `learnomy_cron_jobs`, `unschedule()`), Pro `Queue::schedule_recurring()`,
invariant J1.

## 9. Every log and history table cleans up after itself

A table that grows with activity (logs, deliveries, webhook events, attempts, usage) has a bounded,
batched retention prune on a registered job, or a schema docblock saying why it grows forever (a
ledger). Prune concluded rows only — failed, pending and open rows are never pruned by age. The owner
sees the window and the tables it covers in a retention setting whose label names every table it
deletes from. Queued jobs count: when a row a scheduled job points at is deleted, cancel the job in
the same call, and let daily housekeeping sweep any that were missed. Both live as two helpers in
the family's scheduler (Learnomy: `Cron::cancel_pending_for()`, `Cron::cancel_orphan_jobs()`), not as
a copy per extension. Learnomy had 90 orphan dunning retries and orphan webhook deliveries from
exactly this gap.
