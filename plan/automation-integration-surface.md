# Automation integration surface — design

**Open design. Written 2026-08-15. Covers Free and Pro.**

Filed in Free because the bulk of the work lands here: both registries, the discovery
route, the schemas and the serializer consolidation are Free-owned. Pro's change is
comparatively small — it stops owning an event list and reads Free's. Pro points here
from [`plan/automation-integration-surface-POINTER.md`](../../wb-listora-pro/plan/automation-integration-surface-POINTER.md).

Not scheduled against a release. 1.6.0 is in flight and unreleased; this is the wave
after it unless someone decides otherwise.

## Goal

Make WB Listora a first-class citizen in the automation platforms site owners already
use — Zapier, Make, n8n, Uncanny Automator — by publishing what Listora can **tell**
them (triggers) and what they can **ask** it to do (actions), with a contract strong
enough that we cannot silently break a live integration.

## What exists today

Pro has a real outgoing-webhook subsystem and this design builds on it rather than
replacing it. `includes/features/class-outgoing-webhooks.php` is 2,388 lines carrying:

- a `listora_webhook` CPT for subscribers, with an admin UI
- HMAC-signed delivery on Action Scheduler, `MAX_ATTEMPTS` 4, backoff 60 / 300 / 1800s,
  10s HTTP timeout
- a delivery log with `LOG_RETENTION` 50 and an hourly cleanup job
- REST routes for listing deliveries and retrying one
- a `wb_listora_pro_before_outgoing_webhook` filter that can cancel a dispatch

None of that is in question. The gaps are elsewhere.

### Gap 1 — two events are dispatched into the void

`dispatch_event()` is called with 13 event names. `Outgoing_Webhooks::EVENTS` — the map
the admin UI builds its checkbox list from — contains 11. The missing two are
`coupon_redeemed` and `need_posted`.

Both have real handlers (`on_pro_coupon_redeemed`, `on_pro_need_posted`) that build a
payload and dispatch. No site owner can subscribe to either, because neither appears in
the UI, so `get_active_webhooks_for_event()` returns nothing every time and the payload
is discarded. Two events that cost CPU and deliver nothing, indefinitely.

This is the same shape as the bugs the 1.6.0 wave was built to kill: one list drifting
from another with no check between them. **The fix is not to add two lines to `EVENTS`
— that repairs today and re-opens on the next event anyone adds.** The fix is to delete
the second list entirely, which is what this design does.

### Gap 2 — 11 subscribable events against a plugin with hundreds of facts

The manifests record 311 fired hooks in Free and 247 in Pro. Eleven are subscribable.
There is nothing for members, favourites, services, moderation, need fulfilment, or the
plan lifecycle (activated / paused / resumed) — the last of which is the money journey,
and the thing an owner most wants to automate around.

### Gap 3 — no actions at all, and no way to authenticate one

Listora can currently only talk. An automation platform cannot ask it to do anything.
There are 63 Free and 73 Pro REST routes that would serve perfectly well as actions,
but they are undiscoverable, unnamed as a set, and reachable only by a cookie-and-nonce
session that no external platform has.

### Gap 4 — webhook payloads are a second set of shapes

`Outgoing_Webhooks` carries private `build_listing_payload()`, `build_review_payload()`,
`build_claim_payload()` and `build_user_data()`. These serialize the same entities the
REST controllers serialize, separately, and nothing keeps the two in step.

This is precisely the divergence that produced BC 10194450677, where `featured_image`
meant one thing on `/search` and another on `/related` — except an integrator hits it
later and quieter, because their automation does not error, it just stops mapping a
field.

## Decisions

