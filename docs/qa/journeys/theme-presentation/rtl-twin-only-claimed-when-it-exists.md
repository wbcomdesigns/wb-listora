---
journey: rtl-twin-only-claimed-when-it-exists
group: theme-presentation
actor: system
mode: mixed
plugin: wb-listora
priority: normal
roles: [anonymous, administrator]
covers: [rtl, assets, 404, theme-bridge, layered-css]
prerequisites:
  - "An RTL locale (`ar`) language pack installed, and a theme whose bridge stylesheet loads (BuddyX Pro)"
  - "Pro active for the Pro sheet rows"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
site_profile: required
supersedes: []
requires: "wb-listora-pro (Pro sheet rows only)"
---

# Only claim an RTL stylesheet that exists

## Why
`Plugin::mark_styles_rtl()` (`includes/class-plugin.php:802`) flags a `listora-*` / `wb-listora-*` handle for the `-rtl.css` swap only when `has_rtl_twin()` (`:846`) finds `<name>-rtl.css` on disk (or cannot resolve the source to a local file, then it keeps RTL); otherwise every RTL pageview 404s for each sheet without a twin.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). An LTR locale exercises none of this and an RTL page renders correctly either way: the status code is the only signal. `is_rtl()` is false until WordPress has an RTL locale loaded.
- Listora sheets print as linked `@layer listora` twins (`*.layer.css`, `Theme_Defenses::layer_listora_styles()`, `includes/core/class-theme-defenses.php:131`) AFTER WordPress picked the RTL source, so the browser requests `*-rtl.layer.css`. The three theme bridges (`assets/css/themes/buddyx.css`, `buddyx-pro.css`, `reign.css`) ship generated `-rtl.css` twins; the invariant is still "claim a twin only if it exists". Pro's `mark_styles_rtl()` (`wb-listora-pro/includes/class-assets.php:48`) flags every `wb-listora-pro*` handle unconditionally; all Pro frontend sheets have twins.

## Steps

### 1. No 404s on an RTL locale
- **Setup**: install the `ar` pack and set the site (or a user) locale to `ar`.
- **Action**: load `$SITE_URL/listings/`.
- **Expect**: `document.documentElement.dir === 'rtl'`; every Listora stylesheet request (`wb-listora/...` and `wb-listora-pro/...`) returns 200.
- **Read-back**: statuses from the Network panel or `fetch(url,{method:'HEAD'})`, not from the console or how the page looks. Fails if any `-rtl.css` / `-rtl.layer.css` returns 404. Not run (no RTL pack on the shared site).

### 2. RTL styling is still applied
- **Expect**: the genuine twins load: `listora-variables-rtl.layer.css`, `listora-components-rtl.layer.css`, `listora-base-rtl.layer.css`, `listora-isolation-rtl.css` (isolation is never layered), `pro-frontend-rtl.layer.css`, `quick-view-rtl.layer.css`, block `style-rtl.layer.css`, and the bridge `themes/buddyx-pro-rtl.layer.css`. Fails if any is missing (dropping RTL to hide a 404 is worse than the 404).
- **Read-back**: list of loaded sheets.

### 3. Twin presence on disk
- **Action**: for `listora-base`, `listora-components`, `listora-variables`, `themes/buddyx-pro`, `blocks/listing-{card,grid,map,search}/style`, Pro `pro-frontend` and `quick-view`: check both `<name>-rtl.css` and `<name>-rtl.layer.css`.
- **Expect**: both exist; `listora-isolation-rtl.css` exists with no `.layer` twin (by design); vendor `leaflet*.css` and `MarkerCluster*.css` have no twin and must not be claimed.
- **Read-back**: LTR check `GET /listings/` loads `listora-variables`, `listora-theme-bridge`, `listora-components`, `listora-isolation`, `listora-base` and block sheets, all 200 (verified).

### 4. Removing a twin stops the claim
- **Action**: temporarily rename `assets/css/themes/buddyx-pro-rtl.css`, reload in `ar`.
- **Expect**: the LTR bridge is requested again; no 404 for the missing twin. Restore the file.

### 5. An unresolvable source keeps RTL
- **Expect**: a stylesheet whose `src` is not under `content_url()` (CDN, filtered URL) is still marked (`has_rtl_twin()` returns true).

### 6. Layout in RTL at 390 and dark
- **Expect**: no horizontal scroll, `margin-inline-*` honoured, no mirrored icons that carry meaning. Not run.

## Role ladder
| Rung | Expect |
|---|---|
| {guest} | steps 1-6 (stylesheet requests are identical for members) |
| {admin} (control) | same front-end requests; admin-bar CSS not tested here |

## Pass criteria
1. Every Listora stylesheet request returns 200 on an RTL locale.
2. The genuine twins load; a claimed-but-missing twin is never requested.

## Fail diagnostics
| Symptom | File |
|---|---|
| 404 `-rtl.css` | `includes/class-plugin.php:802` (`mark_styles_rtl`), `:846` (`has_rtl_twin`) |
| Layered twin 404 | `includes/core/class-theme-defenses.php:131` |
| Pro sheet 404 | `wb-listora-pro/includes/class-assets.php:48` |

## Not verified
Steps 1, 2, 4, 5, 6 (no RTL pack installed on the shared site); step 3 is a filesystem + LTR request check.

## Cleanup
Restore any renamed twin; set the locale back.
