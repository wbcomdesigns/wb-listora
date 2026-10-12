---
journey: settings-docs-links-live
plugin: wb-listora
priority: normal
roles: [admin, editor]
covers: ["#9919933465", "settings Documentation buttons", "store docs anchor format"]
prerequisites:
  - "Admin access to wp-admin (varundubey); qa_editor for the ladder"
estimated_runtime_minutes: 2
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light]
changed: true
---

# Every settings Documentation button deep-links into the live store docs

`Settings_Page::get_docs_url()` (`includes/admin/class-settings-page.php:508-542`) emits `https://store.wbcomdesigns.com/listora/docs/#<slug>-ls` for each section (the store renders all docs on ONE page with `{slug}-ls` hash anchors). Mapping: general -> general-settings, maps -> map-settings, submissions -> submission-settings, reviews -> reviews-settings, credits -> credits-and-plans, notifications -> notifications-settings, advanced -> advanced-settings, plus Pro sections pagination -> infinite-scroll, seo -> seo-pages, visibility -> coming-soon, white-label -> white-label. The `wb_listora_docs_url` filter overrides per site. Links render as `<a class="listora-docs-link" target="_blank" rel="noopener noreferrer">` (`:743`).

Verified via: curl as admin of every settings tab (steps 1 and the role row). Step 2 (live anchors) was NOT verified: `store.wbcomdesigns.com` answered HTTP 403 to curl from this machine, so it needs a browser.

## Steps

### 1. Every rendered docs link uses the anchor form
- **Action**: as admin open `admin.php?page=listora-settings&tab=general` and collect `document.querySelectorAll('.listora-docs-link')` hrefs (all tabs are present in the one rendered page).
- **Expect** (observed, 10 links): `#general-settings-ls`, `#map-settings-ls`, `#submission-settings-ls`, `#reviews-settings-ls`, `#credits-and-plans-ls`, `#notifications-settings-ls`, `#advanced-settings-ls`, `#seo-pages-ls`, `#coming-soon-ls`, `#white-label-ls`, all under `https://store.wbcomdesigns.com/listora/docs/`; no `/docs/<section>/` path form. No docs link is rendered for the Features, Integrations or Import/Export tabs in this build even though they are in the map.

### 2. Anchors exist on the live docs page
- **Action** (browser): open `https://store.wbcomdesigns.com/listora/docs/` and check each `<slug>-ls` from step 1 resolves to an element id; page returns 200.
- **Expect**: every anchor resolves. **Not verified here (403 to curl).**

### 3. Filter override still works (positive control for step 1)
- **Action**: `add_filter( 'wb_listora_docs_url', fn() => 'https://example.com/x' );` via a temporary mu-plugin, reload.
- **Expect**: every `.listora-docs-link` points at the override; remove the stub afterwards.

### 4. Role row
- qa_editor opening `admin.php?page=listora-settings`: "Sorry, you are not allowed to access this page." (capability `manage_listora_settings`), so no docs buttons are exposed.
