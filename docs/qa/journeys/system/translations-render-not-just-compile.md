---
journey: translations-render-not-just-compile
plugin: wb-listora
priority: high
roles: [anonymous, member-owner, administrator]
covers: [i18n, l10n-php, mo-catalogue, wp-6.5-loader, js-translations]
prerequisites:
  - "Site reachable at $SITE_URL; WP-CLI at $WP_PATH"
  - "A locale with a complete .po in languages/ (de_DE is the reference)"
  - "WP 6.5 or newer (`.l10n.php` loads in preference to `.mo`)"
  - "A reversible locale switch; never leave WPLANG changed"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unbridged default theme]
supersedes:
  - ora:regression/translations-must-render-not-just-compile.md
fails_on: unknown (pre-fix sha not recorded)
site_profile: required
---

# Verify the rendered string, never the catalogue

## Why
WordPress 6.5+ loads `.l10n.php` in preference to `.mo`. A `.l10n.php` compiled before the last `.po` edit silently shadows a correct `.mo`, so every catalogue check (string counts, `msgfmt --statistics`) is green while the site renders English. Gate: `bin/coding-rules-check.sh` Rule 11, regenerated at package time by `bin/build-release.sh` (`wp i18n make-php languages`).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Pick an unmistakably different string and confirm its `msgstr` first (a string identical in both languages passes regardless).

## Steps

### 1. Switch the site locale
- **Action**: `wp option update WPLANG de_DE; wp language plugin list wb-listora` (or per request `switch_to_locale( 'de_DE' )` in `wp eval-file`, no option change).

### 2. Read a rendered string from each loading path
- **Action**: load a Listora surface and read the text of a known-translated element by selector (never match the expected translation: circular).
- **Expect** one per path, because they fail independently:

| Path | Example surface | Selector / source |
|---|---|---|
| PHP template | success card, dashboard | `.listora-submission__success-actions .listora-btn--primary` ("Go to Dashboard" -> "Zum Dashboard") |
| Block `render.php` | listing search | `.listora-search__toggle-btn span` |
| Interactivity state | `listoraI18n` object | `window.listoraI18n.requiredFieldMessages.agree_terms`, `listoraI18n.fileTooLarge`, `jsBuyCredits` (`includes/class-assets.php`) |
| JS bundle string | `src/utils/i18n.js` consumers | the "Buy credits" link on a 402 (`view.js`, `jsBuyCredits`) |
- **Fails if** the element renders its English source while the `.po` has a non-empty `msgstr`.
- **Positive control**: `grep -n -A1 '^msgid "Go to Dashboard"$' languages/wb-listora-de_DE.po` shows "Zum Dashboard".

### 3. Prove which catalogue served it
- **Action**: `ls -la languages/*.l10n.php languages/*.mo languages/*.po`
- **Expect**: no `.l10n.php` older than its sibling `.po` (a `.mo` older than the `.po` is fine: WP prefers the PHP file).

### 4. Regenerate and re-read
- **Action**: `wp i18n make-php languages`, reload the same surface.
- **Expect**: the rendered string does not change (if it does, the build about to ship served stale translations).

### 5. Coverage of current-release strings
- **Action**: check "This field is required.", "Selected only", "Services enabled", "Show all features (%d)" exist in the `.po`.
- **Expect**: a non-empty `msgstr` each. `expected-fail: only "This field is required." has an entry in wb-listora-de_DE.po; the other three render English in de_DE` (release-time catalogue refresh).

### 6. RTL and long-string layout (added)
- **Action**: switch to `ar` / `he_IL` and `de_DE`; load the Directory, Add Listing and a settings screen at 1440 and 390.
- **Expect**: direction flips with logical properties, long German labels wrap without clipping. Not verified live.

### 7. Restore
- **Action**: `wp option update WPLANG ''`.

## Role ladder
Same output for every role; check {guest} (public pages), {owner} (dashboard strings via `get_user_locale()`) and {admin} (wp-admin locale follows the profile language: verify one admin screen).

## Pass criteria
Every loading path renders the translated string; no `.l10n.php` is older than its `.po`; regenerating does not change the output; current-release strings are present.

## Fail diagnostics

| Symptom | File |
|---|---|
| `.l10n.php` older than `.po` | `bin/coding-rules-check.sh` (Rule 11), `bin/build-release.sh` |
| Template/PHP string English | literal text domain `__( '...', 'wb-listora' )`; `includes/class-plugin.php` `load_textdomain()` at `init@1` |
| JS string English | `languages/wb-listora-de_DE-*.json` missing/stale (`bin/build-i18n-json.mjs`) |

## Not verified
Via wp-cli `switch_to_locale( 'de_DE' )`: "Add Listing" -> "Eintrag hinzufügen", "Go to Dashboard" -> "Zum Dashboard", Pro "Needs" -> "Anfragen". Not checked in a browser; the JS-bundle `.json` path (step 2 row 4), step 4 and step 6 were not run.

## Cleanup
Step 7.
