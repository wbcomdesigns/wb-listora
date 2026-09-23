---
journey: essential-pages-on-activation
plugin: wb-listora
priority: critical
roles: [administrator]
covers: [activation, pages, page-registry, setup-wizard, card-10317818112]
prerequisites:
  - "A PRISTINE install - this cannot be seen on a site that already has the pages"
estimated_runtime_minutes: 6
---

# Activating the plugin leaves the site with its three pages

Activation runs after `init` has already fired, and the plugin's own init callbacks have not run in
that request, so the page registry is empty. `ensure_essential_pages()` correctly deferred itself to
`init` - an `init` that never came again - and nothing re-hooked it on the next request. A fresh
install therefore had no Directory, Add Listing or Dashboard page, `wb_listora_get_public_page_url(
'dashboard' )` returned an empty string, and the wizard told the owner the pages "were auto-created
when you activated the plugin" (card 10317818112).

Activation now leaves `wb_listora_pages_ensure_pending`, which `Plugin::maybe_ensure_pending_pages()`
consumes on the next request at `init` priority 6, once the registry has filled at priority 5.
PHPUnit: `tests/unit/EssentialPagesOnActivationTest.php`.

## Steps

### 1. Activate on a clean database and touch nothing else
- **Action**: fresh WordPress, activate wb-listora from the Plugins screen. Do NOT open the wizard
- **Expect**: `Page_Registry::all()` shows directory, submission and dashboard all `linked` with real ids, and `wb_listora_get_public_page_url( 'dashboard' )` is a real URL, not `""`

### 2. The same via WP-CLI
- **Action**: `wp plugin activate wb-listora` on a clean database
- **Expect**: identical result. CLI activation also runs after `init`, so it used to fail the same way

### 3. It adopts rather than duplicates
- **Action**: before activating, create a page containing `<!-- wp:listora/listing-grid /-->`
- **Expect**: that page is adopted as the directory page; no second Directory page is created

### 4. A deleted page stays deleted
- **Action**: with the plugin active, delete the Dashboard page, then deactivate and reactivate
- **Expect**: it is NOT recreated. `Page_Registry::ensure()` creates once per key per site by design

### 5. The wizard tells the truth
- **Action**: open the setup wizard's Pages step
- **Expect**: when all three exist it says "These pages are ready"; when some are missing it says they are created when you continue. It must never claim they were created at activation when they were not

## Pass criteria

1. A fresh activation, with the wizard untouched, leaves all three pages resolvable.
2. No duplicates on re-activation, and no resurrection of an owner-deleted page.
