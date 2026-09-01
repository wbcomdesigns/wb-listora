# Listora Documentation Coverage — 1.7.0 Assessment

Assessment only. No `docs/website/` scaffolding was created or edited. All counts below are
grepped directly from source (excluding `node_modules/`, `build/`, `dist/`, `vendor/`, `libs/`,
`tests/`, `docs/qa/`) on 2026-08-31/09-01, working tree on branch `1.7.0`. Where a number is
cross-checked against the plugin's own `CLAUDE.md` manifest mirror (last refreshed against
released 1.6.0), both numbers are given — small deltas are expected because the manifest counts
*call sites* in places where this audit counts *unique names*, and because the working tree has
moved past 1.6.0.

## 0. Correction to the task briefing — read this first

The brief that kicked off this assessment said "outside `docs/qa/`, the real docs are: free has
`docs/CONTRIBUTING.md` and `docs/architecture/CSS-ARCHITECTURE.md`; Pro has nothing," and that
neither plugin has the `docs/website/` tree the Wbcom portfolio standard specifies. **That premise
is wrong for Free and only half-right for Pro, and the reason changes what "coverage gap" means
here:**

- **Free already has a full `docs/website/` tree** — 195 files: `getting-started/` (6),
  `settings/` (9), `developer-guide/` (7), `features/` (52 pages + `blocks-overview.md`),
  `user-journeys/` (7), `marketing/` (~35), plus `docs_config.json` and `images/` (60+ PNGs). Most
  recent commit touching it: 2026-08-22. This is not a stub — `hooks-reference.md` alone is 381
  lines / 36KB and `rest-api.md` is 366 lines.
- **Pro has no `docs/website/` by explicit, documented decision**, not by omission. Pro's own
  `CLAUDE.md` states: *"No customer `docs/` in Pro — standing decision, do not revisit... Free's
  `docs/website/` is the single documentation set and it covers Free and Pro features together,
  because that is how a customer meets the product... Splitting it per repo would make a Pro
  customer read two sites to answer one question."* Free is a hard dependency of Pro
  (`Requires Plugins: wb-listora`), so this is a deliberate one-site model, not a gap to close by
  creating a second tree.

**What this means for scope:** the real question for 1.7.0 is not "build `docs/website/` from
scratch" — it exists and is substantial. The real question is **how completely does Free's
existing `docs/website/` cover Pro's surface**, since that's the whole point of the one-site
design. The answer, measured below, is: customer-facing feature coverage is good; developer-facing
Pro coverage is close to the "near zero" the brief expected — just concentrated on Pro's hooks and
helper functions rather than spread across both plugins.

## 1. Developer-facing inventory

**Line-drawing rule:** a hook counts as "third-party-hookable" if it fires under the product's own
namespace (`wb_listora_*`, `wb_listora_pro_*`, `wbcom_credits_*`). Excluded: hooks the plugin fires
only inside `tests/` to replay core WP lifecycle for PHPUnit (`init`, `rest_api_init`), and calls
where the plugin is *consuming* an existing WP/third-party filter rather than defining a new one
(`the_content`, `wp_privacy_personal_data_erasers`, `wpml_object_id`, `https_ssl_verify`,
`phpmailer_init`, `after_setup_theme`, `edd_sl_sdk_*`, `buddynext_app_connect_schemes`) — those
are not extension points this plugin owns.

