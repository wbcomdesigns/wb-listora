---
journey: trigger-labels-translate
plugin: wb-listora
priority: high
roles: [anonymous, admin]
covers:
  - D.no-translation-at-bootstrap
  - BC 9842833276 (same bug class, other end)
likely_files:
  - includes/class-plugin.php
  - includes/automation/class-trigger-definitions.php
  - ../wb-listora-pro/includes/automation/class-pro-trigger-definitions.php
prerequisites:
  - "WP_DEBUG + WP_DEBUG_LOG enabled; Free and Pro active"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
changed: true
---

# Nothing calls __() during bootstrap

Single-role system journey (log + service container): no UI beyond the Pro webhook subscriber page, so no viewport/theme ladder. The trigger catalogue used to be built in `register_services()` at plugin bootstrap, calling `__()` before `init` (where `load_textdomain` is deliberately hooked at `init@1`). WP 6.7+ refuses the too-early load, so on a non-English site every trigger label stayed English and the log filled with `_load_textdomain_just_in_time` notices (both domains, every request). Populating the registry now runs at `init@2` (`includes/class-plugin.php:86-95`): after the textdomain at `init@1`, before consumers. Only the empty `Trigger_Registry` is registered at bootstrap (`:79-80`), so `wb_listora_service( 'triggers' )` still resolves at `wb_listora_loaded`.

## Steps

1. Note the byte size of `wp-content/debug.log`. Load three front-end pages (`/`, `/listings/`, `/add-listing/`) as an anonymous visitor.
2. Assert ZERO new `_load_textdomain_just_in_time` entries naming `wb-listora` or `wb-listora-pro`. Verified 2026-10-12: 0 new entries across those three loads (the historic log holds 879 older entries, the last at 11-Oct 21:25 UTC from CLI runs; compare by offset, not by file total).
3. Assert `wb_listora_service( 'triggers' )->all()` returns the full catalogue with non-empty labels: 34 with Pro active (25 Free + 9 Pro). Verified 34, and `plan_resumed` is registered.
4. Assert it is resolvable from a `wb_listora_loaded` listener (the bootstrap contract Pro depends on): `add_action( 'wb_listora_loaded', function () { var_dump( wb_listora_service( 'triggers' ) instanceof \WBListora\Automation\Trigger_Registry ); } )` in a mu-plugin.
5. Guard the class, not the instance: grep both plugins for `__(`, `_e(`, `esc_html__(` reachable from bootstrap (constructor / `register_services` / any `plugins_loaded` path). Any hit is this bug returning under a new name.
6. Translated labels (positive control for step 2): with `switch_to_locale( 'de_DE' )` active BEFORE `init` (set `WPLANG` for the run), `->all()` labels are German where the `.po` has a `msgstr`; with the registry built before `load_textdomain` they would stay English.

## Role ladder
System journey; spot-check as anonymous (steps 1-2) and admin (the webhook subscriber page `wp-admin/admin.php?page=listora-webhooks&view=add` lists translated labels).

## Fail diagnostics
| Symptom | File |
|---|---|
| Notices from bootstrap | `includes/class-plugin.php:66-95` (`register_services`), `includes/automation/class-trigger-definitions.php:72` (first `__()` label) |
| Pro labels English | `../wb-listora-pro/includes/automation/class-pro-trigger-definitions.php` register_all timing |
| Registry empty at `wb_listora_loaded` | `class-plugin.php:79-80` registers the instance; population at `:86-95` |

## Not verified
Steps 2 and 3 run on 2026-10-12 (curl + `wp eval`). Steps 4, 5 (grep sweep) and 6 were not run. Step 3's labels were not checked in a non-English locale: a `switch_to_locale()` after boot returned English labels, which is expected because the registry was already built.

Verified via: curl, wp-cli.
