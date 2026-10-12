---
journey: admin-shell-header-and-notices
group: admin-listings
actor: admin
mode: browser
plugin: wb-listora
priority: critical
roles: [administrator, editor, member-owner]
covers: [admin-page-header, wp-header-end, notices-below-header, hub-tabs, one-h1-per-screen, third-party-notices-hidden, 2.0.0-admin-shell, BC-10190572606, BC-10154189084, BC-10184284834, BC-10154198434, admin-notices, computed-style, notice-not-duplicated, "card 10332070181", "card 10322574958", menu-prompt, pro-feature-pages-notice]
prerequisites:
  - "Site reachable at $SITE_URL; {admin} session; Free active, Pro optional (its screens are marked 'Pro')"
  - "A deterministic notice trigger: Leads retention save (Listings > Leads) or the Reviews approve action"
  - "For the Maps notice: setting map_provider = google with google_maps_key empty (save the original, restore afterwards)"
  - "For the unlinked-pages notice: at least one Listora page that sits in no nav menu; run once with Pro active and once without"
estimated_runtime_minutes: 14
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default light]
supersedes:
  - ora:admin/2.0-admin-shared-page-header-and-notices.md
  - ora:regression/admin-notices-are-actually-visible.md
  - ora:regression/admin-notices-not-duplicated.md
  - ora:regression/pages-notice-settings-only.md
fails_on: step 7 and 8 pre-fix sha unknown (three cards were signed off as verified and shipped invisible); step 12 fails when a Listora header prints its own wp-header-end on a core list table
site_profile: required
---

# Every Listora admin screen has one header, and its notices appear once, visible, on the right screens

## Why
Owners read admin notices to know a save worked or what to fix. A notice that is present in markup but `display:none` (Listora hides non-`listora-notice` notices inside `.wb-listora-admin`), duplicated by a second `wp-header-end`, rendered above the title, or shown on the wrong screen is the same failure from the owner's side: silence or noise. Lessons from three cards that were fixed correctly, called "browser-verified" and shipped invisible: assert computed style, never `innerText`.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Rule: every `class="notice` inside a `.wb-listora-admin` wrapper must carry `listora-notice` (`assets/css/admin.css` hides `.wb-listora-admin .notice:not(.listora-notice)`, `.update-nag`, `.updated:not(.listora-notice)` with `display:none !important`).
- Header helper: `wb_listora_render_admin_header()` prints `.listora-page-header` (decorative `<i data-lucide aria-hidden=true>`, one `h1.listora-page-header__title`, `p.listora-page-header__desc`) then exactly one `<hr class="wp-header-end">`. Core list tables (Listings, Categories, Plans) keep core's marker; the listing-type editor prints its own once.

## Steps

### 1. One header per screen ({admin}, 1440)
- **Action**: for each screen under `$SITE_URL/wp-admin/`: Dashboard `admin.php?page=listora`, Listing Types `listora-listing-types`, Reviews `listora-reviews`, Claims `listora-claims`, Leads `listora-leads`, Email Log `listora-email-log`, Settings `listora-settings` (any `&tab=`); Pro: Badges `listora-badges`, Analytics `listora-analytics`, Audit Log `listora-audit-log`.
- **Expect** per screen: exactly one `h1` in the page content (`h1.listora-page-header__title`, title equals the screen name), one `hr.wp-header-end` after the header, one description line, no second title bar above; WordPress "Add New" does not duplicate the header.
- **Core list screens** (Listings `edit.php?post_type=listora_listing`, Categories/Locations/Features `edit-tags.php?taxonomy=...`): keep WordPress's own `h1`, carry the hub tab row, not the shared header; exactly one `h1`.

### 2. Hub tab row and menu
- **Action**: open Listings > Leads; Moderation (Reviews, Claims, Needs, Moderators with Pro); Listing Types (Listing Types, Badges with Pro); Tools (Audit Log, Email Log, Webhooks with Pro); Categories (Categories, Locations, Features, Service Categories).
- **Expect**: `nav.listora-hub-tabs[aria-label="<hub name>"]`; active tab `.is-active` + `aria-current="page"`; single-tab hubs (Dashboard, Settings, Analytics) show no tab row; the sidebar shows ONE item per hub (nine) highlighted while any of its tabs is open.

