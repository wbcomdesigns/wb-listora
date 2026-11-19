---
journey: guest-actions-go-to-login
group: listing-detail
actor: customer
mode: browser
plugin: wb-listora
priority: high
roles: [guest]
covers: [login-redirect, wb_listora_login_url, login_url, no-footer-dialog]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "At least one published listing ({listing})"
estimated_runtime_minutes: 5
last_verified: 2026-11-19
viewports: [1440, 390]
themes: [reign, buddyx]
site_profile: required
supersedes:
  - ora:listing-detail/anon-login-modal-register-cta.md
---

# A guest's Save, Claim, Report and Review go to the site's login page

## Why
Listora used to print a log-in dialog in the footer of every guest page; where its stylesheet was not loaded it showed as plain text under the footer. A guest action now follows a link to the site's login page (`wb_listora_login_url()`, which is core `wp_login_url()`), and the page returns to where the guest was through `redirect_to`. A theme or BuddyNext that points `login_url` at its own page is followed with no Listora code. Registration is the login page's job.

## Steps

### 1. Nothing prints under the footer
- **Action**: logged out, open the home page, a blog post and `$SITE_URL/listing/{listing}/` at 1440 and 390.
- **Expect**: no `#listora-login-modal` in the DOM on any of them; nothing after the footer.

### 2. Save goes to the login page and returns
- **Action**: on the listing page click Save to favorites.
- **Expect**: the browser lands on the login page with `redirect_to` set to the listing URL; after logging in it returns to the listing.

### 3. The address can be changed
- **Action**: `add_filter( 'wb_listora_login_url', fn () => home_url( '/sign-in/' ) );` then click Save.
- **Expect**: lands on `/sign-in/` with `redirect_to` set.

### 4. BuddyNext
- **Action**: with BuddyNext active, click Save as a guest.
- **Expect**: lands on the BuddyNext login screen with the page carried.
