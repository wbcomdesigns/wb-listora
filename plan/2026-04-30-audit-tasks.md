# 2026-04-30 audit follow-up tasks

Source: wppqa baseline + `bin/architecture-checks.sh` from the `/wp-plugin-onboard --refresh` run on 2026-04-30 (Free `691fd44`, Pro `759dc53`). Each task is verified file:line, not extrapolation.

## Tasks

| ID | Plugin | Type | Effort | Status |
|---|---|---|---|---|
| T1 | Free | UX (Rule 10) | 15 min | **shipped @ `f69f47f` (PR #25, 2026-04-30)** |
| T2 | Pro | Architecture (INV-4) | 60 min | **shipped @ `4f1f5f9` (PR #26, 2026-04-30)** |
| T3 | Pro | Enqueue check | 10 min | **shipped @ `4f1f5f9` (PR #26, 2026-04-30) — explicit dep added** |
| T4 | Free | Audit classification only | 5 min | **shipped @ `f69f47f` (PR #25, 2026-04-30)** |

**Done deltas:**
- T1: native `confirm()` → `listoraConfirm` modal, verified end-to-end in browser. Side-fix: `listora-confirm` was never enqueued by any block — added to `blocks/user-dashboard/render.php`.
- T2: `Settings_Helper` class built; INV-4 violation count `3 → 0`; `composer arch-checks` ✓. Verified via `wp-cli eval` round-trip on live DB.
- T3: explicit `'listora-confirm'` dep on `wb-listora-pro-dashboard-needs` script; native fallback retained.
- T4: false-positive classification recorded in `audit/manifest.json#/notes` and as inline comment at `class-pro-promotion.php:1188`.

Recommended order: **T4 → T1 → T3 → T2**. T4 has no code, T1 is one file, T3 may be a no-op, T2 is the biggest.

---

## T1 — Replace native `confirm()` on Deactivate listing

**File:** `wb-listora/src/interactivity/store.js:824`

**Evidence (read this session):**

```js
// line 820-824
const confirmMsg =
    ( window.listoraI18n && window.listoraI18n.confirmDeactivate ) ||
    'Deactivate this listing? It will be hidden from the public directory until you reactivate it.';
// eslint-disable-next-line no-alert
if ( ! window.confirm( confirmMsg ) ) {
```

**Context:** `actions.deactivateListing` on the user-dashboard Listings tab. `listoraConfirm` modal helper is enqueued globally via `class-assets.php:32-44` (handle `listora-confirm`), so it's loaded on every frontend Listora page.

**Fix:** replace lines 820-826 with the Promise-returning helper, keep the native call as a defensive fallback. Same pattern as Pro's `dashboard-needs.js:30`.

```js
const confirmed = window.listoraConfirm
    ? await window.listoraConfirm( {
          title: window.listoraI18n?.confirmDeactivateTitle || 'Deactivate listing?',
          message: confirmMsg,
          confirmLabel: window.listoraI18n?.deactivate || 'Deactivate',
          tone: 'danger',
      } )
    : window.confirm( confirmMsg );
if ( ! confirmed ) { return; }
```

Add the two new i18n keys (`confirmDeactivateTitle`, `deactivate`) to wherever `listoraI18n` is built (find via `grep -rn "confirmDeactivate" includes/`).

**Verify:**
1. `npm run build`
2. Open `/listora/dashboard/?tab=listings` as a listing owner.
3. Click Deactivate → custom modal (red confirm button), not native dialog.
4. Cancel → no API call. Confirm → POST to `/listora/v1/listings/:id/deactivate`, success toast, page reload.
5. Re-run `mcp__wp-plugin-qa__wppqa_check_plugin_dev_rules` → Rule 10 hit count drops by 1.

---

## T2 — Build `Settings_Helper` and route 3 direct `get_option` calls

**Files (verified this session):**
- `wb-listora-pro/includes/features/class-infinite-scroll.php:156` — read+write
- `wb-listora-pro/includes/admin/class-setup-wizard.php:197` — read+write (google_maps_key + map_provider)
- `wb-listora-pro/includes/admin/class-setup-wizard.php:593` — read-only (pre-fill wizard input)

**Why a fix-the-3-call-sites-only approach won't work:** the architecture contract (`plan/wb-listora-architecture-contract.md` Invariant 4) says Pro must read Free's settings via `Settings_Helper::get_companion(...)`. **That class doesn't exist** — `grep -lr "class Settings_Helper" includes/` returns only `class-email-helpers.php` (different class). The contract was written against a planned helper that was never authored.

**Fix steps:**

1. Create `wb-listora-pro/includes/class-settings-helper.php` with three static methods:
   - `Settings_Helper::get_free( string $key, $default = null )` — wrap `get_option('wb_listora_settings')` + filter `wb_listora_pro_free_setting`.
   - `Settings_Helper::set_free( string $key, $value ): bool` — single-key write.
   - `Settings_Helper::set_free_many( array $pairs ): bool` — atomic multi-key write (uses one `update_option` call).

2. Edit the 3 sites:
   - `class-infinite-scroll.php:155-158` → `Settings_Helper::set_free( self::SETTING_KEY, $value );`
   - `class-setup-wizard.php:197-200` → `Settings_Helper::set_free_many( [ 'google_maps_key' => $api_key, 'map_provider' => 'google' ] );`
   - `class-setup-wizard.php:593-594` → `$current_key = (string) Settings_Helper::get_free( 'google_maps_key', '' );`

3. Add an exclusion in `bin/architecture-checks.sh::check_a4_no_direct_option`: `grep -v "class-settings-helper.php:"` after the existing grep so the helper itself isn't flagged.

**Verify:**
- `composer arch-checks` → 0 INV-4 violations (down from 3).
- Settings → Pagination → choose Load More → save → DB shows `wb_listora_settings.pagination_type = "load_more"`.
- Run Pro Setup Wizard → Maps step → enter fake API key → save → re-open wizard → field is pre-filled.

---

## T3 — Verify `listora-confirm` is loaded on the Pro Needs Dashboard

**File:** `wb-listora-pro/src/dashboard-needs.js:39`

**Evidence (read this session):**

```js
// line 29-40
function confirmDialog( message ) {
    if ( typeof window.listoraConfirm === 'function' ) {
        return window.listoraConfirm( { ... } );
    }
    // Fallback to native confirm.
    return Promise.resolve( window.confirm( message ) );
}
```

**This is already correct defensive code** — primary path is the modal helper, native is fallback. The wppqa flag is mechanical.

**Verify before any edit:**
1. Read `wb-listora/includes/admin/class-admin.php:120-180` — confirm hook scope of `listora-confirm` enqueue. Does it cover Pro admin pages?
2. Open the Pro Needs Dashboard in browser → DevTools Console → `typeof window.listoraConfirm`.
   - **Returns `"function"`:** no code change. Add a one-line classification note to `audit/manifest.json#/notes` so the flag doesn't re-surface.
   - **Returns `"undefined"`:** add `'listora-confirm'` to the `$deps` array of the `dashboard-needs` script registration in `wb-listora-pro/includes/class-assets.php`. Native fallback stays.

Either branch is reversible.

---

## T4 — Classify `ajax_dismiss_promo` nonce-no-cap as false positive

**File:** `wb-listora/includes/admin/class-pro-promotion.php:1187-1210`

**Evidence (read this session):** `check_ajax_referer( 'wb_listora_promo', 'nonce' )` at line 1188, no `current_user_can(...)`. Action is registered at line 101 as `wp_ajax_wb_listora_dismiss_promo` only — **no `wp_ajax_nopriv_*` companion** (verified). The handler sets a 3-day cookie keyed on the requester's `surface` POST value.

**Why it's a false positive:**
1. `wp_ajax_*` without `_nopriv_` is logged-in-only by core. Anonymous requests get a 0-byte response upstream.
2. The action is per-user UI state — sets the requester's own cookie, no DB write, no shared mutation.
3. Any cap check would over-restrict (CTA is shown to all logged-in users; dismissal must match).

**Fix:** no code change. Document the classification:
- Add an entry under `audit/manifest.json#/notes` (or `static_analysis.notes`) recording the file:line, classification = `false_positive`, and the 1-line reason. The next refresh's wppqa pass will still flag it; the note ensures it isn't treated as new.
- Optionally add a one-line `// Per-user dismissal — wp_ajax_* gates to logged-in users; no cap needed` comment above line 1188 so a human reading the file understands.

---

## Out of scope for these tasks

These are gate hygiene, not bugs. Open separate plans only if pursued:

- 16 Free + 12 Pro `__return_true` REST callbacks need allowlisting in `bin/coding-rules-check.sh` (allowlist data, not code).
- Free's `composer.json` missing `phpcs` + `phpstan` script aliases (gate stages skip silently).
- Pro: 7 distinct CSS breakpoints vs the 3 sanctioned (admin-CSS sprint).

## Done criteria

For each task, "done" means:
- Code change committed via PR (not direct main push).
- The relevant verify step in this file passes.
- This file is updated: change `Status` column to "shipped @ <commit-hash>" with the date.
- The wppqa / arch-checks delta is captured (e.g., "Rule 10 hits: 2 → 1", "INV-4 violations: 3 → 0").

The plan file stays in `plan/` as the historical record. Don't delete on completion.