### 3. Notices appear below the header (positive control first)
- **Action**: Listings > Leads: retention "6 months", Save. Then Moderation > Reviews: Approve a pending review. Then Claims: an approve/reject.
- **Expect**: after redirect `div.notice.notice-success.listora-notice.is-dismissible` "Retention saved. Older leads were removed." whose `getBoundingClientRect().top` is greater than the header's bottom, above the form, dismissible (the X removes it); "<n> review approved. It now shows on the listing." below the header; same for Claims.
- **Negative**: no notice above the title or inside the title row.

### 4. Foreign notices are hidden, Listora's are not
- **Action**: mu-plugin `add_action( 'admin_notices', function () { echo '<div class="notice notice-warning"><p>third-party notice</p></div>'; } );`
- **Expect**: on `admin.php?page=listora-reviews` the text is in the DOM with `getComputedStyle(el).display === 'none'`; on a core screen (Dashboard > Updates, Posts) it is visible (proves the notice code works); the Leads notice of step 3 is `display:block`.

### 5. The setup wizard is quiet and has Skip
- **Action**: with step 4's mu-plugin present open `admin.php?page=listora-setup&rerun=1&step=type`, then `step=demo`, then `step=done`.
- **Expect**: no `.notice` elements on the wizard (it removes every `admin_notices` action; only its own demo-import warning returns); "Skip setup" on type, location, maps, pages and demo steps and NOT on done; Skip closes the run and lands on Dashboard. Remove the mu-plugin afterwards.

### 6. Narrow viewport (390)
- **Expect**: title row wraps (description under the title, actions under it), no horizontal scroll, hub tabs scroll or wrap without clipping the active tab, header action buttons >= 40 px tall.

### 7. Maps notice renders and is computed-visible
- **Setup**: `wp eval '$s=get_option("wb_listora_settings"); $s["map_provider"]="google"; $s["google_maps_key"]=""; update_option("wb_listora_settings",$s);'` (save the original first).
- **Action**: open `admin.php?page=listora-settings&tab=maps`; in the browser run `[...document.querySelectorAll('.wb-listora-admin .notice')].map(el => ({cls: el.className, display: getComputedStyle(el).display, visibility: getComputedStyle(el).visibility}))`.
- **Expect**: every row `display` is not `none` and `visibility: visible`; among them `.notice.listora-notice.notice-info.inline` "OpenStreetMap is still live. You selected Google Maps above, but no API key is saved yet - add a key below to switch the frontend." (Pro `class-google-maps.php`).
- **Positive control**: provider back on OSM shows "Maps use the tile source above. To use Google Maps, choose Google as the provider and save an API key below." and NOT the "still live" one.

### 8. Licence notice on a Listora screen (Pro, unlicensed)
- **Expect**: "WB Listora Pro is running, but plugin updates are paused until this site has an active license. Your website features are unaffected." with "Enter License Key" (`page=listora-settings&tab=license`) and "Dismiss"; `.notice.listora-notice.notice-warning`, visible by the step 7 measurement; always on Plugins and Settings > License, elsewhere until each user dismisses. Brand name follows White Label. Not verified live (reference site is licensed).

### 9. The counterfactual proves the assertion has teeth
- **Action**: `const el=document.querySelector('.wb-listora-admin .notice.listora-notice'); const withClass=getComputedStyle(el).display; el.classList.remove('listora-notice'); const without=getComputedStyle(el).display; el.classList.add('listora-notice'); ({withClass, without});`
- **Expect**: `{ withClass: "block", without: "none" }`. Anything else means the suppression rule changed and this journey needs rewriting.

### 10. Static rule
- **Action**: `bash bin/coding-rules-check.sh` ("Rule 10 - every Listora admin notice carries listora-notice").
- **Expect**: passes; negative control: a deliberately regressed notice makes it fail. Open finding: Pro `class-outgoing-webhooks.php` (`<div class="notice notice-error">` for `listora_webhook_error=registry_unavailable`) omits the class and is hidden; Pro's own `bin/coding-rules-check.sh` has no such rule. `expected-fail: Pro webhook registry_unavailable notice invisible`.

