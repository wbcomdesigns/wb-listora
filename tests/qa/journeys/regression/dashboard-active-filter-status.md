---
journey: dashboard-active-filter-status
plugin: wb-listora
priority: high
roles: [member]
covers: ["#9962484094", "My Listings Active filter", "filter state derivation"]
prerequisites:
  - "Renewal feature toggle ON (the filter dropdown only renders then)"
  - "A user owning listings with mixed statuses: publish + listora_deactivated (or draft/pending)"
estimated_runtime_minutes: 2
---

# My Listings "Active" filter excludes non-published listings

Card #9962484094: the per-row filter state in `tab-listings.php` was derived
as `expired → expiring → (everything else = active)`, so deactivated, draft,
pending, rejected, and awaiting-credits listings all carried
`data-listora-state="active"` and surfaced under the Active filter.

Fix: `active` now additionally requires `post_status === 'publish'`; all
other non-expired/non-expiring statuses get `inactive`, which matches no
dropdown option except "All listings".

## Steps

### 1. Row states are honest
- **Action**: as the listing owner, open the dashboard My Listings tab; read
  each `.listora-dashboard__listing-row`'s `data-listora-state`.
- **Expect**: published+non-expiring rows = `active`; deactivated/draft/
  pending/rejected rows = `inactive`; expired = `expired`; in-window
  published = `expiring`.

### 2. Active filter hides inactive rows
- **Action**: select "Active" in the filter dropdown.
- **Expect**: only `state=active` rows remain visible — the deactivated
  listing disappears.

### 3. All listings shows everything
- **Action**: select "All listings".
- **Expect**: every row visible again, including `inactive` ones.
