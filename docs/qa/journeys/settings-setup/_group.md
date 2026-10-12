---
group: settings-setup
plugin: wb-listora
scope: First-run setup wizard, Settings screen tabs (save, validate, reset), page mapping, listing types, taxonomies, wp-admin owner notices
journeys: 13
site_profile: required
---
# Settings and setup: what an agent checks here

## Scope
Covers what an owner configures in wp-admin to get a directory running: the setup wizard, the Settings screen and its tabs, the Pages table and "Create page", the pages-mapped notice, Listing Types (list and editor), and the taxonomy screens. Pro tabs (SEO, Visibility, White Label, License) and Pro toggles are in the Pro folder: run this group first, then `wb-listora-pro/docs/qa/journeys/settings-setup/_group.md`.

Does NOT cover: what a setting does on the frontend (owned by the group that renders it: `directory-search`, `submission`, `reviews`, `map`); the Notifications tab email editor, Email Log and test sends (`emails-alerts`); Credits tab costs, webhook and checkout (`credits-checkout`); Import / Export and Migration tabs (`import-migration`); Integrations tab (`integrations-api`); Health Check, cron and uninstall (`platform-lifecycle`); the listing editor and its metabox (`admin-listings`).

## Surfaces
| # | Surface | URL / how to reach | Roles that see it | Rendered by (file) |
|---|---|---|---|---|
| S1 | Setup wizard, step `type` | `$SITE_URL/wp-admin/admin.php?page=listora-setup&step=type` (first admin load after activation redirects here once) | {admin} | includes/admin/class-setup-wizard.php `render_step_type` |
| S2 | Wizard steps `location`, `maps`, `pages`, `demo` | `...?page=listora-setup&step=location\|maps\|pages\|demo` | {admin} | class-setup-wizard.php `render_step_*` |
| S3 | Wizard `done` step and import progress | `...?page=listora-setup&step=done` | {admin} | class-setup-wizard.php `render_step_done`, `render_import_progress` |
| S4 | Wizard "already complete" landing (re-run entry) | `...?page=listora-setup` after a finished run | {admin} | class-setup-wizard.php `render_already_complete_notice` |
| S5 | Settings > General | `$SITE_URL/wp-admin/admin.php?page=listora-settings&tab=general` | {admin} | class-settings-page.php `render_general_tab`, class-pages-settings.php `render_section` |
| S6 | Settings > Features | `...?page=listora-settings&tab=features` | {admin} | class-features-settings-tab.php |
| S7 | Settings > Submissions | `...&tab=submissions` | {admin} | class-settings-page.php `render_submissions_tab` |
| S8 | Settings > Reviews | `...&tab=reviews` | {admin} | class-settings-page.php `render_reviews_tab` |
| S9 | Settings > Maps | `...&tab=maps` | {admin} | class-map-settings-tab.php |
| S10 | Settings > Advanced (wizard re-run, app sign-in, legal links, cache, maintenance) | `...&tab=advanced` | {admin} | class-advanced-settings-tab.php |
| S11 | Settings sidebar, tab header, save/reset footer and save notices (present on every tab) | any `listora-settings` tab | {admin} | class-settings-page.php `render` |
| S12 | Settings tabs owned elsewhere but whose single Save is tested here: Notifications, Credits | `...&tab=notifications`, `...&tab=credits` | {admin} | class-settings-page.php `render_notifications_tab`, class-credits-settings-tab.php |
| S13 | Pages-mapped notice ("is set up") on any Listora admin screen | `$SITE_URL/wp-admin/admin.php?page=listora` after activation | {admin} | includes/page-registry-helpers.php (markup ~line 243), assets/js/admin/pages-review-notice.js |
| S14 | "Create page" result notice | redirect of `admin-post.php?action=wb_listora_create_page` to Settings > General | {admin} | class-pages-settings.php `create_page`, `created_page_notice` |
| S15 | Listing Types list | `$SITE_URL/wp-admin/admin.php?page=listora-listing-types` | {admin} | includes/admin/class-type-editor.php |
| S16 | Listing type editor (new / edit) | `...?page=listora-listing-types&action=edit&type={type}` (new: "Add New Type") | {admin} | class-type-editor.php, assets/js/admin/type-editor.js |
| S17 | Categories, Locations, Features, Service Categories, Tags term screens | `$SITE_URL/wp-admin/edit-tags.php?taxonomy=listora_listing_cat&post_type=listora_listing` (and `_location`, `_feature`, `listora_service_cat`, `listing_tag`) | {admin} | class-admin.php (menu), class-admin-hubs.php (hub tabs), class-taxonomy-fields.php |
| S18 | Term archive page of a taxonomy term | `$SITE_URL/listing-category/{cat}/` (bases: listing-category, listing-location, listing-feature, listing-tag, listing-type) | {guest} | templates (owned by `directory-search`; opened here only to prove the assignment) |
| S19 | Every screen above as a refused user | same URLs as {editor} and {guest} | {editor}, {guest} | capability gates `manage_listora_settings`, `manage_listora_types` |

