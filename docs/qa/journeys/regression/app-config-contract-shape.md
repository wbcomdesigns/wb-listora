---
journey: app-config-contract-shape
plugin: wb-listora
priority: critical
roles: [anonymous]
covers: [app-config-contract, contract_version, free-feature-flags, pro-flag-leak-guard, app-config-back-compat, wb_listora_app_config-request-arg]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "wb-listora active (Pro may be active or not — see step 3)"
estimated_runtime_minutes: 3
covers_commit: 23ad1b3
---

# `GET /settings/app-config` serves the whole contract, and Free resolves ONLY free flags

`/listora/v1/settings/app-config` is the public bootstrap payload a native app reads
before it can render anything — or sign anyone in. It is `permission_callback =>
__return_true`, so it must answer 200 to an anonymous client, and every key the app
branches on must be present on EVERY response. A missing key is not a cosmetic
regression: the app has no config to fall back to at cold start.

The sharp edge this journey guards is the `features{}` resolver. Pro registers its 20
toggles **into Free's registry** (`wb_listora_features_registry`) tagged
`store => 'wb_listora_pro_features'`, but stores their VALUES in its own option. The
first cut of this endpoint resolved every registry row with Free's reader
(`wb_listora_feature_enabled()`), which reads Free's option — so a Pro row came back as
the registry default. Live symptom: the payload said `analytics: false` while Pro's
option said `true`, and an app that gated on it hid a feature the site had switched on.
The fix filters the registry to `store === 'free'` rows; Pro merges its real values in
through the `wb_listora_app_config` filter, where it reads its own option.

So: **Free's resolver must emit exactly the 10 free keys and never a Pro key** — even
though 20 Pro rows are sitting in the registry it just read. That is what step 3 pins.

`app_enabled` has its own journey (`app-config-app-enabled-free-false.md`); the license
gate + Pro merge live in Pro's tree (`admin/18-app-config-license-gate.md`,
`regression/app-config-pro-flags-branding-merge.md`).

## Setup

- Site: `$SITE_URL`
- Anonymous — send no cookies, no nonce. This journey mutates NO state.

## Steps

### 1. Anonymous GET returns 200
- **Action**:
  ```bash
  curl -s -o /tmp/appcfg.json -w '%{http_code}\n' "$SITE_URL/wp-json/listora/v1/settings/app-config"
  ```
- **Expect**: `200`. Body is a JSON object (not an array, not a `code`/`message` error envelope).
- **On fail**: `includes/rest/class-settings-controller.php` ~line 84 — the route's
  `permission_callback` must be `__return_true`.

### 2. Every contract key is present
- **Action**:
  ```bash
  python3 - <<'EOF'
  import json
  d = json.load(open('/tmp/appcfg.json'))
  expected = ['contract_version','plugin_version','rest_namespace','directory_url',
      'submit_url','dashboard_url','per_page','distance_unit','currency',
      'default_country','moderation','enable_claiming','enable_guest_submission',
      'enable_reviews','enable_favorites','enable_captcha','captcha_provider',
      'is_pro_active','app_enabled','min_app_version','branding','legal','features',
      'languages','timezone']
  missing = [k for k in expected if k not in d]
  print('MISSING:', missing or 'none')
  print('contract_version:', d.get('contract_version'))
  print('min_app_version:', d.get('min_app_version'))
  print('branding keys:', sorted(d.get('branding', {})))
  print('legal keys:', sorted(d.get('legal', {})))
  print('types ok:', isinstance(d.get('branding'), dict), isinstance(d.get('legal'), dict),
        isinstance(d.get('features'), dict), isinstance(d.get('app_enabled'), bool))
  EOF
  ```