### 11. Notices show once on core list screens
- **Action**: `edit.php?post_type=listora_listing&updated=1`; `edit-tags.php?taxonomy=listora_listing_cat&post_type=listora_listing`; also `paged=2` and `s=` variants.
- **Expect**: exactly one `hr.wp-header-end` in the DOM and `document.querySelectorAll('#message').length === 1` after `common.js` ran; one notice "1 post updated."; no second copy after the table header.

### 12. Plugin pages keep one marker and one notice
- **Expect**: Listing Types, Settings and Reviews each have exactly one marker and their notices render once below `.listora-page-header`. Positive control: `page=listora-reviews&listora_notice=approve&n=1` shows one "1 review approved..." notice with `display` not `none`.

### 13. The unlinked-pages notice appears on exactly one screen
- **Setup**: a Listora page in no nav menu. Both notices match ONE screen id, `listora_page_listora-settings` (Free `Menu_Prompt::SETTINGS_SCREEN_ID`, Pro `Feature_Pages_Notice::SETTINGS_SCREEN_ID`); assert the two constants are equal.
- **Action**: open `edit.php?post_type=listora_listing`, `post.php?post=<id>&action=edit`, the category/location/feature/service-category `edit-tags.php` screens, then `admin.php?page=listora`, then `admin.php?page=listora-settings`.
- **Expect**: the content screens and the landing page contain neither "These pages exist but nothing on your site links to them." (Free) nor "Some Pro features have nowhere to send people yet." (Pro); Settings shows the Free notice with its Add to menu / Create action and a **No thanks** link (positive control: this catches an over-tight fix); Pro's notice shows only while a Pro feature page is missing and not dismissed (delete one Pro page on a scratch site for the positive control).
- **Dismissal**: **No thanks** (Free user meta `wb_listora_menu_prompt_dismissed`; Pro `wb_listora_pro_pages_notice_dismissed`) stays dismissed for that user; a second administrator still sees it. Not verified live (dismiss click, Pro positive control).
- **Automated**: Free `tests/integration/MenuPromptScreenScopeTest.php`, Pro `tests/integration/FeaturePagesNoticeScopeTest.php`.

## Role ladder

| Rung | Expect |
|---|---|
| {admin} | all steps |
| {editor} (reviews + claims only) | Moderation hub with Reviews and Claims tabs only (no Needs/Moderators without Pro), shared header on both, notices visible by the step 7 measurement; `admin.php?page=listora-settings` is "Sorry, you are not allowed to access this page."; no Leads/Settings/Listing Types items; no unlinked-pages notice (needs `edit_theme_options`) |
| {owner} (Subscriber) / {guest} | no Listora menu; `admin.php?page=listora-reviews` not allowed / login redirect |

## Pass criteria
1. Every Listora screen has one `h1.listora-page-header__title`, one `hr.wp-header-end` after it, one description line.
2. Hub tab rows correct; one menu item per hub.
3. Success notices render below the header, computed-visible, dismissible, once.
4. Third-party notices hidden on Listora screens; wizard notice-free with Skip hidden on the last step.
5. The unlinked-pages notice appears only on Settings.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Two titles | screen prints its own `<h1>` besides the helper | the screen's render method |
| Notice above header | `hr.wp-header-end` missing or before the header | `includes/class-render-helpers.php` `wb_listora_render_admin_header` |
| Listora notice present but invisible | missing `listora-notice` | the notice markup; CSS `assets/css/admin.css` |
| Rule 10 silent | detector broken | `bin/coding-rules-check.sh` |
| Two markers / duplicated notice on a core table | a Listora header printing `wp-header-end` there | `includes/class-render-helpers.php` (plugin-page only); `includes/admin/class-type-editor.php` prints its own once |
| Third-party notice visible | scope class missing | the screen wrapper `<div class="wrap wb-listora-admin">` |
| Pages notice on content screens | substring screen match | `includes/admin/class-menu-prompt.php`, Pro `class-feature-pages-notice.php` |

## Not verified
Steps 8, 13 dismissal and Pro positive control; the client-side `common.js` move in step 11 was counted from DOM markers only.

## Cleanup
Remove the mu-plugin, restore `wb_listora_settings` map keys, delete throwaway pages.
