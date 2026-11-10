---
journey: no-early-translation-notices
group: platform-lifecycle
actor: system
mode: cli
plugin: wb-listora
priority: high
site_profile: required
roles: [admin, guest, system]
covers: [translation-timing, plugin-bootstrap, settings-defaults, map-provider-registry, "10491206201"]
prerequisites:
  - "Free + Pro active on WordPress 6.7 or later; debug.log writable"
estimated_runtime_minutes: 5
last_verified: 2026-11-09
verified_against: free 2.1.1 + pro 2.1.1
viewports: []
themes: [reign]
---

# Neither plugin translates anything before `init`

## Why
WordPress 6.7 logs "Translation loading for the `wb-listora` (or `wb-listora-pro`) domain was triggered too early" when code calls `__()` before `init`. It repeats on every request, fills debug.log and shows on a site with WP_DEBUG on. Two causes shipped in 2.1.1 work and were fixed: Pro's map provider fields were built at boot, and Free's settings defaults built the tile preset list (translated labels) at boot.

## Steps
### 1. Command line
- **Action**: run `wp eval 'echo "ok";'` on the site with both plugins active.
- **Expect**: only `ok`. No line containing `_load_textdomain_just_in_time` or `triggered too early`.

### 2. Front end, REST and admin
- **Action**: note the size of `wp-content/debug.log`, then load `{directory}`, a listing, `/wp-json/listora/v1/settings/maps` and Settings > Maps once each, as admin.
- **Expect**: debug.log grew by 0 bytes, or any new line is not about translation timing for `wb-listora` or `wb-listora-pro`. The Maps tab still shows its labelled provider fields (translated text is fine after `init`).

### 3. Both plugins together and each alone
- **Action**: repeat step 1 with Pro deactivated, then with Free only.
- **Expect**: the same clean result.

## Pass criteria
Zero too-early translation notices for either text domain in steps 1-3.
