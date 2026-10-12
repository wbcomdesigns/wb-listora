---
journey: role-cap-matrix
plugin: wb-listora
priority: critical
roles: [administrator, editor, author, contributor, subscriber, anonymous]
covers: [capabilities, custom-caps, role-permissions, admin-menu-by-role, rest-forbidden-shape, leads-and-types-gates]
prerequisites:
  - "Site reachable at $SITE_URL; users: varundubey (administrator), qa_editor (editor), author1 (author), contributor1 (contributor), subscriber2 or qa_vendor_01 (subscriber)"
  - "Pro inactive for the Free matrix (Pro adds the `listora_moderator` role; see its own journeys)"
estimated_runtime_minutes: 7
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [wp-admin default]
---

# Every role has exactly the Listora capabilities it should, and forbidden actions refuse cleanly

The custom capabilities are granted on activation from `Capabilities::get_caps_map()` (`includes/core/class-capabilities.php`). `view_listora_dashboard` is a virtual capability granted to anyone who can manage settings or edit listings (it opens the top-level menu). Custom roles from Pro (Listora Moderator) are out of scope here.

## Expected matrix (verified live with `user_can`)

| Capability | administrator | editor | author | contributor | subscriber | anonymous |
|---|---|---|---|---|---|---|
| `manage_listora_settings` | Y | - | - | - | - | - |
| `manage_listora_types` (also every Listora taxonomy) | Y | - | - | - | - | - |
| `moderate_listora_reviews` | Y | Y | - | - | - | - |
| `manage_listora_claims` | Y | Y | - | - | - | - |
| `submit_listora_listing` | Y | Y | Y | Y | Y | - |
| `edit_listora_listings` (own) | Y | Y | Y | Y | - | - |
| `edit_others_listora_listings` | Y | Y | - | - | - | - |
| `publish_listora_listings` | Y | Y | - | - | - | - |
| `delete_others_listora_listings` | Y | - | - | - | - | - |
| `view_listora_dashboard` (virtual) | Y | Y | Y | Y | - | - |
| `upload_files` (granted to submitters) | Y | Y | Y | Y | Y | - |

## Steps

### 1. Cap table matches (WP-CLI, no browser)
- **Action**:
  ```bash
  wp eval '
  $caps=["manage_listora_settings","manage_listora_types","moderate_listora_reviews","manage_listora_claims","submit_listora_listing","edit_listora_listings","edit_others_listora_listings","publish_listora_listings","delete_others_listora_listings","view_listora_dashboard","upload_files"];
  foreach(["varundubey","qa_editor","author1","contributor1","subscriber2"] as $l){ $u=get_user_by("login",$l); echo str_pad($l,14); foreach($caps as $c) echo user_can($u,$c)?"Y":"-"; echo "\n"; }'
  ```
- **Expect**: rows `YYYYYYYYYYY`, `--YYYYYY-YY`, `----YY---YY`, `----YY---YY`, `----Y-----Y` in column order above (admin all; editor lacks settings, types, delete_others; author and contributor lack moderation and publish; subscriber only submit + upload).
- **On fail**: `get_caps_map()` (~110) and `Activator` grant; `wp eval 'wb_listora_grant_caps();'`-style repair via deactivate/reactivate.

### 2. The helper methods agree with `current_user_can`
- **Action**:
  ```bash
  wp eval '$u=get_user_by("login","qa_editor")->ID; foreach(["can_manage_settings","can_moderate_reviews","can_manage_claims","can_manage_types","can_submit_listing"] as $m) echo $m,": ", \WBListora\Core\Capabilities::$m($u)?"Y":"N","\n";'
  ```
- **Expect**: settings N, moderate Y, claims Y, types N, submit Y.

### 3. Admin screens per role (browser, ?autologin=<login>)
| Screen | administrator | editor | author | contributor | subscriber |
|---|---|---|---|---|---|
| `admin.php?page=listora` (Dashboard) | 200 | 200 | 200 (own listings view) | 200 | not allowed |
| `admin.php?page=listora-settings` | 200 | not allowed | not allowed | not allowed | not allowed |
| `admin.php?page=listora-listing-types` | 200 | not allowed | not allowed | not allowed | not allowed |
| `admin.php?page=listora-reviews` | 200 | 200 | not allowed | not allowed | not allowed |
| `admin.php?page=listora-claims` | 200 | 200 | not allowed | not allowed | not allowed |
| `admin.php?page=listora-leads` | 200 | not allowed | not allowed | not allowed | not allowed |
| `admin.php?page=listora-email-log` | 200 | not allowed | not allowed | not allowed | not allowed |
| `edit.php?post_type=listora_listing` | 200 | 200 | 200 (own) | 200 (own) | not allowed |
- **Expect**: "not allowed" = WordPress's "Sorry, you are not allowed to access this page." (never a fatal, never a blank page); the sidebar for each role lists only the hubs it can open (editor: Dashboard, Listings, Moderation with Reviews and Claims; no Listing Types, Settings, Tools).

### 4. Frontend submit gate
- **Action**: `$SITE_URL/add-listing/?autologin=<login>` per role; anonymous without login.
- **Expect**: administrator, editor, author, contributor, subscriber see the wizard; anonymous gets the login prompt (or the guest flow when guests may submit); a member whose role cannot submit (strip with `wp user remove-cap`/a role-stripper) sees a reason and a link to My Dashboard, and the dashboard no longer offers Add Listing (2.0.0).

### 5. REST: refusal shape (never `false`/`0`)
| Call | Rung | Expect |
|---|---|---|
| `GET /listora/v1/settings` | anonymous | 401 `listora_unauthorized` |
| `GET /listora/v1/settings` | subscriber / editor | 403 `listora_forbidden` |
| `PUT /listora/v1/reviews/<id> {"status":"approved"}` | subscriber | 403 `listora_forbidden` or `listora_forbidden_status` |
| `POST /listora/v1/listing-types` with a valid body (a body with missing params is a 400 `rest_missing_callback_param` first) | editor | 403 |
| `GET /listora/v1/leads` | anonymous | 401 `rest_forbidden`; subscriber with no listings: 200 empty list |
| `POST /wp/v2/listings/<id>` (title) | subscriber owner | 403 `rest_cannot_edit` |
- **Expect**: every refusal is a `WP_Error` JSON body `{code, message, data:{status}}`; a bare `false` permission callback is a defect (wppqa R4).

### 6. Grants survive reactivation
- **Action**: `wp plugin deactivate wb-listora && wp plugin activate wb-listora`; repeat step 1.
- **Expect**: unchanged (activation is idempotent), no duplicated role rows.

### 7. Uninstall hygiene (scratch only)
- With "Delete data on uninstall", Listora caps are not stranded on roles (see `admin/2.0-uninstall-deletes-everything-both-orders.md`).

## Pass criteria

1. Every cell of the matrix matches `user_can`.
2. Screens and menu items open only for the roles above, with WordPress's own refusal text.
3. REST refusals use the 401/403 `WP_Error` shape.
4. Reactivation keeps the grants.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Editor opens Settings | `manage_listora_settings` granted too widely | `get_caps_map()` |
| Contributor cannot reach Add Listing | submit cap missing on the role | activation grant, `get_caps_map()` |
| 200 with empty body on forbidden REST | `permission_callback` returning false | the controller's permission method |
| Cap lost after reactivate | non-idempotent grant | `Activator` role grant loop |
