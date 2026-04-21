# WB Listora — Long-Term Stability Plan

> Internal document. Read this before you write a line of code.  
> Last updated: 2026-04-21

---

## 1. North Star

In 18 months, WB Listora is the directory plugin that site owners reach for when they want a genuinely fast, block-based experience — and that app developers reach for when they need a clean, predictable REST API on day one. Every competing plugin in this space still routes user actions through `admin-ajax.php` and renders pages with jQuery. We do not. That technical gap is our moat. We defend it by keeping every architectural pillar non-negotiable, by shipping the reference app that proves our API works, and by being the only directory plugin with meaningful CI gates that would actually catch a regression before it ships.

---

## 2. Competitive Positioning

| | Directorist | GeoDirectory | HivePress | Business Dir. Plugin | **WB Listora** |
|---|---|---|---|---|---|
| **JS stack** | jQuery | jQuery + some Vue | Vanilla JS / React (HivePress 2.x) | jQuery | Interactivity API (zero jQuery on frontend) |
| **Admin API** | admin-ajax | admin-ajax | mixed | admin-ajax | REST (39 Free + 21 Pro endpoints); settings save still form-POST — tracked as debt |
| **Rendering** | Classic PHP | Classic PHP | Classic PHP templates | Classic PHP | Block-first, server-rendered with progressive hydration |
| **App-ready** | None | Partial (WP REST core only) | Partial | None | Full (consistent envelope, WP_Error permission callbacks, app-password auth) |
| **Theme override** | Locked (custom templates) | Partial | Partial | Locked | WooCommerce-style override at `{theme}/wb-listora/` |
| **Free/Pro split** | Addons marketplace | Addons | Marketplace | Addons | Native (Pro as single add-on requiring Free) |

**Honest notes:**

- The "Admin API" row is where we are not yet ahead of ourselves. Settings save still uses a form POST. That is the most important debt item this quarter.
- HivePress 2.x is moving to React. We are not. That is a deliberate bet: IA gives us SSR + progressive hydration without a client-side bundle. If that bet is wrong, we revisit in Phase 2.
- GeoDirectory has more addon volume and a larger ecosystem. We do not compete on addon count — we compete on core quality.

---

## 3. Architectural Pillars

### Pillar 1: 100% REST-ready

**What it means.** Every user-facing action — submit a listing, toggle a favorite, vote on a review, save a search, purchase a credit pack — must be reachable over `listora/v1` with application-password authentication. The admin dashboard must use the same endpoints an external app would use. Two exceptions are permitted and must be documented: the webhook receiver (`class-webhook-receiver.php`) which receives inbound POSTs from payment processors, and any WordPress core lifecycle hook (activation, deactivation) that has no REST analog.

**How we verify it.** The `wb-listora-app-starter` reference app (Phase 1 deliverable) must complete a full user journey — register, submit listing, favorite, review, dashboard view — using only `listora/v1` endpoints over application passwords, with zero `admin-ajax.php` calls in the network log.

**What we give up.** We cannot use `wp_ajax_*` hooks for any new feature. This adds a few hours to each feature ticket because you must also write the REST route. We accept that cost.

---

### Pillar 2: Block-based + Interactivity API

**What it means.** All 11 blocks use `apiVersion: 3`, register a `viewScriptModule` (ESM, not classic IIFE), and import the shared IA store from `src/interactivity/store.js`. No block has inline `onclick`, `alert`, or `confirm`. All actions live in the single `listora/directory` namespace in the shared store. No block loads jQuery. No block loads a third-party JS framework (React, Vue, Alpine).

**How we verify it.** The CI WPCS check catches `onclick` in PHP. The ESLint config (once added — see debt) catches event binding outside the store. Any PR that adds a second IA namespace or a new `import { useState }` must be rejected at review. The `Listora_Block_Base` PHP class (Phase 1) will enforce that every block has `viewScriptModule` set; a missing entry throws an exception in `WP_DEBUG` mode.

**What we give up.** No React-based block editor components beyond what `@wordpress/components` provides. No SPA routing between listing detail pages. No third-party map SDK that requires its own JS bundle (Google Maps JS is loaded only when the Pro Maps feature is active; the Free map uses Leaflet loaded as a module). These constraints keep our frontend JS bundle well under the 150 KB uncompressed target.