## Components on each surface
- **S1 type**: page `h1` "{brand} Setup"; progress bar `.listora-wizard__progress` with 6 `.listora-wizard__progress-step` (is-completed / is-current, `title` = step label: Directory Type, Location, Map Provider, Pages, Demo Content, Done!); card `.listora-wizard__card`; type grid `.listora-wizard__type-grid` of `.listora-wizard__type-card` (icon `.listora-wizard__type-icon`, name `.listora-wizard__type-name`, radio/checkbox); nav `.listora-wizard__nav` (no Back on first step, submit); "Skip setup" link `.listora-wizard__skip`.
- **S2 location**: `.listora-wizard__field` x2 (location text inputs), `.listora-wizard__field--coords` (lat, lng), `.listora-wizard__global-label` checkbox; nav with "← Back". **maps**: `.listora-wizard__map-options` with two `.listora-wizard__option-card` (OpenStreetMap enabled; Google Maps `--disabled` with "Requires API key + billing. Available with Pro plugin."), tile URL field. **pages**: `.listora-wizard__pages-list` rows (name, slug, `.listora-wizard-check` check mark when it exists, checkbox when offered), showcase list second `<ul>`. **demo**: `.listora-wizard__import-options` two `.listora-wizard__option-card` (import pack / "Skip demo content"), one card per demo pack.
- **S3 done**: `.listora-wizard__success` heading (follows import state), import progress block, `.listora-wizard__actions` buttons, `.listora-wizard-done-form` (finish form POST).
- **S4**: `.listora-wizard__card` with completion summary and `.listora-wizard__actions` ("re-run" explicit action).
- **S5 General**: sidebar group "Directory"; tab header `.listora-tab-header` (`GENERAL` title uppercased, description, "Documentation" link `.listora-docs-link`); block Basics: `#per_page`, `#listing_slug`, `#currency`, `[distance_unit]` select (Kilometers / Miles), `#near_me_radius`; block Listing Lifecycle: `[enable_expiration]` checkbox, `#default_expiration` (days), `#renewal_window_days`, `#default_renewal_duration_days`, `#default_renewal_credit_cost`; block Pages: `table.form-table` one row per registered page: label, status pill `.listora-page-status--{linked|missing|trashed|orphan|inactive}`, `select#page-{key}` (first option "Not mapped"), "Edit page" / "View page" links, "Create page" `a.button` when missing; footer "Reset this tab" + "Save Changes".
- **S6 Features**: own form `.listora-features-form` (action `features`), blocks "Core Features" and "SEO & Meta", rows `.listora-feature-row` (`__icon`, `__label`, `__desc`, `.listora-toggle` input `features[{key}]` with Enabled / Disabled status), `.listora-badge` "Pro" on Pro rows, own "Save Changes" (no Reset button: tab is in `skip_form_tabs`).
- **S7 Submissions**: Moderation radios (`[moderation]` Require admin approval / Auto-approve), Submission form style (`[submission_form_style]` wizard / single page), Max file size (read-only server value), `#max_gallery_images`, `#captcha_provider` (None, reCAPTCHA v3, Turnstile), `#captcha_site_key`, `#captcha_secret_key`, Social Links `[social_platforms][]` checkboxes; footer Reset + Save.
- **S8 Reviews**: `[reviews][auto_approve]`, `#review_min_length`, `[reviews][one_per_listing]`, `[reviews][allow_reply]`; footer.
- **S9 Maps**: provider radios (`[map_provider]`: OpenStreetMap, Google Maps disabled without Pro), `#wb_listora_map_tile_url`, `#wb_listora_map_tile_attribution`, tile source select ("Choose a tile source..." / "My own or another provider"), "Find a place" `#listora-map-center-search`, picker `.listora-submission__map-picker`, "Enter coordinates" disclosure with `#map_default_lat`, `#map_default_lng`, `#map_default_zoom`, Options: `[map_clustering]`, `[map_search_on_drag]`, `#map_max_markers`; footer.
- **S10 Advanced**: "Re-run setup" button, App sign-in `#wb_listora_app_password_login`, legal links (`#legal_privacy_policy_url` read-only from Settings > Privacy with Publish-it prompt, terms page select, `#legal_terms_url`, `#legal_community_guidelines_url`, `#legal_abuse_contact_email`), Cache `#search_cache_ttl`, maintenance buttons, footer.
- **S11 chrome**: `.wb-listora-admin` header (title "Settings"); `nav.listora-settings-sidebar` with groups (Directory: General, Features, Submissions, Reviews, Maps; Monetization: Credits; Communication: Notifications; Advanced: Integrations, Import / Export, Migration, Advanced; Pro adds SEO, Visibility, White Label, License) of `a.listora-settings-nav-item` (`.is-active`, `.listora-pro-badge` on locked tabs); notices `.notice.listora-notice` for "Settings saved.", per-section errors, "This tab was reset to its defaults. Other tabs kept their settings.", "Search index rebuild scheduled."
- **S12**: Notifications rows `.listora-email-row` (toggle `[notifications][key]`, Customized badge, Edit template, Preview), test-send `#listora-notification-test-event/recipient/send/status`, preview `#listora-email-preview`; Credits `#listora_default_listing_credit_cost`, `#listora_featured_credit_cost`, `#listora_featured_duration_days` + `.listora-featured-duration-preset`, `#wb_listora_low_credit_threshold`, webhook URL/secret `.listora-copy-field`, `.listora-help-block`.
- **S13**: `.notice.listora-notice.notice-info.is-dismissible.listora-pages-review-notice` with "{brand} is set up." text, page count, links, small "Dismiss" link, core X `.notice-dismiss` (JS posts the dismissal).
- **S14**: success notice "Page created and mapped." with "Edit it" link; error "The page could not be created..."; permission wp_die.
- **S15**: heading "Listing Types"; "Add New Type" button; table columns Type, Status, Fields, Listings, Search engines see; Default badge; Active / Draft status; per-row Edit, "Delete..." with reassign select `#listora-reassign-{slug}` ("Move listings to", "Move listings and delete type" / "Delete type"); empty state "No listing types yet".
- **S16**: "Back to Types"; title "Edit: {name}"; `#listora-save-type` "Save Type"; TYPE SETTINGS (`#listora-type-status`, `#listora-type-name`, `#listora-type-slug` locked after creation, `#listora-type-icon`, `#listora-type-color`, `#listora-type-schema`); FEATURES switches (`#listora-type-map`, `#listora-type-submission`, `#listora-type-services`, `#listora-type-default`); `#listora-type-moderation`, `#listora-type-reviews`, `#listora-type-expiry`; `#listora-field-builder`; CATEGORIES `#listora-type-categories` and FEATURES & AMENITIES `#listora-type-features` pickers (`data-listora-picker`: search, "Selected only" `data-listora-picker-only`, count "%1$s of %2$s selected", `data-listora-picker-none`); REVIEW CRITERIA `#listora-type-criteria` rows (`data-listora-criteria-key/label/remove`, "+ Add Criterion"); no-categories warning.
- **S17**: hub tab row `.listora-hub-tabs` (Categories, Locations, Features, Service Categories), WP list table, add-term form with extra Icon / Image / Color / Listing types fields (class-taxonomy-fields.php), hierarchy indent for Categories and Locations, flat Features.
- **S18**: term archive heading, listing grid for the term, per-type views.
- **S19**: capability refusal text ("You do not have permission...") or wp-login redirect.

