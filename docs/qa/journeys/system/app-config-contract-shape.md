---
journey: app-config-contract-shape
plugin: wb-listora
priority: critical
site_profile: required
roles: [guest, admin]
covers: [app-config-contract, contract_version, free-feature-flags, pro-flag-leak-guard, app-config-back-compat, wb_listora_app_config-request-arg, app-config-app-enabled, pro-only-app-gate, fail-closed, free-standalone-contract]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "wb-listora active (Pro may be active or not, see step 3)"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
themes: []
supersedes:
  - ora:system/app-config-app-enabled-fails-closed.md
---

# `GET /settings/app-config` serves the whole contract, and Free resolves ONLY free flags

## Why

`/listora/v1/settings/app-config` is the public bootstrap payload a native app reads before it can render or sign anyone in (`permission_callback => __return_true`, `includes/rest/class-settings-controller.php:93-103`). A missing key leaves the app with no config at cold start, and a resolved Pro flag from Free's reader shows a dead screen.

The sharp edge is the `features{}` resolver. Pro registers its toggles INTO Free's registry (`wb_listora_features_registry`) tagged `store => 'wb_listora_pro_features'`, but stores their VALUES in its own option. Resolving every registry row with Free's reader (`wb_listora_feature_enabled()`) returned the registry default for a Pro row (live symptom: `analytics: false` while Pro's option said `true`). `free_feature_flags()` (`class-settings-controller.php:710-727`) skips rows with `store !== 'free'`; Pro merges its real values in through the `wb_listora_app_config` filter (`wb-listora-pro/includes/class-app-config.php:38-60`), where it reads its own option.

So: Free's resolver emits exactly the free keys and never a Pro key, even though Pro rows sit in the registry it just read.

`app_enabled` fails closed in Free (steps 8-10 below); the license gate, Pro merge and payload hygiene live in Pro's tree (`wb-listora-pro: system/app-config-pro-merge.md`, which `extends` this journey).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Site: `$SITE_URL`; `$WP_PATH` for `wp` commands. Guest: send no cookies, no nonce. Mutates NO state.

## Steps

### 1. Anonymous GET returns 200
- **Action**: `curl -s -o "$WP_PATH/../qa-artifacts/appcfg.json" -w '%{http_code}\n' "$SITE_URL/wp-json/listora/v1/settings/app-config"`
- **Expect**: `200`; body is a JSON object (not an array, not a `code`/`message` error envelope).
- **On fail**: route `permission_callback` must be `__return_true` (`class-settings-controller.php:103`).

### 2. Every contract key is present (verified 2026-10-12: 32 keys)
- **Action**:
  ```bash
  python3 - "$WP_PATH/../qa-artifacts/appcfg.json" <<'PY'
  import json, sys
  d = json.load(open(sys.argv[1]))
  expected = ['contract_version','plugin_version','rest_namespace','directory_url',
      'submit_url','dashboard_url','per_page','distance_unit','near_me_radius',
      'currency','currency_symbol','currency_position','decimals','listing_statuses',
      'default_country','moderation','enable_claiming','enable_reviews','enable_favorites',
      'enable_captcha','captcha_provider','is_pro_active','contact_path','app_enabled',
      'min_app_version','branding','legal','features','auth','password_login',
      'languages','timezone']
  print('MISSING:', [k for k in expected if k not in d] or 'none')
  print('EXTRA:', sorted(set(d) - set(expected)) or 'none')
  print('enable_guest_submission present:', 'enable_guest_submission' in d)
  print('contract_version:', d.get('contract_version'), '| min_app_version:', d.get('min_app_version'))
  print('branding keys:', sorted(d.get('branding', {})))
  print('legal keys:', sorted(d.get('legal', {})))
  print('types ok:', isinstance(d.get('branding'), dict), isinstance(d.get('legal'), dict),
        isinstance(d.get('features'), dict), isinstance(d.get('app_enabled'), bool),
        isinstance(d.get('listing_statuses'), list), isinstance(d.get('auth'), dict))
  PY
  ```
- **Expect**:
  - `MISSING: none` (32 keys) and `enable_guest_submission present: False` (removed in 1.6.0 with guest submission; must stay absent). `EXTRA` should be `none`: a new key means the contract grew and this list and the app must be updated together.
  - `contract_version` is the integer `1` (`Settings_Controller::APP_CONTRACT_VERSION`, `:37`); `min_app_version` is `'0.0.0'` unless the site raised the floor.
  - `branding` keys exactly `accent_color`, `logo_url`, `login_bg_url`; `legal` keys exactly `abuse_contact_email`, `community_guidelines_url`, `privacy_policy_url`, `terms_url`.
  - `listing_statuses` is an ordered list of `{slug, label}` (`draft`, `pending`, `publish`, ... from `Status_Manager::get_statuses()`); `currency_symbol` / `currency_position` / `decimals` agree with `wb_listora_get_currency_format()` (read both, assert equal); `near_me_radius` equals the Settings > General start value (`wb_listora_get_setting`).
  - `auth` carries `social_providers`, `twofactor`, `register`, `app_passwords_available`, `connect_url`, `connect_schemes`; `password_login` is a boolean.
  - `contact_path` is `/listings/{id}/contact-form` (the single contact route since 2.0.0; Pro's old `/contact` is deprecated).
  - `types ok: True True True True True True`.
- **On fail**: a key was dropped from `$data` in `get_app_config()` (`:540-680`), or a filter callback returned a partial array instead of merging.

### 3. Free's resolver emits exactly the free keys and NO Pro leak (the sentinel; verified)
Runs in-process with Pro's app-config filter removed but Pro's registry filter still attached: the free-only resolve path on a combo site, per-request only.
- **Action**:
  ```bash
  wp eval '
    remove_filter( "wb_listora_app_config", array( "WBListoraPro\\Pro_Plugin", "filter_app_config" ), 10 );
    $reg = wb_listora_features_registry();
    $pro_rows = array_filter( $reg, function( $c ) { return ( $c["store"] ?? "free" ) !== "free"; } );
    $free_rows = array_filter( $reg, function( $c ) { return ( $c["store"] ?? "free" ) === "free"; } );
    $f = rest_do_request( new WP_REST_Request( "GET", "/listora/v1/settings/app-config" ) )->get_data()["features"];
    echo "registry_total=" . count( $reg ) . " free_rows=" . count( $free_rows ) . " pro_rows_in_registry=" . count( $pro_rows ) . "\n";
    echo "free_flag_count=" . count( $f ) . "\n";
    echo "free_keys=" . implode( ",", array_keys( $f ) ) . "\n";
    echo "leaked=" . implode( ",", array_intersect( array_keys( $f ), array_keys( $pro_rows ) ) ) . "\n";
  '
  ```
- **Expect**: `free_flag_count` equals `free_rows`; `free_keys` is the Free registry (`submission,reviews,claims,favorites,contact_form,renewal,owner_name,report_listings,schema,opengraph,breadcrumbs,sitemap` at 2.0.0); `leaked=` empty.
  - The Pro row count moves as Pro adds toggles; the assertion is `pro_rows_in_registry > 0` and `free_flag_count == free_rows`. If `pro_rows_in_registry` is `0` the journey proved nothing (Pro's registry filter is not attached): treat as INCONCLUSIVE, not PASS, and check Pro is active.
- **On fail**: `free_feature_flags()` lost its `if ( 'free' !== $store ) { continue; }` guard (`:720`): the original bug is back.

### 4. A Pro-stored flag is NOT answered by Free's reader (verified)
- **Action**: `wp eval '$reg = wb_listora_features_registry(); echo "analytics_store=" . ( $reg["analytics"]["store"] ?? "free" ) . "\n"; echo "free_reader=" . var_export( wb_listora_feature_enabled( "analytics" ), true ) . "\n"; echo "pro_reader=" . var_export( wb_listora_pro_feature_enabled( "analytics" ), true ) . "\n";'`
- **Expect**: `analytics_store=wb_listora_pro_features`; `pro_reader` matches Pro's stored option. `free_reader` may legitimately disagree (Free's reader does not know Pro's option); that disagreement is WHY `free_feature_flags()` must skip the row. The contract assertion is step 3's `leaked=` being empty; the served value is Pro's job (`system/app-config-pro-merge.md`).
- **On fail**: `analytics_store=free` means Pro stopped tagging `store` in `register_pro_features_on_free_screen`; Free would then wrongly resolve it.

### 5. Back-compat keys survive (verified)
Pre-1.2.3 clients read the `enable_*` spelling.
- **Action**: from the saved payload, print `enable_claiming`, `enable_reviews`, `enable_favorites`, `is_pro_active` and compare each with `features.claims`, `features.reviews`, `features.favorites`.
- **Expect**: all four present and boolean; each `enable_*` equals its `features{}` twin (both resolve through `wb_listora_feature_enabled()`).
- **On fail**: a legacy key was dropped (`class-settings-controller.php:567-574`); a shipped client breaks. They may only go in a major.

### 6. The filter receives `$request` as its 2nd arg (verified)
- **Action**: `wp eval 'add_filter( "wb_listora_app_config", function( $data, $request = null ) { $GLOBALS["qa_t"] = is_object( $request ) ? get_class( $request ) : gettype( $request ); $GLOBALS["qa_r"] = is_object( $request ) ? $request->get_route() : "n/a"; return $data; }, 99, 2 ); rest_do_request( new WP_REST_Request( "GET", "/listora/v1/settings/app-config" ) ); echo "arg2_type=" . $GLOBALS["qa_t"] . " route=" . $GLOBALS["qa_r"] . "\n";'`
- **Expect**: `arg2_type=WP_REST_Request route=/listora/v1/settings/app-config`.
- **On fail**: `apply_filters( 'wb_listora_app_config', $data, $request )` (`:693`) lost its 2nd arg.

### 7. Role ladder: the same keys for every role
- **Action**: run step 1-2's key check as {guest}, {owner} (Application Password or `wp eval` with `wp_set_current_user`), {editor} and {admin}.
- **Expect**: identical key set and identical `features`/`branding`/`legal` for all four; nothing role-dependent is merged into this public payload.
- **Read-back**: diff the four JSON bodies minus volatile keys (none expected).
- Not verified live (added by consolidation).

### 8. `app_enabled` fails closed: Free's source default is a hard `false`
Free hardcodes `app_enabled => false`; only Pro's `wb_listora_app_config` listener can flip it true, and only for a valid license (`WBListoraPro\Features\License::is_valid()`). If Free ever derived it from `is_pro_active`, a toggle or anything but a literal `false`, every free-only site would serve `true`: a licensing leak. The key is a public, spoofable client gate; per-user authorization stays in the REST permission callbacks.
- **Action**: `grep -n "'app_enabled'" includes/rest/class-settings-controller.php`.
- **Expect**: exactly one hit assigning the literal `false`; NOT derived from `is_pro_active`, `wb_listora_feature_enabled()`, `class_exists()` or an option read.

### 9. Free's resolved payload says false with Pro's filter out of the way
- **Action**: `wp eval 'remove_filter( "wb_listora_app_config", array( "WBListoraPro\\Pro_Plugin", "filter_app_config" ), 10 ); $d = rest_do_request( new WP_REST_Request( "GET", "/listora/v1/settings/app-config" ) )->get_data(); echo "app_enabled=" . var_export( $d["app_enabled"], true ) . "\nis_strict_bool=" . var_export( false === $d["app_enabled"], true ) . "\nis_pro_active=" . var_export( $d["is_pro_active"], true ) . "\n";'`
- **Expect**: `app_enabled=false`, `is_strict_bool=true`, and `is_pro_active=true` on a combo site (Pro installed is not Pro licensed). `Pro_Plugin::filter_app_config` is a thin proxy kept so this `remove_filter` works; the logic is in `WBListoraPro\App_Config`.

### 10. Over the wire, anonymously, and as a real boolean
- **Action**: `curl -s $SITE_URL/wp-json/listora/v1/settings/app-config | grep -o '"app_enabled":[^,]*'` as {guest}, then as {admin}.
- **Expect**: free-only site: `"app_enabled":false`; combo site: Pro's answer (`true` when licensed, `false` when not; do not assert `false` in combo mode); never `"0"`, `0`, `""` or `null`; identical for {guest} and {admin}.
- **Positive control**: a licensed combo site returning `true` here while step 9 returns `false` proves the gate is Pro-driven and the default is the fallback.
- **On fail** (free-only site returning true): something else answered the filter: `grep -rn "wb_listora_app_config" wp-content/`.

### 11. No PHP notices
- **Action**: `tail -50 $WP_PATH/wp-content/debug.log`
- **Expect**: zero new Notice/Warning/Fatal from `class-settings-controller.php`.

## Role ladder

| Actor | Request | Expect |
|---|---|---|
| {guest} | `GET /settings/app-config` | 200, full contract |
| {owner}, {editor} | same | 200, identical body |
| {admin} (control) | same | 200, identical body |

## Pass criteria
1. Anonymous GET returns 200 with a JSON object.
2. All 32 contract keys present, no extras, `enable_guest_submission` absent, `contract_version === 1`, `min_app_version === '0.0.0'` (unless raised), `branding` and `legal` full shape, `app_enabled` a real boolean.
3. With Pro's app-config filter removed and Pro rows in the registry, Free returns exactly its free keys and zero Pro keys.
4. Back-compat `enable_*` and `is_pro_active` present, boolean, agreeing with `features{}`.
5. `wb_listora_app_config` is passed a `WP_REST_Request` as 2nd arg.
6. Every role sees the same payload.
7. debug.log clean.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| 401/403 anonymous | route lost `__return_true` | `includes/rest/class-settings-controller.php:93-103` |
| A contract key missing | dropped from `$data`, or filter returned a partial array | `class-settings-controller.php:516-700` |
| `leaked=` non-empty / `free_flag_count` > free rows | store guard gone | `class-settings-controller.php:710-727` |
| `free_flag_count` < free rows | a free toggle left the registry or was retagged | `includes/class-features.php` |
| `pro_rows_in_registry=0` | Pro inactive / registry filter unhooked: INCONCLUSIVE | `wb-listora-pro/includes/class-pro-plugin.php` `register_pro_features_on_free_screen` |
| `contract_version` drifts | payload hand-edited, not `APP_CONTRACT_VERSION` | `class-settings-controller.php:37,540` |
| `arg2_type=NULL` | `$request` not passed | `class-settings-controller.php:693` |

## Not verified
Steps 1-6 executed 2026-10-12 via curl and wp eval; step 7 (role ladder) and 8 (debug.log) were not run.

## Cleanup
None: the journey mutates nothing (step 6 adds its filter in-process only).