- **Expect**:
  - `MISSING: none` — all 25 top-level keys present.
  - `contract_version` is the integer `1` (matches `Settings_Controller::APP_CONTRACT_VERSION`).
  - `min_app_version` is `'0.0.0'` on a site that has not raised the floor.
  - `branding` keys are exactly `accent_color`, `logo_url`, `login_bg_url`.
  - `legal` keys are exactly `abuse_contact_email`, `community_guidelines_url`,
    `privacy_policy_url`, `terms_url`.
  - `types ok: True True True True` — `branding`/`legal`/`features` are JSON objects and
    `app_enabled` is a real boolean, not `0`/`1`/`""`.
- **On fail**: a key was dropped from the `$data` array in
  `class-settings-controller.php::get_app_config()`, or a filter callback returned a
  partial array instead of merging.

### 3. Free's resolver emits exactly the 10 free keys — NO Pro leak (the sentinel)
Run in-process with **Pro's app-config filter removed but Pro's registry filter still
attached**. That is precisely the free-only resolve path, reproduced on a combo site
without deactivating Pro, and it is non-destructive (per-request only — nothing is
written).

- **Action**:
  ```bash
  wp eval '
    remove_filter( "wb_listora_app_config", array( "WBListoraPro\\Pro_Plugin", "filter_app_config" ), 10 );
    $reg = wb_listora_features_registry();
    $pro_rows = array_filter( $reg, function( $c ) { return ( $c["store"] ?? "free" ) !== "free"; } );
    $r = rest_do_request( new WP_REST_Request( "GET", "/listora/v1/settings/app-config" ) );
    $f = $r->get_data()["features"];
    echo "registry_total=" . count( $reg ) . " pro_rows_in_registry=" . count( $pro_rows ) . "\n";
    echo "free_flag_count=" . count( $f ) . "\n";
    echo "free_keys=" . implode( ",", array_keys( $f ) ) . "\n";
    echo "leaked=" . implode( ",", array_intersect( array_keys( $f ), array_keys( $pro_rows ) ) ) . "\n";
  '
  ```