| Category | Free | Pro | Documented (Free / Pro) |
|---|---|---|---|
| `do_action()` / `apply_filters()`, unique names, product-owned | **323** (155 actions + 168 filters) — CLAUDE.md manifest: 331 (151+180) at 1.6.0 | **214** (86 actions + 128 filters) — CLAUDE.md manifest: 254 at 1.6.0 | **214/323 (66%)** in `hooks-reference.md` / **6/214 (2.8%)** in the same file |
| `register_rest_route()` call sites | 72 (manifest: 63 unique routes) | 75 (manifest: 73 call sites / 70 unique routes) | Category-level: all 10 Free resource groups have a section in `docs/REST-API.md` + `docs/website/developer-guide/rest-api.md`; all 7 Pro groups (Credits & plans, Webhooks, Needs, Coupons, Badges, Imports, Lead form) have a section too. **Per-route method/args detail not verified against all 147 call sites** — spot checks below found the doc format is prose-by-resource, not one entry per route. |
| REST controller classes | 14 (+1 non-controller helper, `class-listing-write-guards.php`) | 8 | — |
| CPTs | 1 (`listora_listing`) | 5 (`listora_plan`, `listora_coupon`, `listora_webhook`, `listora_badge`, `listora_need`) | 0/1 dedicated CPT reference page (mentioned in prose only) / 0/5 |
| Taxonomies | 6 (verified: `register_taxonomy()` call count matches) | 0 | Named in `CLAUDE.md`, not in `docs/website/` as a dedicated reference |
| Custom capabilities | 15 (per manifest) | 2 (per manifest) | `developer-guide/capabilities.md` exists (175 lines, dated 27 Jul — **not verified against the current 15/2, flag for a pre-1.7.0 spot check**) |
| Custom DB tables | 12 `CREATE TABLE` statements in `class-activator.php` (CLAUDE.md names 11) | 7 (per manifest) | No dedicated schema reference page in either plugin |
| WP-CLI commands | 1 command / 11 subcommands (per manifest) | 1 command | `developer-guide/wp-cli-commands.md` exists, 193 lines, dated 20 Aug — recent, not independently re-verified subcommand-by-subcommand |
| Public helper functions (`wb_listora_*` / `wb_listora_pro_*`, top-level `function_exists()`-guarded) | **103** | **12** | **15/103 (15%)** mentioned anywhere in `docs/` / **1/12 (8%)** |

**Biggest developer-facing gap, concretely:** 109 Free hooks + 208 Pro hooks (317 total) and 88
Free + 11 Pro helper functions (99 total) exist in source with zero mention in any prose doc. This
is the real "near zero" the brief expected — it's just concentrated in two categories (hooks,
helpers) and skewed toward Pro, not spread evenly across every category and both plugins.

## 2. Customer-facing inventory

