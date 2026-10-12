# Customer Journeys — verification framework

## Why journeys, not unit tests

A PHPUnit test that mocks a controller method's response shape passes even if the JS that consumes it never actually wires the redirect. It tests **the unit**, not **the user's reality**.

A journey is a contract that says: _"As a logged-in user named X, on resource Y, when I do Z, I should land in state Q within 3 seconds."_ Passing means the whole stack works — REST + JS + DOM + DB write — for an actual customer.

Journeys also cost less than the equivalent test suite. Each journey is a single self-contained markdown file. A cheap LLM agent can execute it end-to-end via Playwright + curl + MySQL, returning PASS/FAIL with the exact failure point. Re-running 30 journeys per release is cheaper than maintaining 200 unit tests.

## Schema

Every journey follows `.template.md` exactly: the same frontmatter keys and the same sections in the same order.

Frontmatter (all required): `journey` (the slug: the behaviour, no card id, date or version), `plugin`, `priority`, `roles`, `covers` (behaviour tags and `bc-<card id>`), `prerequisites`, `estimated_runtime_minutes`, `last_verified`, `verified_against`, `viewports`, `themes`, `supersedes`, `site_profile: required`. Optional: `fails_on`, `extends`, `requires`.

Sections, in this order: `# <behaviour as a sentence>`, `## Why`, `## Setup`, `## Steps` (numbered; each step has Action, Expect with one named outcome, Read-back), `## Role ladder`, `## Pass criteria`, `## Fail diagnostics`, `## Not verified`, `## Cleanup`.

## How an agent executes a journey

A journey-aware agent (today: a `general-purpose` agent with Playwright MCP + curl + mysql_query MCP loaded; tomorrow: a `wppqa_run_journeys` MCP tool) reads the file, then for each step:

1. **Action** — typically a `playwright_navigate` / `playwright_click` / `curl -X` / `mysql_query` call.
2. **Expectation** — assertion on the resulting state (DOM contains text, REST returns shape, DB row updated).
3. **On match → next step.** On mismatch → record actual vs expected + step number + suspected file → exit FAIL.

Output goes to `audit/journey-runs/{YYYY-MM-DD-HHMM}/{journey-slug}.json`:

```json
{
  "journey": "<slug>",
  "started_at": "2026-04-30T18:55:00Z",
  "site": "<url>",
  "outcome": "PASS | FAIL",
  "duration_seconds": 47,
  "steps": [
    { "n": 1, "action": "...", "outcome": "PASS" }
  ]
}
```

When `outcome: FAIL`, include `failure_step`, `expected`, `actual`, and `likely_files`.

## Directory layout

```
docs/qa/journeys/
├── README.md        (this file)
├── .template.md     the uniform template; copy it for a new journey
├── customer/        member and guest flows (87)
├── admin/           wp-admin flows: owner, editor, moderator (48)
└── system/          REST, cron, CLI, uninstall, security, theme matrix (40)
```

There is no `regression/` folder. A regression guard is a numbered step, with its `fails_on` sha, inside the journey that owns the behaviour.

## One journey per behaviour

- Each behaviour has exactly one owner journey. Before writing a journey or a step, find the behaviour in `wb-listora/docs/qa/coverage/<area>.md` (one row per behaviour: owner journey, surfaces, roles, viewports, gaps). If the row exists, add the step there. If not, add the row and the journey together.
- A behaviour lives in the repo whose code owns it. A Pro extension of a Free flow says `extends: <free journey>` and does not restate the Free steps.
- Never copy a setup block or a step into a second journey. Refer to the fixture or to the owning journey.
- `supersedes:` in the frontmatter lists the old files merged into a journey, so an old card or commit reference can still be traced.
- A step that documents a known defect asserts the correct behaviour and is marked `expected-fail: <card>`. It is never written as "observed gap".

## Self-growth contract

QA self-grows. Every commit that changes customer behavior MUST add to journeys in the same PR. See `audit/qa-index.json#/maintenance_loop` for the canonical rules. The CLAUDE.md "QA Pipeline" section mirrors them.

| Trigger | Required journey addition |
|---|---|
| Customer-visible bug fix | New regression journey at `regression/<descriptor>.md` |
| New customer feature | New `customer/<NN>-<slug>.md` (happy path + 1 negative test) |
| New admin page or workflow | New `admin/<NN>-<slug>.md` |
| New REST endpoint | Either extend an existing journey OR new journey with 1 happy + 1 unauth + 1 invalid-input |
| Two clean releases of a regression journey | Graduate to `customer/` or `admin/` |

## When to write a new journey

Add one when:
- A new customer-facing feature lands (one journey per feature)
- A bug is fixed that wasn't journey-covered (the journey becomes the regression sentinel)
- A REST/AJAX endpoint family changes shape (the journey re-locks the contract)

