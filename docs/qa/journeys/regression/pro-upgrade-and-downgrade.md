---
journey: pro-upgrade-and-downgrade
plugin: wb-listora
priority: critical
roles: [admin, editor, anonymous]
covers: [page-availability, feature-pages-notice, pro-activation]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "WB Listora Free active; Pro available to activate"
estimated_runtime_minutes: 20
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Activating Pro changes nothing you did not ask for, and turning a feature off does not leave blank pages

**Upgrade.** Activating Pro must not publish pages onto a live site; it OFFERS them once (Pro `Feature_Pages_Notice`).
**Downgrade.** Switching a Pro feature off, or deactivating Pro, must not leave a published page whose blocks render nothing:
`Page_Availability::maybe_hide()` (`wb-listora/includes/core/class-page-availability.php:34`) serves a 404 for a page this plugin
created (meta `_wb_listora_page_key`) whose feature is unavailable, when nothing else is on it, never for someone who can edit it.
Filter `wb_listora_hide_unavailable_pages` disables it. Pro's `buy_credits` page is exempt (it explains itself while Monetization
is off, `wb-listora-pro/includes/class-pro-plugin.php:122-127`).

Role ladder: anonymous gets the 404 / 200; an editor/administrator who can `edit_post` the page still sees it (with the admin-bar
Edit link); the administrator reads the status in Settings. Admin is the control for "page still reachable".

Feature toggles live in option `wb_listora_pro_features` (keys `reverse_listings`, `comparison`, `monetization`, ...), edited in
Listora > Settings > Features. Page slugs on this site: Browse Needs `/needs/`, Post a Need `/submit-a-request/` (renamed),
Compare `/compare-listings/`, Buy Credits `/buy-credits/`.

## Steps

### 1. Activating Pro creates nothing (NOT RUN)
- **Setup**: Pro features ON but their pages absent (set toggles first, then delete pages, so no OFF->ON transition fires).
- **Action**: activate Pro, load a few admin pages. **Expect**: no pages created, nothing in Pages or menus.

### 2. It offers once and takes no for an answer (NOT RUN end to end)
- **Expect**: on `admin.php?page=listora-settings` only, a notice "Some Pro features have nowhere to send people yet." naming
  the missing pages with a **Create N page(s)** button (`_n( 'Create %d page', 'Create %d pages' )`) and **No thanks**
  (`wb-listora-pro/includes/admin/class-feature-pages-notice.php:104-128`).
- **Action**: Create -> all created and mapped, notice gone. Separate run: **No thanks** -> never returns for that user
  (user meta `wb_listora_pro_pages_notice_dismissed`).

### 3. NEGATIVE: the notice is visible, not merely present
- **Action**: on Settings, `[...document.querySelectorAll('.notice')].find(n => n.innerText.includes('nowhere to send')).getBoundingClientRect().height`.
- **Expect**: height > 0, exactly one `h1`, exactly one `.wp-header-end` (verified in the DOM: 1 and 1; render helper
  `includes/class-render-helpers.php:218-250`). The height itself needs a browser.

### 4. A feature switched off returns 404 (verified)
- **Action**: Pro features: Reverse Listings OFF, Comparison OFF, Monetization OFF. Request logged out `/needs/`, `/submit-a-request/`, `/compare-listings/`, `/buy-credits/`.
- **Expect**: `404`, `404`, `404`, and **`200` for `/buy-credits/`** (it explains itself). Verified 2026-10-12; features restored.
- **Positive control**: with the features ON all four return 200.

### 5. NEGATIVE: a page the owner wrote on stays up (NOT RUN)
- **Action**: add a paragraph of your own above the Compare block, switch Comparison off, request logged out.
- **Expect**: `200` with the paragraph (`has_own_content()`, `class-page-availability.php:93`, treats any non-Listora text/blocks as theirs).

### 6. NEGATIVE: an editor can still reach it (verified for admin)
- **Action**: request `/needs/` as an administrator while Reverse Listings is off.
- **Expect**: `200` (the page renders, with the Edit link). Repeat as `qa_editor`: the same.

### 7. Settings says what happened (verified)
- **Action**: `admin.php?page=listora-settings&tab=general` > Pages.
- **Expect**: Compare Listings, Buy Credits, Post a Need, Browse Needs read **Feature off** with "This page exists but its feature
  is switched off, so it has nothing to show and the site returns a 404 for it. Turn the feature back on, or delete the page."
  Note: Buy Credits reads "Feature off" although it still serves 200.

### 8. Deactivating Pro entirely behaves the same (NOT RUN)
- **Expect**: 404 for the bare Pro pages, 200 for one carrying the owner's paragraph, Free's own pages unaffected.

### 9. All of it is reversible (verified for toggles)
- **Action**: toggle the features back on.
- **Expect**: every page 200 again, same ids, no duplicates, nothing deleted.

## Fail diagnostics
`wb-listora/includes/core/class-page-availability.php:34-130`; per-key availability `Page_Registry::is_available()`
(`includes/core/class-page-registry.php:326`) fed by Pro's `is_available` callbacks (`wb-listora-pro/includes/class-pro-plugin.php:231-298`);
Settings badge `includes/admin/class-pages-settings.php:56-80`.

## Not verified
The editor repeat of step 6 (`qa_editor`), steps 1, 2, 5, 8 (plugin activation/deactivation and content edits on the shared site), the rendered notice height, and 390px layout.
