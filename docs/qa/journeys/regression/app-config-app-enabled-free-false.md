---
journey: app-config-app-enabled-free-false
plugin: wb-listora
priority: critical
roles: [anonymous]
covers: [app-config-app-enabled, pro-only-app-gate, fail-closed, free-standalone-contract]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "wb-listora active (Pro may be active or not — step 2 removes Pro's filter in-process)"
estimated_runtime_minutes: 2
covers_commit: 23ad1b3
---

# Free declares `app_enabled: false` — the mobile app is a Pro benefit, and it fails closed

The native app is a Pro benefit. Free hardcodes `app_enabled => false` and **only** Pro's
`wb_listora_app_config` listener can flip it true, and then only for a site holding a valid
license. When it is false the app shows a "requires WB Listora Pro" screen and refuses to
sign in, so it never runs against a free-only or unlicensed install.

The direction of the default is the whole point: it **fails closed**. If Free ever computed
this key optimistically — from `is_pro_active`, from a feature toggle, from anything other
than a hard `false` — then every free-only site would start serving `app_enabled: true` the
moment that signal flipped, and the app would let users in on a site with no license. This
journey pins the default at the source and over the wire.

Note what this gate is NOT. `app_enabled` is a public boolean and therefore trivially
spoofable — it is a **licensing/product gate on the CLIENT, never an authorization gate on
the DATA**. Per-user authorization stays in the REST permission callbacks. A regression here
is a licensing leak, not a data breach; do not "fix" it by hardening this key, and do not
downgrade a REST permission callback because this key exists.

The licensed-site true path lives in Pro: `admin/18-app-config-license-gate.md`.

## Setup

- Site: `$SITE_URL`
- Anonymous. This journey mutates NO state (step 2's `remove_filter` is per-request only).

## Steps

### 1. Free's source default is a hard `false`
- **Action**:
  ```bash
  grep -n "'app_enabled'" includes/rest/class-settings-controller.php
  ```
- **Expect**: exactly one hit, and it assigns the literal `false`:
  `'app_enabled'             => false,`
  It must NOT be derived from `is_pro_active`, `wb_listora_feature_enabled()`,
  `class_exists()`, or any option read.
- **On fail**: `includes/rest/class-settings-controller.php` ~488 — Free computed the gate.
  Free has no runtime dependency on Pro (INV-1); it cannot know license state and must not
  guess. Revert to the literal `false`.

### 2. Free's resolved payload says false, with Pro's filter out of the way
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
- **Expect**: `app_enabled=false`, `is_strict_bool=true`.
  `is_pro_active` may be `true` (Pro IS installed) — and `app_enabled` must STILL be false.
  That divergence is the contract: **Pro being installed is not Pro being licensed.**
- **On fail**: Free is deriving the gate from Pro's presence — see step 1.

### 3. Over the wire, anonymously
- **Action**:
  ```bash
  curl -s "$SITE_URL/wp-json/listora/v1/settings/app-config" \
    | python3 -c "import sys,json; d=json.load(sys.stdin); print('app_enabled =', json.dumps(d['app_enabled']), '| strict false:', d['app_enabled'] is False)"
  ```
- **Expect**: on a **free-only** site → `false` / `strict false: True`.
  On a **combo** site the live value is Pro's answer and is only `false` when the license is
  inactive — do not assert `false` here in combo mode; step 2 is the free-only assertion.
  Record the value and let Pro's `admin/18-app-config-license-gate.md` own the true path.
- **On fail (free-only site returning true)**: something other than Pro answered the filter.
  Grep for `add_filter( 'wb_listora_app_config'` across `wp-content/`.

### 4. JSON type is a real boolean
- **Action**:
  ```bash
  curl -s "$SITE_URL/wp-json/listora/v1/settings/app-config" | grep -o '"app_enabled":[^,]*'
  ```
- **Expect**: `"app_enabled":false` or `"app_enabled":true` — never `"0"`, `0`, `""`, or `null`.
  A strict-equality client (`config.app_enabled === true`) must not be tricked by a truthy
  `"0"` string, and a loose one must not be tricked by a falsy `0` from a licensed site.
- **On fail**: a filter callback returned a non-boolean. Cast at the fire site.

## Pass criteria

ALL of the following hold:
1. `'app_enabled'` is assigned the literal `false` in Free's source — one hit, no derivation.
2. With Pro's filter removed, the resolved payload is **strictly** `false` even when
   `is_pro_active` is `true`.
3. The endpoint serves `app_enabled` as a real JSON boolean.
4. A free-only site never serves `app_enabled: true`.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Free's source derives `app_enabled` from anything | fail-OPEN regression — free-only sites would serve `true` | `includes/rest/class-settings-controller.php` ~488 |
| Step 2 returns `true` | Free read Pro state directly (INV-1 violation) or another plugin hooked the filter | `class-settings-controller.php::get_app_config()`; grep `add_filter( 'wb_listora_app_config'` |
| `"app_enabled":"0"` / `0` / `null` | a filter returned non-boolean; strict-equality clients misread | Pro `class-pro-plugin.php::filter_app_config` ~3124 — must cast to bool |
| Free-only site serves `true` | third-party/mu-plugin flipped the gate | grep `wb_listora_app_config` across `wp-content/` |
| Key missing entirely | dropped from `$data` — app cold-starts with no gate | `class-settings-controller.php::get_app_config()` ~488 |