## States to force
| Surface | States and how |
|---|---|
| S1-S3 | Pristine first run (delete `wb_listora_setup_data`, `wb_listora_settings` backed up, clear transient `wb_listora_wizard_session_{admin id}`); mid-session reload; unknown step `&step=zzz`; replayed POST with an old nonce; import running / finished / failed (demo step with background import); Skip setup; 390 viewport |
| S4 | Finished run vs an open session (open session shows step 1 instead) |
| S5 | `enable_expiration` off / on; `default_expiration` 0 vs 30; page status linked / missing (delete a mapped page) / trashed / orphan (page exists unmapped) / inactive (Pro feature off with its page present); 100+ WP pages in the select; mapped page switched |
| S6 | Free only (no Pro rows) vs Pro active; a toggle flipped and saved; Settings > Features opened by {editor} |
| S7 | Captcha None / provider; social platforms all ticked / one unticked; long secret key |
| S9 | Provider OSM vs Google (Pro); tile source preset vs custom; out-of-range lat/lng/zoom |
| S10 | Privacy page unset / draft / published; terms page selected AND external URL |
| S11 | `settings-updated=true`, `listora_reset=1`, `listora_reindexed=1` query flags; a refused section (malformed Stripe key) producing a warning; no-JS (`?tab=` server activation) |
| S13 | Fresh activation (transient `wb_listora_pages_review_pending`), dismissed by X, dismissed by link, second admin tab |
| S15 | Zero custom types; many types (50+); a type in Draft; a type with listings (delete forces reassign) |
| S16 | New vs edit; type with no categories; 300+ categories in the picker; "Selected only" on; 0 criteria |
| S17 | Empty taxonomy; 3-level location tree; 1000+ terms; term with no listings |

