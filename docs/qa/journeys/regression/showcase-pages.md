---
journey: showcase-pages
plugin: wb-listora
priority: normal
roles: [administrator, editor]
covers: [pages, page-registry, setup-wizard, blocks, card-10167582244]
prerequisites:
  - "A site where the calendar/categories/featured pages do not yet exist. This site has all three mapped (Browse Categories, Featured Listings, Events Calendar), so the 'offered' branch needs a clean install or Playground; use the 'already on your site' branch here"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
changed: true
---

# The calendar, categories and featured blocks have somewhere to live

The page registry knows three showcase pages (`includes/page-registry-helpers.php:369-415`): `categories` ("Browse Categories", default slug `browse-categories`, block `listora/listing-categories`, option `wb_listora_categories_page_id`), `featured` ("Featured Listings", `wb_listora_featured_page_id`, `listora/listing-featured`) and `calendar` ("Events Calendar", `wb_listora_calendar_page_id`, `listora/listing-calendar`). They are offered, never imposed (owner decision 2026-09-18). PHPUnit: `tests/unit/ShowcasePagesTest.php` (3 tests).

Verified via: source read (wizard list at `includes/admin/class-setup-wizard.php:677-679,746-766`) and the General tab Pages section as admin (rows "Browse Categories", "Featured Listings", "Events Calendar" each with a "Linked" `select` `#page-categories` / `#page-featured` / `#page-calendar` named by the option above and "Edit page | View page"). The create/adopt/resurrect steps were NOT executed (they create pages). The Pages section lives on the **General** tab (`includes/admin/class-pages-settings.php`), create action `admin-post.php?action=wb_listora_create_page`.

## Steps

### 1. The wizard offers them
- **Action**: run the wizard (`admin.php?page=listora-setup&rerun=1`) to the Pages step on a site without them.
- **Expect**: an "Optional pages" h3 with a checkbox (`name="showcase_pages[]"`) each for Browse Categories, Featured Listings and Events Calendar. Anything already present is listed as "already on your site" instead of offered (this is what this site shows: positive control for the other branch).

### 2. Only what was ticked is created
- **Action**: tick two of three, continue.
- **Expect**: exactly those two exist afterwards and resolve in the registry (`Page_Registry::get_id()`); the third is still missing.

### 3. Adoption, not duplication
- **Action**: on a site with a hand-made page containing `<!-- wp:listora/listing-categories /-->`, use Settings > General > Pages > Create for that key.
- **Expect**: the existing page is adopted and mapped; no second page created.

### 4. A deleted page stays deleted
- **Action**: delete a created showcase page, run the wizard again. **Expect**: not resurrected (created once per key per site).

### 5. Upgrade safety
- **Action**: update the plugin on a site that never had these pages. **Expect**: no new pages appear.

### Role ladder
Administrator: all of the above. qa_editor: "Sorry, you are not allowed to access this page." for the wizard and Settings; anonymous: cannot reach either. The created pages are public (anonymous sees the block).

## Pass criteria
1. All three keys resolve through the registry and Settings > General > Pages. 2. Nothing is created without the owner asking.
