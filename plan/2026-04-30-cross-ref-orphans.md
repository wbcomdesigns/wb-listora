# 2026-04-30 cross-ref orphan tasks

Source: cross-referenced wb-listora ↔ wb-listora-pro manifests after the audit-tasks PRs (#25 Free, #26 Pro) shipped, then a deeper code-level verification revealed the bug surface is wider than the manifest captured. Every line:file evidence below was re-grep'd this session.

> **Scope correction (post-verify):** the original plan classified this as "Pro listens to a hook Free doesn't fire" — which scoped the fix to Pro alone. A code-level read showed **Free's own `class-notifications.php` has the same dead listeners**, so customer-facing email notifications for listing approval, rejection, and expiration are all silently broken too. The corrected scope makes Free authoritative (it already fires the canonical hook) and treats Pro as upscaling Free's pattern, not inventing parallel contracts.

## Tasks

| ID | Plugin | Type | Customer impact | Effort | Status |
|---|---|---|---|---|---|
| F1 | Free | Bug — 3 dead notification listeners | **High** — listing approve/reject/expire emails never sent to authors | 30 min | **shipped @ `0aa62ca` (PR wb-listora#29, 2026-04-30)** |
| P1 | Pro | Bug — 2 dead webhook listeners | **High** — outgoing webhooks on approve/reject never fire | 20 min | **shipped @ `97810e8` (PR wb-listora-pro#27, 2026-04-30)** |
| O3 | Free + Pro | Architecture coherence — dead filter | Low (feature works via option write) | 30 min | **shipped @ `847dcc8` (PR wb-listora#31, 2026-04-30)** |
| O4 | Audit-only | Manifest accuracy refresh | None — audit hygiene | auto on next refresh | pending — runs on next `/wp-plugin-onboard --refresh` |

**Done deltas (F1 + P1 + O3):**
- F1: 3 listing-status emails (approved, rejected, expired) restored. Verified end-to-end: each transition fires `wp_mail` with the correct subject. Pre-existing `in_array($old, [pending, listora_rejected, listora_expired, draft])` gate on approval emails preserved.
- P1: 2 outgoing webhook events (listing_approved, listing_rejected) restored. Verified end-to-end: both transitions schedule `wb_listora_pro_deliver_webhook` cron events with the correct args. Pro mirrors Free's dispatcher pattern verbatim — same canonical hook (`wb_listora_listing_status_changed`), same switch shape, same separation of `wb_listora_listing_expired` (cron path stays on its dedicated listener to avoid double-fire).
- O3: `wb_listora_map_provider` filter now fires from Free's `wb_listora_get_setting()` resolver — Pro's existing listener (and any future override) is finally honoured. Pure additive change: existing sites resolve the same value (Pro's gate triple-checks before attaching), new sites can now override the resolved provider via filter without writing to the option. Verified: baseline `osm` returns `'openstreetmap'` (option value); test filter returning `'osm-forced'` → final resolved value is `'osm-forced'`.

Order: **F1 → P1** (Free first, Pro adopts Free's canonical hook), then **O3** separately, **O4** is automatic.

---

## The canonical hook (Free is authoritative)

`wb-listora/includes/search/class-search-indexer.php:553` already fires the canonical listing-status-transition hook:

```php
// Inside on_status_change(), gated by post_type === 'listora_listing'
// AND $new !== $old, so it fires once per real transition.
do_action( 'wb_listora_listing_status_changed', $post->ID, $new, $old );
```

This is the single hook both F1 and P1 should consume. **No new hook gets introduced** — Pro upscales Free by adopting Free's vocabulary, not by defining parallel events. The dead listener names (`wb_listora_listing_publish`, `wb_listora_listing_listora_rejected`, `wb_listora_listing_listora_expired`) appear to be authoring mistakes — the doubled `listora_listora` looks like a botched WP-core `{$old_status}_{$new_status}` transition-pattern attempt — and nobody noticed because there's no automated check that listeners have a firer.

---

## F1 — Free: restore listing-lifecycle email notifications

**File:** `wb-listora/includes/workflow/class-notifications.php:39-41`

**Evidence (read this session):**

```php
// Listing status changes.
add_action( 'wb_listora_listing_publish', array( $this, 'listing_approved' ), 10, 2 );
add_action( 'wb_listora_listing_listora_rejected', array( $this, 'listing_rejected' ), 10, 2 );
add_action( 'wb_listora_listing_listora_expired', array( $this, 'listing_expired' ), 10, 2 );
```

All three hooks return **0 hits** when grepped against any `do_action` / `apply_filters` site in either plugin. Three email events broken silently.

**Handler signatures (verified):**
- `listing_approved( $post_id, $old_status )` — gates with `! in_array( $old_status, ['pending','listora_rejected','listora_expired','draft'], true )` so meaningless transitions don't email.
- `listing_rejected( $post_id, $old_status )` — unconditional once fired.
- `listing_expired( $post_id, $old_status )` — verify by reading the body before changing the registration; arity is `2` per the existing add_action.

**Fix:** replace the 3 dead `add_action` lines with a single canonical-hook listener + a dispatcher that branches on `$new`:

```php
// before (lines 39-41)
add_action( 'wb_listora_listing_publish', array( $this, 'listing_approved' ), 10, 2 );
add_action( 'wb_listora_listing_listora_rejected', array( $this, 'listing_rejected' ), 10, 2 );
add_action( 'wb_listora_listing_listora_expired', array( $this, 'listing_expired' ), 10, 2 );

// after
add_action( 'wb_listora_listing_status_changed', array( $this, 'on_listing_status_changed' ), 10, 3 );
```

Then add (next to the existing `listing_approved` etc. methods):

```php
/**
 * Canonical listing-status dispatcher.
 *
 * Free fires `wb_listora_listing_status_changed( $post_id, $new, $old )`
 * once per actual transition (Search_Indexer::on_status_change short-
 * circuits when $new === $old). Branch by $new and forward to the
 * per-event handler with its expected ($post_id, $old_status) signature.
 *
 * @param int    $post_id Listing post ID.
 * @param string $new     New post status.
 * @param string $old     Previous post status.
 */
public function on_listing_status_changed( $post_id, $new, $old ) {
    switch ( $new ) {
        case 'publish':
            $this->listing_approved( $post_id, $old );
            break;
        case 'listora_rejected':
            $this->listing_rejected( $post_id, $old );
            break;
        case 'listora_expired':
            $this->listing_expired( $post_id, $old );
            break;
    }
}
```

The existing `listing_approved` keeps its `in_array($old, [pending, listora_rejected, listora_expired, draft])` gate — first-time approvals + re-approvals fire mail; no spam on republish-from-publish.

**Verify (browser, real status transitions):**
1. Submit a listing as a frontend user → status `pending`. Approve via WP admin (Quick Edit → Published). Author receives the `listing_approved` email.
2. Reject via Quick Edit (status → `listora_rejected`). Author receives the `listing_rejected` email with the rejection reason from `_listora_rejection_reason` post meta.
3. Set a listing's expiration to past, run the expiration cron (`wp listora cron run --hook=wb_listora_check_expirations` or wait). Author receives the `listing_expired` email when the cron transitions status to `listora_expired`.
4. Re-publish an already-published listing (no status change) → no email (existing `$new === $old` short-circuit at `class-search-indexer.php:539`).
5. Edit a published listing's content (status stays `publish`) → no email.

---

## P1 — Pro: restore outgoing webhooks for listing approve / reject

**File:** `wb-listora-pro/includes/features/class-outgoing-webhooks.php:149-153`

**Evidence (read this session):**

```php
// Listing approved (status transition to publish).
add_action( 'wb_listora_listing_publish', array( $this, 'on_listing_approved' ), 50, 2 );

// Listing rejected (status transition to listora_rejected).
add_action( 'wb_listora_listing_listora_rejected', array( $this, 'on_listing_rejected' ), 50, 2 );
```

Same dead-hook pattern as F1; same fix shape. (Lines 156, `wb_listora_listing_expired` at priority 50 — that one **does** fire from the expiration cron, leave it alone.)

**Handler signatures:** `on_listing_approved( $post_id, $old_status )` and `on_listing_rejected( $post_id, $old_status )` — same `(id, old)` shape as Free's notification handlers, so the dispatcher is structurally identical.

**Fix:**

```php
// before (lines 149-153)
// Listing approved (status transition to publish).
add_action( 'wb_listora_listing_publish', array( $this, 'on_listing_approved' ), 50, 2 );

// Listing rejected (status transition to listora_rejected).
add_action( 'wb_listora_listing_listora_rejected', array( $this, 'on_listing_rejected' ), 50, 2 );

// after
// Listing status transitions (approved + rejected) — ride Free's
// canonical wb_listora_listing_status_changed hook. Free's expired
// transition is dispatched separately via wb_listora_listing_expired
// from the expiration cron (kept on its own listener at priority 50).
add_action( 'wb_listora_listing_status_changed', array( $this, 'on_listing_status_changed' ), 50, 3 );
```

Add the dispatcher in Pro:

```php
/**
 * Canonical status-change dispatcher — mirrors Free's pattern.
 *
 * @param int    $post_id Listing post ID.
 * @param string $new     New post status.
 * @param string $old     Previous post status.
 */
public function on_listing_status_changed( $post_id, $new, $old ) {
    switch ( $new ) {
        case 'publish':
            $this->on_listing_approved( $post_id, $old );
            break;
        case 'listora_rejected':
            $this->on_listing_rejected( $post_id, $old );
            break;
    }
}
```

Pro's existing `on_listing_approved` / `on_listing_rejected` are public, so this is purely a re-routing — no behaviour change, no payload change. Webhooks fire on every real transition (no `pending → publish` gating like the email has, because webhooks are infrastructure events, not customer-facing emails).

**Verify (browser → register webhook → trigger transition):**
1. Pro → Webhooks → register a webhook with events `listing_approved` + `listing_rejected`. Use a `requestbin.com`-style temporary URL.
2. Approve a pending listing in WP admin → bin receives the `listing_approved` payload.
3. Reject a pending listing → bin receives the `listing_rejected` payload with `old_status` correctly populated.
4. Inspect `wp_postmeta` for the webhook log entries — both deliveries logged with `status: succeeded`.

---

## O3 — `wb_listora_map_provider` filter is decorative dead code

**File:** `wb-listora-pro/includes/features/class-google-maps.php:41`

(unchanged from prior plan version — applies the same Free→Pro upscale model)

**Evidence (read this session):**

```php
// Pro
add_filter( 'wb_listora_map_provider', array( $this, 'get_provider' ) );
```

```php
// Free — no apply_filters('wb_listora_map_provider', ...) anywhere.
// Free reads via wb_listora_get_setting('map_provider', 'osm') at:
//   class-pro-promotion.php:993
//   class-settings-controller.php:271
//   class-setup-wizard.php:311 / 671
```

Pro's filter listener never runs. The Google-Maps replacement *appears* to work because Pro's setup-wizard (T2 path) writes `map_provider = 'google'` straight into the `wb_listora_settings` option, and Free reads the option directly. The filter is architectural cosplay — neither side respects it.

**Two valid fixes — pick one:**

**Option A (preferred — Free fires the canonical filter):** wrap the provider resolution in Free at the right place. The cleanest insertion is inside `wb_listora_get_setting()` for the `map_provider` key, OR at the read sites that derive UI/REST from the provider. The pattern matches "Free is authoritative; Pro extends via Free's documented hooks." Pro's existing listener takes effect, and the filter becomes the canonical override — a future Pro feature could conditionally override per-request without writing to the option.

**Option B (drop the filter):** delete the listener at `class-google-maps.php:41`, drop `wb_listora_map_provider` from `wb-listora-pro/audit/manifest.json#/free_filters_hooked`. Document that provider is option-driven only. Smaller change but loses the override capability.

**Recommendation:** Option A. Aligns with the Free→Pro upscale model — Pro consumes a Free-fired hook, doesn't define a new contract.

**Verify after Option A:**
1. Set `map_provider` to `osm` → frontend uses OSM tiles.
2. Activate Pro Google Maps with API key → frontend uses Google.
3. Add `add_filter( 'wb_listora_map_provider', fn() => 'osm', 999 )` in a test mu-plugin → frontend reverts to OSM even with Google config.

---

## O4 — Manifest accuracy refresh (auto-resolved)

(unchanged from prior plan version)

1. **Phantom entry**: Pro's `audit/manifest.json#/free_filters_hooked` lists `wb_listora_credits_purchase_url`, but **no code anywhere** registers or fires this hook. Drop on next refresh.
2. **Index drift**: 10 hooks fired by Free AND hooked by Pro have empty `consumed_by` arrays in Free's `hooks_fired[]`. Phase 2.5.10 cross-plugin coupling cache didn't propagate during the 2026-04-30 refresh. Fix: next `/wp-plugin-onboard --refresh` will populate `consumed_by` from Pro's manifest and drop the phantom once the verifier doesn't find any registration. **No manual edits.**

---

## Why this fix shape (architectural rationale)

The user's guidance: "**journey is Free → Pro as upscale model. Pro uses Free all the time and should scale the same.**" Translated to this fix:

| Decision | Rationale |
|---|---|
| Use Free's existing `wb_listora_listing_status_changed` as the canonical hook | Free already fires it correctly. Inventing a new hook (e.g. `wb_listora_listing_approved`) would introduce a parallel contract — Pro would have to define + fire its own variant for each event. That's contract sprawl. |
| Same dispatcher pattern in Free's `class-notifications.php` and Pro's `class-outgoing-webhooks.php` | Pro literally adopts Free's pattern. Future readers see one shape, not two. |
| Don't gate Pro's webhook with the same `in_array($old, [pending, …])` Free uses for emails | Webhooks and emails have different audiences. Webhooks are infrastructure (CI/CD, automation, BI) — they want every transition. Emails are humans — they want only meaningful state changes. Different gates is correct, not inconsistency. |
| Leave `wb_listora_listing_expired` listener alone | Free's expiration cron fires it directly with `do_action( 'wb_listora_listing_expired', $post_id )`. Routing it through the status-transition dispatcher would double-fire (cron AND status-transition). The cron is the source of truth for expiration. |
| Don't introduce a `wb_listora_listing_status_changed` listener priority convention | Free uses 10, Pro uses 50 — same as today. Existing convention preserved. |

## Done criteria

For each task:
- F1: Free PR landed, 3 dead listeners replaced with single canonical dispatcher, manual verify of approve + reject + expire emails reaching the author inbox.
- P1: Pro PR landed, 2 dead listeners replaced with the same dispatcher pattern, manual verify of webhook deliveries via requestbin.
- O3: Free PR (Option A) landed, filter respected end-to-end via mu-plugin override.
- O4: next `/wp-plugin-onboard --refresh` confirms phantom dropped + 10 `consumed_by` arrays populated.

Update the Status column above with commit hash + PR link as each ships. Don't delete this file — it's the historical record.
