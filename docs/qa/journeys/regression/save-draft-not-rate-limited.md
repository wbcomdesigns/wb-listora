---
journey: save-draft-not-rate-limited
plugin: wb-listora
priority: high
roles: [subscriber]
covers: [listing-submission, save-draft, rate-limiter]
prerequisites:
  - "Site reachable at $SITE_URL"
estimated_runtime_minutes: 5
covers_card: 10332281244, 10332258844
---

# Save Draft is not rate-limited and says why it failed

Regression sentinel for the second 1.8.0 QA bounce.

## Background

The submission rate limit (10 per hour per IP) ran before /submit routed an owner's edit to update_listing(), so every Save Draft, autosave and edit counted as a new listing; the tenth save of one draft returned 429. Save Draft's catch then reset the button silently. The check now runs after the edit routing (new listings only, matching PUT /submit/{id} which was never limited), Save Draft reports failures in the form's error box, and the button is hidden on the Type step, which has nothing to save.

## Steps

### 1. Re-save one draft many times
- **Action**: as a member, create a draft, then press Save Draft 12 times.
- **Expect**: every save succeeds; no 429.

### 2. New listings are still limited
- **Action**: create 11 new listings from one IP within the hour.
- **Expect**: the 10th+ returns 429 `listora_rate_limit`.

### 3. A failed save says so
- **Action**: clear the title and press Save Draft.
- **Expect**: the form's error box shows "Title is required."; a later successful save hides it.

### 4. Not on the Type step
- **Action**: open Add Listing with no type preselected.
- **Expect**: Save Draft hidden on the Type step, shown from Basic Info on.
