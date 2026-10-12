---
journey: dashboard-profile-tab
plugin: wb-listora
priority: normal
roles: [owner, other, blocked-member, editor, admin, guest]
covers: [user-dashboard, profile-tab, profile-edit, notification-prefs, social-links, blocked-members, blocked-members-styling, bc-10351026889, covers_card 10322692957]
prerequisites:
  - "Site reachable at $SITE_URL; dashboard page mapped in Listora > Settings > General"
  - "{owner} has a writable profile"
  - "Blocked-members steps: QA-J1-blocker, QA-J1-blocked1, QA-J1-blocked2 (one with a very long display name), blocks created with `\\WBListora\\Core\\Member_Blocks::block( blocker, target )`"
estimated_runtime_minutes: 10
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unbridged theme]
site_profile: required
supersedes:
  - ora:regression/blocked-members-styled.md
fails_on:
  - 1cf098e4 (pre-fix build, step 10)
---

# Member edits their profile, and the Blocked Members section looks like the rest of the tab

## Why
The Profile tab saves name, phone, website, bio, social links and email-notification choices through one REST call, and hosts the Blocked Members list. Silent save failures, an email change without confirmation, or an unstyled blocked list (the 1.7.0 markup shipped with no CSS) hurt every member. The tab has NO avatar uploader: the photo comes from Gravatar (or BuddyPress when active).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- `?autologin={owner}`; capture `USER_ID`. Baseline (restore at the end): `wp user get $USER_ID --fields=display_name,first_name,last_name,description --format=json`.
- Tabs are addressed with `?tab=profile`, not `#profile`.

## Steps

### 1. Open the Profile tab
- **Action**: `$DASH/?tab=profile`.
- **Expect**: `#dash-tab-profile` has `aria-selected="true"`; `#dash-panel-profile` visible. `form.listora-dashboard__profile-form` with `#listora-display-name`, `#listora-email`, `#listora-first-name`, `#listora-last-name`, `#listora-phone` (hint "Private — not shown publicly on your listings."), `#listora-website`, `#listora-bio` (`name="description"`), one `#listora-social-<platform>` input per platform, toggles `input[name^="notification_prefs["]`, button `button[name="listora_update_profile"]` "Save Changes". A "Profile photo" block with a "Change photo" link and the hint "Managed by Gravatar, the profile photo service WordPress uses." No file input.
- **Read-back**: n/a (no write).

### 2. Edit and save
- **Action**: First name `QA-J1`, Last name `Tester`, Bio `QA-J1 bio`, Phone `+1 415 555 0123`; Save Changes.
- **Expect**: toast "Profile saved."; `POST /wp-json/listora/v1/dashboard/profile` with `X-HTTP-Method-Override: PUT`, response `200 {"updated":true}`; Save disabled while in flight and re-enabled after.
- **Read-back**: see step 3.

### 3. Persistence
- **Action**: `wp user meta get $USER_ID first_name`, `last_name`, `description`, `_listora_phone`.
- **Expect**: `QA-J1`, `Tester`, `QA-J1 bio`, `+1 415 555 0123`. Social links land in `_listora_social_links` (only platforms in `Field::social_link_platforms()`); toggles in `_listora_notify_<event>` as `1`/`0`.
- **Read-back**: the CLI reads above; positive control: a value deliberately different (`QA-J1-x`) fails the comparison.

### 4. Reload shows saved values
- **Action**: reload `?tab=profile`.
- **Expect**: every field pre-filled; a toggle switched off stays off.
- **Read-back**: control: switch it back on, save, reload, it is checked.

### 5. Email change is staged, never written
- **Action**: change Email to `qa-j1-new@example.test` and Save (no password field exists in the form).
- **Expect**: expected-fail: the web form should let the member supply the current password; today REST returns `403 listora_password_required` ("Enter your current password to change your email address.") and the form cannot supply it (PRODUCT-BUGS). With `current_password` over REST the response carries `email_change_pending: true` and the address moves only after the confirmation link.
- **Read-back**: `wp user get $USER_ID --field=user_email` unchanged.

### 6. Validation and sanitising
- **Action** (REST, member): `PUT /dashboard/profile {"description":"<script>alert(1)</script>QA-J1 <b>x</b>"}`; also boundary input: empty, whitespace-only, max-length (> 5,000 chars), emoji and quotes in the bio and display name, malformed URL in website/social, negative/huge phone.
- **Expect**: `200`, stored bio `QA-J1 x` (tags stripped), no script on reload; bad URLs rejected or cleaned. Empty `display_name` over REST is NOT refused (WordPress falls back to the login, `200 {"updated":true}`); in the browser the `required` attribute blocks the submit first.
- **Read-back**: bio via `wp user meta get`; reload source has no `<script>` from the bio. Boundary cases beyond the script one: Not verified live.

### 7. Double submit
- **Action**: double-click Save; and submit from two tabs.
- **Expect**: one effective save, last write wins, no error toast; button disabled while in flight.
- **Read-back**: final meta equals the later submit. Not verified live.

