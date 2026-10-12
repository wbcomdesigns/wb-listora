---
journey: admin-features-checkbox-grid
plugin: wb-listora
priority: high
roles: [administrator, editor]
covers: [listing-fields-metabox, listing-features, card-10272654379]
prerequisites:
  - "A published listing of type Job (or any type with features); note its id as <id>"
  - "Back up its feature term ids: wp post term list <id> listora_listing_feature --field=term_id"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
changed: true
---

# wp-admin edits features through the same curated grid members use

Members pick features from the owner's fixed list, narrowed by the listing type's allowlist. wp-admin once offered WordPress's tag-style token box, where typing a name creates a new feature. `Listing_Fields_Metabox` (`includes/admin/class-listing-fields-metabox.php`) renders a "Features & Amenities" checkbox grid (type allowlist plus any feature already on the listing), saves only IDs it could have rendered, and the core panel is gone: taxonomy `meta_box_cb` false and `show_in_quick_edit` false (`includes/core/class-taxonomies.php:265-266`), and the block editor panel removed with `removeEditorPanel('taxonomy-panel-listora_listing_feature')`. Escape hatch: `add_filter( 'wb_listora_admin_features_checkbox_grid', '__return_false' )`.

## Steps

### 1. Grid present, token panel gone
- **Action**: as administrator open `$SITE_URL/wp-admin/post.php?post=<id>&action=edit`; expand the meta box pane (the block editor can start it collapsed).
- **Expect**: a box `#wb_listora_features` titled "Features & Amenities" containing `ul.listora-admin-features[role=group]` with `input[name="listora_features[]"]` checkboxes (current features ticked) and a hidden `input[name="listora_features_present"]`; the settings sidebar has NO "Features" panel; Quick Edit on Listora > Listings shows Categories, Locations and Tags inputs (`tax_input[...]`) but no Features field.

### 2. Save round-trip
- **Action**: tick one unticked feature, untick one ticked feature, Update, reload.
- **Expect**: the grid shows exactly the new selection; `wp post term list <id> listora_listing_feature --field=term_id` agrees.
- **Note**: the meta box pane's resize separator can intercept pointer clicks; trigger the clicks in-page and save with `wp.data.dispatch('core/editor').savePost()`.

### 3. Forged IDs are dropped (verified 2026-10-12 against a QA-J1- listing)
```bash
wp eval 'wp_set_current_user(1); $M="WBListora\Admin\Listing_Fields_Metabox";
$_POST=[ $M::NONCE_NAME=>wp_create_nonce($M::NONCE_ACTION), "listora_features_present"=>"1",
 "listora_features"=>["<a listora_listing_cat term id>","999999","<a real feature id>"] ];
$M::save_post(<id>); echo implode(",", wp_get_object_terms(<id>,"listora_listing_feature",["fields"=>"ids"]));'
```
- **Expect**: only the real feature id is stored. Posting `listora_features_present=1` with no `listora_features` clears the features (POSITIVE CONTROL: the save path does run; the real id above proves it writes).

### 4. Allowlist respected
- **Action**: Listing Types > edit the listing's type > restrict allowed features, reopen the listing.
- **Expect**: grid shows only those plus any feature already assigned to this listing.

### 5. Role ladder
- **Editor (qa_editor, `edit_others_listora_listings`)**: opens the same listing, same grid, saves the same way.
- **Listing owner (member)**: edits from the frontend wizard, which offers the same curated list; no wp-admin access.
- **Subscriber / logged out**: `post.php` redirects to login or 403; `listora_features` cannot be posted (nonce + `edit_post` gate in `save_post()`).
- **Administrator**: control for steps 1-4.

### 6. Field groups save only when they were on screen
- **Action**: new listing (post-new, no type yet), set the type and save; inspect post meta.
- **Expect**: no checkbox/toggle meta rows (e.g. restaurant `delivery`) were written, since those meta boxes were never rendered. Each rendered group posts `listora_field_groups_rendered[]` (3 on a Job listing: company, job_details, media).
- **Action**: edit a restaurant listing, untick Delivery, save; tick it, save.
- **Expect**: the value follows the checkbox both ways.

## Teardown
`wp post term set <id> listora_listing_feature <backed-up ids> --by=id`; undo any allowlist change; delete any QA-J1- listing.

## Fail diagnostics
| Symptom | File |
|---|---|
| Token box still shown | `includes/core/class-taxonomies.php:265-266`, `class-listing-fields-metabox.php:426-431` |
| Forged id stored | `class-listing-fields-metabox.php:347-361` (`save_features()` intersects with `feature_choices()`) |
| Unrendered group written | `class-listing-fields-metabox.php:170-180` (`GROUPS_FIELD`) |

Verified via: curl/DOM as administrator on a QA-J1- Job listing (grid, hidden present field, Quick Edit inputs) and wp eval (steps 3, empty-post). Steps 2, 4 and the editor save were not click-tested.
