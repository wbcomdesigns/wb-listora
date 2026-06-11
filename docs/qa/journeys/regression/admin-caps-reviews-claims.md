---
journey: admin-caps-reviews-claims
plugin: wb-listora
priority: critical
roles: [editor, administrator]
covers: [reviews-page-cap, claims-page-cap, settings-page-cap-regression]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "An editor-role user exists (will be created if missing — capture EDITOR_LOGIN)"
estimated_runtime_minutes: 4
covers_card: 9910737458
covers_commit: faef4d8
---

# Reviews + Claims admin pages gated on the correct moderation caps (not types cap)

Regression sentinel for BC 9910737458 — Reviews and Claims admin pages + their single/bulk action handlers were all gated on `manage_listora_types`. Roles granted the purpose-built `moderate_listora_reviews` / `manage_listora_claims` caps (e.g. the editor role) got 403 on both moderation pages.

The fix (`faef4d8`): Reviews page + review actions/bulk → `moderate_listora_reviews`. Claims page + claim actions/bulk → `manage_listora_claims`. Settings + types + taxonomy pages keep `manage_listora_types` (regression guard).

## Setup

- Site: `$SITE_URL`
- Create or pick an editor-role user (`EDITOR_LOGIN`). The editor role has `moderate_listora_reviews` + `manage_listora_claims` per Free's `Capabilities` defaults, but does NOT have `manage_listora_types`.
- Confirm autologin works for that user.

## Steps

### 1. Editor user has the right caps
- **Action**:
  ```
  wp eval "echo get_role('editor')->has_cap('moderate_listora_reviews') ? '1' : '0'; echo PHP_EOL;
  echo get_role('editor')->has_cap('manage_listora_claims') ? '1' : '0'; echo PHP_EOL;
  echo get_role('editor')->has_cap('manage_listora_types') ? '1' : '0'; echo PHP_EOL;"
  ```
- **Expect**: prints `1`, `1`, `0`. (If `0`, `0`, `0` — the role-defaults aren't being applied; check `Capabilities::add_listora_caps_to_role()`.)

### 2. Reviews page renders for editor
- **Action**: visit `$SITE_URL/wp-admin/admin.php?page=listora-reviews&autologin=<EDITOR_LOGIN>`.
- **Expect**:
  - HTTP 200, page title contains "Reviews".
  - `h1` contains "Reviews".
  - Body does NOT contain "Sorry, you are not allowed".
- **On fail**: check `includes/admin/class-admin.php:369` — must be `moderate_listora_reviews`, not `manage_listora_types`.

### 3. Claims page renders for editor
- **Action**: visit `$SITE_URL/wp-admin/admin.php?page=listora-claims&autologin=<EDITOR_LOGIN>`.
- **Expect**: same as step 2 but `h1` = "Claims".
- **On fail**: check `class-admin.php:379` — must be `manage_listora_claims`.

### 4. Settings page is BLOCKED for editor (regression guard)
- **Action**: visit `$SITE_URL/wp-admin/admin.php?page=listora-settings&autologin=<EDITOR_LOGIN>`.
- **Expect**:
  - Page title contains "Error" or shows "Sorry, you are not allowed to access this page."
- **On fail**: editor mistakenly got `manage_listora_types` capability — Settings should remain admin-only. Check the cap-grant logic in `Capabilities` class.

### 5. Single review action handler — capability check fires before action
- **Action** (as editor, with a pending review present): trigger a review-approve URL handler:
  ```
  wp eval "wp_set_current_user(<EDITOR_ID>); echo current_user_can('moderate_listora_reviews') ? 'allowed' : 'denied';"
  ```
- **Expect**: `allowed`.
- **Action 2** (as a non-editor user with no caps): the same eval with that user's ID → `denied`.
- **Why**: ensures the cap check in the handler (lines 1090, 1108 of `class-admin.php`) gates the action, not the page render.

### 6. Single claim action handler — same gating
- **Action**: same as step 5 but with `manage_listora_claims`.

### 7. Bulk-moderate handlers also gated on the right caps
- **Action**: `grep -n "moderate_listora_reviews\|manage_listora_claims" includes/admin/class-admin.php | head` — verify both caps appear in the bulk handlers (lines 1090/1108 for reviews, 1366/1420 for claims).
- **Expect**: 4 references total (2 per cap) covering the single + bulk paths.

### Cleanup

If a temporary editor user was created for the run, delete it.

## Notes

- Free's capability defaults grant `moderate_listora_reviews` + `manage_listora_claims` to the editor role + administrator. `manage_listora_types` is administrator-only. This separation is INTENTIONAL — moderation is delegable, schema configuration isn't.
- Pro's `listora_moderator` role (when active) gets the moderation caps via Pro's role registration; same gate path applies to that role too.
- A future change to the cap layout requires updating: (a) this journey's expected caps, (b) the menu-cap declarations at `class-admin.php:332/341/351/360/369/379`, (c) the 4 action handler cap checks (1090/1108/1366/1420).
