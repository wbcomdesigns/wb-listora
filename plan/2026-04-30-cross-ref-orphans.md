# 2026-04-30 cross-ref orphan tasks

Source: cross-referenced wb-listora ↔ wb-listora-pro manifests after the audit-tasks PRs (#25 Free, #26 Pro) shipped. Every line:file evidence below was re-grep'd this session.

## Tasks

| ID | Plugin | Type | Customer impact | Effort | Status |
|---|---|---|---|---|---|
| O1 | Pro | Bug — outgoing webhook never fires on approve | High (silent webhook miss) | 20 min | pending |
| O2 | Pro | Bug — outgoing webhook never fires on reject | High (silent webhook miss) | 10 min | pending |
| O3 | Free + Pro | Architecture coherence — dead filter | Low (feature still works via option write) | 30 min | pending |
| O4 | Audit-only | Manifest accuracy refresh | None — audit hygiene | auto on next refresh | pending |

Order: **O1 → O2** (same-file, single PR), then **O3** separately, then **O4** is auto.

---

## O1 — Outgoing webhook on listing approve never fires

**File:** `wb-listora-pro/includes/features/class-outgoing-webhooks.php:150`

**Evidence (read this session):**

```php
// Listing approved (status transition to publish).
add_action( 'wb_listora_listing_publish', array( $this, 'on_listing_approved' ), 50, 2 );
```

The hook `wb_listora_listing_publish` is **never fired** anywhere — Free, Pro, or core. Verified:
```
grep -rE "(do_action|apply_filters)\s*\(\s*['\"]wb_listora_listing_publish['\"]"
  → 0 hits across both plugins
```

What Free **does** fire is `wb_listora_listing_status_changed( $post_id, $new, $old )` at `wb-listora/includes/search/class-search-indexer.php:553`.

**Fix:** swap the listener to consume the existing hook with status filtering:

```php
// before
add_action( 'wb_listora_listing_publish', array( $this, 'on_listing_approved' ), 50, 2 );

// after
add_action( 'wb_listora_listing_status_changed', array( $this, 'on_listing_status_changed' ), 50, 3 );
```

Then in `on_listing_status_changed( $post_id, $new, $old )`, dispatch only when `$new === 'publish' && $old !== 'publish'` (avoids re-firing on inconsequential same-status updates). Reuse `on_listing_approved`'s body as the inner handler.

**Verify:**
1. Approve a pending listing in WP admin (or change status via REST).
2. Outgoing webhook hits the configured URL with the listing payload.
3. Re-saving an already-published listing does NOT fire the webhook (idempotent).

---

## O2 — Outgoing webhook on listing reject never fires

**File:** `wb-listora-pro/includes/features/class-outgoing-webhooks.php:153`

**Evidence (read this session):**

```php
// Listing rejected (status transition to listora_rejected).
add_action( 'wb_listora_listing_listora_rejected', array( $this, 'on_listing_rejected' ), 50, 2 );
```

The hook name `wb_listora_listing_listora_rejected` has "listora" twice — looks like a transposed `{old_status}_{new_status}` core-pattern attempt that ended up doubled. Verified:
```
grep -rE "(do_action|apply_filters)\s*\(\s*['\"]wb_listora_listing_listora_rejected['\"]"
  → 0 hits
grep -rE "(do_action|apply_filters)\s*\(\s*['\"]wb_listora_listing_rejected['\"]"
  → 0 hits  (the un-doubled name doesn't fire either)
```

**Fix:** roll into the same `on_listing_status_changed` handler from O1 — when `$new === 'listora_rejected'`, dispatch the rejection webhook. Single listener, two branches.

**Verify:** reject a pending listing → rejection webhook hits the URL with the payload.

---

## O3 — `wb_listora_map_provider` filter is decorative dead code

**File:** `wb-listora-pro/includes/features/class-google-maps.php:41`

**Evidence (read this session):**

```php
// Pro
add_filter( 'wb_listora_map_provider', array( $this, 'get_provider' ) );
```

```php
// Free — no apply_filters('wb_listora_map_provider', ...) anywhere.
// Free reads via wb_listora_get_setting('map_provider', 'osm'):
//   class-pro-promotion.php:993
//   class-settings-controller.php:271
//   class-setup-wizard.php:311 / 671
```

Pro's filter listener never runs. The Google-Maps replacement *appears* to work because Pro's setup-wizard (T2 path) writes `map_provider = 'google'` straight into the `wb_listora_settings` option, and Free reads the option directly. The filter is architectural cosplay — neither side respects it.

**Two valid fixes — pick one:**

**Option A (preferred — preserve the filter contract):** change Free's `wb_listora_get_setting()` call sites for `map_provider` to wrap the resolved value in `apply_filters('wb_listora_map_provider', $value)`. Pro's existing listener then takes effect, and the filter becomes the canonical override path. Cleaner: a future Pro feature could conditionally override the provider per-request without writing to the option.

**Option B (drop the filter):** delete the listener at `class-google-maps.php:41`, drop `wb_listora_map_provider` from `wb_listora_pro/audit/manifest.json#/free_filters_hooked`. Document that provider is option-driven only. Smaller change but loses the override capability.

**Recommendation:** Option A. Pro's setup-wizard already pre-populates the option correctly (T2 wrote that path), so Option A is purely additive — the filter starts running for the first time, but for the only existing listener (Pro's Google_Maps), the result matches what's already in the option. No behaviour change for any current user; future flexibility gained.

**Verify after Option A:**
1. Set `map_provider` to `osm` in settings → frontend uses OSM tiles.
2. Activate Pro Google Maps with API key → frontend uses Google.
3. Add `add_filter('wb_listora_map_provider', fn() => 'osm', 999);` in a test mu-plugin → frontend reverts to OSM even with Google config (proves filter is now respected).

---

## O4 — Manifest accuracy refresh (auto-resolved)

Two issues, neither needs hand-editing — they're refresh-cache deltas:

1. **Phantom entry**: Pro's `audit/manifest.json#/free_filters_hooked` lists `wb_listora_credits_purchase_url`, but **no code anywhere** registers or fires this hook. Verified by `grep -rE "wb_listora_credits_purchase_url"` returning 0 hits in both plugins. Drop on next refresh.

2. **Index drift**: 10 hooks are correctly fired by Free AND hooked by Pro, but Free's `hooks_fired[].consumed_by` array is empty for them. Phase 2.5.10 cross-plugin coupling cache didn't propagate Pro's claims back into Free's manifest during the 2026-04-30 refresh. Hooks affected: `wb_listora_after_listing_fields`, `wb_listora_card_actions`, `wb_listora_dashboard_sections`, `wb_listora_map_config`, `wb_listora_review_criteria`, `wb_listora_search_args`, `wb_listora_send_notification`, `wb_listora_settings_tabs`, `wb_listora_show_dashboard_pro_cta` — plus the phantom from #1.

**Fix:** running `/wp-plugin-onboard --refresh` on Free will populate `consumed_by` from Pro's manifest (Phase 2.5.10), and running it on Pro will drop the phantom entry once the verifier doesn't find any registration. **No manual edits.** Capture as a checklist item for the next refresh.

---

## Done criteria

For each task:
- O1 + O2: PR landed, listener uses `wb_listora_listing_status_changed`, manual end-to-end verification of approve + reject webhook dispatch.
- O3: PR landed (Option A or B picked), filter respected end-to-end (or removed and documented).
- O4: next refresh's diff confirms phantom dropped + 10 `consumed_by` arrays populated.

Update the Status column above with commit hash + PR link as each ships. Don't delete this file — it's the historical record.
