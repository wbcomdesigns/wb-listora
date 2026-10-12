---
journey: app-config-app-enabled-free-false
plugin: wb-listora
priority: critical
roles: [anonymous]
covers: [app-config-app-enabled, pro-only-app-gate, fail-closed, free-standalone-contract]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "wb-listora active (Pro may be active or not: step 2 removes Pro's filter in-process)"
estimated_runtime_minutes: 3
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [REST only]
changed: true
---

# Free declares `app_enabled: false`: the mobile app is a Pro benefit, and it fails closed

The native app is a Pro benefit. Free hardcodes `app_enabled => false` and ONLY Pro's `wb_listora_app_config` listener can flip it true, and then only for a site holding a valid license (`WBListoraPro\Features\License::is_valid()`, `wb-listora-pro/includes/class-app-config.php:38-48`). When it is false the app shows a "requires WB Listora Pro" screen and refuses to sign in, so it never runs against a free-only or unlicensed install.

The direction of the default is the point: it FAILS CLOSED. If Free ever computed this key optimistically (from `is_pro_active`, a feature toggle, anything other than a hard `false`), every free-only site would serve `app_enabled: true` the moment that signal flipped.

What this gate is NOT: `app_enabled` is a public boolean and trivially spoofable. It is a licensing/product gate on the CLIENT, never an authorization gate on the DATA; per-user authorization stays in the REST permission callbacks. A regression here is a licensing leak, not a data breach.

The licensed-site true path lives in Pro: `admin/18-app-config-license-gate.md`.

Single-role by nature: the endpoint is public (`permission_callback => __return_true`), so there is no owner/member/moderator ladder. Control: the same request as an administrator returns the same value (the payload is not user-dependent).

## Setup
- Site: `$SITE_URL`. Anonymous. No state is mutated (step 2's `remove_filter` is per-request).

## Steps

### 1. Free's source default is a hard `false`
- **Action**: `grep -n "'app_enabled'" includes/rest/class-settings-controller.php`
- **Expect**: exactly one hit, `includes/rest/class-settings-controller.php:604`, assigning the literal `false` (`'app_enabled'       => false,`). It must NOT be derived from `is_pro_active`, `wb_listora_feature_enabled()`, `class_exists()` or an option read.
- **On fail**: Free computed the gate. Free has no runtime dependency on Pro (INV-1) and cannot know license state.

### 2. Free's resolved payload says false, with Pro's filter out of the way (verified 2026-10-12)
Reproduces a free-only install on a combo site without deactivating Pro.
- **Action**:
  ```bash
  wp eval '
    remove_filter( "wb_listora_app_config", array( "WBListoraPro\\Pro_Plugin", "filter_app_config" ), 10 );
    $d = rest_do_request( new WP_REST_Request( "GET", "/listora/v1/settings/app-config" ) )->get_data();
    echo "app_enabled=" . var_export( $d["app_enabled"], true ) . "\n";
    echo "is_strict_bool=" . var_export( false === $d["app_enabled"], true ) . "\n";
    echo "is_pro_active=" . var_export( $d["is_pro_active"], true ) . "\n";
  '
  ```
- **Expect**: `app_enabled=false`, `is_strict_bool=true`, and `is_pro_active=true` on a combo site. That divergence is the contract: Pro being installed is not Pro being licensed. (`Pro_Plugin::filter_app_config` is a thin proxy kept so this `remove_filter` keeps working; the logic is in `WBListoraPro\App_Config`.)
- **On fail**: Free is deriving the gate from Pro's presence (step 1).

### 3. Over the wire, anonymously
- **Action**:
  ```bash
  curl -s "$SITE_URL/wp-json/listora/v1/settings/app-config" \
    | python3 -c "import sys,json; d=json.load(sys.stdin); print('app_enabled =', json.dumps(d['app_enabled']), '| strict bool:', isinstance(d['app_enabled'], bool))"
  ```
- **Expect**: on a free-only site `false`. On a combo site the live value is Pro's answer: `true` on a licensed site (the reference site returns `true`), `false` when the license is inactive. Do not assert `false` here in combo mode; step 2 is the free-only assertion.
- **POSITIVE CONTROL**: the combo reference site returning `true` here while step 2 returns `false` proves the gate is Pro-driven and that the default really is the fallback.
- **On fail (free-only site returning true)**: something other than Pro answered the filter; `grep -rn "wb_listora_app_config" wp-content/`.

### 4. JSON type is a real boolean
- **Action**: `curl -s "$SITE_URL/wp-json/listora/v1/settings/app-config" | grep -o '"app_enabled":[^,]*'`
- **Expect**: `"app_enabled":false` or `"app_enabled":true`, never `"0"`, `0`, `""` or `null` (strict-equality clients). Pro casts through `class_exists() && License::is_valid()`, which is boolean.

## Pass criteria
1. `'app_enabled'` is assigned the literal `false` in Free's source: one hit, no derivation.
2. With Pro's filter removed, the resolved payload is strictly `false` even when `is_pro_active` is `true`.
3. The endpoint serves `app_enabled` as a real JSON boolean.
4. A free-only site never serves `app_enabled: true`.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Source derives `app_enabled` from anything | fail-OPEN regression | `includes/rest/class-settings-controller.php:604` |
| Step 2 returns `true` | Free read Pro state, or another plugin hooked the filter | `class-settings-controller.php:516` `get_app_config()`; filter applied at `:693` |
| `"app_enabled":"0"` / `0` / `null` | a filter returned non-boolean | `wb-listora-pro/includes/class-app-config.php:44-45` |
| Key missing | dropped from `$data` | `class-settings-controller.php:540-680` |

Verified via: wp eval (step 2) and curl (steps 3-4) against http://wb-listora.local, plus source for step 1.
