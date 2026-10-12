---
journey: app-config-app-enabled-fails-closed
plugin: wb-listora
priority: critical
roles: [anonymous, administrator]
covers: [app-config-app-enabled, pro-only-app-gate, fail-closed, free-standalone-contract]
prerequisites:
  - "Site reachable at $SITE_URL; WP-CLI at $WP_PATH"
  - "wb-listora active (Pro may be active or not: step 2 removes Pro's filter in-process)"
estimated_runtime_minutes: 3
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
themes: []
supersedes:
  - ora:regression/app-config-app-enabled-free-false.md
fails_on: unknown (pre-fix sha not recorded)
site_profile: required
---

# Free declares `app_enabled: false`: the mobile app is a Pro benefit and the default fails closed

## Why
Free hardcodes `app_enabled => false`; only Pro's `wb_listora_app_config` listener can flip it true, and only for a valid license (`WBListoraPro\Features\License::is_valid()`). When false the app shows a "requires WB Listora Pro" screen and refuses to sign in. If Free ever computed the key optimistically (from `is_pro_active`, a toggle, anything but a literal `false`), every free-only site would serve `true` the moment that signal flipped: a licensing leak, not a data breach. The key is a public, spoofable client gate; per-user authorization stays in the REST permission callbacks. The licensed true path lives in Pro (`pro:admin/18-app-config-license-gate.md`); the rest of the payload contract in `system/app-config-contract-shape.md` (area F2, which only asserts the key is a boolean).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Anonymous; no state mutated (step 2's `remove_filter` is per-request).

## Steps

### 1. Free's source default is a hard `false`
- **Action**: `grep -n "'app_enabled'" includes/rest/class-settings-controller.php`
- **Expect**: exactly one hit assigning the literal `false`; NOT derived from `is_pro_active`, `wb_listora_feature_enabled()`, `class_exists()` or an option read.

### 2. Free's resolved payload says false with Pro's filter out of the way
- **Action**: `wp eval 'remove_filter( "wb_listora_app_config", array( "WBListoraPro\\Pro_Plugin", "filter_app_config" ), 10 ); $d = rest_do_request( new WP_REST_Request( "GET", "/listora/v1/settings/app-config" ) )->get_data(); echo "app_enabled=" . var_export( $d["app_enabled"], true ) . "\nis_strict_bool=" . var_export( false === $d["app_enabled"], true ) . "\nis_pro_active=" . var_export( $d["is_pro_active"], true ) . "\n";'`
- **Expect**: `app_enabled=false`, `is_strict_bool=true`, and `is_pro_active=true` on a combo site (Pro installed is not Pro licensed). `Pro_Plugin::filter_app_config` is a thin proxy kept so this `remove_filter` works; the logic is in `WBListoraPro\App_Config`.

### 3. Over the wire, anonymously
- **Action**: `curl -s $SITE_URL/wp-json/listora/v1/settings/app-config | python3 -c "import sys,json; d=json.load(sys.stdin); print('app_enabled =', json.dumps(d['app_enabled']), '| strict bool:', isinstance(d['app_enabled'], bool))"`
- **Expect**: free-only site: `false`. Combo site: Pro's answer (`true` when licensed, `false` when not); do not assert `false` in combo mode.
- **Positive control**: a licensed combo site returning `true` here while step 2 returns `false` proves the gate is Pro-driven and the default is the fallback.
- **On fail** (free-only site returning true): something else answered the filter: `grep -rn "wb_listora_app_config" wp-content/`.

### 4. JSON type is a real boolean
- **Action**: `curl -s $SITE_URL/wp-json/listora/v1/settings/app-config | grep -o '"app_enabled":[^,]*'`
- **Expect**: `"app_enabled":false` or `true`, never `"0"`, `0`, `""` or `null`.

### 5. Role ladder control
- **Action**: the same GET as {admin}.
- **Expect**: identical value (the payload is not user-dependent).

## Role ladder

| Rung | Expect |
|---|---|
| {guest} | steps 1-4 (endpoint is `permission_callback => __return_true`) |
| {admin} | same value (control) |

## Pass criteria
1. One hit, literal `false`, no derivation.
2. With Pro's filter removed the payload is strictly `false` even when `is_pro_active` is `true`.
3. `app_enabled` is a real JSON boolean.
4. A free-only site never serves `true`.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Source derives the key | fail-OPEN regression | `includes/rest/class-settings-controller.php` |
| Step 2 returns true | Free read Pro state or another plugin hooked the filter | `Settings_Controller::get_app_config()`, filter application |
| `"app_enabled":"0"` / `0` / `null` | a filter returned non-boolean | `wb-listora-pro/includes/class-app-config.php` |
| Key missing | dropped from `$data` | `class-settings-controller.php` |

## Not verified
None of the steps need mutation; step 5 was not run.

## Cleanup
None.