### 8. Blocked Members section: structure
- **Action**: as the blocker (two blocks) open `?tab=profile`, scroll to Blocked Members.
- **Expect**: `div.listora-dashboard__section.listora-dashboard__blocked` OUTSIDE the profile `<form>` > `h3.listora-dashboard__section-title` "Blocked Members", `p.listora-dashboard__section-desc` "You will not see their reviews, and they cannot contact you. Blocking works in both directions.", the shared empty card `div.listora-card--empty[data-listora-blocked-empty]` ("You have not blocked anyone.", present but `hidden` while blocks exist), `ul.listora-dashboard__blocked-list` > `li.listora-dashboard__blocked-row` > `img.listora-dashboard__blocked-avatar` + `span.listora-dashboard__blocked-name` + `button.listora-dashboard__unblock-btn` (`aria-label="Unblock <name>"`, text "Unblock"). With nobody blocked only the empty card shows.
- **Read-back**: control: block a member from a review card, reload, the row appears.

### 9. Blocked Members styling
- **Action**: compare computed styles.
- **Expect**: card chrome like `.listora-dashboard__profile-section` (same background, border, radius; padding `var(--listora-space-5)`, shadow `--listora-shadow-sm`); `.listora-dashboard__section-desc` is `--listora-text-size-sm` and `--listora-fg-muted`; the list has `list-style-type: none`, zero margin/padding, rows `display:flex` with a 32px circular avatar (HTML says `width="24"`, CSS sets 32px), name and Unblock on one line at desktop, a hairline between rows.
- **Read-back**: `getComputedStyle` of `.listora-dashboard__blocked` vs `.listora-dashboard__profile-section` `border-radius` and `border`.

### 10. Long display name and Unblock without reload
- **Action**: view the long-named blocked member; click Unblock for each member.
- **Expect**: the name wraps (`overflow-wrap: anywhere`), Unblock stays visible and clickable (min-height 40px), no horizontal scroll. Rows disappear (`DELETE /listora/v1/me/blocks/{id}` via `src/interactivity/store.js:2095`); when the last goes, `[data-listora-blocked-empty]` loses `hidden` with the same shared empty-state styling (the JS reveal depends on the same CSS, so a fix that only styled the server render leaves this branch bare).
- **Read-back**: `wp eval` block list for the blocker is empty after the last unblock; the empty card is in the DOM from first render (positive control that it can be revealed).

### 11. "Change photo" lands where its label and hint say (card 10351026889)
- **Action**: click the visible "Change photo" control. Run once with BuddyPress Members inactive, once active.
- **Expect**: inactive: opens `https://gravatar.com/profile`, no file input in the panel; active: opens the BuddyPress change-avatar page (`bp_members_get_user_url( $uid ) . 'profile/change-avatar/'`) whose uploader is present, and after uploading `get_avatar_url( $uid )` differs. The copy never promises an uploader the link does not reach.
- **Read-back**: `get_avatar_url( $uid )` before and after; negative control: BP inactive keeps it unchanged because the upload is off-site.
- **fails_on**: pre-1cf098e4 build (behaviour reworded in 1cf098e4). Not verified live.

### 12. Viewports and themes
- **Action**: repeat steps 1-2 and 8-10 at 390 in light and dark.
- **Expect**: no horizontal scroll (`scrollWidth <= innerWidth`); Save Changes >= 40px (measured 46px); fields one column; at <= 640px a blocked row wraps and Unblock drops to its own full-width row (`flex: 1 0 100%`, >= 40px) with the avatar beside the name. Both `blocks/user-dashboard/style-rtl.css` and `style.layer.css` carry the `__blocked` rules (generated, never hand-edit; run the build).
- **Read-back**: `grep -c __blocked blocks/user-dashboard/style-rtl.css blocks/user-dashboard/style.layer.css` both > 0.

## Role ladder
| Rung | Expect |
|---|---|
| {owner} | steps 1-12; edits only their own profile |
| blocker | steps 8-10 (list is per blocker) |
| blocked member | does not see the blocker in their own Blocked Members (the contact block itself is symmetric; see `blocking-enforced-on-live-contact-route`) |
| {other} | `PUT /dashboard/profile` only changes the caller (no user id param); control: saving as {other} leaves {owner} unchanged |
| {editor}, {admin} | same, own profile only |
| {guest} | `GET`/`PUT /dashboard/profile` `401 listora_unauthorized`; dashboard shows "Please log in to view your dashboard." |

## Pass criteria
1. Names, phone, bio, website, social links and notification toggles save and reload.
2. REST returns `{updated:true}`; email never changes without password + confirmation; bio sanitised.
3. Blocked Members has card chrome, no bullets, wraps long names, restyles correctly after unblocking without reload.
4. "Change photo" reaches the page its hint names; no avatar upload control on the tab.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Save 200 but meta unchanged | param not read | `includes/rest/class-dashboard-controller.php:685` (`update_profile`) |
| Email changed without password | gate removed | `class-dashboard-controller.php:756` |
| Toast missing / button stuck | save action | `src/interactivity/store.js:1725` (`saveProfile`) |
| Wrong field set / ids | template | `templates/blocks/user-dashboard/tab-profile.php:27` |
| Notification list wrong | event registry | `class-dashboard-controller.php:33` |
| Bare blocked list | CSS lost or build not run | `blocks/user-dashboard/style.css:2335-2415`, `style.layer.css` stale |
| Empty state not revealed | unblock handler | `src/interactivity/store.js:2095-2135` |
| Section inside the form | markup | `tab-profile.php:195-275` (`:213`) |

## Not verified
"Change photo" with BuddyPress active; dark/unbridged screenshots; steps 6 (boundary beyond script), 7; computed styles, unblock flow and 390 for the blocked list were read from served HTML and source only.

## Cleanup
Restore the baseline profile; delete QA-J1-blocker/blocked users and their blocks.
