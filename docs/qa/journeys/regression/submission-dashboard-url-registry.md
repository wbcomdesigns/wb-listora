---
journey: submission-dashboard-url-registry
plugin: wb-listora
priority: normal
roles: [subscriber]
covers: [submission-success-card, dashboard-url-resolution, page-registry, no-hardcoded-slug]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A contributor/subscriber who can submit listings (capture SUBMITTER_LOGIN)"
  - "The Dashboard page exists and can be re-slugged to a non-default URL"
estimated_runtime_minutes: 5
covers_card: null
covers_commit: f623a39
---

# Submission success card "Go to Dashboard" uses the registered Dashboard URL, never a hardcoded slug

Regression sentinel for UX-M2 (`f623a39`). The submission success card's "Go to
Dashboard" button hardcoded `home_url('/dashboard/')`. On a site whose Dashboard
page lives at a non-default slug, that link 404'd. The fix points both the
new-listing and edit-listing success buttons at `wb_listora_get_dashboard_url()`
(`templates/blocks/listing-submission/submission.php:131` and `:142`) — the
registry-resolved URL of the actual Dashboard page (registry default
`/my-dashboard/`).

## Setup

- Site: `$SITE_URL`; submitter = `SUBMITTER_LOGIN`.
- Move the Dashboard page to a non-default slug to expose the bug:
  ```
  wp eval "\$id = wb_listora_get_page_id('dashboard'); wp_update_post(['ID'=>\$id,'post_name'=>'member-hub']);"
  wp rewrite flush
  ```
  Capture `EXPECTED_URL` = output of `wp eval "echo wb_listora_get_dashboard_url();"` (should now end `/member-hub/`).

## Steps

### 1. Buttons resolve via the helper in code
- **Action**:
  ```
  grep -n "wb_listora_get_dashboard_url\|home_url( '/dashboard/' )\|home_url('/dashboard/')" templates/blocks/listing-submission/submission.php
  ```
- **Expect**: both "Go to Dashboard" hrefs use `esc_url( wb_listora_get_dashboard_url() )`. ZERO occurrences of `home_url('/dashboard/')`.
- **On fail**: `f623a39` — a hardcoded slug remains.

### 2. New-listing success card links to the registered URL
- **Action**: as `SUBMITTER_LOGIN`, complete the submission wizard for a NEW listing through to the success card.
- **Expect**: the "Go to Dashboard" button's `href` equals `EXPECTED_URL` (`.../member-hub/`), NOT `.../dashboard/`. Clicking it lands on the dashboard (HTTP 200), not a 404.
- **Verify**:
  ```js
  const a = [...document.querySelectorAll('a.listora-btn--primary')].find(x => /dashboard/i.test(x.textContent));
  a.getAttribute('href');   // expect to end with /member-hub/
  ```
- **On fail**: button uses the hardcoded slug.

### 3. Edit-listing success card too
- **Action**: edit an existing listing (dashboard → Edit → resubmit) through to its success card.
- **Expect**: the same — "Go to Dashboard" href equals `EXPECTED_URL`, 200 not 404. (The fix touches both branches at `:131` and `:142`.)
- **On fail**: only one branch was fixed.

### 4. Default-slug site still works
- **Action**: restore the Dashboard slug to the registry default; resubmit.
- **Expect**: the button resolves to the default dashboard URL and lands cleanly. The helper is correct in both configurations.

### Cleanup
- Restore the Dashboard page slug to its original value; `wp rewrite flush`; delete the QA listing.

## Notes
- `wb_listora_get_dashboard_url()` is the single registry-resolved accessor — any new "go to dashboard" affordance MUST use it, never `home_url('/dashboard/')`. Pairs with the page-registry journeys; the registry default slug is `/my-dashboard/`.
