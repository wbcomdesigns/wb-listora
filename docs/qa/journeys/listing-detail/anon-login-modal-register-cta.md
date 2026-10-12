---
journey: anon-login-modal-register-cta
group: listing-detail
actor: customer
mode: browser
plugin: wb-listora
priority: high
roles: [guest, owner, admin]
covers: [F-04, login-modal, register-cta, wb_listora_login_modal_register_url]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "At least one published listing ({listing})"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unbridged theme]
site_profile: required
supersedes:
  - ora:regression/anon-login-modal-register-cta.md
---

# The anonymous login modal always renders a Create account CTA

## Why
The modal that opens when a guest clicks Save (favourite) or Claim on a listing detail page must show BOTH "Log in" and "Create account". The Create account link must not be gated on `get_option('users_can_register')`: WordPress already says "Registration is currently not allowed" on the registration page, a clearer affordance than silently hiding the CTA. Sites suppress it by returning an empty string from `wb_listora_login_modal_register_url`. Origin: smoke F-04 (a dead end for guests on a `users_can_register=0` site).

Markup (`blocks/listing-detail/render.php:1074-1092`): `#listora-login-modal.listora-detail__modal` > `[role=dialog][aria-modal=true]` > `h3#listora-login-modal-title` ("Log in to save listings" for Save, "Log in to claim this listing" from Claim), and `.listora-detail__modal-actions` with `a.listora-btn--primary` "Log in" and `a.listora-btn--secondary` "Create account". The Save control is `button.listora-btn--secondary` "Save to favorites" (`data-wp-on--click="actions.toggleFavorite"`).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Anonymous browser session (no auth cookie).
- Record `wp option get users_can_register` and RESTORE it at the end.
- `wp eval 'echo wp_registration_url();'` gives the expected Create account href (BuddyPress sites: `$SITE_URL/register/`; plain WordPress: `/wp-login.php?action=register`).

## Steps

### 1. Registration ON: open the modal from Save
- **Action**: `wp option update users_can_register 1`; open `$SITE_URL/listing/{listing}/` at 1440 and 390; click Save.
- **Expect**: `#listora-login-modal` gets `.is-open` (visible dialog, focus inside); heading "Log in to save listings"; "Log in" anchor `href` `/wp-login.php?redirect_to=<url-encoded listing permalink>`; "Create account" anchor `href` equal to `wp_registration_url()`; both visible and >= 40px tall at 390.
- **Read-back**: anchors' `getAttribute('href')`; neither is `#` or empty.

### 2. Registration OFF: still both anchors
- **Action**: `wp option update users_can_register 0`; reload as guest; click Save.
- **Expect**: the modal still renders BOTH anchors; Create account's `href` is still `wp_registration_url()` (WordPress shows "Registration is currently not allowed" on arrival, the canonical UX).
- **Read-back**: on fail, `render.php` gates `$listora_reg_url` on `users_can_register` again.

### 3. Claim opens the same modal
- **Action**: click Claim as guest (registration ON and OFF).
- **Expect**: title "Log in to claim this listing"; same two anchors.
- **Read-back**: anchors equal step 1. Not verified live.

### 4. Filter suppression (control for "CTA hidden")
- **Action**: from a mu-plugin or `wp eval-file` bootstrap `add_filter( 'wb_listora_login_modal_register_url', '__return_empty_string' );` reload, click Save.
- **Expect**: only "Log in"; no "Create account" (the `if ( $listora_reg_url )` guard at `render.php:1087`). Steps 1-2 are the control proving the anchor otherwise appears.
- **Read-back**: anchor count is 1.

### 5. Keyboard and dark
- **Action**: open the modal with the keyboard (Enter on Save), Tab through, Esc; repeat in dark and on the unbridged theme.
- **Expect**: focus enters the dialog, both anchors reachable, Esc closes and returns focus to Save; anchors readable in dark.
- **Read-back**: `document.activeElement`. Not verified live.

### 6. Restore
- **Action**: remove the filter; restore `users_can_register`.
- **Expect**: step 1 or 2 result as per the restored value.
- **Read-back**: `wp option get users_can_register`.

## Role ladder
| Rung | Expect |
|---|---|
| {guest} | subject of the journey (steps 1-5) |
| {owner} (logged in) | Save toggles the favourite directly; `#listora-login-modal` never gets `.is-open` (the markup stays in the page, so assert on `.is-open`/visibility, not presence) |
| {admin} | same as {owner} |
| Listing owner | same as {owner} |

## Pass criteria
1. `users_can_register=1`: both anchors.
2. `users_can_register=0`: both anchors.
3. Filter returning `''`: only Log in.
4. Both links carry real `href` values.

## Fail diagnostics
| Symptom | File |
|---|---|
| No Create account with `users_can_register=0` | `blocks/listing-detail/render.php:1050-1060` - `$listora_reg_url` must call `wp_registration_url()` unconditionally |
| Filter has no effect | `blocks/listing-detail/render.php:1072` |
| Log in `href` is `#` | `$listora_login_url` in `render.php` |
| Anchors invisible | `blocks/listing-detail/style.css` `.listora-detail__modal a` |

## Not verified
Opening the modal by click, the Claim-opened title, the logged-in positive control, the filter case (needs a mu-plugin) and 390px sizing were not run; markup and both registration states were verified by curl as a guest.

## Cleanup
Remove the filter bootstrap; restore `users_can_register`.