| # | Decision | Rationale |
|---|---|---|
| D1 | **Integration surface for external platforms**, not an internal rules engine | Builds on the delivery engine that already exists. An in-plugin "when X do Y" builder duplicates what Zapier and Automator do well, and is a far larger build. A rules engine can be layered on the same registries later if it is ever wanted. |
| D2 | **Free owns the registries, actions and auth; Pro keeps delivery** | Matches the upscale model — Free is the platform, Pro is the premium scale/UX layer. Pro consumes a documented Free registry instead of reaching into Free's internals, so INV-3 holds by construction. Free-only sites become automatable. |
| D3 | **WordPress Application Passwords** for authentication | Zero auth code to own. Already understood by every target platform. Revocable per-integration from the user profile. Inherits every existing capability check unchanged. Requires HTTPS and acts as a real user — both acceptable, and both true of the alternatives that cost far more. |
| D4 | **JSON Schema per event, published via discovery and enforced in CI** | The schema does double duty: it is the discovery payload the platforms read to build their trigger fields, and the artifact CI diffs. Silently breaking a live integration is the worst failure a webhook system has, because the owner learns about it days later from missing data. |
| D5 | **Hand-authored registry, cross-checked against the manifest by CI** | The catalogue is a deliberate product decision, not an accident of implementation. Deriving it from 558 hooks would publish mostly internal plumbing, and `args_signature` is a PHP call signature, not a payload schema. Runtime self-registration cannot be checked statically or served as discovery. Explicit registries are also the pattern the codebase already teaches — `Field_Registry`, `Listing_Type_Registry`, `Page_Registry`. |
| D6 | **`version` is per-event, not global** | A global version forces every integration to care about changes to events it does not consume. |
| D7 | **Schemas are JSON files on disk**, not PHP arrays | Diffable in review, servable byte-for-byte by discovery, and CI can diff them without booting WordPress. |
| D8 | **One canonical serializer per entity, shared with REST** | Closes Gap 4 at the root. A webhook's `listing` object *is* what `/listings/{id}` returns, from the same code. The four private builders are deleted. |

D5's honest cost: someone must add a registry entry when they add an event, and
forgetting is possible. G12 (below) converts that silent omission into a red build.

## Architecture

### Registries (Free)

Two classes under `includes/automation/`, each behind an interface in
`includes/contracts/`, each registered in `Plugin::register_services()` before
`wb_listora_loaded` fires so Pro can resolve them at its own boot:

```
wb_listora_service( 'triggers' )  → Contracts\Trigger_Registry_Interface
wb_listora_service( 'actions' )   → Contracts\Action_Registry_Interface
```

**`Trigger_Registry`** answers: what can be subscribed to, and what does it send? An
entry carries:

