---
journey: trigger-labels-translate
plugin: wb-listora
priority: high
roles: [guest, admin]
covers: [no-translation-at-bootstrap, trigger-registry, textdomain-timing, bc-9842833276]
prerequisites:
  - "WP_DEBUG + WP_DEBUG_LOG enabled; Free and Pro active (combo)"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [n/a]
site_profile: required
supersedes: []
fails_on: "trigger catalogue built in register_services() before init@1"
---

# Nothing calls __() during plugin bootstrap, so trigger labels translate

## Why
The trigger catalogue used to be built at plugin bootstrap, calling `__()` before `init` (where `load_textdomain` is hooked at `init@1`). WP 6.7+ refuses the too-early load, so on a non-English site every trigger label stayed English and the log filled with `_load_textdomain_just_in_time` notices on every request.

Populating the registry now runs at `init@2` after the textdomain; only the empty `Trigger_Registry` is registered at bootstrap, so `wb_listora_service( 'triggers' )` still resolves at `wb_listora_loaded`.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Note the byte size of `$WP_PATH/wp-content/debug.log` (compare by offset, not file total).

## Steps

### 1. No early textdomain notices
- **Action**: load three front-end pages (home, the Directory, Add Listing) as {guest}.
- **Expect**: zero new `_load_textdomain_just_in_time` entries naming `wb-listora` or `wb-listora-pro` after the recorded offset.

### 2. Full catalogue with labels
- **Action**: `wp eval 'echo count( wb_listora_service( "triggers" )->all() );'` and print each label.
- **Expect**: the full catalogue (Free + Pro triggers; count derived from `audit/manifest.json` trigger list), every label non-empty, `plan_resumed` registered.

### 3. Resolvable at `wb_listora_loaded`
- **Action**: mu-plugin `add_action( 'wb_listora_loaded', function () { var_dump( wb_listora_service( 'triggers' ) instanceof \WBListora\Automation\Trigger_Registry ); } );`
- **Expect**: `bool(true)` (the bootstrap contract Pro depends on). Not run.

### 4. Guard the class, not the instance
- **Action**: grep both plugins for `__(`, `_e(`, `esc_html__(` reachable from bootstrap (constructors, `register_services`, any `plugins_loaded` path).
- **Expect**: no hit; any hit is this bug returning under a new name. Not run.

### 5. Translated labels (positive control for step 1)
- **Action**: set `WPLANG` to a locale with a `.po` (for example `de_DE`) BEFORE `init`; read `->all()` labels.
- **Expect**: translated where the `.po` has a `msgstr`; a `switch_to_locale()` after boot returns English because the registry is already built (expected). Not run.

### 6. Admin surface
- **Action**: as {admin} open the Pro webhook subscriber page (`wp-admin/admin.php?page=listora-webhooks&view=add`).
- **Expect**: trigger labels listed, translated in a non-English locale.

## Role ladder
System journey: steps 1-2 as {guest}; step 6 as {admin}. No per-user branch.

## Pass criteria
1. Zero early-textdomain notices from either plugin.
2. The registry is complete and resolvable at `wb_listora_loaded`.
3. Labels translate when a translation exists.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Notices from bootstrap | label built in `register_services` | `includes/class-plugin.php:66-95`; `includes/automation/class-trigger-definitions.php` (~:72) |
| Pro labels English | register timing | `wb-listora-pro/includes/automation/class-pro-trigger-definitions.php` |
| Registry empty at `wb_listora_loaded` | instance registered at :79-80, population at :86-95 | `class-plugin.php` |

## Not verified
Steps 3-5 not run; steps 1-2 run on 2026-10-12 (curl + wp eval). Non-English label content not checked.

## Cleanup
Remove the mu-plugin and any `WPLANG` override.
