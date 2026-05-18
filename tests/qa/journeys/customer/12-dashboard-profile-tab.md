---
journey: dashboard-profile-tab
plugin: wb-listora
priority: normal
roles: [member]
covers: [user-dashboard, profile-tab, profile-edit, avatar-upload]
prerequisites:
  - "Test member has writable profile"
estimated_runtime_minutes: 4
---

# Member edits profile and changes persist

Verifies the Profile tab on the user dashboard saves display name, bio, social links, and avatar — and that the save uses REST (not form-post) with proper nonce + REST envelope.

## Setup

- Site: `$SITE_URL`
- Test member: `member1` (autologin); capture `USER_ID`
- Baseline:
  ```bash
  wp user meta list $USER_ID --keys=description,first_name,last_name --format=json
  ```

## Steps

### 1. Open Profile tab
- **Action**: `playwright_navigate $SITE_URL/dashboard/#profile?autologin=member1`
- **Expect**: form fields: Display Name, First Name, Last Name, Bio textarea, Avatar uploader

### 2. Edit + save
- **Action**: set First Name = "Smoke", Last Name = "Tester", Bio = "Test bio"; click Save
- **Expect**: REST `POST /wp-json/listora/v1/profile` returns 200 with `{ user_id, updated: true }`

### 3. Verify persistence (data flow)
- **Action**:
  ```sql
  SELECT meta_key, meta_value FROM wp_usermeta
  WHERE user_id=$USER_ID AND meta_key IN ('first_name','last_name','description');
  ```
- **Expect**: `first_name=Smoke`, `last_name=Tester`, `description=Test bio`

### 4. Reload page → values reflected
- **Action**: refresh, inspect form fields
- **Expect**: values pre-filled from saved state

### 5. Avatar upload
- **Action**: upload a 200KB PNG via the avatar uploader
- **Expect**: REST `POST /wp-json/listora/v1/profile/avatar` returns 200 with `{ avatar_url }`; preview updates immediately

### 6. Verify avatar URL persisted
- **Action**:
  ```bash
  wp user meta get $USER_ID listora_avatar_id
  ```
- **Expect**: numeric attachment ID

### 7. Avatar shows on listing detail review card
- **Action**: navigate to a listing where this member left a review
- **Expect**: review card uses the new avatar (or BP avatar if BP active)

### 8. Validation — empty display name
- **Action**: clear "Display Name" field → Save
- **Expect**: inline validation error "Display name is required"; 400 from REST; no DB write

### 9. Validation — XSS attempt in bio
- **Action**: set bio = `<script>alert(1)</script>`; save; reload
- **Expect**: bio field shows escaped text `&lt;script&gt;alert(1)&lt;/script&gt;` or stripped via `wp_kses`. No alert dialog.

### 10. Developer filter (extensibility)
- **Action**: `wp eval 'add_filter("wb_listora_profile_update_fields", function($f){ $f["custom_field"] = sanitize_text_field($_POST["custom_field"] ?? ""); return $f; });'` submit form with custom_field
- **Expect**: filter merges custom field; saved as user meta `listora_custom_field`

## Pass criteria

1. Display name + name fields + bio save and reload correctly
2. Avatar uploads, persists as attachment ID, renders on review cards
3. Validation prevents empty required + sanitizes XSS
4. REST returns canonical `{ user_id, updated }` shape
5. `wb_listora_profile_update_fields` filter accepts additional fields

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Save returns 200 but DB unchanged | sanitize callback nullifies value | `class-profile-controller.php::sanitize` |
| Avatar upload 500 | media upload caps missing OR mime check fails | `class-profile-controller.php::handle_avatar` |
| XSS rendered raw | output not escaped in template | `templates/blocks/user-dashboard/tab-profile.php` |
| Form-post instead of REST | regression — admin-post hook used | grep `admin_post_listora_profile` (must be empty) |
