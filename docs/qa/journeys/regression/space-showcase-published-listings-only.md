---
journey: space-showcase-published-listings-only
plugin: wb-listora
priority: normal
roles: [space-team, space-member, anonymous, admin]
covers: [space-listings, showcase]
prerequisites:
  - "BuddyNext active, or the space permission filters answered true for the run (wb_listora_user_can_moderate_space, wb_listora_user_can_view_space)"
  - "Throwaway space id, e.g. 987654 (no BuddyNext space needed when the filters are forced)"
estimated_runtime_minutes: 4
covers_card: null
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
changed: true
---

# Only published listings can be approved into a space showcase, and the total counts only those

Regression sentinel for `Space_Listings_Controller::approve()` and `Space_Listings_Model::approved_count()`. REST-only journey: there is no space UI in Free, so there is no theme or viewport axis (BuddyNext renders the showcase).

## Background

A space curator could "approve" any post id: another member's pending listing, a private attachment, a page. Nothing was displayed, but the row was stored, the approval hook fired, and the showcase `X-WP-Total` counted it.

## Setup

`wp eval-file` with `wp_set_current_user( 1 )`, then force the permission filters for the run:
`add_filter( 'wb_listora_user_can_moderate_space', '__return_true' ); add_filter( 'wb_listora_user_can_view_space', '__return_true' );`
Create three posts: a published `listora_listing` (PUB), a `pending` `listora_listing` (PEND), a published `page` (PAGE). Use `SPACE = 987654`. Prefix titles `QA-J1-`.

## Steps

### 1. Positive control: approve a published listing
- **Action**: `POST /listora/v1/spaces/{SPACE}/listings/{PUB}/approve` as the space team.
- **Expect**: HTTP 200 body `{"status":"approved"}`; one row for `(SPACE, PUB)` in `{prefix}listora_space_listings` with `status = approved`.

### 2. Approve a pending listing and a page id
- **Action**: same route with `{PEND}` then `{PAGE}`.
- **Expect**: HTTP 404, code `listora_not_found`, message "Listing not found."; no row stored for either id.

### 3. Showcase total counts only what renders
- **Action**: insert a row for `(SPACE2, PAGE)` with `status = approved` straight into the table (simulates a leftover), then `GET /listora/v1/spaces/{SPACE2}/listings`.
- **Expect**: HTTP 200, `X-WP-Total: 0`, empty array. Positive control: `GET /listora/v1/spaces/{SPACE}/listings` returns `X-WP-Total: 1` and one item (the published PUB).

### 4. Role ladder
| Actor | Call | Expect |
|---|---|---|
| Space team (filter true) | approve PUB | 200 |
| Space member / any member (moderate filter false, the default) | approve PUB | 403 `listora_forbidden` "Only the space team can do that." |
| Logged out | approve PUB | 403 `listora_forbidden` (same permission callback) |
| Site admin | approve PUB | admin is a control only; the gate is the space filter, not the role, so without the filter it is also 403 |

### 5. Cleanup
Delete the table rows for both space ids and the three QA-J1- posts.

## Pass criteria
1. Published listing approves (200), pending listing and page 404 with `listora_not_found`, nothing stored.
2. `X-WP-Total` equals the number of items rendered, even with a non-listing row in the table.

## Fail diagnostics
| Symptom | File |
|---|---|
| Pending listing or page approved (200) | `includes/rest/class-space-listings-controller.php:386` (`is_listing()` + `'publish' !== get_post_status()` guard) |
| Total larger than items | `includes/core/class-space-listings-model.php:240` `approved_count()` (INNER JOIN on posts, `post_status = 'publish'`) |
| Non-team caller gets 200 | `includes/rest/class-space-listings-controller.php:206` `can_moderate()` |

## Not verified
The 403 rows for non-team/logged-out are read from `can_moderate()`; the run proved 200/404/total only. Real BuddyNext spaces were not exercised (filters forced).

Verified via: wp-cli `rest_do_request` on wb-listora.local, 2026-10-12.
