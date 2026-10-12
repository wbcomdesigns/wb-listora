---
journey: essential-pages-on-activation
plugin: wb-listora
priority: critical
roles: [administrator]
covers: [activation, pages, page-registry, setup-wizard, card-10317818112]
prerequisites:
  - "A PRISTINE install (fresh database): this cannot be seen on a site that already has the pages"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
changed: true
---

# Activating the plugin leaves the site with its pages

Activation runs after `init` has fired and the plugin's own init callbacks have not run in that request, so the page registry is empty. `Activator::ensure_essential_pages()` therefore defers itself and leaves the option `wb_listora_pages_ensure_pending` (`includes/class-activator.php:581`); `Plugin::maybe_ensure_pending_pages()` (`includes/class-plugin.php:123`, hooked at `init` priority 6, `:190`) consumes it on the next request once the registry has filled at priority 5. PHPUnit: `tests/unit/EssentialPagesOnActivationTest.php`.

Verified via: source read plus the live state of this (non-pristine) site on 2026-10-12: `Page_Registry::all()` lists `directory` (id 8, /listings/), `submission` (6, /add-listing/), `dashboard` (7, /my-listings/), `categories` and `featured` (281), `calendar` (806), `compare` (9), `buy_credits` (10), and Pro `post_need` / `browse_needs`, all `linked`; `wb_listora_get_public_page_url()` returns real URLs. Steps 1-4 need a fresh database and were NOT run.

## Steps

### 1. Activate on a clean database and touch nothing else
- **Action**: fresh WordPress, activate wb-listora from Plugins. Do NOT open the wizard. Load any admin page once (that is the "next request").
- **Expect**: `wp eval 'foreach( \WBListora\Core\Page_Registry::all() as $k => $v ) echo $k, " ", $v["status"], "\n";'` shows directory, submission, dashboard `linked` with real ids; `wb_listora_get_public_page_url( 'dashboard' )` is a URL, not `""`; option `wb_listora_pages_ensure_pending` is gone.

### 2. The same via WP-CLI
- **Action**: `wp plugin activate wb-listora` on a clean database, then any `wp` command.
- **Expect**: identical result.

### 3. Adopts rather than duplicates
- **Action**: before activating create a page containing `<!-- wp:listora/listing-grid /-->`.
- **Expect**: adopted as the directory page; no second Directory page.

### 4. A deleted page stays deleted
- **Action**: delete the Dashboard page, deactivate, reactivate.
- **Expect**: NOT recreated (`Page_Registry::ensure()` creates once per key per site; `includes/core/class-page-registry.php:585`).

### 5. The wizard tells the truth
- **Action**: open the setup wizard Pages step (`includes/admin/class-setup-wizard.php:676-696`).
- **Expect**: when all exist the heading reads "These pages are ready" and the text "Each one comes with the right blocks already in place..."; when some are missing "Your directory pages" and "Anything missing below is created when you continue." (a missing row reads "(will be created when you continue)"). It never claims they were created at activation when they were not.

### 6. Role ladder
- Only an administrator activates plugins. Another member or editor cannot reach the wizard; anonymous gets the login redirect. Admin is the actor, not a control, so the ladder is single-role by nature.

## Pass criteria

1. A fresh activation with the wizard untouched leaves directory, submission and dashboard resolvable.
2. No duplicates on re-activation; an owner-deleted page is not resurrected.

## Fail diagnostics

| Symptom | File |
|---|---|
| Dashboard URL empty on fresh install | `includes/class-activator.php:581-600` defer flag, `includes/class-plugin.php:190` |
| Duplicate Directory pages | `includes/core/class-page-registry.php:585` `ensure()` adopt-first |
| Wizard text wrong | `includes/admin/class-setup-wizard.php:686-696` |

## Not verified

Steps 1-4 (need a pristine database). Wizard step 5 not opened.
