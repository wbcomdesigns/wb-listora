---
journey: admin-features-checkbox-grid
plugin: wb-listora
priority: high
roles: [administrator, editor]
covers: [listing-fields-metabox, listing-features, card-10272654379]
prerequisites:
  - "A listing with some features assigned (Job listing 1044 on the QA seed)"
  - "Back up its feature term ids: wp post term list <id> listora_listing_feature --field=term_id"
estimated_runtime_minutes: 5
---

# wp-admin edits features through the same curated grid members use

Members pick features from the owner's fixed list, narrowed by the listing type's allowlist. wp-admin
offered WordPress's tag-style token box instead, where typing a name creates a new feature - the one
item of card 10272654379 the first fix skipped. `Listing_Fields_Metabox` now renders a
"Features & Amenities" checkbox grid (type allowlist + any feature already on the listing), saves
only IDs it could have rendered, and the core panel is gone: `meta_box_cb` false, `show_in_quick_edit`
false, and the block editor panel removed with `removeEditorPanel`. Escape hatch:
`add_filter( 'wb_listora_admin_features_checkbox_grid', '__return_false' )`.

## Steps

### 1. Grid present, token panel gone
- **Action**: as administrator open `post.php?post=<id>&action=edit`, expand the meta box pane (WP 7 starts it collapsed)
- **Expect**: a "Features & Amenities" box with checkboxes, current features ticked; the settings sidebar has NO "Features" panel; Quick Edit on the listings list has no Features field

### 2. Save round-trip
- **Action**: tick one unticked feature, untick one ticked feature, Update, reload
- **Expect**: the grid shows exactly the new selection; `wp post term list <id> listora_listing_feature` agrees
- **Note**: the meta box pane's resize separator can intercept pointer clicks - trigger clicks in-page and save with `wp.data.dispatch('core/editor').savePost()`

### 3. Forged IDs are dropped
```bash
wp eval 'wp_set_current_user(1); $M="WBListora\Admin\Listing_Fields_Metabox";
$_POST=[ $M::NONCE_NAME=>wp_create_nonce($M::NONCE_ACTION), "listora_features_present"=>"1",
 "listora_features"=>["<a listora_listing_cat term id>","999999","<a real feature id>"] ];
$M::save_post(<id>); echo implode(",", wp_get_object_terms(<id>,"listora_listing_feature",["fields"=>"ids"]));'
```
- **Expect**: only the real feature id is stored

### 4. Allowlist respected
- **Action**: restrict the listing's type to a few features (Listing Types → edit → allowed features), reopen the listing
- **Expect**: grid shows only those plus any feature already assigned to this listing

### 5. Editor role
- **Action**: as editor (edit_others_listora_listings) open the same listing
- **Expect**: same grid, saves the same way

## Teardown
`wp post term set <id> listora_listing_feature <backed-up ids> --by=id`; undo any allowlist change.
