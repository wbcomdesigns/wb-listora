---
journey: translations-must-render-not-just-compile
plugin: wb-listora
priority: high
roles: [anonymous, member, admin]
covers: [i18n, l10n-php, mo-catalogue, wp-6.5-loader]
prerequisites:
  - "A locale with a complete .po in languages/ (de_DE is the reference)"
  - "WP 6.5 or newer: `.l10n.php` is loaded in preference to `.mo`"
  - "Use a locale-switch that is reversible; never leave WPLANG changed"
estimated_runtime_minutes: 7
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Verify the rendered string, never the catalogue

WordPress 6.5+ loads `.l10n.php` in preference to `.mo`. A `.l10n.php` compiled before the last `.po` edit silently shadows a correct `.mo`, so every catalogue-level check (string counts, `msgfmt --statistics`) is green while the site renders English. Assert on what the browser received. Gate: `bin/coding-rules-check.sh` Rule 11 (`:307-326`), regenerated at package time by `bin/build-release.sh:55-64` (`wp i18n make-php languages`).

## Steps

### 1. Switch the site locale
```bash
wp option update WPLANG de_DE
wp language plugin list wb-listora
```
(Or per request with `switch_to_locale( 'de_DE' )` in `wp eval-file`, which needs no option change.)

### 2. Read a rendered string from the front end
Load a Listora surface and read the text of a known-translated element by selector, never by matching the expected translation (circular). Cover one string from each loading path, because they fail independently:

| Path | Example surface | Selector / source |
|---|---|---|
| PHP template | success card, dashboard | `.listora-submission__success-actions .listora-btn--primary` ("Go to Dashboard" -> "Zum Dashboard") |
| Block `render.php` | listing search | `.listora-search__toggle-btn span` |
| Interactivity state from `includes/class-assets.php` | `listoraI18n` object | `window.listoraI18n.requiredFieldMessages.agree_terms`, `listoraI18n.fileTooLarge`, `jsBuyCredits` (`includes/class-assets.php:274-406`) |
| JS bundle string | `src/utils/i18n.js` consumers | e.g. the "Buy credits" link on a 402 (`view.js:412`, `jsBuyCredits`) |

- **Fails if** the element still renders its English source string while the `.po` has a non-empty `msgstr`.
- **Positive control**: confirm the `msgstr` exists first (`grep -n -A1 '^msgid "Go to Dashboard"$' languages/wb-listora-de_DE.po` -> "Zum Dashboard").

### 3. Prove which catalogue served it
```bash
ls -la languages/*.l10n.php languages/*.mo languages/*.po
```
- **Fails if** any `.l10n.php` is older than its sibling `.po` (2026-10-12: all `.po` and `.l10n.php` share one mtime; `.mo` is older, which is fine because WP prefers the PHP file).

### 4. Regenerate and re-read
```bash
wp i18n make-php languages
```
Reload the same surface: the rendered string must not change. If it does, the build that was about to ship served stale translations.

### 5. Coverage of 2.0.0 strings
- **Action**: check the new 2.0.0 strings exist in the `.po`: "This field is required.", "Selected only", "Services enabled", "Show all features (%d)".
- **Expect**: a non-empty `msgstr` for each. 2026-10-12: only "This field is required." is in `wb-listora-de_DE.po`; the other three have no entry yet, so they render English in de_DE (release-time catalogue refresh).

### 6. Restore
```bash
wp option update WPLANG ''
```

## Role ladder
Same output for every role; spot-check as anonymous (public pages) and as a member (dashboard strings, user locale `get_user_locale()`); admin uses the wp-admin locale (profile language) so verify one admin screen too.

## Test-data trap
A string identical in both languages (brand name, "OK", "Directory" which the de_DE `.po` leaves untranslated) passes regardless. Pick an unmistakably different one and confirm the `msgstr` first.

## Fail diagnostics
| Symptom | File |
|---|---|
| `.l10n.php` older than `.po` | `bin/coding-rules-check.sh:321` (Rule 11), `bin/build-release.sh:63` |
| Template/PHP string English | text domain call `__( '...', 'wb-listora' )` literal domain; `includes/class-plugin.php:103` `load_textdomain()` at `init@1` |
| JS string English | `languages/wb-listora-de_DE-*.json` missing/stale (`bin/build-i18n-json.mjs`) |

## Not verified
Via wp-cli `switch_to_locale( 'de_DE' )`: "Add Listing" -> "Eintrag hinzufügen", "Go to Dashboard" -> "Zum Dashboard", and Pro "Needs" -> "Anfragen". Not checked in a browser; the JS-bundle `.json` path (step 2 row 4) and the `make-php` regeneration (step 4) were not run. The wb-listora-de_DE `.l10n.php` content holds 2533 messages.

Verified via: wp-cli.
