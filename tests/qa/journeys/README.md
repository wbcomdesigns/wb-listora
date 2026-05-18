# Customer Journeys — verification framework

## Why journeys, not unit tests

A PHPUnit test that mocks a controller method's response shape passes even if the JS that consumes it never actually wires the redirect. It tests **the unit**, not **the user's reality**.

A journey is a contract that says: _"As a logged-in user named X, on resource Y, when I do Z, I should land in state Q within 3 seconds."_ Passing means the whole stack works — REST + JS + DOM + DB write — for an actual customer.

Journeys also cost less than the equivalent test suite. Each journey is a single self-contained markdown file. A cheap LLM agent can execute it end-to-end via Playwright + curl + MySQL, returning PASS/FAIL with the exact failure point. Re-running 30 journeys per release is cheaper than maintaining 200 unit tests.

## Schema

Each journey is one markdown file with YAML frontmatter:

```yaml
---
journey: <slug-with-dashes>
plugin: wb-listora
priority: critical | high | normal | nice-to-have
roles: [<role-1>, <role-2>, ...]
covers: [<bug-or-feature-tag>]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "<other setup needed>"
estimated_runtime_minutes: 5
---
```

Followed by:

- **## Setup** — initial state needed (autologin URL, IDs, fixtures)
- **## Steps** — numbered, each with: action, expectation, REST/DB checks
- **## Pass criteria** — ALL listed assertions must hold
- **## Fail diagnostics** — for each likely failure, point at the suspected file

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
audit/journeys/
├── README.md                       (this file)
├── customer/                       End-user flows
├── admin/                          Admin flows
├── regression/                     One file per shipped D-row regression
└── system/                         Cron, webhooks, background
```

## Authored journeys (Free) — 29 total

**Customer (10):**
| Slug | Priority | Covers |
|---|---|---|
| browse-and-favourite-a-listing | critical | favorites flow + modal-getter pattern |
| submit-a-listing-wizard-end-to-end | critical | submission wizard, conditional fields |
| write-and-reply-to-a-review | critical | review create + helpful + dashboard reply |
| search-with-filters | critical | search engine, geo, facets, count badge, empty state |
| claim-a-business | critical | claim modal, login gate, listora_claims, hooks |
| listing-renewal-flow | high | renewal REST + reminder cron + expiration filter |
| guest-submission-email-verify | high | logged-out submission + verify token + expired-token UX |
| calendar-block | normal | recurring events + virtual occurrences + nav |
| categories-block | normal | category counts + click-through + filter hook |
| featured-listings-rotation | normal | featured-rotation cron + responsive carousel |

**Admin (10):**
| Slug | Priority | Covers |
|---|---|---|
| admin-approve-pending-listing | critical | status transition + notification email + log |
| admin-moderate-review | critical | reviews REST status enum + moderator cap |
| admin-approve-claim | critical | post_author transfer + is_claimed flag |
| admin-setup-wizard-first-run | critical | wizard headers regression #9867159785 |
| admin-add-listing-from-wp-admin | high | CPT edit + services photo + expiration |
| admin-listing-types-crud | high | type registry + submission wizard + cleanup |
| admin-taxonomy-crud | normal | hierarchical cat/location + flat feature + cap map |
| admin-settings-tabs-merge | high | tabs merge + reset + Pro purge listener |
| admin-health-check | normal | cron + db version + index repair CTAs |
| admin-import-export | normal | CSV/JSON/GeoJSON round-trip integrity |

**Regression sentinels (9):**
| Slug | Priority | Covers |
|---|---|---|
| dashboard-2-col-layout | high | sidebar+main grid (today's regression) |
| empty-state-server-rendered | high | 0-result IAPI getter (today's regression) |
| services-photo-upload | high | services metabox photo column #9872014083 |
| map-fatal | critical | listing-map fatal #9871222447 + popup image #9867372176 |
| empty-media-fieldset | high | submission step suppression #9867347053 |
| overview-company-logo-id | normal | tabs.php file-type skip #9867775853 |
| service-details-toggle | normal | services tab toggle #9872013428 |
| filter-count-dropdowns | normal | badge count dropdowns #9871208081 |
| business-hours-firefox | high | flatpickr round-2 #9856828615 (Firefox manual) |

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