## Settings that change this group
| Setting (option key / admin location) | Values | What must visibly change, where |
|---|---|---|
| `wb_listora_settings[per_page]` (General) | integer, bounded | cards per page on `{directory}` (owned by `directory-search`) |
| `[listing_slug]` (General) | slug | single listing URLs; rewrite flush on save |
| `[enable_expiration]`, `[default_expiration]` (General) | off/on, days (0 = lifetime) | expiry note text on General; Add Listing expiry text (`plans-expiry-renewal`) |
| `[distance_unit]`, `[near_me_radius]` (General) | km / mi, number | unit label next to near-me radius on the search bar |
| Page mapping `wb_listora_*_page_id` (General > Pages) | page id or 0 | links everywhere that read the registry; status pill |
| `wb_listora_features` / `features[key]` (Features) | on / off | surface removed or present; Pro toggles in Pro group |
| `[moderation]`, `[submission_form_style]` (Submissions) | approval / auto, wizard / single | new listing status; Add Listing layout (`submission`) |
| `[reviews][...]` (Reviews) | flags, min length | review form and moderation (`reviews`) |
| `[map_provider]`, `[map_default_*]`, `[map_clustering]` (Maps) | values | map blocks (`map`) |
| Type editor switches (map, submission, services, reviews, expiry) | on / off | per-type behaviour on Add Listing, listing page, Services box |
| `wb_listora_app_password_login` (Advanced) | on / off | app sign-in flow (`integrations-api`) |
| Reset this tab | confirm | only that tab returns to defaults |

## Fixtures and setup
- Resolve placeholders from `docs/qa/sites/<site>.json` (`QA_SITE=<host>`).
- Back up before any save: `wp option get wb_listora_settings --format=json > $SCRATCH/settings-baseline.json`; also `wb_listora_features`, `wb_listora_setup_data`.
- Page rows: `wp eval 'echo \WBListora\Core\Page_Registry::get_id( "directory" );'` for each key.
- Content for term and type checks: `wp eval-file docs/qa/fixtures/2.0-fixtures.php`.
- Scratch records use the `QA-` prefix (`qa-type`, `QA Category`).
- Restore afterwards: `wp option update wb_listora_settings < $SCRATCH/settings-baseline.json --format=json`, delete `QA-` types and terms, re-map any page you unmapped, `wp transient delete wb_listora_pages_review_pending` only if you set it.

## Run order
1. `setup-wizard-first-run.md` (browser, critical): redirect, six steps, done heading, replay protection.
2. `setup-pages-created-adopted-once.md` (mixed, critical): pages created on activation, adopted, showcase offered (needs pristine DB).
3. `pages-review-notice-dismiss-persists.md` (browser, high): the X and Dismiss both persist.
4. `pages-are-created-once.md` (mixed, critical): ensure once, "Create page" recovers a deleted page, Pages table.
5. `settings-tabs-save-validate-reset.md` (browser, high): tabs save independently, numbers clamp, reset one tab, docs links.
6. `settings-one-save-per-tab.md` (browser, critical): one form and one Save per tab, malformed key refused.
7. `save-and-reset-confirm-success.md` (browser, normal): confirmation survives the redirect.
8. `listing-types-crud.md` (mixed, high): create, edit, built-ins, Services switch, delete reassign.
9. `type-editor-selected-only-saves.md` (browser, high): picker filter never alters saved ids.
10. `taxonomy-crud.md` (browser, normal): five taxonomies, hierarchy, term pages.
11. `page-registry-heals-stale-mapping.md` (cli, high): deleted or trashed mapped page re-adopted.
12. `admin-features-checkbox-grid.md` (mixed, high): misfiled here, owned by `admin-listings`; run it with that group.

