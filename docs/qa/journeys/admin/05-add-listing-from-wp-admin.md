---
journey: admin-add-listing-from-wp-admin
plugin: wb-listora
priority: high
roles: [administrator, qa_editor, member-owner, member-other]
covers: [cpt-edit-screen, listing-type-metabox, services-metabox, service-categories-checkboxes, expiry-only-when-on, admin-save-keeps-fields, 2.0.0-expiry-default]
prerequisites:
  - "Site reachable at $SITE_URL; admin ?autologin=1"
  - "Block editor in use for Listora listings (it is: `use_block_editor_for_post_type('listora_listing')` true). Run the expiry steps with the setting you want: Settings > General > Listing expiration"
estimated_runtime_minutes: 7
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [wp-admin default]
---

# Admin creates a listing from wp-admin

`post-new.php?post_type=listora_listing` (block editor) with the Listora meta boxes: Listing Type, the field boxes for that type, Features & Amenities, Featured, Services. Verifies the create works with expiry on AND off, that Services persist (with category checkboxes), that expiry is written only when expiry is enabled, and that the saved fields appear on the frontend.

## Steps

### 1. New listing screen (administrator)
- **Action**: `$SITE_URL/wp-admin/post-new.php?post_type=listora_listing&autologin=1`.
- **Expect**: block editor with title and content; side/below boxes "Listing Type", the type's field groups, "Features & Amenities", "Featured", "Services". The native Categories/Locations/Tags panels carry the listing taxonomies; the Locations panel help text says Locations drive Browse by Location and filters and the shown address comes from the Address field.
- **On fail**: `Listing_Type_Metabox::register_metabox` (priority 5), `Listing_Fields_Metabox::register_metaboxes`.

### 2. Fill and publish
- **Action**: title `J20 Admin Listing`; Listing Type = Restaurant; fill Phone `555-0100`, Address (pick via the map picker or type), tick a Category and a Feature; in "Services" click "+ Add Another Service": Title `J20 Service`, Price `10`, Duration 30, Status active, tick one Category checkbox (several can be ticked without Ctrl/Cmd); Publish.
- **Expect**: the editor reports "Post published" / the URL becomes `post.php?post=<ID>&action=edit`; no PHP notice; the admin save does not reset fields it has no control for.
- **On fail**: `Services_Metabox::save_post` (~44), `Listing_Fields_Metabox::save_post`.

### 3. Expiry follows the setting (run both)
- **Setting OFF (default)**: `wp post meta get <ID> _listora_expiration_date` is empty; the listing stays published.
- **Setting ON** (Settings > General > "Enable automatic listing expiration", Default expiration 30): publish another; `_listora_expiration_date` ~ now + 30 days (UTC), reminder meta cleared.
- **Positive control**: the two runs differ; with Pro Pricing Plans active expiry is on by itself (`admin/2.0-expiry-off-on-free-directory.md`).
- **On fail**: `Status_Manager::set_expiration_on_publish` (`class-status-manager.php` ~480).

### 4. Services and fields persisted
- **Action**:
  ```sql
  SELECT title, price, duration_minutes, status, image_id FROM wp_listora_services WHERE listing_id=<ID>;
  ```
  ```bash
  wp post meta get <ID> _listora_phone
  wp post term list <ID> listora_listing_cat --field=slug
  ```
- **Expect**: one service row (title "J20 Service", price 10.00, duration_minutes 30), phone `555-0100`, the ticked category; the service-category assignment is stored (`wp eval 'print_r(\WBListora\Core\Services::get_service_categories(<service id>));'`). Reopening the edit screen shows them again.

### 5. Frontend
- **Action**: `$SITE_URL/listing/j20-admin-listing/` as anonymous.
- **Expect**: renders with the phone in the contact card and a Services tab listing "J20 Service" at the price (for a type with services enabled); Features list the ticked feature.

### 6. Editing as a moderator keeps terms accepted
- **Action**: as qa_editor edit the same listing and Update.
- **Expect**: no "accept the terms" prompt (2.0.0), nothing else changes.

### 7. Role ladder
| Rung | Action | Expect |
|---|---|---|
| administrator | steps 1-5 | pass |
| qa_editor | new listing | allowed (Editor has the listing caps); can publish |
| author1 (Author) | new listing | allowed to create; Publish becomes "Submit for review"-style only if `publish_listora_listings` missing (Authors lack it: saved as pending/draft, not published) |
| contributor1 | new listing | can save draft only |
| subscriber owner (qa_vendor_01) | `post-new.php?post_type=listora_listing` | "Sorry, you are not allowed to create posts as this user." (members use Add Listing on the frontend) |

## Pass criteria

1. The screen shows the Listora boxes and publishes without error.
2. Expiry is written only when expiry is enabled.
3. Services (with categories), fields and terms persist and render on the frontend.
4. Capabilities decide who can create/publish; subscribers cannot reach the screen.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Fatal on save with expiry on | expiration calc | `Status_Manager::set_expiration_on_publish` |
| Services row missing | metabox save skipped (no nonce / blank title) | `Services_Metabox::save_post` |
| Expiry written with setting off | `wb_listora_expiration_enabled()` bypassed | `class-status-manager.php` |
| Only one service category saves | select instead of checkbox list | `Services_Metabox::render_category_select` (~337) |