- **Expect**:
  - `pro_rows_in_registry` is `20` — proving the guard is under real load. **If this is
    `0`, the journey proved nothing** (Pro's registry filter is not attached): treat as
    inconclusive, not PASS, and check Pro is active.
  - `free_flag_count=10`
  - `free_keys=submission,reviews,claims,favorites,renewal,report_listings,schema,opengraph,breadcrumbs,sitemap`
  - `leaked=` (empty) — not one Pro key resolved through Free's reader.
- **On fail**: `class-settings-controller.php::free_feature_flags()` lost its
  `if ( 'free' !== $store ) { continue; }` guard — the original bug is back.

### 4. A Pro-stored flag is NOT answered by Free's reader
Pins the exact false-value symptom, not just the key set.

- **Action**:
  ```bash
  wp eval '
    $reg = wb_listora_features_registry();
    echo "analytics_store=" . ( $reg["analytics"]["store"] ?? "free" ) . "\n";
    echo "free_reader=" . var_export( wb_listora_feature_enabled( "analytics" ), true ) . "\n";
    echo "pro_reader=" . var_export( wb_listora_pro_feature_enabled( "analytics" ), true ) . "\n";
  '
  ```
- **Expect**: `analytics_store=wb_listora_pro_features`. The two readers may legitimately
  disagree (Free's reader does not know Pro's option) — that disagreement is WHY
  `free_feature_flags()` must skip the row. Record both values; the contract assertion is
  step 3's `leaked=` being empty, and the served value is Pro's job
  (`regression/app-config-pro-flags-branding-merge.md` step 2).
- **On fail**: `analytics_store=free` means Pro's `register_pro_features_on_free_screen`
  stopped tagging `store` — Free would then legitimately (and wrongly) resolve it.

### 5. Back-compat keys survive
Pre-1.2.3 clients read the `enable_*` spelling. They must keep working.

- **Action**:
  ```bash
  python3 - <<'EOF'
  import json
  d = json.load(open('/tmp/appcfg.json'))
  for k in ['enable_claiming','enable_reviews','enable_favorites','is_pro_active']:
      print(k, '=', json.dumps(d.get(k)), '| bool:', isinstance(d.get(k), bool))
  f = d.get('features', {})
  print('claims/enable_claiming agree:', f.get('claims') == d.get('enable_claiming'))
  print('reviews/enable_reviews agree:', f.get('reviews') == d.get('enable_reviews'))
  print('favorites/enable_favorites agree:', f.get('favorites') == d.get('enable_favorites'))
  EOF
  ```
- **Expect**: all four keys present and boolean; each `enable_*` agrees with its
  `features{}` twin (both resolve through `wb_listora_feature_enabled()`).
- **On fail**: `get_app_config()` dropped a legacy key — a shipped client breaks. These
  are contract, not decoration; they may only go in a major.

### 6. The filter receives `$request` as a 2nd arg
- **Action**:
  ```bash
  wp eval '
    add_filter( "wb_listora_app_config", function( $data, $request = null ) {
      $GLOBALS["qa_t"] = is_object( $request ) ? get_class( $request ) : gettype( $request );
      $GLOBALS["qa_r"] = is_object( $request ) ? $request->get_route() : "n/a";
      return $data;
    }, 99, 2 );
    rest_do_request( new WP_REST_Request( "GET", "/listora/v1/settings/app-config" ) );
    echo "arg2_type=" . $GLOBALS["qa_t"] . " route=" . $GLOBALS["qa_r"] . "\n";
  '
  ```
- **Expect**: `arg2_type=WP_REST_Request route=/listora/v1/settings/app-config`.
- **On fail**: `apply_filters( 'wb_listora_app_config', $data, $request )` in
  `get_app_config()` lost its 2nd arg — Pro's `filter_app_config( $data, $request )` and
  every site-owner callback that varies config per-request go blind.

### 7. No PHP notices
- **Action**: `tail -50 wp-content/debug.log`
- **Expect**: zero new Notice/Warning/Fatal from `class-settings-controller.php`.

## Pass criteria

ALL of the following hold:
1. Anonymous GET returns **200** with a JSON object.
2. All 25 contract keys present; `contract_version === 1`; `min_app_version === '0.0.0'`;
   `branding` + `legal` carry their full declared shape; `app_enabled` is a real boolean.
3. With Pro's app-config filter removed and 20 Pro rows in the registry, Free's resolver
   returns **exactly the 10 free keys** and **zero** Pro keys.
4. Back-compat `enable_claiming` / `enable_reviews` / `enable_favorites` / `is_pro_active`
   are present, boolean, and agree with their `features{}` twins.
5. The `wb_listora_app_config` filter is passed a `WP_REST_Request` as its 2nd arg.
6. debug.log clean.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| 401/403 anonymous | route lost `__return_true` | `includes/rest/class-settings-controller.php` ~84 |
| A contract key missing | dropped from `$data`, or a filter returned a partial array instead of merging | `class-settings-controller.php::get_app_config()` ~453-543 |
| `free_flag_count` > 10 / `leaked=` non-empty | **the original bug is back** — `store` guard gone, Pro rows resolved with Free's reader | `class-settings-controller.php::free_feature_flags()` ~588-596 |
| `free_flag_count` < 10 | a free toggle left `wb_listora_features_registry()`, or was retagged with a non-`free` store | `includes/class-features.php` registry |
| `pro_rows_in_registry=0` in step 3 | Pro inactive / registry filter unhooked — journey is **inconclusive**, not PASS | Pro `class-pro-plugin.php::register_pro_features_on_free_screen` |
| `contract_version` drifts from the const | payload hand-edited instead of reading `APP_CONTRACT_VERSION` | `class-settings-controller.php:37,454` |
| `arg2_type=NULL` | `$request` not passed to `apply_filters` | `class-settings-controller.php:558` |
| `enable_*` key gone | back-compat break for shipped apps | `class-settings-controller.php::get_app_config()` ~465-471 |
