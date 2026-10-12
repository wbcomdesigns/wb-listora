---
journey: setup-pages-created-adopted-once
group: settings-setup
actor: admin
mode: mixed
plugin: wb-listora
priority: critical
roles: [administrator, editor, guest]
covers: [activation, pages, page-registry, setup-wizard-pages-step, showcase-pages, adopt-not-duplicate, deleted-stays-deleted, upgrade-safety, card-10317818112, card-10167582244]
prerequisites:
  - "Steps 1-4: a PRISTINE install (fresh database): cannot be seen on a site that already has the pages"
  - "Steps 6-9: a site where the calendar/categories/featured pages do not yet exist (else use the 'already on your site' branch, and a clean install or Playground for the 'offered' branch)"
estimated_runtime_minutes: 10
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [wp-admin default]
site_profile: required
supersedes:
  - "ora:regression/essential-pages-on-activation.md"
  - "ora:regression/showcase-pages.md"
fails_on: []
---

# Activation leaves the site with its pages, showcase pages are offered not imposed, and nothing is duplicated or resurrected

## Why
A fresh activation that leaves the Dashboard URL empty strands the member; a second Directory page, or a page the owner deleted coming back, breaks the owner's site. The wizard must not claim pages were created when they were not.

Activation runs after `init`, before the plugin's own init callbacks, so the registry is empty: `Activator::ensure_essential_pages()` defers (option `wb_listora_pages_ensure_pending`, `includes/class-activator.php:581`) and `Plugin::maybe_ensure_pending_pages()` (`includes/class-plugin.php:123`, `init` priority 6, `:190`) consumes it on the next request. The registry also knows three showcase pages (`includes/page-registry-helpers.php:369-415`): `categories` ("Browse Categories", block `listora/listing-categories`, option `wb_listora_categories_page_id`), `featured` ("Featured Listings", `listora/listing-featured`, `wb_listora_featured_page_id`) and `calendar` ("Events Calendar", `listora/listing-calendar`, `wb_listora_calendar_page_id`); offered, never imposed. PHPUnit: `tests/unit/EssentialPagesOnActivationTest.php`, `tests/unit/ShowcasePagesTest.php`.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Pages section lives on Settings > General (`includes/admin/class-pages-settings.php`); create action `admin-post.php?action=wb_listora_create_page`.
- State probe: `wp eval 'foreach( \WBListora\Core\Page_Registry::all() as $k => $v ) echo $k, " ", $v["status"], "\n";'`.

## Steps

### 1. Activate on a clean database and touch nothing else
- **Action**: fresh WordPress, activate wb-listora from Plugins, do NOT open the wizard; load any admin page once.
- **Expect**: the probe shows `directory`, `submission`, `dashboard` `linked` with real, distinct, non-zero ids, each a published page; `wb_listora_get_public_page_url( 'dashboard' )` is a URL, not `""`; option `wb_listora_pages_ensure_pending` is gone.
- **On fail**: `class-activator.php:581-600` defer flag, `class-plugin.php:190`.

### 2. The same via WP-CLI
- **Action**: `wp plugin activate wb-listora` on a clean database, then any `wp` command.
- **Expect**: identical result.

### 3. Adopts rather than duplicates
- **Action**: before activating create a page containing `<!-- wp:listora/listing-grid /-->`.
- **Expect**: adopted as the directory page; no second Directory page (`Page_Registry::ensure()` adopt-first, `includes/core/class-page-registry.php:585`).

### 4. A deleted page stays deleted
- **Action**: delete the Dashboard page, deactivate, reactivate.
- **Expect**: NOT recreated. The `ensure()` ledger contract (created once per key, Missing row, Create page recovery) is owned by `settings-setup/pages-are-created-once.md` step 4; this step only proves the reactivation path.

### 5. The wizard's Pages step tells the truth
- **Action**: open the setup wizard Pages step (`includes/admin/class-setup-wizard.php:676-696`).
- **Expect**: when all exist the heading reads "These pages are ready" with "Each one comes with the right blocks already in place..."; when some are missing "Your directory pages" and "Anything missing below is created when you continue." (a missing row reads "(will be created when you continue)"). It never claims they were created at activation when they were not.

### 6. The wizard offers the showcase pages
- **Action**: run the wizard (`admin.php?page=listora-setup&rerun=1`) to the Pages step on a site without them.
- **Expect**: an "Optional pages" `h3` with a checkbox (`name="showcase_pages[]"`) each for Browse Categories, Featured Listings and Events Calendar; anything already present is listed as "already on your site" instead of offered (the other branch's positive control).

### 7. Only what was ticked is created
- **Action**: tick two of three, continue.
- **Expect**: exactly those two exist and resolve through `Page_Registry::get_id()`; the third is still missing; Settings > General > Pages shows rows for all three with a "Linked" select (`#page-categories` / `#page-featured` / `#page-calendar`) and "Edit page | View page".

### 8. Adoption, not duplication, for showcase pages
- **Action**: on a site with a hand-made page containing `<!-- wp:listora/listing-categories /-->`, use Settings > General > Pages > Create for that key.
- **Expect**: the existing page is adopted and mapped; no second page.

### 9. A deleted showcase page stays deleted; upgrade creates nothing
- **Action**: delete a created showcase page, run the wizard again; separately update the plugin on a site that never had these pages.
- **Expect**: not resurrected; no new pages appear after the update.
- **Read-back**: `wp post list --post_type=page --format=count` before/after the upgrade is identical.

### 10. Scale and multisite
- **Action**: activate network-wide on a multisite with several sites.
- **Expect**: each site gets its own registry and pages, none shared. Not verified live (added in consolidation).

## Role ladder
| Rung | Expect |
|---|---|
| {admin} | steps 1-9 (the actor, not a control; single-role by nature) |
| {editor} | "Sorry, you are not allowed to access this page." for the wizard and Settings |
| {guest} | login redirect for both; the created pages are public (the block renders for a guest) |

## Pass criteria
1. A fresh activation with the wizard untouched leaves directory, submission and dashboard resolvable.
2. Nothing is created without the owner asking (showcase pages); no duplicates on re-activation; an owner-deleted page is not resurrected; upgrades create nothing.
3. The wizard text matches the real state.

## Fail diagnostics
| Symptom | File |
|---|---|
| Dashboard URL empty on fresh install | `includes/class-activator.php:581-600`, `includes/class-plugin.php:190` |
| Duplicate Directory pages | `includes/core/class-page-registry.php:585` `ensure()` adopt-first |
| Wizard text wrong | `includes/admin/class-setup-wizard.php:686-696` |
| Showcase key missing from Settings > Pages | `includes/page-registry-helpers.php:369-415`, `class-pages-settings.php` |

## Not verified
- Steps 1-4 need a pristine database and were not run; the live state of a non-pristine site (all keys `linked`, real URLs) was read; wizard step 5 not opened; the create/adopt/resurrect steps (7-9) create pages and were not executed (source and the General tab Pages section as {admin} were read).

## Cleanup
- Pages created or adopted by steps 1-9 are the test data: trash the ones this run created with `wp post delete <id> --force` (Directory, Add Listing, Dashboard, Browse Categories, Featured Listings, Events Calendar, and the hand-made adopt fixtures from steps 3 and 8).
- Delete the registry options for the removed pages (`wb_listora_categories_page_id`, `wb_listora_featured_page_id`, `wb_listora_calendar_page_id`, plus the essential-page ids) and `wb_listora_pages_ensure_pending`.
- Steps 1-4 ran on a pristine database: discard that database or site rather than restoring it.
