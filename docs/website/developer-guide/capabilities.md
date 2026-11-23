# Capabilities & Roles

WB Listora ships **15 custom capabilities** that gate every admin and frontend surface in the plugin. Capabilities are added to the standard WordPress roles (administrator, editor, author, contributor, subscriber) on plugin activation and removed on uninstall - no role editor required for a default install. Use this reference when you need to grant directory-management permissions to a custom role, audit who can do what, or write a permissions-aware integration.

## The capability map

### Listing CRUD capabilities (10)

The standard WordPress `capability_type` map for the `listora_listing` custom post type - every CPT gets these.

| Capability | What it gates |
|---|---|
| `edit_listora_listing` | Edit a single owned listing |
| `edit_listora_listings` | See the Listings admin list table |
| `edit_others_listora_listings` | Edit listings owned by other users |
| `edit_published_listora_listings` | Edit listings after they've been published |
| `publish_listora_listings` | Publish a listing (set status to `publish`) |
| `delete_listora_listing` | Delete a single owned listing |
| `delete_listora_listings` | Bulk-delete listings |
| `delete_others_listora_listings` | Delete listings owned by other users |
| `delete_published_listora_listings` | Delete published listings |
| `read_private_listora_listings` | View listings with status `private` |

### Management capabilities (5)

Custom caps for the plugin's own admin surfaces. Each is exposed as a `Capabilities` class constant so call-sites can avoid magic strings.

| Capability | Constant | Gates |
|---|---|---|
| `manage_listora_settings` | `Capabilities::CAP_MANAGE_SETTINGS` | Settings pages, REST `/settings/*`, Email Log page, Setup Wizard, Pro license activation |
| `moderate_listora_reviews` | `Capabilities::CAP_MODERATE_REVIEWS` | Approve / reject / hide / spam reviews from the Reviews admin page and `/reviews/{id}` REST |
| `manage_listora_claims` | `Capabilities::CAP_MANAGE_CLAIMS` | Approve / reject business-claim requests (transfers `post_author` on approve) |
| `manage_listora_types` | `Capabilities::CAP_MANAGE_TYPES` | Create / edit / delete listing types and the taxonomies (Categories, Locations, Features, Tags) |
| `submit_listora_listing` | `Capabilities::CAP_SUBMIT_LISTING` | Use the frontend submission wizard / block |

### Virtual capability (1)

`view_listora_dashboard` (`Capabilities::CAP_VIEW_DASHBOARD`) is granted at runtime - never persisted in the role table - to any user who has either `manage_listora_settings` OR `edit_listora_listings`. Lets one menu page (Listora → Dashboard) act as a shared entry point for settings-managers and listing-editors without registering the menu twice. Set up by `Capabilities::grant_view_dashboard_to_managers()` in `includes/core/class-capabilities.php`.

## Default role assignments

Activate the plugin and these caps are added to each role automatically (full grid at `includes/core/class-capabilities.php:get_caps_map()`).

| Role | Listing CRUD | Settings | Reviews | Claims | Types | Submit | Notes |
|---|---|---|---|---|---|---|---|
| **administrator** | All 10 listing-CRUD caps | Yes | Yes | Yes | Yes | Yes | Full access |
| **editor** | All listing-CRUD caps EXCEPT `delete_others_listora_listings` | - | Yes | Yes | - | Yes | Moderates content but can't delete other users' listings or edit types |
| **author** | Own listings + publish | - | - | - | - | Yes | Standard listing owner. WordPress gives authors `upload_files`. |
| **contributor** | Own listings (no publish, no edit-after-publish) | - | - | - | - | Yes | Uploads listing photos through `POST /listora/v1/media` |
| **subscriber** | None | - | - | - | - | Yes | Frontend submission only. Uploads listing photos through `POST /listora/v1/media` |

### Member photos and `upload_files`

Since 2.1.2 Listora gives no member the core `upload_files` cap. The submission wizard and the dashboard upload through `POST /listora/v1/media`, which accepts JPEG, PNG, WebP and GIF from anyone who may add a listing or already owns one. Members stay out of the Media menu, and their photos stay out of the admin's library.

Every photo a member uploads carries the `_listora_member_media` attachment meta (set with `wb_listora_mark_member_media()`; Pro review photos carry it too). The Media Library, the media pickers and `GET /wp/v2/media` collections leave these photos out. A listing's **Uploaded to this listing** view and lookups by ID still find them, and **Media > Library > Listing member media** (list view) shows all of them.

Upgrading to 2.1.2 marks the photos members already put on listings and removes `upload_files` from Subscriber and Contributor. WordPress gives neither role that cap, so Listora had added it.

Two filters restore the old behaviour:

```php
// Give listing submitters core upload_files again.
add_filter( 'wb_listora_grant_upload_files_to_submitters', '__return_true' );

// Show members' photos in the Media Library again.
add_filter( 'wb_listora_hide_member_media', '__return_false' );
```

## Pro capabilities (when wb-listora-pro is active)

Pro adds two capability families on top of Free.

### Moderator caps

