---
journey: dashboard-profile-tab
plugin: wb-listora
priority: normal
roles: [member]
covers: [user-dashboard, profile-tab, profile-edit, notification-prefs, social-links, blocked-members, bc-10351026889]
prerequisites:
  - "Site reachable at $SITE_URL; dashboard page set in Listora > Settings > General (QA site: /my-listings/)"
  - "Test member with a writable profile (QA site: subscriber2, display name 'Sam Subscriber')"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Member edits their profile and the changes persist

The Profile tab saves name, phone, website, bio, social links and email-notification choices through one REST call. It has NO avatar uploader: the photo comes from Gravatar (or BuddyPress when active) and the tab only links out to it.

Verified via: curl/DOM + REST (rest_do_request as the member) + one Playwright pass on the shared MCP browser (save toast at 390). Not every step below was re-driven in a browser; see "Not verified".

## Setup

- Site: `$SITE_URL`. Dashboard URL = the page holding the `listora/user-dashboard` block; tabs are addressed with `?tab=<id>` (not `#profile`).
- Member: `?autologin=subscriber2`; capture `USER_ID` (4 on the QA site).
- Baseline, restore it at the end:
  ```bash
  wp user get $USER_ID --fields=display_name,first_name,last_name,description --format=json
  ```

## Steps

### 1. Open the Profile tab
- **Action**: `$SITE_URL/my-listings/?tab=profile`
- **Expect**: `#dash-tab-profile` has `aria-selected="true"`; `#dash-panel-profile` visible. Form `form.listora-dashboard__profile-form` with inputs `#listora-display-name`, `#listora-email`, `#listora-first-name`, `#listora-last-name`, `#listora-phone` (hint "Private — not shown publicly on your listings."), `#listora-website`, `#listora-bio` (`name="description"`), one `#listora-social-<platform>` URL input per platform, toggles `input[name^="notification_prefs["]`, button `button[name="listora_update_profile"]` labelled "Save Changes".
- **Expect (photo)**: a "Profile photo" block with a "Change photo" link (opens `https://gravatar.com/profile`, or the BuddyPress change-avatar page when BuddyPress Members is active) and the hint "Managed by Gravatar, the profile photo service WordPress uses." There is no file input in the panel.

### 2. Edit and save
- **Action**: set First name `QA-J1`, Last name `Tester`, Bio `QA-J1 bio`, Phone `+1 415 555 0123`; click Save Changes.
- **Expect**: toast "Profile saved."; request `POST /wp-json/listora/v1/dashboard/profile` with header `X-HTTP-Method-Override: PUT`, response `200 {"updated":true}`. The Save button is disabled while in flight and re-enabled after.

### 3. Persistence (DB)
- **Action**:
  ```bash
  wp user meta get $USER_ID first_name; wp user meta get $USER_ID last_name
  wp user meta get $USER_ID description; wp user meta get $USER_ID _listora_phone
  ```
- **Expect**: `QA-J1`, `Tester`, `QA-J1 bio`, `+1 415 555 0123`. Social links land in `_listora_social_links` (only platforms in `Field::social_link_platforms()`); toggles land in `_listora_notify_<event>` as `1`/`0`.

### 4. Reload shows saved values
- **Action**: reload `?tab=profile`.
- **Expect**: every field pre-filled from the saved state; a toggle switched off stays off (POSITIVE CONTROL: switch it back on, save, reload, it is checked).

### 5. Email change is staged, never written
- **Action**: change Email to `qa-j1-new@example.test` and Save (no password field exists in the form).
- **Expect**: REST `403 listora_password_required` "Enter your current password to change your email address." and `user_email` unchanged. With `current_password` supplied over REST the response carries `email_change_pending: true` and the address only moves after the confirmation link. See PRODUCT-BUGS in the report: the web form cannot supply the password.

### 6. Validation and sanitising
- **Action (REST, member)**: `PUT /dashboard/profile {"description":"<script>alert(1)</script>QA-J1 <b>x</b>"}`.
- **Expect**: `200`, stored bio `QA-J1 x` (tags stripped), no script anywhere on reload. Empty `display_name` over REST is NOT refused: WordPress falls back to the login (`200 {"updated":true}`); in the browser the `required` attribute on `#listora-display-name` blocks the submit first.

### 7. Role ladder
- **Anonymous**: `GET /dashboard/profile` and `PUT` return `401 listora_unauthorized`; the dashboard page shows "Please log in to view your dashboard." with a Log In link.
- **Another member**: `PUT /dashboard/profile` only ever changes the caller; there is no user id parameter (control: subscriber3 saving leaves subscriber2 unchanged).
- **Editor / admin (control)**: same, they edit their own profile only.
- Single-object journey otherwise: the object is the caller's own profile.

### 8. Blocked Members block (same tab)
- **Expect**: heading "Blocked Members"; with nobody blocked the empty card "You have not blocked anyone." (`[data-listora-blocked-empty]`); with a block, a list `.listora-dashboard__blocked-list` whose rows have a button named "Unblock <name>". POSITIVE CONTROL: block a member from a review card, reload, the row appears.

### 9. 390 viewport
- **Expect**: no horizontal page scroll (`document.documentElement.scrollWidth <= innerWidth`); Save Changes height >= 40px (measured 46px); fields stack to one column.

### 10. "Change photo" lands where its label and hint say (card 10351026889)
- **Action**: as the member click the visible "Change photo" control (do not just read its href). Run once with BuddyPress Members inactive, once active.
- **Expect**: inactive: the click opens `https://gravatar.com/profile` (the page the hint "Managed by Gravatar..." names) and the panel shows no file input; active: it opens the BuddyPress change-avatar page (`bp_members_get_user_url( $uid ) . 'profile/change-avatar/'`), whose uploader is present, and after uploading there `get_avatar_url( $uid )` differs from the value before. The copy never promises an uploader the link does not reach.
- **Read-back**: record `get_avatar_url( $uid )` before and after; negative control: with BP inactive it must stay unchanged because the upload happens off-site.
- **fails_on**: unknown (card 10351026889; behaviour reworded in 1cf098e4, so a pre-1cf098e4 build is the likely failing point). Not verified live.

## Pass criteria

1. Names, phone, bio, website, social links and notification toggles save and reload.
2. REST returns `{updated:true}`; email never changes without password + confirmation.
3. Bio is sanitised; anonymous gets 401; save only affects the caller.
4. No avatar upload control exists on this tab; "Change photo" reaches the Gravatar or BuddyPress page its hint names (step 10).

## Fail diagnostics

| Symptom | Likely cause | File:line |
|---|---|---|
| Save returns 200 but meta unchanged | param not read in update | `includes/rest/class-dashboard-controller.php:685` (`update_profile`) |
| Email changed without password | gate removed | `includes/rest/class-dashboard-controller.php:756` |
| Toast missing / button stuck disabled | save action | `src/interactivity/store.js:1725` (`saveProfile`) |
| Wrong field set / ids | template | `templates/blocks/user-dashboard/tab-profile.php:27` |
| Notification list wrong | event registry | `includes/rest/class-dashboard-controller.php:33` (`member_notification_events`) |

## Not verified

- Avatar "Change photo" target with BuddyPress active (BuddyPress not active on the QA site).
- Dark-mode and unknown-theme screenshots (no browser pass at those themes in this refresh).
- `wb_listora_profile_update_fields` filter named by the previous version of this journey does not exist in source; that step was removed.