Don't add one for:
- Internal refactors with no user-visible change (the journey can't tell)
- Performance optimizations (use Lighthouse instead)
- One-off admin scripts run from CLI (use `wp` command tests)

## How journeys integrate with `bin/local-ci.sh`

Stage 4.1 of local-CI runs `bin/run-journeys.sh` against the configured site. Skipped automatically when the site isn't reachable, so the gate works on a fresh clone before WordPress is even installed. To force-run on a non-default site:

```
bash bin/local-ci.sh --site http://staging.local
```

To skip journeys (useful for headless CI without a browser):

```
bash bin/local-ci.sh --no-journeys
```

---

## Free-only mode contract

The plugin is **upscale model**: Free is mandatory, Pro extends Free, Pro never stands alone. But Free MUST run standalone — a customer with only the Free plugin installed must have full functional coverage.

### Free-only smoke coverage (as of 2026-05-21)

```
Total Free-tree journeys:   68
Free-only safe:             63   (run cleanly without Pro)
Pro OPTIONAL (one step):     1   (system/spam-protection-layers.md — Pro-only audit log step)
Hard-require Pro:            4   (regression sentinels for Pro's consumption of Free hooks)
```

### Hard-require Pro (intentional — see why each one belongs in Free's tree)

| Journey | Why it's in Free's tree but needs Pro |
|---|---|
| `admin/17-verification-badge.md` | Verification IS a Pro feature, but the journey tests Free's `wb_listora_is_verified()` resolver hooks (6 read sites) which only matter when Pro can answer the filter to disable. |
| `system/cron-schedule-and-maintenance.md` | Tests Free's `Cron_Scheduler::has_action_scheduler()` gate AGAINST Pro's license cron — the readiness check must work for both Free and Pro recurring jobs. |
| `system/migration-listener-context.md` | Tests that the `wb_listora_listing_submitted` action's `$context['source']='migration'` arg silences Pro listeners (Notifications, BP integration). Without Pro, there are no listeners to silence — journey is moot. |
| `system/term-helper-consolidation.md` | Tests that Pro's visual importer consumes Free's `Term_Helper` via the canonical namespace. Without Pro there's no consumer to test. |

### Documented features → journey coverage (Free-only mode)

100% of Free-or-shared documented features (32 of 32) have at least one journey that runs cleanly without Pro. Pro-only features (Comparison, Quick View, SEO Pages, Saved Searches, Advanced Search, Lead Forms, Analytics, Verification, Coupons, Badges, Audit Log, Needs Marketplace, BuddyPress Integration, Outgoing Webhooks, Payment Webhooks, Multi-Criteria Reviews, Photo Reviews, Digest Notifications, White Label, Coming Soon, Infinite Scroll, Credits, Pricing Plans, Google Maps, Moderators) live in `wb-listora-pro/docs/qa/journeys/`.

### `/wp-plugin-smoke free` mode

Per the global wp-plugin-smoke skill, three modes are supported:
- **`combo`** — Free + Pro active (default; covers all 68 Free + 46 Pro journeys = 114 total)
- **`free`** — Free-only (covers the 64 non-Pro-required Free journeys: 63 clean + 1 with optional steps)
- **`single`** — alias of free for plugins without a Pro pair

The smoke skill reads `docs/qa/qa-config.json` to know which mode to dispatch.

## Journey rules v2 (mandatory for every new or edited journey)

Audit of 175 shipped bugs found only ~3% would have been caught by the older journeys: they checked the happy path as admin in UTC at 1440px. Every journey now meets these rules.

1. **Role ladder.** Run the surface as owner, another member, guest, editor and admin. Admin is the control rung, never the only one.
2. **One named outcome per setting state.** No "A or B" expectations. Iterate every value of a setting and assert its own effect, not only that it saved.
3. **Boundary and adversarial input per field.** Empty, max length, whitespace only, markup, quotes, emoji, negative, zero, huge, wrong type.
4. **Sibling surfaces.** Run the same fixture through every entry point of the behaviour (block, shortcode, REST, template, email, export).
5. **Viewport and theme matrix.** 1440 and 390, light and dark, BuddyX Pro plus one unbridged theme (Twenty-series).
6. **Read-back with a positive control.** Check DB or REST after every write, and show the check can fail by running it against a known-different value.
7. **Lifecycle seeds.** Cover draft, pending, published, expired, trashed, and the deleted-author case.
8. **Migration step.** Start from data written by the previous version, not only from fresh data.
9. **Retry and parallel step** for every submit, upload and payment: double click, replay, two tabs.
10. **Timezone matrix** for anything dated: UTC, UTC+14, UTC-12, then restore UTC.
11. **Scale seed.** Paging and counts run against a QA-prefixed seed of N published listings, not the ~100 on the playground.
12. **Output-bearing steps** run with N=1, N>1 and N=0, and with a zero-decimal currency.
13. **`fails_on`.** Every regression step names the pre-fix commit or tag where it fails (`fails_on: <sha|tag>`). A step never shown to fail is a sentinel, not a regression guard.
14. **No journey asserts buggy behaviour.** If a step encodes a known bug as expected, fix the step and file the bug.

## Site profiles: journeys never name a site

QA runs on several local sites and domains, so a journey is written for the plugin, not for one site. Everything site-specific lives in a profile, `wb-listora/docs/qa/sites/<site-host>.json` (copy `example.json`; Pro journeys use the same profile because the pair shares one site). Select it with `QA_SITE=<site-host>`; the default is the host of `base_url` in `qa-config.json`.

| Write this | Never this |
|---|---|
| `$SITE_URL`, `$WP_PATH` | `http://wb-listora.local`, an absolute path |
| `{owner}` `{other}` `{unnamed}` `{editor}` `{admin}` `{guest}` | `qa_vendor_01`, `dana_whitfield`, `varundubey`, a user ID |
| `{cat}` `{loc}` `{tag}` `{feature}` `{type}` `{listing}` | a demo slug or post ID |
| a count read in the step (`wp term get ... --field=count`, `wp post list --format=count`, the REST `X-WP-Total`) | `2809 listings`, `38 listings`, `85 credits` |

- `fixtures/2.0-fixtures.php seed` resolves personas from the profile and creates any persona user the site lacks, so it runs on a fresh site. `clean` removes only what it tagged.
- Frontmatter: `site_profile: required` on every journey; `plugin:` names the owning plugin (Free or Pro); Pro-only extensions of a Free flow say `extends: <free journey>`.
- A new site needs one new profile file, never an edit to a journey.