---

### Pillar 3: Fast rendering

**What it means.** Blocks render meaningful HTML on the server. The client hydrates progressively — the listing grid is readable before JS loads. Database queries inside `render.php` are bounded: one `Listing_Data::load()` call per card, not one `Meta_Handler::get_all_values()` call per field. Meta and term caches are primed with `update_postmeta_cache()` / `update_object_term_cache()` before any `prepare_*` loop. REST list endpoints return at most 20 items by default (hard-capped at 100). Dashboard stats are cached in a 60-second transient.

**How we verify it.** Phase 1 adds a `.size-limit.json` file and a Lighthouse CI action. Lighthouse score must be >= 90 on the reference listing grid page. Bundle size CI fails if any individual block's viewScriptModule exceeds the budget. These checks run on every PR targeting `main`.

**What we give up.** We cannot add a real-time WebSocket feature, a client-side search-as-you-type that hits every keystroke, or any feature that requires loading a second JS runtime. Push notifications are delivered via outgoing webhooks (Phase 2), not a persistent socket.

---

## 4. Current State Snapshot

### Solid

- REST envelope consistency (`items`, `total`, `pages`, `has_more`) across all 60 endpoints.
- All permission callbacks return `WP_Error`, never bare `false` — apps get HTTP status codes they can act on.
- CI gates: PHP Lint, WPCS, PHPStan L7, PHPUnit, PCP (noise-filtered) — all green on Free and Pro.
- 11 blocks on IA with shared store and single namespace. No jQuery in any `view.js`.
- Four documentation tiers: `REST-API.md`, `ARCHITECTURE.md`, `CONTRIBUTING.md`, `docs/website/` customer docs.
- Template override system documented and functional for `listing-card`, `listing-detail`, `user-dashboard`.
- `Listing_Data` helper class separates DB queries from `render.php`.
- Write-operation hooks (`before_` / `after_`) on every CRUD operation — Pro and extensions can intercept without forking.

### Shaky

- **Admin still has form-POST paths.** Settings save (`class-settings-page.php`), claims moderation, and reviews moderation do not go through the REST API. A React Native app cannot perform admin operations.
- **No performance budget enforcement in CI.** We know we're fast; we cannot prove it won't regress on the next PR.
- **No Lighthouse benchmark on record.** We have not run a baseline score. We do not know our current number.
- **No sample app.** The "REST-ready" claim is architectural, not demonstrated. No reference app exists that would catch a broken endpoint before a customer reports it.
- **CAPTCHA bypass landed this week.** reCAPTCHA v3 + Turnstile are new. The rate-limiting logic is untested under load. Monitor for false positives on mobile IPs.
- **Saved-search PUT landed this week.** Edge case: a user with a saved search whose criteria include a listing type that is later deleted. Not yet handled.

### Missing

- `Listora_Block_Base` PHP class — blocks are consistent but not enforced by a common parent.
- REST base controller class — each controller reimplements envelope assembly. Not yet extracted.
- Block pattern library — no drop-in compositions exist.
- Type-aware template variants — restaurant and hotel render the same card layout.
- `.size-limit.json` and Lighthouse CI action.
- `.github/pull_request_template.md`.
- ESLint config for IA store discipline.
- GraphQL layer.
- Service worker / PWA manifest.

---

## 5. 18-Month Roadmap

### Phase 1 (0–6 months): Make the claims defensible

This phase is about closing the gap between what we say and what we can prove. Every item here either prevents a regression or enables the reference app.

**Infrastructure**

- `Listora_Block_Base` PHP abstract class. Every block's `render_callback` must extend it. Enforces: `viewScriptModule` present, `Block_CSS::render()` called, template override path checked. Missing any of these throws a `_doing_it_wrong()` in `WP_DEBUG` mode.
- REST base controller class `Listora_REST_Controller`. Extracted from `class-listings-controller.php`. Provides: `envelope()`, `require_logged_in()`, standard `register_routes()` stub. All 10 Free controllers refactored to extend it.
- `.size-limit.json` — per-block JS budget, total CSS budget. Fails CI if exceeded.
- Lighthouse CI GitHub Action — runs against staging URL on PRs. Required score >= 90. Blocks merge if it fails.
- ESLint rule: no IA actions outside `store.js`, no second IA namespace registration.

