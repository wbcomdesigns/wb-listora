---
journey: anon-login-modal-register-cta
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [F-04, login-modal, register-cta, wb_listora_login_modal_register_url]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "At least one published listing (any type)"
estimated_runtime_minutes: 2
---

# Anon login modal always renders Create Account CTA (F-04 sentinel)

The login modal that opens when an anonymous visitor clicks Save-to-Favorites
or Claim-Business on a listing detail page MUST always render BOTH a Log In
link and a Create Account link. The Create Account link must NOT be gated on
`get_option('users_can_register')` — WordPress already shows "Registration is
currently not allowed" on `/wp-login.php?action=register` when the option is
off, which is a clearer affordance than silently hiding the CTA.

Sites that want to suppress the CTA can return an empty string from the
`wb_listora_login_modal_register_url` filter.

Originally smoke F-04 (2026-05-18 BLOCKED): modal rendered only the Log In
link on a site with `users_can_register=0` → dead end for anonymous visitors.
Fix: commit 773a89a.

## Setup

- Site: `$SITE_URL`
- Anonymous browser session (no auth cookie)
- Confirm `users_can_register` option state via:
  `wp option get users_can_register` → record value but DO NOT change it

## Steps — case A: registration ON (`users_can_register = 1`)

### 1. Open a listing detail page
- **Action**: `playwright_navigate $SITE_URL/listing/<slug>/`
- **Expect**: page renders, Save-to-Favorites button visible

### 2. Click Save-to-Favorites
- **Action**: click `.listora-detail__favorite-btn` (or its labeled equivalent)
- **Expect**: `.listora-detail__modal#listora-login-modal` flips to `.is-open`

### 3. Inspect modal contents
- **Action**: snapshot `#listora-login-modal`
- **Expect**:
  - Heading present (e.g. "Log in to save listings")
  - **Log In** anchor present with `href` containing `/wp-login.php` and a `redirect_to=` parameter back to the current listing
  - **Create Account** anchor present with `href` containing `/wp-login.php?action=register`
  - Both anchors are inside the modal, not collapsed/hidden

## Steps — case B: registration OFF (`users_can_register = 0`)

### 4. Toggle registration off
- **Action**: `wp option update users_can_register 0`
- (Restore at end of test.)

### 5. Reload the listing page as anonymous → click Save
- **Action**: hard reload, click Save-to-Favorites
- **Expect**:
  - Modal still renders BOTH anchors
  - **Create Account** anchor still present and visible
  - `href` still points to `/wp-login.php?action=register` (WordPress will display "Registration is currently not allowed" on landing — that's the canonical WP UX)
- **On fail**: regression of F-04 — `blocks/listing-detail/render.php` is gating `$listora_reg_url` on `users_can_register` again. The line MUST be unconditional `wp_registration_url()` (then filterable).

## Steps — case C: filter suppression

### 6. Suppress the CTA via the public filter
- **Action**: add a mu-plugin or `wp eval-file` with:
  ```php
  add_filter( 'wb_listora_login_modal_register_url', '__return_empty_string' );
  ```
- Reload and click Save again
- **Expect**:
  - Modal renders Log In anchor only
  - Create Account anchor is NOT rendered (templated `<?php if ( $listora_reg_url ) : ?>` guard)

### 7. Restore
- **Action**: remove the mu-plugin / filter, restore `users_can_register` to original value

## Pass criteria

1. With `users_can_register=1`: modal shows BOTH Log In and Create Account
2. With `users_can_register=0`: modal still shows BOTH (CTA always visible)
3. With `wb_listora_login_modal_register_url` returning '': only Log In shows
4. Both links carry valid `href` values (no `#` placeholders, no `null`)

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| No Create Account anchor with `users_can_register=0` | regression of F-04 | `blocks/listing-detail/render.php` ~line 776 — `$listora_reg_url` must call `wp_registration_url()` unconditionally |
| `wb_listora_login_modal_register_url` filter has no effect | Filter not applied to resolved URL | `blocks/listing-detail/render.php` ~line 801 — `apply_filters( 'wb_listora_login_modal_register_url', ... )` must wrap the URL |
| Both anchors present but Log In `href` is `#` | wp_login_url unavailable | Check `function_exists( 'wp_login_url' )` guard isn't false in this context |
| Modal opens but anchors invisible | CSS regression, not F-04 | `blocks/listing-detail/style.css` — `.listora-detail__modal a` visibility |
