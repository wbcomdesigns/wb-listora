---
journey: add-listing-from-wp-admin
plugin: wb-listora
priority: high
roles: [administrator, editor, author, contributor, member-owner, member-other, anonymous]
covers: [cpt-edit-screen, listing-type-metabox, services-metabox, service-categories-checkboxes, expiry-only-when-on, admin-save-keeps-fields, 2.0.0-expiry-default, bc-10351076301]
prerequisites:
  - "Site reachable at $SITE_URL; {admin} session"
  - "Block editor in use for Listora listings (use_block_editor_for_post_type('listora_listing') true)"
  - "Run the expiry steps with Settings > General > Listing expiration both OFF (default) and ON"
estimated_runtime_minutes: 9
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default; buddyx-pro light for the frontend]
supersedes: []
fails_on: step 9 fails on 939a5e6c (parent of 222fe596)
site_profile: required
---

# Admin creates a listing from wp-admin: boxes, services with categories, expiry only when on, fields on the frontend

## Why
`post-new.php?post_type=listora_listing` (block editor) carries the Listora meta boxes: Listing Type, the type's field boxes, Features & Amenities, Featured, Services. If the admin save drops fields, writes an expiry while expiry is off, or loses service categories, owners lose data on the screen they trust most.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Pick a registered {type} with services enabled, a {cat} and a {feature}.

## Steps

### 1. New listing screen
- **Action**: `$SITE_URL/wp-admin/post-new.php?post_type=listora_listing` as {admin}.
- **Expect**: block editor with title/content; boxes "Listing Type", the type's field groups, "Features & Amenities", "Featured", "Services"; native Categories/Locations/Tags panels carry the listing taxonomies; the Locations help text says Locations drive Browse by Location and filters and the shown address comes from the Address field.

### 2. Fill and publish
- **Action**: title `QA Admin Listing`; Listing Type = {type}; Phone `555-0100`; Address (map picker or typed); tick {cat} and {feature}; Services > "+ Add Another Service": Title `QA Service`, Price `10`, Duration 30, Status active, tick one Category checkbox (several without Ctrl/Cmd); Publish.
- **Expect**: "Post published", URL becomes `post.php?post=<ID>&action=edit`; no PHP notice; the admin save does not reset fields it has no control for.
- **Boundary input (added)**: empty service title, price `-1`, price `999999999`, duration 0, markup and emoji in titles: invalid rows are skipped or sanitised, never fatal. Not verified live.

### 3. Expiry follows the setting (run both)
- **OFF (default)**: `wp post meta get <ID> _listora_expiration_date` is empty; stays published.
- **ON** (Settings > General "Enable automatic listing expiration", Default expiration 30): publish another; `_listora_expiration_date` ~ now + 30 days (UTC); reminder meta cleared.
- **Positive control**: the two runs differ. With Pro Pricing Plans active expiry is on by itself (`admin/expiry-off-on-free-directory.md`).
- **Timezone (added)**: repeat the ON run with site timezone UTC+14 and UTC-12, then restore UTC; the stored date stays a UTC instant ~30 days out. Not verified live.

### 4. Services and fields persisted
- **Action**: `SELECT title, price, duration_minutes, status, image_id FROM ${PREFIX}listora_services WHERE listing_id=<ID>`; `wp post meta get <ID> _listora_phone`; `wp post term list <ID> listora_listing_cat --field=slug`; `wp eval 'print_r(\WBListora\Core\Services::get_service_categories(<service id>));'`.
- **Expect**: one service row (title, price 10.00, duration_minutes 30), phone `555-0100`, the ticked category and the stored service-category assignment; reopening the edit screen shows them.

### 5. Frontend
- **Action**: open the listing permalink as {guest}.
- **Expect**: phone in the contact card, a Services tab listing the service at its price (for a services-enabled type), the ticked feature in Features.

### 6. Editing as a moderator keeps terms accepted
- **Action**: as {editor} edit the same listing and Update.
- **Expect**: no "accept the terms" prompt; nothing else changes.

### 7. Lifecycle seeds (added)
- **Action**: save as draft, pending, publish, move to trash, restore.
- **Expect**: services/fields persist across each; trashing keeps rows hidden not deleted. Not verified live.

### 8. Retry (added)
- **Action**: double-click Publish / two tabs saving the same listing.
- **Expect**: one service row per service (no duplicates), last write wins on fields. Not verified live.

### 9. A service category set on the frontend is visible and editable in wp-admin and REST (card 10351076301)
- **Action**: as {owner} add a service `QA Cat` on the dashboard with Category = CAT_A. As {admin} open that listing's edit screen; read the Services box row; tick CAT_B too; Update. `GET /listora/v1/services/<id>` and `GET /listings/<id>/services` as {admin} and {guest}.
- **Expect**: wp-admin row shows CAT_A ticked (not blank); after Update CAT_A and CAT_B; REST `categories` lists both ids for both callers; the frontend Services tab shows the service under both. The dashboard form, wp-admin box and REST return the same set.
- **Read-back**: `SELECT term_taxonomy_id FROM ${PREFIX}term_relationships WHERE object_id=<service id>` returns CAT_A and CAT_B; negative control: unticking CAT_B and Update removes only CAT_B. fails_on 939a5e6c. Not verified live.

### 10. 390px (added)
- **Expect**: the Services box and field boxes stack; no horizontal scroll in the editor sidebar. Not verified live.

## Role ladder

| Rung | Action | Expect |
|---|---|---|
| {admin} | steps 1-10 | pass |
| {editor} | new listing | allowed; can publish |
| author | new listing | allowed to create; Authors lack `publish_listora_listings`: saved as pending/draft, not published |
| contributor | new listing | can save draft only |
| {owner} (Subscriber) | `post-new.php?post_type=listora_listing` | "Sorry, you are not allowed to create posts as this user." (members use Add Listing on the frontend) |
| {guest} | same | login redirect |

## Pass criteria
1. The screen shows the Listora boxes and publishes without error.
2. Expiry is written only when expiry is enabled.
3. Services (with categories), fields and terms persist and render on the frontend.
4. Capabilities decide who can create/publish; subscribers cannot reach the screen.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Fatal on save with expiry on | expiration calc | `Status_Manager::set_expiration_on_publish` (`includes/workflow/class-status-manager.php`) |
| Services row missing | metabox save skipped (no nonce / blank title) | `Services_Metabox::save_post` |
| Expiry written with setting off | `wb_listora_expiration_enabled()` bypassed | `class-status-manager.php` |
| Only one service category saves | select instead of checkbox list | `Services_Metabox::render_category_select` |
| Boxes missing | registration | `Listing_Type_Metabox::register_metabox`, `Listing_Fields_Metabox::register_metaboxes` |

## Not verified
Steps 2 boundary input, 3 timezone, 7, 8, 9, 10.

## Cleanup
Delete the QA listing and service; restore the expiration setting.