Then run: `wb-listora-pro/docs/qa/journeys/settings-setup/_group.md`.
13. `privacy-policy-url-draft-state.md` - mode browser, high. Legal rows tell a draft page apart from no page

## Group-specific micro checks
- G-01 Wizard progress bar shows exactly 6 `.listora-wizard__progress-step`, exactly one `is-current`, and every earlier one `is-completed`; `title` attributes read Directory Type, Location, Map Provider, Pages, Demo Content, Done!.
- G-02 The wizard has no "← Back" on step `type` and has one on every later step except `done`, which has no nav at all.
- G-03 Each settings tab header title is the tab label in capitals (`GENERAL`, `FEATURES`...) and matches the highlighted sidebar item text exactly.
- G-04 On `?tab=reviews` loaded cold (no hash, JS off) the sidebar item and the section both carry `is-active`; an unknown `?tab=zzz` falls back to General, not a blank pane.
- G-05 Only General, Submissions, Reviews, Maps, Notifications, Credits, Advanced (+ Pro form tabs) show "Reset this tab" and "Save Changes"; Features, Integrations, Import / Export, Migration have no Reset button (Features has its own Save).
- G-06 The Reset confirm dialog names the tab being reset (`data-tab-label`) and the result notice reads "This tab was reset to its defaults. Other tabs kept their settings."; a value saved on another tab is unchanged afterwards.
- G-07 Pages table: the status pill text equals the `select` state (Linked has a non-zero mapped id, Missing shows "Create page", Feature off shows the "switched off" sentence); "View page" and "Edit page" appear only when an id is mapped.
- G-08 Pages table has no duplicate rows for one key, and the row count equals the number of registered keys (six Free; Pro adds Compare, Buy Credits, Post a Need, Browse Needs).
- G-09 "Default expiration" 0 reads as lifetime in the help text; entering -1 or 99999 clamps server-side and the field re-renders the clamped value, not the typed one.
- G-10 `#near_me_radius` hint text names the unit currently selected in `[distance_unit]` after a save with Miles.
- G-11 The Max file size row is read-only text equal to `min(upload_max_filesize, post_max_size)` on the server, never an input.
- G-12 Listing Types list: the Listings count per row equals `wp post list --post_type=listora_listing --format=count` filtered by that type; the Default badge sits on exactly one row.
- G-13 Type editor slug input is editable on a new type and read-only on an existing type, and the caption "Cannot be changed after creation" is shown only in the second case.
- G-14 Picker count "%1$s of %2$s selected" equals the number of checked boxes and the total rows; turning "Selected only" on never changes the count or the saved ids.
- G-15 Deleting a type with listings requires choosing "Move listings to"; the button text switches from "Delete type" to "Move listings and delete type" only when listings exist.
- G-16 Wizard Pages step: a row with `.listora-wizard-check` is never also offered as a checkbox; the count in the done summary equals the pages that exist afterwards.
- G-17 The pages-mapped notice states a page count equal to the number of mapped registry rows, and both dismiss paths (X, "Dismiss") keep it gone on the next admin load for that user only.
- G-18 Every "Documentation" link (`.listora-docs-link`) opens in a new tab with `rel="noopener noreferrer"` and its target slug matches the current tab.
- G-19 Term screens: the hub tab row highlights the open taxonomy and the Listora menu item stays open (current) on every taxonomy and CPT screen.
- G-20 Social Links: unticking a platform removes it from the Add Listing form and the listing sidebar, and re-ticking brings back links members already saved.

## Known defects (expected-fail)
None in this folder's journeys (no `expected-fail` steps). Journey `setup-wizard-first-run.md` lists `fails_on` shas for earlier regressions only.

## Not covered yet
- Settings > Advanced (cache TTL, app sign-in switch, legal links, maintenance buttons) has no journey step beyond the wizard re-run entry.
- Settings > Submissions fields (captcha provider and keys, social platforms, gallery cap, form style) have no save and effect journey.
- Settings > Maps tab save and tile-source presets (owner only in `map`, but the save path and bounds are not asserted here).
- Settings > Reviews save and bounded `review_min_length` are not stepped.
- Listing type editor: review criteria rows, `#listora-type-expiry`, moderation override and the no-categories warning have no step.
