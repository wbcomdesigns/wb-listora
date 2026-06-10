---
journey: settings-docs-links-live
plugin: wb-listora
priority: normal
roles: [admin]
covers: ["#9919933465", "settings Documentation buttons", "store docs anchor format"]
prerequisites:
  - "Admin access to wp-admin"
estimated_runtime_minutes: 2
---

# Every settings Documentation button deep-links into the live store docs

Card #9919933465: the Documentation buttons on the settings sections built
per-section paths (`https://store.wbcomdesigns.com/listora/docs/{section}/`)
that 404 — the store renders ALL product docs on ONE page at
`https://store.wbcomdesigns.com/listora/docs/` with `#{slug}-ls` hash anchors.

`Settings_Page::get_docs_url()` now emits the hash-anchor form, maps the
`credits` section to its real doc (`credits-and-plans`), and maps the four
Pro-injected sections (`pagination` → `infinite-scroll`, `seo` → `seo-pages`,
`visibility` → `coming-soon`, `white-label` → `white-label`). The
`wb_listora_docs_url` filter still allows per-site overrides.

## Steps

### 1. Every rendered docs link uses the anchor form
- **Action**: visit `admin.php?page=listora-settings&tab=general` and collect
  `document.querySelectorAll('.listora-docs-link')` hrefs.
- **Expect**: every href matches
  `https://store.wbcomdesigns.com/listora/docs/#<slug>-ls` — no
  `/docs/<section>/` path form remains.

### 2. Anchors exist on the live docs page
- **Action**: `curl -sL https://store.wbcomdesigns.com/listora/docs/` and
  check each `<slug>-ls` from step 1 appears as an `<article id="...">`.
- **Expect**: every anchor resolves; the page itself returns HTTP 200.

### 3. Filter override still works
- **Action**: `add_filter( 'wb_listora_docs_url', fn() => 'https://example.com/x' )`
  via eval, reload settings.
- **Expect**: links point at the override.
