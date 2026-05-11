---
journey: role-cap-matrix
plugin: wb-listora
priority: critical
roles: [admin, editor, contributor, subscriber]
covers: [capabilities, custom-caps, role-permissions, listora_capabilities_class]
prerequisites:
  - "Test users for each role: admin1, editor1, contributor1, subscriber1"
estimated_runtime_minutes: 6
---

# Each WordPress role has the correct Listora capability set

Verifies the role/capability matrix: every Listora custom cap is granted to the right roles, and forbidden actions return proper 403/401 (never silent succeed, never fatal).

## Capability matrix (expected state)

| Capability | admin | editor | contributor | subscriber | anonymous |
|---|---|---|---|---|---|
| `manage_listora_settings` | ✓ | ✗ | ✗ | ✗ | ✗ |
| `moderate_listora_reviews` | ✓ | ✓ | ✗ | ✗ | ✗ |
| `manage_listora_claims` | ✓ | ✓ | ✗ | ✗ | ✗ |
| `manage_listora_types` | ✓ | ✗ | ✗ | ✗ | ✗ |
| `submit_listora_listing` | ✓ | ✓ | ✓ | ✓ | ✗ |
| `edit_own_listora_listing` | ✓ | ✓ | ✓ | ✓ | ✗ |
| `read` (login required) | ✓ | ✓ | ✓ | ✓ | ✗ |

## Setup

- Site: `$SITE_URL`
- 4 test users exist for the 4 roles.

## Steps (per role)

For each role, perform a permission probe and assert pass/fail:

### Probe template
```
For each (role, capability) pair:
1. Autologin as the test user for that role
2. Attempt the action that requires the capability
3. Assert: passes if expected ✓, returns 403 if expected ✗
```

### 1. Admin: manage_listora_settings
- **Action**: GET `/wp-admin/admin.php?page=wb-listora-settings`
- **Expect**: 200 with settings form rendered

### 2. Editor: manage_listora_settings
- **Action**: same URL as editor1
- **Expect**: 403 "You do not have permission" OR redirect — NOT a fatal

### 3. Contributor: submit_listora_listing
- **Action**: `playwright_navigate $SITE_URL/submit/?autologin=contributor1`
- **Expect**: submission form renders (must NOT redirect to login)

### 4. Anonymous: submit_listora_listing
- **Action**: same URL, no autologin
- **Expect**: login-required gate OR guest-submission flow if `allow_guest_submission` enabled. Never a fatal.

### 5. Editor: moderate_listora_reviews
- **Action**: GET `/wp-admin/admin.php?page=wb-listora-reviews`
- **Expect**: 200 with reviews moderation table

### 6. Contributor: moderate_listora_reviews
- **Action**: same URL as contributor1
- **Expect**: 403

### 7. Editor: manage_listora_claims
- **Action**: GET `/wp-admin/admin.php?page=wb-listora-claims`
- **Expect**: 200

### 8. Subscriber: manage_listora_claims
- **Action**: same URL as subscriber1
- **Expect**: 403

### 9. REST permission_callback returns WP_Error not false
- **Action**:
  ```bash
  curl -s -X PATCH "$SITE_URL/wp-json/listora/v1/listings/1" \
    -H "Content-Type: application/json" \
    --cookie "wordpress_logged_in_..." \
    -d '{"post_status":"trash"}'
  ```
  as subscriber1
- **Expect**: 401 OR 403 with `WP_Error` JSON body `{ code: rest_forbidden, message: ..., data: { status: 401 } }`. **MUST NOT** return `false` or `0` — wppqa Rule R4 violation.

### 10. Capabilities helper methods consistent
- **Action**:
  ```bash
  wp eval '$caps = ["manage_listora_settings","moderate_listora_reviews","manage_listora_claims","manage_listora_types","submit_listora_listing"]; foreach ($caps as $c) echo $c.": ".(\WBListora\Capabilities::has_cap(get_user_by("login","editor1")->ID, $c) ? "Y" : "N")."\n";'
  ```
- **Expect**: output matches the matrix above for editor row

### 11. Custom role: Listora Moderator (if Pro adds one)
- If Pro adds the `listora_moderator` role: verify it has moderate_reviews + manage_claims but NOT manage_settings
- **Action**: `wp role list` should show the role
- **Expect**: capabilities subset matches Pro's expected grant

### 12. Capability grants persist after deactivate/reactivate
- **Action**: `wp plugin deactivate wb-listora && wp plugin activate wb-listora`
- **Expect**: roles still have the right caps (re-applied on activation hook idempotently)

## Pass criteria

1. Every (role, cap) cell matches the expected matrix above
2. REST 401/403 errors use `WP_Error`, never `false`
3. Forbidden actions never produce a PHP fatal
4. `Capabilities::has_cap()` helper matches `current_user_can()` for all caps
5. Deactivation + reactivation preserves grants

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Editor can access settings | `manage_listora_settings` granted too broadly | `class-capabilities.php::get_role_caps` |
| Contributor cannot submit | `submit_listora_listing` not on contributor role | activation hook OR `class-capabilities.php` |
| 401 returns `false` | regression of R-S security rule | grep `permission_callback.*return false` (must be 0) |
| Cap removed after reactivate | activation isn't idempotent | `class-activator.php::activate` |
| Pro moderator role missing | Pro's role registration not run | `wb-listora-pro/includes/class-activator.php` |
