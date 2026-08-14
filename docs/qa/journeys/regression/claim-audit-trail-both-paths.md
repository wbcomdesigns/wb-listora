---
journey: claim-audit-trail-both-paths
plugin: wb-listora
roles: [admin, member]
priority: high
covers: [wb_listora_after_update_claim, wb_listora_after_submit_claim, audit-log-claim-rows, admin-claim-approval, BC-10199419982]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "wb-listora AND wb-listora-pro both active (the audit log is Pro)"
  - "A published listing and a member who can claim it"
estimated_runtime_minutes: 5
---

# The claim audit trail records the right listing, on both approval paths

Two defects, one surface. The audit trail is a compliance record, so "mostly right" is not a state
it is allowed to be in.

**Wrong listing.** Free fires `wb_listora_after_submit_claim( $claim_id, $listing_id, $request )` —
scalars. Pro's `Audit_Log::on_claim_submitted` expected `( $claim_id, $data )` and read
`$data['listing_id']`, an array offset on an int. Every `claim_submitted` row was written with
`listing_id: 0` and an empty title. `on_claim_updated` had the matching bug against a status
*string*, so it logged `claim_updated` even for an approval.

**Missing event.** The admin Claims page called `apply_approval_side_effects()` directly and never
fired `wb_listora_after_update_claim` — only the REST `update_claim()` did. So an admin approving a
claim produced **no audit row at all**: the trail showed a claim submitted, then nothing.

The reason this survived so long is that the visible half worked. The claim-approved *email* fires
off the separate `wb_listora_claim_approved` inside `apply_approval_side_effects()`, so the
customer-facing behaviour looked correct while the record did not exist.

> Free's own `Suite_Notifications` and Pro's `Outgoing_Webhooks` always consumed the real signature.
> `Audit_Log` was the only wrong consumer — so "the hook is broken" was never the right conclusion.

## Steps

### 1 — Submitted claim records the listing it targets

Have a member submit a claim on a published listing (frontend claim modal, or
`POST /wp-json/listora/v1/listings/{id}/claim`).

```bash
wp eval '
global $wpdb; $t = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . "audit_log";
$r = $wpdb->get_row( "SELECT action, details FROM $t WHERE object_type=\"claim\" ORDER BY id DESC LIMIT 1", ARRAY_A );
echo $r["action"] . " :: " . $r["details"] . "\n";'
```

- **Expect** `claim_submitted :: {"listing_id":<real id>,"listing_title":"<real title>",...}`.
- `"listing_id":0` with an empty title is the regression.

### 2 — Admin approval writes an audit row

Approve that claim from **wp-admin → Listora → Claims → Approve** (not REST — the admin path is the
one that was silent).

- **Expect** a new `claim_approved` row whose details carry the real `listing_id`, real
  `listing_title`, and `"status":"approved"`.
- **No new row at all** is the original regression.
- A row reading `claim_updated` instead of `claim_approved` is the signature regression.

### 3 — Bulk approval behaves identically

Repeat with the bulk **Approve** action on the Claims page. Same assertions — the bulk path had the
same gap and must not drift from the single-row path again.

### 4 — Rejection, both paths

Reject a claim from the single-row action and from bulk. **Expect** a `claim_rejected` row each
time, alongside the existing `wb_listora_claim_rejected` fire.

### 5 — Pro's webhooks see admin approvals too

With an outgoing webhook configured for `claim_approved`, approve from wp-admin.

- **Expect** a delivery. Pro's `Outgoing_Webhooks::on_claim_updated` listens on the same hook, so it
  missed every admin approval for the same reason. A delivery on REST but not on admin means the
  hook is being fired from only one path again.

## Cleanup

Delete the probe claim, its audit rows, and clear `_listora_is_claimed` on the listing — approval
transfers ownership and sets that flag.
