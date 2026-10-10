---
journey: uninstall-removes-every-status
plugin: wb-listora
priority: high
roles: [admin]
covers: [uninstall-custom-statuses]
prerequisites:
  - "Site reachable at $SITE_URL"
estimated_runtime_minutes: 5
---

# Uninstall removes listings in every status

Uninstall fetched listings with `post_status => 'any'`, which skips statuses registered with exclude_from_search - all of Listora's - so expired, rejected, deactivated and awaiting-payment listings and their meta outlived the plugin (31 found on listora.local).

## Steps

### 1. On a scratch site only (the Docker bench, never a site with data you need)
- **Action**: activate Free; create one listing each in publish, pending, listora_expired, listora_rejected, listora_deactivated, listora_payment, each with `_listora_phone`; set `delete_on_uninstall`; `wp plugin deactivate wb-listora && wp plugin uninstall wb-listora`.
- **Expect**: 0 `listora_listing` rows and 0 `_listora_%` postmeta rows; 0 options matching `wbcom_credits_%` for this consumer (`%_listora`, `%wb-listora%`), so a reinstall recreates the credit tables.
- **On fail**: `uninstall.php` listing loop.

## Pass criteria

ALL of the following hold:
1. No listing of any status survives uninstall when data deletion is on.
