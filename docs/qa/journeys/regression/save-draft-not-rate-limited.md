---
journey: save-draft-not-rate-limited
plugin: wb-listora
priority: high
roles: [subscriber, another-member, anonymous, administrator]
covers: [listing-submission, save-draft, rate-limiter]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A member login that can submit (author1 or qa_vendor_01) and a second member (contributor1)"
estimated_runtime_minutes: 5
covers_card: 10332281244, 10332258844
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Save Draft is not rate-limited and says why it failed

Regression sentinel for the second 1.8.0 QA bounce. Verified via: wp eval + `rest_do_request` as author1/contributor1/anonymous and a code read. The Save Draft button flow itself was NOT clicked in a browser (see Not verified).

## Background

`POST /listora/v1/submit` routes an owner's edit (a body carrying `listing_id`) to `update_listing()` BEFORE the rate-limit check, so Save Draft, autosave and edits never count. Only NEW listings are counted: `Rate_Limiter::check( 'submission' )` allows 10 per hour per IP (and 30 per hour per user), the 11th returns 429 `listora_rate_limit` with a `Retry-After` header (2.0.0: the wait no longer grows while a client keeps trying). Save Draft reports a failure in the form's error box (`.listora-submission__error p`).

## Steps

### 1. Re-save one draft many times (positive: it works)
- **Action**: as author1 on `$SITE_URL/add-listing/`, pick a type, enter a title `QA-J1-draft`, press **Save Draft** 12 times.
- **Expect**: each press shows "Saving..." then "✓ Saved"; no error box. First save is `POST /wp-json/listora/v1/submit` -> 201 `{"id":N,"status":"draft","message":"Draft saved."}`; later saves carry `listing_id=N` and return 200 `Your listing has been updated.`.
- **Verified**: via REST, 1 create + 12 re-saves, all 200.

### 2. New listings are still limited (positive control for the limiter)
- **Action**: as one member from one IP, create 11 NEW listings (`QA-J1-rl-*`, no `listing_id`) inside an hour.
- **Expect**: the 11th returns 429, code `listora_rate_limit`, `data.scope` `ip` (or `user` after 30), response header `Retry-After: <seconds>`. Re-trying inside the window does not extend `Retry-After`.
- **Cleanup**: delete the QA-J1-rl-* drafts; `wp transient delete --all` is NOT needed, the window expires in an hour (or `wp transient delete listora_rl_submission_ip_<hash>`).

### 3. A failed save says so
- **Action**: open Add Listing, pick a type, leave the title empty, press **Save Draft**.
- **Expect**: the form's error box (`.listora-submission__error`) shows "Title is required." (server code `listora_title_required`, 400); a later successful save hides it.
- **Note**: this only fires for a draft that has never been saved. Re-saving an existing draft with an emptied title returns 200 and keeps the old title silently (see PRODUCT-BUGS in the report).

### 4. Not on the Type step
- **Action**: open Add Listing with no type preselected.
- **Expect**: `.listora-submission__save-draft` is hidden (`hidden` + `is-hidden`) on the Type step, visible from Basic Info through Media, hidden again on the last (Preview) step. In single-form layout it is always visible.

### 5. Role ladder (draft N owned by author1)
| Actor | Action | Expect |
|---|---|---|
| author1 (owner) | re-save N | 200 |
| contributor1 (other member) | POST /submit with `listing_id=N` | 403 `listora_forbidden` |
| logged out | POST /submit with `listing_id=N` | 401 `listora_unauthorized` |
| administrator (control) | re-save N via wp-admin/dashboard | 200, edits the member's draft, no duplicate-listing warning |

## Pass criteria
1. 12 re-saves of one draft all succeed. 2. The 11th NEW listing from one IP is 429 with Retry-After. 3. Empty title on a never-saved draft shows "Title is required.". 4. Button hidden on Type and Preview steps. 5. Ladder as above.

## Fail diagnostics
| Symptom | File |
|---|---|
| 429 on re-save | rate check moved above the edit routing: `includes/rest/class-submission-controller.php:593` (edit routing is `:575-587`) |
| Button flips back silently | catch block in `src/blocks/listing-submission/view.js:429-465` (`saveDraft`) |
| Button visible on Type step | `updateNavButtons()` `src/blocks/listing-submission/view.js:1824-1866` |
| Limits wrong | `includes/class-rate-limiter.php:60-65` (`submission`) |

## Not verified
- Button text/visibility in a real browser at 1440/390 and on the three themes (the shared browser was occupied by another worker; only REST + source verified).
