---
journey: role-stripped-account-cannot-write
plugin: wb-listora
priority: critical
roles: [subscriber, anonymous]
covers: [member-suspension, rest-write-gate, card-10100523205]
prerequisites:
  - "journey_owner (owns listing 'QA owned listing') and journey_other personas exist - bin/qa-fixtures.sh"
  - "A listing with an approved review (The Golden Fork on the QA seed) - note a listing id and review id"
estimated_runtime_minutes: 6
---

# An account with its role removed cannot write - anywhere - and can still read and erase itself

Removing a user's role is WordPress's own moderation lever. A zero-capability account kept posting
reviews, favourites and reports (card 10100523205). The first fix added a `read` check inside
`wb_listora_require_logged_in()`: that blocked the reviewer routes, missed every owner/author-match
route (deactivate, reactivate, edit via PUT /submit, services, Pro needs and plan activation), and -
because the same callback guards reads and `DELETE /me` - blocked 11 GET routes and account erasure.

The check now lives in `Member_Suspension::is_write_blocked()` as a third blocked state
(`is_role_stripped()`), so the existing namespace-wide gate on `rest_request_before_callbacks`
applies it to every `listora/v1` write, Free and Pro, with the existing exemptions: reads are never
gated, `DELETE /me` always passes. Error code: `listora_account_restricted` (403).
Escape hatch: `add_filter( 'wb_listora_user_can_act', '__return_true' )`.

## Setup

```bash
wp user remove-role journey_other subscriber
wp user remove-role journey_owner subscriber
wp user create qa_zero qa_zero@example.test --role=subscriber --porcelain && wp user remove-role qa_zero subscriber
```

Get a REST nonce in the page: `await (await fetch('/wp-admin/admin-ajax.php?action=rest-nonce')).text()`.

## Steps

### 1. Reviewer writes blocked (journey_other, stripped)
- **Action**: POST `/listora/v1/favorites {listing_id}`, POST `/reviews/{review}/helpful`, POST `/listings/{id}/report {reason:"spam"}`
- **Expect**: each 403 `listora_account_restricted`; no rows written

### 2. Reads still work (journey_other, stripped)
- **Action**: GET `/listora/v1/favorites`, GET `/dashboard/stats`
- **Expect**: 200 (the first fix returned 403 here)

### 3. Owner routes blocked (journey_owner, stripped)
- **Action**: POST `/listings/{own id}/deactivate`, PUT `/submit/{own id} {title}`, POST `/listings/{own id}/services`
- **Expect**: each 403 `listora_account_restricted` (the first fix returned 200)

### 4. Erasure always allowed (qa_zero, stripped)
- **Action**: DELETE `/listora/v1/me {confirm:"DELETE"}`
- **Expect**: 200 `deleted: true` - the account is gone (the first fix returned 403)

### 5. Controls
- **Action**: restore journey_other's role; POST then DELETE `/favorites`
- **Expect**: 201 then 200. Logged out: POST `/favorites` → 401 `listora_unauthorized`

## Teardown
`wp user add-role journey_other subscriber`; `wp user add-role journey_owner subscriber`; delete qa_zero if step 4 was skipped. Reactivate listing 1152 if step 3 succeeded anywhere.
