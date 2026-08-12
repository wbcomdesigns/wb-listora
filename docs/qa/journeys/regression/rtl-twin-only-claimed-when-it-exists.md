---
journey: rtl-twin-only-claimed-when-it-exists
plugin: wb-listora
priority: normal
roles: [anonymous]
covers: [rtl, assets, 404, theme-bridge]
prerequisites: ["An RTL locale (ar), and a theme whose bridge stylesheet loads (BuddyX Pro)"]
estimated_runtime_minutes: 4
---

# Only claim an RTL stylesheet that exists

`mark_styles_rtl()` marked EVERY `listora-*` / `wb-listora-*` handle with
`wp_style_add_data( $handle, 'rtl', 'replace' )` unconditionally. WordPress then
requested a `-rtl.css` for each one - including the three theme bridges
(`buddyx.css`, `buddyx-pro.css`, `reign.css`), which are hand-written overrides
with no generated twin. Every RTL-locale pageview took a **404**.

The page still looked right, because the base RTL stylesheets carry the
mirroring. So this was invisible to anyone reading the page and visible only in
the Network panel - which is why the smoke's browser-first rule found it and a
markup assertion never would have.

Fixed by checking the twin exists rather than by maintaining an exclusion list:
a stylesheet added later opts into RTL by HAVING a twin, not by someone
remembering to exclude it.

## Steps

### 1. No 404s on an RTL locale
Set the site locale to `ar`, load the directory.
- `document.documentElement.dir === 'rtl'`.
- Every Listora stylesheet request returns **200**. Read the status from the
  Network panel or a `fetch(..., {method:'HEAD'})` - do NOT infer it from the
  console, and do NOT infer it from the page looking correct.
- **Fails if** any `-rtl.css` returns 404.

### 2. RTL styling is still applied
The genuine twins still load: `listora-variables-rtl`, `listora-components-rtl`,
`listora-base-rtl`, `pro-frontend-rtl`, `quick-view-rtl`, block `style-rtl`.
- **Fails if** any is missing. Suppressing the 404 by dropping RTL entirely
  would be a far worse fix than the bug.

### 3. The bridge loads its LTR file
`assets/css/themes/<theme>.css` returns 200 and is NOT swapped for a twin.

### 4. Adding a twin opts a file in
Create `themes/buddyx-pro-rtl.css`, reload: it is now requested and returns 200.
Delete it: the LTR file is requested again, still no 404.

### 5. An unresolvable source keeps RTL
A stylesheet whose `src` is not under `content_url()` (CDN, filtered URL) must
still be marked. Skipping on "cannot prove it exists" would silently drop RTL,
which is worse than a 404.

## Test-data trap

An LTR locale exercises none of this - WordPress never asks for a twin. And the
page renders correctly in RTL either way, so a visual or markup check passes
with the bug fully present. The status code is the only signal.