**Admin dogfooding REST**

- Refactor settings save to POST to `listora/v1/settings` (endpoint exists in `class-settings-controller.php`; admin form currently bypasses it).
- Refactor claims moderation and reviews moderation admin pages to use `listora/v1/claims` and `listora/v1/reviews` — same endpoints the frontend blocks use.
- Inline `<script>` blocks in `class-settings-page.php` and `class-admin.php:1236` (onboarding checklist) move to `assets/js/admin/settings.js` and `assets/js/admin/onboarding.js` as ES modules.

**Reference app**

- `wb-listora-app-starter` — separate public repo. React Native (Expo) + Next.js. Covers: auth via app passwords, listing browse + search, listing detail, submit listing, add review, toggle favorite, user dashboard. README documents every endpoint used and how to point it at a local site.
- This repo also serves as the integration test harness: a `curl`-based journey test script checks all critical endpoints return expected shape.

**Block patterns**

Six drop-in block pattern compositions registered under the `wb-listora` category:
1. Directory home (search + featured + categories)
2. City page (location filter + grid + map)
3. Event calendar (calendar + upcoming list)
4. Hotel funnel (type-filtered grid + detail strip)
5. Marketplace (needs + listings side-by-side)
6. Dashboard shell (user-dashboard full-width)

**Type-aware template variants**

Restaurant, hotel, real-estate, and event listing types each render a distinct card layout and detail layout automatically, determined by the listing type's `template_variant` config key. No custom code required from the site owner.

---

### Phase 2 (6–12 months): Customer-visible polish

By this phase, the infrastructure is solid. We ship things customers can see and benchmark.

- **Optimistic UI** for favorite toggle, helpful vote, search alert toggle, claim status. The UI updates immediately; a rollback fires on server error.
- **Prefetch-on-hover** for listing detail pages using the `speculation-rules` API (with `<link rel="prefetch">` fallback for unsupported browsers).
- **Skeleton loaders** replace all CSS spinners in listing-grid, listing-search, and user-dashboard.
- **Service worker** for offline browsing — cache the last-viewed listing grid and detail page. PWA manifest for add-to-home-screen on mobile.
- **Push notifications via outgoing webhooks** — when a listing is approved, a review is posted, or a claim is updated, POST a signed payload to a configurable URL. The site owner wires this to their notification service.
- **ETag / Last-Modified** on all list endpoints. Clients that send `If-None-Match` get a `304` if the collection has not changed. Reduces bandwidth for polling apps.
- **GraphQL layer** — a thin `listora/graphql` endpoint that wraps the REST controllers. Uses `wp-graphql` if active, otherwise a minimal custom resolver. Targets frontend devs who prefer query colocation over REST round trips.
- **Needs marketplace Free variant** — a simplified version of the Pro needs system (no moderation, no categories) to drive Free-to-Pro upgrades.

---

### Phase 3 (12–18 months): Differentiation at scale

These features require Phase 1 and Phase 2 to be stable. Do not start Phase 3 work early.

