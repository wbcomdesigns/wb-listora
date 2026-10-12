---
journey: rtl-twin-only-claimed-when-it-exists
plugin: wb-listora
priority: normal
roles: [anonymous]
covers: [rtl, assets, 404, theme-bridge]
prerequisites: ["An RTL locale (ar) installed, and a theme whose bridge stylesheet loads (BuddyX Pro)"]
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Only claim an RTL stylesheet that exists

`Plugin::mark_styles_rtl()` (`includes/class-plugin.php:802`) flags a `listora-*` / `wb-listora-*` handle for the `-rtl.css` swap only when
`has_rtl_twin()` (`:846`) finds `<name>-rtl.css` on disk (or cannot resolve the source to a local file, in which case it keeps RTL).
Otherwise every RTL pageview 404s for each sheet without a twin. Pro's `mark_styles_rtl()`
(`wb-listora-pro/includes/class-assets.php:48`) still flags every `wb-listora-pro*` handle unconditionally; all Pro frontend sheets have
twins today (`pro-frontend`, `quick-view`, ...).

What changed by 2.0.0: Listora sheets are printed as linked `@layer listora` twins (`*.layer.css`, `Theme_Defenses::layer_listora_styles()`,
`includes/core/class-theme-defenses.php:131`) AFTER WordPress picked the RTL source, so the browser requests `*-rtl.layer.css`. The three
theme bridges (`assets/css/themes/buddyx.css`, `buddyx-pro.css`, `reign.css`) now ship generated `-rtl.css` twins as well (verified on disk),
so step 3/4 of the old journey ("bridge has no twin") is no longer true; the invariant is still "claim a twin only if it exists".

Role ladder: anonymous visitor in an RTL locale (the stylesheet requests are identical for members and admin; admin is the control only
for the admin-bar CSS, not tested here).

## Steps

### 1. No 404s on an RTL locale (NOT RUN)
- **Setup**: install the `ar` language pack and set the site (or a user) locale to `ar`; `is_rtl()` is false until WordPress has an RTL locale
  loaded, so a plain English site exercises none of this.
- **Action**: load `$SITE_URL/listings/`.
- **Expect**: `document.documentElement.dir === 'rtl'`; every Listora stylesheet request (`wb-listora/...` and `wb-listora-pro/...`) returns
  **200**. Read statuses from the Network panel or `fetch(url, {method:'HEAD'})`, not from the console or from how the page looks.
- **Fails if** any `-rtl.css` / `-rtl.layer.css` returns 404.

### 2. RTL styling is still applied
- **Expect**: the genuine twins load: `listora-variables-rtl.layer.css`, `listora-components-rtl.layer.css`, `listora-base-rtl.layer.css`,
  `listora-isolation-rtl.css` (isolation is never layered), `pro-frontend-rtl.layer.css`, `quick-view-rtl.layer.css`, block
  `style-rtl.layer.css`, and the bridge `themes/buddyx-pro-rtl.layer.css`.
- **Fails if** any is missing (dropping RTL to hide a 404 is worse than the 404).

### 3. Twin presence on disk (verified 2026-10-12)
- For `listora-base`, `listora-components`, `listora-variables`, `themes/buddyx-pro`, `blocks/listing-{card,grid,map,search}/style`,
  Pro `pro-frontend` and `quick-view`: both `<name>-rtl.css` and `<name>-rtl.layer.css` exist. `listora-isolation-rtl.css` exists with no
  `.layer` twin (by design). Vendor `leaflet*.css` and `MarkerCluster*.css` have no twin and therefore must not be claimed.
- LTR check (verified): `GET /listings/` loads `listora-variables`, `listora-theme-bridge` (`themes/buddyx-pro.layer.css`),
  `listora-components`, `listora-isolation`, `listora-base` and block sheets, all 200.

### 4. Removing a twin stops the claim
- **Action**: temporarily rename `assets/css/themes/buddyx-pro-rtl.css`, reload in `ar`.
- **Expect**: the LTR bridge is requested again, no 404 for the missing twin. Restore the file.

### 5. An unresolvable source keeps RTL
- A stylesheet whose `src` is not under `content_url()` (CDN, filtered URL) is still marked (`has_rtl_twin()` returns true).

## Test-data trap
An LTR locale exercises none of this, and an RTL page renders correctly either way. The status code is the only signal.

## Not verified
Steps 1, 2, 4, 5 were not run: no RTL language pack is installed on the shared site; step 3 is a filesystem + LTR request check.
