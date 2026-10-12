---
journey: anon-login-modal-register-cta
plugin: wb-listora
priority: high
roles: [anonymous, subscriber, administrator]
covers: [F-04, login-modal, register-cta, wb_listora_login_modal_register_url]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "At least one published listing (the reference site has /listing/fitlab-performance-gym/)"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Anon login modal always renders a Create account CTA (F-04 sentinel)

The login modal that opens when an anonymous visitor clicks Save (favorite) or Claim on a listing detail page MUST render BOTH a "Log in" link and a "Create account" link. The Create account link must NOT be gated on `get_option('users_can_register')`: WordPress already says "Registration is currently not allowed" on the registration page when the option is off, a clearer affordance than silently hiding the CTA. Sites that want to suppress the CTA return an empty string from the `wb_listora_login_modal_register_url` filter.

Origin: smoke F-04 (2026-05-18): the modal rendered only Log in on a site with `users_can_register=0`, a dead end for anonymous visitors.

Current markup (`blocks/listing-detail/render.php:1074-1092`): `#listora-login-modal.listora-detail__modal` > `[role=dialog][aria-modal=true]` with `h3#listora-login-modal-title` ("Log in to save listings" for Save, "Log in to claim this listing" when opened from Claim), and `.listora-detail__modal-actions` holding `a.listora-btn--primary` "Log in" and `a.listora-btn--secondary` "Create account". The Save control is `button.listora-btn--secondary` labelled "Save to favorites" (`data-wp-on--click="actions.toggleFavorite"`); there is no `.listora-detail__favorite-btn` class any more.

## Setup

- Anonymous browser session (no auth cookie).
- Record `wp option get users_can_register` and RESTORE it at the end. Reference site value: `0`.
- `wp eval 'echo wp_registration_url();'` gives the expected Create account href. On this site (BuddyPress) it is `$SITE_URL/register/`; on plain WordPress it is `/wp-login.php?action=register`.

## Case A: registration ON (`users_can_register = 1`)

### 1. Open a listing detail page
- **Action**: open `$SITE_URL/listing/<slug>/` at 1440 and 390.
- **Expect**: the page renders and the Save button (`aria-label="Save to favorites"`) is visible.

### 2. Click Save
- **Action**: click Save.
- **Expect**: `#listora-login-modal` gets `.is-open` (visible dialog, focus inside).

### 3. Inspect the modal
- **Expect**: heading "Log in to save listings"; "Log in" anchor with `href` `/wp-login.php?redirect_to=<url-encoded listing permalink>`; "Create account" anchor with `href` equal to `wp_registration_url()`; both visible and >= 40px tall at 390. Verified in the served HTML 2026-10-12 with the option set to 1.

## Case B: registration OFF (`users_can_register = 0`)

### 4. Toggle off
- **Action**: `wp option update users_can_register 0` (restore at the end).

### 5. Reload as anonymous and click Save
- **Expect**: the modal still renders BOTH anchors; Create account still present and visible; its `href` is still `wp_registration_url()` (WordPress shows "Registration is currently not allowed" on arrival: that is the canonical UX). Verified in the served HTML with the option at 0.
- **On fail**: `blocks/listing-detail/render.php` is gating `$listora_reg_url` on `users_can_register` again. It must be an unconditional `wp_registration_url()` and then filtered.

## Case C: filter suppression (POSITIVE CONTROL for "CTA hidden")

### 6. Suppress via the public filter
- **Action**: from a mu-plugin or `wp eval-file` bootstrap: `add_filter( 'wb_listora_login_modal_register_url', '__return_empty_string' );` reload, click Save.
- **Expect**: only the "Log in" anchor; no "Create account" (the `<?php if ( $listora_reg_url ) : ?>` guard at `render.php:1087`). Cases A and B above are the control proving the anchor otherwise appears.

### 7. Restore
- Remove the filter and restore `users_can_register`.

## Role ladder
- **Anonymous**: the subject of this journey (cases A-C).
- **Subscriber (logged in, e.g. subscriber2)**: Save toggles the favorite directly and `#listora-login-modal` never gets `.is-open` (the markup is still in the page for logged-in users, so assert on `.is-open`/visibility, not presence).
- **Administrator**: same as subscriber.
- **Listing owner**: same as subscriber (their own listing).

## Pass criteria
1. `users_can_register=1`: modal shows Log in and Create account.
2. `users_can_register=0`: modal still shows both.
3. Filter returning `''`: only Log in.
4. Both links carry real `href` values (no `#`, no empty).

## Fail diagnostics

| Symptom | File |
|---|---|
| No Create account with `users_can_register=0` | `blocks/listing-detail/render.php:1050-1060` - `$listora_reg_url` must call `wp_registration_url()` unconditionally |
| Filter has no effect | `blocks/listing-detail/render.php:1072` - `apply_filters( 'wb_listora_login_modal_register_url', ... )` |
| Log in `href` is `#` | `wp_login_url` unavailable: `render.php` `$listora_login_url` |
| Modal opens but anchors invisible | CSS: `blocks/listing-detail/style.css` `.listora-detail__modal a` |

## Not verified
Opening the modal by click, the Claim-opened title, the logged-in positive control, the filter case (needs a mu-plugin) and 390px sizing were not run; the modal's server markup and both registration states were verified by curl as anonymous.
