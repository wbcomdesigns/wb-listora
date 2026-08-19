---
journey: trigger-labels-translate
plugin: wb-listora
priority: high
covers:
  - D.no-translation-at-bootstrap
  - BC 9842833276 (same bug class, other end)
likely_files:
  - includes/class-plugin.php
  - includes/automation/class-trigger-definitions.php
  - ../wb-listora-pro/includes/automation/class-pro-trigger-definitions.php
---

# Nothing calls __() during bootstrap

The 1.6.0 automation wave built the trigger catalogue inside
`register_services()`, which runs at plugin bootstrap. Every trigger carries a
translated `label`, so the catalogue called `__()` before `init` — while
`load_textdomain` is deliberately hooked at `init@1` precisely to avoid that
(the comment saying so sits directly above the offending call).

The notices were the visible half: ~6 `_load_textdomain_just_in_time` entries
per request, against BOTH domains, on every page load. The silent half was the
damage — WP 6.7+ refuses the too-early load, so on a non-English site every
trigger label fell back to English permanently, in the webhook subscriber UI
and in the published trigger catalogue.

Populating the registry now runs at `init@2`: after the textdomain at `init@1`,
before every consumer. Only the empty instance is registered at bootstrap, so
`wb_listora_service('triggers')` still resolves at `wb_listora_loaded`.

## Steps

1. Truncate or mark `wp-content/debug.log`. Load three front-end pages as an
   anonymous visitor.
2. Assert ZERO `_load_textdomain_just_in_time` entries naming `wb-listora` or
   `wb-listora-pro`. Before the fix this produced ~6 per two page loads.
3. Assert `wb_listora_service('triggers')->all()` still returns the full
   catalogue (34 with Pro active: 25 Free + 9 Pro) with non-empty labels —
   deferring must not empty the registry.
4. Assert the object is still resolvable from a `wb_listora_loaded` listener
   (the bootstrap contract Pro depends on).
5. **Guard the class, not the instance:** grep both plugins for `__(`, `_e(`,
   `esc_html__(` reachable from bootstrap (constructor / `register_services` /
   any plugins_loaded path). Any hit is this bug returning under a new name.

## Verified

2026-08-19, wb-listora.local, Free+Pro 1.6.0. Three anonymous page loads
produced 0 textdomain notices (6 per 2 loads before). Registry returned 34
triggers with labels intact. Root cause traced live with a `doing_it_wrong_run`
backtrace probe to class-trigger-definitions.php:169 via class-plugin.php:91.
