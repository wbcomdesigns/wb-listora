---
journey: write-and-reply-to-a-review
plugin: wb-listora
priority: critical
roles: [subscriber, listing_owner]
covers: [reviews-flow, helpful-vote, owner-reply, dashboard-reply-form]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Dev auto-login mu-plugin installed"
  - "1 published listora_listing exists owned by user 'owner'"
  - "Test users 'reviewer' and 'owner' exist (subscriber + listing-owner roles)"
estimated_runtime_minutes: 6
---

# Write and reply to a review

A regular customer (`reviewer`) writes a review on a listing, taps the Helpful button, and submits. The listing's owner (`owner`) opens their dashboard's Reviews tab, replies inline, and verifies the reply renders on the public detail page. Verifies four discrete features that all regressed during the past week's bug-fix sprint:
- Review create POST + the IAPI review-list refresh
- The Helpful button on the detail-page Reviews tab (commit 253cef9)
- The dashboard inline-reply form wiring to `/reviews/{id}/reply` (commit e01486b)
- The hide-inner-submit-state-spans visual fix (commit 182f654)

## Setup

- Site: `$SITE_URL`
- Users: `reviewer` (subscriber, autologin via `?autologin=reviewer`); `owner` (autologin via `?autologin=owner`, owns the test listing)
- Fixture: 1 published `listora_listing` owned by `owner`. Capture `LISTING_ID`.
- DB clean:
  ```sql
  DELETE FROM wp_listora_reviews WHERE listing_id=$LISTING_ID;
  DELETE FROM wp_listora_review_votes WHERE listing_id=$LISTING_ID;
  ```

## Steps

### 1. Auto-login as reviewer
- **Action**: `playwright_navigate $SITE_URL/?autologin=reviewer`
- **Expect**: authenticated session
- **On fail**: dev-auto-login mu-plugin

### 2. Open the listing detail page
- **Action**: `playwright_navigate $SITE_URL/?p=$LISTING_ID`
- **Expect**: DOM contains `.wp-block-listora-listing-detail`; `state.activeModal === null`
- **Capture**: `LISTING_SLUG` ← from the canonical URL
- **On fail**: detail-page render, modal-getter regression

### 3. Click the Reviews tab
- **Action**: `playwright_click 'button.listora-detail__tab[data-tab="reviews"]'`
- **Expect**: `.listora-detail__tab-panel--reviews.is-active` visible; `state.activeTab === 'reviews'`
- **On fail**: `templates/blocks/listing-detail/tabs.php`, IAPI tab-switch action

### 4. Submit a 4-star review
- **Action**:
  - `playwright_click '.listora-reviews__rating-input [data-value="4"]'`
  - `playwright_fill_form 'textarea.listora-reviews__body' 'Great place, would visit again.'`
  - `playwright_click 'button.listora-reviews__submit'`
- **Expect**:
  - `apiFetch POST /wp-json/listora/v1/listings/$LISTING_ID/reviews` returns `200 OK` with `{ id: <int>, rating: 4, ... }`
  - The new review card appears at the top of the list with rating "4" and the body text
- **Capture**: `REVIEW_ID` ← from response or DOM `data-review-id`
- **On fail**: `Reviews_Controller::create_review`, `Reviews_Controller::permissions_check`

### 5. Verify DB write
- **Action**: `mysql_query "SELECT id, rating, status FROM wp_listora_reviews WHERE listing_id=$LISTING_ID AND user_id=(SELECT ID FROM wp_users WHERE user_login='reviewer')"`
- **Expect**: 1 row with rating=4
- **On fail**: `Reviews_Controller::create_review` insert path or moderation hook flipping status to spam

### 6. Tap the Helpful button on the new review
- **Action**: `playwright_click '.listora-review-card[data-review-id="$REVIEW_ID"] button.listora-review-card__helpful'`
- **Expect**:
  - `apiFetch POST /wp-json/listora/v1/reviews/$REVIEW_ID/helpful` returns `200 OK`
  - Button shows the new count (1) and gains `is-voted` class
- **On fail**: commit 253cef9 — Helpful button missing from Reviews tab template, or `Reviews_Controller::vote_helpful` handler

