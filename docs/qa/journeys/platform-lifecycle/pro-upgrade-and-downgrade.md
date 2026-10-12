---
journey: pro-upgrade-and-downgrade
group: platform-lifecycle
actor: admin
mode: mixed
plugin: wb-listora
priority: critical
roles: [admin, editor, guest]
covers: [page-availability, feature-pages-notice, pro-activation]
prerequisites:
  - "Site reachable at $SITE_URL; Free active; Pro available to activate"
  - "Nobody else is testing Pro features on this site (feature toggles are shared state)"
estimated_runtime_minutes: 20
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, unbridged]
site_profile: required
supersedes: []
requires: [pro:feature pages notice, pro:feature toggles]
---

# Activating Pro changes nothing you did not ask for, and turning a feature off leaves no blank page

## Why
Activating Pro must not publish pages onto a live site; it OFFERS them once. Switching a Pro feature off, or deactivating Pro, must not leave a published page whose blocks render nothing (a blank public page looks broken and is indexed).

`Page_Availability::maybe_hide()` (Free `includes/core/class-page-availability.php`) serves a 404 for a page this plugin created (meta `_wb_listora_page_key`) whose feature is unavailable, when nothing else is on it, never for someone who can edit it. Filter `wb_listora_hide_unavailable_pages` disables it. Pro's `buy_credits` page is exempt (it explains itself while Monetization is off).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Feature toggles live in option `wb_listora_pro_features` (keys `reverse_listings`, `comparison`, `monetization`, ...), edited in Listora > Settings > Features. Page URLs: `wp eval` the page registry for Browse Needs, Post a Need, Compare, Buy Credits (slugs are owner-renamable).
- Record the starting toggle values to restore.

## Steps

### 1. Activating Pro creates nothing
- **Setup**: Pro features ON but their pages absent (set toggles first, then delete pages, so no OFF->ON transition fires).
- **Action**: activate Pro, load a few admin pages.
- **Expect**: no pages created; nothing in Pages or menus. Not run.

### 2. It offers once and takes no for an answer
- **Expect**: on `admin.php?page=listora-settings` only, a notice "Some Pro features have nowhere to send people yet." naming the missing pages with **Create N page(s)** and **No thanks** (`wb-listora-pro/includes/admin/class-feature-pages-notice.php`).
- **Action**: Create -> all created and mapped, notice gone. Separate run: **No thanks** -> never returns for that user (user meta `wb_listora_pro_pages_notice_dismissed`). Not run end to end.

### 3. The notice is visible, not merely present
- **Action**: on Settings, `[...document.querySelectorAll('.notice')].find(n => n.innerText.includes('nowhere to send')).getBoundingClientRect().height`.
- **Expect**: height > 0, exactly one `h1`, exactly one `.wp-header-end` (render helper `includes/class-render-helpers.php`). At 390 the notice does not overflow. Height needs a browser.

### 4. A feature switched off returns 404
- **Action**: Reverse Listings OFF, Comparison OFF, Monetization OFF. Request as {guest} the Needs, Post a Need, Compare and Buy Credits URLs.
- **Expect**: `404`, `404`, `404`, and **`200` for Buy Credits** (it explains itself).
- **Positive control**: with the features ON all four return 200.

### 5. A page the owner wrote on stays up
- **Action**: add a paragraph of your own above the Compare block, switch Comparison off, request as {guest}.
- **Expect**: `200` with the paragraph (`has_own_content()` treats any non-Listora text/blocks as theirs). Not run.

### 6. An editor can still reach it
- **Action**: request the Needs URL as {admin} and as {editor} while Reverse Listings is off.
- **Expect**: `200` (page renders, with the admin-bar Edit link).

### 7. Settings says what happened
- **Action**: `admin.php?page=listora-settings&tab=general` > Pages.
- **Expect**: Compare, Buy Credits, Post a Need, Browse Needs read **Feature off** with "This page exists but its feature is switched off, so it has nothing to show and the site returns a 404 for it. Turn the feature back on, or delete the page." Buy Credits reads "Feature off" although it still serves 200.

### 8. Deactivating Pro entirely behaves the same
- **Expect**: 404 for the bare Pro pages, 200 for one carrying the owner's paragraph, Free's own pages unaffected. Not run.

### 9. All of it is reversible
- **Action**: toggle the features back on.
- **Expect**: every page 200 again, same ids, no duplicates, nothing deleted.

## Role ladder
| Rung | Expect |
|---|---|
| {guest} | 404 / 200 per step 4 |
| {editor} | page still reachable (can `edit_post`), step 6 |
| {admin} (control) | page reachable; reads the status in Settings |

## Pass criteria
1. Activating Pro creates no page; the notice appears once and honours "No thanks".
2. An off feature 404s its bare page for visitors only; owner-written pages stay up.
3. Settings states the reason; re-enabling restores everything unchanged.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Blank page still 200 | availability gate | `wb-listora/includes/core/class-page-availability.php` (~:34-130) |
| Wrong availability for a key | `Page_Registry::is_available()` (~:326) fed by Pro callbacks | `wb-listora-pro/includes/class-pro-plugin.php` (~:231-298) |
| Settings badge wrong | | `includes/admin/class-pages-settings.php` (~:56-80) |
| Buy Credits 404 while Monetization off | exemption lost | `class-pro-plugin.php` (~:122-127) |

## Not verified
Steps 1, 2, 5, 8 (plugin activation/deactivation and content edits on the shared site), the rendered notice height, 390px layout, and the {editor} repeat of step 6.

## Cleanup
Restore the recorded toggle values and Pro activation state; delete any page created in step 2.
