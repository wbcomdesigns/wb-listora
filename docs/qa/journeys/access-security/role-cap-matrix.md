---
journey: role-cap-matrix
group: access-security
actor: admin
mode: mixed
plugin: wb-listora
priority: critical
roles: [administrator, editor, author, contributor, subscriber, anonymous]
covers: [capabilities, custom-caps, role-permissions, admin-menu-by-role, rest-forbidden-shape, leads-and-types-gates]
prerequisites:
  - "Site reachable at $SITE_URL; users for each role: {admin}, {editor}, an author, a contributor, a subscriber ({owner})"
  - "Pro inactive for the Free matrix (Pro adds the listora_moderator role; see its own journeys)"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [wp-admin default]
supersedes: []
site_profile: required
---

# Every role has exactly the Listora capabilities it should, and forbidden actions refuse cleanly

## Why
Capabilities are granted on activation from `Capabilities::get_caps_map()`. `view_listora_dashboard` is virtual: granted to anyone who can manage settings or edit listings. A cap that is too wide lets an editor open Settings; too narrow strands a contributor. Pro's Listora Moderator role is out of scope.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).

Expected matrix (verified with `user_can`):

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

### 1. Cap table matches (WP-CLI)
- **Action**: `wp eval '$caps=["manage_listora_settings","manage_listora_types","moderate_listora_reviews","manage_listora_claims","submit_listora_listing","edit_listora_listings","edit_others_listora_listings","publish_listora_listings","delete_others_listora_listings","view_listora_dashboard","upload_files"]; foreach(["<admin login>","<editor login>","<author login>","<contributor login>","<subscriber login>"] as $l){ $u=get_user_by("login",$l); echo str_pad($l,14); foreach($caps as $c) echo user_can($u,$c)?"Y":"-"; echo "\n"; }'`
- **Expect**: rows `YYYYYYYYYYY`, `--YYYYYY-YY`, `----YY---YY`, `----YY---YY`, `----Y-----Y` in column order (admin all; editor lacks settings, types, delete_others; author and contributor lack moderation and publish; subscriber only submit + upload).

### 2. The helper methods agree with `current_user_can`
- **Action**: `wp eval '$u=get_user_by("login","<editor login>")->ID; foreach(["can_manage_settings","can_moderate_reviews","can_manage_claims","can_manage_types","can_submit_listing"] as $m) echo $m,": ", \WBListora\Core\Capabilities::$m($u)?"Y":"N","\n";'`
- **Expect**: settings N, moderate Y, claims Y, types N, submit Y.

### 3. Admin screens per role (browser, autologin as each login)

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
- **Expect**: "not allowed" is WordPress's "Sorry, you are not allowed to access this page." (never a fatal or blank page); each role's sidebar lists only the hubs it can open (editor: Dashboard, Listings, Moderation with Reviews and Claims; no Listing Types, Settings, Tools).

### 4. Frontend submit gate
- **Action**: `$SITE_URL/add-listing/` as each role and as {guest}.
- **Expect**: administrator, editor, author, contributor, subscriber see the wizard; {guest} gets the login prompt (or the guest flow when guests may submit); a member whose role cannot submit (strip with `wp user remove-cap`) sees a reason and a link to My Dashboard, and the dashboard no longer offers Add Listing.

### 5. REST: refusal shape (never `false`/`0`)

| Call | Rung | Expect |
|---|---|---|
| `GET /listora/v1/settings` | anonymous | 401 `listora_unauthorized` |
| `GET /listora/v1/settings` | subscriber / editor | 403 `listora_forbidden` |
| `PUT /listora/v1/reviews/<id> {"status":"approved"}` | subscriber | 403 `listora_forbidden` or `listora_forbidden_status` |
| `POST /listora/v1/listing-types` with a valid body (missing params give 400 `rest_missing_callback_param` first) | editor | 403 |
| `GET /listora/v1/leads` | anonymous | 401 `rest_forbidden`; subscriber with no listings: 200 empty list |
| `POST /wp/v2/listings/<id>` (title) | subscriber owner | 403 `rest_cannot_edit` |
- **Expect**: every refusal is a `WP_Error` JSON body `{code, message, data:{status}}`; a bare `false` permission callback is a defect (wppqa R4).

### 6. Grants survive reactivation
- **Action**: `wp plugin deactivate wb-listora && wp plugin activate wb-listora`; repeat step 1.
- **Expect**: unchanged, no duplicated role rows.

### 7. Uninstall hygiene (scratch only)
- **Expect**: with "Delete data on uninstall", Listora caps are not stranded on roles (`platform-lifecycle/uninstall-deletes-everything-both-orders.md`).

### 8. Deleted-author and retry (added)
- **Action**: a listing whose author was deleted; two tabs approving the same pending listing.
- **Expect**: the listing stays editable by moderators and shows no fatal; the second approval is a no-op with the info notice. Not verified live.

## Role ladder
The whole journey is the ladder: steps 1, 3, 4, 5 run for each rung; {admin} is the control.

## Pass criteria
1. Every cell of the matrix matches `user_can`.
2. Screens and menu items open only for the roles above, with WordPress's own refusal text.
3. REST refusals use the 401/403 `WP_Error` shape.
4. Reactivation keeps the grants.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Editor opens Settings | cap granted too widely | `includes/core/class-capabilities.php` `get_caps_map()` |
| Contributor cannot reach Add Listing | submit cap missing | activation grant |
| 200 with empty body on forbidden REST | `permission_callback` returns false | the controller's permission method |
| Cap lost after reactivate | non-idempotent grant | `Activator` role grant loop |

## Not verified
Step 8; steps 4 (stripped-role case) and 7.

## Cleanup
Restore any cap removed in step 4.
