---
journey: dashboard-no-empty-more-menu
plugin: wb-listora
priority: normal
roles: [subscriber]
covers: [user-dashboard, listing-actions]
prerequisites:
  - "Site reachable at $SITE_URL"
estimated_runtime_minutes: 5
covers_card: 10332121171
---

# Dashboard rows show More only when it has actions

Regression sentinel for the second 1.8.0 QA bounce.

## Background

Pending and awaiting-credits rows rendered the More (three-dot) button with an empty menu, which read as an action that did nothing. The menu now renders only when it holds Renew, Deactivate or Reactivate.

## Steps

### 1. Pending row
- **Expect**: no More button.

### 2. Published row
- **Expect**: More button with Deactivate.

### 3. Deactivated / renewable rows
- **Expect**: More with Reactivate / Renew.