### 7. Verify helpful vote DB write
- **Action**: `mysql_query "SELECT COUNT(*) AS n FROM wp_listora_review_votes WHERE review_id=$REVIEW_ID AND user_id=(SELECT ID FROM wp_users WHERE user_login='reviewer')"`
- **Expect**: `n = 1`
- **On fail**: vote-helpful handler

### 8. Switch sessions — auto-login as owner
- **Action**: `playwright_navigate $SITE_URL/?autologin=owner`
- **Expect**: authenticated as `owner`

### 9. Open user dashboard, Reviews tab
- **Action**: `playwright_navigate $SITE_URL/dashboard?tab=reviews`
- **Expect**: DOM contains `.listora-dashboard__reviews-list` with at least 1 row whose `data-review-id="$REVIEW_ID"`
- **On fail**: `Dashboard_Controller::get_reviews`, dashboard reviews template

### 10. Click "Reply" on the reviewer's review (inline form)
- **Action**: `playwright_click '.listora-dashboard__reviews-list [data-review-id="$REVIEW_ID"] button.listora-dashboard__reply-btn'`
- **Expect**:
  - Inline `<form class="listora-dashboard__reply-form">` becomes visible (not in a modal)
  - The textarea has focus; submit button enabled (commit e01486b)
- **On fail**: `templates/blocks/user-dashboard/tab-reviews.php` (commit e01486b inline-form regression)

### 11. Type the reply and submit
- **Action**:
  - `playwright_fill_form 'form.listora-dashboard__reply-form textarea' 'Thank you for the kind words!'`
  - `playwright_click 'form.listora-dashboard__reply-form button[type=submit]'`
- **Expect**:
  - Submit button's inner state-spans (`<span class="listora-btn__label">`, `<span class="listora-btn__loading">`) hide/show via `is-hidden` class — never both visible at once (commit 182f654)
  - `apiFetch POST /wp-json/listora/v1/reviews/$REVIEW_ID/reply` returns `200 OK`
  - Form collapses; reply text appears under the original review row
- **On fail**: commit e01486b reply wiring, or commit 182f654 `is-hidden` regression on submit-state spans

### 12. Verify reply DB write
- **Action**: `mysql_query "SELECT owner_reply FROM wp_listora_reviews WHERE id=$REVIEW_ID"`
- **Expect**: `owner_reply = 'Thank you for the kind words!'`
- **On fail**: `Reviews_Controller::owner_reply` insert path

### 13. Verify reply renders on public detail page
- **Action**: `playwright_navigate $SITE_URL/?p=$LISTING_ID` and click Reviews tab
- **Expect**: review card for `$REVIEW_ID` shows owner-reply block with the reply text
- **On fail**: detail-page review-card template / `Reviews_Controller::get_listing_reviews` response shape

## Pass criteria

ALL of the following hold:
1. Reviewer can submit a 4-star review; row exists in `wp_listora_reviews`
2. Helpful button on detail-page Reviews tab fires and stores a vote (commit 253cef9)
3. Owner sees the review in dashboard with a working Reply button (commit e01486b)
4. Submit-state spans never double-render (commit 182f654)
5. Reply POST returns 200 and persists `owner_reply`
6. Public detail page shows the owner reply

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Helpful button absent on Reviews tab | template regression | `templates/blocks/listing-detail/tabs.php` (commit 253cef9) |
| Helpful click does nothing | data-wp-on--click not bound or store action missing | `src/interactivity/store.js`, `blocks/listing-detail/style.css` |
| Reply button opens a modal instead of inline form | regression of commit e01486b | `templates/blocks/user-dashboard/tab-reviews.php` |
| Submit button shows both label + spinner | `is-hidden` class regression | `blocks/user-dashboard/style.css` (commit 182f654) |
| Reply POST 403 | `Reviews_Controller::owner_reply` permission check | grep `owner_reply.*permission` in `class-reviews-controller.php` |
| Reply renders but disappears on page reload | `owner_reply` column not persisted | `Reviews_Controller::owner_reply`, `wp_listora_reviews` schema |
