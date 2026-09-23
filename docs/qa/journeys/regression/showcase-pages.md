---
journey: showcase-pages
plugin: wb-listora
priority: normal
roles: [administrator]
covers: [pages, page-registry, setup-wizard, blocks, card-10167582244]
prerequisites:
  - "A site where the calendar/categories/featured pages do not yet exist"
estimated_runtime_minutes: 5
---

# The calendar, categories and featured blocks have somewhere to live

Three shipped blocks had no page. They were registered and theme-defended, and an owner only found
them by hunting through the block inserter. The registry now knows all three, so Settings → Pages
can create and heal them, and the setup wizard offers them.

They are **offered, not imposed**: a running site does not wake up to three new pages after an
update (owner decision, 2026-09-18). PHPUnit: `tests/unit/ShowcasePagesTest.php`.

## Steps

### 1. The wizard offers them
- **Action**: run the setup wizard to the Pages step on a site without them
- **Expect**: an "Optional pages" list with a checkbox each for Browse Categories, Featured Listings and Events Calendar. Anything already present is listed as "already on your site" instead of offered

### 2. Only what was ticked is created
- **Action**: tick two of the three, continue
- **Expect**: exactly those two exist afterwards and resolve in the registry; the third is still missing

### 3. Adoption, not duplication
- **Action**: on a site that already has a hand-made page containing `<!-- wp:listora/listing-categories /-->`, run Settings → Pages → create for that key
- **Expect**: the existing page is adopted and mapped. No second page is created — this is the case that would otherwise leave an owner with two Categories pages, one of them orphaned

### 4. A deleted page stays deleted
- **Action**: delete a created showcase page, then run the wizard again
- **Expect**: it is not resurrected. The registry creates once per key per site

### 5. Upgrade safety
- **Action**: update the plugin on a site that never had these pages, without touching the wizard
- **Expect**: no new pages appear on their own

## Pass criteria

1. All three keys resolve through the registry and Settings → Pages.
2. Nothing is created without the owner asking for it.