- **AI-assisted listing submission.** An LLM (via WordPress site's configured API key, not bundled) reads pasted plain text and extracts structured field values. Site owner reviews before save. No data leaves the server without explicit user action.
- **Multi-site federation.** A network admin can configure source sites. Listings from source sites appear in the hub site's directory with a canonical link back. REST-based pull, not DB replication.
- **Block-based email editor.** The 14 notification templates currently in `templates/emails/` become Gutenberg post objects. Site owners edit them in the block editor. Rendered server-side on send.
- **White-label SDK for Pro customers.** Pro adds a WP-CLI command `wp listora scaffold child-plugin` that generates a plugin skeleton pre-wired to Free's hooks and base classes. Pro customers ship branded plugins built on Free's core without forking.
- **Full headless starters.** Next.js App Router and Astro starter repos with ISR on listing grid pages and SSG for listing detail pages. Documented in `docs/developer/headless.md`.
- **Public third-party extension marketplace.** A `listora_extension` CPT on a hosted site. Extensions register via a manifest URL. Free and Pro surfaces an in-admin browser.

---

## 6. Technical Debt Inventory

Ranked by regression risk. Target: all items below resolved within 8 weeks.

| Rank | File | Issue | Risk |
|------|------|-------|------|
| 1 | `includes/features/class-analytics.php` | Analytics REST routes are not documented in `REST-API.md`. App developers cannot discover or rely on them. | High — undocumented endpoints are treated as unstable and may change without notice |
| 2 | `includes/admin/class-settings-page.php` | Inline `<script>` blocks for settings UI. Violates CSP, untestable, not tree-shaken. | High — blocks ability to add Content-Security-Policy header |
| 3 | `includes/admin/class-admin.php:1236` | Onboarding checklist JS is inline. Same risk as above. | High |
| 4 | `includes/admin/class-setup-wizard.php` | Classic PHP multi-step form. Cannot be driven via REST. Blocks admin dogfooding goal. | Medium — new users hit this on every install |
| 5 | `blocks/*/render.php` (multiple) | Some files still call `Meta_Handler::get_all_values()` multiple times per render instead of using `Listing_Data`. Each call is a DB query. | Medium — silent performance regression, hard to detect without profiling |
| 6 | CI config | No Lighthouse CI action. No `.size-limit.json`. Performance regressions are invisible. | Medium |
| 7 | `.github/` | No `pull_request_template.md`. PR quality is reviewer-dependent. | Medium |
| 8 | `src/` | No ESLint rule enforcing IA store discipline. A developer can register a second namespace accidentally. | Low — catchable in code review, but manual |

---

## 7. Release Cadence + Versioning

### Free and Pro ship together

Free and Pro always release on the same day. A Pro release without a matching Free release is not permitted, because Pro depends on Free's version constant check.

| Bump | Trigger | Cadence |
|------|---------|---------|
| Patch `x.y.Z` | Bug fixes, copy changes, CSS-only changes | Weekly (or immediate for security) |
| Minor `x.Y.0` | New features, new endpoints, new blocks | Monthly |
| Major `X.0.0` | Breaking changes to REST shape, DB schema, hook signatures | Yearly maximum; `UPGRADE.md` required |

### REST API versioning

`listora/v1` is stable. Once an endpoint exists in `v1`, its response shape is frozen. Breaking changes — removing a field, changing a field type, requiring a new parameter — go to `listora/v2`. The `v1` endpoint remains functional for a minimum of 12 months after `v2` launches.

Non-breaking additions (new optional request parameters, new optional response fields) are **not** a version bump. They are announced in `CHANGELOG.md` under `### Added`.

### Release gate checklist

A release is blocked until all of the following are true:

1. All 4 CI gates green on both Free and Pro `main`.
2. Every item in the PR checklist ticked on all PRs in the milestone.
3. `REST-API.md` updated for any endpoint added, changed, or removed.
4. `docs/website/` updated for any user-facing behavior changed.
5. Manual smoke test on Twenty Twenty-Five, BuddyX, and Astra at 390px and 1366px viewport.
6. `CHANGELOG.md` entries written for Free and Pro.

---

## 8. Team Process — How We Stop Regressing

The rules below are not suggestions. A PR that violates them is not merged.

- Every PR touching REST, blocks, or admin uses `.github/pull_request_template.md` verbatim. The template is Section 9 of this document.
- No feature lands without a customer-facing doc page in `docs/website/features/`. Draft is acceptable; "TBD" is not.
- No REST endpoint lands without an entry in `docs/REST-API.md`. The entry must include the path, method, parameters, and response shape.
- No block lands without extending `Listora_Block_Base` (once the class exists, this is enforced by a `_doing_it_wrong()` call).
- No Pro feature introduces a pattern not already established in Free. Pro uses the same `wb_listora_require_logged_in()` helper, the same REST base controller, the same IA store namespace.
- **Quarterly architecture review.** Once per quarter, one developer audits the entire plugin against the `wp-plugin-development` skill document. Findings are logged in a GitHub Issue labeled `architecture-audit`. Items are assigned to the next milestone.

---

## 9. Definition of Done

A PR is mergeable only when every item below is checked. Copy this into `.github/pull_request_template.md`.

- [ ] All local CI gates green (Lint, WPCS, PHPStan L7, PHPUnit, PCP)
- [ ] 390px viewport tested manually; screenshot attached if UI changed
- [ ] 1366px viewport tested manually; screenshot attached if UI changed
- [ ] REST response envelope verified: `has_more === (offset + count(items)) < total`
- [ ] No inline `onclick`, `alert`, or `confirm` in PHP or JS output
- [ ] No hardcoded hex color in light-mode CSS (use `--listora-*` tokens)
- [ ] No new `wp_ajax_*` hook — use a REST endpoint
- [ ] No second Interactivity API namespace registered (`listora/directory` only)
- [ ] Meta and term caches primed before any `prepare_*` loop (`update_postmeta_cache`, `update_object_term_cache`)
- [ ] No `Meta_Handler::get_all_values()` call inside a loop — use `Listing_Data::load()`
- [ ] All write operations fire `before_` filter and `after_` action
- [ ] Permission callback returns `WP_Error`, not `false`
- [ ] New block extends `Listora_Block_Base` and registers `viewScriptModule` (not `viewScript`)
- [ ] `REST-API.md` updated if any endpoint was added, changed, or removed
- [ ] Customer doc page in `docs/website/features/` created or updated
- [ ] `CHANGELOG.md` entry written (Free and Pro if both affected)
- [ ] No `var_dump`, `error_log`, `print_r`, or `die()` left in code
- [ ] PHPStan level 7 passes with no new ignored errors
- [ ] Tested with Twenty Twenty-Five block theme active
- [ ] PR title follows `[Area]: Short description` format (e.g., `[REST]: Add ETag support to listings endpoint`)

---

## 10. Risks and Mitigations

| Risk | Likelihood | Mitigation |
|------|-----------|------------|
| WordPress deprecates or significantly changes the Interactivity API before Phase 2 | Low — IA is on the long-term roadmap, ships in core | We track the `@wordpress/interactivity` changelog. The shared store is isolated in `src/interactivity/store.js` — a migration is a single file change plus `view.js` updates. Estimated migration cost: 2 weeks. |
| App clients discover broken endpoints that browser testing never caught | Medium — browser tests cover happy paths; apps stress-test edge cases | Phase 1 deliverable: `wb-listora-app-starter` repo + curl-based journey test script. The journey test runs in CI against a `wp-env` instance. |
| Performance regresses silently as blocks accumulate | Medium — each PR adds a little weight | `.size-limit.json` + Lighthouse CI action block merge if budgets are exceeded. Baseline must be established in the first two weeks of Phase 1. |
| New code stops following established patterns as the team grows | Medium — happens on every codebase over 12 months | `Listora_Block_Base` and `Listora_REST_Controller` make the right way the only way. Quarterly architecture audit catches drift. PR template makes checklist unavoidable. |
| Pro features drift from Free's REST and IA patterns | Low currently — Pro team is small | Enforced by: Pro extending Free's base classes, shared `wb_listora_require_logged_in()`, shared IA store namespace. Any Pro PR that introduces a new pattern not in Free requires explicit architectural sign-off. |
| CAPTCHA bypass causes spam surge (new this week) | Medium — rate limiting is new and untested under load | Monitor `listora_submissions` table row count daily for 4 weeks post-launch. Alert threshold: > 3x baseline submissions/day. Roll back to reCAPTCHA-required if triggered. |
| Saved-search PUT edge case: listing type deleted after alert created | Low — niche scenario | Phase 1: add a `listora_listing_type_deleted` action that nullifies orphaned saved searches. Expose a `GET /saved-searches/{id}/status` field returning `active|orphaned`. |
| Free/Pro ship-together discipline breaks under deadline pressure | Medium — the temptation to patch Pro alone is real | Release gate checklist (Section 7) blocks any release where Free and Pro versions do not match. Automate this check in the release CI job. |