| Surface | Free | Pro | Documented |
|---|---|---|---|
| Admin settings tabs | 10 (General, Features, Maps, Submissions, Reviews, Credits, Notifications, Advanced, Import/Export, Migration) | 18 settings, 11 admin pages (per manifest; folded into the same settings UI, not separate pages) | 9 of `docs/website/settings/*.md` exist (general, features-toggles, map, submission, reviews, notifications, advanced, import-export, search). **Missing: Credits tab, Migration tab.** No Pro-specific settings walkthrough anywhere. |
| Blocks | 11 (search, grid, card, map, categories, featured, calendar, detail, reviews, submission, user-dashboard) | 5 (comparison, credit-purchase, moderator-queue, needs-grid, post-need) | `features/blocks-overview.md` covers all 11 Free blocks at a high level. Pro's 5 blocks are not individually documented as blocks, though their underlying features (comparison, credits, moderation, needs) each have a `features/*.md` page. |
| Shortcodes | 0 | 1 (`listora_compare`) | Not documented as a shortcode anywhere (only reachable via the Comparison feature page, which doesn't mention the shortcode tag). |
| Feature-Manager toggles (`class-feature-manager.php`) | N/A (no feature manager in Free) | 27 registered features (~20 have a distinct settings toggle per manifest; 7 are infra features with no toggle by design) | **Strong**: cross-referencing the 27 feature keys against `docs/website/features/*.md` (52 files) finds a matching page for essentially every toggle by name (advanced-search, analytics, audit-log, buddypress-integration, coming-soon, comparison→compare-listings, coupons, notification_digest→digest-notifications, google-maps, infinite-scroll, lead_form→lead-forms, moderator→moderators, multi-criteria, reverse_listings→needs-marketplace, outgoing-webhooks, photo-reviews, quick-view, seo-pages, verification→verification-badges, white-label, credit_system/pricing_plans→credits-and-plans/pricing-plans). A few (`field_mapper`, `google_places`, `services_pro`, `badges` as distinct from `verification`) don't have an obviously 1:1 page name — worth a manual pass, not a rebuild. |
| Setup wizards | 1 (`class-setup-wizard.php`) | 1 (`class-setup-wizard.php`, extends Free's) | `getting-started/setup-wizard.md` exists and covers this. |

**Customer-facing verdict: this is the part that's actually in good shape.** 52 feature pages, 9
settings pages, 6 getting-started pages, 7 user-journey pages already exist and, by spot-checking
feature names against `class-feature-manager.php`, cover nearly all of Pro's toggles even though
Pro has no dedicated tree — the one-site design is working as intended on the customer side. The
gaps are narrow: 2 missing settings tabs (Credits, Migration), no shortcode reference, no
block-by-block reference for Pro's 5 blocks.

## 3. Existing prose accuracy — grepped against source, not against each other

### `docs/CONTRIBUTING.md` — four false/stale claims found

1. **"Pull requests are automatically checked by GitHub Actions"** (CI Pipeline section) — **FALSE**.
   `ls .github/workflows` returns nothing; no workflows directory exists. The plugin's own
   `CLAUDE.md` states outright: *"GitHub Actions CI was retired in 1.0.4 — `composer ci`
   (local-CI pre-push hook) is the single quality gate."* This is actively misleading to a new
   contributor deciding whether local checks matter.
2. **"REST: `includes/rest/` -- 9 controllers, 36+ endpoints under `listora/v1`"** — **stale**.
   `ls includes/rest/*.php` = 15 files (14 real controllers + 1 write-guards helper), and
   `register_rest_route()` appears 72 times in Free's source (63 unique routes per the 1.6.0
   manifest). The doc undercounts controllers by ~55% and endpoints by ~45-75%.
3. **"Database: 10 custom tables prefixed `listora_`"** — **stale**. `class-activator.php` contains
   12 `CREATE TABLE` statements; `CLAUDE.md` itself names 11 tables (`geo`, `search_index`,
   `field_index`, `reviews`, `review_votes`, `favorites`, `claims`, `hours`, `analytics`,
   `payments`, `services`). 10 is wrong under either count.
4. **"See `docs/ARCHITECTURE.md` for the full architecture reference"** — **dead link**.
   `docs/ARCHITECTURE.md` does not exist anywhere in the repo. The real architecture material is
   under `audit/` (`FEATURE_AUDIT.md`, `CODE_FLOWS.md`, `architecture/`).

Lower-confidence flag (not verified deeply): the Branch Workflow section describes short-lived
`feature/*` branches PR'd and squash-merged into `main`. The working tree is currently checked out
on a branch literally named `1.7.0`, consistent with this user's own recorded convention that
plugin work happens on one version branch at a time rather than main. Worth a maintainer
confirmation, not asserted as false here since it wasn't checked against merge history.

### `docs/architecture/CSS-ARCHITECTURE.md` — accurate on spot check

Checked the naming this file depends on (`src/tokens/` → `src/variables/`, `src/primitives/` →
`src/components/`, the 2026-05-21 rename) against the actual `src/` directory listing
(`admin, blocks, components, editor, interactivity, shared, utils, variables`). The doc's table
rows for Variables/Components paths match current source exactly. No false claims found in this
file on the sections checked (build pipeline table, layer cascade). Not exhaustively verified line
by line.

### Root `docs/REST-API.md` vs `docs/website/developer-guide/rest-api.md` — undetected duplication risk

These are two separately-maintained files covering the same ground (361 vs 366 lines, both
structured by resource group, both listing the same 10 Free + 7 Pro categories) with no
cross-reference between them. Neither was found to contradict the other in a spot check, but this
is exactly the shape of thing that drifts silently — one gets updated for a new route, the other
doesn't, and nothing catches it. Recommend consolidating to one before it does.

## 4. Sizing — `docs/website/` sections, against what already exists

| Section | Exists now | Gap | Generatable vs authored |
|---|---|---|---|
| `getting-started/` | 6 pages | Adequate for 1.7.0 as-is | n/a |
| `settings/` | 9 pages | 2 pages (Credits tab, Migration tab) | Authored — needs screenshots + walkthrough prose, same pattern as the other 9 |
| `developer-guide/hooks-reference.md` | 381 lines, 66% of Free's hooks | 109 Free + 208 Pro hook entries missing (317 total) | **Mostly generatable.** Name, file:line, fires-when, and args can be scripted straight from the `do_action`/`apply_filters` call sites this audit already extracted; only the 1-2 sentence "why you'd hook this" needs a human pass. Estimate: 1-2 days scripted extraction + review, not a from-scratch write. |
| `developer-guide/rest-api.md` (+ duplicate `docs/REST-API.md`) | Category-complete, per-route detail unverified | Consolidate the two files into one; verify per-route args against all 147 `register_rest_route()` call sites | Half-generatable (route signatures, args, permission callbacks are mechanical) / half-authored (example request/response prose already exists and is decent, just needs the merge) |
| `developer-guide/helper-functions.md` — **does not exist as its own page** | 0 | New page: 88 Free + 11 Pro undocumented helper functions | **Mostly generatable** — these are `function_exists()`-guarded top-level functions, almost certainly already carry a PHPDoc block; a signature + docblock dump gets you most of the value. |
| `developer-guide/capabilities.md` | 175 lines, dated 27 Jul (pre-dates recent hook/REST refreshes) | Verify 15 Free + 2 Pro caps are all listed and current | Small — a re-verify pass, not a rewrite |
| `features/` | 52 pages | Narrow: shortcode reference (1 shortcode, `listora_compare`), Pro block-by-block reference (5 blocks) | Authored, but small — 2-4 short pages |
| `user-journeys/` | 7 pages | None identified | n/a |
| `marketing/` | ~35 pages | Out of scope for this assessment (not "documentation" in the dev/customer sense) | n/a |

## 5. Recommendation

**Ship for 1.7.0** (small, mechanical, or already-scoped):

1. Fix the 4 false claims in `docs/CONTRIBUTING.md` (CI claim, controller/endpoint counts, table
   count, dead `docs/ARCHITECTURE.md` link) — this is a ~30 minute edit correcting an actively
   misleading onboarding doc, not new authoring.
2. Complete `hooks-reference.md` for the 317 missing hook entries (109 Free, 208 Pro) — mechanical
   extraction from source, the highest-leverage single item since it takes hook coverage from
   66%/3% to near-100% on both plugins for close to a documentation-generator's worth of effort.
3. Add the missing Credits and Migration settings pages (2 pages, same template as the existing 9).
4. Consolidate `docs/REST-API.md` and `docs/website/developer-guide/rest-api.md` into one file —
   removes an active drift risk for near-zero cost.

**Defer to 1.8.0** (genuinely needs authored effort and isn't blocking a release):

1. `developer-guide/helper-functions.md` for 99 functions — worth doing, but it's a new page, not
   a patch to an existing one, and needs a human pass to group functions usefully rather than dump
   103+12 entries alphabetically.
2. Pro-specific admin walkthroughs (18 settings surfaces, 11 admin pages) beyond the settings-tab
   pages above — the feature-level prose already covers *what* these do; a full admin-screen
   walkthrough with screenshots is a bigger, genuinely authored lift.
3. Shortcode + Pro block reference pages (small, but not urgent — the underlying feature is
   already documented in `features/`).
4. Re-verifying `capabilities.md` and `wp-cli-commands.md` line-by-line against source (spot
   checks here didn't find a problem, but neither was fully verified).

**What I would not do:** scaffold a second `docs/website/` tree in Pro. That contradicts Pro's own
documented architecture decision and would fork content that's currently (mostly) staying in sync
by living in one place. The 1.7.0 work is closing gaps in the existing one-site model, not building
a second one.
