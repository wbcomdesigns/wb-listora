---
journey: dashboard-claims-tab
plugin: wb-listora
priority: normal
roles: [member]
covers: [user-dashboard, claims-tab, claim-status-display]
prerequisites:
  - "Test member with at least 2 claim records (1 approved, 1 pending) in listora_claims"
estimated_runtime_minutes: 3
---

# Member sees their own claim history on the Claims tab

Verifies the Claims tab on the member dashboard renders only the current user's claims with correct status badges and that the REST list is permission-scoped.

## Setup

- Site: `$SITE_URL`
- Test member: `member1`; capture `USER_ID`
- Seed:
  ```sql
  INSERT INTO wp_listora_claims (user_id, listing_id, status, message, created_at)
  VALUES ($USER_ID, 1, 'pending', 'I own this', NOW() - INTERVAL 2 DAY),
         ($USER_ID, 2, 'approved', 'Mine too', NOW() - INTERVAL 5 DAY);
  ```

## Steps

### 1. Open Claims tab
- **Action**: `playwright_navigate $SITE_URL/dashboard/#claims?autologin=member1`
- **Expect**: tab active; table or card list visible

### 2. Verify 2 claim rows render
- **Action**: `browser_evaluate "document.querySelectorAll('.listora-dashboard__claim-row').length"`
- **Expect**: 2

### 3. Status badges correct
- **Action**: `browser_evaluate "Array.from(document.querySelectorAll('.listora-dashboard__claim-row')).map(r => r.querySelector('.listora-badge')?.textContent.trim())"`
- **Expect**: includes "Under review" + "Approved" (the pending-state label is "Under review", not "Pending"; case-insensitive)

### 4. Listing title links work
- **Action**: click the first claim's listing title
- **Expect**: navigates to the listing detail page

### 5. REST permission scope (data flow)
- **Action**:
  ```bash
  curl -s "$SITE_URL/wp-json/listora/v1/claims?mine=1" -H "X-WP-Nonce: $NONCE" --cookie "..." | jq '.items[] | .user_id' | sort -u
  ```
- **Expect**: only `$USER_ID` — never returns other members' claims

### 6. Cross-user access denied
- **Action**:
  ```bash
  curl -s "$SITE_URL/wp-json/listora/v1/claims?user_id=999" -H "X-WP-Nonce: $NONCE" --cookie "..."
  ```
- **Expect**: 403 OR results filtered to current user only. Non-admin cannot fetch other users' claims.

### 7. Empty state for new member
- **Action**: autologin as a brand-new member
- **Expect**: Claims tab shows empty-state card "You haven't claimed any businesses yet" with CTA to browse directory

### 8. Pending claim → withdraw action
- **Action**: on the pending claim, click "Withdraw"
- **Expect**: confirm modal (NOT native confirm); on confirm → `DELETE /wp-json/listora/v1/claims/<id>` returns 200; row disappears

### 9. Approved claim shows "Manage Listing" link
- **Action**: inspect approved claim row
- **Expect**: "Manage Listing" CTA visible; links to /dashboard/#listings or to the listing edit page

### 10. Developer hook (filter prepare_claim)
- **Action**: `wp eval 'add_filter("wb_listora_rest_prepare_claim", function($c){ $c["custom_badge"] = "VIP"; return $c; });'` reload
- **Expect**: REST response items include `custom_badge: "VIP"`. Pro consumes this filter for premium claim flows.

## Pass criteria

1. Member sees only own claims (no cross-user leakage)
2. Status badges correct
3. Empty-state for no-claims members
4. Withdraw uses `listoraConfirm` modal not native confirm
5. `wb_listora_rest_prepare_claim` filter accepted

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Claims of other users visible | permission_callback doesn't scope to current_user | `class-claims-controller.php::permission_callback` |
| Native confirm fires | regression — `listoraConfirm` fallback restored | `src/interactivity/store.js` claim withdrawal action |
| Withdraw doesn't delete | DELETE route missing OR returns 401 | `class-claims-controller.php::register_routes` |
| Empty-state shows zeros not CTA | template empty-state condition wrong | `templates/blocks/user-dashboard/tab-claims.php` |