The Pro Moderators feature (`Listora → Moderators`) gives non-admin team members targeted permissions across the moderation queue. Caps are added by `\WBListoraPro\Features\Moderator::register_caps()` and removed when the feature is toggled off.

| Capability | Gates |
|---|---|
| `manage_listora_moderators` | Add or remove team members from the moderators list (administrators) |
| `moderate_listora_listings` | Approve or reject pending listings (moderators and administrators) |
| `moderate_listora_reviews` | Approve, reject or remove reviews |
| `manage_listora_claims` | Approve or reject business claims |

Moderators never get `publish_listora_listings`, the delete caps, `manage_listora_settings`, `manage_listora_types` or `view_listora_analytics`. Reports are reached through `edit_others_listora_listings`; there is no separate reports capability.

A WordPress user added to the Moderators list gets these caps automatically. Remove them from the list and the caps are revoked.

### Reverse-listing caps (Needs marketplace)

The Pro Needs Marketplace feature adds CPT-style caps for the `listora_need` post type (buyer-posted requests).

| Capability | Gates |
|---|---|
| `read_listora_need` | Read a single need |
| `read_private_listora_needs` | Read needs with status `private` |
| `edit_listora_need` | Edit a single owned need |
| `edit_listora_needs` | Edit list of needs |
| `edit_others_listora_needs` | Edit needs posted by other users |
| `edit_published_listora_needs` | Edit a need after it is published |
| `edit_private_listora_needs` | Edit private needs |
| `publish_listora_needs` | Publish a need (status `publish`) |
| `delete_listora_need` | Delete a single owned need |
| `delete_listora_needs` | Delete needs in bulk |
| `delete_others_listora_needs` | Delete needs posted by others |
| `delete_published_listora_needs` | Delete published needs |
| `delete_private_listora_needs` | Delete private needs |

## Usage in code

### Check the current user (recommended)

Prefer the static helpers - a future cap rename only has to update one file.

```php
use WBListora\Core\Capabilities;

if ( Capabilities::can_manage_settings() ) {
// Render the settings link.
}

if ( Capabilities::can_moderate_reviews() ) {
// Show the bulk-moderate UI.
}

if ( Capabilities::can_submit_listing() ) {
// Render the submission CTA.
}
```

### Check a specific user

Pass a user ID to any helper, or use the generic dispatcher.

```php
Capabilities::can_manage_claims( $user_id );

Capabilities::user_can( Capabilities::CAP_MANAGE_TYPES, $user_id );
```

### Add caps to a custom role

To let a role add listings, tick it under **Settings > Submissions > Who can add listings** (since 2.1.2). Saving grants or removes `submit_listora_listing` on each role, through the admin form, `POST /listora/v1/settings` with `submission_roles`, or any write of the `wb_listora_settings` option that carries that key. Code like the example below is only needed for the other caps.

```php
add_action( 'init', function () {
$role = get_role( 'shop_manager' );
if ( ! $role ) {
return;
}
$role->add_cap( 'moderate_listora_reviews', true );
$role->add_cap( 'manage_listora_claims', true );
$role->add_cap( 'submit_listora_listing', true );
}, 20 ); // Priority 20 so it runs after our Capabilities::register() at 10.
```

### Restrict a built-in role

Same pattern with `remove_cap()` - useful when an editor shouldn't moderate reviews on a particular site.

```php
add_action( 'init', function () {
$role = get_role( 'editor' );
if ( $role ) {
$role->remove_cap( 'moderate_listora_reviews' );
}
}, 20 );
```

## How REST permission callbacks use these

Every Listora REST controller's `permission_callback` returns either `true`, `current_user_can('cap_name')`, or a `WP_Error` with HTTP 401 / 403. Pattern:

```php
'permission_callback' => function () {
if ( ! is_user_logged_in() ) {
return new \WP_Error( 'rest_forbidden', __( 'Authentication required.', 'wb-listora' ), array( 'status' => 401 ) );
}
if ( ! current_user_can( 'moderate_listora_reviews' ) ) {
return new \WP_Error( 'rest_forbidden', __( 'You cannot moderate reviews.', 'wb-listora' ), array( 'status' => 403 ) );
}
return true;
},
```

This is why removing a cap from a role automatically blocks both the admin UI AND the REST endpoint - the JS frontend code calls the same REST routes the admin pages call.

## Uninstall behaviour

`Capabilities::remove_caps()` iterates every WordPress role and removes all 15 standard caps when the plugin is uninstalled (it runs from `uninstall.php`). The virtual `view_listora_dashboard` cap is granted at runtime by a filter - it has no persisted state, so nothing to clean up. Pro adds its own removal logic for moderator + reverse-listing caps when uninstalled.

## Related

- [REST API reference](rest-api.md) - every endpoint with the cap it requires.
- [Hooks reference](hooks-reference.md) - `user_has_cap` filter is the runtime grant point.
- [Moderators (Pro)](../features/moderators.md) - UI for managing the team that receives Pro moderator caps.
- [Business Claims](../features/business-claims.md) - uses `manage_listora_claims` for approve / reject.
- [Moderation Queue](../features/moderation-queue.md) - uses `moderate_listora_reviews` + `edit_others_listora_listings`.
