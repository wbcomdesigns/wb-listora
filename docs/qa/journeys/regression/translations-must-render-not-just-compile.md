---
journey: translations-must-render-not-just-compile
plugin: wb-listora
priority: high
roles: [anonymous, member, admin]
covers: [i18n, l10n-php, mo-catalogue, wp-6.5-loader]
prerequisites:
  - "A locale with a complete .po in languages/ (de_DE is the reference)"
  - "WP 6.5 or newer - the .l10n.php preference is what makes this bug possible"
estimated_runtime_minutes: 6
---

# Verify the rendered string, never the catalogue

The 1.5.0 translation pass verified `.po` completeness and `.mo` freshness, both
green, and reported the plugin translation-ready. The site still rendered
English.

WordPress 6.5+ loads **`.l10n.php` in preference to `.mo`**. `languages/` held a
`.l10n.php` compiled hours before the last `.po` edit, so the stale PHP
catalogue silently shadowed a perfectly correct `.mo`. Every catalogue-level
check — string counts, fuzzy counts, `msgfmt --statistics` — reported 100%,
because every catalogue-level check was reading the file WordPress had already
decided to ignore.

The lesson generalises past i18n: **assert on what the browser received, not on
the artefact you believe it will read.**

## Steps

### 1. Switch the site locale
```bash
wp option update WPLANG de_DE
wp language plugin list wb-listora
```

### 2. Read a rendered string from the front end
Load a Listora surface and read the text of a known-translated element **by
selector**, never by matching visible text (matching on the expected translation
makes the assertion circular).
- **Fails if** the element still renders its English source string.

Cover at least one string from each loading path, because they fail
independently: a PHP template, a block `render.php`, an Interactivity API state
string from `class-assets.php`, and a JS string from `src/utils/i18n.js`.

### 3. Prove which catalogue actually served it
```bash
ls -la languages/*.l10n.php languages/*.mo languages/*.po
```
- **Fails if** any `.l10n.php` is older than its sibling `.po`. This is
  `bin/coding-rules-check.sh` Rule 11, and `bin/build-release.sh` regenerates
  them at package time — but a dev build predates both, which is exactly where a
  hand-verification lands.

### 4. Regenerate and re-read
```bash
wp i18n make-php languages
```
Reload the same surface. The rendered string must not change — if it does, the
build that was about to ship was serving stale translations.

### 5. Restore
```bash
wp option update WPLANG ''
```

## Test-data trap

Checking a string that happens to be identical in both languages (a brand name,
"OK", a bare number) passes regardless. Pick a string that is unmistakably
different in the target locale, and confirm the expected translation exists in
the `.po` **before** the run — a missing msgstr renders English too, and looks
exactly like this bug.
