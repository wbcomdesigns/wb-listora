---
journey: submission-dashboard-url-registry
plugin: wb-listora
priority: normal
roles: [member, logged-out, admin]
covers: [submission-success-card, dashboard-url-resolution, page-registry, no-hardcoded-slug]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A member who can submit listings (SUBMITTER_LOGIN, e.g. QA-J1-member)"
  - "The Dashboard page is mapped in the page registry and can be re-slugged (here page id 7, slug `my-listings`)"
estimated_runtime_minutes: 5
covers_card: null
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Submission success card "Go to Dashboard" uses the registered Dashboard URL, never a hardcoded slug

The success card in `templates/blocks/listing-submission/submission.php` links "Go to Dashboard" (new-listing branch line 127, edit branch line 116) through `wb_listora_get_dashboard_url()` (`includes/class-template-helpers.php:269`): the registry-resolved URL of the page mapped to `dashboard`, falling back to `wb_listora_default_page_url( 'dashboard' )` (registry default `/my-dashboard/`), then the `wb_listora_dashboard_url` filter. 2.0.0: the `dashboard_slug` setting is no longer read; only the mapped page, the default and the filter matter.

## Setup
- `DASH_ID = wb_listora_get_page_id( 'dashboard' )`; note its `post_name` (this site: `my-listings`, URL `http://wb-listora.local/my-listings/`).
- Capture `EXPECTED_URL = wb_listora_get_dashboard_url()` via `wp eval`.

## Steps

### 1. No hardcoded slug in the template
- **Action**: `grep -n "home_url( '/dashboard/' )\|wb_listora_get_dashboard_url" templates/blocks/listing-submission/submission.php`
- **Expect**: two `wb_listora_get_dashboard_url()` hrefs; zero `home_url( '/dashboard/' )`.

### 2. Re-slug the Dashboard page, helper follows
- **Action**: `wp_update_post( array( 'ID' => DASH_ID, 'post_name' => 'qa-j1-member-hub' ) )`; `wp eval 'echo wb_listora_get_dashboard_url();'`.
- **Expect**: `.../qa-j1-member-hub/` (verified 2026-10-12). `wb_listora_default_page_url( 'dashboard' )` still prints `.../my-dashboard/` (registry default, unaffected).
- **Positive control**: restore the slug and the helper returns the original URL again.

### 3. Success card (new listing)
- **Action**: as SUBMITTER_LOGIN complete the wizard to the success card (or at 390 un-hide `.listora-submission__success`).
- **Expect**: inside `.listora-submission__success-actions` the primary `a.listora-btn--primary` "Go to Dashboard" has `href` equal to `EXPECTED_URL`; the secondary is "Add Another Listing". Clicking lands on the dashboard with HTTP 200.
```js
[...document.querySelectorAll('.listora-submission__success-actions a')].map(a => [a.textContent.trim(), a.getAttribute('href')])
```
- On the draft-submit path (dashboard "Submit Listing") the heading swaps to "Listing Submitted!" via `data-listora-submitted-title`.

### 4. Edit-listing success card
- **Action**: Dashboard > Edit on a listing, change the title, **Update Listing**.
- **Expect**: heading "Listing Updated!"; buttons "Go to Dashboard" (same `EXPECTED_URL`) and "View Listing" (the listing permalink).

### 5. Role ladder
Member: steps 3-4. Another member cannot reach another member's edit URL (404/403). Logged out: `/add-listing/` shows the sign-in prompt, no card. Admin: same card (control).

### 6. 390px
Success card width <= 520px, centered, the two buttons stacked at full width, >= 40px tall (verified 267x44 on 2026-10-12 with the card force-shown), no horizontal scroll.

## Cleanup
Restore `post_name` to the original; `wp rewrite flush`; delete the QA listing.

## Fail diagnostics
| Symptom | File |
|---|---|
| Button 404s on a re-slugged site | `templates/blocks/listing-submission/submission.php:116` / `:127` |
| Helper ignores the mapped page | `includes/class-template-helpers.php:269-282` |
| Default slug wrong | `includes/class-template-helpers.php:215` `wb_listora_default_page_url()` |

## Not verified
Steps 3-4 clicking through a real submission were not run; the card markup, the two hrefs' source and the 390 geometry were read from source / a force-shown card. Step 2 was run via wp-cli and restored.

Verified via: wp-cli, Playwright MCP (force-shown card at 390).
