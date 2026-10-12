---
journey: submission-dashboard-url-registry
group: submission
actor: customer
mode: mixed
plugin: wb-listora
priority: normal
roles: [owner, other, admin, guest]
covers: [submission-success-card, dashboard-url-resolution, page-registry, no-hardcoded-slug]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "{owner} can submit listings"
  - "The Dashboard page is mapped in the page registry and can be re-slugged"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unbridged theme]
site_profile: required
supersedes:
  - ora:regression/submission-dashboard-url-registry.md
---

# The submission success card "Go to Dashboard" uses the registered Dashboard URL, never a hardcoded slug

## Why
A site that re-slugged or remapped its Dashboard page got a 404 from the success card button. `templates/blocks/listing-submission/submission.php` links "Go to Dashboard" (new-listing branch line 127, edit branch line 116) through `wb_listora_get_dashboard_url()` (`includes/class-template-helpers.php:269`): the registry-resolved URL of the page mapped to `dashboard`, falling back to `wb_listora_default_page_url( 'dashboard' )` (registry default `/my-dashboard/`), then the `wb_listora_dashboard_url` filter. 2.0.0: the `dashboard_slug` setting is no longer read.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- `DASH_ID = wb_listora_get_page_id( 'dashboard' )`; note its `post_name`.
- `EXPECTED_URL = wb_listora_get_dashboard_url()` via `wp eval`.

## Steps

### 1. No hardcoded slug in the template
- **Action**: `grep -n "home_url( '/dashboard/' )\|wb_listora_get_dashboard_url" templates/blocks/listing-submission/submission.php`
- **Expect**: two `wb_listora_get_dashboard_url()` hrefs; zero `home_url( '/dashboard/' )`.
- **Read-back**: grep count.

### 2. Re-slug the Dashboard page, the helper follows
- **Action**: `wp_update_post( array( 'ID' => DASH_ID, 'post_name' => 'qa-j1-member-hub' ) )`; `wp eval 'echo wb_listora_get_dashboard_url();'`.
- **Expect**: `.../qa-j1-member-hub/`. `wb_listora_default_page_url( 'dashboard' )` still prints the registry default (`.../my-dashboard/`).
- **Read-back**: positive control: restore the slug and the helper returns the original URL again.

### 3. Success card (new listing)
- **Action**: as {owner} complete the wizard to the success card (or at 390 un-hide `.listora-submission__success`).
- **Expect**: in `.listora-submission__success-actions` the primary `a.listora-btn--primary` "Go to Dashboard" has `href` equal to `EXPECTED_URL` (and with the step-2 slug, the new URL); the secondary is "Add Another Listing". Clicking lands on the dashboard with HTTP 200. On the draft-submit path (dashboard "Submit Listing") the heading swaps to "Listing Submitted!" via `data-listora-submitted-title`.
- **Read-back**:
  ```js
  [...document.querySelectorAll('.listora-submission__success-actions a')].map(a => [a.textContent.trim(), a.getAttribute('href')])
  ```

### 4. Edit-listing success card
- **Action**: Dashboard > Edit on a listing, change the title, **Update Listing**.
- **Expect**: heading "Listing Updated!"; buttons "Go to Dashboard" (same `EXPECTED_URL`) and "View Listing" (the listing permalink).
- **Read-back**: stored title changed; both hrefs as above. The edit flow itself is owned by `17-edit-my-listing`.

### 5. Dashboard unmapped or page deleted
- **Action**: unmap or trash the Dashboard page; reload the success card.
- **Expect**: the button falls back to the registry default URL (never an empty `href` or `#`); filter `wb_listora_dashboard_url` can override.
- **Read-back**: `wb_listora_get_dashboard_url()` non-empty. Not verified live.

### 6. 390px
- **Action**: force-show the card at 390x844.
- **Expect**: width <= 520px, centered, the two buttons stacked at full width and >= 40px tall (measured 267x44), no horizontal scroll.
- **Read-back**: `scrollWidth <= innerWidth`; repeat in dark. Dark not verified live.

## Role ladder
| Rung | Expect |
|---|---|
| {owner} | steps 3-4 |
| {other} | cannot reach {owner}'s edit URL (404/403) |
| {guest} | `/add-listing/` shows the sign-in prompt, no card |
| {admin} | same card (control) |

## Pass criteria
1. The template carries no hardcoded dashboard slug.
2. The helper and both card hrefs follow the mapped page after a re-slug and revert after restoring.
3. Both cards link the Dashboard with HTTP 200; the 390 layout stacks at full width.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Button 404s on a re-slugged site | hardcoded slug | `templates/blocks/listing-submission/submission.php:116`, `:127` |
| Helper ignores the mapped page | resolver | `includes/class-template-helpers.php:269-282` |
| Default slug wrong | registry default | `includes/class-template-helpers.php:215` `wb_listora_default_page_url()` |

## Not verified
Steps 3-4 clicking through a real submission were not run; the card markup, hrefs' source and the 390 geometry were read from source / a force-shown card (Playwright). Step 2 ran via wp-cli and was restored.

## Cleanup
Restore `post_name`; `wp rewrite flush`; delete the QA listing.