| Field | Meaning |
|---|---|
| `name` | Stable event key, `snake_case` (e.g. `listing_approved`). Never renamed. |
| `label` | Human-readable, translated, shown in the subscriber UI. |
| `group` | UI grouping only — listing / reviews / claims / members / favourites / services / money. |
| `hook` | The WordPress hook the event hangs off. G13 checks the manifest records it as fired. |
| `capability` | The capability required **to subscribe to this event**, not to fire it. A trigger performs nothing; this exists because a payload can carry data not everyone should receive — `payment_received` and `credits_added` expose money, `claim_submitted` exposes a member's contact details. Defaults to `manage_listora_settings`, matching who can already configure webhooks today, so this is not a new restriction. |
| `version` | Current schema version, integer. |
| `schema` | Path to the JSON Schema file for the current version. |
| `condition` | *(optional, added during Task 5)* When two or more triggers share the same `hook` — e.g. `listing_approved`/`listing_rejected`/`listing_deactivated`/`listing_reactivated`/`listing_pending_review` all hang off `wb_listora_listing_status_changed`, and `claim_approved`/`claim_rejected` both hang off `wb_listora_after_update_claim` — this array is INTENDED to tell a future dispatcher which of the hook's own fired arguments must match for THIS trigger to fire. Shape: `array( 'new_status' => 'publish', 'previous_status' => array( 'pending', 'listora_rejected' ) )` — `previous_status` is optional and only needed when `new_status` alone is not unique across the triggers sharing the hook (e.g. both `listing_approved` and `listing_reactivated` produce `new_status = publish`; `previous_status` is what tells them apart). Additive: `Trigger_Registry::register()` only validates the 7 keys above and stores any extra key unchanged, so no registry code change was needed to add this field. **`condition` is declarative only as of this wave — nothing evaluates or enforces it today.** Pro's `Outgoing_Webhooks` handlers branch on `$new_status` themselves inside their own callbacks, independent of this field. Wiring a real dispatcher to enforce `condition` is a prerequisite before any discovery endpoint publishes conditions to third parties, and is deliberately out of scope here — enforcing it would change live delivery (e.g. `listora_deactivated -> publish` currently satisfies `listing_approved`'s condition and would stop firing under naive enforcement, and `listing_reactivated` has no Pro delivery today, so subscribers would simply lose the event). That is its own task, with its own verification and a production-rule-3 escape hatch. Every trigger sharing a hook MUST still declare a `condition` that is mutually exclusive with its siblings on that hook — declaring two with overlapping conditions would reintroduce exactly the double-fire bug Ruling B exists to prevent, the moment a dispatcher does start enforcing it — and the mutual-exclusivity test enforces this today even though nothing yet evaluates the field at delivery time. |

It delivers nothing — delivery is Pro's.

**`Action_Registry`** answers: what can an external system make Listora do? An entry
maps a stable `name` to an existing `route` and `method`, the `capability` that route
already enforces, and an input schema. This is overwhelmingly a **catalogue over the
136 routes that already exist**, not a set of new endpoints.

Both registries are extensible by filter, which is how Pro contributes its own entries
without Free knowing Pro exists.

### Discovery (Free)

One public route:

```
GET /listora/v1/automation/catalogue
```

returns both registries with their schemas inlined. This single response is what a
Zapier / Make / n8n integration reads to build its trigger and action lists. It is
generated from the registries, so it cannot describe something that does not exist.

Public and unauthenticated by design — it is a capability catalogue, not data. It must
never carry site data, user data, or anything derived from a licence key.

### Delivery (Pro, largely unchanged)

`Outgoing_Webhooks` keeps its CPT, HMAC, retry ladder, log, cleanup and admin UI. Two
changes only:

1. `EVENTS` is deleted. The admin UI builds its checkbox list from
   `wb_listora_service( 'triggers' )`. There is no longer a second list to drift.
2. The four private payload builders are deleted in favour of the shared serializers.

### Auth

Application Passwords over Basic auth. Every action inherits its route's existing
permission callback — no action invents authorization. An Application Password acting
as a member can do exactly what that member can do in the browser, and nothing more.

## Triggers

### Envelope

Today Pro sends `{ event, timestamp, site_url, data }`. Production rule 2 forbids
renaming a public identifier without an alias, and every live subscriber parses those
four keys, so **they keep their names and meanings exactly**. Two keys are added:

| Key | Type | Purpose |
|---|---|---|
| `version` | int | Schema version of THIS event. Bumped only by a deliberate breaking change. |
| `id` | string | Delivery UUID, stable across retries, so a receiver can dedupe. |

Additive only, which is what production rule 8 permits in a minor release.

### Naming

Event names stay `snake_case`. No move to dotted names — the churn buys nothing and
breaks everyone. `coupon_redeemed` and `need_posted` are *declared*, not renamed: they
become subscribable and start working. That is a fix, not a break.

### What earns a trigger

Three tests, each drawn from a bug already paid for:

1. **It is a business fact an owner would automate on**, not an implementation detail.
   This is what stops the catalogue becoming 558 hooks.
2. **It fires from every path that produces the fact.** The claim audit trail missed
   admin approvals because REST fired the hook and wp-admin did not (BC 10199419982). A
   trigger that fires on one of two paths is worse than none, because the automation
   looks like it works.
3. **Its payload resolves without the caller's request context**, so cron and WP-CLI
   paths fire it identically.

### Catalogue

Grouped as: listing lifecycle, reviews, claims, members, favourites, services, and
money (payments, credits, plans, coupons). Rough size is 30–35 against today's 11 — the
exact set is sized against the real hook inventory during planning rather than guessed
into this spec.

### Versioning

Changing a payload's shape bumps that event's `version`. G15 will not let a schema
change through without it.

## Actions

An action is a stable name over an existing route:

```
listing.create   → POST   /listora/v1/submit
claim.approve    → PATCH  /listora/v1/claims/{id}
credits.add      → POST   /listora/v1/credits/admin-add     (Pro)
```

Where a genuine gap appears, the route lands in **Free first** and Pro's consumer commit
ships behind it — rule 3 of the upscale contract. Given 136 existing routes, the honest
gaps are expected to be few, and they get enumerated during planning rather than
assumed here.

## Serializer consolidation

Each entity gets one canonical serializer, used by both the REST controllers and the
trigger payloads. The four private builders in `Outgoing_Webhooks` are deleted.

This is the highest-value item in the design and the one most likely to be cut under
time pressure. It should not be: it is the root cause of Gap 4, and leaving it means
shipping a brand-new public contract that is already inconsistent with the API it sits
beside.

## CI

Four new guardrails in `bin/audit-guardrails.sh`, following G1–G11:

| Check | Fails when | Would have caught |
|---|---|---|
| **G12** | an event is dispatched but not declared in the trigger registry | `coupon_redeemed`, `need_posted` |
| **G13** | a declared trigger names a hook the manifest does not record as fired, or a declared action names a route that does not exist | registry drift after a rename |
| **G14** | a declared trigger has no schema file, or the schema does not match what the serializer produces | payload drifting from its own contract |
| **G15** | a schema file changed without its event's `version` being bumped | every silent break of a live integration |

G15 is the one that protects integrations in the field. Every one of these must be
**mutation-tested on authorship** — revert the fix, confirm the check fails, restore.
Three of the six detectors written during the 1.6.0 wave passed on a deliberate
regression the first time, and were only caught because each was mutation-tested. A
detector that cannot fail is worse than none, because it reports green.

## QA

- A **local receiver fixture** so journeys can assert real delivery rather than
  intent — signature verification, the retry ladder, and dedupe by `id`.
- Journeys under `docs/qa/journeys/` covering: subscribe → fire → receive; a failing
  endpoint climbing the retry ladder and giving up at `MAX_ATTEMPTS`; an action called
  with an Application Password succeeding; the same action called by a user without the
  capability returning 403; discovery listing exactly what the registries hold.
- Runbook rows in `docs/qa/AGENT_SMOKE_RUNBOOK.md`, because a webhook that silently
  stops delivering is invisible to every check that exists today.
- Both plugins' manifests updated in the same PR as the code, per the manifest-first
  rule.

## Compatibility

Nothing is removed and nothing is renamed. Existing subscribers keep working untouched:
same event names, same envelope keys, new keys only. The two orphaned events begin
delivering to anyone who subscribes to them. This is a minor release, not a major.

The one behaviour change is that `coupon_redeemed` and `need_posted` start reaching
subscribers — which cannot surprise anyone, since nobody can be subscribed to them
today.

## Explicitly not in scope

- An internal rules engine ("when X happens, do Y" in wp-admin). D1 defers it; the
  registries are designed so it could be built on them later without redesign.
- A published Zapier app, Make app, or n8n node. Those are separate deliverables in
  separate repos, and each needs this surface to exist first.
- OAuth 2.0. Revisit if and when a public Zapier app listing requires it.
- Migrating existing subscribers to anything. They are not touched.

## Deferred triggers

Written while declaring Free's trigger catalogue (Task 5). Every entry here failed test 2
("fires from every path that produces the fact") or test 3 ("payload resolves without
request context"), verified in source. None are declared in
`includes/automation/class-trigger-definitions.php`. Money-owned candidates
(`wb_listora_listing_limit_overflow` and anything payment/credit/plan/coupon-shaped) are
excluded from this list — they belong to Task 7, not to a test-2/3 failure.

| Candidate | Hook | Why deferred |
|---|---|---|
| `review_approved` / `review_rejected` | `wb_listora_review_status_changed` | **Real gap, found while building this task, unfixed.** The hook only fires from the REST `update_review()` endpoint. `Admin::render_reviews_page()`'s approve/reject row action AND its bulk action run a raw `$wpdb->update()` directly against the `reviews` table and fire **no hook at all** — not `review_status_changed`, not even the generic `wb_listora_after_update_review`. Same shape as the claim audit trail bug (BC 10199419982), which got a fix (`fire_claim_updated()`) in 1.6.0; the review admin page never got the equivalent fix. Declaring `review_approved`/`review_rejected` today would mean every review moderated from wp-admin silently fails to fire the automation while REST-moderated reviews fire it — the automation "looks like it works." Fix: give `render_reviews_page()` the same wrapper `class-admin.php` already has for claims (`fire_claim_updated()`), firing `wb_listora_review_status_changed` + `wb_listora_after_update_review` from all three review-admin action sites, THEN declare the triggers. |
| `listing_deactivated` / `listing_reactivated` off the dedicated REST hooks | `wb_listora_after_deactivate_listing` / `wb_listora_after_reactivate_listing` | Only the REST `deactivate_listing()` / `reactivate_listing()` endpoints fire these. wp-admin's classic Edit Post screen can move a `listora_listing` between `publish` and `listora_deactivated` via its native Status dropdown (WP core always offers the post's current non-builtin status as a dropdown option) without ever calling REST, bypassing both hooks. **Not actually missing from the catalogue** — `listing_deactivated`/`listing_reactivated` ARE declared, just off `wb_listora_listing_status_changed` instead (the `transition_post_status` chokepoint, which fires on every path including the native dropdown). Listed here so nobody re-adds these two dedicated hooks as a second, REST-only source for the same facts. |
| `listing_trashed` | `wb_listora_listing_trashed` | REST-only (single call site in `Listings_Controller`). wp-admin's standard "Move to Trash" row action / bulk action on the CPT list table trashes a listing via WP core's own trash flow, never calling this endpoint, so the hook misses the majority real-world path. Falls under Ruling C's blanket deferral of deletion-adjacent triggers to a follow-up wave regardless — `wp_listora_get_listing_cards()` (which `Payload::listing()` uses) queries `search_index`, and `Search_Indexer::remove_from_index()` runs on `trashed_post`, so by the time any trash-shaped hook could reliably fire from every path, the payload risks resolving to nothing depending on hook priority ordering. |
| `listing_updated` / `wb_listora_after_update_listing` | `wb_listora_listing_updated` / `wb_listora_after_update_listing` | Both fire from a single site in `Submission_Controller` (REST update). wp-admin's classic Edit Post screen edits a `listora_listing`'s title/content/meta directly via `save_post`, which fires neither hook — an admin-side edit is invisible to both. No chokepoint equivalent exists (unlike status changes, plain field edits don't route through `transition_post_status`). Needs a `save_post_listora_listing` listener that fires the same hook before this can be declared. |
| `listing_type_changed` | `wb_listora_listing_type_changed` | Single fire site, admin-only (`Listing_Type_Metabox`). No REST path fires it when a listing's type is changed via the submission/update REST endpoints, so REST-driven type changes are invisible to any automation subscribed to this event. |
| `wb_listora_after_create_listing`, `wb_listora_after_update_listing`, `wb_listora_after_deactivate_listing`, `wb_listora_after_reactivate_listing`, `wb_listora_after_renew_listing`, `wb_listora_claim_approved`, `wb_listora_claim_rejected`, `wb_listora_after_add_favorite`, `wb_listora_after_remove_favorite` | (various) | Same-occurrence twins of a declared trigger (Ruling B and its extension — see the docblock in `class-trigger-definitions.php` for the full list and which sibling was kept and why). Not test-2/3 failures; declaring both sides of a pair double-fires the automation for one real-world event. |
| `wb_listora_listing_pending_admin` | `wb_listora_listing_pending_admin` | **Dead hook — never re-use for a trigger.** Corrected during a Task 5 review round: this hook was originally used for `listing_pending_review`, but all three of its call sites are gated on the listing already being in `pending_verification` status, and the only writer of that status (`Submission_Controller::create_listing()`) is gated on `$verification_required`, hardcoded `false` since guest submission was removed and never reassigned anywhere. No listing on any install can reach `pending_verification`, so this hook cannot fire on any install, ever, under the current codebase. `listing_pending_review` is now declared on `wb_listora_listing_status_changed` (`new_status = pending`) instead — see the declared catalogue. If `pending_verification` is ever reintroduced, this hook would need re-auditing, not blind re-use. |

Deletion-shaped triggers (`wb_listora_after_delete_listing`, `wb_listora_listing_data_deleted`) remain deferred to a follow-up wave per the spec ruling — their payload cannot resolve at fire time (`Payload::listing()` reads `search_index`, which `Search_Indexer::remove_from_index()` has already cleared by then). `wb_listora_listing_expired` was checked against the same test and does NOT belong on this list — see the "listing_expired" entry in the declared catalogue.

### Services trigger payload — hard dependency for a later task

The three declared service triggers (`service_created`, `service_updated`, `service_deleted`) have no
canonical entity serializer today, unlike listing/review/claim/user (`Payload::listing/review/claim/user()`
in `includes/automation/class-payload.php`). Their schemas describe the intended contract from the DB
columns and hook args directly (`Services::create_service()`/`update_service()`/`delete_service()`'s own
`$data`/`$existing` arrays), not from a shared builder.

A later task (the dispatcher) MUST add `Payload::service( $service_id )` before these three triggers can
actually deliver a payload. The natural implementation would reuse
`Services_Controller::prepare_service_response()` — the same method `GET /listings/{id}/services` already
uses per row — except that method is **`private`**. Making the service triggers byte-identical to the REST
shape (the same design goal `Payload::review()`/`claim()` already meet) requires changing that method's
visibility to `public` (or extracting it to a `public static` helper, matching the `format_review_row()` /
`format_claim_row()` pattern on the Reviews/Claims controllers) in Free, before Pro's dispatcher work can
consume it. This is a real blocker for those three triggers going live, not a nice-to-have.

## Acceptance

- `coupon_redeemed` and `need_posted` are subscribable and deliver.
- `Outgoing_Webhooks::EVENTS` no longer exists; the admin UI reads the Free registry.
- The four private payload builders no longer exist; webhook entities match their REST
  counterparts byte for byte.
- `GET /automation/catalogue` returns every trigger and action with a schema, and
  nothing that is not registered.
- An Application Password can call every catalogued action, and is refused by capability
  exactly as a browser session would be.
- G12–G15 are active, passing, and each has been mutation-tested.
- `composer ci` green in both repos; boundary check still 0.
- Every existing subscriber receives the same events with the same envelope keys as
  before the change.
